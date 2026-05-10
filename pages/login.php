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
        // Verificar se o email pertence a um utilizador banido
        $st_ban = db()->prepare('SELECT motivo FROM banidos WHERE email = ?');
        $st_ban->execute([$email]);
        $ban = $st_ban->fetch();
        if ($ban) {
            $erro = 'banido';
            $motivos_label = [
                'spam'                  => 'Spam',
                'comportamento_abusivo' => 'Comportamento abusivo',
                'conteudo_inapropriado' => 'Conteúdo inapropriado',
                'fraude'                => 'Fraude',
                'outro'                 => 'Outro',
            ];
            $motivo_ban = $motivos_label[$ban['motivo']] ?? $ban['motivo'];
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

    <!-- Mensagem de utilizador banido -->
    <p id="google-msg" style="color:#c0392b;font-size:.9rem;font-weight:600;display:none;margin-bottom:1.25rem;"></p>

    <!-- Mensagem de utilizador banido (login normal) -->
    <?php if ($erro === 'banido'): ?>
      <p style="color:#c0392b;font-size:.9rem;font-weight:600;margin-bottom:1.25rem;">
        <i class="fas fa-user-alt-slash"></i> Conta banida pelo administrador pelo motivo: <?= h($motivo_ban ?? '') ?>.
      </p>

    <!-- Mensagem de erro normal (credenciais incorretas) -->
    <?php elseif ($erro): ?>
      <div class="flash flash-error" style="position:static; margin-bottom:1.25rem; border-radius:0;">
        <i class="fas fa-exclamation-circle"></i> <?= h($erro) ?>
      </div>
    <?php endif; ?>

    <!-- Formulário de login por email e password -->
    <form method="POST" novalidate id="form-login">
      <div class="form-group">
        <label for="email"><i class="fas fa-envelope"></i> Email</label>
        <input type="email" id="email" name="email" value="<?= h($_POST['email'] ?? '') ?>"
               placeholder="o.teu@email.pt" required autocomplete="email">
      </div>
      <div class="form-group">
        <label for="password"><i class="fas fa-lock"></i> Password</label>
        <input type="password" id="password" name="password" placeholder="••••••••" required autocomplete="current-password">
      </div>
      <button type="submit" id="btn-entrar" class="btn btn-primary" style="width:100%; justify-content:center; margin-top:.5rem;">
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
      <p id="google-msg" style="color:#c0392b;font-size:.9rem;font-weight:600;display:none;margin-bottom:1.25rem;"></p>

      <!-- Botão de login com GitHub -->
      <div style="display:flex;justify-content:center;">
        <a href="<?= SITE_URL ?>/pages/github_redirect.php"
           style="display:flex;align-items:center;justify-content:space-between;
                  width:300px;padding:.65rem 1rem;border:1.5px solid #d0d5dd;
                  border-radius:0;background:#fff;color:#1e1e1e;
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

<!-- Modal Termos e Condições (para novos utilizadores via Google) -->
<input type="hidden" id="termos-aceites-em" value="">
<div id="modal-termos" style="display:none;position:fixed;inset:0;z-index:9000;background:rgba(0,0,0,.55);align-items:center;justify-content:center;padding:1rem;">
  <div style="background:#fff;width:100%;max-width:600px;max-height:88vh;display:flex;flex-direction:column;box-shadow:0 8px 32px rgba(0,0,0,.2);">
    <div style="display:flex;align-items:center;justify-content:space-between;padding:1.25rem 1.75rem;border-bottom:1px solid #e5e7eb;flex-shrink:0;">
      <h3 style="font-size:1rem;font-weight:700;color:var(--verde-escuro);margin:0;">Termos e Condições de Utilização</h3>
      <button onclick="document.getElementById('modal-termos').style.display='none'" style="background:none;border:none;font-size:1.1rem;cursor:pointer;color:#9ca3af;padding:.2rem .4rem;">&#x2715;</button>
    </div>
    <div style="overflow-y:auto;flex:1;padding:1.5rem 1.75rem;font-size:.85rem;line-height:1.75;color:#374151;">
      <p style="margin:0 0 1rem;color:#6b7280;">Ao criares conta através do Google no Segredo Lusitano, declaras ter lido e aceite os presentes Termos e a nossa Política de Privacidade.</p>
      <p style="font-weight:700;margin:0 0 .3rem;">Responsabilidade</p>
      <p style="margin:0 0 1rem;">O utilizador é exclusivamente responsável pela visita a locais partilhados. Muitos podem situar-se em propriedade privada. O Segredo Lusitano não se responsabiliza por acidentes, lesões ou infrações.</p>
      <p style="font-weight:700;margin:0 0 .3rem;">Dados Pessoais (RGPD)</p>
      <p style="margin:0 0 1rem;">Os dados recolhidos destinam-se ao funcionamento da plataforma. Podes apagar a conta em Perfil → Zona de Perigo.</p>
      <p style="font-weight:700;margin:0 0 .3rem;">Conduta</p>
      <p style="margin:0;">É proibido publicar conteúdo falso, difamatório ou ilegal. O incumprimento pode resultar na suspensão imediata da conta.</p>
    </div>
    <div style="padding:1rem 1.75rem;border-top:1px solid #e5e7eb;display:flex;justify-content:flex-end;gap:.75rem;flex-shrink:0;">
      <button onclick="document.getElementById('modal-termos').style.display='none'"
              style="background:none;border:1px solid #d1d5db;padding:.55rem 1.1rem;cursor:pointer;font-size:.85rem;color:#6b7280;border-radius:3px;">
        Fechar
      </button>
      <button onclick="aceitarTermos()" class="btn btn-primary btn-sm">
        <i class="fas fa-check"></i> Li e Aceito os Termos
      </button>
    </div>
  </div>
</div>

<!-- Biblioteca Google Identity Services -->
<?php if (GOOGLE_CLIENT_ID && GOOGLE_CLIENT_ID !== ''): ?>
<script src="https://accounts.google.com/gsi/client" async defer></script>
<script>
window._pendingGoogleResponse = null;

function handleGoogleSignIn(response) {
  _executarGoogleLogin(response);
}

function _executarGoogleLogin(response) {
  const msg = document.getElementById('google-msg');
  const termosEm = document.getElementById('termos-aceites-em')?.value || '';
  fetch('<?= SITE_URL ?>/pages/google_auth.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: 'id_token=' + encodeURIComponent(response.credential) + '&termos_aceites_em=' + encodeURIComponent(termosEm)
  })
  .then(r => r.json())
  .then(data => {
    if (data.ok) {
      window.location.href = data.redirect;
    } else if (data.precisa_termos) {
      window._pendingGoogleResponse = response;
      document.getElementById('modal-termos').style.display = 'flex';
    } else {
      msg.innerHTML = data.msg || 'Erro ao iniciar sessão com Google.';
      msg.style.display = 'block';
    }
  })
  .catch(() => {
    msg.textContent = 'Erro de ligação. Tenta novamente.';
    msg.style.display = 'block';
  });
}

function aceitarTermos() {
  document.getElementById('modal-termos').style.display = 'none';
  const now = new Date();
  const pad = n => String(n).padStart(2, '0');
  const dt = now.getFullYear() + '-' + pad(now.getMonth()+1) + '-' + pad(now.getDate())
           + ' ' + pad(now.getHours()) + ':' + pad(now.getMinutes()) + ':' + pad(now.getSeconds());
  document.getElementById('termos-aceites-em').value = dt;
  if (window._pendingGoogleResponse) {
    _executarGoogleLogin(window._pendingGoogleResponse);
    window._pendingGoogleResponse = null;
  }
}
</script>
<?php endif; ?>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>