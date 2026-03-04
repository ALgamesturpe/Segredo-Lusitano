<?php
// ============================================================
// ARQUIVO DE TESTE - Verificar se Email/SMS estão funcionando
// ============================================================
// Uso: Acede a http://localhost/Segredo-Lusitano/test_mailer.php
// ============================================================

require_once 'includes/config.php';
require_once 'includes/mailer.php';

$resultado_email = null;
$resultado_sms = null;
$erro_email = null;
$erro_sms = null;

// ============================================================
// TESTE DE EMAIL
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_email'])) {
    $email_teste = trim($_POST['email_teste'] ?? '');
    $nome_teste = trim($_POST['nome_teste'] ?? 'Utilizador Teste');
    
    if (empty($email_teste)) {
        $erro_email = '❌ Email vazio!';
    } elseif (!filter_var($email_teste, FILTER_VALIDATE_EMAIL)) {
        $erro_email = '❌ Email inválido!';
    } else {
        $codigo_teste = '123456';
        
        if (enviar_codigo_verificacao($email_teste, $nome_teste, $codigo_teste, 'registo')) {
            $resultado_email = '✅ Email enviado com sucesso!';
        } else {
            $erro_email = '❌ Erro ao enviar email. Verifique as configurações SMTP e os logs.';
        }
    }
}

// ============================================================
// TESTE DE SMS
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_sms'])) {
    if (!SMS_ENABLED) {
        $erro_sms = '❌ SMS não está ativado em config.php (SMS_ENABLED = false)';
    } else {
        $numero_teste = trim($_POST['numero_teste'] ?? '');
        
        if (empty($numero_teste)) {
            $erro_sms = '❌ Número de telemóvel vazio!';
        } else {
            $codigo_teste = '123456';
            
            if (enviar_codigo_sms($numero_teste, $codigo_teste, 'registo')) {
                $resultado_sms = '✅ SMS enviado com sucesso!';
            } else {
                $erro_sms = '❌ Erro ao enviar SMS. Verifique as credenciais Twilio e os logs.';
            }
        }
    }
}

// ============================================================
// RESUMO DE CONFIGURAÇÃO
// ============================================================
$config_email = [
    'Host' => constant('MAIL_HOST'),
    'Porta' => constant('MAIL_PORT'),
    'Utilizador' => constant('MAIL_USER'),
    'Password' => str_repeat('*', min(5, strlen(constant('MAIL_PASS')))) . (strlen(constant('MAIL_PASS')) > 5 ? '...' : ''),
    'De' => constant('MAIL_FROM'),
];

$config_sms = [
    'Ativado' => constant('SMS_ENABLED') ? '✅ Sim' : '❌ Não',
    'Provider' => constant('SMS_PROVIDER'),
    'País Default' => constant('SMS_DEFAULT_COUNTRY'),
    'Twilio SID' => substr(constant('TWILIO_ACCOUNT_SID'), 0, 4) . '...',
];

