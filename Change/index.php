<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Pastelería</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <h2 style="color: var(--primary);">Pastelería Dulce Sabor</h2>
            
            <?php if(isset($_GET['error'])): ?>
                <div class="error-msg"><?php echo htmlspecialchars($_GET['error']); ?></div>
            <?php endif; ?>

            <form action="auth_login.php" method="POST">
                <input type="email" name="correo" class="login-input" placeholder="Correo electrónico" required autofocus>
                
                <input type="password" name="password" class="login-input" placeholder="Contraseña" required>
                
                <button type="submit" class="btn btn-primary w-100">INGRESAR</button>

           
            </form>
        </div>
    </div>
</body>
</html>