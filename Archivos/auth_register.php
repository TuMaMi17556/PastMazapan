<?php
session_start();
require 'db.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nombre = $_POST['nombre'] ?? '';
    $correo = $_POST['correo'] ?? '';
    $password = $_POST['contrasena'] ?? ''; 
    $rol = $_POST['rol'] ?? 'usuario';
    $activo = 1;

    try {
        $check = $conn->prepare("SELECT id FROM usuarios WHERE correo = ?");
        $check->bind_param("s", $correo);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            header("Location: register.php?error=Este correo ya está registrado");
            exit();
        }
        $check->close();

        $sql = "INSERT INTO usuarios (nombre, correo, contrasena, rol, activo) VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssi", $nombre, $correo, $password, $rol, $activo);

        if ($stmt->execute()) {
            header("Location: index.php?error=Cuenta creada con éxito. Inicia sesión.");
        } else {
            header("Location: register.php?error=Error al guardar");
        }
        $stmt->close();
        $conn->close();

    } catch (Exception $e) {
        header("Location: register.php?error=" . urlencode($e->getMessage()));
    }
}
?>