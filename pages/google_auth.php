<?php
// ============================================================
// SEGREDO LUSITANO — Google Sign-In (endpoint AJAX)
// Recebe o token JWT do Google, valida-o e inicia sessão
// ou cria uma conta nova automaticamente.
// ============================================================

// Capturar erros PHP e devolvê-los como JSON
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Erro interno do servidor.', 'debug' => "PHP Error: $errstr in $errfile:$errline"]);
    exit;
});

try {
    require_once dirname(__DIR__) . '/includes/auth.php';
} catch (Exception $e) {
    echo json_encode(['ok' => false, 'msg' => 'Erro ao carregar configuração.', 'debug' => $e->getMessage()]);
    exit;
}

header('Content-Type: application/json');

// Só aceitar pedidos POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'msg' => 'Método inválido.']);
    exit;
}

$id_token = trim($_POST['id_token'] ?? '');
if (!$id_token) {
    echo json_encode(['ok' => false, 'msg' => 'Token em falta.']);
    exit;
}

// Verificar o token junto da API do Google
$url = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($id_token);

// Em localhost desativar verificação SSL (só para desenvolvimento)
$is_localhost = in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1', '::1']);

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_SSL_VERIFYPEER => !$is_localhost,
    CURLOPT_SSL_VERIFYHOST => $is_localhost ? 0 : 2,
    CURLOPT_FOLLOWLOCATION => false,
]);

$response   = curl_exec($ch);
$http_code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
$curl_errno = curl_errno($ch);
curl_close($ch);

if ($response === false) {
    echo json_encode(['ok' => false, 'msg' => 'Erro de conexão ao verificar token Google.', 'debug' => $is_localhost ? $curl_error : null]);
    exit;
}

if ($http_code !== 200) {
    echo json_encode(['ok' => false, 'msg' => 'Não foi possível verificar o token Google.', 'debug' => $is_localhost ? "HTTP $http_code" : null]);
    exit;
}

$payload = json_decode($response, true);

if (!is_array($payload) || empty($payload)) {
    echo json_encode(['ok' => false, 'msg' => 'Resposta inválida do Google.']);
    exit;
}

// Validações de segurança do token
if (empty($payload['email_verified'])) {
    echo json_encode(['ok' => false, 'msg' => 'Email Google não verificado.']);
    exit;
}

if (($payload['aud'] ?? '') !== GOOGLE_CLIENT_ID) {
    echo json_encode(['ok' => false, 'msg' => 'Client ID inválido.']);
    exit;
}

$google_email = $payload['email'];
$google_nome  = $payload['name'] ?? 'Utilizador';

try {
    // Verificar se já existe conta com este email
    $st = db()->prepare('SELECT * FROM utilizadores WHERE email = ? AND ativo = 1');
    $st->execute([$google_email]);
    $user = $st->fetch();

    if ($user && !$user['ativo']) {
        echo json_encode(['ok' => false, 'msg' => 'Conta suspensa. Contacta o administrador.']);
        exit;
    }

    if ($user) {
        // Conta já existe — iniciar sessão diretamente
        // Marcar conta como verificada se ainda não estava
        if (!$user['verificado']) {
            db()->prepare('UPDATE utilizadores SET verificado = 1 WHERE id = ?')->execute([$user['id']]);
        }
        $_SESSION['user_id'] = $user['id'];
        echo json_encode(['ok' => true, 'redirect' => SITE_URL . '/index.php', 'novo' => false]);

    } else {
        // Criar conta nova com os dados do Google
        // Gerar username único a partir do primeiro nome
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

        // Password aleatória — não é usada porque o login é via Google
        $password_hash = password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT);

        $st = db()->prepare('INSERT INTO utilizadores (nome, username, email, password, verificado, pontos, tipo_auth) VALUES (?, ?, ?, ?, 1, 0, "google")');
        $st->execute([$google_nome, $username, $google_email, $password_hash]);

        $novo_id = (int)db()->lastInsertId();
        $_SESSION['user_id'] = $novo_id;
        echo json_encode(['ok' => true, 'redirect' => SITE_URL . '/index.php', 'novo' => true, 'nome' => $google_nome]);
    }

} catch (PDOException $e) {
    error_log('Google OAuth DB error: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'msg' => 'Erro na base de dados.', 'debug' => $is_localhost ? $e->getMessage() : null]);
    exit;
}