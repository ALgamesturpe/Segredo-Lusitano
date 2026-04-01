<?php
// ============================================================
// SEGREDO LUSITANO — Página de Login
// Permite ao utilizador entrar na conta por 3 métodos:
// 1. Email + Password (formulário tradicional)
// 2. Google Sign-In (OAuth via Google Identity Services)
// 3. GitHub OAuth
// ============================================================
require_once dirname(__DIR__) . '/includes/auth.php';

// Limpar códigos de verificação expirados da base de dados (limpeza periódica)
if (file_exists(dirname(__DIR__) . '/includes/mailer.php')) {
    require_once dirname(__DIR__) . '/includes/mailer.php';
    limpar_codigos_expirados();
}

// Se o utilizador já tem sessão iniciada, redirecionar para a página inicial
if (auth_user()) { header('Location: ' . SITE_URL . '/index.php'); exit; }

$erro = '';

// ── Processar formulário de login por email e password ────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');

    // Validar que ambos os campos foram preenchidos
    if (!$email || !$password) {
        $erro = 'Preenche todos os campos.';
    } else {
        require_once dirname(__DIR__) . '/includes/mailer.php';

        // Chamar a função login() que verifica o email e a password na base de dados
        $res = login($email, $password);

        if ($res['ok']) {
            // Login bem sucedido — redirecionar para a página anterior ou para o início
            $redirect = $_GET['redirect'] ?? (SITE_URL . '/index.php');
            header('Location: ' . $redirect);
            exit;

        } elseif (!empty($res['verificar'])) {
            // A conta existe mas o email ainda não foi verificado

            if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
                // PHPMailer não instalado — verificar automaticamente
                db()->prepare('UPDATE utilizadores SET verificado = 1 WHERE id = ?')->execute([$res['id']]);
                $_SESSION['user_id'] = $res['id'];
                flash('success', 'Email não configurado. Bem-vindo!');
                $redirect = $_GET['redirect'] ?? (SITE_URL . '/index.php');
                header('Location: ' . $redirect);
                exit;
            }

            // Gerar e enviar código de verificação por email
            $st = db()->prepare('SELECT nome FROM utilizadores WHERE id = ?');
            $st->execute([$res['id']]);
            $u      = $st->fetch();
            $codigo = gerar_e_guardar_codigo($res['id'], 'login');
            $enviado = enviar_codigo_verificacao($email, $u['nome'] ?? '', $codigo, 'login');

            if (!$enviado) {
                // Falha no envio — verificar automaticamente
                db()->prepare('UPDATE utilizadores SET verificado = 1 WHERE id = ?')->execute([$res['id']]);
                $_SESSION['user_id'] = $res['id'];
                flash('success', 'Erro ao enviar email. Conta verificada automaticamente.');
                $redirect = $_GET['redirect'] ?? (SITE_URL . '/index.php');
                header('Location: ' . $redirect);
                exit;
            }

            // Redirecionar para a página de verificação do código
            $_SESSION['verificar_id']   = $res['id'];
            $_SESSION['verificar_tipo'] = 'login';
            header('Location: ' . SITE_URL . '/pages/verificar.php');
            exit;

        } else {
            // Email ou password incorretos
            $erro = $res['msg'];
        }
    }
}

$page_title = 'Entrar';
include dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-content" style="display:flex; align-items:center; justify-content:center; padding:2rem; min-height:calc(100vh - 72px);">
  <div class="form-container">

    <!-- Logo centrado no topo -->
    <div style="display:flex; justify-content:center; margin-bottom:2rem;">
      <img src="<?= SITE_URL ?>/assets/images/logo_icon.png" alt="Segredo Lusitano" style="height:80px;width:80px;object-fit:contain;filter:drop-shadow(0 0 10px rgba(201,168,76,.5));">
    </div>

    <h1 class="form-title" style="text-align:center;">Bem-vindo de volta</h1>
    <p class="form-subtitle" style="text-align:center;">Entra na tua conta de explorador</p>

    <!-- Mensagem de erro (credenciais incorretas) -->
    <?php if ($erro): ?>
      <div class="flash flash-error" style="position:static; margin-bottom:1.25rem; border-radius:8px;">
        <i class="fas fa-exclamation-circle"></i> <?= h($erro) ?>
      </div>
    <?php endif; ?>

    <!-- Formulário de login por email e password -->
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

    <!-- Botões de login social -->
    <div style="display:flex;flex-direction:column;align-items:center;gap:.75rem;">

      <!-- Google Sign-In — botão gerado automaticamente pela biblioteca do Google -->
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

      <!-- Mensagem de erro do Google Sign-In -->
      <p id="google-msg" style="color:#c0392b;font-size:.85rem;font-weight:600;text-align:center;
         display:none;margin-top:.25rem;padding:.65rem 1rem;
         background:rgba(192,57,43,.08);border:1px solid rgba(192,57,43,.25);
         border-radius:8px;width:300px;"></p>

      <!-- Botão de login com GitHub -->
      <div style="display:flex;justify-content:center;">
        <a href="<?= SITE_URL ?>/pages/github_redirect.php"
           style="display:flex;align-items:center;justify-content:space-between;
                  width:300px;padding:.65rem 1rem;border:1.5px solid #d0d5dd;
                  border-radius:8px;background:#fff;color:#1e1e1e;
                  font-size:.9rem;font-weight:500;text-decoration:none;
                  transition:background .2s;">
          <div style="display:flex;align-items:center;gap:.65rem;">
            <i class="fab fa-github" style="font-size:1.1rem;color:#24292e;"></i>
            <span>Iniciar sessão com GitHub</span>
          </div>
          <i class="fab fa-github" style="font-size:1.1rem;color:#24292e;"></i>
        </a>
      </div>

    </div>
    <?php endif; ?>

    <div class="form-divider" style="margin-top:1.25rem;"></div>
    <p style="text-align:center; font-size:.9rem;">
      Ainda não tens conta? <a href="<?= SITE_URL ?>/pages/registo.php" class="form-link">Regista-te grátis</a>
    </p>
  </div>
</div>

<!-- Biblioteca Google Identity Services -->
<?php if (GOOGLE_CLIENT_ID && GOOGLE_CLIENT_ID !== ''): ?>
<script src="https://accounts.google.com/gsi/client" async defer></script>
<script>
// Callback chamado pelo Google após o utilizador selecionar a conta
// Recebe o token JWT que é enviado para o servidor para validação
function handleGoogleSignIn(response) {
  const msg = document.getElementById('google-msg');

  // Enviar token JWT para o servidor
  fetch('<?= SITE_URL ?>/pages/google_auth.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: 'id_token=' + encodeURIComponent(response.credential)
  })
  .then(r => r.json())
  .then(data => {
    if (data.ok) {
      // Login bem sucedido — redirecionar
      window.location.href = data.redirect;
    } else {
      // Mostrar mensagem de erro
      msg.textContent = data.msg || 'Erro ao iniciar sessão com Google.';
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