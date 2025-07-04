<?php
session_start();
include("config.php");

if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}

// Mensajes de notificación desde la URL
$mensaje = $_GET['mensaje'] ?? '';
$tipoMensaje = $_GET['tipo'] ?? ''; // 'success', 'info', 'danger'

// Procesar acciones (Confirmar/Cancelar Pedido)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['accion']) && !empty($_POST['pedido_id']) && is_numeric($_POST['pedido_id'])) {
        $accion = $_POST['accion'];
        $pedidoId = (int) $_POST['pedido_id'];

        try {
            $pdo->beginTransaction();

            if ($accion === 'confirmar') {
                // Actualizar estado del pedido a 'entregado'
                $stmt = $pdo->prepare("UPDATE pedido SET estado = 'entregado' WHERE pedido_id = :id");
                $stmt->execute([':id' => $pedidoId]);

                // Actualizar stock de los productos en el pedido
                $stmtDetalles = $pdo->prepare("SELECT producto_id, cantidad FROM detalle_pedido WHERE pedido_id = :id");
                $stmtDetalles->execute([':id' => $pedidoId]);

                while ($detalle = $stmtDetalles->fetch(PDO::FETCH_ASSOC)) {
                    $productoId = $detalle['producto_id'];
                    $cantidad = $detalle['cantidad'];

                    // Verificar si el producto ya tiene un registro en stock
                    $checkStock = $pdo->prepare("SELECT COUNT(*) FROM stock WHERE producto_id = :producto_id");
                    $checkStock->execute([':producto_id' => $productoId]);
                    $existe = $checkStock->fetchColumn();

                    if ($existe) {
                        // Si existe, actualizar la cantidad
                        $actualizarStock = $pdo->prepare("UPDATE stock SET cantidad = cantidad + :cantidad WHERE producto_id = :producto_id");
                        $actualizarStock->execute([
                            ':cantidad' => $cantidad,
                            ':producto_id' => $productoId
                        ]);
                    } else {
                        // Si no existe, insertar un nuevo registro de stock
                        $insertarStock = $pdo->prepare("INSERT INTO stock (producto_id, cantidad) VALUES (:producto_id, :cantidad)");
                        $insertarStock->execute([
                            ':producto_id' => $productoId,
                            ':cantidad' => $cantidad
                        ]);
                    }
                }

                $pdo->commit();
                header("Location: gestion_existencias_pedidos.php?mensaje=" . urlencode("Recepción confirmada y stock actualizado correctamente.") . "&tipo=success");
                exit();

            } elseif ($accion === 'cancelar') {
                $stmt = $pdo->prepare("UPDATE pedido SET estado = 'cancelado' WHERE pedido_id = :id");
                $stmt->execute([':id' => $pedidoId]);

                $pdo->commit();
                header("Location: gestion_existencias_pedidos.php?mensaje=" . urlencode("Pedido cancelado correctamente.") . "&tipo=info");
                exit();
            }

        } catch (Exception $e) {
            $pdo->rollBack();
            header("Location: gestion_existencias_pedidos.php?mensaje=" . urlencode("Error al procesar acción: " . $e->getMessage()) . "&tipo=danger");
            exit();
        }
    }
}

// Obtener datos del usuario logueado
$usuario = $_SESSION['usuario'];
$sql_usuario = "SELECT per.nombre, per.apellido_paterno, r.nombre AS rol
                FROM usuario_empleado ue
                JOIN persona per ON ue.persona_id = per.persona_id
                JOIN rol r ON ue.rol_id = r.rol_id
                WHERE ue.usuario = :usuario";
$stmt = $pdo->prepare($sql_usuario);
$stmt->execute([':usuario' => $usuario]);
$datosUsuario = $stmt->fetch(PDO::FETCH_ASSOC);

$nombreCompleto = 'Usuario Desconocido'; 
$rolUsuario = ''; 

if ($datosUsuario) {
    $nombreCompleto = $datosUsuario['nombre'] . ' ' . $datosUsuario['apellido_paterno'];
    $rolUsuario = strtolower($datosUsuario['rol']);
}


// Obtener todos los estados posibles para el filtro
$estados = $pdo->query("SELECT nombre FROM estado ORDER BY nombre")->fetchAll(PDO::FETCH_COLUMN);

// Lógica de filtrado de pedidos
$filtroPedidoId = $_GET['pedido_id'] ?? '';
$filtroFechaInicio = $_GET['fecha_inicio'] ?? '';
$filtroFechaFin = $_GET['fecha_fin'] ?? '';
$filtroEstado = $_GET['estado'] ?? '';

