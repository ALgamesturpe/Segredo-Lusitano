<?php
// ============================================================
// SEGREDO LUSITANO — Registo
// ============================================================
require_once dirname(__DIR__) . '/includes/auth.php';

if (auth_user()) { header('Location: ' . SITE_URL . '/index.php'); exit; }

$erros = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome     = trim($_POST['nome']     ?? '');
    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');
    $confirm  = trim($_POST['confirm']  ?? '');

    if (strlen($nome) < 2)         $erros['nome']     = 'Nome demasiado curto.';
    if (strlen($username) < 3)     $erros['username'] = 'Username com mínimo 3 caracteres.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $erros['email'] = 'Email inválido.';
    if (strlen($password) < 6)     $erros['password'] = 'Password com mínimo 6 caracteres.';
    if ($password !== $confirm)    $erros['confirm']  = 'As passwords não coincidem.';

    if (!$erros) {
        $res = register($nome, $username, $email, $password);
        if (!$res['ok']) {
            $erros['email'] = $res['msg'];
        } else {
            // Gerar e enviar código de verificação
            require_once dirname(__DIR__) . '/includes/mailer.php';

            // Verificar se PHPMailer está disponível
            if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
                // PHPMailer não instalado - auto-verificar e fazer login direto
                db()->prepare('UPDATE utilizadores SET verificado = 1 WHERE id = ?')->execute([$res['id']]);
                $_SESSION['user_id'] = $res['id'];
                add_pontos($res['id'], PONTOS_LOCAL); // Bónus de boas-vindas
                flash('success', 'Conta criada com sucesso! Bem-vindo ao Segredo Lusitano! 🎉 (Email não configurado - conta verificada automaticamente)');
                header('Location: ' . SITE_URL . '/index.php');
                exit;
            }

            $codigo = gerar_e_guardar_codigo($res['id'], 'registo');
            $enviado = enviar_codigo_verificacao($email, $nome, $codigo, 'registo');

            // Guardar ID na sessão para a página de verificação
            $_SESSION['verificar_id']   = $res['id'];
            $_SESSION['verificar_tipo'] = 'registo';

            if (!$enviado) {
                // Falha no envio - auto-verificar também
                db()->prepare('UPDATE utilizadores SET verificado = 1 WHERE id = ?')->execute([$res['id']]);
                $_SESSION['user_id'] = $res['id'];
                add_pontos($res['id'], PONTOS_LOCAL);
                flash('success', 'Conta criada! Email não enviado (erro SMTP) - conta verificada automaticamente.');
                header('Location: ' . SITE_URL . '/index.php');
                exit;
            }

            header('Location: ' . SITE_URL . '/pages/verificar.php');
            exit;
        }
    }
}

$page_title = 'Criar Conta';
include dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-content" style="display:flex; align-items:center; justify-content:center; padding:2rem; min-height:calc(100vh - 72px);">
  <div class="form-container" style="max-width:600px;">
    <div style="text-align:center; margin-bottom:2rem;">
      <img src="<?= SITE_URL ?>/assets/images/logo_icon.png" alt="Segredo Lusitano" style="height:80px;width:80px;object-fit:contain;filter:drop-shadow(0 0 10px rgba(201,168,76,.5));">
    </div>
    <h1 class="form-title" style="text-align:center;">Torna-te um Explorador</h1>
    <p class="form-subtitle" style="text-align:center;">Junta-te à comunidade de Segredos Lusitanos</p>

    <form method="POST" novalidate>
      <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
        <div class="form-group">
          <label for="nome">Nome Completo</label>
          <input type="text" id="nome" name="nome" value="<?= h($_POST['nome'] ?? '') ?>"
                 placeholder="João Silva" required>
          <?php if (isset($erros['nome'])): ?><div class="form-error"><?= h($erros['nome']) ?></div><?php endif; ?>
        </div>
        <div class="form-group">
          <label for="username">Username</label>
          <input type="text" id="username" name="username" value="<?= h($_POST['username'] ?? '') ?>"
                 placeholder="explorador42" required>
          <?php if (isset($erros['username'])): ?><div class="form-error"><?= h($erros['username']) ?></div><?php endif; ?>
        </div>
      </div>
      <div class="form-group">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" value="<?= h($_POST['email'] ?? '') ?>"
               placeholder="o.teu@email.pt" required>
        <?php if (isset($erros['email'])): ?><div class="form-error"><?= h($erros['email']) ?></div><?php endif; ?>
      </div>
      <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
        <div class="form-group">
          <label for="password">Password</label>
          <input type="password" id="password" name="password" placeholder="Mínimo 6 caracteres" required>
          <?php if (isset($erros['password'])): ?><div class="form-error"><?= h($erros['password']) ?></div><?php endif; ?>
        </div>
        <div class="form-group">
          <label for="confirm">Confirmar Password</label>
          <input type="password" id="confirm" name="confirm" placeholder="Repetir password" required>
          <?php if (isset($erros['confirm'])): ?><div class="form-error"><?= h($erros['confirm']) ?></div><?php endif; ?>
        </div>
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%; justify-content:center; margin-top:.5rem;">
        <i class="fas fa-user-plus"></i> Criar Conta
      </button>
    </form>

    <?php if (GOOGLE_CLIENT_ID && GOOGLE_CLIENT_ID !== ''): ?>
    <div class="form-divider">ou entra com</div>

    <!-- Google Sign-In (Google Identity Services - 2024) -->
    <div style="display:flex;flex-direction:column;align-items:center;gap:.75rem;margin-bottom:1rem;">
      <div id="g_id_onload"
           data-client_id="<?= GOOGLE_CLIENT_ID ?>"
           data-context="signup"
           data-callback="handleGoogleSignIn"
           data-auto_prompt="false">
      </div>
      <div class="g_id_signin"
           data-type="standard"
           data-shape="rectangular"
           data-theme="outline"
           data-text="signup_with"
           data-size="large"
           data-locale="pt-PT"
           data-width="300">
      </div>
      <p id="google-msg" style="color:#c0392b;font-size:.85rem;display:none;"></p>
    </div>
    <?php endif; ?>

    <p style="text-align:center; font-size:.9rem;">
      Já tens conta? <a href="<?= SITE_URL ?>/pages/login.php" class="form-link">Iniciar sessão</a>
    </p>
  </div>
</div>

<!-- Google Identity Services (nova biblioteca 2024) -->
<?php if (GOOGLE_CLIENT_ID && GOOGLE_CLIENT_ID !== ''): ?>
<script src="https://accounts.google.com/gsi/client" async defer></script>
<script>
function handleGoogleSignIn(response) {
  const msg = document.getElementById('google-msg');
  fetch('<?= SITE_URL ?>/pages/google_auth.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: 'id_token=' + encodeURIComponent(response.credential)
  })
  .then(r => r.json())
  .then(data => {
    if (data.ok) {
      window.location.href = data.redirect;
    } else {
      msg.textContent = data.msg || 'Erro ao entrar com Google.';
      msg.style.display = 'block';
    }
  })
  .catch(() => {
    msg.textContent = 'Erro de ligação. Tenta novamente.';
    msg.style.display = 'block';
  });
}
</script>
<?php endif; ?>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
