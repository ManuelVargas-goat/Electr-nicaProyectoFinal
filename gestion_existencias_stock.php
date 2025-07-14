<?php
session_start();
include("config.php"); // Asegúrate de que 'config.php' contenga la conexión PDO.

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

$nombreCompleto = htmlspecialchars($datosUsuario['nombre'] . ' ' . $datosUsuario['apellido_paterno']);
$rolUsuario = strtolower(htmlspecialchars($datosUsuario['rol']));

// Filtro por nombre o categoría
$filtroNombre = $_GET['nombre'] ?? '';
$filtroCategoria = $_GET['categoria'] ?? '';

$where = [];
$params = [];

if (!empty($filtroNombre)) {
    $where[] = "p.nombre ILIKE :nombre";
    $params[':nombre'] = "%$filtroNombre%";
}
if (!empty($filtroCategoria)) {
    $where[] = "c.nombre ILIKE :categoria";
    $params[':categoria'] = "%$filtroCategoria%";
}

$whereClause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

$sql = "
SELECT p.producto_id,
       p.nombre,
       p.marca,
       c.nombre AS categoria,
       (
         -- ENTRADAS
         COALESCE((
           SELECT SUM(dp.cantidad)
           FROM detalle_pedido dp
           JOIN pedido ped ON ped.pedido_id = dp.pedido_id
           WHERE dp.producto_id = p.producto_id AND ped.estado = 'entregado'
         ), 0)
         +
         COALESCE((
           SELECT SUM(dd.cantidad)
           FROM detalle_devolucion dd
           JOIN devolucion dv ON dv.devolucion_id = dd.devolucion_id
           WHERE dd.producto_id = p.producto_id AND dv.tipo = 'cliente' AND dd.reingresado_stock IS TRUE
         ), 0)
         -- SALIDAS
         -
         COALESCE((
           SELECT SUM(dc.cantidad)
           FROM detalle_compra dc
           JOIN compra co ON dc.compra_id = co.compra_id -- Alias 'co' para evitar conflicto con 'c' de categoria
           JOIN estado es ON co.estado_id = es.estado_id
           WHERE dc.producto_id = p.producto_id AND es.nombre = 'confirmado'
         ), 0)
         -
         COALESCE((
           SELECT SUM(dd2.cantidad)
           FROM detalle_devolucion dd2
           JOIN devolucion dv2 ON dv2.devolucion_id = dd2.devolucion_id
           WHERE dd2.producto_id = p.producto_id AND dv2.tipo = 'proveedor'
         ), 0)
       ) AS stock_disponible
FROM producto p
LEFT JOIN categoria c ON p.categoria_id = c.categoria_id
$whereClause
ORDER BY p.producto_id DESC
";


$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$productos = $stmt->fetchAll();

// Variable para el nombre del archivo actual (sin la extensión .php)
$currentPage = basename($_SERVER['PHP_SELF'], ".php");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Existencias - Stock</title>
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
            <h3 class="mb-4">Gestión de Existencias - Stock</h3>

            <form class="row g-2 mb-4" method="GET">
                <div class="col-md-4">
                    <input type="text" name="nombre" value="<?= htmlspecialchars($filtroNombre) ?>" class="form-control" placeholder="Buscar por nombre">
                </div>
                <div class="col-md-4">
                    <input type="text" name="categoria" value="<?= htmlspecialchars($filtroCategoria) ?>" class="form-control" placeholder="Buscar por categoría">
                </div>
                <div class="col-md-4">
                    <button class="btn btn-primary w-100">Buscar</button>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-bordered table-hover text-center">
                    <thead class=""> <tr>
                                <th>ID</th>
                                <th>Nombre</th>
                                <th>Marca</th>
                                <th>Categoría</th>
                                <th>Stock Disponible</th>
                                <th>Acción</th>
                            </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($productos)): ?>
                            <?php foreach ($productos as $producto): ?>
                                <tr>
                                    <td><?= htmlspecialchars($producto['producto_id']) ?></td>
                                    <td><?= htmlspecialchars($producto['nombre']) ?></td>
                                    <td><?= htmlspecialchars($producto['marca']) ?></td>
                                    <td><?= htmlspecialchars($producto['categoria'] ?? 'Sin categoría') ?></td>
                                    <td class="<?= ($producto['stock_disponible'] <= 0) ? 'bg-danger text-white fw-bold' : '' ?>">
                                        <?= ($producto['stock_disponible'] <= 0) ? 'Sin stock' : htmlspecialchars($producto['stock_disponible']) ?>
                                    </td>
                                    <td>
                                        <a href="detalle_stock.php?producto_id=<?= htmlspecialchars($producto['producto_id']) ?>" class="btn btn-sm btn-info">Ver detalles</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center">No se encontraron productos en el stock con los filtros aplicados.</td>
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