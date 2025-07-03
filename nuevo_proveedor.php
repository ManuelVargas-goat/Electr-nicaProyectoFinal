<?php
session_start();
include("config.php");

if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}

$usuario = $_SESSION['usuario'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre_empresa = trim($_POST['nombre_empresa']);
    $persona_id = (int)$_POST['persona_id'];
    $pais = trim($_POST['pais']);
    $ciudad = trim($_POST['ciudad']);
    $direccion = trim($_POST['direccion']);
    $codigo_postal = trim($_POST['codigo_postal']);

    if (!empty($nombre_empresa)) {
        $sql = "INSERT INTO proveedor (nombre_empresa, persona_id, pais, ciudad, direccion, codigo_postal)
                VALUES (:nombre_empresa, :persona_id, :pais, :ciudad, :direccion, :codigo_postal)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':nombre_empresa' => $nombre_empresa,
            ':persona_id' => $persona_id,
            ':pais' => $pais,
            ':ciudad' => $ciudad,
            ':direccion' => $direccion,
            ':codigo_postal' => $codigo_postal
        ]);
        header("Location: gestion_catalogo_proveedores.php");
        exit();
    } else {
        $error = "El nombre de la empresa es obligatorio.";
    }
}

// Obtener personas para asociar con proveedor
$personas = $pdo->query("SELECT persona_id, nombre, apellido_paterno FROM persona ORDER BY nombre")->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Nuevo Proveedor</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="css/estilos.css?v=<?= time(); ?>">
</head>
<body>
<div class="container mt-5">
  <h3 class="main-title">Registrar Nuevo Proveedor</h3>

  <?php if (isset($error)): ?>
    <div class="alert alert-danger"><?= $error ?></div>
  <?php endif; ?>

  <form method="POST" class="card p-4">
    <div class="mb-3">
      <label for="nombre_empresa" class="form-label">Nombre de Empresa</label>
      <input type="text" name="nombre_empresa" id="nombre_empresa" class="form-control" required>
    </div>

    <div class="mb-3">
      <label for="persona_id" class="form-label">Representante (Persona)</label>
      <select name="persona_id" id="persona_id" class="form-select" required>
        <option value="">Seleccione una persona</option>
        <?php foreach ($personas as $persona): ?>
          <option value="<?= $persona['persona_id'] ?>">
            <?= htmlspecialchars($persona['nombre'] . ' ' . $persona['apellido_paterno']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="mb-3">
  <label for="pais" class="form-label">País</label>
  <select name="pais" id="pais" class="form-select" required>
    <option value="">Seleccione un país</option>
    <option value="Argentina">Argentina</option>
    <option value="Bolivia">Bolivia</option>
    <option value="Brasil">Brasil</option>
    <option value="Chile">Chile</option>
    <option value="Colombia">Colombia</option>
    <option value="Ecuador">Ecuador</option>
    <option value="México">México</option>
    <option value="Panamá">Panamá</option>
    <option value="Paraguay">Paraguay</option>
    <option value="Perú">Perú</option>
    <option value="Uruguay">Uruguay</option>
    <option value="Venezuela">Venezuela</option>
    <option value="Estados Unidos">Estados Unidos</option>
    <option value="España">España</option>
    <option value="Francia">Francia</option>
    <option value="Italia">Italia</option>
    <option value="Alemania">Alemania</option>
  </select>
</div>

    <div class="mb-3">
  <label for="ciudad" class="form-label">Ciudad</label>
  <select name="ciudad" id="ciudad" class="form-select" required>
    <option value="">Seleccione un país primero</option>
  </select>
</div>

    <div class="mb-3">
      <label for="direccion" class="form-label">Dirección</label>
      <textarea name="direccion" id="direccion" class="form-control" rows="2" required></textarea>
    </div>

    <div class="mb-3">
      <label for="codigo_postal" class="form-label">Código Postal</label>
      <input type="text" name="codigo_postal" id="codigo_postal" class="form-control">
    </div>

    <div class="d-flex justify-content-between">
      <a href="gestion_catalogo_proveedores.php" class="btn btn-secondary">Cancelar</a>
      <button type="submit" class="btn btn-primary">Registrar</button>
    </div>
  </form>
</div>
<script>
  const ciudadesPorPais = {
    "Perú": ["Arequipa", "Lima", "Cusco", "Trujillo", "Piura"],
    "Colombia": ["Bogotá", "Medellín", "Cali", "Barranquilla"],
    "Argentina": ["Buenos Aires", "Córdoba", "Rosario", "Mendoza"],
    "Chile": ["Santiago", "Valparaíso", "Concepción"],
    "México": ["Ciudad de México", "Guadalajara", "Monterrey"],
    "España": ["Madrid", "Barcelona", "Valencia"],
    "Estados Unidos": ["New York", "Los Angeles", "Chicago"],
    "Otro": ["Otra"]
  };

  const paisSelect = document.getElementById('pais');
  const ciudadSelect = document.getElementById('ciudad');

  paisSelect.addEventListener('change', function () {
    const paisSeleccionado = this.value;
    ciudadSelect.innerHTML = '<option value="">Seleccione una ciudad</option>';

    if (ciudadesPorPais[paisSeleccionado]) {
      ciudadesPorPais[paisSeleccionado].forEach(ciudad => {
        const opcion = document.createElement('option');
        opcion.value = ciudad;
        opcion.textContent = ciudad;
        ciudadSelect.appendChild(opcion);
      });
    } else {
      const opcion = document.createElement('option');
      opcion.value = "Otra";
      opcion.textContent = "Otra";
      ciudadSelect.appendChild(opcion);
    }
  });
</script>
</body>
</html>
