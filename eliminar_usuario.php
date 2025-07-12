<?php
session_start();
include("config.php"); // Asegúrate de que 'config.php' contenga la conexión PDO.

// Verificar si el usuario ha iniciado sesión y tiene permisos de administrador.
// **IMPORTANTE**: Asegúrate de que $_SESSION['rol_id'] se establezca correctamente en tu login.php.
// Ajusta '1' al ID del rol que corresponde a un administrador en tu base de datos.
if (!isset($_SESSION['usuario']) || !isset($_SESSION['rol_id']) || $_SESSION['rol_id'] != 1) {
    // Si no está logueado o no es admin, redirigir al login o a una página de acceso denegado
    header("Location: login.php?error=acceso_denegado");
    exit();
}

$usuario_empleado_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($usuario_empleado_id === 0) {
    // Si no se proporciona un ID válido, redirigir con un error
    header("Location: gestion_usuarios.php?status=error_invalid_id");
    exit();
}

$mensaje = '';
$claseMensaje = '';

// Obtener los datos del usuario para mostrar en la confirmación
$userData = null;
try {
    $sqlUser = "SELECT ue.usuario, p.nombre, p.apellido_paterno, p.apellido_materno, ue.estado
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
    }
} catch (PDOException $e) {
    error_log("Error al cargar datos del usuario para eliminación: " . $e->getMessage());
    $mensaje = "Error al cargar los datos del usuario. Intente de nuevo.";
    $claseMensaje = 'alert-danger';
}

