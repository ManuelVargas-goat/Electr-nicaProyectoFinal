<?php
session_start();
include("config.php");

if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}

// Obtener datos del usuario empleado actual
$usuario = $_SESSION['usuario'];
$sqlEmpleado = "SELECT ue.usuario_empleado_id, p.nombre || ' ' || p.apellido_paterno AS nombre
                FROM usuario_empleado ue
                JOIN persona p ON ue.persona_id = p.persona_id
                WHERE ue.usuario = :usuario";
$stmtEmpleado = $pdo->prepare($sqlEmpleado);
$stmtEmpleado->execute(['usuario' => $usuario]);
$empleadoActivo = $stmtEmpleado->fetch();

$empleadoId = $empleadoActivo['usuario_empleado_id'];
$nombreEmpleado = $empleadoActivo['nombre'];

// Obtener pedidos entregados
$pedidos = $pdo->query("SELECT pedido_id, fecha_pedido FROM pedido WHERE estado = 'entregado' ORDER BY pedido_id DESC")->fetchAll(PDO::FETCH_ASSOC);

// Obtener relaciones producto-proveedor-pedido
$relaciones = $pdo->query("
    SELECT dp.pedido_id, dp.producto_id, p.nombre AS producto, dp.proveedor_id, pr.nombre_empresa, dp.cantidad
    FROM detalle_pedido dp
    JOIN producto p ON dp.producto_id = p.producto_id
    JOIN proveedor pr ON dp.proveedor_id = pr.proveedor_id
    WHERE dp.pedido_id IN (SELECT pedido_id FROM pedido WHERE estado = 'entregado')
    ORDER BY dp.pedido_id DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pedidoId = $_POST['pedido_id'];
    $productoId = $_POST['producto_id'];
    $cantidad = $_POST['cantidad'];
    $motivo = $_POST['motivo'];
    $reingresado = isset($_POST['reingresado_stock']);
    $proveedorId = $_POST['proveedor_id'];

    try {
        $pdo->beginTransaction();

        // Insertar la devolución
        $sql = "INSERT INTO devolucion (fecha, tipo, usuario_empleado_id, observaciones)
                VALUES (CURRENT_TIMESTAMP, 'proveedor', :empleado_id, :motivo)
                RETURNING devolucion_id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':empleado_id' => $empleadoId,
            ':motivo' => $motivo
        ]);
        $devolucionId = $stmt->fetchColumn();

        // Insertar detalle de devolución
        $sqlDetalle = "INSERT INTO detalle_devolucion (devolucion_id, producto_id, proveedor_id, cantidad, motivo, reingresado_stock)
                       VALUES (:devolucion_id, :producto_id, :proveedor_id, :cantidad, :motivo, :reingresado)";
        $stmt = $pdo->prepare($sqlDetalle);
        $stmt->execute([
            ':devolucion_id' => $devolucionId,
            ':producto_id' => $productoId,
            ':proveedor_id' => $proveedorId,
            ':cantidad' => $cantidad,
            ':motivo' => $motivo,
            ':reingresado' => $reingresado ? 'true' : 'false'
        ]);

        // Si se marcó "reingresado al stock", generar un nuevo pedido automáticamente
        if ($reingresado) {
            $sqlPedido = "INSERT INTO pedido (fecha_pedido, estado, usuario_empleado_id, almacen_id)
                          VALUES (CURRENT_DATE, 'pendiente', :empleado_id, NULL)
                          RETURNING pedido_id";
            $stmt = $pdo->prepare($sqlPedido);
            $stmt->execute([':empleado_id' => $empleadoId]);
            $pedidoIdNuevo = $stmt->fetchColumn();

            // Insertar detalle del nuevo pedido (producto devuelto)
            $sqlDetallePedido = "INSERT INTO detalle_pedido (pedido_id, producto_id, proveedor_id, cantidad, precio_unidad)
                                 VALUES (:pedido_id, :producto_id, :proveedor_id, :cantidad, 0.0)";
            $stmt = $pdo->prepare($sqlDetallePedido);
            $stmt->execute([
                ':pedido_id' => $pedidoIdNuevo,
                ':producto_id' => $productoId,
                ':proveedor_id' => $proveedorId,
                ':cantidad' => $cantidad
            ]);
        }

        $pdo->commit();
        header("Location: gestion_existencias_devoluciones.php");
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        die("Error al registrar devolución: " . $e->getMessage());
    }
}

