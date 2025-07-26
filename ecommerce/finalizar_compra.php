<?php
session_start(); // Inicia la sesión

// --- Configuración de la Base de Datos PostgreSQL ---
define('DB_HOST', 'localhost');
define('DB_NAME', 'TiendaElectro');
define('DB_USER', 'juan');
define('DB_PASSWORD', '123');
define('DB_PORT', '5555'); // Puerto de PostgreSQL (se asume que es 5555 o el que estés usando)

$conn = null;
try {
    $conn = new PDO("pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME, DB_USER, DB_PASSWORD);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->exec("SET NAMES 'UTF8'");
} catch (PDOException $e) {
    error_log("Error de conexión a la base de datos en finalizar_compra.php: " . $e->getMessage());
    die("Error al conectar con la base de datos. Por favor, inténtalo de nuevo más tarde.");
}

$message = ''; // Mensaje para notificaciones
$is_payment_successful = false;

// --- Inicializar el carrito en la sesión si no existe (AÑADIDO PARA RESOLVER EL ERROR) ---
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// --- 1. Verificar si hay una sesión de cliente iniciada ---
// Suponemos que 'usuario_cliente_id' se guarda en la sesión al iniciar sesión.
// Si no tienes un sistema de login aún, puedes simularlo o crear uno básico.
$usuario_cliente_id = isset($_SESSION['usuario_cliente_id']) ? intval($_SESSION['usuario_cliente_id']) : 0;

if ($usuario_cliente_id === 0) {
    // Si no hay sesión de cliente, redirigir a la página de login
    // NOTA: Debes crear o tener una página 'login.php' que establezca $_SESSION['usuario_cliente_id'] al iniciar sesión.
    header('Location: login.php?redirect_to=finalizar_compra.php');
    exit();
}

// --- 2. Verificar si el carrito está vacío ---
if (empty($_SESSION['cart'])) {
    header('Location: carritocompras.php?msg=' . urlencode('Tu carrito está vacío. No se puede proceder al pago.'));
    exit();
}

// --- 3. Procesar el pago y registrar la venta ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_payment'])) {
    $conn->beginTransaction(); // Iniciar una transacción para asegurar la integridad de los datos

    try {
        $almacen_id = 1; // Asumimos un ID de almacén por defecto. Podrías permitir al usuario seleccionarlo.
        $estado_id_pendiente = 1; // ID para el estado 'pendiente' en la tabla 'estado'

        // 3.1. Insertar la compra en la tabla 'compra'
        $stmt_insert_compra = $conn->prepare("INSERT INTO compra (usuario_cliente_id, fecha_compra, estado_id, almacen_id) VALUES (?, CURRENT_TIMESTAMP, ?, ?)");
        $stmt_insert_compra->execute([$usuario_cliente_id, $estado_id_pendiente, $almacen_id]);
        $compra_id = $conn->lastInsertId(); // Obtener el ID de la compra recién insertada

        // 3.2. Recorrer los productos en el carrito para insertar detalles y actualizar stock
        foreach ($_SESSION['cart'] as $product_id => $item) {
            $quantity_in_cart = $item['quantity'];

            // Obtener el stock actual del producto
            $stmt_get_stock = $conn->prepare("SELECT cantidad FROM stock WHERE producto_id = ? FOR UPDATE"); // Bloquear la fila para evitar condiciones de carrera
            $stmt_get_stock->execute([$product_id]);
            $current_stock_data = $stmt_get_stock->fetch(PDO::FETCH_ASSOC);
            $current_stock = $current_stock_data ? $current_stock_data['cantidad'] : 0;

            if ($current_stock < $quantity_in_cart) {
                throw new Exception("Stock insuficiente para el producto: " . htmlspecialchars($item['name']) . ". Disponible: $current_stock.");
            }

            // Insertar detalle de compra
            $stmt_insert_detalle = $conn->prepare("INSERT INTO detalle_compra (compra_id, producto_id, cantidad, precio_unitario, descuento) VALUES (?, ?, ?, ?, ?)");
            // Asumimos proveedor_id puede ser NULL o se determinará más tarde.
            // Para este ejemplo, no estamos rastreando el proveedor en detalle_compra desde el carrito.
            $stmt_insert_detalle->execute([$compra_id, $product_id, $quantity_in_cart, $item['price'], 0]); // Descuento 0 por defecto

            // Actualizar stock
            $stmt_update_stock = $conn->prepare("UPDATE stock SET cantidad = cantidad - ? WHERE producto_id = ?");
            $stmt_update_stock->execute([$quantity_in_cart, $product_id]);
        }

        $conn->commit(); // Confirmar la transacción
        $is_payment_successful = true;
        $message = "¡Pago realizado con éxito! Tu pedido ha sido registrado.";
        unset($_SESSION['cart']); // Vaciar el carrito después de la compra exitosa

    } catch (Exception $e) {
        $conn->rollBack(); // Revertir la transacción en caso de error
        $message = "Error al procesar el pago: " . $e->getMessage();
        error_log("Error en finalizar_compra: " . $e->getMessage());
    }
}

