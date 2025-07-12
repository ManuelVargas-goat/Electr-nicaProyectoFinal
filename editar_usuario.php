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
try {
    $sqlUser = "SELECT ue.usuario_empleado_id, ue.usuario, ue.rol_id, ue.persona_id, ue.estado,
                        p.nombre, p.apellido_paterno, p.apellido_materno, p.fecha_nacimiento, p.sexo, p.email, p.direccion, p.telefono
                 FROM usuario_empleado ue
                 JOIN persona p ON ue.persona_id = p.persona_id
                 WHERE ue.usuario_empleado_id = :usuario_empleado_id";
    $stmtUser = $pdo->prepare($sqlUser);
    $stmtUser->execute([':usuario_empleado_id' => $usuario_empleado_id]);
    $userData = $stmtUser->fetch(PDO::FETCH_ASSOC);

    if (!$userData) {
        // Si el usuario no se encuentra, redirigir a la gestión de usuarios
        header("Location: gestion_usuarios.php?status=error_notfound");
        exit();
    } else {
        // Asignar los datos para rellenar el formulario
        $nombre = $userData['nombre'];
        $apellido_paterno = $userData['apellido_paterno'];
        $apellido_materno = $userData['apellido_materno'];
        $email = $userData['email'];
        $telefono = $userData['telefono'];
        $direccion = $userData['direccion'];
        $fecha_nacimiento = $userData['fecha_nacimiento'];
        $sexo = $userData['sexo'];
        $usuario_login = $userData['usuario']; // Renombrado para consistencia con nuevo_empleado
        $rol_id = $userData['rol_id'];
        $estado = $userData['estado'];
    }
} catch (PDOException $e) {
    error_log("Error al cargar datos del usuario: " . $e->getMessage());
    $mensaje = "Error al cargar los datos del usuario. Intente de nuevo.";
    $claseMensaje = 'alert-danger';
}


// 2. Obtener lista de roles
try {
    $sqlRoles = "SELECT rol_id, nombre FROM rol ORDER BY nombre";
    $stmtRoles = $pdo->query($sqlRoles);
    $roles = $stmtRoles->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error al cargar roles: " . $e->getMessage());
    $mensaje = "Error al cargar los roles. Por favor, intente de nuevo.";
    $claseMensaje = 'alert-danger';
}


