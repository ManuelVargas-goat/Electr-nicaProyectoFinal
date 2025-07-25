<?php
session_start();
include("config.php"); // Asegúrate de que este archivo contenga tu conexión PDO ($pdo)

// Constantes
define('PEDIDO_ESTADO_ENTREGADO', 'entregado');
define('PEDIDO_ESTADO_CANCELADO', 'cancelado');
define('PEDIDO_ESTADO_PENDIENTE', 'pendiente');
define('ALERT_SUCCESS', 'success');
define('ALERT_DANGER', 'danger');
define('ALERT_WARNING', 'warning');
define('ALERT_INFO', 'info');

if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}

// Obtener datos del usuario empleado actual
$usuario = $_SESSION['usuario'];
$sqlEmpleado = "SELECT ue.usuario_empleado_id, p.nombre || ' ' || p.apellido_paterno AS nombre
                FROM usuario_empleado ue
                JOIN persona p ON ue.persona_id = p.persona_id
                JOIN rol r ON ue.rol_id = r.rol_id
                WHERE ue.usuario = :usuario";
$stmtEmpleado = $pdo->prepare($sqlEmpleado);
$stmtEmpleado->execute(['usuario' => $usuario]);
$empleadoActivo = $stmtEmpleado->fetch(PDO::FETCH_ASSOC);

$empleadoId = $empleadoActivo['usuario_empleado_id'];
$nombreEmpleado = $empleadoActivo['nombre'];

// Generar token CSRF para el formulario
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// --- Consulta de Pedidos (Simplificada para solo mostrar "entregados" sin texto adicional) ---
$sqlPedidos = "
    SELECT p.pedido_id, TO_CHAR(p.fecha_pedido, 'YYYY-MM-DD HH24:MI:SS') AS fecha_formateada,
           '' AS tipo_pedido_reingreso -- Siempre una cadena vacía, eliminando cualquier texto adicional
    FROM pedido p
    WHERE p.estado = 'entregado' -- Solo pedidos en estado 'entregado'
    ORDER BY p.pedido_id DESC
";
$pedidos = $pdo->query($sqlPedidos)->fetchAll(PDO::FETCH_ASSOC);


// --- Consulta de Relaciones (solo para pedidos "entregados") ---
// Esta consulta obtiene los detalles de los productos de pedidos entregados
// y calcula la cantidad ya devuelta a proveedor para cada producto/proveedor/pedido.
$sqlRelaciones = "
    SELECT
        dp.pedido_id,
        prod.producto_id,
        prod.nombre AS producto_nombre,
        dp.proveedor_id,
        prov.nombre_empresa,
        dp.cantidad AS cantidad_original_pedido,
        -- Suma las cantidades devueltas *para este producto y proveedor específicamente de devoluciones relacionadas con este pedido*
        COALESCE(SUM(ddet.cantidad) FILTER (WHERE dev.tipo = 'proveedor' AND dev.pedido_original_id = dp.pedido_id AND ddet.producto_id = dp.producto_id AND ddet.proveedor_id = dp.proveedor_id), 0) AS cantidad_devuelta_proveedor
    FROM detalle_pedido dp
    JOIN producto prod ON dp.producto_id = prod.producto_id
    JOIN proveedor prov ON dp.proveedor_id = prov.proveedor_id
    JOIN pedido p ON dp.pedido_id = p.pedido_id

    -- Unir a devolucion y detalle_devolucion para calcular las cantidades ya devueltas
    LEFT JOIN devolucion dev ON dev.pedido_original_id = dp.pedido_id
    LEFT JOIN detalle_devolucion ddet ON ddet.devolucion_id = dev.devolucion_id
                                     AND ddet.producto_id = dp.producto_id
                                     AND ddet.proveedor_id = dp.proveedor_id

    WHERE p.estado = 'entregado' -- Solo pedidos en estado 'entregado'
    GROUP BY dp.pedido_id, prod.producto_id, prod.nombre, dp.proveedor_id, prov.nombre_empresa, dp.cantidad
    ORDER BY dp.pedido_id DESC
";

$relaciones = $pdo->query($sqlRelaciones)->fetchAll(PDO::FETCH_ASSOC);

$mapa_relaciones = [];
foreach ($relaciones as $rel) {
    if (!isset($mapa_relaciones[$rel['pedido_id']])) {
        $mapa_relaciones[$rel['pedido_id']] = [];
    }
    // La cantidad disponible para devolver es la cantidad original menos lo ya devuelto a proveedor
    $rel['cantidad_disponible_para_devolver'] = $rel['cantidad_original_pedido'] - $rel['cantidad_devuelta_proveedor'];
    // Solo añadir si aún hay cantidad disponible para devolver
    if ($rel['cantidad_disponible_para_devolver'] > 0) {
        $mapa_relaciones[$rel['pedido_id']][$rel['producto_id']] = $rel;
    }
}


