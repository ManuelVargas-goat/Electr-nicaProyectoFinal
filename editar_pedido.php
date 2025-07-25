<?php
session_start();
include("config.php"); // Asegúrate de que este archivo contiene tu conexión PDO ($pdo)

// --- Constantes (para consistencia, aunque no todas se usen directamente aquí) ---
define('PEDIDO_ESTADO_ENTREGADO', 'entregado');
define('PEDIDO_ESTADO_CANCELADO', 'cancelado');
define('PEDIDO_ESTADO_PENDIENTE', 'pendiente');

define('ALERT_SUCCESS', 'success');
define('ALERT_DANGER', 'danger');
define('ALERT_WARNING', 'warning');
define('ALERT_INFO', 'info');

// Redirecciona al login si el usuario no ha iniciado sesión
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}

// Obtener el ID del pedido de la URL
$pedidoId = $_GET['id'] ?? null;
if (!$pedidoId || !is_numeric($pedidoId) || (int)$pedidoId <= 0) {
    // Si el ID del pedido no es válido, redirigir con un mensaje de error
    $_SESSION['modal_message'] = "Error: ID de pedido no válido para editar.";
    $_SESSION['modal_type'] = ALERT_DANGER;
    header("Location: gestion_existencias_pedidos.php");
    exit();
}
$pedidoId = (int)$pedidoId; // Asegurarse de que es un número entero

// Generar o verificar token CSRF para el formulario
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Inicializar mensaje de alerta (para posibles mensajes GET o POST fallidos antes de la redirección)
$mensaje = ''; 
$tipoMensaje = ''; 

