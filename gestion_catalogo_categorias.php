<?php
session_start();
include("config.php");

if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}

$usuario = $_SESSION['usuario'];

// Obtener nombre del usuario
$sql = "SELECT per.nombre, per.apellido_paterno, r.nombre AS rol FROM usuario_empleado ue
        JOIN persona per ON ue.persona_id = per.persona_id
        JOIN rol r ON ue.rol_id = r.rol_id
        WHERE ue.usuario = :usuario";

$stmt = $pdo->prepare($sql);
$stmt->execute([':usuario' => $usuario]);

$usuarioActual = $stmt->fetch(PDO::FETCH_ASSOC);

$nombreUsuario = 'Usuario';
$rolUsuario = '';

if ($usuarioActual) {
    $nombreUsuario = $usuarioActual['nombre'] . ' ' . $usuarioActual['apellido_paterno'];
    $rolUsuario = strtolower($usuarioActual['rol']); // Forzamos a minúsculas
}


// Lógica de búsqueda
$filtro = '';
$param = [];

if (!empty($_POST['busqueda'])) {
    $busqueda = trim($_POST['busqueda']);
    $filtro = "WHERE CAST(categoria_id AS TEXT) ILIKE :busqueda OR nombre ILIKE :busqueda";
    $param = [':busqueda' => "%$busqueda%"];
}

$sqlCategorias = "SELECT * FROM categoria $filtro ORDER BY categoria_id DESC";
$stmtCat = $pdo->prepare($sqlCategorias);
$stmtCat->execute($param);
$categorias = $stmtCat->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Gestión de Categorías</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="css/estilos.css?v=<?= time(); ?>">
</head>
<body>
<div class="container-fluid">
  <div class="row">
    <!-- Sidebar -->
    <div class="col-md-2 sidebar">
      <div class="user-box text-center mb-4">
  <img src="https://cdn-icons-png.flaticon.com/512/149/149071.png" alt="Usuario" class="img-fluid rounded-circle mb-2" style="width: 64px;">
  <div class="fw-bold"><?= htmlspecialchars($nombreUsuario) ?></div>
  
  <?php if (strtolower($rolUsuario) === 'administrador'): ?>
    <div class="fw-bold text-warning small">Administrador</div>
  <?php endif; ?>
</div>

      <a href="#" class="active">Gestión de Catálogo</a>
      <div class="submenu">
        <a href="gestion_catalogo_categorias.php" class="active">Categorías</a>
        <a href="gestion_catalogo_productos.php">Productos</a>
        <a href="gestion_catalogo_proveedores.php">Proveedores</a>
      </div>
      <a href="gestion_usuarios.php">Gestión de Usuarios</a>
      <a href="gestion_existencias_stock.php">Gestión de Existencias</a>
      <a href="configuracion.php">Configuración</a>

      <div class="mt-4">
  <a href="principal.php" class="btn btn-outline-primary w-100">← Volver al inicio</a>
</div>
    </div>


    <!-- Contenido -->
    <div class="col-md-10 content">
      
      <h3 class="main-title">Gestión de Categorías</h3>
   <?php if (isset($_GET['error']) && $_GET['error'] === 'asociada'): ?>
  <div class="alert alert-warning alert-dismissible fade show" role="alert">
    No se puede eliminar la categoría porque tiene productos relacionados.
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
  </div>
<?php elseif (isset($_GET['eliminado']) && $_GET['eliminado'] === 'ok'): ?>
  <div class="alert alert-success alert-dismissible fade show" role="alert">
    Categoría eliminada correctamente.
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
  </div>
<?php elseif (isset($_GET['error']) && $_GET['error'] === 'invalido'): ?>
  <div class="alert alert-danger alert-dismissible fade show" role="alert">
    ID de categoría no válido.
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
  </div>
<?php endif; ?>

      <form method="POST" class="row g-3 mb-3">
        <div class="col-md-4">
          <input type="text" name="busqueda" class="form-control" placeholder="Buscar por ID o nombre" value="<?= isset($_POST['busqueda']) ? htmlspecialchars($_POST['busqueda']) : '' ?>">
        </div>
        <div class="col-md-2">
          <button type="submit" class="btn btn-primary w-100">Buscar</button>
        </div>
        <div class="col-md-2">
          <a href="gestion_catalogo_categorias.php" class="btn btn-secondary w-100">Limpiar</a>
        </div>
        <div class="col-md-4 text-end">
          <a href="nueva_categoria.php" class="btn btn-primary">+ Agregar Categoría</a>
        </div>
      </form>

      <div class="table-responsive">
        <table class="table table-bordered table-hover text-center">
          <thead>
            <tr>
              <th>ID</th>
              <th>Nombre</th>
              <th>Descripción</th>
              <th>Acción</th>
            </tr>
          </thead>
          <tbody>
            <?php if (count($categorias) > 0): ?>
              <?php foreach ($categorias as $cat): ?>
                <tr>
                  <td><?= $cat['categoria_id'] ?></td>
                  <td><?= htmlspecialchars($cat['nombre']) ?></td>
                  <td><?= htmlspecialchars($cat['descripcion']) ?></td>
                  <td>
                    <div class="d-flex justify-content-center gap-2">
                      <a href="editar_categoria.php?id=<?= $cat['categoria_id'] ?>" class="btn btn-sm btn-info">Editar</a>
                      <a href="eliminar_categoria.php?id=<?= $cat['categoria_id'] ?>"
                        class="btn btn-sm btn-danger"
                        onclick="return confirm('¿Seguro que deseas eliminar esta categoría?');">
                        Eliminar
                      </a>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr><td colspan="4" class="text-center">No se encontraron resultados.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>


</body>
</html>
