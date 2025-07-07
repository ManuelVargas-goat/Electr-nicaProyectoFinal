<?php
$host = 'localhost';
$dbname = 'TiendaElectro'; // Nombre de la base de datos
$user = 'juan';          // Usuario de la base de datos
$password = '123';    // Contraseña del usuario
$port = '5432';          // Puerto de PostgreSQL (ajústalo si es diferente)

try {
    $pdo = new PDO("pgsql:host=$host;dbname=$dbname;port=$port", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); // Manejo de errores con excepciones
} catch (PDOException $e) {
    echo 'Error de conexión: ' . $e->getMessage();
    die();
}

 // Contador de Productos en Carrito

$num_cart = 0;
if (isset($_SESSION['carrito']['productos'])){
    $num_cart= count($_SESSION['carrito']['productos']);
}
?>
