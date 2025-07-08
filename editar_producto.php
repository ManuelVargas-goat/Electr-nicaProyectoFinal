<?php
session_start(); // Inicia la sesión al principio
include("config.php"); // Asegúrate de que 'config.php' contenga la conexión PDO.

// 1. Verificar si el usuario está logueado
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}

// Obtener datos del usuario logueado para el sidebar (aunque no se mostrará, la lógica aún puede necesitar estos datos si se reutiliza)
$usuario_actual = $_SESSION['usuario'];
$stmt_user = $pdo->prepare("SELECT per.nombre, per.apellido_paterno, r.nombre AS rol FROM usuario_empleado ue JOIN persona per ON ue.persona_id = per.persona_id JOIN rol r ON ue.rol_id = r.rol_id WHERE ue.usuario = :usuario");
$stmt_user->execute([':usuario' => $usuario_actual]);
$datosUsuario = $stmt_user->fetch(PDO::FETCH_ASSOC);

$nombreCompleto = 'Usuario Desconocido';
$rolUsuario = '';

if ($datosUsuario) {
    $nombreCompleto = $datosUsuario['nombre'] . ' ' . $datosUsuario['apellido_paterno'];
    $rolUsuario = strtolower($datosUsuario['rol']);
}

// Inicializar variables para el formulario
$producto = null;
$mensaje = $_GET['mensaje'] ?? '';
$tipoMensaje = $_GET['tipo'] ?? '';

// 2. Procesar el ID del producto (ya sea de GET para cargar o de POST para guardar)
$producto_id = $_GET['id'] ?? null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['producto_id'])) {
    $producto_id = $_POST['producto_id']; // Si es POST, toma el ID del campo oculto
}

if (!filter_var($producto_id, FILTER_VALIDATE_INT)) {
    header("Location: gestion_catalogo_productos.php?mensaje=" . urlencode("ID de producto no válido para editar.") . "&tipo=danger");
    exit();
}

