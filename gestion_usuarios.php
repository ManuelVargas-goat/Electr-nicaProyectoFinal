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

$nombreCompleto = 'Usuario Desconocido'; // Valor por defecto
$rolUsuario = ''; // Valor por defecto

if ($usuarioActual) {
    $nombreCompleto = htmlspecialchars($usuarioActual['nombre'] . ' ' . $usuarioActual['apellido_paterno']);
    $rolUsuario = strtolower(htmlspecialchars($usuarioActual['rol']));
}

// Lógica de búsqueda y filtro para usuarios (empleados y clientes)
$busqueda = $_POST['busqueda'] ?? '';
$tipo_usuario_filtro = $_POST['tipo_usuario_filtro'] ?? 'all'; // 'all', 'empleado', 'cliente'

$where = [];
$params = [];

if (!empty($busqueda)) {
    // Usamos ILIKE para búsquedas insensibles a mayúsculas/minúsculas en PostgreSQL
    // CAST(ue.usuario_empleado_id AS TEXT) para buscar por ID numérico como texto
    $where[] = "(p.nombre ILIKE :busqueda OR p.apellido_paterno ILIKE :busqueda OR p.email ILIKE :busqueda OR CAST(ue.usuario_empleado_id AS TEXT) ILIKE :busqueda_empleado_id OR CAST(uc.usuario_cliente_id AS TEXT) ILIKE :busqueda_cliente_id OR ue.usuario ILIKE :busqueda_user_empleado OR uc.usuario ILIKE :busqueda_user_cliente)";
    $params[':busqueda'] = "%$busqueda%";
    $params[':busqueda_empleado_id'] = "%$busqueda%";
    $params[':busqueda_cliente_id'] = "%$busqueda%";
    $params[':busqueda_user_empleado'] = "%$busqueda%";
    $params[':busqueda_user_cliente'] = "%$busqueda%";
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
        ue.estado
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
        uc.rol AS rol_nombre,
        NULL AS estado
    FROM
        usuario_cliente uc
    JOIN
        persona p ON uc.persona_id = p.persona_id
";

$usuarios = [];
if ($tipo_usuario_filtro === 'empleado') {
    $finalSql = $sqlEmpleados;
    if (!empty($where)) {
        $finalSql .= " WHERE " . implode(" AND ", $where);
    }
    // Ajustar parámetros para empleados
    unset($params[':busqueda_cliente_id']);
    unset($params[':busqueda_user_cliente']);

    $stmt = $pdo->prepare($finalSql . " ORDER BY tipo_usuario, id DESC");
    $stmt->execute($params);
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

} elseif ($tipo_usuario_filtro === 'cliente') {
    $finalSql = $sqlClientes;
    if (!empty($where)) {
        $finalSql .= " WHERE " . implode(" AND ", $where);
    }
    // Ajustar parámetros para clientes
    unset($params[':busqueda_empleado_id']);
    unset($params[':busqueda_user_empleado']);

    $stmt = $pdo->prepare($finalSql . " ORDER BY tipo_usuario, id DESC");
    $stmt->execute($params);
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

} else { // 'all' o sin filtro
    // Para 'all', necesitamos construir WHERE clauses separadas porque los parámetros son diferentes
    $where_clause_empleados = '';
    $paramsEmpleados = $params;
    // Filtrar condiciones que solo aplican a clientes para la consulta de empleados
    $empleado_where_conditions = $where;
    foreach ($empleado_where_conditions as $key => $condition) {
        if (strpos($condition, 'uc.usuario_cliente_id') !== false || strpos($condition, 'uc.usuario ILIKE') !== false) {
            unset($empleado_where_conditions[$key]);
        }
    }
    if (!empty($empleado_where_conditions)) {
        $where_clause_empleados = " WHERE " . implode(" AND ", $empleado_where_conditions);
    }
    unset($paramsEmpleados[':busqueda_cliente_id']);
    unset($paramsEmpleados[':busqueda_user_cliente']);


    $where_clause_clientes = '';
    $paramsClientes = $params;
    // Filtrar condiciones que solo aplican a empleados para la consulta de clientes
    $cliente_where_conditions = $where;
    foreach ($cliente_where_conditions as $key => $condition) {
        if (strpos($condition, 'ue.usuario_empleado_id') !== false || strpos($condition, 'ue.usuario ILIKE') !== false) {
            unset($cliente_where_conditions[$key]);
        }
    }
    if (!empty($cliente_where_conditions)) {
        $where_clause_clientes = " WHERE " . implode(" AND ", $cliente_where_conditions);
    }
    unset($paramsClientes[':busqueda_empleado_id']);
    unset($paramsClientes[':busqueda_user_empleado']);


    $stmtEmpleados = $pdo->prepare($sqlEmpleados . $where_clause_empleados);
    $stmtEmpleados->execute($paramsEmpleados);
    $usuariosEmpleados = $stmtEmpleados->fetchAll(PDO::FETCH_ASSOC);

    $stmtClientes = $pdo->prepare($sqlClientes . $where_clause_clientes);
    $stmtClientes->execute($paramsClientes);
    $usuariosClientes = $stmtClientes->fetchAll(PDO::FETCH_ASSOC);

    $usuarios = array_merge($usuariosEmpleados, $usuariosClientes);

    // Ordenar después de la unión
    usort($usuarios, function($a, $b) {
        $typeCompare = strcmp($a['tipo_usuario'], $b['tipo_usuario']);
        if ($typeCompare === 0) {
            return $b['id'] - $a['id']; // Ordenar por ID descendente dentro de cada tipo
        }
        return $typeCompare; // Ordenar por tipo de usuario (empleado primero, luego cliente)
    });
}

// =================================================================
// Manejo de mensajes de sesión (alertas) - AHORA COMO ALERTA DE BOOTSTRAP
// =================================================================
$alert_message = '';
$alert_type = '';
if (isset($_SESSION['alert_message']) && !empty($_SESSION['alert_message'])) {
    $alert_message = $_SESSION['alert_message'];
    $alert_type = $_SESSION['alert_type'];
    // Limpiar las variables de sesión para que la alerta no se muestre de nuevo
    unset($_SESSION['alert_message']);
    unset($_SESSION['alert_type']);
}
// =================================================================
// FIN Manejo de mensajes de sesión
// =================================================================

// Variable para el nombre del archivo actual (sin la extensión .php)
$currentPage = basename($_SERVER['PHP_SELF'], ".php");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Usuarios</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/estilos.css?v=<?= time(); ?>">
    <style>
        /* CSS para el sidebar y submenús (duplicado para consistencia) */
        .sidebar {
            background-color: #1a202c; /* Color de fondo oscuro para el sidebar */
            color: #e0e0e0;
            padding-top: 20px;
            min-height: 100vh;
        }

        .user-box {
            border-bottom: 1px solid #364052;
            padding-bottom: 20px;
            margin-bottom: 20px;
        }

        .user-box img {
            border: 2px solid #ffc107;
        }

        .user-box .fw-bold {
            color: #ffffff; /* Color blanco para el nombre del usuario */
        }

        .user-box .text-warning {
            color: #ffc107 !important; /* Color amarillo para el rol de administrador */
        }

        .user-box .text-light {
            color: #f8f9fa !important; /* Color claro para otros roles */
        }

        .sidebar a {
            display: block;
            padding: 8px 15px;
            color: #e0e0e0; /* Color gris claro por defecto para el texto normal */
            text-decoration: none;
            border-radius: 5px;
            margin-bottom: 5px;
            transition: background-color 0.3s, color 0.3s;
        }

        .sidebar a:hover {
            background-color: #495057; /* Fondo un poco más oscuro al pasar el ratón */
            color: #ffffff; /* Color blanco al pasar el ratón */
        }

        /* Estilo para el enlace de la página ACTUAL */
        .sidebar a.current-page {
            background-color: #495057; /* Fondo gris oscuro */
            color: #ffc107 !important; /* Texto amarillo anaranjado */
            font-weight: bold;
        }

        /* Submenú */
        .submenu {
            display: none; /* Oculto por defecto */
            padding-left: 20px; /* Indentación */
        }

        .submenu.active {
            display: block; /* Visible cuando está activo */
        }

        .submenu a {
            padding: 6px 0 6px 15px; /* Ajuste para sub-ítems */
            font-size: 0.9em;
            color: #e0e0e0; /* Color por defecto para sub-ítems */
        }

        /* Asegurarse que los sub-enlaces activos también tienen el color */
        .submenu a.current-page {
            background-color: #495057; /* Fondo gris oscuro para submenú activo */
            color: #ffc107 !important; /* Texto amarillo anaranjado para submenú activo */
        }

        /* Estilo para el botón "Volver al Inicio" */
        .sidebar .btn-outline-primary {
            background-color: #1a202c; /* Fondo azul oscuro, mismo que el sidebar */
            color: #ffffff; /* Texto blanco */
            border-color: #007bff; /* Borde azul */
            font-weight: bold;
        }

        .sidebar .btn-outline-primary:hover {
            background-color: #2c3447; /* Un poco más claro al pasar el ratón */
            color: #ffffff;
            border-color: #007bff; /* Borde azul se mantiene */
        }

        /* Estilo para el botón de búsqueda y otros botones de formulario */
        .btn-primary {
            background-color: #007bff; /* Azul de Bootstrap */
            border-color: #007bff;
            font-weight: bold;
        }

        .btn-primary:hover {
            background-color: #0056b3;
            border-color: #0056b3;
        }

        .btn-success {
            background-color: #28a745; /* Verde de Bootstrap */
            border-color: #28a745;
        }

        .btn-success:hover {
            background-color: #218838;
            border-color: #1e7e34;
        }

        .btn-secondary {
            background-color: #6c757d; /* Gris de Bootstrap */
            border-color: #6c757d;
        }

        .btn-secondary:hover {
            background-color: #5a6268;
            border-color: #545b62;
        }

        .btn-info {
            background-color: #17a2b8; /* Azul claro de Bootstrap */
            border-color: #17a2b8;
        }

        .btn-info:hover {
            background-color: #138496;
            border-color: #117a8b;
        }

        .btn-danger {
            background-color: #dc3545; /* Rojo de Bootstrap */
            border-color: #dc3545;
        }

        .btn-danger:hover {
            background-color: #c82333;
            border-color: #bd2130;
        }

        /* Contenido principal */
        .content {
            padding: 20px;
        }

        /* Estilos para la alerta de Bootstrap (adaptativa y de ancho máximo) */
        #pageAlert {
            position: sticky; /* La alerta se pegará en la parte superior al hacer scroll */
            top: 10px; /* Distancia desde la parte superior de la ventana */
            z-index: 1050; /* Asegura que esté por encima de otros elementos */
            margin-bottom: 1rem; /* Añade un pequeño margen inferior para separar del contenido */
        }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <div class="col-md-2 sidebar">
            <div class="user-box text-center mb-4">
                <img src="https://cdn-icons-png.flaticon.com/512/149/149071.png" alt="Usuario" class="img-fluid rounded-circle mb-2" style="width: 64px;">
                <div class="fw-bold"><?= $nombreCompleto ?></div>
                <div class="<?= ($rolUsuario === 'admin' || $rolUsuario === 'administrador') ? 'text-warning' : 'text-light' ?> small">
                    <?= ucfirst($rolUsuario === 'admin' || $rolUsuario === 'administrador' ? 'administrador' : $rolUsuario) ?>
                </div>
            </div>

            <a href="#" id="catalogoToggle"
               class="sidebar-link <?= (strpos($currentPage, 'gestion_catalogo') !== false) ? 'current-page' : '' ?>">Gestión de Catálogo</a>
            <div class="submenu <?= (strpos($currentPage, 'gestion_catalogo') !== false) ? 'active' : '' ?>" id="catalogoSubmenu">
                <a href="gestion_catalogo_categorias.php"
                   class="<?= ($currentPage === 'gestion_catalogo_categorias') ? 'current-page' : '' ?>">Categorías</a>
                <a href="gestion_catalogo_productos.php"
                   class="<?= ($currentPage === 'gestion_catalogo_productos') ? 'current-page' : '' ?>">Productos</a>
                <a href="gestion_catalogo_proveedores.php"
                   class="<?= ($currentPage === 'gestion_catalogo_proveedores') ? 'current-page' : '' ?>">Proveedores</a>
            </div>
            
            <a href="gestion_usuarios.php"
               class="<?= ($currentPage === 'gestion_usuarios') ? 'current-page' : '' ?>">Gestión de Usuarios</a>
            
            <a href="#" id="existenciasToggle" 
               class="sidebar-link <?= (strpos($currentPage, 'gestion_existencias') !== false) ? 'current-page' : '' ?>">Gestión de Existencias</a>
            <div class="submenu <?= (strpos($currentPage, 'gestion_existencias') !== false) ? 'active' : '' ?>" id="existenciasSubmenu">
                <a href="gestion_existencias_pedidos.php"
                   class="<?= ($currentPage === 'gestion_existencias_pedidos') ? 'current-page' : '' ?>">Pedidos</a>
                <a href="gestion_existencias_ventas.php"
                   class="<?= ($currentPage === 'gestion_existencias_ventas') ? 'current-page' : '' ?>">Ventas</a>
                <a href="gestion_existencias_devoluciones.php"
                   class="<?= ($currentPage === 'gestion_existencias_devoluciones') ? 'current-page' : '' ?>">Devoluciones</a>
                <a href="gestion_existencias_stock.php"
                   class="<?= ($currentPage === 'gestion_existencias_stock') ? 'current-page' : '' ?>">Stock</a>
            </div>
            
            <a href="configuracion.php"
               class="<?= ($currentPage === 'configuracion') ? 'current-page' : '' ?>">Configuración</a>

            <div class="mt-4 text-center">
                <a href="principal.php" class="btn btn-outline-primary w-100">Volver al Inicio</a>
            </div>
        </div>

        <div class="col-md-10 content">
            <h3 class="main-title">Gestión de Usuarios</h3>

            <?php if (!empty($alert_message)): ?>
                <div id="pageAlert" class="alert alert-<?= htmlspecialchars($alert_type) ?> alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($alert_message) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
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
                                    <td><?= htmlspecialchars($user['id']) ?></td>
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
                                            <a href="editar_usuario.php?type=<?= htmlspecialchars($user['tipo_usuario']) ?>&id=<?= htmlspecialchars($user['id']) ?>" class="btn btn-sm btn-info">Editar</a>
                                            <button type="button"
                                                    class="btn btn-sm btn-danger eliminar-usuario-btn"
                                                    data-id="<?= htmlspecialchars($user['id']) ?>"
                                                    data-usuario="<?= htmlspecialchars($user['usuario']) ?>"
                                                    data-tipo="<?= htmlspecialchars($user['tipo_usuario']) ?>">
                                                Eliminar
                                            </button>
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

