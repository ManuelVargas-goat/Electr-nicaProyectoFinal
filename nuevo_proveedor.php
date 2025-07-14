<?php
session_start(); // Inicia la sesión al principio
include("config.php"); // Asegúrate de que 'config.php' contenga la conexión PDO.

if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}

$formError = ''; // Variable para errores de validación en este formulario (anteriormente $error)

// Obtener personas para asociar con proveedor (incluyendo apellido_materno para el desplegable)
$personas = [];
try {
    $stmtPersonas = $pdo->query("SELECT persona_id, nombre, apellido_paterno, apellido_materno FROM persona ORDER BY nombre");
    $personas = $stmtPersonas->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Manejar error al cargar personas, pero permite que el formulario se muestre
    $formError = "Error al cargar la lista de personas: " . $e->getMessage();
}

// Inicializar variables para mantener los valores en el formulario si hay un error de POST
$nombre_empresa = '';
$persona_id_selected = ''; // Variable para mantener la persona seleccionada en caso de error o desde la URL
$pais_selected = '';
$ciudad_selected = '';
$direccion = '';
$codigo_postal = '';

// Verificar si se pasó un persona_id desde la URL (después de crear o editar una persona)
// Si viene de una redirección de 'nueva_persona.php' o 'editar_persona.php'
if (isset($_GET['persona_id']) && filter_var($_GET['persona_id'], FILTER_VALIDATE_INT)) {
    $persona_id_selected = (int)$_GET['persona_id'];
    // No necesitamos manejar $_GET['mensaje'] aquí directamente. Se espera que los mensajes se manejen con $_SESSION.
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre_empresa = trim($_POST['nombre_empresa'] ?? '');
    $persona_id_selected = (int)($_POST['persona_id'] ?? 0); // Captura el ID para repoblar el select
    $pais_selected = trim($_POST['pais'] ?? '');
    $ciudad_selected = trim($_POST['ciudad'] ?? '');
    $direccion = trim($_POST['direccion'] ?? '');
    $codigo_postal = trim($_POST['codigo_postal'] ?? '');

    // Validaciones
    if (empty($nombre_empresa)) {
        $formError = "El nombre de la empresa es obligatorio.";
    } elseif ($persona_id_selected <= 0) {
        $formError = "Debe seleccionar una persona representante válida.";
    } elseif (empty($pais_selected)) {
        $formError = "Debe seleccionar un país.";
    } elseif (empty($ciudad_selected)) {
        $formError = "Debe seleccionar una ciudad.";
    } elseif (empty($direccion)) {
        $formError = "La dirección es obligatoria.";
    } else {
        try {
            $sql = "INSERT INTO proveedor (nombre_empresa, persona_id, pais, ciudad, direccion, codigo_postal)
                    VALUES (:nombre_empresa, :persona_id, :pais, :ciudad, :direccion, :codigo_postal)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':nombre_empresa' => $nombre_empresa,
                ':persona_id' => $persona_id_selected,
                ':pais' => $pais_selected,
                ':ciudad' => $ciudad_selected,
                ':direccion' => $direccion,
                ':codigo_postal' => $codigo_postal
            ]);

            // Establecer mensaje de éxito en la sesión y redirigir
            $_SESSION['modal_message'] = "Proveedor registrado con éxito.";
            $_SESSION['modal_type'] = "success";
            header("Location: gestion_catalogo_proveedores.php"); // Redirige a la gestión de proveedores
            exit();
        } catch (PDOException $e) {
            // Error de base de datos
            error_log("Error al registrar el proveedor en DB: " . $e->getMessage());
            $formError = "Error al registrar el proveedor: " . $e->getMessage(); // Muestra el error en el formulario
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Nuevo Proveedor</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/estilos.css?v=<?= time(); ?>">
    <style>
        body {
            background-color: #f8f9fa; /* Fondo claro general */
        }
        .container {
            margin-top: 50px;
            margin-bottom: 50px;
        }
        .main-title {
            text-align: center;
            margin-bottom: 30px;
            color: #343a40;
        }
        .card {
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            border-radius: 8px;
        }
        /* Estilos para el tema oscuro (si los tienes definidos en estilos.css o en el body) */
        body.dark-mode {
            background-color: #343a40;
            color: #f8f9fa;
        }
        body.dark-mode .main-title {
            color: #f8f9fa;
        }
        body.dark-mode .card {
            background-color: #545b62;
            border-color: #6c757d;
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
<div class="container">
    <h3 class="main-title">Registrar Nuevo Proveedor</h3>

    <?php if (!empty($formError)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($formError) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php
    // **BLOQUE PARA MOSTRAR LA ALERTA DE BOOTSTRAP (si viene de una redirección)**
    // Esto es para mensajes que pudieron venir de 'nueva_persona.php' o 'editar_persona.php'
    $mensajeModal = '';
    $tipoMensajeModal = '';
    if (isset($_SESSION['modal_message']) && !empty($_SESSION['modal_message'])) {
        $mensajeModal = $_SESSION['modal_message'];
        $tipoMensajeModal = $_SESSION['modal_type'];
        // Limpiar las variables de sesión para que el mensaje no se muestre de nuevo al recargar la página
        unset($_SESSION['modal_message']);
        unset($_SESSION['modal_type']);
    }

    if (!empty($mensajeModal) && !empty($tipoMensajeModal)) {
        $alertClass = '';
        switch ($tipoMensajeModal) {
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
                $alertClass = 'alert-info';
        }
        echo '<div class="alert ' . $alertClass . ' alert-dismissible fade show mt-3" role="alert">';
        echo htmlspecialchars($mensajeModal);
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
        echo '</div>';
    }
    ?>

    <form method="POST" class="card p-4">
        <div class="mb-3">
            <label for="nombre_empresa" class="form-label">Nombre de Empresa</label>
            <input type="text" name="nombre_empresa" id="nombre_empresa" class="form-control" value="<?= htmlspecialchars($nombre_empresa) ?>" required>
        </div>

        <div class="mb-3">
            <label for="persona_id" class="form-label">Representante (Persona)</label>
            <div class="row g-2 align-items-end">
                <div class="col-md-7">
                    <select name="persona_id" id="persona_id" class="form-select" required>
                        <option value="">Seleccione una persona</option>
                        <?php foreach ($personas as $persona):
                            $nombre_completo = htmlspecialchars($persona['nombre'] . ' ' . $persona['apellido_paterno']);
                            if (!empty($persona['apellido_materno'])) {
                                $nombre_completo .= ' ' . htmlspecialchars($persona['apellido_materno']);
                            }
                        ?>
                            <option value="<?= htmlspecialchars($persona['persona_id']) ?>"
                                <?= ($persona_id_selected == $persona['persona_id']) ? 'selected' : '' ?>>
                                <?= $nombre_completo ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-5 d-flex gap-2">
                    <a href="#" id="btnEditarPersona" class="btn btn-outline-info flex-fill" title="Editar o seleccionar persona">
                        <?= ($persona_id_selected > 0) ? 'Editar Persona' : 'Seleccionar Persona' ?>
                    </a>
                    <a href="nueva_persona.php?redirect_to=nuevo_proveedor.php" class="btn btn-outline-success flex-fill" title="Agregar nueva persona">
                        + Nueva Persona
                    </a>
                </div>
            </div>
        </div>

        <div class="mb-3">
            <label for="pais" class="form-label">País</label>
            <select name="pais" id="pais" class="form-select" required>
                <option value="">Seleccione un país</option>
                <?php
                $paises = [
                    "Argentina", "Bolivia", "Brasil", "Chile", "Colombia", "Ecuador", "México",
                    "Panamá", "Paraguay", "Perú", "Uruguay", "Venezuela", "Estados Unidos",
                    "España", "Francia", "Italia", "Alemania"
                ];
                foreach ($paises as $p) {
                    echo "<option value=\"".htmlspecialchars($p)."\" ".($pais_selected == $p ? 'selected' : '').">".htmlspecialchars($p)."</option>";
                }
                ?>
            </select>
        </div>

        <div class="mb-3">
            <label for="ciudad" class="form-label">Ciudad</label>
            <select name="ciudad" id="ciudad" class="form-select" required>
                <option value="">Seleccione un país primero</option>
                <?php
                // Definir ciudadesPorPais en PHP para que esté disponible si el formulario se recarga con errores
                $ciudadesPorPais = [
                    "Perú" => ["Arequipa", "Lima", "Cusco", "Trujillo", "Piura"],
                    "Colombia" => ["Bogotá", "Medellín", "Cali", "Barranquilla"],
                    "Argentina" => ["Buenos Aires", "Córdoba", "Rosario", "Mendoza"],
                    "Chile" => ["Santiago", "Valparaíso", "Concepción"],
                    "México" => ["Ciudad de México", "Guadalajara", "Monterrey"],
                    "España" => ["Madrid", "Barcelona", "Valencia"],
                    "Estados Unidos" => ["New York", "Los Angeles", "Chicago"],
                    "Otro" => ["Otra"]
                ];
                if (!empty($pais_selected) && isset($ciudadesPorPais[$pais_selected])):
                    foreach ($ciudadesPorPais[$pais_selected] as $c): ?>
                        <option value="<?= htmlspecialchars($c) ?>" <?= ($ciudad_selected == $c ? 'selected' : '') ?>>
                            <?= htmlspecialchars($c) ?>
                        </option>
                    <?php endforeach;
                elseif (!empty($pais_selected) && !isset($ciudadesPorPais[$pais_selected])): ?>
                    <option value="Otra" selected>Otra</option>
                <?php endif; ?>
            </select>
        </div>

        <div class="mb-3">
            <label for="direccion" class="form-label">Dirección</label>
            <textarea name="direccion" id="direccion" class="form-control" rows="2" required><?= htmlspecialchars($direccion) ?></textarea>
        </div>

        <div class="mb-3">
            <label for="codigo_postal" class="form-label">Código Postal</label>
            <input type="text" name="codigo_postal" id="codigo_postal" class="form-control" value="<?= htmlspecialchars($codigo_postal) ?>">
        </div>

        <div class="d-flex justify-content-between">
            <a href="gestion_catalogo_proveedores.php" class="btn btn-secondary">Cancelar</a>
            <button type="submit" class="btn btn-primary">Registrar</button>
        </div>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const ciudadesPorPais = {
        "Perú": ["Arequipa", "Lima", "Cusco", "Trujillo", "Piura"],
        "Colombia": ["Bogotá", "Medellín", "Cali", "Barranquilla"],
        "Argentina": ["Buenos Aires", "Córdoba", "Rosario", "Mendoza"],
        "Chile": ["Santiago", "Valparaíso", "Concepción"],
        "México": ["Ciudad de México", "Guadalajara", "Monterrey"],
        "España": ["Madrid", "Barcelona", "Valencia"],
        "Estados Unidos": ["New York", "Los Angeles", "Chicago"],
        "Otro": ["Otra"]
    };

    const paisSelect = document.getElementById('pais');
    const ciudadSelect = document.getElementById('ciudad');
    const personaSelect = document.getElementById('persona_id');
    const btnEditarPersona = document.getElementById('btnEditarPersona');

    function actualizarCiudades() {
        const paisSeleccionado = paisSelect.value;
        ciudadSelect.innerHTML = '<option value="">Seleccione una ciudad</option>';

        if (ciudadesPorPais[paisSeleccionado]) {
            ciudadesPorPais[paisSeleccionado].forEach(ciudad => {
                const opcion = document.createElement('option');
                opcion.value = ciudad;
                opcion.textContent = ciudad;
                ciudadSelect.appendChild(opcion);
            });
        } else if (paisSeleccionado !== "") {
            const opcion = document.createElement('option');
            opcion.value = "Otra";
            opcion.textContent = "Otra";
            ciudadSelect.appendChild(opcion);
        }

        const ciudadSelectedFromPhp = '<?= $ciudad_selected ?>';
        const paisSelectedFromPhp = '<?= $pais_selected ?>';

        if (paisSeleccionado === paisSelectedFromPhp) {
            ciudadSelect.value = ciudadSelectedFromPhp;
        } else {
            ciudadSelect.value = '';
        }
    }

    function actualizarBotonEditarPersona() {
        const personaIdSeleccionada = personaSelect.value;
        const redirectToUrl = 'nuevo_proveedor.php'; // Siempre redirigir a esta página

        if (personaIdSeleccionada && personaIdSeleccionada !== "") {
            btnEditarPersona.href = `editar_persona.php?persona_id=${personaIdSeleccionada}&redirect_to=${redirectToUrl}`;
            btnEditarPersona.textContent = 'Editar Persona';
            btnEditarPersona.classList.remove('disabled');
        } else {
            btnEditarPersona.href = `seleccionar_persona.php?redirect_to=${redirectToUrl}`;
            btnEditarPersona.textContent = 'Seleccionar Persona';
            btnEditarPersona.classList.remove('disabled');
        }
    }

    // Event Listeners
    paisSelect.addEventListener('change', actualizarCiudades);
    personaSelect.addEventListener('change', actualizarBotonEditarPersona);

    // Call functions on DOM load to set initial state
    document.addEventListener('DOMContentLoaded', function() {
        if (paisSelect.value !== '') {
            actualizarCiudades();
        } else {
            // Asegura que al cargar la página si no hay país seleccionado, el select de ciudad muestre el mensaje correcto
            ciudadSelect.innerHTML = '<option value="">Seleccione un país primero</option>';
        }
        actualizarBotonEditarPersona(); // Llama a la función para establecer el estado inicial del botón
                                      // Esto asegura que el texto se muestre correctamente al cargar la página
                                      // si ya hay una persona seleccionada por PHP.

        // Dark mode logic
        const body = document.body;
        if (localStorage.getItem('darkMode') === 'enabled') {
            body.classList.add('dark-mode');
        }
    });
</script>
</body>
</html>