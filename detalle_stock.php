<?php
include("config.php"); // Asegúrate de que este archivo contenga tu conexión PDO ($pdo)
session_start();

// Redirecciona al login si el usuario no ha iniciado sesión
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}

// Validar que se ha proporcionado un ID de producto válido
if (!isset($_GET['producto_id']) || !is_numeric($_GET['producto_id'])) {
    // Si no hay un ID válido, redirige o muestra un mensaje de error
    header("Location: gestion_existencias_stock.php?mensaje=ID de producto no especificado o inválido.&tipo=danger");
    exit();
}

$producto_id = (int)$_GET['producto_id'];

// --- Obtener solo el nombre del producto ---
// Ya no necesitamos el stock de la tabla 'stock' para mostrar el stock actual
$sql_producto_info = "
    SELECT p.nombre
    FROM producto p
    WHERE p.producto_id = :producto_id";
$stmtProductoInfo = $pdo->prepare($sql_producto_info);
$stmtProductoInfo->execute([':producto_id' => $producto_id]);
$productoInfo = $stmtProductoInfo->fetch(PDO::FETCH_ASSOC);

// Si el producto no existe en la base de datos, redirige o muestra un error
if (!$productoInfo) {
    header("Location: gestion_existencias_stock.php?mensaje=Producto no encontrado.&tipo=danger");
    exit();
}

$nombreProducto = $productoInfo['nombre'];
// La variable $stockActual se calculará después de obtener todos los movimientos.


// --- Lógica para identificar pedidos de reingreso ---
// Esto es para diferenciar los "Ingresos nuevos" estándar de los "Reingresos por devolución"
// y evitar que un reingreso sea contado dos veces como "entrada" en el reporte.
$sql_reingreso_pedido_ids = "SELECT DISTINCT pedido_reingreso_id FROM devolucion WHERE pedido_reingreso_id IS NOT NULL";
$stmt_reingreso_pedido_ids = $pdo->prepare($sql_reingreso_pedido_ids);
$stmt_reingreso_pedido_ids->execute();
$reingreso_pedido_ids_array = $stmt_reingreso_pedido_ids->fetchAll(PDO::FETCH_COLUMN, 0);

$in_clause_part = "";
$params_reingreso = [];
if (!empty($reingreso_pedido_ids_array)) {
    // Construye la parte 'AND p.pedido_id NOT IN (...)' dinámicamente
    $placeholders = [];
    foreach ($reingreso_pedido_ids_array as $index => $id) {
        $placeholder_name = ":reingreso_id_" . $index;
        $placeholders[] = $placeholder_name;
        $params_reingreso[$placeholder_name] = $id;
    }
    $in_clause_part = "AND p.pedido_id NOT IN (" . implode(', ', $placeholders) . ")";
}

