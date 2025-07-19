<?php
session_start();
include("config.php");

if (!isset($_SESSION['usuario'])) {
    header('Location: login.php');
    exit();
}

$usuario = $_SESSION['usuario'];

// Obtener el ID del usuario empleado
$sql = "SELECT usuario_empleado_id FROM usuario_empleado WHERE usuario = :usuario";
$stmt = $pdo->prepare($sql);
$stmt->execute([':usuario' => $usuario]);
$empleado = $stmt->fetch();

if (!$empleado) {
    die("Error: Empleado no encontrado."); // Mensaje más descriptivo si el usuario no tiene ID de empleado
}
$usuarioEmpleadoId = $empleado['usuario_empleado_id'];

// Obtener categorías
$categorias = $pdo->query("SELECT categoria_id, nombre FROM categoria ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);

// MODIFICACIÓN CLAVE AQUÍ: Obtener productos incluyendo su estado 'descontinuado'
// Solo seleccionamos los productos que NO están descontinuados para la lista inicial
$productos = $pdo->query("SELECT producto_id, nombre, categoria_id, precio, descontinuado FROM producto ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);

// Obtener relación producto-proveedor
$proveedorProducto = $pdo->query("
    SELECT pp.producto_id, pr.proveedor_id, pr.nombre_empresa
    FROM proveedor_producto pp
    JOIN proveedor pr ON pr.proveedor_id = pp.proveedor_id
")->fetchAll(PDO::FETCH_ASSOC);

// Lógica para procesar el formulario de pedido
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $productoIds = $_POST['producto_id'] ?? [];
    $proveedorIds = $_POST['proveedor_id'] ?? [];
    $cantidades = $_POST['cantidad'] ?? [];
    $precios = $_POST['precio_unidad'] ?? [];

    // Basic validation to ensure at least one item is present
    if (!empty($productoIds) && !empty($cantidades) && !empty($precios) && !empty($proveedorIds)) {
        $pdo->beginTransaction();

        try {
            // Insertar el nuevo pedido
            $stmt = $pdo->prepare("INSERT INTO pedido (fecha_pedido, estado, usuario_empleado_id)
                                   VALUES (NOW(), 'pendiente', :usuario_id) RETURNING pedido_id");
            $stmt->execute([':usuario_id' => $usuarioEmpleadoId]);
            $pedidoId = $stmt->fetchColumn();

            // Insertar los detalles del pedido
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
            // Redirigir con un mensaje de éxito si lo deseas, o simplemente a la lista de pedidos
            $_SESSION['modal_message'] = '¡Pedido registrado con éxito!';
            $_SESSION['modal_type'] = 'success';
            header("Location: gestion_existencias_pedidos.php");
            exit();
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['modal_message'] = 'Error al registrar pedido: ' . $e->getMessage();
            $_SESSION['modal_type'] = 'danger';
            header("Location: nuevo_pedido.php"); // Redirigir de vuelta al formulario con el error
            exit();
        }
    } else {
        $_SESSION['modal_message'] = 'No se han agregado productos al pedido.';
        $_SESSION['modal_type'] = 'warning';
        header("Location: nuevo_pedido.php"); // Redirigir de vuelta al formulario
        exit();
    }
}

// **LÓGICA MEJORADA PARA MENSAJES (Prioriza SESSION)**
$mensaje = '';
$tipoMensaje = ''; // Puede ser 'success', 'danger', 'warning', 'info'

// Check for messages from session (e.g., redirect after an action)
if (isset($_SESSION['modal_message']) && !empty($_SESSION['modal_message'])) {
    $mensaje = $_SESSION['modal_message'];
    $tipoMensaje = $_SESSION['modal_type'];
    // Clear session variables so the message doesn't reappear on refresh
    unset($_SESSION['modal_message']);
    unset($_SESSION['modal_type']);
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Nuevo Pedido</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="css/estilos.css?v=<?= time(); ?>"> <!-- Asumiendo que estilos.css ya tiene los estilos para dark-mode -->
    <style>
        /* Estilos para el tema oscuro (duplicado para consistencia, si estilos.css no es suficiente) */
        body.dark-mode {
            background-color: #343a40; /* Fondo oscuro */
            color: #f8f9fa; /* Texto claro */
        }
        body.dark-mode .container {
            background-color: #495057; /* Fondo del contenedor oscuro */
        }
        body.dark-mode .card {
            background-color: #545b62; /* Tarjetas oscuras */
            border-color: #6c757d;
        }
        body.dark-mode .form-label {
            color: #f8f9fa; /* Etiquetas claras */
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
    </style>
</head>
<body class="bg-light">
<div class="container mt-5">
    <h3 class="mb-4">Registrar Nuevo Pedido</h3>

    <?php
    // **BLOQUE PARA MOSTRAR LA ALERTA DE BOOTSTRAP**
    if (!empty($mensaje) && !empty($tipoMensaje)) {
        $alertClass = '';
        switch ($tipoMensaje) {
            case 'success':
                $alertClass = 'alert-success';
                break;
            case 'danger':
                $alertClass = 'alert-danger';
                break;
            case 'warning':
                $alertClass = 'alert-warning';
                break;
            case 'info':
                $alertClass = 'alert-info';
                break;
            default:
                $alertClass = 'alert-info'; // Tipo predeterminado si no coincide
        }
        echo '<div class="alert ' . $alertClass . ' alert-dismissible fade show mb-4" role="alert">';
        echo htmlspecialchars($mensaje); // Muestra el mensaje escapando caracteres especiales
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>'; // Botón para cerrar la alerta
        echo '</div>';
    }
    ?>

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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Pasa los datos de PHP a JavaScript
const categorias = <?= json_encode($categorias, JSON_UNESCAPED_UNICODE) ?>;
const productos = <?= json_encode($productos, JSON_UNESCAPED_UNICODE) ?>;
const proveedorProducto = <?= json_encode($proveedorProducto, JSON_UNESCAPED_UNICODE) ?>;

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
                <input type="number" name="precio_unidad[]" class="form-control precio-unidad" step="0.01" readonly>
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

        // MODIFICACIÓN CLAVE AQUÍ: Filtrar productos para que solo se muestren los NO descontinuados
        const productosFiltrados = productos.filter(p => p.categoria_id === categoriaId && p.descontinuado == false); // Usa == false para flexibilidad con 0/1 o true/false
        
        if (productosFiltrados.length === 0) {
            const opt = document.createElement('option');
            opt.value = "";
            opt.textContent = "No hay productos activos en esta categoría";
            productoSelect.appendChild(opt);
            productoSelect.disabled = true;
        } else {
            productosFiltrados.forEach(p => {
                const opt = document.createElement('option');
                opt.value = p.producto_id;
                opt.textContent = p.nombre;
                productoSelect.appendChild(opt);
            });
            productoSelect.disabled = false;
        }
    });

    productoSelect.addEventListener('change', () => {
        const prodId = parseInt(productoSelect.value);
        const producto = productos.find(p => p.producto_id === prodId);
        
        precioUnidad.value = (producto && !isNaN(producto.precio)) ? parseFloat(producto.precio).toFixed(2) : '';
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
    const cards = document.querySelectorAll('#productos-container .card'); // Asegurarse de seleccionar solo los cards de productos
    let total = 0;
    cards.forEach(card => {
        const precioInput = card.querySelector('.precio-unidad');
        const cantidadInput = card.querySelector('.cantidad-input');
        
        const precio = parseFloat(precioInput?.value || 0);
        const cantidad = parseFloat(cantidadInput?.value || 0);
        
        if (!isNaN(precio) && !isNaN(cantidad)) {
            total += precio * cantidad;
        }
    });
    document.getElementById('total-general').value = total.toFixed(2);
}

// Lógica para el Modo Oscuro (se mantiene para consistencia, aunque no hay un switch aquí)
const body = document.body;
document.addEventListener('DOMContentLoaded', (event) => {
    if (localStorage.getItem('darkMode') === 'enabled') {
        body.classList.add('dark-mode');
    }
});

// Agregar un producto por defecto al cargar la página
window.addEventListener('DOMContentLoaded', agregarProducto);
</script>
</body>
</html>