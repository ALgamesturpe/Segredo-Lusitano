<?php
// ============================================================
// TESTE DE CONEXÃO SMTP COM DEBUG
// ============================================================

require_once 'includes/config.php';

echo "═════════════════════════════════════════════════════\n";
echo "🔍 TESTE DE CONEXÃO SMTP\n";
echo "═════════════════════════════════════════════════════\n\n";

// Carrega PHPMailer
require_once 'lib/PHPMailer/src/Exception.php';
require_once 'lib/PHPMailer/src/PHPMailer.php';
require_once 'lib/PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

echo "1️⃣  Configuração:\n";
echo "   Host: " . MAIL_HOST . "\n";
echo "   Porta: " . MAIL_PORT . "\n";
echo "   User: " . MAIL_USER . "\n";
echo "   From: " . MAIL_FROM . "\n";
echo "   Pass: " . (strlen(MAIL_PASS) . " caracteres\n");

echo "\n2️⃣  Testando conexão SMTP...\n\n";

$mail = new PHPMailer(true);

try {
    // Ativar DEBUG (mostra tudo)
    $mail->SMTPDebug = SMTP::DEBUG_LOWLEVEL;
    $mail->Debugoutput = 'html';
    
    // Configuração
    $mail->isSMTP();
    $mail->Host       = MAIL_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = MAIL_USER;
    $mail->Password   = MAIL_PASS;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = MAIL_PORT;
    $mail->CharSet    = 'UTF-8';
    
    // Email de teste
    $mail->setFrom(MAIL_FROM, 'Segredo Lusitano (Teste)');
    $mail->addAddress(MAIL_USER, 'Teste');
    
    $mail->Subject = '🧪 Teste de Verificação - Segredo Lusitano';
    $mail->Body = '<h1>Teste de Email</h1><p>Se recebeste este email, a configuração SMTP está correcta!</p>';
    $mail->AltBody = 'Teste de Email - Se recebeste este, SMTP ok!';
    
    echo "Enviando email de teste para: " . MAIL_USER . "\n\n";
    
    if ($mail->send()) {
        echo "\n\n✅ EMAIL ENVIADO COM SUCESSO!\n";
        echo "   O sistema de verificação deve funcionar agora.\n";
    } else {
        echo "\n\n❌ FALHA AO ENVIAR\n";
        echo "   Erro: " . $mail->ErrorInfo . "\n";
    }
    
} catch (Exception $e) {
    echo "\n\n❌ ERRO:\n";
    echo "   " . $e->getMessage() . "\n";
    echo "\n   ErrorInfo: " . $mail->ErrorInfo . "\n";
}

echo "\n═════════════════════════════════════════════════════\n\n";

echo "💡 DICAS DE TROUBLESHOOTING:\n\n";

echo "❌ Se diz 'Certificate Authority file not found':\n";
echo "   → Vai a: C:\\xampp\\php\\php.ini\n";
echo "   → Procura: openssl.cafile\n";
echo "   → Certifica-te que está descomentado (sem ;)\n\n";

echo "❌ Se diz 'network connection error':\n";
echo "   → Verifica password do Gmail (deve ser App Password)\n";
echo "   → Tenta: https://myaccount.google.com/apppasswords\n\n";

echo "❌ Se diz 'Authentication failed':\n";
echo "   → Email ou senha incorretos\n";
echo "   → Gmail pode ter bloqueado a ligação\n";
echo "   → Tenta desativar 2FA ou usar App Password\n\n";

?>
