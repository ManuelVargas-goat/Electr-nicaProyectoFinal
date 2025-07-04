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

$nombreCompleto = $datosUsuario['nombre'] . ' ' . $datosUsuario['apellido_paterno'];
$rolUsuario = strtolower($datosUsuario['rol']);

// Filtro por nombre o categoría
$filtroNombre = $_GET['nombre'] ?? '';
$filtroCategoria = $_GET['categoria'] ?? '';

$where = [];
$params = [];

if (!empty($filtroNombre)) {
    $where[] = "p.nombre ILIKE :nombre";
    $params[':nombre'] = "%$filtroNombre%";
}
if (!empty($filtroCategoria)) {
    $where[] = "c.nombre ILIKE :categoria";
    $params[':categoria'] = "%$filtroCategoria%";
}

$whereClause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

$sql = "
SELECT p.producto_id,
       p.nombre,
       p.marca,
       c.nombre AS categoria,
       (
         -- ENTRADAS
         COALESCE((
           SELECT SUM(dp.cantidad)
           FROM detalle_pedido dp
           JOIN pedido ped ON ped.pedido_id = dp.pedido_id
           WHERE dp.producto_id = p.producto_id AND ped.estado = 'entregado'
         ), 0)
         +
         COALESCE((
           SELECT SUM(dd.cantidad)
           FROM detalle_devolucion dd
           JOIN devolucion dv ON dv.devolucion_id = dd.devolucion_id
           WHERE dd.producto_id = p.producto_id AND dv.tipo = 'cliente' AND dd.reingresado_stock IS TRUE
         ), 0)
         -- SALIDAS
         -
         COALESCE((
           SELECT SUM(dc.cantidad)
           FROM detalle_compra dc
           JOIN compra c ON dc.compra_id = c.compra_id
           JOIN estado es ON c.estado_id = es.estado_id
           WHERE dc.producto_id = p.producto_id AND es.nombre = 'confirmado'
         ), 0)
         -
         COALESCE((
           SELECT SUM(dd2.cantidad)
           FROM detalle_devolucion dd2
           JOIN devolucion dv2 ON dv2.devolucion_id = dd2.devolucion_id
           WHERE dd2.producto_id = p.producto_id AND dv2.tipo = 'proveedor'
         ), 0)
       ) AS stock_disponible
FROM producto p
LEFT JOIN categoria c ON p.categoria_id = c.categoria_id
$whereClause
ORDER BY p.producto_id DESC
";


$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$productos = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Existencias - Stock</title>
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
            <a href="gestion_existencias_stock.php" class="active">Gestión de Existencias</a> 
            <div class="ps-3">
                <a href="gestion_existencias_pedidos.php">Pedidos</a>
                <a href="gestion_existencias_ventas.php">Ventas</a>
                <a href="gestion_existencias_devoluciones.php">Devoluciones</a>
                <a href="gestion_existencias_stock.php" class="active">Stock</a> 
            </div>
            <a href="configuracion.php">Configuración</a> 
            <div class="mt-4 text-center">
                <a href="principal.php" class="btn btn-outline-primary btn-sm w-100">Volver al Inicio</a>
            </div>
        </div>

        <div class="col-md-10 content">
            <h3 class="mb-4">Gestión de Existencias - Stock</h3>

            <form class="row g-2 mb-4" method="GET">
                <div class="col-md-4">
                    <input type="text" name="nombre" value="<?= htmlspecialchars($filtroNombre) ?>" class="form-control" placeholder="Buscar por nombre">
                </div>
                <div class="col-md-4">
                    <input type="text" name="categoria" value="<?= htmlspecialchars($filtroCategoria) ?>" class="form-control" placeholder="Buscar por categoría">
                </div>
                <div class="col-md-4">
                    <button class="btn btn-primary w-100">Buscar</button>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-bordered table-hover text-center">
                    <thead class=""> <tr>
                            <th>ID</th>
                            <th>Nombre</th>
                            <th>Marca</th>
                            <th>Categoría</th>
                            <th>Stock Disponible</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($productos as $producto): ?>
                            <tr>
                                <td><?= $producto['producto_id'] ?></td>
                                <td><?= htmlspecialchars($producto['nombre']) ?></td>
                                <td><?= htmlspecialchars($producto['marca']) ?></td>
                                <td><?= htmlspecialchars($producto['categoria'] ?? 'Sin categoría') ?></td>
                                <td class="<?= ($producto['stock_disponible'] <= 0) ? 'bg-danger text-white fw-bold' : '' ?>">
                                    <?= ($producto['stock_disponible'] <= 0) ? 'Sin stock' : $producto['stock_disponible'] ?>
                                </td>
                                <td>
                                    <a href="detalle_stock.php?producto_id=<?= $producto['producto_id'] ?>" class="btn btn-sm btn-info">Ver detalles</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
</body>
</html>