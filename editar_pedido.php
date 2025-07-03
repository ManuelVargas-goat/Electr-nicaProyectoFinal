<?php
session_start();
include("config.php");

if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}

$pedidoId = $_GET['id'] ?? null;
if (!$pedidoId || !is_numeric($pedidoId)) {
    die("ID de pedido no válido.");
}

$mensaje = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $proveedorIds = $_POST['proveedor_id'];
    $cantidades = $_POST['cantidad'];
    $productoIds = $_POST['producto_id'];

    try {
        $pdo->beginTransaction();

        $deleteStmt = $pdo->prepare("DELETE FROM detalle_pedido WHERE pedido_id = :pedido_id");
        $deleteStmt->execute([':pedido_id' => $pedidoId]);

        $insertStmt = $pdo->prepare("INSERT INTO detalle_pedido 
            (pedido_id, producto_id, proveedor_id, cantidad, precio_unidad)
            VALUES (:pedido_id, :producto_id, :proveedor_id, :cantidad, 
                (SELECT precio FROM producto WHERE producto_id = :producto_id))");

        for ($i = 0; $i < count($productoIds); $i++) {
            $insertStmt->execute([
                ':pedido_id' => $pedidoId,
                ':producto_id' => $productoIds[$i],
                ':proveedor_id' => $proveedorIds[$i],
                ':cantidad' => $cantidades[$i]
            ]);
        }

        $pdo->commit();
        header("Location: gestion_existencias_pedidos.php");
        exit();
        $mensaje = "Pedido actualizado correctamente.";
    } catch (Exception $e) {
        $pdo->rollBack();
        $mensaje = "Error al actualizar: " . $e->getMessage();
    }
}

    $sql = "
    SELECT dp.producto_id, p.nombre AS producto, dp.cantidad, dp.precio_unidad, 
        pr.proveedor_id, pr.nombre_empresa
    FROM detalle_pedido dp
    JOIN producto p ON dp.producto_id = p.producto_id
    JOIN proveedor pr ON dp.proveedor_id = pr.proveedor_id
    WHERE dp.pedido_id = :pedido_id
    ";
$stmt = $pdo->prepare($sql);
$stmt->execute([':pedido_id' => $pedidoId]);
$productosPedido = $stmt->fetchAll(PDO::FETCH_ASSOC);

$proveedores = $pdo->query("SELECT proveedor_id, nombre_empresa FROM proveedor ORDER BY nombre_empresa")->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Editar Pedido</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-5">
  <h3 class="mb-4">Editar Pedido #<?= htmlspecialchars($pedidoId) ?></h3>

  <?php if (!empty($mensaje)): ?>
    <div class="alert alert-info"><?= htmlspecialchars($mensaje) ?></div>
  <?php endif; ?>

  <form method="POST" class="bg-white border rounded shadow p-4" id="form-edicion">
    <div id="productos-container">
      <?php foreach ($productosPedido as $index => $item): ?>
        <div class="card mb-3 p-3 producto-linea">
          <div class="row g-2 align-items-end">
            <div class="col-md-3">
              <label class="form-label">Producto</label>
              <input type="text" class="form-control" value="<?= htmlspecialchars($item['producto']) ?>" readonly>
              <input type="hidden" name="producto_id[]" value="<?= $item['producto_id'] ?>">
            </div>
            <div class="col-md-2">
              <label class="form-label">Proveedor</label>
              <select name="proveedor_id[]" class="form-select" required>
                <?php foreach ($proveedores as $prov): ?>
                  <option value="<?= $prov['proveedor_id'] ?>" <?= $item['proveedor_id'] == $prov['proveedor_id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($prov['nombre_empresa']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-2">
              <label class="form-label">Cantidad</label>
              <input type="number" name="cantidad[]" class="form-control cantidad-input" value="<?= $item['cantidad'] ?>" min="1" required>
            </div>
            <div class="col-md-2">
              <label class="form-label">Precio Unitario (S/)</label>
              <input type="text" class="form-control precio-unitario" value="<?= number_format($item['precio_unidad'], 2) ?>" readonly>
            </div>
            <div class="col-md-2">
              <label class="form-label">Subtotal (S/)</label>
              <input type="text" class="form-control subtotal-linea" readonly>
            </div>
            <div class="col-md-1 text-end">
              <button type="button" class="btn btn-danger btn-sm eliminar-linea">✕</button>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
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

<script>
  function recalcularTotales() {
    let total = 0;

    document.querySelectorAll('.producto-linea').forEach(linea => {
      const cantidad = parseFloat(linea.querySelector('.cantidad-input')?.value || 0);
      const precio = parseFloat(linea.querySelector('.precio-unitario')?.value || 0);
      const subtotal = cantidad * precio;

      linea.querySelector('.subtotal-linea').value = subtotal.toFixed(2);
      total += subtotal;
    });

    document.getElementById('total-general').value = total.toFixed(2);
  }

  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.cantidad-input').forEach(input => {
      input.addEventListener('input', recalcularTotales);
    });

    document.querySelectorAll('.eliminar-linea').forEach(btn => {
      btn.addEventListener('click', e => {
        const card = e.target.closest('.producto-linea');
        card.remove();
        recalcularTotales();
      });
    });

    recalcularTotales();
  });
</script>

</body>
</html>