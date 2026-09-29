<?php
session_start();
require 'db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) { echo json_encode(['success'=>false, 'msg'=>'Login requerido']); exit; }

$data = json_decode(file_get_contents('php://input'), true);
$conn->begin_transaction();

try {
    // 1. Crear cabecera de devolución
    $stmt = $conn->prepare("INSERT INTO devoluciones (id_venta, id_usuario, fecha) VALUES (?, ?, NOW())");
    $stmt->bind_param("ii", $data['id_venta'], $_SESSION['user_id']);
    $stmt->execute();
    $id_dev = $conn->insert_id;

    // 2. Crear Detalle
    // EL TRIGGER `trg_devolucion_detalle_insert` SUMARÁ EL STOCK Y VALIDARÁ CANTIDAD
    $stmt_det = $conn->prepare("INSERT INTO devoluciones_det (id_devolucion, id_venta, id_item, cantidad, monto_devuelto) VALUES (?, ?, ?, ?, 0)");
    
    // El monto devuelto lo ponemos en 0 por ahora para simplificar, o podrías consultarlo.
    // Lo importante es que el trigger mueva el inventario.
    $stmt_det->bind_param("iiii", $id_dev, $data['id_venta'], $data['id_item'], $data['cantidad']);
    
    if (!$stmt_det->execute()) {
        throw new Exception("Error: " . $conn->error . " (Posiblemente estás devolviendo más de lo vendido)");
    }

    $conn->commit();
    echo json_encode(['success'=>true]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success'=>false, 'msg'=>$e->getMessage()]);
}
?>