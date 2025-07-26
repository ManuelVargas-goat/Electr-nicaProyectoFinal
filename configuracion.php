<?php
session_start();
include("config.php"); // Asegúrate de que 'config.php' contenga la conexión PDO.

if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}

$usuario = $_SESSION['usuario'];

// Obtener datos del usuario actual (nombre y rol) para el sidebar
$sql = "SELECT per.nombre, per.apellido_paterno, r.nombre AS rol, ue.persona_id, ue.clave as stored_password_hash
        FROM usuario_empleado ue
        JOIN persona per ON ue.persona_id = per.persona_id
        JOIN rol r ON ue.rol_id = r.rol_id
        WHERE ue.usuario = :usuario";

$stmt = $pdo->prepare($sql);
$stmt->execute([':usuario' => $usuario]);

$usuarioActual = $stmt->fetch(PDO::FETCH_ASSOC);

$nombreUsuario = 'Usuario'; // Valor por defecto
$rolUsuario = ''; // Valor por defecto
$personaId = null;
$storedPasswordHash = null; // Para almacenar el hash de la contraseña actual

if ($usuarioActual) {
    $nombreUsuario = htmlspecialchars($usuarioActual['nombre'] . ' ' . $usuarioActual['apellido_paterno']);
    $rolUsuario = strtolower(htmlspecialchars($usuarioActual['rol']));
    $personaId = $usuarioActual['persona_id'];
    $storedPasswordHash = $usuarioActual['stored_password_hash']; // Captura el hash de la contraseña
}

// Obtener datos completos de la persona para la configuración del perfil
$perfilUsuario = [];
if ($personaId) {
    $sqlPerfil = "SELECT nombre, apellido_paterno, apellido_materno, fecha_nacimiento, sexo, email, direccion, telefono
                  FROM persona WHERE persona_id = :persona_id";
    $stmtPerfil = $pdo->prepare($sqlPerfil);
    $stmtPerfil->execute([':persona_id' => $personaId]);
    $perfilUsuario = $stmtPerfil->fetch(PDO::FETCH_ASSOC);
}

// **LÓGICA MEJORADA PARA MENSAJES (Prioriza SESSION, luego POST)**
$mensaje = '';
$tipoMensaje = ''; // Puede ser 'success', 'danger', 'warning', 'info'

// Check for messages from session (e.g., redirect after an action)
if (isset($_SESSION['modal_message']) && !empty($_SESSION['modal_message'])) {
    $mensaje = $_SESSION['modal_message'];
    $tipoMensaje = $_SESSION['modal_type'];
    // Clear session variables so the message doesn't reappear on refresh
    unset($_SESSION['modal_message']);
    unset($_SESSION['modal_type']);
}

