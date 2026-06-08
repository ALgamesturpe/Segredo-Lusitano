<?php
// ============================================================
// SEGREDO LUSITANO - Funções de Autenticação
// ============================================================
require_once __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function auth_user(): ?array {
    if (!isset($_SESSION['user_id'])) return null;

    static $cache = false; // false = ainda não verificado; null = não encontrado; array = utilizador
    if ($cache !== false) return $cache;

    // Invalidar sessão se a base de dados foi reiniciada — corre apenas uma vez
    try {
        $token = db()->query("SELECT valor FROM app_meta WHERE nome='reset_token'")->fetchColumn();
        if ($token !== false) {
            if (!isset($_SESSION['reset_token'])) {
                $_SESSION['reset_token'] = $token;
            } elseif ($_SESSION['reset_token'] !== $token) {
                $_SESSION = [];
                session_destroy();
                $cache = null;
                return null;
            }
        }
    } catch (\Exception $e) {
        // app_meta pode não existir em bases de dados antigas
    }
    if ($cache) return $cache;

    // Buscar sem filtro ativo para distinguir suspensão de conta inexistente
    $st = db()->prepare('SELECT * FROM utilizadores WHERE id = ? AND role != "[deleted]"');
    $st->execute([$_SESSION['user_id']]);
    $user = $st->fetch() ?: null;

    if (!$user) {
        // Conta banida/eliminada — limpar sessão silenciosamente
        $_SESSION = [];
        session_destroy();
        return null;
    }

    if (!$user['ativo']) {
        // Conta suspensa — notificar e redirecionar para login
        $_SESSION = [];
        session_destroy();
        session_start();
        flash('error', 'A tua conta foi suspensa pelo administrador.');
        if (!headers_sent()) {
            header('Location: ' . SITE_URL . '/pages/login.php');
            exit;
        }
        return null;
    }

    $cache = $user;
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
    // Buscar utilizador pelo email, excluindo contas fantasma [deleted]
    $st = db()->prepare('SELECT * FROM utilizadores WHERE email = ? AND role != "[deleted]"');
    $st->execute([$email]);
    $user = $st->fetch();

    // Verificar se a conta existe e a password está correta
    if (!$user || !password_verify($password, $user['password'])) {
        return ['ok' => false, 'msg' => 'Email ou password incorretos.'];
    }
    // Verificar se a conta está suspensa
    if (!$user['ativo']) {
        return ['ok' => false, 'msg' => 'suspenso'];
    }
    // Verificar se a conta está verificada por email
    if (!$user['verificado']) {
        // Conta não verificada há mais de 24h: apagar e tratar como inexistente
        if (strtotime($user['criado_em']) < time() - 86400) {
            db()->prepare('DELETE FROM codigos_verificacao WHERE utilizador_id = ?')->execute([$user['id']]);
            db()->prepare('DELETE FROM utilizadores WHERE id = ?')->execute([$user['id']]);
            return ['ok' => false, 'msg' => 'Email ou password incorretos.'];
        }
        return ['ok' => false, 'verificar' => true, 'id' => $user['id'], 'msg' => 'Conta não verificada.'];
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    return ['ok' => true];
}

function logout(): void {
    session_destroy();
    header('Location: ' . SITE_URL . '/index.php');
    exit;
}

function register(string $nome, string $username, string $email, string $password, ?string $termos_aceites_em = null): array {
    // Se o email já existe mas não foi verificado, apagar conta fantasma e permitir novo registo
    $st = db()->prepare('SELECT id, verificado FROM utilizadores WHERE email = ?');
    $st->execute([$email]);
    $existing_email = $st->fetch();
    if ($existing_email) {
        if ($existing_email['verificado']) {
            return ['ok' => false, 'msg' => 'Email já registado.'];
        }
        db()->prepare('DELETE FROM codigos_verificacao WHERE utilizador_id = ?')->execute([$existing_email['id']]);
        db()->prepare('DELETE FROM utilizadores WHERE id = ?')->execute([$existing_email['id']]);
    }

    // Verificar username separadamente
    $st = db()->prepare('SELECT id FROM utilizadores WHERE username = ?');
    $st->execute([$username]);
    if ($st->fetch()) {
        return ['ok' => false, 'msg' => 'Username já registado.'];
    }

    $password_hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    $st = db()->prepare('INSERT INTO utilizadores (nome, username, email, password, verificado, pontos, termos_aceites_em) VALUES (?,?,?,?,0,0,?)');
    $st->execute([$nome, $username, $email, $password_hash, $termos_aceites_em]);
    $new_id = (int) db()->lastInsertId();
    guardar_localizacao_registo($new_id);
    return ['ok' => true, 'id' => $new_id];
}

function add_pontos(int $user_id, int $pontos): void {
    db()->prepare('UPDATE utilizadores SET pontos = pontos + ? WHERE id = ?')
         ->execute([$pontos, $user_id]);
}

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function csrf_token(): string {
    return $_SESSION['csrf_token'] ?? '';
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
}

function verificar_csrf(): void {
    $enviado = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!$enviado || !hash_equals($_SESSION['csrf_token'] ?? '', $enviado)) {
        http_response_code(403);
        if (!headers_sent()) header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'erro' => 'Token CSRF inválido. Recarrega a página.']);
        exit;
    }
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
