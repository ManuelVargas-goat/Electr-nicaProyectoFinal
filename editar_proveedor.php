<?php
session_start();
include("config.php"); // Asegúrate de que 'config.php' contenga la conexión PDO.

if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}

// Obtener datos del usuario actual
$usuario = $_SESSION['usuario'];
$sql_usuario = "SELECT per.nombre, per.apellido_paterno, r.nombre AS rol
                FROM usuario_empleado ue
                JOIN persona per ON ue.persona_id = per.persona_id
                JOIN rol r ON ue.rol_id = r.rol_id
                WHERE ue.usuario = :usuario";
$stmt = $pdo->prepare($sql_usuario);
$stmt->execute([':usuario' => $usuario]);
$datosUsuario = $stmt->fetch(PDO::FETCH_ASSOC);

$nombreCompleto = htmlspecialchars($datosUsuario['nombre'] . ' ' . $datosUsuario['apellido_paterno']);
$rolUsuario = strtolower(htmlspecialchars($datosUsuario['rol']));

// --- Obtener todas las categorías para el SELECT y para los checkboxes de categorías ---
$todas_las_categorias = [];
try {
    $stmtCategorias = $pdo->query("SELECT categoria_id, nombre FROM categoria ORDER BY nombre");
    $todas_las_categorias = $stmtCategorias->fetchAll(PDO::FETCH_ASSOC); // Fetch all for checkboxes
    $categorias = array_column($todas_las_categorias, 'nombre'); // Just names for the select filter
} catch (PDOException $e) {
    error_log("Error al cargar categorías: " . $e->getMessage());
    $categorias = []; // Asegura que $categorias sea un array vacío si falla
    $todas_las_categorias = [];
}

// Validar que se reciba un ID de proveedor válido
if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    // Redirige a la página de gestión con un mensaje de error si el ID es inválido
    $_SESSION['modal_message'] = "ID de proveedor no válido.";
    $_SESSION['modal_type'] = "danger";
    header("Location: gestion_catalogo_proveedores.php");
    exit();
}

$proveedor_id = (int)$_GET['id'];
$formError = ''; // Variable para errores de validación en este formulario

// Obtener datos del proveedor existente
try {
    $stmtProveedor = $pdo->prepare("SELECT proveedor_id, nombre_empresa, persona_id, pais, ciudad, direccion, codigo_postal FROM proveedor WHERE proveedor_id = :id");
    $stmtProveedor->execute([':id' => $proveedor_id]);
    $proveedor = $stmtProveedor->fetch(PDO::FETCH_ASSOC);

    if (!$proveedor) {
        // Si el proveedor no se encuentra, redirige con un mensaje de error
        $_SESSION['modal_message'] = "Proveedor no encontrado.";
        $_SESSION['modal_type'] = "danger";
        header("Location: gestion_catalogo_proveedores.php");
        exit();
    }
} catch (PDOException $e) {
    // Si hay un error de DB al cargar el proveedor
    error_log("Error al cargar datos del proveedor en editar_proveedor.php: " . $e->getMessage());
    $_SESSION['modal_message'] = "Error interno al cargar el proveedor.";
    $_SESSION['modal_type'] = "danger";
    header("Location: gestion_catalogo_proveedores.php");
    exit();
}

// Obtener personas para asociar con proveedor (incluyendo apellido_materno para el desplegable)
$personas = [];
try {
    $stmtPersonas = $pdo->query("SELECT persona_id, nombre, apellido_paterno, apellido_materno FROM persona ORDER BY nombre");
    $personas = $stmtPersonas->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Manejar error al cargar personas, pero permite que el formulario se muestre
    $formError = "Error al cargar la lista de personas: " . $e->getMessage();
}

// Obtener las categorías actualmente asociadas a este proveedor
$categorias_asociadas_al_proveedor = [];
try {
    $stmtProveedorCategorias = $pdo->prepare("SELECT categoria_id FROM proveedor_categoria WHERE proveedor_id = :proveedor_id");
    $stmtProveedorCategorias->execute([':proveedor_id' => $proveedor_id]);
    $categorias_asociadas_al_proveedor = $stmtProveedorCategorias->fetchAll(PDO::FETCH_COLUMN, 0);
} catch (PDOException $e) {
    error_log("Error al cargar categorías asociadas al proveedor: " . $e->getMessage());
    // No establecer $formError aquí para no sobrescribir otros errores, solo loguear.
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
    // Si la persona fue actualizada/creada desde otra página, mostrar un mensaje de éxito si lo hay
    if (isset($_SESSION['modal_message']) && $_SESSION['modal_type'] === 'success') {
        // No necesitamos hacer nada aquí, ya que el mensaje de la sesión ya estará disponible
        // para ser mostrado en la siguiente carga de la página.
    }
}

