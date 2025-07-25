<?php
session_start(); // Inicia la sesión para acceder al carrito

// --- Configuración de la Base de Datos PostgreSQL ---
define('DB_HOST', 'localhost');
define('DB_NAME', 'TiendaElectro');
define('DB_USER', 'juan');
define('DB_PASSWORD', '123');
define('DB_PORT', '5432'); // Puerto de PostgreSQL

$conn = null;
try {
    $conn = new PDO("pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME, DB_USER, DB_PASSWORD);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->exec("SET NAMES 'UTF8'");
} catch (PDOException $e) {
    error_log("Error de conexión a la base de datos en agregar_producto.php: " . $e->getMessage());
    die("Error al conectar con la base de datos. Por favor, inténtalo de nuevo más tarde.");
}

$product = null;
$stock_quantity = 0;
$message = ''; // Mensaje para notificaciones o alertas

// --- Obtener categorías de la base de datos (AÑADIDO PARA RESOLVER EL ERROR) ---
$categories = [];
try {
    $stmt = $conn->query("SELECT categoria_id, nombre FROM categoria ORDER BY nombre ASC");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error al obtener categorías en agregar_producto.php: " . $e->getMessage());
    $categories = []; // Asegura que $categories sea un array vacío si hay un error
}


// Obtener el ID del producto de la URL (GET) o del formulario (POST)
$product_id = isset($_REQUEST['product_id']) ? intval($_REQUEST['product_id']) : 0;

if ($product_id > 0) {
    try {
        // Obtener detalles del producto
        $stmt_product = $conn->prepare("SELECT producto_id as id, nombre, precio, marca, descripcion, ruta_imagen FROM producto WHERE producto_id = ? AND descontinuado = FALSE");
        $stmt_product->execute([$product_id]);
        $product = $stmt_product->fetch(PDO::FETCH_ASSOC);

        if ($product) {
            // Obtener stock del producto
            $stmt_stock = $conn->prepare("SELECT cantidad FROM stock WHERE producto_id = ?");
            $stmt_stock->execute([$product_id]);
            $stock_data = $stmt_stock->fetch(PDO::FETCH_ASSOC);
            $stock_quantity = $stock_data ? $stock_data['cantidad'] : 0;

            // Definir un umbral para stock bajo
            $low_stock_threshold = 10;
            if ($stock_quantity <= $low_stock_threshold && $stock_quantity > 0) {
                $message = "<div class='alert alert-warning text-center' role='alert'><i class='fas fa-exclamation-triangle me-2'></i> ¡Atención! Quedan pocas unidades en stock ($stock_quantity).</div>";
            } elseif ($stock_quantity == 0) {
                $message = "<div class='alert alert-danger text-center' role='alert'><i class='fas fa-times-circle me-2'></i> ¡Producto agotado!</div>";
            }

        } else {
            $message = "<div class='alert alert-danger text-center' role='alert'>Producto no encontrado o descontinuado.</div>";
        }

    } catch (PDOException $e) {
        error_log("Error al obtener detalles del producto o stock: " . $e->getMessage());
        $message = "<div class='alert alert-danger text-center' role='alert'>Error al cargar los detalles del producto.</div>";
    }
} else {
    $message = "<div class='alert alert-danger text-center' role='alert'>ID de producto no especificado.</div>";
}

// Calcular el número de ítems en el carrito para mostrar en la barra de navegación
$cart_item_count = 0;
if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        $cart_item_count += $item['quantity'];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $product ? htmlspecialchars($product['nombre']) : 'Producto No Encontrado'; ?> - Mi Tienda</title>
    <!-- Bootstrap CSS CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
    <!-- Font Awesome para iconos -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <!-- Custom CSS -->
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #eaeded; /* Light gray background, similar to Amazon */
            color: #111;
        }
        .navbar-amazon {
            background-color: #131921; /* Amazon dark blue/black */
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
            background-color: #febd69; /* Amazon orange */
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
            display: none; /* Hide default caret for Amazon style */
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
            background-color: #232f3e; /* Amazon dark gray */
            color: white;
            padding: 0.5rem 1rem;
        }
        .navbar-secondary .nav-link {
            color: white !important;
            padding: 0.5rem 1rem;
        }
        .navbar-secondary .nav-link:hover {
            border: 1px solid white; /* Highlight on hover */
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

        /* Product Detail Specific Styles */
        .product-detail-card {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,.1);
            padding: 30px;
            margin-top: 30px;
        }
        .product-detail-img {
            max-width: 100%;
            height: auto;
            object-fit: contain;
            border: 1px solid #eee;
            border-radius: 5px;
            padding: 10px;
        }
        .product-title {
            font-size: 2.2rem;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .product-brand {
            font-size: 1.1rem;
            color: #555;
            margin-bottom: 15px;
        }
        .product-price-detail {
            font-size: 2rem;
            font-weight: bold;
            color: #b12704;
            margin-bottom: 20px;
        }
        .product-description {
            font-size: 1rem;
            color: #333;
            line-height: 1.6;
            margin-bottom: 25px;
        }
        .quantity-selector {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }
        .quantity-selector label {
            margin-right: 10px;
            font-weight: bold;
        }
        .quantity-input {
            width: 80px;
            text-align: center;
            border-radius: 5px;
            border: 1px solid #ccc;
            padding: 8px;
        }
        .btn-add-to-cart-detail {
            background-color: #ffd814; /* Amazon yellow */
            border-color: #ffd814;
            color: #111;
            font-weight: bold;
            border-radius: 20px;
            padding: 12px 25px;
            width: 100%;
            transition: background-color 0.2s ease-in-out;
        }
        .btn-add-to-cart-detail:hover {
            background-color: #f7ca00;
            border-color: #f7ca00;
            color: #111;
        }
        .stock-info {
            font-size: 0.9rem;
            color: #666;
            margin-top: 10px;
        }
        .section-title {
            color: #007bff;
            margin-bottom: 40px;
            font-weight: 700;
        }

        /* Footer */
        .footer-amazon {
            background-color: #232f3e; /* Dark gray for footer */
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
        .back-to-top {
            background-color: #37475a;
            color: white;
            text-align: center;
            padding: 15px 0;
            cursor: pointer;
        }
        .back-to-top:hover {
            background-color: #485769;
        }
    </style>
</head>
<body>

    <!-- Back to Top Button (hidden by default, appears on scroll) -->
    <div class="back-to-top" onclick="window.scrollTo({ top: 0, behavior: 'smooth' });">
        Volver arriba
    </div>

    <!-- Main Navbar (Amazon Style) -->
    <nav class="navbar navbar-expand-lg navbar-amazon sticky-top">
        <div class="container-fluid">
            <!-- Offcanvas Toggle Button -->
            <button class="btn btn-link text-white me-3" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasMenu" aria-controls="offcanvasMenu">
                <i class="fas fa-bars"></i> Todas
            </button>

            <!-- Brand Logo -->
            <a class="navbar-brand me-4" href="inicio_Principal.php">
                <span class="text-warning">Mi</span>Tienda
            </a>

            <!-- Search Bar -->
            <div class="d-flex flex-grow-1 search-bar">
                <input class="form-control me-0 search-input" type="search" placeholder="Buscar en Mi Tienda" aria-label="Search">
                <button class="btn search-button" type="submit"><i class="fas fa-search"></i></button>
            </div>

            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdownAccount" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            Hola, identifícate <br> <span class="fw-bold">Cuentas y Listas</span>
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="navbarDropdownAccount">
                            <li><a class="dropdown-item" href="#">Mi Cuenta</a></li>
                            <li><a class="dropdown-item" href="#">Mis Pedidos</a></li>
                            <li><a class="dropdown-item" href="#">Mi Lista de Deseos</a></li>
                            <li><hr class="dropdown-divider bg-secondary"></li>
                            <li><a class="dropdown-item" href="#">Cerrar Sesión</a></li>
                        </ul>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#">
                            Devoluciones <br> <span class="fw-bold">& Pedidos</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="carritocompras.php" id="cart-link">
                            <i class="fas fa-shopping-cart fa-2x"></i>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-warning text-dark" id="cart-count"><?php echo $cart_item_count; ?></span>
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
            <h5 class="offcanvas-title" id="offcanvasMenuLabel"><i class="fas fa-user-circle me-2"></i> Hola, Identifícate</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body">
            <h6 class="text-uppercase fw-bold mb-3">Comprar por Categoría</h6>
            <ul class="list-group list-group-flush">
                <?php foreach ($categories as $category): ?>
                    <li class="list-group-item"><a href="#" class="text-decoration-none text-dark"><?php echo htmlspecialchars($category['nombre']); ?></a></li>
                <?php endforeach; ?>
                <li class="list-group-item"><a href="#" class="text-decoration-none text-dark">Ver todo</a></li>
            </ul>
            <hr>
            <h6 class="text-uppercase fw-bold mb-3">Ayuda y Configuración</h6>
            <ul class="list-group list-group-flush">
                <li class="list-group-item"><a href="#" class="text-decoration-none text-dark">Mi Cuenta</a></li>
                <li class="list-group-item"><a href="#" class="text-decoration-none text-dark">Servicio al Cliente</a></li>
                <li class="list-group-item"><a href="#" class="text-decoration-none text-dark">Idioma</a></li>
            </ul>
        </div>
    </div>

    <!-- Main Content for Product Detail -->
    <main class="container my-5">
        <?php echo $message; // Muestra mensajes de error o stock bajo ?>

        <?php if ($product): ?>
            <div class="row product-detail-card">
                <div class="col-md-5">
                    <img src="<?php echo htmlspecialchars($product['ruta_imagen']); ?>" class="product-detail-img" alt="<?php echo htmlspecialchars($product['nombre']); ?>" onerror="this.onerror=null;this.src='https://placehold.co/600x400/F0F0F0/333?text=Imagen+No+Disponible';">
                </div>
                <div class="col-md-7">
                    <h1 class="product-title"><?php echo htmlspecialchars($product['nombre']); ?></h1>
                    <p class="product-brand">Marca: <?php echo htmlspecialchars($product['marca']); ?></p>
                    <p class="product-price-detail">S/ <?php echo number_format($product['precio'], 2); ?></p>
                    <p class="product-description"><?php echo htmlspecialchars($product['descripcion']); ?></p>

                    <div class="stock-info">
                        Stock disponible: <?php echo $stock_quantity; ?> unidades
                    </div>

                    <form action="carritocompras.php" method="POST" class="mt-4">
                        <input type="hidden" name="action" value="add">
                        <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($product['id']); ?>">

                        <div class="quantity-selector">
                            <label for="quantity">Cantidad:</label>
                            <input type="number" id="quantity" name="quantity" value="1" min="1" max="<?php echo $stock_quantity > 0 ? $stock_quantity : 1; ?>" class="form-control quantity-input">
                        </div>

                        <button type="submit" class="btn btn-add-to-cart-detail" <?php echo ($stock_quantity == 0) ? 'disabled' : ''; ?>>
                            <?php echo ($stock_quantity == 0) ? 'Agotado' : 'Añadir al Carrito'; ?>
                        </button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <!-- Footer -->
    <footer class="footer-amazon">
        <div class="back-to-top" onclick="window.scrollTo({ top: 0, behavior: 'smooth' });">
            Volver arriba
        </div>
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
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
    <!-- Custom JavaScript -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Back to Top button functionality
            const backToTopButton = document.querySelector('.back-to-top');
            window.addEventListener('scroll', () => {
                if (window.scrollY > 200) {
                    backToTopButton.style.display = 'block';
                } else {
                    backToTopButton.style.display = 'none';
                }
            });
        });
    </script>
</body>
</html>
