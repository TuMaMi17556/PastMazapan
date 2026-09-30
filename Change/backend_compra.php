<?php
header('Content-Type: application/json');
ini_set('display_errors', 0);
error_reporting(E_ALL);

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

function responder($ok, $msg = '') {
    echo json_encode(['success' => $ok, 'msg' => $msg]);
    exit();
}

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    responder(false, 'Acceso denegado');
}

$data = json_decode(file_get_contents('php://input'), true);
if (!$data || empty($data['items']) || empty($data['id_proveedor'])) {
    responder(false, 'Selecciona un proveedor y agrega al menos un producto.');
}

$id_proveedor = (int)$data['id_proveedor'];
$items = $data['items'];

// El total se calcula en el servidor (no se confía en el navegador)
$total = 0;
foreach ($items as $it) {
    $total += ((float)$it['costo']) * ((int)$it['cantidad']);
}

$conn->begin_transaction();
try {
    // 1. Encabezado de la compra
    $stmt = $conn->prepare("INSERT INTO compras (id_proveedor, total, fecha) VALUES (?, ?, NOW())");
    $stmt->bind_param("id", $id_proveedor, $total);
    if (!$stmt->execute()) throw new Exception($stmt->error);
    $id_compra = $conn->insert_id;

    $stmtDet  = $conn->prepare("INSERT INTO compras_det (id_compra, id_item, cantidad, costo_unitario) VALUES (?, ?, ?, ?)");
    $stmtUpd  = $conn->prepare("UPDATE existencias SET cantidad = cantidad + ? WHERE id_item = ?");
    $stmtChk  = $conn->prepare("SELECT cantidad FROM existencias WHERE id_item = ?");
    $stmtIns  = $conn->prepare("INSERT INTO existencias (id_item, cantidad) VALUES (?, ?)");

    foreach ($items as $it) {
        $id_item = (int)$it['id_item'];
        $cant    = (int)$it['cantidad'];
        $costo   = (float)$it['costo'];
        if ($id_item <= 0 || $cant <= 0 || $costo < 0) throw new Exception('Datos de producto inválidos.');

        // 2. Detalle (historial) + 3. Sumar al inventario
        $stmtChk->bind_param("i", $id_item);
        $stmtChk->execute();
        $r0 = $stmtChk->get_result()->fetch_assoc();
        $antes = $r0 ? (int)$r0['cantidad'] : null;

        $stmtDet->bind_param("iiid", $id_compra, $id_item, $cant, $costo);
        if (!$stmtDet->execute()) throw new Exception($stmtDet->error);

        $stmtChk->execute();
        $r1 = $stmtChk->get_result()->fetch_assoc();
        $despues = $r1 ? (int)$r1['cantidad'] : null;

        // Si un trigger de la BD ya sumó el stock, no lo sumamos otra vez
        if ($despues === $antes) {
            if ($antes !== null) {
                $stmtUpd->bind_param("ii", $cant, $id_item);
                if (!$stmtUpd->execute()) throw new Exception($stmtUpd->error);
            } else {
                $stmtIns->bind_param("ii", $id_item, $cant);
                if (!$stmtIns->execute()) throw new Exception($stmtIns->error);
            }
        }
    }

    $conn->commit();
    responder(true, 'Compra registrada y stock actualizado.');
} catch (Exception $e) {
    $conn->rollback();
    responder(false, 'Error: ' . $e->getMessage());
}