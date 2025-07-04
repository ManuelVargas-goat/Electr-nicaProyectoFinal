<?php
session_start();
include("config.php"); // Asegúrate de que 'config.php' contenga la conexión PDO.

$mensaje = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Lógica para verificar el código de verificación (primera etapa del reset)
    if (isset($_POST['codigo'])) {
        if (isset($_SESSION['codigo_verificacion']) && $_POST['codigo'] == $_SESSION['codigo_verificacion']) {
            $_SESSION['codigo_validado'] = true;
            $mensaje = "✅ Código verificado correctamente. Ahora puedes establecer tu nueva contraseña.";
        } else {
            $mensaje = "❌ Código incorrecto.";
            $_SESSION['codigo_validado'] = false; // Asegurar que no avance si el código es incorrecto
        }
    // Lógica para actualizar la contraseña (segunda etapa del reset)
    } elseif (isset($_POST['nueva_clave'])) {
        // Solo permitir la actualización si el código fue validado
        if (!isset($_SESSION['codigo_validado']) || !$_SESSION['codigo_validado']) {
            $mensaje = "Acceso denegado. El código de verificación no ha sido validado.";
            // Considera redirigir de vuelta a la página de verificación si no está validado
            // header("Location: recuperar.php"); 
            // exit;
        } elseif ($_POST['nueva_clave'] === $_POST['confirmar_clave']) {
            $usuario = $_SESSION['usuario_para_reset'] ?? null; // Asegúrate de que este valor esté seteado

            if ($usuario) {
                // ¡CORRECCIÓN AQUÍ! Encriptar la nueva clave con MD5 antes de guardarla
                $nueva_clave_hashed = md5($_POST['nueva_clave']);

                $sql = "UPDATE usuario_empleado SET clave = :clave WHERE usuario = :usuario";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':clave' => $nueva_clave_hashed, // Guardar la clave encriptada
                    ':usuario' => $usuario
                ]);

                // Limpiar sesiones relacionadas con el reset
                session_unset();
                session_destroy(); // Destruye toda la sesión

                header("Location: login.php?msg=clave_actualizada");
                exit;
            } else {
                $mensaje = "❌ No se pudo determinar el usuario para actualizar la contraseña. Por favor, reinicie el proceso.";
            }
        } else {
            $mensaje = "⚠️ Las contraseñas no coinciden.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Nueva Contraseña</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #1e1e1e; color: #fff; }
        .form-box { max-width: 400px; margin: 80px auto; background-color: #2c2c2c; padding: 30px; border-radius: 8px; }
        input, label { color: #fff; }
    </style>
</head>
<body>
    <div class="form-box">
        <h4 class="text-center mb-4">Nueva Contraseña</h4>

        <?php if ($mensaje && (!isset($_POST['nueva_clave']) || ($_POST['nueva_clave'] !== $_POST['confirmar_clave']))): ?>
            <div class="alert alert-warning mt-3"><?= $mensaje ?></div>
        <?php endif; ?>

        <?php if (isset($_SESSION['codigo_validado']) && $_SESSION['codigo_validado']): ?>
            <form method="POST">
                <div class="mb-3">
                    <label for="nueva_clave" class="form-label">Nueva Contraseña:</label>
                    <input type="password" class="form-control" name="nueva_clave" id="nueva_clave" required>
                </div>
                <div class="mb-3">
                    <label for="confirmar_clave" class="form-label">Confirmar Nueva Contraseña:</label>
                    <input type="password" class="form-control" name="confirmar_clave" id="confirmar_clave" required>
                </div>
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary">Actualizar Contraseña</button>
                </div>
            </form>
        <?php elseif (!isset($_POST['codigo']) && (!isset($_SESSION['codigo_validado']) || !$_SESSION['codigo_validado'])): ?>
            <div class="alert alert-danger">El código de verificación no ha sido validado.
                <a href="recuperar_cuenta.php" class="text-info">Volver al inicio del proceso de recuperación</a>.
            </div>
        <?php endif; ?>

        <?php if ($mensaje && (isset($_POST['nueva_clave']) && $_POST['nueva_clave'] === $_POST['confirmar_clave'])): ?>
            <div class="alert alert-success mt-3"><?= $mensaje ?></div>
        <?php endif; ?>
    </div>
</body>
</html>