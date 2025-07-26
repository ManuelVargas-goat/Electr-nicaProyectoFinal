<?php
session_start(); // Inicia la sesión para almacenar el carrito

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
    error_log("Error de conexión a la base de datos en carritocompras.php: " . $e->getMessage());
    die("Error al conectar con la base de datos. Por favor, inténtalo de nuevo más tarde.");
}

// Inicializar el carrito en la sesión si no existe
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$message = ''; // Mensaje para notificaciones (usado por el toast)

// Manejar acciones del carrito (añadir, actualizar, eliminar)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
        $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 0;

        if ($product_id > 0) {
            try {
                // Obtener detalles del producto y stock desde la base de datos
                $stmt_product = $conn->prepare("SELECT p.nombre, p.precio, p.ruta_imagen, COALESCE(s.cantidad, 0) as stock_quantity FROM producto p LEFT JOIN stock s ON p.producto_id = s.producto_id WHERE p.producto_id = ? AND p.descontinuado = FALSE");
                $stmt_product->execute([$product_id]);
                $db_product = $stmt_product->fetch(PDO::FETCH_ASSOC);

                if ($db_product) {
                    $product_name = $db_product['nombre'];
                    $product_price = $db_product['precio'];
                    $product_image = $db_product['ruta_imagen'];
                    $available_stock = $db_product['stock_quantity'];

                    switch ($action) {
                        case 'add':
                            if ($quantity <= 0) {
                                $message = "La cantidad a añadir debe ser mayor que cero.";
                            } elseif ($available_stock == 0) {
                                $message = "¡Lo sentimos! '$product_name' está agotado.";
                            } elseif ($quantity > $available_stock) {
                                $message = "No hay suficiente stock de '$product_name'. Disponible: $available_stock.";
                            } else {
                                if (isset($_SESSION['cart'][$product_id])) {
                                    // Verificar que la nueva cantidad no exceda el stock
                                    $new_total_quantity = $_SESSION['cart'][$product_id]['quantity'] + $quantity;
                                    if ($new_total_quantity > $available_stock) {
                                        $message = "No puedes añadir más de '$product_name'. Solo quedan $available_stock unidades en total.";
                                    } else {
                                        $_SESSION['cart'][$product_id]['quantity'] = $new_total_quantity;
                                        $message = "$product_name añadido al carrito.";
                                    }
                                } else {
                                    $_SESSION['cart'][$product_id] = [
                                        'quantity' => $quantity,
                                        'price' => $product_price,
                                        'name' => $product_name,
                                        'image' => $product_image
                                    ];
                                    $message = "$product_name añadido al carrito.";
                                }
                            }
                            break;
                        case 'update':
                            if ($quantity <= 0) {
                                unset($_SESSION['cart'][$product_id]);
                                $message = "$product_name eliminado del carrito.";
                            } elseif ($quantity > $available_stock) {
                                $message = "No puedes establecer la cantidad de '$product_name' a $quantity. Disponible: $available_stock.";
                            } else {
                                $_SESSION['cart'][$product_id]['quantity'] = $quantity;
                                $message = "Cantidad de $product_name actualizada.";
                            }
                            break;
                        case 'remove':
                            unset($_SESSION['cart'][$product_id]);
                            $message = "$product_name eliminado del carrito.";
                            break;
                    }
                } else {
                    $message = "Error: Producto no válido o no encontrado en la base de datos.";
                }
            } catch (PDOException $e) {
                error_log("Error al procesar acción del carrito: " . $e->getMessage());
                $message = "Error al procesar la solicitud del carrito.";
            }
        } else {
            $message = "Error: ID de producto no válido.";
        }
    }
    // Redirigir para evitar reenvío de formulario al recargar
    // Pasar el mensaje a través de la URL para el toast
    header('Location: carritocompras.php?msg=' . urlencode($message));
    exit();
}

// Obtener el mensaje de la URL si existe (después de una redirección)
if (isset($_GET['msg'])) {
    $message = htmlspecialchars($_GET['msg']);
}


