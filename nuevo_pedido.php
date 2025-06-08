<?php
session_start();
include("config.php");

if (!isset($_SESSION['usuario'])) {
    header('Location: login.php');
    exit();
}

$usuario = $_SESSION['usuario'];

// Obtener ID del usuario_empleado
$sql = "SELECT usuario_empleado_id FROM usuario_empleado WHERE usuario = :usuario";
$stmt = $pdo->prepare($sql);
$stmt->execute([':usuario' => $usuario]);
$empleado = $stmt->fetch();

if (!$empleado) {
    die("Empleado no encontrado.");
}
$usuarioEmpleadoId = $empleado['usuario_empleado_id'];

// Listar Categorías, Productos y Proveedor_Producto
$categorias = $pdo->query("SELECT categoria_id, nombre FROM categoria ORDER BY nombre")->fetchAll();
$productos = $pdo->query("SELECT producto_id, nombre, categoria_id, precio FROM producto ORDER BY nombre")->fetchAll();
$proveedorProducto = $pdo->query("
    SELECT pp.producto_id, pr.proveedor_id, pr.nombre_empresa
    FROM proveedor_producto pp
    JOIN proveedor pr ON pr.proveedor_id = pp.proveedor_id
    ORDER BY pr.nombre_empresa
")->fetchAll();

// Registro del pedido
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $productoId = $_POST['producto_id'];
    $proveedorId = $_POST['proveedor_id'];
    $cantidad = $_POST['cantidad'];
    $precio = $_POST['precio_unidad'];

    if ($productoId && $cantidad && $precio && $proveedorId) {
        $pdo->beginTransaction();

        try {
            $sqlPedido = "INSERT INTO pedido (fecha_pedido, estado, usuario_empleado_id)
                          VALUES (CURRENT_DATE, 'pendiente', :usuario_id) RETURNING pedido_id";
            $stmt = $pdo->prepare($sqlPedido);
            $stmt->execute([':usuario_id' => $usuarioEmpleadoId]);
            $pedidoId = $stmt->fetchColumn();

            $sqlDetalle = "INSERT INTO detalle_pedido (pedido_id, producto_id, proveedor_id, cantidad, precio_unidad)
                           VALUES (:pedido_id, :producto_id, :proveedor_id, :cantidad, :precio)";
            $stmt = $pdo->prepare($sqlDetalle);
            $stmt->execute([
                ':pedido_id' => $pedidoId,
                ':producto_id' => $productoId,
                ':proveedor_id' => $proveedorId,
                ':cantidad' => $cantidad,
                ':precio' => $precio
            ]);

            $pdo->commit();
            header("Location: gestion_existencias_pedidos.php");
            exit();
        } catch (Exception $e) {
            $pdo->rollBack();
            die("Error al registrar pedido: " . $e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Nuevo Pedido</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-5">
  <h3 class="mb-4">Registrar Nuevo Pedido</h3>

  <form method="POST" class="border p-4 bg-white shadow-sm rounded">
    <div class="mb-3">
      <label for="categoria_id" class="form-label">Categoría</label>
      <select name="categoria_id" id="categoria_id" class="form-select" required>
        <option value="">Seleccione una categoría</option>
        <?php foreach ($categorias as $cat): ?>
          <option value="<?= $cat['categoria_id'] ?>"><?= htmlspecialchars($cat['nombre']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="mb-3">
      <label for="producto_id" class="form-label">Producto</label>
      <select name="producto_id" id="producto_id" class="form-select" required disabled>
        <option value="">Seleccione una categoría primero</option>
      </select>
    </div>

    <div class="mb-3">
      <label for="proveedor_id" class="form-label">Proveedor</label>
      <select name="proveedor_id" id="proveedor_id" class="form-select" required disabled>
        <option value="">Seleccione un producto primero</option>
      </select>
    </div>

    <div class="mb-3">
      <label for="cantidad" class="form-label">Cantidad</label>
      <input type="number" name="cantidad" class="form-control" min="1" required>
    </div>

    <div class="mb-3">
      <label for="precio_total" class="form-label">Precio Total (S/)</label>
      <input type="number" id="precio_total" class="form-control" readonly>
      <input type="hidden" name="precio_unidad" id="precio_unidad">
    </div>

    <div class="d-flex justify-content-between">
      <a href="gestion_existencias_pedidos.php" class="btn btn-secondary">Cancelar</a>
      <button type="submit" class="btn btn-primary">Registrar Pedido</button>
    </div>
  </form>
</div>

<script>
const productos = <?= json_encode($productos) ?>;
const proveedorProducto = <?= json_encode($proveedorProducto) ?>;

const categoriaSelect = document.getElementById('categoria_id');
const productoSelect = document.getElementById('producto_id');
const proveedorSelect = document.getElementById('proveedor_id');
const cantidadInput = document.querySelector('input[name="cantidad"]');
const precioTotalInput = document.getElementById('precio_total');
const precioUnidadInput = document.getElementById('precio_unidad');

categoriaSelect.addEventListener('change', () => {
  const categoriaId = parseInt(categoriaSelect.value);
  productoSelect.innerHTML = '<option value="">Seleccione un producto</option>';
  proveedorSelect.innerHTML = '<option value="">Seleccione un producto primero</option>';
  proveedorSelect.disabled = true;

  if (!categoriaId) {
    productoSelect.disabled = true;
    return;
  }

  const productosFiltrados = productos.filter(p => p.categoria_id === categoriaId);
  productosFiltrados.forEach(p => {
    const opt = document.createElement('option');
    opt.value = p.producto_id;
    opt.textContent = p.nombre;
    productoSelect.appendChild(opt);
  });

  productoSelect.disabled = false;
});

productoSelect.addEventListener('change', () => {
  const productoId = parseInt(productoSelect.value);
  proveedorSelect.innerHTML = '<option value="">Seleccione un proveedor</option>';

  if (!productoId) {
    proveedorSelect.disabled = true;
    return;
  }

  const producto = productos.find(p => p.producto_id === productoId);
  const precio = parseFloat(producto.precio);
  precioUnidadInput.value = precio.toFixed(2);
  actualizarPrecioTotal();

  const proveedores = proveedorProducto.filter(pp => pp.producto_id === productoId);
  proveedores.forEach(p => {
    const opt = document.createElement('option');
    opt.value = p.proveedor_id;
    opt.textContent = p.nombre_empresa;
    proveedorSelect.appendChild(opt);
  });

  proveedorSelect.disabled = false;
});

cantidadInput.addEventListener('input', actualizarPrecioTotal);

function actualizarPrecioTotal() {
  const cantidad = parseFloat(cantidadInput.value);
  const precioUnitario = parseFloat(precioUnidadInput.value);
  const total = !isNaN(cantidad) && !isNaN(precioUnitario) ? cantidad * precioUnitario : 0;
  precioTotalInput.value = total.toFixed(2);
}
</script>
</body>
</html>