?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Nueva Devolución (Proveedor)</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-5">
  <h3 class="mb-4">Registrar Devolución a Proveedor</h3>
  <form method="POST" class="bg-white p-4 rounded shadow-sm">

    <div class="mb-3">
      <label class="form-label">Empleado</label>
      <input type="text" class="form-control" value="<?= htmlspecialchars($nombreEmpleado) ?>" readonly>
    </div>

    <div class="mb-3">
      <label class="form-label">Pedido Entregado</label>
      <select name="pedido_id" id="pedido_id" class="form-select" required>
        <option value="">Seleccione un pedido</option>
        <?php foreach ($pedidos as $p): ?>
          <option value="<?= $p['pedido_id'] ?>">#<?= $p['pedido_id'] ?> - <?= $p['fecha_pedido'] ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="mb-3">
      <label class="form-label">Producto</label>
      <select name="producto_id" id="producto_id" class="form-select" required disabled>
        <option value="">Seleccione un pedido primero</option>
      </select>
    </div>

    <div class="mb-3">
      <label class="form-label">Proveedor</label>
      <input type="text" id="proveedor_texto" class="form-control" readonly>
      <input type="hidden" name="proveedor_id" id="proveedor_id">
    </div>

    <div class="mb-3">
      <label class="form-label">Cantidad</label>
      <input type="number" name="cantidad" class="form-control" required min="1">
    </div>

    <div class="mb-3">
      <label class="form-label">Motivo</label>
      <textarea name="motivo" class="form-control" required></textarea>
    </div>

    <div class="form-check mb-3">
      <input type="checkbox" name="reingresado_stock" class="form-check-input" id="reingresado_stock">
      <label class="form-check-label" for="reingresado_stock">Reingresar al stock (generar pedido de reemplazo)</label>
    </div>

    <div class="d-flex justify-content-between">
      <a href="gestion_existencias_devoluciones.php" class="btn btn-secondary">Cancelar</a>
      <button type="submit" class="btn btn-primary">Registrar Devolución</button>
    </div>
  </form>
</div>

<script>
const relaciones = <?= json_encode($relaciones) ?>;

const pedidoSelect = document.getElementById('pedido_id');
const productoSelect = document.getElementById('producto_id');
const proveedorInput = document.getElementById('proveedor_id');
const proveedorTexto = document.getElementById('proveedor_texto');
const cantidadInput = document.querySelector('input[name="cantidad"]');

let cantidadMax = 0;

pedidoSelect.addEventListener('change', () => {
  const pedidoId = parseInt(pedidoSelect.value);
  productoSelect.innerHTML = '<option value="">Seleccione un producto</option>';
  productoSelect.disabled = true;
  proveedorInput.value = '';
  proveedorTexto.value = '';
  cantidadInput.value = '';
  cantidadInput.removeAttribute('max');

  if (!pedidoId) return;

  const productos = relaciones.filter(r => r.pedido_id == pedidoId);
  productos.forEach(p => {
    const opt = document.createElement('option');
    opt.value = p.producto_id;
    opt.textContent = p.producto;
    opt.dataset.proveedorId = p.proveedor_id;
    opt.dataset.proveedorNombre = p.nombre_empresa;
    opt.dataset.maxCantidad = p.cantidad;
    productoSelect.appendChild(opt);
  });

  productoSelect.disabled = false;
});

productoSelect.addEventListener('change', () => {
  const option = productoSelect.options[productoSelect.selectedIndex];
  proveedorInput.value = option.dataset.proveedorId || '';
  proveedorTexto.value = option.dataset.proveedorNombre || '';
  cantidadMax = parseInt(option.dataset.maxCantidad) || 0;
  cantidadInput.value = '';
  cantidadInput.setAttribute('max', cantidadMax);
  cantidadInput.setAttribute('placeholder', `Máx: ${cantidadMax}`);
});

cantidadInput.addEventListener('input', () => {
  const val = parseInt(cantidadInput.value);
  if (val > cantidadMax) {
    cantidadInput.value = cantidadMax;
  }
});
</script>
</body>
</html>
