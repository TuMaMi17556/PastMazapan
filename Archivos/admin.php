<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require 'db.php';

// Guard
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

// Consulta de productos
$sql = "SELECT i.*, IFNULL(e.cantidad, 0) as stock 
        FROM items i 
        LEFT JOIN existencias e ON i.id = e.id_item 
        ORDER BY i.id DESC";
$items = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Admin - Inventario</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="admin-layout">
        <nav class="sidebar">
            <div style="padding: 20px;">
                <h3>Panel Admin</h3>
                <small>Hola, <?php echo htmlspecialchars($_SESSION['user_name']); ?></small>
            </div>
            <div class="menu">
                <a href="admin.php" class="active">📦 Inventario</a>
                <a href="admin_usuarios.php">👥 Usuarios</a>
                <a href="admin_proveedores.php">🚚 Proveedores</a>
                
                <a href="admin_compras.php" style="color:#A5D6A7;">📥 + Registrar Compra</a>
                <a href="admin_devoluciones.php" style="color:#FFCC80;">↩️ + Nueva Devolución</a>

                <div style="padding:10px 20px; color:#aaa; font-size:0.8rem; margin-top:10px;">REPORTES</div>
                <a href="admin_reporte_ventas.php">💰 Historial Ventas</a>
                <a href="admin_historial_compras.php">📋 Historial Compras</a>
                <a href="admin_historial_devoluciones.php">🔙 Historial Devoluciones</a>

                <a href="logout.php" style="border-top: 1px solid #444; color: #ff8a80; margin-top:20px;">Cerrar Sesión</a>
            </div>
        </nav>

        <main class="admin-content">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                <h2>Inventario de Productos</h2>
            </div>

            <?php if(isset($_GET['success'])): ?><div class="success-msg"><?= $_GET['success'] ?></div><?php endif; ?>
            <?php if(isset($_GET['error'])): ?><div class="error-msg"><?= $_GET['error'] ?></div><?php endif; ?>

            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Cód</th>
                        <th>Nombre</th>
                        <th>Precio</th>
                        <th>Stock</th>
                        <th>Estado</th>
                        <th>Acciones</th> </tr>
                </thead>
                <tbody>
                    <?php while($row = $items->fetch_assoc()): ?>
                    <tr>
                        <td><?= $row['codigo'] ?></td>
                        <td><?= $row['nombre'] ?></td>
                        <td>$<?= number_format($row['precio'], 2) ?></td>
                        <td style="font-weight:bold; color: <?= $row['stock'] > 0 ? 'green':'red' ?>"><?= $row['stock'] ?></td>
                        <td><?= $row['activo'] ? '<span class="badge badge-active">Activo</span>' : '<span class="badge">Inactivo</span>' ?></td>
                        <td>
                            <a href="backend_delete_product.php?id=<?= $row['id'] ?>" 
                               class="btn btn-danger" 
                               style="padding: 5px 10px; font-size: 0.8rem;"
                               onclick="return confirm('¿Estás seguro de ELIMINAR este producto?\n\nSe borrará todo su historial de ventas y compras.')">
                               🗑️ Eliminar
                            </a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </main>
    </div>

    <div id="modalProducto" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Nuevo Producto</h3>
                <span class="close-modal" onclick="document.getElementById('modalProducto').classList.remove('active')">&times;</span>
            </div>
            
            <form action="backend_save_product.php" method="POST" enctype="multipart/form-data">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Código</label>
                        <input type="text" name="codigo" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Precio</label>
                        <input type="number" step="0.50" name="precio" class="form-control" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Nombre</label>
                    <input type="text" name="nombre" class="form-control" required>
                </div>

                <div class="form-group">
                    <label>Stock Inicial</label>
                    <input type="number" name="cantidad" class="form-control" value="0">
                </div>

                <div class="form-group">
                    <label>Imagen</label>
                    <input type="file" name="imagen" class="form-control" accept="image/*" required>
                </div>
                
                <div class="text-right">
                    <button type="button" class="btn btn-danger" onclick="document.getElementById('modalProducto').classList.remove('active')">Cancelar</button>
                    <button type="submit" class="btn btn-success">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>