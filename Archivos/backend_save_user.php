<?php
session_start();
require 'db.php';
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') { die("Acceso denegado"); }

$nombre = $_POST['nombre'];
$correo = $_POST['correo'];
$password = $_POST['password']; 
$rol = $_POST['rol'];
$activo = 1;

$check = $conn->query("SELECT id FROM usuarios WHERE correo = '$correo'");
if($check->num_rows > 0) {
    header("Location: admin_usuarios.php?error=El correo ya existe");
    exit();
}

$sql = "INSERT INTO usuarios (nombre, correo, contrasena, rol, activo) VALUES (?, ?, ?, ?, ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ssssi", $nombre, $correo, $password, $rol, $activo);

if ($stmt->execute()) {
    header("Location: admin_usuarios.php?success=Usuario creado");
} else {
    header("Location: admin_usuarios.php?error=Error BD");
}
?>