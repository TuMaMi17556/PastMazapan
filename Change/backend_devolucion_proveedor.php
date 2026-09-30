<?php
header('Content-Type: application/json');
ini_set('display_errors', 0);
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

function responder($ok, $msg = '') { echo json_encode(['success' => $ok, 'msg' => $msg]); exit(); }

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') responder(false, 'Acceso denegado');

$data = json_decode(file_get_contents('php://input'), true);
$id_compra = (int)($data['id_compra'] ?? 0);
$motivo    = trim($data['motivo'] ?? '');
$items     = $data['items'] ?? [];
if ($id_compra <= 0 || empty($items)) responder(false, 'No hay productos para devolver.');

$conn->begin_transaction();
try {
    $total = 0;
    $validos = [];

    foreach ($items as $it) {
        $id_item = (int)$it['id_item'];
        $cant    = (int)$it['cantidad'];
        if ($cant <= 0) continue;

        // Lo comprado en ESA compra
        $st = $conn->prepare("SELECT SUM(cantidad) AS cant, MAX(precio_unitario) AS costo FROM compras_det WHERE id_compra=? AND id_item=?");
        $st->bind_param("ii", $id_compra, $id_item); $st->execute();
        $comp = $st->get_result()->fetch_assoc();
        if (!$comp || !$comp['cant']) throw new Exception('Un producto no pertenece a esta compra.');

        // Lo ya devuelto de esa compra
        $st = $conn->prepare("SELECT IFNULL(SUM(dd.cantidad),0) AS dev FROM devoluciones_proveedor_det dd
                              JOIN devoluciones_proveedor dp ON dp.id = dd.id_devolucion
                              WHERE dp.id_compra=? AND dd.id_item=?");
        $st->bind_param("ii", $id_compra, $id_item); $st->execute();
        $ya = (int)$st->get_result()->fetch_assoc()['dev'];

        $disponible = (int)$comp['cant'] - $ya;
        if ($cant > $disponible) throw new Exception("Solo puedes devolver $disponible unidad(es) de un producto (comprado menos ya devuelto).");

        // Stock actual (bloqueado para evitar carreras)
        $st = $conn->prepare("SELECT cantidad FROM existencias WHERE id_item=? FOR UPDATE");
        $st->bind_param("i", $id_item); $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $stock = $row ? (int)$row['cantidad'] : 0;
        if ($cant > $stock) throw new Exception("No hay stock suficiente para devolver $cant (stock actual: $stock).");

        $costo = (float)$comp['costo'];
        $total += $cant * $costo;
        $validos[] = ['id_item' => $id_item, 'cant' => $cant, 'costo' => $costo];
    }
    if (empty($validos)) throw new Exception('Indica al menos una cantidad a devolver.');

    // Encabezado
    $uid = (int)$_SESSION['user_id'];
    $st = $conn->prepare("INSERT INTO devoluciones_proveedor (id_compra, id_usuario, fecha, total, motivo) VALUES (?, ?, NOW(), ?, ?)");
    $st->bind_param("iids", $id_compra, $uid, $total, $motivo);
    $st->execute();
    $id_dev = $conn->insert_id;

    $stDet = $conn->prepare("INSERT INTO devoluciones_proveedor_det (id_devolucion, id_item, cantidad, costo_unitario) VALUES (?, ?, ?, ?)");
    $stUpd = $conn->prepare("UPDATE existencias SET cantidad = cantidad - ? WHERE id_item = ?");
    foreach ($validos as $v) {
        $stDet->bind_param("iiid", $id_dev, $v['id_item'], $v['cant'], $v['costo']); $stDet->execute();
        $stUpd->bind_param("ii", $v['cant'], $v['id_item']); $stUpd->execute();   // RESTA del inventario
    }

    $conn->commit();
    responder(true, 'Devolución al proveedor registrada.');
} catch (Exception $e) {
    $conn->rollback();
    responder(false, $e->getMessage());
}