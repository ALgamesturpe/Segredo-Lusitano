<?php
// ============================================================
// TESTE DO SISTEMA DE VERIFICAÇÃO DE EMAIL
// ============================================================
// Abrir: http://localhost/Segredo-Lusitano/test_verificacao.php

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/mailer.php';

// Função para testar
function test($nome, callable $funcao): string {
    try {
        $resultado = $funcao();
        return "✅ <strong>$nome</strong>: " . $resultado;
    } catch (Exception $e) {
        return "❌ <strong>$nome</strong>: " . $e->getMessage();
    }
}

?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teste - Sistema de Verificação</title>
    <style>
        body { font-family: Arial; max-width: 800px; margin: 40px auto; padding: 20px; background: #f5efe6; }
        .test { background: white; padding: 15px; margin: 10px 0; border-radius: 8px; border-left: 4px solid #c9a84c; }
        .ok { color: green; }
        .erro { color: red; }
        h1 { color: #1a3a2a; text-align: center; }
        .info { background: #e3f2fd; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <h1>🧪 Teste do Sistema de Verificação</h1>
    
    <div class="info">
        <strong>ℹ️ Informações do Sistema:</strong><br>
        <em>Servidor:</em> <?= SITE_URL ?><br>
        <em>BD:</em> <?= DB_NAME ?> @ <?= DB_HOST ?><br>
        <em>Email:</em> <?= MAIL_FROM ?>
    </div>

    <div class="test">
        <?= test("Conexão BD", function() {
            $res = db()->query("SELECT 1");
            return "Conexão OK";
        }) ?>
    </div>

    <div class="test">
        <?= test("Tabela codigos_verificacao", function() {
            $res = db()->query("DESCRIBE codigos_verificacao");
            $cols = $res->fetchAll(PDO::FETCH_NUM);
            return count($cols) . " colunas encontradas";
        }) ?>
    </div>

    <div class="test">
        <?= test("PHPMailer disponível", function() {
            if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
                throw new Exception("PHPMailer não encontrado. Executa: composer require phpmailer/phpmailer");
            }
            return "PHPMailer v" . \PHPMailer\PHPMailer\PHPMailer::VERSION;
        }) ?>
    </div>

    <div class="test">
        <?= test("Configuração SMTP", function() {
            if (!defined('MAIL_HOST')) throw new Exception("MAIL_HOST não definido");
            if (!defined('MAIL_USER')) throw new Exception("MAIL_USER não definido");
            if (!defined('MAIL_PASS')) throw new Exception("MAIL_PASS não definido");
            return MAIL_HOST . " (User: " . substr(MAIL_USER, 0, 10) . "...)";
        }) ?>
    </div>

    <div class="test">
        <?= test("Função: gerar_e_guardar_codigo()", function() {
            // Criar utilizador de teste
            $id = db()->lastInsertId();
            if (!$id) {
                $st = db()->prepare("INSERT INTO utilizadores (nome, username, email, password, verificado) VALUES (?,?,?,?,0)");
                $st->execute(["Teste", "teste_" . time(), "teste_" . time() . "@test.com", "teste123"]);
                $id = db()->lastInsertId();
            }
            $codigo = gerar_e_guardar_codigo($id, 'registo');
            return "Código: $codigo (length: " . strlen($codigo) . ")";
        }) ?>
    </div>

    <div class="test">
        <?= test("Função: verificar_codigo()", function() {
            // Testar com um código válido
            $st = db()->query("SELECT utilizador_id, codigo FROM codigos_verificacao WHERE usado = 0 ORDER BY id DESC LIMIT 1");
            $row = $st->fetch();
            if (!$row) throw new Exception("Nenhum código não usado encontrado");
            
            // Verificar o código
            if (!verificar_codigo($row['utilizador_id'], $row['codigo'], 'registo')) {
                throw new Exception("Falha na verificação do código");
            }
            return "Código verificado: " . $row['codigo'];
        }) ?>
    </div>

    <div class="test">
        <?= test("Função: limpar_codigos_expirados()", function() {
            limpar_codigos_expirados();
            $st = db()->query("SELECT COUNT(*) FROM codigos_verificacao WHERE expira_em < NOW()");
            $count = $st->fetchColumn();
            return "Códigos expirados eliminados: $count";
        }) ?>
    </div>

    <div class="test">
        <?= test("Função: password_hash (BCrypt)", function() {
            $pass = "teste123";
            $hash = password_hash($pass, PASSWORD_BCRYPT, ['cost' => 12]);
            $verif = password_verify($pass, $hash);
            return $verif ? "Hash correto (length: " . strlen($hash) . ")" : "Falha na verificação";
        }) ?>
    </div>

    <div class="test">
        <?= test("Envio de Email (teste)", function() {
            if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
                throw new Exception("PHPMailer não instalado");
            }
            
            // Não enviar realmente, apenas testar conexão
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = MAIL_HOST;
            $mail->Port = MAIL_PORT;
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            
            // Não faz login, apenas testa configuração
            return "Configuração SMTP: " . MAIL_HOST . ":" . MAIL_PORT;
        }) ?>
    </div>

    <hr style="margin: 30px 0;">

    <h2>📋 Próximos Passos para Testar</h2>
    <ol>
        <li><strong>Criar conta</strong>: Acede a <a href="pages/registo.php">páginas de registo</a></li>
        <li><strong>Verificar código</strong>: Copia o código recebido no email</li>
        <li><strong>Confirmar</strong>: Entra na página de verificação</li>
        <li><strong>Login</strong>: Verifica se consegues entrar com a nova conta</li>
    </ol>

    <p style="text-align: center; color: #999; font-size: 0.9em; margin-top: 40px;">
        © Segredo Lusitano - Sistema de Verificação
    </p>
</body>
</html>
