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

// Filtros
$filtroProducto = $_GET['producto'] ?? '';
$filtroFecha = $_GET['fecha'] ?? '';
$filtroEstado = $_GET['estado'] ?? '';

// Armado dinámico del WHERE
$where = [];
$params = [];

if (!empty($filtroProducto)) {
    $where[] = "pr.nombre ILIKE :producto";
    $params[':producto'] = "%$filtroProducto%";
}
if (!empty($filtroFecha)) {
    $where[] = "p.fecha_pedido = :fecha";
    $params[':fecha'] = $filtroFecha;
}
if (!empty($filtroEstado)) {
    $where[] = "p.estado ILIKE :estado";
    $params[':estado'] = "%$filtroEstado%";
}

$whereClause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

// Consulta principal
$sql = "SELECT p.pedido_id, p.fecha_pedido, pr.nombre AS producto, dp.cantidad,
               dp.precio_unidad, per.nombre || ' ' || per.apellido_paterno AS empleado, p.estado
        FROM pedido p
        JOIN detalle_pedido dp ON p.pedido_id = dp.pedido_id
        JOIN producto pr ON dp.producto_id = pr.producto_id
        LEFT JOIN usuario_empleado ue ON ue.usuario_empleado_id = (
            SELECT usuario_empleado_id FROM usuario_empleado ORDER BY usuario_empleado_id LIMIT 1
        )
        LEFT JOIN persona per ON ue.persona_id = per.persona_id
        $whereClause
        ORDER BY p.pedido_id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$resultado = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Gestión de Existencias - Pedidos</title>
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
      <a href="#">Gestión de Productos</a>
      <a href="#">Gestión de Usuarios</a>
      <a href="#" class="active">Gestión de Existencias</a>
      <div class="ps-3">
        <a href="#" class="active">Pedidos</a>
        <a href="gestion_existencias_ventas.php">Ventas</a>
        <a href="gestion_existencias_devoluciones.php">Devoluciones</a>
        <a href="gestion_existencias_stock.php">Stock</a>
      </div>
      <a href="#">Configuración</a>
      <div class="mt-4 text-center">
        <a href="principal.php" class="btn btn-outline-primary btn-sm w-100">Volver al Inicio</a>
      </div>
    </div>

    <div class="col-md-10 content">
      <h3 class="mb-4">Gestión de Existencias - Pedidos</h3>

      <!-- Botón para nuevo pedido -->
      <div class="mb-3">
        <a href="nuevo_pedido.php" class="btn btn-success">➕ Generar Nuevo Pedido</a>
      </div>

      <!-- Formulario de búsqueda -->
      <form class="row g-2 filter-group mb-3" method="GET">
        <div class="col-md-3">
          <input type="text" name="producto" value="<?= htmlspecialchars($filtroProducto) ?>" class="form-control" placeholder="Buscar Producto">
        </div>
        <div class="col-md-3">
          <input type="date" name="fecha" value="<?= htmlspecialchars($filtroFecha) ?>" class="form-control">
        </div>
        <div class="col-md-3">
          <input type="text" name="estado" value="<?= htmlspecialchars($filtroEstado) ?>" class="form-control" placeholder="Buscar Estado">
        </div>
        <div class="col-md-3">
          <button class="btn btn-primary w-100">Buscar</button>
        </div>
      </form>

      <div class="table-responsive">
        <table class="table table-bordered table-hover">
          <thead class="text-center">
            <tr>
              <th>#Pedido</th>
              <th>Fecha</th>
              <th>Producto</th>
              <th>Cantidad</th>
              <th>Precio</th>
              <th>Empleado</th>
              <th>Estado</th>
              <th>Seleccionar</th>
            </tr>
          </thead>
          <tbody class="text-center">
            <?php foreach ($resultado as $row): ?>
              <tr>
                <td><?= $row['pedido_id'] ?></td>
                <td><?= $row['fecha_pedido'] ?></td>
                <td><?= $row['producto'] ?></td>
                <td><?= $row['cantidad'] ?></td>
                <td>S/ <?= number_format($row['precio_unidad'], 2) ?></td>
                <td><?= $row['empleado'] ?? 'No asignado' ?></td>
                <td>
                  <span class="badge bg-<?= strtolower($row['estado']) == 'entregado' ? 'success' : 'warning' ?>">
                    <?= ucfirst($row['estado']) ?>
                  </span>
                </td>
                <td><button class="btn btn-sm btn-info">Editar</button></td>
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
