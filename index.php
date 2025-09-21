<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require_once "includes/functions.php";

// SOLO redirigir si NO está logueado
if (!currentUser()) {
    header("Location: login.php");
    exit;
}

$currentUsername = currentUser();

// Obtener configuraciones del usuario para el modo oscuro
$currentUserProfile = getUserProfile($currentUsername);
$userPreferences = $currentUserProfile['preferences'] ?? [];
$darkModeEnabled = ($userPreferences['dark_mode'] ?? 0) == 1;

// Función para obtener avatar corregida
function getProfilePic($userData) {
    if (!$userData || !is_array($userData)) {
        return "https://via.placeholder.com/50/7b5cf0/ffffff?text=U";
    }
    
    $avatarFile = $userData['profile_pic'] ?? 'default.png';
    $avatarPath = "assets/uploads/profiles/" . $avatarFile;

    // Verificar si el archivo existe
    if (file_exists($avatarPath)) {
        return $avatarPath;
    }

    // Si el archivo no existe, usar default.png
    $defaultPath = "assets/uploads/profiles/default.png";
    if (file_exists($defaultPath)) {
        return $defaultPath;
    }

    // Si no existe default.png, usar placeholder con inicial
    $initial = substr($userData['full_name'] ?? ($userData['username'] ?? 'U'), 0, 1);
    return "https://via.placeholder.com/50/7b5cf0/ffffff?text=" . urlencode(strtoupper($initial));
}

// Función mejorada para obtener nombre de usuario con fallback
function getUserDisplayName($userData) {
    if (!$userData || !is_array($userData)) {
        return 'Usuario Desconocido';
    }
    
    // Prioridad: full_name -> username -> 'Usuario'
    return $userData['full_name'] ?? $userData['username'] ?? 'Usuario';
}

// Función para truncar nombres largos pero mantener legibilidad
function getTruncatedDisplayName($userData, $maxLength = 20) {
    $displayName = getUserDisplayName($userData);
    
    if (strlen($displayName) > $maxLength) {
        return substr($displayName, 0, $maxLength - 3) . '...';
    }
    
    return $displayName;
}

// Función para verificar si un usuario está verificado - CORREGIDA
function isUserVerified($userData) {
    if (!$userData || !is_array($userData)) {
        return false;
    }
    
    // Verificar de múltiples maneras posibles
    if (isset($userData['verified'])) {
        return $userData['verified'] == 1 || $userData['verified'] === true || $userData['verified'] === '1';
    }
    
    // También verificar si existe el campo 'is_verified' (compatibilidad con diferentes formatos)
    if (isset($userData['is_verified'])) {
        return $userData['is_verified'] == 1 || $userData['is_verified'] === true || $userData['is_verified'] === '1';
    }
    
    return false;
}

$posts    = readJSON('data/posts.json') ?? [];
$likes    = readJSON('data/likes.json') ?? [];
$comments = readJSON('data/comments.json') ?? [];
$friends  = readJSON('data/friends.json') ?? [];

$userFriends = getUserFriends($currentUsername) ?? [];
$filteredPosts = array_filter($posts, function ($post) use ($currentUsername, $userFriends) {
    $user = $post['user'] ?? '';
    return ($user === $currentUsername) || in_array($user, $userFriends) || (($post['privacy'] ?? 'public') === 'public');
});
usort($filteredPosts, fn($a, $b) => (int)($b['timestamp'] ?? 0) - (int)($a['timestamp'] ?? 0));

// Función para renderizar badge de verificación - COMPATIBLE CON TODAS LAS VERSIONES
function renderVerifiedBadge(string $size = 'sm'): string {
    // Usar switch en lugar de match para compatibilidad con PHP < 8.0
    switch($size) {
        case 'lg':
            $sizeClass = 'verified-badge verified-badge--lg';
            break;
        case 'sm':
            $sizeClass = 'verified-badge verified-badge--sm';
            break;
        default:
            $sizeClass = 'verified-badge';
            break;
    }
    
    return '
    <span class="'.$sizeClass.'" title="Usuario verificado" aria-label="Usuario verificado">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" role="img" aria-hidden="true">
            <circle cx="12" cy="12" r="12" fill="#1DA1F2"/>
            <path fill="#ffffff" d="M10.5 16.5l-3-3 1.5-1.5 1.5 1.5 4.5-4.5 1.5 1.5-6 6z"/>
        </svg>
    </span>';
}

// Función para obtener todos los usuarios desde users.json
function getAllUsersFromJSON() {
    $usersFile = __DIR__ . '/data/users.json';
    
    // Verificar si el archivo existe
    if (!file_exists($usersFile)) {
        error_log("Archivo de usuarios no encontrado: " . $usersFile);
        return [];
    }
    
    // Leer el contenido del archivo
    $usersContent = file_get_contents($usersFile);
    if ($usersContent === false) {
        error_log("Error al leer el archivo de usuarios: " . $usersFile);
        return [];
    }
    
    // Decodificar el JSON
    $users = json_decode($usersContent, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("Error al decodificar JSON de usuarios: " . json_last_error_msg());
        return [];
    }
    
    return $users ?: [];
}

// Función para obtener sugerencias aleatorias (excluyendo amigos y usuario actual)
function getRandomFriendSuggestions($currentUsername, $allUsers, $userFriends) {
    // Filtrar usuarios que no son el usuario actual ni amigos
    $availableUsers = array_filter($allUsers, function($user) use ($currentUsername, $userFriends) {
        return $user['username'] !== $currentUsername && !in_array($user['username'], $userFriends);
    });
    
    // Mezclar aleatoriamente
    shuffle($availableUsers);
    
    return $availableUsers;
}

// Manejar eliminación de sugerencias
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'remove_suggestion' && isset($_POST['user'])) {
        $usernameToRemove = htmlspecialchars($_POST['user']);
        
        // Guardar las sugerencias eliminadas en la sesión
        if (!isset($_SESSION['removed_suggestions'])) {
            $_SESSION['removed_suggestions'] = [];
        }
        $_SESSION['removed_suggestions'][] = $usernameToRemove;
        
        // Redirigir para evitar reenvío del formulario
        header("Location: index.php");
        exit;
    }
    
    // También manejar envío de solicitudes de amistad
    if ($_POST['action'] === 'send_request' && isset($_POST['user'])) {
        $targetUser = htmlspecialchars($_POST['user']);
        
        // Verificar si ya existe una solicitud pendiente
        $friendRequests = readJSON('data/friend_requests.json') ?? [];
        $requestExists = false;
        
        foreach ($friendRequests as $request) {
            if ($request['from'] === $currentUsername && $request['to'] === $targetUser && $request['status'] === 'pending') {
                $requestExists = true;
                break;
            }
        }
        
        if (!$requestExists) {
            // Crear nueva solicitud
            $newRequest = [
                'id' => uniqid(),
                'from' => $currentUsername,
                'to' => $targetUser,
                'status' => 'pending',
                'timestamp' => time()
            ];
            
            $friendRequests[] = $newRequest;
            writeJSON('data/friend_requests.json', $friendRequests);
            
            // Guardar mensaje de éxito en sesión
            $_SESSION['success_message'] = "Solicitud de amistad enviada a $targetUser";
        } else {
            $_SESSION['info_message'] = "Ya has enviado una solicitud a $targetUser";
        }
        
        header("Location: index.php");
        exit;
    }
}

