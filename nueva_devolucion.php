<?php
session_start();
include("config.php");

if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}

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

// Obtener proveedores
$proveedores = $pdo->query("SELECT proveedor_id, nombre_empresa FROM proveedor ORDER BY nombre_empresa")->fetchAll();

// Obtener productos con relación proveedor-producto
$relaciones = $pdo->query("SELECT pp.proveedor_id, p.producto_id, p.nombre
                            FROM proveedor_producto pp
                            JOIN producto p ON pp.producto_id = p.producto_id
                            ORDER BY p.nombre")->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $productoId = $_POST['producto_id'];
    $cantidad = $_POST['cantidad'];
    $motivo = $_POST['motivo'];
    $reingresado = isset($_POST['reingresado_stock']);
    $proveedorId = $_POST['proveedor_id'];

    try {
        $pdo->beginTransaction();

        $sql = "INSERT INTO devolucion (fecha, tipo, usuario_empleado_id, observaciones)
                VALUES (CURRENT_TIMESTAMP, 'proveedor', :empleado_id, :motivo) RETURNING devolucion_id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':empleado_id' => $empleadoId,
            ':motivo' => $motivo
        ]);
        $devolucionId = $stmt->fetchColumn();

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

        // Si se marcó reingresado_stock, generar pedido
        if ($reingresado) {
            $sqlPedido = "INSERT INTO pedido (fecha_pedido, estado, usuario_empleado_id, almacen_id)
                          VALUES (CURRENT_DATE, 'pendiente', :empleado_id, NULL) RETURNING pedido_id";
            $stmt = $pdo->prepare($sqlPedido);
            $stmt->execute([':empleado_id' => $empleadoId]);
            $pedidoId = $stmt->fetchColumn();

            $sqlDetallePedido = "INSERT INTO detalle_pedido (pedido_id, producto_id, proveedor_id, cantidad, precio_unidad)
                                  VALUES (:pedido_id, :producto_id, :proveedor_id, :cantidad, 0.0)";
            $stmt = $pdo->prepare($sqlDetallePedido);
            $stmt->execute([
                ':pedido_id' => $pedidoId,
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
    <h3 class="mb-4">Registrar Devolución de Proveedor</h3>
    <form method="POST" class="bg-white p-4 rounded shadow-sm">
        <div class="mb-3">
            <label class="form-label">Empleado</label>
            <input type="text" class="form-control" value="<?= htmlspecialchars($nombreEmpleado) ?>" readonly>
        </div>

        <div class="mb-3">
            <label class="form-label">Proveedor</label>
            <select name="proveedor_id" id="proveedor_id" class="form-select" required>
                <option value="">Seleccione proveedor</option>
                <?php foreach ($proveedores as $prov): ?>
                    <option value="<?= $prov['proveedor_id'] ?>"><?= htmlspecialchars($prov['nombre_empresa']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="mb-3">
            <label class="form-label">Producto</label>
            <select name="producto_id" id="producto_id" class="form-select" required>
                <option value="">Seleccione un proveedor primero</option>
            </select>
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
            <label for="reingresado_stock" class="form-check-label">Reingresar al stock (generar pedido de reemplazo)</label>
        </div>

        <div class="d-flex justify-content-between">
            <a href="gestion_existencias_devoluciones.php" class="btn btn-secondary">Cancelar</a>
            <button type="submit" class="btn btn-primary">Registrar Devolución</button>
        </div>
    </form>
</div>

<script>
const relaciones = <?= json_encode($relaciones) ?>;

const proveedorSelect = document.getElementById('proveedor_id');
const productoSelect = document.getElementById('producto_id');

proveedorSelect.addEventListener('change', () => {
    const proveedorId = parseInt(proveedorSelect.value);
    productoSelect.innerHTML = '<option value="">Seleccione producto</option>';

    const productosRelacionados = relaciones.filter(r => r.proveedor_id === proveedorId);
    productosRelacionados.forEach(p => {
        const opt = document.createElement('option');
        opt.value = p.producto_id;
        opt.textContent = p.nombre;
        productoSelect.appendChild(opt);
    });
});
</script>
</body>
</html>
