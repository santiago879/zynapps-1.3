<?php
session_start();

/* CONFIG BÁSICA */
define('USERS_JSON', __DIR__ . '/data/users.json');
define('FRIENDS_JSON', __DIR__ . '/data/friends.json');
define('REQ_JSON', __DIR__ . '/data/friend_requests.json');

/* HELPERS JSON */
function ensureFile(string $path, $default = []) {
    $dir = dirname($path);
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    if (!file_exists($path)) file_put_contents($path, json_encode($default, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
}
function readJSON(string $path): array {
    ensureFile($path, []);
    $raw = @file_get_contents($path);
    $data = json_decode((string)$raw, true);
    return is_array($data) ? $data : [];
}
function writeJSON(string $path, array $data): bool {
    ensureFile($path, []);
    return (bool) file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
}

/* SESIÓN / USUARIO ACTUAL */
function currentUser(): ?string { return $_SESSION['username'] ?? null; }

/* CSRF */
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
function csrf_input(): string { return '<input type="hidden" name="csrf" value="'.htmlspecialchars($_SESSION['csrf'], ENT_QUOTES, 'UTF-8').'">'; }
function csrf_ok(): bool { return $_SERVER['REQUEST_METHOD']!=='POST' || (isset($_POST['csrf']) && hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'])); }

/* UTILIDADES */
function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function timeAgo(int $ts): string {
    $diff = time() - $ts;
    if ($diff < 60) return "hace {$diff}s";
    $m = intdiv($diff,60); if($m<60) return "hace {$m}m";
    $h = intdiv($m,60); if($h<24) return "hace {$h}h";
    $d = intdiv($h,24); return "hace {$d}d";
}

/* FUNCIÓN PARA OBTENER AVATAR */
function getProfilePic(array $userData): string {
    $avatarFile = $userData['profile_pic'] ?? 'default.png';
    $avatarPath = "assets/uploads/profiles/" . $avatarFile;
    if (file_exists($avatarPath) && $avatarFile !== 'default.png') {
        return $avatarPath;
    }
    $defaultPath = "assets/uploads/profiles/default.png";
    if (file_exists($defaultPath)) {
        return $defaultPath;
    }
    $initial = substr($userData['full_name'] ?? ($userData['username'] ?? 'U'), 0, 1);
    return "https://via.placeholder.com/50/7b5cf0/ffffff?text=" . urlencode($initial);
}

/* DATOS DE USUARIOS / AMIGOS / SOLICITUDES */
function getUserProfile(string $username): array {
    $users = readJSON(USERS_JSON);
    foreach($users as $u) if(($u['username']??'')===$username) return $u;
    return ['username'=>$username,'full_name'=>$username,'profile_pic'=>'default.png','last_active'=>time()];
}
function getUserFriends(string $username): array {
    $friends = readJSON(FRIENDS_JSON); $list=[];
    foreach($friends as $f){
        if(($f['a']??'')===$username) $list[]=$f['b'];
        if(($f['b']??'')===$username) $list[]=$f['a'];
    }
    return array_values(array_unique($list));
}
function areFriends(string $u1,string $u2):bool {
    if($u1===''||$u2==='') return false;
    $friends = readJSON(FRIENDS_JSON);
    foreach($friends as $f){
        if(($f['a']??'')===$u1 && ($f['b']??'')===$u2) return true;
        if(($f['a']??'')===$u2 && ($f['b']??'')===$u1) return true;
    }
    return false;
}
function addFriends(string $u1,string $u2):void {
    if($u1===$u2 || areFriends($u1,$u2)) return;
    $friends=readJSON(FRIENDS_JSON);
    $friends[]=['a'=>$u1,'b'=>$u2,'since'=>time()];
    writeJSON(FRIENDS_JSON,$friends);
}
function removeFriend(string $u1,string $u2):void {
    $friends=readJSON(FRIENDS_JSON);
    $friends=array_values(array_filter($friends,fn($f)=>!(($f['a']??'')===$u1 && ($f['b']??'')===$u2)&&!(($f['a']??'')===$u2 && ($f['b']??'')===$u1)));
    writeJSON(FRIENDS_JSON,$friends);
    $req=readJSON(REQ_JSON);
    $req=array_values(array_filter($req,fn($r)=>!(($r['from']??'')===$u1 && ($r['to']??'')===$u2)&&!(($r['from']??'')===$u2 && ($r['to']??'')===$u1)));
    writeJSON(REQ_JSON,$req);
}
/* Solicitudes */
function getFriendRequests(string $toUser): array{
    $req=readJSON(REQ_JSON); $out=[];
    foreach($req as $r) if(($r['to']??'')===$toUser && ($r['status']??'') === 'pending') $out[]=$r; // Solo pendientes
    usort($out,fn($a,$b)=>($b['timestamp']??0)-($a['timestamp']??0));
    return $out;
}
function getSentFriendRequests(string $fromUser): array{
    $req=readJSON(REQ_JSON); $out=[];
    foreach($req as $r) if(($r['from']??'')===$fromUser && ($r['status']??'') === 'pending') $out[]=$r; // Solo pendientes
    usort($out,fn($a,$b)=>($b['timestamp']??0)-($a['timestamp']??0));
    return $out;
}
function hasPendingRequest(string $from,string $to):bool{
    $req=readJSON(REQ_JSON);
    foreach($req as $r) {
        // CORREGIDO: Ahora verifica que el estado sea 'pending' para ser más preciso
        if(($r['from']??'')===$from && ($r['to']??'')===$to && ($r['status']??'') === 'pending') return true;
    }
    return false;
}
function sendFriendRequest(string $from,string $to):void{
    if($from===$to || areFriends($from,$to) || hasPendingRequest($from,$to)) return;
    $req=readJSON(REQ_JSON);
    // CORREGIDO: Se añade el campo 'status' => 'pending' al crear la solicitud
    $req[]=['from'=>$from,'to'=>$to, 'status' => 'pending', 'timestamp'=>time()];
    writeJSON(REQ_JSON,$req);
}
function acceptFriendRequest(string $from,string $to):void{ $req=readJSON(REQ_JSON); $req=array_values(array_filter($req,fn($r)=>!(($r['from']??'')===$from && ($r['to']??'')===$to))); writeJSON(REQ_JSON,$req); addFriends($from,$to);}
function rejectFriendRequest(string $from,string $to):void{ $req=readJSON(REQ_JSON); $req=array_values(array_filter($req,fn($r)=>!(($r['from']??'')===$from && ($r['to']??'')===$to))); writeJSON(REQ_JSON,$req);}
function cancelFriendRequest(string $from,string $to):void{ $req=readJSON(REQ_JSON); $req=array_values(array_filter($req,fn($r)=>!(($r['from']??'')===$from && ($r['to']??'')===$to))); writeJSON(REQ_JSON,$req);}

/* PROTECCIÓN RUTA */
if(!currentUser()){header('Location: login.php');exit;}
$currentUsername=currentUser();

// OBTENER CONFIGURACIONES DEL USUARIO PARA EL MODO OSCURO
$currentUserProfile = getUserProfile($currentUsername);
$userPreferences = $currentUserProfile['preferences'] ?? [];
$darkModeEnabled = ($userPreferences['dark_mode'] ?? 0) == 1;

/* CONTROLADOR POST */
if($_SERVER['REQUEST_METHOD']==='POST'){
    if(!csrf_ok()){http_response_code(403); exit('CSRF inválido');}
    $action=$_POST['action']??''; $target=trim($_POST['user']??'');
    if($target!=='' && $target!==$currentUsername){
        switch($action){
            case 'send_request': if(hasPendingRequest($target,$currentUsername)) acceptFriendRequest($target,$currentUsername); else sendFriendRequest($currentUsername,$target); break;
            case 'accept_request': acceptFriendRequest($target,$currentUsername); break;
            case 'reject_request': rejectFriendRequest($target,$currentUsername); break;
            case 'remove_friend': removeFriend($currentUsername,$target); break;
            case 'cancel_request': cancelFriendRequest($currentUsername,$target); break;
        }
    }
    $tab=$_GET['tab']??'friends'; $q=$_GET['q']??'';
    // Redirigir a la misma página para limpiar el POST y evitar reenvíos
    header("Location: friends.php?tab=".urlencode($tab)."&q=".urlencode($q)); exit;
}

/* DATOS GET */
$tab=$_GET['tab']??'friends'; $q=trim($_GET['q']??'');
$friends=getUserFriends($currentUsername);
$friendRequests=getFriendRequests($currentUsername);
$sentRequests=getSentFriendRequests($currentUsername);

/* BÚSQUEDA */
$searchResults=[];
if($tab==='search' && $q!==''){
    $allUsers=readJSON(USERS_JSON);
    foreach($allUsers as $u){
        $uname=$u['username']??'';
        $full=$u['full_name']??$uname;
        if(stripos($uname,$q)!==false || stripos($full,$q)!==false){
            if($uname!==$currentUsername) $searchResults[]=$u;
        }
    }
}

?>
<!DOCTYPE html>
<html lang="es" class="<?= $darkModeEnabled ? 'dark-mode' : '' ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Amigos — ZyNapps</title>
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
    --border-color: #dfe3f0;
    --danger-color: #e53935;
    --ring: rgba(123,92,240,.25);
    --topbar-bg: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    --input-bg: #ffffff;
    --button-text: #ffffff;
    --shadow-color: rgba(21,24,39,.06);
    --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.dark-mode {
    --text-color: #f7fafc;
    --text-muted: #a0aec0;
    --bg-color: #1a1a1a;
    --card-bg: #2d3748;
    --border-color: #4a5568;
    --danger-color: #fc8181;
    --ring: rgba(99,179,237,.25);
    --topbar-bg: linear-gradient(135deg, #4a5568 0%, #2d3748 100%);
    --input-bg: #4a5568;
    --button-text: #ffffff;
    --shadow-color: rgba(0,0,0,.3);
}

* {
    box-sizing: border-box;
}

body {
    font-family: system-ui, -apple-system, Segoe UI, Roboto, Ubuntu, 'Helvetica Neue', Arial, sans-serif;
    background: var(--bg-color) !important;
    margin: 0;
    color: var(--text-color) !important;
    transition: var(--transition);
}

.topbar {
    background: var(--topbar-bg) !important;
    color: #fff !important;
    padding: 14px 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    letter-spacing: .2px;
    box-shadow: 0 2px 10px rgba(0,0,0,.12);
}

.container {
    max-width: 1000px;
    margin: 24px auto;
    padding: 0 16px;
}

.home-btn {
    display: inline-block;
    margin: 0 0 14px;
    padding: .55rem 1rem;
    border-radius: .5rem;
    background: linear-gradient(90deg, var(--brand-3), var(--brand-4)) !important;
    color: #fff !important;
    text-decoration: none;
    font-weight: 600;
    box-shadow: 0 6px 16px rgba(46,78,255,.18);
}

h2 {
    margin: 0 0 12px;
    color: var(--text-color) !important;
}

.tabs a {
    padding: .6rem 1rem;
    display: inline-block;
    text-decoration: none;
    margin-right: .25rem;
    border: 1px solid var(--border-color) !important;
    border-bottom: none;
    border-radius: .6rem .6rem 0 0;
    color: var(--text-color) !important;
    background: var(--card-bg) !important;
    font-weight: 600;
    transition: var(--transition);
}

.tabs a.active {
    background: var(--card-bg) !important;
    color: var(--text-color) !important;
    border-color: var(--border-color) !important;
    box-shadow: inset 0 -2px 0 0 var(--brand-3);
}

.panel {
    border: 1px solid var(--border-color) !important;
    border-radius: 0 .6rem .6rem .6rem;
    padding: 1rem;
    background: var(--card-bg) !important;
    box-shadow: 0 8px 24px var(--shadow-color);
}

.friends-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 1rem;
}

.request-item, 
.search-item {
    border: 1px solid var(--border-color) !important;
    border-radius: .75rem;
    padding: .9rem;
    display: flex;
    flex-wrap: wrap;
    gap: .75rem;
    align-items: center;
    background: var(--card-bg) !important;
    box-shadow: 0 4px 14px var(--shadow-color);
    margin-bottom: 1rem;
}

.friend-card {
    border: 1px solid var(--border-color) !important;
    border-radius: .75rem;
    padding: .9rem;
    display: flex;
    flex-direction: column;
    gap: .75rem;
    align-items: flex-start;
    background: var(--card-bg) !important;
    box-shadow: 0 4px 14px var(--shadow-color);
}

.profile-pic-small {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    object-fit: cover;
}

.profile-pic-medium {
    width: 78px;
    height: 78px;
    border-radius: 50%;
    object-fit: cover;
}

.row {
    display: flex;
    gap: .5rem;
    align-items: center;
    flex-wrap: wrap;
}

.meta {
    color: var(--text-muted) !important;
    font-size: .92rem;
}

.btn {
    border: none;
    padding: .5rem .8rem;
    border-radius: .5rem;
    cursor: pointer;
    font-weight: 600;
    transition: var(--transition);
}

.btn-primary {
    background: linear-gradient(90deg, var(--brand-1), var(--brand-3)) !important;
    color: #fff !important;
    box-shadow: 0 6px 16px rgba(123,92,240,.24);
}

.btn-primary:hover {
    transform: translateY(-1px);
    box-shadow: 0 8px 20px rgba(123,92,240,.3);
}

.btn-secondary {
    background: var(--card-bg) !important;
    color: var(--text-color) !important;
    border: 1px solid var(--border-color) !important;
}

.btn-secondary:hover {
    background: var(--border-color) !important;
}

.btn-danger {
    background: var(--danger-color) !important;
    color: #fff !important;
}

.btn-danger:hover {
    opacity: 0.9;
    transform: translateY(-1px);
}

.btn:focus, 
.search-form input:focus {
    outline: none;
    box-shadow: 0 0 0 4px var(--ring);
}

.search-form {
    display: flex;
    gap: 1rem;
    align-items: center;
    margin-bottom: 1.5rem;
}

.search-form input {
    padding: .55rem .7rem;
    width: 100%;
    max-width: 420px;
    border: 1px solid var(--border-color) !important;
    border-radius: .5rem;
    background: var(--input-bg) !important;
    color: var(--text-color) !important;
    transition: var(--transition);
}

.search-form input::placeholder {
    color: var(--text-muted) !important;
    opacity: 0.8;
}

.section-title {
    margin: .2rem 0 1rem;
    color: var(--text-color) !important;
}

.inline {
    display: inline;
}

/* Estilos específicos para modo oscuro */
.dark-mode .topbar {
    background: linear-gradient(135deg, #4a5568 0%, #2d3748 100%) !important;
}

.dark-mode .home-btn {
    background: linear-gradient(90deg, #4e7dfc, #2a66ff) !important;
}

.dark-mode .tabs a {
    background: #2d3748 !important;
    color: #f7fafc !important;
    border-color: #4a5568 !important;
}

.dark-mode .tabs a.active {
    background: #2d3748 !important;
    color: #f7fafc !important;
    border-color: #4a5568 !important;
}

.dark-mode .panel {
    background: #2d3748 !important;
    border-color: #4a5568 !important;
}

.dark-mode .request-item,
.dark-mode .search-item,
.dark-mode .friend-card {
    background: #2d3748 !important;
    border-color: #4a5568 !important;
}

.dark-mode .btn-secondary {
    background: #4a5568 !important;
    color: #f7fafc !important;
    border-color: #4a5568 !important;
}

.dark-mode .btn-secondary:hover {
    background: #63b3ed !important;
    color: #1a1a1a !important;
}

.dark-mode .search-form input {
    background: #4a5568 !important;
    color: #f7fafc !important;
    border-color: #4a5568 !important;
}

.dark-mode .search-form input::placeholder {
    color: #a0aec0 !important;
}

/* Mejoras adicionales para el modo oscuro */
.dark-mode h2,
.dark-mode h3,
.dark-mode .section-title {
    color: #f7fafc !important;
}

.dark-mode strong {
    color: #f7fafc !important;
}

.dark-mode .meta {
    color: #a0aec0 !important;
}

/* Responsivo */
@media (max-width: 520px) {
    .profile-pic-medium {
        width: 64px;
        height: 64px;
    }
    
    .friend-card, 
    .request-item, 
    .search-item {
        padding: .75rem;
    }
    
    .search-form {
        flex-direction: column;
        align-items: stretch;
    }
    
    .search-form input {
        max-width: none;
    }
}

/* Transiciones suaves */
.tabs a,
.btn,
.request-item,
.search-item,
.friend-card,
.search-form input {
    transition: var(--transition);
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
</style>
</head>
<body>
<div class="topbar">👥 Amigos — ZyNapps</div>
<div class="container">
    <a href="index.php" class="home-btn">🏠 Inicio</a>
    <h2>Gestión de Amigos</h2>

    <div class="tabs">
        <a href="friends.php?tab=friends" class="<?= $tab==='friends'?'active':'' ?>">Mis Amigos (<?= count($friends) ?>)</a>
        <a href="friends.php?tab=requests" class="<?= $tab==='requests'?'active':'' ?>">Solicitudes (<?= count($friendRequests) ?>)</a>
        <a href="friends.php?tab=sent" class="<?= $tab==='sent'?'active':'' ?>">Enviadas (<?= count($sentRequests) ?>)</a>
        <a href="friends.php?tab=search" class="<?= $tab==='search'?'active':'' ?>">Buscar</a>
    </div>

    <div class="panel">
    <?php if($tab==='search'): ?>
        <form method="get" class="search-form">
            <input type="hidden" name="tab" value="search">
            <input type="text" name="q" placeholder="Buscar usuarios..." value="<?= e($q) ?>">
            <button type="submit" class="btn btn-primary">Buscar</button>
        </form>
        <?php if($q!==''): ?>
            <h3 class="section-title">Resultados para "<?= e($q) ?>"</h3>
            <?php if($searchResults): foreach($searchResults as $u): $uname=$u['username']; $full=$u['full_name']??$uname; ?>
                <div class="search-item">
                    <img src="<?= e(getProfilePic($u)) ?>" class="profile-pic-small">
                    <div>
                        <strong><?= e($full) ?></strong><br>
                        <span class="meta">@<?= e($uname) ?></span>
                    </div>
                    <div style="margin-left:auto" class="row">
                        <?php if(areFriends($currentUsername,$uname)): ?>
                            <span class="meta">Ya son amigos</span>
                        <?php elseif(hasPendingRequest($currentUsername,$uname)): ?>
                            <span class="meta">Solicitud enviada</span>
                        <?php elseif(hasPendingRequest($uname, $currentUsername)): ?>
                            <form method="post" class="inline">
                                <?= csrf_input() ?>
                                <input type="hidden" name="action" value="accept_request">
                                <input type="hidden" name="user" value="<?= e($uname) ?>">
                                <button type="submit" class="btn btn-primary">Aceptar</button>
                            </form>
                        <?php else: ?>
                            <form method="post" class="inline">
                                <?= csrf_input() ?>
                                <input type="hidden" name="action" value="send_request">
                                <input type="hidden" name="user" value="<?= e($uname) ?>">
                                <button type="submit" class="btn btn-primary">Enviar solicitud</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; else: ?>
                <p class="meta">No se encontraron usuarios.</p>
            <?php endif; ?>
        <?php endif; ?>
    <?php elseif($tab==='sent'): ?>
        <h3 class="section-title">Solicitudes enviadas</h3>
        <?php if($sentRequests): foreach($sentRequests as $r): $to=$r['to']; $p=getUserProfile($to); $toFriends=getUserFriends($to); ?>
            <div class="request-item">
                <img src="<?= e(getProfilePic($p)) ?>" class="profile-pic-small">
                <div>
                    <strong><?= e($p['full_name'] ?? $to) ?></strong><br>
                    <span class="meta">@<?= e($to) ?> • Enviada <?= timeAgo($r['timestamp']) ?></span><br>
                    <span class="meta">Amigos: <?= count($toFriends) ?></span>
                </div>
                <div style="margin-left:auto" class="row">
                    <a href="profile.php?user=<?= urlencode($to) ?>" class="btn btn-secondary">Ver perfil</a>
                    <form method="post" class="inline" onsubmit="return confirm('¿Cancelar solicitud enviada?');">
                        <?= csrf_input() ?>
                        <input type="hidden" name="action" value="cancel_request">
                        <input type="hidden" name="user" value="<?= e($to) ?>">
                        <button type="submit" class="btn btn-danger">Cancelar</button>
                    </form>
                </div>
            </div>
        <?php endforeach; else: ?>
            <p class="meta">No has enviado solicitudes pendientes.</p>
        <?php endif; ?>
    <?php elseif($tab==='requests'): ?>
        <h3 class="section-title">Solicitudes recibidas</h3>
        <?php if($friendRequests): foreach($friendRequests as $r): $from=$r['from']; $p=getUserProfile($from); ?>
            <div class="request-item">
                <img src="<?= e(getProfilePic($p)) ?>" class="profile-pic-small">
                <div>
                    <strong><?= e($p['full_name'] ?? $from) ?></strong><br>
                    <span class="meta">@<?= e($from) ?> • Recibida <?= timeAgo($r['timestamp']) ?></span>
                </div>
                <div style="margin-left:auto" class="row">
                    <form method="post" class="inline">
                        <?= csrf_input() ?>
                        <input type="hidden" name="action" value="accept_request">
                        <input type="hidden" name="user" value="<?= e($from) ?>">
                        <button type="submit" class="btn btn-primary">Aceptar</button>
                    </form>
                    <form method="post" class="inline" onsubmit="return confirm('¿Rechazar solicitud?');">
                        <?= csrf_input() ?>
                        <input type="hidden" name="action" value="reject_request">
                        <input type="hidden" name="user" value="<?= e($from) ?>">
                        <button type="submit" class="btn btn-danger">Rechazar</button>
                    </form>
                </div>
            </div>
        <?php endforeach; else: ?>
            <p class="meta">No tienes solicitudes pendientes.</p>
        <?php endif; ?>
    <?php elseif($tab==='friends'): ?>
        <div class="friends-grid">
        <?php if($friends): foreach($friends as $f): $p=getUserProfile($f); ?>
            <div class="friend-card">
                <img src="<?= e(getProfilePic($p)) ?>" class="profile-pic-medium">
                <div><strong><?= e($p['full_name'] ?? $f) ?></strong><br><span class="meta">@<?= e($f) ?></span></div>
                <div class="row">
                    <a href="profile.php?user=<?= urlencode($f) ?>" class="btn btn-secondary">Ver perfil</a>
                    <form method="post" class="inline" onsubmit="return confirm('¿Eliminar amigo?');">
                        <?= csrf_input() ?>
                        <input type="hidden" name="action" value="remove_friend">
                        <input type="hidden" name="user" value="<?= e($f) ?>">
                        <button type="submit" class="btn btn-danger">Eliminar</button>
                    </form>
                </div>
            </div>
        <?php endforeach; else: ?>
            <p class="meta">No tienes amigos aún. ¡Usa la pestaña "Buscar" para encontrar a gente!</p>
        <?php endif; ?>
        </div>
    <?php endif; ?>
    </div>
</div>
</body>
</html>