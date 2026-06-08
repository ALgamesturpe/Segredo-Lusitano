<?php
// SEGREDO LUSITANO — Recuperação de Palavra-passe (Passo 1: inserir email)
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/mailer.php';

if (auth_user()) { header('Location: ' . SITE_URL . '/index.php'); exit; }

$erro    = '';
$email_prefill = trim($_GET['email'] ?? $_POST['email'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();
    $email = trim($_POST['email'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erro = 'Introduz um endereço de email válido.';
    } else {
        $st = db()->prepare('SELECT id, nome, ativo FROM utilizadores WHERE email = ? AND role != "[deleted]"');
        $st->execute([$email]);
        $user = $st->fetch();

        if (!$user) {
            $erro = 'Este email ainda não possui conta associada. Cria conta primeiro.';
        } elseif (in_array($user['tipo_auth'] ?? 'email', ['google', 'github'])) {
            $provedor = ucfirst($user['tipo_auth']);
            $erro = "Esta conta foi criada com {$provedor}. Para iniciar sessão, usa o botão \"Entrar com {$provedor}\" na página de login.";
        } elseif (!$user['ativo']) {
            $erro = 'Esta conta está suspensa. Não é possível recuperar a palavra-passe.';
        } elseif (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            $erro = 'O envio de email não está configurado neste servidor.';
        } else {
            $codigo = gerar_e_guardar_codigo($user['id'], 'recuperar');
            $enviado_email = enviar_codigo_verificacao($email, $user['nome'], $codigo, 'recuperar');

            if ($enviado_email) {
                $_SESSION['recuperar_id'] = $user['id'];
                header('Location: ' . SITE_URL . '/pages/redefinir_password.php');
                exit;
            } else {
                $erro = 'Erro ao enviar o email. Tenta novamente mais tarde.';
            }
        }
    }
    $email_prefill = $email;
}

$page_title = 'Recuperar Palavra-passe';
include dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-content" style="display:flex;align-items:center;justify-content:center;padding:2rem;min-height:calc(100vh - 72px);">
  <div class="form-container" style="max-width:460px;width:100%;">

    <div style="display:flex;justify-content:center;margin-bottom:2rem;">
      <img src="<?= SITE_URL ?>/assets/images/logo_icon.png" alt="Segredo Lusitano" style="height:80px;width:80px;object-fit:contain;filter:drop-shadow(0 0 10px rgba(201,168,76,.5));">
    </div>

    <h1 class="form-title" style="text-align:center;">Recuperar Palavra-passe</h1>
    <p class="form-subtitle" style="text-align:center;">
      Introduz o teu email e enviamos um código para redefinires a tua palavra-passe.
    </p>

    <?php if ($erro): ?>
      <div class="flash flash-error" style="position:static;margin-bottom:1.25rem;border-radius:0;">
        <i class="fas fa-exclamation-circle"></i> <?= h($erro) ?>
        <?php if (str_contains($erro, 'ainda não possui conta')): ?>
          &nbsp;<a href="<?= SITE_URL ?>/pages/registo.php" style="color:#c0392b;font-weight:700;text-decoration:underline;">Criar conta</a>
        <?php elseif (str_contains($erro, 'foi criada com')): ?>
          &nbsp;<a href="<?= SITE_URL ?>/pages/login.php" style="color:#c0392b;font-weight:700;text-decoration:underline;">Ir para o login</a>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <form method="POST" novalidate>
      <?= csrf_field() ?>
      <div class="form-group">
        <label for="email"><i class="fas fa-envelope"></i> Email</label>
        <input type="email" id="email" name="email"
               value="<?= h($email_prefill) ?>"
               placeholder="o.teu@email.pt"
               required autocomplete="email" autofocus>
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;margin-top:.5rem;">
        <i class="fas fa-paper-plane"></i> Enviar Código
      </button>
    </form>

    <div style="text-align:center;margin-top:1.5rem;">
      <a href="<?= SITE_URL ?>/pages/login.php" style="color:var(--texto-muted);font-size:.85rem;">
        <i class="fas fa-arrow-left"></i> Voltar ao login
      </a>
    </div>

  </div>
</div>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
