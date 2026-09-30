<?php
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
header('Content-Type: application/json');
mysqli_report(MYSQLI_REPORT_OFF);

if (!isset($_SESSION['user_role'])) { echo json_encode(['error' => 'Sesión expirada']); exit; }

$tipo = $_GET['tipo'] ?? '';
$id   = (int)($_GET['id'] ?? 0);

switch ($tipo) {
    case 'compra':
        $sql = "SELECT d.cantidad, i.nombre, d.costo_unitario AS precio_unitario
                FROM compras_det d JOIN items i ON i.id = d.id_item
                WHERE d.id_compra = ?";
        break;
    case 'venta':
        $sql = "SELECT d.cantidad, i.nombre, d.precio_unitario
                FROM ventas_det d JOIN items i ON i.id = d.id_item
                WHERE d.id_venta = ?";
        break;
    case 'devolucion':
        $sql = "SELECT d.cantidad, i.nombre, d.monto_devuelto AS precio_unitario
                FROM devoluciones_det d JOIN items i ON i.id = d.id_item
                WHERE d.id_devolucion = ?";
        break;
    case 'devolucion_prov':
        $sql = "SELECT d.cantidad, i.nombre, d.costo_unitario AS precio_unitario
                FROM devoluciones_proveedor_det d JOIN items i ON i.id = d.id_item
                WHERE d.id_devolucion = ?";
        break;
    default:
        echo json_encode(['error' => 'Tipo no válido']); exit;
}

$stmt = $conn->prepare($sql);
if (!$stmt) { echo json_encode(['error' => $conn->error]); exit; }
$stmt->bind_param("i", $id);
if (!$stmt->execute()) { echo json_encode(['error' => $stmt->error]); exit; }
echo json_encode($stmt->get_result()->fetch_all(MYSQLI_ASSOC));