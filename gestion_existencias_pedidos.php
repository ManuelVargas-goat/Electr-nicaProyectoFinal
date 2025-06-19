<?php
session_start();
include("config.php");

if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}

$mensaje = '';

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['accion']) && !empty($_POST['pedido_id']) && is_numeric($_POST['pedido_id'])) {
        $accion = $_POST['accion'];
        $pedidoId = (int) $_POST['pedido_id'];

        try {
            $pdo->beginTransaction();

            if ($accion === 'confirmar') {
                $stmt = $pdo->prepare("UPDATE pedido SET estado = 'entregado' WHERE pedido_id = :id");
                $stmt->execute([':id' => $pedidoId]);

                $stmtDetalles = $pdo->prepare("SELECT producto_id, cantidad FROM detalle_pedido WHERE pedido_id = :id");
                $stmtDetalles->execute([':id' => $pedidoId]);

                while ($detalle = $stmtDetalles->fetch()) {
                    $productoId = $detalle['producto_id'];
                    $cantidad = $detalle['cantidad'];

                    $checkStock = $pdo->prepare("SELECT COUNT(*) FROM stock WHERE producto_id = :producto_id");
                    $checkStock->execute([':producto_id' => $productoId]);
                    $existe = $checkStock->fetchColumn();

                    if ($existe) {
                        $actualizarStock = $pdo->prepare("UPDATE stock SET cantidad = cantidad + :cantidad WHERE producto_id = :producto_id");
                        $actualizarStock->execute([
                            ':cantidad' => $cantidad,
                            ':producto_id' => $productoId
                        ]);
                    } else {
                        $insertarStock = $pdo->prepare("INSERT INTO stock (producto_id, cantidad) VALUES (:producto_id, :cantidad)");
                        $insertarStock->execute([
                            ':producto_id' => $productoId,
                            ':cantidad' => $cantidad
                        ]);
                    }
                }

                $mensaje = 'Recepción confirmada y stock actualizado correctamente.';
            } elseif ($accion === 'cancelar') {
                $stmt = $pdo->prepare("UPDATE pedido SET estado = 'cancelado' WHERE pedido_id = :id");
                $stmt->execute([':id' => $pedidoId]);
                $mensaje = 'Pedido cancelado correctamente.';
            }

            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            $mensaje = "Error al procesar acción: " . $e->getMessage();
        }
    }
}

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

$estados = $pdo->query("SELECT nombre FROM estado ORDER BY nombre")->fetchAll(PDO::FETCH_COLUMN);

$filtroProducto = $_GET['producto'] ?? '';
$filtroFecha = $_GET['fecha'] ?? '';
$filtroEstado = $_GET['estado'] ?? '';

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

      <?php if (!empty($mensaje)): ?>
        <div class="alert alert-info alert-dismissible fade show" role="alert">
          <?= htmlspecialchars($mensaje) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
      <?php endif; ?>

      <div class="mb-3">
        <a href="nuevo_pedido.php" class="btn btn-success">➕ Generar Nuevo Pedido</a>
      </div>

      <form class="row g-2 mb-3" method="GET">
        <div class="col-md-3">
          <input type="text" name="producto" value="<?= htmlspecialchars($filtroProducto) ?>" class="form-control" placeholder="Buscar Producto">
        </div>
        <div class="col-md-3">
          <input type="date" name="fecha" value="<?= htmlspecialchars($filtroFecha) ?>" class="form-control">
        </div>
        <div class="col-md-3">
          <select name="estado" class="form-select">
            <option value="">Todos los Estados</option>
            <?php foreach ($estados as $estado): ?>
              <option value="<?= htmlspecialchars($estado) ?>" <?= $filtroEstado === $estado ? 'selected' : '' ?>>
                <?= ucfirst($estado) ?>
              </option>
            <?php endforeach; ?>
          </select>
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
              <th>Acciones</th>
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
                  <span class="badge bg-<?= strtolower($row['estado']) == 'entregado' ? 'success' : (strtolower($row['estado']) == 'cancelado' ? 'danger' : 'warning') ?>">
                    <?= ucfirst($row['estado']) ?>
                  </span>
                </td>
                <td>
                  <?php if (!in_array(strtolower($row['estado']), ['entregado', 'cancelado'])): ?>
                    <div class="dropdown">
                      <button class="btn btn-sm btn-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        Acciones
                      </button>
                      <ul class="dropdown-menu">
                        <li>
                          <form method="POST" style="display:inline;">
                            <input type="hidden" name="pedido_id" value="<?= $row['pedido_id'] ?>">
                            <input type="hidden" name="accion" value="confirmar">
                            <button type="submit" class="dropdown-item">Confirmar Recepción</button>
                          </form>
                        </li>
                        <li>
                          <form method="POST" style="display:inline;">
                            <input type="hidden" name="pedido_id" value="<?= $row['pedido_id'] ?>">
                            <input type="hidden" name="accion" value="cancelar">
                            <button type="submit" class="dropdown-item">Cancelar Pedido</button>
                          </form>
                        </li>
                        <li>
                          <a class="dropdown-item" href="editar_pedido.php?id=<?= $row['pedido_id'] ?>">Modificar</a>
                        </li>
                      </ul>
                    </div>
                  <?php else: ?>
                    <button class="btn btn-sm btn-secondary" disabled><?= ucfirst($row['estado']) ?></button>
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
