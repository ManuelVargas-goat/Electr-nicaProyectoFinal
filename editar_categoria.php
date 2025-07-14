<?php
session_start();
include("config.php");

if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}

// Validación inicial del ID de categoría
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    // Si no hay ID o no es numérico, redirige con un mensaje de error
    $_SESSION['modal_message'] = "ID de categoría no válido o no proporcionado.";
    $_SESSION['modal_type'] = "danger";
    header("Location: gestion_catalogo_categorias.php");
    exit();
}

$categoria_id = (int)$_GET['id'];
$categoria = null; // Inicializar para evitar errores si no se encuentra la categoría

// Obtener la categoría actual para mostrar en el formulario
try {
    $stmt = $pdo->prepare("SELECT * FROM categoria WHERE categoria_id = :id");
    $stmt->execute([':id' => $categoria_id]);
    $categoria = $stmt->fetch(PDO::FETCH_ASSOC); // Usar FETCH_ASSOC para nombres de columna

    if (!$categoria) {
        // Si la categoría no existe, redirige con un mensaje de error
        $_SESSION['modal_message'] = "Categoría no encontrada para el ID proporcionado.";
        $_SESSION['modal_type'] = "danger";
        header("Location: gestion_catalogo_categorias.php");
        exit();
    }
} catch (PDOException $e) {
    $_SESSION['modal_message'] = "Error de base de datos al cargar la categoría: " . $e->getMessage();
    $_SESSION['modal_type'] = "danger";
    header("Location: gestion_catalogo_categorias.php");
    exit();
}


// Lógica para procesar el formulario cuando se envía (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');

    if (empty($nombre)) {
        // Si el nombre está vacío, establece un mensaje de error en sesión
        $_SESSION['modal_message'] = "El nombre de la categoría no puede estar vacío.";
        $_SESSION['modal_type'] = "danger";
        // Redirige de nuevo a esta misma página de edición para que el usuario vea el error
        header("Location: editar_categoria.php?id=" . $categoria_id);
        exit();
    }

    try {
        $update = $pdo->prepare("UPDATE categoria SET nombre = :nombre, descripcion = :descripcion WHERE categoria_id = :id");
        $update->execute([
            ':nombre' => $nombre,
            ':descripcion' => $descripcion,
            ':id' => $categoria_id
        ]);

        // Si la actualización es exitosa, establece el mensaje de éxito en sesión y redirige
        $_SESSION['modal_message'] = "Categoría '{$nombre}' (ID: {$categoria_id}) actualizada exitosamente.";
        $_SESSION['modal_type'] = "success";
        header("Location: gestion_catalogo_categorias.php"); // Redirige a la página principal de gestión
        exit();
    } catch (PDOException $e) {
        // Si ocurre un error de base de datos, establece el mensaje de error en sesión
        $_SESSION['modal_message'] = "Error al actualizar la categoría: " . $e->getMessage();
        $_SESSION['modal_type'] = "danger";
        // Redirige de nuevo a esta misma página de edición con el error
        header("Location: editar_categoria.php?id=" . $categoria_id);
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Categoría</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/estilos.css?v=<?= time(); ?>">
</head>
<body>
<div class="container mt-5">
    <h3 class="main-title">Editar Categoría</h3>

    <?php
    // Mostrar mensaje de error/éxito si hay uno, para el propio formulario de edición
    // Esto mostrará el mensaje en una alerta fija si se queda en esta página (ej: error de validación)
    if (isset($_SESSION['modal_message']) && !empty($_SESSION['modal_message']) && $_SERVER["REQUEST_METHOD"] === "GET") { // Solo mostrar si viene por GET (después de una redirección)
        $alertClass = 'alert-' . htmlspecialchars($_SESSION['modal_type']);
        echo '<div class="alert ' . $alertClass . ' alert-dismissible fade show" role="alert">';
        echo htmlspecialchars($_SESSION['modal_message']);
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>';
        echo '</div>';
        unset($_SESSION['modal_message']); // Limpia la sesión una vez mostrado
        unset($_SESSION['modal_type']);
    } elseif ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_SESSION['modal_message']) && !empty($_SESSION['modal_message'])) {
        // Si es POST y hay un mensaje, significa que el error ocurrió en el POST y nos quedamos en la misma página
        $alertClass = 'alert-' . htmlspecialchars($_SESSION['modal_type']);
        echo '<div class="alert ' . $alertClass . ' alert-dismissible fade show" role="alert">';
        echo htmlspecialchars($_SESSION['modal_message']);
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>';
        echo '</div>';
        unset($_SESSION['modal_message']); // Limpia la sesión una vez mostrado
        unset($_SESSION['modal_type']);
    }
    ?>

    <form method="POST" class="card p-4">
        <div class="mb-3">
            <label for="nombre" class="form-label">Nombre</label>
            <input type="text" name="nombre" id="nombre" class="form-control" value="<?= htmlspecialchars($categoria['nombre'] ?? '') ?>" required>
        </div>
        <div class="mb-3">
            <label for="descripcion" class="form-label">Descripción</label>
            <textarea name="descripcion" id="descripcion" class="form-control" rows="3"><?= htmlspecialchars($categoria['descripcion'] ?? '') ?></textarea>
        </div>
        <div class="d-flex justify-content-between align-items-center">
            <a href="gestion_catalogo_categorias.php" class="btn btn-secondary">Cancelar</a>
            <button type="submit" class="btn btn-primary">Actualizar</button>
        </div>
    </form>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>