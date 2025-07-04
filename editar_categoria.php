<?php
session_start();
include("config.php");

if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: gestion_catalogo_categorias.php");
    exit();
}

$categoria_id = (int)$_GET['id'];

// Obtener la categoría actual
$stmt = $pdo->prepare("SELECT * FROM categoria WHERE categoria_id = :id");
$stmt->execute([':id' => $categoria_id]);
$categoria = $stmt->fetch();

if (!$categoria) {
    die("Categoría no encontrada.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre']);
    $descripcion = trim($_POST['descripcion']);

    if (!empty($nombre)) {
        $update = $pdo->prepare("UPDATE categoria SET nombre = :nombre, descripcion = :descripcion WHERE categoria_id = :id");
        $update->execute([
            ':nombre' => $nombre,
            ':descripcion' => $descripcion,
            ':id' => $categoria_id
        ]);
        header("Location: gestion_catalogo_categorias.php");
        exit();
    } else {
        $error = "El nombre no puede estar vacío.";
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

  <?php if (isset($error)): ?>
    <div class="alert alert-danger"><?= $error ?></div>
  <?php endif; ?>

  <!-- ... resto del código idéntico ... -->

  <form method="POST" class="card p-4">
  <div class="mb-3">
    <label for="nombre" class="form-label">Nombre</label>
    <input type="text" name="nombre" id="nombre" class="form-control" value="<?= htmlspecialchars($categoria['nombre']) ?>" required>
  </div>
  <div class="mb-3">
    <label for="descripcion" class="form-label">Descripción</label>
    <textarea name="descripcion" id="descripcion" class="form-control" rows="3"><?= htmlspecialchars($categoria['descripcion']) ?></textarea>
  </div>
  <div class="d-flex justify-content-between align-items-center">
    <a href="gestion_catalogo_categorias.php" class="btn btn-secondary">Cancelar</a>
    <button type="submit" class="btn btn-primary">Actualizar</button>
  </div>
</form>


</div>
</body>
</html>