<form id="deleteForm" action="eliminar_usuario.php" method="POST" style="display: none;">
    <input type="hidden" name="user_id" id="deleteUserId">
    <input type="hidden" name="user_type" id="deleteUserType">
</form>

<div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-labelledby="confirmDeleteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="confirmDeleteModalLabel">Confirmar Eliminación</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                ¿Estás seguro de que deseas eliminar al usuario **<span id="modalUserName" class="fw-bold"></span>** (ID: <span id="modalUserId"></span>, Tipo: <span id="modalUserType"></span>)? Esta acción es irreversible.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteButton">Eliminar</button>
            </div>
        </div>
    </div>
</div>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', (event) => {
        // Lógica del submenu para "Gestión de Catálogo"
        const catalogoToggle = document.getElementById('catalogoToggle');
        const catalogoSubmenu = document.getElementById('catalogoSubmenu');

        // Abre el submenú de catálogo si alguna de sus sub-páginas está activa
        if (catalogoToggle && catalogoSubmenu && catalogoToggle.classList.contains('current-page')) {
            catalogoSubmenu.classList.add('active');
        }

        if (catalogoToggle) { // Solo si el elemento existe
            catalogoToggle.addEventListener('click', function(e) {
                e.preventDefault(); // Previene el comportamiento predeterminado del enlace
                if (catalogoSubmenu) {
                    catalogoSubmenu.classList.toggle('active'); // Alterna la clase 'active' para mostrar/ocultar
                }
            });
        }
        
        // Lógica para el submenú de "Gestión de Existencias"
        const existenciasToggle = document.getElementById('existenciasToggle');
        const existenciasSubmenu = document.getElementById('existenciasSubmenu');

        // Abre el submenú de existencias si alguna de sus sub-páginas está activa
        if (existenciasToggle && existenciasSubmenu && existenciasToggle.classList.contains('current-page')) {
             existenciasSubmenu.classList.add('active');
        }
        
        if (existenciasToggle) {
             existenciasToggle.addEventListener('click', function(e) {
                 e.preventDefault();
                 if (existenciasSubmenu) {
                     existenciasSubmenu.classList.toggle('active');
                 }
             });
        }

        // =================================================================
        // JavaScript para manejar la eliminación (el modal que ya existía para esto)
        // =================================================================
        const deleteButtons = document.querySelectorAll('.eliminar-usuario-btn');
        const confirmDeleteModal = new bootstrap.Modal(document.getElementById('confirmDeleteModal'));
        const modalUserName = document.getElementById('modalUserName');
        const modalUserId = document.getElementById('modalUserId');
        const modalUserType = document.getElementById('modalUserType');
        const confirmDeleteButton = document.getElementById('confirmDeleteButton');
        const deleteForm = document.getElementById('deleteForm');
        const deleteUserIdInput = document.getElementById('deleteUserId');
        const deleteUserTypeInput = document.getElementById('deleteUserType');

        deleteButtons.forEach(button => {
            button.addEventListener('click', function() {
                const userId = this.dataset.id;
                const userName = this.dataset.usuario;
                const userType = this.dataset.tipo;

                // Rellenar el modal con los datos del usuario
                modalUserName.textContent = userName;
                modalUserId.textContent = userId;
                modalUserType.textContent = userType;

                // Configurar el formulario oculto para el envío
                deleteUserIdInput.value = userId;
                deleteUserTypeInput.value = userType;

                // Mostrar el modal
                confirmDeleteModal.show();
            });
        });

        // Cuando se hace clic en el botón "Eliminar" dentro del modal
        confirmDeleteButton.addEventListener('click', function() {
            deleteForm.submit(); // Envía el formulario oculto
        });
        // =================================================================
        // FIN JavaScript para manejar la eliminación
        // =================================================================
    });
</script>
</body>
</html>