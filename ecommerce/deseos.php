<?php
session_start(); // Inicia la sesión

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
    error_log("Error de conexión a la base de datos en deseos.php: " . $e->getMessage());
    die("Error al conectar con la base de datos. Por favor, inténtalo de nuevo más tarde.");
}

$wishlist_products = [];
$message = '';

// --- 1. Verificar si hay una sesión de cliente iniciada ---
$usuario_cliente_id = isset($_SESSION['usuario_cliente_id']) ? intval($_SESSION['usuario_cliente_id']) : 0;

if ($usuario_cliente_id === 0) {
    // Si no hay sesión de cliente, redirigir a la página de login
    header('Location: login.php?redirect_to=deseos.php');
    exit();
}

// --- 2. Obtener productos de la lista de deseos del usuario ---
try {
    $stmt = $conn->prepare("
        SELECT
            ld.lista_deseos_id,
            p.producto_id as id,
            p.nombre,
            p.precio,
            p.marca,
            p.descripcion,
            p.ruta_imagen,
            COALESCE(s.cantidad, 0) as stock_quantity
        FROM
            lista_deseos ld
        JOIN
            producto p ON ld.producto_id = p.producto_id
        LEFT JOIN
            stock s ON p.producto_id = s.producto_id
        WHERE
            ld.usuario_cliente_id = ?
        ORDER BY
            ld.fecha_agregado DESC
    ");
    $stmt->execute([$usuario_cliente_id]);
    $wishlist_products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($wishlist_products)) {
        $message = "<div class='alert alert-info text-center' role='alert'>Tu lista de deseos está vacía.</div>";
    }

} catch (PDOException $e) {
    error_log("Error al obtener la lista de deseos: " . $e->getMessage());
    $message = "<div class='alert alert-danger text-center' role='alert'>Error al cargar tu lista de deseos. Por favor, inténtalo de nuevo más tarde.</div>";
}

// --- Obtener categorías de la base de datos para la navegación ---
$categories = [];
try {
    $stmt = $conn->query("SELECT categoria_id, nombre FROM categoria ORDER BY nombre ASC");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error al obtener categorías en deseos.php: " . $e->getMessage());
    $categories = [];
}

// Calcular el número de ítems en el carrito para mostrar en la barra de navegación
$cart_item_count = 0;
if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        $cart_item_count += $item['quantity'];
    }
}

