<?php
session_start();
include("config.php");

if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}

// Obtener datos del usuario actual
$usuario = $_SESSION['usuario'];
$sql_usuario = "SELECT per.nombre, per.apellido_paterno, r.nombre AS rol
                FROM usuario_empleado ue
                JOIN persona per ON ue.persona_id = per.persona_id
                JOIN rol r ON ue.rol_id = r.rol_id
                WHERE ue.usuario = :usuario";
$stmt = $pdo->prepare($sql_usuario);
$stmt->execute([':usuario' => $usuario]);
$datosUsuario = $stmt->fetch(PDO::FETCH_ASSOC);

// Asignar valores
$nombreCompleto = $datosUsuario['nombre'] . ' ' . $datosUsuario['apellido_paterno'];
$rolUsuario = strtolower($datosUsuario['rol']);

// Obtener estados
$estados = $pdo->query("SELECT estado_id, nombre FROM estado ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);

// Capturar filtros
$filtroCliente = $_GET['cliente'] ?? '';
$filtroFecha = $_GET['fecha'] ?? '';
$filtroEstadoId = $_GET['estado'] ?? '';

$where = [];
$params = [];

if ($filtroCliente !== '') {
    $where[] = "(per.nombre || ' ' || per.apellido_paterno) ILIKE :cliente";
    $params[':cliente'] = "%$filtroCliente%";
}
if ($filtroFecha !== '') {
    $where[] = "CAST(c.fecha_compra AS DATE) = :fecha";
    $params[':fecha'] = $filtroFecha;
}
if ($filtroEstadoId !== '') {
    $where[] = "c.estado_id = :estado_id";
    $params[':estado_id'] = (int)$filtroEstadoId;
}

$whereClause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

// Consulta de ventas
$sqlVentas = "SELECT c.compra_id, c.fecha_compra, pr.nombre AS producto, dc.cantidad,
                     dc.precio_unitario, per.nombre || ' ' || per.apellido_paterno AS cliente, e.nombre AS estado
             FROM compra c
             JOIN detalle_compra dc ON c.compra_id = dc.compra_id
             JOIN producto pr ON dc.producto_id = pr.producto_id
             LEFT JOIN usuario_cliente uc ON uc.usuario_cliente_id = c.usuario_cliente_id
             LEFT JOIN persona per ON uc.persona_id = per.persona_id
             LEFT JOIN estado e ON c.estado_id = e.estado_id
             $whereClause
             ORDER BY c.compra_id DESC";

$stmt = $pdo->prepare($sqlVentas);
$stmt->execute($params);
$ventas = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Existencias - Ventas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/estilos.css?v=<?= time(); ?>">
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <div class="col-md-2 sidebar">
            <div class="text-center mb-4">
                <img src="https://cdn-icons-png.flaticon.com/512/149/149071.png" width="60" class="mb-2">
                <div class="fw-bold"><?= htmlspecialchars($nombreCompleto) ?></div>
                <?php if ($rolUsuario === 'admin'): ?>
                    <div class="text-warning">Administrador</div>
                <?php endif; ?>
            </div>
            <a href="gestion_catalogo_categorias.php">Gestión de Catálogo</a>
            <a href="gestion_usuarios.php">Gestión de Usuarios</a>
            <a href="gestion_existencias_ventas.php" class="active">Gestión de Existencias</a> 
            <div class="ps-3">
                <a href="gestion_existencias_pedidos.php">Pedidos</a>
                <a href="gestion_existencias_ventas.php" class="active">Ventas</a> 
                <a href="gestion_existencias_devoluciones.php">Devoluciones</a>
                <a href="gestion_existencias_stock.php">Stock</a>
            </div>
            <a href="configuracion.php">Configuración</a> 
            <div class="mt-4 text-center">
                <a href="principal.php" class="btn btn-outline-primary btn-sm w-100">Volver al Inicio</a>
            </div>
        </div>

        <div class="col-md-10 content">
            <h3 class="mb-4">Gestión de Existencias - Ventas</h3>

            <form class="row g-2 mb-4" method="GET">
                <div class="col-md-3">
                    <input type="text" name="cliente" value="<?= htmlspecialchars($filtroCliente) ?>" class="form-control" placeholder="Buscar Cliente">
                </div>
                <div class="col-md-3">
                    <input type="date" name="fecha" value="<?= htmlspecialchars($filtroFecha) ?>" class="form-control">
                </div>
                <div class="col-md-3">
                    <select name="estado" class="form-select">
                        <option value="">Todos los Estados</option>
                        <?php foreach ($estados as $estado): ?>
                            <option value="<?= $estado['estado_id'] ?>" <?= $filtroEstadoId == $estado['estado_id'] ? 'selected' : '' ?>><?= htmlspecialchars($estado['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <button class="btn btn-primary w-100 fw-bold">Buscar</button>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead class="text-center">
                        <tr>
                            <th>#Venta</th>
                            <th>Fecha</th>
                            <th>Producto</th>
                            <th>Cantidad</th>
                            <th>Precio</th>
                            <th>Cliente</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody class="text-center">
                        <?php foreach ($ventas as $venta): ?>
                            <tr>
                                <td><?= $venta['compra_id'] ?></td>
                                <td><?= $venta['fecha_compra'] ?></td>
                                <td><?= $venta['producto'] ?></td>
                                <td><?= $venta['cantidad'] ?></td>
                                <td>S/ <?= number_format($venta['precio_unitario'], 2) ?></td>
                                <td><?= $venta['cliente'] ?? 'No asignado' ?></td>
                                <td>
                                    <?php if (!empty($venta['estado'])): ?>
                                        <span class="badge bg-<?= strtolower($venta['estado']) == 'entregado' ? 'success' : (strtolower($venta['estado']) == 'cancelado' ? 'danger' : 'warning') ?>">
                                            <?= htmlspecialchars($venta['estado']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Desconocido</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>