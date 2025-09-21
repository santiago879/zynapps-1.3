<?php
session_start();

// Definir rutas de archivos JSON
define('USERS_JSON', __DIR__ . '/data/users.json');
define('FRIENDS_JSON', __DIR__ . '/data/friends.json');
define('MESSAGES_JSON', __DIR__ . '/data/messages.json');
define('AVATAR_DIR', '/assets/uploads/profiles/'); // Ruta pública de los avatares

// Validar que el usuario está autenticado
if (!isset($_SESSION['username']) || empty($_SESSION['username'])) {
    header("Location: login.php");
    exit;
}

$currentUsername = $_SESSION['username'];

// Validar y cargar el archivo users.json
if (!file_exists(USERS_JSON) || !is_readable(USERS_JSON)) {
    die("Error: El archivo users.json no existe o no es accesible.");
}
$usersRaw = json_decode(file_get_contents(USERS_JSON), true);
if (json_last_error() !== JSON_ERROR_NONE) {
    die("Error al decodificar users.json: " . json_last_error_msg());
}

// Crear un array con el username como clave
$users = [];
foreach ($usersRaw as $user) {
    $users[$user['username']] = $user;
}

// Validar que el usuario actual existe en el sistema
if (!isset($users[$currentUsername])) {
    header("Location: login.php");
    exit;
}

$currentUser = $users[$currentUsername]; // Cargar datos del usuario actual

// OBTENER CONFIGURACIONES DEL USUARIO PARA EL MODO OSCURO
$userPreferences = $currentUser['preferences'] ?? [];
$darkModeEnabled = ($userPreferences['dark_mode'] ?? 0) == 1;

// Validar y cargar el archivo friends.json
if (!file_exists(FRIENDS_JSON) || !is_readable(FRIENDS_JSON)) {
    die("Error: El archivo friends.json no existe o no es accesible.");
}
$friendsRaw = json_decode(file_get_contents(FRIENDS_JSON), true);
if (json_last_error() !== JSON_ERROR_NONE) {
    die("Error al decodificar friends.json: " . json_last_error_msg());
}

// Generar la lista de amigos del usuario actual
$friends = [];
foreach ($friendsRaw as $relation) {
    if (isset($relation['a']) && isset($relation['b'])) {
        if ($relation['a'] === $currentUsername) {
            $friends[] = $relation['b'];
        } elseif ($relation['b'] === $currentUsername) {
            $friends[] = $relation['a'];
        }
    }
}

// Validar si hay amigos disponibles
$hasFriends = !empty($friends);

// Validar el ID del amigo desde la URL o seleccionar el primero de la lista
$friendId = $_GET['friend'] ?? null;
$friendUser = null;

if ($friendId !== null) {
    $friendUser = $users[$friendId] ?? null;
    if (!$friendUser) {
        // Si el usuario no existe, asignar valores predeterminados
        $friendUser = [
            'username' => $friendId,
            'full_name' => 'Usuario desconocido',
            'profile_pic' => 'default.jpg',
            'online' => false,
            'verified' => false
        ];
    }
}

// Validar y cargar el archivo messages.json
if (!file_exists(MESSAGES_JSON) || !is_readable(MESSAGES_JSON)) {
    // Si el archivo no existe o está vacío, inicializarlo como un array vacío
    $messagesData = [];
} else {
    $messagesRaw = file_get_contents(MESSAGES_JSON);
    if (trim($messagesRaw) === "null" || trim($messagesRaw) === "") {
        // Si el contenido es "null" o está vacío, inicializar como array vacío
        $messagesData = [];
    } else {
        $messagesData = json_decode($messagesRaw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            die("Error al decodificar messages.json: " . json_last_error_msg());
        }
    }
}

// Función actualizada para obtener el avatar del usuario
function getAvatar($user) {
    $avatarFile = $user['profile_pic'] ?? '';
    
    // Si no hay avatar definido o es vacío, usar por defecto
    if (empty($avatarFile)) {
        $avatarFile = 'default.png';
    }
    
    // Si es una URL completa, usarla directamente
    if (filter_var($avatarFile, FILTER_VALIDATE_URL)) {
        return $avatarFile;
    }
    
    // Lista de posibles nombres de archivo por defecto a verificar
    $defaultAvatars = ['default.png', 'default.jpg', 'avatar.png', 'avatar.jpg'];
    
    // Verificar si el archivo específico existe
    $specificPath = $_SERVER['DOCUMENT_ROOT'] . AVATAR_DIR . $avatarFile;
    if (file_exists($specificPath)) {
        return AVATAR_DIR . htmlspecialchars($avatarFile);
    }
    
    // Buscar algún avatar por defecto que exista
    foreach ($defaultAvatars as $default) {
        $defaultPath = $_SERVER['DOCUMENT_ROOT'] . AVATAR_DIR . $default;
        if (file_exists($defaultPath)) {
            return AVATAR_DIR . $default;
        }
    }
    
    // Si no se encuentra ningún archivo, generar avatar con inicial
    $initial = substr($user['full_name'] ?? 'U', 0, 1);
    $color = substr(md5($user['full_name'] ?? 'user'), 0, 6); // Color único basado en nombre
    return "https://via.placeholder.com/50/{$color}/ffffff?text=" . urlencode($initial);
}

