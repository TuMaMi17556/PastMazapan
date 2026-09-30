<?php
$host = "sql302.infinityfree.com";
$user = "if0_43046433"; // Cambia esto si tienes contraseña
$pass = "GdTf2XSlXY";     
$db   = "if0_43046433_PASTELERIAMZ";

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");
?>