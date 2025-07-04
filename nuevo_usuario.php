<?php
session_start();
include("config.php"); // Asegúrate de que 'config.php' contenga la conexión PDO.

if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}

// Obtener los roles de la tabla 'rol' para el select de empleados
$roles = [];
try {
    $stmt = $pdo->query("SELECT rol_id, nombre FROM rol ORDER BY nombre");
    $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error al cargar roles: " . $e->getMessage());
    $error_message = "Error al cargar los roles. Por favor, intente de nuevo.";
}

$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Datos de la persona
    $nombre = trim($_POST['nombre'] ?? '');
    $apellido_paterno = trim($_POST['apellido_paterno'] ?? '');
    $apellido_materno = trim($_POST['apellido_materno'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $direccion = trim($_POST['direccion'] ?? '');
    $fecha_nacimiento = trim($_POST['fecha_nacimiento'] ?? '');
    $sexo = trim($_POST['sexo'] ?? '');

    // Datos específicos de Usuario Empleado
    $usuario_login = trim($_POST['usuario_login'] ?? '');
    $clave = trim($_POST['clave'] ?? '');
    $confirm_clave = trim($_POST['confirm_clave'] ?? '');
    $rol_id = $_POST['rol_id'] ?? '';
    $estado = isset($_POST['estado']) ? (bool)$_POST['estado'] : true; // Default activo

    // Validaciones
    if (empty($nombre) || empty($apellido_paterno) || empty($email) || empty($telefono) || empty($direccion) || empty($fecha_nacimiento) || empty($sexo) ||
        empty($usuario_login) || empty($clave) || empty($confirm_clave) || empty($rol_id)) {
        $error_message = "Todos los campos obligatorios deben ser completados.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "El formato del email no es válido.";
    } elseif ($clave !== $confirm_clave) {
        $error_message = "Las contraseñas no coinciden.";
    } else {
        try {
            // Iniciar transacción
            $pdo->beginTransaction();

            // 1. Insertar en la tabla persona
            $sql_persona = "INSERT INTO persona (nombre, apellido_paterno, apellido_materno, fecha_nacimiento, sexo, email, direccion, telefono) VALUES (:nombre, :apellido_paterno, :apellido_materno, :fecha_nacimiento, :sexo, :email, :direccion, :telefono)";
            $stmt_persona = $pdo->prepare($sql_persona);
            $stmt_persona->execute([
                ':nombre' => $nombre,
                ':apellido_paterno' => $apellido_paterno,
                ':apellido_materno' => $apellido_materno,
                ':fecha_nacimiento' => $fecha_nacimiento,
                ':sexo' => $sexo,
                ':email' => $email,
                ':direccion' => $direccion,
                ':telefono' => $telefono
            ]);
            $persona_id = $pdo->lastInsertId(); // Obtener el ID de la persona recién insertada

            // Hashear la clave (¡ADVERTENCIA: md5 no es seguro para contraseñas en producción!)
            // Para producción, usa: password_hash($clave, PASSWORD_DEFAULT);
            $hashed_clave = md5($clave);

            // 2. Insertar en usuario_empleado
            $sql_empleado = "INSERT INTO usuario_empleado (usuario, clave, estado, persona_id, rol_id) VALUES (:usuario, :clave, :estado, :persona_id, :rol_id)";
            $stmt_empleado = $pdo->prepare($sql_empleado);
            $stmt_empleado->execute([
                ':usuario' => $usuario_login,
                ':clave' => $hashed_clave,
                ':estado' => $estado,
                ':persona_id' => $persona_id,
                ':rol_id' => $rol_id
            ]);

            $pdo->commit(); // Confirmar la transacción
            $success_message = "Empleado agregado correctamente.";
            // Redirigir a la página de gestión de empleados después de un éxito
            header("Location: gestion_empleados.php?agregado=ok");
            exit();

        } catch (PDOException $e) {
            $pdo->rollBack(); // Revertir la transacción en caso de error
            // Manejo de errores específicos de base de datos
            if ($e->getCode() === '23505') { // Código de error para violación de unique constraint (ej. usuario o email ya existen)
                $error_message = "El nombre de usuario o el email ya existen. Por favor, elija otros.";
            } else {
                $error_message = "Error de base de datos al agregar empleado: " . $e->getMessage();
            }
        } catch (Exception $e) {
            $pdo->rollBack(); // Revertir la transacción en caso de error de lógica
            $error_message = "Error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registrar Nuevo Empleado</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/estilos.css?v=<?= time(); ?>">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <div class="col-md-12 content">
            <h3 class="main-title text-center my-4">Registrar Nuevo Empleado</h3>

            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($success_message) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
                </div>
            <?php endif; ?>
            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($error_message) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
                </div>
            <?php endif; ?>

            <div class="card p-4 mx-auto" style="max-width: 800px;">
                <form method="POST" class="needs-validation" novalidate>
                    <h4>Datos de la Persona</h4>
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label for="nombre" class="form-label">Nombre:</label>
                            <input type="text" class="form-control" id="nombre" name="nombre" required value="<?= htmlspecialchars($_POST['nombre'] ?? '') ?>">
                            <div class="invalid-feedback">Por favor, ingrese el nombre.</div>
                        </div>
                        <div class="col-md-4">
                            <label for="apellido_paterno" class="form-label">Apellido Paterno:</label>
                            <input type="text" class="form-control" id="apellido_paterno" name="apellido_paterno" required value="<?= htmlspecialchars($_POST['apellido_paterno'] ?? '') ?>">
                            <div class="invalid-feedback">Por favor, ingrese el apellido paterno.</div>
                        </div>
                        <div class="col-md-4">
                            <label for="apellido_materno" class="form-label">Apellido Materno:</label>
                            <input type="text" class="form-control" id="apellido_materno" name="apellido_materno" value="<?= htmlspecialchars($_POST['apellido_materno'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="email" class="form-label">Email:</label>
                            <input type="email" class="form-control" id="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                            <div class="invalid-feedback">Por favor, ingrese un email válido.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="telefono" class="form-label">Teléfono:</label>
                            <input type="text" class="form-control" id="telefono" name="telefono" required value="<?= htmlspecialchars($_POST['telefono'] ?? '') ?>">
                            <div class="invalid-feedback">Por favor, ingrese el teléfono.</div>
                        </div>
                        <div class="col-md-12">
                            <label for="direccion" class="form-label">Dirección:</label>
                            <input type="text" class="form-control" id="direccion" name="direccion" required value="<?= htmlspecialchars($_POST['direccion'] ?? '') ?>">
                            <div class="invalid-feedback">Por favor, ingrese la dirección.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="fecha_nacimiento" class="form-label">Fecha de Nacimiento:</label>
                            <input type="date" class="form-control" id="fecha_nacimiento" name="fecha_nacimiento" required value="<?= htmlspecialchars($_POST['fecha_nacimiento'] ?? '') ?>">
                            <div class="invalid-feedback">Por favor, ingrese la fecha de nacimiento.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="sexo" class="form-label">Sexo:</label>
                            <select class="form-select" id="sexo" name="sexo" required>
                                <option value="">Seleccione...</option>
                                <option value="M" <?= (isset($_POST['sexo']) && $_POST['sexo'] == 'M') ? 'selected' : '' ?>>Masculino</option>
                                <option value="F" <?= (isset($_POST['sexo']) && $_POST['sexo'] == 'F') ? 'selected' : '' ?>>Femenino</option>
                            </select>
                            <div class="invalid-feedback">Por favor, seleccione el sexo.</div>
                        </div>
                    </div>

                    <h4>Datos de Acceso del Empleado</h4>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="usuario_login" class="form-label">Nombre de Usuario (Login):</label>
                            <input type="text" class="form-control" id="usuario_login" name="usuario_login" required value="<?= htmlspecialchars($_POST['usuario_login'] ?? '') ?>">
                            <div class="invalid-feedback">Por favor, ingrese un nombre de usuario.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="rol_id" class="form-label">Rol:</label>
                            <select class="form-select" id="rol_id" name="rol_id" required>
                                <option value="">Seleccione un rol...</option>
                                <?php foreach ($roles as $rol): ?>
                                    <option value="<?= $rol['rol_id'] ?>" <?= (isset($_POST['rol_id']) && $_POST['rol_id'] == $rol['rol_id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars(ucfirst($rol['nombre'])) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Por favor, seleccione un rol para el empleado.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="clave" class="form-label">Contraseña:</label>
                            <input type="password" class="form-control" id="clave" name="clave" required>
                            <div class="invalid-feedback">Por favor, ingrese la contraseña.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="confirm_clave" class="form-label">Confirmar Contraseña:</label>
                            <input type="password" class="form-control" id="confirm_clave" name="confirm_clave" required>
                            <div class="invalid-feedback">Las contraseñas no coinciden.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="estado" class="form-label">Estado:</label>
                            <select class="form-select" id="estado" name="estado">
                                <option value="1" <?= (isset($_POST['estado']) && $_POST['estado'] == '1') ? 'selected' : '' ?>>Activo</option>
                                <option value="0" <?= (isset($_POST['estado']) && $_POST['estado'] == '0') ? 'selected' : '' ?>>Inactivo</option>
                            </select>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2 mt-4">
                        <button type="submit" class="btn btn-success"><i class="bi bi-person-check-fill"></i> Registrar Empleado</button>
                        <a href="gestion_empleados.php" class="btn btn-secondary"><i class="bi bi-x-circle-fill"></i> Cancelar</a>
                    </div>
                </form>
            </div></div></div></div><script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // JavaScript para validación de Bootstrap y confirmación de contraseña
    document.addEventListener('DOMContentLoaded', function() {
        const claveInput = document.getElementById('clave');
        const confirmClaveInput = document.getElementById('confirm_clave');
        const rolIdSelect = document.getElementById('rol_id');

        (function () {
            'use strict'
            var forms = document.querySelectorAll('.needs-validation')
            Array.prototype.slice.call(forms)
                .forEach(function (form) {
                    form.addEventListener('submit', function (event) {
                        // Validar que las contraseñas coincidan
                        if (claveInput.value !== confirmClaveInput.value) {
                            confirmClaveInput.setCustomValidity("Las contraseñas no coinciden.");
                        } else {
                            confirmClaveInput.setCustomValidity(""); // Resetear la validación si coinciden
                        }

                        // Validar que el rol esté seleccionado (ya es requerido en HTML, esto es una capa extra)
                        if (rolIdSelect.value === '') {
                             rolIdSelect.setCustomValidity("Por favor, seleccione un rol para el empleado.");
                        } else {
                             rolIdSelect.setCustomValidity("");
                        }

                        if (!form.checkValidity()) {
                            event.preventDefault()
                            event.stopPropagation()
                        }
                        form.classList.add('was-validated')
                    }, false)
                })

            // Resetear validación de confirm_clave al cambiar los campos de contraseña
            claveInput.addEventListener('input', function() {
                if (claveInput.value === confirmClaveInput.value) {
                    confirmClaveInput.setCustomValidity("");
                } else {
                    confirmClaveInput.setCustomValidity("Las contraseñas no coinciden.");
                }
            });
            confirmClaveInput.addEventListener('input', function() {
                if (claveInput.value === claveInput.value) { // Comprobar contra claveInput.value
                    confirmClaveInput.setCustomValidity("");
                } else {
                    confirmClaveInput.setCustomValidity("Las contraseñas no coinciden.");
                }
            });
        })();
    });
</script>
</body>
</html>