// --- Consulta principal de movimientos ---
// Esta consulta combina diferentes tipos de movimientos de stock para el producto seleccionado.
// Se utilizan UNION ALL para unir los resultados de varias subconsultas.
// 'sort_timestamp' y 'order_priority' se usan para un ordenamiento preciso cronológicamente,
// manejando casos donde varios eventos ocurren en el mismo instante.
$sql = "
    -- 1. Entrada por pedido (Ingreso nuevo ESTÁNDAR)
    -- Se usa fecha_confirmacion para la fecha y hora de recepción real.
    SELECT
        'Entrada (Ingreso nuevo)' AS tipo,
        TO_CHAR(p.fecha_confirmacion, 'DD/MM/YYYY') AS fecha,
        TO_CHAR(p.fecha_confirmacion, 'HH24:MI:SS') AS hora,
        COALESCE(CAST(p.fecha_confirmacion AS TIMESTAMP), '1900-01-01 00:00:00'::timestamp) AS sort_timestamp,
        3 AS order_priority, -- Prioridad media-baja para entradas generales
        per.nombre || ' ' || per.apellido_paterno AS persona,
        dp.cantidad
    FROM pedido p
    JOIN detalle_pedido dp ON p.pedido_id = dp.pedido_id
    JOIN usuario_empleado ue ON ue.usuario_empleado_id = p.usuario_empleado_id
    JOIN persona per ON ue.persona_id = per.persona_id
    WHERE dp.producto_id = :producto_id_1
      AND p.estado = 'entregado'
      " . $in_clause_part . " -- Excluye pedidos que son reingresos

    UNION ALL

    -- 2. Salida por compra (venta al cliente)
    SELECT
        'Salida (Venta)' AS tipo,
        TO_CHAR(c.fecha_compra, 'DD/MM/YYYY') AS fecha,
        TO_CHAR(c.fecha_compra, 'HH24:MI:SS') AS hora,
        COALESCE(CAST(c.fecha_compra AS TIMESTAMP), '1900-01-01 00:00:00'::timestamp) AS sort_timestamp,
        4 AS order_priority, -- Prioridad baja para salidas generales
        cli.nombre || ' ' || cli.apellido_paterno AS persona,
        dc.cantidad
    FROM compra c
    JOIN detalle_compra dc ON c.compra_id = dc.compra_id
    JOIN usuario_cliente uc ON c.usuario_cliente_id = uc.usuario_cliente_id
    JOIN persona cli ON uc.persona_id = cli.persona_id
    WHERE dc.producto_id = :producto_id_2

    UNION ALL

    -- 3. Devoluciones de cliente (Entrada al stock)
    SELECT
        'Entrada (Devolución de cliente)' AS tipo,
        TO_CHAR(d.fecha, 'DD/MM/YYYY') AS fecha,
        TO_CHAR(d.fecha, 'HH24:MI:SS') AS hora,
        COALESCE(CAST(d.fecha AS TIMESTAMP), '1900-01-01 00:00:00'::timestamp) AS sort_timestamp,
        3 AS order_priority, -- Prioridad media-baja
        uc_persona.nombre || ' ' || uc_persona.apellido_paterno AS persona,
        dd.cantidad
    FROM devolucion d
    JOIN detalle_devolucion dd ON d.devolucion_id = dd.devolucion_id
    LEFT JOIN usuario_cliente uc ON d.usuario_cliente_id = uc.usuario_cliente_id
    LEFT JOIN persona uc_persona ON uc.persona_id = uc_persona.persona_id
    WHERE dd.producto_id = :producto_id_3 AND d.tipo = 'cliente'

    UNION ALL

    -- 4. Salida por Devolución a Proveedor
    -- Siempre es una salida del stock, independientemente de si hay un reingreso posterior.
    SELECT
        'Salida (Devolución a proveedor)' AS tipo,
        TO_CHAR(d.fecha, 'DD/MM/YYYY') AS fecha,
        TO_CHAR(d.fecha, 'HH24:MI:SS') AS hora,
        COALESCE(CAST(d.fecha AS TIMESTAMP), '1900-01-01 00:00:00'::timestamp) AS sort_timestamp,
        1 AS order_priority, -- ALTA PRIORIDAD: Asegura que si hay una salida y un reingreso en el mismo segundo, la salida se lista primero
        COALESCE(ue_persona.nombre || ' ' || ue_persona.apellido_paterno, 'Proveedor/Sistema') AS persona,
        dd.cantidad
    FROM devolucion d
    JOIN detalle_devolucion dd ON d.devolucion_id = dd.devolucion_id
    LEFT JOIN usuario_empleado ue ON d.usuario_empleado_id = ue.usuario_empleado_id
    LEFT JOIN persona ue_persona ON ue.persona_id = ue_persona.persona_id
    WHERE dd.producto_id = :producto_id_4 AND d.tipo = 'proveedor'

    UNION ALL

    -- 5. Entrada por Reingreso por Devolución (cuando se reingresa stock después de una devolución a proveedor)
    -- También usa la fecha de confirmación del pedido de reingreso.
    SELECT
        'Entrada (Reingreso por devolución)' AS tipo,
        TO_CHAR(p_reingreso.fecha_confirmacion, 'DD/MM/YYYY') AS fecha,
        TO_CHAR(p_reingreso.fecha_confirmacion, 'HH24:MI:SS') AS hora,
        COALESCE(CAST(p_reingreso.fecha_confirmacion AS TIMESTAMP), '1900-01-01 00:00:00'::timestamp) AS sort_timestamp,
        2 AS order_priority, -- Media prioridad: Aparece después de la salida a proveedor pero antes de otras entradas
        COALESCE(ue_reingreso_persona.nombre || ' ' || ue_reingreso_persona.apellido_paterno, 'Proveedor/Sistema') AS persona,
        dd.cantidad
    FROM devolucion d
    JOIN detalle_devolucion dd ON d.devolucion_id = dd.devolucion_id
    JOIN pedido p_reingreso ON d.pedido_reingreso_id = p_reingreso.pedido_id
    LEFT JOIN usuario_empleado ue_reingreso ON p_reingreso.usuario_empleado_id = ue_reingreso.usuario_empleado_id
    LEFT JOIN persona ue_reingreso_persona ON ue_reingreso.persona_id = ue_reingreso_persona.persona_id
    WHERE dd.producto_id = :producto_id_5
      AND d.tipo = 'proveedor'
      AND dd.reingresado_stock IS TRUE
      AND p_reingreso.estado = 'entregado'

    -- Ordena todos los movimientos, primero por fecha/hora descendente, luego por prioridad ascendente
    -- Esto asegura que, en el mismo instante, las salidas (prioridad 1) aparezcan antes que los reingresos (prioridad 2),
    -- y estos antes que otras entradas (prioridad 3).
    ORDER BY sort_timestamp DESC, order_priority ASC NULLS LAST
