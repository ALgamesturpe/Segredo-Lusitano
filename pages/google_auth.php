<?php
// ============================================================
// SEGREDO LUSITANO — Google Sign-In (endpoint)
// ============================================================

// Catch all errors and return as JSON
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'msg' => 'Erro interno do servidor.',
        'debug' => "PHP Error: $errstr in $errfile:$errline"
    ]);
    exit;
});

try {
    require_once dirname(__DIR__) . '/includes/auth.php';
} catch (Exception $e) {
    echo json_encode([
        'ok' => false,
        'msg' => 'Erro ao carregar configuração.',
        'debug' => $e->getMessage()
    ]);
    exit;
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'msg' => 'Método inválido.']);
    exit;
}

$id_token = trim($_POST['id_token'] ?? '');
if (!$id_token) {
    echo json_encode(['ok' => false, 'msg' => 'Token em falta.']);
    exit;
}

// Verificar o token com a API do Google usando cURL com SSL seguro
$url = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($id_token);

// Detectar se estamos em ambiente de desenvolvimento local
$is_localhost = in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1', '::1']);

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 10,
    // Em localhost, desativar verificação SSL (apenas para desenvolvimento!)
    CURLOPT_SSL_VERIFYPEER => !$is_localhost,
    CURLOPT_SSL_VERIFYHOST => $is_localhost ? 0 : 2,
    CURLOPT_FOLLOWLOCATION => false,
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
$curl_errno = curl_errno($ch);
curl_close($ch);

if ($response === false) {
    error_log('Google OAuth cURL error: [' . $curl_errno . '] ' . $curl_error);
    echo json_encode([
        'ok' => false,
        'msg' => 'Erro de conexão ao verificar token Google.',
        'debug' => $is_localhost ? $curl_error : null
    ]);
    exit;
}

if ($http_code !== 200) {
    error_log('Google OAuth HTTP error: ' . $http_code . ' - Response: ' . substr($response, 0, 200));
    echo json_encode([
        'ok' => false,
        'msg' => 'Não foi possível verificar o token Google.',
        'debug' => $is_localhost ? "HTTP $http_code: " . substr($response, 0, 100) : null
    ]);
    exit;
}

$payload = json_decode($response, true);

if (!is_array($payload) || empty($payload)) {
    error_log('Google OAuth invalid payload: ' . substr($response, 0, 200));
    echo json_encode([
        'ok' => false,
        'msg' => 'Resposta inválida do Google.',
        'debug' => $is_localhost ? substr($response, 0, 100) : null
    ]);
    exit;
}

// Validações de segurança
// Google retorna email_verified como boolean true ou string "true"
if (empty($payload['email_verified'])) {
    echo json_encode([
        'ok' => false,
        'msg' => 'Email Google não verificado.',
        'debug' => $is_localhost ? 'email_verified is empty or false' : null
    ]);
    exit;
}

if (($payload['aud'] ?? '') !== GOOGLE_CLIENT_ID) {
    echo json_encode([
        'ok' => false,
        'msg' => 'Client ID inválido.',
        'debug' => $is_localhost ? "Expected: " . GOOGLE_CLIENT_ID . ", Got: " . ($payload['aud'] ?? 'null') : null
    ]);
    exit;
}

$google_email = $payload['email'];
$google_nome  = $payload['name']          ?? 'Utilizador';
$google_sub   = $payload['sub']           ?? '';   // ID único Google

try {
    // Verificar se já existe conta com este email
    $st = db()->prepare('SELECT * FROM utilizadores WHERE email = ? AND ativo = 1');
    $st->execute([$google_email]);
    $user = $st->fetch();

    if ($user) {
        // Já tem conta — iniciar sessão diretamente
        // Marcar como verificado se ainda não estava
        if (!$user['verificado']) {
            db()->prepare('UPDATE utilizadores SET verificado = 1 WHERE id = ?')->execute([$user['id']]);
        }
        $_SESSION['user_id'] = $user['id'];
        echo json_encode(['ok' => true, 'redirect' => SITE_URL . '/index.php', 'novo' => false]);
    } else {
        // Criar conta nova automaticamente
        // Gerar username único a partir do nome
        $base_username = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', explode(' ', $google_nome)[0]));
        if (strlen($base_username) < 3) $base_username = 'user';
        $username = $base_username;
        $sufixo   = 1;
        while (true) {
            $check = db()->prepare('SELECT id FROM utilizadores WHERE username = ?');
            $check->execute([$username]);
            if (!$check->fetch()) break;
            $username = $base_username . $sufixo++;
        }

        // Password aleatória (não usada, login é via Google)
        $password_hash = password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT);

        $st = db()->prepare('
            INSERT INTO utilizadores (nome, username, email, password, verificado, pontos)
            VALUES (?, ?, ?, ?, 1, ?)
        ');
        $st->execute([$google_nome, $username, $google_email, $password_hash, PONTOS_LOCAL]);
        $novo_id = (int) db()->lastInsertId();

        $_SESSION['user_id'] = $novo_id;
        echo json_encode(['ok' => true, 'redirect' => SITE_URL . '/index.php', 'novo' => true, 'nome' => $google_nome]);
    }
} catch (PDOException $e) {
    error_log('Google OAuth DB error: ' . $e->getMessage());
    echo json_encode([
        'ok' => false,
        'msg' => 'Erro ao guardar dados do utilizador.',
        'debug' => $is_localhost ? $e->getMessage() : null
    ]);
    exit;
}
