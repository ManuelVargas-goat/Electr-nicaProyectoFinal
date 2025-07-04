<?php
session_start();
include("config.php"); // Asegúrate de que 'config.php' contenga la conexión PDO.

if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}

$usuario = $_SESSION['usuario'];

// Obtener datos del usuario actual (nombre y rol) para el sidebar
$sql = "SELECT per.nombre, per.apellido_paterno, r.nombre AS rol, ue.persona_id
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

if ($usuarioActual) {
    $nombreUsuario = $usuarioActual['nombre'] . ' ' . $usuarioActual['apellido_paterno'];
    $rolUsuario = strtolower($usuarioActual['rol']);
    $personaId = $usuarioActual['persona_id'];
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

// Lógica para actualizar perfil
$mensaje = '';
$claseMensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
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
        $claseMensaje = 'alert-danger';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $mensaje = 'El formato del email no es válido.';
        $claseMensaje = 'alert-danger';
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
                $claseMensaje = 'alert-success';
                // Volver a cargar los datos del perfil para reflejar los cambios
                $stmtPerfil = $pdo->prepare($sqlPerfil);
                $stmtPerfil->execute([':persona_id' => $personaId]);
                $perfilUsuario = $stmtPerfil->fetch(PDO::FETCH_ASSOC);

                // Actualizar el nombre del usuario en el sidebar si ha cambiado
                $nombreUsuario = $perfilUsuario['nombre'] . ' ' . $perfilUsuario['apellido_paterno'];

            } else {
                $mensaje = 'Error al actualizar el perfil. Inténtelo de nuevo.';
                $claseMensaje = 'alert-danger';
            }
        } catch (PDOException $e) {
            $mensaje = 'Error de base de datos: ' . $e->getMessage();
            $claseMensaje = 'alert-danger';
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($new_password) || empty($confirm_password)) {
        $mensaje = 'Por favor, ingrese y confirme la nueva contraseña.';
        $claseMensaje = 'alert-danger';
    } elseif ($new_password !== $confirm_password) {
        $mensaje = 'Las contraseñas no coinciden.';
        $claseMensaje = 'alert-danger';
    } elseif (strlen($new_password) < 6) {
        $mensaje = 'La contraseña debe tener al menos 6 caracteres.';
        $claseMensaje = 'alert-danger';
    } else {
        try {
            // En una aplicación real, usar password_hash() para almacenar contraseñas de forma segura
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

            $sqlUpdatePassword = "UPDATE usuario_empleado SET password = :password WHERE persona_id = :persona_id";
            $stmtUpdatePassword = $pdo->prepare($sqlUpdatePassword);
            $stmtUpdatePassword->bindParam(':password', $hashed_password);
            $stmtUpdatePassword->bindParam(':persona_id', $personaId, PDO::PARAM_INT);

            if ($stmtUpdatePassword->execute()) {
                $mensaje = '¡Contraseña cambiada con éxito!';
                $claseMensaje = 'alert-success';
            } else {
                $mensaje = 'Error al cambiar la contraseña. Inténtelo de nuevo.';
                $claseMensaje = 'alert-danger';
            }
        } catch (PDOException $e) {
            $mensaje = 'Error de base de datos al cambiar la contraseña: ' . $e->getMessage();
            $claseMensaje = 'alert-danger';
        }
    }
}

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Configuración del Sistema</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/estilos.css?v=<?= time(); ?>">
    <style>
        /* CSS para el submenu */
        .submenu {
            display: none;
            padding-left: 20px;
        }
        .submenu.active {
            display: block;
        }
        .sidebar a {
            display: block;
            padding: 8px 15px;
            color: #ffffff;
            text-decoration: none;
        }
        .sidebar a:hover, .sidebar a.active {
            background-color: #575757;
            border-radius: 5px;
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

            <a href="#" id="catalogoToggle">Gestión de Catálogo</a>
            <div class="submenu" id="catalogoSubmenu">
                <a href="gestion_catalogo_categorias.php">Categorías</a>
                <a href="gestion_catalogo_productos.php">Productos</a>
                <a href="gestion_catalogo_proveedores.php">Proveedores</a>
            </div>
            <a href="gestion_usuarios.php">Gestión de Usuarios</a>
            <a href="gestion_existencias_pedidos.php">Gestión de Existencias</a>
            <a href="configuracion.php" class="active">Configuración</a>

            <div class="mt-4">
                <a href="principal.php" class="btn btn-outline-primary w-100">Volver al inicio</a>
            </div>
        </div>

        <div class="col-md-10 content">
            <h3 class="main-title">Configuración del Sistema</h3>

            <?php if ($mensaje): ?>
                <div class="alert <?= $claseMensaje ?> alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($mensaje) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
                </div>
            <?php endif; ?>

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
                    <h5 class="mt-4">Cambiar Contraseña</h5>
                    <form method="POST">
                        <input type="hidden" name="change_password" value="1">
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

            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    Configuración del Aplicativo
                </div>
                <div class="card-body">
                    <p>Personaliza la apariencia y el comportamiento general de la aplicación.</p>
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="darkModeSwitch">
                        <label class="form-check-label" for="darkModeSwitch">Modo Oscuro</label>
                    </div>
                    <button type="button" class="btn btn-info">Guardar Configuración del App</button>
                </div>
            </div>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.getElementById('catalogoToggle').addEventListener('click', function(event) {
        event.preventDefault(); // Evita que el enlace redirija inmediatamente
        var submenu = document.getElementById('catalogoSubmenu');
        submenu.classList.toggle('active');
    });

    // Lógica para el Modo Oscuro
    const darkModeSwitch = document.getElementById('darkModeSwitch');
    const body = document.body;

    // Cargar preferencia guardada al cargar la página
    document.addEventListener('DOMContentLoaded', (event) => {
        if (localStorage.getItem('darkMode') === 'enabled') {
            body.classList.add('dark-mode');
            darkModeSwitch.checked = true;
        }
    });

    darkModeSwitch.addEventListener('change', function() {
        if (this.checked) {
            body.classList.add('dark-mode');
            localStorage.setItem('darkMode', 'enabled');
        } else {
            body.classList.remove('dark-mode');
            localStorage.setItem('darkMode', 'disabled');
        }
    });
</script>
</body>
</html>