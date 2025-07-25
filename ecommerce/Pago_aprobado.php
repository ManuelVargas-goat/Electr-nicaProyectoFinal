<?php
// Asegúrate de que session_start() está en config.php o al inicio de este archivo
include("config.php"); // Asumiendo que session_start() ya está en config.php

// Redirigir si el usuario no está logueado
if (!isset($_SESSION['usuario'])) {
    header('Location: UserLogin.php');
    exit();
}

$usuario_cliente_id = null;
$usuario_nombre = $_SESSION['usuario']; // Obtenemos el nombre de usuario de la sesión

// Obtener el ID del cliente logueado
try {
    $sql = "SELECT usuario_cliente_id FROM usuario_cliente WHERE usuario = :usuario";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':usuario' => $usuario_nombre]);
    $cliente = $stmt->fetch(PDO::FETCH_ASSOC); // Usar FETCH_ASSOC para acceder por nombre de columna

    if (!$cliente) {
        // Esto no debería pasar si la autenticación es correcta, pero es una buena medida de seguridad
        error_log("ERROR: Usuario logueado '{$usuario_nombre}' no encontrado en la tabla usuario_cliente.");
        die("Error al cargar la información del usuario.");
    }
    $usuario_cliente_id = $cliente['usuario_cliente_id'];
} catch (PDOException $e) {
    error_log("ERROR CRÍTICO: Error al obtener usuario_cliente_id en Pago_aprobado.php: " . $e->getMessage());
    die("Error al cargar la información del usuario.");
}

// Inicializar variables para la compra
$compra_id = isset($_GET['compra_id']) ? intval($_GET['compra_id']) : 0;
$compra_info = null;
$productos_compra = [];
$total_compra = 0;
$mensaje_confirmacion = "";

if ($compra_id > 0) {
    try {
        // Obtener detalles de la compra para el usuario actual
        $sql_compra = $pdo->prepare("SELECT compra_id, fecha_compra, total_compra FROM compra WHERE compra_id = :compra_id AND usuario_cliente_id = :usuario_cliente_id");
        $sql_compra->execute([
            ':compra_id' => $compra_id,
            ':usuario_cliente_id' => $usuario_cliente_id
        ]);
        $compra_info = $sql_compra->fetch(PDO::FETCH_ASSOC);

        if ($compra_info) {
            $total_compra = $compra_info['total_compra'];
            $mensaje_confirmacion = "¡Tu compra N° " . htmlspecialchars($compra_info['compra_id']) . " ha sido realizada con éxito!";

            // Obtener los productos de la compra
            $sql_productos_compra = $pdo->prepare("
                SELECT 
                    dc.cantidad, 
                    dc.precio_unitario, 
                    p.nombre, 
                    p.ruta_imagen 
                FROM detalle_compra dc
                JOIN producto p ON dc.producto_id = p.producto_id
                WHERE dc.compra_id = :compra_id
            ");
            $sql_productos_compra->execute([':compra_id' => $compra_id]);
            $productos_compra = $sql_productos_compra->fetchAll(PDO::FETCH_ASSOC);

        } else {
            $mensaje_confirmacion = "Error: Compra no encontrada o no tienes permiso para verla.";
        }
    } catch (PDOException $e) {
        error_log("ERROR: Error al cargar detalles de la compra en Pago_aprobado.php: " . $e->getMessage());
        $mensaje_confirmacion = "Error al cargar los detalles de tu compra.";
    }
} else {
    $mensaje_confirmacion = "No se proporcionó un ID de compra válido.";
}

// Calcular num_cart para el header. Después de una compra, el carrito de sesión debería estar vacío.
$num_cart = 0;
if (isset($_SESSION['carrito']) && is_array($_SESSION['carrito'])) {
    foreach ($_SESSION['carrito'] as $id_prod_cart => $cantidad_prod_cart) {
        if (is_numeric($id_prod_cart) && is_numeric($cantidad_prod_cart)) {
            $num_cart += (int)$cantidad_prod_cart;
        }
    }
}

?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Tienda Informática - Compra Aprobada</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>

    <link rel="stylesheet" href="css/index.css"> <!-- Asegúrate de que esta ruta sea correcta -->

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css"
        integrity="sha512-Evv84Mr4kqVGRNSgIGL/F/aIDqQb7xQ2vcrdIwxfjThSH8CSR7PBEakCr51Ck+w+/U6swU2Im1vVX0SVk9ABhg=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        .icontable {
            max-width: 100px; /* Ajusta según sea necesario */
            height: auto;
            object-fit: contain;
        }
    </style>
