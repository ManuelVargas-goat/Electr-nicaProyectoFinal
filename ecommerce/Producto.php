<?php

include("config.php");

$cantidadinit = 1;
$id = isset($_GET['id']) ? $_GET['id'] : '';

if($id == ''){
    echo 'Error al CARGAR el producto';
    exit;

} else {
    
    $sql= "SELECT count(producto_id) FROM producto WHERE producto_id=?";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    $resultado = $stmt->fetchAll();

    if ($resultado > 0){

        $sql = $pdo->prepare("SELECT pr.producto_id as id,pr.nombre as nombre,pr.precio as precio, pr.marca as marca, pr.descripcion as descripcion, cat.nombre as categoria, cat.categoria_id as catid
                              From producto pr INNER JOIN categoria cat 
                              ON cat.categoria_id = pr.categoria_id
                              WHERE producto_id=? LIMIT 1");
        $sql->execute([$id]);
        $row = $sql->fetch(PDO::FETCH_ASSOC);
        $id = $row['id'];
        $nombre = $row['nombre'];
        $descripcion = $row['descripcion'];
        $marca = $row['marca'];
        $precio = $row['precio'];
        $catid = $row['catid'];


        $SIMsql = "SELECT pr.producto_id as id,pr.nombre,pr.precio, pr.marca, pr.descripcion, cat.nombre as categoria, pr.ruta_imagen as imagen
                       From producto pr INNER JOIN categoria cat 
                       ON cat.categoria_id = pr.categoria_id
                       WHERE pr.categoria_id= :catid
                       AND producto_id <> :personaid ";

        $stmt = $pdo->prepare($SIMsql);
        $stmt->execute([':catid' => $catid, ':personaid' => $id]);
        $resultadoSIM = $stmt->fetchAll();


    } else {
        echo 'Error al cargar el producto';
        exit;
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

                <a href="UserLogin.php" class="btn btn-warning"><i class="fa-solid fa-user"></i> Usuario </a>
                <a href="carrocompras.php" class="btn btn-primary position-relative">
                <i class="fa-solid fa-cart-shopping"></i> Carrito <span id="num_cart" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"><?php echo $num_cart;?></span></a>

            </div>
        
    
        </div>

    </div>

</header>

<!-- Fin Header -->
<body>

    <!-- Inicio Card Producto Seleccionado -->

    <section class="bg-light">
        <div class="container pb-5">
            <div class="row">
                <div class="col-lg-5 mt-5">
                    <div class="card mb-3">
                        <img class="card-img img-fluid" src="imgs/<?= $nombre; ?>.png" alt="Card image cap" id="product-detail">
                    </div>
                    
                </div>
                <!-- col end -->
                <div class="col-lg-7 mt-5">
                    <div class="card">
                        <div class="card-body">
                            <h1 class="h2"><?=  $nombre; ?></h1>
                            <p class="h3 py-2">S/. <?=  $precio; ?></p>
                            <ul class="list-inline">
                                <li class="list-inline-item">
                                    <h6>Marca:</h6>
                                </li>
                                <li class="list-inline-item">
                                    <p class="text-muted"><strong><?=  $marca; ?></strong></p>
                                </li>
                            </ul>

                            <h6>Descripcion:</h6>
                            <p><?=  $descripcion; ?></p>

                            <h6>Especificaciones:</h6> <!-- Por ver, no hay informacion en las tablas -->
                            <ul class="list-unstyled pb-3">
                                <li>Lorem ipsum dolor sit</li>
                                <li>Amet, consectetur</li>
                                <li>Adipiscing elit,set</li>
                                <li>Duis aute irure</li>
                                <li>Ut enim ad minim</li>
                                <li>Dolore magna aliqua</li>
                                <li>Excepteur sint</li>
                            </ul>
                                <div class="row">
                                    
                                    <div class="col-auto">
                                        <ul class="list-inline pb-3">
                                            <li class="list-inline-item text-right">
                                                Cantidad
                                                <input type="hidden" name="product-quanity" id="product-quanity" value="1">
                                            </li>
                                            <li class="list-inline-item"><span class="btn btn-success" id="btn-minus" onclick="menos()">-</span></li>
                                            <li class="list-inline-item"><span type="number" class="badge bg-secondary" id="cantidadprod" ><?php echo $cantidadinit; ?></span></li>
                                            <li class="list-inline-item"><span class="btn btn-success" id="btn-plus" onclick="mas()">+</span></li>
                                        </ul>
                                    </div>
                                </div>


                                <div class="row pb-3">
                                    <div class="col d-grid">
                                        <a type="submit" href="carrocompras.php" class="btn btn-success btn-lg" onclick="addProducto(<?=  $id; ?>)" name="submit" value="buy">Comprar</a>
                                    </div>
                                    <div class="col d-grid">
                                        <button type="submit" class="btn btn-success btn-lg" onclick="addProducto(<?=  $id; ?>)" name="submit" value="addtocard">Añadir al Carro</button>
                                    </div>
                                </div>
                          

                        </div>
                    </div>
                </div>

                <div>


                 <!-- Inicio Productos selecionados en general -->
            <section class="bg-light">
                <div class="container py-5">
                    <div class="row text-center py-3">
                        <div class="col-lg-6 m-auto">
                            <h1 class="h1">Productos que podrian interesarte</h1>
                        </div>
                    </div>
                    <div class="row">


                        <div class="container-fluid bg-trasparent my-4 p-3" style="position: relative;">
                            <div class="row row-cols-1 row-cols-xs-2 row-cols-sm-2 row-cols-lg-4 g-3">

                                <?php foreach ($resultadoSIM as $rowSIM): ?>

                                    <div class="col">
                                        <div class="card h-100 shadow-sm"> <img src="../<?= $rowSIM['imagen']; ?>"
                                                class="card-img-top" alt="..."></a>
                                            <div class="card-body">
                                                <div class="h2 card-title text-center"><span
                                                        class="float-center price-hp"><?= $rowSIM['nombre'] ?></span>
                                                </div>
                                                <p class=" my-4"><?= $rowSIM['descripcion'] ?></p>
                                                <div class="row">
                                                    <div class="d-grid col-9"><a href="Producto.php?id=<?= $row['id'] ?>"
                                                            class="btn btn-warning"><?= $rowSIM['precio'] ?></a> </div>
                                                    <div class="d-grid col-2">
                                                        <buttton class="btn btn-primary"
                                                            onclick="addProducto(<?= $rowSIM['id'] ?>)"><i
                                                                class="fa-solid fa-cart-plus"></i></button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                <?php endforeach; ?>


                            </div>
                        </div>

                    </div>
                </div>
            </section>
            <!-- Fin Productos selecionados en general -->



                </div>
            </div>
        </div>
    </section>
    <!-- Fin Card Producto Seleccionado -->

    <section>

    
    </section>

<!-- Script Contador de Productos en Carrito -->
    <script>

    
    function mas() {
      let elementoContador = document.getElementById("cantidadprod").innerHTML
      if(elementoContador < 20) {
       elementoContador++
       document.getElementById('cantidadprod').innerHTML = elementoContador
      }      
    }

    function menos() {
      let elementoContador = document.getElementById("cantidadprod").innerHTML
      if(elementoContador > 1 ) {
       elementoContador--
       document.getElementById('cantidadprod').innerHTML = elementoContador
      }   
    }
        function addProducto(id){
            let url = "comprasact.php"
            let cantidad = document.getElementById("cantidadprod").textContent;
            let formData = new FormData()
            
            formData.append('id',id) 
            formData.append('cantidad',cantidad)         


            fetch(
                url,
                {
                method:'POST',
                body: formData,   
                mode: 'cors',     
            }).then(response=>response.json()).then((data) => {
                if(data.ok){
                    console.log(data.numero);
                    let elemento = document.getElementById("num_cart")
                    elemento.innerHTML = data.numero
                }else{console.log('Error')}
            })
        }


     </script>

</body>






  
<!-- Inicio Footer -->
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
    <!-- Fin Footer -->

</html>    