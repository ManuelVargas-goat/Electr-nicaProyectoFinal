<?php
session_start();
include("config.php"); // Asegúrate de que 'config.php' contenga la conexión PDO.

if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}

// Verificar si se ha proporcionado un ID de producto válido
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: gestion_catalogo_productos.php?mensaje=" . urlencode("ID de producto no válido.") . "&tipo=danger");
    exit();
}

$producto_id = (int)$_GET['id'];

$mensaje = '';
$claseMensaje = '';

try {
    // Obtener datos del producto actual
    // Asegúrate de que la columna 'marca' exista en tu tabla 'producto'
    $stmtProducto = $pdo->prepare("SELECT producto_id, nombre, descripcion, precio, marca, categoria_id FROM producto WHERE producto_id = :id");
    $stmtProducto->execute([':id' => $producto_id]);
    $producto = $stmtProducto->fetch(PDO::FETCH_ASSOC);

    if (!$producto) {
        header("Location: gestion_catalogo_productos.php?mensaje=" . urlencode("Producto no encontrado.") . "&tipo=danger");
        exit();
    }

    // Obtener todas las categorías para el select
    $stmtCategorias = $pdo->query("SELECT categoria_id, nombre FROM categoria ORDER BY nombre");
    $categorias = $stmtCategorias->fetchAll(PDO::FETCH_ASSOC);

    // Lógica para actualizar producto
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $nombre = trim($_POST['nombre'] ?? '');
        $descripcion = trim($_POST['descripcion'] ?? '');
        $precio = filter_var($_POST['precio'] ?? '', FILTER_VALIDATE_FLOAT);
        $marca = trim($_POST['marca'] ?? '');
        $categoria_id_nuevo = filter_var($_POST['categoria_id'] ?? '', FILTER_VALIDATE_INT);

        // Validaciones
        if (empty($nombre) || $precio === false || $categoria_id_nuevo === false) {
            $mensaje = 'Por favor, complete todos los campos obligatorios y asegúrese de que el precio sea un número válido.';
            $claseMensaje = 'alert-danger';
        } elseif ($precio < 0) {
            $mensaje = 'El precio no puede ser un número negativo.';
            $claseMensaje = 'alert-danger';
        } else {
            $sqlUpdate = "UPDATE producto SET
                            nombre = :nombre,
                            descripcion = :descripcion,
                            precio = :precio,
                            marca = :marca,
                            categoria_id = :categoria_id
                          WHERE producto_id = :producto_id";

            $stmtUpdate = $pdo->prepare($sqlUpdate);
            $stmtUpdate->bindParam(':nombre', $nombre);
            $stmtUpdate->bindParam(':descripcion', $descripcion);
            $stmtUpdate->bindParam(':precio', $precio);
            $stmtUpdate->bindParam(':marca', $marca);
            $stmtUpdate->bindParam(':categoria_id', $categoria_id_nuevo, PDO::PARAM_INT);
            $stmtUpdate->bindParam(':producto_id', $producto_id, PDO::PARAM_INT);

            if ($stmtUpdate->execute()) {
                // Redireccionar a gestion_catalogo_productos.php con el mensaje de éxito
                header("Location: gestion_catalogo_productos.php?mensaje=" . urlencode("¡Producto actualizado con éxito!") . "&tipo=success");
                exit();
            } else {
                $mensaje = 'Error al actualizar el producto. Inténtelo de nuevo.';
                $claseMensaje = 'alert-danger';
            }
        }
    }
} catch (PDOException $e) {
    $mensaje = 'Error de base de datos: ' . $e->getMessage();
    $claseMensaje = 'alert-danger';
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Producto</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/estilos.css?v=<?= time(); ?>">
</head>
<body>
<div class="container mt-5">
    <h3 class="main-title">Editar Producto</h3>

    <?php if ($mensaje): // Este mensaje solo se mostrará si hubo un error en el POST ?>
        <div class="alert <?= $claseMensaje ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($mensaje) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
    <?php endif; ?>

    <form method="POST" class="card p-4">
        <div class="row">
            <div class="col-md-6 mb-3">
                <label for="nombre" class="form-label">Nombre del Producto:</label>
                <input type="text" name="nombre" id="nombre" class="form-control" value="<?= htmlspecialchars($producto['nombre'] ?? '') ?>" required>
            </div>
            <div class="col-md-6 mb-3">
                <label for="precio" class="form-label">Precio:</label>
                <input type="number" step="0.01" name="precio" id="precio" class="form-control" value="<?= htmlspecialchars($producto['precio'] ?? '') ?>" required min="0">
            </div>
        </div>
        <div class="mb-3">
            <label for="descripcion" class="form-label">Descripción:</label>
            <textarea name="descripcion" id="descripcion" class="form-control" rows="3"><?= htmlspecialchars($producto['descripcion'] ?? '') ?></textarea>
        </div>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label for="marca" class="form-label">Marca:</label>
                <input type="text" name="marca" id="marca" class="form-control" value="<?= htmlspecialchars($producto['marca'] ?? '') ?>">
            </div>
            <div class="col-md-6 mb-3">
                <label for="categoria_id" class="form-label">Categoría:</label>
                <select name="categoria_id" id="categoria_id" class="form-select" required>
                    <option value="">Seleccione una categoría</option>
                    <?php foreach ($categorias as $cat): ?>
                        <option value="<?= htmlspecialchars($cat['categoria_id']) ?>"
                            <?= (isset($producto['categoria_id']) && $producto['categoria_id'] == $cat['categoria_id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="d-flex justify-content-between align-items-center mt-3">
            <a href="gestion_catalogo_productos.php" class="btn btn-secondary">Cancelar</a>
            <button type="submit" class="btn btn-primary">Actualizar Producto</button>
        </div>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>