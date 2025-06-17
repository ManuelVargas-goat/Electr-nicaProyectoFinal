<?php
include 'config.php';

// Manejo de búsqueda
try {
    $searchTerm = '';
    if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['buscar'])) {
        $searchTerm = trim($_GET['buscar']);
    }

    // Filtrar productos por búsqueda
    $sql = "SELECT producto_id, nombre, descripcion, descontinuado
            FROM producto 
            WHERE nombre ILIKE :searchTerm OR categoria ILIKE :searchTerm OR estado ILIKE :searchTerm";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['searchTerm' => '%' . $searchTerm . '%']);
    $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Error en la base de datos: " . $e->getMessage());
}

// Manejo de creación de producto
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['crear'])) {
    $nombre = trim($_POST['nombre']);
    $descripcion = trim($_POST['descripcion']);
    $categoria = $_POST['categoria'];
    $estado = $_POST['estado'];
    $proveedor = $_POST['proveedor'];

    try {
        $sql = "INSERT INTO productos (nombre, descripcion, categoria, estado, proveedor) VALUES (:nombre, :descripcion, :categoria, :estado, :proveedor)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'nombre' => $nombre,
            'descripcion' => $descripcion,
            'categoria' => $categoria,
            'estado' => $estado,
            'proveedor' => $proveedor
        ]);

        header('Location: productos.php');
        exit();
    } catch (PDOException $e) {
        die("Error al crear el producto: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Productos</title>
    <link rel="stylesheet" href="css/estilos.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
</head>
<body>
    <div class="container mt-5">
        <h2 class="text-center">Gestionar Productos</h2>

        <!-- Formulario de búsqueda -->
        <form action="productos.php" method="GET" class="mb-4">
            <div class="form-row">
                <div class="col">
                    <input type="text" class="form-control" name="buscar" placeholder="Buscar por nombre, categoría o estado" value="<?php echo htmlspecialchars($searchTerm); ?>">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary">Buscar</button>
                </div>
            </div>
        </form>

        <!-- Formulario para crear producto -->
        <form action="productos.php" method="POST" class="mb-4">
            <div class="form-group">
                <label for="nombre">Nombre:</label>
                <input type="text" class="form-control" id="nombre" name="nombre" required>
            </div>
            <div class="form-group">
                <label for="descripcion">Descripción:</label>
                <textarea class="form-control" id="descripcion" name="descripcion" required></textarea>
            </div>
            <div class="form-group">
                <label for="categoria">Categoría:</label>
                <input type="text" class="form-control" id="categoria" name="categoria" required>
            </div>
            <div class="form-group">
                <label for="estado">Estado:</label>
                <select class="form-control" id="estado" name="estado" required>
                    <option value="activo">Activo</option>
                    <option value="inactivo">Inactivo</option>
                </select>
            </div>
            <div class="form-group">
                <label for="proveedor">Proveedor:</label>
                <input type="text" class="form-control" id="proveedor" name="proveedor" required>
            </div>
            <button type="submit" name="crear" class="btn btn-success">Crear Producto</button>
        </form>

        <!-- Tabla de productos -->
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>Descripción</th>
                    <th>Categoría</th>
                    <th>Estado</th>
                    <th>Proveedor</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($productos as $producto): ?>
                    <tr>
                        <td><?= $producto['id']; ?></td>
                        <td><?= htmlspecialchars($producto['nombre']); ?></td>
                        <td><?= htmlspecialchars($producto['descripcion']); ?></td>
                        <td><?= htmlspecialchars($producto['categoria']); ?></td>
                        <td><?= ucfirst($producto['estado']); ?></td>
                        <td><?= htmlspecialchars($producto['proveedor']); ?></td>
                        <td>
                            <a href="editar_producto.php?id=<?= $producto['id']; ?>" class="btn btn-primary">Editar</a>
                            <a href="eliminar_producto.php?id=<?= $producto['id']; ?>" class="btn btn-danger" onclick="return confirm('¿Estás seguro de eliminar este producto?');">Eliminar</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <a class="btn btn-warning" href="principal.php">Inicio</a>
    </div>
</body>
</html>
