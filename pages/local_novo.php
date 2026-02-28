<?php
// ============================================================
// SEGREDO LUSITANO — Registar Novo Local
// ============================================================
require_once dirname(__DIR__) . '/includes/functions.php';
require_login();

$user       = auth_user();
$categorias = get_categorias();
$regioes    = get_regioes();
$erros      = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'utilizador_id' => $user['id'],
        'nome'          => trim($_POST['nome']         ?? ''),
        'descricao'     => trim($_POST['descricao']    ?? ''),
        'categoria_id'  => (int)($_POST['categoria_id'] ?? 0),
        'regiao_id'     => (int)($_POST['regiao_id']    ?? 0),
        'latitude'      => (float)($_POST['latitude']   ?? 0),
        'longitude'     => (float)($_POST['longitude']  ?? 0),
        'dificuldade'   => $_POST['dificuldade'] ?? 'medio',
        'foto_capa'     => null,
    ];

    if (strlen($data['nome']) < 3)       $erros['nome']        = 'Nome demasiado curto.';
    if (strlen($data['descricao']) < 20) $erros['descricao']   = 'Descrição muito curta (mínimo 20 caracteres).';
    if (!$data['categoria_id'])          $erros['categoria_id']= 'Seleciona uma categoria.';
    if (!$data['regiao_id'])             $erros['regiao_id']   = 'Seleciona uma região.';
    if (!$data['latitude'] || !$data['longitude']) $erros['coords'] = 'Clica no mapa para definir a localização.';
    if (!in_array($data['dificuldade'], ['facil','medio','dificil'])) $data['dificuldade'] = 'medio';

    // Upload foto capa
    if (isset($_FILES['foto_capa']) && $_FILES['foto_capa']['error'] === 0) {
        $f = $_FILES['foto_capa'];
        $allowed = ['image/jpeg','image/png','image/webp'];
        if (!in_array($f['type'], $allowed)) {
            $erros['foto'] = 'Formato inválido. Usa JPG, PNG ou WebP.';
        } elseif ($f['size'] > 5 * 1024 * 1024) {
            $erros['foto'] = 'Ficheiro demasiado grande (máx. 5MB).';
        } else {
            $ext  = pathinfo($f['name'], PATHINFO_EXTENSION);
            $nome = uniqid('capa_') . '.' . $ext;
            if (move_uploaded_file($f['tmp_name'], UPLOAD_DIR . $nome)) {
                $data['foto_capa'] = $nome;
            }
        }
    }

    if (!$erros) {
        $local_id = save_local($data);
        add_pontos($user['id'], PONTOS_LOCAL);
        flash('success', 'Local publicado com sucesso! Ganhaste ' . PONTOS_LOCAL . ' pontos!');
        header('Location: ' . SITE_URL . '/pages/local.php?id=' . $local_id);
        exit;
    }
}