// 3. Manejar actualización de datos del usuario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user_data'])) {
    $pdo->beginTransaction(); // Iniciar transacción

    try {
        // Capturar y sanear los datos del POST
        $nombre = trim($_POST['nombre'] ?? '');
        $apellido_paterno = trim($_POST['apellido_paterno'] ?? '');
        $apellido_materno = trim($_POST['apellido_materno'] ?? '');
        $fecha_nacimiento = trim($_POST['fecha_nacimiento'] ?? '');
        $sexo = trim($_POST['sexo'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $direccion = trim($_POST['direccion'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');
        $usuario_login = trim($_POST['usuario_login'] ?? ''); // De 'usuario_username' a 'usuario_login' para consistencia
        $rol_id = intval($_POST['rol_id'] ?? 0);
        $estado = trim($_POST['estado'] ?? '1'); // Capturar el estado

        // Validaciones
        if (empty($nombre) || empty($apellido_paterno) || empty($email) || empty($usuario_login) || $rol_id === 0) {
            throw new Exception('Todos los campos obligatorios (marcados con *) deben ser completados.');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('El formato del email no es válido.');
        }

        // Verificar si el nuevo nombre de usuario ya existe para otro usuario
        $sqlCheckUser = "SELECT COUNT(*) FROM usuario_empleado WHERE usuario = :usuario AND usuario_empleado_id != :current_id";
        $stmtCheckUser = $pdo->prepare($sqlCheckUser);
        $stmtCheckUser->execute([':usuario' => $usuario_login, ':current_id' => $usuario_empleado_id]);
        if ($stmtCheckUser->fetchColumn() > 0) {
            throw new Exception('El nombre de usuario ya existe. Por favor, elija otro.');
        }

        // Actualizar tabla persona
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
        $sqlUpdateUserEmpleado = "UPDATE usuario_empleado SET
                                    usuario = :usuario,
                                    rol_id = :rol_id,
                                    estado = :estado
                                    WHERE usuario_empleado_id = :usuario_empleado_id";
        $stmtUpdateUserEmpleado = $pdo->prepare($sqlUpdateUserEmpleado);
        $stmtUpdateUserEmpleado->execute([
            ':usuario' => $usuario_login,
            ':rol_id' => $rol_id,
            ':estado' => $estado,
            ':usuario_empleado_id' => $usuario_empleado_id
        ]);

        $pdo->commit(); // Confirmar transacción

        // Redirigir después de una actualización exitosa
        header("Location: gestion_usuarios.php?status=success_update");
        exit();

    } catch (Exception $e) {
        $pdo->rollBack(); // Revertir transacción en caso de error
        $mensaje = 'Error al actualizar los datos: ' . $e->getMessage();
        $claseMensaje = 'alert-danger';
    }
}

// 4. Manejar cambio de contraseña
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Eliminado el chequeo de strlen($new_password) < 6
    if (empty($new_password) || empty($confirm_password)) {
        $mensaje = 'Por favor, ingrese y confirme la nueva contraseña.';
        $claseMensaje = 'alert-danger';
    } elseif ($new_password !== $confirm_password) {
        $mensaje = 'Las contraseñas no coinciden.';
        $claseMensaje = 'alert-danger';
    } else {
        try {
            // ¡ADVERTENCIA: md5 no es seguro para contraseñas en producción!
            // Para producción, usa: $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $hashed_password = md5($new_password);

            $sqlUpdatePassword = "UPDATE usuario_empleado SET clave = :clave WHERE usuario_empleado_id = :usuario_empleado_id";
            $stmtUpdatePassword = $pdo->prepare($sqlUpdatePassword);
            $stmtUpdatePassword->execute([
                ':clave' => $hashed_password,
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
    <title>Editar Usuario: <?= htmlspecialchars($userData['usuario'] ?? 'N/A') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/estilos.css?v=<?= time(); ?>">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        /* Estilos generales para el formulario, manteniendo la consistencia con nuevo_empleado.php */
        body {
            background-color: #f8f9fa; /* Color de fondo claro por defecto */
        }
        .container.mt-5 {
            max-width: 900px; /* Ancho máximo similar al de productos */
        }
        .main-title {
            color: #343a40;
            margin-bottom: 30px;
            text-align: center;
        }
        .card {
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15); /* Sombra más pronunciada */
            border-radius: 0.75rem; /* Bordes más redondeados */
            border: none; /* Eliminar borde por defecto de la tarjeta */
        }
        .form-label {
            font-weight: 600; /* Un poco más de negrita para las etiquetas */
            color: #495057;
        }
        /* Botones */
        .btn-primary {
            background-color: #007bff;
            border-color: #007bff;
        }
        .btn-primary:hover {
            background-color: #0056b3;
            border-color: #0056b3;
        }
        .btn-secondary {
            background-color: #6c757d;
            border-color: #6c757d;
        }
        .btn-secondary:hover {
            background-color: #5a6268;
            border-color: #545b62;
        }
        .btn-info {
            background-color: #17a2b8;
            border-color: #17a2b8;
        }
        .btn-info:hover {
            background-color: #138496;
            border-color: #117a8b;
        }

        /* Estilos para el tema oscuro, copiados de nuevo_empleado.php */
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
        body.dark-mode .card-header { /* No tenemos card-header aquí, pero lo mantengo por consistencia */
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
        body.dark-mode .form-label {
            color: #f8f9fa; /* Asegura que las etiquetas sean visibles en modo oscuro */
        }
        .required-label::after {
            content: " *";
            color: #dc3545; /* Color rojo para el asterisco */
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

    <form method="POST" class="card p-4 mb-4 needs-validation" novalidate>
        <input type="hidden" name="update_user_data" value="1">
        <h4 class="mb-3">Datos de la Persona</h4>
        <div class="row g-3 mb-3">
            <div class="col-md-4">
                <label for="nombre" class="form-label required-label">Nombre</label>
                <input type="text" class="form-control" id="nombre" name="nombre" required value="<?= htmlspecialchars($nombre) ?>">
                <div class="invalid-feedback">Por favor, ingrese el nombre.</div>
            </div>
            <div class="col-md-4">
                <label for="apellido_paterno" class="form-label required-label">Apellido Paterno</label>
                <input type="text" class="form-control" id="apellido_paterno" name="apellido_paterno" required value="<?= htmlspecialchars($apellido_paterno) ?>">
                <div class="invalid-feedback">Por favor, ingrese el apellido paterno.</div>
            </div>
            <div class="col-md-4">
                <label for="apellido_materno" class="form-label">Apellido Materno</label>
                <input type="text" class="form-control" id="apellido_materno" name="apellido_materno" value="<?= htmlspecialchars($apellido_materno) ?>">
            </div>
            <div class="col-md-6">
                <label for="email" class="form-label required-label">Email</label>
                <input type="email" class="form-control" id="email" name="email" required value="<?= htmlspecialchars($email) ?>">
                <div class="invalid-feedback">Por favor, ingrese un email válido.</div>
            </div>
            <div class="col-md-6">
                <label for="telefono" class="form-label">Teléfono</label>
                <input type="text" class="form-control" id="telefono" name="telefono" value="<?= htmlspecialchars($telefono) ?>">
            </div>
            <div class="col-md-12">
                <label for="direccion" class="form-label">Dirección</label>
                <input type="text" class="form-control" id="direccion" name="direccion" value="<?= htmlspecialchars($direccion) ?>">
            </div>
            <div class="col-md-6">
                <label for="fecha_nacimiento" class="form-label">Fecha de Nacimiento</label>
                <input type="date" class="form-control" id="fecha_nacimiento" name="fecha_nacimiento" value="<?= htmlspecialchars($fecha_nacimiento) ?>">
            </div>
            <div class="col-md-6">
                <label for="sexo" class="form-label">Sexo</label>
                <select class="form-select" id="sexo" name="sexo">
                    <option value="">Seleccione...</option>
                    <option value="M" <?= ($sexo == 'M') ? 'selected' : '' ?>>Masculino</option>
                    <option value="F" <?= ($sexo == 'F') ? 'selected' : '' ?>>Femenino</option>
                </select>
            </div>
        </div>

        <h4 class="mb-3 mt-4">Datos de Acceso del Empleado</h4>
        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <label for="usuario_login" class="form-label required-label">Nombre de Usuario (Login)</label>
                <input type="text" class="form-control" id="usuario_login" name="usuario_login" required value="<?= htmlspecialchars($usuario_login) ?>">
                <div class="invalid-feedback">Por favor, ingrese un nombre de usuario.</div>
            </div>
            <div class="col-md-6">
                <label for="rol_id" class="form-label required-label">Rol</label>
                <select class="form-select" id="rol_id" name="rol_id" required>
                    <option value="">Seleccione un rol...</option>
                    <?php foreach ($roles as $rol): ?>
                        <option value="<?= $rol['rol_id'] ?>" <?= ($rol_id == $rol['rol_id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars(ucfirst($rol['nombre'])) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="invalid-feedback">Por favor, seleccione un rol para el empleado.</div>
            </div>
            <div class="col-md-6">
                <label for="estado" class="form-label">Estado</label>
                <select class="form-select" id="estado" name="estado">
                    <option value="1" <?= ($estado == '1') ? 'selected' : '' ?>>Activo</option>
                    <option value="0" <?= ($estado == '0') ? 'selected' : '' ?>>Inactivo</option>
                </select>
            </div>
        </div>

        <div class="d-flex justify-content-between mt-4">
            <a href="gestion_usuarios.php" class="btn btn-secondary"><i class="bi bi-x-circle-fill"></i> Cancelar</a>
            <button type="submit" class="btn btn-primary"><i class="bi bi-arrow-clockwise"></i> Actualizar Datos</button>
        </div>
    </form>

    <div class="card p-4 mt-4">
        <form method="POST" class="needs-validation" novalidate>
            <input type="hidden" name="change_password" value="1">
            <h4 class="mb-3">Cambiar Contraseña</h4>
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label for="new_password" class="form-label required-label">Nueva Contraseña</label>
                    <input type="password" class="form-control" id="new_password" name="new_password" required>
                    <div class="invalid-feedback">Por favor, ingrese la nueva contraseña.</div>
                </div>
                <div class="col-md-6">
                    <label for="confirm_password" class="form-label required-label">Confirmar Nueva Contraseña</label>
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                    <div class="invalid-feedback">Las contraseñas no coinciden.</div>
                </div>
            </div>
            <div class="d-flex justify-content-end">
                <button type="submit" class="btn btn-info"><i class="bi bi-key-fill"></i> Cambiar Contraseña</button>
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

    // JavaScript para validación de Bootstrap en ambos formularios
    document.addEventListener('DOMContentLoaded', function() {
        // Validación del formulario principal de datos
        const updateForm = document.querySelector('form[name="update_user_data"]');
        if (updateForm) {
            updateForm.addEventListener('submit', function (event) {
                if (!updateForm.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                updateForm.classList.add('was-validated');
            }, false);
        }

        // Validación del formulario de cambio de contraseña
        const passwordForm = document.querySelector('form[name="change_password"]');
        if (passwordForm) {
            const newPasswordInput = document.getElementById('new_password');
            const confirmPasswordInput = document.getElementById('confirm_password');

            passwordForm.addEventListener('submit', function (event) {
                if (newPasswordInput.value !== confirmPasswordInput.value) {
                    confirmPasswordInput.setCustomValidity("Las contraseñas no coinciden.");
                } else {
                    // Si coinciden, limpia el mensaje de error personalizado
                    confirmPasswordInput.setCustomValidity("");
                }

                if (!passwordForm.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                passwordForm.classList.add('was-validated');
            }, false);

            newPasswordInput.addEventListener('input', function() {
                // Solo revalidar si el campo de confirmación ya tiene algo
                if (confirmPasswordInput.value !== '') {
                    if (newPasswordInput.value === confirmPasswordInput.value) {
                        confirmPasswordInput.setCustomValidity("");
                    } else {
                        confirmPasswordInput.setCustomValidity("Las contraseñas no coinciden.");
                    }
                }
            });

            confirmPasswordInput.addEventListener('input', function() {
                if (newPasswordInput.value === confirmPasswordInput.value) {
                    confirmPasswordInput.setCustomValidity("");
                } else {
                    confirmPasswordInput.setCustomValidity("Las contraseñas no coinciden.");
                }
            });
        }
    });
</script>
</body>
</html>