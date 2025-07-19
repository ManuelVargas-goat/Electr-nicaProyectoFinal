<?php
include("config.php");

// Obtener categorías para el filtro
$categoriasLista = $pdo->query("SELECT categoria_id, nombre FROM categoria ORDER BY nombre")->fetchAll();

// Obtener precio mas alto de los productos
$precioAlto = $pdo->query("SELECT MAX(precio) as precio FROM producto")->fetch(PDO::FETCH_ASSOC);


// Parámetros de filtro
$busqueda = $_GET['buscar'] ?? '';
$categoriaFiltrada = $_GET['categoria'] ?? '';
$filtroPrecioMin = $_GET['precio_min'] ?? '';
$filtroPrecioMax = $_GET['precio_max'] ?? '';

// Consulta dinámica
$where = [];
$params = [];

if (!empty($busqueda)) {
    $where[] = "(p.nombre ILIKE :buscar OR CAST(p.producto_id AS TEXT) ILIKE :buscar)";
    $params[':buscar'] = "%$busqueda%";
}

if (!empty($categoriaFiltrada)) {
    $where[] = "p.categoria_id = :categoria";
    $params[':categoria'] = $categoriaFiltrada;
}

if (!empty($filtroPrecioMin)) {
    $where[] = "p.precio >= :precio_min";
    $params[':precio_min'] = $filtroPrecioMin;
}
if (!empty($filtroPrecioMax)) {
    $where[] = "p.precio <= :precio_max";
    $params[':precio_max'] = $filtroPrecioMax;
}


$sqlProductos = "SELECT p.producto_id, p.nombre, p.descripcion, p.marca, p.precio, c.nombre AS categoria,p.ruta_imagen as imagen
                 FROM producto p
                 LEFT JOIN categoria c ON p.categoria_id = c.categoria_id";

if ($where) {
    $sqlProductos .= " WHERE " . implode(" AND ", $where);
}

$sqlProductos .= " ORDER BY p.producto_id DESC";

$stmt = $pdo->prepare($sqlProductos);
$stmt->execute($params);
$productos = $stmt->fetchAll();
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

                    <form class="d-flex mx-auto" role="search" action="/buscar" method="GET" style="max-width: 600px;">
                        <input class="form-control" type="search" placeholder="Buscar..." aria-label="Buscar" name="q">
                        <button class="btn btn-outline-light ms-2" type="submit">Buscar</button>
                    </form>

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



        <!-- Inicio Filtros -->
        <div class="container my-4 ">
            <div class="row">
                <div class="col-md-3">
                    <h5>Filtros de búsqueda</h5>

                    <form method="GET">
                        <div class="mb-3">
                            <input type="text" name="buscar" class="form-control" placeholder="Buscar..."
                                value="<?= htmlspecialchars($busqueda) ?>">
                        </div>
                        <div class="mb-3">
                            <select name="categoria" class="form-select">
                                <option value="">Todas las Categorías</option>
                                <?php foreach ($categoriasLista as $cat): ?>
                                    <option value="<?= $cat['categoria_id'] ?>" <?= $categoriaFiltrada == $cat['categoria_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($cat['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <ul class="list-inline">
                                <li class="list-inline-item">
                                    <input name="precio_min" class="form-control" type="number" id="precio-min" name="precio-min" min="0"
                                        max="<?= htmlspecialchars($precioAlto['precio']) ?>" placeholder="0">
                                </li>
                                <li class="list-inline-item">
                                    <p>-</p>
                                </li>
                                <li class="list-inline-item ">
                                    <input name="precio_max" class="form-control" type="number" id="precio-max" name="precio-max" min="0"
                                        max="<?= htmlspecialchars($precioAlto['precio']) ?>"  placeholder="<?= htmlspecialchars($precioAlto['precio']) ?>">
                                </li>
                            </ul>
                        </div>
                        <div class="mb-2 d-grid">
                            <button type="submit" class="btn btn-primary">Buscar</button>
                        </div>
                        <div class="mb-2 d-grid">
                            <a href="Inicio_Principal_Busqueda.php" class="btn btn-secondary">Limpiar</a>
                        </div>
                    </form>
                </div>


                <!-- Inicio Carga de Productos -->

                <div class="col-md-9">

                    <div class="row">
                        <?php foreach ($productos as $producto): ?>
                            <div class="col-md-4 mb-4">
                                <div class="card">
                                    <div class="flexbox card-head">
                                        <img src="../<?= $producto['imagen']; ?>" class="card-img-top-prod"
                                            alt="<?php echo htmlspecialchars($producto['nombre']); ?>">
                                    </div>
                                    <div class="card-body">
                                        <h5 class="card-title"><?php echo htmlspecialchars($producto['nombre']); ?></h5>
                                        <p class="card-text"><?php echo htmlspecialchars($producto['descripcion']); ?></p>
                                        <div class="row">
                                            <div class="d-grid col-9">
                                                <a href="Producto.php?id=<?= $producto['producto_id'] ?>"
                                                    class="btn btn-warning"><?= $producto['precio'] ?></a>
                                            </div>
                                            <div class="d-grid col-2">
                                                <button class="btn btn-primary"
                                                    onclick="addProducto(<?= $producto['producto_id'] ?>)"><i
                                                        class="fa-solid fa-cart-plus"></i></button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <?php if (empty($productos)): ?>
                            <p>No hay productos que coincidan con los filtros.</p>
                        <?php endif; ?>
                    </div>

                </div>
            </div>
        </div>
    </div>




    <!-- Script Contador de Productos en Carrito -->
    <script>

        function addProducto(id) {
            let url = "/paginas/Electr-nicaProyectoFinal/ecommerce/comprasact.php"
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
    <!-- Fin Footer -->

    <div>
</body>

</html>