// Procesar la solicitud de eliminación (o inactivación)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
    // Verificar si el usuario está intentando inactivarse a sí mismo
    if ($usuario_empleado_id == $_SESSION['usuario_empleado_id']) { // Asume que guardas el ID del usuario logueado en la sesión
        $mensaje = "No puedes inactivar tu propia cuenta mientras estás logueado.";
        $claseMensaje = 'alert-warning';
    } else {
        try {
            // **IMPORTANTE**: Lógica para verificar relaciones existentes
            // Ajusta estas consultas SQL a las tablas reales que tienen FK a usuario_empleado_id
            $hasRelations = false;

            // Ejemplo 1: Verificar en la tabla 'orden'
            $stmtOrden = $pdo->prepare("SELECT COUNT(*) FROM orden WHERE usuario_empleado_id = :id");
            $stmtOrden->execute([':id' => $usuario_empleado_id]);
            if ($stmtOrden->fetchColumn() > 0) {
                $hasRelations = true;
                $mensaje = "No se puede inactivar este usuario porque tiene órdenes asociadas.";
            }

            // Ejemplo 2: Verificar en la tabla 'venta' (si existiera)
            if (!$hasRelations) { // Solo si no se encontró relación en la tabla anterior
                $stmtVenta = $pdo->prepare("SELECT COUNT(*) FROM venta WHERE usuario_empleado_id = :id");
                $stmtVenta->execute([':id' => $usuario_empleado_id]);
                if ($stmtVenta->fetchColumn() > 0) {
                    $hasRelations = true;
                    $mensaje = "No se puede inactivar este usuario porque tiene ventas registradas.";
                }
            }

            // Agrega más verificaciones si tienes otras tablas relacionadas
            // Ejemplo 3: Verificar en la tabla 'registro_actividad'
            /*
            if (!$hasRelations) {
                $stmtActividad = $pdo->prepare("SELECT COUNT(*) FROM registro_actividad WHERE usuario_empleado_id = :id");
                $stmtActividad->execute([':id' => $usuario_empleado_id]);
                if ($stmtActividad->fetchColumn() > 0) {
                    $hasRelations = true;
                    $mensaje = "No se puede inactivar este usuario porque tiene registros de actividad.";
                }
            }
            */

            if ($hasRelations) {
                $claseMensaje = 'alert-warning';
            } else {
                // Si no hay relaciones, proceder con la inactivación
                $stmt = $pdo->prepare("UPDATE usuario_empleado SET estado = 0 WHERE usuario_empleado_id = :id");
                $stmt->execute([':id' => $usuario_empleado_id]);

                // Redirigir después de una inactivación exitosa
                header("Location: gestion_usuarios.php?status=success_delete");
                exit();
            }

        } catch (PDOException $e) {
            $mensaje = "Error al procesar la solicitud: " . $e->getMessage();
            $claseMensaje = 'alert-danger';
            error_log("Error al eliminar/inactivar usuario: " . $e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Confirmar Inactivación de Usuario</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/estilos.css?v=<?= time(); ?>">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .container.mt-5 {
            max-width: 600px;
        }
        .main-title {
            color: #dc3545; /* Rojo para indicar una acción de peligro */
            margin-bottom: 30px;
            text-align: center;
        }
        .card {
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            border-radius: 0.75rem;
            border: none;
            background-color: #ffffff; /* Fondo blanco para la tarjeta de confirmación */
        }
        .card-body {
            text-align: center;
        }
        .btn-danger {
            background-color: #dc3545;
            border-color: #dc3545;
        }
        .btn-danger:hover {
            background-color: #c82333;
            border-color: #bd2130;
        }
        .btn-secondary {
            background-color: #6c757d;
            border-color: #6c757d;
        }
        .btn-secondary:hover {
            background-color: #5a6268;
            border-color: #545b62;
        }

        /* Estilos para el tema oscuro */
        body.dark-mode {
            background-color: #343a40;
            color: #f8f9fa;
        }
        body.dark-mode .container {
            background-color: #343a40;
        }
        body.dark-mode .main-title {
            color: #dc3545; /* Mantener rojo para la advertencia */
        }
        body.dark-mode .card {
            background-color: #545b62;
            border-color: #6c757d;
        }
        body.dark-mode .card-body {
            color: #f8f9fa;
        }
        body.dark-mode .alert { /* Estilo para alertas en modo oscuro */
            color: #343a40; /* Texto oscuro */
            background-color: #f8d7da; /* Fondo claro para alerta de peligro */
            border-color: #f5c6cb;
        }
        body.dark-mode .alert-danger {
            background-color: #f5c6cb;
            border-color: #f1b0b5;
            color: #721c24;
        }
        body.dark-mode .alert-warning { /* Nuevo estilo para alertas de advertencia en modo oscuro */
            background-color: #ffeeba;
            border-color: #ffc720;
            color: #664d03;
        }
        body.dark-mode .btn-close {
            filter: invert(1); /* Para que la 'x' se vea bien en fondos oscuros */
        }
    </style>
</head>
<body>
<div class="container mt-5">
    <h3 class="main-title"><i class="bi bi-exclamation-triangle-fill"></i> Confirmar Inactivación de Usuario</h3>

    <?php if ($mensaje): ?>
        <div class="alert <?= $claseMensaje ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($mensaje) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
    <?php endif; ?>

    <?php if ($userData): ?>
        <div class="card p-4">
            <div class="card-body">
                <?php if ($userData['estado'] == 0): ?>
                    <p class="lead text-info">El usuario "<?= htmlspecialchars($userData['usuario']) ?>" ya está **inactivo**.</p>
                    <a href="gestion_usuarios.php" class="btn btn-secondary btn-lg"><i class="bi bi-arrow-left-circle-fill"></i> Volver</a>
                <?php else: ?>
                    <p class="lead">¿Estás seguro de que quieres **inactivar** al usuario:</p>
                    <h5 class="mb-4">"<?= htmlspecialchars($userData['usuario']) ?>" (<?= htmlspecialchars($userData['nombre'] . ' ' . $userData['apellido_paterno'] . ' ' . $userData['apellido_materno']) ?>)?</h5>
                    <p class="text-danger">Esta acción cambiará su estado a inactivo y ya no podrá iniciar sesión.</p>
                    <form method="POST">
                        <input type="hidden" name="confirm_delete" value="1">
                        <div class="d-flex justify-content-center gap-3">
                            <a href="gestion_usuarios.php" class="btn btn-secondary btn-lg"><i class="bi bi-x-circle-fill"></i> Cancelar</a>
                            <button type="submit" class="btn btn-danger btn-lg"><i class="bi bi-person-slash-fill"></i> Inactivar Usuario</button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <div class="alert alert-warning text-center" role="alert">
            No se encontraron datos para el usuario especificado.
            <a href="gestion_usuarios.php" class="alert-link">Volver a la gestión de usuarios</a>.
        </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Lógica para el Modo Oscuro
    document.addEventListener('DOMContentLoaded', (event) => {
        const body = document.body;
        if (localStorage.getItem('darkMode') === 'enabled') {
            body.classList.add('dark-mode');
        }
    });
</script>
</body>
</html>