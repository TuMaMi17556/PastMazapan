<?php
session_start();
require 'db.php';

// Permitir admin y también usuario (si en el futuro quieres que ellos creen productos)
// Por ahora lo dejamos restringido a admin como estaba
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') { die("Acceso denegado"); }

$codigo = $_POST['codigo'];
$nombre = $_POST['nombre'];
$precio = $_POST['precio'];
$cant   = $_POST['cantidad']; // Stock inicial

$conn->begin_transaction();

try {
    // 1. Insertar Item
    $stmt = $conn->prepare("INSERT INTO items (codigo, nombre, precio, activo) VALUES (?, ?, ?, 1)");
    $stmt->bind_param("ssd", $codigo, $nombre, $precio);
    $stmt->execute();
    $id_item = $stmt->insert_id;

    // 2. Insertar Existencia
    $conn->query("INSERT INTO existencias (id_item, cantidad) VALUES ($id_item, $cant)");

    // 3. GUARDAR IMAGEN (NUEVO)
    if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === 0) {
        $tipo = $_FILES['imagen']['type'];
        // Leemos el archivo como datos binarios
        $datos = file_get_contents($_FILES['imagen']['tmp_name']);
        
        $stmt_img = $conn->prepare("INSERT INTO imagenes_item (id_item, imagen, tipo) VALUES (?, ?, ?)");
        // "ibs" -> Integer, Blob, String. Send_long_data se usa para BLOBs grandes, pero bind_param "b" funciona para medianos.
        // Para simplificar y evitar problemas con drivers, usamos null y send_long_data
        $null = NULL;
        $stmt_img->bind_param("ibs", $id_item, $null, $tipo);
        $stmt_img->send_long_data(1, $datos);
        $stmt_img->execute();
    }

    $conn->commit();
    header("Location: admin.php?success=Producto guardado");

} catch (Exception $e) {
    $conn->rollback();
    header("Location: admin.php?error=" . urlencode($e->getMessage()));
}
?>