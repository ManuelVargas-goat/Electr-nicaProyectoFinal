<?php
include("config.php");

 // Contador de Productos en Carrito en tiempo real

if (isset($_POST['id']) && isset($_POST['cantidad'])&& isset($_POST['precio'])&& isset($_POST['subtotal'])) {

    $id = $_POST['id'];
    $nombre = $_POST['nombre'];
    $precio = $_POST['precio'];
    $cantidad = $_POST['cantidad'];
    $subtotal = $_POST['subtotal'];

      
    $_SESSION['carrito']['productoSeleccionados'][$id] = 
    ['id' => $id, 'nombre' => $nombre,'precio' => $precio,'cantidad' => $cantidad,'subtotal' => $subtotal];

    $datos['ok'] = true;


}else{
 print_r('No se recibio productos.');
 $datos['ok']= false;
}

echo json_encode($datos);
?>