<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) { header("Location: index.php"); exit(); }

// CONSULTA: Traemos la imagen (limitamos a 1 para que no se duplique)
$sql = "SELECT i.id, i.codigo, i.nombre, i.precio, IFNULL(e.cantidad, 0) as cantidad,
        (SELECT imagen FROM imagenes_item WHERE id_item = i.id LIMIT 1) as imagen_blob
        FROM items i 
        LEFT JOIN existencias e ON i.id = e.id_item 
        WHERE i.activo = 1";

$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Punto de Venta</title>
    <link rel="stylesheet" href="styles.css">
    <script>
        let carrito = [];

        // CAMBIO 1: Recibimos la 'img' como parámetro
        function agregar(id, nombre, precio, stock, img) {
            let item = carrito.find(i => i.id === id);
            if (item) {
                if (item.cantidad + 1 > stock) { alert("Stock insuficiente"); return; }
                item.cantidad++;
            } else {
                if (stock < 1) { alert("Agotado"); return; }
                // Guardamos la imagen en el objeto del carrito
                carrito.push({id: id, nombre: nombre, precio: parseFloat(precio), cantidad: 1, imagen: img});
            }
            render();
        }

        function render() {
            let html = '', total = 0;
            carrito.forEach((p, i) => {
                let sub = p.cantidad * p.precio;
                total += sub;
                
                // CAMBIO 2: Agregamos la etiqueta <img> en el HTML del carrito
                html += `
                <div class="ticket-row" style="align-items: center; border-bottom: 1px dashed #ddd; padding-bottom: 5px; margin-bottom: 5px;">
                    <img src="logo.png" style="width: 40px; height: 40px; object-fit: cover; border-radius: 4px; margin-right: 10px; border: 1px solid #ccc;">
                    
                    <div style="flex: 1;">
                        <div style="font-weight:bold; font-size: 0.9rem;">${p.nombre}</div>
                        <small style="color:#666;">${p.cantidad} x $${p.precio.toFixed(2)}</small>
                    </div>
                    
                    <div class="text-right">
                        <div style="font-weight:bold;">$${sub.toFixed(2)}</div>
                        <span style="color:red; cursor:pointer; font-weight:bold; font-size: 0.8rem;" onclick="carrito.splice(${i},1);render()">[QUITAR]</span>
                    </div>
                </div>`;
            });
            document.getElementById('cart-list').innerHTML = html;
            document.getElementById('total-lbl').innerText = '$' + total.toFixed(2);
        }

        function filtrarProductos() {
            let texto = document.getElementById('buscador').value.toLowerCase();
            document.querySelectorAll('.card-product').forEach(card => {
                let nombre = card.getAttribute('data-nombre').toLowerCase();
                let codigo = card.getAttribute('data-codigo').toLowerCase();
                card.style.display = (nombre.includes(texto) || codigo.includes(texto)) ? 'block' : 'none';
            });
        }

        function cobrar() {
            if (carrito.length === 0) return;
            if (!confirm("¿Procesar Venta?")) return;

            fetch('backend_process_sale.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({items: carrito})
            })
            .then(r => r.json())
            .then(d => {
                if (d.success) {
                    imprimirTicket(d.folio, d.fecha, d.total);
                } else {
                    alert("Error: " + d.message);
                }
            });
        }

        function imprimirTicket(folio, fecha, total) {
            const t = parseFloat(total);
            let html = `
            <div class="ticket-80mm">
                <div class="text-center bold">PASTELERÍA DULCE SABOR</div>
                <div class="divider"></div>
                <div>Folio: ${folio}<br>Fecha: ${fecha}<br>Cajero: <?php echo $_SESSION['user_name']; ?></div>
                <div class="divider"></div>
                ${carrito.map(p=>`<div class="t-row"><span class="c-cant">${p.cantidad}</span><span class="c-desc">${p.nombre}</span><span class="c-imp">$${(p.cantidad*p.precio).toFixed(2)}</span></div>`).join('')}
                <div class="divider"></div>
                <div class="text-right bold">TOTAL: $${t.toFixed(2)}</div>
                <div class="text-center" style="margin-top:10px">¡Gracias por su compra!</div>
            </div>`;
            document.getElementById('print-area').innerHTML = html;
            window.print();
            setTimeout(() => window.location.reload(), 500);
        }
    </script>
</head>
<body>
    <div class="pos-layout no-print">
        <header class="header">
            <div>
                <strong>CAJA 01</strong> | <?php echo $_SESSION['user_name']; ?>
                <a href="operador_devoluciones.php" class="btn btn-warning" style="background:#FF9800; margin-left:15px; font-size:0.8rem;">↩️ Devoluciones</a>
            </div>
            <a href="logout.php" class="btn btn-danger">Salir</a>
        </header>

        <div class="pos-body">
            <div class="pos-catalog">
                <input type="text" id="buscador" class="search-bar w-100 mb-2" style="padding:15px" placeholder="Escanear código o buscar nombre..." onkeyup="filtrarProductos()" autofocus>
                
                <div class="grid-products">
                    <?php while($row = $result->fetch_assoc()): 
                        // Preparar Imagen
                        $imgSrc = "https://via.placeholder.com/150/5D4037/FFF?text=Sin+Foto";
                        if (!empty($row['imagen_blob'])) {
                            $imgSrc = 'logo.png' . base64_encode($row['imagen_blob']);
                        }
                    ?>
                        <div class="card-product" 
                             data-nombre="<?php echo $row['nombre']; ?>" 
                             data-codigo="<?php echo $row['codigo']; ?>"
                             onclick="agregar(<?= $row['id'] ?>,'<?= $row['nombre'] ?>',<?= $row['precio'] ?>,<?= $row['cantidad'] ?>, '<?= $imgSrc ?>')">
                            
                            <img src="<?= $imgSrc ?>" alt="Img">
                            <h4><?= $row['nombre'] ?></h4>
                            <div class="price">$<?= number_format($row['precio'], 2) ?></div>
                            <small>Stock: <?= $row['cantidad'] ?></small>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>

            <div class="pos-ticket">
                <div style="padding:15px; background:#f9f9f9; border-bottom:1px solid #ddd;">
                    <h3 style="margin:0">Venta Actual</h3>
                </div>
                
                <div id="cart-list" class="ticket-items">
                    </div>

                <div class="ticket-summary">
                    <div class="ticket-row">
                        <span>Total:</span> 
                        <span id="total-lbl" class="total-big">$0.00</span>
                    </div>
                    <button class="btn btn-success w-100" style="height:50px; font-size:1.2rem;" onclick="cobrar()">COBRAR (F10)</button>
                </div>
            </div>
        </div>
    </div>
    <div id="print-area"></div>
</body>
</html>