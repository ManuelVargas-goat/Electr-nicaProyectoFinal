<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bienvenido a Nuestro Sistema</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/estilos.css?v=<?= time(); ?>">
    <style>
        body {
            background-color: #f8f9fa; /* Color de fondo claro similar al de Bootstrap */
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            font-family: Arial, sans-serif; /* Fuente consistente */
        }
        .welcome-container {
            background-color: #ffffff;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            text-align: center;
            max-width: 500px;
            width: 90%;
        }
        .welcome-container h1 {
            margin-bottom: 30px;
            color: #343a40; /* Color de texto oscuro */
        }
        .welcome-container .btn {
            padding: 12px 25px;
            font-size: 1.1rem;
            margin: 10px;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <div class="welcome-container">
        <h1 class="mb-4">Bienvenido a Nuestro Sistema</h1>
        <p class="lead mb-4">Elige cómo quieres acceder:</p>
        <div class="d-grid gap-3">
            <a href="ecommerce/Inicio_Principal.php" class="btn btn-info btn-lg">
                🛒 Ir a la Tienda
            </a>
            
            <a href="login.php" class="btn btn-primary btn-lg">
                🔑 Iniciar Sesión (Empleados/Administradores)
            </a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>