<?php
session_start(); // Inicia la sesión al principio
include("config.php"); // Asegúrate de que 'config.php' contenga la conexión PDO.

if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}

// Inicializar variables para mantener los valores en el formulario si hay un error
$nombre = '';
$descripcion = '';
$precio = '';
$marca = '';
$descontinuado = false; // Valor por defecto para el checkbox
$categoria_id = '';
$proveedor_id = ''; // <--- NUEVO: Inicializar variable para el proveedor
$rutaimagen = '';

// Obtener todas las categorías para el dropdown
$categorias = [];
try {
    $stmtCategorias = $pdo->query("SELECT categoria_id, nombre FROM categoria ORDER BY nombre");
    $categorias = $stmtCategorias->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error al cargar categorías en nuevo_producto.php: " . $e->getMessage());
    $_SESSION['modal_message'] = "Error interno: No se pudieron cargar las categorías.";
    $_SESSION['modal_type'] = "danger";
}

// <--- NUEVO: Obtener todos los proveedores para el dropdown
$proveedores = [];
try {
    $stmtProveedores = $pdo->query("SELECT proveedor_id, nombre_empresa FROM proveedor ORDER BY nombre_empresa");
    $proveedores = $stmtProveedores->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error al cargar proveedores en nuevo_producto.php: " . $e->getMessage());
    $_SESSION['modal_message'] = "Error interno: No se pudieron cargar los proveedores.";
    $_SESSION['modal_type'] = "danger";
}

