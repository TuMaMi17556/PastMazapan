<?php
session_start();
require 'db.php';

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'login') {
    $correo = $_POST['correo'];
    $pass   = $_POST['password'];

    $stmt = $conn->prepare("SELECT id, nombre, contrasena, rol, activo FROM usuarios WHERE correo = ?");
    $stmt->bind_param("s", $correo);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($row = $res->fetch_assoc()) {
        if ($row['activo'] == 0) {
            header("Location: index.php?error=Usuario inactivo");
            exit;
        }
        // NOTA: Para producción usar password_verify($pass, $row['contrasena'])
        // Usamos comparación directa porque tus datos dummy están en texto plano
        if ($pass === $row['contrasena']) {
            $_SESSION['user'] = [
                'id' => $row['id'],
                'name' => $row['nombre'],
                'role' => $row['rol']
            ];
            
            if ($row['rol'] === 'admin') {
                header("Location: admin.php");
            } else {
                header("Location: pos.php");
            }
        } else {
            header("Location: index.php?error=Contraseña incorrecta");
        }
    } else {
        header("Location: index.php?error=Usuario no encontrado");
    }
} elseif ($action === 'logout') {
    session_destroy();
    header("Location: index.php");
}
?>