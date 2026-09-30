<?php
session_start();
require 'db.php';
if (!isset($_SESSION['user_role'])) { header("Location: index.php"); exit; }

// Consultar Ventas con nombre del cajero
$sql = "SELECT v.*, u.nombre as cajero 
        FROM ventas v 
        JOIN usuarios u ON v.id_usuario = u.id 
        ORDER BY v.id DESC LIMIT 50";
$ventas = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8"><title>Reporte Ventas</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        #modalDetalle { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); justify-content:center; align-items:center; }
        .detalle-content { background:white; padding:20px; width:500px; max-height:80vh; overflow-y:auto; border-radius:8px; }
    </style>
    <script>
        function verDetalle(id) {
            fetch(`backend_ver_detalles.php?tipo=venta&id=${id}`)
            .then(r => r.json())
            .then(data => {
                let html = '<table class="admin-table"><thead><tr><th>Cant</th><th>Prod</th><th>$$</th></tr></thead><tbody>';
                data.forEach(d => {
                    html += `<tr><td>${d.cantidad}</td><td>${d.nombre}</td><td>$${d.precio_unitario}</td></tr>`;
                });
                html += '</tbody></table>';
                document.getElementById('cuerpo-detalle').innerHTML = html;
                document.getElementById('modalDetalle').style.display = 'flex';
            });
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
                <a href="admin_reporte_ventas.php" class="active">💰 Historial Ventas</a>
                <a href="admin_historial_compras.php">📋 Historial Compras</a>
                <a href="admin_historial_devoluciones.php">🔙 Historial Devoluciones</a>

                <a href="logout.php" style="border-top: 1px solid #444; color: #ff8a80; margin-top:20px;">Cerrar Sesión</a>
            </div>
        </nav>
        <main class="admin-content">
            <h2>Historial de Ventas (Últimas 50)</h2>
            <table class="admin-table">
                <thead><tr><th>Folio</th><th>Fecha</th><th>Cajero</th><th>Total</th><th>Detalles</th></tr></thead>
                <tbody>
                    <?php while($r=$ventas->fetch_assoc()): ?>
                    <tr>
                        <td>#<?= str_pad($r['id'], 6, "0", STR_PAD_LEFT) ?></td>
                        <td><?= $r['fecha'] ?></td>
                        <td><?= $r['cajero'] ?></td>
                        <td>$<?= number_format($r['total'], 2) ?></td>
                        <td><button class="btn btn-primary" onclick="verDetalle(<?= $r['id'] ?>)">Ver Productos</button></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </main>
    </div>

    <div id="modalDetalle">
        <div class="detalle-content">
            <div style="display:flex; justify-content:space-between;">
                <h3>Productos Vendidos</h3>
                <button class="btn btn-danger" onclick="document.getElementById('modalDetalle').style.display='none'">X</button>
            </div>
            <div id="cuerpo-detalle"></div>
        </div>
    </div>
</body>
</html>