// Procesar formulario de registro de devolución
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verificación CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $csrf_token) {
        $_SESSION['modal_message'] = "Error de seguridad: Solicitud no válida.";
        $_SESSION['modal_type'] = ALERT_DANGER;
        header("Location: nueva_devolucion.php"); // Redirigir a la misma página para mostrar el error
        exit();
    }
    // Regenerar el token después de un uso exitoso
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); 

    $pedidoId = filter_var($_POST['pedido_id'], FILTER_VALIDATE_INT);
    $productoId = filter_var($_POST['producto_id'], FILTER_VALIDATE_INT);
    $cantidad = filter_var($_POST['cantidad'], FILTER_VALIDATE_INT);
    $motivo = htmlspecialchars(trim($_POST['motivo']));
    $reingresado = isset($_POST['reingresado_stock']); // Checkbox para generar pedido de reemplazo
    $proveedorId = filter_var($_POST['proveedor_id'], FILTER_VALIDATE_INT);

    // Validaciones básicas de entrada
    if (!$pedidoId || !$productoId || !$cantidad || empty($motivo) || !$proveedorId || $cantidad <= 0) {
        $_SESSION['modal_message'] = "Todos los campos son obligatorios y válidos.";
        $_SESSION['modal_type'] = ALERT_DANGER;
        header("Location: nueva_devolucion.php");
        exit();
    }

    // Validación de cantidad a devolver vs. cantidad disponible
    // Se usa el mapa_relaciones precalculado para evitar otra consulta a la DB
    $cantidad_original = $mapa_relaciones[$pedidoId][$productoId]['cantidad_original_pedido'] ?? 0;
    $cantidad_ya_devuelta = $mapa_relaciones[$pedidoId][$productoId]['cantidad_devuelta_proveedor'] ?? 0;
    $cantidad_disponible_devolver = $cantidad_original - $cantidad_ya_devuelta;

    if ($cantidad > $cantidad_disponible_devolver || $cantidad_disponible_devolver <= 0) {
        $_SESSION['modal_message'] = "Error: Cantidad a devolver no válida o excede la disponible ($cantidad_disponible_devolver). Revise las devoluciones previas.";
        $_SESSION['modal_type'] = ALERT_DANGER;
        header("Location: nueva_devolucion.php");
        exit();
    }

    try {
        $pdo->beginTransaction();

        // 1. Insertar en la tabla devolucion (tipo 'proveedor')
        // Se almacena el pedido_id original en pedido_original_id
        $sql = "INSERT INTO devolucion (fecha, tipo, usuario_empleado_id, observaciones, pedido_original_id)
                VALUES (CURRENT_TIMESTAMP, 'proveedor', :empleado_id, :motivo, :pedido_original_id)
                RETURNING devolucion_id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':empleado_id' => $empleadoId,
            ':motivo' => $motivo,
            ':pedido_original_id' => $pedidoId // Se almacena el ID del pedido del que se está haciendo la devolución
        ]);
        $devolucionId = $stmt->fetchColumn();

        // 2. Insertar en la tabla detalle_devolucion
        $sqlDetalle = "INSERT INTO detalle_devolucion (devolucion_id, producto_id, proveedor_id, cantidad, motivo, reingresado_stock)
                       VALUES (:devolucion_id, :producto_id, :proveedor_id, :cantidad, :motivo, :reingresado)";
        $stmt = $pdo->prepare($sqlDetalle);
        $stmt->execute([
            ':devolucion_id' => $devolucionId,
            ':producto_id' => $productoId,
            ':proveedor_id' => $proveedorId,
            ':cantidad' => $cantidad,
            ':motivo' => $motivo,
            ':reingresado' => 'false' // Para devoluciones a proveedor, no se "reingresa" stock
        ]);

        // 3. ¡ACTUALIZAR EL STOCK! (Decremento para devoluciones a proveedor)
        $sqlUpdateStock = "UPDATE stock SET cantidad = cantidad - :cantidad, fecha_ultima_entrada = NOW() WHERE producto_id = :producto_id";
        $stmtUpdateStock = $pdo->prepare($sqlUpdateStock);
        $stmtUpdateStock->execute([
            ':cantidad' => $cantidad,
            ':producto_id' => $productoId
        ]);

        // 4. Si se marcó "Reingresar al stock" (generar pedido de reemplazo), crear un nuevo pedido "interno"
        if ($reingresado) {
            $sqlPedido = "INSERT INTO pedido (fecha_pedido, estado, usuario_empleado_id, almacen_id)
                          VALUES (NOW(), 'pendiente', :empleado_id, NULL)
                          RETURNING pedido_id";
            $stmt = $pdo->prepare($sqlPedido);
            $stmt->execute([':empleado_id' => $empleadoId]);
            $pedidoIdNuevo = $stmt->fetchColumn();

            // Actualizar la devolución para vincularla con el nuevo pedido de reingreso
            $sqlUpdateDevolucion = "UPDATE devolucion SET pedido_reingreso_id = :pedido_reingreso_id WHERE devolucion_id = :devolucion_id";
            $stmtUpdateDevolucion = $pdo->prepare($sqlUpdateDevolucion);
            $stmtUpdateDevolucion->execute([
                ':pedido_reingreso_id' => $pedidoIdNuevo,
                ':devolucion_id' => $devolucionId
            ]);

            // Obtener el precio actual del producto para el detalle del nuevo pedido
            $sqlGetPrecio = "SELECT precio FROM producto WHERE producto_id = :producto_id";
            $stmtGetPrecio = $pdo->prepare($sqlGetPrecio);
            $stmtGetPrecio->execute([':producto_id' => $productoId]);
            $precioUnitario = $stmtGetPrecio->fetchColumn();

            // Insertar el detalle para el nuevo pedido de reemplazo
            $sqlDetallePedido = "INSERT INTO detalle_pedido (pedido_id, producto_id, proveedor_id, cantidad, precio_unidad)
                                 VALUES (:pedido_id, :producto_id, :proveedor_id, :cantidad, :precio_unidad)";
            $stmt = $pdo->prepare($sqlDetallePedido);
            $stmt->execute([
                ':pedido_id' => $pedidoIdNuevo,
                ':producto_id' => $productoId,
                ':proveedor_id' => $proveedorId,
                ':cantidad' => $cantidad,
                ':precio_unidad' => $precioUnitario
            ]);
        }

        $pdo->commit();
        $_SESSION['modal_message'] = "Devolución registrada exitosamente.";
        $_SESSION['modal_type'] = ALERT_SUCCESS;
        header("Location: gestion_existencias_devoluciones.php");
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['modal_message'] = "Error al registrar devolución: " . $e->getMessage();
        $_SESSION['modal_type'] = ALERT_DANGER;
        error_log("Error al registrar devolución: " . $e->getMessage());
        header("Location: nueva_devolucion.php"); // Redirigir de vuelta al formulario con el error
        exit();
    }
}

