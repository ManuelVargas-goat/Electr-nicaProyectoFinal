<?php
include("config.php");

$mensaje = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Lógica para verificar el código de verificación (primera etapa del reset)
    if (isset($_POST['codigo'])) {
        if (isset($_SESSION['codigo_verificacion']) && $_POST['codigo'] == $_SESSION['codigo_verificacion']) {
            $_SESSION['codigo_validado'] = true;
            $mensaje = "✅ Código verificado correctamente. Ahora puedes establecer tu nueva contraseña.";
        } else {
            $mensaje = "❌ Código incorrecto.";
            $_SESSION['codigo_validado'] = false; // Asegurar que no avance si el código es incorrecto
        }
    // Lógica para actualizar la contraseña (segunda etapa del reset)
    } elseif (isset($_POST['nueva_clave'])) {
        // Solo permitir la actualización si el código fue validado
        if (!isset($_SESSION['codigo_validado']) || !$_SESSION['codigo_validado']) {
            $mensaje = "Acceso denegado. El código de verificación no ha sido validado.";
            // Considera redirigir de vuelta a la página de verificación si no está validado
            // header("Location: recuperar.php"); 
            // exit;
        } elseif ($_POST['nueva_clave'] === $_POST['confirmar_clave']) {
            $usuario = $_SESSION['usuario_para_reset'] ?? null; // Asegúrate de que este valor esté seteado

            if ($usuario) {

                $nueva_clave_hashed = $_POST['nueva_clave'];


                $sql = "UPDATE usuario_cliente SET clave = :clave WHERE usuario = :usuario";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':clave' => $nueva_clave_hashed, // Guardar la clave encriptada
                    ':usuario' => $usuario
                ]);

                // Limpiar sesiones relacionadas con el reset
                session_unset();
                session_destroy(); // Destruye toda la sesión

                header("Location: UserLogin.php?msg=clave_actualizada");
                exit;
            } else {
                $mensaje = "❌ No se pudo determinar el usuario para actualizar la contraseña. Por favor, reinicie el proceso.";
            }
        } else {
            $mensaje = "⚠️ Las contraseñas no coinciden.";
        }
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

                <a href="UserLogin.php" class="btn btn-warning"><i class="fa-solid fa-user"></i> Usuario </a>
                
                <a href="carrocompras.php" class="btn btn-primary position-relative">
                <i class="fa-solid fa-cart-shopping"></i> Carrito <span id="num_cart" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"><?php echo $num_cart;?></span></a>

            </div>
        
    
        </div>

    </div>

</header>

<!-- Fin Header -->

<body>

 <div class="login-box">
        <h4 class="mb-4 text-center">Nueva Contraseña</h4>
        
        <?php if ($mensaje && (!isset($_POST['nueva_clave']) || ($_POST['nueva_clave'] !== $_POST['confirmar_clave']))): ?>
            <div class="alert alert-warning mt-3"><?= $mensaje ?></div>
        <?php endif; ?>

        <?php if (isset($_SESSION['codigo_validado']) && $_SESSION['codigo_validado']): ?>
            <form method="POST">
                <div class="mb-3">
                    <label for="nueva_clave" class="form-label">Nueva Contraseña:</label>
                    <input type="password" class="form-control" name="nueva_clave" id="nueva_clave" required>
                </div>
                <div class="mb-3">
                    <label for="confirmar_clave" class="form-label">Confirmar Nueva Contraseña:</label>
                    <input type="password" class="form-control" name="confirmar_clave" id="confirmar_clave" required>
                </div>
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary">Actualizar Contraseña</button>
                </div>
            </form>
        <?php elseif (!isset($_POST['codigo']) && (!isset($_SESSION['codigo_validado']) || !$_SESSION['codigo_validado'])): ?>
            <div class="alert alert-danger">El código de verificación no ha sido validado.
                <a href="recuperar_cuenta.php" class="text-info">Volver al inicio del proceso de recuperación</a>.
            </div>
        <?php endif; ?>

        <?php if ($mensaje && (isset($_POST['nueva_clave']) && $_POST['nueva_clave'] === $_POST['confirmar_clave'])): ?>
            <div class="alert alert-success mt-3"><?= $mensaje ?></div>
        <?php endif; ?>
        
 </div>

</body>
  
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