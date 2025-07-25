<?php
session_start(); // Inicia la sesión al principio
include("config.php"); // Asegúrate de que 'config.php' contenga la conexión PDO.

// 1. Verificar si el usuario está logueado
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
    $nombreUsuario = htmlspecialchars($usuarioActual['nombre'] . ' ' . $usuarioActual['apellido_paterno']);
    // Convert the role from the database to lowercase for comparison
    $rolUsuario = strtolower(htmlspecialchars($usuarioActual['rol']));
}

// **LÓGICA MEJORADA PARA MENSAJES (Prioriza SESSION, luego GET)**
$mensaje = '';
$tipoMensaje = ''; // Puede ser 'success', 'danger', 'warning', 'info'

if (isset($_SESSION['modal_message']) && !empty($_SESSION['modal_message'])) {
    $mensaje = $_SESSION['modal_message'];
    $tipoMensaje = $_SESSION['modal_type'];
    // Limpiar las variables de sesión para que el mensaje no se muestre de nuevo al recargar la página
    unset($_SESSION['modal_message']);
    unset($_SESSION['modal_type']);
} elseif (isset($_GET['mensaje']) && !empty($_GET['mensaje'])) {
    // Si el mensaje viene por GET (como podría ser en una eliminación directa)
    $mensaje = $_GET['mensaje'];
    $tipoMensaje = $_GET['tipo'] ?? 'info'; // 'info' como tipo predeterminado si no se especifica
}

// Filtros de búsqueda
$filtro = $_GET['filtro'] ?? '';
$sqlProveedores = "SELECT proveedor_id, nombre_empresa, pais, ciudad, direccion, codigo_postal FROM proveedor
                    WHERE CAST(proveedor_id AS TEXT) ILIKE :filtro OR nombre_empresa ILIKE :filtro
                    ORDER BY proveedor_id DESC";

$stmtProv = $pdo->prepare($sqlProveedores);
$stmtProv->execute([':filtro' => "%$filtro%"]);
$proveedores = $stmtProv->fetchAll(PDO::FETCH_ASSOC); // Usar FETCH_ASSOC

