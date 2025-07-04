<?php
session_start();
include("config.php");

if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}

$usuario = $_SESSION['usuario'];

// Obtener datos del usuario actual, incluyendo el rol
$sql = "SELECT per.nombre, per.apellido_paterno, r.nombre AS rol FROM usuario_empleado ue
        JOIN persona per ON ue.persona_id = per.persona_id
        JOIN rol r ON ue.rol_id = r.rol_id
        WHERE ue.usuario = :usuario";
$stmt = $pdo->prepare($sql);
$stmt->execute([':usuario' => $usuario]);
$usuarioActual = $stmt->fetch(PDO::FETCH_ASSOC); // Fetch as associative array

$nombreUsuario = 'Usuario'; // Default
$rolUsuario = ''; // Default

if ($usuarioActual) {
    $nombreUsuario = $usuarioActual['nombre'] . ' ' . $usuarioActual['apellido_paterno'];
    // Convert the role from the database to lowercase for comparison
    $rolUsuario = strtolower($usuarioActual['rol']);
}

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
        <div class="col-md-2 sidebar">
            <div class="user-box text-center mb-4">
                <img src="https://cdn-icons-png.flaticon.com/512/149/149071.png" alt="Usuario" class="img-fluid rounded-circle mb-2" style="width: 64px;">

                <div class="fw-bold"><?= htmlspecialchars($nombreUsuario) ?></div>

                <div class="<?= ($rolUsuario === 'admin') ? 'text-warning' : 'text-light' ?> small">
                    <?= ucfirst($rolUsuario === 'admin' ? 'administrador' : $rolUsuario) ?>
                </div>
            </div>

            <a href="#" class="active">Gestión de Catálogo</a>
            <div class="submenu">
                <a href="gestion_catalogo_categorias.php">Categorías</a>
                <a href="gestion_catalogo_productos.php">Productos</a>
                <a href="gestion_catalogo_proveedores.php" class="active">Proveedores</a>
            </div>
            <a href="gestion_usuarios.php">Gestión de Usuarios</a>
            <a href="gestion_existencias_pedidos.php">Gestión de Existencias</a>
            <a href="configuracion.php">Configuración</a>

            <div class="mt-4">
                <a href="principal.php" class="btn btn-outline-primary w-100">Volver al inicio</a>
            </div>
        </div>

        <div class="col-md-10 content">
            <h3 class="main-title">Gestión de Proveedores</h3>

            <?php if (isset($_GET['eliminado']) && $_GET['eliminado'] === 'ok'): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    Proveedor eliminado exitosamente.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php elseif (isset($_GET['error']) && $_GET['error'] === 'relaciones'): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    No se puede eliminar este proveedor porque tiene productos o categorías relacionadas.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
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