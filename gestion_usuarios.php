<?php
session_start();
include("config.php"); // Asegúrate de que 'config.php' contenga la conexión PDO.

if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}

$usuario = $_SESSION['usuario'];

// Obtener datos del usuario actual (nombre y rol) para el sidebar
$sql = "SELECT per.nombre, per.apellido_paterno, r.nombre AS rol FROM usuario_empleado ue
        JOIN persona per ON ue.persona_id = per.persona_id
        JOIN rol r ON ue.rol_id = r.rol_id
        WHERE ue.usuario = :usuario";

$stmt = $pdo->prepare($sql);
$stmt->execute([':usuario' => $usuario]);

$usuarioActual = $stmt->fetch(PDO::FETCH_ASSOC);

$nombreUsuario = 'Usuario'; // Valor por defecto
$rolUsuario = ''; // Valor por defecto

if ($usuarioActual) {
    $nombreUsuario = $usuarioActual['nombre'] . ' ' . $usuarioActual['apellido_paterno'];
    $rolUsuario = strtolower($usuarioActual['rol']); // Forzar a minúsculas para comparación
}

// Lógica de búsqueda y filtro para usuarios (empleados y clientes)
$busqueda = $_POST['busqueda'] ?? '';
$tipo_usuario_filtro = $_POST['tipo_usuario_filtro'] ?? 'all'; // 'all', 'empleado', 'cliente'

$where = [];
$params = [];

if (!empty($busqueda)) {
    // Buscar en nombres de personas, emails, y IDs de usuario
    $where[] = "(p.nombre ILIKE :busqueda OR p.apellido_paterno ILIKE :busqueda OR p.email ILIKE :busqueda OR CAST(ue.usuario_empleado_id AS TEXT) ILIKE :busqueda OR CAST(uc.usuario_cliente_id AS TEXT) ILIKE :busqueda)";
    $params[':busqueda'] = "%$busqueda%";
}

// SQL base para obtener empleados
$sqlEmpleados = "
    SELECT
        'empleado' as tipo_usuario,
        ue.usuario_empleado_id as id,
        ue.usuario,
        p.nombre,
        p.apellido_paterno,
        p.apellido_materno,
        p.email,
        r.nombre AS rol_nombre,
        ue.estado -- Para el estado del empleado
    FROM
        usuario_empleado ue
    JOIN
        persona p ON ue.persona_id = p.persona_id
    JOIN
        rol r ON ue.rol_id = r.rol_id
";

// SQL base para obtener clientes
$sqlClientes = "
    SELECT
        'cliente' as tipo_usuario,
        uc.usuario_cliente_id as id,
        uc.usuario,
        p.nombre,
        p.apellido_paterno,
        p.apellido_materno,
        p.email,
        uc.rol AS rol_nombre, -- El rol está directamente en la tabla usuario_cliente
        NULL AS estado -- Los clientes no tienen una columna 'estado' en este esquema
    FROM
        usuario_cliente uc
    JOIN
        persona p ON uc.persona_id = p.persona_id
";

// Aplicar filtros basados en $tipo_usuario_filtro
if ($tipo_usuario_filtro === 'empleado') {
    $finalSql = $sqlEmpleados;
    if (!empty($where)) {
        $finalSql .= " WHERE " . implode(" AND ", $where);
    }
} elseif ($tipo_usuario_filtro === 'cliente') {
    $finalSql = $sqlClientes;
    if (!empty($where)) {
        $finalSql .= " WHERE " . implode(" AND ", $where);
    }
} else { // 'all' o sin filtro
    $where_clause_empleados = !empty($where) ? " WHERE " . implode(" AND ", $where) : "";
    $where_clause_clientes = !empty($where) ? " WHERE " . implode(" AND ", $where) : "";
    $finalSql = "($sqlEmpleados $where_clause_empleados) UNION ALL ($sqlClientes $where_clause_clientes)";
}

$finalSql .= " ORDER BY tipo_usuario, id DESC"; // Ordenar para consistencia