$page_title = 'Partilhar Local';
$extra_scripts = '<script>const SITE_URL="' . SITE_URL . '"; document.addEventListener("DOMContentLoaded", initMiniMap);</script>';
include dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-content">
<section class="section">
  <div class="container" style="max-width:820px;">
    <div class="section-header">
      <span class="label">Contribuir</span>
      <h2>Partilhar um Segredo</h2>
      <p>Ajuda a comunidade a descobrir locais únicos de Portugal.</p>
    </div>

    <div style="background:var(--branco); border-radius:var(--radius-lg); padding:2.5rem; box-shadow:var(--sombra-md);">
      <form method="POST" enctype="multipart/form-data" novalidate>

        <!-- Nome -->
        <div class="form-group">
          <label for="nome">Nome do Local *</label>
          <input type="text" id="nome" name="nome" value="<?= h($_POST['nome'] ?? '') ?>"
                 placeholder="Ex: Cascata da Pedra Furada" required maxlength="150">
          <?php if (isset($erros['nome'])): ?><div class="form-error"><?= h($erros['nome']) ?></div><?php endif; ?>
        </div>

        <!-- Descrição -->
        <div class="form-group">
          <label for="descricao">Descrição *
            <span style="font-weight:400;color:var(--texto-muted);font-size:.82rem;" data-counter-for="descricao">0/2000</span>
          </label>
          <textarea id="descricao" name="descricao" rows="5" data-maxlength="2000"
                    placeholder="Descreve o local, como chegar lá, o que ver, a melhor época para visitar..."><?= h($_POST['descricao'] ?? '') ?></textarea>
          <?php if (isset($erros['descricao'])): ?><div class="form-error"><?= h($erros['descricao']) ?></div><?php endif; ?>
        </div>

        <!-- Categoria + Região + Dificuldade -->
        <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:1rem;">
          <div class="form-group">
            <label for="categoria_id">Categoria *</label>
            <select id="categoria_id" name="categoria_id" required>
              <option value="">-- Seleciona --</option>
              <?php foreach ($categorias as $c): ?>
                <option value="<?= $c['id'] ?>" <?= ($_POST['categoria_id'] ?? '') == $c['id'] ? 'selected' : '' ?>>
                  <?= h($c['nome']) ?>
                </option>
              <?php endforeach; ?>
            </select>
            <?php if (isset($erros['categoria_id'])): ?><div class="form-error"><?= h($erros['categoria_id']) ?></div><?php endif; ?>
          </div>
          <div class="form-group">
            <label for="regiao_id">Região *</label>
            <select id="regiao_id" name="regiao_id" required>
              <option value="">-- Seleciona --</option>
              <?php foreach ($regioes as $r): ?>
                <option value="<?= $r['id'] ?>" <?= ($_POST['regiao_id'] ?? '') == $r['id'] ? 'selected' : '' ?>>
                  <?= h($r['nome']) ?>
                </option>
              <?php endforeach; ?>
            </select>
            <?php if (isset($erros['regiao_id'])): ?><div class="form-error"><?= h($erros['regiao_id']) ?></div><?php endif; ?>
          </div>
          <div class="form-group">
            <label for="dificuldade">Dificuldade</label>
            <select id="dificuldade" name="dificuldade">
              <option value="facil"   <?= ($_POST['dificuldade'] ?? '') === 'facil'   ? 'selected' : '' ?>>Fácil</option>
              <option value="medio"   <?= ($_POST['dificuldade'] ?? 'medio') === 'medio' ? 'selected' : '' ?>>Médio</option>
              <option value="dificil" <?= ($_POST['dificuldade'] ?? '') === 'dificil' ? 'selected' : '' ?>>Difícil</option>
            </select>
          </div>
        </div>

        <!-- Foto Capa -->
        <div class="form-group">
          <label>Foto de Capa</label>
          <div class="upload-area" data-input-id="foto_capa">
            <i class="fas fa-image upload-icon" style="font-size:2.5rem;color:var(--verde-claro);margin-bottom:.75rem;display:block;"></i>
            <p class="upload-label" style="font-weight:500;margin-bottom:.25rem;">Clica ou arrasta a foto aqui</p>
            <small style="color:var(--texto-muted);">JPG, PNG ou WebP &middot; Máx. 5MB</small>
          </div>
          <input type="file" id="foto_capa" name="foto_capa" accept="image/*" style="display:none;">
          <?php if (isset($erros['foto'])): ?><div class="form-error"><?= h($erros['foto']) ?></div><?php endif; ?>
        </div>

        <!-- Mapa -->
        <div class="form-group">
          <label><i class="fas fa-map-pin"></i> Localização no Mapa * <span style="font-weight:400;color:var(--texto-muted);font-size:.82rem;">Clica no mapa para marcar</span></label>
          <?php if (isset($erros['coords'])): ?><div class="form-error" style="margin-bottom:.5rem;"><?= h($erros['coords']) ?></div><?php endif; ?>
          <div style="margin-bottom:.75rem;">
            <button type="button" id="btn-geolocalizacao" class="btn btn-sm btn-verde">
              <i class="fas fa-crosshairs"></i> Usar Localização Atual
            </button>
            <small style="color:var(--texto-muted);margin-left:.75rem;">ou clica no mapa manualmente</small>
          </div>
          <div id="mini-map" style="height:350px; border-radius:var(--radius); border:1.5px solid var(--creme-escuro);"></div>
          <div style="display:flex; gap:1rem; margin-top:.75rem;">
            <div class="form-group" style="margin:0; flex:1;">
              <label for="latitude" style="font-size:.8rem;">Latitude</label>
              <input type="number" step="any" id="latitude" name="latitude"
                     value="<?= h($_POST['latitude'] ?? '') ?>" placeholder="39.5..." readonly
                     style="background:var(--creme-escuro);">
            </div>
            <div class="form-group" style="margin:0; flex:1;">
              <label for="longitude" style="font-size:.8rem;">Longitude</label>
              <input type="number" step="any" id="longitude" name="longitude"
                     value="<?= h($_POST['longitude'] ?? '') ?>" placeholder="-8.0..." readonly
                     style="background:var(--creme-escuro);">
            </div>
          </div>
        </div>

        <div style="display:flex; gap:1rem; margin-top:1rem;">
          <button type="submit" class="btn btn-primary" style="flex:1; justify-content:center;">
            <i class="fas fa-paper-plane"></i> Submeter Local
          </button>
          <a href="<?= SITE_URL ?>/pages/explorar.php" class="btn" style="border:1px solid var(--creme-escuro);color:var(--texto-muted);">
            Cancelar
          </a>
        </div>

        <?php /* Publicação automática - sem necessidade de aprovação */ ?>
      </form>
    </div>
  </div>
</section>
</div>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