// Si el formulario fue enviado (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Verificación CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['modal_message'] = "Error de seguridad: Solicitud no válida (CSRF). Intente nuevamente.";
        $_SESSION['modal_type'] = ALERT_DANGER;
        header("Location: gestion_existencias_pedidos.php");
        exit();
    }
    // Una vez usado, el token de un solo uso debe ser invalidado y regenerado
    unset($_SESSION['csrf_token']);
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); // Regenerar para el próximo formulario/recarga

    $proveedorIds = $_POST['proveedor_id'] ?? [];
    $cantidades = $_POST['cantidad'] ?? [];
    $productoIds = $_POST['producto_id'] ?? [];

    // Validar que los arrays no están vacíos y tienen el mismo tamaño
    if (empty($productoIds) || count($proveedorIds) !== count($cantidades) || count($productoIds) !== count($cantidades)) {
        $_SESSION['modal_message'] = "Error: Datos de productos incompletos o inconsistentes.";
        $_SESSION['modal_type'] = ALERT_DANGER;
        header("Location: gestion_existencias_pedidos.php");
        exit();
    }

    try {
        $pdo->beginTransaction();

        // Paso 1: Obtener el estado actual del pedido
        $stmt_estado_actual = $pdo->prepare("SELECT estado FROM pedido WHERE pedido_id = :pedido_id");
        $stmt_estado_actual->execute([':pedido_id' => $pedidoId]);
        $estado_actual = $stmt_estado_actual->fetchColumn();

        // Solo permitir la edición si el pedido está en estado PENDIENTE
        if ($estado_actual !== PEDIDO_ESTADO_PENDIENTE) {
            throw new Exception("No es posible modificar un pedido que no está en estado 'pendiente'. Estado actual: " . ucfirst($estado_actual));
        }

        // Paso 2: Eliminar los detalles existentes para este pedido
        $deleteStmt = $pdo->prepare("DELETE FROM detalle_pedido WHERE pedido_id = :pedido_id");
        $deleteStmt->execute([':pedido_id' => $pedidoId]);

        // Paso 3: Insertar los nuevos/modificados detalles del pedido
        // El precio_unidad es obtenido de la tabla 'producto' en el momento de la inserción
        $insertStmt = $pdo->prepare("INSERT INTO detalle_pedido 
            (pedido_id, producto_id, proveedor_id, cantidad, precio_unidad)
            VALUES (:pedido_id, :producto_id, :proveedor_id, :cantidad, 
                (SELECT precio FROM producto WHERE producto_id = :producto_id))");

        for ($i = 0; $i < count($productoIds); $i++) {
            // Filtrar y validar cada entrada para prevenir inyección SQL y datos incorrectos
            $currentProductId = filter_var($productoIds[$i], FILTER_VALIDATE_INT);
            $currentProveedorId = filter_var($proveedorIds[$i], FILTER_VALIDATE_INT);
            $currentCantidad = filter_var($cantidades[$i], FILTER_VALIDATE_INT);

            // Asegurarse de que los valores son válidos antes de usarlos
            if ($currentProductId === false || $currentProductId <= 0 ||
                $currentProveedorId === false || $currentProveedorId <= 0 ||
                $currentCantidad === false || $currentCantidad <= 0) {
                // Si algún dato es inválido, abortar la transacción y lanzar una excepción
                throw new Exception("Datos de producto, proveedor o cantidad inválidos para un ítem del pedido.");
            }

            // Verificar si el producto y el proveedor existen y están relacionados
            $stmt_check_relacion = $pdo->prepare("SELECT COUNT(*) FROM proveedor_producto WHERE proveedor_id = :proveedor_id AND producto_id = :producto_id");
            $stmt_check_relacion->execute([':proveedor_id' => $currentProveedorId, ':producto_id' => $currentProductId]);
            if ($stmt_check_relacion->fetchColumn() == 0) {
                throw new Exception("El producto (ID: {$currentProductId}) no está asociado al proveedor (ID: {$currentProveedorId}).");
            }

            $insertStmt->execute([
                ':pedido_id' => $pedidoId,
                ':producto_id' => $currentProductId,
                ':proveedor_id' => $currentProveedorId,
                ':cantidad' => $currentCantidad
            ]);
        }

        $pdo->commit();
        // Definir el mensaje de éxito en la sesión antes de redireccionar
        $_SESSION['modal_message'] = "Pedido #" . htmlspecialchars($pedidoId) . " actualizado correctamente.";
        $_SESSION['modal_type'] = ALERT_SUCCESS;
        header("Location: gestion_existencias_pedidos.php");
        exit();

    } catch (Exception $e) {
        $pdo->rollBack();
        // Definir el mensaje de error en la sesión antes de redireccionar
        $_SESSION['modal_message'] = "Error al actualizar el pedido: " . $e->getMessage();
        $_SESSION['modal_type'] = ALERT_DANGER;
        // Opcional: registrar el error para depuración
        error_log("Error en editar_pedido.php: " . $e->getMessage());
        header("Location: gestion_existencias_pedidos.php"); // Redirigir de vuelta incluso en caso de error
        exit();
    }
}

// --- Lógica para cargar los datos del pedido para mostrar en el formulario (GET request) ---
$productosPedido = [];
$proveedores = [];

try {
    // Obtener los detalles del pedido
    $sql_productos_pedido = "
        SELECT dp.producto_id, p.nombre AS producto, dp.cantidad, p.precio AS precio_unitario_producto, 
               dp.precio_unidad AS precio_unitario_detalle, pr.proveedor_id, pr.nombre_empresa
        FROM detalle_pedido dp
        JOIN producto p ON dp.producto_id = p.producto_id
        JOIN proveedor pr ON dp.proveedor_id = pr.proveedor_id
        WHERE dp.pedido_id = :pedido_id
    ";
    $stmt_productos_pedido = $pdo->prepare($sql_productos_pedido);
    $stmt_productos_pedido->execute([':pedido_id' => $pedidoId]);
    $productosPedido = $stmt_productos_pedido->fetchAll(PDO::FETCH_ASSOC);

    // Si no se encuentran productos para el pedido, puede ser un ID de pedido incorrecto o sin detalles
    if (empty($productosPedido)) {
        $_SESSION['modal_message'] = "No se encontraron detalles para el pedido #" . htmlspecialchars($pedidoId) . ".";
        $_SESSION['modal_type'] = ALERT_WARNING;
        header("Location: gestion_existencias_pedidos.php");
        exit();
    }

    // Obtener todos los proveedores para la caja de selección
    $proveedores = $pdo->query("SELECT proveedor_id, nombre_empresa FROM proveedor ORDER BY nombre_empresa")->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Error al cargar datos del pedido para edición: " . $e->getMessage());
    $_SESSION['modal_message'] = "Error al cargar los detalles del pedido: " . $e->getMessage();
    $_SESSION['modal_type'] = ALERT_DANGER;
    header("Location: gestion_existencias_pedidos.php");
    exit();
}