// Verificar si el usuario está logeado para personalizar la navegación
$is_logged_in = isset($_SESSION['usuario_cliente_id']) && $_SESSION['usuario_cliente_id'] > 0;
$user_display_name = $is_logged_in ? htmlspecialchars($_SESSION['user_usuario']) : 'identifícate';

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
    <title>Mi Lista de Deseos - Mi Tienda de Electrónica</title>
    <!-- Bootstrap CSS CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
    <!-- Font Awesome para iconos -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <!-- Custom CSS -->
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #eaeded;
            color: #111;
        }
        .navbar-amazon {
            background-color: #131921;
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
        .navbar-secondary {
            background-color: #232f3e;
            color: white;
            padding: 0.5rem 1rem;
        }
        .navbar-secondary .nav-link {
            color: white !important;
            padding: 0.5rem 1rem;
        }
        .navbar-secondary .nav-link:hover {
            border: 1px solid white;
            border-radius: 3px;
        }
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
        .wishlist-container {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,.1);
            padding: 30px;
            margin-top: 30px;
        }
        .wishlist-item-card {
            display: flex;
            align-items: center;
            border: 1px solid #ddd;
            border-radius: 8px;
            margin-bottom: 15px;
            padding: 15px;
        }
        .wishlist-item-card img {
            width: 100px;
            height: 100px;
            object-fit: contain;
            margin-right: 20px;
            border: 1px solid #eee;
            border-radius: 5px;
        }
        .wishlist-item-details {
            flex-grow: 1;
        }
        .wishlist-item-details h5 {
            font-weight: bold;
            margin-bottom: 5px;
        }
        .wishlist-item-details .price {
            color: #b12704;
            font-weight: bold;
        }
        .wishlist-item-actions {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .btn-remove-wishlist, .btn-add-to-cart-wishlist {
            border-radius: 20px;
            padding: 8px 15px;
            font-size: 0.9rem;
            font-weight: bold;
        }
        .btn-remove-wishlist {
            background-color: #dc3545;
            border-color: #dc3545;
            color: white;
        }
        .btn-remove-wishlist:hover {
            background-color: #c82333;
            border-color: #c82333;
        }
        .btn-add-to-cart-wishlist {
            background-color: #ffd814;
            border-color: #ffd814;
            color: #111;
        }
        .btn-add-to-cart-wishlist:hover {
            background-color: #f7ca00;
            border-color: #f7ca00;
        }
        .out-of-stock-badge {
            background-color: #dc3545;
            color: white;
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 0.8rem;
            font-weight: bold;
            margin-top: 5px;
            display: inline-block;
        }
        .section-title {
            color: #007bff;
            margin-bottom: 40px;
            font-weight: 700;
        }
        .footer-amazon {
            background-color: #232f3e;
            color: white;
            padding: 40px 0;
            font-size: 0.9rem;
            margin-top: 50px;
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
        .custom-toast {
            position: fixed;
            top: 20px;
            right: 20px;
            background-color: #28a745;
            color: white;
            padding: 10px 20px;
            border-radius: 5px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
            z-index: 1050;
            opacity: 0;
            transition: opacity 0.5s ease-in-out;
        }
        .custom-toast.show {
            opacity: 1;
        }

        .account-container {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, .1);
            padding: 30px;
            margin-top: 30px;
        }
    </style>
</head>
<body>

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
                           href="<?php echo $is_logged_in ? '#' : 'login.php'; ?>"
                           id="navbarDropdownAccount"
                           role="button"
                           <?php echo $is_logged_in ? 'data-bs-toggle="dropdown" aria-expanded="false"' : ''; ?>>
                            Hola, <?php echo $user_display_name; ?> <br> <span class="fw-bold">Cuentas y Listas</span>
                        </a>
                        <?php if ($is_logged_in): ?>
                            <ul class="dropdown-menu" aria-labelledby="navbarDropdownAccount">
                                <li><a class="dropdown-item" href="cuenta.php">Mi Cuenta</a></li>
                                <li><a class="dropdown-item" href="pedidos.php">Mis Pedidos</a></li>
                                <li><a class="dropdown-item" href="deseos.php">Mi Lista de Deseos</a></li>
                                <li><hr class="dropdown-divider bg-secondary"></li>
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

    <!-- Main Content for Wishlist Page -->
    <main class="container my-5">
        <h2 class="text-center section-title">Mi Lista de Deseos</h2>

        <div class="row justify-content-center">
            <div class="col-lg-10">

                <div class="account-container">
                    <?php echo $message; // Muestra mensajes de error o éxito ?>

                    <?php if ($usuario_cliente_id): ?>
                        <div class="text-center">
                            <a href="cuenta.php" class="btn btn-primary">Editar Perfil</a>
                            <a href="pedidos.php" class="btn btn-primary ms-2">Ver Mis Pedidos</a>
                            <a href="deseos.php" class="btn btn-secondary ms-2">Ver Lista de Deseados</a>
                            <a href="<?php echo $_SERVER['PHP_SELF']; ?>?logout=true" class="btn btn-danger ms-2">Cerrar
                                sesion</a>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info text-center" role="alert">
                            No se pudo cargar la información de tu cuenta.
                        </div>
                    <?php endif; ?>
                </div>

                <div class="wishlist-container">
                    <?php echo $message; // Muestra mensajes de error o si la lista está vacía ?>

                    <?php if (!empty($wishlist_products)): ?>
                        <?php foreach ($wishlist_products as $product): ?>
                            <div class="wishlist-item-card">
                                <img src="<?php echo htmlspecialchars($product['ruta_imagen']); ?>" alt="<?php echo htmlspecialchars($product['nombre']); ?>" onerror="this.onerror=null;this.src='https://placehold.co/100x100/F0F0F0/333?text=No+Img';">
                                <div class="wishlist-item-details">
                                    <h5><?php echo htmlspecialchars($product['nombre']); ?></h5>
                                    <p class="text-muted small"><?php echo htmlspecialchars($product['descripcion']); ?></p>
                                    <p class="price">S/ <?php echo number_format($product['precio'], 2); ?></p>
                                    <?php if ($product['stock_quantity'] == 0): ?>
                                        <span class="out-of-stock-badge">Sin existencias</span>
                                    <?php else: ?>
                                        <p class="text-success small">En stock (<?php echo htmlspecialchars($product['stock_quantity']); ?> unidades)</p>
                                    <?php endif; ?>
                                </div>
                                <div class="wishlist-item-actions">
                                    <button type="button" class="btn btn-remove-wishlist" data-product-id="<?php echo htmlspecialchars($product['id']); ?>">
                                        Eliminar
                                    </button>
                                    <?php if ($product['stock_quantity'] > 0): ?>
                                        <form action="agregar_producto.php" method="POST" class="d-inline">
                                            <input type="hidden" name="action" value="add">
                                            <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($product['id']); ?>">
                                            <input type="hidden" name="quantity" value="1">
                                            <button type="submit" class="btn btn-add-to-cart-wishlist">
                                                Añadir al Carrito
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="footer-amazon">
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
            const customToast = document.getElementById('customToast');

            // Function to show toast notifications
            function showToast(message, type = 'success') {
                customToast.textContent = message;
                customToast.className = 'custom-toast show'; // Reset classes
                if (type === 'success') {
                    customToast.style.backgroundColor = '#28a745';
                } else if (type === 'info') {
                    customToast.style.backgroundColor = '#17a2b8';
                } else if (type === 'danger') {
                    customToast.style.backgroundColor = '#dc3545';
                }
                setTimeout(() => {
                    customToast.classList.remove('show');
                }, 3000); // Hide after 3 seconds
            }

            // Add event listeners for "Eliminar" buttons
            document.querySelectorAll('.btn-remove-wishlist').forEach(button => {
                button.addEventListener('click', function() {
                    const productId = this.dataset.productId;
                    const itemCard = this.closest('.wishlist-item-card'); // Get the parent card element

                    fetch('agregar_deseo.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `product_id=${productId}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.action === 'removed') {
                            showToast('Producto eliminado de tu lista de deseos.', 'info');
                            // Remove the item card from the DOM
                            if (itemCard) {
                                itemCard.remove();
                            }
                            // Check if wishlist is now empty and display message
                            if (document.querySelectorAll('.wishlist-item-card').length === 0) {
                                const wishlistContainer = document.querySelector('.wishlist-container');
                                if (wishlistContainer) {
                                    wishlistContainer.innerHTML = "<div class='alert alert-info text-center' role='alert'>Tu lista de deseos está vacía.</div>";
                                }
                            }
                        } else {
                            showToast(data.message || 'Error al eliminar de la lista de deseos.', 'danger');
                            if (data.redirect_to_login) {
                                setTimeout(() => { window.location.href = 'login.php?redirect_to=' + encodeURIComponent(window.location.href); }, 1500);
                            }
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        showToast('Error de red al procesar la solicitud.', 'danger');
                    });
                });
            });
        });
    </script>
</body>
</html>
