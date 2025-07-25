<?php
// Incluye el archivo de configuración que contiene la conexión PDO y el inicio de sesión
require_once 'config.php';

// Asegúrate de que la sesión esté iniciada y que 'carrito' exista
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Verifica si el carrito está vacío o no existe
if (!isset($_SESSION['carrito']) || empty($_SESSION['carrito'])) {
    // Redirige al carrito de compras si no hay productos
    header('Location: carrocompras.php');
    exit();
}

// Inicia una transacción para asegurar la integridad de los datos
$pdo->beginTransaction();

try {
    $total_compra = 0; // Inicializa el total de la compra

    // Paso 1: Verificar el stock de cada producto en el carrito
    foreach ($_SESSION['carrito'] as $producto_id => $cantidad_solicitada) {
        // Asegúrate de que $producto_id y $cantidad_solicitada sean valores válidos
        if (!is_numeric($producto_id) || !is_numeric($cantidad_solicitada) || $cantidad_solicitada <= 0) {
            throw new Exception("Datos de producto o cantidad no válidos en el carrito.");
        }

        // Consulta para obtener el precio y el stock actual del producto
        // Es crucial que 'st.cantidad' se use para el stock, no 'pr.stock'
        $stmt_producto = $pdo->prepare("SELECT pr.precio, st.cantidad AS stock FROM producto pr INNER JOIN stock st ON pr.producto_id = st.producto_id WHERE pr.producto_id = :producto_id AND pr.descontinuado = FALSE");
        $stmt_producto->bindParam(':producto_id', $producto_id, PDO::PARAM_INT);
        $stmt_producto->execute();
        $producto = $stmt_producto->fetch(PDO::FETCH_ASSOC);

        // Verifica si el producto existe y tiene stock suficiente
        if (!$producto) {
            throw new Exception("Error al verificar el producto ID: " . htmlspecialchars($producto_id) . ". El producto no existe o está descontinuado.");
        }
        if ($producto['stock'] < $cantidad_solicitada) {
            throw new Exception("Stock insuficiente para el producto ID: " . htmlspecialchars($producto_id) . ". Stock disponible: " . htmlspecialchars($producto['stock']) . ", solicitado: " . htmlspecialchars($cantidad_solicitada) . ".");
        }

        // Calcula el subtotal para este producto y lo añade al total de la compra
        $total_compra += $producto['precio'] * $cantidad_solicitada;
    }

    // Paso 2: Insertar la compra en la tabla 'compra'
    // Asegúrate de que la columna 'total_compra' exista en tu tabla 'compra'
    // y que 'usuario_cliente_id' sea el ID del usuario logueado.
    // Si no tienes un sistema de usuarios, puedes usar un ID de usuario por defecto o anónimo.
    $usuario_cliente_id = isset($_SESSION['usuario_id']) ? $_SESSION['usuario_id'] : 1; // Usar 1 como ID por defecto si no hay sesión de usuario
    $fecha_compra = date('Y-m-d H:i:s'); // Fecha y hora actual

    $stmt_compra = $pdo->prepare("INSERT INTO compra (usuario_cliente_id, fecha_compra, total_compra) VALUES (:usuario_cliente_id, :fecha_compra, :total_compra) RETURNING compra_id");
    $stmt_compra->bindParam(':usuario_cliente_id', $usuario_cliente_id, PDO::PARAM_INT);
    $stmt_compra->bindParam(':fecha_compra', $fecha_compra);
    $stmt_compra->bindParam(':total_compra', $total_compra);
    $stmt_compra->execute();
    $compra_id = $stmt_compra->fetchColumn(); // Obtiene el ID de la compra recién insertada

    // Paso 3: Insertar los detalles de la compra en 'detalle_compra' y actualizar el stock
    foreach ($_SESSION['carrito'] as $producto_id => $cantidad_solicitada) {
        // Obtener el precio actual del producto (ya verificado en el paso 1)
        $stmt_precio = $pdo->prepare("SELECT precio FROM producto WHERE producto_id = :producto_id");
        $stmt_precio->bindParam(':producto_id', $producto_id, PDO::PARAM_INT);
        $stmt_precio->execute();
        $precio_unitario = $stmt_precio->fetchColumn();

        // Insertar en detalle_compra
        $stmt_detalle = $pdo->prepare("INSERT INTO detalle_compra (compra_id, producto_id, cantidad, precio_unitario, subtotal) VALUES (:compra_id, :producto_id, :cantidad, :precio_unitario, :subtotal)");
        $subtotal_producto = $cantidad_solicitada * $precio_unitario;
        $stmt_detalle->bindParam(':compra_id', $compra_id, PDO::PARAM_INT);
        $stmt_detalle->bindParam(':producto_id', $producto_id, PDO::PARAM_INT);
        $stmt_detalle->bindParam(':cantidad', $cantidad_solicitada, PDO::PARAM_INT);
        $stmt_detalle->bindParam(':precio_unitario', $precio_unitario);
        $stmt_detalle->bindParam(':subtotal', $subtotal_producto);
        $stmt_detalle->execute();

        // Actualizar el stock del producto
        $stmt_update_stock = $pdo->prepare("UPDATE stock SET cantidad = cantidad - :cantidad WHERE producto_id = :producto_id");
        $stmt_update_stock->bindParam(':cantidad', $cantidad_solicitada, PDO::PARAM_INT);
        $stmt_update_stock->bindParam(':producto_id', $producto_id, PDO::PARAM_INT);
        $stmt_update_stock->execute();
    }

    // Si todo fue exitoso, confirma la transacción
    $pdo->commit();

    // Limpia el carrito después de una compra exitosa
    unset($_SESSION['carrito']);
    $_SESSION['num_cart'] = 0; // Reinicia el contador del carrito

    // Redirige a una página de confirmación o al inicio
    header('Location: compra_exitosa.php');
    exit();

} catch (Exception $e) {
    // Si algo falla, revierte la transacción
    $pdo->rollBack();
    error_log("Error al procesar la compra: " . $e->getMessage()); // Registra el error en el log
    $_SESSION['error_compra'] = "Error al procesar la compra: Problemas con el stock o disponibilidad de productos: " . $e->getMessage();
    header('Location: carrocompras.php'); // Redirige de nuevo al carrito con un mensaje de error
    exit();
}
?>