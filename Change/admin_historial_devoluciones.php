<?php
ini_set('display_errors', 1); error_reporting(E_ALL);
session_start();
require 'db.php';
// --- Crear tablas nuevas si no existen (autocontenido, no depende de otros archivos) ---
try {
    foreach ([
        "CREATE TABLE IF NOT EXISTS compras_det (id INT AUTO_INCREMENT PRIMARY KEY, id_compra INT NOT NULL, id_item INT NOT NULL, cantidad INT NOT NULL, costo_unitario DECIMAL(10,2) NOT NULL) ENGINE=InnoDB",
        "CREATE TABLE IF NOT EXISTS devoluciones_proveedor (id INT AUTO_INCREMENT PRIMARY KEY, id_compra INT NOT NULL, id_usuario INT NOT NULL, fecha DATETIME NOT NULL, total DECIMAL(10,2) NOT NULL DEFAULT 0, motivo VARCHAR(255) NULL) ENGINE=InnoDB",
        "CREATE TABLE IF NOT EXISTS devoluciones_proveedor_det (id INT AUTO_INCREMENT PRIMARY KEY, id_devolucion INT NOT NULL, id_item INT NOT NULL, cantidad INT NOT NULL, costo_unitario DECIMAL(10,2) NOT NULL) ENGINE=InnoDB",
    ] as $__q) { $conn->query($__q); }
} catch (Throwable $__e) { /* el error real se mostrará abajo */ }
$error = ''; $devs = null;
if (!isset($_SESSION['user_role'])) { header("Location: index.php"); exit; }

$tipo = ($_GET['tipo'] ?? 'proveedor') === 'cliente' ? 'cliente' : 'proveedor';

