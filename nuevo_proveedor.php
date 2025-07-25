<?php
session_start(); // Inicia la sesión al principio
include("config.php"); // Asegúrate de que 'config.php' contenga la conexión PDO.

if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}

$formError = '';

// --- Inicialización de variables del formulario ---
// Estas variables se inicializan a vacío, y se llenarán con los datos de POST si el formulario es enviado,
// o serán rellenadas por JavaScript desde sessionStorage si se regresa de una redirección.
$nombre_empresa = '';
$persona_id_selected = '';
$pais_selected = '';
$ciudad_selected = '';
$direccion = '';
$codigo_postal = '';
$categorias_seleccionadas_post = []; // Para repoblar los checkboxes en caso de error de POST

// Obtener personas para asociar con proveedor (incluyendo apellido_materno para el desplegable)
$personas = [];
try {
    $stmtPersonas = $pdo->query("SELECT persona_id, nombre, apellido_paterno, apellido_materno FROM persona ORDER BY nombre");
    $personas = $stmtPersonas->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $formError = "Error al cargar la lista de personas: " . $e->getMessage();
}

// Obtener todas las categorías para el campo de selección múltiple con checkboxes
$todas_las_categorias = [];
try {
    $stmtCategorias = $pdo->query("SELECT categoria_id, nombre FROM categoria ORDER BY nombre");
    $todas_las_categorias = $stmtCategorias->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $formError = "Error al cargar la lista de categorías: " . $e->getMessage();
}

// --- Procesamiento del formulario POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre_empresa = trim($_POST['nombre_empresa'] ?? '');
    $persona_id_selected = (int)($_POST['persona_id'] ?? 0);
    $pais_selected = trim($_POST['pais'] ?? '');
    $ciudad_selected = trim($_POST['ciudad'] ?? '');
    $direccion = trim($_POST['direccion'] ?? '');
    $codigo_postal = trim($_POST['codigo_postal'] ?? '');
    
    $categorias_suministradas = $_POST['categorias_suministradas'] ?? [];
    $categorias_suministradas = array_map('intval', $categorias_suministradas);
    
    // Guardar las categorías seleccionadas para repoblar el formulario en caso de error de validación
    $categorias_seleccionadas_post = $categorias_suministradas;

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
            $pdo->beginTransaction();

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

            $proveedor_id = $pdo->lastInsertId();

            if (!empty($categorias_suministradas)) {
                $sql_insert_pc = "INSERT INTO proveedor_categoria (proveedor_id, categoria_id) VALUES (:proveedor_id, :categoria_id)";
                $stmt_insert_pc = $pdo->prepare($sql_insert_pc);
                foreach ($categorias_suministradas as $cat_id) {
                    $stmt_insert_pc->execute([
                        ':proveedor_id' => $proveedor_id,
                        ':categoria_id' => $cat_id
                    ]);
                }
            }

            $pdo->commit();

            $_SESSION['modal_message'] = "Proveedor registrado con éxito.";
            $_SESSION['modal_type'] = "success";
            header("Location: gestion_catalogo_proveedores.php");
            exit();
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Error al registrar el proveedor en DB: " . $e->getMessage());
            $formError = "Error al registrar el proveedor: " . $e->getMessage();
        }
    }
}

