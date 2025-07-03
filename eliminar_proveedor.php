<?php
include("config.php");

if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    header("Location: gestion_catalogo_proveedores.php");
    exit();
}

$proveedor_id = (int)$_GET['id'];

// Verificar si tiene productos asociados
$sqlProductos = "SELECT COUNT(*) FROM producto 
                 JOIN proveedor_producto ON producto.producto_id = proveedor_producto.producto_id 
                 WHERE proveedor_producto.proveedor_id = :id";
$stmtProductos = $pdo->prepare($sqlProductos);
$stmtProductos->execute([':id' => $proveedor_id]);
$productosRelacionados = $stmtProductos->fetchColumn();

// Verificar si tiene categorías asociadas
$sqlCategorias = "SELECT COUNT(*) FROM categoria 
                  JOIN producto ON categoria.categoria_id = producto.categoria_id 
                  JOIN proveedor_producto ON producto.producto_id = proveedor_producto.producto_id 
                  WHERE proveedor_producto.proveedor_id = :id";
$stmtCategorias = $pdo->prepare($sqlCategorias);
$stmtCategorias->execute([':id' => $proveedor_id]);
$categoriasRelacionadas = $stmtCategorias->fetchColumn();

if ($productosRelacionados > 0 || $categoriasRelacionadas > 0) {
    $tipoRelacion = ($productosRelacionados > 0 ? 'productos' : '') . 
                    ($productosRelacionados > 0 && $categoriasRelacionadas > 0 ? ' y ' : '') . 
                    ($categoriasRelacionadas > 0 ? 'categorías' : '');

    header("Location: gestion_catalogo_proveedores.php?error=relaciones");
    exit();
}

// Eliminar proveedor
$delete = $pdo->prepare("DELETE FROM proveedor WHERE proveedor_id = :id");
$delete->execute([':id' => $proveedor_id]);

header("Location: gestion_catalogo_proveedores.php?eliminado=ok");
exit();