";

$stmt = $pdo->prepare($sql);

// Combina todos los parámetros para la ejecución de la consulta
$all_params = [
    ':producto_id_1' => $producto_id,
    ':producto_id_2' => $producto_id,
    ':producto_id_3' => $producto_id,
    ':producto_id_4' => $producto_id,
    ':producto_id_5' => $producto_id,
];
// Agrega los parámetros para la cláusula IN de reingresos si existen
$all_params = array_merge($all_params, $params_reingreso);

try {
    $stmt->execute($all_params);
    $movimientos = $stmt->fetchAll(PDO::FETCH_ASSOC); // Usar FETCH_ASSOC para nombres de columna

    // --- Calcular el stock actual a partir de los movimientos ---
    $calculatedStockActual = 0;
    foreach ($movimientos as $mov) {
        if (str_starts_with($mov['tipo'], 'Entrada')) {
            $calculatedStockActual += $mov['cantidad'];
        } elseif (str_starts_with($mov['tipo'], 'Salida')) {
            $calculatedStockActual -= $mov['cantidad'];
        }
    }

} catch (PDOException $e) {
    // Muestra un mensaje de error al usuario y registra el error en el log del servidor
    echo "<div class='container mt-4 alert alert-danger' role='alert'>
                Error al cargar movimientos: " . htmlspecialchars($e->getMessage()) . "
              </div>";
    error_log("Error en detalle_stock.php para producto ID " . $producto_id . ": " . $e->getMessage());
    exit();
}

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Detalle de Stock</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        /* Estilos personalizados si son necesarios, por ejemplo, para las filas de la tabla */
        .table-hover tbody tr:hover {
            background-color: #f5f5f5; /* Un gris claro al pasar el ratón */
        }
    </style>
</head>
<body>
<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="mb-0">Reporte de Movimientos - Producto ID <?= htmlspecialchars($producto_id) ?>: <span class="text-primary"><?= htmlspecialchars($nombreProducto) ?></span></h3>

        <div class="alert alert-info py-2 px-3 m-0">
            <strong>Stock Actual:</strong> <?= htmlspecialchars($calculatedStockActual) ?> unidades
        </div>
    </div>

    <?php if (empty($movimientos)): ?>
        <div class="alert alert-warning">No hay movimientos registrados para este producto.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-bordered table-hover text-center">
                <thead class="table-dark">
                    <tr>
                        <th>Tipo de Movimiento</th>
                        <th>Fecha</th>
                        <th>Hora</th>
                        <th>Involucrado</th>
                        <th>Cantidad</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($movimientos as $mov): ?>
                        <tr>
                            <td style="background-color: <?= str_starts_with($mov['tipo'], 'Entrada') ? '#d4edda' : '#ffe5b4' ?>;">
                                <?= htmlspecialchars($mov['tipo']) ?>
                            </td>
                            <td><?= htmlspecialchars($mov['fecha']) ?></td>
                            <td><?= htmlspecialchars($mov['hora']) ?></td>
                            <td><?= htmlspecialchars($mov['persona']) ?></td>
                            <td><?= htmlspecialchars($mov['cantidad']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <a href="gestion_existencias_stock.php" class="btn btn-secondary mt-3">Volver a Stock</a>
</div>
</body>
</html>