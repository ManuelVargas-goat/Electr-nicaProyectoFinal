<?php
session_start();
include("config.php");

$mensaje = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $usuario = $_POST['usuario'];
    $clave = $_POST['clave'];

    // Consulta con JOIN a la tabla rol
    $sql = "SELECT ue.usuario, ue.clave, r.nombre AS rol
            FROM usuario_empleado ue
            JOIN rol r ON ue.rol_id = r.rol_id
            WHERE ue.usuario = :usuario AND ue.clave = :clave AND ue.estado = TRUE";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':usuario' => $usuario,
        ':clave' => $clave
    ]);

    if ($stmt->rowCount() == 1) {
        $datos = $stmt->fetch(PDO::FETCH_ASSOC);
        $_SESSION['usuario'] = $datos['usuario'];
        $_SESSION['rol'] = $datos['rol']; // ← Guardamos el rol aquí

        header("Location: principal.php");
        exit;
    } else {
        $mensaje = "❌ Usuario o contraseña incorrectos.";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Admin Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #1e1e1e;
            color: #fff;
        }
        .login-box {
            max-width: 400px;
            margin: 80px auto;
            background-color: #2c2c2c;
            padding: 30px;
            border-radius: 8px;
        }
        input, label {
            color: #fff;
        }
    </style>
</head>
<body>
    <div class="login-box">
        <h4 class="mb-4 text-center">Login Admin</h4>
        <form method="POST">
            <div class="mb-3">
                <label for="usuario" class="form-label">User Name</label>
                <input type="text" class="form-control" id="usuario" name="usuario" required>
            </div>
            <div class="mb-3">
                <label for="clave" class="form-label">Password</label>
                <input type="password" class="form-control" id="clave" name="clave" required>
            </div>
            <?php if ($mensaje): ?>
                <div class="alert alert-danger py-1"><?= $mensaje ?></div>
            <?php endif; ?>
            <div class="d-grid">
                <button type="submit" class="btn btn-primary">INGRESAR</button>
            </div>
        </form>
        <div class="text-end mt-3">
            <a href="recuperar.php" class="text-info text-decoration-none">Forgot Password?</a>
        </div>
    </div>
</body>
</html>
