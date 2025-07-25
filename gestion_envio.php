<?php
session_start(); // Inicia la sesión para poder usar variables de sesión
include("config.php"); // Asegúrate de que 'config.php' contenga la conexión PDO a tu base de datos.

// Redirecciona al login si el usuario no ha iniciado sesión
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}

// Obtener datos del usuario actual (si necesitas mostrarlos en esta página)
$usuario = $_SESSION['usuario'];
$sql_usuario = "SELECT per.nombre, per.apellido_paterno, r.nombre AS rol
                FROM usuario_empleado ue
                JOIN persona per ON ue.persona_id = per.persona_id
                JOIN rol r ON ue.rol_id = r.rol_id
                WHERE ue.usuario = :usuario";
$stmt = $pdo->prepare($sql_usuario);
$stmt->execute([':usuario' => $usuario]);
$datosUsuario = $stmt->fetch(PDO::FETCH_ASSOC);

// ID de la compra a gestionar, obtenido de la URL
$compra_id = $_GET['compra_id'] ?? null;

// Si no hay ID de compra, redirigir a la página de ventas con un mensaje de error
if (!$compra_id) {
    $_SESSION['modal_message'] = 'ID de compra no especificado para gestionar el envío.';
    $_SESSION['modal_type'] = 'danger';
    header("Location: gestion_existencias_ventas.php");
    exit();
}

// **LÓGICA PARA PROCESAR EL FORMULARIO DE "REALIZAR ENVÍO"**
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'realizar_envio') {
    $fechaEnvioProgramada = $_POST['fecha_envio_programada'] ?? null;

    // Obtener el ID del estado "Entregado"
    $stmtEstadoEntregadoId = $pdo->prepare("SELECT estado_id FROM estado WHERE LOWER(nombre) = 'entregado'");
    $stmtEstadoEntregadoId->execute();
    $idEstadoEntregado = $stmtEstadoEntregadoId->fetchColumn();

    if (!$idEstadoEntregado) {
        $_SESSION['modal_message'] = 'Error: El estado "Entregado" no se encuentra en la base de datos.';
        $_SESSION['modal_type'] = 'danger';
        header("Location: gestion_envio.php?compra_id=" . $compra_id); // Corregido
        exit();
    }

    if (!empty($fechaEnvioProgramada)) {
        try {
            $pdo->beginTransaction();

            // Actualizar la compra: cambiar estado a "Entregado" y registrar la fecha de envío
            $sqlUpdate = "UPDATE compra SET estado_id = :nuevo_estado_id, fecha_envio = :fecha_envio WHERE compra_id = :compra_id";
            $stmtUpdate = $pdo->prepare($sqlUpdate);
            $stmtUpdate->bindValue(':nuevo_estado_id', (int)$idEstadoEntregado, PDO::PARAM_INT); // Se asigna el ID de "Entregado"
            $stmtUpdate->bindValue(':compra_id', (int)$compra_id, PDO::PARAM_INT);
            $stmtUpdate->bindValue(':fecha_envio', $fechaEnvioProgramada, PDO::PARAM_STR); 

            $stmtUpdate->execute();

            $pdo->commit();
            $_SESSION['modal_message'] = 'Envío marcado como "Entregado" para el ' . htmlspecialchars($fechaEnvioProgramada) . ' exitosamente.';
            $_SESSION['modal_type'] = 'success';
            header("Location: gestion_existencias_ventas.php");
            exit();

        } catch (PDOException $e) {
            $pdo->rollBack();
            $_SESSION['modal_message'] = 'Error al marcar como "Entregado": ' . $e->getMessage();
            $_SESSION['modal_type'] = 'danger';
            header("Location: gestion_envio.php?compra_id=" . $compra_id); // Corregido
            exit();
        }
    } else {
        $_SESSION['modal_message'] = 'Debes especificar la fecha de entrega.';
        $_SESSION['modal_type'] = 'warning';
        header("Location: gestion_envio.php?compra_id=" . $compra_id); // Corregido
        exit();
    }
}


