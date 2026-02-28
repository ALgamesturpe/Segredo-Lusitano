<?php
// ============================================================
// SEGREDO LUSITANO - Envio de Email (PHPMailer)
// ============================================================
// Instalar PHPMailer: na pasta do projeto, executar:
//   composer require phpmailer/phpmailer
// Ou descarregar manualmente de: https://github.com/PHPMailer/PHPMailer
// ============================================================

require_once __DIR__ . '/config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Autoload do Composer (se instalado)
$composerAutoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($composerAutoload)) {
    require_once $composerAutoload;
} else {
    // Fallback manual: a pasta lib/PHPMailer deve conter os ficheiros src/Exception.php, PHPMailer.php, SMTP.php
    $phplib = __DIR__ . '/../lib/PHPMailer/src';
    if (file_exists($phplib . '/Exception.php')) {
        require_once $phplib . '/Exception.php';
        require_once $phplib . '/PHPMailer.php';
        require_once $phplib . '/SMTP.php';
    }
}

// --- stub classes when PHPMailer is not available --------------------------------------
if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
    // minimal replacements, with aliases to the expected namespace so 'use' statements still work
    class PHPMailerStub {
        public function __construct($exceptions = false) {}
        public function isSMTP() { return $this; }
        public function setFrom(...$args) { return $this; }
        public function addAddress(...$args) { return $this; }
        public function send() {
            error_log('PHPMailer não instalado: email não enviado');
            return false;
        }
        public function __call($name, $args) { return $this; }
    }
    class SMTPStub {}

    // aliases into the PHPMailer\PHPMailer namespace
    class_alias('PHPMailerStub', 'PHPMailer\\PHPMailer\\PHPMailer');
    class_alias('SMTPStub', 'PHPMailer\\PHPMailer\\SMTP');
    class_alias('\\Exception', 'PHPMailer\\PHPMailer\\Exception');
}

/**
 * Envia email com código de verificação
 */
function enviar_codigo_verificacao(string $email, string $nome, string $codigo, string $tipo = 'registo'): bool {
    $mail = new PHPMailer(true);
    try {
        // Configuração SMTP
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USER;
        $mail->Password   = MAIL_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = MAIL_PORT;
        $mail->CharSet    = 'UTF-8';

        // Remetente e destinatário
        $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
        $mail->addAddress($email, $nome);

        // Assunto
        $assunto = $tipo === 'login'
            ? 'O teu código de acesso — Segredo Lusitano'
            : 'Confirma a tua conta — Segredo Lusitano';
        $mail->Subject = $assunto;

        // Corpo do email em HTML
        $mail->isHTML(true);
        $mail->Body = email_template($nome, $codigo, $tipo);
        $mail->AltBody = "Olá $nome,\n\nO teu código é: $codigo\n\nExpira em 15 minutos.\n\nSegredo Lusitano";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('Erro ao enviar email: ' . $mail->ErrorInfo);
        return false;
    }
}

/**
 * Gera um código de 6 dígitos, guarda na BD e retorna o código
 */
function gerar_e_guardar_codigo(int $utilizador_id, string $tipo = 'registo'): string {
    // Invalidar códigos anteriores do mesmo tipo
    db()->prepare('UPDATE codigos_verificacao SET usado = 1 WHERE utilizador_id = ? AND tipo = ? AND usado = 0')
         ->execute([$utilizador_id, $tipo]);

    // Gerar código de 6 dígitos
    $codigo = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

    // Expira em 15 minutos
    $expira = date('Y-m-d H:i:s', strtotime('+15 minutes'));

    db()->prepare('INSERT INTO codigos_verificacao (utilizador_id, codigo, tipo, expira_em) VALUES (?,?,?,?)')
         ->execute([$utilizador_id, $codigo, $tipo, $expira]);

    return $codigo;
}

/**
 * Verifica se o código é válido
 */
function verificar_codigo(int $utilizador_id, string $codigo, string $tipo = 'registo'): bool {
    $st = db()->prepare('
        SELECT id FROM codigos_verificacao
        WHERE utilizador_id = ? AND codigo = ? AND tipo = ?
          AND usado = 0 AND expira_em > NOW()
        ORDER BY id DESC LIMIT 1
    ');
    $st->execute([$utilizador_id, $codigo, $tipo]);
    $row = $st->fetch();

    if ($row) {
        // Marcar como usado
        db()->prepare('UPDATE codigos_verificacao SET usado = 1 WHERE id = ?')
             ->execute([$row['id']]);
        return true;
    }
    return false;
}

/**
 * Template HTML do email
 */
function email_template(string $nome, string $codigo, string $tipo): string {
    $titulo = $tipo === 'login' ? 'Código de Acesso' : 'Confirma a tua Conta';
    $msg    = $tipo === 'login'
        ? 'Usa este código para concluir o teu início de sessão.'
        : 'Usa este código para ativar a tua conta de explorador.';

    // Código dividido em dígitos individuais para melhor visual
    $digitos = '';
    foreach (str_split($codigo) as $d) {
        $digitos .= "<span style='display:inline-block;width:44px;height:54px;line-height:54px;text-align:center;
                     background:#1a3a2a;color:#c9a84c;font-size:1.6rem;font-weight:700;
                     border:2px solid #c9a84c;border-radius:8px;margin:0 3px;
                     font-family:monospace;'>$d</span>";
    }

    return "
<!DOCTYPE html>
<html lang='pt'>
<head><meta charset='UTF-8'><meta name='viewport' content='width=device-width,initial-scale=1'></head>
<body style='margin:0;padding:0;background:#f5efe6;font-family:Arial,sans-serif;'>
  <table width='100%' cellpadding='0' cellspacing='0' style='background:#f5efe6;padding:40px 20px;'>
    <tr><td align='center'>
      <table width='560' cellpadding='0' cellspacing='0' style='background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.1);max-width:100%;'>

        <!-- Cabeçalho -->
        <tr>
          <td style='background:#1a3a2a;padding:32px;text-align:center;border-bottom:3px solid #c9a84c;'>
            <div style='font-family:Georgia,serif;font-size:1.5rem;color:#c9a84c;font-weight:700;letter-spacing:.05em;'>
              🧭 Segredo Lusitano
            </div>
            <div style='color:#a8c5b0;font-size:.85rem;margin-top:.4rem;'>Descobre o Portugal escondido</div>
          </td>
        </tr>

        <!-- Corpo -->
        <tr>
          <td style='padding:40px 48px;text-align:center;'>
            <h1 style='font-family:Georgia,serif;color:#1a3a2a;font-size:1.6rem;margin:0 0 .5rem;'>$titulo</h1>
            <p style='color:#555;font-size:1rem;margin:0 0 2rem;'>Olá <strong>$nome</strong>! $msg</p>

            <!-- Código -->
            <div style='margin:1.5rem 0;'>$digitos</div>

            <p style='color:#888;font-size:.85rem;margin:1.5rem 0 0;'>
              ⏱ Este código expira em <strong>15 minutos</strong>.
            </p>
            <p style='color:#aaa;font-size:.8rem;margin:.5rem 0 0;'>
              Se não foste tu, ignora este email.
            </p>
          </td>
        </tr>

        <!-- Rodapé -->
        <tr>
          <td style='background:#f0ebe3;padding:20px 48px;text-align:center;border-top:1px solid #ddd;'>
            <p style='color:#999;font-size:.78rem;margin:0;'>
              © " . date('Y') . " Segredo Lusitano &mdash; Todos os direitos reservados
            </p>
          </td>
        </tr>

      </table>
    </td></tr>
  </table>
</body>
</html>";
}
