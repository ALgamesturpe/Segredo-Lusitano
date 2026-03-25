<?php
// ============================================================
// SEGREDO LUSITANO - Envio de Email + SMS
// ============================================================
// PHPMailer (já configurado): composer require phpmailer/phpmailer
// SMS (opcional): composer require twilio/sdk
// Vê CONFIGURACAO_MAILER.md para passo a passo completo
// ============================================================

require_once __DIR__ . '/config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

$phpmailer_disponivel = false;

// 1) Composer autoload (pasta raiz do projeto)
$composerAutoload = dirname(__DIR__) . '/vendor/autoload.php';
if (file_exists($composerAutoload)) {
    require_once $composerAutoload;
    $phpmailer_disponivel = class_exists(PHPMailer::class);
}

// 2) Fallback manual (lib/PHPMailer/src)
if (!$phpmailer_disponivel) {
    $caminhosManuais = [
        dirname(__DIR__) . '/lib/PHPMailer/src',
        __DIR__ . '/lib/PHPMailer/src',
    ];

    foreach ($caminhosManuais as $srcPath) {
        if (
            file_exists($srcPath . '/Exception.php') && file_exists($srcPath . '/PHPMailer.php') && file_exists($srcPath . '/SMTP.php')
        ) {
            require_once $srcPath . '/Exception.php';
            require_once $srcPath . '/PHPMailer.php';
            require_once $srcPath . '/SMTP.php';
            $phpmailer_disponivel = class_exists(PHPMailer::class);
            if ($phpmailer_disponivel) {
                break;
            }
        }
    }
}

if (!$phpmailer_disponivel) {
    error_log('PHPMailer nao encontrado. Instala com: composer require phpmailer/phpmailer');
}

// ============================================================
// VERIFICAÇÃO DE TWILIO (SMS) - OPCIONAL
// ============================================================
$twilio_disponivel = false;
$twilio_client = null;

if (SMS_ENABLED) {
    $composerAutoloadTwilio = dirname(__DIR__) . '/vendor/autoload.php';
    if (file_exists($composerAutoloadTwilio)) {
        require_once $composerAutoloadTwilio;
        if (class_exists('Twilio\Rest\Client')) {
            try {
                $twilio_client = new \Twilio\Rest\Client(TWILIO_ACCOUNT_SID, TWILIO_AUTH_TOKEN);
                $twilio_disponivel = true;
            } catch (Exception $e) {
                error_log('Erro ao inicializar Twilio: ' . $e->getMessage());
            }
        }
    }
    
    if (!$twilio_disponivel) {
        error_log('SMS ativado mas Twilio nao encontrado. Instala com: composer require twilio/sdk');
    }
}

