<?php
session_start();
include("config.php"); // Asegúrate de tener tu conexión PDO aquí

if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}

// Lógica para procesar el formulario cuando se envía
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nombre = trim($_POST['nombre'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');

    if (empty($nombre)) {
        $_SESSION['modal_message'] = "El nombre de la categoría no puede estar vacío.";
        $_SESSION['modal_type'] = "danger";
        header("Location: nueva_categoria.php"); // Redirige de nuevo al formulario con el error
        exit();
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO categoria (nombre, descripcion) VALUES (:nombre, :descripcion)");
        $stmt->execute([':nombre' => $nombre, ':descripcion' => $descripcion]);

        $_SESSION['modal_message'] = "Categoría '{$nombre}' agregada exitosamente.";
        $_SESSION['modal_type'] = "success";
        header("Location: gestion_catalogo_categorias.php"); // Redirige a la página de gestión
        exit();
    } catch (PDOException $e) {
        // Manejo de errores de la base de datos
        $_SESSION['modal_message'] = "Error al agregar la categoría: " . $e->getMessage();
        $_SESSION['modal_type'] = "danger";
        header("Location: nueva_categoria.php"); // O a gestion_catalogo_categorias.php si prefieres mostrar el error allí
        exit();
    }
}
// ... (resto del código HTML para el formulario de nueva categoría si lo tienes)
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Nueva Categoría</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/estilos.css?v=<?= time(); ?>">
    <style>
        /* Estilos específicos para este formulario si los necesitas */
    </style>
</head>
<body>
    <div class="container mt-5">
        <div class="card">
            <div class="card-header">
                <h3>Agregar Nueva Categoría</h3>
            </div>
            <div class="card-body">
                <?php
                // Mostrar mensaje de error/éxito si hay uno, para el propio formulario de nueva categoría
                if (isset($_SESSION['modal_message']) && !empty($_SESSION['modal_message'])) {
                    $alertClass = 'alert-' . htmlspecialchars($_SESSION['modal_type']);
                    echo '<div class="alert ' . $alertClass . ' alert-dismissible fade show" role="alert">';
                    echo htmlspecialchars($_SESSION['modal_message']);
                    echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>';
                    echo '</div>';
                    unset($_SESSION['modal_message']);
                    unset($_SESSION['modal_type']);
                }
                ?>
                <form action="nueva_categoria.php" method="POST">
                    <div class="mb-3">
                        <label for="nombre" class="form-label">Nombre de Categoría:</label>
                        <input type="text" class="form-control" id="nombre" name="nombre" required>
                    </div>
                    <div class="mb-3">
                        <label for="descripcion" class="form-label">Descripción:</label>
                        <textarea class="form-control" id="descripcion" name="descripcion" rows="3"></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">Guardar Categoría</button>
                    <a href="gestion_catalogo_categorias.php" class="btn btn-secondary">Cancelar</a>
                </form>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>