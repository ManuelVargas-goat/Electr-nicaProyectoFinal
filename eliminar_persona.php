<?php
session_start();
include("config.php"); // Asegúrate de que 'config.php' contenga la conexión PDO y $pdo.

// =================================================================
// INICIO DE DEPURACIÓN DE SESIÓN (PARA DIAGNOSTICAR REDIRECCIONES A LOGIN)
// Estas líneas escribirán en el log de errores de PHP.
// Asegúrate de revisar tu php_error.log o el log de errores de tu servidor web.
error_log("--- ELIMINAR_USUARIO.PHP INICIADO ---");
error_log("SESION COMPLETA: " . print_r($_SESSION, true));
error_log("USUARIO EN SESION: " . (isset($_SESSION['usuario']) ? $_SESSION['usuario'] : 'NO ESTABLECIDO'));
error_log("ROL_ID EN SESION: " . (isset($_SESSION['rol_id']) ? $_SESSION['rol_id'] : 'NO ESTABLECIDO'));
error_log("USUARIO_EMPLEADO_ID EN SESION: " . (isset($_SESSION['usuario_empleado_id']) ? $_SESSION['usuario_empleado_id'] : 'NO ESTABLECIDO'));
error_log("PARAMETRO GET ID: " . (isset($_GET['id']) ? $_GET['id'] : 'NO ESTABLECIDO'));
error_log("--- FIN DE DEPURACIÓN INICIAL ---");
// =================================================================

// =================================================================
// 1. VERIFICACIÓN DE PERMISOS Y AUTENTICACIÓN
// =================================================================
// VERIFICA:
// 1. Si el usuario está logueado (isset($_SESSION['usuario']))
// 2. Si el rol_id está en sesión (isset($_SESSION['rol_id']))
// 3. Si el rol_id es igual a 1 (asumiendo 1 es el ID para administradores)
// Si alguna de estas falla, redirige al login.
if (!isset($_SESSION['usuario']) || !isset($_SESSION['rol_id']) || $_SESSION['rol_id'] != 1) {
    error_log("Intento de acceso no autorizado a eliminar_usuario.php. Redirigiendo a login.");
    header("Location: login.php?error=" . urlencode("Acceso denegado. Se requiere iniciar sesión como administrador."));
    exit();
}

// =================================================================
// 2. VALIDACIÓN DE ENTRADA (ID del usuario a eliminar)
// =================================================================
$usuario_empleado_id_a_eliminar = isset($_GET['id']) ? intval($_GET['id']) : 0;
$redirect_to = isset($_GET['redirect_to']) ? htmlspecialchars($_GET['redirect_to']) : 'gestion_usuarios.php';

if ($usuario_empleado_id_a_eliminar === 0) {
    error_log("Error: ID de usuario no proporcionado o inválido para eliminar.");
    header("Location: " . $redirect_to . "?status=error&mensaje=" . urlencode("ID de usuario no proporcionado o inválido para eliminar."));
    exit();
}

// =================================================================
// 3. OBTENER DATOS DEL USUARIO (NECESARIO PARA OBTENER persona_id Y VERIFICAR ESTADO)
// =================================================================
$persona_id_asociada = 0;
$usuario_estado = -1; // Inicializar con un valor que indique que no se ha obtenido

try {
    $sqlUser = "SELECT ue.persona_id, ue.estado, ue.usuario, p.nombre, p.apellido_paterno
                 FROM usuario_empleado ue
                 JOIN persona p ON ue.persona_id = p.persona_id
                 WHERE ue.usuario_empleado_id = :usuario_empleado_id";
    $stmtUser = $pdo->prepare($sqlUser);
    $stmtUser->execute([':usuario_empleado_id' => $usuario_empleado_id_a_eliminar]);
    $userData = $stmtUser->fetch(PDO::FETCH_ASSOC);

    if (!$userData) {
        error_log("Error: Usuario con ID {$usuario_empleado_id_a_eliminar} no encontrado en la base de datos.");
        header("Location: " . $redirect_to . "?status=error&mensaje=" . urlencode("Usuario no encontrado o ya eliminado."));
        exit();
    }
    $persona_id_asociada = $userData['persona_id'];
    $usuario_estado = $userData['estado']; // Obtener el estado del usuario

    // Opcional: Si la política es que no se puede eliminar un usuario ya inactivo, o que la eliminación es solo para activos
    // if ($usuario_estado == 0) {
    //     header("Location: " . $redirect_to . "?status=warning&mensaje=" . urlencode("El usuario '{$userData['usuario']}' ya se encuentra inactivo y no puede ser eliminado (consulte la política de eliminación)."));
    //     exit();
    // }

} catch (PDOException $e) {
    error_log("Error PDO al cargar datos del usuario {$usuario_empleado_id_a_eliminar}: " . $e->getMessage());
    header("Location: " . $redirect_to . "?status=error&mensaje=" . urlencode("Error de base de datos al cargar datos del usuario."));
    exit();
}

