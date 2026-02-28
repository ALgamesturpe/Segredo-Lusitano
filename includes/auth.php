<?php
// ============================================================
// SEGREDO LUSITANO - Funções de Autenticação
// ============================================================
require_once __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function auth_user(): ?array {
    if (!isset($_SESSION['user_id'])) return null;
    static $cache = null;
    if ($cache) return $cache;
    $st = db()->prepare('SELECT * FROM utilizadores WHERE id = ? AND ativo = 1');
    $st->execute([$_SESSION['user_id']]);
    $cache = $st->fetch() ?: null;
    return $cache;
}

function require_login(): void {
    if (!auth_user()) {
        header('Location: ' . SITE_URL . '/pages/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
        exit;
    }
}

function require_admin(): void {
    $u = auth_user();
    if (!$u || $u['role'] !== 'admin') {
        header('Location: ' . SITE_URL . '/index.php');
        exit;
    }
}

function is_admin(): bool {
    $u = auth_user();
    return $u && $u['role'] === 'admin';
}

function login(string $email, string $password): array {
    $st = db()->prepare('SELECT * FROM utilizadores WHERE email = ? AND ativo = 1');
    $st->execute([$email]);
    $user = $st->fetch();
    if (!$user || !password_verify($password, $user['password'])) {
        return ['ok' => false, 'msg' => 'Email ou password incorretos.'];
    }
    // Verificar se a conta está verificada
    if (!$user['verificado']) {
        return ['ok' => false, 'verificar' => true, 'id' => $user['id'], 'msg' => 'Conta não verificada.'];
    }
    $_SESSION['user_id'] = $user['id'];
    return ['ok' => true];
}

function logout(): void {
    session_destroy();
    header('Location: ' . SITE_URL . '/index.php');
    exit;
}

function register(string $nome, string $username, string $email, string $password): array {
    $st = db()->prepare('SELECT id FROM utilizadores WHERE email = ? OR username = ?');
    $st->execute([$email, $username]);
    if ($st->fetch()) {
        return ['ok' => false, 'msg' => 'Email ou username já registado.'];
    }
    $hash = password_hash($password, PASSWORD_BCRYPT);
    // verificado = 0 por defeito — precisa de confirmar o email
    $st = db()->prepare('INSERT INTO utilizadores (nome, username, email, password, verificado) VALUES (?,?,?,?,0)');
    $st->execute([$nome, $username, $email, $hash]);
    return ['ok' => true, 'id' => (int) db()->lastInsertId()];
}

function add_pontos(int $user_id, int $pontos): void {
    db()->prepare('UPDATE utilizadores SET pontos = pontos + ? WHERE id = ?')
         ->execute([$pontos, $user_id]);
}

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function flash(string $key, string $msg = ''): string {
    if ($msg !== '') {
        $_SESSION['flash'][$key] = $msg;
        return '';
    }
    $out = $_SESSION['flash'][$key] ?? '';
    unset($_SESSION['flash'][$key]);
    return $out;
}