// Obtener datos del usuario actual (para el encabezado, si es necesario, aunque la barra lateral sea eliminada)
$usuario_actual = $_SESSION['usuario'];
$sql_usuario = "SELECT per.nombre, per.apellido_paterno, r.nombre AS rol
                FROM usuario_empleado ue
                JOIN persona per ON ue.persona_id = per.persona_id
                JOIN rol r ON ue.rol_id = r.rol_id
                WHERE ue.usuario = :usuario";
$stmt_usuario = $pdo->prepare($sql_usuario);
$stmt_usuario->execute([':usuario' => $usuario_actual]);
$datosUsuario = $stmt_usuario->fetch(PDO::FETCH_ASSOC);

$nombreCompleto = 'Usuario Desconocido';
$rolUsuario = '';
if ($datosUsuario) {
    $nombreCompleto = htmlspecialchars($datosUsuario['nombre'] . ' ' . $datosUsuario['apellido_paterno']);
    $rolUsuario = strtolower(htmlspecialchars($datosUsuario['rol']));
}

// Variable para el nombre del archivo actual (sin la extensión .php)
$currentPage = basename($_SERVER['PHP_SELF'], ".php");
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Editar Pedido</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="css/estilos.css?v=<?= time(); ?>">
  <style>
    /* Estilos adicionales si son necesarios, o usar un archivo CSS externo */
    body {
        background-color: #f8f9fa; /* Un gris muy claro */
    }
    .container {
        max-width: 900px; /* Ancho máximo para el formulario */
    }
    .card {
        border: 1px solid #dee2e6; /* Borde sutil para cada línea de producto */
        background-color: #ffffff;
    }
    /* Estilos de la barra lateral eliminados */
  </style>
