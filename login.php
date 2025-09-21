<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require_once "includes/functions.php";

// SOLO redirigir si YA está logueado
if (currentUser()) {
    header("Location: index.php");
    exit;
}

// Solo procesar login si se envió el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    // Verificar credenciales
    $users = readJSON('data/users.json');
    $user = null;
    
    foreach ($users as $u) {
        if (($u['email'] === $email || $u['username'] === $email) && password_verify($password, $u['password'])) {
            $user = $u;
            break;
        }
    }
    
    if ($user) {
        $_SESSION['username'] = $user['username'];
        header("Location: index.php");
        exit;
    } else {
        $error = "Credenciales incorrectas";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - ZyNapps</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #667eea;
            --primary-hover: #5a6fd8;
            --secondary-color: #764ba2;
            --bg-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --card-bg: rgba(255, 255, 255, 0.95);
            --text-color: #2d3748;
            --text-light: #718096;
            --border-color: #e2e8f0;
            --shadow-color: rgba(0, 0, 0, 0.1);
            --success-color: #48bb78;
            --error-color: #f56565;
            --border-radius: 16px;
            --animation-speed: 0.3s;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background: var(--bg-gradient);
            color: var(--text-color);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 1rem;
            position: relative;
            overflow: hidden;
        }
        
        /* Animated background elements */
        body::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="75" cy="75" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="75" cy="25" r="1" fill="rgba(255,255,255,0.05)"/><circle cx="25" cy="75" r="1" fill="rgba(255,255,255,0.05)"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
            animation: float 20s ease-in-out infinite;
            z-index: 0;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(180deg); }
        }
        
        .login-container {
            background: var(--card-bg);
            padding: 3rem 2.5rem;
            border-radius: var(--border-radius);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 
                0 20px 25px -5px var(--shadow-color),
                0 10px 10px -5px var(--shadow-color),
                inset 0 1px 0 rgba(255, 255, 255, 0.1);
            width: 100%;
            max-width: 420px;
            position: relative;
            z-index: 1;
            transform: translateY(0);
            transition: all var(--animation-speed) ease;
        }
        
        .login-container:hover {
            transform: translateY(-5px);
            box-shadow: 
                0 25px 35px -5px var(--shadow-color),
                0 15px 15px -5px var(--shadow-color),
                inset 0 1px 0 rgba(255, 255, 255, 0.1);
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 2.5rem;
        }
        
        .logo {
            width: 60px;
            height: 60px;
            background: var(--bg-gradient);
            border-radius: 50%;
            margin: 0 auto 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 10px 20px var(--shadow-color);
            animation: pulse 2s ease-in-out infinite;
        }
        
        .logo::before {
            content: 'Z';
            color: white;
            font-size: 24px;
            font-weight: 700;
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        
        .login-header h1 {
            background: var(--bg-gradient);
            background-clip: text;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            letter-spacing: -0.02em;
        }
        
        .login-header p {
            color: var(--text-light);
            font-size: 0.95rem;
            font-weight: 400;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
            position: relative;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.75rem;
            font-weight: 500;
            color: var(--text-color);
            font-size: 0.9rem;
            letter-spacing: 0.01em;
        }
        
        .input-wrapper {
            position: relative;
        }
        
        .form-group input {
            width: 100%;
            padding: 1rem 1.25rem;
            border: 2px solid var(--border-color);
            border-radius: 12px;
            font-size: 1rem;
            font-family: inherit;
            background: rgba(255, 255, 255, 0.8);
            transition: all var(--animation-speed) ease;
            outline: none;
        }
        
        .form-group input:focus {
            border-color: var(--primary-color);
            background: rgba(255, 255, 255, 1);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            transform: translateY(-1px);
        }
        
        .form-group input::placeholder {
            color: #a0aec0;
            transition: opacity var(--animation-speed) ease;
        }
        
        .form-group input:focus::placeholder {
            opacity: 0.5;
        }
        
        .btn-login {
            width: 100%;
            padding: 1rem 1.25rem;
            background: var(--bg-gradient);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all var(--animation-speed) ease;
            position: relative;
            overflow: hidden;
            letter-spacing: 0.01em;
            margin-bottom: 1.5rem;
        }
        
        .btn-login::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s ease;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
        }
        
        .btn-login:hover::before {
            left: 100%;
        }
        
        .btn-login:active {
            transform: translateY(0);
        }
        
        .error-message {
            background: rgba(245, 101, 101, 0.1);
            color: var(--error-color);
            padding: 1rem;
            border-radius: 12px;
            text-align: center;
            margin-bottom: 1.5rem;
            border: 1px solid rgba(245, 101, 101, 0.2);
            animation: shake 0.5s ease-in-out;
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }
        
        .register-link {
            text-align: center;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--border-color);
        }
        
        .register-link p {
            color: var(--text-light);
            font-size: 0.9rem;
        }
        
        .register-link a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 600;
            transition: all var(--animation-speed) ease;
            position: relative;
        }
        
        .register-link a::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 0;
            height: 2px;
            background: var(--primary-color);
            transition: width var(--animation-speed) ease;
        }
        
        .register-link a:hover {
            color: var(--primary-hover);
        }
        
        .register-link a:hover::after {
            width: 100%;
        }
        
        /* Mobile responsiveness */
        @media (max-width: 480px) {
            .login-container {
                padding: 2rem 1.5rem;
                margin: 1rem;
            }
            
            .login-header h1 {
                font-size: 1.75rem;
            }
            
            .logo {
                width: 50px;
                height: 50px;
            }
            
            .logo::before {
                font-size: 20px;
            }
        }
        
        /* Dark mode support */
        @media (prefers-color-scheme: dark) {
            :root {
                --card-bg: rgba(45, 55, 72, 0.95);
                --text-color: #f7fafc;
                --text-light: #a0aec0;
                --border-color: #4a5568;
            }
            
            .form-group input {
                background: rgba(45, 55, 72, 0.8);
                color: #f7fafc;
            }
            
            .form-group input:focus {
                background: rgba(45, 55, 72, 1);
            }
        }
        
        /* Loading animation for form submission */
        .btn-login.loading {
            pointer-events: none;
            opacity: 0.8;
        }
        
        .btn-login.loading::after {
            content: '';
            position: absolute;
            width: 16px;
            height: 16px;
            margin: auto;
            border: 2px solid transparent;
            border-top-color: #ffffff;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <div class="logo"></div>
            <h1>ZyNapps</h1>
            <p>Bienvenido de vuelta</p>
        </div>
        
        <?php if (isset($error)): ?>
            <div class="error-message">
                <strong>¡Oops!</strong> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="login.php" id="loginForm">
            <div class="form-group">
                <label for="email">Correo electrónico o usuario</label>
                <div class="input-wrapper">
                    <input type="text" id="email" name="email" placeholder="Ingresa tu email o usuario" required>
                </div>
            </div>
            
            <div class="form-group">
                <label for="password">Contraseña</label>
                <div class="input-wrapper">
                    <input type="password" id="password" name="password" placeholder="Ingresa tu contraseña" required>
                </div>
            </div>
            
            <button type="submit" class="btn-login" id="loginBtn">
                Iniciar sesión
            </button>
        </form>
        
        <div class="register-link">
            <p>¿No tienes cuenta? <a href="register.php">Crear cuenta</a></p>
        </div>
    </div>

    <script>
        // Add smooth form submission with loading state
        document.getElementById('loginForm').addEventListener('submit', function() {
            const btn = document.getElementById('loginBtn');
            btn.classList.add('loading');
            btn.innerHTML = 'Iniciando sesión...';
        });

        // Add input focus animations
        const inputs = document.querySelectorAll('input');
        inputs.forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.parentElement.style.transform = 'translateY(-2px)';
            });
            
            input.addEventListener('blur', function() {
                this.parentElement.parentElement.style.transform = 'translateY(0)';
            });
        });

        // Prevent form resubmission on page refresh
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
    </script>
</body>
</html>