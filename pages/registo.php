<?php
// SEGREDO LUSITANO — Registo
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/mailer.php';

// Limpar códigos expirados (limpeza periódica)
limpar_codigos_expirados();

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
    if (empty($_POST['aceitar_termos'])) $erros['termos'] = 'Deves aceitar os Termos e Condições para continuar.';

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
    <div style="display:flex; justify-content:center; margin-bottom:2rem;">
      <img src="<?= SITE_URL ?>/assets/images/logo_icon.png" alt="Segredo Lusitano" style="height:80px;width:80px;object-fit:contain;filter:drop-shadow(0 0 10px rgba(201,168,76,.5));">
    </div>
    <h1 class="form-title" style="text-align:center;">Torna-te um Explorador</h1>
    <p class="form-subtitle" style="text-align:center;">Junta-te à comunidade de Segredos Lusitanos</p>

    <form method="POST" novalidate>
      <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
        <div class="form-group">
          <label for="nome">Nome Completo</label>
          <input type="text" id="nome" name="nome" value="<?= h($_POST['nome'] ?? '') ?>"
                 placeholder="Gonçalo Teixeira" required>
          <?php if (isset($erros['nome'])): ?><div class="form-error"><?= h($erros['nome']) ?></div><?php endif; ?>
        </div>
        <div class="form-group">
          <label for="username">Username</label>
          <input type="text" id="username" name="username" value="<?= h($_POST['username'] ?? '') ?>"
                 placeholder="Gonçalo123" required>
          <?php if (isset($erros['username'])): ?><div class="form-error"><?= h($erros['username']) ?></div><?php endif; ?>
        </div>
      </div>
      <div class="form-group">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" value="<?= h($_POST['email'] ?? '') ?>"
               placeholder="o.teu@email.com" required>
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
      <!-- Termos e Condições -->
      <div class="form-group" style="margin-bottom:.75rem;">
        <input type="checkbox" id="aceitar-termos" name="aceitar_termos" style="display:none;" <?= isset($_POST['aceitar_termos']) ? 'checked' : '' ?>>
        <p style="font-size:.85rem;line-height:1.6;color:var(--texto-muted);">
          <a href="#" onclick="document.getElementById('modal-termos').style.display='flex';return false;" class="form-link" style="font-weight:600;">Termos e Condições</a>
          &nbsp;&mdash; Li e aceito os termos. Compreendo que a visita a locais pode envolver riscos e que a entrada em propriedade privada é da responsabilidade exclusiva do utilizador. O Segredo Lusitano não se responsabiliza por qualquer dano, acidente ou invasão de propriedade.
        </p>
        <?php if (isset($erros['termos'])): ?><div class="form-error"><?= h($erros['termos']) ?></div><?php endif; ?>
      </div>
      <button type="submit" id="btn-criar" class="btn btn-primary" style="width:100%; justify-content:center; margin-top:.5rem;" <?= !isset($_POST['aceitar_termos']) ? 'disabled' : '' ?>>
        <i class="fas fa-user-plus"></i> Criar Conta
      </button>
    </form>

    <?php if (GOOGLE_CLIENT_ID && GOOGLE_CLIENT_ID !== ''): ?>
    <div class="form-divider">ou entrar com</div>

    <!-- Google Sign-In (Google Identity Services - 2024) -->
    <div style="display:flex;flex-direction:column;align-items:center;gap:.75rem;">
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
    <!-- GitHub Sign-In -->
    <div style="display:flex;justify-content:center;">
      <a href="#" onclick="verificarTermosParaSocial('<?= SITE_URL ?>/pages/github_redirect.php'); return false;"
        style="display:flex;align-items:center;justify-content:space-between;
                width:300px;padding:.65rem 1rem;border:1.5px solid #d0d5dd;
                border-radius:4px;background:#fff;color:#1e1e1e;
                font-size:.9rem;font-weight:500;text-decoration:none;
                transition:background .2s;margin-top:.5rem;">
        <div style="display:flex;align-items:center;gap:.65rem;">
          <i class="fab fa-github" style="font-size:1.1rem;color:#24292e;"></i>
          <span>Iniciar sessão com GitHub</span>
        </div>
        <i class="fab fa-github" style="font-size:1.1rem;color:#24292e;"></i>
      </a>
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
let _pendingGoogleResponse = null;

function handleGoogleSignIn(response) {
  const termos = document.getElementById('aceitar-termos');
  if (!termos || !termos.checked) {
    _pendingGoogleResponse = response;
    document.getElementById('modal-termos').style.display = 'flex';
    return;
  }
  _executarGoogleLogin(response);
}

