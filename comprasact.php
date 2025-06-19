<?php
include("config.php");

 // Contador de Productos en Carrito en tiempo real

if (isset($_POST['id'])) {

    $id = $_POST['id'];

        if (isset($_SESSION['carrito']['productos'][$id])){
             $_SESSION['carrito']['productos'][$id] += 1;
        }else{
            $_SESSION['carrito']['productos'][$id] = 1;
        }
            $datos['numero'] =count($_SESSION['carrito']['productos']);
            $datos['ok'] = true;


}else{
           $datos['ok']= false;
        }

echo json_encode($datos);
?>