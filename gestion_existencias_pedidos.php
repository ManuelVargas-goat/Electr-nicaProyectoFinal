<?php
session_start(); // Inicia la sesión para poder usar variables de sesión
include("config.php"); // Asegúrate de que 'config.php' contenga la conexión PDO a tu base de datos.

// Redirecciona al login si el usuario no ha iniciado sesión
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}

// Obtener datos del usuario actual (si ya no están en sesión)
$usuario = $_SESSION['usuario'];
$sql_usuario = "SELECT per.nombre, per.apellido_paterno, r.nombre AS rol
                FROM usuario_empleado ue
                JOIN persona per ON ue.persona_id = per.persona_id
                JOIN rol r ON ue.rol_id = r.rol_id
                WHERE ue.usuario = :usuario";
$stmt = $pdo->prepare($sql_usuario);
$stmt->execute([':usuario' => $usuario]);
$datosUsuario = $stmt->fetch(PDO::FETCH_ASSOC);

// Asignar valores
$nombreCompleto = 'Usuario Desconocido'; // Valor predeterminado
$rolUsuario = ''; // Valor predeterminado
if ($datosUsuario) {
    $nombreCompleto = htmlspecialchars($datosUsuario['nombre'] . ' ' . $datosUsuario['apellido_paterno']);
    $rolUsuario = strtolower(htmlspecialchars($datosUsuario['rol']));
}

// **LÓGICA PARA MOSTRAR MENSAJES EN ALERTAS DE BOOTSTRAP**
// Priorizamos los mensajes almacenados en la sesión (para redirecciones POST)
// Luego, si no hay en sesión, verificamos los parámetros GET
$mensaje = '';
$tipoMensaje = ''; // Puede ser 'success', 'danger', 'warning', 'info'

if (isset($_SESSION['modal_message']) && !empty($_SESSION['modal_message'])) {
    $mensaje = $_SESSION['modal_message'];
    $tipoMensaje = $_SESSION['modal_type'];
    // Limpiar las variables de sesión para que el mensaje no se muestre de nuevo al recargar la página
    unset($_SESSION['modal_message']);
    unset($_SESSION['modal_type']);
} elseif (isset($_GET['mensaje']) && !empty($_GET['mensaje'])) {
    // Si el mensaje viene por GET (para redirecciones GET)
    $mensaje = $_GET['mensaje'];
    $tipoMensaje = $_GET['tipo'] ?? 'info'; // 'info' como tipo predeterminado si no se especifica
}