try {
if ($tipo === 'proveedor') {
    $devs = $conn->query("SELECT d.*, u.nombre AS usuario, p.nombre AS proveedor
                          FROM devoluciones_proveedor d
                          JOIN usuarios u ON d.id_usuario = u.id
                          JOIN compras c ON d.id_compra = c.id
                          JOIN proveedores p ON c.id_proveedor = p.id
                          ORDER BY d.id DESC");
} else {
    $devs = $conn->query("SELECT d.*, u.nombre AS usuario
                          FROM devoluciones d
                          JOIN usuarios u ON d.id_usuario = u.id
                          ORDER BY d.id DESC");
}
} catch (Throwable $e) { $error = $e->getMessage(); }
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8"><title>Historial Devoluciones</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .tabs { display:flex; gap:10px; margin-bottom:15px; }
        .tabs a { padding:8px 16px; border-radius:6px; background:#eee; color:#333; text-decoration:none; font-weight:bold; }
        .tabs a.on { background:#FF9800; color:#fff; }
        .dev-layout { display:flex; gap:20px; align-items:flex-start; }
        .dev-lista { flex:1.4; }
        .dev-detalle { flex:1; background:#fff; border:1px solid #ddd; border-radius:8px; padding:15px; position:sticky; top:10px; min-height:200px; }
        tr.sel { background:#fff3e0; }
        .msg-error { color:#c62828; font-weight:bold; }
    </style>
    <script>
        const TIPO = '<?= $tipo ?>';
        function verDetalle(id, fila) {
            document.querySelectorAll('tr.sel').forEach(t => t.classList.remove('sel'));
            if (fila) fila.classList.add('sel');
            const cont = document.getElementById('cuerpo-detalle');
            document.getElementById('titulo-detalle').innerText = 'Devolución #' + id;
            cont.innerHTML = 'Cargando...';

            const tipoApi = TIPO === 'proveedor' ? 'devolucion_prov' : 'devolucion';
            fetch('backend_ver_detalles.php?tipo=' + tipoApi + '&id=' + id)
            .then(r => r.text())
            .then(txt => {
                let data;
                try { data = JSON.parse(txt); }
                catch (e) { cont.innerHTML = '<p class="msg-error">Respuesta inesperada del servidor:</p><pre style="white-space:pre-wrap;font-size:12px">' + txt.replace(/</g,'&lt;').substring(0,500) + '</pre>'; return; }
                if (data.error) { cont.innerHTML = '<p class="msg-error">Error: ' + data.error + '</p>'; return; }
                if (data.length === 0) { cont.innerHTML = '<p>Sin detalle registrado.</p>'; return; }

                let html, total = 0;
                if (TIPO === 'proveedor') {
                    html = '<table class="admin-table"><thead><tr><th>Cant</th><th>Producto</th><th>Costo</th><th>Subtotal</th></tr></thead><tbody>';
                    data.forEach(d => {
                        const sub = d.cantidad * d.precio_unitario; total += sub;
                        html += `<tr><td>${d.cantidad}</td><td>${d.nombre}</td><td>$${parseFloat(d.precio_unitario).toFixed(2)}</td><td>$${sub.toFixed(2)}</td></tr>`;
                    });
                    html += '</tbody></table><h4 style="text-align:right">Total devuelto: $' + total.toFixed(2) + '</h4>';
                } else {
                    html = '<table class="admin-table"><thead><tr><th>Cant</th><th>Producto devuelto</th></tr></thead><tbody>';
                    data.forEach(d => { html += `<tr><td>${d.cantidad}</td><td>${d.nombre}</td></tr>`; });
                    html += '</tbody></table>';
                }
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
                <a href="admin_historial_compras.php">📋 Historial Compras</a>
                <a href="admin_historial_devoluciones.php" class="active">🔙 Historial Devoluciones</a>

                <a href="logout.php" style="border-top: 1px solid #444; color: #ff8a80; margin-top:20px;">Cerrar Sesión</a>
            </div>
        </nav>

        <main class="admin-content">
            <h2>Historial de Devoluciones</h2>
            <?php if ($error): ?>
                <div style="background:#ffebee;color:#c62828;padding:12px;border-radius:6px;margin-bottom:15px;"><b>Error de base de datos:</b> <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <div class="tabs">
                <a href="?tipo=proveedor" class="<?= $tipo === 'proveedor' ? 'on' : '' ?>">🚚 A proveedores</a>
                <a href="?tipo=cliente" class="<?= $tipo === 'cliente' ? 'on' : '' ?>">🧾 De clientes (caja)</a>
            </div>

            <div class="dev-layout">
                <div class="dev-lista">
                <table class="admin-table">
                    <?php if ($tipo === 'proveedor'): ?>
                    <thead><tr><th>ID</th><th>Fecha</th><th>Compra #</th><th>Proveedor</th><th>Total</th><th>Motivo</th><th>Por</th><th></th></tr></thead>
                    <tbody>
                        <?php while ($devs && ($r = $devs->fetch_assoc())): ?>
                        <tr onclick="verDetalle(<?= (int)$r['id'] ?>, this)" style="cursor:pointer">
                            <td><?= $r['id'] ?></td>
                            <td><?= $r['fecha'] ?></td>
                            <td>#<?= $r['id_compra'] ?></td>
                            <td><?= htmlspecialchars($r['proveedor']) ?></td>
                            <td>$<?= number_format($r['total'], 2) ?></td>
                            <td><?= htmlspecialchars($r['motivo'] ?? '') ?></td>
                            <td><?= htmlspecialchars($r['usuario']) ?></td>
                            <td><button class="btn btn-primary" onclick="event.stopPropagation(); verDetalle(<?= (int)$r['id'] ?>, this.closest('tr'))">Ver Items</button></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                    <?php else: ?>
                    <thead><tr><th>ID</th><th>Fecha</th><th>De la Venta #</th><th>Procesada por</th><th></th></tr></thead>
                    <tbody>
                        <?php while ($devs && ($r = $devs->fetch_assoc())): ?>
                        <tr onclick="verDetalle(<?= (int)$r['id'] ?>, this)" style="cursor:pointer">
                            <td><?= $r['id'] ?></td>
                            <td><?= $r['fecha'] ?></td>
                            <td>#<?= $r['id_venta'] ?></td>
                            <td><?= htmlspecialchars($r['usuario']) ?></td>
                            <td><button class="btn btn-primary" onclick="event.stopPropagation(); verDetalle(<?= (int)$r['id'] ?>, this.closest('tr'))">Ver Items</button></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                    <?php endif; ?>
                </table>
                </div>

                <div class="dev-detalle">
                    <h3 id="titulo-detalle">Detalle de la devolución</h3>
                    <div id="cuerpo-detalle"><p style="color:#888">Haz clic en una devolución para ver sus productos.</p></div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>