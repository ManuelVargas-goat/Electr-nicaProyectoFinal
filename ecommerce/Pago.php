<?php
include("config.php");

if (!isset($_SESSION['usuario'])) {
    header('Location: UserLogin.php');
    exit();
}

$usuario = $_SESSION['usuario'];

$sql = "SELECT usuario_cliente_id FROM usuario_cliente WHERE usuario = :usuario";
$stmt = $pdo->prepare($sql);
$stmt->execute([':usuario' => $usuario]);
$cliente = $stmt->fetch();

if (!$cliente) {
    die("Cliente no encontrado.");
} else {
    $usuarioclienteId = $cliente['usuario_cliente_id'];
}

$productoSeleccionados = isset($_SESSION['carrito']['productoSeleccionados']) ? $_SESSION['carrito']['productoSeleccionados'] : null;

if ($productoSeleccionados != null) {
    print_r($productoSeleccionados);
} else {
    header("Location: Inicio_Principal.php");
    exit;
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['RealizarPedido'])) {
    print_r('Se realizara el conteo');
    $conteo = 0;

    if (is_array($productoSeleccionados)) {
        foreach ($productoSeleccionados as $elemento) {
            if (is_array($elemento)) {
                $conteo++;
            }
        }
    }

    if ($conteo) {
        $pdo->beginTransaction();

        try {
            // USAR NOW() en lugar de CURRENT_DATE
            $stmt = $pdo->prepare("INSERT INTO compra (usuario_cliente_id,fecha_compra, estado_id, almacen_id)
                                   VALUES (:usuario_id, NOW(), '1','1') RETURNING compra_id");
            $stmt->execute([':usuario_id' => $usuarioclienteId]);
            $compraId = $stmt->fetchColumn();

            $stmt = $pdo->prepare("INSERT INTO detalle_compra
                (compra_id, producto_id, proveedor_id, cantidad, precio_unitario)
                VALUES (:compra_id, :producto_id, '1', :cantidad, :precio)");

            foreach ($productoSeleccionados as $producto) {

                $_id = $producto['id'];
                $precio = $producto['precio'];
                $cantidad = $producto['cantidad'];

                $stmt->execute([
                    ':compra_id' => $compraId,
                    ':producto_id' => $_id,
                    ':cantidad' => $cantidad,
                    ':precio' => $precio
                ]);
            }

       
            $pdo->commit();
            header("Location: Inicio_Principal.php");
            exit();
        } catch (Exception $e) {
            $pdo->rollBack();
            die("Error al registrar pedido: " . $e->getMessage());
        }
    }


} else {
    print_r("Los valores no llegaron");
}
?>


<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Tienda Informatica</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>

    <link rel="stylesheet" href="css/index.css">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css"
        integrity="sha512-Evv84Mr4kqVGRNSgIGL/F/aIDqQb7xQ2vcrdIwxfjThSH8CSR7PBEakCr51Ck+w+/U6swU2Im1vVX0SVk9ABhg=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
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
                        <a class="navbar-sm-brand text-light text-decoration-none" href="tel:010-020-0340">984 854
                            555</a>
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
                <div class="container">
                    <a href="Inicio_Principal.php" class="navbar-brand">
                        <strong>Tienda Electronica</strong>
                    </a>

                    <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
                        data-bs-target="#navbarHeader" aria-controls="navbarHeader" aria-label="Toggle navigation">
                        <span class="navbar-toggler-icon"></span>
                    </button>

                    <div class="collapse navbar-collapse" style="display: flex; justify-content: flex-end;"
                        id="navbarHeader">

                        <a href="UserLogin.php" class="btn btn-warning"><i class="fa-solid fa-user"></i> Usuario </a>
                        <a href="carrocompras.php" class="btn btn-primary position-relative">
                            <i class="fa-solid fa-cart-shopping"></i> Carrito <span id="num_cart"
                                class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"><?php echo $num_cart; ?></span></a>

                    </div>


                </div>

            </div>

        </header>

        <!-- Fin Header -->



        <div class="content">
            <div class="container">
                <br><br>

                <div class="table-responsive">
                    <table class="table" style="text-align: center;">
                        <thead>
                            <tr>
                                <th>Imagen</th>
                                <th>Producto</th>
                                <th>Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($productoSeleccionados)) {
                                echo '<tr><td colspan="5" class="text-center"><b>Lista vacia</b></td></tr>';
                            } else {
                                $total = 0;
                                foreach ($productoSeleccionados as $producto) {

                                    $_id = $producto['id'];
                                    $nombre = $producto['nombre'];
                                    $precio = $producto['precio'];
                                    $cantidad = $producto['cantidad'];
                                    $subtotal = $producto['subtotal'];
                                    $total += $subtotal;
                                    ?>
                                    <tr id="info_productos">
                                        <td style="display: none;"><?php echo $_id; ?></td>

                                        <td> <a href="Producto.php?id=<?php echo $_id; ?>">
                                                <img class="icontable" style="width: 100px; height: auto;"
                                                    src="imgs/<?= $nombre; ?>.png" alt="Imagen de <?php echo $nombre; ?>">
                                            </a></td>

                                        <td><?php echo $nombre; ?></td>

                                        <td style="display: none;"><?php echo $precio; ?></td>

                                        <td>
                                            <div id="subtotal_<?php echo $_id; ?>" name="subtotal[]">
                                                <?php echo 'S/.' . number_format($subtotal, 2, '.', ','); ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php } ?>

                                <tr>
                                    <td colspan="4">
                                        <p class="h3 text-end" id="total" name="name">
                                            <?php echo 'S/.' . number_format($total, 2, '.', ','); ?>
                                        </p>
                                    </td>
                                </tr>
                            </tbody>
                        <?php } ?>
                    </table>
                </div>

                <form id="form_RealizarPedido" method="POST">
                    <input type="hidden" name="RealizarPedido" id="RealizarPedido" value="9" />

                    <button type="submit" class="btn btn-primary">Realizar Compra</button>

                </form>
            </div>



            <br><br>


            <script>

                function EnviarProductos() {

                    const filas = document.querySelectorAll('#info_productos')
                    const productos = []

                    filas.forEach(function (tr) {
                        const celdas = tr.querySelectorAll('td')

                        // Extrae los datos de las celdas, ajusta según tu estructura
                        const id = celdas[0].innerText.trim() // ID oculto en la primera columna
                        const nombre = celdas[2].innerText.trim() // Producto
                        const precio = celdas[3].innerText.trim() // Precio
                        const cantidad = celdas[4].innerText.trim() // Cantidad

                        console.log(productos)

                        productos.push({
                            id: id,
                            nombre: nombre,
                            precio: precio,
                            cantidad: cantidad
                        });
                    });


                    // Convertir a JSON
                    const productosJSON = JSON.stringify(productos)

                    // Asignar al input oculto
                    document.getElementById('productos_json').value = productosJSON

                    // Enviar el formulario
                    document.getElementById('form_productos').submit()
                }


            </script>




        </div>

    </div>

    <!-- Start Footer -->
    <footer class="bg-dark" id="footer">
        <div class="container">
            <div class="row">

                <div class="col-md-4 pt-5">
                    <h2 class="h2 text-success border-bottom pb-3 border-light logo">Tienda electronica</h2>
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

                    <ul class="list-unstyled text-light footer-link-list">

                    </ul>
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