?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🧪 Teste Mailer - Segredo Lusitano</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #1a3a2a 0%, #2d5a47 100%);
            color: #333;
            margin: 0;
            padding: 20px;
            min-height: 100vh;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
        }
        .header {
            text-align: center;
            color: #c9a84c;
            margin-bottom: 40px;
        }
        .header h1 {
            margin: 0;
            font-size: 2rem;
        }
        
        .section {
            background: white;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 20px;
            box-shadow: 0 4px 24px rgba(0, 0, 0, 0.15);
        }
        
        .section h2 {
            color: #1a3a2a;
            border-bottom: 3px solid #c9a84c;
            padding-bottom: 10px;
            margin-top: 0;
        }
        
        .config-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        
        .config-table th,
        .config-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        .config-table th {
            background: #f5efe6;
            font-weight: 600;
            color: #1a3a2a;
        }
        
        .config-table td:first-child {
            font-weight: 500;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            font-weight: 500;
            margin-bottom: 8px;
            color: #1a3a2a;
        }
        
        input[type="text"],
        input[type="email"],
        input[type="tel"] {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 6px;
            font-size: 1rem;
            box-sizing: border-box;
            transition: border-color 0.3s;
        }
        
        input[type="text"]:focus,
        input[type="email"]:focus,
        input[type="tel"]:focus {
            outline: none;
            border-color: #c9a84c;
            box-shadow: 0 0 0 3px rgba(201, 168, 76, 0.1);
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            font-size: 1rem;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: #1a3a2a;
            color: #c9a84c;
        }
        
        .btn-primary:hover {
            background: #0f2620;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }
        
        .alert {
            padding: 16px;
            border-radius: 6px;
            margin-bottom: 20px;
            border-left: 4px solid;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border-color: #28a745;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border-color: #f5c6cb;
        }
        
        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border-color: #bee5eb;
        }
        
        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        
        .status-ok {
            background: #d4edda;
            color: #155724;
        }
        
        .status-warning {
            background: #fff3cd;
            color: #856404;
        }
        
        .note {
            background: #f0ebe3;
            padding: 15px;
            border-radius: 6px;
            margin: 20px 0;
            border-left: 4px solid #c9a84c;
        }
        
        .note strong {
            color: #1a3a2a;
        }
        
        .logs {
            background: #1a3a2a;
            color: #c9a84c;
            padding: 15px;
            border-radius: 6px;
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            max-height: 300px;
            overflow-y: auto;
            margin-top: 15px;
        }
        
        .footer-note {
            text-align: center;
            color: #666;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🧪 Teste do Sistema de Email/SMS</h1>
            <p>Segredo Lusitano - Verificação de Configuração</p>
        </div>

        <!-- RESUMO DE CONFIGURAÇÃO -->
        <div class="section">
            <h2>📋 Resumo de Configuração</h2>
            
            <h3>📧 Email (PHPMailer)</h3>
            <table class="config-table">
                <tr>
                    <th>Parâmetro</th>
                    <th>Valor</th>
                </tr>
                <?php foreach ($config_email as $key => $value): ?>
                <tr>
                    <td><?php echo $key; ?></td>
                    <td><code><?php echo htmlspecialchars($value); ?></code></td>
                </tr>
                <?php endforeach; ?>
            </table>

            <h3>📱 SMS (Twilio)</h3>
            <table class="config-table">
                <tr>
                    <th>Parâmetro</th>
                    <th>Valor</th>
                </tr>
                <?php foreach ($config_sms as $key => $value): ?>
                <tr>
                    <td><?php echo $key; ?></td>
                    <td>
                        <?php 
                        if ($key === 'Ativado') {
                            echo $value;
                        } else {
                            echo '<code>' . htmlspecialchars($value) . '</code>';
                        }
                        ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>

        <!-- TESTE DE EMAIL -->
        <div class="section">
            <h2>📧 Teste de Email</h2>
            
            <?php if ($resultado_email): ?>
                <div class="alert alert-success"><?php echo $resultado_email; ?></div>
            <?php elseif ($erro_email): ?>
                <div class="alert alert-error"><?php echo $erro_email; ?></div>
            <?php endif; ?>

            <form method="post">
                <div class="form-group">
                    <label for="nome-teste">Nome (opcional):</label>
                    <input type="text" id="nome-teste" name="nome_teste" placeholder="Seu nome" value="Utilizador Teste">
                </div>

                <div class="form-group">
                    <label for="email-teste">Email para teste:</label>
                    <input type="email" id="email-teste" name="email_teste" placeholder="seu.email@gmail.com" required>
                </div>

                <button type="submit" name="test_email" class="btn btn-primary">📧 Enviar Email de Teste</button>
            </form>

            <div class="note">
                <strong>ℹ️ Instruções:</strong>
                <ol>
                    <li>Introduza um email válido (ideal: o seu email de teste)</li>
                    <li>Clique em "Enviar Email de Teste"</li>
                    <li>Verifique a caixa de entrada (+ pasta de SPAM)</li>
                    <li>O email conterá um código de exemplo: <code>123456</code></li>
                </ol>
            </div>
        </div>

        <!-- TESTE DE SMS -->
        <div class="section">
            <h2>📱 Teste de SMS</h2>
            
            <?php if ($resultado_sms): ?>
                <div class="alert alert-success"><?php echo $resultado_sms; ?></div>
            <?php elseif ($erro_sms): ?>
                <div class="alert alert-error"><?php echo $erro_sms; ?></div>
            <?php endif; ?>

            <?php if (!SMS_ENABLED): ?>
                <div class="alert alert-info">
                    ⚠️ SMS está desativado em <code>config.php</code>. 
                    Mude <code>SMS_ENABLED</code> para <code>true</code> para testar.
                </div>
            <?php else: ?>
                <form method="post">
                    <div class="form-group">
                        <label for="numero-teste">Número de Telemóvel:</label>
                        <input type="tel" id="numero-teste" name="numero_teste" placeholder="+351912345678 ou 912345678" required>
                        <small>Formato: +CC... ou sem código de país (assumirá <?php echo SMS_DEFAULT_COUNTRY; ?>)</small>
                    </div>

                    <button type="submit" name="test_sms" class="btn btn-primary">📱 Enviar SMS de Teste</button>
                </form>

                <div class="note">
                    <strong>ℹ️ Instruções:</strong>
                    <ol>
                        <li>Introduza um número de telemóvel válido</li>
                        <li>Clique em "Enviar SMS de Teste"</li>
                        <li>Verifique a caixa de SMS do seu telemóvel</li>
                        <li>O SMS conterá um código de exemplo: <code>123456</code></li>
                    </ol>
                </div>
            <?php endif; ?>
        </div>

        <!-- TROUBLESHOOTING -->
        <div class="section">
            <h2>🔧 Resolução de Problemas</h2>

            <h3>❌ Email não é enviado?</h3>
            <ul>
                <li>✓ Verifique se <code>MAIL_USER</code> e <code>MAIL_PASS</code> estão corretos</li>
                <li>✓ Confirme que utilizou uma <strong>App Password</strong> do Gmail (não a senha da conta)</li>
                <li>✓ Verifique se 2FA está ativado no Gmail</li>
                <li>✓ Tente desativar firewall temporariamente (porta 587)</li>
                <li>✓ Verifique os logs: <code>error_log()</code></li>
            </ul>

            <h3>❌ SMS não é enviado?</h3>
            <ul>
                <li>✓ Certifique-se que <code>SMS_ENABLED = true</code> em config.php</li>
                <li>✓ Verifique <code>TWILIO_ACCOUNT_SID</code> e <code>TWILIO_AUTH_TOKEN</code></li>
                <li>✓ Confirme que <code>TWILIO_PHONE</code> começa com <code>+</code></li>
                <li>✓ Verifique saldo da conta Twilio (modo trial ou pagante?)</li>
                <li>✓ O número destino está no formato correto? (ex: <code>+351912345678</code>)</li>
            </ul>

            <h3>Como ver os erros?</h3>
            <p>Procure o arquivo <code>php_errors.log</code> na raiz do projeto ou em <code>/xampp/apache/logs/</code></p>
        </div>

        <div class="footer-note">
            <p>🔗 <a href="/../CONFIGURACAO_MAILER.md" style="color: #c9a84c; text-decoration: none;">Ver Guia Completo de Configuração</a></p>
            <p style="color: #999; font-size: 0.9rem;">
                ⚠️ <strong>Segurança:</strong> Este arquivo de teste deve ser eliminado em produção.
            </p>
        </div>
    </div>
</body>
</html>
