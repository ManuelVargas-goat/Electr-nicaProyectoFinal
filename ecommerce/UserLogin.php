<?php
include("config.php");

$mensaje = '';


if (isset($_SESSION['usuario'])) {
    header('Location: UserInfo.php');
    exit();
} else if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $usuario = $_POST['usuario'];
    $clave = $_POST['clave'];

    // Consulta con JOIN a la tabla rol
    $sql = "SELECT uc.usuario, uc.clave, uc.rol
            FROM usuario_cliente uc
            WHERE uc.usuario = :usuario AND uc.clave = :clave"; // Usar $clave_hashed

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':usuario' => $usuario,
        ':clave' => $clave // Pasar la clave encriptada
    ]);

    if ($stmt->rowCount() == 1) {
        $datos = $stmt->fetch(PDO::FETCH_ASSOC);
        $_SESSION['usuario'] = $datos['usuario'];
        $_SESSION['rol'] = $datos['rol']; // ← Guardamos el rol aquí

        header("Location:  UserInfo.php");
        exit;
    } else {
        $mensaje = "❌ Usuario o contraseña incorrectos.";
    }
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

                    <form class="d-flex mx-auto" role="search" action="Inicio_Principal_Busqueda.php" method="GET"
                        style="max-width: 600px;">
                        <input type="text" name="buscar" class="form-control" placeholder="Buscar...">
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

    <div class="login-box">
        <h4 class="mb-4 text-center">Bienvenido Usuario</h4>
        <form method="POST">
            <div class="mb-3">
                <label for="usuario" class="form-label">Nombre de Usuario</label>
                <input type="text" class="form-control" id="usuario" name="usuario" required>
            </div>
            <div class="mb-3">
                <label for="clave" class="form-label">Contraseña
                    <a href="UserRecoverPassword.php" class="text-info text-end text-decoration-none">¿Olvidaste tu
                        contraseña?</a></label>
                <input type="password" class="form-control" id="clave" name="clave" required>
            </div>
            <div class="d-grid">
                <button type="submit" class="btn btn-primary">Ingresar</button>
            </div>
        </form>
        <div class="text-end mt-3">
            <p>¿Aun no tienes una cuenta? <a href="UserRegister.php"
                    class="text-info text-decoration-none">Registrate</a></p>
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