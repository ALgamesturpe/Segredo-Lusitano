<?php
// ============================================================
// SEGREDO LUSITANO — Google OAuth Callback
// Este ficheiro recebe o redirect do Google após autorização
// Fluxo: Login → Google → google_callback.php → Site
// ============================================================
require_once dirname(__DIR__) . '/includes/auth.php';

// ── Verificar state (proteção CSRF) ─────────────────────────
$state_recebido = $_GET['state'] ?? '';
$state_sessao   = $_SESSION['google_oauth_state'] ?? '';
unset($_SESSION['google_oauth_state']);

if (!$state_recebido || $state_recebido !== $state_sessao) {
    flash('error', 'Erro de segurança na autenticação Google. Tenta novamente.');
    header('Location: ' . SITE_URL . '/pages/login.php');
    exit;
}

// ── Verificar se houve erro ──────────────────────────────────
if (isset($_GET['error'])) {
    $erro = $_GET['error'];
    if ($erro === 'access_denied') {
        flash('error', 'Autenticação Google cancelada.');
    } else {
        flash('error', 'Erro Google: ' . htmlspecialchars($erro));
    }
    header('Location: ' . SITE_URL . '/pages/login.php');
    exit;
}

// ── Obter o código de autorização ───────────────────────────
$code = $_GET['code'] ?? '';
if (!$code) {
    flash('error', 'Código de autorização em falta.');
    header('Location: ' . SITE_URL . '/pages/login.php');
    exit;
}

// ── Trocar o código por um access token ─────────────────────
$redirect_uri = SITE_URL . '/pages/google_callback.php';

$token_data = [
    'code'          => $code,
    'client_id'     => GOOGLE_CLIENT_ID,
    'client_secret' => GOOGLE_CLIENT_SECRET,
    'redirect_uri'  => $redirect_uri,
    'grant_type'    => 'authorization_code',
];

$token_response = null;

if (function_exists('curl_init')) {
    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($token_data),
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
    ]);
    $token_response = curl_exec($ch);
    curl_close($ch);
} else {
    $opts = ['http' => [
        'method'  => 'POST',
        'header'  => 'Content-Type: application/x-www-form-urlencoded',
        'content' => http_build_query($token_data),
    ]];
    $token_response = @file_get_contents('https://oauth2.googleapis.com/token', false, stream_context_create($opts));
}

if (!$token_response) {
    flash('error', 'Erro ao comunicar com o Google. Tenta novamente.');
    header('Location: ' . SITE_URL . '/pages/login.php');
    exit;
}

$tokens = json_decode($token_response, true);

if (empty($tokens['id_token'])) {
    flash('error', 'Resposta inválida do Google.');
    header('Location: ' . SITE_URL . '/pages/login.php');
    exit;
}

// ── Verificar o id_token ─────────────────────────────────────
$verify_url  = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($tokens['id_token']);
$verify_resp = null;

if (function_exists('curl_init')) {
    $ch = curl_init($verify_url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_TIMEOUT => 10]);
    $verify_resp = curl_exec($ch);
    curl_close($ch);
} else {
    $verify_resp = @file_get_contents($verify_url);
}

$payload = $verify_resp ? json_decode($verify_resp, true) : null;

if (!$payload || !empty($payload['error']) || ($payload['aud'] ?? '') !== GOOGLE_CLIENT_ID) {
    flash('error', 'Token Google inválido.');
    header('Location: ' . SITE_URL . '/pages/login.php');
    exit;
}

$google_id     = $payload['sub']     ?? '';
$google_email  = $payload['email']   ?? '';
$google_nome   = $payload['name']    ?? 'Utilizador';
$google_avatar = $payload['picture'] ?? '';

// ── Procurar ou criar conta ──────────────────────────────────
$st = db()->prepare('SELECT * FROM utilizadores WHERE (email = ? OR google_id = ?) AND ativo = 1');
$st->execute([$google_email, $google_id]);
$user = $st->fetch();

if ($user) {
    $sets = ['verificado = 1'];
    $par  = [];
    if (empty($user['google_id']))          { $sets[] = 'google_id = ?';          $par[] = $google_id; }
    if ($google_avatar && empty($user['google_avatar_url'])) { $sets[] = 'google_avatar_url = ?'; $par[] = $google_avatar; }
    $par[] = $user['id'];
    db()->prepare('UPDATE utilizadores SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($par);

    $_SESSION['user_id'] = $user['id'];
    flash('success', 'Bem-vindo de volta, ' . $user['nome'] . '!');
} else {
    $base = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', explode(' ', $google_nome)[0]));
    if (strlen($base) < 3) $base = 'explorador';
    $username = $base; $n = 1;
    while (true) {
        $c = db()->prepare('SELECT id FROM utilizadores WHERE username = ?');
        $c->execute([$username]);
        if (!$c->fetch()) break;
        $username = $base . $n++;
    }
    $hash = password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT);
    $st = db()->prepare('
        INSERT INTO utilizadores (nome, username, email, password, google_id, google_avatar_url, verificado, pontos)
        VALUES (?, ?, ?, ?, ?, ?, 1, ?)
    ');
    $st->execute([$google_nome, $username, $google_email, $hash, $google_id, $google_avatar, PONTOS_LOCAL]);
    $_SESSION['user_id'] = (int) db()->lastInsertId();
    flash('success', 'Conta criada com sucesso! Bem-vindo, ' . $google_nome . '! 🎉');
}

$redirect = $_SESSION['google_redirect_after'] ?? SITE_URL . '/index.php';
unset($_SESSION['google_redirect_after']);
header('Location: ' . $redirect);
exit;