// =================================================================
// 4. PROCESAR LA SOLICITUD DE ELIMINACIÓN (LÓGICA CORE)
// =================================================================

// 4.1. Verificar si el administrador está intentando eliminarse a sí mismo
// Necesitas tener el 'usuario_empleado_id' del usuario LOGUEADO en la sesión
if (isset($_SESSION['usuario_empleado_id']) && $usuario_empleado_id_a_eliminar == $_SESSION['usuario_empleado_id']) {
    error_log("Error: Intento de auto-eliminación por el usuario logueado (ID: {$usuario_empleado_id_a_eliminar}).");
    header("Location: " . $redirect_to . "?status=error&mensaje=" . urlencode("No puedes eliminar tu propia cuenta mientras estás logueado."));
    exit();
}

try {
    $pdo->beginTransaction(); // Iniciar una transacción para asegurar la atomicidad

    // --- 4.2. VERIFICACIONES DE RELACIONES DEL USUARIO_EMPLEADO ---
    $hasUserRelations = false;
    $userRelationsMessages = [];

    // Ejemplo 1: Verificar en la tabla 'orden'
    $stmtCheckOrden = $pdo->prepare("SELECT COUNT(*) FROM orden WHERE usuario_empleado_id = :id");
    $stmtCheckOrden->execute([':id' => $usuario_empleado_id_a_eliminar]);
    if ($stmtCheckOrden->fetchColumn() > 0) {
        $hasUserRelations = true;
        $userRelationsMessages[] = "Órdenes (Existen " . $stmtCheckOrden->fetchColumn() . " órdenes)"; // Mensaje más detallado
    }

    // Ejemplo 2: Verificar en la tabla 'venta' (si existiera)
    $stmtCheckVenta = $pdo->prepare("SELECT COUNT(*) FROM venta WHERE usuario_empleado_id = :id");
    $stmtCheckVenta->execute([':id' => $usuario_empleado_id_a_eliminar]);
    if ($stmtCheckVenta->fetchColumn() > 0) {
        $hasUserRelations = true;
        $userRelationsMessages[] = "Ventas (Existen " . $stmtCheckVenta->fetchColumn() . " ventas)"; // Mensaje más detallado
    }

    // --- AÑADE AQUÍ MÁS VERIFICACIONES PARA OTRAS TABLAS QUE REFERENCIEN usuario_empleado_id ---
    // Consulta tu esquema de base de datos y agrega un bloque similar para cada tabla.
    // Ejemplo:
    /*
    $stmtCheckActividad = $pdo->prepare("SELECT COUNT(*) FROM registro_actividad WHERE usuario_empleado_id = :id");
    $stmtCheckActividad->execute([':id' => $usuario_empleado_id_a_eliminar]);
    if ($stmtCheckActividad->fetchColumn() > 0) {
        $hasUserRelations = true;
        $userRelationsMessages[] = "Registros de Actividad";
    }
    */

    if ($hasUserRelations) {
        $error = "No se puede eliminar el usuario '{$userData['usuario']}' ({$userData['nombre']} {$userData['apellido_paterno']}). Está asociado a registros en: " . implode(", ", $userRelationsMessages) . ". Por favor, elimine/desasocie esos registros primero.";
        $pdo->rollBack(); // Deshacer cualquier operación si hubiera alguna.
        error_log("Error: Usuario con ID {$usuario_empleado_id_a_eliminar} no pudo ser eliminado debido a relaciones de usuario: " . $error);
        header("Location: " . $redirect_to . "?status=error&mensaje=" . urlencode($error));
        exit();
    } else {
        // --- 4.3. PROCEDER CON LA ELIMINACIÓN DEL USUARIO_EMPLEADO ---
        $stmtDeleteUserEmpleado = $pdo->prepare("DELETE FROM usuario_empleado WHERE usuario_empleado_id = :id");
        $stmtDeleteUserEmpleado->execute([':id' => $usuario_empleado_id_a_eliminar]);
        error_log("Usuario_empleado con ID {$usuario_empleado_id_a_eliminar} eliminado.");

        // --- 4.4. DESPUÉS DE ELIMINAR EL USUARIO_EMPLEADO, VERIFICAR Y ELIMINAR LA PERSONA ASOCIADA ---
        $hasPersonRelations = false;
        $personRelationsMessages = [];

        if ($persona_id_asociada > 0) {
            // Verificar si la persona asociada tiene otras relaciones (cliente, proveedor, etc.)
            // Importante: No verificar contra usuario_empleado ya que acabamos de eliminar esa relación.
            
            // Ejemplo 1: Verificar si la persona es también un cliente
            $stmtCheckCliente = $pdo->prepare("SELECT COUNT(*) FROM cliente WHERE persona_id = :persona_id");
            $stmtCheckCliente->execute([':persona_id' => $persona_id_asociada]);
            if ($stmtCheckCliente->fetchColumn() > 0) {
                $hasPersonRelations = true;
                $personRelationsMessages[] = "Cliente";
            }

            // Ejemplo 2: Verificar si la persona es también un proveedor
            $stmtCheckProveedor = $pdo->prepare("SELECT COUNT(*) FROM proveedor WHERE persona_id = :persona_id");
            $stmtCheckProveedor->execute([':persona_id' => $persona_id_asociada]);
            if ($stmtCheckProveedor->fetchColumn() > 0) {
                $hasPersonRelations = true;
                $personRelationsMessages[] = "Proveedor";
            }

            // --- AÑADE AQUÍ MÁS VERIFICACIONES PARA OTRAS TABLAS QUE REFERENCIEN persona_id ---
            // Consulta tu esquema de base de datos y agrega un bloque similar para cada tabla.
            // Ejemplo:
            /*
            $stmtCheckContactos = $pdo->prepare("SELECT COUNT(*) FROM contactos WHERE persona_id = :persona_id");
            $stmtCheckContactos->execute([':persona_id' => $persona_id_asociada]);
            if ($stmtCheckContactos->fetchColumn() > 0) {
                $hasPersonRelations = true;
                $personRelationsMessages[] = "Contactos";
            }
            */

            if ($hasPersonRelations) {
                // Si la persona tiene otras relaciones, no la eliminamos.
                $mensaje_exito = "Usuario '{$userData['usuario']}' eliminado con éxito. Sin embargo, la persona asociada ('{$userData['nombre']} {$userData['apellido_paterno']}') no fue eliminada porque tiene otras relaciones: " . implode(", ", $personRelationsMessages) . ".";
                error_log("Info: Persona con ID {$persona_id_asociada} no eliminada debido a otras relaciones.");
            } else {
                // Si la persona NO tiene otras relaciones, la eliminamos.
                $stmtDeletePersona = $pdo->prepare("DELETE FROM persona WHERE persona_id = :persona_id");
                $stmtDeletePersona->execute([':persona_id' => $persona_id_asociada]);
                $mensaje_exito = "Usuario '{$userData['usuario']}' y persona asociada ('{$userData['nombre']} {$userData['apellido_paterno']}') eliminados con éxito.";
                error_log("Persona con ID {$persona_id_asociada} eliminada.");
            }
        } else {
            $mensaje_exito = "Usuario '{$userData['usuario']}' eliminado con éxito. No se encontró una persona asociada válida (esto podría indicar un problema de integridad en la base de datos).";
            error_log("Advertencia: Usuario {$usuario_empleado_id_a_eliminar} eliminado, pero no se encontró persona_id asociada.");
        }

        $pdo->commit(); // Confirmar la transacción si todo fue exitoso
        error_log("Transacción de eliminación de usuario completada con éxito.");
        header("Location: " . $redirect_to . "?status=success&mensaje=" . urlencode($mensaje_exito));
        exit();
    }

} catch (PDOException $e) {
    $pdo->rollBack(); // Deshacer la transacción en caso de cualquier error
    $errorMessage = "Error de base de datos al intentar eliminar el usuario: " . $e->getMessage();
    // Error SQLSTATE 23000 es para violación de integridad (FK). Si nuestra lógica de conteo falló, esto lo atraparía.
    if ($e->getCode() === '23000') {
         $errorMessage = "Error: No se pudo eliminar el usuario debido a relaciones existentes en la base de datos (problema de integridad referencial). Revise los registros asociados.";
    }
    error_log("ERROR CRÍTICO: Falló la eliminación del usuario {$usuario_empleado_id_a_eliminar}: " . $errorMessage);
    header("Location: " . $redirect_to . "?status=error&mensaje=" . urlencode($errorMessage));
    exit();
}

// Este punto no debería ser alcanzado si todo funciona correctamente, ya que todas las rutas tienen un 'exit()'.
error_log("ADVERTENCIA: Script eliminar_usuario.php alcanzó el final sin redirección explícita. Esto es inesperado.");
?>