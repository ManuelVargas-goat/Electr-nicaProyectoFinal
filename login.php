<?php
session_start();
include("config.php"); // Asegúrate de que 'config.php' contenga la conexión PDO.

$mensaje = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $usuario_ingresado = $_POST['usuario'];
    $clave_ingresada = $_POST['clave'];

    try {
        // 1. Obtener el hash de la contraseña almacenado y el rol del usuario
        $sql = "SELECT ue.usuario, ue.clave, ue.estado, r.nombre AS rol
                FROM usuario_empleado ue
                JOIN rol r ON ue.rol_id = r.rol_id
                WHERE ue.usuario = :usuario";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':usuario' => $usuario_ingresado]);
        $datos_usuario_db = $stmt->fetch(PDO::FETCH_ASSOC);

        // 2. Verificar si el usuario existe y si la contraseña es correcta
        if ($datos_usuario_db) {
            // Verificar el estado de la cuenta
            if (!$datos_usuario_db['estado']) {
                $mensaje = "❌ Su cuenta está inactiva. Contacte al administrador.";
            } elseif (password_verify($clave_ingresada, $datos_usuario_db['clave'])) {
                // Contraseña correcta
                $_SESSION['usuario'] = $datos_usuario_db['usuario'];
                $_SESSION['rol'] = $datos_usuario_db['rol'];

                header("Location: principal.php");
                exit;
            } else {
                // Contraseña incorrecta
                $mensaje = "❌ Usuario o contraseña incorrectos.";
            }
        } else {
            // Usuario no encontrado
            $mensaje = "❌ Usuario o contraseña incorrectos.";
        }
    } catch (PDOException $e) {
        error_log("Error en el login: " . $e->getMessage());
        $mensaje = "❌ Error interno del servidor. Intente de nuevo más tarde.";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login</title>
    <link href="[https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css](https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css)" rel="stylesheet">
    <style>
        body {
            background-color: #1e1e1e;
            color: #fff;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            /* Mejora: Fondo con imagen sutil y degradado */
            background-image: linear-gradient(rgba(0, 0, 0, 0.7), rgba(0, 0, 0, 0.7)), url('[https://placehold.co/1920x1080/1a1a1a/ffffff?text=Fondo+de+Login](https://placehold.co/1920x1080/1a1a1a/ffffff?text=Fondo+de+Login)'); /* Placeholder para una imagen de fondo más formal */
            background-size: cover;
            background-position: center;
            font-family: 'Roboto', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; /* Fuente más profesional */
        }
        .login-box {
            max-width: 380px; /* Ancho más pequeño para un aspecto más compacto */
            width: 90%; /* Ajuste para responsividad */
            background-color: #2a2a2a; /* Fondo más sólido y oscuro */
            padding: 40px; /* Menos padding para un diseño más limpio */
            border-radius: 15px; /* Bordes más redondeados y suaves */
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.4); /* Sombra más sutil y profunda */
            border: 1px solid rgba(70, 70, 70, 0.2); /* Borde sutil */
            text-align: center; /* Centrar el contenido de la caja */
        }
        h4 {
            font-weight: 600; /* Texto más negrita */
            color: #f0f0f0; /* Color de título ligeramente más claro */
            margin-bottom: 30px !important; /* Menos espacio debajo del título */
            font-size: 1.6rem; /* Tamaño de fuente del título ligeramente más pequeño */
        }
        input.form-control {
            background-color: #3a3a3a;
            border: 1px solid #555;
            color: #fff;
            padding: 10px 15px; /* Menos padding en los inputs */
            border-radius: 8px; /* Bordes más redondeados */
            font-size: 0.95rem; /* Tamaño de fuente ligeramente más pequeño */
        }
        input.form-control:focus {
            background-color: #4a4a4a;
            border-color: #007bff; /* Borde azul al enfocar */
            box-shadow: 0 0 0 0.25rem rgba(0, 123, 255, 0.25); /* Sombra azul al enfocar */
        }
        label {
            color: #ccc;
            font-weight: 500; /* Texto de etiqueta más negrita */
            margin-bottom: 8px; /* Menos espacio debajo de la etiqueta */
            display: block; /* Asegura que la etiqueta esté en su propia línea */
            text-align: left; /* Alinea las etiquetas a la izquierda */
        }
        .btn-primary {
            background-color: #007bff;
            border-color: #007bff;
            padding: 12px 20px; /* Botón más pequeño */
            font-size: 1.05rem; /* Texto del botón más pequeño */
            border-radius: 8px; /* Bordes más redondeados */
            transition: background-color 0.3s ease, border-color 0.3s ease, box-shadow 0.3s ease;
            width: 100%; /* Botón ocupa todo el ancho */
        }
        .btn-primary:hover {
            background-color: #0056b3;
            border-color: #0056b3;
            box-shadow: 0 5px 15px rgba(0, 123, 255, 0.4); /* Sombra al pasar el ratón */
        }
        .alert-danger {
            background-color: #dc3545;
            color: #fff;
            border-color: #dc3545;
            padding: 8px 15px; /* Padding ajustado para el mensaje de error */
            border-radius: 8px;
            font-size: 0.9rem;
            margin-bottom: 15px; /* Menos espacio debajo del mensaje de error */
        }
        .text-info {
            color: #17a2b8 !important;
            font-size: 0.9rem; /* Tamaño de texto ligeramente más pequeño */
            display: block; /* Asegura que esté en su propia línea */
            margin-top: 10px; /* Menos espacio encima del enlace */
        }
        .text-info:hover {
            color: #117a8b !important;
            text-decoration: underline !important; /* Subrayado al pasar el ratón */
        }
        .d-grid {
            margin-top: 20px; /* Menos espacio encima del botón */
        }
    </style>
</head>
<body>
    <div class="login-box">
        <h4 class="mb-4 text-center">Acceso de Administrador</h4>
        <form method="POST">
            <div class="mb-3">
                <label for="usuario" class="form-label">Nombre de Usuario</label>
                <input type="text" class="form-control" id="usuario" name="usuario" required>
            </div>
            <div class="mb-3">
                <label for="clave" class="form-label">Contraseña</label>
                <input type="password" class="form-control" id="clave" name="clave" required>
            </div>
            <?php if ($mensaje): ?>
                <div class="alert alert-danger py-1"><?= $mensaje ?></div>
            <?php endif; ?>
            <div class="d-grid">
                <button type="submit" class="btn btn-primary">INGRESAR</button>
            </div>
            <div class="text-end mt-3">
                <a href="recuperar_cuenta.php" class="text-info text-decoration-none">¿Olvidaste tu contraseña?</a>
            </div>
        </form>
    </div>
</body>
</html>