<?php
session_start(); // Inicia la sesión para poder usar variables de sesión
include("config.php"); // Asegúrate de que 'config.php' contenga la conexión PDO a tu base de datos.

// Redirecciona al login si el usuario no ha iniciado sesión
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}

// Obtener datos del usuario actual
$usuario = $_SESSION['usuario'];
$sqlUser = "SELECT per.nombre, per.apellido_paterno, r.nombre AS rol
            FROM usuario_empleado ue
            JOIN persona per ON ue.persona_id = per.persona_id
            JOIN rol r ON ue.rol_id = r.rol_id
            WHERE ue.usuario = :usuario";
$stmtUser = $pdo->prepare($sqlUser);
$stmtUser->execute(['usuario' => $usuario]);
$datosUsuario = $stmtUser->fetch(PDO::FETCH_ASSOC); // Fetch como array asociativo

// Asignar valores
$nombreCompleto = 'Usuario Desconocido'; // Valor predeterminado
$rol = ''; // Valor predeterminado
if ($datosUsuario) {
    $nombreCompleto = htmlspecialchars($datosUsuario['nombre'] . ' ' . $datosUsuario['apellido_paterno']);
    $rol = strtolower(htmlspecialchars($datosUsuario['rol']));
}

// **LÓGICA PARA MOSTRAR MENSAJES EN ALERTAS DE BOOTSTRAP**
$mensaje = '';
$tipoMensaje = '';

if (isset($_SESSION['modal_message']) && !empty($_SESSION['modal_message'])) {
    $mensaje = $_SESSION['modal_message'];
    $tipoMensaje = $_SESSION['modal_type'];
    unset($_SESSION['modal_message']);
    unset($_SESSION['modal_type']);
} elseif (isset($_GET['mensaje']) && !empty($_GET['mensaje'])) {
    $mensaje = $_GET['mensaje'];
    $tipoMensaje = $_GET['tipo'] ?? 'info';
}

// Filtros
$filtroDevolucion = $_GET['devolucion'] ?? '';
$filtroFecha = $_GET['fecha'] ?? '';
$filtroTipo = $_GET['tipo'] ?? '';

// Armado dinámico del WHERE
$where = [];
$params = [];

if (!empty($filtroDevolucion)) {
    $where[] = "d.devolucion_id = :devolucion";
    $params[':devolucion'] = (int)$filtroDevolucion; // Asegúrate de que sea un entero
}
if (!empty($filtroFecha)) {
    $where[] = "CAST(d.fecha AS DATE) = :fecha";
    $params[':fecha'] = $filtroFecha;
}
if (!empty($filtroTipo)) {
    $where[] = "d.tipo = :tipo";
    $params[':tipo'] = $filtroTipo;
}

$whereClause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

// Consulta principal
$sql = "SELECT d.devolucion_id, d.fecha, dd.cantidad, dd.motivo, dd.reingresado_stock,
                pr.nombre AS producto,
                CASE
                    WHEN d.tipo = 'cliente' THEN (SELECT p.nombre || ' ' || p.apellido_paterno
                                                 FROM persona p
                                                 JOIN usuario_cliente uc ON uc.persona_id = p.persona_id
                                                 WHERE uc.usuario_cliente_id = d.usuario_cliente_id)
                    WHEN d.tipo = 'proveedor' THEN (SELECT p.nombre || ' ' || p.apellido_paterno
                                                 FROM persona p
                                                 JOIN usuario_empleado ue ON ue.persona_id = p.persona_id
                                                 WHERE ue.usuario_empleado_id = d.usuario_empleado_id)
                    ELSE 'No asignado'
                END AS devuelto_por,
                d.tipo
        FROM devolucion d
        JOIN detalle_devolucion dd ON d.devolucion_id = dd.devolucion_id
        JOIN producto pr ON dd.producto_id = pr.producto_id
        $whereClause
        ORDER BY d.devolucion_id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$devoluciones = $stmt->fetchAll(PDO::FETCH_ASSOC); // Fetch como array asociativo

