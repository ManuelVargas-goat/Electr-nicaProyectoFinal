<?php
include("config.php");

if (!isset($_SESSION['usuario'])) {
    header("Location: UserLogin.php");
    exit();
}

$usuario = $_SESSION['usuario'];

// Obtener datos del usuario actual (nombre) para el sidebar
$sql = "SELECT uc.usuario_cliente_id, per.nombre, per.apellido_paterno, uc.usuario_cliente_id
        FROM usuario_cliente uc
        JOIN persona per ON uc.persona_id = per.persona_id
        WHERE uc.usuario = :usuario";

$stmt = $pdo->prepare($sql);
$stmt->execute([':usuario' => $usuario]);

$usuarioActual = $stmt->fetch(PDO::FETCH_ASSOC);

$nombreUsuario = 'Usuario'; // Valor por defecto
$usuarioId = null;

if ($usuarioActual) {
    $nombreUsuario = $usuarioActual['nombre'] . ' ' . $usuarioActual['apellido_paterno'];
    $usuarioId = $usuarioActual['usuario_cliente_id'];
}

// Obtener compras realizadas
$compras = "SELECT c.usuario_cliente_id as cliente_id, c.compra_id, c.fecha_compra 
                        FROM compra c
                        WHERE c.usuario_cliente_id = :usuario 
                        AND c.estado_id = 2 ORDER BY c.compra_id DESC";
$stmtCompras = $pdo->prepare($compras);
$stmtCompras->execute([':usuario' => $usuarioId]);
$ComprasUsuario = $stmtCompras->fetchAll(PDO::FETCH_ASSOC);

print_r($usuarioId);

print_r($ComprasUsuario);

// Obtener relaciones producto-proveedor-pedido
$relaciones = $pdo->query("
    SELECT dc.compra_id, dc.producto_id, p.nombre AS producto, dc.cantidad
    FROM detalle_compra dc
    JOIN producto p ON dc.producto_id = p.producto_id
    WHERE dc.compra_id IN (SELECT compra_id FROM compra WHERE estado_id = 2)
    ORDER BY dc.compra_id DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pedidoId = $_POST['pedido_id'];
    $productoId = $_POST['producto_id'];
    $cantidad = $_POST['cantidad'];
    $motivo = $_POST['motivo'];

    try {
        $pdo->beginTransaction();

        // Insertar la devolución
        $sql = "INSERT INTO devolucion (fecha, tipo, usuario_cliente_id, observaciones)
                VALUES (CURRENT_TIMESTAMP, 'cliente', :usuario_id, :motivo)
                RETURNING devolucion_id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':usuario_id' => $usuarioId,
            ':motivo' => $motivo
        ]);
        $devolucionId = $stmt->fetchColumn();

        // Insertar detalle de devolución
        $sqlDetalle = "INSERT INTO detalle_devolucion (devolucion_id, producto_id, cantidad, motivo, reingresado_stock)
                       VALUES (:devolucion_id, :producto_id, :cantidad, :motivo, 'false')";
        $stmt = $pdo->prepare($sqlDetalle);
        $stmt->execute([
            ':devolucion_id' => $devolucionId,
            ':producto_id' => $productoId,
            ':cantidad' => $cantidad,
            ':motivo' => $motivo
        ]);

        $pdo->commit();
        header("Location: UserRegistroPedido.php");
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        die("Error al registrar devolución: " . $e->getMessage());
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

    <link rel="stylesheet" href="css/index.css">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css"
        integrity="sha512-Evv84Mr4kqVGRNSgIGL/F/aIDqQb7xQ2vcrdIwxfjThSH8CSR7PBEakCr51Ck+w+/U6swU2Im1vVX0SVk9ABhg=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        .sidebar a {
            display: block;
            padding: 8px 15px;
            color: #0b0b0b;
            text-decoration: none;
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



        <div class="container-fluid">

            <div class="row">

                <div class="col-md-3 sidebar">
                    <div class="user-box text-center mb-4">
                        <img src="https://cdn-icons-png.flaticon.com/512/149/149071.png" alt="Usuario"
                            class="img-fluid rounded-circle mb-2" style="width: 64px;">

                        <div class="fw-bold" style="color: #0b0b0b;"><?= htmlspecialchars($nombreUsuario) ?></div>

                    </div>
                    <nav class="d-none d-md-block">
                        <ul>
                            <a href="UserInfo.php">Cuenta</a>
                            <a href="UserRegistroPedido.php" class="active">Ordenes</a>
                            <a href="<?php echo $_SERVER['PHP_SELF']; ?>?logout=true">Cerrar sesion</a>
                        </ul>
                    </nav>
                </div>

                <div class="col-md-9">
                    <form method="POST" class="bg-white p-4 rounded shadow-sm">

                        <div class="mb-3">
                            <label class="form-label">Usuario</label>
                            <input type="text" class="form-control" value="<?= htmlspecialchars($nombreUsuario) ?>"
                                readonly>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Pedido Entregado</label>
                            <select name="compra_id" id="compra_id" class="form-select" required>
                                <option value="">Seleccione una compra</option>
                                <?php foreach ($ComprasUsuario as $p): ?>
                                    <option value="<?= $p['compra_id'] ?>">#<?= $p['compra_id'] ?> -
                                        <?= $p['fecha_compra'] ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Producto</label>
                            <select name="producto_id" id="producto_id" class="form-select" required disabled>
                                <option value="">Seleccione un pedido primero</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Cantidad</label>
                            <input type="number" name="cantidad" class="form-control" required min="1">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Motivo</label>
                            <textarea name="motivo" class="form-control" required></textarea>
                        </div>

                        <div class="d-flex justify-content-between">
                            <a href="gestion_existencias_devoluciones.php" class="btn btn-secondary">Cancelar</a>
                            <button type="submit" class="btn btn-primary">Registrar Devolución</button>
                        </div>
                    </form>

                </div>



            </div>

        </div>
        <script>

            const relaciones = <?= json_encode($relaciones) ?>;

            const compraSelect = document.getElementById('compra_id')
            const productoSelect = document.getElementById('producto_id')
            const cantidadInput = document.querySelector('input[name="cantidad"]')


            console.log(compraSelect)

            let cantidadMax = 0;

            compraSelect.addEventListener('change', () => {
                const compraId = parseInt(compraSelect.value);
                console.log(compraId)
                productoSelect.innerHTML = '<option value="">Seleccione un producto</option>'
                productoSelect.disabled = true
                cantidadInput.value = ''
                cantidadInput.removeAttribute('max')

                if (!compraId) return

                const productos = relaciones.filter(r => r.compra_id == compraId);
                productos.forEach(p => {
                    const opt = document.createElement('option')
                    opt.value = p.producto_id
                    opt.textContent = p.producto
                    opt.dataset.maxCantidad = p.cantidad
                    productoSelect.appendChild(opt)
                });

                productoSelect.disabled = false;
            });


            productoSelect.addEventListener('change', () => {
                const option = productoSelect.options[productoSelect.selectedIndex];
                cantidadMax = parseInt(option.dataset.maxCantidad) || 0;
                cantidadInput.value = '';
                cantidadInput.setAttribute('max', cantidadMax);
                cantidadInput.setAttribute('placeholder', `Máx: ${cantidadMax}`);
            });

            cantidadInput.addEventListener('input', () => {
                const val = parseInt(cantidadInput.value);
                if (val > cantidadMax) {
                    cantidadInput.value = cantidadMax;
                }
            })

        </script>

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
        <!-- End Footer -->

    </div>

</body>

</html>