function _executarGoogleLogin(response) {
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

function verificarTermosParaSocial(url) {
  const termos = document.getElementById('aceitar-termos');
  if (termos && termos.checked) {
    window.location.href = url;
  } else {
    window._pendingGithubUrl = url;
    document.getElementById('modal-termos').style.display = 'flex';
  }
}
</script>
<?php endif; ?>

<!-- Modal Termos e Condições -->
<div id="modal-termos" style="display:none;position:fixed;inset:0;z-index:9000;background:rgba(0,0,0,.55);align-items:center;justify-content:center;padding:1rem;">
  <div style="background:#fff;border-radius:4px;max-width:560px;width:100%;max-height:82vh;display:flex;flex-direction:column;box-shadow:0 8px 32px rgba(0,0,0,.2);">

    <!-- Cabeçalho fixo -->
    <div style="display:flex;align-items:center;justify-content:space-between;padding:1.25rem 1.75rem;border-bottom:1px solid #e5e7eb;flex-shrink:0;">
      <h3 style="font-size:1rem;font-weight:700;color:var(--verde-escuro);margin:0;letter-spacing:.01em;">Termos e Condições de Utilização</h3>
      <button onclick="document.getElementById('modal-termos').style.display='none'" style="background:none;border:none;font-size:1.1rem;cursor:pointer;color:#9ca3af;line-height:1;padding:.2rem .4rem;">&#x2715;</button>
    </div>

    <!-- Conteúdo com scroll — botão de aceite no fundo -->
    <div style="overflow-y:auto;padding:1.5rem 1.75rem;flex:1;font-size:.875rem;line-height:1.8;color:#374151;">

      <p style="margin-bottom:1.5rem;color:#6b7280;">Lê atentamente os seguintes termos antes de criares conta no Segredo Lusitano. Ao registares-te, declaras ter lido e aceite todas as condições abaixo.</p>

      <p style="font-weight:700;color:var(--verde-escuro);margin-bottom:.4rem;">1. Responsabilidade do Utilizador</p>
      <p style="margin-bottom:1.5rem;">O utilizador assume total responsabilidade pelas suas ações durante a visita aos locais partilhados na plataforma. O Segredo Lusitano não se responsabiliza por quaisquer danos, perdas ou consequências legais resultantes dessas atividades.</p>

      <p style="font-weight:700;color:var(--verde-escuro);margin-bottom:.4rem;">2. Propriedade Privada</p>
      <p style="margin-bottom:1.5rem;">Muitos locais partilhados podem estar em propriedade privada ou de acesso restrito. O Segredo Lusitano não incentiva, apoia nem se responsabiliza pela entrada em propriedade privada, reservas naturais protegidas ou quaisquer locais de acesso proibido. A responsabilidade é exclusivamente do utilizador.</p>

      <p style="font-weight:700;color:var(--verde-escuro);margin-bottom:.4rem;">3. Riscos e Segurança</p>
      <p style="margin-bottom:1.5rem;">A visita a locais secretos pode envolver riscos físicos significativos. O utilizador deve sempre avaliar as condições do local, levar equipamento adequado e informar alguém da sua localização. O Segredo Lusitano não se responsabiliza por acidentes, danos físicos ou lesões de qualquer natureza.</p>

      <p style="font-weight:700;color:var(--verde-escuro);margin-bottom:.4rem;">4. Conteúdo Partilhado</p>
      <p style="margin-bottom:1.5rem;">O utilizador é o único responsável pelo conteúdo que partilha na plataforma. Não é permitida a partilha de locais que incentivem atividades ilegais, perigosas ou que violem direitos de terceiros.</p>

      <p style="font-weight:700;color:var(--verde-escuro);margin-bottom:.4rem;">5. Aceitação</p>
      <p style="margin-bottom:2rem;">Ao criar conta nesta plataforma, o utilizador declara ter lido, compreendido e aceite estes termos na íntegra. O Segredo Lusitano reserva-se o direito de atualizar estes termos sem aviso prévio.</p>

      <!-- Botão de aceite — apenas visível após scroll -->
      <div style="border-top:1px solid #e5e7eb;padding-top:1.25rem;display:flex;justify-content:flex-end;gap:.75rem;">
        <button onclick="document.getElementById('modal-termos').style.display='none'"
                style="background:none;border:1px solid #d1d5db;border-radius:4px;padding:.55rem 1.1rem;cursor:pointer;font-size:.875rem;color:#6b7280;">
          Fechar
        </button>
        <button onclick="aceitarTermos()" class="btn btn-primary btn-sm">
          <i class="fas fa-check"></i> Li e Aceito os Termos
        </button>
      </div>
    </div>

  </div>
</div>
<script>
function aceitarTermos() {
  document.getElementById('modal-termos').style.display = 'none';
  document.getElementById('aceitar-termos').checked = true;
  document.getElementById('btn-criar').disabled = false;
  localStorage.setItem('termos_aceites', '1');

  if (typeof _executarGoogleLogin === 'function' && window._pendingGoogleResponse) {
    _executarGoogleLogin(window._pendingGoogleResponse);
    window._pendingGoogleResponse = null;
  } else if (window._pendingGithubUrl) {
    window.location.href = window._pendingGithubUrl;
    window._pendingGithubUrl = null;
  } else {
    const nome     = (document.getElementById('nome')?.value     || '').trim();
    const username = (document.getElementById('username')?.value || '').trim();
    const email    = (document.getElementById('email')?.value    || '').trim();
    const pass     = (document.getElementById('password')?.value || '').trim();
    const confirm  = (document.getElementById('confirm')?.value  || '').trim();
    if (nome && username && email && pass && confirm) {
      document.querySelector('form[method="POST"]').submit();
    }
  }
}

// Se já aceitou antes, desbloqueia imediatamente sem passar pelo modal
if (localStorage.getItem('termos_aceites') === '1') {
  document.getElementById('aceitar-termos').checked = true;
  document.getElementById('btn-criar').disabled = false;
}
</script>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