$stmt = $pdo->prepare($finalSql);
$stmt->execute($params);
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Usuarios</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/estilos.css?v=<?= time(); ?>">
    <style>
        /* CSS para el submenu - Asegúrate de que esto esté en estilos.css o aquí */
        .submenu {
            display: none; /* Oculto por defecto */
            padding-left: 20px; /* Indentación para el submenu */
        }
        .submenu.active {
            display: block; /* Visible cuando está activo */
        }
        .sidebar a {
            display: block; /* Asegura que cada enlace ocupe su propia línea */
            padding: 8px 15px;
            color: #ffffff;
            text-decoration: none;
        }
        .sidebar a:hover, .sidebar a.active {
            background-color: #575757;
            border-radius: 5px;
        }
    </style>
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

            <a href="gestion_catalogo_categorias.php" id="catalogoToggle">Gestión de Catálogo</a>
            <div class="submenu" id="catalogoSubmenu">
                <a href="gestion_catalogo_categorias.php">Categorías</a>
                <a href="gestion_catalogo_productos.php">Productos</a>
                <a href="gestion_catalogo_proveedores.php">Proveedores</a>
            </div>
            <a href="gestion_usuarios.php" class="active">Gestión de Usuarios</a>
            <a href="gestion_existencias_pedidos.php">Gestión de Existencias</a>
            <a href="configuracion.php">Configuración</a>

            <div class="mt-4">
                <a href="principal.php" class="btn btn-outline-primary w-100">Volver al inicio</a>
            </div>
        </div>

        <div class="col-md-10 content">
            <h3 class="main-title">Gestión de Usuarios</h3>

            <?php if (isset($_GET['eliminado']) && $_GET['eliminado'] === 'ok'): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    Usuario eliminado correctamente.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
                </div>
            <?php elseif (isset($_GET['error']) && $_GET['error'] === 'invalido'): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    ID de usuario no válido.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
                </div>
            <?php endif; ?>

            <div class="mb-3">
                <a href="nuevo_usuario.php" class="btn btn-primary">+ Agregar Usuario</a>
            </div>

            <form method="POST" class="row g-3 mb-3">
                <div class="col-md-4">
                    <input type="text" name="busqueda" class="form-control" placeholder="Buscar por nombre, email o ID" value="<?= htmlspecialchars($busqueda) ?>">
                </div>
                <div class="col-md-4">
                    <select name="tipo_usuario_filtro" class="form-select">
                        <option value="all" <?= ($tipo_usuario_filtro === 'all') ? 'selected' : '' ?>>Todos los Usuarios</option>
                        <option value="empleado" <?= ($tipo_usuario_filtro === 'empleado') ? 'selected' : '' ?>>Empleados</option>
                        <option value="cliente" <?= ($tipo_usuario_filtro === 'cliente') ? 'selected' : '' ?>>Clientes</option>
                    </select>
                </div>
                <div class="col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary w-100">Buscar</button>
                    <a href="gestion_usuarios.php" class="btn btn-secondary w-100">Limpiar</a>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-bordered table-hover text-center">
                    <thead>
                        <tr>
                            <th>Tipo</th>
                            <th>ID</th>
                            <th>Usuario</th>
                            <th>Nombre Completo</th>
                            <th>Email</th>
                            <th>Rol</th>
                            <th>Estado (Empleado)</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($usuarios) > 0): ?>
                            <?php foreach ($usuarios as $user): ?>
                                <tr>
                                    <td><?= ucfirst($user['tipo_usuario']) ?></td>
                                    <td><?= $user['id'] ?></td>
                                    <td><?= htmlspecialchars($user['usuario']) ?></td>
                                    <td><?= htmlspecialchars($user['nombre'] . ' ' . $user['apellido_paterno'] . ' ' . $user['apellido_materno']) ?></td>
                                    <td><?= htmlspecialchars($user['email']) ?></td>
                                    <td><?= htmlspecialchars(ucfirst($user['rol_nombre'])) ?></td>
                                    <td>
                                        <?php
                                        if ($user['tipo_usuario'] === 'empleado') {
                                            echo $user['estado'] ? 'Activo' : 'Inactivo';
                                        } else {
                                            echo 'N/A';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <div class="d-flex justify-content-center gap-2">
                                            <a href="editar_usuario.php?type=<?= $user['tipo_usuario'] ?>&id=<?= $user['id'] ?>" class="btn btn-sm btn-info">Editar</a>
                                            <a href="eliminar_usuario.php?type=<?= $user['tipo_usuario'] ?>&id=<?= $user['id'] ?>"
                                               class="btn btn-sm btn-danger"
                                               onclick="return confirm('¿Seguro que deseas eliminar este usuario? Esta acción es irreversible.');">
                                                Eliminar
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="8" class="text-center">No se encontraron usuarios.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.getElementById('catalogoToggle').addEventListener('click', function(event) {
        var submenu = document.getElementById('catalogoSubmenu');
        submenu.classList.toggle('active');
    });
</script>
</body>
</html>