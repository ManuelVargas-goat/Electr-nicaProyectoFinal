<?php
session_start();
include("config.php");

if (!isset($_SESSION['usuario'])) {
    header('Location: login.php');
    exit();
}

$usuario = $_SESSION['usuario'];
$sqlUser = "SELECT per.nombre, per.apellido_paterno, r.nombre AS rol
            FROM usuario_empleado ue
            JOIN persona per ON ue.persona_id = per.persona_id
            JOIN rol r ON ue.rol_id = r.rol_id
            WHERE ue.usuario = :usuario";
$stmtUser = $pdo->prepare($sqlUser);
$stmtUser->execute(['usuario' => $usuario]);
$datosUsuario = $stmtUser->fetch();

$nombreCompleto = $datosUsuario ? $datosUsuario['nombre'] . ' ' . $datosUsuario['apellido_paterno'] : $usuario;
$rol = strtolower($datosUsuario['rol'] ?? '');

// Filtros
$filtroProducto = $_GET['producto'] ?? '';
$filtroFecha = $_GET['fecha'] ?? '';
$filtroTipo = $_GET['tipo'] ?? '';

// Armado dinámico del WHERE
$where = [];
$params = [];

if (!empty($filtroProducto)) {
    $where[] = "pr.nombre ILIKE :producto";
    $params[':producto'] = "%$filtroProducto%";
}
if (!empty($filtroFecha)) {
    $where[] = "d.fecha = :fecha";
    $params[':fecha'] = $filtroFecha;
}
if (!empty($filtroTipo)) {
    $where[] = "d.tipo ILIKE :tipo";
    $params[':tipo'] = "%$filtroTipo%";
}

$whereClause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

// Consulta principal
$sql = "SELECT d.devolucion_id, d.fecha, dd.cantidad, dd.motivo, dd.reingresado_stock,
               pr.nombre AS producto,
               CASE
                 WHEN d.tipo = 'cliente' THEN (SELECT p.nombre || ' ' || p.apellido_paterno
                                               FROM persona p
                                               JOIN usuario_cliente uc ON uc.persona_id = p.persona_id
                                               WHERE uc.usuario_cliente_id = d.usuario_cliente_id)
                 WHEN d.tipo = 'proveedor' THEN (SELECT p.nombre || ' ' || p.apellido_paterno
                                                 FROM persona p
                                                 JOIN usuario_empleado ue ON ue.persona_id = p.persona_id
                                                 WHERE ue.usuario_empleado_id = d.usuario_empleado_id)
                 ELSE 'No asignado'
               END AS devuelto_por,
               d.tipo
        FROM devolucion d
        JOIN detalle_devolucion dd ON d.devolucion_id = dd.devolucion_id
        JOIN producto pr ON dd.producto_id = pr.producto_id
        $whereClause
        ORDER BY d.devolucion_id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$devoluciones = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Gestión de Existencias - Devoluciones</title>
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
        <?php if ($rol === 'admin'): ?>
          <div class="text-warning">Administrador</div>
        <?php endif; ?>
      </div>
      <a href="#">Gestión de Productos</a>
      <a href="#">Gestión de Usuarios</a>
      <a href="#" class="active">Gestión de Existencias</a>
      <div class="ps-3">
        <a href="gestion_existencias_pedidos.php">Pedidos</a>
        <a href="gestion_existencias_ventas.php">Ventas</a>
        <a href="#" class="active">Devoluciones</a>
        <a href="gestion_existencias_stock.php">Stock</a>
      </div>
      <a href="#">Configuración</a>
      <div class="mt-4 text-center">
        <a href="principal.php" class="btn btn-outline-primary btn-sm w-100">Volver al Inicio</a>
      </div>
    </div>

    <div class="col-md-10 content">
      <h3 class="mb-4">Gestión de Existencias - Devoluciones</h3>

      <!-- Botón Generar Devolución -->
      <div class="mb-3">
        <a href="nueva_devolucion.php" class="btn btn-success">+ Generar Devolución</a>
      </div>

      <!-- Formulario de búsqueda -->
      <form class="row g-2 mb-4" method="GET">
        <div class="col-md-3">
          <input type="text" name="producto" value="<?= htmlspecialchars($filtroProducto) ?>" class="form-control" placeholder="Buscar Producto">
        </div>
        <div class="col-md-3">
          <input type="date" name="fecha" value="<?= htmlspecialchars($filtroFecha) ?>" class="form-control">
        </div>
        <div class="col-md-3">
          <input type="text" name="tipo" value="<?= htmlspecialchars($filtroTipo) ?>" class="form-control" placeholder="Tipo (cliente/proveedor)">
        </div>
        <div class="col-md-3">
          <button class="btn btn-primary w-100">Buscar</button>
        </div>
      </form>

      <div class="table-responsive">
        <table class="table table-bordered table-hover">
          <thead class="text-center">
            <tr>
              <th>#Devolución</th>
              <th>Fecha</th>
              <th>Producto</th>
              <th>Cantidad</th>
              <th>Motivo</th>
              <th>Devuelto Por</th>
              <th>Tipo</th>
              <th>Stock Reingresado</th>
            </tr>
          </thead>
          <tbody class="text-center">
            <?php foreach ($devoluciones as $row): ?>
              <tr>
                <td><?= $row['devolucion_id'] ?></td>
                <td><?= $row['fecha'] ?></td>
                <td><?= $row['producto'] ?></td>
                <td><?= $row['cantidad'] ?></td>
                <td><?= $row['motivo'] ?></td>
                <td><?= $row['devuelto_por'] ?></td>
                <td><span class="badge bg-dark"><?= ucfirst($row['tipo']) ?></span></td>
                <td>
                  <span class="badge bg-<?= $row['reingresado_stock'] ? 'success' : 'danger' ?>">
                    <?= $row['reingresado_stock'] ? 'Sí' : 'No' ?>
                  </span>
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
