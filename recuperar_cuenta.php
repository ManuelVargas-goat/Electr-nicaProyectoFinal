<?php
session_start();
include("config.php");

$mensaje = '';
$codigo_enviado = false;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $correo = $_POST['correo'];
    $codigo = rand(100000, 999999);

    $sql = "SELECT p.persona_id, ue.usuario FROM persona p
            JOIN usuario_empleado ue ON ue.persona_id = p.persona_id
            WHERE p.email = :correo";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':correo' => $correo]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($usuario) {

        $_SESSION['codigo_verificacion'] = $codigo;
        $_SESSION['usuario_para_reset'] = $usuario['usuario'];



        $mensaje = "✅ Código enviado al correo (simulado: <b>$codigo</b>)";
        $codigo_enviado = true;
    } else {
        $mensaje = "❌ Correo no encontrado.";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Recuperar contraseña</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { background-color: #1e1e1e; color: #fff; }
    .form-box { max-width: 400px; margin: 80px auto; background-color: #2c2c2c; padding: 30px; border-radius: 8px; }
    input, label { color: #fff; }
  </style>
</head>
<body>
  <div class="form-box">
    <h4 class="text-center mb-4">Correo de Recuperación</h4>
    <form method="POST">
        <div class="mb-3">
            <label for="correo">Correo:</label>
            <input type="email" class="form-control" name="correo" required>
        </div>
        <div class="d-grid">
            <button type="submit" class="btn btn-primary">Enviar Código</button>
        </div>
    </form>

    <?php if ($codigo_enviado): ?>
    <form action="nueva_clave.php" method="POST" class="mt-4">
        <div class="mb-3">
            <label for="codigo">Código de Verificación:</label>
            <input type="text" class="form-control" name="codigo" required>
        </div>
        <div class="d-grid">
            <button type="submit" class="btn btn-success">Validar Código</button>
        </div>
    </form>
    <?php endif; ?>

    <?php if ($mensaje): ?>
        <div class="alert alert-info mt-3"><?= $mensaje ?></div>
    <?php endif; ?>
  </div>
</body>
</html>