// Obtener todos los usuarios desde el archivo JSON
$allUsers = getAllUsersFromJSON();

// Obtener todas las sugerencias aleatorias
$allRandomSuggestions = getRandomFriendSuggestions($currentUsername, $allUsers, $userFriends);

// Filtrar sugerencias que han sido eliminadas
if (isset($_SESSION['removed_suggestions'])) {
    $allRandomSuggestions = array_filter($allRandomSuggestions, function ($suggestion) {
        return !in_array($suggestion['username'], $_SESSION['removed_suggestions']);
    });
}

// Obtener solicitudes de amistad pendientes para verificar estado
$friendRequests = readJSON('data/friend_requests.json') ?? [];
$pendingRequests = [];
foreach ($friendRequests as $request) {
    if ($request['from'] === $currentUsername && $request['status'] === 'pending') {
        $pendingRequests[] = $request['to'];
    }
}

// Configurar paginación de sugerencias
$suggestionsPerPage = 5; // Número de sugerencias a mostrar por página
$currentSuggestionPage = isset($_GET['suggestion_page']) ? (int)$_GET['suggestion_page'] : 1;
$totalSuggestions = count($allRandomSuggestions);
$totalSuggestionPages = ceil($totalSuggestions / $suggestionsPerPage);

// Obtener solo las sugerencias para la página actual
$startIndex = ($currentSuggestionPage - 1) * $suggestionsPerPage;
$filteredSuggestions = array_slice($allRandomSuggestions, $startIndex, $suggestionsPerPage);
?>
<!DOCTYPE html>
<html lang="es" class="<?= $darkModeEnabled ? 'dark-mode' : '' ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ZyNapps</title>
<link rel="stylesheet" href="assets/css/style.css">
<style>
/* Variables CSS para modo claro y oscuro */
:root {
    --primary-color: #3498db;
    --primary-hover: #2980b9;
    --secondary-color: #95a5a6;
    --danger-color: #e74c3c;
    --success-color: #2ecc71;
    --warning-color: #f39c12;
    --dark-color: #34495e;
    --light-color: #ecf0f1;
    --text-color: #2c3e50;
    --text-muted: #7f8c8d;
    --bg-color: #f8fafc;
    --card-bg: #ffffff;
    --border-color: #e1e8ed;
    --shadow-color: rgba(0, 0, 0, 0.1);
    --input-bg: #ffffff;
    --button-text: #ffffff;
    --link-color: #3498db;
    --hover-bg: #f8f9fa;
    --form-bg: #ffffff;
    --select-bg: #ffffff;
    --option-bg: #ffffff;
    --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.dark-mode {
    --bg-color: #1a1a1a;
    --card-bg: #2d3748;
    --text-color: #f7fafc;
    --text-muted: #a0aec0;
    --border-color: #4a5568;
    --shadow-color: rgba(0, 0, 0, 0.3);
    --input-bg: #4a5568;
    --button-text: #ffffff;
    --link-color: #63b3ed;
    --hover-bg: #4a5568;
    --form-bg: #2d3748;
    --select-bg: #4a5568;
    --option-bg: #2d3748;
    --success-color: #68d391;
    --danger-color: #fc8181;
    --warning-color: #fbb740;
}

/* Reset universal para evitar colores hardcodeados */
* {
    box-sizing: border-box;
}

/* Aplicar variables a elementos base */
body {
    background-color: var(--bg-color) !important;
    color: var(--text-color) !important;
    transition: var(--transition);
    margin: 0;
    padding: 0;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

/* Asegurar que todos los elementos hereden colores apropiados */
div, span, p, h1, h2, h3, h4, h5, h6, article, section, aside, nav, main {
    color: inherit;
}

/* BADGE DE VERIFICACIÓN - CORREGIDO Y MEJORADO */
.verified-badge {
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    width: 18px !important;
    height: 18px !important;
    margin-left: 6px !important;
    margin-right: 0 !important;
    vertical-align: middle !important;
    flex-shrink: 0 !important;
    transition: all 0.3s ease !important;
    position: relative !important;
}

.verified-badge svg {
    width: 100% !important;
    height: 100% !important;
    display: block !important;
    border-radius: 50% !important;
}

/* Tamaños específicos */
.verified-badge--sm {
    width: 16px !important;
    height: 16px !important;
    margin-left: 4px !important;
}

.verified-badge--lg {
    width: 22px !important;
    height: 22px !important;
    margin-left: 8px !important;
}

/* Colores del badge - FORZADOS */
.verified-badge svg circle {
    fill: #1DA1F2 !important; /* Azul de verificación */
}

.verified-badge svg path {
    fill: #ffffff !important; /* Check mark blanco */
}

/* Efecto hover */
.username-link:hover .verified-badge,
.profile-link:hover .verified-badge {
    transform: scale(1.1) !important;
}

/* Asegurar visibilidad en ambos modos */
.dark-mode .verified-badge svg circle,
html:not(.dark-mode) .verified-badge svg circle {
    fill: #1DA1F2 !important;
}

.dark-mode .verified-badge svg path,
html:not(.dark-mode) .verified-badge svg path {
    fill: #ffffff !important;
}

/* Contenedor principal mejorado */
.container {
    display: flex;
    gap: 20px;
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 15px;
}

/* Mejorar la visualización del feed principal */
.main-content {
    flex: 1;
    min-width: 0;
}

.feed {
    margin-top: 20px;
}

/* Create Post Form - COMPLETAMENTE CORREGIDO */
.create-post {
    background: var(--card-bg) !important;
    border: 1px solid var(--border-color) !important;
    border-radius: 8px;
    padding: 16px;
    margin-bottom: 20px;
    box-shadow: 0 2px 4px var(--shadow-color);
    transition: var(--transition);
}

.create-post h3 {
    color: var(--text-color) !important;
    margin-bottom: 12px;
}

.create-post form {
    background: transparent !important;
}

.create-post textarea {
    width: 100% !important;
    background: var(--input-bg) !important;
    color: var(--text-color) !important;
    border: 1px solid var(--border-color) !important;
    border-radius: 6px;
    padding: 12px;
    margin-bottom: 10px;
    resize: vertical;
    transition: var(--transition);
    font-family: inherit;
}

.create-post textarea:focus {
    border-color: var(--primary-color) !important;
    outline: none;
    box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.2);
}

.create-post textarea::placeholder {
    color: var(--text-muted) !important;
    opacity: 0.8;
}

.create-post input[type="file"] {
    background: var(--input-bg) !important;
    color: var(--text-color) !important;
    border: 1px solid var(--border-color) !important;
    border-radius: 6px;
    padding: 8px;
    margin-bottom: 10px;
    width: 100%;
}

.create-post select {
    background: var(--select-bg) !important;
    color: var(--text-color) !important;
    border: 1px solid var(--border-color) !important;
    border-radius: 6px;
    padding: 8px;
    margin-bottom: 10px;
    width: 100%;
}

.create-post select option {
    background: var(--option-bg) !important;
    color: var(--text-color) !important;
}

.create-post button {
    background: var(--primary-color) !important;
    color: var(--button-text) !important;
    border: none !important;
    padding: 10px 20px;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 600;
    transition: var(--transition);
    width: 100%;
    margin-top: 8px;
}

.create-post button:hover {
    background: var(--primary-hover) !important;
}

/* Posts del Feed - COMPLETAMENTE CORREGIDO */
.post {
    background: var(--card-bg) !important;
    border: 1px solid var(--border-color) !important;
    border-radius: 8px;
    padding: 12px;
    margin-bottom: 12px;
    transition: var(--transition);
    box-shadow: 0 2px 4px var(--shadow-color);
}

.post:hover {
    box-shadow: 0 4px 8px var(--shadow-color);
}

/* CORRECCIÓN ESPECÍFICA PARA POST-HEADER - MÁS ESPACIO PARA NOMBRES */

/* Post header con más espacio para contenido */
.post-header {
    display: flex !important;
    justify-content: space-between !important;
    align-items: center !important;
    gap: 20px !important; /* Más espacio entre elementos */
    margin-bottom: 12px !important;
    padding-bottom: 8px !important;
}

/* User info con más espacio para nombres largos */
.user-info {
    display: flex !important;
    align-items: center !important;
    gap: 12px !important;
    flex: 1 !important;
    min-width: 0 !important;
    max-width: calc(100% - 120px) !important; /* Reservar espacio para timestamp */
}

/* Username link en post-header - MÁS ESPACIO */
.post-header .username-link {
    font-weight: 700 !important;
    display: inline-flex !important;
    align-items: center !important;
    gap: 8px !important;
    text-decoration: none !important;
    transition: var(--transition) !important;
    max-width: 100% !important;
    overflow: visible !important; /* NO cortar */
    white-space: nowrap !important;
}

/* Username en post-header - NOMBRE COMPLETO VISIBLE */
.post-header .username {
    font-weight: 700 !important;
    display: inline-flex !important;
    align-items: center !important;
    gap: 8px !important;
    max-width: 100% !important;
    overflow: visible !important; /* NO cortar */
    white-space: nowrap !important;
    line-height: 1.4 !important;
}

/* Username text en post-header - SIN TRUNCAR */
.post-header .username-text {
    overflow: visible !important; /* NO cortar el nombre */
    text-overflow: unset !important; /* NO usar ellipsis */
    white-space: nowrap !important;
    flex-shrink: 0 !important; /* NO reducir el nombre */
    max-width: none !important; /* SIN límite de ancho */
}

/* Badge en post-header - SIEMPRE VISIBLE */
.post-header .verified-badge {
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    width: 22px !important; /* Más grande en posts */
    height: 22px !important;
    margin-left: 8px !important;
    margin-right: 0 !important;
    vertical-align: middle !important;
    flex-shrink: 0 !important; /* NUNCA se reduce */
    transition: all 0.3s ease !important;
    position: relative !important;
}

.post-header .verified-badge--lg {
    width: 24px !important; /* Aún más grande para posts principales */
    height: 24px !important;
    margin-left: 10px !important;
}

/* Timestamp más compacto para dar espacio al nombre */
.post-header .timestamp {
    color: var(--text-muted) !important;
    font-size: 14px !important;
    white-space: nowrap !important;
    flex-shrink: 0 !important;
    margin-left: auto !important;
    text-align: right !important;
    min-width: 80px !important; /* Ancho mínimo para timestamps */
}

/* Avatar en post-header */
.post-header .profile-pic-small {
    width: 45px !important; /* Ligeramente más grande */
    height: 45px !important;
    border-radius: 50% !important;
    object-fit: cover !important;
    flex-shrink: 0 !important;
}

/* CORRECCIÓN PARA NOMBRES LARGOS Y BADGE VISIBLE - SIDEBAR */

/* Username container - más espacio y mejor distribución */
.username {
    font-weight: 700 !important;
    display: inline-flex !important;
    align-items: center !important;
    gap: 6px !important;
    max-width: 100% !important;
    overflow: hidden !important;
    line-height: 1.2 !important;
}

.username-link {
    font-weight: 700 !important;
    display: inline-flex !important;
    align-items: center !important;
    gap: 6px !important;
    text-decoration: none !important;
    transition: var(--transition) !important;
    max-width: 100% !important;
    overflow: hidden !important;
}

/* CORRECCIÓN PRINCIPAL - Truncar nombres largos pero mantener badge visible */
.username-text {
    max-width: calc(100% - 30px) !important; /* Reservar espacio para el badge */
    overflow: hidden !important;
    text-overflow: ellipsis !important;
    white-space: nowrap !important;
    flex-shrink: 1 !important;
}

/* COLORES ESPECÍFICOS PARA POST-HEADER */

/* Modo claro - post-header */
html:not(.dark-mode) .post-header .username-link,
html:not(.dark-mode) .post-header .username-link span,
html:not(.dark-mode) .post-header .username,
html:not(.dark-mode) .post-header .username span,
html:not(.dark-mode) .post-header .username-text {
    color: #000000 !important; /* NEGRO PURO */
}

html:not(.dark-mode) .post-header .username-link:hover,
html:not(.dark-mode) .post-header .username-link:hover .username-text {
    color: #3498db !important; /* Azul al hacer hover */
}

/* Modo oscuro - post-header */
.dark-mode .post-header .username-link,
.dark-mode .post-header .username-link span,
.dark-mode .post-header .username,
.dark-mode .post-header .username span,
.dark-mode .post-header .username-text {
    color: #ffffff !important; /* BLANCO PURO */
}

.dark-mode .post-header .username-link:hover,
.dark-mode .post-header .username-link:hover .username-text {
    color: #63b3ed !important; /* Azul claro al hacer hover */
}

/* Badge colores en post-header */
.post-header .verified-badge svg circle {
    fill: #1DA1F2 !important;
}

.post-header .verified-badge svg path {
    fill: #ffffff !important;
}

/* Efecto hover para badge en post-header */
.post-header .username-link:hover .verified-badge {
    transform: scale(1.15) !important; /* Efecto más pronunciado */
}

/* LAYOUT FLEXIBLE MEJORADO */
.post-header .user-info a:first-child {
    flex-shrink: 0 !important; /* Avatar no se reduce */
}

.post-header .user-info a:last-child {
    flex: 1 !important; /* Nombre toma el espacio disponible */
    min-width: 0 !important;
    overflow: visible !important;
}

/* MODO CLARO - USERNAME EN NEGRO (Para otros elementos) */
html:not(.dark-mode) .username-link,
html:not(.dark-mode) .username-link span,
html:not(.dark-mode) .username,
html:not(.dark-mode) .username span,
html:not(.dark-mode) .username-text {
    color: #000000 !important; /* NEGRO PURO */
}

html:not(.dark-mode) .username-link:hover,
html:not(.dark-mode) .username-link:hover .username-text {
    color: #3498db !important; /* Azul al hacer hover */
}

/* MODO OSCURO - USERNAME EN BLANCO (Para otros elementos) */
.dark-mode .username-link,
.dark-mode .username-link span,
.dark-mode .username,
.dark-mode .username span,
.dark-mode .username-text {
    color: #ffffff !important; /* BLANCO PURO */
}

.dark-mode .username-link:hover,
.dark-mode .username-link:hover .username-text {
    color: #63b3ed !important; /* Azul claro al hacer hover */
}

/* CORRECCIÓN PARA COMENTARIOS TAMBIÉN */
html:not(.dark-mode) .comment .profile-link,
html:not(.dark-mode) .comment .profile-link strong,
html:not(.dark-mode) .comment strong,
html:not(.dark-mode) .comment .username-text {
    color: #000000 !important; /* NEGRO en modo claro */
}

.dark-mode .comment .profile-link,
.dark-mode .comment .profile-link strong,
.dark-mode .comment strong,
.dark-mode .comment .username-text {
    color: #ffffff !important; /* BLANCO en modo oscuro */
}

/* CORRECCIÓN PARA SUGERENCIAS DE AMIGOS */
html:not(.dark-mode) .friend-suggestion-username,
html:not(.dark-mode) .friend-suggestion-username a,
html:not(.dark-mode) .friend-suggestion-username .username-text {
    color: #000000 !important; /* NEGRO en modo claro */
}

.dark-mode .friend-suggestion-username,
.dark-mode .friend-suggestion-username a,
.dark-mode .friend-suggestion-username .username-text {
    color: #ffffff !important; /* BLANCO en modo oscuro */
}

/* CORRECCIÓN PARA AMIGOS EN LÍNEA */
html:not(.dark-mode) .friend-item a,
html:not(.dark-mode) .friend-item .username-text {
    color: #000000 !important; /* NEGRO en modo claro */
}

.dark-mode .friend-item a,
.dark-mode .friend-item .username-text {
    color: #ffffff !important; /* BLANCO en modo oscuro */
}

.post-description {
    margin: 8px 0;
    color: var(--text-color) !important;
}

.post-media img,
.post-media video {
    max-width: 100%;
    border-radius: 8px;
    border: 1px solid var(--border-color);
}

.post-actions button {
    margin-right: 6px;
    border: none !important;
    background: var(--hover-bg) !important;
    color: var(--text-color) !important;
    padding: 6px 10px;
    border-radius: 6px;
    cursor: pointer;
    transition: var(--transition);
    border: 1px solid var(--border-color) !important;
}

.post-actions button:hover {
    background: var(--primary-color) !important;
    color: var(--button-text) !important;
}

.like-btn.liked {
    background: var(--danger-color) !important;
    color: var(--button-text) !important;
}

/* Timestamps */
.timestamp,
.comment-time {
    color: var(--text-muted) !important;
}

/* Comments Section - COMPLETAMENTE CORREGIDO */
.comments-section {
    border-top: 1px solid var(--border-color) !important;
    margin-top: 12px;
    padding-top: 12px;
    background: transparent !important;
}

.comment {
    background: var(--hover-bg) !important;
    padding: 8px 12px;
    border-radius: 6px;
    margin: 6px 0;
    border: 1px solid var(--border-color) !important;
    color: var(--text-color) !important;
}

.comments-list {
    background: transparent !important;
}

.comment-form {
    display: flex;
    gap: 8px;
    margin-top: 10px;
    background: transparent !important;
}

.comment-form input {
    flex: 1;
    background: var(--input-bg) !important;
    color: var(--text-color) !important;
    border: 1px solid var(--border-color) !important;
    border-radius: 6px;
    padding: 8px 12px;
}

.comment-form input:focus {
    border-color: var(--primary-color) !important;
    outline: none;
}

.comment-form input::placeholder {
    color: var(--text-muted) !important;
    opacity: 0.8;
}

.comment-form button {
    background: var(--primary-color) !important;
    color: var(--button-text) !important;
    border: none !important;
    padding: 8px 16px;
    border-radius: 6px;
    cursor: pointer;
    transition: var(--transition);
}

.comment-form button:hover {
    background: var(--primary-hover) !important;
}

/* CORRECCIÓN PARA COMENTARIOS */
.comment .profile-link strong {
    display: inline-flex !important;
    align-items: center !important;
    gap: 6px !important;
    max-width: 200px !important;
    overflow: hidden !important;
}

.comment .username-text {
    max-width: calc(100% - 25px) !important;
    overflow: hidden !important;
    text-overflow: ellipsis !important;
    white-space: nowrap !important;
}

/* Enlaces de perfil */
.user-info a {
    text-decoration: none;
    color: inherit;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: var(--transition);
}

.user-info a:hover {
    opacity: 0.8;
}

.profile-pic-small {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    object-fit: cover;
}

.profile-link {
    text-decoration: none;
    color: inherit;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: var(--transition);
}

.profile-link:hover {
    opacity: 0.8;
    color: var(--primary-color) !important;
}

/* Sidebar y layout - COMPLETAMENTE CORREGIDO */
aside.sidebar {
    width: 250px;
    padding: 1rem;
    background: var(--card-bg) !important;
    border-radius: 12px;
    box-shadow: 0 4px 6px var(--shadow-color);
    border: 1px solid var(--border-color);
    transition: var(--transition);
    margin-top: 10px;
}

.friend-item,
.friend-suggestion {
    display: flex;
    align-items: center;
    gap: .5rem;
    margin-bottom: .5rem;
    cursor: pointer;
}

.friend-item {
    padding: 8px 12px;
    border-radius: 6px;
    background: var(--card-bg) !important;
    border: 1px solid var(--border-color);
    transition: var(--transition);
}

.friend-item:hover {
    background: var(--hover-bg) !important;
    box-shadow: 0 1px 3px var(--shadow-color);
}

.online-indicator {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: var(--success-color);
    margin-left: auto;
}

/* Estilos mejorados para sugerencias de amigos */
.friend-suggestion {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px;
    border-radius: 10px;
    background: var(--card-bg) !important;
    border: 1px solid var(--border-color);
    margin-bottom: 10px;
    transition: var(--transition);
    box-shadow: 0 2px 6px var(--shadow-color);
}

.friend-suggestion:hover {
    background: var(--hover-bg) !important;
    box-shadow: 0 4px 12px var(--shadow-color);
    transform: translateY(-1px);
}

.friend-suggestion-content {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 4px;
    overflow: hidden;
}

/* CORRECCIÓN PARA SUGERENCIAS DE AMIGOS - nombres muy largos */
.friend-suggestion-username {
    font-weight: 600 !important;
    font-size: 14px !important;
    display: inline-flex !important;
    align-items: center !important;
    gap: 6px !important;
    white-space: nowrap !important;
    overflow: hidden !important;
    max-width: 150px !important; /* Más espacio */
}

.friend-suggestion-username .username-text {
    max-width: calc(100% - 25px) !important;
    overflow: hidden !important;
    text-overflow: ellipsis !important;
    white-space: nowrap !important;
}

/* Títulos de sección */
.sidebar h4 {
    margin: 0 0 15px 0;
    color: var(--text-color) !important;
    font-size: 17px;
    font-weight: 700;
    padding-bottom: 10px;
    border-bottom: 2px solid var(--border-color);
}

/* Amigos en línea mejorados */
.online-friends .friend-item {
    padding: 10px 12px;
    margin-bottom: 8px;
    border-radius: 8px;
    background: var(--card-bg) !important;
    border: 1px solid var(--border-color);
}

.online-friends .friend-item:hover {
    background: var(--hover-bg) !important;
    box-shadow: 0 2px 4px var(--shadow-color);
}

/* CORRECCIÓN PARA AMIGOS EN LÍNEA */
.friend-item a {
    display: flex !important;
    align-items: center !important;
    gap: 6px !important;
    max-width: 170px !important; /* Más espacio */
    overflow: hidden !important;
    text-overflow: ellipsis !important;
    white-space: nowrap !important;
    text-decoration: none !important;
    color: inherit !important;
}

.friend-item .username-text {
    max-width: calc(100% - 25px) !important;
    overflow: hidden !important;
    text-overflow: ellipsis !important;
    white-space: nowrap !important;
}

.friends-suggestions,
.online-friends {
    margin-bottom: 25px;
}

/* Estilos para botones de sugerencias */
.suggestion-buttons {
    display: flex;
    gap: 8px;
    margin-top: 4px;
}

.btn-add {
    background: linear-gradient(135deg, #4e7dfc, #2a66ff) !important;
    color: white !important;
    border: none !important;
    padding: 6px 12px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 12px;
    font-weight: 600;
    transition: var(--transition);
    white-space: nowrap;
}

.btn-add:hover {
    background: linear-gradient(135deg, #3b6de0, #1a54e8) !important;
    transform: translateY(-1px);
}

.btn-remove {
    background: #808080 !important;
    color: white !important;
    border: none !important;
    padding: 6px 12px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 12px;
    font-weight: 600;
    transition: var(--transition);
    white-space: nowrap;
}

.btn-remove:hover {
    background: #666 !important;
    transform: translateY(-1px);
}

.btn-pending {
    background: var(--hover-bg) !important;
    color: var(--text-muted) !important;
    border: 1px solid var(--border-color) !important;
    padding: 8px 16px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
    white-space: nowrap;
    cursor: default;
    width: 100%;
    text-align: center;
}

/* Estilos para el botón "Mostrar más" */
.show-more-btn {
    display: block;
    width: 100%;
    padding: 10px;
    background: var(--hover-bg) !important;
    border: 1px solid var(--border-color) !important;
    border-radius: 6px;
    color: var(--primary-color) !important;
    font-weight: 600;
    text-align: center;
    cursor: pointer;
    margin-top: 10px;
    transition: var(--transition);
    text-decoration: none;
}

.show-more-btn:hover {
    background: var(--primary-color) !important;
    color: var(--button-text) !important;
}

/* Menú móvil inferior */
.mobile-nav {
    display: none;
    position: fixed;
    bottom: 0;
    left: 0;
    width: 100%;
    background: var(--card-bg) !important;
    border-top: 1px solid var(--border-color);
    justify-content: space-around;
    padding: .5rem 0;
    z-index: 999;
    transition: var(--transition);
}

.mobile-nav a {
    text-decoration: none;
    font-size: 1.5rem;
    color: var(--text-color) !important;
    transition: var(--transition);
}

.mobile-nav a:hover {
    color: var(--primary-color) !important;
}

/* Mensajes de alerta */
.alert-message {
    padding: 12px 16px;
    border-radius: 8px;
    margin-bottom: 16px;
    font-weight: 500;
    border: 1px solid;
    transition: var(--transition);
    background: var(--card-bg) !important;
}

.alert-success {
    background: var(--card-bg) !important;
    color: var(--success-color) !important;
    border-color: var(--success-color) !important;
}

.alert-info {
    background: var(--card-bg) !important;
    color: var(--primary-color) !important;
    border-color: var(--primary-color) !important;
}

.alert-message button {
    background: none !important;
    border: none !important;
    cursor: pointer;
    font-weight: bold;
    color: inherit !important;
    opacity: 0.7;
    transition: var(--transition);
}

.alert-message button:hover {
    opacity: 1;
}

/* Animación para mensajes */
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

.alert-message {
    animation: fadeIn 0.3s ease;
}

/* Estilos adicionales para el modo oscuro */
.dark-mode .post-media img,
.dark-mode .post-media video {
    opacity: 0.9;
}

/* Placeholder text para modo oscuro */
.dark-mode ::placeholder {
    color: var(--text-muted) !important;
    opacity: 0.8;
}

/* Scrollbar para modo oscuro */
.dark-mode ::-webkit-scrollbar {
    width: 8px;
}

.dark-mode ::-webkit-scrollbar-track {
    background: var(--bg-color);
}

.dark-mode ::-webkit-scrollbar-thumb {
    background: var(--border-color);
    border-radius: 4px;
}

.dark-mode ::-webkit-scrollbar-thumb:hover {
    background: var(--text-muted);
}

/* OVERRIDE ESPECÍFICO PARA MODO OSCURO - CORRECCIÓN DEFINITIVA */
.dark-mode .create-post {
    background: #2d3748 !important;
    border-color: #4a5568 !important;
}

.dark-mode .create-post h3 {
    color: #f7fafc !important;
}

.dark-mode .create-post textarea {
    background: #4a5568 !important;
    color: #f7fafc !important;
    border-color: #4a5568 !important;
}

.dark-mode .create-post textarea::placeholder {
    color: #a0aec0 !important;
}

.dark-mode .create-post input[type="file"] {
    background: #4a5568 !important;
    color: #f7fafc !important;
    border-color: #4a5568 !important;
}

.dark-mode .create-post select {
    background: #4a5568 !important;
    color: #f7fafc !important;
    border-color: #4a5568 !important;
}

.dark-mode .create-post select option {
    background: #2d3748 !important;
    color: #f7fafc !important;
}

.dark-mode .create-post button {
    background: #3498db !important;
    color: white !important;
}

.dark-mode .create-post button:hover {
    background: #2980b9 !important;
}

/* Posts en modo oscuro */
.dark-mode .post {
    background: #2d3748 !important;
    border-color: #4a5568 !important;
}

.dark-mode .post-description {
    color: #f7fafc !important;
}

.dark-mode .timestamp,
.dark-mode .comment-time {
    color: #a0aec0 !important;
}

.dark-mode .post-actions button {
    background: #4a5568 !important;
    color: #f7fafc !important;
    border-color: #4a5568 !important;
}

.dark-mode .post-actions button:hover {
    background: #3498db !important;
    color: white !important;
}

.dark-mode .like-btn.liked {
    background: #fc8181 !important;
    color: white !important;
}

/* Comentarios en modo oscuro */
.dark-mode .comments-section {
    border-top-color: #4a5568 !important;
}

.dark-mode .comment {
    background: #4a5568 !important;
    border-color: #4a5568 !important;
    color: #f7fafc !important;
}

.dark-mode .comment-form input {
    background: #4a5568 !important;
    color: #f7fafc !important;
    border-color: #4a5568 !important;
}

.dark-mode .comment-form input::placeholder {
    color: #a0aec0 !important;
}

.dark-mode .comment-form button {
    background: #3498db !important;
    color: white !important;
}

.dark-mode .comment-form button:hover {
    background: #2980b9 !important;
}

/* Sidebar en modo oscuro */
.dark-mode .sidebar {
    background: #2d3748 !important;
    border-color: #4a5568 !important;
}

.dark-mode .sidebar h4 {
    color: #f7fafc !important;
    border-bottom-color: #4a5568 !important;
}

.dark-mode .friend-suggestion {
    background: #2d3748 !important;
    border-color: #4a5568 !important;
}

.dark-mode .friend-suggestion:hover {
    background: #4a5568 !important;
}

.dark-mode .friend-item {
    background: #2d3748 !important;
    border-color: #4a5568 !important;
}

.dark-mode .friend-item:hover {
    background: #4a5568 !important;
}

.dark-mode .btn-pending {
    background: #4a5568 !important;
    color: #a0aec0 !important;
    border-color: #4a5568 !important;
}

.dark-mode .show-more-btn {
    background: #4a5568 !important;
    color: #63b3ed !important;
    border-color: #4a5568 !important;
}

.dark-mode .show-more-btn:hover {
    background: #3498db !important;
    color: white !important;
}

.dark-mode .sidebar p {
    color: #a0aec0 !important;
}

.dark-mode .mobile-nav {
    background: #2d3748 !important;
    border-top-color: #4a5568 !important;
}

.dark-mode .mobile-nav a {
    color: #f7fafc !important;
}

.dark-mode .mobile-nav a:hover {
    color: #63b3ed !important;
}

/* Para el párrafo de "No hay publicaciones" */
.dark-mode .feed p {
    color: #a0aec0 !important;
}

/* RESPONSIVE PARA POST-HEADER */
@media (max-width: 768px) {
    .post-header {
        gap: 15px !important;
    }
    
    .user-info {
        max-width: calc(100% - 100px) !important;
    }
    
    .post-header .timestamp {
        font-size: 12px !important;
        min-width: 70px !important;
    }
    
    .post-header .verified-badge {
        width: 20px !important;
        height: 20px !important;
        margin-left: 6px !important;
    }
    
    .post-header .verified-badge--lg {
        width: 22px !important;
        height: 22px !important;
        margin-left: 8px !important;
    }
    
    .username-text {
        max-width: calc(100% - 25px) !important;
    }
    
    .friend-suggestion-username {
        max-width: 120px !important;
    }
    
    .friend-item a {
        max-width: 140px !important;
    }
    
    aside.sidebar {
        display: none;
    }
    .mobile-nav {
        display: flex !important;
    }
}

@media (max-width: 480px) {
    .post-header {
        gap: 10px !important;
    }
    
    .user-info {
        max-width: calc(100% - 80px) !important;
    }
    
    .post-header .profile-pic-small {
        width: 40px !important;
        height: 40px !important;
    }
    
    .post-header .timestamp {
        font-size: 11px !important;
        min-width: 60px !important;
    }
}

@media (min-width: 769px) {
    aside.sidebar {
        display: block;
    }
    .mobile-nav {
        display: none !important;
    }
}

@media (max-width: 1024px) {
    .friend-suggestion-username {
        max-width: 100px !important;
    }
}

@media (max-width: 900px) {
    .friend-suggestion {
        gap: 10px;
        padding: 10px;
    }
    .friend-suggestion-username {
        max-width: 80px !important;
        font-size: 13px;
    }
}

/* CORRECCIÓN ADICIONAL: Asegurar que TODOS los elementos usen variables */
form {
    background: transparent !important;
}

input, textarea, select, button {
    background: var(--input-bg) !important;
    color: var(--text-color) !important;
    border: 1px solid var(--border-color) !important;
}

/* Evitar estilos externos que puedan sobrescribir */
.dark-mode * {
    border-color: var(--border-color) !important;
}

.dark-mode input:not([type="submit"]):not([type="button"]) {
    background: var(--input-bg) !important;
    color: var(--text-color) !important;
}

.dark-mode textarea {
    background: var(--input-bg) !important;
    color: var(--text-color) !important;
}

.dark-mode select {
    background: var(--select-bg) !important;
    color: var(--text-color) !important;
}

/* Corrección especial para áreas blancas */
.dark-mode .main-content,
.dark-mode .container,
.dark-mode .feed,
.dark-mode .friend-suggestion-content,
.dark-mode .suggestion-buttons,
.dark-mode .friends-suggestions,
.dark-mode .online-friends {
    background: transparent !important;
}
</style>
</head>
<body>
<?php include "includes/header.php"; ?>

<div class="container" style="display:flex;gap:20px;">
    <!-- Contenido principal -->
    <div class="main-content" style="flex:1;">
        <!-- Mostrar mensajes de éxito/información -->
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert-message alert-success">
                <?= htmlspecialchars($_SESSION['success_message']) ?>
                <button onclick="this.parentElement.style.display='none'" style="float:right;background:none;border:none;cursor:pointer;font-weight:bold;">×</button>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['info_message'])): ?>
            <div class="alert-message alert-info">
                <?= htmlspecialchars($_SESSION['info_message']) ?>
                <button onclick="this.parentElement.style.display='none'" style="float:right;background:none;border:none;cursor:pointer;font-weight:bold;">×</button>
            </div>
            <?php unset($_SESSION['info_message']); ?>
        <?php endif; ?>

        <!-- Crear Publicación -->
        <div class="create-post">
            <h3>¿Qué estás pensando?</h3>
            <form action="upload.php" method="POST" enctype="multipart/form-data">
                <textarea name="description" placeholder="Escribe algo..." rows="3" required></textarea>
                <input type="file" name="media" accept="image/*,video/*">
                <select name="privacy" required>
                    <option value="public">Público</option>
                    <option value="friends">Solo amigos</option>
                </select>
                <button type="submit">Publicar</button>
            </form>
        </div>

        <!-- Feed -->
        <div class="feed">
            <?php if (!empty($filteredPosts)): ?>
                <?php foreach ($filteredPosts as $post): ?>
                    <?php 
                    // Obtener datos del perfil del usuario del post
                    $postUsername = $post['user'] ?? '';
                    $postUserProfile = getUserProfile($postUsername);
                    
                    // Verificar si el perfil existe y es válido
                    if (!$postUserProfile || !is_array($postUserProfile)) {
                        // Si no existe el perfil, intentar buscarlo en todos los usuarios
                        $allUsers = getAllUsersFromJSON();
                        foreach ($allUsers as $user) {
                            if ($user['username'] === $postUsername) {
                                $postUserProfile = $user;
                                break;
                            }
                        }
                        
                        // Si aún no se encuentra, crear perfil mínimo
                        if (!$postUserProfile || !is_array($postUserProfile)) {
                            $postUserProfile = [
                                'username' => $postUsername,
                                'full_name' => $postUsername,
                                'verified' => false,
                                'profile_pic' => 'default.png'
                            ];
                        }
                    }
                    
                    // Obtener nombre a mostrar
                    $displayName = getUserDisplayName($postUserProfile);
                    
                    // Verificar si está verificado
                    $isVerified = isUserVerified($postUserProfile);
                    
                    // Obtener avatar
                    $avatarSrc = getProfilePic($postUserProfile);
                    ?>
                    <article class="post" data-post-id="<?= htmlspecialchars($post['id']) ?>">
                        <!-- POST HEADER OPTIMIZADO -->
                        <div class="post-header">
                            <div class="user-info">
                                <!-- Avatar con enlace -->
                                <a href="profile.php?user=<?= urlencode($postUsername) ?>">
                                    <img src="<?= htmlspecialchars($avatarSrc) ?>" class="profile-pic-small" alt="avatar">
                                </a>
                                
                                <!-- Nombre con badge - SIN RESTRICCIONES DE ANCHO -->
                                <a href="profile.php?user=<?= urlencode($postUsername) ?>" class="username-link">
                                    <span class="username">
                                        <span class="username-text"><?= htmlspecialchars($displayName) ?></span>
                                        <?php if ($isVerified): ?>
                                            <?= renderVerifiedBadge('lg') ?>
                                        <?php endif; ?>
                                    </span>
                                </a>
                            </div>
                            
                            <!-- Timestamp compacto -->
                            <time class="timestamp"><?= htmlspecialchars(timeAgo($post['timestamp'] ?? time())) ?></time>
                        </div>

                        <?php if (!empty($post['description'])): ?>
                            <p class="post-description"><?= nl2br(htmlspecialchars($post['description'])) ?></p>
                        <?php endif; ?>

                        <?php if (!empty($post['file'])): ?>
                            <div class="post-media">
                                <?php if (($post['type'] ?? '') === 'image'): ?>
                                    <img src="assets/uploads/<?= htmlspecialchars($post['file']) ?>" alt="Post image">
                                <?php elseif (($post['type'] ?? '') === 'video'): ?>
                                    <video controls>
                                        <source src="assets/uploads/<?= htmlspecialchars($post['file']) ?>" type="video/mp4">
                                    </video>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <div class="post-actions">
                            <button class="like-btn <?= hasUserLiked($post['id'], $currentUsername) ? 'liked' : '' ?>" onclick="toggleLike('<?= htmlspecialchars($post['id']) ?>')">
                                ❤️ <span class="like-count"><?= (int) getLikeCount($post['id']) ?></span>
                            </button>
                            <button class="comment-btn" onclick="toggleComments('<?= htmlspecialchars($post['id']) ?>')">💬 Comentar</button>
                            <button class="share-btn">📤 Compartir</button>
                        </div>

                        <div class="comments-section" id="comments-<?= htmlspecialchars($post['id']) ?>" style="display:none;">
                            <div class="comments-list">
                                <?php foreach (getPostComments($post['id']) as $comment): ?>
                                    <?php 
                                    $commentUserProfile = getUserProfile($comment['user']); 
                                    $commentDisplayName = getUserDisplayName($commentUserProfile);
                                    $commentIsVerified = isUserVerified($commentUserProfile);
                                    ?>
                                    <div class="comment" style="margin:6px 0;">
                                        <a href="profile.php?user=<?= urlencode($comment['user']) ?>" class="profile-link">
                                            <strong style="display:inline-flex;align-items:center;gap:6px;">
                                                <span class="username-text"><?= htmlspecialchars($commentDisplayName) ?></span>
                                                <?php if ($commentIsVerified): ?>
                                                    <?= renderVerifiedBadge('sm') ?>
                                                <?php endif; ?>
                                            </strong>
                                        </a>
                                        <?= nl2br(htmlspecialchars($comment['text'])) ?>
                                        <time class="comment-time" style="color:var(--text-muted);margin-left:6px;"><?= htmlspecialchars(timeAgo($comment['timestamp'])) ?></time>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <form class="comment-form" onsubmit="addComment(event, '<?= htmlspecialchars($post['id']) ?>')">
                                <input type="text" placeholder="Escribe un comentario..." required>
                                <button type="submit">Enviar</button>
                            </form>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php else: ?>
                <p style="color: var(--text-muted); text-align: center; padding: 20px;">No hay publicaciones para mostrar.</p>
            <?php endif; ?>
        </div>
    </div>
    <!-- Sidebar PC -->
    <aside class="sidebar" style="width:325px;">
        <div class="friends-suggestions" id="friends-suggestions">
            <h4>Sugerencias de amistad</h4>
            <?php if (!empty($filteredSuggestions)): ?>
                <?php foreach ($filteredSuggestions as $suggestion): ?>
                    <?php 
                    $sVer = isUserVerified($suggestion); 
                    $suggestionAvatar = getProfilePic($suggestion);
                    $suggestionUsername = $suggestion['username'];
                    $suggestionDisplayName = getUserDisplayName($suggestion);
                    $hasPendingRequest = in_array($suggestionUsername, $pendingRequests);
                    ?>
                    <div class="friend-suggestion">
                        <!-- Enlace alrededor del avatar en sugerencias -->
                        <a href="profile.php?user=<?= urlencode($suggestionUsername) ?>">
                            <img src="<?= htmlspecialchars($suggestionAvatar) ?>" class="profile-pic-small" alt="avatar">
                        </a>
                        <div class="friend-suggestion-content">
                            <!-- Enlace alrededor del nombre en sugerencias - ESTRUCTURA MEJORADA -->
                            <a href="profile.php?user=<?= urlencode($suggestionUsername) ?>" class="friend-suggestion-username" title="<?= htmlspecialchars($suggestionDisplayName) ?>">
                                <span class="username-text"><?= htmlspecialchars($suggestionDisplayName) ?></span>
                                <?php if ($sVer): ?>
                                    <?= renderVerifiedBadge('sm') ?>
                                <?php endif; ?>
                            </a>
                            <div class="suggestion-buttons">
                                <?php if ($hasPendingRequest): ?>
                                    <!-- Solo mostrar "Solicitud enviada" sin botón de eliminar -->
                                    <span class="btn-pending">Solicitud enviada</span>
                                <?php else: ?>
                                    <!-- Mostrar botones de agregar y eliminar -->
                                    <form method="post">
                                        <input type="hidden" name="action" value="send_request">
                                        <input type="hidden" name="user" value="<?= htmlspecialchars($suggestionUsername) ?>">
                                        <button type="submit" class="btn-add">Agregar</button>
                                    </form>
                                    <form method="post">
                                        <input type="hidden" name="action" value="remove_suggestion">
                                        <input type="hidden" name="user" value="<?= htmlspecialchars($suggestionUsername) ?>">
                                        <button type="submit" class="btn-remove">Eliminar</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <!-- Botón "Mostrar más" (fuera del bucle foreach) -->
                <?php if ($currentSuggestionPage < $totalSuggestionPages): ?>
                    <a href="?suggestion_page=<?= $currentSuggestionPage + 1 ?>#friends-suggestions" class="show-more-btn">
                        Mostrar más (<?= $totalSuggestions - ($currentSuggestionPage * $suggestionsPerPage) ?> restantes)
                    </a>
                <?php endif; ?>
            <?php else: ?>
                <p style="color: var(--text-muted); font-size: 14px; text-align: center; padding: 10px;">
                    No hay sugerencias de amistad disponibles.
                </p>
            <?php endif; ?>
        </div>

        <div class="online-friends" style="margin-top:20px;">
            <h4>Amigos en línea</h4>
            <?php if (!empty($userFriends)): ?>
                <?php foreach ($userFriends as $friend): ?>
                    <?php 
                    $friendProfile = getUserProfile($friend); 
                    $fVer = isUserVerified($friendProfile);
                    $friendAvatar = getProfilePic($friendProfile);
                    $friendDisplayName = getUserDisplayName($friendProfile);
                    ?>
                    <div class="friend-item" onclick="window.location='chat.php?with=<?= urlencode($friend) ?>'">
                        <!-- Enlace alrededor del avatar en amigos -->
                        <a href="profile.php?user=<?= urlencode($friend) ?>">
                            <img src="<?= htmlspecialchars($friendAvatar) ?>" class="profile-pic-small" alt="avatar">
                        </a>
                        <!-- Enlace alrededor del nombre en amigos - ESTRUCTURA MEJORADA -->
                        <a href="profile.php?user=<?= urlencode($friend) ?>" style="display:flex;align-items:center;gap:6px;max-width:170px;overflow:hidden;text-decoration:none;color:inherit;">
                            <span class="username-text"><?= htmlspecialchars($friendDisplayName) ?></span>
                            <?php if ($fVer): ?>
                                <?= renderVerifiedBadge('sm') ?>
                            <?php endif; ?>
                        </a>
                        <span class="online-indicator"></span>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p style="color: var(--text-muted); font-size: 14px; text-align: center; padding: 10px;">
                    No tienes amigos aún.
                </p>
            <?php endif; ?>
        </div>
    </aside>
</div>


<script src="assets/js/main.js"></script>
<script>
// Función para mostrar mensajes de forma más atractiva
function showNotification(message, type) {
    // Crear elemento de notificación
    const notification = document.createElement('div');
    notification.className = `alert-message alert-${type}`;
    notification.innerHTML = `
        ${message}
        <button onclick="this.parentElement.remove()" style="float:right;background:none;border:none;cursor:pointer;font-weight:bold;">×</button>
    `;
    
    // Insertar al principio del contenido principal
    const mainContent = document.querySelector('.main-content');
    mainContent.insertBefore(notification, mainContent.firstChild);
    
    // Eliminar después de 5 segundos
    setTimeout(() => {
        if (notification.parentElement) {
            notification.remove();
        }
    }, 5000);
}

// Manejar envío de formularios con AJAX para mejor experiencia de usuario
document.addEventListener('DOMContentLoaded', function() {
    const forms = document.querySelectorAll('form');
    
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const action = this.querySelector('input[name="action