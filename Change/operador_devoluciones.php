<?php
session_start();
require 'db.php';
if (!isset($_SESSION['user_id'])) { header("Location: index.php"); exit(); }

$venta = null;
if (isset($_GET['folio'])) {
    $folio = $_GET['folio'];
    // Buscar detalles de la venta
    $sql = "SELECT vd.*, i.nombre 
            FROM ventas_det vd 
            JOIN items i ON vd.id_item = i.id 
            WHERE vd.id_venta = $folio";
    $venta = $conn->query($sql);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8"><title>Devoluciones</title>
    <link rel="stylesheet" href="styles.css">
    <script>
        function procesarDevolucion(id_venta, id_item, nombre_prod, max_cant, precio) {
            let cant = prompt("Cantidad a devolver (Máx: " + max_cant + "):");
            if(cant && cant > 0 && cant <= max_cant) {
                
                fetch('backend_devolucion.php', {
                    method: 'POST',
                    headers: {'Content-Type':'application/json'},
                    body: JSON.stringify({
                        id_venta: id_venta, 
                        id_item: id_item, 
                        cantidad: cant
                    })
                })
                .then(r => r.json())
                .then(d => {
                    if(d.success) {
                        // AQUÍ IMPRIMIMOS EL TICKET DE DEVOLUCIÓN
                        imprimirTicketDevolucion(id_venta, nombre_prod, cant, precio);
                    } else {
                        alert(d.msg);
                    }
                });
            } else {
                alert("Cantidad inválida");
            }
        }

        function imprimirTicketDevolucion(folioOriginal, prodNombre, cantidad, precioUnit) {
            const totalDev = (cantidad * precioUnit).toFixed(2);
            const fecha = new Date().toLocaleString();
            
            const html = `
                <div class="ticket-80mm">
                    <div class="text-center bold">PASTELERÍA DULCE SABOR</div>
                    <div class="text-center">Av. Siempre Viva 123</div>
                    <div class="divider"></div>
                    <div class="text-center bold" style="font-size:1.2em;">*** DEVOLUCIÓN ***</div>
                    <div class="divider"></div>
                    <div>
                        Ref. Venta: #${folioOriginal}<br>
                        Fecha: ${fecha}<br>
                        Atendió: <?php echo $_SESSION['user_name']; ?>
                    </div>
                    <div class="divider"></div>
                    <div class="t-row bold">
                        <span class="c-cant">Cant</span>
                        <span class="c-desc">Producto</span>
                        <span class="c-imp">Reembolso</span>
                    </div>
                    <div class="divider"></div>
                    <div class="t-row">
                        <span class="c-cant">-${cantidad}</span>
                        <span class="c-desc">${prodNombre}</span>
                        <span class="c-imp">-$${totalDev}</span>
                    </div>
                    <div class="divider"></div>
                    <div class="text-right bold">TOTAL DEVUELTO: -$${totalDev}</div>
                    <div class="divider"></div>
                    <div class="text-center">Firma de conformidad</div>
                    <br><br>
                    <div class="text-center">__________________________</div>
                </div>
            `;
            
            document.getElementById('print-area').innerHTML = html;
            window.print();
            
            setTimeout(() => window.location.href = 'operador_devoluciones.php', 1000);
        }
    </script>
</head>
<body>
    <div class="pos-layout no-print">
        <header class="header" style="background:#444;">
            <div>Módulo de Devoluciones</div>
            <a href="pos.php" class="btn btn-primary">Volver a Caja</a>
        </header>

        <main class="pos-body" style="flex-direction:column; padding:20px; overflow-y:auto;">
            <div style="background:white; padding:20px; border-radius:8px; max-width:600px; margin:0 auto; width:100%;">
                <h3>Buscar Venta para Devolución</h3>
                <form method="GET" style="display:flex; gap:10px;">
                    <input type="number" name="folio" class="form-control" placeholder="Escanea o escribe el Folio de Venta" required autofocus>
                    <button class="btn btn-primary">Buscar</button>
                </form>

                <?php if($venta && $venta->num_rows > 0): ?>
                    <h4 style="margin-top:20px;">Items de la Venta #<?= $_GET['folio'] ?></h4>
                    <table class="admin-table">
                        <thead><tr><th>Producto</th><th>Vendidos</th><th>Precio</th><th>Acción</th></tr></thead>
                        <tbody>
                            <?php while($r = $venta->fetch_assoc()): ?>
                            <tr>
                                <td><?= $r['nombre'] ?></td>
                                <td><?= $r['cantidad'] ?></td>
                                <td>$<?= $r['precio_unitario'] ?></td>
                                <td>
                                    <button class="btn btn-danger" onclick="procesarDevolucion(<?= $r['id_venta'] ?>, <?= $r['id_item'] ?>, '<?= $r['nombre'] ?>', <?= $r['cantidad'] ?>, <?= $r['precio_unitario'] ?>)">
                                        Devolver
                                    </button>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php elseif(isset($_GET['folio'])): ?>
                    <p style="color:red; margin-top:10px;">Venta no encontrada.</p>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <div id="print-area"></div>
</body>
</html>