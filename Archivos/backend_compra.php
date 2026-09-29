<?php
header('Content-Type: application/json');
ini_set('display_errors', 0); // Evita que advertencias rompan el formato JSON
error_reporting(E_ALL);

session_start();
require 'db.php';

// Función para devolver respuestas JSON
function responder($success, $msg = '') {
    echo json_encode(['success' => $success, 'msg' => $msg]);
    exit();
}

// 1. Validar sesión de administrador
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    responder(false, 'Acceso denegado');
}

// 2. Leer la información JSON enviada desde fetch()
$raw_input = file_get_contents('php://input');
$data      = json_decode($raw_input, true);

if (!$data || empty($data['items'])) {
    responder(false, 'No se recibieron productos en la compra.');
}

$items = $data['items'];
$proveedor = $data['proveedor'];
$total = $data['total'];
$conn->begin_transaction();

try {
    // Sentencias preparadas para la tabla compras (solo id_item y cantidad) y existencias
    $pdo->beginTransaction();
    $stmtCompra = $pdo->prepare("INSERT INTO compras (proveedor, total, fecha) VALUES (?, ?, NOW())");
    $stmtCompra->execute([$proveedor, $total]);
    $compra_id = $pdo->lastInsertId();
    
    $stmtDetalle = $pdo->prepare("INSERT INTO detalle_compras (compra_id, codigo, producto, cantidad, costo) VALUES (?, ?, ?, ?, ?)");
    
    $stmtInventario = $pdo->prepare("INSERT INTO productos (codigo, nombre, precio, stock, estado) 
                                     VALUES (?, ?, ?, ?, 'Activo') 
                                     ON DUPLICATE KEY UPDATE stock = stock + ?");
    foreach ($items as $item) {
        // Guardar detalle
        $stmtDetalle->execute([
            $compra_id, 
            $item['codigo'], 
            $item['nombre'], 
            $item['cantidad'], 
            $item['costo']
        ]);

        // Actualizar/Crear inventario (Asumiendo que 'precio_venta' es a lo que tú lo vendes)
        $stmtInventario->execute([
            $item['codigo'], 
            $item['nombre'], 
            $item['precio_venta'], 
            $item['cantidad'], // Stock si se crea nuevo
            $item['cantidad']  // Stock a sumar si ya existe
        ]);
    }

    // Confirmar la transacción
  $pdo->commit();
    echo json_encode(['status' => 'success', 'message' => 'Compra y stock actualizados.']);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['status' => 'error', 'message' => 'Error: ' . $e->getMessage()]);
}
?>