// **CONSULTA PARA OBTENER LOS DETALLES COMPLETOS DE LA COMPRA**
$sqlDetalleCompra = "SELECT
                        c.compra_id,
                        c.fecha_compra,
                        c.fecha_envio,
                        COALESCE(per.nombre || ' ' || per.apellido_paterno, 'Cliente Invitado') AS cliente_nombre,
                        per.direccion AS cliente_direccion,
                        per.telefono AS cliente_telefono,
                        e.nombre AS estado_nombre,
                        e.estado_id, -- Necesitamos el ID del estado para el formulario
                        alm.nombre AS almacen_nombre,
                        JSON_AGG(
                            JSON_BUILD_OBJECT(
                                'producto', pr.nombre,
                                'cantidad', dc.cantidad,
                                'precio_unitario', dc.precio_unitario,
                                'subtotal', dc.cantidad * dc.precio_unitario
                            ) ORDER BY pr.nombre
                        ) AS productos_detalle,
                        SUM(dc.cantidad * dc.precio_unitario) AS total_compra
                    FROM compra c
                    JOIN detalle_compra dc ON c.compra_id = dc.compra_id
                    JOIN producto pr ON dc.producto_id = pr.producto_id
                    LEFT JOIN usuario_cliente uc ON uc.usuario_cliente_id = c.usuario_cliente_id
                    LEFT JOIN persona per ON uc.persona_id = per.persona_id
                    LEFT JOIN estado e ON c.estado_id = e.estado_id
                    LEFT JOIN almacen alm ON c.almacen_id = alm.almacen_id
                    WHERE c.compra_id = :compra_id
                    GROUP BY c.compra_id, c.fecha_compra, c.fecha_envio, cliente_nombre, cliente_direccion, cliente_telefono, estado_nombre, e.estado_id, almacen_nombre";

$stmt = $pdo->prepare($sqlDetalleCompra);
$stmt->execute([':compra_id' => $compra_id]);
$ventaDetalle = $stmt->fetch(PDO::FETCH_ASSOC);

// Si la compra no se encuentra, redirigir con un mensaje
if (!$ventaDetalle) {
    $_SESSION['modal_message'] = 'La compra con ID ' . htmlspecialchars($compra_id) . ' no fue encontrada.';
    $_SESSION['modal_type'] = 'danger';
    header("Location: gestion_existencias_ventas.php");
    exit();
}

$productos = json_decode($ventaDetalle['productos_detalle'], true);

// LÓGICA PARA MOSTRAR MENSAJES EN ALERTAS DE BOOTSTRAP
$mensaje = '';
$tipoMensaje = ''; 
if (isset($_SESSION['modal_message']) && !empty($_SESSION['modal_message'])) {
    $mensaje = $_SESSION['modal_message'];
    $tipoMensaje = $_SESSION['modal_type'];
    // Limpiar variables de sesión para que no se muestren de nuevo
    unset($_SESSION['modal_message']);
    unset($_SESSION['modal_type']);
}

