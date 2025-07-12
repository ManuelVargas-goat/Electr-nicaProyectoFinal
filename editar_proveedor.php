<?php
session_start();
include("config.php"); // Asegúrate de que 'config.php' contenga la conexión PDO.

if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}

// Validar que se reciba un ID de proveedor válido
if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    header("Location: gestion_catalogo_proveedores.php");
    exit();
}

$proveedor_id = (int)$_GET['id'];
$error = '';
$mensaje_exito = '';

// Obtener datos del proveedor existente
try {
    $stmtProveedor = $pdo->prepare("SELECT proveedor_id, nombre_empresa, persona_id, pais, ciudad, direccion, codigo_postal FROM proveedor WHERE proveedor_id = :id");
    $stmtProveedor->execute([':id' => $proveedor_id]);
    $proveedor = $stmtProveedor->fetch(PDO::FETCH_ASSOC);

    if (!$proveedor) {
        die("Proveedor no encontrado.");
    }
} catch (PDOException $e) {
    die("Error al cargar datos del proveedor: " . $e->getMessage());
}

// Obtener personas para asociar con proveedor (incluyendo apellido_materno para el desplegable)
$personas = [];
try {
    $stmtPersonas = $pdo->query("SELECT persona_id, nombre, apellido_paterno, apellido_materno FROM persona ORDER BY nombre");
    $personas = $stmtPersonas->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error al cargar personas: " . $e->getMessage();
}

// Inicializar variables con los datos del proveedor o con valores de POST si se envió el formulario
$nombre_empresa = $proveedor['nombre_empresa'];
$persona_id_selected = $proveedor['persona_id']; // El ID de la persona ya asociada al proveedor
$pais_selected = $proveedor['pais'];
$ciudad_selected = $proveedor['ciudad'];
$direccion = $proveedor['direccion'];
$codigo_postal = $proveedor['codigo_postal'];

// Verificar si se pasó un persona_id desde la URL (después de seleccionar/crear/editar una persona)
if (isset($_GET['persona_id']) && filter_var($_GET['persona_id'], FILTER_VALIDATE_INT)) {
    $new_persona_id_from_url = (int)$_GET['persona_id'];
    $persona_id_selected = $new_persona_id_from_url;
}

