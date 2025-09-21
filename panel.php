<?php
session_start();
include "includes/functions.php"; // Debe tener readJSON() y writeJSON()

// ===== LOGIN AL PANEL =====
if (isset($_POST['login_submit'])) {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // Leer usuarios de la base de datos
    $users = readJSON('data/users.json') ?? [];
    $authenticated_user = null;
    
    // Buscar usuario por email y verificar que sea admin
    foreach ($users as $user) {
        if (($user['email'] ?? '') === $email && ($user['role'] ?? '') === 'admin') {
            // Verificar contraseña
            if (password_verify($password, $user['password'] ?? '')) {
                $authenticated_user = $user;
                break;
            }
        }
    }
    
    if ($authenticated_user) {
        $_SESSION['panel_access'] = true;
        $_SESSION['admin_email'] = $authenticated_user['email'];
        $_SESSION['admin_username'] = $authenticated_user['username'];
        $_SESSION['admin_name'] = $authenticated_user['full_name'];
        $_SESSION['admin_role'] = 'Administrador';
        $_SESSION['admin_id'] = $authenticated_user['id'];
        $_SESSION['login_time'] = date('Y-m-d H:i:s');
        
        // Actualizar última actividad
        foreach ($users as $k => $u) {
            if ($u['id'] === $authenticated_user['id']) {
                $users[$k]['last_active'] = time();
                writeJSON('data/users.json', $users);
                break;
            }
        }
        
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    } else {
        $error = "Credenciales incorrectas o no tienes permisos de administrador.";
    }
}

// Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

