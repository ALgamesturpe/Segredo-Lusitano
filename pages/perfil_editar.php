<?php
// ============================================================
// SEGREDO LUSITANO — Editar Perfil
// ============================================================
require_once dirname(__DIR__) . '/includes/functions.php';
require_login();

$user = auth_user();
if (!$user) {
    header('Location: ' . SITE_URL . '/pages/login.php');
    exit;
}

$erros = [];
$nome = (string)($user['nome'] ?? '');
$username = (string)($user['username'] ?? '');
$bio = (string)($user['bio'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $bio = trim($_POST['bio'] ?? '');

    if (mb_strlen($nome) < 2) {
        $erros['nome'] = 'Nome demasiado curto.';
    }
    if (mb_strlen($username) < 3) {
        $erros['username'] = 'Username com minimo 3 caracteres.';
    }
    if (mb_strlen($bio) > 500) {
        $erros['bio'] = 'Bio demasiado longa (maximo 500 caracteres).';
    }

    if (!$erros) {
        $check = db()->prepare('SELECT id FROM utilizadores WHERE username = ? AND id <> ? LIMIT 1');
        $check->execute([$username, (int)$user['id']]);
        if ($check->fetch()) {
            $erros['username'] = 'Esse username ja esta em uso.';
        }
    }

    if (!$erros) {
        $st = db()->prepare('UPDATE utilizadores SET nome = ?, username = ?, bio = ? WHERE id = ?');
        $st->execute([$nome, $username, $bio !== '' ? $bio : null, (int)$user['id']]);

        flash('success', 'Perfil atualizado com sucesso.');
        header('Location: ' . SITE_URL . '/pages/perfil.php?id=' . (int)$user['id']);
        exit;
    }
}

$page_title = 'Editar Perfil';
include dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-content" style="padding:calc(var(--nav-h) + 2rem) 0 4rem;">
  <section class="section" style="padding-top:0; padding-bottom:0;">
    <div class="container" style="max-width:760px;">
      <div class="form-container" style="max-width:100%;">
        <h1 class="form-title">Editar Perfil</h1>
        <p class="form-subtitle" style="margin-bottom:1.2rem;">Atualiza os teus dados publicos.</p>

        <form method="POST" novalidate>
          <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
            <div class="form-group">
              <label for="nome">Nome</label>
              <input type="text" id="nome" name="nome" value="<?= h($nome) ?>" required>
              <?php if (isset($erros['nome'])): ?><div class="form-error"><?= h($erros['nome']) ?></div><?php endif; ?>
            </div>
            <div class="form-group">
              <label for="username">Username</label>
              <input type="text" id="username" name="username" value="<?= h($username) ?>" required>
              <?php if (isset($erros['username'])): ?><div class="form-error"><?= h($erros['username']) ?></div><?php endif; ?>
            </div>
          </div>

          <div class="form-group">
            <label for="bio" style="display:flex; align-items:center; justify-content:space-between; gap:.75rem;">
              <span>Bio</span>
              <small id="bio-counter" style="color:var(--texto-muted); font-weight:500;">500 restantes</small>
            </label>
            <textarea id="bio" name="bio" rows="5" maxlength="500" placeholder="Fala um pouco sobre ti..." style="width:100%; border:1px solid var(--creme-escuro); border-radius:var(--radius); padding:.7rem .85rem;"><?= h($bio) ?></textarea>
            <?php if (isset($erros['bio'])): ?><div class="form-error"><?= h($erros['bio']) ?></div><?php endif; ?>
          </div>

          <div style="display:flex; gap:.6rem; margin-top:.4rem; flex-wrap:wrap;">
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Guardar</button>
            <a href="<?= SITE_URL ?>/pages/perfil.php?id=<?= (int)$user['id'] ?>" class="btn btn-sm">Cancelar</a>
          </div>
        </form>
      </div>
    </div>
  </section>
</div>

<script>
(function() {
  var bio = document.getElementById('bio');
  var counter = document.getElementById('bio-counter');
  if (!bio || !counter) return;

  var max = parseInt(bio.getAttribute('maxlength') || '500', 10);
  var update = function() {
    var remaining = Math.max(0, max - bio.value.length);
    counter.textContent = remaining + ' restantes';
  };

  bio.addEventListener('input', update);
  update();
})();
</script>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