// Calcular el total del carrito para mostrar en la página
$subtotal = 0;
$cart_item_count = 0;
// Aquí ya no debería haber problemas con $_SESSION['cart']
foreach ($_SESSION['cart'] as $id => $item) {
    $subtotal += $item['quantity'] * $item['price'];
    $cart_item_count += $item['quantity'];
}
$shipping_cost = $subtotal > 0 ? 15.00 : 0;
$total = $subtotal + $shipping_cost;

// --- Obtener categorías de la base de datos para la navegación ---
$categories = [];
try {
    $stmt = $conn->query("SELECT categoria_id, nombre FROM categoria ORDER BY nombre ASC");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error al obtener categorías en finalizar_compra.php: " . $e->getMessage());
    $categories = [];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finalizar Compra - Mi Tienda de Electrónica</title>
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
        .checkout-container {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,.1);
            padding: 30px;
            margin-top: 30px;
        }
        .checkout-summary-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }
        .checkout-summary-item:last-child {
            border-bottom: none;
            font-weight: bold;
            font-size: 1.1rem;
        }
        .btn-confirm-payment {
            background-color: #f0c14b; /* Amazon yellow for payment */
            border-color: #a88734 #9c7e31 #846a29;
            color: #111;
            font-weight: bold;
            border-radius: 5px;
            padding: 10px 20px;
            width: 100%;
            margin-top: 20px;
        }
        .btn-confirm-payment:hover {
            background-color: #e4b93b;
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
    </style>
</head>
<body>

    <!-- Back to Top Button -->
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

    <!-- Custom Toast Notification Element -->
    <div id="customToast" class="custom-toast"></div>

    <!-- Main Content for Finalize Purchase -->
    <main class="container my-5">
        <h2 class="text-center section-title">Finalizar Compra</h2>

        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="checkout-container">
                    <?php if ($message): ?>
                        <div class="alert <?php echo $is_payment_successful ? 'alert-success' : 'alert-danger'; ?> text-center" role="alert">
                            <?php echo $message; ?>
                        </div>
                        <?php if ($is_payment_successful): ?>
                            <div class="text-center mt-4">
                                <a href="inicio_Principal.php" class="btn btn-primary">Volver a la Tienda</a>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php if (!$is_payment_successful && !empty($_SESSION['cart'])): ?>
                        <h4>Resumen de tu Pedido</h4>
                        <ul class="list-group mb-4">
                            <?php foreach ($_SESSION['cart'] as $product_id => $item): ?>
                                <li class="list-group-item checkout-summary-item">
                                    <span><?php echo htmlspecialchars($item['name']); ?> (x<?php echo htmlspecialchars($item['quantity']); ?>)</span>
                                    <span>S/ <?php echo number_format($item['price'] * $item['quantity'], 2); ?></span>
                                </li>
                            <?php endforeach; ?>
                            <li class="list-group-item checkout-summary-item">
                                <span>Subtotal:</span>
                                <span>S/ <?php echo number_format($subtotal, 2); ?></span>
                            </li>
                            <li class="list-group-item checkout-summary-item">
                                <span>Costo de Envío:</span>
                                <span>S/ <?php echo number_format($shipping_cost, 2); ?></span>
                            </li>
                            <li class="list-group-item checkout-summary-item total">
                                <span>Total a Pagar:</span>
                                <span>S/ <?php echo number_format($total, 2); ?></span>
                            </li>
                        </ul>

                        <form action="finalizar_compra.php" method="POST">
                            <input type="hidden" name="confirm_payment" value="1">
                            <!-- Aquí podrías añadir campos para la información de pago real (tarjeta de crédito, etc.) -->
                            <!-- Por simplicidad, este botón simula la confirmación del pago. -->
                            <button type="submit" class="btn btn-confirm-payment">
                                Confirmar Pago
                            </button>
                        </form>
                    <?php elseif (empty($_SESSION['cart']) && !$is_payment_successful): ?>
                        <div class="alert alert-info text-center" role="alert">
                            No hay productos en tu carrito para finalizar la compra.
                            <a href="inicio_Principal.php" class="alert-link">Volver a la tienda</a>.
                        </div>
                    <?php endif; ?>
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