/*Envia email com código de verificação*/
function enviar_codigo_verificacao(string $email, string $nome, string $codigo, string $tipo = 'registo'): bool {
    if (!class_exists(PHPMailer::class)) {
        error_log('ERRO CRITICO: PHPMailer nao encontrado. Instala com: composer require phpmailer/phpmailer');
        // Set a session warning for the user
        if (session_status() === PHP_SESSION_NONE) session_start();
        $_SESSION['flash']['error'] = 'Sistema de email não configurado. Contacta o administrador.';
        return false;
    }

    $mail = new PHPMailer(true);
    try {
        // Configuração SMTP
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USER;
        $mail->Password   = MAIL_PASS;
        // ⚠️ XAMPP Local: usar SMTPS com contexto SSL que ignora certificado
        // Em produção, usar STARTTLS na porta 587
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = 465;
        $mail->CharSet    = 'UTF-8';
        
        // Opções SSL para ignorar erro de certificado em ambiente local
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true,
                'ciphers'           => 'DEFAULT'
            )
        );

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

        // Tentar enviar - pode falhar em XAMPP local
        if (!@$mail->send()) {
            // Se falhar, apenas log (não quebra a app)
            error_log('Email não enviado (fallback: conta marca como verificada) - ' . $mail->ErrorInfo);
            // Retornar true para permitir conta continuar (com fallback)
            return false;
        }
        return true;
    } catch (Exception $e) {
        error_log('Erro ao enviar email: ' . $e->getMessage() . ' - ' . $mail->ErrorInfo);
        if (session_status() === PHP_SESSION_NONE) session_start();
        $_SESSION['flash']['error'] = 'Email não enviado (verificação automática ativada).';
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
        : 'Este é o código de ativação.';

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
              Segredo Lusitano
            </div>
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
              Este código expira em <strong>15 minutos</strong>.
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
// ============================================================
// FUNÇÕES DE SMS (Twilio)
// ============================================================

/**
 * Normaliza número de telemóvel para formato +CC...
 * Exemplo: "912345678" → "+351912345678"
 */
function formatar_numero_telefone(string $numero, string $pais = 'PT'): string {
    // Remove espaços, hífens, parênteses
    $numero = preg_replace('/[\s\-\(\)]+/', '', $numero);
    
    // Se já tem +, apenas remove caracteres inválidos
    if (strpos($numero, '+') === 0) {
        return $numero;
    }
    
    // Indicativos de país comuns
    $indicativos = [
        'PT' => '351',  // Portugal
        'BR' => '55',   // Brasil
        'AO' => '244',  // Angola
        'MZ' => '258',  // Moçambique
        'CV' => '238',  // Cabo Verde
    ];
    
    $codigo = $indicativos[$pais] ?? '351'; // Default para Portugal
    
    // Se começa com 0, remove (formato local PT)
    if (strpos($numero, '0') === 0) {
        $numero = substr($numero, 1);
    }
    
    // Adiciona +indicativo
    return '+' . $codigo . $numero;
}

/**
 * Envia código de verificação por SMS (Twilio)
 */
function enviar_codigo_sms(string $numero_telemovel, string $codigo, string $tipo = 'registo'): bool {
    global $twilio_client, $twilio_disponivel;
    
    if (!SMS_ENABLED) {
        error_log('SMS não está ativado. Ativa em config.php: SMS_ENABLED = true');
        if (session_status() === PHP_SESSION_NONE) session_start();
        $_SESSION['flash']['error'] = 'SMS não está disponível neste momento.';
        return false;
    }
    
    if (!$twilio_disponivel || !$twilio_client) {
        error_log('Twilio não está disponível. Instala com: composer require twilio/sdk');
        if (session_status() === PHP_SESSION_NONE) session_start();
        $_SESSION['flash']['error'] = 'Sistema de SMS não configurado. Contacta o administrador.';
        return false;
    }
    
    try {
        // Formata o número de telemóvel
        $numero = formatar_numero_telefone($numero_telemovel, SMS_DEFAULT_COUNTRY);
        
        // Mensagem do SMS
        $titulo_tipo = ($tipo === 'login') ? 'acesso' : 'confirmação de conta';
        $mensagem = "Segredo Lusitano - Código de $titulo_tipo: $codigo (válido 15 min)";
        
        // Envia SMS via Twilio
        $message = $twilio_client->messages->create(
            $numero,  // Para (destinatário)
            [
                'from' => TWILIO_PHONE,
                'body' => $mensagem
            ]
        );
        
        error_log('SMS enviado com sucesso. SID: ' . $message->sid);
        return true;
        
    } catch (\Twilio\Exceptions\TwilioException $e) {
        error_log('Erro ao enviar SMS: ' . $e->getMessage());
        if (session_status() === PHP_SESSION_NONE) session_start();
        $_SESSION['flash']['error'] = 'Erro ao enviar SMS. Verifica o número de telemóvel.';
        return false;
    } catch (Exception $e) {
        error_log('Erro geral ao enviar SMS: ' . $e->getMessage());
        if (session_status() === PHP_SESSION_NONE) session_start();
        $_SESSION['flash']['error'] = 'Erro ao enviar SMS. Tenta novamente.';
        return false;
    }
}

/**
 * Helper: envia código por EMAIL ou SMS
 * Uso: enviar_codigo($email, $nome, $numero_sms, $codigo, 'email')
 */
function enviar_codigo(
    string $email,
    string $nome,
    string $numero_sms,
    string $codigo,
    string $metodo = 'email',
    string $tipo = 'registo'
): bool {
    if ($metodo === 'sms') {
        return enviar_codigo_sms($numero_sms, $codigo, $tipo);
    } else {
        return enviar_codigo_verificacao($email, $nome, $codigo, $tipo);
    }
}

/**
 * Limpa códigos de verificação expirados (executar periodicamente)
 * Chamada automática no início de registo/login
 */
function limpar_codigos_expirados(): void {
    db()->prepare('DELETE FROM codigos_verificacao WHERE expira_em < NOW()')->execute();
}