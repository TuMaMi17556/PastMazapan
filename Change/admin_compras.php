<?php
session_start();
require 'db.php';
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') { header("Location: index.php"); exit(); }

$provs = $conn->query("SELECT * FROM proveedores WHERE activo=1");
$items = $conn->query("SELECT * FROM items WHERE activo=1");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8"><title>Compras</title>
    <link rel="stylesheet" href="styles.css">
    <script>
        let compra = [];

        function agregar(id, nombre) {
            let costo = prompt("Costo unitario para " + nombre + ":");
            if (!costo || isNaN(costo) || parseFloat(costo) < 0) return;
            let cant = prompt("Cantidad a comprar:");
            if (!cant || isNaN(cant) || parseInt(cant) <= 0) return;

            compra.push({id_item: id, nombre: nombre, costo: parseFloat(costo), cantidad: parseInt(cant)});
            render();
        }

        function quitar(i) { compra.splice(i, 1); render(); }

        function render() {
            let html = '', total = 0;
            compra.forEach((p, i) => {
                let sub = p.cantidad * p.costo;
                total += sub;
                html += `<tr><td>${p.cantidad}</td><td>${p.nombre}</td><td>$${p.costo.toFixed(2)}</td><td>$${sub.toFixed(2)}</td><td><button class="btn btn-danger" onclick="quitar(${i})">X</button></td></tr>`;
            });
            document.getElementById('lista').innerHTML = html;
            document.getElementById('total').innerText = total.toFixed(2);
        }

        function guardar() {
            const prov = document.getElementById('prov').value;
            if (!prov) { alert('Selecciona un proveedor'); return; }
            if (compra.length === 0) { alert('Agrega al menos un producto'); return; }

            fetch('backend_compra.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({id_proveedor: parseInt(prov), items: compra})
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert('Compra registrada y stock actualizado');
                    window.location.href = 'admin_historial_compras.php';
                } else {
                    alert('Error: ' + data.msg);
                }
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
                <a href="admin.php" >📦 Inventario</a>
                <a href="admin_usuarios.php">👥 Usuarios</a>
                <a href="admin_proveedores.php">🚚 Proveedores</a>
                
                <a href="admin_compras.php" style="color:#A5D6A7;" class="active">📥 + Registrar Compra</a>
                <a href="admin_devoluciones.php" style="color:#FFCC80;">↩️ + Nueva Devolución</a>

                <div style="padding:10px 20px; color:#aaa; font-size:0.8rem; margin-top:10px;">REPORTES</div>
                <a href="admin_reporte_ventas.php">💰 Historial Ventas</a>
                <a href="admin_historial_compras.php">📋 Historial Compras</a>
                <a href="admin_historial_devoluciones.php">🔙 Historial Devoluciones</a>

                <a href="logout.php" style="border-top: 1px solid #444; color: #ff8a80; margin-top:20px;">Cerrar Sesión</a>
            </div>
        </nav>
        <main class="admin-content">
            <h2>Registrar Compra</h2>
            <div class="form-group">
                <label>Proveedor:</label>
                <select id="prov" class="form-control">
                    <option value="">-- Selecciona --</option>
                    <?php while($p=$provs->fetch_assoc()): ?><option value="<?= $p['id'] ?>"><?= $p['nombre'] ?></option><?php endwhile; ?>
                </select>
            </div>
            <div style="display:flex; gap:20px;">
                <div style="flex:1; height:400px; overflow-y:auto; border:1px solid #ccc; padding:10px;">
                    <h4>Productos</h4>
                    <?php while($i=$items->fetch_assoc()): ?>
                        <div style="padding:10px; border-bottom:1px solid #eee; cursor:pointer" onclick="agregar(<?= (int)$i['id'] ?>, <?= htmlspecialchars(json_encode($i['nombre']), ENT_QUOTES) ?>)">
                            <b><?= $i['nombre'] ?></b> (<?= $i['codigo'] ?>)
                        </div>
                    <?php endwhile; ?>
                </div>
                <div style="flex:1">
                    <h4>Detalle</h4>
                    <table class="admin-table">
                        <thead><tr><th>Cant</th><th>Prod</th><th>Costo</th><th>Sub</th><th></th></tr></thead>
                        <tbody id="lista"></tbody>
                    </table>
                    <h3>Total: $<span id="total">0.00</span></h3>
                    <button class="btn btn-success w-100" onclick="guardar()">FINALIZAR COMPRA</button>
                </div>
            </div>
        </main>
    </div>
</body>
</html>