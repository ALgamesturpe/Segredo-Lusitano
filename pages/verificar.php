<?php
// ============================================================
// SEGREDO LUSITANO — Verificação de Código
// ============================================================
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/mailer.php';

if (auth_user()) { header('Location: ' . SITE_URL . '/index.php'); exit; }

// Precisa de ter um utilizador pendente na sessão
if (empty($_SESSION['verificar_id']) || empty($_SESSION['verificar_tipo'])) {
    header('Location: ' . SITE_URL . '/pages/login.php');
    exit;
}

$uid  = (int) $_SESSION['verificar_id'];
$tipo = $_SESSION['verificar_tipo']; // 'registo' ou 'login'
$erro = '';
$sucesso = false;

// Reenviar código
if (isset($_POST['reenviar'])) {
    $st = db()->prepare('SELECT nome, email FROM utilizadores WHERE id = ?');
    $st->execute([$uid]);
    $u = $st->fetch();
    if ($u) {
        $novo_codigo = gerar_e_guardar_codigo($uid, $tipo);
        $enviado = enviar_codigo_verificacao($u['email'], $u['nome'], $novo_codigo, $tipo);
        if ($enviado) {
            flash('success', 'Novo código enviado para o teu email!');
        } else {
            $erro = 'Erro ao enviar email. Verifica as configurações SMTP.';
        }
    }
    header('Location: ' . SITE_URL . '/pages/verificar.php');
    exit;
}

// Submeter código
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['codigo'])) {
    $codigo = trim(preg_replace('/\D/', '', $_POST['codigo'] ?? ''));

    if (strlen($codigo) !== 6) {
        $erro = 'O código deve ter 6 dígitos.';
    } elseif (!verificar_codigo($uid, $codigo, $tipo)) {
        $erro = 'Código inválido ou expirado. Pede um novo código.';
    } else {
        // Código correto!
        if ($tipo === 'registo') {
            // Marcar conta como verificada
            db()->prepare('UPDATE utilizadores SET verificado = 1 WHERE id = ?')->execute([$uid]);
        }
        // Iniciar sessão
        $_SESSION['user_id'] = $uid;
        unset($_SESSION['verificar_id'], $_SESSION['verificar_tipo']);

        $msg = $tipo === 'registo'
            ? 'Bem-vindo à comunidade Segredo Lusitano!'
            : 'Bem-vindo de volta!';
        flash('success', $msg);
        header('Location: ' . SITE_URL . '/index.php');
        exit;
    }
}

// Obter email mascarado para mostrar ao utilizador
$st = db()->prepare('SELECT email FROM utilizadores WHERE id = ?');
$st->execute([$uid]);
$u = $st->fetch();
$email_mask = '';
if ($u) {
    [$local, $domain] = explode('@', $u['email']);
    $email_mask = substr($local, 0, 2) . str_repeat('*', max(0, strlen($local)-2)) . '@' . $domain;
}

$page_title = 'Verificar Código';
include dirname(__DIR__) . '/includes/header.php';

$flash_success = flash('success');
?>

<div class="page-content" style="display:flex;align-items:center;justify-content:center;padding:2rem;min-height:calc(100vh - 72px);">
  <div class="form-container" style="max-width:460px;width:100%;">

    <!-- Ícone -->
    <div style="text-align:center;margin-bottom:1.5rem;">
      <div style="width:80px;height:80px;background:#1a3a2a;border:3px solid #c9a84c;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:2rem;">
        📧
      </div>
    </div>

    <h1 class="form-title" style="text-align:center;">Verifica o teu Email</h1>
    <p class="form-subtitle" style="text-align:center;">
      Enviámos um código de 6 dígitos para<br>
      <strong style="color:var(--dourado);"><?= h($email_mask) ?></strong>
    </p>

    <?php if ($flash_success): ?>
      <div class="flash flash-success" style="position:static;margin-bottom:1.25rem;border-radius:3px;">
        <i class="fas fa-check-circle"></i> <?= h($flash_success) ?>
      </div>
    <?php endif; ?>

    <?php if ($erro): ?>
      <div class="flash flash-error" style="position:static;margin-bottom:1.25rem;border-radius:3px;">
        <i class="fas fa-exclamation-circle"></i> <?= h($erro) ?>
      </div>
    <?php endif; ?>

    <!-- Formulário do código -->
    <form method="POST" novalidate>
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

      <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;">
        <i class="fas fa-check"></i> Confirmar Código
      </button>
    </form>

    <!-- Reenviar -->
    <div style="text-align:center;margin-top:1.5rem;">
      <p style="color:var(--texto-muted);font-size:.9rem;margin-bottom:.75rem;">Não recebeste o email?</p>
      <form method="POST" style="display:inline;">
        <button type="submit" name="reenviar" value="1" class="btn btn-sm" style="background:rgba(201,168,76,.15);color:var(--dourado);border:1px solid var(--dourado);padding:.4rem 1.2rem;border-radius:3px;">
          <i class="fas fa-paper-plane"></i> Reenviar Código
        </button>
      </form>
    </div>

    <div style="text-align:center;margin-top:1.5rem;">
      <a href="<?= SITE_URL ?>/pages/login.php" style="color:var(--texto-muted);font-size:.85rem;">
        <i class="fas fa-arrow-left"></i> Voltar ao login
      </a>
    </div>

  </div>
</div>

<script>
// Auto-submit quando 6 dígitos inseridos
document.getElementById('codigo').addEventListener('input', function() {
  this.value = this.value.replace(/\D/g, ''); // só números
  if (this.value.length === 6) {
    this.closest('form').submit();
  }
});
</script>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
