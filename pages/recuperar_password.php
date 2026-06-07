<?php
// SEGREDO LUSITANO — Recuperação de Palavra-passe (Passo 1: inserir email)
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/mailer.php';

if (auth_user()) { header('Location: ' . SITE_URL . '/index.php'); exit; }

$erro   = '';
$enviado = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();
    $email = trim($_POST['email'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erro = 'Introduz um endereço de email válido.';
    } else {
        $st = db()->prepare('SELECT id, nome, ativo FROM utilizadores WHERE email = ? AND role != "[deleted]"');
        $st->execute([$email]);
        $user = $st->fetch();

        // Resposta genérica para não revelar se o email existe ou não
        if ($user && $user['ativo']) {
            if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
                $erro = 'O envio de email não está configurado neste servidor.';
            } else {
                $codigo  = gerar_e_guardar_codigo($user['id'], 'recuperar');
                $enviado_email = enviar_codigo_verificacao($email, $user['nome'], $codigo, 'recuperar');

                if ($enviado_email) {
                    $_SESSION['recuperar_id'] = $user['id'];
                    header('Location: ' . SITE_URL . '/pages/redefinir_password.php');
                    exit;
                } else {
                    $erro = 'Erro ao enviar o email. Tenta novamente mais tarde.';
                }
            }
        } else {
            // Conta suspensa, inexistente ou banida — mesma resposta para não revelar estado
            // Redirecionar para dar feedback sem revelar se o email existe
            $_SESSION['recuperar_enviado_feedback'] = true;
            header('Location: ' . SITE_URL . '/pages/recuperar_password.php?enviado=1');
            exit;
        }
    }
}

$feedback_enviado = isset($_GET['enviado']) && !empty($_SESSION['recuperar_enviado_feedback']);
unset($_SESSION['recuperar_enviado_feedback']);

$page_title = 'Recuperar Palavra-passe';
include dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-content" style="display:flex;align-items:center;justify-content:center;padding:2rem;min-height:calc(100vh - 72px);">
  <div class="form-container" style="max-width:460px;width:100%;">

    <div style="text-align:center;margin-bottom:1.5rem;">
      <div style="width:80px;height:80px;background:#1a3a2a;border:3px solid #c9a84c;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:2rem;">
        🔑
      </div>
    </div>

    <h1 class="form-title" style="text-align:center;">Recuperar Palavra-passe</h1>
    <p class="form-subtitle" style="text-align:center;">
      Introduz o teu email e enviamos um código para redefinires a tua palavra-passe.
    </p>

    <?php if ($feedback_enviado): ?>
      <div class="flash flash-success" style="position:static;margin-bottom:1.25rem;border-radius:0;">
        <i class="fas fa-envelope"></i> Se existir uma conta associada a esse email, receberás instruções em breve.
      </div>
    <?php endif; ?>

    <?php if ($erro): ?>
      <div class="flash flash-error" style="position:static;margin-bottom:1.25rem;border-radius:0;">
        <i class="fas fa-exclamation-circle"></i> <?= h($erro) ?>
      </div>
    <?php endif; ?>

    <?php if (!$feedback_enviado): ?>
    <form method="POST" novalidate>
      <?= csrf_field() ?>
      <div class="form-group">
        <label for="email"><i class="fas fa-envelope"></i> Email</label>
        <input type="email" id="email" name="email"
               value="<?= h($_POST['email'] ?? '') ?>"
               placeholder="o.teu@email.pt"
               required autocomplete="email" autofocus>
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;margin-top:.5rem;">
        <i class="fas fa-paper-plane"></i> Enviar Código
      </button>
    </form>
    <?php endif; ?>

    <div style="text-align:center;margin-top:1.5rem;">
      <a href="<?= SITE_URL ?>/pages/login.php" style="color:var(--texto-muted);font-size:.85rem;">
        <i class="fas fa-arrow-left"></i> Voltar ao login
      </a>
    </div>

  </div>
</div>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