// Variable para errores de validación en este formulario
$formError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $precio = filter_var($_POST['precio'] ?? '', FILTER_VALIDATE_FLOAT);
    $marca = trim($_POST['marca'] ?? '');

    $descontinuado = isset($_POST['descontinuado']) ? 1 : 0; // Convertimos a 1 si marcado, 0 si no.

    $categoria_id = filter_var($_POST['categoria_id'] ?? '', FILTER_VALIDATE_INT);
    $proveedor_id = filter_var($_POST['proveedor_id'] ?? '', FILTER_VALIDATE_INT); // <--- NUEVO: Obtener ID del proveedor

    // Stock inicial se establecerá a 0 por defecto al insertar
    $stock_inicial_default = 0;
    
    $directorio = 'ecommerce/imgs/'; // Asegúrate de que esta ruta sea correcta y accesible para escritura
    // Asegúrate de que el directorio exista y tenga permisos de escritura (0755 o 0777 temporalmente para prueba)
    if (!is_dir($directorio)) {
        mkdir($directorio, 0755, true); // Crea el directorio recursivamente si no existe
    }

    $nombreArchivoImagen = ''; // Para almacenar el nombre del archivo final
    $target_file = ''; // Ruta completa del archivo en el servidor

    // Lógica para la carga de la imagen
    if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
        // Generar un nombre de archivo único para evitar colisiones
        $ext = pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION);
        $nombreArchivoImagen = uniqid('prod_', true) . '.' . $ext; // Nombre único para la imagen
        $target_file = $directorio . $nombreArchivoImagen;
        $rutaimagen = $target_file; // Guardamos la ruta relativa en la DB

        $check = getimagesize($_FILES['imagen']['tmp_name']);
        if($check === false) {
            $formError = "El archivo no es una imagen válida.";
        } elseif ($_FILES['imagen']['size'] > 500000) { // Limitar a 500KB
            $formError = "Lo sentimos, tu archivo es demasiado grande (máx. 500KB).";
        } elseif (!in_array(strtolower($ext), ['png', 'jpg', 'jpeg', 'gif'])) { // Tipos de archivo permitidos, case-insensitive
            $formError = "Solo se permiten archivos JPG, JPEG, PNG y GIF.";
        }
    } else {
        $formError = "Debe seleccionar una imagen para el producto.";
        // Si el error es UPLOAD_ERR_NO_FILE (4), puede ser el caso más común si no se selecciona.
        if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_NO_FILE) {
             $formError = "Debe seleccionar una imagen para el producto.";
        } else if (isset($_FILES['imagen'])) {
            $formError = "Error al subir la imagen: " . $_FILES['imagen']['error'] . ". Intente de nuevo.";
        }
    }

    // Validaciones del formulario
    if (empty($nombre)) {
        $formError = "El nombre del producto es obligatorio.";
    } elseif ($precio === false || $precio < 0) {
        $formError = "El precio debe ser un número válido y no negativo.";
    } elseif ($categoria_id === false || $categoria_id <= 0) {
        $formError = "Debe seleccionar una categoría válida.";
    } elseif ($proveedor_id === false || $proveedor_id <= 0) { // <--- NUEVA VALIDACIÓN PARA EL PROVEEDOR
        $formError = "Debe seleccionar un proveedor válido.";
    }


    // Si no hay errores en el formulario ni en la carga de imagen
    if (empty($formError)) {
        // <--- INICIO DE TRANSACCIÓN
        $pdo->beginTransaction();
        try {
            // Mover el archivo subido a la ubicación final
            if (!move_uploaded_file($_FILES["imagen"]["tmp_name"], $target_file)) {
                $formError = "Error al subir la imagen. Verifique los permisos de la carpeta 'ecommerce/imgs/'.";
                $pdo->rollBack(); // Deshacer si la imagen no se puede mover
            } else {
                // Insertar el producto en la base de datos
                $stmt = $pdo->prepare("INSERT INTO producto (nombre, descripcion, precio, unidad_stock, marca, descontinuado, categoria_id, ruta_imagen)
                                       VALUES (:nombre, :descripcion, :precio, :unidad_stock, :marca, :descontinuado, :categoria_id, :ruta_imagen)
                                       RETURNING producto_id"); // <-- IMPORTANTE: RETURNING para obtener el ID
                $stmt->execute([
                    ':nombre' => $nombre,
                    ':descripcion' => $descripcion,
                    ':precio' => $precio,
                    ':unidad_stock' => $stock_inicial_default, // Establece stock inicial a 0
                    ':marca' => $marca,
                    ':descontinuado' => $descontinuado, // Será 0 o 1
                    ':categoria_id' => $categoria_id,
                    ':ruta_imagen' => $rutaimagen // La ruta relativa que se guardará
                ]);
                $new_producto_id = $stmt->fetchColumn(); // Obtener el ID del producto recién insertado

                // <--- NUEVO: Insertar la relación en la tabla 'proveedor_producto'
                $stmtProveedorProducto = $pdo->prepare("INSERT INTO proveedor_producto (proveedor_id, producto_id) VALUES (:proveedor_id, :producto_id)");
                $stmtProveedorProducto->execute([
                    ':proveedor_id' => $proveedor_id,
                    ':producto_id' => $new_producto_id
                ]);

                $pdo->commit(); // Confirmar la transacción si todo fue exitoso
                // Establecer mensaje de éxito en la sesión y redirigir
                $_SESSION['modal_message'] = "Producto registrado con éxito.";
                $_SESSION['modal_type'] = "success";
                header("Location: gestion_catalogo_productos.php");
                exit();
            }
        } catch (PDOException $e) {
            $pdo->rollBack(); // Deshacer toda la transacción si algo falla
            // Si la imagen ya se movió y la DB falló, podrías intentar borrar la imagen aquí para limpieza
            if (!empty($target_file) && file_exists($target_file)) {
                unlink($target_file); // Eliminar la imagen si la inserción en DB falla
            }
            error_log("Error al guardar el producto o proveedor_producto en DB: " . $e->getMessage()); // Para depuración
            $formError = "Error de base de datos al registrar el producto y su proveedor."; // Mensaje amigable para el usuario
        } catch (Exception $e) {
            $pdo->rollBack();
            if (!empty($target_file) && file_exists($target_file)) {
                unlink($target_file);
            }
            error_log("Error inesperado: " . $e->getMessage());
            $formError = "Ocurrió un error inesperado al registrar el producto.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrar Nuevo Producto</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/estilos.css?v=<?= time(); ?>">
    <style>
        /* Estilos para el tema oscuro - Puedes mover esto a estilos.css si lo usas globalmente */
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

    <?php if (!empty($formError)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($formError) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
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
        <div class="mb-3">
            <label for="proveedor_id" class="form-label">Proveedor Principal</label>
            <select name="proveedor_id" id="proveedor_id" class="form-select" required>
                <option value="" disabled selected>Seleccione un proveedor</option>
                <?php foreach ($proveedores as $prov): ?>
                    <option value="<?= htmlspecialchars($prov['proveedor_id']) ?>" <?= ($proveedor_id == $prov['proveedor_id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($prov['nombre_empresa']) ?>
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
            <label for="imagen" class="form-label">Imagen (Solo PNG, JPG, JPEG, GIF - Max 500KB)</label>
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