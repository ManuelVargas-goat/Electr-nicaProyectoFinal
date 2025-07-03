<?php
session_start();
include("config.php");

// Validación del parámetro recibido
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: gestion_catalogo_categorias.php?error=invalido");
    exit();
}

$categoria_id = (int)$_GET['id'];

// Verificar si existen productos relacionados a la categoría
$sqlRelacion = "SELECT COUNT(*) FROM producto WHERE categoria_id = :id";
$stmt = $pdo->prepare($sqlRelacion);
$stmt->execute([':id' => $categoria_id]);
$totalRelacionados = $stmt->fetchColumn();

if ($totalRelacionados > 0) {
    // Si hay productos relacionados, no se permite eliminar
    header("Location: gestion_catalogo_categorias.php?error=asociada");
    exit();
}

// Ejecutar eliminación
$sqlDelete = "DELETE FROM categoria WHERE categoria_id = :id";
$stmt = $pdo->prepare($sqlDelete);
$stmt->execute([':id' => $categoria_id]);

// Redirige después de eliminar
header("Location: gestion_catalogo_categorias.php?eliminado=ok");
exit();
?>
