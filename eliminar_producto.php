<?php
session_start(); // Inicia la sesión al principio
include("config.php"); // Asegúrate de que 'config.php' contenga la conexión PDO.

// 1. Verificar si el usuario está logueado
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}

// 2. Verificar si se ha proporcionado un ID de producto válido
if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    // Redireccionar con un mensaje de error si el ID no es válido
    header("Location: gestion_catalogo_productos.php?mensaje=" . urlencode("ID de producto no válido.") . "&tipo=danger");
    exit();
}

$producto_id = (int)$_GET['id'];

try {
    $canDelete = true;
    $errorMessage = "";

    // 3. Verificar si el producto está en algún detalle de compra
    $stmtDetalleCompra = $pdo->prepare("SELECT COUNT(*) FROM detalle_compra WHERE producto_id = :id");
    $stmtDetalleCompra->execute([':id' => $producto_id]);
    $comprasAsociadas = $stmtDetalleCompra->fetchColumn();

    if ($comprasAsociadas > 0) {
        $canDelete = false;
        $errorMessage .= "El producto está asociado a " . $comprasAsociadas . " compra(s). ";
    }

    // 4. Verificar si el producto está en algún detalle de pedido
    $stmtDetallePedido = $pdo->prepare("SELECT COUNT(*) FROM detalle_pedido WHERE producto_id = :id");
    $stmtDetallePedido->execute([':id' => $producto_id]);
    $pedidosAsociados = $stmtDetallePedido->fetchColumn();

    if ($pedidosAsociados > 0) {
        $canDelete = false;
        $errorMessage .= "El producto está asociado a " . $pedidosAsociados . " pedido(s). ";
    }

    // 5. Verificar si el producto está en algún detalle de devolución
    $stmtDetalleDevolucion = $pdo->prepare("SELECT COUNT(*) FROM detalle_devolucion WHERE producto_id = :id");
    $stmtDetalleDevolucion->execute([':id' => $producto_id]);
    $devolucionesAsociadas = $stmtDetalleDevolucion->fetchColumn();

    if ($devolucionesAsociadas > 0) {
        $canDelete = false;
        $errorMessage .= "El producto está asociado a " . $devolucionesAsociadas . " devolución(es). ";
    }

    // 6. Si no se puede eliminar debido a las relaciones, redirigir con un mensaje de error
    if (!$canDelete) {
        $finalErrorMessage = "No se puede eliminar el producto. " . $errorMessage . " Elimine primero las relaciones.";
        header("Location: gestion_catalogo_productos.php?mensaje=" . urlencode($finalErrorMessage) . "&tipo=danger");
        exit();
    }

    // 7. Se busca la ruta de la imagen y se comprueba que existe la ruta
    $sqlruta = $pdo->prepare("SELECT ruta_imagen FROM producto WHERE producto_id = :id");
    $sqlruta->execute([':id' => $producto_id]);
    $ruta_imagen = $sqlruta->fetchColumn();

    if (!empty($ruta_imagen) && file_exists($ruta_imagen)) {
        if (!unlink($ruta_imagen)) {
        $finalErrorMessage = "No se pudo eliminar la imagen.";
        header("Location: gestion_catalogo_productos.php?mensaje=" . urlencode($finalErrorMessage) . "&tipo=danger");
        exit();
        } 
    } else {
        $finalErrorMessage = "El archivo no existe.";
        header("Location: gestion_catalogo_productos.php?mensaje=" . urlencode($finalErrorMessage) . "&tipo=danger");
        exit();
    }

    // 8. Si no hay relaciones que impidan la eliminación, proceder con el DELETE
    $delete = $pdo->prepare("DELETE FROM producto WHERE producto_id = :id");
    $delete->execute([':id' => $producto_id]);
    

    // 9. Redireccionar con mensaje de éxito
    header("Location: gestion_catalogo_productos.php?mensaje=" . urlencode("Producto eliminado con éxito.") . "&tipo=success");
    exit();

} catch (PDOException $e) {
    // 10. Capturar cualquier excepción de la base de datos (por ejemplo, si hubiera otra restricción no considerada)
    error_log("Error al eliminar producto: " . $e->getMessage()); // Esto es útil para la depuración en los logs del servidor
    header("Location: gestion_catalogo_productos.php?mensaje=" . urlencode("Error de base de datos al eliminar el producto: " . $e->getMessage()) . "&tipo=danger");
    exit();
}
?>