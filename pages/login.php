<?php
// ============================================================
// SEGREDO LUSITANO — Login
// ============================================================
require_once dirname(__DIR__) . '/includes/auth.php';

if (auth_user()) { header('Location: ' . SITE_URL . '/index.php'); exit; }

$erro = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');
    if (!$email || !$password) {
        $erro = 'Preenche todos os campos.';
    } else {
        require_once dirname(__DIR__) . '/includes/mailer.php';
        $res = login($email, $password);
        if ($res['ok']) {
            $redirect = $_GET['redirect'] ?? (SITE_URL . '/index.php');
            header('Location: ' . $redirect);
            exit;
        } elseif (!empty($res['verificar'])) {
            // Conta existe mas não está verificada

            // Verificar se PHPMailer está disponível
            if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
                // PHPMailer não instalado - auto-verificar e fazer login
                db()->prepare('UPDATE utilizadores SET verificado = 1 WHERE id = ?')->execute([$res['id']]);
                $_SESSION['user_id'] = $res['id'];
                flash('success', 'Conta verificada automaticamente (email não configurado). Bem-vindo!');
                $redirect = $_GET['redirect'] ?? (SITE_URL . '/index.php');
                header('Location: ' . $redirect);
                exit;
            }

            // Enviar código de verificação
            $st = db()->prepare('SELECT nome FROM utilizadores WHERE id = ?');
            $st->execute([$res['id']]);
            $u = $st->fetch();
            $codigo = gerar_e_guardar_codigo($res['id'], 'login');
            $enviado = enviar_codigo_verificacao($email, $u['nome'] ?? '', $codigo, 'login');

            if (!$enviado) {
                // Falha no envio - auto-verificar
                db()->prepare('UPDATE utilizadores SET verificado = 1 WHERE id = ?')->execute([$res['id']]);
                $_SESSION['user_id'] = $res['id'];
                flash('success', 'Conta verificada automaticamente (erro ao enviar email).');
                $redirect = $_GET['redirect'] ?? (SITE_URL . '/index.php');
                header('Location: ' . $redirect);
                exit;
            }

            $_SESSION['verificar_id']   = $res['id'];
            $_SESSION['verificar_tipo'] = 'login';
            header('Location: ' . SITE_URL . '/pages/verificar.php');
            exit;
        } else {
            $erro = $res['msg'];
        }
    }
}

$page_title = 'Entrar';
include dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-content" style="display:flex; align-items:center; justify-content:center; padding:2rem; min-height:calc(100vh - 72px);">
  <div class="form-container">
    <!-- Logo -->
    <div style="text-align:center; margin-bottom:2rem;">
      <img src="<?= SITE_URL ?>/assets/images/logo_icon.png" alt="Segredo Lusitano" style="height:80px;width:80px;object-fit:contain;filter:drop-shadow(0 0 10px rgba(201,168,76,.5));">
    </div>
    <h1 class="form-title" style="text-align:center;">Bem-vindo de volta</h1>
    <p class="form-subtitle" style="text-align:center;">Entra na tua conta de explorador</p>

    <?php if ($erro): ?>
      <div class="flash flash-error" style="position:static; margin-bottom:1.25rem; border-radius:8px;">
        <i class="fas fa-exclamation-circle"></i> <?= h($erro) ?>
      </div>
    <?php endif; ?>

    <form method="POST" novalidate>
      <div class="form-group">
        <label for="email"><i class="fas fa-envelope"></i> Email</label>
        <input type="email" id="email" name="email" value="<?= h($_POST['email'] ?? '') ?>"
               placeholder="o.teu@email.pt" required autocomplete="email">
      </div>
      <div class="form-group">
        <label for="password"><i class="fas fa-lock"></i> Password</label>
        <input type="password" id="password" name="password" placeholder="••••••••" required autocomplete="current-password">
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%; justify-content:center; margin-top:.5rem;">
        <i class="fas fa-sign-in-alt"></i> Entrar
      </button>
    </form>

    <?php if (GOOGLE_CLIENT_ID && GOOGLE_CLIENT_ID !== ''): ?>
    <div class="form-divider">ou</div>

    <!-- Google Sign-In (Google Identity Services - 2024) -->
    <div style="display:flex;flex-direction:column;align-items:center;gap:.75rem;">
      <div id="g_id_onload"
           data-client_id="<?= GOOGLE_CLIENT_ID ?>"
           data-context="signin"
           data-callback="handleGoogleSignIn"
           data-auto_prompt="false">
      </div>
      <div class="g_id_signin"
           data-type="standard"
           data-shape="rectangular"
           data-theme="outline"
           data-text="signin_with"
           data-size="large"
           data-locale="pt-PT"
           data-width="300">
      </div>
      <p id="google-msg" style="color:#c0392b;font-size:.85rem;display:none;"></p>
    </div>
    <?php endif; ?>

    <div class="form-divider" style="margin-top:1.25rem;"></div>
    <p style="text-align:center; font-size:.9rem;">
      Ainda não tens conta? <a href="<?= SITE_URL ?>/pages/registo.php" class="form-link">Regista-te grátis</a>
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
      msg.textContent = data.msg || 'Erro ao iniciar sessão com Google.';
      if (data.debug) {
        msg.textContent += ' (Debug: ' + data.debug + ')';
      }
      msg.style.display = 'block';
      console.error('Google Sign-In error:', data);
    }
  })
  .catch(err => {
    msg.textContent = 'Erro de ligação. Tenta novamente.';
    msg.style.display = 'block';
    console.error('Fetch error:', err);
  });
}
</script>
<?php endif; ?>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