// También verificar si hay un mensaje de éxito desde la redirección
if (isset($_GET['mensaje'])) {
    $mensaje_exito = htmlspecialchars($_GET['mensaje']);
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre_empresa = trim($_POST['nombre_empresa'] ?? '');
    $persona_id_selected = (int)($_POST['persona_id'] ?? 0); // Captura el ID de la persona seleccionada
    $pais_selected = trim($_POST['pais'] ?? '');
    $ciudad_selected = trim($_POST['ciudad'] ?? '');
    $direccion = trim($_POST['direccion'] ?? '');
    $codigo_postal = trim($_POST['codigo_postal'] ?? '');

    if (empty($nombre_empresa)) {
        $error = "El nombre de la empresa es obligatorio.";
    } elseif ($persona_id_selected <= 0) {
        $error = "Debe seleccionar una persona representante válida.";
    } elseif (empty($pais_selected)) {
        $error = "Debe seleccionar un país.";
    } elseif (empty($ciudad_selected)) {
        $error = "Debe seleccionar una ciudad.";
    } elseif (empty($direccion)) {
        $error = "La dirección es obligatoria.";
    } else {
        try {
            $sql = "UPDATE proveedor
                    SET nombre_empresa = :nombre_empresa,
                        persona_id = :persona_id,
                        pais = :pais,
                        ciudad = :ciudad,
                        direccion = :direccion,
                        codigo_postal = :codigo_postal
                    WHERE proveedor_id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':nombre_empresa' => $nombre_empresa,
                ':persona_id' => $persona_id_selected,
                ':pais' => $pais_selected,
                ':ciudad' => $ciudad_selected,
                ':direccion' => $direccion,
                ':codigo_postal' => $codigo_postal,
                ':id' => $proveedor_id
            ]);
            header("Location: gestion_catalogo_proveedores.php?status=success_edit");
            exit();
        } catch (PDOException $e) {
            $error = "Error al actualizar el proveedor: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Proveedor</title>
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
    <h3 class="main-title">Editar Proveedor #<?= htmlspecialchars($proveedor_id) ?></h3>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if (!empty($mensaje_exito)): ?>
        <div class="alert alert-success"><?= $mensaje_exito ?></div>
    <?php endif; ?>

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
                        </a>
                    <a href="nueva_persona.php?redirect_to=editar_proveedor.php&proveedor_id=<?= htmlspecialchars($proveedor_id) ?>" class="btn btn-outline-success flex-fill" title="Agregar nueva persona">
                        + Nueva Persona
                    </a>
                    <a href="#" id="btnEliminarPersona" class="btn btn-outline-danger flex-fill" title="Eliminar persona seleccionada">
                        Eliminar Persona
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
            <button type="submit" class="btn btn-primary">Guardar Cambios</button>
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
    const btnEliminarPersona = document.getElementById('btnEliminarPersona'); // Nuevo botón de eliminar
    const proveedorId = <?= json_encode($proveedor_id); ?>; // Pass the supplier ID to JavaScript

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
        } else if (paisSeleccionado !== "") { // Si hay un país seleccionado pero no en la lista predefinida
            const opcion = document.createElement('option');
            opcion.value = "Otra";
            opcion.textContent = "Otra";
            ciudadSelect.appendChild(opcion);
        }
        
        // Maintain the selected city if the country hasn't changed or is the same
        const ciudadSelectedFromPhp = '<?= $ciudad_selected ?>';
        const paisSelectedFromPhp = '<?= $pais_selected ?>';

        if (paisSeleccionado === paisSelectedFromPhp) {
            ciudadSelect.value = ciudadSelectedFromPhp;
        } else {
            // Si el país ha cambiado, resetear la ciudad seleccionada para evitar que se quede un valor obsoleto
            ciudadSelect.value = ''; 
        }
    }

    function actualizarBotonesPersona() {
        const personaIdSeleccionada = personaSelect.value;

        // Lógica para el botón "Editar Persona"
        if (personaIdSeleccionada && personaIdSeleccionada !== "") {
            btnEditarPersona.href = 'editar_persona.php?persona_id=' + personaIdSeleccionada + '&redirect_to=editar_proveedor.php&proveedor_id=' + proveedorId;
            btnEditarPersona.textContent = 'Editar Persona';
            btnEditarPersona.classList.remove('disabled');
        } else {
            btnEditarPersona.href = 'seleccionar_persona.php?redirect_to=editar_proveedor.php&proveedor_id=' + proveedorId;
            btnEditarPersona.textContent = 'Seleccionar Persona';
            // btnEditarPersona.classList.add('disabled'); // Podrías deshabilitarlo si no hay persona seleccionada
        }

        // Lógica para el botón "Eliminar Persona"
        if (personaIdSeleccionada && personaIdSeleccionada !== "") {
            btnEliminarPersona.href = '#'; // El enlace real se manejará en el evento click
            btnEliminarPersona.classList.remove('disabled');
            btnEliminarPersona.onclick = function() {
                if (confirm('¿Está seguro de que desea eliminar a esta persona? Esto la desasociará de este proveedor y la eliminará permanentemente del sistema si no está asociada a otros proveedores.')) {
                    // Redirigir a un script de eliminación
                    window.location.href = 'eliminar_persona.php?persona_id=' + personaIdSeleccionada + '&redirect_to=editar_proveedor.php&proveedor_id=' + proveedorId;
                }
                return false; // Evita la acción por defecto del enlace
            };
        } else {
            btnEliminarPersona.href = '#';
            btnEliminarPersona.classList.add('disabled'); // Deshabilita el botón si no hay persona seleccionada
            btnEliminarPersona.onclick = null; // Elimina el evento click
        }
    }

    // Event Listeners
    paisSelect.addEventListener('change', actualizarCiudades);
    personaSelect.addEventListener('change', actualizarBotonesPersona); // Actualiza los dos botones

    // Call functions on DOM load to set initial state
    document.addEventListener('DOMContentLoaded', function() {
        // Ensure cities are loaded correctly if a country is already selected
        if (paisSelect.value !== '') {
            actualizarCiudades();
        } else {
            // If no country is selected initially, ensure the city select is empty
            ciudadSelect.innerHTML = '<option value="">Seleccione un país primero</option>';
        }
        // Update the initial state of the "Edit Person" and "Delete Person" buttons
        actualizarBotonesPersona();

        // Dark mode logic
        const body = document.body;
        if (localStorage.getItem('darkMode') === 'enabled') {
            body.classList.add('dark-mode');
        }
    });
</script>
</body>
</html>