// Calcular el total del carrito y obtener categorías para la navegación
$subtotal = 0;
$cart_item_count = 0;
foreach ($_SESSION['cart'] as $id => $item) {
    $subtotal += $item['quantity'] * $item['price'];
    $cart_item_count += $item['quantity'];
}
$shipping_cost = $subtotal > 0 ? 15.00 : 0; // Simulación de costo de envío
$total = $subtotal + $shipping_cost;

// --- Obtener categorías de la base de datos para la navegación ---
$categories = [];
try {
    $stmt = $conn->query("SELECT categoria_id, nombre FROM categoria ORDER BY nombre ASC");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error al obtener categorías en carritocompras.php: " . $e->getMessage());
    $categories = []; // Asegura que $categories sea un array vacío si hay un error
}

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
    <title>Carrito de Compras - Mi Tienda de Electrónica</title>
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

        /* Cart Specific Styles */
        .cart-item {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,.1);
            margin-bottom: 20px;
            padding: 20px;
            display: flex;
            align-items: center;
        }
        .cart-item img {
            width: 100px;
            height: 100px;
            object-fit: contain;
            margin-right: 20px;
            border: 1px solid #eee;
            border-radius: 5px;
        }
        .cart-item-details {
            flex-grow: 1;
        }
        .cart-item-details h5 {
            font-weight: bold;
            margin-bottom: 5px;
        }
        .cart-item-details .price {
            color: #b12704;
            font-weight: bold;
        }
        .cart-item-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .cart-summary {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,.1);
            padding: 20px;
        }
        .cart-summary h4 {
            font-weight: bold;
            margin-bottom: 20px;
        }
        .cart-summary .list-group-item {
            background-color: transparent;
            border: none;
            padding: 8px 0;
            display: flex;
            justify-content: space-between;
        }
        .cart-summary .list-group-item.total {
            font-size: 1.25rem;
            font-weight: bold;
            border-top: 1px solid #eee;
            padding-top: 15px;
            margin-top: 10px;
        }
        .btn-proceed-checkout {
            background-color: #f7a847; /* Amazon orange */
            border-color: #f7a847;
            color: #111;
            font-weight: bold;
            border-radius: 20px;
            padding: 10px 20px;
            width: 100%;
            margin-top: 20px;
        }
        .btn-proceed-checkout:hover {
            background-color: #e69138;
            border-color: #e69138;
            color: #111;
        }
        .quantity-input {
            width: 70px;
            text-align: center;
            border-radius: 5px;
            border: 1px solid #ccc;
            padding: 5px;
        }
        .btn-update-quantity, .btn-remove-item {
            border-radius: 5px;
            padding: 5px 10px;
            font-size: 0.9rem;
        }
        .btn-remove-item {
            background-color: #dc3545;
            color: white;
            border: none;
        }
        .btn-remove-item:hover {
            background-color: #c82333;
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

        /* Custom Toast Notification */
        .custom-toast {
            position: fixed;
            top: 20px;
            right: 20px;
            background-color: #28a745; /* Green for success */
            color: white;
            padding: 10px 20px;
            border-radius: 5px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
            z-index: 1050; /* Above Bootstrap modals */
            opacity: 0;
            transition: opacity 0.5s ease-in-out;
        }
        .custom-toast.show {
            opacity: 1;
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

            <!-- Brand Logo - MODIFICADO -->
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
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdownAccount" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            Hola, identifícate <br> <span class="fw-bold">Cuentas y Listas</span>
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="navbarDropdownAccount">
                                <li><a class="dropdown-item" href="cuenta.php">Mi Cuenta</a></li>
                                <li><a class="dropdown-item" href="pedidos.php">Mis Pedidos</a></li>
                                <li><a class="dropdown-item" href="deseos.php">Mi Lista de Deseos</a></li>
                                <li><hr class="dropdown-divider bg-secondary"></li>
                                <li><a class="dropdown-item" href="<?php echo $_SERVER['PHP_SELF']; ?>?logout=true">Cerrar Sesión</a></li>
                            </ul>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="Reclamo.php">
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
                        <a class="nav-link" href="Inicio_Principal_Busqueda.php?id=<?php echo $category['categoria_id']; ?>"><?php echo htmlspecialchars($category['nombre']); ?></a>
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
                    <li class="list-group-item"><a href="Inicio_Principal_Busqueda.php?id=<?php echo $category['categoria_id']; ?>" class="text-decoration-none text-dark"><?php echo htmlspecialchars($category['nombre']); ?></a></li>
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

    <!-- Main Content for Shopping Cart -->
    <main class="container my-5">
        <h2 class="text-center section-title">Tu Carrito de Compras</h2>

        <div class="row">
            <div class="col-lg-8">
                <?php if (empty($_SESSION['cart'])): ?>
                    <div class="alert alert-info text-center" role="alert">
                        Tu carrito está vacío. ¡Empieza a comprar!
                    </div>
                <?php else: ?>
                    <?php foreach ($_SESSION['cart'] as $product_id => $item): ?>
                        <div class="cart-item">
                            <img src="<?php echo htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" onerror="this.onerror=null;this.src='https://placehold.co/100x100/F0F0F0/333?text=No+Img';">
                            <div class="cart-item-details">
                                <h5><?php echo htmlspecialchars($item['name']); ?></h5>
                                <p class="price">S/ <?php echo number_format($item['price'], 2); ?></p>
                            </div>
                            <div class="cart-item-actions">
                                <form action="carritocompras.php" method="POST" class="d-flex align-items-center">
                                    <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($product_id); ?>">
                                    <input type="hidden" name="action" value="update">
                                    <input type="number" name="quantity" value="<?php echo htmlspecialchars($item['quantity']); ?>" min="1" class="form-control quantity-input me-2">
                                    <button type="submit" class="btn btn-primary btn-update-quantity me-2">Actualizar</button>
                                </form>
                                <form action="carritocompras.php" method="POST">
                                    <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($product_id); ?>">
                                    <input type="hidden" name="action" value="remove">
                                    <button type="submit" class="btn btn-danger btn-remove-item">Eliminar</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="col-lg-4">
                <div class="cart-summary">
                    <h4>Resumen del Pedido</h4>
                    <ul class="list-group">
                        <li class="list-group-item">
                            Subtotal (<?php echo $cart_item_count; ?> productos):
                            <span>S/ <?php echo number_format($subtotal, 2); ?></span>
                        </li>
                        <li class="list-group-item">
                            Envío:
                            <span>S/ <?php echo number_format($shipping_cost, 2); ?></span>
                        </li>
                        <li class="list-group-item total">
                            Total:
                            <span>S/ <?php echo number_format($total, 2); ?></span>
                        </li>
                    </ul>
                    <!-- Botón para proceder al pago, ahora redirige a finalizar_compra.php -->
                    <button class="btn btn-proceed-checkout" onclick="window.location.href='finalizar_compra.php'">
                        Proceder al Pago
                    </button>
                </div>
            </div>
        </div>
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
            // Get the message from PHP (if any) and display toast
            const urlParams = new URLSearchParams(window.location.search);
            const phpMessage = urlParams.get('msg'); // Obtener el mensaje de la URL
            const customToast = document.getElementById('customToast');

            function showToast(message) {
                if (message) {
                    customToast.textContent = message;
                    customToast.classList.add('show');
                    setTimeout(() => {
                        customToast.classList.remove('show');
                        // Limpiar el parámetro 'msg' de la URL después de mostrar el toast
                        const newUrl = new URL(window.location.href);
                        newUrl.searchParams.delete('msg');
                        window.history.replaceState({}, document.title, newUrl.toString());
                    }, 3000); // Hide after 3 seconds
                }
            }

            // Display toast if there's a message from PHP
            showToast(phpMessage);

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
