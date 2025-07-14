<?php
session_start(); // Inicia la sesión al principio
include("config.php"); // Asegúrate de que 'config.php' contenga la conexión PDO.

// =================================================================
// VERIFICACIÓN DE PERMISOS
// =================================================================
if (!isset($_SESSION['usuario'])) {
    $_SESSION['alert_message'] = "Acceso denegado. Debes iniciar sesión para realizar esta acción."; // Cambiado a alert_message
    $_SESSION['alert_type'] = "danger"; // Cambiado a alert_type
    header("Location: gestion_usuarios.php"); // Redirigir de vuelta
    exit();
}

// Obtener el ID de empleado de la sesión si existe, para la prevención de auto-eliminación
$loggedInEmployeeId = null;
// Asumiendo que 'usuario_empleado_id' es el ID de la persona empleado logueada
// Debes asegurarte de que este valor se guarde en la sesión al loguearse.
if (isset($_SESSION['usuario_empleado_id'])) {
    $loggedInEmployeeId = $_SESSION['usuario_empleado_id'];
}

// Solo procesamos si el método de solicitud es POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
    $user_type = filter_input(INPUT_POST, 'user_type', FILTER_SANITIZE_STRING);

    if ($user_id === false || !in_array($user_type, ['empleado', 'cliente'])) {
        $_SESSION['alert_message'] = "Datos de usuario no válidos o incompletos para eliminar.";
        $_SESSION['alert_type'] = "danger";
        header("Location: gestion_usuarios.php");
        exit();
    }

    $canDelete = true;
    $errorMessage = "";
    $persona_id = null; // Inicializar persona_id

    try {
        if ($user_type === 'empleado') {
            // Prevention: Don't allow the logged-in user (employee) to delete themselves
            if ($loggedInEmployeeId !== null && $loggedInEmployeeId == $user_id) {
                $canDelete = false;
                $errorMessage = "No puedes eliminar tu propia cuenta de empleado.";
            } else {
                // Get persona_id and rol for usuario_empleado
                $stmt_get_employee_data = $pdo->prepare("SELECT ue.persona_id, r.nombre AS rol_nombre FROM usuario_empleado ue JOIN rol r ON ue.rol_id = r.rol_id WHERE ue.usuario_empleado_id = :id");
                $stmt_get_employee_data->execute([':id' => $user_id]);
                $employee_data = $stmt_get_employee_data->fetch(PDO::FETCH_ASSOC);

                if ($employee_data) {
                    $persona_id = $employee_data['persona_id'];
                    $rol_empleado = strtolower($employee_data['rol_nombre']);

                    // Further prevention: Don't allow deletion of an 'administrador'
                    if ($rol_empleado === 'administrador' || $rol_empleado === 'admin') {
                        $canDelete = false;
                        $errorMessage = "No se puede eliminar un usuario con rol de 'Administrador' directamente.";
                    }
                } else {
                    $canDelete = false;
                    $errorMessage = "Usuario empleado no encontrado.";
                }

                if ($canDelete) {
                    // =================================================================
                    // ASSOCIATION CHECKS FOR usuario_empleado
                    // =================================================================
                    $stmtPedidos = $pdo->prepare("SELECT COUNT(*) FROM pedido WHERE usuario_empleado_id = :id");
                    $stmtPedidos->execute([':id' => $user_id]);
                    $pedidosAsociados = $stmtPedidos->fetchColumn();
                    if ($pedidosAsociados > 0) {
                        $canDelete = false;
                        $errorMessage .= "El empleado ha registrado " . $pedidosAsociados . " pedido(s). ";
                    }

                    $stmtDevolucionesEmpleado = $pdo->prepare("SELECT COUNT(*) FROM devolucion WHERE usuario_empleado_id = :id");
                    $stmtDevolucionesEmpleado->execute([':id' => $user_id]);
                    $devolucionesEmpleadoAsociadas = $stmtDevolucionesEmpleado->fetchColumn();
                    if ($devolucionesEmpleadoAsociadas > 0) {
                        $canDelete = false;
                        $errorMessage .= "El empleado ha gestionado " . $devolucionesEmpleadoAsociadas . " devolución(es). ";
                    }
                }
            }
        } elseif ($user_type === 'cliente') {
            // Get persona_id for usuario_cliente
            $stmt_get_person_id = $pdo->prepare("SELECT persona_id FROM usuario_cliente WHERE usuario_cliente_id = :id");
            $stmt_get_person_id->execute([':id' => $user_id]);
            $persona_data = $stmt_get_person_id->fetch(PDO::FETCH_ASSOC);

            if ($persona_data) {
                $persona_id = $persona_data['persona_id'];
            } else {
                $canDelete = false;
                $errorMessage = "Usuario cliente no encontrado.";
            }

            if ($canDelete) {
                // =================================================================
                // ASSOCIATION CHECKS FOR usuario_cliente
                // =================================================================
                $stmtCompras = $pdo->prepare("SELECT COUNT(*) FROM compra WHERE usuario_cliente_id = :id");
                $stmtCompras->execute([':id' => $user_id]);
                $comprasAsociadas = $stmtCompras->fetchColumn();
                if ($comprasAsociadas > 0) {
                    $canDelete = false;
                    $errorMessage .= "El cliente ha realizado " . $comprasAsociadas . " compra(s). ";
                }

                $stmtDevolucionesCliente = $pdo->prepare("SELECT COUNT(*) FROM devolucion WHERE usuario_cliente_id = :id");
                $stmtDevolucionesCliente->execute([':id' => $user_id]);
                $devolucionesClienteAsociadas = $stmtDevolucionesCliente->fetchColumn();
                if ($devolucionesClienteAsociadas > 0) {
                    $canDelete = false;
                    $errorMessage .= "El cliente ha realizado " . $devolucionesClienteAsociadas . " devolución(es). ";
                }
            }
        }

        // =================================================================
        // PROCEED WITH DELETION IF POSSIBLE (Transaction starts here)
        // =================================================================
        if (!$canDelete) {
            $_SESSION['alert_message'] = "No se puede eliminar el usuario. " . $errorMessage . " Considere desactivar la cuenta en su lugar.";
            $_SESSION['alert_type'] = "danger";
        } else {
            // Si todo está bien y podemos eliminar, INICIAMOS LA TRANSACCIÓN AQUÍ
            $pdo->beginTransaction();

            if ($user_type === 'empleado') {
                $delete_stmt = $pdo->prepare("DELETE FROM usuario_empleado WHERE usuario_empleado_id = :id");
            } else { // cliente
                $delete_stmt = $pdo->prepare("DELETE FROM usuario_cliente WHERE usuario_cliente_id = :id");
            }
            $delete_stmt->execute([':id' => $user_id]);

            // Eliminar de la tabla persona si persona_id fue obtenido y no está asociada a otro usuario/proveedor
            if ($persona_id) {
                // Validamos si la persona_id está asociada a algún otro empleado, cliente o proveedor
                $stmt_check_other_uses = $pdo->prepare("
                    SELECT COUNT(*) FROM usuario_empleado WHERE persona_id = :persona_id
                    UNION ALL
                    SELECT COUNT(*) FROM usuario_cliente WHERE persona_id = :persona_id
                    UNION ALL
                    SELECT COUNT(*) FROM proveedor WHERE persona_id = :persona_id
                ");
                $stmt_check_other_uses->execute([':persona_id' => $persona_id]);
                $total_uses = 0;
                while ($row = $stmt_check_other_uses->fetchColumn()) {
                    $total_uses += $row;
                }

                // Si después de eliminar el usuario actual, la persona_id ya no es referenciada, se elimina.
                if ($total_uses == 0) {
                    $delete_persona_stmt = $pdo->prepare("DELETE FROM persona WHERE persona_id = :persona_id");
                    $delete_persona_stmt->execute([':persona_id' => $persona_id]);
                }
            }

            $pdo->commit(); // Confirma la transacción
            $_SESSION['alert_message'] = "Usuario (" . $user_type . ") y sus datos personales eliminados con éxito.";
            $_SESSION['alert_type'] = "success";
        }

    } catch (PDOException $e) {
        // Si una transacción está activa, haz rollback
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['alert_message'] = "Error de base de datos al eliminar usuario: " . $e->getMessage();
        $_SESSION['alert_type'] = "danger";
        error_log("Error de BD al eliminar usuario (eliminar_usuario.php): " . $e->getMessage());
    } catch (Exception $e) {
        // Captura cualquier otra excepción general
        if ($pdo->inTransaction()) { // Si por algún motivo se inició una transacción y falló
            $pdo->rollBack();
        }
        $_SESSION['alert_message'] = "Error inesperado al eliminar usuario: " . $e->getMessage();
        $_SESSION['alert_type'] = "danger";
        error_log("Error inesperado al eliminar usuario (eliminar_usuario.php): " . $e->getMessage());
    }
} else {
    $_SESSION['alert_message'] = "Método de solicitud no permitido.";
    $_SESSION['alert_type'] = "danger";
}

// Redirigir siempre de vuelta a gestion_usuarios.php
header("Location: gestion_usuarios.php");
exit();
?>