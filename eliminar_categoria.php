<?php
session_start(); // Inicia la sesión al principio
include("config.php"); // Asegúrate de que 'config.php' contenga la conexión PDO.

// 1. Verificar si el usuario está logueado
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}

// 2. Validación del parámetro recibido (ID de categoría)
if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    // Redireccionar con un mensaje de error si el ID no es válido
    // Este mensaje será rojo y cerrable en gestion_catalogo_categorias.php
    header("Location: gestion_catalogo_categorias.php?mensaje=" . urlencode("ID de categoría no válido.") . "&tipo=danger");
    exit();
}

$categoria_id = (int)$_GET['id'];

try {
    // 3. Verificar si existen productos relacionados a la categoría
    $sqlRelacion = "SELECT COUNT(*) FROM producto WHERE categoria_id = :id";
    $stmt = $pdo->prepare($sqlRelacion);
    $stmt->execute([':id' => $categoria_id]);
    $totalRelacionados = $stmt->fetchColumn();

    if ($totalRelacionados > 0) {
        // 4. Si hay productos relacionados, no se permite eliminar
        // Redirigir con el mensaje de error Específico para este caso
        // Este mensaje será rojo y cerrable en gestion_catalogo_categorias.php
        $errorMessage = "No se puede eliminar la categoría porque está asociada a " . $totalRelacionados . " producto(s).";
        header("Location: gestion_catalogo_categorias.php?mensaje=" . urlencode($errorMessage) . "&tipo=danger");
        exit();
    }

    // 5. Si no hay relaciones, proceder con la eliminación de la categoría
    $sqlDelete = "DELETE FROM categoria WHERE categoria_id = :id";
    $stmt = $pdo->prepare($sqlDelete);
    $stmt->execute([':id' => $categoria_id]);

    // 6. Redirigir después de eliminar con un mensaje de éxito
    // Este mensaje será verde y cerrable en gestion_catalogo_categorias.php
    header("Location: gestion_catalogo_categorias.php?mensaje=" . urlencode("Categoría eliminada con éxito.") . "&tipo=success");
    exit();

} catch (PDOException $e) {
    // 7. Capturar cualquier excepción de la base de datos
    error_log("Error al eliminar categoría: " . $e->getMessage()); // Registra el error para depuración
    // Este mensaje será rojo y cerrable en gestion_catalogo_categorias.php
    header("Location: gestion_catalogo_categorias.php?mensaje=" . urlencode("Error de base de datos al eliminar la categoría: " . $e->getMessage()) . "&tipo=danger");
    exit();
}
?>