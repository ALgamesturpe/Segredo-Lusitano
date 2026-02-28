<?php
// ============================================================
// SEGREDO LUSITANO - Configuração da Base de Dados
// ============================================================
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'segredo_lusitano');
define('SITE_NAME', 'Segredo Lusitano');

// Automatically detect SITE_URL based on current script location
if (!defined('SITE_URL')) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptPath = dirname($_SERVER['SCRIPT_NAME']);

    // Remove trailing subdirectories (includes, pages, admin) to get project root
    $basePath = $scriptPath;
    $basePath = preg_replace('#/(includes|pages|admin)$#', '', $basePath);

    define('SITE_URL', $protocol . $host . $basePath);
}

define('UPLOAD_DIR', __DIR__ . '/../uploads/locais/');
define('PONTOS_LOCAL',      20);
define('PONTOS_COMENTARIO', 5);
define('PONTOS_LIKE',       2);

// ============================================================
// GOOGLE SIGN-IN
// Obtém em: https://console.cloud.google.com → APIs → Credenciais
// ============================================================
define('GOOGLE_CLIENT_ID', '912763585849-sh3s7jrhi36toua034jgci2ktp3cge3k.apps.googleusercontent.com');
// ============================================================
// CONFIGURAÇÃO DE EMAIL (SMTP)
// Preenche com os dados do teu email
// ============================================================
define('MAIL_HOST',     'smtp.gmail.com');   // servidor SMTP
define('MAIL_PORT',     587);                // porta (587 = TLS)
define('MAIL_USER',     'o.teu@gmail.com');  // ← o teu email
define('MAIL_PASS',     'a_tua_password');   // ← password de app Gmail
define('MAIL_FROM',     'o.teu@gmail.com');  // remetente
define('MAIL_FROM_NAME','Segredo Lusitano'); // nome do remetente

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