// --- NUEVA LÓGICA PARA OBTENER LAS CATEGORÍAS DE CADA PROVEEDOR ---
$proveedorCategorias = [];
if (!empty($proveedores)) {
    $stmtCategorias = $pdo->prepare("
        SELECT c.nombre
        FROM categoria c
        JOIN proveedor_categoria pc ON c.categoria_id = pc.categoria_id
        WHERE pc.proveedor_id = :proveedor_id
        ORDER BY c.nombre
    ");
    foreach ($proveedores as $prov) {
        $stmtCategorias->execute([':proveedor_id' => $prov['proveedor_id']]);
        $categorias = $stmtCategorias->fetchAll(PDO::FETCH_COLUMN); // Obtiene solo los nombres de las categorías
        // Almacena las categorías como una cadena separada por comas para cada proveedor
        $proveedorCategorias[$prov['proveedor_id']] = implode(', ', $categorias); 
    }
}
// --- FIN DE NUEVA LÓGICA ---

// Variable para el nombre del archivo actual (sin la extensión .php)
$currentPage = basename($_SERVER['PHP_SELF'], ".php");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Proveedores</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/estilos.css?v=<?= time(); ?>">
    <style>
        /* CSS para el sidebar y submenús (duplicado del archivo de ventas para consistencia) */
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

        .btn-secondary {
            background-color: #6c757d; /* Gris de Bootstrap */
            border-color: #6c757d;
        }

        .btn-secondary:hover {
            background-color: #5c636a;
            border-color: #565e64;
        }


        /* Contenido principal */
        .content {
            padding: 20px;
        }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <div class="col-md-2 sidebar">
            <div class="user-box text-center mb-4">
                <img src="https://cdn-icons-png.flaticon.com/512/149/149071.png" alt="Usuario" class="img-fluid rounded-circle mb-2" style="width: 64px;">

                <div class="fw-bold"><?= $nombreUsuario ?></div>

                <div class="<?= ($rolUsuario === 'admin') ? 'text-warning' : 'text-light' ?> small">
                    <?= ucfirst($rolUsuario === 'admin' ? 'administrador' : $rolUsuario) ?>
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
            <h3 class="main-title">Gestión de Proveedores</h3>

            <?php
            // **BLOQUE PARA MOSTRAR LA ALERTA DE BOOTSTRAP**
            if (!empty($mensaje) && !empty($tipoMensaje)) {
                $alertClass = '';
                switch ($tipoMensaje) {
                    case 'success': $alertClass = 'alert-success'; break;
                    case 'danger':  $alertClass = 'alert-danger';  break;
                    case 'warning': $alertClass = 'alert-warning'; break;
                    case 'info':    $alertClass = 'alert-info';    break;
                    default:        $alertClass = 'alert-info';
                }
                echo '<div class="alert ' . $alertClass . ' alert-dismissible fade show mt-3" role="alert">';
                echo htmlspecialchars($mensaje); // Muestra el mensaje escapando caracteres especiales
                echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>'; // Botón para cerrar la alerta
                echo '</div>';
            }
            ?>

            <div class="d-flex justify-content-between mb-3">
                <form class="d-flex flex-grow-1 me-3" method="GET" action="">
                    <div class="row g-2 align-items-center w-100">
                        <div class="col-md-6"> <input type="text" name="filtro" class="form-control" placeholder="Buscar por ID o Empresa" value="<?= htmlspecialchars($filtro) ?>">
                        </div>
                        <div class="col-md-3">
                            <button class="btn btn-primary w-100" type="submit">Buscar</button>
                        </div>
                        <div class="col-md-3">
                            <a href="gestion_catalogo_proveedores.php" class="btn btn-secondary w-100">Limpiar</a>
                        </div>
                    </div>
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
                            <th>Categorías</th> <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($proveedores)): ?>
                            <?php foreach ($proveedores as $prov): ?>
                                <tr>
                                    <td><?= htmlspecialchars($prov['proveedor_id']) ?></td>
                                    <td><?= htmlspecialchars($prov['nombre_empresa']) ?></td>
                                    <td><?= htmlspecialchars($prov['pais']) ?></td>
                                    <td><?= htmlspecialchars($prov['ciudad']) ?></td>
                                    <td><?= htmlspecialchars($prov['direccion']) ?></td>
                                    <td><?= htmlspecialchars($prov['codigo_postal']) ?></td>
                                    <td>
                                        <?= htmlspecialchars($proveedorCategorias[$prov['proveedor_id']] ?? 'N/A') ?>
                                    </td>
                                    <td>
                                        <div class="d-flex justify-content-center gap-2">
                                            <a href="editar_proveedor.php?id=<?= htmlspecialchars($prov['proveedor_id']) ?>" class="btn btn-sm btn-info">Editar</a>
                                            <button type="button"
                                                     class="btn btn-sm btn-danger delete-btn"
                                                     data-bs-toggle="modal"
                                                     data-bs-target="#confirmDeleteProveedorModal"
                                                     data-id="<?= htmlspecialchars($prov['proveedor_id']) ?>"
                                                     data-nombre="<?= htmlspecialchars($prov['nombre_empresa']) ?>">
                                                    Eliminar
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="8" class="text-muted">No se encontraron resultados.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="confirmDeleteProveedorModal" tabindex="-1" aria-labelledby="confirmDeleteProveedorModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="confirmDeleteProveedorModalLabel">Confirmar Eliminación de Proveedor</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                ¿Estás seguro de que deseas eliminar al proveedor "<span id="proveedorNombreEliminar"></span>" (ID: <span id="proveedorIdEliminar"></span>)? Esta acción es irreversible.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <a href="#" id="confirmDeleteProveedorLink" class="btn btn-danger">Eliminar</a>
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
        // LÓGICA PARA EL MODAL DE CONFIRMACIÓN DE ELIMINACIÓN DE PROVEEDORES
        // =================================================================
        const confirmDeleteProveedorModal = document.getElementById('confirmDeleteProveedorModal');
        if (confirmDeleteProveedorModal) {
            confirmDeleteProveedorModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget; // Botón que activó el modal
                const proveedorId = button.getAttribute('data-id');
                const proveedorNombre = button.getAttribute('data-nombre');

                const modalBodyId = confirmDeleteProveedorModal.querySelector('#proveedorIdEliminar');
                const modalBodyNombre = confirmDeleteProveedorModal.querySelector('#proveedorNombreEliminar');
                const confirmDeleteLink = confirmDeleteProveedorModal.querySelector('#confirmDeleteProveedorLink');

                if (modalBodyId && modalBodyNombre && confirmDeleteLink) {
                    modalBodyId.textContent = proveedorId;
                    modalBodyNombre.textContent = proveedorNombre;
                    // Establecer la URL del enlace de confirmación
                    confirmDeleteLink.href = 'eliminar_proveedor.php?id=' + proveedorId;
                }
            });
        }
    });
</script>
</body>
</html>