// Función para mostrar insignia de verificación
function getVerifiedBadge($user) {
    if (isset($user['verified']) && $user['verified']) {
        return '<span class="verified-badge" title="Verificado"></span>';
    }
    return '';
}

// Función para truncar texto largo
function truncateText($text, $maxLength = 100) {
    if (strlen($text) > $maxLength) {
        return substr($text, 0, $maxLength) . '...';
    }
    return $text;
}

// Enviar mensaje
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message']) && $friendId !== null) {
    $newMessage = [
        'from' => $currentUsername,
        'to' => $friendId,
        'text' => trim($_POST['message']),
        'time' => date('Y-m-d H:i:s'),
        'seen' => false
    ];
    $messagesData[] = $newMessage;
    file_put_contents(MESSAGES_JSON, json_encode($messagesData, JSON_PRETTY_PRINT));
    header("Location: chat.php?friend=$friendId");
    exit;
}

// Marcar mensajes como vistos
foreach ($messagesData as &$msg) {
    if ($msg['to'] === $currentUsername && $msg['from'] === $friendId) {
        $msg['seen'] = true;
    }
}
file_put_contents(MESSAGES_JSON, json_encode($messagesData, JSON_PRETTY_PRINT));

// Filtrar mensajes del chat actual
$chatMessages = array_filter($messagesData, function ($m) use ($currentUsername, $friendId) {
    return ($m['from'] === $currentUsername && $m['to'] === $friendId) || ($m['from'] === $friendId && $m['to'] === $currentUsername);
});

