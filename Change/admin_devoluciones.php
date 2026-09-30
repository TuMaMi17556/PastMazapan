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
$error = '';
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') { header("Location: index.php"); exit(); }

$compra = null; $filas = []; $recientes = null;
try {
if (isset($_GET['folio']) && $_GET['folio'] !== '') {
    $folio = (int)$_GET['folio'];
    $st = $conn->prepare("SELECT c.*, p.nombre AS proveedor FROM compras c JOIN proveedores p ON p.id = c.id_proveedor WHERE c.id = ?");
    $st->bind_param("i", $folio); $st->execute();
    $compra = $st->get_result()->fetch_assoc();

    if ($compra) {
        $sql = "SELECT d.id_item, i.nombre, SUM(d.cantidad) AS comprado, MAX(d.precio_unitario) AS costo,
                       IFNULL(e.cantidad,0) AS stock
                FROM compras_det d
                JOIN items i ON i.id = d.id_item
                LEFT JOIN existencias e ON e.id_item = d.id_item
                WHERE d.id_compra = ?
                GROUP BY d.id_item, i.nombre, e.cantidad";
        $st = $conn->prepare($sql); $st->bind_param("i", $folio); $st->execute();
        $filas = $st->get_result()->fetch_all(MYSQLI_ASSOC);

        $st = $conn->prepare("SELECT dd.id_item, SUM(dd.cantidad) AS dev FROM devoluciones_proveedor_det dd
                              JOIN devoluciones_proveedor dp ON dp.id = dd.id_devolucion
                              WHERE dp.id_compra = ? GROUP BY dd.id_item");
        $st->bind_param("i", $folio); $st->execute();
        $devueltos = [];
        foreach ($st->get_result()->fetch_all(MYSQLI_ASSOC) as $r) $devueltos[$r['id_item']] = (int)$r['dev'];
        foreach ($filas as &$f) { $f['devuelto'] = $devueltos[$f['id_item']] ?? 0; } unset($f);
    }
}

// Últimas compras para elegir rápido
$recientes = $conn->query("SELECT c.id, c.fecha, c.total, p.nombre AS proveedor
                           FROM compras c JOIN proveedores p ON p.id = c.id_proveedor
                           ORDER BY c.id DESC LIMIT 15");
} catch (Throwable $e) { $error = $e->getMessage(); }
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8"><title>Devolución a Proveedor</title>
    <link rel="stylesheet" href="styles.css">
    <script>
        function registrar(idCompra) {
            const items = [];
            document.querySelectorAll('input.cant-dev').forEach(inp => {
                const v = parseInt(inp.value) || 0;
                if (v > 0) items.push({id_item: parseInt(inp.dataset.item), cantidad: v});
            });
            if (items.length === 0) { alert('Indica la cantidad a devolver de al menos un producto'); return; }
            if (!confirm('¿Registrar la devolución al proveedor? El stock se descontará del inventario.')) return;

            fetch('backend_devolucion_proveedor.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({id_compra: idCompra, motivo: document.getElementById('motivo').value, items: items})
            })
            .then(r => r.json())
            .then(d => {
                if (d.success) { alert('Devolución al proveedor registrada'); window.location.href = 'admin_historial_devoluciones.php'; }
                else { alert('Error: ' + d.msg); }
            })
            .catch(() => alert('Error de conexión con el servidor'));
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
                <a href="admin_devoluciones.php" style="color:#FFCC80;" class="active">↩️ + Nueva Devolución</a>

                <div style="padding:10px 20px; color:#aaa; font-size:0.8rem; margin-top:10px;">REPORTES</div>
                <a href="admin_reporte_ventas.php">💰 Historial Ventas</a>
                <a href="admin_historial_compras.php">📋 Historial Compras</a>
                <a href="admin_historial_devoluciones.php">🔙 Historial Devoluciones</a>

                <a href="logout.php" style="border-top: 1px solid #444; color: #ff8a80; margin-top:20px;">Cerrar Sesión</a>
            </div>
        </nav>
        <main class="admin-content">
            <h2>Devolución a Proveedor</h2>
            <?php if ($error): ?>
                <div style="background:#ffebee;color:#c62828;padding:12px;border-radius:6px;margin-bottom:15px;"><b>Error de base de datos:</b> <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <p style="color:#666">Devuelve mercancía que le compraste a un proveedor. Se descuenta del inventario.</p>

            <form method="GET" style="display:flex; gap:10px; margin-bottom:20px;">
                <input type="number" name="folio" class="form-control" placeholder="Folio de la compra (ID)" value="<?= isset($_GET['folio']) ? (int)$_GET['folio'] : '' ?>" required>
                <button class="btn btn-primary">Buscar Compra</button>
            </form>

            <?php if ($compra): ?>
                <h3>Compra #<?= (int)$compra['id'] ?> — <?= htmlspecialchars($compra['proveedor']) ?> <small style="color:#888">(<?= $compra['fecha'] ?>)</small></h3>
                <?php if (empty($filas)): ?>
                    <p>Esta compra no tiene detalle guardado, no se puede devolver.</p>
                <?php else: ?>
                <table class="admin-table">
                    <thead><tr><th>Producto</th><th>Comprado</th><th>Ya devuelto</th><th>Stock actual</th><th>Costo</th><th>Cant. a devolver</th></tr></thead>
                    <tbody>
                    <?php foreach ($filas as $f):
                        $disp = min((int)$f['comprado'] - $f['devuelto'], (int)$f['stock']); ?>
                        <tr>
                            <td><?= htmlspecialchars($f['nombre']) ?></td>
                            <td><?= (int)$f['comprado'] ?></td>
                            <td><?= $f['devuelto'] ?></td>
                            <td><?= (int)$f['stock'] ?></td>
                            <td>$<?= number_format($f['costo'], 2) ?></td>
                            <td>
                                <?php if ($disp > 0): ?>
                                    <input type="number" class="form-control cant-dev" data-item="<?= (int)$f['id_item'] ?>" min="0" max="<?= $disp ?>" value="0" style="width:90px">
                                    <small style="color:#888">máx <?= $disp ?></small>
                                <?php else: ?><small style="color:#888">Nada que devolver</small><?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <div class="form-group" style="margin-top:15px;">
                    <label>Motivo (opcional):</label>
                    <input type="text" id="motivo" class="form-control" maxlength="255" placeholder="Ej. producto dañado, caducado...">
                </div>
                <button class="btn btn-danger" onclick="registrar(<?= (int)$compra['id'] ?>)">REGISTRAR DEVOLUCIÓN AL PROVEEDOR</button>
                <?php endif; ?>
            <?php elseif (isset($_GET['folio']) && $_GET['folio'] !== ''): ?>
                <p style="color:red">No se encontró la compra #<?= (int)$_GET['folio'] ?>.</p>
            <?php endif; ?>

            <h3 style="margin-top:30px;">Compras recientes</h3>
            <table class="admin-table">
                <thead><tr><th>Folio</th><th>Fecha</th><th>Proveedor</th><th>Total</th><th></th></tr></thead>
                <tbody>
                <?php while ($recientes && ($r = $recientes->fetch_assoc())): ?>
                    <tr>
                        <td>#<?= $r['id'] ?></td><td><?= $r['fecha'] ?></td>
                        <td><?= htmlspecialchars($r['proveedor']) ?></td><td>$<?= number_format($r['total'], 2) ?></td>
                        <td><a class="btn btn-primary" href="admin_devoluciones.php?folio=<?= (int)$r['id'] ?>">Seleccionar</a></td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </main>
    </div>
</body>
</html>