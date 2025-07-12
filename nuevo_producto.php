<?php
session_start();
include("config.php"); // Asegúrate de que 'config.php' contenga la conexión PDO.

if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}

$error = '';

// Obtener todas las categorías para el dropdown
$categorias = [];
try {
    $stmtCategorias = $pdo->query("SELECT categoria_id, nombre FROM categoria ORDER BY nombre");
    $categorias = $stmtCategorias->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error al cargar categorías: " . $e->getMessage();
}

// Inicializar variables para mantener los valores en el formulario si hay un error
$nombre = '';
$descripcion = '';
$precio = '';
$marca = '';
$descontinuado = false; // Valor por defecto para el checkbox
$categoria_id = '';
$rutaimagen = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $precio = filter_var($_POST['precio'] ?? '', FILTER_VALIDATE_FLOAT);
    $marca = trim($_POST['marca'] ?? '');

    // Corregir la asignación del valor booleano para 'descontinuado'
    // Convertimos explícitamente a 1 si está marcado, 0 si no.
    $descontinuado = isset($_POST['descontinuado']) ? 1 : 0;

    $categoria_id = filter_var($_POST['categoria_id'] ?? '', FILTER_VALIDATE_INT);

    // Stock inicial se establecerá a 0 por defecto al insertar
    $stock_inicial_default = 0;
    $directorio = 'ecommerce/imgs/';
    $tipoArchivo = $_FILES['imagen']['type'];
    $rutaimagen = $directorio . $nombre. ".png";

    if (empty($nombre)) {
        $error = "El nombre del producto es obligatorio.";
    } elseif ($precio === false || $precio < 0) {
        $error = "El precio debe ser un número válido y no negativo.";
    } elseif ($categoria_id === false || $categoria_id <= 0) {
        $error = "Debe seleccionar una categoría válida.";
    } elseif ($tipoArchivo != 'image/png') {
        $error = "La imagen no es de tipo png.";
    } else {
        try {
            move_uploaded_file($_FILES["imagen"]["tmp_name"],$rutaimagen);
            
            $stmt = $pdo->prepare("INSERT INTO producto (nombre, descripcion, precio, unidad_stock, marca, descontinuado, categoria_id, ruta_imagen) VALUES (:nombre, :descripcion, :precio, :unidad_stock, :marca, :descontinuado, :categoria_id, :ruta_imagen)");
            $stmt->execute([
                ':nombre' => $nombre,
                ':descripcion' => $descripcion,
                ':precio' => $precio,
                ':unidad_stock' => $stock_inicial_default, // Establece stock inicial a 0
                ':marca' => $marca,
                ':descontinuado' => $descontinuado, // Ahora será 0 o 1
                ':categoria_id' => $categoria_id,
                ':ruta_imagen' => $rutaimagen
            ]);
            header("Location: gestion_catalogo_productos.php?status=success_add");
            exit();
        } catch (PDOException $e) {
            $error = "Error al guardar el producto: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registrar Nuevo Producto</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/estilos.css?v=<?= time(); ?>">
    <style>
        /* Estilos para el tema oscuro */
        body.dark-mode {
            background-color: #343a40; /* Fondo oscuro */
            color: #f8f9fa; /* Texto claro */
        }
        body.dark-mode .container { /* Asegurar que el contenedor principal también se adapte */
            background-color: #343a40;
        }
        body.dark-mode .main-title {
            color: #f8f9fa;
        }
        body.dark-mode .card {
            background-color: #545b62; /* Tarjetas oscuras */
            border-color: #6c757d;
        }
        body.dark-mode .card-header {
            background-color: #6c757d !important; /* Header de tarjetas más oscuro */
            color: #f8f9fa !important;
        }
        body.dark-mode .form-control, body.dark-mode .form-select {
            background-color: #6c757d;
            color: #f8f9fa;
            border-color: #495057;
        }
        body.dark-mode .form-control::placeholder {
            color: #ced4da;
        }
        body.dark-mode .form-control:focus, body.dark-mode .form-select:focus {
            background-color: #7a8288;
            color: #f8f9fa;
            border-color: #889299;
            box-shadow: 0 0 0 0.25rem rgba(108, 117, 125, 0.25);
        }
    </style>
</head>
<body>
<div class="container mt-5">
    <h3 class="main-title">Registrar Nuevo Producto</h3>

    <?php if (isset($error) && !empty($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" class="card p-4" enctype="multipart/form-data">
        <div class="mb-3">
            <label for="nombre" class="form-label">Nombre del Producto</label>
            <input type="text" name="nombre" id="nombre" class="form-control" value="<?= htmlspecialchars($nombre) ?>" required>
        </div>
        <div class="mb-3">
            <label for="descripcion" class="form-label">Descripción</label>
            <textarea name="descripcion" id="descripcion" class="form-control" rows="3"><?= htmlspecialchars($descripcion) ?></textarea>
        </div>
        <div class="row mb-3">
            <div class="col-md-6">
                <label for="precio" class="form-label">Precio</label>
                <input type="number" name="precio" id="precio" class="form-control" step="0.01" min="0" value="<?= htmlspecialchars($precio) ?>" required>
            </div>
            <div class="col-md-6">
                <label for="marca" class="form-label">Marca</label>
                <input type="text" name="marca" id="marca" class="form-control" value="<?= htmlspecialchars($marca) ?>">
            </div>
        </div>
        <div class="mb-3">
            <label for="categoria_id" class="form-label">Categoría</label>
            <select name="categoria_id" id="categoria_id" class="form-select" required>
                <option value="" disabled selected>Seleccione una categoría</option>
                <?php foreach ($categorias as $cat): ?>
                    <option value="<?= htmlspecialchars($cat['categoria_id']) ?>" <?= ($categoria_id == $cat['categoria_id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cat['nombre']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" name="descontinuado" id="descontinuado" <?= $descontinuado ? 'checked' : '' ?>>
            <label class="form-check-label" for="descontinuado">
                Producto descontinuado
            </label>
        </div>
        <div class="mb-4">
            <label for="imagen" class="form-label">Imagen</label>
            <input type="file" name="imagen" id="imagen" class="form-control" required>
        </div>
        <div class="d-flex justify-content-between">
            <a href="gestion_catalogo_productos.php" class="btn btn-secondary">Cancelar</a>
            <button type="submit" class="btn btn-primary">Registrar Producto</button>
        </div>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Lógica para el Modo Oscuro (se aplica al cargar la página si la preferencia está guardada)
    document.addEventListener('DOMContentLoaded', (event) => {
        const body = document.body;
        if (localStorage.getItem('darkMode') === 'enabled') {
            body.classList.add('dark-mode');
        }
    });
</script>
</body>
</html>