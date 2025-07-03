<?php
session_start();
include("config.php");

if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}

$usuario = $_SESSION['usuario'];

// Obtener datos del usuario actual
$sql = "SELECT per.nombre, per.apellido_paterno FROM usuario_empleado ue
        JOIN persona per ON ue.persona_id = per.persona_id
        WHERE ue.usuario = :usuario";
$stmt = $pdo->prepare($sql);
$stmt->execute([':usuario' => $usuario]);
$usuarioActual = $stmt->fetch();
$nombreUsuario = $usuarioActual ? $usuarioActual['nombre'] . ' ' . $usuarioActual['apellido_paterno'] : 'Usuario';

// Filtros de búsqueda
$filtro = $_GET['filtro'] ?? '';
$sqlProveedores = "SELECT proveedor_id, nombre_empresa, pais, ciudad, direccion, codigo_postal FROM proveedor
                   WHERE CAST(proveedor_id AS TEXT) ILIKE :filtro OR nombre_empresa ILIKE :filtro
                   ORDER BY proveedor_id DESC";

$stmtProv = $pdo->prepare($sqlProveedores);
$stmtProv->execute([':filtro' => "%$filtro%"]);
$proveedores = $stmtProv->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Gestión de Proveedores</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="css/estilos.css?v=<?= time(); ?>">
</head>
<body>
<div class="container-fluid">
  <div class="row">
    <!-- Sidebar -->
    <div class="col-md-2 sidebar">
      <div class="user-box">
        <img src="https://cdn-icons-png.flaticon.com/512/149/149071.png" alt="Usuario">
        <div><?= htmlspecialchars($nombreUsuario) ?></div>
      </div>
      <a href="#" class="active">Gestión de Productos</a>
      <div class="submenu">
        <a href="gestion_catalogo_categorias.php">Categorías</a>
        <a href="gestion_catalogo_productos.php">Productos</a>
        <a href="gestion_catalogo_proveedores.php" class="active">Proveedores</a>
      </div>
      <a href="gestion_usuarios.php">Gestión de Usuarios</a>
      <a href="gestion_existencias_stock.php">Gestión de Existencias</a>
      <a href="configuracion.php">Configuración</a>
    </div>

    <!-- Contenido -->
    <div class="col-md-10 content">
      <h3 class="main-title">Gestión de Proveedores</h3>

      <?php if (isset($_GET['eliminado']) && $_GET['eliminado'] === 'ok'): ?>
        <div class="alert alert-success">Proveedor eliminado exitosamente.</div>
      <?php elseif (isset($_GET['error']) && $_GET['error'] === 'relaciones'): ?>
        <div class="alert alert-danger">
          No se puede eliminar este proveedor porque tiene productos o categorías relacionadas.
        </div>
      <?php endif; ?>

      <div class="d-flex justify-content-between mb-3">
        <form class="d-flex" method="GET" action="">
          <input type="text" name="filtro" class="form-control me-2" placeholder="Buscar por ID o empresa..." value="<?= htmlspecialchars($filtro) ?>">
          <button class="btn btn-primary me-2" type="submit">Buscar</button>
          <a href="gestion_catalogo_proveedores.php" class="btn btn-secondary">Limpiar</a>
        </form>
        <a href="nuevo_proveedor.php" class="btn btn-primary">+ Agregar Proveedor</a>
      </div>

      <div class="table-responsive">
        <table class="table table-bordered table-hover text-center">
          <thead>
            <tr>
              <th>ID</th>
              <th>Empresa</th>
              <th>País</th>
              <th>Ciudad</th>
              <th>Dirección</th>
              <th>Código Postal</th>
              <th>Acción</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($proveedores as $prov): ?>
              <tr>
                <td><?= $prov['proveedor_id'] ?></td>
                <td><?= htmlspecialchars($prov['nombre_empresa']) ?></td>
                <td><?= htmlspecialchars($prov['pais']) ?></td>
                <td><?= htmlspecialchars($prov['ciudad']) ?></td>
                <td><?= htmlspecialchars($prov['direccion']) ?></td>
                <td><?= htmlspecialchars($prov['codigo_postal']) ?></td>
                <td>
                  <a href="editar_proveedor.php?id=<?= $prov['proveedor_id'] ?>" class="btn btn-sm btn-info">Editar</a>
                  <a href="eliminar_proveedor.php?id=<?= $prov['proveedor_id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('¿Seguro que deseas eliminar este proveedor?');">Eliminar</a>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if (empty($proveedores)): ?>
              <tr><td colspan="7" class="text-muted">No se encontraron resultados.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
</body>
</html>
 