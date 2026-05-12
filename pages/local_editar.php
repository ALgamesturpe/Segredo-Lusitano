<?php
// ============================================================
// SEGREDO LUSITANO — Editar Local
// ============================================================
require_once dirname(__DIR__) . '/includes/functions.php';
require_login();

$user  = auth_user();
$id    = (int)($_GET['id'] ?? 0);
$local = $id ? get_local($id) : null;

if (!$local || ($local['utilizador_id'] != $user['id'] && !is_admin())) {
    flash('error', 'Não tens permissão para editar este local.');
    header('Location: ' . SITE_URL . '/index.php');
    exit;
}

$categorias = get_categorias();
$regioes    = get_regioes();
$erros      = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'nome'         => trim($_POST['nome']         ?? ''),
        'descricao'    => trim($_POST['descricao']    ?? ''),
        'categoria_id' => (int)($_POST['categoria_id'] ?? 0),
        'regiao_id'    => (int)($_POST['regiao_id']    ?? 0),
        'latitude'     => (float)($_POST['latitude']   ?? 0),
        'longitude'    => (float)($_POST['longitude']  ?? 0),
        'dificuldade'  => $_POST['dificuldade'] ?? 'medio',
        'foto_capa'    => null,
    ];

    if (strlen($data['nome']) < 3)       $erros['nome']      = 'Nome demasiado curto.';
    if (strlen($data['descricao']) < 20) $erros['descricao'] = 'Descrição muito curta.';

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
        save_local($data, $id);
        flash('success', 'Local atualizado com sucesso!');
        header('Location: ' . SITE_URL . '/pages/local.php?id=' . $id);
        exit;
    }
}

// Preencher com dados atuais se não houve POST
$d = ($_SERVER['REQUEST_METHOD'] === 'POST') ? $_POST : $local;

$page_title = 'Editar Local';
$extra_head = '<style>
  .novo-local-grid { display:grid; grid-template-columns:1fr 400px; gap:3rem; align-items:start; }
  .novo-local-map-col {
    position:sticky;
    top:calc(var(--nav-h) + 1rem);
    display:flex;
    flex-direction:column;
  }
  #mini-map { height:660px; }
  @media (max-width:900px) {
    .novo-local-grid { grid-template-columns:1fr; }
    .novo-local-map-col { position:static; }
    #mini-map { height:320px; }
  }
