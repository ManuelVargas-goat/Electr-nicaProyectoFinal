<?php
session_start(); // Inicia la sesión para acceder al carrito

// --- Configuración de la Base de Datos PostgreSQL ---
// ¡IMPORTANTE! Estos valores han sido actualizados con tu configuración.
// Asegúrate de que tu servidor PostgreSQL esté en ejecución y accesible en este puerto.
define('DB_HOST', 'localhost');
define('DB_NAME', 'TiendaElectro');
define('DB_USER', 'juan');
define('DB_PASSWORD', '123');
define('DB_PORT', '5432'); // Puerto de PostgreSQL

$conn = null;
try {
    // Establecer conexión a la base de datos usando PDO
    // Se incluye el puerto en la cadena de conexión
    $conn = new PDO("pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME, DB_USER, DB_PASSWORD);
    // Establecer el modo de error de PDO a excepción para una mejor depuración
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Opcional: Establecer el juego de caracteres a UTF-8
    $conn->exec("SET NAMES 'UTF8'");
} catch (PDOException $e) {
    // En un entorno de producción, es mejor registrar el error detallado (error_log)
    // y mostrar un mensaje genérico al usuario por seguridad.
    error_log("Error de conexión a la base de datos: " . $e->getMessage()); // Esto guarda el error en los logs del servidor
    die("Error al conectar con la base de datos. Por favor, inténtalo de nuevo más tarde."); // Mensaje para el usuario
}

// --- Obtener productos de la base de datos (con stock) ---
$products = [];
try {
    $stmt = $conn->query("SELECT p.producto_id as id, p.nombre, p.precio, p.marca, p.descripcion, p.ruta_imagen, p.categoria_id, COALESCE(s.cantidad, 0) as stock_quantity FROM producto p LEFT JOIN stock s ON p.producto_id = s.producto_id WHERE p.descontinuado = FALSE ORDER BY p.nombre ASC");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error al obtener productos: " . $e->getMessage());
    // Si hay un error al obtener productos, la página se mostrará sin ellos.
    $products = [];
}

// --- Obtener categorías de la base de datos ---
$categories = [];
try {
    $stmt = $conn->query("SELECT categoria_id, nombre FROM categoria ORDER BY nombre ASC");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error al obtener categorías: " . $e->getMessage());
    $categories = [];
}

// Calcular el número de ítems en el carrito para mostrar en la barra de navegación
$cart_item_count = 0;
if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        $cart_item_count += $item['quantity'];
    }
}

// Obtener precio mas alto de los productos
$precioAlto = $conn->query("SELECT MAX(precio) as precio FROM producto")->fetch(PDO::FETCH_ASSOC);

// Verificar si el usuario está logeado para personalizar la navegación
$is_logged_in = isset($_SESSION['usuario_cliente_id']) && $_SESSION['usuario_cliente_id'] > 0;
$user_display_name = $is_logged_in ? htmlspecialchars($_SESSION['user_usuario']) : 'identifícate'; // Asume 'user_usuario' se establece en login.php

// Parámetros de filtro
$busqueda = $_GET['buscar'] ?? '';
$categoriaFiltrada = $_GET['categoria'] ?? '';
$filtroPrecioMin = $_GET['precio_min'] ?? '';
$filtroPrecioMax = $_GET['precio_max'] ?? '';

if (isset($_GET['id'])) {
    $categoriaFiltrada = $_GET['id'];
}

// Consulta dinámica
$where = [];
$params = [];

$where[] = "p.descontinuado = FALSE ";

if (!empty($busqueda)) {
    $where[] = "(p.nombre ILIKE :buscar OR CAST(p.producto_id AS TEXT) ILIKE :buscar)";
    $params[':buscar'] = "%$busqueda%";
}

if (!empty($categoriaFiltrada)) {
    $where[] = "p.categoria_id = :categoria";
    $params[':categoria'] = $categoriaFiltrada;
}

if (!empty($filtroPrecioMin)) {
    $where[] = "p.precio >= :precio_min";
    $params[':precio_min'] = $filtroPrecioMin;
}
if (!empty($filtroPrecioMax)) {
    $where[] = "p.precio <= :precio_max";
    $params[':precio_max'] = $filtroPrecioMax;
}

