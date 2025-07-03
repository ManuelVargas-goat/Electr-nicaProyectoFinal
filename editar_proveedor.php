<?php
session_start();
include("config.php");

if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: gestion_catalogo_proveedores.php");
    exit();
}

$proveedor_id = (int)$_GET['id'];

// Obtener datos del proveedor
$stmt = $pdo->prepare("SELECT * FROM proveedor WHERE proveedor_id = :id");
$stmt->execute([':id' => $proveedor_id]);
$proveedor = $stmt->fetch();

if (!$proveedor) {
    die("Proveedor no encontrado.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre_empresa = trim($_POST['nombre_empresa']);
    $pais = trim($_POST['pais']);
    $ciudad = trim($_POST['ciudad']);
    $direccion = trim($_POST['direccion']);
    $codigo_postal = trim($_POST['codigo_postal']);

    if ($nombre_empresa && $pais && $ciudad && $direccion && $codigo_postal) {
        $actualizar = $pdo->prepare("UPDATE proveedor 
            SET nombre_empresa = :nombre_empresa,
                pais = :pais,
                ciudad = :ciudad,
                direccion = :direccion,
                codigo_postal = :codigo_postal
            WHERE proveedor_id = :id");
        $actualizar->execute([
            ':nombre_empresa' => $nombre_empresa,
            ':pais' => $pais,
            ':ciudad' => $ciudad,
            ':direccion' => $direccion,
            ':codigo_postal' => $codigo_postal,
            ':id' => $proveedor_id
        ]);
        header("Location: gestion_catalogo_proveedores.php");
        exit();
    } else {
        $error = "Todos los campos son obligatorios.";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Editar Proveedor</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="css/estilos.css?v=<?= time(); ?>">
</head>
<body>
<div class="container mt-5">
  <h3 class="main-title">Editar Proveedor</h3>

  <?php if (isset($error)): ?>
    <div class="alert alert-danger"><?= $error ?></div>
  <?php endif; ?>

  <form method="POST" class="card p-4">
    <div class="mb-3">
      <label for="nombre_empresa" class="form-label">Nombre de Empresa</label>
      <input type="text" name="nombre_empresa" id="nombre_empresa" class="form-control" required value="<?= htmlspecialchars($proveedor['nombre_empresa']) ?>">
    </div>

    <div class="mb-3">
      <label for="pais" class="form-label">País</label>
      <input type="text" name="pais" id="pais" class="form-control" required value="<?= htmlspecialchars($proveedor['pais']) ?>">
    </div>

    <div class="mb-3">
      <label for="ciudad" class="form-label">Ciudad</label>
      <input type="text" name="ciudad" id="ciudad" class="form-control" required value="<?= htmlspecialchars($proveedor['ciudad']) ?>">
    </div>

    <div class="mb-3">
      <label for="direccion" class="form-label">Dirección</label>
      <input type="text" name="direccion" id="direccion" class="form-control" required value="<?= htmlspecialchars($proveedor['direccion']) ?>">
    </div>

    <div class="mb-3">
      <label for="codigo_postal" class="form-label">Código Postal</label>
      <input type="text" name="codigo_postal" id="codigo_postal" class="form-control" required value="<?= htmlspecialchars($proveedor['codigo_postal']) ?>">
    </div>

    <div class="d-flex justify-content-between">
      <a href="gestion_catalogo_proveedores.php" class="btn btn-secondary">Cancelar</a>
      <button type="submit" class="btn btn-primary">Guardar Cambios</button>
    </div>
  </form>
</div>
</body>
</html>
