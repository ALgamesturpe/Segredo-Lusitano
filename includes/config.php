<?php
// ============================================================
// SEGREDO LUSITANO - Configuração da Base de Dados
// ============================================================
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'segredo_lusitano');
define('SITE_NAME', 'Segredo Lusitano');

// SITE_URL automático — baseia-se no nome da pasta do projeto
// config.php está em /includes/, por isso dirname(__DIR__) = pasta raiz do projeto
$_protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$_host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
$_folder   = basename(dirname(__DIR__)); // nome da pasta: "segredo_lusitano"
define('SITE_URL', $_protocol . '://' . $_host . '/' . $_folder);
unset($_protocol, $_host, $_folder);

define('UPLOAD_DIR', __DIR__ . '/../uploads/locais/');
define('PONTOS_LOCAL',      20);
define('PONTOS_COMENTARIO', 5);
define('PONTOS_LIKE',       2);

// ============================================================
// GOOGLE SIGN-IN
// Obtém em: https://console.cloud.google.com → APIs → Credenciais
// ============================================================
define('GOOGLE_CLIENT_ID',     'O_TEU_CLIENT_ID_AQUI.apps.googleusercontent.com');
define('GOOGLE_CLIENT_SECRET', 'O_TEU_CLIENT_SECRET_AQUI');
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
