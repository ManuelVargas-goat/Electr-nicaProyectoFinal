<?php
session_start();
include("config.php"); // Asegúrate de que 'config.php' contenga la conexión PDO.

// 1. Verificar si el usuario está logueado
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}

$error = '';
$mensaje_exito = '';

// Inicializar variables para mantener los valores en el formulario si hay un error
$nombre = '';
$apellido_paterno = '';
$apellido_materno = '';
$fecha_nacimiento = '';
$sexo = ''; // Para el campo select
$email = '';
$direccion = '';
$telefono = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 2. Recoger y sanear datos del formulario
    $nombre = trim($_POST['nombre'] ?? '');
    $apellido_paterno = trim($_POST['apellido_paterno'] ?? '');
    $apellido_materno = trim($_POST['apellido_materno'] ?? '');
    $fecha_nacimiento = trim($_POST['fecha_nacimiento'] ?? '');
    $sexo = trim($_POST['sexo'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $direccion = trim($_POST['direccion'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');

    // 3. Validar datos
    if (empty($nombre) || empty($apellido_paterno) || empty($fecha_nacimiento) || empty($sexo) || empty($email) || empty($direccion) || empty($telefono)) {
        $error = "Todos los campos obligatorios deben ser completados.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "El formato del correo electrónico no es válido.";
    } else {
        try {
            // 4. Preparar y ejecutar la consulta INSERT
            $stmt = $pdo->prepare("INSERT INTO persona (nombre, apellido_paterno, apellido_materno, fecha_nacimiento, sexo, email, direccion, telefono) 
                                 VALUES (:nombre, :apellido_paterno, :apellido_materno, :fecha_nacimiento, :sexo, :email, :direccion, :telefono)");
            $stmt->execute([
                ':nombre' => $nombre,
                ':apellido_paterno' => $apellido_paterno,
                ':apellido_materno' => $apellido_materno,
                ':fecha_nacimiento' => $fecha_nacimiento,
                ':sexo' => $sexo,
                ':email' => $email,
                ':direccion' => $direccion,
                ':telefono' => $telefono
            ]);

            // OBTENER EL ID DE LA PERSONA RECIÉN CREADA
            $new_persona_id = $pdo->lastInsertId();

            // Redireccionar a nuevo_proveedor.php pasando el ID de la nueva persona
            header("Location: nuevo_proveedor.php?persona_id=" . $new_persona_id . "&mensaje=" . urlencode("Persona registrada con éxito.") . "&tipo=success");
            exit();

        } catch (PDOException $e) {
            $error = "Error al registrar la persona: " . $e->getMessage();
            // Considera loggear el error completo para depuración: error_log($e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registrar Nueva Persona</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/estilos.css?v=<?= time(); ?>">
    <style>
        body {
            background-color: #f8f9fa; /* Fondo claro */
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh; /* Ocupa al menos el 100% del alto de la ventana */
        }
        .container {
            max-width: 800px; /* ANCHO MODIFICADO AQUÍ */
            background-color: #ffffff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        .main-title {
            text-align: center;
            margin-bottom: 30px;
            color: #343a40;
        }
        /* Estilos para tema oscuro si es necesario */
        body.dark-mode {
            background-color: #343a40;
            color: #f8f9fa;
        }
        body.dark-mode .container {
            background-color: #545b62;
            box-shadow: 0 4px 10px rgba(0,0,0,0.3);
        }
        body.dark-mode .main-title {
            color: #f8f9fa;
        }
        body.dark-mode .form-control, body.dark-mode .form-select {
            background-color: #6c757d;
            color: #f8f9fa;
            border-color: #495057;
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
<div class="container">
    <h3 class="main-title">Registrar Nueva Persona</h3>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if (!empty($mensaje_exito)): ?>
        <div class="alert alert-success"><?= htmlspecialchars($mensaje_exito) ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="row mb-3">
            <div class="col-md-6">
                <label for="nombre" class="form-label">Nombre</label>
                <input type="text" name="nombre" id="nombre" class="form-control" value="<?= htmlspecialchars($nombre) ?>" required>
            </div>
            <div class="col-md-6">
                <label for="apellido_paterno" class="form-label">Apellido Paterno</label>
                <input type="text" name="apellido_paterno" id="apellido_paterno" class="form-control" value="<?= htmlspecialchars($apellido_paterno) ?>" required>
            </div>
        </div>

        <div class="row mb-3">
            <div class="col-md-6">
                <label for="apellido_materno" class="form-label">Apellido Materno</label>
                <input type="text" name="apellido_materno" id="apellido_materno" class="form-control" value="<?= htmlspecialchars($apellido_materno) ?>">
            </div>
            <div class="col-md-6">
                <label for="fecha_nacimiento" class="form-label">Fecha de Nacimiento</label>
                <input type="date" name="fecha_nacimiento" id="fecha_nacimiento" class="form-control" value="<?= htmlspecialchars($fecha_nacimiento) ?>" required>
            </div>
        </div>

        <div class="mb-3">
            <label for="sexo" class="form-label">Sexo</label>
            <select name="sexo" id="sexo" class="form-select" required>
                <option value="">Seleccione</option>
                <option value="M" <?= ($sexo == 'M') ? 'selected' : '' ?>>Masculino</option>
                <option value="F" <?= ($sexo == 'F') ? 'selected' : '' ?>>Femenino</option>
            </select>
        </div>

        <div class="mb-3">
            <label for="email" class="form-label">Email</label>
            <input type="email" name="email" id="email" class="form-control" value="<?= htmlspecialchars($email) ?>" required>
        </div>

        <div class="mb-3">
            <label for="direccion" class="form-label">Dirección</label>
            <textarea name="direccion" id="direccion" class="form-control" rows="2" required><?= htmlspecialchars($direccion) ?></textarea>
        </div>

        <div class="mb-3">
            <label for="telefono" class="form-label">Teléfono</label>
            <input type="text" name="telefono" id="telefono" class="form-control" value="<?= htmlspecialchars($telefono) ?>" required>
        </div>

        <div class="d-flex justify-content-between mt-4">
            <a href="nuevo_proveedor.php" class="btn btn-secondary">Cancelar</a>
            <button type="submit" class="btn btn-primary">Registrar Persona</button>
        </div>
    </form>
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