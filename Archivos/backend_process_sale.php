<?php
// backend_process_sale.php
session_start();
require 'db.php';
header('Content-Type: application/json');

// Validar sesión
if (!isset($_SESSION['user_id'])) { 
    echo json_encode(['success'=>false, 'message'=>'Sesión expirada']); 
    exit; 
}

// Leer JSON
$input = json_decode(file_get_contents('php://input'), true);
if (!$input || empty($input['items'])) { 
    echo json_encode(['success'=>false, 'message'=>'Carrito vacío']); 
    exit; 
}

$conn->begin_transaction();
try {
    $id_usuario = $_SESSION['user_id'];
    $total = 0;
    
    // Calcular totales
    foreach($input['items'] as $i) { 
        $total += ($i['precio'] * $i['cantidad']); 
    }
    
    // Calcular desglose para tu tabla (subtotal, iva, total)
    // Asumiendo IVA 16% incluido
    $subtotal = $total / 1.16;
    $iva = $total - $subtotal;

    // 1. Insertar Encabezado de Venta (Usando id_usuario)
    $stmt = $conn->prepare("INSERT INTO ventas (id_usuario, subtotal, iva, total, fecha) VALUES (?, ?, ?, ?, NOW())");
    $stmt->bind_param("iddd", $id_usuario, $subtotal, $iva, $total);
    
    if (!$stmt->execute()) throw new Exception("Error al guardar venta: " . $stmt->error);
    $id_venta = $conn->insert_id;

    // 2. Insertar Detalles
    // TU TRIGGER SE EJECUTARÁ AQUÍ AUTOMÁTICAMENTE PARA RESTAR STOCK
    $stmt_det = $conn->prepare("INSERT INTO ventas_det (id_venta, id_item, cantidad, precio_unitario, total) VALUES (?, ?, ?, ?, ?)");
    
    foreach($input['items'] as $i) {
        $line_total = $i['precio'] * $i['cantidad'];
        // Bind: id_venta, id_item, cantidad, precio, total
        $stmt_det->bind_param("iiidd", $id_venta, $i['id'], $i['cantidad'], $i['precio'], $line_total);
        
        if (!$stmt_det->execute()) {
            // Si el trigger falla (ej. stock insuficiente), lanzará error aquí
            throw new Exception("Error al guardar detalle. Verifica stock.");
        }
    }

    $conn->commit();
    echo json_encode([
        'success' => true, 
        'folio' => str_pad($id_venta, 6, "0", STR_PAD_LEFT), // Folio formateado 000001
        'fecha' => date('Y-m-d H:i'),
        'total' => $total
    ]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success'=>false, 'message'=>$e->getMessage()]);
}
?>