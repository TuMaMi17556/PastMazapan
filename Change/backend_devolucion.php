<?php
// Devolución de un CLIENTE (caja): regresa el producto al inventario
session_start();
require 'db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) { echo json_encode(['success'=>false, 'msg'=>'Login requerido']); exit; }

$data = json_decode(file_get_contents('php://input'), true);
$id_venta = (int)($data['id_venta'] ?? 0);
$id_item  = (int)($data['id_item'] ?? 0);
$cantidad = (int)($data['cantidad'] ?? 0);

function stock_actual($conn, $id_item) {
    $st = $conn->prepare("SELECT cantidad FROM existencias WHERE id_item = ?");
    $st->bind_param("i", $id_item); $st->execute();
    $row = $st->get_result()->fetch_assoc();
    return $row ? (int)$row['cantidad'] : null;
}

$conn->begin_transaction();
try {
    if ($id_venta <= 0 || $id_item <= 0 || $cantidad <= 0) throw new Exception("Datos inválidos.");

    // Validar: no devolver más de lo vendido menos lo ya devuelto
    $st = $conn->prepare("SELECT IFNULL(SUM(cantidad),0) AS c FROM ventas_det WHERE id_venta=? AND id_item=?");
    $st->bind_param("ii", $id_venta, $id_item); $st->execute();
    $vendido = (int)$st->get_result()->fetch_assoc()['c'];

    $st = $conn->prepare("SELECT IFNULL(SUM(cantidad),0) AS c FROM devoluciones_det WHERE id_venta=? AND id_item=?");
    $st->bind_param("ii", $id_venta, $id_item); $st->execute();
    $ya = (int)$st->get_result()->fetch_assoc()['c'];

    if ($vendido <= 0) throw new Exception("Ese producto no pertenece a la venta.");
    if ($cantidad > $vendido - $ya) throw new Exception("Solo se pueden devolver " . ($vendido - $ya) . " unidad(es) (vendidas $vendido, ya devueltas $ya).");

    // 1. Cabecera
    $uid = (int)$_SESSION['user_id'];
    $stmt = $conn->prepare("INSERT INTO devoluciones (id_venta, id_usuario, fecha) VALUES (?, ?, NOW())");
    $stmt->bind_param("ii", $id_venta, $uid);
    $stmt->execute();
    $id_dev = $conn->insert_id;

    // 2. Detalle + regreso al inventario
    $antes = stock_actual($conn, $id_item);
    $stmt_det = $conn->prepare("INSERT INTO devoluciones_det (id_devolucion, id_venta, id_item, cantidad, monto_devuelto) VALUES (?, ?, ?, ?, 0)");
    $stmt_det->bind_param("iiii", $id_dev, $id_venta, $id_item, $cantidad);
    if (!$stmt_det->execute()) throw new Exception("Error: " . $stmt_det->error);

    // Si un trigger ya sumó el stock, no lo sumamos otra vez
    if (stock_actual($conn, $id_item) === $antes) {
        if ($antes === null) {
            $s = $conn->prepare("INSERT INTO existencias (id_item, cantidad) VALUES (?, ?)");
            $s->bind_param("ii", $id_item, $cantidad); $s->execute();
        } else {
            $s = $conn->prepare("UPDATE existencias SET cantidad = cantidad + ? WHERE id_item = ?");
            $s->bind_param("ii", $cantidad, $id_item); $s->execute();
        }
    }

    $conn->commit();
    echo json_encode(['success'=>true]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success'=>false, 'msg'=>$e->getMessage()]);
}
?>