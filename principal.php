<?php
session_start();
if (!isset($_SESSION['usuario'])) {
    header('Location: login.php');
    exit();
}

include("config.php");

$usuario = $_SESSION['usuario'];
$rol = $_SESSION['rol'];
$nombre_persona = 'Usuario';

// Consultar nombre completo
$sql = "SELECT per.nombre || ' ' || per.apellido_paterno AS nombre_completo
        FROM usuario_empleado ue
        JOIN persona per ON ue.persona_id = per.persona_id
        WHERE ue.usuario = :usuario";
$stmt = $pdo->prepare($sql);
$stmt->execute([':usuario' => $usuario]);
$nombre_persona = $stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Admin Panel - Electrónica</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/estilos.css?v=<?= time(); ?>">
</head>
<body>

<div class="container-fluid">
  <div class="row">
    <div class="col-md-2 sidebar">
        <div class="user-box text-center">
            <img src="https://cdn-icons-png.flaticon.com/512/149/149071.png" alt="Usuario" width="60" class="mb-2">
            <p class="mb-0 fw-bold"><?= htmlspecialchars($nombre_persona); ?></p>
            <?php if ($rol === 'admin'): ?>
                <small class="text-warning">Administrador</small>
            <?php endif; ?>
        </div>

        <a href="productos.php">Gestión de Productos</a>
        <a href="usuarios.php">Gestión de Usuarios</a>
        <a href="gestion_existencias_pedidos.php">Gestión de Existencias</a>
        <a href="#">Configuración</a>
        <a href="salir.php" class="text-danger">Cerrar Sesión</a>
    </div>

    <div class="col-md-10 content">
        <h2 class="main-title mb-4">Comunicados del Sistema</h2>

        <div class="card mb-3">
            <div class="card-body">
                <h5 class="card-title text-primary">📢 Mantenimiento programado</h5>
                <p class="card-text">El sistema estará en mantenimiento el sábado 8 de junio desde las 10:00 p.m. hasta las 2:00 a.m.</p>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-body">
                <h5 class="card-title text-success">✅ Nuevas funcionalidades</h5>
                <p class="card-text">Se ha habilitado la opción de gestionar devoluciones desde el panel de existencias.</p>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-body">
                <h5 class="card-title text-danger">🚨 Importante</h5>
                <p class="card-text">Recuerda cerrar sesión al terminar tus actividades para proteger tu información.</p>
            </div>
        </div>
    </div>
  </div>
</div>

</body>
</html>