</style>';
$extra_scripts = '<script>const SITE_URL="' . SITE_URL . '"; document.addEventListener("DOMContentLoaded", initMiniMap);</script>';
include dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-content">
<section class="section">
  <div class="container" style="max-width:1060px;">

    <div style="margin-bottom:2rem;">
      <span class="label">Editar · Local</span>
      <h2 style="font-size:clamp(1.8rem,4vw,2.6rem);margin-bottom:.5rem;color:var(--verde-escuro);text-align:left;"><?= h($local['nome']) ?></h2>
    </div>

    <form method="POST" enctype="multipart/form-data" novalidate>

      <!-- Coordenadas hidden — preenchidas pelo JS do mapa -->
      <input type="hidden" id="latitude"  name="latitude"  value="<?= h($d['latitude']  ?? '') ?>">
      <input type="hidden" id="longitude" name="longitude" value="<?= h($d['longitude'] ?? '') ?>">

      <div class="novo-local-grid">

        <!-- ── COLUNA ESQUERDA: campos ── -->
        <div style="background:var(--branco);padding:2rem;">

          <!-- Nome -->
          <div class="form-group">
            <label for="nome">Nome do Local</label>
            <input type="text" id="nome" name="nome" value="<?= h($d['nome'] ?? '') ?>" required maxlength="150">
            <?php if (isset($erros['nome'])): ?><div class="form-error"><?= h($erros['nome']) ?></div><?php endif; ?>
          </div>

          <!-- Região + Categoria -->
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
            <div class="form-group">
              <label for="regiao_id">Região</label>
              <select id="regiao_id" name="regiao_id" required>
                <?php foreach ($regioes as $r): ?>
                  <option value="<?= $r['id'] ?>" <?= ($d['regiao_id'] ?? 0) == $r['id'] ? 'selected' : '' ?>>
                    <?= h($r['nome']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label for="categoria_id">Categoria</label>
              <select id="categoria_id" name="categoria_id" required>
                <?php foreach ($categorias as $c): ?>
                  <option value="<?= $c['id'] ?>" <?= ($d['categoria_id'] ?? 0) == $c['id'] ? 'selected' : '' ?>>
                    <?= h($c['nome']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <!-- Dificuldade -->
          <div class="form-group">
            <label for="dificuldade">Dificuldade</label>
            <select id="dificuldade" name="dificuldade">
              <option value="facil"   <?= ($d['dificuldade'] ?? '') === 'facil'   ? 'selected' : '' ?>>Fácil</option>
              <option value="medio"   <?= ($d['dificuldade'] ?? '') === 'medio'   ? 'selected' : '' ?>>Médio</option>
              <option value="dificil" <?= ($d['dificuldade'] ?? '') === 'dificil' ? 'selected' : '' ?>>Difícil</option>
            </select>
          </div>

          <!-- Foto de Capa -->
          <div class="form-group">
            <label>Foto de Capa</label>
            <?php if ($local['foto_capa']): ?>
              <img src="<?= SITE_URL ?>/uploads/locais/<?= h($local['foto_capa']) ?>"
                   style="width:100%;height:140px;object-fit:cover;margin-bottom:.6rem;display:block;">
              <p style="font-size:.8rem;color:var(--texto-muted);margin:0 0 .5rem;">Foto atual — arrasta ou seleciona uma nova para substituir</p>
            <?php endif; ?>
            <div class="upload-area" data-input-id="foto_capa" style="padding:1.25rem;">
              <i class="fas fa-image upload-icon" style="font-size:2rem;color:var(--verde-claro);margin-bottom:.5rem;display:block;"></i>
              <p class="upload-label" style="font-weight:500;margin:0 0 .2rem;">Clica ou arrasta a foto aqui</p>
              <small style="color:var(--texto-muted);">JPG, PNG ou WebP &middot; Máx. 5MB</small>
            </div>
            <input type="file" id="foto_capa" name="foto_capa" accept="image/*" style="display:none;">
            <?php if (isset($erros['foto'])): ?><div class="form-error"><?= h($erros['foto']) ?></div><?php endif; ?>
          </div>

          <!-- Descrição -->
          <div class="form-group">
            <label for="descricao">Descrição
              <span style="font-weight:400;color:var(--texto-muted);font-size:.82rem;" data-counter-for="descricao">0/2000</span>
            </label>
            <textarea id="descricao" name="descricao" rows="5" data-maxlength="2000"
                      placeholder="Descreve o local, como chegar lá, o que ver, a melhor época para visitar..."><?= h($d['descricao'] ?? '') ?></textarea>
            <?php if (isset($erros['descricao'])): ?><div class="form-error"><?= h($erros['descricao']) ?></div><?php endif; ?>
          </div>

          <!-- Botões -->
          <div style="display:flex;gap:1rem;margin-top:.5rem;">
            <button type="submit" class="btn btn-primary" style="flex:1;justify-content:center;">
              <i class="fas fa-save"></i> Guardar Alterações
            </button>
            <a href="<?= SITE_URL ?>/pages/local.php?id=<?= $id ?>" class="btn" style="border:1px solid var(--creme-escuro);color:var(--texto-muted);">
              Cancelar
            </a>
          </div>

          <!-- Zona de perigo -->
          <div style="margin-top:1.5rem;padding-top:1.25rem;border-top:1px solid var(--creme-escuro);">
            <a href="<?= SITE_URL ?>/pages/local_apagar.php?id=<?= $id ?>"
               class="btn btn-danger"
               style="width:100%;justify-content:center;"
               onclick="return confirm('Tens a certeza que queres apagar este local? Esta ação é irreversível.')">
              <i class="fas fa-trash"></i> Eliminar Local
            </a>
          </div>

        </div>

        <!-- ── COLUNA DIREITA: mapa (sticky) ── -->
        <div class="novo-local-map-col">
          <div style="background:var(--branco);overflow:hidden;">
            <div style="padding:1rem 1.25rem;">
              <p style="font-size:.85rem;font-weight:600;margin:0 0 .75rem;color:var(--texto);">
                <i class="fas fa-map-pin" style="color:var(--verde);margin-right:.35rem;"></i>Localização <span style="font-weight:400;color:var(--texto-muted);">— Clica no mapa para alterar</span>
              </p>
              <button type="button" id="btn-geolocalizacao" class="btn btn-sm btn-verde" style="width:100%;justify-content:center;">
                <i class="fas fa-crosshairs"></i> Usar Localização Atual
              </button>
            </div>
            <div id="mini-map" style="overflow:hidden;"></div>
          </div>
        </div>

      </div>
    </form>
  </div>
</section>
</div>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