$where = [];
$params = [];

if (!empty($filtroPedidoId)) {
    $where[] = "CAST(p.pedido_id AS TEXT) ILIKE :pedido_id";
    $params[':pedido_id'] = "%$filtroPedidoId%";
}
if (!empty($filtroFechaInicio)) {
    $where[] = "p.fecha_pedido >= :fecha_inicio";
    $params[':fecha_inicio'] = $filtroFechaInicio;
}
if (!empty($filtroFechaFin)) {
    $where[] = "p.fecha_pedido <= :fecha_fin";
    $params[':fecha_fin'] = $filtroFechaFin;
}
if (!empty($filtroEstado)) {
    $where[] = "p.estado ILIKE :estado";
    $params[':estado'] = "$filtroEstado"; 
}

$whereClause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

// Consulta principal para obtener los pedidos y sus detalles
$sql = "
    SELECT 
      p.pedido_id,
      p.fecha_pedido,
      p.estado,
      COALESCE(per.nombre || ' ' || per.apellido_paterno, 'N/A') AS empleado,
      SUM(dp.cantidad * CASE 
          WHEN dp.precio_unidad = 0 THEN pr.precio
          ELSE dp.precio_unidad
        END
      ) AS total_precio,
      SUM(dp.cantidad) AS total_cantidad,
      json_agg(json_build_object(
        'producto', pr.nombre,
        'cantidad', dp.cantidad,
        'precio_unitario', 
          CASE 
            WHEN dp.precio_unidad = 0 THEN pr.precio
            ELSE dp.precio_unidad
          END
      )) AS productos
    FROM pedido p
    JOIN detalle_pedido dp ON p.pedido_id = dp.pedido_id
    JOIN producto pr ON dp.producto_id = pr.producto_id
    LEFT JOIN usuario_empleado ue ON p.usuario_empleado_id = ue.usuario_empleado_id
    LEFT JOIN persona per ON ue.persona_id = per.persona_id
    $whereClause
    GROUP BY p.pedido_id, p.fecha_pedido, per.nombre, per.apellido_paterno, p.estado
    ORDER BY p.pedido_id DESC
";


