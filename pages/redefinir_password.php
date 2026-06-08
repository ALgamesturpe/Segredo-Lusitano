<?php
// SEGREDO LUSITANO — Redefinir Palavra-passe (Passo 2: código + nova password)
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/mailer.php';

if (auth_user()) { header('Location: ' . SITE_URL . '/index.php'); exit; }

if (empty($_SESSION['recuperar_id'])) {
    header('Location: ' . SITE_URL . '/pages/recuperar_password.php');
    exit;
}

$uid  = (int)$_SESSION['recuperar_id'];
$erro = '';

// Reenviar código
if (isset($_POST['reenviar'])) {
    verificar_csrf();
    $st = db()->prepare('SELECT nome, email FROM utilizadores WHERE id = ?');
    $st->execute([$uid]);
    $u = $st->fetch();
    if ($u) {
        $novo_codigo = gerar_e_guardar_codigo($uid, 'recuperar');
        $enviado = enviar_codigo_verificacao($u['email'], $u['nome'], $novo_codigo, 'recuperar');
        flash($enviado ? 'success' : 'error', $enviado
            ? 'Novo código enviado para o teu email!'
            : 'Erro ao enviar email. Verifica as configurações SMTP.');
    }
    header('Location: ' . SITE_URL . '/pages/redefinir_password.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['codigo'])) {
    verificar_csrf();
    $codigo   = trim(preg_replace('/\D/', '', $_POST['codigo'] ?? ''));
    $password = trim($_POST['password'] ?? '');
    $confirm  = trim($_POST['confirm']  ?? '');

    if (strlen($codigo) !== 6) {
        $erro = 'O código deve ter 6 dígitos.';
    } elseif (strlen($password) < 6) {
        $erro = 'A password deve ter pelo menos 6 caracteres.';
    } elseif (!preg_match('/[A-Z]/', $password)) {
        $erro = 'A password deve conter pelo menos uma letra maiúscula.';
    } elseif (!preg_match('/[0-9]/', $password)) {
        $erro = 'A password deve conter pelo menos um número.';
    } elseif (!preg_match('/[^a-zA-Z0-9]/', $password)) {
        $erro = 'A password deve conter pelo menos um carácter especial.';
    } elseif ($password !== $confirm) {
        $erro = 'As passwords não coincidem.';
    } elseif (!verificar_codigo($uid, $codigo, 'recuperar')) {
        $erro = 'Código inválido ou expirado. Pede um novo código.';
    } else {
        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        db()->prepare('UPDATE utilizadores SET password = ? WHERE id = ?')->execute([$hash, $uid]);
        unset($_SESSION['recuperar_id']);
        flash('success', 'Palavra-passe alterada com sucesso! Podes fazer login.');
        header('Location: ' . SITE_URL . '/pages/login.php');
        exit;
    }
}

// Email mascarado para mostrar ao utilizador
$st = db()->prepare('SELECT email FROM utilizadores WHERE id = ?');
$st->execute([$uid]);
$u = $st->fetch();
$email_mask = '';
if ($u) {
    [$local, $domain] = explode('@', $u['email']);
    $email_mask = substr($local, 0, 2) . str_repeat('*', max(0, strlen($local) - 2)) . '@' . $domain;
}

$page_title = 'Redefinir Palavra-passe';
include dirname(__DIR__) . '/includes/header.php';

$flash_success = flash('success');
$flash_error   = flash('error');
?>

<div class="page-content" style="display:flex;align-items:center;justify-content:center;padding:2rem;min-height:calc(100vh - 72px);">
  <div class="form-container" style="max-width:460px;width:100%;">

    <div style="display:flex;justify-content:center;margin-bottom:2rem;">
      <img src="<?= SITE_URL ?>/assets/images/logo_icon.png" alt="Segredo Lusitano" style="height:80px;width:80px;object-fit:contain;filter:drop-shadow(0 0 10px rgba(201,168,76,.5));">
    </div>

    <h1 class="form-title" style="text-align:center;">Nova Palavra-passe</h1>
    <p class="form-subtitle" style="text-align:center;">
      Introduz o código enviado para<br>
      <strong style="color:var(--dourado);"><?= h($email_mask) ?></strong>
      e define a tua nova palavra-passe.
    </p>

    <?php if ($flash_success): ?>
      <div class="flash flash-success" style="position:static;margin-bottom:1.25rem;border-radius:0;">
        <i class="fas fa-check-circle"></i> <?= h($flash_success) ?>
      </div>
    <?php endif; ?>
    <?php if ($flash_error): ?>
      <div class="flash flash-error" style="position:static;margin-bottom:1.25rem;border-radius:0;">
        <i class="fas fa-exclamation-circle"></i> <?= h($flash_error) ?>
      </div>
    <?php endif; ?>
    <?php if ($erro): ?>
      <div class="flash flash-error" style="position:static;margin-bottom:1.25rem;border-radius:0;">
        <i class="fas fa-exclamation-circle"></i> <?= h($erro) ?>
      </div>
    <?php endif; ?>

    <form method="POST" novalidate>
      <?= csrf_field() ?>

      <div class="form-group">
        <label for="codigo" style="text-align:center;display:block;">Código de Verificação</label>
        <input type="text" id="codigo" name="codigo"
               placeholder="000000"
               maxlength="6"
               autocomplete="one-time-code"
               inputmode="numeric"
               pattern="[0-9]{6}"
               style="text-align:center;font-size:2rem;letter-spacing:.5rem;font-weight:700;padding:.75rem;"
               required autofocus>
        <small style="color:var(--texto-muted);display:block;text-align:center;margin-top:.4rem;">
          <i class="fas fa-clock"></i> Expira em 15 minutos
        </small>
      </div>

      <div class="form-group">
        <label for="password"><i class="fas fa-lock"></i> Nova Palavra-passe</label>
        <input type="password" id="password" name="password" placeholder="Nova password segura" required>
        <div id="pw-requisitos" style="margin-top:.5rem;display:flex;flex-direction:column;gap:.3rem;">
          <div class="pw-req" id="req-length" style="display:flex;align-items:center;gap:.5rem;font-size:.8rem;color:var(--texto-muted);">
            <span class="pw-circle" style="width:16px;height:16px;border-radius:50%;border:2px solid #ccc;display:inline-flex;align-items:center;justify-content:center;flex-shrink:0;transition:all .2s;"></span>
            Mínimo 6 caracteres
          </div>
          <div class="pw-req" id="req-upper" style="display:flex;align-items:center;gap:.5rem;font-size:.8rem;color:var(--texto-muted);">
            <span class="pw-circle" style="width:16px;height:16px;border-radius:50%;border:2px solid #ccc;display:inline-flex;align-items:center;justify-content:center;flex-shrink:0;transition:all .2s;"></span>
            Letra maiúscula (A-Z)
          </div>
          <div class="pw-req" id="req-number" style="display:flex;align-items:center;gap:.5rem;font-size:.8rem;color:var(--texto-muted);">
            <span class="pw-circle" style="width:16px;height:16px;border-radius:50%;border:2px solid #ccc;display:inline-flex;align-items:center;justify-content:center;flex-shrink:0;transition:all .2s;"></span>
            Um número (0-9)
          </div>
          <div class="pw-req" id="req-special" style="display:flex;align-items:center;gap:.5rem;font-size:.8rem;color:var(--texto-muted);">
            <span class="pw-circle" style="width:16px;height:16px;border-radius:50%;border:2px solid #ccc;display:inline-flex;align-items:center;justify-content:center;flex-shrink:0;transition:all .2s;"></span>
            Carácter especial (!@#$...)
          </div>
        </div>
      </div>

      <div class="form-group">
        <label for="confirm"><i class="fas fa-lock"></i> Confirmar Palavra-passe</label>
        <input type="password" id="confirm" name="confirm" placeholder="Repetir password" required>
      </div>

      <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;margin-top:.5rem;">
        Definir Nova Palavra-passe
      </button>
    </form>

    <div style="text-align:center;margin-top:1.25rem;">
      <p style="color:var(--texto-muted);font-size:.9rem;margin-bottom:.75rem;">Não recebeste o email?</p>
      <form method="POST" style="display:inline;">
        <?= csrf_field() ?>
        <button type="submit" name="reenviar" value="1" class="btn btn-sm"
                style="background:rgba(201,168,76,.15);color:var(--dourado);border:1px solid var(--dourado);padding:.4rem 1.2rem;border-radius:0;">
          <i class="fas fa-paper-plane"></i> Reenviar Código
        </button>
      </form>
    </div>

    <div style="text-align:center;margin-top:1rem;">
      <a href="<?= SITE_URL ?>/pages/recuperar_password.php" style="color:var(--texto-muted);font-size:.85rem;">
        <i class="fas fa-arrow-left"></i> Voltar
      </a>
    </div>

  </div>
</div>

<script>
// Auto-avançar para o campo de password ao completar o código
document.getElementById('codigo').addEventListener('input', function() {
  this.value = this.value.replace(/\D/g, '');
  if (this.value.length === 6) document.getElementById('password').focus();
});

// Indicadores visuais da password
function _pwCircle(id, ok) {
  const el = document.getElementById(id);
  if (!el) return;
  const c = el.querySelector('.pw-circle');
  if (ok) {
    c.style.border = '2px solid var(--verde)';
    c.style.background = 'var(--verde)';
    c.innerHTML = '<svg width="10" height="10" viewBox="0 0 10 10"><polyline points="1.5,5 4,7.5 8.5,2.5" stroke="white" stroke-width="1.5" fill="none" stroke-linecap="round" stroke-linejoin="round"/></svg>';
    el.style.color = 'var(--verde-escuro)';
  } else {
    c.style.border = '2px solid #ccc';
    c.style.background = 'transparent';
    c.innerHTML = '';
    el.style.color = 'var(--texto-muted)';
  }
}
document.getElementById('password').addEventListener('input', function() {
  const v = this.value;
  _pwCircle('req-length',  v.length >= 6);
  _pwCircle('req-upper',   /[A-Z]/.test(v));
  _pwCircle('req-number',  /[0-9]/.test(v));
  _pwCircle('req-special', /[^a-zA-Z0-9]/.test(v));
});
</script>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
