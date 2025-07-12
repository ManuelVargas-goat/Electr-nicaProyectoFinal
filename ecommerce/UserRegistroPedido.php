<?php
include("config.php");

if (!isset($_SESSION['usuario'])) {
    header("Location: UserLogin.php");
    exit();
}

$usuario = $_SESSION['usuario'];

print_r($usuario );

// Mensajes de notificación desde la URL
$mensaje = $_GET['mensaje'] ?? '';
$tipoMensaje = $_GET['tipo'] ?? '';


// Obtener datos del usuario actual (nombre) para el sidebar
$sql = "SELECT per.nombre, per.apellido_paterno, uc.persona_id
        FROM usuario_cliente uc
        JOIN persona per ON uc.persona_id = per.persona_id
        WHERE uc.usuario = :usuario";

$stmt = $pdo->prepare($sql);
$stmt->execute([':usuario' => $usuario]);

$usuarioActual = $stmt->fetch(PDO::FETCH_ASSOC);

$nombreUsuario = 'Usuario'; // Valor por defecto
$personaId = null;

if ($usuarioActual) {
    $nombreUsuario = $usuarioActual['nombre'] . ' ' . $usuarioActual['apellido_paterno'];
    $personaId = $usuarioActual['persona_id'];
}

print_r($personaId );

if ($personaId) {
    $sqlCompras = "SELECT c.compra_id,c.fecha_compra,e.nombre as estado, 
                   COALESCE(per.nombre || ' ' || per.apellido_paterno, 'N/A') AS cliente,
                   SUM(dc.cantidad * CASE 
                   WHEN dc.precio_unitario = 0 THEN pr.precio
                   ELSE dc.precio_unitario
                   END ) AS total_precio,
                   SUM(dc.cantidad) AS total_cantidad
                   FROM compra c 
                   JOIN detalle_compra dc ON c.compra_id = dc.compra_id
                   JOIN estado e ON c.estado_id = e.estado_id
                   JOIN producto pr ON dc.producto_id = pr.producto_id
                   LEFT JOIN usuario_cliente uc ON c.usuario_cliente_id = uc.usuario_cliente_id
                   LEFT JOIN persona per ON uc.persona_id = per.persona_id
                   WHERE per.persona_id = :persona_id
                   GROUP BY c.compra_id, c.fecha_compra, per.nombre, per.apellido_paterno, e.nombre
                   ORDER BY c.compra_id DESC";
    $stmtCompras = $pdo->prepare($sqlCompras);
    $stmtCompras->execute([':persona_id' => $personaId]);
    $ComprasUsuario = $stmtCompras->fetchAll(PDO::FETCH_ASSOC);
}

if (isset($_GET['logout']) && $_GET['logout'] == 'true') {
    $_SESSION = array(); // Limpiar variables de sesión
    session_destroy();  
    header("Location: Inicio_Principal.php");
    exit();
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
     <style>
        .sidebar a {
            display: block;
            padding: 8px 15px;
            color: #0b0b0b;
            text-decoration: none;
        }


     </style>

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

<div class="container-fluid">

        <div class="row">

            <div class="col-md-3 sidebar">
                <div class="user-box text-center mb-4">
                    <img src="https://cdn-icons-png.flaticon.com/512/149/149071.png" alt="Usuario" class="img-fluid rounded-circle mb-2" style="width: 64px;">

                    <div class="fw-bold"><?= htmlspecialchars($nombreUsuario) ?></div>

            </div>
            <a href="UserInfo.php">Cuenta</a>
            <a href="UserRegistroPedido.php">Ordenes</a>
            <a href="<?php echo $_SERVER['PHP_SELF']; ?>?logout=true">Cerrar sesion</a>

            </div>

            <div class="col-md-8 content">

                <div class="text-center">

                <?php if (count($ComprasUsuario) > 0): ?>
                    <?php foreach ($ComprasUsuario as $row): ?>

                        <div class="card">
                            <div class="card-header bg-light fw-bold d-flex justify-content-between align-items-center">
                                
                            <h4 class="card-title fw-bold mb-0"><?= htmlspecialchars( date('d-m-Y', strtotime($row['fecha_compra'])) ) ?></h4> <span># <?= htmlspecialchars($row['compra_id']) ?></span>
                        </div>

                    <div class="card-body">
                        <h6 class="card-title fw-bold">Estado: <?= htmlspecialchars($row['estado']) ?></h6>
                        <h6 class="card-title fw-bold">Total de la Orden: <?= htmlspecialchars($row['total_precio']) ?></h6>
                    </div>
                    <?php endforeach; ?>
                    <?php else: ?>
                    <tr>
                        <td colspan="7" class="text-center">No se encontraron pedidos.</td>
                     </tr>
                    <?php endif; ?>

                </div>

            </div>
        
        </div>


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