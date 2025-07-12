<?php
session_start();
include("config.php"); // Asegúrate de que 'config.php' contenga la conexión PDO.

// Verificar si el usuario está logueado
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}

$error = '';
$personas = [];
$search_query = '';

// Manejar búsqueda de personas
if (isset($_GET['search'])) {
    $search_query = trim($_GET['search']);
    try {
        // Prepara la consulta para buscar en varios campos
        $stmt = $pdo->prepare("SELECT persona_id, nombre, apellido_paterno, apellido_materno, email, telefono 
                               FROM persona 
                               WHERE nombre LIKE :search OR apellido_paterno LIKE :search OR apellido_materno LIKE :search OR email LIKE :search OR telefono LIKE :search
                               ORDER BY nombre, apellido_paterno");
        $stmt->execute([':search' => '%' . $search_query . '%']);
        $personas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $error = "Error al buscar personas: " . $e->getMessage();
    }
} else {
    // Si no hay búsqueda, listar todas las personas
    try {
        $stmt = $pdo->query("SELECT persona_id, nombre, apellido_paterno, apellido_materno, email, telefono FROM persona ORDER BY nombre, apellido_paterno");
        $personas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $error = "Error al cargar personas: " . $e->getMessage();
    }
}

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Seleccionar Persona</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/estilos.css?v=<?= time(); ?>">
    <style>
        body {
            background-color: #f8f9fa; /* Fondo claro general */
        }
        .container {
            margin-top: 50px;
            margin-bottom: 50px;
        }
        .main-title {
            text-align: center;
            margin-bottom: 30px;
            color: #343a40;
        }
        .card {
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            border-radius: 8px;
        }
        /* Estilos para el tema oscuro (si los tienes definidos en estilos.css o en el body) */
        body.dark-mode {
            background-color: #343a40;
            color: #f8f9fa;
        }
        body.dark-mode .main-title {
            color: #f8f9fa;
        }
        body.dark-mode .card {
            background-color: #545b62;
            border-color: #6c757d;
        }
        body.dark-mode .form-control, body.dark-mode .form-select {
            background-color: #6c757d;
            color: #f8f9fa;
            border-color: #495057;
        }
        body.dark-mode .form-control::placeholder {
            color: #ced4da;
        }
        body.dark-mode .form-control:focus, body.dark-mode .form-select:focus {
            background-color: #7a8288;
            color: #f8f9fa;
            border-color: #889299;
            box-shadow: 0 0 0 0.25rem rgba(108, 117, 125, 0.25);
        }
        body.dark-mode .table {
            color: #f8f9fa; /* Color de texto para la tabla en modo oscuro */
        }
        body.dark-mode .table th {
            border-bottom-color: #7a8288; /* Color de borde para encabezados en modo oscuro */
        }
        body.dark-mode .table td {
            border-top-color: #7a8288; /* Color de borde para celdas en modo oscuro */
        }
        body.dark-mode .table-striped > tbody > tr:nth-of-type(odd) > * {
            background-color: rgba(0, 0, 0, 0.05); /* Ligeramente más oscuro para filas impares */
        }
        body.dark-mode .table-hover tbody tr:hover {
            background-color: #6c757d; /* Fondo al pasar el ratón en modo oscuro */
        }
    </style>
</head>
<body>
<div class="container">
    <h3 class="main-title">Seleccionar Persona Existente</h3>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="card p-4 mb-4">
        <form method="GET" class="row g-3 align-items-center">
            <div class="col-md-8">
                <input type="text" name="search" class="form-control" placeholder="Buscar por nombre, apellido, email o teléfono..." value="<?= htmlspecialchars($search_query) ?>">
            </div>
            <div class="col-md-4 d-grid">
                <button type="submit" class="btn btn-primary">Buscar</button>
            </div>
        </form>
    </div>

    <div class="card p-4">
        <?php if (empty($personas)): ?>
            <div class="alert alert-warning text-center">No se encontraron personas con los criterios de búsqueda.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover table-striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nombre Completo</th>
                            <th>Email</th>
                            <th>Teléfono</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($personas as $persona): ?>
                            <tr>
                                <td><?= htmlspecialchars($persona['persona_id']) ?></td>
                                <td><?= htmlspecialchars($persona['nombre'] . ' ' . $persona['apellido_paterno'] . ' ' . $persona['apellido_materno']) ?></td>
                                <td><?= htmlspecialchars($persona['email']) ?></td>
                                <td><?= htmlspecialchars($persona['telefono']) ?></td>
                                <td>
                                    <a href="nuevo_proveedor.php?persona_id=<?= htmlspecialchars($persona['persona_id']) ?>" class="btn btn-sm btn-success">Seleccionar</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        <div class="d-flex justify-content-end mt-3">
            <a href="nuevo_proveedor.php" class="btn btn-secondary">Volver Atras</a>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Lógica para el Modo Oscuro (se aplica al cargar la página si la preferencia está guardada)
    document.addEventListener('DOMContentLoaded', (event) => {
        const body = document.body;
        if (localStorage.getItem('darkMode') === 'enabled') {
            body.classList.add('dark-mode');
        }
    });
</script>
</body>
</html>