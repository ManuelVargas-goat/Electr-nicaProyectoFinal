<?php
session_start();
include("config.php"); // Asegúrate de que 'config.php' contenga la conexión PDO.

if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}

$usuario_empleado_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($usuario_empleado_id === 0) {
    // Si no se proporciona un ID válido, redirigir a la gestión de usuarios
    header("Location: gestion_usuarios.php");
    exit();
}

$mensaje = '';
$claseMensaje = '';

$userData = [];
$perfilData = [];
$roles = [];

// 1. Obtener datos del usuario a editar
$sqlUser = "SELECT ue.usuario_empleado_id, ue.usuario, ue.rol_id, ue.persona_id, p.nombre, p.apellido_paterno, p.apellido_materno, p.fecha_nacimiento, p.sexo, p.email, p.direccion, p.telefono
            FROM usuario_empleado ue
            JOIN persona p ON ue.persona_id = p.persona_id
            WHERE ue.usuario_empleado_id = :usuario_empleado_id";
$stmtUser = $pdo->prepare($sqlUser);
$stmtUser->execute([':usuario_empleado_id' => $usuario_empleado_id]);
$userData = $stmtUser->fetch(PDO::FETCH_ASSOC);

if (!$userData) {
    // Si el usuario no se encuentra, redirigir a la gestión de usuarios
    header("Location: gestion_usuarios.php");
    exit();
} else {
    $perfilData = [
        'nombre' => $userData['nombre'],
        'apellido_paterno' => $userData['apellido_paterno'],
        'apellido_materno' => $userData['apellido_materno'],
        'fecha_nacimiento' => $userData['fecha_nacimiento'],
        'sexo' => $userData['sexo'],
        'email' => $userData['email'],
        'direccion' => $userData['direccion'],
        'telefono' => $userData['telefono']
    ];
}

// 2. Obtener lista de roles
$sqlRoles = "SELECT rol_id, nombre FROM rol ORDER BY nombre";
$stmtRoles = $pdo->query($sqlRoles);
$roles = $stmtRoles->fetchAll(PDO::FETCH_ASSOC);


