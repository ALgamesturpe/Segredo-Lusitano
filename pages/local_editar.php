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
        $ext  = pathinfo($f['name'], PATHINFO_EXTENSION);
        $nome = uniqid('capa_') . '.' . $ext;
        if (move_uploaded_file($f['tmp_name'], UPLOAD_DIR . $nome)) {
            $data['foto_capa'] = $nome;
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
$extra_scripts = '<script>const SITE_URL="' . SITE_URL . '"; document.addEventListener("DOMContentLoaded", initMiniMap);</script>';
include dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-content">
<section class="section">
  <div class="container" style="max-width:820px;">
    <div class="section-header">
      <span class="label">Editar</span>
      <h2><?= h($local['nome']) ?></h2>
    </div>
    <div style="background:var(--branco);border-radius:var(--radius-lg);padding:2.5rem;box-shadow:var(--sombra-md);">
      <form method="POST" enctype="multipart/form-data">
        <div class="form-group">
          <label for="nome">Nome do Local</label>
          <input type="text" id="nome" name="nome" value="<?= h($d['nome'] ?? '') ?>" required>
          <?php if (isset($erros['nome'])): ?><div class="form-error"><?= h($erros['nome']) ?></div><?php endif; ?>
        </div>
        <div class="form-group">
          <label for="descricao">Descrição</label>
          <textarea id="descricao" name="descricao" rows="5"><?= h($d['descricao'] ?? '') ?></textarea>
          <?php if (isset($erros['descricao'])): ?><div class="form-error"><?= h($erros['descricao']) ?></div><?php endif; ?>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;">
          <div class="form-group">
            <label for="categoria_id">Categoria</label>
            <select id="categoria_id" name="categoria_id">
              <?php foreach ($categorias as $c): ?>
                <option value="<?= $c['id'] ?>" <?= ($d['categoria_id'] ?? 0) == $c['id'] ? 'selected' : '' ?>>
                  <?= h($c['nome']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label for="regiao_id">Região</label>
            <select id="regiao_id" name="regiao_id">
              <?php foreach ($regioes as $r): ?>
                <option value="<?= $r['id'] ?>" <?= ($d['regiao_id'] ?? 0) == $r['id'] ? 'selected' : '' ?>>
                  <?= h($r['nome']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label for="dificuldade">Dificuldade</label>
            <select id="dificuldade" name="dificuldade">
              <option value="facil"   <?= ($d['dificuldade'] ?? '') === 'facil'   ? 'selected' : '' ?>>Fácil</option>
              <option value="medio"   <?= ($d['dificuldade'] ?? '') === 'medio'   ? 'selected' : '' ?>>Médio</option>
              <option value="dificil" <?= ($d['dificuldade'] ?? '') === 'dificil' ? 'selected' : '' ?>>Difícil</option>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label>Nova Foto de Capa (opcional)</label>
          <?php if ($local['foto_capa']): ?>
            <img src="<?= SITE_URL ?>/uploads/locais/<?= h($local['foto_capa']) ?>" style="height:120px;object-fit:cover;border-radius:8px;margin-bottom:.75rem;">
          <?php endif; ?>
          <input type="file" name="foto_capa" accept="image/*" style="padding:.5rem;border:1.5px solid var(--creme-escuro);border-radius:8px;width:100%;">
        </div>
        <div class="form-group">
          <label>Localização</label>
          <div style="margin-bottom:.75rem;">
            <button type="button" id="btn-geolocalizacao" class="btn btn-sm btn-verde">
              <i class="fas fa-crosshairs"></i> Usar Localização Atual
            </button>
            <small style="color:var(--texto-muted);margin-left:.75rem;">ou clica no mapa</small>
          </div>
          <div id="mini-map" style="height:300px;border-radius:var(--radius);border:1.5px solid var(--creme-escuro);"></div>
          <div style="display:flex;gap:1rem;margin-top:.75rem;">
            <input type="number" step="any" id="latitude" name="latitude" value="<?= h($d['latitude'] ?? '') ?>" readonly style="flex:1;padding:.6rem;border:1.5px solid var(--creme-escuro);border-radius:8px;background:var(--creme-escuro);">
            <input type="number" step="any" id="longitude" name="longitude" value="<?= h($d['longitude'] ?? '') ?>" readonly style="flex:1;padding:.6rem;border:1.5px solid var(--creme-escuro);border-radius:8px;background:var(--creme-escuro);">
          </div>
        </div>
        <div style="display:flex;gap:1rem;margin-top:1rem;">
          <button type="submit" class="btn btn-primary" style="flex:1;justify-content:center;">
            <i class="fas fa-save"></i> Guardar Alterações
          </button>
          <a href="<?= SITE_URL ?>/pages/local.php?id=<?= $id ?>" class="btn" style="border:1px solid var(--creme-escuro);color:var(--texto-muted);">Cancelar</a>
        </div>
      </form>
    </div>
  </div>
</section>
</div>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
