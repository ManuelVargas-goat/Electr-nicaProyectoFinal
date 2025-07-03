<?php
session_start();
include("config.php");

if (!isset($_SESSION['usuario'])) {
    header('Location: login.php');
    exit();
}

$usuario = $_SESSION['usuario'];

$sql = "SELECT usuario_empleado_id FROM usuario_empleado WHERE usuario = :usuario";
$stmt = $pdo->prepare($sql);
$stmt->execute([':usuario' => $usuario]);
$empleado = $stmt->fetch();

if (!$empleado) {
    die("Empleado no encontrado.");
}
$usuarioEmpleadoId = $empleado['usuario_empleado_id'];

$categorias = $pdo->query("SELECT categoria_id, nombre FROM categoria ORDER BY nombre")->fetchAll();
$productos = $pdo->query("SELECT producto_id, nombre, categoria_id, precio FROM producto ORDER BY nombre")->fetchAll();
$proveedorProducto = $pdo->query("
    SELECT pp.producto_id, pr.proveedor_id, pr.nombre_empresa
    FROM proveedor_producto pp
    JOIN proveedor pr ON pr.proveedor_id = pp.proveedor_id
")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $productoIds = $_POST['producto_id'];
    $proveedorIds = $_POST['proveedor_id'];
    $cantidades = $_POST['cantidad'];
    $precios = $_POST['precio_unidad'];

    if ($productoIds && $cantidades && $precios && $proveedorIds) {
        $pdo->beginTransaction();

        try {
            // USAR NOW() en lugar de CURRENT_DATE
            $stmt = $pdo->prepare("INSERT INTO pedido (fecha_pedido, estado, usuario_empleado_id)
                                   VALUES (NOW(), 'pendiente', :usuario_id) RETURNING pedido_id");
            $stmt->execute([':usuario_id' => $usuarioEmpleadoId]);
            $pedidoId = $stmt->fetchColumn();

            $stmt = $pdo->prepare("INSERT INTO detalle_pedido 
                (pedido_id, producto_id, proveedor_id, cantidad, precio_unidad)
                VALUES (:pedido_id, :producto_id, :proveedor_id, :cantidad, :precio)");

            for ($i = 0; $i < count($productoIds); $i++) {
                $stmt->execute([
                    ':pedido_id' => $pedidoId,
                    ':producto_id' => $productoIds[$i],
                    ':proveedor_id' => $proveedorIds[$i],
                    ':cantidad' => $cantidades[$i],
                    ':precio' => $precios[$i]
                ]);
            }

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
    <div id="productos-container"></div>
    <button type="button" class="btn btn-outline-secondary mb-4" onclick="agregarProducto()">+ Agregar Producto</button>

    <div class="mb-3 text-end">
      <label class="form-label fw-bold">Total General (S/)</label>
      <input type="number" id="total-general" class="form-control text-end" readonly>
    </div>

    <div class="d-flex justify-content-between">
      <a href="gestion_existencias_pedidos.php" class="btn btn-secondary">Cancelar</a>
      <button type="submit" class="btn btn-primary">Registrar Pedido</button>
    </div>
  </form>
</div>

<script>
const categorias = <?= json_encode($categorias) ?>;
const productos = <?= json_encode($productos) ?>;
const proveedorProducto = <?= json_encode($proveedorProducto) ?>;

function agregarProducto() {
  const wrapper = document.createElement('div');
  wrapper.classList.add('card', 'p-3', 'mb-4', 'shadow-sm');
  wrapper.innerHTML = `
    <div class="row g-3 align-items-end">
      <div class="col-md-3">
        <label class="form-label">Categoría</label>
        <select class="form-select categoria-select" required>
          <option value="">Seleccione...</option>
          ${categorias.map(c => `<option value="${c.categoria_id}">${c.nombre}</option>`).join('')}
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label">Producto</label>
        <select name="producto_id[]" class="form-select producto-select" required disabled>
          <option value="">Seleccione una categoría primero</option>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label">Proveedor</label>
        <select name="proveedor_id[]" class="form-select proveedor-select" required disabled>
          <option value="">Seleccione un producto primero</option>
        </select>
      </div>
      <div class="col-md-1">
        <label class="form-label">Cantidad</label>
        <input type="number" name="cantidad[]" class="form-control cantidad-input" min="1" required>
      </div>
      <div class="col-md-1">
        <label class="form-label">Precio (S/)</label>
        <input type="number" name="precio_unidad[]" class="form-control precio-unidad" readonly>
      </div>
      <div class="col-md-1 text-end">
        <button type="button" class="btn btn-danger btn-sm" onclick="eliminarProducto(this)">Eliminar</button>
      </div>
    </div>
  `;
  document.getElementById('productos-container').appendChild(wrapper);

  const categoriaSelect = wrapper.querySelector('.categoria-select');
  const productoSelect = wrapper.querySelector('.producto-select');
  const proveedorSelect = wrapper.querySelector('.proveedor-select');
  const precioUnidad = wrapper.querySelector('.precio-unidad');
  const cantidadInput = wrapper.querySelector('.cantidad-input');

  categoriaSelect.addEventListener('change', () => {
    const categoriaId = parseInt(categoriaSelect.value);
    productoSelect.innerHTML = '<option value="">Seleccione producto</option>';
    proveedorSelect.innerHTML = '<option value="">Seleccione un producto primero</option>';
    proveedorSelect.disabled = true;
    precioUnidad.value = '';
    cantidadInput.value = '';

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
    const prodId = parseInt(productoSelect.value);
    const producto = productos.find(p => p.producto_id === prodId);
    precioUnidad.value = producto?.precio ?? '';
    cantidadInput.value = '';
    calcularTotalGeneral();

    proveedorSelect.innerHTML = '<option value="">Seleccione proveedor</option>';
    proveedorProducto
      .filter(pp => pp.producto_id === prodId)
      .forEach(pp => {
        const opt = document.createElement('option');
        opt.value = pp.proveedor_id;
        opt.textContent = pp.nombre_empresa;
        proveedorSelect.appendChild(opt);
      });

    proveedorSelect.disabled = false;
  });

  cantidadInput.addEventListener('input', calcularTotalGeneral);
}

function eliminarProducto(btn) {
  const card = btn.closest('.card');
  card.remove();
  calcularTotalGeneral();
}

function calcularTotalGeneral() {
  const cards = document.querySelectorAll('.card');
  let total = 0;
  cards.forEach(card => {
    const precio = parseFloat(card.querySelector('.precio-unidad')?.value || 0);
    const cantidad = parseFloat(card.querySelector('.cantidad-input')?.value || 0);
    if (!isNaN(precio) && !isNaN(cantidad)) {
      total += precio * cantidad;
    }
  });
  document.getElementById('total-general').value = total.toFixed(2);
}

window.addEventListener('DOMContentLoaded', agregarProducto);
</script>
</body>
</html>