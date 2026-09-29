<?php
$host = "sql106.infinityfree.com";
$user = "if0_43019697"; // Cambia esto si tienes contraseña
$pass = "lmjAVq73R1QhI";     
$db   = "if0_43019697_pasteleriaMZ";

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");
?>