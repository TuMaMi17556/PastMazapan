<?php
session_start();
require 'db.php';

// Seguridad: Solo admin puede borrar
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    die("Acceso denegado");
}

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    
    // Al borrar el item, la BD borrará automáticamente las existencias y fotos asociadas
    // gracias a la configuración ON DELETE CASCADE que ya tienes.
    $stmt = $conn->prepare("DELETE FROM items WHERE id = ?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        header("Location: admin.php?success=Producto eliminado");
    } else {
        header("Location: admin.php?error=No se pudo eliminar");
    }
} else {
    header("Location: admin.php");
}
?>