// Lógica para actualizar perfil, cambiar contraseña o cambiar usuario (procesa solo si no hay mensaje de sesión pendiente)
if (empty($mensaje)) { // Only process if no session message is pending
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
        // --- INICIO DE LÓGICA PARA ACTUALIZAR PERFIL ---
        $nombre = trim($_POST['nombre'] ?? '');
        $apellido_paterno = trim($_POST['apellido_paterno'] ?? '');
        $apellido_materno = trim($_POST['apellido_materno'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $direccion = trim($_POST['direccion'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');
        $fecha_nacimiento = trim($_POST['fecha_nacimiento'] ?? '');
        $sexo = trim($_POST['sexo'] ?? '');

        // Validaciones básicas (puedes añadir más si es necesario)
        if (empty($nombre) || empty($apellido_paterno) || empty($email)) {
            $mensaje = 'Por favor, complete los campos obligatorios (Nombre, Apellido Paterno, Email).';
            $tipoMensaje = 'danger';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $mensaje = 'El formato del email no es válido.';
            $tipoMensaje = 'danger';
        } else {
            try {
                $sqlUpdate = "UPDATE persona SET
                                  nombre = :nombre,
                                  apellido_paterno = :apellido_paterno,
                                  apellido_materno = :apellido_materno,
                                  email = :email,
                                  direccion = :direccion,
                                  telefono = :telefono,
                                  fecha_nacimiento = :fecha_nacimiento,
                                  sexo = :sexo
                                  WHERE persona_id = :persona_id";

                $stmtUpdate = $pdo->prepare($sqlUpdate);
                $stmtUpdate->bindParam(':nombre', $nombre);
                $stmtUpdate->bindParam(':apellido_paterno', $apellido_paterno);
                $stmtUpdate->bindParam(':apellido_materno', $apellido_materno);
                $stmtUpdate->bindParam(':email', $email);
                $stmtUpdate->bindParam(':direccion', $direccion);
                $stmtUpdate->bindParam(':telefono', $telefono);
                $stmtUpdate->bindParam(':fecha_nacimiento', $fecha_nacimiento);
                $stmtUpdate->bindParam(':sexo', $sexo);
                $stmtUpdate->bindParam(':persona_id', $personaId, PDO::PARAM_INT);

                if ($stmtUpdate->execute()) {
                    $mensaje = '¡Perfil actualizado con éxito!';
                    $tipoMensaje = 'success';
                    // Volver a cargar los datos del perfil para reflejar los cambios
                    $stmtPerfil = $pdo->prepare($sqlPerfil);
                    $stmtPerfil->execute([':persona_id' => $personaId]);
                    $perfilUsuario = $stmtPerfil->fetch(PDO::FETCH_ASSOC);

                    // Actualizar el nombre del usuario en el sidebar si ha cambiado
                    $nombreUsuario = htmlspecialchars($perfilUsuario['nombre'] . ' ' . $perfilUsuario['apellido_paterno']);

                } else {
                    $mensaje = 'Error al actualizar el perfil. Inténtelo de nuevo.';
                    $tipoMensaje = 'danger';
                }
            } catch (PDOException $e) {
                $mensaje = 'Error de base de datos: ' . $e->getMessage();
                $tipoMensaje = 'danger';
            }
        }
        // --- FIN DE LÓGICA PARA ACTUALIZAR PERFIL ---
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_username'])) {
        // --- INICIO DE LÓGICA PARA CAMBIAR USUARIO ---
        $new_username = trim($_POST['new_username'] ?? '');

        if (empty($new_username)) {
            $mensaje = 'Por favor, ingrese el nuevo nombre de usuario.';
            $tipoMensaje = 'danger';
        } else {
            try {
                // Verificar si el nuevo nombre de usuario ya existe
                $stmtCheckUser = $pdo->prepare("SELECT COUNT(*) FROM usuario_empleado WHERE usuario = :new_username AND persona_id <> :persona_id");
                $stmtCheckUser->execute([':new_username' => $new_username, ':persona_id' => $personaId]);
                if ($stmtCheckUser->fetchColumn() > 0) {
                    $mensaje = 'El nombre de usuario ya está en uso. Por favor, elija otro.';
                    $tipoMensaje = 'danger';
                } else {
                    // Actualizar el nombre de usuario
                    $sqlUpdateUser = "UPDATE usuario_empleado SET usuario = :new_username WHERE persona_id = :persona_id";
                    $stmtUpdateUser = $pdo->prepare($sqlUpdateUser);
                    $stmtUpdateUser->bindParam(':new_username', $new_username);
                    $stmtUpdateUser->bindParam(':persona_id', $personaId, PDO::PARAM_INT);

                    if ($stmtUpdateUser->execute()) {
                        $_SESSION['usuario'] = $new_username; // Actualizar la sesión con el nuevo nombre de usuario
                        $_SESSION['modal_message'] = '¡Nombre de usuario cambiado con éxito!';
                        $_SESSION['modal_type'] = 'success';
                        header("Location: configuracion.php"); // Redirigir para refrescar la página y el sidebar
                        exit();
                    } else {
                        $mensaje = 'Error al cambiar el nombre de usuario. Inténtelo de nuevo.';
                        $tipoMensaje = 'danger';
                    }
                }
            } catch (PDOException $e) {
                $mensaje = 'Error de base de datos al cambiar el nombre de usuario: ' . $e->getMessage();
                $tipoMensaje = 'danger';
            }
        }
        // --- FIN DE LÓGICA PARA CAMBIAR USUARIO ---
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $mensaje = 'Por favor, complete todos los campos de contraseña.';
            $tipoMensaje = 'danger';
        } elseif (!password_verify($current_password, $storedPasswordHash)) {
            $mensaje = 'La contraseña actual no es correcta.';
            $tipoMensaje = 'danger';
        } elseif ($new_password !== $confirm_password) {
            $mensaje = 'Las nuevas contraseñas no coinciden.';
            $tipoMensaje = 'danger';
        } elseif (strlen($new_password) < 6) {
            $mensaje = 'La nueva contraseña debe tener al menos 6 caracteres.';
            $tipoMensaje = 'danger';
        } else {
            try {
                $hashed_new_password = password_hash($new_password, PASSWORD_DEFAULT);

                $sqlUpdatePassword = "UPDATE usuario_empleado SET clave = :new_password_hash WHERE persona_id = :persona_id";
                $stmtUpdatePassword = $pdo->prepare($sqlUpdatePassword);
                $stmtUpdatePassword->bindParam(':new_password_hash', $hashed_new_password);
                $stmtUpdatePassword->bindParam(':persona_id', $personaId, PDO::PARAM_INT);

                if ($stmtUpdatePassword->execute()) {
                    $mensaje = '¡Contraseña cambiada con éxito!';
                    $tipoMensaje = 'success';
                } else {
                    $mensaje = 'Error al cambiar la contraseña. Inténtelo de nuevo.';
                    $tipoMensaje = 'danger';
                }
            } catch (PDOException $e) {
                $mensaje = 'Error de base de datos al cambiar la contraseña: ' . $e->getMessage();
                $tipoMensaje = 'danger';
            }
        }
    }
}

// Variable para el nombre del archivo actual (sin la extensión .php)
$currentPage = basename($_SERVER['PHP_SELF'], ".php");

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Configuración del Sistema</title>
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

        .btn-danger {
            background-color: #dc3545; /* Rojo de Bootstrap */
            border-color: #dc3545;
        }

        .btn-danger:hover {
            background-color: #c82333;
            border-color: #bd2130;
        }

        .btn-secondary {
            background-color: #6c757d; /* Gris de Bootstrap */
            border-color: #6c757d;
        }

        .btn-secondary:hover {
            background-color: #5c636a;
            border-color: #565e64;
        }

        /* Contenido principal */
        .content {
            padding: 20px;
        }

        /* Estilos para el tema oscuro */
        body.dark-mode {
            background-color: #343a40; /* Fondo oscuro */
            color: #f8f9fa; /* Texto claro */
        }
        body.dark-mode .sidebar {
            background-color: #212529; /* Sidebar más oscuro */
        }
        body.dark-mode .content {
            background-color: #495057; /* Contenido más oscuro */
            color: #f8f9fa;
        }
        body.dark-mode .main-title {
            color: #f8f9fa;
        }
        body.dark-mode .card {
            background-color: #545b62; /* Tarjetas oscuras */
            border-color: #6c757d;
        }
        body.dark-mode .card-header {
            background-color: #6c757d !important; /* Header de tarjetas más oscuro */
            color: #f8f9fa !important;
        }
        body.dark-mode .form-control, body.dark-mode .form-select {
            background-color: #6c757d;
            color: #f8f9fa;
            border-color: #495057;
        }
        body.dark-mode .form-control::placeholder {
            color: #ced4da;
        }
        body.dark-mode .form-control:focus, body.dark-mode .form-select:focus {
            background-color: #7a8288;
            color: #f8f9fa;
            border-color: #889299;
            box-shadow: 0 0 0 0.25rem rgba(108, 117, 125, 0.25);
        }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <div class="col-md-2 sidebar">
            <div class="user-box text-center mb-4">
                <img src="https://cdn-icons-png.flaticon.com/512/149/149071.png" alt="Usuario" class="img-fluid rounded-circle mb-2" style="width: 64px;">

                <div class="fw-bold"><?= htmlspecialchars($nombreUsuario) ?></div>

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
            <h3 class="main-title">Configuración del Sistema</h3>

            <?php
            // **BLOQUE PARA MOSTRAR LA ALERTA DE BOOTSTRAP**
            if (!empty($mensaje) && !empty($tipoMensaje)) {
                $alertClass = '';
                switch ($tipoMensaje) {
                    case 'success':
                        $alertClass = 'alert-success';
                        break;
                    case 'danger':
                        $alertClass = 'alert-danger';
                        break;
                    case 'warning':
                        $alertClass = 'alert-warning';
                        break;
                    case 'info':
                        $alertClass = 'alert-info';
                        break;
                    default:
                        $alertClass = 'alert-info'; // Tipo predeterminado si no coincide
                }
                echo '<div class="alert ' . $alertClass . ' alert-dismissible fade show mt-3" role="alert">';
                echo htmlspecialchars($mensaje); // Muestra el mensaje escapando caracteres especiales
                echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>'; // Botón para cerrar la alerta
                echo '</div>';
            }
            ?>

            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    Configuración de Perfil
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="update_profile" value="1">
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="nombre" class="form-label">Nombre:</label>
                                <input type="text" class="form-control" id="nombre" name="nombre" value="<?= htmlspecialchars($perfilUsuario['nombre'] ?? '') ?>">
                            </div>
                            <div class="col-md-4">
                                <label for="apellido_paterno" class="form-label">Apellido Paterno:</label>
                                <input type="text" class="form-control" id="apellido_paterno" name="apellido_paterno" value="<?= htmlspecialchars($perfilUsuario['apellido_paterno'] ?? '') ?>">
                            </div>
                            <div class="col-md-4">
                                <label for="apellido_materno" class="form-label">Apellido Materno:</label>
                                <input type="text" class="form-control" id="apellido_materno" name="apellido_materno" value="<?= htmlspecialchars($perfilUsuario['apellido_materno'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="email" class="form-label">Email:</label>
                                <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($perfilUsuario['email'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="telefono" class="form-label">Teléfono:</label>
                                <input type="tel" class="form-control" id="telefono" name="telefono" value="<?= htmlspecialchars($perfilUsuario['telefono'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="direccion" class="form-label">Dirección:</label>
                            <input type="text" class="form-control" id="direccion" name="direccion" value="<?= htmlspecialchars($perfilUsuario['direccion'] ?? '') ?>">
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="fecha_nacimiento" class="form-label">Fecha de Nacimiento:</label>
                                <input type="date" class="form-control" id="fecha_nacimiento" name="fecha_nacimiento" value="<?= htmlspecialchars($perfilUsuario['fecha_nacimiento'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="sexo" class="form-label">Sexo:</label>
                                <select class="form-select" id="sexo" name="sexo">
                                    <option value="" disabled selected>Seleccione</option>
                                    <option value="M" <?= (isset($perfilUsuario['sexo']) && $perfilUsuario['sexo'] === 'M') ? 'selected' : '' ?>>Masculino</option>
                                    <option value="F" <?= (isset($perfilUsuario['sexo']) && $perfilUsuario['sexo'] === 'F') ? 'selected' : '' ?>>Femenino</option>
                                </select>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary">Guardar Cambios del Perfil</button>
                    </form>
                    <hr>
                    <h5 class="mt-4">Cambiar Nombre de Usuario</h5>
                    <form method="POST">
                        <input type="hidden" name="change_username" value="1">
                        <div class="mb-3">
                            <label for="newUsername" class="form-label">Nuevo Nombre de Usuario:</label>
                            <input type="text" class="form-control" id="newUsername" name="new_username" value="<?= htmlspecialchars($usuario) ?>" required>
                        </div>
                        <button type="submit" class="btn btn-warning">Cambiar Nombre de Usuario</button>
                    </form>
                    <hr>
                    <h5 class="mt-4">Cambiar Contraseña</h5>
                    <form method="POST">
                        <input type="hidden" name="change_password" value="1">
                        <div class="mb-3">
                            <label for="currentPassword" class="form-label">Contraseña Actual:</label>
                            <input type="password" class="form-control" id="currentPassword" name="current_password" required>
                        </div>
                        <div class="mb-3">
                            <label for="newPassword" class="form-label">Nueva Contraseña:</label>
                            <input type="password" class="form-control" id="newPassword" name="new_password" required>
                        </div>
                        <div class="mb-3">
                            <label for="confirmPassword" class="form-label">Confirmar Nueva Contraseña:</label>
                            <input type="password" class="form-control" id="confirmPassword" name="confirm_password" required>
                        </div>
                        <button type="submit" class="btn btn-warning">Cambiar Contraseña</button>
                    </form>
                </div>
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
