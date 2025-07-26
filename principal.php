<?php
session_start();
if (!isset($_SESSION['usuario'])) {
    header('Location: login.php');
    exit();
}

include("config.php");

$usuario = $_SESSION['usuario'];
// Obtener el rol y nombre completo del usuario
$sql_user_info = "SELECT per.nombre || ' ' || per.apellido_paterno AS nombre_completo, r.nombre AS rol
                  FROM usuario_empleado ue
                  JOIN persona per ON ue.persona_id = per.persona_id
                  JOIN rol r ON ue.rol_id = r.rol_id
                  WHERE ue.usuario = :usuario";
$stmt_user_info = $pdo->prepare($sql_user_info);
$stmt_user_info->execute([':usuario' => $usuario]);
$user_info = $stmt_user_info->fetch(PDO::FETCH_ASSOC);

$nombre_persona = $user_info['nombre_completo'] ?? 'Usuario';
$rolUsuario = strtolower($user_info['rol'] ?? 'usuario'); // Default to 'usuario' if not found

// Lógica para obtener comunicados del sistema
$comunicados = [];
try {
    // Asume que tienes una tabla 'comunicados' con columnas 'comunicado_id', 'titulo', 'contenido', 'fecha_publicacion' y 'activo'
    $sql_comunicados = "SELECT comunicado_id, titulo, contenido, fecha_publicacion FROM comunicados WHERE activo = TRUE ORDER BY fecha_publicacion DESC";
    $stmt_comunicados = $pdo->query($sql_comunicados);
    $comunicados = $stmt_comunicados->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error al cargar comunicados: " . $e->getMessage());
    // Puedes mostrar un mensaje al usuario si lo deseas, o simplemente no mostrar comunicados
    // $mensaje_comunicados = "<div class='alert alert-danger'>Error al cargar los comunicados del sistema.</div>";
}


// Variable para el nombre del archivo actual (sin la extensión .php)
$currentPage = basename($_SERVER['PHP_SELF'], ".php");

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Admin Panel - Electrónica</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/estilos.css?v=<?= time(); ?>">
    <style>
        /* CSS para el sidebar y submenús (duplicado para asegurar consistencia) */
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

        /* Estilos para el tema oscuro */
        body.dark-mode {
            background-color: #343a40; /* Fondo oscuro */
            color: #f8f9fa; /* Texto claro */
        }
        body.dark-mode .sidebar {
            background-color: #212529; /* Sidebar más oscuro */
        }
        body.dark-mode .content {
            background-color: #495057; /* Contenido más oscuro */
            color: #f8f9fa;
        }
        body.dark-mode .main-title {
            color: #f8f9fa;
        }
        body.dark-mode .card {
            background-color: #545b62; /* Tarjetas oscuras */
            border-color: #6c757d;
        }
        body.dark-mode .card-header {
            background-color: #6c757d !important; /* Header de tarjetas más oscuro */
            color: #f8f9fa !important;
        }
        body.dark-mode .form-control, body.dark-mode .form-select {
            background-color: #6c757d;
            color: #f8f9fa;
            border-color: #495057;
        }
        body.dark-mode .form-control::placeholder {
            color: #ced4da;
        }
        body.dark-mode .form-control:focus, body.dark-mode .form-select:focus {
            background-color: #7a8288;
            color: #f8f9fa;
            border-color: #889299;
            box-shadow: 0 0 0 0.25rem rgba(108, 117, 125, 0.25);
        }
    </style>
</head>
<body>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-2 sidebar">
            <div class="user-box text-center mb-4">
                <img src="https://cdn-icons-png.flaticon.com/512/149/149071.png" alt="Usuario" class="img-fluid rounded-circle mb-2" style="width: 64px;">

                <div class="fw-bold"><?= htmlspecialchars($nombre_persona); ?></div>

                <div class="<?= ($rolUsuario === 'admin') ? 'text-warning' : 'text-light' ?> small">
                    <?= ucfirst($rolUsuario === 'admin' ? 'administrador' : $rolUsuario) ?>
                </div>
            </div>

            <a href="principal.php" class="<?= ($currentPage === 'principal') ? 'current-page' : '' ?>">Inicio</a>

            <?php if ($rolUsuario === 'admin'): // Solo para administradores ?>
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
            <?php endif; ?>
            
            <?php if ($rolUsuario === 'admin' || $rolUsuario === 'empleado'): // Para administradores y empleados ?>
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
            <?php endif; ?>

            <div class="mt-4 text-center">
                <a href="salir.php" class="btn btn-outline-danger w-100">Cerrar Sesión</a>
            </div>
        </div>

        <div class="col-md-10 content">
            <h2 class="main-title mb-4">Comunicados del Sistema
                <?php if ($rolUsuario === 'admin'): ?>
                    <a href="gestion_comunicados.php" class="btn btn-primary btn-sm ms-3">Editar Comunicados</a>
                <?php endif; ?>
            </h2>

            <?php if (!empty($comunicados)): ?>
                <?php foreach ($comunicados as $comunicado): ?>
                    <div class="card mb-3">
                        <div class="card-body">
                            <h5 class="card-title text-primary">📢 <?= htmlspecialchars($comunicado['titulo']) ?></h5>
                            <p class="card-text"><?= nl2br(htmlspecialchars($comunicado['contenido'])) ?></p>
                            <p class="card-text"><small class="text-muted">Publicado el: <?= date('d/m/Y H:i', strtotime($comunicado['fecha_publicacion'])) ?></small></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="alert alert-info" role="alert">
                    No hay comunicados del sistema disponibles en este momento.
                </div>
            <?php endif; ?>
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

        // Lógica para el Modo Oscuro (si existe el switch en esta página)
        const darkModeSwitch = document.getElementById('darkModeSwitch');
        const body = document.body;

        // Cargar preferencia guardada al cargar la página
        if (localStorage.getItem('darkMode') === 'enabled') {
            body.classList.add('dark-mode');
            if(darkModeSwitch) darkModeSwitch.checked = true; // Check if switch exists before setting checked
        }

        if (darkModeSwitch) { // Only add listener if the switch exists on the page
            darkModeSwitch.addEventListener('change', function() {
                if (this.checked) {
                    body.classList.add('dark-mode');
                    localStorage.setItem('darkMode', 'enabled');
                } else {
                    body.classList.remove('dark-mode');
                    localStorage.setItem('darkMode', 'disabled');
                }
            });
        }
    });
</script>
</body>
</html>
