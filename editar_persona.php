<?php
ini_set('display_errors', 1); // Debugging: display PHP errors on screen
ini_set('display_startup_errors', 1); // Debugging: display PHP startup errors
error_reporting(E_ALL); // Debugging: report all types of errors

session_start();
include("config.php"); // Ensure 'config.php' contains the PDO connection.

// 1. Verify user is logged in
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}

$error = '';
$mensaje_exito = '';

// Variables for redirection after editing
$redirect_to = isset($_GET['redirect_to']) ? htmlspecialchars($_GET['redirect_to']) : '';
$proveedor_id = isset($_GET['proveedor_id']) ? (int)$_GET['proveedor_id'] : 0;
$new_persona_id_on_save = 0; // To capture the ID of the edited person and pass it back

// Validate that a valid persona_id is received from the URL for EDITING
if (!isset($_GET['persona_id']) || !filter_var($_GET['persona_id'], FILTER_VALIDATE_INT)) {
    // If a valid ID is not received, redirect to a management page or person list.
    // This is important to prevent accessing editar_persona.php without an ID.
    header("Location: gestion_catalogo_proveedores.php?error=" . urlencode("ID de persona no proporcionado o inválido para edición.")); // Adjust this redirection
    exit();
}

$persona_id = (int)$_GET['persona_id']; // ID of the person to be edited

// Initialize form variables with default values (empty)
// They will be overwritten with DB data if the person is found.
$nombre = '';
$apellido_paterno = '';
$apellido_materno = '';
$fecha_nacimiento = '';
$sexo = ''; // For the select field, initially empty
$email = '';
$direccion = '';
$telefono = '';

// 2. Get existing person data to pre-fill the form
try {
    $stmtPersona = $pdo->prepare("SELECT persona_id, nombre, apellido_paterno, apellido_materno, fecha_nacimiento, sexo, email, direccion, telefono FROM persona WHERE persona_id = :id");
    $stmtPersona->execute([':id' => $persona_id]);
    $persona = $stmtPersona->fetch(PDO::FETCH_ASSOC);

    if (!$persona) {
        // If the person is not found, redirect or show an error
        header("Location: gestion_catalogo_proveedores.php?error=" . urlencode("La persona solicitada para edición no fue encontrada.")); // Adjust redirection
        exit();
    }

    // Load person data into variables to pre-fill the form
    $nombre = $persona['nombre'];
    $apellido_paterno = $persona['apellido_paterno'];
    $apellido_materno = $persona['apellido_materno'];
    $fecha_nacimiento = $persona['fecha_nacimiento'];
    $sexo = $persona['sexo']; // Get sex from DB
    $email = $persona['email'];
    $direccion = $persona['direccion'];
    $telefono = $persona['telefono'];

} catch (PDOException $e) {
    $error = "Error al cargar datos de la persona: " . $e->getMessage();
    // Consider logging the full error for debugging: error_log($e->getMessage());
}


// 3. Process form submission (when "Save Changes" is clicked)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect and sanitize form data (POST)
    $nombre = trim($_POST['nombre'] ?? '');
    $apellido_paterno = trim($_POST['apellido_paterno'] ?? '');
    $apellido_materno = trim($_POST['apellido_materno'] ?? '');
    $fecha_nacimiento = trim($_POST['fecha_nacimiento'] ?? '');
    // NOTE: 'sexo' is NOT collected from POST as it's read-only
    $email = trim($_POST['email'] ?? '');
    $direccion = trim($_POST['direccion'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');

    // Validations
    // 'sexo' is not included in validation as it's not modifiable by user
    if (empty($nombre) || empty($apellido_paterno) || empty($fecha_nacimiento) || empty($email) || empty($direccion) || empty($telefono)) {
        $error = "Todos los campos obligatorios deben ser completados.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "El formato del correo electrónico no es válido.";
    } else {
        try {
            // Prepare and execute the UPDATE query
            // 'sexo' is excluded from the UPDATE query as it should not be modified
            $sql = "UPDATE persona
                    SET nombre = :nombre,
                        apellido_paterno = :apellido_paterno,
                        apellido_materno = :apellido_materno,
                        fecha_nacimiento = :fecha_nacimiento,
                        email = :email,
                        direccion = :direccion,
                        telefono = :telefono
                    WHERE persona_id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':nombre' => $nombre,
                ':apellido_paterno' => $apellido_paterno,
                ':apellido_materno' => $apellido_materno,
                ':fecha_nacimiento' => $fecha_nacimiento,
                ':email' => $email,
                ':direccion' => $direccion,
                ':telefono' => $telefono,
                ':id' => $persona_id // Ensure the correct person is updated
            ]);

            $new_persona_id_on_save = $persona_id; // The ID of the edited person is the same

            // Redirect after successful update
            $mensaje_exito = "Persona actualizada con éxito.";

            if ($redirect_to === 'editar_proveedor.php' && $proveedor_id > 0) {
                // If coming from editar_proveedor.php, return there with the original supplier ID and the updated person ID
                header("Location: editar_proveedor.php?id=" . $proveedor_id . "&persona_id=" . $new_persona_id_on_save . "&mensaje=" . urlencode($mensaje_exito));
            } elseif ($redirect_to === 'nuevo_proveedor.php') {
                // If coming from nuevo_proveedor.php, return there with the updated person ID
                header("Location: nuevo_proveedor.php?persona_id=" . $new_persona_id_on_save . "&mensaje=" . urlencode($mensaje_exito));
            } else {
                // Default redirection if no specific or invalid destination
                header("Location: gestion_catalogo_proveedores.php?status=persona_edited"); // Or to a person listing page
            }
            exit();

        } catch (PDOException $e) {
            $error = "Error al actualizar la persona: " . $e->getMessage();
            // Consider logging the full error for debugging: error_log($e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Persona</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/estilos.css?v=<?= time(); ?>">
    <style>
        body {
            background-color: #f8f9fa; /* Light background */
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .container {
            max-width: 800px;
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
        body.dark-mode .form-control, body.dark-mode .form-select, body.dark-mode .form-control-plaintext {
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
    <h3 class="main-title">Editar Persona #<?= htmlspecialchars($persona_id) ?></h3>

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
            <input type="text" id="sexo" class="form-control-plaintext" value="<?php
                if ($sexo === 'M') {
                    echo 'Masculino';
                } elseif ($sexo === 'F') {
                    echo 'Femenino';
                } else {
                    echo 'Otro';
                }
            ?>" readonly>
            <input type="hidden" name="sexo" value="<?= htmlspecialchars($sexo) ?>">
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
            <?php
            // Logic for the "Cancel" button
            $cancel_url = "gestion_catalogo_proveedores.php"; // Default fallback URL
            if ($redirect_to === 'editar_proveedor.php' && $proveedor_id > 0) {
                $cancel_url = "editar_proveedor.php?id=" . htmlspecialchars($proveedor_id);
            } elseif ($redirect_to === 'nuevo_proveedor.php') {
                $cancel_url = "nuevo_proveedor.php";
            }
            ?>
            <a href="<?= $cancel_url ?>" class="btn btn-secondary">Cancelar</a>
            <button type="submit" class="btn btn-primary">Guardar Cambios</button>
        </div>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', (event) => {
        const body = document.body;
        if (localStorage.getItem('darkMode') === 'enabled') {
            body.classList.add('dark-mode');
        }
    });
</script>
</body>
</html>