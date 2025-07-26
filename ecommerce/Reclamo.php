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
    $conn = new PDO("pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME, DB_USER, DB_PASSWORD);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->exec("SET NAMES 'UTF8'");
} catch (PDOException $e) {
    error_log("Error de conexión a la base de datos en cuenta.php: " . $e->getMessage());
    die("Error al conectar con la base de datos. Por favor, inténtalo de nuevo más tarde.");
}

$user_data = null;
$message = '';

// --- 1. Verificar si hay una sesión de cliente iniciada ---
$usuario_cliente_id = isset($_SESSION['usuario_cliente_id']) ? intval($_SESSION['usuario_cliente_id']) : 0;

if ($usuario_cliente_id === 0) {
    // Si no hay sesión de cliente, redirigir a la página de login
    header('Location: login.php?redirect_to=cuenta.php');
    exit();
}

// --- 2. Obtener datos del usuario logeado ---
try {
    $stmt = $conn->prepare("SELECT usuario_cliente_id, usuario, clave, rol, persona_id FROM usuario_cliente WHERE usuario_cliente_id = ?");
    $stmt->execute([$usuario_cliente_id]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmtPersona = $conn->prepare("SELECT p.persona_id as persona_id,p.nombre, p.apellido_paterno, p.apellido_materno, p.fecha_nacimiento, p.sexo, p.email, p.direccion, p.telefono
                  FROM persona p JOIN usuario_cliente uc ON uc.persona_id = p.persona_id
                  WHERE uc.usuario_cliente_id = ?");
    $stmtPersona->execute([$usuario_cliente_id]);
    $user_perfil = $stmtPersona->fetch(PDO::FETCH_ASSOC);


    if (!$user_data) {
        // Si el ID de usuario en sesión no corresponde a un usuario válido
        session_destroy(); // Destruir la sesión inválida
        header('Location: login.php?redirect_to=cuenta.php&msg=' . urlencode('Tu sesión ha expirado o es inválida. Por favor, inicia sesión de nuevo.'));
        exit();
    }
} catch (PDOException $e) {
    error_log("Error al obtener datos del usuario en cuenta.php: " . $e->getMessage());
    $message = "<div class='alert alert-danger text-center' role='alert'>Error al cargar los datos de tu cuenta.</div>";
}

// --- 2. Obtener nombre completo del usuario logeado ---
if ($user_perfil) {
    $nombreUsuario = $user_perfil['nombre'] . ' ' . $user_perfil['apellido_paterno'];
}

// --- 3. Obtener historial de compras del usuario ---
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

// --- 3. Obtener relaciones producto-proveedor-pedido para el formulario --- 
$relaciones = $conn->query("
    SELECT dc.compra_id, dc.producto_id, p.nombre AS producto, dc.cantidad
    FROM detalle_compra dc
    JOIN producto p ON dc.producto_id = p.producto_id
    ORDER BY dc.compra_id DESC
")->fetchAll(PDO::FETCH_ASSOC);

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
$user_display_name = $is_logged_in ? htmlspecialchars($_SESSION['user_usuario']) : 'identifícate'; // Asume 'user_usuario' se establece en login.php

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pedidoId = $_POST['pedido_id'];
    $productoId = $_POST['producto_id'];
    $cantidad = $_POST['cantidad'];
    $motivo = $_POST['motivo'];

    try {
        $conn->beginTransaction();

        // Insertar la devolución
        $sql = "INSERT INTO devolucion (fecha, tipo, usuario_cliente_id, observaciones)
                VALUES (CURRENT_TIMESTAMP, 'cliente', :usuario_id, :motivo)
                RETURNING devolucion_id";
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':usuario_id' => $usuario_cliente_id,
            ':motivo' => $motivo
        ]);
        $devolucionId = $stmt->fetchColumn();

        // Insertar detalle de devolución
        $sqlDetalle = "INSERT INTO detalle_devolucion (devolucion_id, producto_id, cantidad, motivo, reingresado_stock)
                       VALUES (:devolucion_id, :producto_id, :cantidad, :motivo, 'false')";
        $stmt = $conn->prepare($sqlDetalle);
        $stmt->execute([
            ':devolucion_id' => $devolucionId,
            ':producto_id' => $productoId,
            ':cantidad' => $cantidad,
            ':motivo' => $motivo
        ]);

        $conn->commit();
        header("Location: pedidos.php");
        exit();
    } catch (Exception $e) {
        $conn->rollBack();
        die("Error al registrar devolución: " . $e->getMessage());
    }
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
                    <input class="form-control" type="search" placeholder="Buscar..." aria-label="Buscar"
                        style="color: black;" name="q">
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
                                <li><a class="dropdown-item" href="#">Cerrar Sesión</a></li>
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
                <li class="list-group-item"><a href="#" class="text-decoration-none text-dark">Mi Cuenta</a></li>
                <li class="list-group-item"><a href="#" class="text-decoration-none text-dark">Servicio al Cliente</a>
                </li>
                <li class="list-group-item"><a href="#" class="text-decoration-none text-dark">Idioma</a></li>
            </ul>
        </div>
    </div>

    <!-- Custom Toast Notification Element -->
    <div id="customToast" class="custom-toast"></div>

    <!-- Main Content -->
    <main class="container my-5">

        <div class="row justify-content-center">

            <div class="col-lg-8">

                <div class="account-container">

                    <form method="POST" class="bg-white p-4 rounded shadow-sm">

                        <div class="mb-3">
                            <label class="form-label">Usuario</label>
                            <input type="text" class="form-control" value="<?= htmlspecialchars($nombreUsuario) ?>"
                                readonly>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Pedido Entregado</label>
                            <select name="compra_id" id="compra_id" class="form-select" required>
                                <option value="">Seleccione una compra</option>
                                <?php foreach ($orders as $o): ?>
                                    <option value="<?= $o['info']['compra_id'] ?>">#<?= $o['info']['compra_id'] ?> -
                                        <?= $o['info']['fecha_compra'] ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Producto</label>
                            <select name="producto_id" id="producto_id" class="form-select" required disabled>
                                <option value="">Seleccione un pedido primero</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Cantidad</label>
                            <input type="number" name="cantidad" class="form-control" required min="1">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Motivo</label>
                            <textarea name="motivo" class="form-control" required></textarea>
                        </div>

                        <div class="d-flex justify-content-between">
                            <a href="gestion_existencias_devoluciones.php" class="btn btn-secondary">Cancelar</a>
                            <button type="submit" class="btn btn-primary">Registrar Devolución</button>
                        </div>
                    </form>

                </div>

            </div>

        </div>

    </main>

    <script>

            const relaciones = <?= json_encode($relaciones) ?>;

            const compraSelect = document.getElementById('compra_id')
            const productoSelect = document.getElementById('producto_id')
            const cantidadInput = document.querySelector('input[name="cantidad"]')


            console.log(compraSelect)

            let cantidadMax = 0;

            compraSelect.addEventListener('change', () => {
                const compraId = parseInt(compraSelect.value);
                console.log(compraId)
                productoSelect.innerHTML = '<option value="">Seleccione un producto</option>'
                productoSelect.disabled = true
                cantidadInput.value = ''
                cantidadInput.removeAttribute('max')

                if (!compraId) return

                const productos = relaciones.filter(r => r.compra_id == compraId);
                productos.forEach(p => {
                    const opt = document.createElement('option')
                    opt.value = p.producto_id
                    opt.textContent = p.producto
                    opt.dataset.maxCantidad = p.cantidad
                    productoSelect.appendChild(opt)
                });

                productoSelect.disabled = false;
            });


            productoSelect.addEventListener('change', () => {
                const option = productoSelect.options[productoSelect.selectedIndex];
                cantidadMax = parseInt(option.dataset.maxCantidad) || 0;
                cantidadInput.value = '';
                cantidadInput.setAttribute('max', cantidadMax);
                cantidadInput.setAttribute('placeholder', `Máx: ${cantidadMax}`);
            });

            cantidadInput.addEventListener('input', () => {
                const val = parseInt(cantidadInput.value);
                if (val > cantidadMax) {
                    cantidadInput.value = cantidadMax;
                }
            })

        </script>

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