// 3. Manejar actualización de datos del usuario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user_data'])) {
    $pdo->beginTransaction(); // Iniciar transacción

    try {
        // Actualizar tabla persona
        $nombre = trim($_POST['nombre'] ?? '');
        $apellido_paterno = trim($_POST['apellido_paterno'] ?? '');
        $apellido_materno = trim($_POST['apellido_materno'] ?? '');
        $fecha_nacimiento = trim($_POST['fecha_nacimiento'] ?? '');
        $sexo = trim($_POST['sexo'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $direccion = trim($_POST['direccion'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');

        if (empty($nombre) || empty($apellido_paterno) || empty($email)) {
            throw new Exception('Por favor, complete los campos de perfil obligatorios (Nombre, Apellido Paterno, Email).');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('El formato del email no es válido.');
        }

        $sqlUpdatePersona = "UPDATE persona SET
                            nombre = :nombre,
                            apellido_paterno = :apellido_paterno,
                            apellido_materno = :apellido_materno,
                            fecha_nacimiento = :fecha_nacimiento,
                            sexo = :sexo,
                            email = :email,
                            direccion = :direccion,
                            telefono = :telefono
                            WHERE persona_id = :persona_id";
        $stmtUpdatePersona = $pdo->prepare($sqlUpdatePersona);
        $stmtUpdatePersona->execute([
            ':nombre' => $nombre,
            ':apellido_paterno' => $apellido_paterno,
            ':apellido_materno' => $apellido_materno,
            ':fecha_nacimiento' => $fecha_nacimiento,
            ':sexo' => $sexo,
            ':email' => $email,
            ':direccion' => $direccion,
            ':telefono' => $telefono,
            ':persona_id' => $userData['persona_id']
        ]);

        // Actualizar tabla usuario_empleado
        $usuario_username = trim($_POST['usuario_username'] ?? '');
        $rol_id = intval($_POST['rol_id'] ?? 0);

        if (empty($usuario_username) || $rol_id === 0) {
            throw new Exception('Por favor, complete los campos de cuenta obligatorios (Nombre de Usuario, Rol).');
        }

        // Verificar si el nuevo nombre de usuario ya existe para otro usuario
        $sqlCheckUser = "SELECT COUNT(*) FROM usuario_empleado WHERE usuario = :usuario AND usuario_empleado_id != :current_id";
        $stmtCheckUser = $pdo->prepare($sqlCheckUser);
        $stmtCheckUser->execute([':usuario' => $usuario_username, ':current_id' => $usuario_empleado_id]);
        if ($stmtCheckUser->fetchColumn() > 0) {
            throw new Exception('El nombre de usuario ya existe. Por favor, elija otro.');
        }

        $sqlUpdateUserEmpleado = "UPDATE usuario_empleado SET
                                usuario = :usuario,
                                rol_id = :rol_id
                                WHERE usuario_empleado_id = :usuario_empleado_id";
        $stmtUpdateUserEmpleado = $pdo->prepare($sqlUpdateUserEmpleado);
        $stmtUpdateUserEmpleado->execute([
            ':usuario' => $usuario_username,
            ':rol_id' => $rol_id,
            ':usuario_empleado_id' => $usuario_empleado_id
        ]);

        $pdo->commit(); // Confirmar transacción
        // Redirigir después de una actualización exitosa
        header("Location: gestion_usuarios.php?status=success_update");
        exit();

    } catch (Exception $e) {
        $pdo->rollBack(); // Revertir transacción en caso de error
        $mensaje = 'Error al actualizar el usuario: ' . $e->getMessage();
        $claseMensaje = 'alert-danger';
    }
}

// 4. Manejar cambio de contraseña
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
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
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

            $sqlUpdatePassword = "UPDATE usuario_empleado SET password = :password WHERE usuario_empleado_id = :usuario_empleado_id";
            $stmtUpdatePassword = $pdo->prepare($sqlUpdatePassword);
            $stmtUpdatePassword->execute([
                ':password' => $hashed_password,
                ':usuario_empleado_id' => $usuario_empleado_id
            ]);

            // Redirigir después de un cambio de contraseña exitoso
            header("Location: gestion_usuarios.php?status=success_password");
            exit();
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
    <title>Editar Usuario</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/estilos.css?v=<?= time(); ?>">
    <style>
        /* Estilos para el tema oscuro (copiados de configuracion.php) */
        body.dark-mode {
            background-color: #343a40; /* Fondo oscuro */
            color: #f8f9fa; /* Texto claro */
        }
        body.dark-mode .container { /* Asegurar que el contenedor principal también se adapte */
            background-color: #343a40;
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
<div class="container mt-5">
    <h3 class="main-title">Editar Usuario: <?= htmlspecialchars($userData['usuario'] ?? 'N/A') ?></h3>

    <?php if ($mensaje): ?>
        <div class="alert <?= $claseMensaje ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($mensaje) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
    <?php endif; ?>

    <div class="card mb-4 p-4">
        <form method="POST">
            <input type="hidden" name="update_user_data" value="1">
            <h5 class="mb-3">Datos Personales</h5>
            <div class="row mb-3">
                <div class="col-md-4">
                    <label for="nombre" class="form-label">Nombre:</label>
                    <input type="text" class="form-control" id="nombre" name="nombre" value="<?= htmlspecialchars($perfilData['nombre'] ?? '') ?>" required>
                </div>
                <div class="col-md-4">
                    <label for="apellido_paterno" class="form-label">Apellido Paterno:</label>
                    <input type="text" class="form-control" id="apellido_paterno" name="apellido_paterno" value="<?= htmlspecialchars($perfilData['apellido_paterno'] ?? '') ?>" required>
                </div>
                <div class="col-md-4">
                    <label for="apellido_materno" class="form-label">Apellido Materno:</label>
                    <input type="text" class="form-control" id="apellido_materno" name="apellido_materno" value="<?= htmlspecialchars($perfilData['apellido_materno'] ?? '') ?>">
                </div>
            </div>
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="email" class="form-label">Email:</label>
                    <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($perfilData['email'] ?? '') ?>" required>
                </div>
                <div class="col-md-6">
                    <label for="telefono" class="form-label">Teléfono:</label>
                    <input type="tel" class="form-control" id="telefono" name="telefono" value="<?= htmlspecialchars($perfilData['telefono'] ?? '') ?>">
                </div>
            </div>
            <div class="mb-3">
                <label for="direccion" class="form-label">Dirección:</label>
                <input type="text" class="form-control" id="direccion" name="direccion" value="<?= htmlspecialchars($perfilData['direccion'] ?? '') ?>">
            </div>
            <div class="row mb-4">
                <div class="col-md-6">
                    <label for="fecha_nacimiento" class="form-label">Fecha de Nacimiento:</label>
                    <input type="date" class="form-control" id="fecha_nacimiento" name="fecha_nacimiento" value="<?= htmlspecialchars($perfilData['fecha_nacimiento'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label for="sexo" class="form-label">Sexo:</label>
                    <select class="form-select" id="sexo" name="sexo">
                        <option value="" disabled>Seleccione</option>
                        <option value="M" <?= (isset($perfilData['sexo']) && $perfilData['sexo'] === 'M') ? 'selected' : '' ?>>Masculino</option>
                        <option value="F" <?= (isset($perfilData['sexo']) && $perfilData['sexo'] === 'F') ? 'selected' : '' ?>>Femenino</option>
                    </select>
                </div>
            </div>

            <h5 class="mb-3">Datos de la Cuenta</h5>
            <div class="row mb-4">
                <div class="col-md-6">
                    <label for="usuario_username" class="form-label">Nombre de Usuario:</label>
                    <input type="text" class="form-control" id="usuario_username" name="usuario_username" value="<?= htmlspecialchars($userData['usuario'] ?? '') ?>" required>
                </div>
                <div class="col-md-6">
                    <label for="rol_id" class="form-label">Rol:</label>
                    <select class="form-select" id="rol_id" name="rol_id" required>
                        <option value="" disabled>Seleccione un Rol</option>
                        <?php foreach ($roles as $rol): ?>
                            <option value="<?= htmlspecialchars($rol['rol_id']) ?>" <?= (isset($userData['rol_id']) && $userData['rol_id'] == $rol['rol_id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars(ucfirst($rol['nombre'])) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="d-flex justify-content-between align-items-center">
                <a href="gestion_usuarios.php" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary">Actualizar Datos</button>
            </div>
        </form>
    </div>

    <div class="card mb-4 p-4">
        <h5 class="mb-3">Cambiar Contraseña</h5>
        <form method="POST">
            <input type="hidden" name="change_password" value="1">
            <div class="mb-3">
                <label for="newPassword" class="form-label">Nueva Contraseña:</label>
                <input type="password" class="form-control" id="newPassword" name="new_password" required>
            </div>
            <div class="mb-4">
                <label for="confirmPassword" class="form-label">Confirmar Nueva Contraseña:</label>
                <input type="password" class="form-control" id="confirmPassword" name="confirm_password" required>
            </div>
            <div class="d-flex justify-content-end">
                <button type="submit" class="btn btn-warning">Cambiar Contraseña</button>
            </div>
        </form>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Lógica para el Modo Oscuro (se aplica al cargar la página si la preferencia está guardada)
    document.addEventListener('DOMContentLoaded', (event) => {
        const body = document.body;
        if (localStorage.getItem('darkMode') === 'enabled') {
            body.classList.add('dark-mode');
        }
    });
</script>
</body>
</html>