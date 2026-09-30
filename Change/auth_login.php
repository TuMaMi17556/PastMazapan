<?php
    ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require 'db.php';

// Verificar que lleguen datos del formulario
if (!isset($_POST['correo']) || !isset($_POST['password'])) {
    header("Location: index.php?error=Por favor llena el formulario");
    exit();
}

$correo = $_POST['correo'];
$password = $_POST['password']; // Ahora coincide con el name="password" del index

// Buscar usuario en BD
$sql = "SELECT id, nombre, contrasena, rol, activo FROM usuarios WHERE correo = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $correo);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $row = $result->fetch_assoc();
    
    // Comparar la contraseña escrita con la de la BD
    if ($password === $row['contrasena']) { 
        
        // Verificar si está activo
        if($row['activo'] == 0){
            header("Location: index.php?error=Usuario inactivo");
            exit();
        }

        // Crear sesión
        $_SESSION['user_id'] = $row['id'];
        $_SESSION['user_name'] = $row['nombre'];
        $_SESSION['user_role'] = $row['rol'];

        // Redireccionar
        if ($row['rol'] === 'admin') {
            header("Location: admin.php");
        } else {
            header("Location: pos.php");
        }
        exit();

    } else {
        header("Location: index.php?error=Contraseña incorrecta");
        exit();
    }
} else {
    header("Location: index.php?error=Usuario no encontrado");
    exit();
}
?>