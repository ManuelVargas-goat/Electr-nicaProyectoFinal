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
$sql_usuario = "SELECT per.nombre, per.apellido_paterno, r.nombre AS rol
                FROM usuario_empleado ue
                JOIN persona per ON ue.persona_id = per.persona_id
                JOIN rol r ON ue.rol_id = r.rol_id
                WHERE ue.usuario = :usuario";
$stmt = $pdo->prepare($sql_usuario);
$stmt->execute([':usuario' => $usuario]);
$datosUsuario = $stmt->fetch(PDO::FETCH_ASSOC);

// Asignar valores
$nombreCompleto = 'Usuario Desconocido'; // Valor predeterminado
$rolUsuario = ''; // Valor predeterminado
if ($datosUsuario) {
    $nombreCompleto = htmlspecialchars($datosUsuario['nombre'] . ' ' . $datosUsuario['apellido_paterno']);
    $rolUsuario = strtolower(htmlspecialchars($datosUsuario['rol']));
}

// **LÓGICA PARA MOSTRAR MENSAJES EN ALERTAS DE BOOTSTRAP**
// Priorizamos los mensajes almacenados en la sesión
// Luego, si no hay en sesión, verificamos los parámetros GET
$mensaje = '';
$tipoMensaje = ''; // Puede ser 'success', 'danger', 'warning', 'info'

if (isset($_SESSION['modal_message']) && !empty($_SESSION['modal_message'])) {
    $mensaje = $_SESSION['modal_message'];
    $tipoMensaje = $_SESSION['modal_type'];
    // Limpiar las variables de sesión para que el mensaje no se muestre de nuevo al recargar la página
    unset($_SESSION['modal_message']);
    unset($_SESSION['modal_type']);
} elseif (isset($_GET['mensaje']) && !empty($_GET['mensaje'])) {
    // Si el mensaje viene por GET
    $mensaje = $_GET['mensaje'];
    $tipoMensaje = $_GET['tipo'] ?? 'info'; // 'info' como tipo predeterminado si no se especifica
}