// La variable $mensaje_exito de $_GET ya no es necesaria aquí, se usará $_SESSION

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre_empresa = trim($_POST['nombre_empresa'] ?? '');
    $persona_id_selected = (int)($_POST['persona_id'] ?? 0); // Captura el ID de la persona seleccionada
    $pais_selected = trim($_POST['pais'] ?? '');
    $ciudad_selected = trim($_POST['ciudad'] ?? '');
    $direccion = trim($_POST['direccion'] ?? '');
    $codigo_postal = trim($_POST['codigo_postal'] ?? '');
    $categorias_suministradas = $_POST['categorias_suministradas'] ?? [];
    $categorias_suministradas = array_map('intval', $categorias_suministradas);


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

            // Actualizar categorías asociadas:
            // 1. Eliminar asociaciones existentes para este proveedor
            $sql_delete_pc = "DELETE FROM proveedor_categoria WHERE proveedor_id = :proveedor_id";
            $stmt_delete_pc = $pdo->prepare($sql_delete_pc);
            $stmt_delete_pc->execute([':proveedor_id' => $proveedor_id]);

            // 2. Insertar nuevas asociaciones
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
            // Establecer mensaje de éxito en la sesión y redirigir
            $_SESSION['modal_message'] = "Proveedor actualizado con éxito.";
            $_SESSION['modal_type'] = "success";
            header("Location: gestion_catalogo_proveedores.php");
            exit();
        } catch (PDOException $e) {
            $pdo->rollBack();
            // Error de base de datos
            error_log("Error al actualizar el proveedor en DB: " . $e->getMessage());
            $formError = "Error al actualizar el proveedor: " . $e->getMessage(); // Muestra el error en el formulario
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
    <h3 class="main-title">Editar Proveedor #<?= htmlspecialchars($proveedor_id) ?></h3>

    <?php if (!empty($formError)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($formError) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php
    // **BLOQUE PARA MOSTRAR LA ALERTA DE BOOTSTRAP (si viene de una redirección)**
    // Esto es para mensajes que pudieron venir de 'nueva_persona.php' o similar
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
                                <?= in_array($categoria['categoria_id'], $categorias_asociadas_al_proveedor) ? 'checked' : '' ?>>
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
    const btnEliminarPersona = document.getElementById('btnEliminarPersona');
    const proveedorId = <?= json_encode($proveedor_id); ?>;

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

        const ciudadSelectedFromPhp = '<?= $ciudad_selected ?>';
        const paisSelectedFromPhp = '<?= $pais_selected ?>';

        if (paisSeleccionado === paisSelectedFromPhp) {
            ciudadSelect.value = ciudadSelectedFromPhp;
        } else {
            ciudadSelect.value = '';
        }
    }

    function actualizarBotonesPersona() {
        const personaIdSeleccionada = personaSelect.value;
        const proveedor_id = <?= json_encode($proveedor_id); ?>;

        // Lógica para el botón "Editar Persona"
        if (personaIdSeleccionada && personaIdSeleccionada !== "") {
            btnEditarPersona.href = `editar_persona.php?persona_id=${personaIdSeleccionada}&redirect_to=editar_proveedor.php&proveedor_id=${proveedor_id}`;
            btnEditarPersona.textContent = 'Editar Persona';
            btnEditarPersona.classList.remove('disabled');
        } else {
            // Si no hay persona seleccionada, el botón "Editar Persona" se convierte en "Seleccionar Persona"
            btnEditarPersona.href = `seleccionar_persona.php?redirect_to=editar_proveedor.php&proveedor_id=${proveedor_id}`;
            btnEditarPersona.textContent = 'Seleccionar Persona';
            // No se deshabilita, ya que es la acción para seleccionar una persona
            btnEditarPersona.classList.remove('disabled');
        }

        // Lógica para el botón "Eliminar Persona"
        if (personaIdSeleccionada && personaIdSeleccionada !== "") {
            btnEliminarPersona.href = '#'; // El enlace real se manejará en el evento click
            btnEliminarPersona.classList.remove('disabled');
            btnEliminarPersona.textContent = 'Eliminar Persona'; // Asegura que siempre tenga texto cuando está activo
            // Usamos un evento click para mostrar un confirm y luego redirigir
            btnEliminarPersona.onclick = function(e) {
                e.preventDefault(); // Prevenir el comportamiento por defecto del enlace
                if (confirm('¿Está seguro de que desea desasociar y eliminar permanentemente a esta persona del sistema?')) {
                    // Redirigir a un script de eliminación de persona.
                    // Se asume que este script manejará la eliminación y la redirección de vuelta con un mensaje.
                    window.location.href = `eliminar_persona.php?persona_id=${personaIdSeleccionada}&redirect_to=editar_proveedor.php&proveedor_id=${proveedor_id}`;
                }
            };
        } else {
            btnEliminarPersona.href = '#';
            btnEliminarPersona.classList.add('disabled'); // Deshabilita el botón si no hay persona seleccionada
            btnEliminarPersona.onclick = null; // Elimina el evento click
            btnEliminarPersona.textContent = 'Eliminar Persona'; // Restaurar texto si se deshabilita
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

    // Event Listeners
    paisSelect.addEventListener('change', actualizarCiudades);
    personaSelect.addEventListener('change', actualizarBotonesPersona);
    categoriaSearchInput.addEventListener('keyup', filterCategories); // Listener para el filtro de categorías


    // Call functions on DOM load to set initial state
    document.addEventListener('DOMContentLoaded', function() {
        if (paisSelect.value !== '') {
            actualizarCiudades();
        } else {
            ciudadSelect.innerHTML = '<option value="">Seleccione un país primero</option>';
        }
        actualizarBotonesPersona(); // Llama a la función para establecer el estado inicial de los botones
                                    // Esto asegura que el texto se muestre correctamente al cargar la página
                                    // si ya hay una persona seleccionada.
        
        // Dark mode logic
        const body = document.body;
        if (localStorage.getItem('darkMode') === 'enabled') {
            body.classList.add('dark-mode');
        }
    });
</script>
</body>
</html>
