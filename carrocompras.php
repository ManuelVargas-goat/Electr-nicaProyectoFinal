<?php
include("config.php");

$productos = isset($_SESSION['carrito']['productos']) ? $_SESSION['carrito']['productos'] : null;

print_r($_SESSION);
if ($productos != null){
     foreach ($productos as $clave => $cantidad){

        $sql = $pdo->prepare("SELECT pr.producto_id as id,pr.nombre as nombre,pr.precio as precio, $cantidad as cantidad, pr.marca as marca, pr.descripcion as descripcion
                              From producto pr
                              WHERE pr.producto_id=? AND descontinuado= false ");
        $sql->execute([$clave]);
        $lista_carrito[]=$sql->fetch(PDO::FETCH_ASSOC);

     }

}

?>


<!DOCTYPE html>
<html lang="es" >
  <head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Tienda Informatica</title>

     <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
     <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
     
     <link rel="stylesheet" href="css/index.css">

     <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" integrity="sha512-Evv84Mr4kqVGRNSgIGL/F/aIDqQb7xQ2vcrdIwxfjThSH8CSR7PBEakCr51Ck+w+/U6swU2Im1vVX0SVk9ABhg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
  </head>

<!-- Nav Bar Redes-->
    <nav class="navbar navbar-expand-lg bg-dark navbar-light d-none d-lg-block" id="templatemo_nav_top">
        <div class="container text-light">
            <div class="w-100 d-flex justify-content-between">
                <div>
                    <i class="fa fa-envelope mx-2"></i>
                    <a class="navbar-sm-brand text-light text-decoration-none" href="mailto:info@company.com">ExperienciasFormativas@isur.edu.pe</a>
                    <i class="fa fa-phone mx-2"></i>
                    <a class="navbar-sm-brand text-light text-decoration-none" href="tel:010-020-0340">984 854 555</a>
                </div>
                <div>
                    <a class="text-light" href="https://fb.com/templatemo" target="_blank" rel="sponsored"><i class="fab fa-facebook-f fa-sm fa-fw me-2"></i></a>
                    <a class="text-light" href="https://www.instagram.com/" target="_blank"><i class="fab fa-instagram fa-sm fa-fw me-2"></i></a>
                    <a class="text-light" href="https://twitter.com/" target="_blank"><i class="fab fa-twitter fa-sm fa-fw me-2"></i></a>
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

            <div class="collapse navbar-collapse"  style="display: flex; justify-content: flex-end;" id="navbarHeader">

                <a href="login.php" class="btn btn-warning"><i class="fa-solid fa-user"></i> Usuario </a>
                <a href="carrocompras.php" class="btn btn-primary position-relative">
                <i class="fa-solid fa-cart-shopping"></i> Carrito <span id="num_cart" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"><?php echo $num_cart;?></span></a>

            </div>
        
    
        </div>

    </div>

</header>

<!-- Fin Header -->

<body>

<div class="container">
    <br><br>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Imagen</th>
                    <th>Producto</th>
                    <th>Precio</th>
                    <th>Cantidad</th>
                    <th>Subtotal</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if($lista_carrito == null){
                    echo '<tr><td colspan="5" class="text-center"><b>Lista vacia</b></td></tr>';
                } else {
                    $total = 0;
                    foreach($lista_carrito as $producto){
                        
                        $_id = $producto['id'];
                        $nombre = $producto ['nombre'];
                        $precio = $producto['precio'];
                        $cantidad = $producto['cantidad'];
                        $subtotal= $cantidad * $precio;
                        $total= $subtotal;
                     ?>
                <tr>
                    <td><?php echo $_id; ?></td>
                    <td><?php echo $nombre; ?></td>
                    <td><?php echo $precio; ?></td>
                    <td>
                        <input type="number" min="1" max="10" step="1" value="<?php echo $cantidad; ?>" 
                        size="5" id="cantidad_<?php echo $_id; ?>" onchange="">
                    </td>
                    <td><div id="subtotal_<?php echo $_id; ?>" name="subtotal[]"><?php echo $subtotal; ?></div>
                </td>
                    <td><a href="#" id="eliminar" class="btn btn-warning btn-sm" data-bs-id="<?php echo $_id; ?>"
                    data-bs-toogle="modal" data-bs-target="eliminarModal">Eliminar</a></td>
                </tr>
               <?php } ?>

               <tr>
                <td colspan="4"></td>
                <td colspan="2">
                    <p class="h3" id="total"><?php echo 'S/.' . number_format($total,2,'.',',') ;?></p>
                </td>
               </tr>
            </tbody>
         <?php } ?>
        </table>
    </div>
    
    <div class="row">
        <div class="col md-5 offset-md-7 d-grid gap-2">
            <button class="btn btn-primary btn-lg">Realizar pago</button>
        </div>
    </div>

    <br><br>

</div>

</body>
  
<!-- Start Footer -->
    <footer class="bg-dark" id="tempaltemo_footer">
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
                            <a class="text-decoration-none" href="mailto:info@company.com">ExperienciasFormativas@isur.edu.pe</a>
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
                            <a class="text-light text-decoration-none" target="_blank" href="http://facebook.com/"><i class="fab fa-facebook-f fa-lg fa-fw"></i></a>
                        </li>
                        <li class="list-inline-item border border-light rounded-circle text-center">
                            <a class="text-light text-decoration-none" target="_blank" href="https://www.instagram.com/"><i class="fab fa-instagram fa-lg fa-fw"></i></a>
                        </li>
                        <li class="list-inline-item border border-light rounded-circle text-center">
                            <a class="text-light text-decoration-none" target="_blank" href="https://twitter.com/"><i class="fab fa-twitter fa-lg fa-fw"></i></a>
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

</html>    