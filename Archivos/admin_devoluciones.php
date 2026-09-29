<?php
session_start();
require 'db.php';
if (!isset($_SESSION['user_role'])) { header("Location: index.php"); exit(); }

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
        function devolver(id_venta, id_item, cant_vendida) {
            let cant = prompt("¿Cuántos devuelve? (Máx: " + cant_vendida + ")");
            if(cant && cant > 0 && cant <= cant_vendida) {
                fetch('backend_devolucion.php', {
                    method: 'POST',
                    headers: {'Content-Type':'application/json'},
                    body: JSON.stringify({id_venta, id_item, cantidad: cant})
                }).then(r=>r.json()).then(d=>{
                    if(d.success) { alert("Devolución Exitosa"); location.reload(); }
                    else { alert(d.msg); }
                });
            } else {
                alert("Cantidad inválida");
            }
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
            <h2>Procesar Devolución</h2>
            <form method="GET" style="display:flex; gap:10px; margin-bottom:20px;">
                <input type="number" name="folio" class="form-control" placeholder="Ingrese Folio de Venta" required>
                <button class="btn btn-primary">Buscar Venta</button>
            </form>

            <?php if($venta && $venta->num_rows > 0): ?>
                <h3>Productos en Venta #<?= $_GET['folio'] ?></h3>
                <table class="admin-table">
                    <thead><tr><th>Producto</th><th>Cant. Vendida</th><th>Precio</th><th>Acción</th></tr></thead>
                    <tbody>
                        <?php while($row = $venta->fetch_assoc()): ?>
                        <tr>
                            <td><?= $row['nombre'] ?></td>
                            <td><?= $row['cantidad'] ?></td>
                            <td>$<?= $row['precio_unitario'] ?></td>
                            <td>
                                <button class="btn btn-danger" onclick="devolver(<?= $row['id_venta'] ?>, <?= $row['id_item'] ?>, <?= $row['cantidad'] ?>)">Devolver</button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php elseif(isset($_GET['folio'])): ?>
                <p>No se encontró la venta o no tiene productos.</p>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>