if (empty($_SESSION['panel_access'])): ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Administración - ZyNapps</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }
        
        .background-animation {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0.1;
            background: 
                radial-gradient(ellipse at 25% 25%, #ffffff 2px, transparent 2px),
                radial-gradient(ellipse at 75% 75%, #ffffff 1px, transparent 1px);
            background-size: 60px 60px, 40px 40px;
            animation: float 20s ease-in-out infinite;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(2deg); }
        }
        
        .geometric-shapes {
            position: absolute;
            width: 100%;
            height: 100%;
            overflow: hidden;
        }
        
        .shape {
            position: absolute;
            opacity: 0.1;
        }
        
        .shape1 {
            width: 200px;
            height: 200px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
            top: 10%;
            left: 10%;
            animation: rotate 30s linear infinite;
        }
        
        .shape2 {
            width: 150px;
            height: 150px;
            background: rgba(255,255,255,0.05);
            transform: rotate(45deg);
            top: 70%;
            right: 10%;
            animation: rotate 25s linear infinite reverse;
        }
        
        .shape3 {
            width: 100px;
            height: 100px;
            background: rgba(255,255,255,0.08);
            border-radius: 30%;
            bottom: 20%;
            left: 20%;
            animation: pulse 4s ease-in-out infinite;
        }
        
        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }
        
        .login-container {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(25px);
            border-radius: 20px;
            box-shadow: 
                0 20px 40px rgba(0, 0, 0, 0.2),
                inset 0 1px 0 rgba(255, 255, 255, 0.3);
            padding: 30px 35px;
            width: 100%;
            max-width: 380px;
            text-align: center;
            position: relative;
            z-index: 10;
            border: 1px solid rgba(255, 255, 255, 0.2);
            transform: translateY(0);
            animation: slideIn 0.8s ease-out;
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .login-header {
            margin-bottom: 30px;
        }
        
        .logo-container {
            position: relative;
            margin-bottom: 15px;
        }
        
        .logo {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, rgba(255,255,255,0.3) 0%, rgba(255,255,255,0.1) 100%);
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
            box-shadow: 
                0 10px 20px rgba(0, 0, 0, 0.2),
                inset 0 1px 0 rgba(255, 255, 255, 0.4);
            position: relative;
            overflow: hidden;
        }
        
        .logo::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(45deg, transparent, rgba(255,255,255,0.1), transparent);
            animation: shimmer 3s ease-in-out infinite;
        }
        
        @keyframes shimmer {
            0% { transform: translateX(-100%) translateY(-100%) rotate(45deg); }
            50% { transform: translateX(100%) translateY(100%) rotate(45deg); }
            100% { transform: translateX(-100%) translateY(-100%) rotate(45deg); }
        }
        
        .logo i {
            font-size: 1.8rem;
            color: white;
            position: relative;
            z-index: 2;
            text-shadow: 0 2px 10px rgba(0,0,0,0.3);
        }
        
        .brand-name {
            font-size: 1.8rem;
            font-weight: 700;
            color: white;
            margin-bottom: 5px;
            text-shadow: 0 2px 15px rgba(0,0,0,0.3);
            letter-spacing: -1px;
        }
        
        .brand-tagline {
            font-size: 0.9rem;
            color: rgba(255,255,255,0.9);
            margin-bottom: 5px;
            font-weight: 300;
        }
        
        .admin-subtitle {
            font-size: 0.75rem;
            color: rgba(255,255,255,0.8);
            font-weight: 400;
            letter-spacing: 1px;
            text-transform: uppercase;
        }
        
        .current-time {
            background: rgba(255,255,255,0.1);
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 0.75rem;
            color: rgba(255,255,255,0.9);
            margin-top: 10px;
            display: inline-block;
            backdrop-filter: blur(10px);
        }
        
        .form-section {
            margin-bottom: 25px;
        }
        
        .form-group {
            margin-bottom: 18px;
            position: relative;
            text-align: left;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: rgba(255,255,255,0.9);
            font-weight: 500;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
        }
        
        .input-container {
            position: relative;
        }
        
        .input-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: rgba(255,255,255,0.6);
            z-index: 2;
            font-size: 0.9rem;
        }
        
        .form-control {
            width: 100%;
            padding: 15px 15px 15px 40px;
            border: 2px solid rgba(255,255,255,0.2);
            border-radius: 12px;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.1);
            color: white;
            font-family: 'Poppins', sans-serif;
            backdrop-filter: blur(10px);
        }
        
        .form-control::placeholder {
            color: rgba(255,255,255,0.6);
        }
        
        .form-control:focus {
            outline: none;
            border-color: rgba(255,255,255,0.6);
            box-shadow: 0 0 0 4px rgba(255, 255, 255, 0.1);
            background: rgba(255, 255, 255, 0.15);
            transform: translateY(-2px);
        }
        
        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: rgba(255,255,255,0.6);
            cursor: pointer;
            z-index: 2;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }
        
        .password-toggle:hover {
            color: rgba(255,255,255,0.9);
            transform: translateY(-50%) scale(1.1);
        }
        
        .btn-login {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, rgba(255,255,255,0.25) 0%, rgba(255,255,255,0.15) 100%);
            color: white;
            border: 2px solid rgba(255,255,255,0.3);
            border-radius: 12px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.4s ease;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            position: relative;
            overflow: hidden;
            backdrop-filter: blur(10px);
            font-family: 'Poppins', sans-serif;
        }
        
        .btn-login::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.6s;
        }
        
        .btn-login:hover::before {
            left: 100%;
        }
        
        .btn-login:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.3);
            border-color: rgba(255,255,255,0.5);
            background: linear-gradient(135deg, rgba(255,255,255,0.3) 0%, rgba(255,255,255,0.2) 100%);
        }
        
        .btn-login:active {
            transform: translateY(-1px);
        }
        
        .error-message {
            background: rgba(231, 76, 60, 0.2);
            color: #fff;
            padding: 12px 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            border: 1px solid rgba(231, 76, 60, 0.3);
            text-align: left;
            display: flex;
            align-items: center;
            gap: 8px;
            backdrop-filter: blur(10px);
            animation: shake 0.5s ease-in-out;
            font-size: 0.85rem;
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }
        
        .security-section {
            margin-top: 25px;
        }
        
        .security-note {
            padding: 15px;
            background: rgba(255,255,255,0.1);
            border-radius: 12px;
            border: 1px solid rgba(255,255,255,0.2);
            backdrop-filter: blur(15px);
            text-align: left;
        }
        
        .security-note h4 {
            color: rgba(255,255,255,0.95);
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        
        .security-note p {
            color: rgba(255,255,255,0.8);
            line-height: 1.5;
            margin: 0;
            font-size: 0.75rem;
        }
        
        .company-section {
            margin-top: 20px;
            text-align: center;
        }
        
        .company-info {
            padding: 15px;
            background: rgba(255,255,255,0.08);
            border-radius: 12px;
            border: 1px solid rgba(255,255,255,0.15);
            backdrop-filter: blur(15px);
        }
        
        .company-info h3 {
            color: rgba(255,255,255,0.95);
            font-size: 0.95rem;
            margin-bottom: 5px;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .company-info p {
            color: rgba(255,255,255,0.8);
            font-size: 0.75rem;
            margin: 0;
            font-weight: 300;
        }
        
        .version-info {
            margin-top: 15px;
            font-size: 0.7rem;
            color: rgba(255,255,255,0.6);
            text-align: center;
        }
        
        @media (max-width: 480px) {
            .login-container {
                margin: 20px;
                padding: 25px 20px;
                max-width: 320px;
            }
            
            .brand-name {
                font-size: 1.5rem;
            }
            
            .logo {
                width: 50px;
                height: 50px;
            }
            
            .logo i {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="background-animation"></div>
    
    <div class="geometric-shapes">
        <div class="shape shape1"></div>
        <div class="shape shape2"></div>
        <div class="shape shape3"></div>
    </div>
    
    <div class="login-container">
        <div class="login-header">
            <div class="logo-container">
                <div class="logo">
                    <i class="fas fa-shield-alt"></i>
                </div>
            </div>
            <div class="brand-name">ZyNapps</div>
            <div class="brand-tagline">Panel de Administración</div>
            <div class="admin-subtitle">Acceso Restringido</div>
            <div class="current-time">
                <i class="fas fa-clock"></i> <?= date('d/m/Y H:i:s') ?>
            </div>
        </div>
        
        <?php if (!empty($error)): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-triangle"></i>
                <span><?= $error ?></span>
            </div>
        <?php endif; ?>
        
        <div class="form-section">
            <form method="POST">
                <div class="form-group">
                    <label for="email">
                        <i class="fas fa-envelope"></i> Correo Electrónico
                    </label>
                    <div class="input-container">
                        <i class="fas fa-envelope input-icon"></i>
                        <input type="email" 
                               id="email" 
                               name="email" 
                               class="form-control" 
                               placeholder="admin@zynapps.com" 
                               required 
                               autofocus
                               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="password">
                        <i class="fas fa-lock"></i> Contraseña
                    </label>
                    <div class="input-container">
                        <i class="fas fa-lock input-icon"></i>
                        <input type="password" 
                               id="password" 
                               name="password" 
                               class="form-control" 
                               placeholder="••••••••••••" 
                               required>
                        <i class="fas fa-eye password-toggle" onclick="togglePassword()"></i>
                    </div>
                </div>
                
                <button type="submit" name="login_submit" class="btn-login">
                    <i class="fas fa-sign-in-alt"></i> Iniciar Sesión
                </button>
            </form>
        </div>
        
        <div class="company-section">
            <div class="company-info">
                <h3><i class="fas fa-rocket"></i> ZyNapps</h3>
                <p>Sistema de administración y moderación avanzado</p>
            </div>
        </div>
    </div>

    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.querySelector('.password-toggle');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }
        
        // Auto-focus on email input
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('email').focus();
        });
        
        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const email = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value;
            
            if (!email || !password) {
                e.preventDefault();
                alert('Por favor, completa todos los campos.');
            }
        });
        
        // Security: Clear password field on page unload
        window.addEventListener('beforeunload', function() {
            document.getElementById('password').value = '';
        });
        
        // Update time every second
        setInterval(function() {
            const now = new Date();
            const timeString = now.toLocaleString('es-ES', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            });
            document.querySelector('.current-time').innerHTML = '<i class="fas fa-clock"></i> ' + timeString;
        }, 1000);
        
        // Add floating animation to login container
        function addFloatingAnimation() {
            const container = document.querySelector('.login-container');
            let start = null;
            
            function animate(timestamp) {
                if (!start) start = timestamp;
                const progress = timestamp - start;
                
                const offsetY = Math.sin(progress * 0.001) * 5;
                container.style.transform = `translateY(${offsetY}px)`;
                
                requestAnimationFrame(animate);
            }
            
            requestAnimationFrame(animate);
        }
        
        // Start floating animation when page loads
        window.addEventListener('load', addFloatingAnimation);
    </script>
