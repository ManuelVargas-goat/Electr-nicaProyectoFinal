<?php
session_start(); // Inicia la sesión

// --- Configuración de la Base de Datos PostgreSQL ---
define('DB_HOST', 'localhost');
define('DB_NAME', 'TiendaElectro');
define('DB_USER', 'juan');
define('DB_PASSWORD', '123');
define('DB_PORT', '5432');

$conn = null;
try {
    $conn = new PDO("pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME, DB_USER, DB_PASSWORD);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->exec("SET NAMES 'UTF8'");
} catch (PDOException $e) {
    error_log("Error de conexión a la base de datos en agregar_deseo.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error de conexión a la base de datos.']);
    exit();
}

header('Content-Type: application/json'); // Asegura que la respuesta sea JSON

$response = ['success' => false, 'message' => ''];

// Verificar si el usuario está logeado
$usuario_cliente_id = isset($_SESSION['usuario_cliente_id']) ? intval($_SESSION['usuario_cliente_id']) : 0;
if ($usuario_cliente_id === 0) {
    $response['message'] = 'Debes iniciar sesión para añadir productos a tu lista de deseos.';
    $response['redirect_to_login'] = true; // Indicar al JS que redirija
    echo json_encode($response);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['product_id'])) {
    $product_id = intval($_POST['product_id']);

    if ($product_id <= 0) {
        $response['message'] = 'ID de producto no válido.';
        echo json_encode($response);
        exit();
    }

    try {
        // Verificar si el producto ya está en la lista de deseos del usuario
        $stmt_check = $conn->prepare("SELECT COUNT(*) FROM lista_deseos WHERE usuario_cliente_id = ? AND producto_id = ?");
        $stmt_check->execute([$usuario_cliente_id, $product_id]);
        $exists = $stmt_check->fetchColumn();

        if ($exists) {
            // Si el producto ya está en la lista de deseos, lo eliminamos
            $stmt_delete = $conn->prepare("DELETE FROM lista_deseos WHERE usuario_cliente_id = ? AND producto_id = ?");
            $stmt_delete->execute([$usuario_cliente_id, $product_id]);
            $response['success'] = true;
            $response['action'] = 'removed'; // Indicar que se eliminó
            $response['message'] = 'Producto eliminado de tu lista de deseos.';
        } else {
            // Si el producto no está en la lista de deseos, lo añadimos
            $stmt_insert = $conn->prepare("INSERT INTO lista_deseos (usuario_cliente_id, producto_id, fecha_agregado) VALUES (?, ?, CURRENT_TIMESTAMP)");
            $stmt_insert->execute([$usuario_cliente_id, $product_id]);
            $response['success'] = true;
            $response['action'] = 'added'; // Indicar que se añadió
            $response['message'] = 'Producto añadido a tu lista de deseos.';
        }
    } catch (PDOException $e) {
        error_log("Error al actualizar lista de deseos: " . $e->getMessage());
        $response['message'] = 'Error al procesar la solicitud de la lista de deseos.';
    }
} else {
    $response['message'] = 'Solicitud no válida.';
}

echo json_encode($response);
