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
    error_log("Error de conexión a la base de datos en login.php: " . $e->getMessage());
    die("Error al conectar con la base de datos. Por favor, inténtalo de nuevo más tarde.");
}

$register_message = ''; // Mensaje para mostrar al usuario (éxito o error)

// --- Obtener categorías para la navegación (similar a otras páginas) ---
$categories = [];
try {
    $stmt = $conn->query("SELECT categoria_id, nombre FROM categoria ORDER BY nombre ASC");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error al obtener categorías en login.php: " . $e->getMessage());
    $categories = [];
}

// Calcular el número de ítems en el carrito para mostrar en la barra de navegación
$cart_item_count = 0;
if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        $cart_item_count += $item['quantity'];
    }
}

// --- Procesar el formulario de registro de cliente ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombres = trim($_POST['nombres'] ?? '');
    $apellidopat = trim($_POST['apellidopat'] ?? '');
    $apellidomat = trim($_POST['apellidomat'] ?? '');
    $sexo = $_POST['sexo'];
    $fechanac = $_POST['fechanac'];
    $direccion = trim($_POST['direccion'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $email = trim($_POST['email'] ?? '');

    $usuario = trim($_POST['usuario'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $repassword = trim($_POST['repassword'] ?? '');

    if (empty($nombres) || empty($apellidopat) || empty($apellidomat) || empty($email) || empty($telefono) || empty($direccion) || empty($fechanac) || empty($sexo) ||
        empty($usuario) || empty($password) || empty($repassword)) {
        $register_message = "<div class='alert alert-danger text-center' role='alert'>Todos los campos obligatorios deben ser completados.</div>";
    } elseif ($password !== $repassword) {
        $register_message = "<div class='alert alert-danger text-center' role='alert'>Las contraseñas no coinciden.</div>";
    } else {
        try {

            // Iniciar transacción
            $conn->beginTransaction();

            // 1. Insertar en la tabla persona
            $sql_persona = "INSERT INTO persona (nombre, apellido_paterno, apellido_materno, fecha_nacimiento, sexo, email, direccion, telefono) VALUES (:nombre, :apellido_paterno, :apellido_materno, :fecha_nacimiento, :sexo, :email, :direccion, :telefono)";
            $stmt_persona = $conn->prepare($sql_persona);
            $stmt_persona->execute([
                ':nombre' => $nombres,
                ':apellido_paterno' => $apellidopat,
                ':apellido_materno' => $apellidomat,
                ':fecha_nacimiento' => $fechanac,
                ':sexo' => $sexo,
                ':email' => $email,
                ':direccion' => $direccion,
                ':telefono' => $telefono
            ]);
            $persona_id = $conn->lastInsertId(); // Obtener el ID de la persona recién insertada

            $sql_empleado = "INSERT INTO usuario_cliente (usuario, clave, rol, persona_id) VALUES (:usuario, :clave, :rol, :persona_id)";
            $stmt_empleado = $conn->prepare($sql_empleado);
            $stmt_empleado->execute([
                ':usuario' => $usuario,
                ':clave' => $password,
                ':rol' => 'cliente',
                ':persona_id' => $persona_id
            ]);

            $conn->commit(); // Confirmar la transacción
            $success_message = "Usuario agregado correctamente.";
            // Redirigir al usuario a la página original o a inicio_Principal.php
                $redirect_to = isset($_GET['redirect_to']) ? $_GET['redirect_to'] : 'Inicio_Principal.php';
                header('Location: ' . $redirect_to);
                exit();

        } catch (PDOException $e) {
            error_log("Error de login: " . $e->getMessage());
            $login_message = "<div class='alert alert-danger text-center' role='alert'>Error al intentar  registrar al usuario. Por favor, inténtalo de nuevo más tarde.</div>";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro - Mi Tienda de Electrónica</title>
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

        .register-container {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, .1);
            padding: 30px;
            margin-top: 50px;
            max-width: 900px;
            margin-left: auto;
            margin-right: auto;
        }

        .register-container h2 {
            text-align: center;
            margin-bottom: 30px;
            font-weight: bold;
            color: #007bff;
        }

        .register-container .form-control {
            border-radius: 5px;
            padding: 10px;
        }

        .register-container .btn-login {
            background-color: #f0c14b;
            border-color: #a88734 #9c7e31 #846a29;
            color: #111;
            font-weight: bold;
            border-radius: 5px;
            padding: 10px 20px;
            width: 100%;
            margin-top: 20px;
        }

        .register-container .btn-login:hover {
            background-color: #e4b93b;
        }

        .register-container .text-center a {
            color: #007bff;
            text-decoration: none;
        }

        .register-container .text-center a:hover {
            text-decoration: underline;
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
            /* Adjust margin to push footer down */
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
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
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
            <button class="btn btn-link text-white me-3" type="button" data-bs-toggle="offcanvas"
                data-bs-target="#offcanvasMenu" aria-controls="offcanvasMenu">
                <i class="fas fa-bars"></i> Todas
            </button>

            <!-- Brand Logo -->
            <a class="navbar-brand me-4" href="inicio_Principal.php">
                <span class="text-warning">Mi</span>Tienda
            </a>

            <!-- Search Bar -->
            <div class="d-flex flex-grow-1 search-bar">
                <input class="form-control me-0 search-input" type="search" placeholder="Buscar en Mi Tienda"
                    aria-label="Search">
                <button class="btn search-button" type="submit"><i class="fas fa-search"></i></button>
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

    <!-- Main Content for Login -->
    <main class="container">
        <div class="register-container">

            <h2>Ingrese sus datos</h2>

            <form class="row g-3" 
             method="post" autocomplete="of">
                <div class="col-md-6">
                    <label class="mb-2" for="nombre">Nombre <span class="text-danger">*</span></label>
                    <input type="text" name="nombres" id="nombres" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="mb-2" for="apellidopat">Apellido Paterno y Materno <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <input type="text" name="apellidopat" id="apellidopat" class="form-control" required>
                        <input type="text" name="apellidomat" id="apellidomat" class="form-control" required>
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="mb-2" for="sexo">Sexo <span class="text-danger">*</span></label>
                    <select name="sexo" id="sexo" class="form-select" required>
                        <option value="H" selected>Hombre</option>
                        <option value="M">Mujer</option>
                        <option value="S">No especifica</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="mb-2" for="fechanac">Fecha de Nacimiento<span class="text-danger">*</span></label>
                    <input type="date" name="fechanac" id="fechanac" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="mb-2" for="direccion">Direccion</label>
                    <input type="text" name="direccion" id="direccion" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="mb-2" for="telefono">Telefono <span class="text-danger">*</span></label>
                    <input type="text" name="telefono" id="telefono" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="mb-2" for="email">Email <span class="text-danger">*</span></label>
                    <input type="email" name="email" id="email" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="mb-2" for="usuario">Usuario <span class="text-danger">*</span></label>
                    <input type="text" name="usuario" id="usuario" class="form-control" required>
                </div>
                <div class="col-md-6 ">
                    <label class="mb-2" for="password">Contraseña<span class="text-danger">*</span></label>
                    <input type="password" name="password" id="password" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="mb-2" for="repassword">Confirmar Contraseña<span class="text-danger">*</span></label>
                    <input type="password" name="repassword" id="repassword" class="form-control" required>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">Registrarse</button>
                </div>

            </form>

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
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        crossorigin="anonymous"></script>
    <!-- Custom JavaScript -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
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