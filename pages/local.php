<?php
// ============================================================
// SEGREDO LUSITANO — Detalhe do Local
// ============================================================
require_once dirname(__DIR__) . '/includes/functions.php';

$id = (int)($_GET['id'] ?? 0);
$local = $id ? get_local($id) : null;

if (!$local || ($local['estado'] !== 'aprovado' && !is_admin())) {
    header('Location: ' . SITE_URL . '/pages/explorar.php');
    exit;
}

incrementar_vistas($id);

$user       = auth_user();
$comentarios = get_comentarios($id);
$fotos      = get_fotos($id);
$liked      = $user ? user_liked($id, $user['id']) : false;

// --- POST: Comentário ---
$erro_com = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['comentario'])) {
    if (!$user) { header('Location: ' . SITE_URL . '/pages/login.php'); exit; }
    $texto = trim($_POST['comentario']);
    if (strlen($texto) < 3) {
        $erro_com = 'O comentário é muito curto.';
    } else {
        add_comentario($id, $user['id'], $texto);
        flash('success', 'Comentário publicado!');
        header('Location: ' . SITE_URL . '/pages/local.php?id=' . $id . '#comentarios');
        exit;
    }
}

// --- POST: Upload de foto ---
$erro_foto = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['fotos'])) {
    if (!$user) { header('Location: ' . SITE_URL . '/pages/login.php'); exit; }
    $files = $_FILES['fotos'];
    $count = count($files['name']);
    for ($i = 0; $i < $count; $i++) {
        $f = [
            'name'     => $files['name'][$i],
            'type'     => $files['type'][$i],
            'tmp_name' => $files['tmp_name'][$i],
            'error'    => $files['error'][$i],
            'size'     => $files['size'][$i],
        ];
        if ($f['error'] === 0) upload_foto($f, $id, $user['id']);
    }
    flash('success', 'Foto(s) adicionada(s)!');
    header('Location: ' . SITE_URL . '/pages/local.php?id=' . $id);
    exit;
}

// --- POST: Denúncia ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['denunciar'])) {
    if (!$user) { header('Location: ' . SITE_URL . '/pages/login.php'); exit; }
    reportar($_POST['tipo'], (int)$_POST['ref_id'], $user['id'], $_POST['motivo'] ?? '');
    flash('success', 'Denúncia registada. Obrigado!');
    header('Location: ' . SITE_URL . '/pages/local.php?id=' . $id);
    exit;
}

$page_title = $local['nome'];
$extra_scripts = '<script>const SITE_URL = "' . SITE_URL . '";</script>';

include dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-content">

<!-- HERO DO LOCAL -->
<div class="detalhe-hero">
  <?php if ($local['foto_capa']): ?>
    <img src="<?= SITE_URL ?>/uploads/locais/<?= h($local['foto_capa']) ?>" alt="<?= h($local['nome']) ?>">
  <?php endif; ?>
  <div class="detalhe-hero-content">
    <div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-bottom:.75rem;">
      <span class="badge badge-cat"><i class="<?= h($local['categoria_icone']) ?>"></i> <?= h($local['categoria_nome']) ?></span>
      <?php
        $dif_class = ['facil'=>'badge-dif-facil','medio'=>'badge-dif-medio','dificil'=>'badge-dif-dificil'][$local['dificuldade']];
        $dif_label = ['facil'=>'Fácil','medio'=>'Médio','dificil'=>'Difícil'][$local['dificuldade']];
      ?>
      <span class="badge <?= $dif_class ?>"><?= $dif_label ?></span>
    </div>
    <h1><?= h($local['nome']) ?></h1>
  </div>
</div>