// --- Verificar si se pasó un persona_id desde la URL (tiene prioridad sobre otros métodos si viene de una edición/selección) ---
// Esto asegura que la persona seleccionada se muestre en el dropdown si se redirige con un ID específico.
if (isset($_GET['persona_id']) && filter_var($_GET['persona_id'], FILTER_VALIDATE_INT)) {
    $persona_id_selected = (int)$_GET['persona_id'];
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
            background-color: #f8f9fa;
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
        .checkbox-group {
            border: 1px solid #ced4da;
            border-radius: .25rem;
            padding: .75rem 1rem;
            max-height: 150px;
            overflow-y: auto;
        }
        .dark-mode .checkbox-group {
            background-color: #6c757d;
            border-color: #495057;
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
    $mensajeModal = '';
    $tipoMensajeModal = '';
    if (isset($_SESSION['modal_message']) && !empty($_SESSION['modal_message'])) {
        $mensajeModal = $_SESSION['modal_message'];
        $tipoMensajeModal = $_SESSION['modal_type'];
        unset($_SESSION['modal_message']);
        unset($_SESSION['modal_type']);
    }

    if (!empty($mensajeModal) && !empty($tipoMensajeModal)) {
        $alertClass = '';
        switch ($tipoMensajeModal) {
            case 'success': $alertClass = 'alert-success'; break;
            case 'danger':  $alertClass = 'alert-danger';  break;
            case 'warning': $alertClass = 'alert-warning'; break;
            case 'info':    $alertClass = 'alert-info';    break;
            default:        $alertClass = 'alert-info';
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
                    <a href="nueva_persona.php?redirect_to=nuevo_proveedor.php" id="btnNuevaPersona" class="btn btn-outline-success flex-fill" title="Agregar nueva persona">
                        + Nueva Persona
                    </a>
                </div>
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label">Categorías que puede suministrar:</label>
            <input type="text" id="categoriaSearch" class="form-control mb-2" placeholder="Buscar categoría...">
            <div class="checkbox-group" id="categoriasCheckboxGroup">
                <?php if (empty($todas_las_categorias)): ?>
                    <p class="text-muted">No hay categorías disponibles. Por favor, agregue categorías primero.</p>
                <?php else: ?>
                    <?php foreach ($todas_las_categorias as $categoria): ?>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" 
                                name="categorias_suministradas[]" 
                                value="<?= htmlspecialchars($categoria['categoria_id']) ?>" 
                                id="categoria_<?= htmlspecialchars($categoria['categoria_id']) ?>"
                                <?= in_array($categoria['categoria_id'], $categorias_seleccionadas_post) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="categoria_<?= htmlspecialchars($categoria['categoria_id']) ?>">
                                <?= htmlspecialchars($categoria['nombre']) ?>
                            </label>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
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
    const btnNuevaPersona = document.getElementById('btnNuevaPersona'); // Nuevo: Referencia al botón "+ Nueva Persona"

    // Elementos para la búsqueda de categorías
    const categoriaSearchInput = document.getElementById('categoriaSearch');
    const categoriasCheckboxGroup = document.getElementById('categoriasCheckboxGroup');

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
        // No pre-seleccionar ciudad aquí, eso lo hará el DOMContentLoaded con sessionStorage
    }

    function actualizarBotonEditarPersona() {
        const personaIdSeleccionada = personaSelect.value;
        const redirectToUrl = 'nuevo_proveedor.php'; 

        if (personaIdSeleccionada && personaIdSeleccionada !== "") {
            btnEditarPersona.href = `editar_persona.php?persona_id=${personaIdSeleccionada}&redirect_to=${encodeURIComponent(redirectToUrl)}`;
            btnEditarPersona.textContent = 'Editar Persona';
            btnEditarPersona.classList.remove('disabled');
        } else {
            btnEditarPersona.href = `seleccionar_persona.php?redirect_to=${encodeURIComponent(redirectToUrl)}`;
            btnEditarPersona.textContent = 'Seleccionar Persona';
            btnEditarPersona.classList.remove('disabled');
        }
    }

    function filterCategories() {
        const searchTerm = categoriaSearchInput.value.toLowerCase();
        const categoryItems = categoriasCheckboxGroup.querySelectorAll('.form-check');

        categoryItems.forEach(item => {
            const label = item.querySelector('.form-check-label');
            const categoryName = label ? label.textContent.toLowerCase() : '';
            
            if (categoryName.includes(searchTerm)) {
                item.style.display = 'block';
            } else {
                item.style.display = 'none';
            }
        });
    }

    // --- Funciones para guardar/cargar el estado del formulario en sessionStorage ---
    function saveFormDataToSessionStorage() {
        const formData = {
            nombre_empresa: document.getElementById('nombre_empresa').value,
            persona_id_selected: personaSelect.value,
            pais_selected: paisSelect.value,
            ciudad_selected: ciudadSelect.value, // Asegurarse de tomar el valor actual
            direccion: document.getElementById('direccion').value,
            codigo_postal: document.getElementById('codigo_postal').value,
            // Recoge los IDs de las categorías seleccionadas
            categorias_seleccionadas_post: Array.from(categoriasCheckboxGroup.querySelectorAll('input[type="checkbox"]:checked')).map(cb => cb.value)
        };
        sessionStorage.setItem('form_nuevo_proveedor_temp_data', JSON.stringify(formData));
    }

    function loadFormDataFromSessionStorage() {
        const tempData = sessionStorage.getItem('form_nuevo_proveedor_temp_data');
        if (tempData) {
            const data = JSON.parse(tempData);
            document.getElementById('nombre_empresa').value = data.nombre_empresa || '';
            personaSelect.value = data.persona_id_selected || '';
            paisSelect.value = data.pais_selected || '';
            document.getElementById('direccion').value = data.direccion || '';
            document.getElementById('codigo_postal').value = data.codigo_postal || '';

            // Restaurar categorías
            categoriasCheckboxGroup.querySelectorAll('input[type="checkbox"]').forEach(cb => {
                cb.checked = false; // Desmarcar todas por defecto antes de restaurar
            });
            if (data.categorias_seleccionadas_post) {
                data.categorias_seleccionadas_post.forEach(selectedId => {
                    const checkbox = document.getElementById(`categoria_${selectedId}`);
                    if (checkbox) {
                        checkbox.checked = true;
                    }
                });
            }
            
            // Re-actualizar ciudades y luego seleccionar la ciudad guardada
            actualizarCiudades(); // Esto llena las opciones de ciudad según el país
            ciudadSelect.value = data.ciudad_selected || ''; // Y esto selecciona la ciudad si está en las opciones

            // Es importante llamar a esta función para que el texto y href del botón "Editar Persona" se actualicen
            actualizarBotonEditarPersona(); 

            // Limpiar los datos temporales después de usarlos
            sessionStorage.removeItem('form_nuevo_proveedor_temp_data');
        }
    }
    // --- Fin de funciones sessionStorage ---


    // Event Listeners
    paisSelect.addEventListener('change', actualizarCiudades);
    personaSelect.addEventListener('change', actualizarBotonEditarPersona);
    categoriaSearchInput.addEventListener('keyup', filterCategories);

    // Añadir listeners de click a los botones de redirección para guardar el estado del formulario
    btnEditarPersona.addEventListener('click', saveFormDataToSessionStorage);
    btnNuevaPersona.addEventListener('click', saveFormDataToSessionStorage);

    // Call functions on DOM load to set initial state
    document.addEventListener('DOMContentLoaded', function() {
        // Primero intenta cargar datos de sessionStorage (si se regresa de otra página)
        loadFormDataFromSessionStorage();

        // Luego, si no se cargaron datos de sessionStorage o si solo es la carga inicial,
        // asegura que los dropdowns dependientes y botones se inicialicen correctamente.
        // La persona_id_selected de PHP (que podría venir de GET) tiene prioridad sobre sessionStorage aquí,
        // pero sessionStorage ya la habría manejado si estaba presente.
        if (paisSelect.value !== '') {
            actualizarCiudades();
        } else {
            ciudadSelect.innerHTML = '<option value="">Seleccione un país primero</option>';
        }
        actualizarBotonEditarPersona(); 
        
        // Dark mode logic
        const body = document.body;
        if (localStorage.getItem('darkMode') === 'enabled') {
            body.classList.add('dark-mode');
        }
    });
</script>
</body>
</html>