</head>
<body class="bg-light">
<div class="container-fluid">
    <div class="row">
        <!-- La barra lateral fue eliminada de aquí -->
        <div class="col-md-12 content"> <!-- Cambiado a col-md-12 para ocupar todo el ancho -->
            <h3 class="mb-4">Editar Pedido #<?= htmlspecialchars($pedidoId) ?></h3>

            <?php 
                // Mostrar mensaje de alerta, si hay alguno (por ejemplo, de un ID GET inválido o falla POST)
                if (!empty($mensaje) && !empty($tipoMensaje)) {
                    $alertClass = '';
                    switch ($tipoMensaje) {
                        case ALERT_SUCCESS: $alertClass = 'alert-success'; break;
                        case ALERT_DANGER:  $alertClass = 'alert-danger';  break;
                        case ALERT_WARNING: $alertClass = 'alert-warning'; break;
                        case ALERT_INFO:    $alertClass = 'alert-info';    break;
                        default:            $alertClass = 'alert-info';
                    }
                    echo '<div class="alert ' . $alertClass . ' alert-dismissible fade show mt-3" role="alert">';
                    echo htmlspecialchars($mensaje);
                    echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
                    echo '</div>';
                }
            ?>

            <form method="POST" class="bg-white border rounded shadow p-4" id="form-edicion">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

                <div id="productos-container">
                    <?php if (!empty($productosPedido)): ?>
                        <?php foreach ($productosPedido as $index => $item): ?>
                            <div class="card mb-3 p-3 producto-linea">
                                <div class="row g-2 align-items-end">
                                    <div class="col-md-3">
                                        <label class="form-label">Producto</label>
                                        <input type="text" class="form-control" value="<?= htmlspecialchars($item['producto']) ?>" readonly>
                                        <input type="hidden" name="producto_id[]" value="<?= htmlspecialchars($item['producto_id']) ?>">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Proveedor</label>
                                        <select name="proveedor_id[]" class="form-select" required>
                                            <?php foreach ($proveedores as $prov): ?>
                                                <option value="<?= htmlspecialchars($prov['proveedor_id']) ?>" <?= $item['proveedor_id'] == $prov['proveedor_id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($prov['nombre_empresa']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Cantidad</label>
                                        <input type="number" name="cantidad[]" class="form-control cantidad-input" value="<?= htmlspecialchars($item['cantidad']) ?>" min="1" required>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Precio Unitario (S/)</label>
                                        <!-- Usar el precio_unitario del detalle_pedido si existe, caso contrario el del producto -->
                                        <input type="text" class="form-control precio-unitario" value="<?= number_format(empty($item['precio_unitario_detalle']) ? $item['precio_unitario_producto'] : $item['precio_unitario_detalle'], 2, '.', '') ?>" readonly>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Subtotal (S/)</label>
                                        <input type="text" class="form-control subtotal-linea" readonly>
                                    </div>
                                    <div class="col-md-1 text-end">
                                        <button type="button" class="btn btn-danger btn-sm eliminar-linea" title="Eliminar este producto del pedido">✕</button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="alert alert-warning">No se encontraron productos para este pedido.</div>
                    <?php endif; ?>
                </div>

                <div class="mb-3 text-end">
                    <label class="form-label fw-bold">Total General del Pedido (S/)</label>
                    <input type="text" id="total-general" class="form-control text-end" readonly>
                </div>

                <div class="d-flex justify-content-between">
                    <a href="gestion_existencias_pedidos.php" class="btn btn-secondary">Volver</a>
                    <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Función para recalcular los subtotales de línea y el total general
    function recalcularTotales() {
        let total = 0;

        document.querySelectorAll('.producto-linea').forEach(linea => {
            const cantidadInput = linea.querySelector('.cantidad-input');
            const precioInput = linea.querySelector('.precio-unitario');
            const subtotalInput = linea.querySelector('.subtotal-linea');

            if (cantidadInput && precioInput && subtotalInput) {
                const cantidad = parseFloat(cantidadInput.value || 0);
                const precio = parseFloat(precioInput.value || 0); 
                const subtotal = cantidad * precio;

                subtotalInput.value = subtotal.toFixed(2);
                total += subtotal;
            }
        });

        document.getElementById('total-general').value = total.toFixed(2);
    }

    document.addEventListener('DOMContentLoaded', () => {
        // Event listeners para los inputs de cantidad
        document.querySelectorAll('.cantidad-input').forEach(input => {
            input.addEventListener('input', recalcularTotales);
        });

        // Event listeners para los botones de eliminar (delegación de eventos)
        document.getElementById('productos-container').addEventListener('click', function(event) {
            if (event.target.classList.contains('eliminar-linea')) {
                const card = event.target.closest('.producto-linea');
                if (card) {
                    card.remove();
                    recalcularTotales(); // Recalcular después de eliminar una línea
                }
            }
        });

        // Los scripts de la barra lateral fueron eliminados

        // Script para evitar que los dropdowns se cierren al hacer clic dentro (mantenido para dropdowns generales)
        const dropdowns = document.querySelectorAll('.dropdown-toggle');
        dropdowns.forEach(btn => {
            btn.addEventListener('click', e => e.stopPropagation());
        });
        const menus = document.querySelectorAll('.dropdown-menu');
        menus.forEach(menu => {
            menu.addEventListener('click', e => e.stopPropagation());
        });

        // Recalcular totales al cargar la página inicialmente
        recalcularTotales();
    });
</script>
</body>
</html>