$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$resultado = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
            <div class="user-box text-center mb-4">
                <img src="https://cdn-icons-png.flaticon.com/512/149/149071.png" alt="Usuario" class="img-fluid rounded-circle mb-2" style="width: 64px;">
                <div class="fw-bold"><?= htmlspecialchars($nombreCompleto) ?></div>
                <div class="<?= ($rolUsuario === 'admin') ? 'text-warning' : 'text-light' ?> small">
                    <?= ucfirst($rolUsuario === 'admin' ? 'administrador' : $rolUsuario) ?>
                </div>
            </div>

            <a href="gestion_catalogo_categorias.php">Gestión de Catálogo</a>
            <a href="gestion_usuarios.php">Gestión de Usuarios</a>
            <a href="#" class="active">Gestión de Existencias</a>
            <div class="ps-3">
                <a href="gestion_existencias_pedidos.php" class="active">Pedidos</a>
                <a href="gestion_existencias_ventas.php">Ventas</a>
                <a href="gestion_existencias_devoluciones.php">Devoluciones</a>
                <a href="gestion_existencias_stock.php">Stock</a>
            </div>
            <a href="configuracion.php">Configuración</a>
            
            <div class="mt-4 text-center">
                <a href="principal.php" class="btn btn-outline-primary w-100">Volver al Inicio</a>
            </div>
        </div>

        <div class="col-md-10 content">
            <h3 class="main-title mb-4">Gestión de Existencias - Pedidos</h3>

            <?php if (!empty($mensaje)): ?>
                <div class="alert alert-<?= htmlspecialchars($tipoMensaje) ?> alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($mensaje) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
                </div>
            <?php endif; ?>

            <div class="mb-3">
                <a href="nuevo_pedido.php" class="btn btn-success">➕ Generar Nuevo Pedido</a>
            </div>

            <form class="row g-2 mb-3" method="GET">
                <div class="col-md-2">
                    <input type="text" name="pedido_id" value="<?= htmlspecialchars($filtroPedidoId) ?>" class="form-control" placeholder="Número de Pedido">
                </div>
                <div class="col-md-2">
                    <label for="fecha_inicio" class="form-label visually-hidden">Fecha Inicio</label>
                    <input type="date" name="fecha_inicio" id="fecha_inicio" value="<?= htmlspecialchars($filtroFechaInicio) ?>" class="form-control" title="Fecha de inicio">
                </div>
                <div class="col-md-2">
                    <label for="fecha_fin" class="form-label visually-hidden">Fecha Fin</label>
                    <input type="date" name="fecha_fin" id="fecha_fin" value="<?= htmlspecialchars($filtroFechaFin) ?>" class="form-control" title="Fecha de fin">
                </div>
                <div class="col-md-2">
                    <select name="estado" class="form-select">
                        <option value="">Todos los Estados</option>
                        <?php foreach ($estados as $estado): ?>
                            <option value="<?= htmlspecialchars($estado) ?>" <?= $filtroEstado === $estado ? 'selected' : '' ?>>
                                <?= ucfirst($estado) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">Buscar</button>
                </div>
                <div class="col-md-2">
                    <a href="gestion_existencias_pedidos.php" class="btn btn-secondary w-100">Limpiar</a>
                </div>
            </form>
            
            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle">
                    <thead class="text-center">
                        <tr>
                            <th>#Pedido</th>
                            <th>Fecha</th>
                            <th>Empleado</th>
                            <th>Cantidad Total</th>
                            <th>Total (S/)</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="text-center">
                        <?php if (count($resultado) > 0): ?>
                            <?php foreach ($resultado as $row): ?>
                                <tr class="table-row">
                                    <td 
                                        data-bs-toggle="collapse" 
                                        data-bs-target="#detalle<?= $row['pedido_id'] ?>" 
                                        class="fw-bold" 
                                        style="cursor:pointer">
                                        <?= $row['pedido_id'] ?>
                                    </td>
                                    <td><?= htmlspecialchars($row['fecha_pedido']) ?></td>
                                    <td><?= htmlspecialchars($row['empleado']) ?></td>
                                    <td><?= htmlspecialchars($row['total_cantidad']) ?></td>
                                    <td>S/ <?= number_format($row['total_precio'], 2) ?></td>
                                    <td>
                                        <span class="badge bg-<?= strtolower($row['estado']) == 'entregado' ? 'success' : (strtolower($row['estado']) == 'cancelado' ? 'danger' : 'warning') ?>">
                                            <?= ucfirst($row['estado']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (!in_array(strtolower($row['estado']), ['entregado', 'cancelado'])): ?>
                                            <div class="dropdown">
                                                <button class="btn btn-sm btn-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">Acciones</button>
                                                <ul class="dropdown-menu">
                                                    <li>
                                                        <form method="POST" class="m-0">
                                                            <input type="hidden" name="pedido_id" value="<?= $row['pedido_id'] ?>">
                                                            <input type="hidden" name="accion" value="confirmar">
                                                            <button type="submit" class="dropdown-item">Confirmar Recepción</button>
                                                        </form>
                                                    </li>
                                                    <li>
                                                        <form method="POST" class="m-0">
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
                                <tr class="collapse bg-light" id="detalle<?= $row['pedido_id'] ?>">
                                    <td colspan="7">
                                        <div class="p-3 text-start">
                                            <h6 class="fw-bold mb-3">Productos del Pedido</h6>
                                            <ul class="list-group list-group-flush">
                                                <?php 
                                                $productos_decodificados = json_decode($row['productos'], true);
                                                if (is_array($productos_decodificados)):
                                                    foreach ($productos_decodificados as $item): 
                                                ?>
                                                    <li class="list-group-item d-flex justify-content-between">
                                                        <span><?= htmlspecialchars($item['producto']) ?> (<?= $item['cantidad'] ?> unid.)</span>
                                                        <span>S/ <?= number_format($item['precio_unitario'], 2) ?></span>
                                                    </li>
                                                <?php 
                                                    endforeach;
                                                else:
                                                    echo '<li class="list-group-item">No hay productos asociados a este pedido.</li>';
                                                endif;
                                                ?>
                                            </ul>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center">No se encontraron pedidos.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const dropdowns = document.querySelectorAll('.dropdown-toggle');
        dropdowns.forEach(btn => {
            btn.addEventListener('click', e => e.stopPropagation());
        });

        const menus = document.querySelectorAll('.dropdown-menu');
        menus.forEach(menu => {
            menu.addEventListener('click', e => e.stopPropagation());
        });
    });
</script>
</body>
</html>