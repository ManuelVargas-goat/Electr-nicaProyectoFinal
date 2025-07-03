<?php
include("config.php");
session_start();

if (!isset($_GET['producto_id']) || !is_numeric($_GET['producto_id'])) {
    echo "Producto no especificado.";
    exit();
}

$producto_id = (int)$_GET['producto_id'];

// Obtener nombre del producto
$sql_nombre = "SELECT nombre FROM producto WHERE producto_id = :producto_id";
$stmtNombre = $pdo->prepare($sql_nombre);
$stmtNombre->execute([':producto_id' => $producto_id]);
$producto = $stmtNombre->fetch(PDO::FETCH_ASSOC);
$nombreProducto = $producto ? $producto['nombre'] : 'Desconocido';

// Consulta de movimientos
// Fragmento dentro del archivo PHP justo donde obtienes los movimientos
$sql = "
    -- Entrada por pedido (Ingreso nuevo)
    SELECT 
        'Entrada (Ingreso nuevo)' AS tipo,
        TO_CHAR(p.fecha_pedido, 'DD/MM/YYYY') AS fecha,
        TO_CHAR(p.fecha_pedido, 'HH24:MI') AS hora,
        per.nombre || ' ' || per.apellido_paterno AS persona,
        dp.cantidad
    FROM pedido p
    JOIN detalle_pedido dp ON p.pedido_id = dp.pedido_id
    JOIN usuario_empleado ue ON ue.usuario_empleado_id = p.usuario_empleado_id
    JOIN persona per ON ue.persona_id = per.persona_id
    WHERE dp.producto_id = :producto_id AND p.estado = 'entregado'

    UNION

    -- Salida por compra (venta al cliente)
    SELECT 
        'Salida (Venta)' AS tipo,
        TO_CHAR(c.fecha_compra, 'DD/MM/YYYY') AS fecha,
        TO_CHAR(c.fecha_compra, 'HH24:MI') AS hora,
        cli.nombre || ' ' || cli.apellido_paterno AS persona,
        dc.cantidad
    FROM compra c
    JOIN detalle_compra dc ON c.compra_id = dc.compra_id
    JOIN usuario_cliente uc ON c.usuario_cliente_id = uc.usuario_cliente_id
    JOIN persona cli ON uc.persona_id = cli.persona_id
    WHERE dc.producto_id = :producto_id

    UNION

    -- Entradas y salidas por devoluciones
    SELECT 
        CASE 
            WHEN d.tipo = 'cliente' THEN 'Entrada (Devolución de cliente)'
            WHEN d.tipo = 'proveedor' AND dd.reingresado_stock IS TRUE THEN 'Entrada (Reingreso por devolución)'
            ELSE 'Salida (Devolución a proveedor)'
        END AS tipo,
        TO_CHAR(d.fecha, 'DD/MM/YYYY') AS fecha,
        TO_CHAR(d.fecha, 'HH24:MI') AS hora,
        per.nombre || ' ' || per.apellido_paterno AS persona,
        dd.cantidad
    FROM devolucion d
    JOIN detalle_devolucion dd ON d.devolucion_id = dd.devolucion_id
    LEFT JOIN usuario_cliente uc ON d.usuario_cliente_id = uc.usuario_cliente_id
    LEFT JOIN usuario_empleado ue ON d.usuario_empleado_id = ue.usuario_empleado_id
    LEFT JOIN persona per ON
        (d.tipo = 'cliente' AND uc.persona_id = per.persona_id) OR
        (d.tipo = 'proveedor' AND ue.persona_id = per.persona_id)
    WHERE dd.producto_id = :producto_id

    ORDER BY fecha DESC, hora DESC
";



$stmt = $pdo->prepare($sql);
$stmt->execute([':producto_id' => $producto_id]);
$movimientos = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Detalle de Stock</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-4">
  <h3 class="mb-4">Reporte de Movimientos - Producto ID <?= $producto_id ?>: <span class="text-primary"><?= htmlspecialchars($nombreProducto) ?></span></h3>

  <?php if (empty($movimientos)): ?>
    <div class="alert alert-warning">No hay movimientos registrados para este producto.</div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table table-bordered table-hover text-center">
        <thead class="table-dark">
          <tr>
            <th>Tipo</th>
            <th>Fecha</th>
            <th>Hora</th>
            <th>Cliente / Empleado</th>
            <th>Cantidad</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($movimientos as $mov): ?>
            <tr>
              <td style="background-color: <?= str_starts_with($mov['tipo'], 'Entrada') ? '#d4edda' : '#ffe5b4' ?>;">
                <?= $mov['tipo'] ?>
              </td>
              <td><?= $mov['fecha'] ?></td>
              <td><?= $mov['hora'] ?></td>
              <td><?= htmlspecialchars($mov['persona']) ?></td>
              <td><?= $mov['cantidad'] ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

  <a href="gestion_existencias_stock.php" class="btn btn-secondary mt-3">Volver</a>
</div>
</body>
</html>

