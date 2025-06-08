<?php
include 'config.php';

// Manejo de búsqueda
try {
    $searchTerm = '';
    if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['buscar'])) {
        $searchTerm = trim($_GET['buscar']);
    }

    // Filtrar usuarios por búsqueda
    $sql = "SELECT id, usuario, rol 
            FROM usuarios 
            WHERE usuario ILIKE :searchTerm OR rol ILIKE :searchTerm";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['searchTerm' => '%' . $searchTerm . '%']);
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Error en la base de datos: " . $e->getMessage());
}

// Manejo de creación de usuario
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['crear'])) {
    $usuario = trim($_POST['usuario']);
    $clave = password_hash($_POST['clave'], PASSWORD_DEFAULT);
    $rol = $_POST['rol'];

    try {
        // Verificar si el usuario ya existe
        $sql = "SELECT * FROM usuarios WHERE usuario = :usuario";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['usuario' => $usuario]);
        $existing_user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing_user) {
            $error = "El usuario ya existe. Intente con otro nombre de usuario.";
        } else {
            // Crear el nuevo usuario
            $sql = "INSERT INTO usuarios (usuario, clave, rol) VALUES (:usuario, :clave, :rol)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['usuario' => $usuario, 'clave' => $clave, 'rol' => $rol]);

            // Redirigir para evitar reenvío del formulario
            header('Location: usuarios.php');
            exit();
        }
    } catch (PDOException $e) {
        die("Error al crear el usuario: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Usuarios</title>
    <link rel="stylesheet" href="css/estilos.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
</head>
<body>
    <div class="container mt-5">
        <h2 class="text-center">Gestionar Usuarios</h2>
        
        <!-- Formulario de búsqueda -->
        <form action="usuarios.php" method="GET" class="mb-4">
            <div class="form-row">
                <div class="col">
                    <input type="text" class="form-control" name="buscar" placeholder="Buscar por usuario o rol" value="<?php echo htmlspecialchars($searchTerm); ?>">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary">Buscar</button>
                </div>
            </div>
        </form>

        <!-- Mensaje de error -->
        <?php if (isset($error)): ?>
            <div class="alert alert-danger text-center"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- Formulario para crear usuario -->
        <form action="usuarios.php" method="POST" class="mb-4">
            <div class="form-group">
                <label for="usuario">Usuario:</label>
                <input type="text" class="form-control" id="usuario" name="usuario" required>
            </div>
            <div class="form-group">
                <label for="clave">Clave:</label>
                <input type="password" class="form-control" id="clave" name="clave" required>
            </div>
            <div class="form-group">
                <label for="rol">Rol:</label>
                <select class="form-control" id="rol" name="rol" required>
                    <option value="admin">Administrador</option>
                    <option value="empleado">Empleado</option>
                </select>
            </div>
            <button type="submit" name="crear" class="btn btn-success">Crear Usuario</button>
        </form>

        <!-- Tabla de usuarios -->
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Usuario</th>
                    <th>Rol</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($usuarios as $usuario): ?>
                    <tr>
                        <td><?= $usuario['id']; ?></td>
                        <td><?= htmlspecialchars($usuario['usuario']); ?></td>
                        <td><?= ucfirst($usuario['rol']); ?></td>
                        <td>
                            <a href="editar_usuario.php?id=<?= $usuario['id']; ?>" class="btn btn-primary">Editar</a>
                            <a href="eliminar_usuario.php?id=<?= $usuario['id']; ?>" class="btn btn-danger" 
                               onclick="return confirm('¿Estás seguro de eliminar este usuario?');">Eliminar</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <a class="btn btn-warning" href="principal.php">Inicio</a>
    </div>
</body>
</html>
