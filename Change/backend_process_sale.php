<?php
// backend_process_sale.php  — venta del cajero: guarda la venta y RESTA del inventario
session_start();
require 'db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success'=>false, 'message'=>'Sesión expirada']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || empty($input['items'])) {
    echo json_encode(['success'=>false, 'message'=>'Carrito vacío']);
    exit;
}

// Stock actual de un producto (null si no tiene fila en existencias)
function stock_actual($conn, $id_item, $bloquear = false) {
    $sql = "SELECT cantidad FROM existencias WHERE id_item = ?" . ($bloquear ? " FOR UPDATE" : "");
    $st = $conn->prepare($sql);
    $st->bind_param("i", $id_item);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    return $row ? (int)$row['cantidad'] : null;
}

$conn->begin_transaction();
try {
    $id_usuario = (int)$_SESSION['user_id'];
    $items = [];
    $total = 0;

    // Validar carrito y agrupar por producto (por si el mismo item viene repetido)
    foreach ($input['items'] as $i) {
        $id = (int)$i['id']; $cant = (int)$i['cantidad']; $precio = (float)$i['precio'];
        if ($id <= 0 || $cant <= 0 || $precio < 0) throw new Exception("Producto o cantidad inválidos.");
        $items[] = ['id'=>$id, 'nombre'=>$i['nombre'] ?? ("#$id"), 'cant'=>$cant, 'precio'=>$precio];
        $total += $precio * $cant;
    }

    // Verificar stock ANTES de vender
    $pedido = [];
    foreach ($items as $it) { $pedido[$it['id']] = ($pedido[$it['id']] ?? 0) + $it['cant']; }
    foreach ($pedido as $id => $cant) {
        $stock = stock_actual($conn, $id, true);
        if ($stock === null || $stock < $cant) {
            $nom = ''; foreach ($items as $it) if ($it['id'] === $id) { $nom = $it['nombre']; break; }
            throw new Exception("Stock insuficiente de \"$nom\" (disponible: " . ($stock ?? 0) . ", pedido: $cant).");
        }
    }

    $subtotal = $total / 1.16;
    $iva = $total - $subtotal;

    // 1. Encabezado
    $stmt = $conn->prepare("INSERT INTO ventas (id_usuario, subtotal, iva, total, fecha) VALUES (?, ?, ?, ?, NOW())");
    $stmt->bind_param("iddd", $id_usuario, $subtotal, $iva, $total);
    if (!$stmt->execute()) throw new Exception("Error al guardar venta: " . $stmt->error);
    $id_venta = $conn->insert_id;

    // 2. Detalle + descuento de inventario
    $stmt_det = $conn->prepare("INSERT INTO ventas_det (id_venta, id_item, cantidad, precio_unitario, total) VALUES (?, ?, ?, ?, ?)");
    $stmt_upd = $conn->prepare("UPDATE existencias SET cantidad = cantidad - ? WHERE id_item = ?");

    foreach ($items as $it) {
        $line_total = $it['precio'] * $it['cant'];
        $antes = stock_actual($conn, $it['id']);

        $stmt_det->bind_param("iiidd", $id_venta, $it['id'], $it['cant'], $it['precio'], $line_total);
        if (!$stmt_det->execute()) throw new Exception("Error al guardar detalle: " . $stmt_det->error);

        // Si un trigger de la BD ya descontó el stock, no lo descontamos otra vez.
        $despues = stock_actual($conn, $it['id']);
        if ($despues === $antes) {
            $stmt_upd->bind_param("ii", $it['cant'], $it['id']);
            if (!$stmt_upd->execute()) throw new Exception("Error al actualizar inventario: " . $stmt_upd->error);
        }
    }

    $conn->commit();
    echo json_encode([
        'success' => true,
        'folio' => str_pad($id_venta, 6, "0", STR_PAD_LEFT),
        'fecha' => date('Y-m-d H:i'),
        'total' => $total
    ]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success'=>false, 'message'=>$e->getMessage()]);
}
?>