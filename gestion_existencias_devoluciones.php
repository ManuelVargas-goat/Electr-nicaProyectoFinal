<?php
session_start(); // Inicia la sesión para poder usar variables de sesión
include("config.php"); // Asegúrate de que 'config.php' contenga la conexión PDO a tu base de datos.

// --- Constantes ---
define('PEDIDO_ESTADO_ENTREGADO', 'entregado'); // No usado directamente aquí, pero útil para consistencia
define('PEDIDO_ESTADO_CANCELADO', 'cancelado'); // Corregido typo: PEDIDO_ESTIDO_CANCELADO -> PEDIDO_ESTADO_CANCELADO
define('PEDIDO_ESTADO_PENDIENTE', 'pendiente'); // No usado directamente aquí

define('ALERT_SUCCESS', 'success');
define('ALERT_DANGER', 'danger');
define('ALERT_WARNING', 'warning');
define('ALERT_INFO', 'info');

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
$rolUsuario = ''; // Variable de rol cambiada a $rolUsuario para consistencia
if ($datosUsuario) {
    $nombreCompleto = htmlspecialchars($datosUsuario['nombre'] . ' ' . $datosUsuario['apellido_paterno']);
    $rolUsuario = strtolower(htmlspecialchars($datosUsuario['rol']));
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

// --- Generar token CSRF para el formulario ---
// Este token se usará para proteger cualquier acción POST que se realice en esta página
// o que redirija a esta página después de una acción.
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// --- Lógica para PROCESAR DEVOLUCIONES (Afectar Stock) ---
// Este bloque se ejecutará si se recibe una solicitud POST, por ejemplo,
// desde un formulario en 'nueva_devolucion.php' o 'editar_devolucion.php'
// que redirige aquí después de procesar.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'procesar_devolucion') {
    // 1. Verificación CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $csrf_token) {
        $_SESSION['modal_message'] = "Error de seguridad: Solicitud no válida (CSRF). Intente nuevamente.";
        $_SESSION['modal_type'] = ALERT_DANGER;
        header("Location: gestion_existencias_devoluciones.php");
        exit();
    }
    // Regenerar el token CSRF después de un uso exitoso para prevenir reenvíos
    unset($_SESSION['csrf_token']);
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    // Recopilar y validar datos de la devolución
    $devolucionId = filter_var($_POST['devolucion_id'] ?? null, FILTER_VALIDATE_INT);
    $productoId = filter_var($_POST['producto_id'] ?? null, FILTER_VALIDATE_INT);
    $cantidad = filter_var($_POST['cantidad'] ?? null, FILTER_VALIDATE_INT);
    $tipoDevolucion = $_POST['tipo_devolucion'] ?? ''; // 'cliente' o 'proveedor'
    $reingresadoStock = isset($_POST['reingresado_stock']) ? true : false; // Solo para devoluciones de cliente

    // Validar datos mínimos
    if (!$devolucionId || !$productoId || !$cantidad || $cantidad <= 0 || empty($tipoDevolucion)) {
        $_SESSION['modal_message'] = "Error: Datos de devolución incompletos o inválidos para procesar stock.";
        $_SESSION['modal_type'] = ALERT_DANGER;
        header("Location: gestion_existencias_devoluciones.php");
        exit();
    }

    try {
        $pdo->beginTransaction();

        // Lógica para actualizar el stock
        $stmtUpdateStock = $pdo->prepare("UPDATE stock SET cantidad = cantidad " . ($tipoDevolucion === 'cliente' && $reingresadoStock ? '+' : '-') . " :cantidad, fecha_ultima_entrada = NOW() WHERE producto_id = :producto_id");
        
        // Ejecutar la actualización de stock
        $stmtUpdateStock->execute([
            ':cantidad' => $cantidad,
            ':producto_id' => $productoId
        ]);

        // NUEVO: Actualizar el flag reingresado_stock en la tabla detalle_devolucion
        // Esto asegura que el valor mostrado en la tabla sea consistente con la acción de procesamiento.
        $stmtUpdateDetalleDevolucionFlag = $pdo->prepare("UPDATE detalle_devolucion SET reingresado_stock = :reingresado_stock WHERE devolucion_id = :devolucion_id AND producto_id = :producto_id");
        $stmtUpdateDetalleDevolucionFlag->execute([
            ':reingresado_stock' => $reingresadoStock,
            ':devolucion_id' => $devolucionId,
            ':producto_id' => $productoId
        ]);

        $pdo->commit();
        $_SESSION['modal_message'] = "Stock y estado de reingreso actualizados para la devolución #" . htmlspecialchars($devolucionId) . ".";
        $_SESSION['modal_type'] = ALERT_SUCCESS;

    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['modal_message'] = "Error al procesar la devolución y actualizar el stock: " . $e->getMessage();
        $_SESSION['modal_type'] = ALERT_DANGER;
        error_log("Error al procesar devolución y stock en gestion_existencias_devoluciones.php: " . $e->getMessage());
    }
    // Redirigir siempre después de procesar el POST
    header("Location: gestion_existencias_devoluciones.php");
    exit();
}


