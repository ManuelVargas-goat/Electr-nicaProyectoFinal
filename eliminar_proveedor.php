<?php
session_start(); // Inicia la sesión para usar $_SESSION
include("config.php"); // Asegúrate de que 'config.php' contenga la conexión PDO.

// 1. Validar que se reciba un ID de proveedor válido
if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    $_SESSION['modal_message'] = "ID de proveedor no válido para eliminar.";
    $_SESSION['modal_type'] = "danger";
    header("Location: gestion_catalogo_proveedores.php");
    exit();
}

$proveedor_id = (int)$_GET['id'];

try {
    // 2. Verificar si tiene productos asociados
    $sqlProductos = "SELECT COUNT(*) FROM proveedor_producto WHERE proveedor_id = :id";
    $stmtProductos = $pdo->prepare($sqlProductos);
    $stmtProductos->execute([':id' => $proveedor_id]);
    $productosRelacionados = $stmtProductos->fetchColumn();

    // 3. Verificar si tiene categorías asociadas
    // Esta consulta es un poco compleja. Un proveedor no está directamente asociado a categorías.
    // Un proveedor se asocia a productos, y los productos a categorías.
    // Si la intención es verificar si ALGÚN producto asociado a este proveedor
    // también está asociado a una categoría, la consulta original era:
    // "SELECT COUNT(*) FROM categoria JOIN producto ON categoria.categoria_id = producto.categoria_id JOIN proveedor_producto ON producto.producto_id = proveedor_producto.producto_id WHERE proveedor_producto.proveedor_id = :id"
    // Sin embargo, si un proveedor no tiene productos, tampoco tendrá "categorías relacionadas a través de sus productos".
    // La verificación de $productosRelacionados ya cubre si hay productos.
    // Si la idea es evitar eliminar un proveedor si *al menos uno* de sus productos relacionados tiene una categoría,
    // entonces la consulta es correcta. Si no, $productosRelacionados es suficiente.
    // Asumiré que quieres mantener la lógica de verificar categorías vía productos.

    $sqlCategorias = "SELECT COUNT(DISTINCT c.categoria_id)
                      FROM categoria c
                      JOIN producto p ON c.categoria_id = p.categoria_id
                      JOIN proveedor_producto pp ON p.producto_id = pp.producto_id
                      WHERE pp.proveedor_id = :id";
    $stmtCategorias = $pdo->prepare($sqlCategorias);
    $stmtCategorias->execute([':id' => $proveedor_id]);
    $categoriasRelacionadas = $stmtCategorias->fetchColumn();

    // 4. Lógica de impedimento de eliminación por relaciones
    if ($productosRelacionados > 0 || $categoriasRelacionadas > 0) {
        $mensajeError = "No se puede eliminar el proveedor porque tiene ";
        $relaciones = [];
        if ($productosRelacionados > 0) {
            $relaciones[] = "productos asociados";
        }
        if ($categoriasRelacionadas > 0) {
            $relaciones[] = "categorías asociadas (a través de sus productos)";
        }
        $mensajeError .= implode(" y ", $relaciones) . ". Desasocie primero.";

        $_SESSION['modal_message'] = $mensajeError;
        $_SESSION['modal_type'] = "danger";
        header("Location: gestion_catalogo_proveedores.php");
        exit();
    }

    // 5. Si no hay relaciones, proceder a eliminar el proveedor
    $delete = $pdo->prepare("DELETE FROM proveedor WHERE proveedor_id = :id");
    $delete->execute([':id' => $proveedor_id]);

    // 6. Establecer mensaje de éxito
    $_SESSION['modal_message'] = "Proveedor eliminado con éxito.";
    $_SESSION['modal_type'] = "success";
    header("Location: gestion_catalogo_proveedores.php");
    exit();

} catch (PDOException $e) {
    // Capturar cualquier error de base de datos durante el proceso
    error_log("Error al intentar eliminar proveedor: " . $e->getMessage()); // Para depuración en logs del servidor
    $_SESSION['modal_message'] = "Error al eliminar el proveedor: " . $e->getMessage();
    $_SESSION['modal_type'] = "danger";
    header("Location: gestion_catalogo_proveedores.php");
    exit();
}
?>