<!-- CONTEÚDO -->
<section class="section" style="padding-top:2.5rem;">
  <div class="container">
    <div class="detalhe-grid">

      <!-- COLUNA PRINCIPAL -->
      <div>
        <!-- Ações -->
        <div style="display:flex; align-items:center; gap:1rem; margin-bottom:2rem; flex-wrap:wrap;">
          <button class="like-btn <?= $liked ? 'liked' : '' ?>" id="like-btn" data-local="<?= $id ?>">
            <i class="fas fa-heart"></i>
            <span id="like-count"><?= $local['total_likes'] ?></span>
          </button>
          <a href="<?= SITE_URL ?>/pages/mapa.php" class="btn btn-sm btn-verde">
            <i class="fas fa-map"></i> Ver no Mapa
          </a>
          <?php if ($user && ($user['id'] == $local['utilizador_id'] || is_admin())): ?>
            <a href="<?= SITE_URL ?>/pages/local_editar.php?id=<?= $id ?>" class="btn btn-sm btn-outline" style="color:var(--texto-muted);border-color:var(--creme-escuro);">
              <i class="fas fa-edit"></i> Editar
            </a>
            <a href="<?= SITE_URL ?>/pages/local_apagar.php?id=<?= $id ?>"
               class="btn btn-sm btn-danger"
               data-confirm="Tens a certeza que queres apagar este local? Esta ação é irreversível.">
              <i class="fas fa-trash"></i>
            </a>
          <?php endif; ?>
          <?php if ($user && $user['id'] != $local['utilizador_id']): ?>
            <button onclick="document.getElementById('modal-denuncia').style.display='flex'"
                    class="btn btn-sm" style="color:var(--texto-muted);border:1px solid var(--creme-escuro);border-radius:50px;">
              <i class="fas fa-flag"></i> Denunciar
            </button>
          <?php endif; ?>
        </div>

        <!-- Descrição -->
        <div class="info-card" style="margin-bottom:1.5rem;">
          <h3><i class="fas fa-align-left"></i> Descrição</h3>
          <p style="line-height:1.8; color:var(--texto);"><?= nl2br(h($local['descricao'])) ?></p>
        </div>

        <!-- Galeria de Fotos -->
        <?php if ($fotos): ?>
        <div class="info-card" style="margin-bottom:1.5rem;">
          <h3><i class="fas fa-images"></i> Galeria</h3>
          <div class="galeria">
            <?php foreach ($fotos as $foto): ?>
              <div class="galeria-item">
                <img src="<?= SITE_URL ?>/uploads/locais/<?= h($foto['ficheiro']) ?>"
                     alt="Foto do local" loading="lazy">
              </div>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>

        <!-- Upload de Fotos -->
        <?php if ($user): ?>
        <div class="info-card" style="margin-bottom:1.5rem;">
          <h3><i class="fas fa-camera"></i> Adicionar Fotos</h3>
          <form method="POST" enctype="multipart/form-data">
            <div class="upload-area" data-input-id="fotos">
              <i class="fas fa-cloud-upload-alt upload-icon" style="font-size:2.5rem;color:var(--verde-claro);margin-bottom:.75rem;display:block;"></i>
              <p class="upload-label" style="font-weight:500;margin-bottom:.25rem;">Clica ou arrasta as fotos aqui</p>
              <small style="color:var(--texto-muted);">JPG, PNG ou WebP · Máx. 5MB</small>
            </div>
            <input type="file" id="fotos" name="fotos[]" multiple accept="image/*" style="display:none;">
            <button type="submit" class="btn btn-verde" style="margin-top:1rem; width:100%;">
              <i class="fas fa-upload"></i> Enviar Fotos
            </button>
          </form>
        </div>
        <?php endif; ?>

        <!-- Comentários -->
        <div class="info-card" id="comentarios">
          <h3><i class="fas fa-comments"></i> Comentários <span style="color:var(--texto-muted);font-size:.9rem;">(<?= count($comentarios) ?>)</span></h3>

          <?php if ($user): ?>
            <form method="POST" style="margin-bottom:1.5rem;">
              <div class="form-group" style="margin-bottom:.75rem;">
                <textarea name="comentario" rows="3" placeholder="Partilha a tua experiência neste local..."
                          style="width:100%;padding:.75rem 1rem;border:1.5px solid var(--creme-escuro);border-radius:10px;background:var(--creme);resize:vertical;"><?= h($_POST['comentario'] ?? '') ?></textarea>
                <?php if ($erro_com): ?><div class="form-error"><?= h($erro_com) ?></div><?php endif; ?>
              </div>
              <button type="submit" class="btn btn-verde btn-sm"><i class="fas fa-paper-plane"></i> Publicar Comentário</button>
            </form>
          <?php else: ?>
            <p style="margin-bottom:1.5rem; color:var(--texto-muted); font-size:.9rem;">
              <a href="<?= SITE_URL ?>/pages/login.php" class="form-link">Inicia sessão</a> para deixar um comentário.
            </p>
          <?php endif; ?>

          <?php if ($comentarios): ?>
            <div>
              <?php foreach ($comentarios as $com): ?>
                <div class="comentario">
                  <div class="comentario-avatar">
                    <?= mb_strtoupper(mb_substr($com['username'],0,1)) ?>
                  </div>
                  <div class="comentario-body">
                    <div class="comentario-meta">
                      <strong><?= h($com['autor_nome']) ?></strong>
                      &bull; <?= date('d M Y', strtotime($com['criado_em'])) ?>
                    </div>
                    <div class="comentario-texto"><?= nl2br(h($com['texto'])) ?></div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <p style="color:var(--texto-muted);font-size:.9rem;">Ainda sem comentários. Sê o primeiro!</p>
          <?php endif; ?>
        </div>
      </div>

      <!-- SIDEBAR -->
      <div class="detalhe-sidebar">
        <!-- Mapa mini -->
        <div class="info-card" style="padding:0; overflow:hidden;">
          <div id="mini-map-detalhe" style="height:220px; border-radius:var(--radius-lg);"></div>
        </div>

        <!-- Info -->
        <div class="info-card">
          <h3>Informações</h3>
          <div class="info-row">
            <span class="label"><i class="fas fa-map-marker-alt"></i> Região</span>
            <span class="val"><?= h($local['regiao_nome']) ?></span>
          </div>
          <div class="info-row">
            <span class="label"><i class="fas fa-tag"></i> Categoria</span>
            <span class="val"><?= h($local['categoria_nome']) ?></span>
          </div>
          <div class="info-row">
            <span class="label"><i class="fas fa-hiking"></i> Dificuldade</span>
            <span class="val"><?= $dif_label ?></span>
          </div>
          <div class="info-row">
            <span class="label"><i class="fas fa-eye"></i> Visualizações</span>
            <span class="val"><?= number_format($local['vistas']) ?></span>
          </div>
          <div class="info-row">
            <span class="label"><i class="fas fa-calendar"></i> Publicado em</span>
            <span class="val"><?= date('d/m/Y', strtotime($local['criado_em'])) ?></span>
          </div>
        </div>

        <!-- Autor -->
        <div class="info-card">
          <h3>Explorador</h3>
          <div style="display:flex; align-items:center; gap:.75rem;">
            <div class="rank-avatar"><?= mb_strtoupper(mb_substr($local['username'],0,1)) ?></div>
            <div>
              <div style="font-weight:700;"><?= h($local['autor_nome']) ?></div>
              <a href="<?= SITE_URL ?>/pages/perfil.php?id=<?= $local['utilizador_id'] ?>"
                 style="color:var(--verde); font-size:.85rem;">@<?= h($local['username']) ?></a>
            </div>
          </div>
        </div>

        <!-- Coordenadas -->
        <div class="info-card">
          <h3>Coordenadas GPS</h3>
          <code style="font-size:.85rem; word-break:break-all; color:var(--verde-escuro);">
            <?= number_format($local['latitude'],6,',','') ?>°N,
            <?= number_format(abs($local['longitude']),6,',','') ?>°O
          </code>
          <a href="https://www.google.com/maps?q=<?= $local['latitude'] ?>,<?= $local['longitude'] ?>"
             target="_blank" rel="noopener"
             class="btn btn-sm btn-verde" style="margin-top:.75rem; width:100%;">
            <i class="fas fa-external-link-alt"></i> Abrir no Google Maps
          </a>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- MODAL DENÚNCIA -->