// --- Lógica de Filtrado de Devoluciones ---
$filtroDevolucionId = $_GET['devolucion_id'] ?? '';
$filtroFechaInicio = $_GET['fecha_inicio'] ?? '';
$filtroFechaFin = $_GET['fecha_fin'] ?? '';
$filtroTipo = $_GET['tipo'] ?? '';
$filtroProductoId = $_GET['producto_id'] ?? ''; // Nuevo filtro para producto

$where = [];
$params = [];

if (!empty($filtroDevolucionId)) {
    $where[] = "d.devolucion_id = :devolucion_id";
    $params[':devolucion_id'] = (int)$filtroDevolucionId;
}
if (!empty($filtroFechaInicio)) {
    $where[] = "d.fecha >= :fecha_inicio";
    $params[':fecha_inicio'] = $filtroFechaInicio . ' 00:00:00'; // Incluir desde el inicio del día
}
if (!empty($filtroFechaFin)) {
    $where[] = "d.fecha <= :fecha_fin";
    $params[':fecha_fin'] = $filtroFechaFin . ' 23:59:59'; // Incluir hasta el final del día
}
if (!empty($filtroTipo)) {
    $where[] = "d.tipo = :tipo";
    $params[':tipo'] = $filtroTipo;
}
if (!empty($filtroProductoId)) { // Nuevo: Filtrar por producto
    $where[] = "dd.producto_id = :producto_id";
    $params[':producto_id'] = (int)$filtroProductoId;
}

$whereClause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

// Consulta principal para obtener las devoluciones y sus detalles
$sql = "SELECT d.devolucion_id,
                TO_CHAR(d.fecha, 'YYYY-MM-DD') AS fecha_solo_fecha,
                TO_CHAR(d.fecha, 'HH24:MI:SS') AS fecha_solo_hora,
                dd.cantidad, dd.motivo, dd.reingresado_stock,
                pr.nombre AS producto,
                CASE
                    WHEN d.tipo = 'cliente' THEN COALESCE((SELECT p.nombre || ' ' || p.apellido_paterno
                                                           FROM persona p
                                                           JOIN usuario_cliente uc ON uc.persona_id = p.persona_id
                                                           WHERE uc.usuario_cliente_id = d.usuario_cliente_id), 'Cliente Desconocido')
                    WHEN d.tipo = 'proveedor' THEN COALESCE((SELECT p.nombre || ' ' || p.apellido_paterno
                                                             FROM persona p
                                                             JOIN usuario_empleado ue ON ue.persona_id = p.persona_id
                                                             WHERE ue.usuario_empleado_id = d.usuario_empleado_id), 'Empleado Desconocido')
                    ELSE 'N/A'
                END AS devuelto_por,
                d.tipo
        FROM devolucion d
        JOIN detalle_devolucion dd ON d.devolucion_id = dd.devolucion_id
        JOIN producto pr ON dd.producto_id = pr.producto_id
        $whereClause
        ORDER BY d.devolucion_id DESC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $devoluciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error al cargar devoluciones: " . $e->getMessage());
    $devoluciones = []; // Asegura que $devoluciones esté definido
    $mensaje = "Error al cargar las devoluciones: " . $e->getMessage();
    $tipoMensaje = ALERT_DANGER;
}

