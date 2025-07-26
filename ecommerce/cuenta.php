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

// --- Obtener categorías de la base de datos para la navegación ---
$categories = [];
try {
    $stmt = $conn->query("SELECT categoria_id, nombre FROM categoria ORDER BY nombre ASC");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error al obtener categorías en cuenta.php: " . $e->getMessage());
    $categories = [];
}

// Calcular el número de ítems en el carrito para mostrar en la barra de navegación
$cart_item_count = 0;
if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        $cart_item_count += $item['quantity'];
    }
}


// Lógica para actualizar Direccion y Telefono
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_datos'])) {
    $direccion = trim($_POST['direccion'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $ID_usuario = $user_perfil['persona_id'];

    // Validaciones básicas (puedes añadir más si es necesario)
    if (empty($direccion) || empty($telefono)) {
        $message = "<div class='alert alert-danger text-center' role='alert'>Por favor, complete los campos obligatorios (Direccion y Telefono).</div>";
    } else {
        try {
            $sqlUpdate = "UPDATE persona SET
                          direccion = :direccion,
                          telefono = :telefono
                          WHERE persona_id = :persona_id";

            $stmtUpdate = $conn->prepare($sqlUpdate);
            $stmtUpdate->bindParam(':direccion', $direccion);
            $stmtUpdate->bindParam(':telefono', $telefono);
            $stmtUpdate->bindParam(':persona_id', $ID_usuario, PDO::PARAM_INT);

            if ($stmtUpdate->execute()) {
                header("Location: cuenta.php");
                $message = "<div class='alert alert-success text-center' role='alert'>¡Perfil actualizado con éxito!</div>";
            } else {
                $message = "<div class='alert alert-danger text-center' role='alert'>Error al actualizar el perfil. Inténtelo de nuevo.</div>";
            }
        } catch (PDOException $e) {
            error_log("Error al obtener datos del usuario en cuenta.php: " . $e->getMessage());
            $message = "<div class='alert alert-danger text-center' role='alert'>Error al cargar los datos de tu cuenta.</div>";
        }
    }
    // Lógica para cambiar el email
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_email'])) {

    // Buscamos la clave
    $stmt = $conn->prepare("SELECT usuario as usuario,  clave as clave
                  FROM usuario_cliente 
                  WHERE usuario_cliente_id = ?");
    $stmt->execute([$usuario_cliente_id]);
    $user_perfil = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user_perfil) {
        $password = $user_perfil['clave'];
    } else {
        $message = "<div class='alert alert-danger text-center' role='alert'>❌ Error al comunicarse con la base.</div>";
    }


    $new_email = $_POST['new_email'] ?? '';
    $confirm_password = $_POST['password'] ?? '';
    $ID_usuario = $user_perfil['persona_id'];

    if (empty($new_email) || empty($confirm_password)) {
        $message = "<div class='alert alert-danger text-center' role='alert'>Por favor, llene todos los campos correspondientes.</div>";
    } elseif ($password != $confirm_password) {
        $message = "<div class='alert alert-danger text-center' role='alert'>Su antigua contraseña es incorrecta.</div>";
    } else {
        try {


            $sqlUpdateEmail = "UPDATE persona 
                               SET email = :email 
                               WHERE persona_id = :persona_id";
            $stmtUpdateEmail = $conn->prepare($sqlUpdateEmail);
            $stmtUpdateEmail->bindParam(':email', $new_email);
            $stmtUpdateEmail->bindParam(':persona_id', $ID_usuario, PDO::PARAM_INT);

            if ($stmtUpdateEmail->execute()) {
                header("Location: cuenta.php");
                $message = "<div class='alert alert-success text-center' role='alert'>Email cambiado con éxito!</div>";
            } else {
                $message = "<div class='alert alert-danger text-center' role='alert'>Error al cambiar el email. Inténtelo de nuevo.</div>";
            }
        } catch (PDOException $e) {
            $message = "<div class='alert alert-danger text-center' role='alert'>Error de base de datos al cambiar el email</div>";
        }
    }
    // Lógica para cambiar la contraseña
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {


    // Buscamos la clave
    $stmt = $conn->prepare("SELECT usuario as usuario,  clave as clave
                  FROM usuario_cliente 
                  WHERE usuario_cliente_id = ?");
    $stmt->execute([$usuario_cliente_id]);
    $user_perfil = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user_perfil) {
        $password = $user_perfil['clave'];
    } else {
        $message = "<div class='alert alert-danger text-center' role='alert'>❌ Error al comunicarse con la base.</div>";
    }

    $old_password = $_POST['old_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($old_password) || empty($new_password) || empty($confirm_password)) {
        $message = "<div class='alert alert-danger text-center' role='alert'>Por favor, ingrese y confirme la nueva contraseña.</div>";
    } elseif ($password != $old_password) {
        $message = "<div class='alert alert-danger text-center' role='alert'>Su antigua contraseña es incorrecta.</div>";
    } elseif ($new_password !== $confirm_password) {
        $message = "<div class='alert alert-danger text-center' role='alert'>Las contraseñas no coinciden.</div>";
    } elseif (strlen($new_password) < 3) {
        $message = "<div class='alert alert-danger text-center' role='alert'>La contraseña debe tener al menos 3 caracteres.</div>";
    } else {
        try {
            // En una aplicación real, usar password_hash() para almacenar contraseñas de forma segura
            $hashed_password = $new_password;

            $sqlUpdatePassword = "UPDATE usuario_cliente SET clave = :password WHERE persona_id = :persona_id";
            $stmtUpdatePassword = $conn->prepare($sqlUpdatePassword);
            $stmtUpdatePassword->bindParam(':password', $hashed_password);
            $stmtUpdatePassword->bindParam(':persona_id', $ID_usuario, PDO::PARAM_INT);

            if ($stmtUpdatePassword->execute()) {
                header("Location: cuenta.php");
                $message = "<div class='alert alert-success text-center' role='alert'>¡Contraseña cambiada con éxito!</div>";
            } else {
                $message = "<div class='alert alert-danger text-center' role='alert'>Error al cambiar la contraseña. Inténtelo de nuevo.</div>";
            }
        } catch (PDOException $e) {
            error_log("Error de base de datos al cambiar la contraseña: " . $e->getMessage());
            $message = "<div class='alert alert-danger text-center' role='alert'>Error de base de datos al cambiar la contraseña.</div>";
        }
    }
}

// Logica para cerrar sesión
if (isset($_GET['logout']) && $_GET['logout'] == 'true') {
    $_SESSION = array(); // Limpiar variables de sesión
    session_destroy();
    header("Location: Inicio_Principal.php");
    exit();
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
    <title>Mi Cuenta - Mi Tienda de Electrónica</title>
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

        .account-container {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, .1);
            padding: 30px;
            margin-top: 30px;
        }

        .account-details-group {
            margin-bottom: 20px;
        }

        .account-details-group label {
            font-weight: bold;
            color: #555;
            margin-bottom: 5px;
        }

        .account-details-group p {
            background-color: #f8f8f8;
            border: 1px solid #eee;
            border-radius: 5px;
            padding: 10px 15px;
            margin-bottom: 0;
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
            <div class="d-flex flex-grow-1 search-bar">
                <input class="form-control me-0 search-input" type="search" placeholder="Buscar en Mi Tienda"
                    aria-label="Search">
                <button class="btn search-button" type="submit"><i class="fas fa-search"></i></button>
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
                                <li><a class="dropdown-item" href="#">Mis Pedidos</a></li>
                                <li><a class="dropdown-item" href="#">Mi Lista de Deseos</a></li>
                                <li>
                                    <hr class="dropdown-divider bg-secondary">
                                </li>
                                <li><a class="dropdown-item" href="logout.php">Cerrar Sesión</a></li>
                                <!-- Enlace para cerrar sesión -->
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
                <li class="list-group-item"><a href="#" class="text-decoration-none text-dark">Servicio al Cliente</a>
                </li>
                <li class="list-group-item"><a href="#" class="text-decoration-none text-dark">Idioma</a></li>
                <li class="list-group-item"><a href="logout.php" class="text-decoration-none text-dark">Cerrar
                        Sesión</a></li>
            </ul>
        </div>
    </div>

    <!-- Main Content for Account Page -->
    <main class="container my-5">
        <h2 class="text-center section-title">Mi Cuenta</h2>

        <div class="row justify-content-center">

            <div class="col-lg-8">

                <div class="account-container">
                    <?php echo $message; // Muestra mensajes de error o éxito ?>

                    <?php if ($user_data): ?>
                        <div class="text-center">
                            <a href="cuenta.php" class="btn btn-secondary">Editar Perfil</a>
                            <a href="pedidos.php" class="btn btn-primary ms-2">Ver Mis Pedidos</a>
                            <a href="<?php echo $_SERVER['PHP_SELF']; ?>?logout=true" class="btn btn-danger ms-2">Cerrar
                                sesion</a>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info text-center" role="alert">
                            No se pudo cargar la información de tu cuenta.
                        </div>
                    <?php endif; ?>
                </div>

                <div class="account-container">
                    <?php echo $message; // Muestra mensajes de error o éxito ?>

                    <?php if ($user_perfil): ?>
                        <div class="card-header fw-bold">

                            <ul class="list-inline d-flex">
                                <li class="list-inline-item">
                                    <h4 class="card-title fw-bold">Datos Personales</h4>
                                </li>
                                <li class="list-inline-item ms-auto">
                                    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal"
                                        data-bs-target="#editDatosModal">
                                        <i class="fa fa-edit"></i> Editar
                                    </button>
                                </li>
                            </ul>



                        </div>
                        <ul class="list-inline">
                            <li class="list-inline-item col-md-6">
                                <div>
                                    <div class="mb-3">
                                        <h6 class="card-title fw-bold">Nombre</h6>
                                        <span><?= htmlspecialchars($user_perfil['nombre'] ?? '') . ' ' .
                                            htmlspecialchars($user_perfil['apellido_paterno'] ?? '') . ' ' .
                                            htmlspecialchars($user_perfil['apellido_materno'] ?? '') ?></span>
                                    </div>
                                    <div>
                                        <h6 class="card-title fw-bold">Direccion</h6>
                                        <span><?= htmlspecialchars($user_perfil['direccion'] ?? '') ?></span>
                                    </div>
                                </div>
                            </li>
                            <li class="list-inline-item col-md-5">
                                <div>
                                    <div class="mb-3">
                                        <h6 class="card-title fw-bold">Fecha de Nacimiento</h6>
                                        <span><?= htmlspecialchars($user_perfil['fecha_nacimiento'] ?? '') ?></span>
                                    </div>
                                    <div>
                                        <h6 class="card-title fw-bold">Telefono</h6>
                                        <span><?= htmlspecialchars($user_perfil['telefono'] ?? '') ?></span>
                                    </div>
                                </div>
                            </li>
                        </ul>
                    <?php else: ?>
                        <div class="alert alert-info text-center" role="alert">
                            No se pudo cargar la información de tu cuenta.
                        </div>
                    <?php endif; ?>
                </div>

                <div class="account-container">
                    <?php echo $message; // Muestra mensajes de error o éxito ?>

                    <?php if ($user_perfil): ?>

                        <div class="account-details-group mb-3">
                            <h4 class="card-title fw-bold">Email</h4>
                        </div>

                        <div class="card-body">

                            <div class="account-details-group mb-3">
                                <label>Correo Actual:</label>
                                <span><?= htmlspecialchars($user_perfil['email'] ?? '') ?></span>
                            </div>

                            <h6 class="card-title fw-bold mb-3">Cambiar correo inscrito</h6>
                            <form method="POST">

                                <div class="account-details-group mb-3">

                                    <input type="hidden" name="change_email" value="1">
                                    <label for="newEmail" class="form-label">Nuevo Correo</label><br>
                                    <input type="email" class="form-control" id="new_email" name="new_email" required><br>

                                </div>

                                <div class="account-details-group mb-3">

                                    <label for="confirmEmail" class="form-label">Contraseña</label><br>
                                    <input type="password" class="form-control" id="password" name="password" required><br>

                                </div>

                                <button type="submit" class="btn btn-primary">Cambiar Correo</button>
                            </form>
                        </div>

                    <?php else: ?>
                        <div class="alert alert-info text-center" role="alert">
                            No se pudo cargar la información de tu cuenta.
                        </div>
                    <?php endif; ?>
                </div>

                <div class="account-container">
                    <?php echo $message; // Muestra mensajes de error o éxito ?>

                    <?php if ($user_perfil): ?>

                        <div class="account-details-group mb-3">
                            <h4 class="card-title fw-bold">Cambiar Contraseña</h4>
                        </div>

                        <div class="card-body">

                            <form method="POST">

                                <div class="account-details-group">

                                    <input type="hidden" name="change_password" value="2">
                                    <label for="oldPassword" class="form-label">Contraseña Actual:</label><br>
                                    <input type="password" class="form-control" id="oldPassword" name="old_password"
                                        required>

                                </div>

                                <div class="account-details-group">

                                    <label for="newPassword" class="form-label">Nueva Contraseña:</label><br>
                                    <input type="password" class="form-control" id="newPassword" name="new_password"
                                        required>
                                </div>

                                <div class="account-details-group">

                                    <label for="confirmPassword" class="form-label">Confirmar Nueva
                                        Contraseña:</label>
                                    <input type="password" class="form-control" id="confirmPassword" name="confirm_password"
                                        required><br>
                                </div>

                                <button type="submit" class="btn btn-primary">Cambiar Contraseña</button>

                            </form>
                        </div>

                    <?php else: ?>
                        <div class="alert alert-info text-center" role="alert">
                            No se pudo cargar la información de tu cuenta.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>


    <!-- Modal para editar Dirección y Teléfono -->
    <div class="modal fade" id="editDatosModal" tabindex="-1" aria-labelledby="editDatosModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">

                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editDatosModalLabel">Editar Datos Personales</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <!-- Campos para editar dirección y teléfono -->
                        <div class="mb-3">
                            <label for="direccion" class="form-label">Dirección</label>
                            <input type="text" class="form-control" id="direccion" name="direccion"
                                value="<?= htmlspecialchars($user_perfil['direccion'] ?? '') ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="telefono" class="form-label">Teléfono</label>
                            <input type="text" class="form-control" id="telefono" name="telefono"
                                value="<?= htmlspecialchars($user_perfil['telefono'] ?? '') ?>" required>
                        </div>
                        <!-- Agrega un campo oculto para indicar la acción -->
                        <input type="hidden" name="update_datos" value="3">
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    </div>
                </form>

            </div>
        </div>
    </div>


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