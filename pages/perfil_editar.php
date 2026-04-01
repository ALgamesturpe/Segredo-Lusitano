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

// Recarregar utilizador da BD para garantir campos atualizados
$st_reload = db()->prepare('SELECT * FROM utilizadores WHERE id = ?');
$st_reload->execute([$user['id']]);
$user_reload = $st_reload->fetch();
if ($user_reload) $user = $user_reload;

$erros = [];
$erros_pass = [];
$nome = (string)($user['nome'] ?? '');
$username = (string)($user['username'] ?? '');
$bio = (string)($user['bio'] ?? '');
$avatar_atual = (string)($user['avatar'] ?? '');
$avatar_upload = null;
$avatar_extensoes = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
];

// ── POST: Guardar dados do perfil ─────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_perfil'])) {
    $nome     = trim($_POST['nome']     ?? '');
    $username = trim($_POST['username'] ?? '');
    $bio      = trim($_POST['bio']      ?? '');

    if (mb_strlen($nome) < 2)     $erros['nome']     = 'Nome demasiado curto.';
    if (mb_strlen($username) < 3) $erros['username'] = 'Username com minimo 3 caracteres.';
    if (mb_strlen($bio) > 500)    $erros['bio']      = 'Bio demasiado longa (maximo 500 caracteres).';

    if (!$erros) {
        $check = db()->prepare('SELECT id FROM utilizadores WHERE username = ? AND id <> ? LIMIT 1');
        $check->execute([$username, (int)$user['id']]);
        if ($check->fetch()) $erros['username'] = 'Esse username ja esta em uso.';
    }

    // Processar upload de avatar
    if (isset($_FILES['avatar']) && (int)($_FILES['avatar']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $avatar      = $_FILES['avatar'];
        $avatar_error = (int)($avatar['error']    ?? UPLOAD_ERR_NO_FILE);
        $avatar_size  = (int)($avatar['size']     ?? 0);
        $avatar_tmp   = (string)($avatar['tmp_name'] ?? '');

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
            if (!is_dir(UPLOAD_DIR)) @mkdir(UPLOAD_DIR, 0777, true);
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

// ── POST: Alterar password (apenas para contas com email/password) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['alterar_password'])) {
    $pass_atual = $_POST['pass_atual'] ?? '';
    $pass_nova  = $_POST['pass_nova']  ?? '';
    $pass_conf  = $_POST['pass_conf']  ?? '';

    // Verificar password atual
    if (!password_verify($pass_atual, $user['password'])) {
        $erros_pass['pass_atual'] = 'Password atual incorreta.';
    }
    if (strlen($pass_nova) < 6) {
        $erros_pass['pass_nova'] = 'A nova password deve ter pelo menos 6 caracteres.';
    }
    if ($pass_nova !== $pass_conf) {
        $erros_pass['pass_conf'] = 'As passwords não coincidem.';
    }

    if (!$erros_pass) {
        // Guardar nova password com hash bcrypt
        $hash = password_hash($pass_nova, PASSWORD_BCRYPT, ['cost' => 12]);
        db()->prepare('UPDATE utilizadores SET password = ? WHERE id = ?')->execute([$hash, (int)$user['id']]);
        flash('success', 'Password alterada com sucesso!');
        header('Location: ' . SITE_URL . '/pages/perfil_editar.php');
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

        <!-- ── FORMULÁRIO DO PERFIL ── -->
        <form method="POST" enctype="multipart/form-data" novalidate>
          <input type="hidden" name="guardar_perfil" value="1">

          <!-- Foto de perfil com preview e overlay ao hover -->
          <div class="form-group">
            <label>Foto de Perfil</label>
            <div style="margin-bottom:.85rem;">
              <div id="avatar-wrapper"
                   onclick="document.getElementById('avatar').click()"
                   style="position:relative; width:80px; height:80px; cursor:pointer; border-radius:50%; overflow:hidden;">
                <div class="perfil-avatar" id="avatar-preview" style="width:80px; height:80px; margin:0; font-size:1.8rem;">
                  <?php if ($avatar_atual): ?>
                    <img src="<?= SITE_URL ?>/uploads/locais/<?= h($avatar_atual) ?>" alt="Avatar atual" style="width:100%;height:100%;object-fit:cover;">
                  <?php else: ?>
                    <?= mb_strtoupper(mb_substr($username ?: $user['username'],0,1)) ?>
                  <?php endif; ?>
                </div>
                <div id="avatar-overlay"
                     style="position:absolute;inset:0;background:rgba(0,0,0,.45);border-radius:50%;
                            display:flex;align-items:center;justify-content:center;
                            opacity:0;transition:opacity .2s;">
                  <i class="fa-solid fa-image" style="color:#fff;font-size:1.6rem;"></i>
                </div>
              </div>
            </div>
            <input type="file" id="avatar" name="avatar" accept="image/jpeg,image/png,image/webp" style="display:none;">
            <?php if (isset($erros['avatar'])): ?><div class="form-error"><?= h($erros['avatar']) ?></div><?php endif; ?>
          </div>

          <!-- Nome e Username lado a lado -->
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

          <!-- Bio com contador de caracteres -->
          <div class="form-group">
            <label for="bio">Bio</label>
            <textarea id="bio" name="bio" rows="5" maxlength="500" placeholder="Fala um pouco sobre ti..."
                      style="width:100%; border:1px solid var(--creme-escuro); border-radius:var(--radius); padding:.7rem .85rem;"><?= h($bio) ?></textarea>
            <small id="bio-counter" style="display:block; margin-top:.45rem; color:var(--texto-muted);">500 restantes</small>
            <?php if (isset($erros['bio'])): ?><div class="form-error"><?= h($erros['bio']) ?></div><?php endif; ?>
          </div>

          <div style="display:flex; gap:.6rem; margin-top:.4rem; flex-wrap:wrap;">
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Guardar</button>
            <a href="<?= SITE_URL ?>/pages/perfil.php?id=<?= (int)$user['id'] ?>" class="btn btn-sm">Cancelar</a>
          </div>
        </form>

        <!-- ── ALTERAR PASSWORD (só para contas com email/password) ── -->
        <?php if (($user['tipo_auth'] ?? 'email') === 'email'): ?>
        <div style="margin-top:2.5rem; padding-top:2rem; border-top:1.5px solid var(--creme-escuro);">
          <h2 style="font-size:1.2rem; margin-bottom:.35rem;">
            <i class="fas fa-key" style="color:var(--verde);"></i> Alterar Password
          </h2>
          <p style="font-size:.88rem; color:var(--texto-muted); margin-bottom:1.25rem;">
            Para alterar a tua password, introduz primeiro a password atual.
          </p>

          <form method="POST" novalidate>
            <input type="hidden" name="alterar_password" value="1">

            <!-- Campo de password atual -->
            <div class="form-group">
              <label for="pass_atual">Password Atual</label>
              <input type="password" id="pass_atual" name="pass_atual" placeholder="••••••••" required>
              <?php if (isset($erros_pass['pass_atual'])): ?><div class="form-error"><?= h($erros_pass['pass_atual']) ?></div><?php endif; ?>
            </div>

            <!-- Nova password e confirmação lado a lado -->
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
              <div class="form-group">
                <label for="pass_nova">Nova Password</label>
                <input type="password" id="pass_nova" name="pass_nova" placeholder="Mínimo 6 caracteres" required>
                <?php if (isset($erros_pass['pass_nova'])): ?><div class="form-error"><?= h($erros_pass['pass_nova']) ?></div><?php endif; ?>
              </div>
              <div class="form-group">
                <label for="pass_conf">Confirmar Nova Password</label>
                <input type="password" id="pass_conf" name="pass_conf" placeholder="Repetir password" required>
                <?php if (isset($erros_pass['pass_conf'])): ?><div class="form-error"><?= h($erros_pass['pass_conf']) ?></div><?php endif; ?>
              </div>
            </div>

            <button type="submit" class="btn btn-verde"><i class="fas fa-key"></i> Alterar Password</button>
          </form>
        </div>
        <?php endif; ?>

      </div>
    </div>
  </section>
</div>

<script>
// Contador de caracteres da bio
(function() {
  var bio     = document.getElementById('bio');
  var counter = document.getElementById('bio-counter');
  if (!bio || !counter) return;
  var max    = parseInt(bio.getAttribute('maxlength') || '500', 10);
  var update = function() { counter.textContent = Math.max(0, max - bio.value.length) + ' restantes'; };
  bio.addEventListener('input', update);
  update();
})();

// Limitar enters na bio a 5 linhas
document.getElementById('bio').addEventListener('keydown', function(e) {
  if (e.key === 'Enter') {
    const enters = (this.value.match(/\n/g) || []).length;
    if (enters >= 5) e.preventDefault();
  }
});

// Mostrar overlay ao passar o rato por cima do avatar
const wrapper = document.getElementById('avatar-wrapper');
const overlay = document.getElementById('avatar-overlay');
if (wrapper && overlay) {
  wrapper.addEventListener('mouseenter', () => overlay.style.opacity = '1');
  wrapper.addEventListener('mouseleave', () => overlay.style.opacity = '0');
}

// Pré-visualização do avatar antes de guardar
document.getElementById('avatar').addEventListener('change', function() {
  const file = this.files[0];
  if (!file) return;
  const reader = new FileReader();
  reader.onload = e => {
    const preview = document.getElementById('avatar-preview');
    preview.innerHTML = `<img src="${e.target.result}" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">`;
  };
  reader.readAsDataURL(file);
});
</script>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>