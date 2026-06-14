<?php
// ============================================================
// SEGREDO LUSITANO - Configuração da Base de Dados
// ============================================================
$host = "localhost";
$dbname = "segredol_1";
$username = "segredol_1";
$password = "#Segredo-lusitano";

define('DB_HOST', $host);
define('DB_NAME', $dbname);
define('DB_USER', $username);
define('DB_PASS', $password);

define('SITE_NAME', 'Segredo Lusitano');
define('GITHUB_CLIENT_ID', 'Ov23ctMdjdh1VqlMWmgS');
define('GITHUB_CLIENT_SECRET', 'f659d9ab53b4e98c4d4a9d16f6049ffaa8c82890');

// Automatically detect SITE_URL based on current script location
if (!defined('SITE_URL')) {
    // Detetar HTTPS — inclui o caso do ngrok que envia X-Forwarded-Proto
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        || (($_SERVER['SERVER_PORT'] ?? '') == 443);
    $protocol = $isHttps ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptPath = dirname($_SERVER['SCRIPT_NAME']);

    // Remove trailing subdirectories (includes, pages, admin) to get project root
    $basePath = $scriptPath;
    $basePath = preg_replace('#/(includes|pages|admin)$#', '', $basePath);

    define('SITE_URL', $protocol . $host . $basePath);
}

define('UPLOAD_DIR', __DIR__ . '/../uploads/locais/');
define('PONTOS_LOCAL',      20);
define('PONTOS_LIKE',       5);
define('PONTOS_COMENTARIO', 1);

// ============================================================
// GOOGLE SIGN-IN
// Obtém em: https://console.cloud.google.com → APIs → Credenciais
// ============================================================
define('GOOGLE_CLIENT_ID', '912763585849-13julq7vmr2aepsp9kep35bpaifvisim.apps.googleusercontent.com');

// ============================================================
// CONFIGURAÇÃO DE EMAIL (SMTP)
// ============================================================
define('MAIL_HOST',      'mail.segredolusitano.pt');
define('MAIL_PORT',      587);
define('MAIL_USER',      'envio_de_email@segredolusitano.pt');
define('MAIL_PASS',      'KuxNgWy,iQ0S1pLd');
define('MAIL_FROM',      'envio_de_email@segredolusitano.pt');
define('MAIL_FROM_NAME', 'Segredo Lusitano');

// ============================================================
// CONFIGURAÇÃO DE SMS (Twilio) - OPCIONAL
// ============================================================
define('SMS_ENABLED',        false);
define('SMS_DEFAULT_COUNTRY','PT');
define('TWILIO_ACCOUNT_SID', 'ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx');
define('TWILIO_AUTH_TOKEN',  '0a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p');
define('TWILIO_PHONE',       '+12345678901');

// Migração: adicionar colunas de localização de registo
function _migrar_localizacao(): void {
    static $done = false;
    if ($done) return;
    $done = true;
    $cols = ['pais_registo VARCHAR(100)', 'regiao_registo VARCHAR(100)', 'cidade_registo VARCHAR(100)'];
    foreach ($cols as $col) {
        try { db()->exec("ALTER TABLE utilizadores ADD COLUMN $col NULL"); } catch (\Exception $e) {}
    }
}

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    }
    return $pdo;
}
