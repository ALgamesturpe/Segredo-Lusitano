<?php
// ============================================================
// SEGREDO LUSITANO — GitHub OAuth Callback
// ============================================================
require_once dirname(__DIR__) . '/includes/functions.php';

if (auth_user()) {
    header('Location: ' . SITE_URL . '/index.php');
    exit;
}

$code  = $_GET['code']  ?? '';
$state = $_GET['state'] ?? '';

// Verificar state para prevenir CSRF
if (!$code || !$state || $state !== ($_SESSION['github_state'] ?? '')) {
    flash('error', 'Autenticação GitHub inválida. Tenta novamente.');
    header('Location: ' . SITE_URL . '/pages/login.php');
    exit;
}
unset($_SESSION['github_state']);

// Trocar code por access_token
$resp = @file_get_contents('https://github.com/login/oauth/access_token', false, stream_context_create([
    'http' => [
        'method'  => 'POST',
        'header'  => "Content-Type: application/x-www-form-urlencoded\r\nAccept: application/json\r\n",
        'content' => http_build_query([
            'client_id'     => GITHUB_CLIENT_ID,
            'client_secret' => GITHUB_CLIENT_SECRET,
            'code'          => $code,
        ])
    ]
]));

if (!$resp) {
    flash('error', 'Erro ao comunicar com o GitHub.');
    header('Location: ' . SITE_URL . '/pages/login.php');
    exit;
}

$token_data = json_decode($resp, true);
$access_token = $token_data['access_token'] ?? '';

if (!$access_token) {
    flash('error', 'Não foi possível obter o token do GitHub.');
    header('Location: ' . SITE_URL . '/pages/login.php');
    exit;
}

// Obter dados do utilizador
$user_resp = @file_get_contents('https://api.github.com/user', false, stream_context_create([
    'http' => [
        'method' => 'GET',
        'header' => "Authorization: Bearer $access_token\r\nUser-Agent: SegredoLusitano\r\nAccept: application/json\r\n",
    ]
]));

if (!$user_resp) {
    flash('error', 'Não foi possível obter os dados do GitHub.');
    header('Location: ' . SITE_URL . '/pages/login.php');
    exit;
}

$github_user = json_decode($user_resp, true);
$github_id   = (string)($github_user['id']    ?? '');
$github_name = (string)($github_user['name']  ?? $github_user['login'] ?? '');
$github_login= (string)($github_user['login'] ?? '');

// Obter email (pode ser privado, precisa de pedido extra)
$email = (string)($github_user['email'] ?? '');
if (!$email) {
    $emails_resp = @file_get_contents('https://api.github.com/user/emails', false, stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => "Authorization: Bearer $access_token\r\nUser-Agent: SegredoLusitano\r\nAccept: application/json\r\n",
        ]
    ]));
    if ($emails_resp) {
        $emails = json_decode($emails_resp, true);
        foreach ($emails as $e) {
            if (!empty($e['primary']) && !empty($e['verified'])) {
                $email = $e['email'];
                break;
            }
        }
    }
}

if (!$email) {
    flash('error', 'Não foi possível obter o email da conta GitHub. Certifica-te de que tens um email público ou verificado.');
    header('Location: ' . SITE_URL . '/pages/login.php');
    exit;
}

// Verificar se já existe conta com este email
$st = db()->prepare('SELECT * FROM utilizadores WHERE email = ? AND ativo = 1');
$st->execute([$email]);
$user = $st->fetch();

if ($user) {
    // Verificar se está suspenso
    if (!(int)$user['ativo']) {
        flash('error', 'Conta suspensa. Contacta o administrador.');
        header('Location: ' . SITE_URL . '/pages/login.php');
        exit;
    }
    // Login na conta existente
    $_SESSION['user_id'] = $user['id'];
    flash('success', 'Bem-vindo de volta, ' . $user['nome'] . '!');
    header('Location: ' . SITE_URL . '/index.php');
    exit;
}

// Criar conta nova
$nome     = $github_name ?: $github_login;
$username = $github_login;

// Verificar se username já existe e adicionar sufixo se necessário
$st2 = db()->prepare('SELECT id FROM utilizadores WHERE username = ?');
$st2->execute([$username]);
if ($st2->fetch()) {
    $username = $username . '_gh';
}

$password_hash = password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT, ['cost' => 12]);

$termos_em = $_SESSION['github_termos_aceites_em'] ?? null;
unset($_SESSION['github_termos_aceites_em']);

if (!$termos_em) {
    // Conta nova sem termos aceites — guardar dados e redirecionar para aceitar termos
    $_SESSION['github_pendente'] = [
        'nome'          => $nome,
        'email'         => $email,
        'username'      => $username,
        'password_hash' => $password_hash,
    ];
    flash('info', 'Para criar a tua conta com GitHub, aceita primeiro os Termos e Condições.');
    header('Location: ' . SITE_URL . '/pages/registo.php?continuar=github');
    exit;
}

$st3 = db()->prepare('INSERT INTO utilizadores (nome, username, email, password, verificado, pontos, tipo_auth, termos_aceites_em) VALUES (?,?,?,?,1,0,"github",?)');
$st3->execute([$nome, $username, $email, $password_hash, $termos_em]);
$new_id = (int)db()->lastInsertId();

$_SESSION['user_id'] = $new_id;
flash('success', 'Conta criada com GitHub, bem-vindo à comunidade!');
header('Location: ' . SITE_URL . '/index.php');
exit;