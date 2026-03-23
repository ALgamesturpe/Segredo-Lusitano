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
$avatar_atual = (string)($user['avatar'] ?? '');
$avatar_upload = null;
$avatar_extensoes = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
];

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

    if (isset($_FILES['avatar']) && (int)($_FILES['avatar']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $avatar = $_FILES['avatar'];
        $avatar_error = (int)($avatar['error'] ?? UPLOAD_ERR_NO_FILE);
        $avatar_size = (int)($avatar['size'] ?? 0);
        $avatar_tmp = (string)($avatar['tmp_name'] ?? '');

        if ($avatar_error !== UPLOAD_ERR_OK) {
            $erros['avatar'] = 'Nao foi possivel carregar a foto de perfil.';
        } elseif ($avatar_size > 5 * 1024 * 1024) {
            $erros['avatar'] = 'A foto de perfil nao pode exceder 5MB.';
        } elseif (!is_uploaded_file($avatar_tmp)) {
            $erros['avatar'] = 'Upload invalido.';
        } else {
            $mime = function_exists('mime_content_type') ? mime_content_type($avatar_tmp) : '';
            if (!isset($avatar_extensoes[$mime])) {
                $erros['avatar'] = 'Formato invalido. Usa JPG, PNG ou WebP.';
            } else {
                $avatar_upload = [
                    'tmp_name' => $avatar_tmp,
                    'filename' => 'avatar_' . (int)$user['id'] . '_' . bin2hex(random_bytes(8)) . '.' . $avatar_extensoes[$mime],
                ];
            }
        }
    }

    if (!$erros) {
        $avatar_guardar = $avatar_atual !== '' ? $avatar_atual : null;

        if ($avatar_upload) {
            if (!is_dir(UPLOAD_DIR)) {
                @mkdir(UPLOAD_DIR, 0777, true);
            }

            $destino = UPLOAD_DIR . $avatar_upload['filename'];
            if (!move_uploaded_file($avatar_upload['tmp_name'], $destino)) {
                $erros['avatar'] = 'Falha ao guardar a foto de perfil';
            } else {
                $avatar_guardar = $avatar_upload['filename'];
            }
        }

        if (!$erros) {
            try {
                $st = db()->prepare('UPDATE utilizadores SET nome = ?, username = ?, bio = ?, avatar = ? WHERE id = ?');
                $st->execute([$nome, $username, $bio !== '' ? $bio : null, $avatar_guardar, (int)$user['id']]);

                if ($avatar_upload && $avatar_atual !== '' && $avatar_atual !== $avatar_guardar) {
                    apagar_upload_local($avatar_atual);
                }

                flash('success', 'Perfil atualizado.');
                header('Location: ' . SITE_URL . '/pages/perfil.php?id=' . (int)$user['id']);
                exit;
            } catch (Throwable $e) {
                if ($avatar_upload && isset($avatar_guardar) && is_string($avatar_guardar) && $avatar_guardar !== '' && $avatar_guardar !== $avatar_atual) {
                    apagar_upload_local($avatar_guardar);
                }
                $erros['avatar'] = 'Nao foi possivel guardar a foto de perfil';
            }
        }
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

        <form method="POST" enctype="multipart/form-data" novalidate>
          <div class="form-group">
            <label>Foto de Perfil</label>
            <div style="display:flex; align-items:center; gap:1rem; margin-bottom:.85rem; flex-wrap:wrap;">
              <div class="perfil-avatar" style="width:72px; height:72px; margin:0; font-size:1.6rem;">
                <?php if ($avatar_atual): ?>
                  <img src="<?= SITE_URL ?>/uploads/locais/<?= h($avatar_atual) ?>" alt="Avatar atual">
                <?php else: ?>
                  <?= mb_strtoupper(mb_substr($username ?: $user['username'],0,1)) ?>
                <?php endif; ?>
              </div>
              <div>
                <strong style="display:block; margin-bottom:.2rem;">Foto atual</strong>
                <small style="color:var(--texto-muted);">JPG, PNG ou WebP &middot; Máx. 5MB</small>
              </div>
            </div>
            <div class="upload-area" data-input-id="avatar">
              <i class="fas fa-user-circle upload-icon" style="font-size:2.5rem;color:var(--verde-claro);margin-bottom:.75rem;display:block;"></i>
              <p class="upload-label" style="font-weight:500;margin-bottom:.25rem;">Clica ou arrasta a nova foto aqui</p>
              <small style="color:var(--texto-muted);">A imagem atual so muda depois de guardares</small>
            </div>
            <input type="file" id="avatar" name="avatar" accept="image/jpeg,image/png,image/webp" style="display:none;">
            <?php if (isset($erros['avatar'])): ?><div class="form-error"><?= h($erros['avatar']) ?></div><?php endif; ?>
          </div>

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
            <label for="bio">Bio</label>
            <textarea id="bio" name="bio" rows="5" maxlength="500" placeholder="Fala um pouco sobre ti..." style="width:100%; border:1px solid var(--creme-escuro); border-radius:var(--radius); padding:.7rem .85rem;"><?= h($bio) ?></textarea>
            <small id="bio-counter" style="display:block; margin-top:.45rem; color:var(--texto-muted);">500 restantes</small>
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

<script>
document.getElementById('bio').addEventListener('keydown', function(e) {
    if (e.key === 'Enter') {
        const enters = (this.value.match(/\n/g) || []).length;
        if (enters >= 5) {
            e.preventDefault();
        }
    }
});
</script>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