// Determinar la clase del badge para el estado actual
$badgeClass = 'secondary';
switch (strtolower($ventaDetalle['estado_nombre'])) {
    case 'entregado':
        $badgeClass = 'success';
        break;
    case 'cancelado':
        $badgeClass = 'danger';
        break;
    case 'pendiente':
        $badgeClass = 'warning';
        break;
    case 'en proceso de envío': // Si tuvieras este estado para otros propósitos, se mantendría aquí
        $badgeClass = 'info';
        break;
    default:
        $badgeClass = 'secondary';
        break;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestionar Envío - Compra #<?= htmlspecialchars($compra_id) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/estilos.css?v=<?= time(); ?>">
    <style>
        .container {
            max-width: 900px;
        }
        .card-header {
            background-color: #007bff;
            color: white;
            font-weight: bold;
        }
        .table-sm th, .table-sm td {
            padding: 0.5rem;
        }
        .btn-back {
            background-color: #6c757d;
            color: white;
        }
        .btn-back:hover {
            background-color: #5a6268;
            color: white;
        }
    </style>
</head>
<body>
<div class="container mt-5">
    <h3 class="mb-4 text-center">Gestionar Envío de Compra #<?= htmlspecialchars($ventaDetalle['compra_id']) ?></h3>

    <?php
    // BLOQUE PARA MOSTRAR LA ALERTA DE BOOTSTRAP
    if (!empty($mensaje) && !empty($tipoMensaje)) {
        echo '<div class="alert ' . htmlspecialchars('alert-' . $tipoMensaje) . ' alert-dismissible fade show mt-3" role="alert">';
        echo htmlspecialchars($mensaje);
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
        echo '</div>';
    }
    ?>

    <div class="card mb-4 shadow-sm">
        <div class="card-header">
            Detalles de la Compra
        </div>
        <div class="card-body">
            <div class="row mb-3">
                <div class="col-md-6">
                    <p class="mb-1"><strong>Fecha de Compra:</strong> <?= htmlspecialchars($ventaDetalle['fecha_compra']) ?></p>
                    <p class="mb-1"><strong>Estado Actual:</strong> <span class="badge bg-<?= $badgeClass ?>"><?= htmlspecialchars($ventaDetalle['estado_nombre']) ?></span></p>
                    <p class="mb-1"><strong>Fecha de Entrega:</strong> <?= htmlspecialchars($ventaDetalle['fecha_envio'] ? date('Y-m-d', strtotime($ventaDetalle['fecha_envio'])) : 'No entregado') ?></p>
                </div>
                <div class="col-md-6">
                    <p class="mb-1"><strong>Almacén de Origen:</strong> <?= htmlspecialchars($ventaDetalle['almacen_nombre'] ?? 'N/A') ?></p>
                    <p class="mb-1"><strong>Total de la Compra:</strong> S/ <?= number_format($ventaDetalle['total_compra'], 2) ?></p>
                </div>
            </div>
            <hr>
            <h5>Información del Cliente:</h5>
            <p class="mb-1"><strong>Cliente:</strong> <?= htmlspecialchars($ventaDetalle['cliente_nombre']) ?></p>
            <p class="mb-1"><strong>Dirección:</strong> <?= htmlspecialchars($ventaDetalle['cliente_direccion'] ?? 'N/A') ?></p>
            <p class="mb-3"><strong>Teléfono:</strong> <?= htmlspecialchars($ventaDetalle['cliente_telefono'] ?? 'N/A') ?></p>

            <hr>
            <h5>Productos de la Orden:</h5>
            <div class="table-responsive">
                <table class="table table-bordered table-sm">
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th>Cantidad</th>
                            <th>Precio Unitario</th>
                            <th>Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($productos)): ?>
                            <?php foreach ($productos as $item): ?>
                                <tr>
                                    <td><?= htmlspecialchars($item['producto']) ?></td>
                                    <td><?= htmlspecialchars($item['cantidad']) ?></td>
                                    <td>S/ <?= number_format($item['precio_unitario'], 2) ?></td>
                                    <td>S/ <?= number_format($item['subtotal'], 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="text-center">No hay productos asociados a esta compra.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php if (strtolower($ventaDetalle['estado_nombre']) === 'pendiente'): ?>
        <div class="card mb-4 shadow-sm">
            <div class="card-header">
                Marcar como Entregado
            </div>
            <div class="card-body">
                <form action="gestion_envio.php?compra_id=<?= htmlspecialchars($compra_id) ?>" method="POST"> <input type="hidden" name="action" value="realizar_envio">
                    
                    <div class="mb-3">
                        <label for="fecha_envio_programada" class="form-label">Fecha de Entrega:</label>
                        <input type="date" class="form-control" id="fecha_envio_programada" name="fecha_envio_programada" 
                                value="<?= htmlspecialchars(date('Y-m-d')) ?>" required>
                        <small class="form-text text-muted">La compra cambiará a estado "Entregado".</small>
                    </div>
                    
                    <div class="d-flex justify-content-between">
                        <button type="submit" class="btn btn-primary">Marcar como Entregado</button>
                        <a href="gestion_existencias_ventas.php" class="btn btn-back">Volver a Ventas</a>
                    </div>
                </form>
            </div>
        </div>
    <?php else: ?>
        <div class="alert alert-info text-center" role="alert">
            Esta compra ya no está en estado **"Pendiente"**. Su estado actual es **"<?= htmlspecialchars($ventaDetalle['estado_nombre']) ?>"**.
            <br> No se pueden realizar más gestiones de envío desde aquí.
        </div>
        <div class="text-center">
            <a href="gestion_existencias_ventas.php" class="btn btn-secondary">Volver a Ventas</a>
        </div>
    <?php endif; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>