// Obtener estados para el filtro
$estados = $pdo->query("SELECT estado_id, nombre FROM estado ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);

// **NUEVO: Obtener todos los clientes para el dropdown**
$sql_clientes = "SELECT uc.usuario_cliente_id, per.nombre, per.apellido_paterno
                 FROM usuario_cliente uc
                 JOIN persona per ON uc.persona_id = per.persona_id
                 ORDER BY per.nombre, per.apellido_paterno";
$stmt_clientes = $pdo->query($sql_clientes);
$clientes = $stmt_clientes->fetchAll(PDO::FETCH_ASSOC);

// Capturar filtros de ventas
$filtroClienteId = $_GET['cliente_id'] ?? ''; // CAMBIO: Ahora se espera el ID del cliente
$filtroFechaInicio = $_GET['fecha_inicio'] ?? '';
$filtroFechaFin = $_GET['fecha_fin'] ?? '';
$filtroEstadoId = $_GET['estado'] ?? '';
$filtroVentaId = $_GET['venta_id'] ?? '';

$where = [];
$params = [];

// CAMBIO: Filtrar por ID de cliente si se seleccionó uno
if ($filtroClienteId !== '') {
    $where[] = "c.usuario_cliente_id = :cliente_id";
    $params[':cliente_id'] = (int)$filtroClienteId;
}
if ($filtroFechaInicio !== '') {
    $where[] = "CAST(c.fecha_compra AS DATE) >= :fecha_inicio";
    $params[':fecha_inicio'] = $filtroFechaInicio;
}
if ($filtroFechaFin !== '') {
    $where[] = "CAST(c.fecha_compra AS DATE) <= :fecha_fin";
    $params[':fecha_fin'] = $filtroFechaFin;
}
if ($filtroEstadoId !== '') {
    $where[] = "c.estado_id = :estado_id";
    $params[':estado_id'] = (int)$filtroEstadoId;
}
if ($filtroVentaId !== '') {
    $where[] = "c.compra_id = :compra_id";
    $params[':compra_id'] = (int)$filtroVentaId;
}

$whereClause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

$sqlVentas = "SELECT
                c.compra_id,
                c.fecha_compra,
                COALESCE(per.nombre || ' ' || per.apellido_paterno, 'Cliente Invitado') AS cliente,
                e.nombre AS estado,
                SUM(dc.cantidad * dc.precio_unitario) AS total_compra,
                JSON_AGG(
                    JSON_BUILD_OBJECT(
                        'producto', pr.nombre,
                        'cantidad', dc.cantidad,
                        'precio_unitario', dc.precio_unitario
                    ) ORDER BY pr.nombre
                ) AS productos_detalle
              FROM compra c
              JOIN detalle_compra dc ON c.compra_id = dc.compra_id
              JOIN producto pr ON dc.producto_id = pr.producto_id
              LEFT JOIN usuario_cliente uc ON uc.usuario_cliente_id = c.usuario_cliente_id
              LEFT JOIN persona per ON uc.persona_id = per.persona_id
              LEFT JOIN estado e ON c.estado_id = e.estado_id
              $whereClause
              GROUP BY c.compra_id, c.fecha_compra, cliente, e.nombre
              ORDER BY c.compra_id DESC";

$stmt = $pdo->prepare($sqlVentas);
$stmt->execute($params);
$ventas = $stmt->fetchAll(PDO::FETCH_ASSOC);

$currentPage = basename($_SERVER['PHP_SELF'], ".php");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Existencias - Ventas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/estilos.css?v=<?= time(); ?>">
    <style>
        /* CSS para el sidebar y submenús (mantengo el tuyo) */
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

        /* Estilos para las filas desplegables */
        .detalle-productos-row {
            display: none; /* Oculto por defecto */
            background-color: #f8f9fa; /* Un color de fondo diferente para el detalle */
        }
        .detalle-productos-row.show {
            display: table-row;
        }
        .detalle-productos-row td {
            padding-left: 30px; /* Indentación para el contenido del detalle */
            border-top: none; /* Quita el borde superior para que parezca parte de la misma celda */
        }
        .detalle-productos-table {
            width: 100%;
            margin-top: 10px;
            border: 1px solid #dee2e6;
            border-radius: .25rem;
        }
        .detalle-productos-table th, .detalle-productos-table td {
            padding: .75rem;
            vertical-align: top;
            border-top: 1px solid #dee2e6;
        }
        .detalle-productos-table thead {
            background-color: #e9ecef;
        }
        /* Nuevo estilo para el número de venta clicable */
        .venta-id-toggle {
            cursor: pointer;
            font-weight: bold;
        }

        /* Estilo para la barra de búsqueda para que ocupe todo el ancho */
        .search-bar-container {
            width: 100%;
            margin-bottom: 1.5rem; /* Ajuste para espaciar de la tabla */
        }
        .search-bar-form .form-control,
        .search-bar-form .form-select {
            flex-grow: 1; /* Permite que los inputs se estiren */
            min-width: 0; /* Previene desbordamiento en flexbox */
        }
        .search-bar-form .col-auto {
            flex-grow: 1;
        }
        .search-bar-form .col-auto:last-of-type {
            flex-grow: 0; /* No permitir que los botones se estiren tanto */
        }
        .search-bar-form .btn {
            width: 100%; /* Asegura que los botones ocupen el ancho de su columna */
        }

        /* Ajustes responsivos para que los campos no se apilen tan rápido */
        @media (min-width: 992px) { /* Para pantallas grandes (lg y superiores) */
            .search-bar-form .col-lg {
                flex: 1 1 auto; /* Permite que los elementos se distribuyan uniformemente */
            }
            .search-bar-form .col-lg-auto {
                flex: 0 0 auto; /* Para los botones, que no se estiren */
            }
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
            <h3 class="mb-4">Gestión de Existencias - Ventas</h3>

            <?php
            // **BLOQUE PARA MOSTRAR LA ALERTA DE BOOTSTRAP**
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

            <div class="search-bar-container">
                <form class="row row-cols-lg-auto g-2 align-items-center search-bar-form" method="GET">
                    <div class="col-12 col-lg">
                        <label class="visually-hidden" for="venta_id">Número de Venta</label>
                        <input type="text" name="venta_id" id="venta_id" value="<?= htmlspecialchars($filtroVentaId) ?>" class="form-control" placeholder="Número de Venta">
                    </div>
                    <div class="col-12 col-lg">
                        <label class="visually-hidden" for="cliente_id">Buscar Cliente</label>
                        <select name="cliente_id" id="cliente_id" class="form-select">
                            <option value="">Todos los Clientes</option>
                            <?php foreach ($clientes as $cliente): ?>
                                <option value="<?= $cliente['usuario_cliente_id'] ?>" <?= $filtroClienteId == $cliente['usuario_cliente_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cliente['nombre'] . ' ' . htmlspecialchars($cliente['apellido_paterno'])) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12 col-lg">
                        <label for="fecha_inicio" class="visually-hidden">Fecha Inicio</label>
                        <input type="date" name="fecha_inicio" id="fecha_inicio" value="<?= htmlspecialchars($filtroFechaInicio) ?>" class="form-control" title="Fecha de Inicio">
                    </div>
                    <div class="col-12 col-lg">
                        <label for="fecha_fin" class="visually-hidden">Fecha Fin</label>
                        <input type="date" name="fecha_fin" id="fecha_fin" value="<?= htmlspecialchars($filtroFechaFin) ?>" class="form-control" title="Fecha de Fin">
                    </div>
                    <div class="col-12 col-lg">
                        <label class="visually-hidden" for="estado">Estado</label>
                        <select name="estado" id="estado" class="form-select">
                            <option value="">Todos los Estados</option>
                            <?php foreach ($estados as $estado): ?>
                                <option value="<?= $estado['estado_id'] ?>" <?= $filtroEstadoId == $estado['estado_id'] ? 'selected' : '' ?>><?= htmlspecialchars($estado['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12 col-lg-auto">
                        <button type="submit" class="btn btn-primary fw-bold">Buscar</button>
                    </div>
                    <div class="col-12 col-lg-auto">
                        <a href="gestion_existencias_ventas.php" class="btn btn-secondary">Limpiar</a>
                    </div>
                </form>
            </div>

            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead class="text-center">
                        <tr>
                            <th>#Venta</th>
                            <th>Fecha</th> <th>Hora</th>  <th>Cliente</th>
                            <th>Total</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="text-center">
                        <?php if (count($ventas) > 0): ?>
                            <?php foreach ($ventas as $venta): ?>
                                <tr>
                                    <td class="venta-id-toggle" data-bs-toggle="collapse" data-bs-target="#detalleVenta<?= $venta['compra_id'] ?>" aria-expanded="false" aria-controls="detalleVenta<?= $venta['compra_id'] ?>">
                                        <strong><?= htmlspecialchars($venta['compra_id']) ?></strong>
                                    </td>
                                    <?php
                                    $fecha = 'N/A';
                                    $hora = 'N/A';
                                    if (!empty($venta['fecha_compra'])) {
                                        $dateTime = new DateTime($venta['fecha_compra']);
                                        $fecha = $dateTime->format('Y-m-d');
                                        $hora = $dateTime->format('H:i:s');
                                    }
                                    ?>
                                    <td><?= htmlspecialchars($fecha) ?></td>
                                    <td><?= htmlspecialchars($hora) ?></td>
                                    <td><?= htmlspecialchars($venta['cliente']) ?></td>
                                    <td>S/ <?= number_format($venta['total_compra'], 2) ?></td>
                                    <td>
                                        <?php if (!empty($venta['estado'])): ?>
                                            <?php
                                            $badgeClass = 'secondary'; // Default
                                            switch (strtolower($venta['estado'])) {
                                                case 'entregado':
                                                    $badgeClass = 'success';
                                                    break;
                                                case 'cancelado':
                                                    $badgeClass = 'danger';
                                                    break;
                                                case 'devuelto': 
                                                    $badgeClass = 'info';
                                                    break;
                                                case 'pendiente':
                                                    $badgeClass = 'warning';
                                                    break;
                                                default:
                                                    $badgeClass = 'secondary';
                                                    break;
                                            }
                                            ?>
                                            <span class="badge bg-<?= $badgeClass ?>">
                                                <?= htmlspecialchars($venta['estado']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Desconocido</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (strtolower($venta['estado']) === 'pendiente'): ?>
                                            <a href="gestion_envio.php?compra_id=<?= htmlspecialchars($venta['compra_id']) ?>" class="btn btn-sm btn-primary">Gestionar Envío</a>
                                        <?php else: ?>
                                            <span class="text-muted">No gestionable</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr class="collapse detalle-productos-row" id="detalleVenta<?= $venta['compra_id'] ?>">
                                    <td colspan="7"> <div class="p-3">
                                            <strong>Productos de la Orden #<?= htmlspecialchars($venta['compra_id']) ?>:</strong>
                                            <table class="detalle-productos-table">
                                                <thead>
                                                    <tr>
                                                        <th>Producto</th>
                                                        <th>Cantidad</th>
                                                        <th>Precio Unitario</th>
                                                        <th>Subtotal</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php
                                                    $productos = json_decode($venta['productos_detalle'], true);
                                                    if (!empty($productos)):
                                                        foreach ($productos as $item):
                                                    ?>
                                                            <tr>
                                                                <td><?= htmlspecialchars($item['producto']) ?></td>
                                                                <td><?= htmlspecialchars($item['cantidad']) ?></td>
                                                                <td>S/ <?= number_format($item['precio_unitario'], 2) ?></td>
                                                                <td>S/ <?= number_format($item['cantidad'] * $item['precio_unitario'], 2) ?></td>
                                                            </tr>
                                                    <?php
                                                        endforeach;
                                                    else:
                                                    ?>
                                                        <tr>
                                                            <td colspan="4" class="text-center">No hay productos en esta orden.</td>
                                                        </tr>
                                                    <?php endif; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center">No se encontraron ventas.</td> </tr>
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