</body>
</html>
<?php exit; endif;

// ===== PANEL PRINCIPAL =====
$users = readJSON('data/users.json') ?? [];
$posts = readJSON('data/posts.json') ?? [];
$admin_email = $_SESSION['admin_email'] ?? '';
$admin_username = $_SESSION['admin_username'] ?? '';
$admin_name = $_SESSION['admin_name'] ?? 'Admin';
$admin_role = $_SESSION['admin_role'] ?? 'Admin';
$admin_id = $_SESSION['admin_id'] ?? '';
$login_time = $_SESSION['login_time'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_type'])) {
    $action     = $_POST['action_type'];
    $targetUser = $_POST['target_user'] ?? '';
    $targetPost = $_POST['target_post'] ?? '';

    // Asignar rol
    if ($action === 'assign_role' && $targetUser && isset($_POST['role'])) {
        foreach ($users as $k => $u) {
            if (($u['username'] ?? '') === $targetUser) {
                $users[$k]['role'] = $_POST['role'];
                writeJSON('data/users.json', $users);
                $_SESSION['success_message'] = "Rol actualizado correctamente para $targetUser";
                header("Location: ".$_SERVER['PHP_SELF']); exit;
            }
        }
    }

    // Verificar
    if ($action === 'verify_user' && $targetUser) {
        foreach ($users as $k => $u) {
            if (($u['username'] ?? '') === $targetUser) {
                $users[$k]['verified'] = true;
                writeJSON('data/users.json', $users);
                $_SESSION['success_message'] = "Usuario $targetUser verificado correctamente";
                header("Location: ".$_SERVER['PHP_SELF']); exit;
            }
        }
    }

    // Quitar verificación
    if ($action === 'unverify_user' && $targetUser) {
        foreach ($users as $k => $u) {
            if (($u['username'] ?? '') === $targetUser) {
                $users[$k]['verified'] = false;
                writeJSON('data/users.json', $users);
                $_SESSION['success_message'] = "Verificación removida de $targetUser";
                header("Location: ".$_SERVER['PHP_SELF']); exit;
            }
        }
    }

    // Banear
    if ($action === 'ban_user' && $targetUser) {
        foreach ($users as $k => $u) {
            if (($u['username'] ?? '') === $targetUser) {
                $users[$k]['banned'] = true;
                writeJSON('data/users.json', $users);
                $_SESSION['success_message'] = "Usuario $targetUser baneado correctamente";
                header("Location: ".$_SERVER['PHP_SELF']); exit;
            }
        }
    }

    // Desbanear
    if ($action === 'unban_user' && $targetUser) {
        foreach ($users as $k => $u) {
            if (($u['username'] ?? '') === $targetUser) {
                $users[$k]['banned'] = false;
                writeJSON('data/users.json', $users);
                $_SESSION['success_message'] = "Usuario $targetUser desbaneado correctamente";
                header("Location: ".$_SERVER['PHP_SELF']); exit;
            }
        }
    }

    // Mutear
    if ($action === 'mute_user' && $targetUser) {
        foreach ($users as $k => $u) {
            if (($u['username'] ?? '') === $targetUser) {
                $users[$k]['muted'] = true;
                writeJSON('data/users.json', $users);
                $_SESSION['success_message'] = "Usuario $targetUser muteado correctamente";
                header("Location: ".$_SERVER['PHP_SELF']); exit;
            }
        }
    }

    // Desmutear
    if ($action === 'unmute_user' && $targetUser) {
        foreach ($users as $k => $u) {
            if (($u['username'] ?? '') === $targetUser) {
                $users[$k]['muted'] = false;
                writeJSON('data/users.json', $users);
                $_SESSION['success_message'] = "Usuario $targetUser desmuteado correctamente";
                header("Location: ".$_SERVER['PHP_SELF']); exit;
            }
        }
    }

    // Eliminar post
    if ($action === 'delete_post' && $targetPost) {
        foreach ($posts as $k => $p) {
            if (($p['id'] ?? '') == $targetPost) {
                unset($posts[$k]);
                writeJSON('data/posts.json', array_values($posts));
                $_SESSION['success_message'] = "Publicación eliminada correctamente";
                header("Location: ".$_SERVER['PHP_SELF']); exit;
            }
        }
    }
}