// Variable para el nombre del archivo actual (sin la extensión .php)
$currentPage = basename($_SERVER['PHP_SELF'], ".php");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Existencias - Devoluciones</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/estilos.css?v=<?= time(); ?>">
    <style>
        /* CSS para el sidebar y submenús */
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

        /* Estilo para el enlace de la página ACTUAL con texto y fondo de los ejemplos */
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

        /* Estilo para el botón "+ Generar Devolución" */
        .btn-success {
            background-color: #ffc107; /* Fondo amarillo anaranjado */
            border-color: #ffc107;
            color: #343a40; /* Texto oscuro para contraste */
            font-weight: bold;
        }

        .btn-success:hover {
            background-color: #e0a800; /* Un poco más oscuro al pasar el ratón */
            border-color: #e0a800;
            color: #343a40;
        }

        /* Estilo para el botón de búsqueda */
        .btn-primary {
            background-color: #007bff; /* Azul de Bootstrap */
            border-color: #007bff;
            font-weight: bold;
        }

        .btn-primary:hover {
            background-color: #0056b3;
            border-color: #0056b3;
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
                <div class="fw-bold"><?= $nombreCompleto ?></div>
                <div class="<?= ($rol === 'admin') ? 'text-warning' : 'text-light' ?> small">
                    <?= ucfirst($rol === 'admin' ? 'administrador' : $rol) ?>
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
            <h3 class="mb-4">Gestión de Existencias - Devoluciones</h3>

            <?php
            // BLOQUE PARA MOSTRAR LA ALERTA DE BOOTSTRAP
            if (!empty($mensaje) && !empty($tipoMensaje)) {
                $alertClass = '';
                switch ($tipoMensaje) {
                    case 'success':
                        $alertClass = 'alert-success';
                        break;
                    case 'danger':
                        $alertClass = 'alert-danger';
                        break;
                    case 'warning':
                        $alertClass = 'alert-warning';
                        break;
                    case 'info':
                        $alertClass = 'alert-info';
                        break;
                    default:
                        $alertClass = 'alert-info'; // Tipo predeterminado si no coincide
                }
                echo '<div class="alert ' . $alertClass . ' alert-dismissible fade show mt-3" role="alert">';
                echo htmlspecialchars($mensaje); // Muestra el mensaje escapando caracteres especiales
                echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>'; // Botón para cerrar la alerta
                echo '</div>';
            }
            ?>

            <div class="mb-3">
                <a href="nueva_devolucion.php" class="btn btn-success">+ Generar Devolución</a>
            </div>

            <form class="row g-2 mb-4" method="GET">
                <div class="col-md-3">
                    <input type="number" name="devolucion" value="<?= htmlspecialchars($filtroDevolucion) ?>" class="form-control" placeholder="#Devolución">
                </div>
                <div class="col-md-3">
                    <input type="date" name="fecha" value="<?= htmlspecialchars($filtroFecha) ?>" class="form-control">
                </div>
                <div class="col-md-3">
                    <select name="tipo" class="form-select">
                        <option value="">Todos</option>
                        <option value="cliente" <?= $filtroTipo === 'cliente' ? 'selected' : '' ?>>Cliente</option>
                        <option value="proveedor" <?= $filtroTipo === 'proveedor' ? 'selected' : '' ?>>Proveedor</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <button class="btn btn-primary w-100">Buscar</button>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead class="text-center">
                        <tr>
                            <th>#Devolución</th>
                            <th>Fecha</th>
                            <th>Producto</th>
                            <th>Cantidad</th>
                            <th>Motivo</th>
                            <th>Devuelto Por</th>
                            <th>Tipo</th>
                            <th>Stock Reingresado</th>
                        </tr>
                    </thead>
                    <tbody class="text-center">
                        <?php if (count($devoluciones) > 0): ?>
                            <?php foreach ($devoluciones as $row): ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['devolucion_id']) ?></td>
                                    <td><?= htmlspecialchars($row['fecha']) ?></td>
                                    <td><?= htmlspecialchars($row['producto']) ?></td>
                                    <td><?= htmlspecialchars($row['cantidad']) ?></td>
                                    <td><?= htmlspecialchars($row['motivo']) ?></td>
                                    <td><?= htmlspecialchars($row['devuelto_por']) ?></td>
                                    <td><span class="badge bg-dark"><?= ucfirst(htmlspecialchars($row['tipo'])) ?></span></td>
                                    <td>
                                        <span class="badge bg-<?= $row['reingresado_stock'] ? 'success' : 'danger' ?>">
                                            <?= $row['reingresado_stock'] ? 'Sí' : 'No' ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center">No se encontraron devoluciones.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
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
    });
</script>
</body>
</html>