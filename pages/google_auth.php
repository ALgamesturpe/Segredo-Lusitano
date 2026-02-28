<?php
// ============================================================
// SEGREDO LUSITANO — Google Sign-In (Token Endpoint)
// ============================================================
require_once dirname(__DIR__) . '/includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'msg' => 'Método inválido.']);
    exit;
}

$id_token = trim($_POST['id_token'] ?? '');
if (!$id_token) {
    echo json_encode(['ok' => false, 'msg' => 'Token em falta.']);
    exit;
}

// Verificar token com a API do Google
$url      = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($id_token);
$response = @file_get_contents($url);

// Fallback com cURL
if (!$response && function_exists('curl_init')) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_TIMEOUT => 10]);
    $response = curl_exec($ch);
    curl_close($ch);
}

if (!$response) {
    echo json_encode(['ok' => false, 'msg' => 'Não foi possível verificar o token. Verifica se allow_url_fopen=On no php.ini.']);
    exit;
}

$payload = json_decode($response, true);

if (!empty($payload['error'])) {
    echo json_encode(['ok' => false, 'msg' => 'Token inválido: ' . $payload['error']]);
    exit;
}
if (empty($payload['email_verified']) || $payload['email_verified'] !== 'true') {
    echo json_encode(['ok' => false, 'msg' => 'Email Google não verificado.']);
    exit;
}
if (($payload['aud'] ?? '') !== GOOGLE_CLIENT_ID) {
    echo json_encode(['ok' => false, 'msg' => 'Client ID não corresponde.']);
    exit;
}

$google_email  = $payload['email'] ?? '';
$google_nome   = $payload['name']  ?? 'Utilizador';

if (!$google_email) {
    echo json_encode(['ok' => false, 'msg' => 'Email em falta no token Google.']);
    exit;
}

// Verificar se já existe conta com este email (apenas por email — compatível com BD antiga)
$st = db()->prepare('SELECT * FROM utilizadores WHERE email = ? AND ativo = 1');
$st->execute([$google_email]);
$user = $st->fetch();

if ($user) {
    // Marcar como verificado e iniciar sessão
    db()->prepare('UPDATE utilizadores SET verificado = 1 WHERE id = ?')->execute([$user['id']]);
    $_SESSION['user_id'] = $user['id'];
    echo json_encode(['ok' => true, 'redirect' => SITE_URL . '/index.php', 'novo' => false]);
} else {
    // Criar conta nova
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
    $st = db()->prepare('INSERT INTO utilizadores (nome, username, email, password, verificado, pontos) VALUES (?, ?, ?, ?, 1, ?)');
    $st->execute([$google_nome, $username, $google_email, $hash, PONTOS_LOCAL]);
    $_SESSION['user_id'] = (int) db()->lastInsertId();
    echo json_encode(['ok' => true, 'redirect' => SITE_URL . '/index.php', 'novo' => true, 'nome' => $google_nome]);
}
