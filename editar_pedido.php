<?php
include("config.php");
session_start();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo "Pedido no válido.";
    exit();
}

$pedido_id = (int) $_GET['id'];

// Procesar edición
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fecha = $_POST['fecha'];
    $productos = $_POST['producto_id'];
    $cantidades = $_POST['cantidad'];

    try {
        $pdo->beginTransaction();

        // Actualizar fecha
        $stmt = $pdo->prepare("UPDATE pedido SET fecha_pedido = :fecha WHERE pedido_id = :id");
        $stmt->execute([':fecha' => $fecha, ':id' => $pedido_id]);

        // Eliminar detalles antiguos
        $pdo->prepare("DELETE FROM detalle_pedido WHERE pedido_id = :id")
            ->execute([':id' => $pedido_id]);

        // Insertar nuevos detalles
        $stmtDetalle = $pdo->prepare("INSERT INTO detalle_pedido (pedido_id, producto_id, cantidad, precio_unidad) VALUES (:pedido_id, :producto_id, :cantidad, 0)");

        foreach ($productos as $i => $prod_id) {
            $stmtDetalle->execute([
                ':pedido_id' => $pedido_id,
                ':producto_id' => $prod_id,
                ':cantidad' => $cantidades[$i]
            ]);
        }

        $pdo->commit();
        header("Location: gestion_existencias_pedidos.php?actualizado=1");
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        echo "Error al actualizar: " . $e->getMessage();
    }
}

// Obtener datos actuales
$stmtPedido = $pdo->prepare("SELECT fecha_pedido FROM pedido WHERE pedido_id = :id");
$stmtPedido->execute([':id' => $pedido_id]);
$pedido = $stmtPedido->fetch();

$stmtDetalles = $pdo->prepare("
    SELECT dp.producto_id, dp.cantidad, pr.nombre 
    FROM detalle_pedido dp
    JOIN producto pr ON dp.producto_id = pr.producto_id
    WHERE dp.pedido_id = :id");
$stmtDetalles->execute([':id' => $pedido_id]);
$detalles = $stmtDetalles->fetchAll();

// Lista de productos disponibles
$productosDisponibles = $pdo->query("SELECT producto_id, nombre FROM producto ORDER BY nombre")->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Pedido</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-4">
    <h3>Editar Pedido #<?= $pedido_id ?></h3>
    <form method="POST">
        <div class="mb-3">
            <label for="fecha" class="form-label">Fecha del Pedido</label>
            <input type="date" id="fecha" name="fecha" class="form-control" required value="<?= date('Y-m-d', strtotime($pedido['fecha_pedido'])) ?>">
        </div>

        <h5>Productos</h5>
        <div id="productos-container">
            <?php foreach ($detalles as $i => $detalle): ?>
                <div class="row mb-2">
                    <div class="col-md-6">
                        <select name="producto_id[]" class="form-select" required>
                            <?php foreach ($productosDisponibles as $producto): ?>
                                <option value="<?= $producto['producto_id'] ?>" <?= $producto['producto_id'] == $detalle['producto_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($producto['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <input type="number" name="cantidad[]" class="form-control" min="1" value="<?= $detalle['cantidad'] ?>" required>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-success">Guardar Cambios</button>
            <a href="gestion_existencias_pedidos.php" class="btn btn-secondary">Cancelar</a>
        </div>
    </form>
</div>
</body>
</html>