</head>

<body>
    <div class="wrapper">
        <!-- Nav Bar Redes-->
        <nav class="navbar navbar-expand-lg bg-dark navbar-light d-none d-lg-block" id="templatemo_nav_top">
            <div class="container text-light">
                <div class="w-100 d-flex justify-content-between">
                    <div>
                        <i class="fa fa-envelope mx-2"></i>
                        <a class="navbar-sm-brand text-light text-decoration-none"
                            href="mailto:info@company.com">ExperienciasFormativas@isur.edu.pe</a>
                        <i class="fa fa-phone mx-2"></i>
                        <a class="navbar-sm-brand text-light text-decoration-none" href="tel:010-020-0340">984 854 555</a>
                    </div>
                    <div>
                        <a class="text-light" href="https://fb.com/templatemo" target="_blank" rel="sponsored"><i
                                    class="fab fa-facebook-f fa-sm fa-fw me-2"></i></a>
                        <a class="text-light" href="https://www.instagram.com/" target="_blank"><i
                                    class="fab fa-instagram fa-sm fa-fw me-2"></i></a>
                        <a class="text-light" href="https://twitter.com/" target="_blank"><i
                                    class="fab fa-twitter fa-sm fa-fw me-2"></i></a>
                    </div>
                </div>
            </div>
        </nav>
        <!-- Nav Bar Redes-->

        <!-- Header -->
        <header>
            <div class="navbar navbar-expand-lg navbar-dark bg-dark">
                <div class="container d-flex justify-content-between align-items-center">
                    <a href="Inicio_Principal.php" class="navbar-brand">
                        <strong>Tienda Electrónica</strong>
                    </a>
                    <div class="d-flex gap-2">
                        <a href="UserLogin.php" class="btn btn-warning"><i class="fa-solid fa-user"></i> Usuario </a>
                        <a href="carrocompras.php" class="btn btn-primary position-relative">
                            <i class="fa-solid fa-cart-shopping"></i> Carrito
                            <span id="num_cart"
                                class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                <?php echo $num_cart; ?>
                            </span>
                        </a>
                    </div>
                </div>
            </div>
        </header>
        <!-- Fin Header -->

        <div class="content" style="margin: 30px 200px;">
            <div class="container d-flex justify-content-center align-items-center flex-column">
                <div class="text-center">
                    <img src="imgs/compra_aprobada.png" alt="Pago Aprobado" style="max-width: 200px; height: auto;">
                    <h1 class="mt-3 pb-3"><?php echo htmlspecialchars($mensaje_confirmacion); ?></h1>
                </div>

                <?php if ($compra_info && !empty($productos_compra)): ?>
                    <div class="card shadow-sm w-75 mb-4">
                        <div class="card-header bg-success text-white">
                            <h4 class="mb-0">Resumen de tu Compra</h4>
                        </div>
                        <div class="card-body">
                            <p><strong>ID de Compra:</strong> <?php echo htmlspecialchars($compra_info['compra_id']); ?></p>
                            <p><strong>Fecha de Compra:</strong> <?php echo htmlspecialchars($compra_info['fecha_compra']); ?></p>
                            <hr>
                            <h5>Productos Comprados:</h5>
                            <div class="table-responsive">
                                <table class="table table-sm table-striped">
                                    <thead>
                                        <tr>
                                            <th>Imagen</th>
                                            <th>Producto</th>
                                            <th>Cantidad</th>
                                            <th>Precio Unitario</th>
                                            <th>Subtotal</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($productos_compra as $item): 
                                            $item_subtotal = $item['cantidad'] * $item['precio_unitario'];
                                            $image_path = 'imgs/' . htmlspecialchars($item['ruta_imagen'] ?? '');
                                            $fallback_image = 'imgs/no-image.png';
                                        ?>
                                            <tr>
                                                <td>
                                                    <?php if (!empty($item['ruta_imagen']) && file_exists($image_path) && !is_dir($image_path)): ?>
                                                        <img class="icontable" src="<?php echo $image_path; ?>" alt="Imagen de <?php echo htmlspecialchars($item['nombre']); ?>">
                                                    <?php else: ?>
                                                        <img class="icontable" src="<?php echo $fallback_image; ?>" alt="Imagen no disponible">
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($item['nombre']); ?></td>
                                                <td><?php echo htmlspecialchars($item['cantidad']); ?></td>
                                                <td>S/. <?php echo number_format($item['precio_unitario'], 2, '.', ','); ?></td>
                                                <td>S/. <?php echo number_format($item_subtotal, 2, '.', ','); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <td colspan="4" class="text-end"><strong>Total de la Compra:</strong></td>
                                            <td><strong>S/. <?php echo number_format($total_compra, 2, '.', ','); ?></strong></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <a href="Inicio_Principal.php" class="btn btn-primary mt-3">Regresar al inicio principal</a>
            </div>
        </div>

    </div>

    <!-- Start Footer -->
    <footer class="bg-dark" id="footer">
        <div class="container">
            <div class="row">

                <div class="col-md-4 pt-5">
                    <h2 class="h2 text-success border-bottom pb-3 border-light logo">Tienda electrónica</h2>
                    <ul class="list-unstyled text-light footer-link-list">
                        <li>
                            <i class="fa-regular fa-clock fa-fw"></i>
                            Lunes a viernes de 1:45 p.m. a 6:15 p.m. y los domingos de 7:00 a.m. a 2:00 p.m.
                        </li>
                        <li>
                            <i class="fas fa-map-marker-alt fa-fw"></i>
                            Avenida Salaverry 301, Vallecito, Arequipa
                        </li>
                        <li>
                            <i class="fa-brands fa-whatsapp fa-fw"></i>
                            <a class="text-decoration-none" href="tel:010-020-0340">984 854 555</a>
                        </li>
                        <li>
                            <i class="fa fa-envelope fa-fw"></i>
                            <a class="text-decoration-none"
                                href="mailto:info@company.com">ExperienciasFormativas@isur.edu.pe</a>
                        </li>
                    </ul>
                </div>

                <div class="col-md-4 pt-5">
                    <ul class="list-unstyled text-light footer-link-list"></ul>
                </div>

                <div class="col-md-4 pt-5">
                    <h2 class="h2 text-light border-bottom pb-3 border-light">Contacto</h2>
                    <ul class="list-unstyled text-light footer-link-list">
                        <li><a class="text-decoration-none" href="#">Home</a></li>
                        <li><a class="text-decoration-none" href="#">About Us</a></li>
                        <li><a class="text-decoration-none" href="#">FAQs</a></li>
                        <li><a class="text-decoration-none" href="#">Contact</a></li>
                    </ul>
                </div>

            </div>

            <div class="row text-light mb-4">
                <div class="col-12 mb-3">
                    <div class="w-100 my-3 border-top border-light"></div>
                </div>
                <div class="col-auto me-auto">
                    <ul class="list-inline text-left footer-icons">
                        <li class="list-inline-item border border-light rounded-circle text-center">
                            <a class="text-light text-decoration-none" target="_blank" href="http://facebook.com/"><i
                                        class="fab fa-facebook-f fa-lg fa-fw"></i></a>
                        </li>
                        <li class="list-inline-item border border-light rounded-circle text-center">
                            <a class="text-light text-decoration-none" target="_blank"
                                href="https://www.instagram.com/"><i class="fab fa-instagram fa-lg fa-fw"></i></a>
                        </li>
                        <li class="list-inline-item border border-light rounded-circle text-center">
                            <a class="text-light text-decoration-none" target="_blank" href="https://twitter.com/"><i
                                        class="fab fa-twitter fa-lg fa-fw"></i></a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="w-100 bg-black py-3">
            <div class="container">
                <div class="row pt-2">
                    <div class="col-12">
                        <p class="text-left text-light">
                            Copyright &copy; 2025 ISUR
                        </p>
                    </div>
                </div>
            </div>
        </div>

    </footer>
    <!-- End Footer -->
    <div>
</body>

</html>