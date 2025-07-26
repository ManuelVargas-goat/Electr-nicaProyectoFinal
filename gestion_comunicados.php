<?php
session_start();
include("config.php"); // Asegúrate de que 'config.php' contenga la conexión PDO.

// Redireccionar si el usuario no está logueado
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}

// Obtener datos del usuario actual para verificar el rol
$usuario = $_SESSION['usuario'];
$sql_user_info = "SELECT r.nombre AS rol
                  FROM usuario_empleado ue
                  JOIN rol r ON ue.rol_id = r.rol_id
                  WHERE ue.usuario = :usuario";
$stmt_user_info = $pdo->prepare($sql_user_info);
$stmt_user_info->execute([':usuario' => $usuario]);
$user_info = $stmt_user_info->fetch(PDO::FETCH_ASSOC);

$rolUsuario = strtolower($user_info['rol'] ?? 'usuario');

// Redireccionar si no es administrador
if ($rolUsuario !== 'admin') {
    $_SESSION['modal_message'] = "Acceso denegado. Solo los administradores pueden gestionar comunicados.";
    $_SESSION['modal_type'] = 'danger';
    header("Location: principal.php");
    exit();
}

$mensaje = '';
$tipoMensaje = '';

// Lógica para procesar el formulario (añadir/editar/eliminar)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        try {
            if ($_POST['action'] === 'add_edit') {
                $titulo = trim($_POST['titulo'] ?? '');
                $contenido = trim($_POST['contenido'] ?? '');
                // MODIFICACIÓN: Convertir el booleano a 1 o 0 para la base de datos
                $activo = isset($_POST['activo']) ? 1 : 0; 
                $comunicado_id = $_POST['comunicado_id'] ?? null;

                if (empty($titulo) || empty($contenido)) {
                    $mensaje = 'El título y el contenido del comunicado no pueden estar vacíos.';
                    $tipoMensaje = 'danger';
                } else {
                    if ($comunicado_id) { // Editar comunicado existente
                        $sqlUpdate = "UPDATE comunicados SET titulo = :titulo, contenido = :contenido, activo = :activo WHERE comunicado_id = :comunicado_id";
                        $stmtUpdate = $pdo->prepare($sqlUpdate);
                        $stmtUpdate->execute([
                            ':titulo' => $titulo,
                            ':contenido' => $contenido,
                            ':activo' => $activo,
                            ':comunicado_id' => $comunicado_id
                        ]);
                        $mensaje = 'Comunicado actualizado con éxito.';
                        $tipoMensaje = 'success';
                    } else { // Añadir nuevo comunicado
                        $sqlInsert = "INSERT INTO comunicados (titulo, contenido, fecha_publicacion, activo) VALUES (:titulo, :contenido, NOW(), :activo)";
                        $stmtInsert = $pdo->prepare($sqlInsert);
                        $stmtInsert->execute([
                            ':titulo' => $titulo,
                            ':contenido' => $contenido,
                            ':activo' => $activo
                        ]);
                        $mensaje = 'Comunicado añadido con éxito.';
                        $tipoMensaje = 'success';
                    }
                }
            } elseif ($_POST['action'] === 'delete') {
                $comunicado_id = $_POST['comunicado_id'] ?? null;
                if ($comunicado_id && is_numeric($comunicado_id)) {
                    $sqlDelete = "DELETE FROM comunicados WHERE comunicado_id = :comunicado_id";
                    $stmtDelete = $pdo->prepare($sqlDelete);
                    $stmtDelete->execute([':comunicado_id' => $comunicado_id]);
                    $mensaje = 'Comunicado eliminado con éxito.';
                    $tipoMensaje = 'success';
                } else {
                    $mensaje = 'ID de comunicado no válido para eliminar.';
                    $tipoMensaje = 'danger';
                }
            }
        } catch (PDOException $e) {
            $mensaje = 'Error de base de datos: ' . $e->getMessage();
            $tipoMensaje = 'danger';
            error_log("Error en gestion_comunicados.php: " . $e->getMessage());
        }
    }
}