<div id="modal-denuncia" style="display:none; position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:3000;align-items:center;justify-content:center;padding:1rem;">
  <div style="background:#fff;border-radius:var(--radius-lg);padding:2rem;max-width:460px;width:100%;">
    <h3 style="margin-bottom:1rem;"><i class="fas fa-flag"></i> Denunciar Local</h3>
    <form method="POST">
      <input type="hidden" name="denunciar" value="1">
      <input type="hidden" name="tipo" value="local">
      <input type="hidden" name="ref_id" value="<?= $id ?>">
      <div class="form-group">
        <label>Motivo</label>
        <textarea name="motivo" rows="4" placeholder="Descreve o problema..." style="width:100%;padding:.75rem;border:1.5px solid var(--creme-escuro);border-radius:10px;"></textarea>
      </div>
      <div style="display:flex;gap:.75rem;">
        <button type="submit" class="btn btn-danger">Enviar Denúncia</button>
        <button type="button" onclick="document.getElementById('modal-denuncia').style.display='none'" class="btn" style="border:1px solid var(--creme-escuro);color:var(--texto-muted);">Cancelar</button>
      </div>
    </form>
  </div>
</div>
</div>

<!-- Mapa mini sidebar -->
<script>
const SITE_URL = "<?= SITE_URL ?>";
document.addEventListener('DOMContentLoaded', () => {
  const map2 = L.map('mini-map-detalhe', { zoomControl:false, dragging:false, scrollWheelZoom:false })
    .setView([<?= $local['latitude'] ?>, <?= $local['longitude'] ?>], 13);
  L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
    attribution: '© CARTO', maxZoom: 18
  }).addTo(map2);
  L.marker([<?= $local['latitude'] ?>, <?= $local['longitude'] ?>]).addTo(map2);
});
</script>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