$sqlProductosBuscados = "SELECT p.producto_id as id, p.nombre, p.descripcion, p.marca, p.precio, p.categoria_id,
                         p.ruta_imagen as ruta_imagen, COALESCE(s.cantidad, 0) as stock_quantity
                         FROM producto p LEFT JOIN stock s ON p.producto_id = s.producto_id 
                        ";

if ($where) {
    $sqlProductosBuscados .= " WHERE " . implode(" AND ", $where);
}

$sqlProductosBuscados .= " ORDER BY p.producto_id DESC";

$stmt = $conn->prepare($sqlProductosBuscados);
$stmt->execute($params);
$productos = $stmt->fetchAll();


// Logica para cerrar sesión
if (isset($_GET['logout']) && $_GET['logout'] == 'true') {
    $_SESSION = array(); // Limpiar variables de sesión
    session_destroy();
    header("Location: Inicio_Principal.php");
    exit();
}


?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Tienda de Electrónica - Estilo Amazon</title>
    <!-- Bootstrap CSS CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
        crossorigin="anonymous">
    <!-- Font Awesome para iconos (opcional, pero útil para un look más completo) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css"
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <!-- Custom CSS -->
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #eaeded;
            /* Light gray background, similar to Amazon */
            color: #111;
        }

        .navbar-amazon {
            background-color: #131921;
            /* Amazon dark blue/black */
            padding: 0.5rem 1rem;
            color: white;
        }

        .navbar-amazon .navbar-brand {
            font-weight: bold;
            font-size: 1.5rem;
            color: white !important;
        }

        .navbar-amazon .nav-link,
        .navbar-amazon .form-control {
            color: white !important;
        }

        .navbar-amazon .nav-link:hover {
            color: #ddd !important;
        }

        .navbar-amazon .search-bar {
            flex-grow: 1;
            margin: 0 15px;
            max-width: 600px;
        }

        .navbar-amazon .search-input {
            border-radius: 5px 0 0 5px;
            border: none;
            padding: 0.5rem 1rem;
            height: 40px;
        }

        .navbar-amazon .search-button {
            background-color: #febd69;
            /* Amazon orange */
            border-radius: 0 5px 5px 0;
            border: none;
            color: #111;
            padding: 0.5rem 1rem;
            height: 40px;
            cursor: pointer;
        }

        .navbar-amazon .search-button:hover {
            background-color: #f7a847;
        }

        .navbar-amazon .nav-item .dropdown-toggle::after {
            display: none;
            /* Hide default caret for Amazon style */
        }

        .navbar-amazon .nav-item .dropdown-menu {
            background-color: #131921;
            border: 1px solid #333;
        }

        .navbar-amazon .nav-item .dropdown-item {
            color: white;
        }

        .navbar-amazon .nav-item .dropdown-item:hover {
            background-color: #333;
        }

        /* Secondary Navbar */
        .navbar-secondary {
            background-color: #232f3e;
            /* Amazon dark gray */
            color: white;
            padding: 0.5rem 1rem;
        }

        .navbar-secondary .nav-link {
            color: white !important;
            padding: 0.5rem 1rem;
        }

        .navbar-secondary .nav-link:hover {
            border: 1px solid white;
            /* Highlight on hover */
            border-radius: 3px;
        }

        /* Offcanvas Menu */
        .offcanvas-header {
            background-color: #232f3e;
            color: white;
        }

        .offcanvas-body {
            background-color: #fff;
            color: #111;
        }

        .offcanvas-body .list-group-item {
            border: none;
            padding: 10px 15px;
        }

        .offcanvas-body .list-group-item:hover {
            background-color: #f0f0f0;
        }

        /* Product Cards */
        .card {
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, .1);
            transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
            border: 1px solid #ddd;
        }

        .card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, .15);
        }

        .card-img-top {
            border-top-left-radius: 8px;
            border-top-right-radius: 8px;
            height: 200px;
            object-fit: contain;
            /* Use contain to prevent cropping and show full image */
            padding: 10px;
            /* Add some padding around the image */
        }

        .btn-add-to-cart {
            background-color: #ffd814;
            /* Amazon yellow */
            border-color: #ffd814;
            color: #111;
            border-radius: 20px;
            /* More rounded */
            font-weight: bold;
            transition: background-color 0.2s ease-in-out;
        }

        .btn-add-to-cart:hover {
            background-color: #f7ca00;
            border-color: #f7ca00;
            color: #111;
        }

        .product-price {
            font-size: 1.4rem;
            font-weight: bold;
            color: #b12704;
            /* Amazon red for price */
        }

        .product-brand {
            font-size: 0.85rem;
            color: #555;
        }

        .out-of-stock-badge {
            background-color: #dc3545;
            /* Red for out of stock */
            color: white;
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 0.8rem;
            font-weight: bold;
            margin-top: 5px;
            display: inline-block;
        }


        /* Footer */
        .footer-amazon {
            background-color: #232f3e;
            /* Dark gray for footer */
            color: white;
            padding: 40px 0;
            font-size: 0.9rem;
        }

        .footer-amazon a {
            color: #ddd;
            text-decoration: none;
            display: block;
            margin-bottom: 5px;
        }

        .footer-amazon a:hover {
            text-decoration: underline;
            color: white;
        }

        /* Removed .back-to-top styles as per request */

        /* Custom Toast Notification */
        .custom-toast {
            position: fixed;
            top: 20px;
            right: 20px;
            background-color: #28a745;
            /* Green for success */
            color: white;
            padding: 10px 20px;
            border-radius: 5px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
            z-index: 1050;
            /* Above Bootstrap modals */
            opacity: 0;
            transition: opacity 0.5s ease-in-out;
        }

        .custom-toast.show {
            opacity: 1;
        }
    </style>