$success_message = $_SESSION['success_message'] ?? '';
unset($_SESSION['success_message']);

// Calcular estadísticas
$total_users = count($users);
$verified_users = count(array_filter($users, fn($u) => !empty($u['verified'])));
$banned_users = count(array_filter($users, fn($u) => !empty($u['banned'])));
$muted_users = count(array_filter($users, fn($u) => !empty($u['muted'])));
$admin_users = count(array_filter($users, fn($u) => ($u['role'] ?? '') === 'admin'));
$mod_users = count(array_filter($users, fn($u) => ($u['role'] ?? '') === 'mod'));
$total_posts = count($posts);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Administración - ZyNapps</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f8f9fa;
            color: #333;
            line-height: 1.6;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px 0;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
        }
        
        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header h1 {
            font-size: 2rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .header-info {
            display: flex;
            align-items: center;
            gap: 25px;
        }
        
        .admin-profile {
            background: rgba(255,255,255,0.15);
            padding: 12px 20px;
            border-radius: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
            backdrop-filter: blur(10px);
        }
        
        .admin-avatar {
            width: 40px;
            height: 40px;
            background: rgba(255,255,255,0.3);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }
        
        .admin-details h4 {
            font-size: 0.95rem;
            margin-bottom: 2px;
        }
        
        .admin-details p {
            font-size: 0.8rem;
            opacity: 0.9;
        }
        
        .logout-btn {
            background: rgba(255,255,255,0.1);
            color: white;
            padding: 12px 20px;
            border: 2px solid rgba(255,255,255,0.3);
            border-radius: 10px;
            text-decoration: none;
            transition: all 0.3s ease;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .logout-btn:hover {
            background: rgba(255,255,255,0.2);
            border-color: rgba(255,255,255,0.5);
            transform: translateY(-1px);
        }
        
        .container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }
        
        .success-message {
            background: linear-gradient(135deg, #2ecc71, #27ae60);
            color: white;
            padding: 18px 25px;
            border-radius: 12px;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 12px;
            box-shadow: 0 6px 20px rgba(46, 204, 113, 0.3);
            animation: slideInDown 0.5s ease;
        }
        
        @keyframes slideInDown {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .section {
            background: white;
            border-radius: 18px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.08);
            margin-bottom: 35px;
            overflow: hidden;
        }
        
        .section-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px 30px;
            font-size: 1.4rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .table-container {
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 18px 25px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        th {
            background: #f8f9fa;
            font-weight: 600;
            color: #555;
            font-size: 0.95rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        tr:hover {
            background: #f8f9fa;
        }
        
        .badge {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .badge-verified {
            background: #d4edda;
            color: #155724;
        }
        
        .badge-admin {
            background: #f8d7da;
            color: #721c24;
        }
        
        .badge-mod {
            background: #fff3cd;
            color: #856404;
        }
        
        .badge-user {
            background: #e2e3e5;
            color: #383d41;
        }
        
        .badge-banned {
            background: #f8d7da;
            color: #721c24;
        }
        
        .badge-muted {
            background: #ffeaa7;
            color: #e17055;
        }
        
        .btn {
            padding: 10px 15px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.85rem;
            font-weight: 500;
            transition: all 0.3s ease;
            margin-right: 6px;
            margin-bottom: 6px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0,0,0,0.2);
        }
        
        .btn-verify {
            background: #3498db;
            color: white;
        }
        
        .btn-unverify {
            background: #e74c3c;
            color: white;
        }
        
        .btn-ban {
            background: #c0392b;
            color: white;
        }
        
        .btn-unban {
            background: #27ae60;
            color: white;
        }
        
        .btn-mute {
            background: #f39c12;
            color: white;
        }
        
        .btn-unmute {
            background: #3498db;
            color: white;
        }
        
        .btn-delete {
            background: #e74c3c;
            color: white;
        }
        
        .btn-assign {
            background: #2ecc71;
            color: white;
        }
        
        .role-form {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .role-select {
            padding: 8px 12px;
            border: 2px solid #ddd;
            border-radius: 6px;
            font-size: 0.85rem;
        }
        
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 25px;
            margin-bottom: 35px;
        }
        
        .stat-card {
            background: white;
            padding: 30px;
            border-radius: 18px;
            text-align: center;
            box-shadow: 0 8px 25px rgba(0,0,0,0.08);
            border-left: 6px solid #667eea;
            transition: transform 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-number {
            font-size: 2.8rem;
            font-weight: 700;
            color: #667eea;
            margin-bottom: 8px;
        }
        
        .stat-label {
            color: #666;
            font-size: 0.95rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .session-info {
            background: rgba(52, 152, 219, 0.1);
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            border-left: 4px solid #3498db;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .session-info .info {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #2980b9;
        }
        
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 20px;
                text-align: center;
            }
            
            .header-info {
                flex-direction: column;
                gap: 15px;
            }
            
            .admin-profile {
                flex-direction: column;
                text-align: center;
            }
            
            .container {
                padding: 0 15px;
            }
            
            .role-form {
                flex-direction: column;
                align-items: stretch;
            }
            
            .role-select {
                width: 100%;
            }
            
            .session-info {
                flex-direction: column;
                gap: 10px;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <h1>
                <i class="fas fa-shield-alt"></i>
                Panel de Administración ZyNapps
            </h1>
            <div class="header-info">
                <div class="admin-profile">
                    <div class="admin-avatar">
                        <i class="fas fa-user-shield"></i>
                    </div>
                    <div class="admin-details">
                        <h4><?= htmlspecialchars($admin_name) ?></h4>
                        <p>@<?= htmlspecialchars($admin_username) ?></p>
                    </div>
                </div>
                <a href="?logout=1" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i> Cerrar Sesión
                </a>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="session-info">
            <div class="info">
                <i class="fas fa-clock"></i>
                <span>Sesión iniciada: <?= $login_time ?></span>
            </div>
            <div class="info">
                <i class="fas fa-envelope"></i>
                <span><?= htmlspecialchars($admin_email) ?></span>
            </div>
        </div>

        <?php if ($success_message): ?>
            <div class="success-message">
                <i class="fas fa-check-circle"></i>
                <?= htmlspecialchars($success_message) ?>
            </div>
        <?php endif; ?>

        <div class="stats">
            <div class="stat-card">
                <div class="stat-number"><?= $total_users ?></div>
                <div class="stat-label">Total Usuarios</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $verified_users ?></div>
                <div class="stat-label">Verificados</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $admin_users ?></div>
                <div class="stat-label">Administradores</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $mod_users ?></div>
                <div class="stat-label">Moderadores</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $total_posts ?></div>
                <div class="stat-label">Publicaciones</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $banned_users ?></div>
                <div class="stat-label">Baneados</div>
            </div>
        </div>

        <div class="section">
            <div class="section-header">
                <i class="fas fa-users"></i>
                Gestión de Usuarios (<?= $total_users ?> usuarios)
            </div>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Usuario</th>
                            <th>Nombre Completo</th>
                            <th>Email</th>
                            <th>Rol</th>
                            <th>Estado</th>
                            <th>Última Actividad</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): 
                            $isVerified = !empty($u['verified']);
                            $isBanned = !empty($u['banned']);
                            $isMuted = !empty($u['muted']);
                            $role = $u['role'] ?? 'user';
                            $lastActive = $u['last_active'] ?? 0;
                        ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($u['username'] ?? '') ?></strong>
                                <?php if ($isVerified): ?>
                                    <i class="fas fa-check-circle" style="color: #3498db; margin-left: 5px;"></i>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($u['full_name'] ?? '') ?></td>
                            <td><?= htmlspecialchars($u['email'] ?? '') ?></td>
                            <td>
                                <span class="badge badge-<?= $role ?>">
                                    <?= strtoupper($role) ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($isBanned): ?>
                                    <span class="badge badge-banned">BANEADO</span>
                                <?php elseif ($isMuted): ?>
                                    <span class="badge badge-muted">MUTEADO</span>
                                <?php else: ?>
                                    <span class="badge" style="background: #d4edda; color: #155724;">ACTIVO</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($lastActive): ?>
                                    <?= date('d/m/Y H:i', $lastActive) ?>
                                <?php else: ?>
                                    <span style="color: #999;">Nunca</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="display: flex; flex-wrap: wrap; gap: 6px;">
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="target_user" value="<?= htmlspecialchars($u['username'] ?? '') ?>">
                                        <?php if (!$isVerified): ?>
                                            <input type="hidden" name="action_type" value="verify_user">
                                            <button class="btn btn-verify">
                                                <i class="fas fa-check"></i> Verificar
                                            </button>
                                        <?php else: ?>
                                            <input type="hidden" name="action_type" value="unverify_user">
                                            <button class="btn btn-unverify">
                                                <i class="fas fa-times"></i> Quitar Verif
                                            </button>
                                        <?php endif; ?>
                                    </form>

                                    <?php if (!$isBanned): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="target_user" value="<?= htmlspecialchars($u['username'] ?? '') ?>">
                                        <input type="hidden" name="action_type" value="ban_user">
                                        <button class="btn btn-ban" onclick="return confirm('¿Estás seguro de banear a este usuario?')">
                                            <i class="fas fa-ban"></i> Banear
                                        </button>
                                    </form>
                                    <?php else: ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="target_user" value="<?= htmlspecialchars($u['username'] ?? '') ?>">
                                        <input type="hidden" name="action_type" value="unban_user">
                                        <button class="btn btn-unban">
                                            <i class="fas fa-check"></i> Desbanear
                                        </button>
                                    </form>
                                    <?php endif; ?>

                                    <?php if (!$isMuted): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="target_user" value="<?= htmlspecialchars($u['username'] ?? '') ?>">
                                        <input type="hidden" name="action_type" value="mute_user">
                                        <button class="btn btn-mute">
                                            <i class="fas fa-volume-mute"></i> Mutear
                                        </button>
                                    </form>
                                    <?php else: ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="target_user" value="<?= htmlspecialchars($u['username'] ?? '') ?>">
                                        <input type="hidden" name="action_type" value="unmute_user">
                                        <button class="btn btn-unmute">
                                            <i class="fas fa-volume-up"></i> Desmutear
                                        </button>
                                    </form>
                                    <?php endif; ?>

                                    <form method="POST" class="role-form">
                                        <input type="hidden" name="target_user" value="<?= htmlspecialchars($u['username'] ?? '') ?>">
                                        <select name="role" class="role-select">
                                            <option value="user" <?= ($role=='user')?'selected':'' ?>>User</option>
                                            <option value="mod" <?= ($role=='mod')?'selected':'' ?>>Moderador</option>
                                            <option value="admin" <?= ($role=='admin')?'selected':'' ?>>Admin</option>
                                        </select>
                                        <input type="hidden" name="action_type" value="assign_role">
                                        <button class="btn btn-assign">
                                            <i class="fas fa-user-cog"></i> Actualizar
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="section">
            <div class="section-header">
                <i class="fas fa-newspaper"></i>
                Gestión de Publicaciones (<?= $total_posts ?> publicaciones)
            </div>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Usuario</th>
                            <th>Descripción</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($posts as $p): ?>
                        <tr>
                            <td><code><?= htmlspecialchars($p['id'] ?? '') ?></code></td>
                            <td>
                                <strong><?= htmlspecialchars($p['username'] ?? $p['user'] ?? '') ?></strong>
                            </td>
                            <td>
                                <?= htmlspecialchars(substr($p['description'] ?? '', 0, 100)) ?>
                                <?= strlen($p['description'] ?? '') > 100 ? '...' : '' ?>
                            </td>
                            <td>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="target_post" value="<?= htmlspecialchars($p['id'] ?? '') ?>">
                                    <input type="hidden" name="action_type" value="delete_post">
                                    <button class="btn btn-delete" onclick="return confirm('¿Estás seguro de eliminar esta publicación?')">
                                        <i class="fas fa-trash"></i> Eliminar
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        // Auto-hide success message after 5 seconds
        setTimeout(function() {
            const successMsg = document.querySelector('.success-message');
            if (successMsg) {
                successMsg.style.transition = 'opacity 0.5s ease';
                successMsg.style.opacity = '0';
                setTimeout(() => successMsg.remove(), 500);
            }
        }, 5000);
    </script>
</body>
</html>