// Lógica para cargar comunicados para la tabla
$comunicados = [];
try {
    $sql_comunicados = "SELECT comunicado_id, titulo, contenido, fecha_publicacion, activo FROM comunicados ORDER BY fecha_publicacion DESC";
    $stmt_comunicados = $pdo->query($sql_comunicados);
    $comunicados = $stmt_comunicados->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error al cargar comunicados para gestión: " . $e->getMessage());
    $mensaje = 'Error al cargar los comunicados para la gestión.';
    $tipoMensaje = 'danger';
}

// Para el formulario de edición: cargar datos si se pasa un ID por GET
$comunicado_a_editar = null;
if (isset($_GET['edit_id']) && is_numeric($_GET['edit_id'])) {
    $edit_id = (int)$_GET['edit_id'];
    try {
        $sql_edit = "SELECT comunicado_id, titulo, contenido, activo FROM comunicados WHERE comunicado_id = :edit_id";
        $stmt_edit = $pdo->prepare($sql_edit);
        $stmt_edit->execute([':edit_id' => $edit_id]);
        $comunicado_a_editar = $stmt_edit->fetch(PDO::FETCH_ASSOC);
        if (!$comunicado_a_editar) {
            $mensaje = 'Comunicado no encontrado para editar.';
            $tipoMensaje = 'warning';
        }
    } catch (PDOException $e) {
        error_log("Error al cargar comunicado para edición: " . $e->getMessage());
        $mensaje = 'Error al cargar el comunicado para edición.';
        $tipoMensaje = 'danger';
    }
}

