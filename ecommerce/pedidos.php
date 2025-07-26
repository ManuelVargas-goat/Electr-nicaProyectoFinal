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
    error_log("Error de conexión a la base de datos en pedidos.php: " . $e->getMessage());
    die("Error al conectar con la base de datos. Por favor, inténtalo de nuevo más tarde.");
}

$orders = [];
$message = '';

// --- 1. Verificar si hay una sesión de cliente iniciada ---
$usuario_cliente_id = isset($_SESSION['usuario_cliente_id']) ? intval($_SESSION['usuario_cliente_id']) : 0;

if ($usuario_cliente_id === 0) {
    // Si no hay sesión de cliente, redirigir a la página de login
    header('Location: login.php?redirect_to=pedidos.php');
    exit();
}

// --- 2. Obtener historial de compras del usuario ---
try {
    // Obtener todas las compras del usuario
    $stmt_orders = $conn->prepare("
        SELECT
            c.compra_id,
            c.fecha_compra,
            e.nombre as estado_compra,
            a.nombre as nombre_almacen
        FROM
            compra c
        JOIN
            estado e ON c.estado_id = e.estado_id
        JOIN
            almacen a ON c.almacen_id = a.almacen_id
        WHERE
            c.usuario_cliente_id = ?
        ORDER BY
            c.fecha_compra DESC
    ");
    $stmt_orders->execute([$usuario_cliente_id]);
    $raw_orders = $stmt_orders->fetchAll(PDO::FETCH_ASSOC);

    foreach ($raw_orders as $order) {
        $order_id = $order['compra_id'];
        $order_total = 0;
        $order_details = [];

        // Obtener los detalles de cada compra
        $stmt_details = $conn->prepare("
            SELECT
                dc.compra_id,
                dc.cantidad,
                dc.precio_unitario,
                dc.descuento,
                p.nombre as producto_nombre,
                p.ruta_imagen
            FROM
                detalle_compra dc
            JOIN
                producto p ON dc.producto_id = p.producto_id
            WHERE
                dc.compra_id = ?
        ");
        $stmt_details->execute([$order_id]);
        $raw_details = $stmt_details->fetchAll(PDO::FETCH_ASSOC);

        foreach ($raw_details as $detail) {
            $item_subtotal = $detail['cantidad'] * $detail['precio_unitario'] - $detail['descuento'];
            $order_total += $item_subtotal;
            $order_details[] = $detail;
        }

        $orders[] = [
            'info' => $order,
            'details' => $order_details,
            'total' => $order_total
        ];
    }

    if (empty($orders)) {
        $message = "<div class='alert alert-info text-center' role='alert'>No has realizado ninguna compra aún.</div>";
    }

} catch (PDOException $e) {
    error_log("Error al obtener historial de pedidos en pedidos.php: " . $e->getMessage());
    $message = "<div class='alert alert-danger text-center' role='alert'>Error al cargar tu historial de pedidos. Por favor, inténtalo de nuevo más tarde.</div>";
}

// --- Obtener categorías de la base de datos para la navegación ---
$categories = [];
try {
    $stmt = $conn->query("SELECT categoria_id, nombre FROM categoria ORDER BY nombre ASC");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error al obtener categorías en pedidos.php: " . $e->getMessage());
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
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Pedidos - Mi Tienda de Electrónica</title>
    <!-- Bootstrap CSS CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
        crossorigin="anonymous">
    <!-- Font Awesome para iconos -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css"
        crossorigin="anonymous" referrerpolicy="no-referrer" />
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

        .orders-container {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, .1);
            padding: 30px;
            margin-top: 30px;
        }

        .order-card {
            border: 1px solid #ddd;
            border-radius: 8px;
            margin-bottom: 20px;
            overflow: hidden;
        }

        .order-header {
            background-color: #f0f0f0;
            padding: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #ddd;
        }

        .order-header h5 {
            margin-bottom: 0;
            font-weight: bold;
            color: #333;
        }

        .order-header span {
            font-size: 0.9rem;
            color: #555;
        }

        .order-details-item {
            display: flex;
            align-items: center;
            padding: 15px;
            border-bottom: 1px solid #eee;
        }

        .order-details-item:last-child {
            border-bottom: none;
        }

        .order-details-item img {
            width: 80px;
            height: 80px;
            object-fit: contain;
            margin-right: 15px;
            border: 1px solid #eee;
            border-radius: 5px;
        }

        .order-item-info {
            flex-grow: 1;
        }

        .order-item-info h6 {
            margin-bottom: 5px;
            font-weight: bold;
        }

        .order-item-info p {
            margin-bottom: 0;
            font-size: 0.9rem;
            color: #666;
        }

        .order-total-footer {
            background-color: #f0f0f0;
            padding: 15px;
            text-align: right;
            font-weight: bold;
            font-size: 1.1rem;
            border-top: 1px solid #ddd;
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
    </style>
</head>

<body>

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
                                <li><a class="dropdown-item" href="#">Mi Lista de Deseos</a></li>
                                <li>
                                    <hr class="dropdown-divider bg-secondary">
                                </li>
                                <li><a class="dropdown-item" href="logout.php">Cerrar Sesión</a></li>
                            </ul>
                        <?php endif; ?>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#">
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
                    <li class="list-group-item"><a href="#"
                            class="text-decoration-none text-dark"><?php echo htmlspecialchars($category['nombre']); ?></a>
                    </li>
                <?php endforeach; ?>
                <li class="list-group-item"><a href="#" class="text-decoration-none text-dark">Ver todo</a></li>
            </ul>
            <hr>
            <h6 class="text-uppercase fw-bold mb-3">Ayuda y Configuración</h6>
            <ul class="list-group list-group-flush">
                <li class="list-group-item"><a href="cuenta.php" class="text-decoration-none text-dark">Mi Cuenta</a>
                </li>
                <li class="list-group-item"><a href="pedidos.php" class="text-decoration-none text-dark">Mis Pedidos</a>
                </li>
                <li class="list-group-item"><a href="#" class="text-decoration-none text-dark">Servicio al Cliente</a>
                </li>
                <li class="list-group-item"><a href="#" class="text-decoration-none text-dark">Idioma</a></li>
                <li class="list-group-item"><a href="logout.php" class="text-decoration-none text-dark">Cerrar
                        Sesión</a></li>
            </ul>
        </div>
    </div>





    <!-- Main Content for Orders Page -->
    <main class="container my-5">


        <h2 class="text-center section-title">Mis Pedidos</h2>

        <div class="row justify-content-center">
            <div class="col-lg-10">


                <div class="account-container">
                    <?php echo $message; // Muestra mensajes de error o éxito ?>

                    <?php if (!empty($orders)): ?>
                        <div class="text-center">
                            <a href="cuenta.php" class="btn btn-primary ms-2">Editar Perfil</a>
                            <a href="pedidos.php" class="btn btn-secondary ms-2">Ver Mis Pedidos</a>
                            <a href="<?php echo $_SERVER['PHP_SELF']; ?>?logout=true" class="btn btn-danger ms-2">Cerrar
                                sesion</a>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info text-center" role="alert">
                            No se pudo cargar la información de tu cuenta.
                        </div>
                    <?php endif; ?>
                </div>

                <div class="orders-container">
                    <?php echo $message; // Muestra mensajes de error o si no hay pedidos ?>

                    <?php if (!empty($orders)): ?>
                        <?php foreach ($orders as $order): ?>
                            <div class="order-card">
                                <div class="order-header">
                                    <div>
                                        <h5>Pedido #<?php echo htmlspecialchars($order['info']['compra_id']); ?></h5>
                                        <span>Fecha:
                                            <?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($order['info']['fecha_compra']))); ?></span><br>
                                        <span>Estado:
                                            <?php echo htmlspecialchars($order['info']['estado_compra']); ?></span><br>
                                        <span>Almacén: <?php echo htmlspecialchars($order['info']['nombre_almacen']); ?></span>
                                    </div>
                                    <div class="justify-content-center">
                                        Total del Pedido: <span class="text-success">S/
                                            <?php echo number_format($order['total'], 2); ?></span>
                                    </div>
                                    <div class="text-end">
                                        <span><a href="Reclamo.php?compra_id=<?php echo htmlspecialchars($order['info']['compra_id']); ?>"
                                                class="btn btn-danger">Realizar un Reclamo</a></span>
                                    </div>
                                </div>
                                <div class="order-body">
                                    <?php foreach ($order['details'] as $detail): ?>
                                        <div class="order-details-item">
                                            <img src="<?php echo htmlspecialchars($detail['ruta_imagen']); ?>"
                                                alt="<?php echo htmlspecialchars($detail['producto_nombre']); ?>"
                                                onerror="this.onerror=null;this.src='https://placehold.co/80x80/F0F0F0/333?text=No+Img';">
                                            <div class="order-item-info">
                                                <h6><?php echo htmlspecialchars($detail['producto_nombre']); ?></h6>
                                                <p>Cantidad: <?php echo htmlspecialchars($detail['cantidad']); ?></p>
                                                <p>Precio Unitario: S/ <?php echo number_format($detail['precio_unitario'], 2); ?>
                                                </p>
                                                <?php if ($detail['descuento'] > 0): ?>
                                                    <p class="text-danger">Descuento: S/
                                                        <?php echo number_format($detail['descuento'], 2); ?>
                                                    </p>
                                                <?php endif; ?>
                                                <p>Subtotal: S/
                                                    <?php echo number_format($detail['cantidad'] * $detail['precio_unitario'] - $detail['descuento'], 2); ?>
                                                </p>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="order-total-footer">
                                    Total del Pedido: S/ <?php echo number_format($order['total'], 2); ?>
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
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        crossorigin="anonymous"></script>
</body>

</html>