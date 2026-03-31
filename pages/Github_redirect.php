<?php
// ============================================================
// SEGREDO LUSITANO — GitHub OAuth Redirect
// ============================================================
require_once dirname(__DIR__) . '/includes/auth.php';

if (auth_user()) {
    header('Location: ' . SITE_URL . '/index.php');
    exit;
}

// Gerar state aleatório para prevenir CSRF
$state = bin2hex(random_bytes(16));
$_SESSION['github_state'] = $state;

$params = http_build_query([
    'client_id' => GITHUB_CLIENT_ID,
    'scope'     => 'user:email',
    'state'     => $state,
]);

header('Location: https://github.com/login/oauth/authorize?' . $params);
exit;