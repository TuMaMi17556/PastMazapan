<?php
session_start();
require 'db.php';

// Validar que venga el folio
if (!isset($_GET['folio'])) { die("Folio no especificado"); }
$folio = $_GET['folio'];

// 1. Obtener datos de la VENTA
$sqlVenta = "SELECT v.*, u.nombre as cajero 
             FROM ventas v 
             JOIN usuarios u ON v.id_usuario = u.id 
             WHERE v.id = ?";
$stmt = $conn->prepare($sqlVenta);
$stmt->bind_param("i", $folio);
$stmt->execute();
$resVenta = $stmt->get_result();

if ($resVenta->num_rows === 0) { die("Venta no encontrada"); }
$venta = $resVenta->fetch_assoc();

// 2. Obtener los PRODUCTOS vendidos
$sqlDetalles = "SELECT d.*, i.nombre 
                FROM ventas_det d 
                JOIN items i ON d.id_item = i.id 
                WHERE d.id_venta = ?";
$stmt2 = $conn->prepare($sqlDetalles);
$stmt2->bind_param("i", $folio);
$stmt2->execute();
$detalles = $stmt2->get_result();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ticket #<?= $folio ?></title>
    <style>
        /* ESTILO EXCLUSIVO DEL TICKET (80mm) */
        body {
            font-family: 'Courier New', Courier, monospace;
            font-size: 12px;
            margin: 0;
            padding: 5px;
            width: 72mm; /* Ancho seguro para impresoras de 80mm */
        }
        .header, .footer { text-align: center; }
        .bold { font-weight: bold; }
        .divider { border-top: 1px dashed black; margin: 5px 0; }
        .row { display: flex; justify-content: space-between; }
        .col-cant { width: 15%; text-align: left; }
        .col-desc { width: 55%; text-align: left; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .col-imp { width: 30%; text-align: right; }
        .text-right { text-align: right; }
    </style>
</head>
<body onload="window.print(); setTimeout(window.close, 1000);">
    
    <div class="header">
        <span class="bold" style="font-size: 14px;">PASTELERÍA DULCE SABOR</span><br>
        RFC: XAXX010101000<br>
        Av. Siempre Viva 123<br>
        Tel: 555-123-4567
    </div>

    <div class="divider"></div>

    <div>
        Folio: <strong>#<?= str_pad($venta['id'], 6, "0", STR_PAD_LEFT) ?></strong><br>
        Fecha: <?= date('d/m/Y H:i', strtotime($venta['fecha'])) ?><br>
        Cajero: <?= $venta['cajero'] ?>
    </div>

    <div class="divider"></div>

    <div class="row bold">
        <span class="col-cant">Cant</span>
        <span class="col-desc">Producto</span>
        <span class="col-imp">Total</span>
    </div>

    <div class="divider"></div>

    <?php while($item = $detalles->fetch_assoc()): ?>
    <div class="row">
        <span class="col-cant"><?= $item['cantidad'] ?></span>
        <span class="col-desc"><?= substr($item['nombre'], 0, 18) ?></span>
        <span class="col-imp">$<?= number_format($item['total'], 2) ?></span>
    </div>
    <?php endwhile; ?>

    <div class="divider"></div>

    <div class="text-right">
        Subtotal: $<?= number_format($venta['subtotal'], 2) ?><br>
        IVA: $<?= number_format($venta['iva'], 2) ?><br>
        <span class="bold" style="font-size: 14px;">TOTAL: $<?= number_format($venta['total'], 2) ?></span>
    </div>

    <br>
    <div class="footer">
        ¡Gracias por su compra!<br>
        Vuelva pronto
    </div>

</body>
</html>