try {
    // 3. Si es una solicitud GET o si es POST pero se necesita recargar el formulario después de un error
    //    Obtener los datos del producto para mostrarlos en el formulario
    $stmt = $pdo->prepare("SELECT producto_id, nombre, descripcion, precio, marca, descontinuado, categoria_id FROM producto WHERE producto_id = :id");
    $stmt->execute([':id' => $producto_id]);
    $producto = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$producto) {
        header("Location: gestion_catalogo_productos.php?mensaje=" . urlencode("Producto no encontrado.") . "&tipo=danger");
        exit();
    }

    // 4. Obtener todas las categorías para el campo de selección
    $stmtCategorias = $pdo->query("SELECT categoria_id, nombre FROM categoria ORDER BY nombre");
    $categorias = $stmtCategorias->fetchAll(PDO::FETCH_ASSOC);

    // 5. Manejar el envío del formulario (POST) para actualizar el producto
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $nombre = trim($_POST['nombre'] ?? '');
        $descripcion = trim($_POST['descripcion'] ?? '');
        $precio = filter_var($_POST['precio'] ?? '', FILTER_VALIDATE_FLOAT);
        $marca = trim($_POST['marca'] ?? '');
        $categoria_id = filter_var($_POST['categoria_id'] ?? '', FILTER_VALIDATE_INT);
        $descontinuado = isset($_POST['descontinuado']) ? 1 : 0; 
        $errores = [];

        if (empty($nombre)) {
            $errores[] = "El nombre del producto es obligatorio.";
        }
        if (empty($descripcion)) {
            $errores[] = "La descripción es obligatoria.";
        }
        if ($precio === false || $precio <= 0) {
            $errores[] = "El precio debe ser un número válido mayor que cero.";
        }
        if (empty($marca)) {
            $errores[] = "La marca es obligatoria.";
        }
        if ($categoria_id === false || $categoria_id <= 0) {
            $errores[] = "Debe seleccionar una categoría válida.";
        }

        if (empty($errores)) {
            // Actualizar el producto en la base de datos
            $updateStmt = $pdo->prepare("UPDATE producto SET nombre = :nombre, descripcion = :descripcion, precio = :precio, marca = :marca, categoria_id = :categoria_id, descontinuado = :descontinuado WHERE producto_id = :producto_id");
            $updateStmt->execute([
                ':nombre' => $nombre,
                ':descripcion' => $descripcion,
                ':precio' => $precio,
                ':marca' => $marca,
                ':categoria_id' => $categoria_id,
                ':descontinuado' => $descontinuado,
                ':producto_id' => $producto_id
            ]);

            header("Location: gestion_catalogo_productos.php?mensaje=" . urlencode("Producto actualizado con éxito.") . "&tipo=success");
            exit();

        } else {
            // Si hay errores, mostrar los mensajes y recargar el formulario con los datos enviados
            $mensaje = implode("<br>", $errores);
            $tipoMensaje = "danger";
            // Para que los datos ingresados persistan en el formulario a pesar del error
            $producto['nombre'] = $nombre;
            $producto['descripcion'] = $descripcion;
            $producto['precio'] = $_POST['precio']; // Usar el valor original del POST
            $producto['marca'] = $marca;
            $producto['categoria_id'] = $categoria_id;
            $producto['descontinuado'] = $descontinuado;
        }
    }

} catch (PDOException $e) {
    error_log("Error en editar_producto.php: " . $e->getMessage());
    $mensaje = "Error de base de datos: " . $e->getMessage();
    $tipoMensaje = "danger";
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Producto</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/estilos.css?v=<?= time(); ?>">
    <style>
        body {
            background-color: #f8f9fa; /* Color de fondo general de la página */
        }
        .form-label {
            font-weight: bold;
        }
        .form-section {
            background-color: #ffffff; /* Fondo blanco para el contenedor del formulario */
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1); /* Sutil sombra para destacarlo */
            margin-top: 20px; /* Margen superior para que no esté pegado al borde */
        }
        .container-fluid {
            padding-left: 20px; /* Ajuste de padding si es necesario */
            padding-right: 20px;
        }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row justify-content-center"> <div class="col-md-8 content"> <h3 class="main-title mb-4 text-center">Editar Producto</h3>

            <?php if (!empty($mensaje)): ?>
                <div class="alert alert-<?= htmlspecialchars($tipoMensaje) ?> alert-dismissible fade show" role="alert">
                    <?= $mensaje ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
                </div>
            <?php endif; ?>

            <?php if ($producto): ?>
            <div class="form-section">
                <form action="editar_producto.php?id=<?= $producto['producto_id'] ?>" method="POST">
                    <input type="hidden" name="producto_id" value="<?= htmlspecialchars($producto['producto_id']) ?>">

                    <div class="mb-3">
                        <label for="nombre" class="form-label">Nombre del Producto</label>
                        <input type="text" class="form-control" id="nombre" name="nombre" value="<?= htmlspecialchars($producto['nombre']) ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="descripcion" class="form-label">Descripción</label>
                        <textarea class="form-control" id="descripcion" name="descripcion" rows="3" required><?= htmlspecialchars($producto['descripcion']) ?></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="precio" class="form-label">Precio (S/)</label>
                            <input type="number" step="0.01" class="form-control" id="precio" name="precio" value="<?= htmlspecialchars($producto['precio']) ?>" required min="0.01">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="marca" class="form-label">Marca</label>
                            <input type="text" class="form-control" id="marca" name="marca" value="<?= htmlspecialchars($producto['marca']) ?>" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="categoria_id" class="form-label">Categoría</label>
                        <select class="form-select" id="categoria_id" name="categoria_id" required>
                            <option value="">Seleccione una categoría</option>
                            <?php foreach ($categorias as $cat): ?>
                                <option value="<?= htmlspecialchars($cat['categoria_id']) ?>"
                                    <?= ($cat['categoria_id'] == $producto['categoria_id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="descontinuado" name="descontinuado" value="1" <?= $producto['descontinuado'] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="descontinuado">Producto descontinuado</label>
                    </div>

                    <div class="d-flex justify-content-end mt-4">
                        <a href="gestion_catalogo_productos.php" class="btn btn-secondary me-2">Cancelar</a>
                        <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                    </div>
                </form>
            </div>
            <?php else: ?>
                <div class="alert alert-info text-center form-section">Cargando datos del producto o producto no encontrado.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>