</head>

<body>

    <!-- Removed Back to Top Button as per request -->

    <!-- Main Navbar (Amazon Style) -->
    <nav class="navbar navbar-expand-lg navbar-amazon sticky-top">
        <div class="container-fluid">
            <!-- Offcanvas Toggle Button -->
            <button class="btn btn-link text-white me-3" type="button" data-bs-toggle="offcanvas"
                data-bs-target="#offcanvasMenu" aria-controls="offcanvasMenu">
                <i class="fas fa-bars"></i> Todas
            </button>

            <!-- Brand Logo -->
            <a class="navbar-brand me-4" href="inicio_Principal.php">
                <span class="text-warning">Mi</span>Tienda
            </a>

            <!-- Search Bar -->
            <div class="d-flex flex-grow-1">
                <form class="d-flex mx-auto" role="search" action="Inicio_Principal_Busqueda.php" method="GET">
                        <input class="form-control" type="search" placeholder="Buscar..." aria-label="Buscar"style="color: black;" name="q">
                        <button class="btn btn-outline-light ms-2" type="submit">Buscar</button>
                    </form>
            </div>

            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item <?php echo $is_logged_in ? 'dropdown' : ''; ?>">
                        <a class="nav-link <?php echo $is_logged_in ? 'dropdown-toggle' : ''; ?>"
                            href="<?php echo $is_logged_in ? '#' : 'login.php'; ?>" id="navbarDropdownAccount"
                            role="button" <?php echo $is_logged_in ? 'data-bs-toggle="dropdown" aria-expanded="false"' : ''; ?>>
                            Hola, <?php echo $user_display_name; ?> <br> <span class="fw-bold">Cuentas y Listas</span>
                        </a>
                        <?php if ($is_logged_in): ?>
                            <ul class="dropdown-menu" aria-labelledby="navbarDropdownAccount">
                                <li><a class="dropdown-item" href="cuenta.php">Mi Cuenta</a></li>
                                <li><a class="dropdown-item" href="pedidos.php">Mis Pedidos</a></li>
                                <li><a class="dropdown-item" href="deseos.php">Mi Lista de Deseos</a></li>
                                <li>
                                    <hr class="dropdown-divider bg-secondary">
                                </li>
                                <li><a class="dropdown-item" href="<?php echo $_SERVER['PHP_SELF']; ?>?logout=true">Cerrar Sesión</a></li>
                            </ul>
                        <?php endif; ?>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="Reclamo.php">
                            Devoluciones <br> <span class="fw-bold">& Pedidos</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="carritocompras.php" id="cart-link">
                            <i class="fas fa-shopping-cart fa-2x"></i>
                            <span
                                class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-warning text-dark"
                                id="cart-count"><?php echo $cart_item_count; ?></span>
                            <span class="ms-2">Carrito</span>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Secondary Navbar (Categories/Departments) -->
    <nav class="navbar navbar-expand navbar-secondary">
        <div class="container-fluid">
            <ul class="navbar-nav">
                <?php foreach ($categories as $category): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="#"><?php echo htmlspecialchars($category['nombre']); ?></a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </nav>

    <!-- Offcanvas Menu (Left Sidebar) -->
    <div class="offcanvas offcanvas-start" tabindex="-1" id="offcanvasMenu" aria-labelledby="offcanvasMenuLabel">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title" id="offcanvasMenuLabel"><i class="fas fa-user-circle me-2"></i> Hola,
                Identifícate</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"
                aria-label="Close"></button>
        </div>
        <div class="offcanvas-body">
            <h6 class="text-uppercase fw-bold mb-3">Comprar por Categoría</h6>
            <ul class="list-group list-group-flush">
                <?php foreach ($categories as $category): ?>
                    <li class="list-group-item"><a href="Inicio_Principal_Busqueda.php?id=<?php echo $category['categoria_id']; ?>"
                            class="text-decoration-none text-dark"><?php echo htmlspecialchars($category['nombre']); ?></a>
                    </li>
                <?php endforeach; ?>
                <li class="list-group-item"><a href="#" class="text-decoration-none text-dark">Ver todo</a></li>
            </ul>
            <hr>
            <h6 class="text-uppercase fw-bold mb-3">Ayuda y Configuración</h6>
            <ul class="list-group list-group-flush">
                <li class="list-group-item"><a href="cuenta.php" class="text-decoration-none text-dark">Mi Cuenta</a></li>
                <li class="list-group-item"><a href="pedidos.php" class="text-decoration-none text-dark">Mis Pedidos</a></li>
                <li class="list-group-item"><a href="deseos.php" class="text-decoration-none text-dark">Mi Lista de Deseos</a></li>
                <li class="list-group-item"><a href="#">Servicio al Cliente</a></li>
                <li class="list-group-item"><a href="#">Idioma</a></li>
                <li class="list-group-item"><a href="<?php echo $_SERVER['PHP_SELF']; ?>?logout=true">Cerrar Sesión</a></li>
            </ul>
        </div>
    </div>

    <!-- Custom Toast Notification Element -->
    <div id="customToast" class="custom-toast"></div>

    <!-- Main Content -->
    <main class="container my-5">

        <div class="row">

            <div class="col-md-3">
                <h5>Filtros de búsqueda</h5>

                <form method="GET">
                    <div class="mb-3">
                        <input type="text" name="buscar" class="form-control" placeholder="Buscar..."
                            value="<?= htmlspecialchars($busqueda) ?>">
                    </div>
                    <div class="mb-3">
                        <select name="categoria" class="form-select">
                            <option value="">Todas las Categorías</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['categoria_id'] ?>" <?= $categoriaFiltrada == $cat['categoria_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <ul class="list-inline">
                            <li class="list-inline-item">
                                <input name="precio_min" class="form-control" type="number" id="precio-min"
                                    name="precio-min" min="0" max="<?= htmlspecialchars($precioAlto['precio']) ?>"
                                    placeholder="0">
                            </li>
                            <li class="list-inline-item">
                                <p>-</p>
                            </li>
                            <li class="list-inline-item ">
                                <input name="precio_max" class="form-control" type="number" id="precio-max"
                                    name="precio-max" min="0" max="<?= htmlspecialchars($precioAlto['precio']) ?>"
                                    placeholder="<?= htmlspecialchars($precioAlto['precio']) ?>">
                            </li>
                        </ul>
                    </div>
                    <div class="mb-2 d-grid">
                        <button type="submit" class="btn btn-primary">Buscar</button>
                    </div>
                    <div class="mb-2 d-grid">
                        <a href="Inicio_Principal_Busqueda.php" class="btn btn-secondary">Limpiar</a>
                    </div>
                </form>
            </div>

            <!-- Inicio Carga de Productos -->

            <div class="col-md-9">

                <div class="row">
                    <?php foreach ($productos as $producto): ?>

                        <div class="col-md-4 mb-4">
                            <div class="card h-100">
                                <img src="<?php echo htmlspecialchars($producto['ruta_imagen']); ?>" class="card-img-top"
                                    alt="<?php echo htmlspecialchars($producto['nombre']); ?>"
                                    onerror="this.onerror=null;this.src='https://placehold.co/400x300/F0F0F0/333?text=Imagen+No+Disponible';">
                                <div class="card-body d-flex flex-column">
                                    <h5 class="card-title"><?php echo htmlspecialchars($producto['nombre']); ?></h5>
                                    <p class="card-text text-muted small">
                                        <?php echo htmlspecialchars($producto['descripcion']); ?>
                                    </p>
                                    <div class="mt-auto">
                                        <p class="mb-1 product-brand">Marca:
                                            <?php echo htmlspecialchars($producto['marca']); ?></p>
                                        <p class="product-price">S/ <?php echo number_format($producto['precio'], 2); ?></p>
                                        <?php if ($producto['stock_quantity'] == 0): ?>
                                            <span class="out-of-stock-badge">Sin existencias</span>
                                            <button type="button" class="btn btn-add-to-cart w-100 mt-2" disabled>
                                                Agotado
                                            </button>
                                        <?php else: ?>
                                            <!-- Formulario para añadir al carrito, ahora envía a agregar_producto.php -->
                                            <form action="agregar_producto.php" method="POST">
                                                <input type="hidden" name="action" value="add">
                                                <input type="hidden" name="product_id"
                                                    value="<?php echo htmlspecialchars($producto['id']); ?>">
                                                <input type="hidden" name="quantity" value="1"> <!-- Añade 1 por defecto -->
                                                <button type="submit" class="btn btn-add-to-cart w-100">
                                                    Añadir al Carrito
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <?php if (empty($productos)): ?>
                        <p>No hay productos que coincidan con los filtros.</p>
                    <?php endif; ?>
                </div>

            </div>

        </div>
        
    </main>

    <!-- Footer -->
    <footer class="footer-amazon">
        <!-- Removed Back to Top Button HTML as per request -->
        <div class="container py-4">
            <div class="row">
                <div class="col-md-3 mb-3">
                    <h6 class="fw-bold mb-3">Conócenos</h6>
                    <a href="#">Trabajar en Mi Tienda</a>
                    <a href="#">Información corporativa</a>
                    <a href="#">Mi Tienda y el COVID-19</a>
                </div>
                <div class="col-md-3 mb-3">
                    <h6 class="fw-bold mb-3">Gana dinero con nosotros</h6>
                    <a href="#">Vender en Mi Tienda</a>
                    <a href="#">Vender en Mi Tienda Business</a>
                    <a href="#">Programa de afiliados</a>
                </div>
                <div class="col-md-3 mb-3">
                    <h6 class="fw-bold mb-3">Métodos de pago</h6>
                    <a href="#">Tarjetas de crédito y débito</a>
                    <a href="#">Tarjetas regalo</a>
                    <a href="#">Financiación</a>
                </div>
                <div class="col-md-3 mb-3">
                    <h6 class="fw-bold mb-3">Ayuda</h6>
                    <a href="#">Mi Cuenta</a>
                    <a href="#">Envío y Devoluciones</a>
                    <a href="#">Ayuda</a>
                </div>
            </div>
            <hr class="border-secondary my-4">
            <div class="text-center">
                <p>&copy; 2025 Mi Tienda de Electrónica. Todos los derechos reservados.</p>
                <div class="mt-2">
                    <a href="#" class="text-white mx-2 text-decoration-none">Condiciones de Uso</a> |
                    <a href="#" class="text-white mx-2 text-decoration-none">Aviso de privacidad</a> |
                    <a href="#" class="text-white mx-2 text-decoration-none">Cookies</a>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS CDN (Bundle with Popper) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        crossorigin="anonymous"></script>
    <!-- Custom JavaScript -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Get the message from PHP (if any) and display toast
            // The $message variable is not passed from carritocompras.php to this page directly on reload
            // If you want toast messages from adding to cart, you'd need AJAX for the form submission.
            const customToast = document.getElementById('customToast');

            function showToast(message) {
                if (message) {
                    customToast.textContent = message;
                    customToast.classList.add('show');
                    setTimeout(() => {
                        customToast.classList.remove('show');
                    }, 3000); // Hide after 3 seconds
                }
            }

            // Removed Back to Top button functionality as per request
        });
    </script>
</body>

</html>