$currentPage = basename($_SERVER['PHP_SELF'], ".php");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Comunicados</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/estilos.css?v=<?= time(); ?>">
    <style>
        /* CSS para el sidebar y submenús (duplicado para asegurar consistencia) */
        .sidebar {
            background-color: #1a202c; /* Color de fondo oscuro para el sidebar */
            color: #e0e0e0;
            padding-top: 20px;
            min-height: 100vh;
        }

        .user-box {
            border-bottom: 1px solid #364052;
            padding-bottom: 20px;
            margin-bottom: 20px;
        }

        .user-box img {
            border: 2px solid #ffc107;
        }

        .user-box .fw-bold {
            color: #ffffff; /* Color blanco para el nombre del usuario */
        }

        .user-box .text-warning {
            color: #ffc107 !important; /* Color amarillo para el rol de administrador */
        }

        .user-box .text-light {
            color: #f8f9fa !important; /* Color claro para otros roles */
        }

        .sidebar a {
            display: block;
            padding: 8px 15px;
            color: #e0e0e0; /* Color gris claro por defecto para el texto normal */
            text-decoration: none;
            border-radius: 5px;
            margin-bottom: 5px;
            transition: background-color 0.3s, color 0.3s;
        }

        .sidebar a:hover {
            background-color: #495057; /* Fondo un poco más oscuro al pasar el ratón */
            color: #ffffff; /* Color blanco al pasar el ratón */
        }

        /* Estilo para el enlace de la página ACTUAL */
        .sidebar a.current-page {
            background-color: #495057; /* Fondo gris oscuro */
            color: #ffc107 !important; /* Texto amarillo anaranjado */
            font-weight: bold;
        }

        /* Submenú */
        .submenu {
            display: none; /* Oculto por defecto */
            padding-left: 20px; /* Indentación */
        }

        .submenu.active {
            display: block; /* Visible cuando está activo */
        }

        .submenu a {
            padding: 6px 0 6px 15px; /* Ajuste para sub-ítems */
            font-size: 0.9em;
            color: #e0e0e0; /* Color por defecto para sub-ítems */
        }

        /* Asegurarse que los sub-enlaces activos también tienen el color */
        .submenu a.current-page {
            background-color: #495057; /* Fondo gris oscuro para submenú activo */
            color: #ffc107 !important; /* Texto amarillo anaranjado para submenú activo */
        }

        /* Estilo para el botón "Volver al Inicio" */
        .sidebar .btn-outline-primary {
            background-color: #1a202c; /* Fondo azul oscuro, mismo que el sidebar */
            color: #ffffff; /* Texto blanco */
            border-color: #007bff; /* Borde azul */
            font-weight: bold;
        }

        .sidebar .btn-outline-primary:hover {
            background-color: #2c3447; /* Un poco más claro al pasar el ratón */
            color: #ffffff;
            border-color: #007bff; /* Borde azul se mantiene */
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

        /* Estilos personalizados para los botones de acción */
        .btn-turquesa {
            background-color: #20c997; /* Un tono de turquesa */
            border-color: #20c997;
            color: #ffffff; /* Letras blancas */
        }
        .btn-turquesa:hover {
            background-color: #17a2b8; /* Un tono más oscuro al pasar el ratón */
            border-color: #17a2b8;
            color: #ffffff;
        }
        .btn-danger {
            background-color: #dc3545; /* Rojo estándar de Bootstrap */
            border-color: #dc3545;
            color: #ffffff; /* Letras blancas */
        }
        .btn-danger:hover {
            background-color: #c82333; /* Rojo más oscuro al pasar el ratón */
            border-color: #bd2130;
            color: #ffffff;
        }
    </style>
</head>
<body>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-2 sidebar">
            <!-- Contenido del sidebar (puede ser incluido de otro archivo o duplicado) -->
            <div class="user-box text-center mb-4">
                <img src="https://cdn-icons-png.flaticon.com/512/149/149071.png" alt="Usuario" class="img-fluid rounded-circle mb-2" style="width: 64px;">
                <div class="fw-bold"><?= htmlspecialchars($usuario); ?></div>
                <div class="<?= ($rolUsuario === 'admin') ? 'text-warning' : 'text-light' ?> small">
                    <?= ucfirst($rolUsuario === 'admin' ? 'administrador' : $rolUsuario) ?>
                </div>
            </div>

            <a href="principal.php" class="<?= ($currentPage === 'principal') ? 'current-page' : '' ?>">Inicio</a>
            <a href="#" id="catalogoToggle" class="sidebar-link <?= (strpos($currentPage, 'gestion_catalogo') !== false) ? 'current-page' : '' ?>">Gestión de Catálogo</a>
            <div class="submenu <?= (strpos($currentPage, 'gestion_catalogo') !== false) ? 'active' : '' ?>" id="catalogoSubmenu">
                <a href="gestion_catalogo_categorias.php" class="<?= ($currentPage === 'gestion_catalogo_categorias') ? 'current-page' : '' ?>">Categorías</a>
                <a href="gestion_catalogo_productos.php" class="<?= ($currentPage === 'gestion_catalogo_productos') ? 'current-page' : '' ?>">Productos</a>
                <a href="gestion_catalogo_proveedores.php" class="<?= ($currentPage === 'gestion_catalogo_proveedores') ? 'current-page' : '' ?>">Proveedores</a>
            </div>
            <a href="gestion_usuarios.php" class="sidebar-link <?= ($currentPage === 'gestion_usuarios') ? 'current-page' : '' ?>">Gestión de Usuarios</a>
            <a href="#" id="existenciasToggle" class="sidebar-link <?= (strpos($currentPage, 'gestion_existencias') !== false) ? 'current-page' : '' ?>">Gestión de Existencias</a>
            <div class="submenu <?= (strpos($currentPage, 'gestion_existencias') !== false) ? 'active' : '' ?>" id="existenciasSubmenu">
                <a href="gestion_existencias_pedidos.php" class="<?= ($currentPage === 'gestion_existencias_pedidos') ? 'current-page' : '' ?>">Pedidos</a>
                <a href="gestion_existencias_ventas.php" class="<?= ($currentPage === 'gestion_existencias_ventas') ? 'current-page' : '' ?>">Ventas</a>
                <a href="gestion_existencias_devoluciones.php" class="<?= ($currentPage === 'gestion_existencias_devoluciones') ? 'current-page' : '' ?>">Devoluciones</a>
                <a href="gestion_existencias_stock.php" class="<?= ($currentPage === 'gestion_existencias_stock') ? 'current-page' : '' ?>">Stock</a>
            </div>
            <a href="configuracion.php" class="<?= ($currentPage === 'configuracion') ? 'current-page' : '' ?>">Configuración</a>
            <div class="mt-4 text-center">
                <a href="salir.php" class="btn btn-outline-danger w-100">Cerrar Sesión</a>
            </div>
        </div>

        <div class="col-md-10 content">
            <h3 class="main-title">Gestión de Comunicados del Sistema</h3>

            <?php if (!empty($mensaje)): ?>
                <div class="alert alert-<?= htmlspecialchars($tipoMensaje) ?> alert-dismissible fade show mt-3" role="alert">
                    <?= htmlspecialchars($mensaje) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <?= $comunicado_a_editar ? 'Editar Comunicado' : 'Añadir Nuevo Comunicado' ?>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="add_edit">
                        <?php if ($comunicado_a_editar): ?>
                            <input type="hidden" name="comunicado_id" value="<?= htmlspecialchars($comunicado_a_editar['comunicado_id']) ?>">
                        <?php endif; ?>
                        <div class="mb-3">
                            <label for="titulo" class="form-label">Título:</label>
                            <input type="text" class="form-control" id="titulo" name="titulo" value="<?= htmlspecialchars($comunicado_a_editar['titulo'] ?? '') ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="contenido" class="form-label">Contenido:</label>
                            <textarea class="form-control" id="contenido" name="contenido" rows="5" required><?= htmlspecialchars($comunicado_a_editar['contenido'] ?? '') ?></textarea>
                        </div>
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" id="activo" name="activo" <?= ($comunicado_a_editar['activo'] ?? true) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="activo">Activo (Visible en el Inicio)</label>
                        </div>
                        <button type="submit" class="btn btn-success">
                            <?= $comunicado_a_editar ? 'Guardar Cambios' : 'Añadir Comunicado' ?>
                        </button>
                        <?php if ($comunicado_a_editar): ?>
                            <a href="gestion_comunicados.php" class="btn btn-secondary ms-2">Cancelar Edición</a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header bg-info text-white">
                    Listado de Comunicados
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Título</th>
                                    <th>Contenido</th>
                                    <th>Fecha Publicación</th>
                                    <th>Activo</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($comunicados)): ?>
                                    <?php foreach ($comunicados as $comunicado): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($comunicado['comunicado_id']) ?></td>
                                            <td><?= htmlspecialchars($comunicado['titulo']) ?></td>
                                            <td><?= nl2br(htmlspecialchars(mb_strimwidth($comunicado['contenido'], 0, 100, "..."))) ?></td>
                                            <td><?= date('d/m/Y H:i', strtotime($comunicado['fecha_publicacion'])) ?></td>
                                            <td><?= $comunicado['activo'] ? 'Sí' : 'No' ?></td>
                                            <td>
                                                <a href="gestion_comunicados.php?edit_id=<?= htmlspecialchars($comunicado['comunicado_id']) ?>" class="btn btn-sm btn-turquesa me-1">Editar</a>
                                                <form method="POST" style="display:inline-block;">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="comunicado_id" value="<?= htmlspecialchars($comunicado['comunicado_id']) ?>">
                                                    <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('¿Estás seguro de que quieres eliminar este comunicado?');">Eliminar</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center">No hay comunicados para gestionar.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="mt-4 text-center">
                <a href="principal.php" class="btn btn-outline-primary w-100">Volver al Inicio</a>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', (event) => {
        // Lógica del submenu para "Gestión de Catálogo"
        const catalogoToggle = document.getElementById('catalogoToggle');
        const catalogoSubmenu = document.getElementById('catalogoSubmenu');

        // Abre el submenú de catálogo si alguna de sus sub-páginas está activa
        if (catalogoToggle && catalogoSubmenu && catalogoToggle.classList.contains('current-page')) {
            catalogoSubmenu.classList.add('active');
        }

        if (catalogoToggle) { // Solo si el elemento existe
            catalogoToggle.addEventListener('click', function(e) {
                e.preventDefault(); // Previene el comportamiento predeterminado del enlace
                if (catalogoSubmenu) {
                    catalogoSubmenu.classList.toggle('active'); // Alterna la clase 'active' para mostrar/ocultar
                }
            });
        }
        
        // Lógica para el submenú de "Gestión de Existencias"
        const existenciasToggle = document.getElementById('existenciasToggle');
        const existenciasSubmenu = document.getElementById('existenciasSubmenu');

        // Abre el submenú de existencias si alguna de sus sub-páginas está activa
        if (existenciasToggle && existenciasSubmenu && existenciasToggle.classList.contains('current-page')) {
             existenciasSubmenu.classList.add('active');
        }
        
        if (existenciasToggle) {
             existenciasToggle.addEventListener('click', function(e) {
                 e.preventDefault();
                 if (existenciasSubmenu) {
                     existenciasSubmenu.classList.toggle('active');
                 }
             });
        }
    });
</script>
</body>
</html>