$mensaje = '';
$tipoMensaje = '';
if (isset($_SESSION['modal_message']) && !empty($_SESSION['modal_message'])) {
    $mensaje = $_SESSION['modal_message'];
    $tipoMensaje = $_SESSION['modal_type'];
    unset($_SESSION['modal_message']);
    unset($_SESSION['modal_type']);
} elseif (isset($_GET['mensaje']) && !empty($_GET['mensaje'])) {
    $mensaje = $_GET['mensaje'];
    $tipoMensaje = $_GET['tipo'] ?? ALERT_INFO;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Nueva Devolución (Proveedor)</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-5">
    <h3 class="mb-4">Registrar Devolución a Proveedor</h3>
    <form method="POST" class="bg-white p-4 rounded shadow-sm">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

        <div class="mb-2">
            <label class="form-label">Empleado</label>
            <input type="text" class="form-control" value="<?= htmlspecialchars($nombreEmpleado) ?>" readonly>
        </div>

        <div class="mb-2">
            <label class="form-label">Pedido Entregado</label>
            <select name="pedido_id" id="pedido_id" class="form-select" required>
                <option value="">Seleccione un pedido</option>
                <?php foreach ($pedidos as $p): ?>
                    <option value="<?= $p['pedido_id'] ?>">#<?= $p['pedido_id'] ?> - <?= $p['fecha_formateada'] ?><?= htmlspecialchars($p['tipo_pedido_reingreso']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="mb-2">
            <label class="form-label">Producto</label>
            <select name="producto_id" id="producto_id" class="form-select" required disabled>
                <option value="">Seleccione un pedido primero</option>
            </select>
        </div>

        <div class="mb-2">
            <label class="form-label">Proveedor</label>
            <input type="text" id="proveedor_texto" class="form-control" readonly>
            <input type="hidden" name="proveedor_id" id="proveedor_id">
        </div>

        <div class="mb-2">
            <label class="form-label">Cantidad</label>
            <input type="number" name="cantidad" class="form-control" required min="1">
            <small class="form-text text-muted" id="cantidad_info"></small>
        </div>

        <div class="mb-2">
            <label class="form-label">Motivo</label>
            <textarea name="motivo" class="form-control" required></textarea>
        </div>

        <div class="form-check mb-3">
            <input type="checkbox" name="reingresado_stock" class="form-check-input" id="reingresado_stock">
            <label class="form-check-label" for="reingresado_stock">Generar pedido de reemplazo al proveedor</label>
        </div>

        <div class="d-flex justify-content-between">
            <a href="gestion_existencias_devoluciones.php" class="btn btn-secondary">Cancelar</a>
            <button type="submit" class="btn btn-primary">Registrar Devolución</button>
        </div>
    </form>

    <?php
    if (!empty($mensaje) && !empty($tipoMensaje)) {
        $alertClass = '';
        switch ($tipoMensaje) {
            case ALERT_SUCCESS: $alertClass = 'alert-success'; break;
            case ALERT_DANGER: $alertClass = 'alert-danger'; break;
            case ALERT_WARNING: $alertClass = 'alert-warning'; break;
            case ALERT_INFO: $alertClass = 'alert-info'; break;
            default: $alertClass = 'alert-info';
        }
        echo '<div class="alert ' . $alertClass . ' alert-dismissible fade show mt-3" role="alert">' . htmlspecialchars($mensaje) . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
    }
    ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
const mapaRelaciones = <?= json_encode($mapa_relaciones) ?>;
const pedidoSelect = document.getElementById('pedido_id');
const productoSelect = document.getElementById('producto_id');
const proveedorInput = document.getElementById('proveedor_id');
const proveedorTexto = document.getElementById('proveedor_texto');
const cantidadInput = document.querySelector('input[name="cantidad"]');
const cantidadInfo = document.getElementById('cantidad_info');

let cantidadDisponibleParaDevolver = 0;

pedidoSelect.addEventListener('change', () => {
    const pedidoId = parseInt(pedidoSelect.value);
    productoSelect.innerHTML = '<option value="">Seleccione un producto</option>';
    productoSelect.disabled = true; // Deshabilitar por defecto
    proveedorInput.value = '';
    proveedorTexto.value = '';
    cantidadInput.value = '';
    cantidadInput.removeAttribute('max');
    cantidadInput.setAttribute('placeholder', '');
    cantidadInfo.textContent = '';

    if (!pedidoId) return;

    const productosDeEstePedido = mapaRelaciones[pedidoId] || {};
    const productosArray = Object.values(productosDeEstePedido);

    console.log("Pedido seleccionado:", pedidoId);
    console.log("Productos para este pedido (desde mapaRelaciones):", productosDeEstePedido);
    console.log("Productos en array para iterar:", productosArray);


    productosArray.forEach(p => {
        const disponible = p.cantidad_original_pedido - p.cantidad_devuelta_proveedor;
        // Solo añadimos productos que tienen una cantidad disponible para devolver
        if (disponible > 0) {
            const opt = document.createElement('option');
            opt.value = p.producto_id;
            opt.textContent = `${p.producto_nombre} (Disponibles: ${disponible} de ${p.cantidad_original_pedido})`;
            opt.dataset.proveedorId = p.proveedor_id;
            opt.dataset.proveedorNombre = p.nombre_empresa;
            opt.dataset.maxCantidadDevolver = disponible;
            productoSelect.appendChild(opt);
        }
    });

    const productosFiltradosConDisponibilidad = productosArray.filter(p => (p.cantidad_original_pedido - p.cantidad_devuelta_proveedor) > 0);
    productoSelect.disabled = (productosFiltradosConDisponibilidad.length === 0);
    if (productoSelect.disabled) {
        productoSelect.innerHTML = '<option value="">No hay productos disponibles para devolver en este pedido</option>';
    }
});

productoSelect.addEventListener('change', () => {
    const option = productoSelect.options[productoSelect.selectedIndex];
    proveedorInput.value = option.dataset.proveedorId || '';
    proveedorTexto.value = option.dataset.proveedorNombre || '';

    cantidadDisponibleParaDevolver = parseInt(option.dataset.maxCantidadDevolver) || 0;

    cantidadInput.value = '';
    cantidadInput.setAttribute('max', cantidadDisponibleParaDevolver);
    cantidadInput.setAttribute('placeholder', `Máx: ${cantidadDisponibleParaDevolver}`);
    cantidadInfo.textContent = `Cantidad máxima a devolver: ${cantidadDisponibleParaDevolver}`;
});

cantidadInput.addEventListener('input', () => {
    const val = parseInt(cantidadInput.value);
    if (isNaN(val) || val < 1) {
        cantidadInput.value = 1;
    }
    if (val > cantidadDisponibleParaDevolver) {
        cantidadInput.value = cantidadDisponibleParaDevolver;
    }
});
</script>
</body>
</html>