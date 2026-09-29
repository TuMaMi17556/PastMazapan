<?php
require 'db.php';

if (!isset($_GET['id'])) {
    echo "<tr><td colspan='3'>ID no proporcionado</td></tr>";
    exit;
}

$compra_id = $_GET['id'];

try {
    $stmt = $pdo->prepare("SELECT cantidad, producto, costo FROM detalle_compras WHERE compra_id = ?");
    $stmt->execute([$compra_id]);
    $detalles = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($detalles) > 0) {
        foreach ($detalles as $row) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['cantidad']) . "</td>";
            echo "<td>" . htmlspecialchars($row['producto']) . "</td>";
            echo "<td>$" . number_format($row['costo'], 2) . "</td>";
            echo "</tr>";
        }
    } else {
        echo "<tr><td colspan='3'>No hay detalles para esta compra.</td></tr>";
    }
} catch (Exception $e) {
    echo "<tr><td colspan='3'>Error de base de datos.</td></tr>";
}
?>