// Últimos mensajes por amigo
$lastMessages = [];
foreach ($friends as $fid) {
    $msgs = array_filter($messagesData, function ($m) use ($currentUsername, $fid) {
        return ($m['from'] === $currentUsername && $m['to'] === $fid) || ($m['from'] === $fid && $m['to'] === $currentUsername);
    });
    $lastMessages[$fid] = $msgs ? truncateText(array_pop($msgs)['text'], 30) : '';
}
?>
<!DOCTYPE html>
<html lang="es" class="<?= $darkModeEnabled ? 'dark-mode' : '' ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Chat ZyNapps</title>
<style>
/* Variables CSS para modo claro y oscuro */
:root {
    --brand-1: #7b5cf0;
    --brand-2: #5f6af8;
    --brand-3: #4e7dfc;
    --brand-4: #2a66ff;
    --text-color: #1f2328;
    --text-muted: #6b7280;
    --bg-color: #f3f5fb;
    --card-bg: #ffffff;
    --border-color: #e1e8ed;
    --danger-color: #e53935;
    --online-color: #22c55e;
    --offline-color: #9ca3af;
    --shadow-color: rgba(0,0,0,0.1);
    --input-bg: #ffffff;
    --friends-bg: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.dark-mode {
    --text-color: #f7fafc;
    --text-muted: #a0aec0;
    --bg-color: #1a1a1a;
    --card-bg: #2d3748;
    --border-color: #4a5568;
    --danger-color: #fc8181;
    --online-color: #68d391;
    --offline-color: #a0aec0;
    --shadow-color: rgba(0,0,0,0.3);
    --input-bg: #4a5568;
    --friends-bg: linear-gradient(135deg, #4a5568 0%, #2d3748 100%);
}

/* Badge verificado estilo profile/index */
.verified-badge {
    display: inline-block;
    width: 18px;
    height: 18px;
    background: linear-gradient(135deg, #1DA1F2, #0d8ddb);
    border-radius: 50%;
    position: relative;
    margin-left: 5px;
}

.verified-badge::after {
    content: '✔';
    color: #fff;
    font-size: 12px;
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    font-weight: bold;
}

/* Estilos base */
body {
    margin: 0;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    background: var(--bg-color) !important;
    color: var(--text-color) !important;
    transition: var(--transition);
}

.container {
    display: flex;
    height: 100vh;
}

.friends-list {
    width: 250px;
    background: var(--friends-bg) !important;
    color: #fff !important;
    padding: 20px;
    box-sizing: border-box;
    overflow-y: auto;
}

.friends-list h2 {
    margin-top: 0;
    font-size: 18px;
    margin-bottom: 15px;
    color: #fff !important;
}

.friend-item {
    display: flex;
    align-items: center;
    padding: 10px;
    margin-bottom: 5px;
    border-radius: 8px;
    cursor: pointer;
    background: rgba(255, 255, 255, 0.1);
    text-decoration: none;
    color: #fff !important;
    position: relative;
    transition: var(--transition);
}

.friend-item.active {
    background: var(--brand-3) !important;
}

.friend-item:hover {
    background: var(--brand-3) !important;
}

.friend-item img {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    margin-right: 10px;
    object-fit: cover;
    flex-shrink: 0;
}

.friend-info-container {
    flex: 1;
    min-width: 0;
    overflow: hidden;
}

.friend-item .friend-name {
    font-weight: bold;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    color: #fff !important;
}

.friend-item .last-msg {
    font-size: 12px;
    color: #eee !important;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 180px;
}

.friend-item .status {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    position: absolute;
    top: 15px;
    right: 15px;
    flex-shrink: 0;
}

.chat-area {
    flex: 1;
    display: flex;
    flex-direction: column;
    border-left: 1px solid var(--border-color);
    border-right: 1px solid var(--border-color);
    background: var(--bg-color) !important;
}

.chat-header {
    display: flex;
    align-items: center;
    padding: 15px 20px;
    background: var(--brand-2) !important;
    color: #fff !important;
    font-size: 18px;
    font-weight: bold;
    border-bottom: 1px solid var(--brand-4);
    position: relative;
}

.chat-header img {
    border-radius: 50%;
    width: 40px;
    height: 40px;
    margin-right: 10px;
    object-fit: cover;
    flex-shrink: 0;
}

.chat-header .status {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    position: absolute;
    top: 18px;
    left: 48px;
    border: 2px solid #fff;
    flex-shrink: 0;
}

.messages {
    flex: 1;
    padding: 20px;
    overflow-y: auto;
    display: flex;
    flex-direction: column-reverse;
    justify-content: flex-start;
    background: var(--bg-color) !important;
    gap: 15px;
}

.message-container {
    display: flex;
    align-items: flex-start;
    margin-bottom: 10px;
    animation: fadeIn 0.3s ease forwards;
    opacity: 0;
    transform: translateY(20px);
}

.message-container.me {
    flex-direction: row-reverse;
}

.message-container img {
    width: 35px;
    height: 35px;
    border-radius: 50%;
    margin: 0 10px;
    object-fit: cover;
    flex-shrink: 0;
}

.message-content {
    max-width: 70%;
    border-radius: 15px;
    padding: 12px 15px;
    word-wrap: break-word;
    overflow-wrap: break-word;
    position: relative;
    display: flex;
    flex-direction: column;
    box-shadow: 0 2px 5px var(--shadow-color);
    transition: var(--transition);
}

.message-content.me {
    background: var(--brand-1) !important;
    color: #fff !important;
}

.message-content.friend {
    background: var(--brand-4) !important;
    color: #fff !important;
}

.message-name {
    font-weight: bold;
    font-size: 13px;
    margin-bottom: 5px;
    display: flex;
    align-items: center;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 100%;
    color: inherit !important;
}

.message-text {
    font-size: 14px;
    line-height: 1.4;
    word-break: break-word;
    overflow-wrap: anywhere;
    max-width: 100%;
    color: inherit !important;
}

.message-time {
    font-size: 10px;
    text-align: right;
    color: rgba(255, 255, 255, 0.7) !important;
    margin-top: 5px;
    opacity: 0.8;
}

.message-seen {
    font-size: 10px;
    text-align: right;
    color: rgba(255, 255, 255, 0.7) !important;
    margin-top: 3px;
}

.send-form {
    display: flex;
    padding: 15px;
    background: var(--card-bg) !important;
    border-top: 1px solid var(--border-color);
    gap: 10px;
}

.send-form input {
    flex: 1;
    padding: 12px 15px;
    border: 1px solid var(--border-color) !important;
    border-radius: 20px;
    outline: none;
    font-size: 14px;
    background: var(--input-bg) !important;
    color: var(--text-color) !important;
    transition: var(--transition);
}

.send-form input:focus {
    border-color: var(--brand-2) !important;
    box-shadow: 0 0 0 3px rgba(123, 92, 240, 0.1);
}

.send-form input::placeholder {
    color: var(--text-muted) !important;
    opacity: 0.8;
}

.send-form button {
    padding: 12px 20px;
    background: var(--brand-2) !important;
    color: #fff !important;
    border: none;
    border-radius: 20px;
    cursor: pointer;
    font-weight: bold;
    transition: var(--transition);
    white-space: nowrap;
}

.send-form button:hover {
    background: var(--brand-3) !important;
    transform: translateY(-1px);
}

.friend-info {
    width: 200px;
    background: var(--card-bg) !important;
    padding: 20px;
    box-sizing: border-box;
    text-align: center;
    border-left: 1px solid var(--border-color);
}

.friend-info img {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    margin-bottom: 10px;
    object-fit: cover;
}

.friend-info h3 {
    margin: 0;
    font-size: 18px;
    color: var(--brand-1) !important;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.friend-info p {
    font-size: 14px;
    color: var(--text-muted) !important;
    margin-top: 5px;
}

.home-btn {
    display: block;
    text-align: center;
    margin: 10px 0;
}

.home-btn a {
    text-decoration: none;
    background: var(--brand-3) !important;
    color: #fff !important;
    padding: 8px 15px;
    border-radius: 20px;
    transition: var(--transition);
    display: inline-block;
}

.home-btn a:hover {
    background: var(--brand-4) !important;
    transform: translateY(-2px);
}

/* Estilos específicos para modo oscuro */
.dark-mode .friends-list {
    background: linear-gradient(135deg, #4a5568 0%, #2d3748 100%) !important;
}

.dark-mode .chat-header {
    background: #4a5568 !important;
}

.dark-mode .send-form input {
    background: #4a5568 !important;
    color: #f7fafc !important;
    border-color: #4a5568 !important;
}

.dark-mode .send-form input::placeholder {
    color: #a0aec0 !important;
}

.dark-mode .friend-info {
    background: #2d3748 !important;
    border-left-color: #4a5568 !important;
}

.dark-mode .friend-info h3 {
    color: #63b3ed !important;
}

.dark-mode .friend-info p {
    color: #a0aec0 !important;
}

/* Animaciones */
@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* RESPONSIVE */
@media screen and (max-width: 900px) {
    .friend-info {
        display: none;
    }
    .friends-list {
        width: 200px;
    }
}

@media screen and (max-width: 700px) {
    .container {
        flex-direction: column;
    }
    .friends-list {
        width: 100%;
        height: auto;
        display: block;
    }
    .chat-area {
        width: 100%;
        flex: 1;
    }
    .message-content {
        max-width: 80%;
    }
}

@media screen and (max-width: 500px) {
    .friend-item img {
        width: 35px;
        height: 35px;
    }
    .send-form input {
        font-size: 13px;
    }
    .send-form button {
        padding: 10px 15px;
        font-size: 13px;
    }
    .message-content {
        max-width: 85%;
    }
    .messages {
        padding: 15px 10px;
    }
    .message-container img {
        width: 30px;
        height: 30px;
        margin: 0 8px;
    }
}

/* Scrollbar personalizado */
.messages::-webkit-scrollbar {
    width: 6px;
}

.messages::-webkit-scrollbar-track {
    background: transparent;
}

.messages::-webkit-scrollbar-thumb {
    background: var(--text-muted);
    border-radius: 3px;
}

.messages::-webkit-scrollbar-thumb:hover {
    background: var(--brand-1);
}

.friends-list::-webkit-scrollbar {
    width: 6px;
}

.friends-list::-webkit-scrollbar-track {
    background: rgba(255, 255, 255, 0.1);
}

.friends-list::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.3);
    border-radius: 3px;
}

.friends-list::-webkit-scrollbar-thumb:hover {
    background: rgba(255, 255, 255, 0.5);
}

/* Modo oscuro para scrollbars */
.dark-mode .messages::-webkit-scrollbar-thumb {
    background: #4a5568;
}

.dark-mode .messages::-webkit-scrollbar-thumb:hover {
    background: #63b3ed;
}
</style>
</head>
<body>
<div class="container">
    <!-- Lista de amigos -->
    <div class="friends-list">
        <h2>Chats</h2>
        <?php if ($hasFriends): ?>
            <?php foreach ($friends as $fid): ?>
                <?php $friend = $users[$fid] ?? [
                    'username' => $fid, 
                    'full_name' => 'Usuario ' . $fid, 
                    'profile_pic' => 'default.jpg', 
                    'online' => false, 
                    'verified' => false
                ]; ?>
                <a href="?friend=<?php echo $fid; ?>" class="friend-item <?php echo $fid == $friendId ? 'active' : ''; ?>">
                    <img src="<?php echo getAvatar($friend); ?>" alt="Avatar">
                    <div class="friend-info-container">
                        <div class="friend-name"><?php echo htmlspecialchars($friend['full_name']); ?><?php echo getVerifiedBadge($friend); ?></div>
                        <div class="last-msg"><?php echo htmlspecialchars($lastMessages[$fid] ?? 'Iniciar conversación'); ?></div>
                    </div>
                    <div class="status" style="background:<?php echo ($friend['online'] ?? false) ? 'var(--online-color)' : 'var(--offline-color)'; ?>"></div>
                </a>
            <?php endforeach; ?>
        <?php else: ?>
            <p style="padding:20px; color: #fff; text-align: center;">No tienes amigos disponibles.</p>
        <?php endif; ?>
    </div>

    <!-- Chat central -->
    <div class="chat-area">
        <div class="home-btn"><a href="index.php">🏠 Inicio</a></div>
        <?php if ($friendUser): ?>
        <div class="chat-header">
            <img src="<?php echo getAvatar($friendUser); ?>" alt="Avatar">
            <?php echo htmlspecialchars($friendUser['full_name']); ?><?php echo getVerifiedBadge($friendUser); ?>
            <div class="status" style="background:<?php echo $friendUser['online'] ? 'var(--online-color)' : 'var(--offline-color)'; ?>"></div>
        </div>

        <div class="messages" id="messages">
            <?php if (empty($chatMessages)): ?>
                <div style="text-align: center; padding: 30px; color: var(--text-muted);">
                    <p>No hay mensajes aún.</p>
                    <p>¡Envía el primero!</p>
                </div>
            <?php else: ?>
                <?php foreach (array_reverse($chatMessages) as $index => $msg): ?>
                <?php 
                    $isMe = $msg['from'] == $currentUsername; 
                    $sender = $users[$msg['from']] ?? [
                        'username' => $msg['from'],
                        'full_name' => 'Usuario ' . $msg['from'], 
                        'profile_pic' => 'default.jpg', 
                        'verified' => false
                    ];
                ?>
                <div class="message-container <?php echo $isMe ? 'me' : 'friend'; ?>" style="animation-delay: <?php echo $index * 0.05; ?>s">
                    <img src="<?php echo getAvatar($sender); ?>" alt="Avatar">
                    <div class="message-content <?php echo $isMe ? 'me' : 'friend'; ?>">
                        <div class="message-name"><?php echo htmlspecialchars($sender['full_name']); ?><?php echo getVerifiedBadge($sender); ?></div>
                        <div class="message-text"><?php echo htmlspecialchars($msg['text']); ?></div>
                        <div class="message-time"><?php echo date('H:i', strtotime($msg['time'])); ?></div>
                        <?php if($isMe): ?>
                        <div class="message-seen"><?php echo $msg['seen'] ? '✔ Visto' : '⌛ Enviado'; ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <form method="post" class="send-form">
            <input type="text" name="message" placeholder="Escribe un mensaje..." required maxlength="500">
            <button type="submit">Enviar</button>
        </form>
        <?php else: ?>
            <div style="padding: 40px; text-align: center; color: var(--text-muted);">
                <p>Selecciona un amigo para iniciar el chat.</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Info amigo derecho -->
    <?php if ($friendUser): ?>
    <div class="friend-info">
        <img src="<?php echo getAvatar($friendUser); ?>" alt="Avatar">
        <h3><?php echo htmlspecialchars($friendUser['full_name']); ?><?php echo getVerifiedBadge($friendUser); ?></h3>
        <p>Estado: <?php echo $friendUser['online'] ? 'En línea' : 'Desconectado'; ?></p>
        <p>Conversando con este usuario</p>
    </div>
    <?php endif; ?>
</div>

<script>
// Scroll automático al último mensaje
const messagesContainer = document.getElementById('messages');
if(messagesContainer) {
    messagesContainer.scrollTop = 0; // Para mensajes en orden inverso
    
    // Asegurarse de que los mensajes se muestren correctamente después de cargar
    setTimeout(() => {
        messagesContainer.scrollTop = 0;
    }, 100);
}

// Enfocar el campo de mensaje al cargar
document.querySelector('input[name="message"]')?.focus();

// Validación del formulario
document.querySelector('form')?.addEventListener('submit', function(e) {
    const messageInput = this.querySelector('input[name="message"]');
    if (!messageInput.value.trim()) {
        e.preventDefault();
        messageInput.focus();
    }
});
</script>
</body>
</html>