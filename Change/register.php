<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro - Pastelería</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <h2 style="color: var(--primary);">Crear Cuenta</h2>
            
            <?php if(isset($_GET['error'])): ?>
                <div class="error-msg"><?php echo htmlspecialchars($_GET['error']); ?></div>
            <?php endif; ?>

            <form action="auth_register.php" method="POST">
                <input type="text" name="nombre" class="login-input" placeholder="Nombre completo" required>
                <input type="email" name="correo" class="login-input" placeholder="Correo electrónico" required>
                <input type="password" name="contrasena" class="login-input" placeholder="Contraseña" required>
                
                <div class="form-group" style="text-align: left; margin-bottom: 15px;">
                    <label style="font-size: 0.8rem; color: #666;">Rol de usuario:</label>
                    <select name="rol" class="form-control">
                        <option value="usuario">Cajero (Solo ventas)</option>
                        <option value="admin">Administrador (Control total)</option>
                    </select>
                </div>

                <button type="submit" class="btn btn-success w-100">REGISTRARSE</button>
            </form>
            
            <div style="margin-top: 15px;">
                <a href="index.php" style="color: #666; text-decoration: none;">&larr; Volver al Login</a>
            </div>
        </div>
    </div>
</body>
</html>