// Obtener todos los productos para el nuevo filtro desplegable
$sqlProductos = "SELECT producto_id, nombre FROM producto ORDER BY nombre";
$productos = $pdo->query($sqlProductos)->fetchAll(PDO::FETCH_ASSOC);

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

        /* Estilo para el input de búsqueda del producto */
        .product-search-container {
            position: relative;
            width: 100%;
        }
        .product-search-input {
            width: 100%;
            padding-right: 1.5rem; /* Espacio para una posible flecha o limpiar texto */
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23343a40' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M2 5l6 6 6-6'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 0.5rem center;
            background-size: 1rem 1rem;
        }
        .product-select-dropdown {
            display: block;
            width: 100%;
            height: auto;
            max-height: 200px;
            overflow-y: auto;
            border: 1px solid #ced4da;
            border-top: none;
            border-radius: 0 0 .25rem .25rem;
            position: absolute;
            z-index: 1000;
            background-color: #fff;
            box-shadow: 0 .5rem 1rem rgba(0,0,0,.15);
            padding: 0;
            margin-top: -1px;
        }
        .product-select-dropdown option {
            padding: .375rem .75rem;
            cursor: pointer;
        }
        .product-select-dropdown option:hover {
            background-color: #e9ecef;
        }
        .product-select-dropdown option[hidden] {
            display: none;
        }
        .product-select-dropdown:focus {
            outline: none;
        }
        
        /* Ajustes específicos para que los botones quepan en línea */
        .btn-group-inline .btn {
            white-space: nowrap; /* Evita que el texto de los botones se rompa */
            padding: .375rem .5rem; /* Reduce el padding de los botones */
            font-size: .875rem; /* Reduce el tamaño de la fuente de los botones */
        }
        /* Margen entre botones dentro del mismo grupo en línea */
        .btn-group-inline .btn + .btn {
            margin-left: 5px; /* Pequeño margen entre botones */
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
                <div class="<?= ($rolUsuario === 'admin') ? 'text-warning' : 'text-light' ?> small">
                    <?= ucfirst($rolUsuario === 'admin' ? 'administrador' : $rolUsuario) ?>
                </div>
            </div>

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
                        $alertClass = 'alert-info'; // Tipo predeterminado si no coincide
                }
                echo '<div class="alert ' . $alertClass . ' alert-dismissible fade show mt-3" role="alert" style="margin-left: -20px; margin-right: -20px; width: calc(100% + 40px);">';
                echo htmlspecialchars($mensaje); // Muestra el mensaje escapando caracteres especiales
                echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>'; // Botón para cerrar la alerta
                echo '</div>';
            }
            ?>

            <div class="mb-3">
                <a href="nueva_devolucion.php" class="btn btn-primary">+ Generar Devolución</a>
            </div>

            <form class="row gx-2 mb-4 align-items-end" method="GET">
                <div class="col-md-2 col-sm-6">
                    <label for="devolucion_id" class="form-label visually-hidden">#Devolución</label>
                    <input type="number" id="devolucion_id" name="devolucion_id" value="<?= htmlspecialchars($filtroDevolucionId) ?>" class="form-control form-control-sm" placeholder="#Devolución">
                </div>
                <div class="col-md-2 col-sm-6">
                    <label for="fecha_inicio" class="form-label visually-hidden">Fecha Inicio</label>
                    <input type="date" id="fecha_inicio" name="fecha_inicio" value="<?= htmlspecialchars($filtroFechaInicio) ?>" class="form-control form-control-sm" title="Fecha de Inicio">
                </div>
                <div class="col-md-2 col-sm-6">
                    <label for="fecha_fin" class="form-label visually-hidden">Fecha Fin</label>
                    <input type="date" id="fecha_fin" name="fecha_fin" value="<?= htmlspecialchars($filtroFechaFin) ?>" class="form-control form-control-sm" title="Fecha de Fin">
                </div>
                <div class="col-md-3 col-sm-6"> <label for="productSearchInput" class="form-label visually-hidden">Producto</label>
                    <div class="product-search-container">
                        <input type="text" id="productSearchInput" class="form-control form-control-sm product-search-input" placeholder="Todos los productos" autocomplete="off">
                        <select name="producto_id" id="productoSelect" class="form-select product-select-dropdown" size="5" style="display: none;">
                            <option value="">Todos los productos </option>
                            <?php foreach ($productos as $prod): ?>
                                <option value="<?= htmlspecialchars($prod['producto_id']) ?>" <?= ($filtroProductoId == $prod['producto_id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($prod['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-1 col-sm-6"> <label for="tipo" class="form-label visually-hidden">Tipo</label>
                    <select id="tipo" name="tipo" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        <option value="cliente" <?= $filtroTipo === 'cliente' ? 'selected' : '' ?>>Cli.</option> <option value="proveedor" <?= $filtroTipo === 'proveedor' ? 'selected' : '' ?>>Prov.</option> </select>
                </div>
                <div class="col-md-2 col-sm-12 d-flex justify-content-end align-items-center btn-group-inline">
                    <button type="submit" class="btn btn-primary btn-sm">Buscar</button>
                    <a href="gestion_existencias_devoluciones.php" class="btn btn-secondary btn-sm">Limpiar</a>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead class="text-center">
                        <tr>
                            <th>#Devolución</th>
                            <th>Fecha</th>
                            <th>Hora</th>
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
                                    <td><?= htmlspecialchars($row['fecha_solo_fecha']) ?></td>
                                    <td><?= htmlspecialchars($row['fecha_solo_hora']) ?></td>
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
                                <td colspan="9" class="text-center">No se encontraron devoluciones.</td>
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

        // Script para evitar que los dropdowns se cierren al hacer clic dentro
        const dropdowns = document.querySelectorAll('.dropdown-toggle');
        dropdowns.forEach(btn => {
            btn.addEventListener('click', e => e.stopPropagation());
        });

        const menus = document.querySelectorAll('.dropdown-menu');
        menus.forEach(menu => {
            menu.addEventListener('click', e => e.stopPropagation());
        });

        // --- Lógica para el filtro de producto con búsqueda ---
        const productSearchInput = document.getElementById('productSearchInput');
        const productoSelect = document.getElementById('productoSelect');
        const allProductOptions = Array.from(productoSelect.options); // Guardar todas las opciones originales

        // Mostrar/ocultar el select al enfocar/desenfocar el input
        productSearchInput.addEventListener('focus', () => {
            productoSelect.style.display = 'block';
            filterProductOptions(); // Filtrar al enfocar por si ya hay texto
        });

        // Ocultar el select cuando se pierde el foco, con un pequeño retraso
        // para permitir el clic en una opción
        productSearchInput.addEventListener('blur', () => {
            setTimeout(() => {
                productoSelect.style.display = 'none';
            }, 150); // Pequeño retraso
        });

        // Filtrar opciones mientras se escribe
        productSearchInput.addEventListener('input', filterProductOptions);

        function filterProductOptions() {
            const searchText = productSearchInput.value.toLowerCase();
            let hasVisibleOptions = false;
            allProductOptions.forEach(option => {
                const optionText = option.textContent.toLowerCase();
                // Si la opción es "Todos los productos" (value vacío)
                if (option.value === "") {
                    // Solo mostrar "Todos los productos" si el campo de búsqueda está vacío
                    // o si el texto de búsqueda coincide con "todos los productos"
                    if (searchText === "" || optionText.includes(searchText)) {
                        option.style.display = '';
                        option.hidden = false;
                    } else {
                        option.style.display = 'none';
                        option.hidden = true;
                    }
                } else if (optionText.includes(searchText)) {
                    option.style.display = ''; // Mostrar
                    option.hidden = false;
                    hasVisibleOptions = true;
                } else {
                    option.style.display = 'none'; // Ocultar
                    option.hidden = true;
                }
            });
            // Asegurarse de que el dropdown se muestre si hay opciones visibles o si el campo está vacío
            if (hasVisibleOptions || searchText === "") {
                productoSelect.style.display = 'block';
            } else {
                productoSelect.style.display = 'none';
            }
        }

        // Seleccionar una opción del select y actualizar el input
        productoSelect.addEventListener('change', () => {
            const selectedOption = productoSelect.options[productoSelect.selectedIndex];
            if (selectedOption) {
                if (selectedOption.value === "") {
                    productSearchInput.value = ""; // Limpiar el input para que se vea el placeholder
                    productSearchInput.placeholder = "Todos los productos"; // Asegurar que el placeholder se muestre
                    productoSelect.value = ""; // Asegurar que el select retenga la opción vacía
                } else {
                    productSearchInput.value = selectedOption.textContent;
                    productSearchInput.placeholder = ""; // Ocultar el placeholder
                }
                productoSelect.style.display = 'none'; // Ocultar el select después de la selección
            }
        });

        // Al cargar la página, si hay un filtro de producto, mostrar el nombre; si no, dejar el input vacío
        // para que el placeholder "Todos los productos" se vea.
        const initialSelectedProductId = "<?= htmlspecialchars($filtroProductoId) ?>";
        if (initialSelectedProductId) {
            const selectedOption = Array.from(productoSelect.options).find(opt => opt.value === initialSelectedProductId);
            if (selectedOption) {
                productSearchInput.value = selectedOption.textContent;
                productSearchInput.placeholder = ""; // Si hay un valor, ocultar el placeholder
            }
        } else {
            productSearchInput.value = ""; // Asegura que el input esté vacío para que el placeholder se muestre
            productSearchInput.placeholder = "Todos los productos"; // Establecer el placeholder
        }

        // Cuando el usuario borra el texto del input, el placeholder debe volver a aparecer.
        productSearchInput.addEventListener('input', () => {
            if (productSearchInput.value === "") {
                productSearchInput.placeholder = "Todos los productos";
                // Cuando el input está vacío, aseguramos que la opción "Todos los productos" esté seleccionada en el select oculto
                productoSelect.value = ""; 
            } else {
                productSearchInput.placeholder = ""; 
            }
        });
        
    });
</script>
</body>
</html>
