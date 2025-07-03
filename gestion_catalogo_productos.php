<?php
session_start();
include("config.php");

if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}

$usuario = $_SESSION['usuario'];

// Obtener datos del usuario
$sql = "SELECT per.nombre, per.apellido_paterno FROM usuario_empleado ue 
        JOIN persona per ON ue.persona_id = per.persona_id 
        WHERE ue.usuario = :usuario";
$stmt = $pdo->prepare($sql);
$stmt->execute([':usuario' => $usuario]);
$usuarioActual = $stmt->fetch();
$nombreUsuario = $usuarioActual ? $usuarioActual['nombre'] . ' ' . $usuarioActual['apellido_paterno'] : 'Nom_Usuario';

// Obtener categorías para el filtro
$categoriasLista = $pdo->query("SELECT categoria_id, nombre FROM categoria ORDER BY nombre")->fetchAll();

// Parámetros de filtro
$busqueda = $_GET['buscar'] ?? '';
$categoriaFiltrada = $_GET['categoria'] ?? '';

// Consulta dinámica
$where = [];
$params = [];

if (!empty($busqueda)) {
    $where[] = "(p.nombre ILIKE :buscar OR CAST(p.producto_id AS TEXT) ILIKE :buscar)";
    $params[':buscar'] = "%$busqueda%";
}

if (!empty($categoriaFiltrada)) {
    $where[] = "p.categoria_id = :categoria";
    $params[':categoria'] = $categoriaFiltrada;
}

$sqlProductos = "SELECT p.producto_id, p.nombre, p.descripcion, p.marca, p.precio, c.nombre AS categoria
                 FROM producto p
                 LEFT JOIN categoria c ON p.categoria_id = c.categoria_id";

if ($where) {
    $sqlProductos .= " WHERE " . implode(" AND ", $where);
}

$sqlProductos .= " ORDER BY p.producto_id DESC";

$stmt = $pdo->prepare($sqlProductos);
$stmt->execute($params);
$productos = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Gestión de Productos</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="css/estilos.css?v=<?= time(); ?>">
</head>
<body>
<div class="container-fluid">
  <div class="row">
    <!-- Sidebar -->
    <div class="col-md-2 sidebar">
      <div class="user-box">
        <img src="https://cdn-icons-png.flaticon.com/512/149/149071.png" class="mb-2">
        <div class="fw-bold"><?= htmlspecialchars($nombreUsuario) ?></div>
      </div>
      <a href="#" class="active">Gestión de Productos</a>
      <div class="submenu">
        <a href="gestion_catalogo_categorias.php">Categorías</a>
        <a href="gestion_catalogo_productos.php" class="active">Productos</a>
        <a href="gestion_catalogo_proveedores.php">Proveedores</a>
      </div>
      <a href="gestion_usuarios.php">Gestión de Usuarios</a>
      <a href="gestion_existencias_stock.php">Gestión de Existencias</a>
      <a href="configuracion.php">Configuración</a>
    </div>

    <!-- Contenido -->
    <div class="col-md-10 content">
      <h3 class="main-title">Gestión de Productos</h3>
      <a href="nuevo_producto.php" class="btn btn-primary mb-3">+ Agregar Producto</a>

      <!-- Formulario de búsqueda -->
      <form method="GET" class="row mb-4 g-2">
        <div class="col-md-4">
          <input type="text" name="buscar" class="form-control" placeholder="Buscar por ID o Nombre" value="<?= htmlspecialchars($busqueda) ?>">
        </div>
        <div class="col-md-4">
          <select name="categoria" class="form-select">
            <option value="">-- Todas las Categorías --</option>
            <?php foreach ($categoriasLista as $cat): ?>
              <option value="<?= $cat['categoria_id'] ?>" <?= $categoriaFiltrada == $cat['categoria_id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($cat['nombre']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2 d-grid">
          <button type="submit" class="btn btn-primary">Buscar</button>
        </div>
        <div class="col-md-2 d-grid">
          <a href="gestion_catalogo_productos.php" class="btn btn-secondary">Limpiar</a>
        </div>
      </form>

      <!-- Tabla -->
      <div class="table-responsive">
        <table class="table table-bordered table-hover text-center">
          <thead>
            <tr>
              <th>ID</th>
              <th>Nombre</th>
              <th>Descripción</th>
              <th>Marca</th>
              <th>Precio (S/)</th>
              <th>Categoría</th>
              <th>Acción</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($productos as $prod): ?>
              <tr>
                <td><?= $prod['producto_id'] ?></td>
                <td><?= htmlspecialchars($prod['nombre']) ?></td>
                <td><?= htmlspecialchars($prod['descripcion']) ?></td>
                <td><?= htmlspecialchars($prod['marca']) ?></td>
                <td><?= number_format($prod['precio'], 2) ?></td>
                <td><?= htmlspecialchars($prod['categoria'] ?? 'Sin categoría') ?></td>
                <td>
                  <a href="editar_producto.php?id=<?= $prod['producto_id'] ?>" class="btn btn-sm btn-info">Editar</a>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if (empty($productos)): ?>
              <tr><td colspan="7">No se encontraron productos.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
</body>
</html>
