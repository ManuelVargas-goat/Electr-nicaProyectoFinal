<?php
session_start();
include("config.php");

$mensaje = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['codigo'])) {
        if ($_POST['codigo'] == $_SESSION['codigo_verificacion']) {
            $_SESSION['codigo_validado'] = true;
        } else {
            $mensaje = "❌ Código incorrecto.";
        }
    } elseif (isset($_POST['nueva_clave'])) {
        if ($_POST['nueva_clave'] === $_POST['confirmar_clave']) {
            $usuario = $_SESSION['usuario_para_reset'];
            $nueva_clave = $_POST['nueva_clave'];

            $sql = "UPDATE usuario_empleado SET clave = :clave WHERE usuario = :usuario";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':clave' => $nueva_clave,
                ':usuario' => $usuario
            ]);

            session_unset();
            session_destroy();
            header("Location: login.php?msg=clave_actualizada");
            exit;
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

    <?php if (isset($_SESSION['codigo_validado']) && $_SESSION['codigo_validado']): ?>
    <form method="POST">
        <div class="mb-3">
            <label for="nueva_clave">Nueva Contraseña:</label>
            <input type="password" class="form-control" name="nueva_clave" required>
        </div>
        <div class="mb-3">
            <label for="confirmar_clave">Confirmar Nueva Contraseña:</label>
            <input type="password" class="form-control" name="confirmar_clave" required>
        </div>
        <div class="d-grid">
            <button type="submit" class="btn btn-primary">Enviar</button>
        </div>
    </form>
    <?php else: ?>
        <div class="alert alert-danger">Código no validado. <a href="recuperar.php" class="text-info">Volver</a></div>
    <?php endif; ?>

    <?php if ($mensaje): ?>
        <div class="alert alert-warning mt-3"><?= $mensaje ?></div>
    <?php endif; ?>
  </div>
</body>
</html>