// Procesar acciones (Confirmar/Cancelar Pedido)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['accion']) && !empty($_POST['pedido_id']) && is_numeric($_POST['pedido_id'])) {
        $accion = $_POST['accion'];
        $pedidoId = (int) $_POST['pedido_id'];

        try {
            $pdo->beginTransaction();

            if ($accion === 'confirmar') {
                // Actualizar estado del pedido a 'entregado'
                $stmt = $pdo->prepare("UPDATE pedido SET estado = 'entregado' WHERE pedido_id = :id");
                $stmt->execute([':id' => $pedidoId]);

                // Actualizar stock de los productos en el pedido
                $stmtDetalles = $pdo->prepare("SELECT producto_id, cantidad FROM detalle_pedido WHERE pedido_id = :id");
                $stmtDetalles->execute([':id' => $pedidoId]);

                while ($detalle = $stmtDetalles->fetch(PDO::FETCH_ASSOC)) {
                    $productoId = $detalle['producto_id'];
                    $cantidad = $detalle['cantidad'];

                    // Verificar si el producto ya tiene un registro en stock
                    $checkStock = $pdo->prepare("SELECT COUNT(*) FROM stock WHERE producto_id = :producto_id");
                    $checkStock->execute([':producto_id' => $productoId]);
                    $existe = $checkStock->fetchColumn();

                    if ($existe) {
                        // Si existe, actualizar la cantidad
                        $actualizarStock = $pdo->prepare("UPDATE stock SET cantidad = cantidad + :cantidad WHERE producto_id = :producto_id");
                        $actualizarStock->execute([
                            ':cantidad' => $cantidad,
                            ':producto_id' => $productoId
                        ]);
                    } else {
                        // Si no existe, insertar un nuevo registro de stock
                        $insertarStock = $pdo->prepare("INSERT INTO stock (producto_id, cantidad) VALUES (:producto_id, :cantidad)");
                        $insertarStock->execute([
                            ':producto_id' => $productoId,
                            ':cantidad' => $cantidad
                        ]);
                    }
                }

                $pdo->commit();
                // Usar variables de sesión para el mensaje en redirecciones POST
                $_SESSION['modal_message'] = "Recepción confirmada y stock actualizado correctamente.";
                $_SESSION['modal_type'] = "success";
                header("Location: gestion_existencias_pedidos.php");
                exit();

            } elseif ($accion === 'cancelar') {
                $stmt = $pdo->prepare("UPDATE pedido SET estado = 'cancelado' WHERE pedido_id = :id");
                $stmt->execute([':id' => $pedidoId]);

                $pdo->commit();
                $_SESSION['modal_message'] = "Pedido cancelado correctamente.";
                $_SESSION['modal_type'] = "info";
                header("Location: gestion_existencias_pedidos.php");
                exit();
            }

        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['modal_message'] = "Error al procesar acción: " . $e->getMessage();
            $_SESSION['modal_type'] = "danger";
            header("Location: gestion_existencias_pedidos.php");
            exit();
        }
    }
}


// Obtener todos los estados posibles para el filtro
$estados = $pdo->query("SELECT nombre FROM estado ORDER BY nombre")->fetchAll(PDO::FETCH_COLUMN);

// Lógica de filtrado de pedidos
$filtroPedidoId = $_GET['pedido_id'] ?? '';
$filtroFechaInicio = $_GET['fecha_inicio'] ?? '';
$filtroFechaFin = $_GET['fecha_fin'] ?? '';
$filtroEstado = $_GET['estado'] ?? '';

$where = [];
$params = [];

if (!empty($filtroPedidoId)) {
    // Usar CAST para asegurar que la comparación sea con texto si pedido_id es numérico en la DB
    $where[] = "CAST(p.pedido_id AS TEXT) ILIKE :pedido_id";
    $params[':pedido_id'] = "%$filtroPedidoId%";
}
if (!empty($filtroFechaInicio)) {
    $where[] = "p.fecha_pedido >= :fecha_inicio";
    $params[':fecha_inicio'] = $filtroFechaInicio;
}
if (!empty($filtroFechaFin)) {
    $where[] = "p.fecha_pedido <= :fecha_fin";
    $params[':fecha_fin'] = $filtroFechaFin;
}
if (!empty($filtroEstado)) {
    $where[] = "p.estado ILIKE :estado";
    $params[':estado'] = "$filtroEstado";
}

$whereClause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

// Consulta principal para obtener los pedidos y sus detalles
$sql = "
    SELECT
      p.pedido_id,
      p.fecha_pedido,
      p.estado,
      COALESCE(per.nombre || ' ' || per.apellido_paterno, 'N/A') AS empleado,
      SUM(dp.cantidad * CASE
          WHEN dp.precio_unidad = 0 THEN pr.precio
          ELSE dp.precio_unidad
        END
      ) AS total_precio,
      SUM(dp.cantidad) AS total_cantidad,
      json_agg(json_build_object(
        'producto', pr.nombre,
        'cantidad', dp.cantidad,
        'precio_unitario',
          CASE
            WHEN dp.precio_unidad = 0 THEN pr.precio
            ELSE dp.precio_unidad
          END
      )) AS productos
    FROM pedido p
    JOIN detalle_pedido dp ON p.pedido_id = dp.pedido_id
    JOIN producto pr ON dp.producto_id = pr.producto_id
    LEFT JOIN usuario_empleado ue ON p.usuario_empleado_id = ue.usuario_empleado_id
    LEFT JOIN persona per ON ue.persona_id = per.persona_id
    $whereClause
    GROUP BY p.pedido_id, p.fecha_pedido, per.nombre, per.apellido_paterno, p.estado
    ORDER BY p.pedido_id DESC
