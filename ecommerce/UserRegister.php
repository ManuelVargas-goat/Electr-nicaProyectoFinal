<?php
include("config.php");

$success_message = '';
$error_message = '';

if($_SERVER['REQUEST_METHOD'] === 'POST'){

   $nombres = trim($_POST['nombres'] ?? '');
   $apellidopat = trim($_POST['apellidopat'] ?? '');
   $apellidomat = trim($_POST['apellidomat'] ?? '');
   $sexo = $_POST['sexo']; 
   $fechanac = $_POST['fechanac']; 
   $direccion = trim($_POST['direccion'] ?? '');
   $telefono = trim($_POST['telefono'] ?? '');
   $email = trim($_POST['email'] ?? '');

   $usuario = trim($_POST['usuario'] ?? '');
   $password = trim($_POST['password'] ?? '');
   $repassword = trim($_POST['repassword'] ?? '');

   if (empty($nombres) || empty($apellidopat)|| empty($apellidomat) || empty($email) || empty($telefono) || empty($direccion) || empty($fechanac) || empty($sexo) ||
        empty($usuario) || empty($password) || empty($repassword)) {
        $error_message = "Todos los campos obligatorios deben ser completados.";
    } elseif ($password !== $repassword) {
        $error_message = "Las contraseñas no coinciden.";
    } else {
        try {

            // Iniciar transacción
            $pdo->beginTransaction();

             // 1. Insertar en la tabla persona
            $sql_persona = "INSERT INTO persona (nombre, apellido_paterno, apellido_materno, fecha_nacimiento, sexo, email, direccion, telefono) VALUES (:nombre, :apellido_paterno, :apellido_materno, :fecha_nacimiento, :sexo, :email, :direccion, :telefono)";
            $stmt_persona = $pdo->prepare($sql_persona);
            $stmt_persona->execute([
                ':nombre' => $nombres,
                ':apellido_paterno' => $apellidopat,
                ':apellido_materno' => $apellidomat,
                ':fecha_nacimiento' => $fechanac,
                ':sexo' => $sexo,
                ':email' => $email,
                ':direccion' => $direccion,
                ':telefono' => $telefono
            ]);
            $persona_id = $pdo->lastInsertId(); // Obtener el ID de la persona recién insertada

            $sql_empleado = "INSERT INTO usuario_cliente (usuario, clave, rol, persona_id) VALUES (:usuario, :clave, :rol, :persona_id)";
            $stmt_empleado = $pdo->prepare($sql_empleado);
            $stmt_empleado->execute([
                ':usuario' => $usuario,
                ':clave' => $password,
                ':rol' => 'cliente',
                ':persona_id' => $persona_id
            ]);

            $pdo->commit(); // Confirmar la transacción
            $success_message = "Usuario agregado correctamente.";
            // Redirigir a la página de gestión de empleados después de un éxito
            header("Location: UserRegistroPedido.php?agregado=ok");
            exit();
        } catch (PDOException $e) {
            $pdo->rollBack(); // Revertir la transacción en caso de error
            // Manejo de errores específicos de base de datos
            if ($e->getCode() === '23505') { // Código de error para violación de unique constraint (ej. usuario o email ya existen)
                $error_message = "El nombre de usuario o el email ya existen. Por favor, elija otros.";
            } else {
                $error_message = "Error de base de datos al agregar empleado: " . $e->getMessage();
            }
        } catch (Exception $e) {
            $pdo->rollBack(); // Revertir la transacción en caso de error de lógica
            $error_message = "Error: " . $e->getMessage();
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

 <div class="register-box">
    <h2>Ingrese sus datos</h2>

 <form class="row g-3" action="UserRegister.php" method="post" autocomplete="of">
    <div class="col-md-6">
        <label for="nombre">Nombre <span class="text-danger">*</span></label>
        <input type="text" name="nombres" id="nombres" class="form-control" required>
    </div>
    <div class="col-md-6">
        <label for="apellidopat">Apellido Paterno y Materno <span class="text-danger">*</span></label>
        <div class="input-group">
            <input type="text" name="apellidopat" id="apellidopat" class="form-control" required>
            <input type="text" name="apellidomat" id="apellidomat" class="form-control" required>
        </div>
    </div>
    <div class="col-md-3">
        <label for="sexo">Sexo <span class="text-danger">*</span></label>
        <select name="sexo" id="sexo"  class="form-select" required>
            <option value="H" selected>Hombre</option>
            <option value="M">Mujer</option>
            <option value="S">No especifica</option>
        </select>
    </div>
    <div class="col-md-3">
        <label for="fechanac">Fecha de Nacimiento<span class="text-danger">*</span></label>
        <input type="date" name="fechanac" id="fechanac" class="form-control" required>
    </div>
    <div class="col-md-6">
        <label for="direccion">Direccion</label>
        <input type="text" name="direccion" id="direccion" class="form-control" required>
    </div>
    <div class="col-md-6">
        <label for="telefono">Telefono <span class="text-danger">*</span></label>
        <input type="text" name="telefono" id="telefono" class="form-control" required>
    </div>
    <div class="col-md-6">
        <label for="email">Email <span class="text-danger">*</span></label>
        <input type="email" name="email" id="email" class="form-control" required>
    </div>
    <div class="col-md-6">
        <label for="usuario">Usuario <span class="text-danger">*</span></label>
        <input type="text" name="usuario" id="usuario" class="form-control" required>
    </div>
    <div class="col-md-6 ">
        <label for="password">Contraseña<span class="text-danger">*</span></label>
        <input type="password" name="password" id="password" class="form-control" required>
    </div>
    <div class="col-md-6">
        <label for="repassword">Confirmar Contraseña<span class="text-danger">*</span></label>
        <input type="password" name="repassword" id="repassword" class="form-control" required>
    </div>
   <div class="col-12">
        <button type="submit" class="btn btn-primary">Registrarse</button>
   </div>

 </form>

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