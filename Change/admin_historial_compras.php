<?php
session_start();
require 'db.php';
if (!isset($_SESSION['user_role'])) { header("Location: index.php"); exit; }

$sql = "SELECT c.*, p.nombre as proveedor 
        FROM compras c 
        JOIN proveedores p ON c.id_proveedor = p.id 
        ORDER BY c.id DESC";
$compras = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8"><title>Historial Compras</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .compras-layout { display:flex; gap:20px; align-items:flex-start; }
        .compras-lista { flex:1.3; }
        .compras-detalle { flex:1; background:#fff; border:1px solid #ddd; border-radius:8px; padding:15px; position:sticky; top:10px; min-height:200px; }
        tr.sel { background:#fff3e0; }
        .msg-error { color:#c62828; font-weight:bold; }
    </style>
    <script>
        function verDetalle(id, fila) {
            document.querySelectorAll('tr.sel').forEach(t => t.classList.remove('sel'));
            if (fila) fila.classList.add('sel');
            const cont = document.getElementById('cuerpo-detalle');
            document.getElementById('titulo-detalle').innerText = 'Compra #' + id;
            cont.innerHTML = 'Cargando...';

            fetch('backend_ver_detalles.php?tipo=compra&id=' + id)
            .then(r => r.text())
            .then(txt => {
                let data;
                try { data = JSON.parse(txt); }
                catch (e) { cont.innerHTML = '<p class="msg-error">El servidor respondió algo inesperado:</p><pre style="white-space:pre-wrap;font-size:12px">' + txt.replace(/</g,'&lt;').substring(0,500) + '</pre>'; return; }

                if (data.error) { cont.innerHTML = '<p class="msg-error">Error: ' + data.error + '</p>'; return; }
                if (data.length === 0) { cont.innerHTML = '<p>Esta compra no tiene detalle guardado (posiblemente se registró antes de la corrección).</p>'; return; }

                let total = 0;
                let html = '<table class="admin-table"><thead><tr><th>Cant</th><th>Producto</th><th>Costo</th><th>Subtotal</th></tr></thead><tbody>';
                data.forEach(d => {
                    const sub = d.cantidad * d.precio_unitario; total += sub;
                    html += `<tr><td>${d.cantidad}</td><td>${d.nombre}</td><td>$${parseFloat(d.precio_unitario).toFixed(2)}</td><td>$${sub.toFixed(2)}</td></tr>`;
                });
                html += '</tbody></table><h4 style="text-align:right">Total: $' + total.toFixed(2) + '</h4>';
                cont.innerHTML = html;
            })
            .catch(() => { cont.innerHTML = '<p class="msg-error">No se pudo conectar con el servidor.</p>'; });
        }
    </script>
</head>
<body>
    <div class="admin-layout">
        <nav class="sidebar">
            <div style="padding: 20px;">
                <h3>Panel Admin</h3>
                <small>Hola, <?php echo htmlspecialchars($_SESSION['user_name']); ?></small>
            </div>
            <div class="menu">
                <a href="admin.php">📦 Inventario</a>
                <a href="admin_usuarios.php">👥 Usuarios</a>
                <a href="admin_proveedores.php">🚚 Proveedores</a>
                
                <a href="admin_compras.php" style="color:#A5D6A7;">📥 + Registrar Compra</a>
                <a href="admin_devoluciones.php" style="color:#FFCC80;">↩️ + Nueva Devolución</a>

                <div style="padding:10px 20px; color:#aaa; font-size:0.8rem; margin-top:10px;">REPORTES</div>
                <a href="admin_reporte_ventas.php">💰 Historial Ventas</a>
                <a href="admin_historial_compras.php" class="active">📋 Historial Compras</a>
                <a href="admin_historial_devoluciones.php">🔙 Historial Devoluciones</a>

                <a href="logout.php" style="border-top: 1px solid #444; color: #ff8a80; margin-top:20px;">Cerrar Sesión</a>
            </div>
        </nav>
        <main class="admin-content">
            <h2>Historial de Compras a Proveedores</h2>
            <div class="compras-layout">
            <div class="compras-lista">
            <table class="admin-table">
                <thead><tr><th>ID</th><th>Fecha</th><th>Proveedor</th><th>Total</th><th>Detalles</th></tr></thead>
                <tbody>
                    <?php while($r=$compras->fetch_assoc()): ?>
                    <tr onclick="verDetalle(<?= (int)$r['id'] ?>, this)" style="cursor:pointer">
                        <td><?= $r['id'] ?></td>
                        <td><?= $r['fecha'] ?></td>
                        <td><?= $r['proveedor'] ?></td>
                        <td>$<?= number_format($r['total'], 2) ?></td>
                        <td><button class="btn btn-primary" onclick="event.stopPropagation(); verDetalle(<?= (int)$r['id'] ?>, this.closest('tr'))">Ver Items</button></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            </div>
            <div class="compras-detalle">
                <h3 id="titulo-detalle">Detalle de la compra</h3>
                <div id="cuerpo-detalle"><p style="color:#888">Haz clic en "Ver Items" para ver la mercancía recibida.</p></div>
            </div>
            </div>
        </main>
    </div>
</body>
</html>