";


$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$resultado = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Variable para el nombre del archivo actual (sin la extensión .php)
$currentPage = basename($_SERVER['PHP_SELF'], ".php");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Existencias - Pedidos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/estilos.css?v=<?= time(); ?>">
    <style>
        /* CSS para el sidebar y submenús (duplicado del archivo de ventas para consistencia) */
        .sidebar {
            background-color: #1a202c; /* Color de fondo oscuro para el sidebar */
            color: #e0e0e0;
            padding-top: 20px;
            min-height: 100vh;
        }

        .user-box {
            border-bottom: 1px solid #364052;
            padding-bottom: 20px;
            margin-bottom: 20px;
        }

        .user-box img {
            border: 2px solid #ffc107;
        }

        .user-box .fw-bold {
            color: #ffffff; /* Color blanco para el nombre del usuario */
        }

        .user-box .text-warning {
            color: #ffc107 !important; /* Color amarillo para el rol de administrador */
        }

        .user-box .text-light {
            color: #f8f9fa !important; /* Color claro para otros roles */
        }

        .sidebar a {
            display: block;
            padding: 8px 15px;
            color: #e0e0e0; /* Color gris claro por defecto para el texto normal */
            text-decoration: none;
            border-radius: 5px;
            margin-bottom: 5px;
            transition: background-color 0.3s, color 0.3s;
        }

        .sidebar a:hover {
            background-color: #495057; /* Fondo un poco más oscuro al pasar el ratón */
            color: #ffffff; /* Color blanco al pasar el ratón */
        }

        /* Estilo para el enlace de la página ACTUAL */
        .sidebar a.current-page {
            background-color: #495057; /* Fondo gris oscuro */
            color: #ffc107 !important; /* Texto amarillo anaranjado */
            font-weight: bold;
        }

        /* Submenú */
        .submenu {
            display: none; /* Oculto por defecto */
            padding-left: 20px; /* Indentación */
        }

        .submenu.active {
            display: block; /* Visible cuando está activo */
        }

        .submenu a {
            padding: 6px 0 6px 15px; /* Ajuste para sub-ítems */
            font-size: 0.9em;
            color: #e0e0e0; /* Color por defecto para sub-ítems */
        }

        /* Asegurarse que los sub-enlaces activos también tienen el color */
        .submenu a.current-page {
            background-color: #495057; /* Fondo gris oscuro para submenú activo */
            color: #ffc107 !important; /* Texto amarillo anaranjado para submenú activo */
        }

        /* Estilo para el botón "Volver al Inicio" */
        .sidebar .btn-outline-primary {
            background-color: #1a202c; /* Fondo azul oscuro, mismo que el sidebar */
            color: #ffffff; /* Texto blanco */
            border-color: #007bff; /* Borde azul */
            font-weight: bold;
        }

        .sidebar .btn-outline-primary:hover {
            background-color: #2c3447; /* Un poco más claro al pasar el ratón */
            color: #ffffff;
            border-color: #007bff; /* Borde azul se mantiene */
        }

        /* Estilo para el botón de búsqueda y otros botones de formulario */
        .btn-primary {
            background-color: #007bff; /* Azul de Bootstrap */
            border-color: #007bff;
            font-weight: bold;
        }

        .btn-primary:hover {
            background-color: #0056b3;
            border-color: #0056b3;
        }

        .btn-success {
            background-color: #28a745; /* Verde de Bootstrap */
            border-color: #28a745;
        }

        .btn-success:hover {
            background-color: #218838;
            border-color: #1e7e34;
        }

        .btn-secondary {
            background-color: #6c757d; /* Gris de Bootstrap */
            border-color: #6c757d;
        }

        .btn-secondary:hover {
            background-color: #5a6268;
            border-color: #545b62;
        }

        /* Contenido principal */
        .content {
            padding: 20px;
        }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <div class="col-md-2 sidebar">
            <div class="user-box text-center mb-4">
                <img src="https://cdn-icons-png.flaticon.com/512/149/149071.png" alt="Usuario" class="img-fluid rounded-circle mb-2" style="width: 64px;">
                <div class="fw-bold"><?= $nombreCompleto ?></div>
                <div class="<?= ($rolUsuario === 'admin') ? 'text-warning' : 'text-light' ?> small">
                    <?= ucfirst($rolUsuario === 'admin' ? 'administrador' : $rolUsuario) ?>
                </div>
            </div>

            <a href="#" id="catalogoToggle"
               class="sidebar-link <?= (strpos($currentPage, 'gestion_catalogo') !== false) ? 'current-page' : '' ?>">Gestión de Catálogo</a>
            <div class="submenu <?= (strpos($currentPage, 'gestion_catalogo') !== false) ? 'active' : '' ?>" id="catalogoSubmenu">
                <a href="gestion_catalogo_categorias.php"
                   class="<?= ($currentPage === 'gestion_catalogo_categorias') ? 'current-page' : '' ?>">Categorías</a>
                <a href="gestion_catalogo_productos.php"
                   class="<?= ($currentPage === 'gestion_catalogo_productos') ? 'current-page' : '' ?>">Productos</a>
                <a href="gestion_catalogo_proveedores.php"
                   class="<?= ($currentPage === 'gestion_catalogo_proveedores') ? 'current-page' : '' ?>">Proveedores</a>
            </div>
            
            <a href="gestion_usuarios.php"
               class="<?= ($currentPage === 'gestion_usuarios') ? 'current-page' : '' ?>">Gestión de Usuarios</a>
            
            <a href="#" id="existenciasToggle" 
               class="sidebar-link <?= (strpos($currentPage, 'gestion_existencias') !== false) ? 'current-page' : '' ?>">Gestión de Existencias</a>
            <div class="submenu <?= (strpos($currentPage, 'gestion_existencias') !== false) ? 'active' : '' ?>" id="existenciasSubmenu">
                <a href="gestion_existencias_pedidos.php"
                   class="<?= ($currentPage === 'gestion_existencias_pedidos') ? 'current-page' : '' ?>">Pedidos</a>
                <a href="gestion_existencias_ventas.php"
                   class="<?= ($currentPage === 'gestion_existencias_ventas') ? 'current-page' : '' ?>">Ventas</a>
                <a href="gestion_existencias_devoluciones.php"
                   class="<?= ($currentPage === 'gestion_existencias_devoluciones') ? 'current-page' : '' ?>">Devoluciones</a>
                <a href="gestion_existencias_stock.php"
                   class="<?= ($currentPage === 'gestion_existencias_stock') ? 'current-page' : '' ?>">Stock</a>
            </div>
            
            <a href="configuracion.php"
               class="<?= ($currentPage === 'configuracion') ? 'current-page' : '' ?>">Configuración</a>

            <div class="mt-4 text-center">
                <a href="principal.php" class="btn btn-outline-primary w-100">Volver al Inicio</a>
            </div>
        </div>

        <div class="col-md-10 content">
            <h3 class="main-title mb-4">Gestión de Existencias - Pedidos</h3>

            <?php
            // **BLOQUE PARA MOSTRAR LA ALERTA DE BOOTSTRAP**
            if (!empty($mensaje) && !empty($tipoMensaje)) {
                $alertClass = '';
                switch ($tipoMensaje) {
                    case 'success':
                        $alertClass = 'alert-success';
                        break;
                    case 'danger':
                        $alertClass = 'alert-danger';
                        break;
                    case 'warning':
                        $alertClass = 'alert-warning';
                        break;
                    case 'info':
                        $alertClass = 'alert-info';
                        break;
                    default:
                        $alertClass = 'alert-info'; // Tipo predeterminado si no coincide
                }
                echo '<div class="alert ' . $alertClass . ' alert-dismissible fade show mt-3" role="alert">';
                echo htmlspecialchars($mensaje); // Muestra el mensaje escapando caracteres especiales
                echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>'; // Botón para cerrar la alerta
                echo '</div>';
            }
            ?>

            <div class="mb-3">
                <a href="nuevo_pedido.php" class="btn btn-success">➕ Generar Nuevo Pedido</a>
            </div>

            <form class="row g-2 mb-3" method="GET">
                <div class="col-md-2">
                    <input type="text" name="pedido_id" value="<?= htmlspecialchars($filtroPedidoId) ?>" class="form-control" placeholder="Número de Pedido">
                </div>
                <div class="col-md-2">
                    <label for="fecha_inicio" class="form-label visually-hidden">Fecha Inicio</label>
                    <input type="date" name="fecha_inicio" id="fecha_inicio" value="<?= htmlspecialchars($filtroFechaInicio) ?>" class="form-control" title="Fecha de inicio">
                </div>
                <div class="col-md-2">
                    <label for="fecha_fin" class="form-label visually-hidden">Fecha Fin</label>
                    <input type="date" name="fecha_fin" id="fecha_fin" value="<?= htmlspecialchars($filtroFechaFin) ?>" class="form-control" title="Fecha de fin">
                </div>
                <div class="col-md-2">
                    <select name="estado" class="form-select">
                        <option value="">Todos los Estados</option>
                        <?php foreach ($estados as $estado): ?>
                            <option value="<?= htmlspecialchars($estado) ?>" <?= $filtroEstado === $estado ? 'selected' : '' ?>>
                                <?= ucfirst($estado) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">Buscar</button>
                </div>
                <div class="col-md-2">
                    <a href="gestion_existencias_pedidos.php" class="btn btn-secondary w-100">Limpiar</a>
                </div>
            </form>
            
            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle">
                    <thead class="text-center">
                        <tr>
                            <th>#Pedido</th>
                            <th>Fecha</th>
                            <th>Empleado</th>
                            <th>Cantidad Total</th>
                            <th>Total (S/)</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="text-center">
                        <?php if (count($resultado) > 0): ?>
                            <?php foreach ($resultado as $row): ?>
                                <tr class="table-row">
                                    <td
                                        data-bs-toggle="collapse"
                                        data-bs-target="#detalle<?= $row['pedido_id'] ?>"
                                        class="fw-bold"
                                        style="cursor:pointer">
                                        <?= htmlspecialchars($row['pedido_id']) ?>
                                    </td>
                                    <td><?= htmlspecialchars($row['fecha_pedido']) ?></td>
                                    <td><?= htmlspecialchars($row['empleado']) ?></td>
                                    <td><?= htmlspecialchars($row['total_cantidad']) ?></td>
                                    <td>S/ <?= number_format($row['total_precio'], 2) ?></td>
                                    <td>
                                        <span class="badge bg-<?= strtolower($row['estado']) == 'entregado' ? 'success' : (strtolower($row['estado']) == 'cancelado' ? 'danger' : 'warning') ?>">
                                            <?= ucfirst(htmlspecialchars($row['estado'])) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (!in_array(strtolower($row['estado']), ['entregado', 'cancelado'])): ?>
                                            <div class="dropdown">
                                                <button class="btn btn-sm btn-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">Acciones</button>
                                                <ul class="dropdown-menu">
                                                    <li>
                                                        <form method="POST" class="m-0">
                                                            <input type="hidden" name="pedido_id" value="<?= htmlspecialchars($row['pedido_id']) ?>">
                                                            <input type="hidden" name="accion" value="confirmar">
                                                            <button type="submit" class="dropdown-item">Confirmar Recepción</button>
                                                        </form>
                                                    </li>
                                                    <li>
                                                        <form method="POST" class="m-0">
                                                            <input type="hidden" name="pedido_id" value="<?= htmlspecialchars($row['pedido_id']) ?>">
                                                            <input type="hidden" name="accion" value="cancelar">
                                                            <button type="submit" class="dropdown-item">Cancelar Pedido</button>
                                                        </form>
                                                    </li>
                                                    <li>
                                                        <a class="dropdown-item" href="editar_pedido.php?id=<?= htmlspecialchars($row['pedido_id']) ?>">Modificar</a>
                                                    </li>
                                                </ul>
                                            </div>
                                        <?php else: ?>
                                            <button class="btn btn-sm btn-secondary" disabled>No Acciones</button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr class="collapse bg-light" id="detalle<?= $row['pedido_id'] ?>">
                                    <td colspan="7">
                                        <div class="p-3 text-start">
                                            <h6 class="fw-bold mb-3">Productos del Pedido</h6>
                                            <ul class="list-group list-group-flush">
                                                <?php
                                                $productos_decodificados = json_decode($row['productos'], true);
                                                if (is_array($productos_decodificados)):
                                                    foreach ($productos_decodificados as $item):
                                                ?>
                                                        <li class="list-group-item d-flex justify-content-between">
                                                            <span><?= htmlspecialchars($item['producto']) ?> (<?= htmlspecialchars($item['cantidad']) ?> unid.)</span>
                                                            <span>S/ <?= number_format($item['precio_unitario'], 2) ?></span>
                                                        </li>
                                                <?php
                                                    endforeach;
                                                else:
                                                    echo '<li class="list-group-item">No hay productos asociados a este pedido.</li>';
                                                endif;
                                                ?>
                                            </ul>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center">No se encontraron pedidos.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', (event) => {
        // Lógica del submenu para "Gestión de Catálogo"
        const catalogoToggle = document.getElementById('catalogoToggle');
        const catalogoSubmenu = document.getElementById('catalogoSubmenu');

        // Abre el submenú de catálogo si alguna de sus sub-páginas está activa
        if (catalogoToggle && catalogoSubmenu && catalogoToggle.classList.contains('current-page')) {
            catalogoSubmenu.classList.add('active');
        }

        if (catalogoToggle) { // Solo si el elemento existe
            catalogoToggle.addEventListener('click', function(e) {
                e.preventDefault(); // Previene el comportamiento predeterminado del enlace
                if (catalogoSubmenu) {
                    catalogoSubmenu.classList.toggle('active'); // Alterna la clase 'active' para mostrar/ocultar
                }
            });
        }
        
        // Lógica para el submenú de "Gestión de Existencias"
        const existenciasToggle = document.getElementById('existenciasToggle');
        const existenciasSubmenu = document.getElementById('existenciasSubmenu');

        // Abre el submenú de existencias si alguna de sus sub-páginas está activa
        if (existenciasToggle && existenciasSubmenu && existenciasToggle.classList.contains('current-page')) {
             existenciasSubmenu.classList.add('active');
        }
        
        if (existenciasToggle) {
             existenciasToggle.addEventListener('click', function(e) {
                 e.preventDefault();
                 if (existenciasSubmenu) {
                     existenciasSubmenu.classList.toggle('active');
                 }
             });
        }

        // Script para evitar que los dropdowns se cierren al hacer clic dentro
        const dropdowns = document.querySelectorAll('.dropdown-toggle');
        dropdowns.forEach(btn => {
            btn.addEventListener('click', e => e.stopPropagation());
        });

        const menus = document.querySelectorAll('.dropdown-menu');
        menus.forEach(menu => {
            menu.addEventListener('click', e => e.stopPropagation());
        });
    });
</script>
</body>
</html>