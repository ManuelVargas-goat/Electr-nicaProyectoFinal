<?php
include("config.php");

$sql = "SELECT pr.producto_id as id,pr.nombre,pr.precio, pr.marca, pr.descripcion, cat.nombre as categoria, pr.ruta_imagen as imagen
       From producto pr INNER JOIN categoria cat 
       ON cat.categoria_id = pr.categoria_id";

$stmt = $pdo->prepare($sql);
$stmt->execute();
$resultado = $stmt->fetchAll();
?>


<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Tienda Informatica</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css"
        integrity="sha512-Evv84Mr4kqVGRNSgIGL/F/aIDqQb7xQ2vcrdIwxfjThSH8CSR7PBEakCr51Ck+w+/U6swU2Im1vVX0SVk9ABhg=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />

    <link rel="stylesheet" href="css/index.css">

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
                <div class="container d-flex justify-content-between align-items-center">

                    <a href="Inicio_Principal.php" class="navbar-brand">
                        <strong>Tienda Electronica</strong>
                    </a>

                    <form class="d-flex mx-auto" role="search" action="Inicio_Principal_Busqueda.php" method="GET" style="max-width: 600px;">
                        <input class="form-control" type="search" placeholder="Buscar..." aria-label="Buscar" name="q">
                        <button class="btn btn-outline-light ms-2" type="submit">Buscar</button>
                    </form>

                    <div class="d-flex gap-2">
                        <a href="UserLogin.php" class="btn btn-warning"><i class="fa-solid fa-user"></i> Usuario </a>
                        <a href="carrocompras.php" class="btn btn-primary position-relative">
                            <i class="fa-solid fa-cart-shopping"></i> Carrito
                            <span id="num_cart" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                <?php echo $num_cart; ?>
                            </span>
                        </a>
                    </div> 


                </div>

            </div>

        </header>

        <!-- Fin Header -->



        <!-- Inicio Carousel de Productos Selecionados -->
        <div class="content">
            <div id="carousel" class="carousel slide" data-bs-ride="carousel">

                <div class="carousel-inner">
                    <div class="carousel-item active">
                        <div class="container">
                            <div class="row p-5">
                                <div class="mx-auto col-md-8 col-lg-6 order-lg-last">
                                    <img class="img-fluid" src="imgs/AlcatelPiolus.png" alt="">
                                </div>
                                <div class="col-lg-6 mb-0 d-flex align-items-center">
                                    <div class="text-align-left align-self-center">
                                        <h1 class="h1 text-success"><b>Tienda</b> Electronica</h1>
                                        <h3 class="h2">Los mejores descuentos en moviles</h3>
                                        <p>
                                            Del <strong>11 al 15</strong> de noviembre ¡Aprovecha y compra todo da
                                            precios increibles!
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="carousel-item">
                        <div class="container">
                            <div class="row p-5">
                                <div class="mx-auto col-md-8 col-lg-6 order-lg-last">
                                    <img class="img-fluid" src="imgs/AcerMaximus.png" alt="">
                                </div>
                                <div class="col-lg-6 mb-0 d-flex align-items-center">
                                    <div class="text-align-left">
                                        <h1 class="h1">Proident occaecat</h1>
                                        <h3 class="h2">Aliquip ex ea commodo consequat</h3>
                                        <p>
                                            Lorem ipsum dolor sit amet consectetur adipiscing elit, placerat blandit
                                            malesuada nisi laoreet semper vel ornare, venenatis vivamus natoque
                                            tristique phasellus taciti.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="carousel-item">
                        <div class="container">
                            <div class="row p-5">
                                <div class="mx-auto col-md-8 col-lg-6 order-lg-last">
                                    <img class="img-fluid" src="imgs/Moto G Movil.png" alt="">
                                </div>
                                <div class="col-lg-6 mb-0 d-flex align-items-center">
                                    <div class="text-align-left">
                                        <h1 class="h1">Repr in voluptate</h1>
                                        <h3 class="h2">Ullamco laboris nisi ut </h3>
                                        <p>
                                            Lorem ipsum dolor sit amet consectetur adipiscing elit, placerat blandit
                                            malesuada nisi laoreet semper vel ornare, venenatis vivamus natoque
                                            tristique phasellus taciti.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <button class="carousel-control-prev" type="button" data-bs-slide="prev">
                    <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                    <span class="visually-hidden">Previous</span>
                </button>
                <button class="carousel-control-next" type="button" data-bs-slide="next">
                    <span class="carousel-control-next-icon" aria-hidden="true"></span>
                    <span class="visually-hidden">Next</span>
                </button>
            </div>

            <!-- Fin Carousel de Productos Selecionados -->

            <!-- Inicio Productos selecionados en general -->
            <section class="bg-light">
                <div class="container py-5">
                    <div class="row text-center py-3">
                        <div class="col-lg-6 m-auto">
                            <h1 class="h1">Ofertas de semana</h1>
                            <p>
                                No te pierdas la oportunidad de aprovechar estos descuentos!
                            </p>
                        </div>
                    </div>
                    <div class="row">


                        <div class="container-fluid bg-trasparent my-4 p-3" style="position: relative;">
                            <div class="row row-cols-1 row-cols-xs-2 row-cols-sm-2 row-cols-lg-4 g-3">

                                <?php foreach ($resultado as $row): ?>

                                    <div class="col">
                                        <div class="card h-100 shadow-sm"> <img src="../<?= $row['imagen']; ?>"
                                                class="card-img-top" alt="..."></a>
                                            <div class="card-body">
                                                <div class="h2 card-title text-center"><span
                                                        class="float-center price-hp"><?= $row['nombre'] ?></span>
                                                </div>
                                                <p class=" my-4"><?= $row['descripcion'] ?></p>
                                                <div class="row">
                                                    <div class="d-grid col-9"><a href="Producto.php?id=<?= $row['id'] ?>"
                                                            class="btn btn-warning"><?= $row['precio'] ?></a> </div>
                                                    <div class="d-grid col-2">
                                                        <buttton class="btn btn-primary"
                                                            onclick="addProducto(<?= $row['id'] ?>)"><i
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

            <!-- Script Contador de Productos en Carrito -->
            <script>

                function addProducto(id) {
                    let url = "comprasact.php"
                    let formData = new FormData()
                    formData.append('id', id)


                    fetch(
                        url,
                        {
                            method: 'POST',
                            body: formData,
                            mode: 'cors',
                        }).then(response => response.json()).then((data) => {
                            if (data.ok) {
                                console.log(data.numero);
                                let elemento = document.getElementById("num_cart")
                                elemento.innerHTML = data.numero
                            } else { console.log('Error') }
                        })
                }


            </script>
        </div>



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
                                <a class="text-light text-decoration-none" target="_blank"
                                    href="http://facebook.com/"><i class="fab fa-facebook-f fa-lg fa-fw"></i></a>
                            </li>
                            <li class="list-inline-item border border-light rounded-circle text-center">
                                <a class="text-light text-decoration-none" target="_blank"
                                    href="https://www.instagram.com/"><i class="fab fa-instagram fa-lg fa-fw"></i></a>
                            </li>
                            <li class="list-inline-item border border-light rounded-circle text-center">
                                <a class="text-light text-decoration-none" target="_blank"
                                    href="https://twitter.com/"><i class="fab fa-twitter fa-lg fa-fw"></i></a>
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

        <div>
</body>

</html>