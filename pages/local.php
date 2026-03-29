<?php
// SEGREDO LUSITANO — Detalhe do Local
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
$motivos_denuncia = motivos_denuncia();
$local_bloqueado = ((int)($local['bloqueado'] ?? 0) === 1);

// --- POST: Comentário ---
$erro_com = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['comentario'])) {
    if (!$user) { header('Location: ' . SITE_URL . '/pages/login.php'); exit; }
    if ($local_bloqueado) {
        flash('error', 'Este post esta bloqueado e nao aceita novos comentarios.');
        header('Location: ' . SITE_URL . '/pages/local.php?id=' . $id . '#comentarios');
        exit;
    }
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
    if ($local_bloqueado) {
        flash('error', 'Este post esta bloqueado e nao aceita novas imagens.');
        header('Location: ' . SITE_URL . '/pages/local.php?id=' . $id);
        exit;
    }
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

    $ok = reportar(
        (string)($_POST['tipo'] ?? ''),
        (int)($_POST['ref_id'] ?? 0),
        $user['id'],
        (string)($_POST['motivo'] ?? '')
    );

    if ($ok) {
        flash('success', 'Denuncia registada. Obrigado!');
    } else {
        flash('error', 'Nao foi possivel registar a denuncia (duplicada, invalida ou proibida).');
    }
    header('Location: ' . SITE_URL . '/pages/local.php?id=' . $id);
    exit;
}

$page_title = local_nome_publico($local);
include dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-content">

<!-- HERO DO LOCAL -->
<div class="detalhe-hero">
  <?php if ($local['foto_capa']): ?>
    <img src="<?= SITE_URL ?>/uploads/locais/<?= h($local['foto_capa']) ?>" alt="<?= h(local_nome_publico($local)) ?>">
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
    <h1><?= h(local_nome_publico($local)) ?></h1>
  </div>
</div>

<!-- CONTEUDO -->
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
          <?php if ($user): ?>
            <a href="<?= SITE_URL ?>/pages/mapa.php?abrir=<?= $id ?>" class="btn btn-sm btn-verde">
              <i class="fas fa-map"></i> Ver no Mapa
            </a>
          <?php else: ?>
            <a href="<?= SITE_URL ?>/pages/login.php" class="btn btn-sm btn-verde">
              <i class="fas fa-map"></i> Ver no Mapa
            </a>
          <?php endif; ?>
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
            <button onclick="abrirModalDenuncia('local', <?= $id ?>, 'Local')"
                    class="btn btn-sm" style="color:var(--texto-muted);border:1px solid var(--creme-escuro);border-radius:50px;">
              <i class="fas fa-flag"></i> Denunciar
            </button>
          <?php endif; ?>
        </div>

        <!-- Descrição -->
        <div class="info-card" style="margin-bottom:1.5rem;">
          <h3><i class="fas fa-align-left"></i> Descrição</h3>
          <p class="text-wrap-anywhere" style="line-height:1.8; color:var(--texto);"><?= nl2br(h(local_descricao_publica($local))) ?></p>
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
        <?php if ($user && !$local_bloqueado): ?>
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

          <?php if ($local_bloqueado): ?>
            <p style="margin-bottom:1.5rem; color:var(--texto-muted); font-size:.9rem;">
              Este post esta bloqueado. Novos comentarios estao desativados.
            </p>
          <?php elseif ($user): ?>
            <form method="POST" style="margin-bottom:1.5rem;">
              <div class="form-group" style="margin-bottom:.75rem;">
                <label for="comentario-local" style="display:none;">Comentario</label>
                <textarea id="comentario-local" name="comentario" rows="3" placeholder="Partilha a tua experiência neste local..."
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
                <?php $comentario_bloqueado = ((int)$com['denunciado'] === 1); ?>
                <div class="comentario">
                  <div class="comentario-avatar">
                    <?php if (!$comentario_bloqueado && !empty($com['avatar'])): ?>
                      <img src="<?= SITE_URL ?>/uploads/locais/<?= h($com['avatar']) ?>" alt="<?= h($com['autor_nome']) ?>">
                    <?php else: ?>
                      <?= $comentario_bloqueado ? '!' : mb_strtoupper(mb_substr($com['username'],0,1)) ?>
                    <?php endif; ?>
                  </div>
                  <div class="comentario-body">
                    <div class="comentario-meta" style="display:flex;align-items:center;gap:.45rem;flex-wrap:wrap;">
                      <strong><?= h(comentario_autor_publico($com)) ?></strong>
                      &bull; <?= date('d M Y', strtotime($com['criado_em'])) ?>
                      <?php if ($user && !$comentario_bloqueado && $user['id'] !== (int)$com['utilizador_id']): ?>
                        <button type="button"
                                onclick="abrirModalDenuncia('comentario', <?= (int)$com['id'] ?>, 'Comentario')"
                                class="btn btn-sm"
                                style="margin-left:.35rem;padding:.2rem .55rem;border:1px solid var(--creme-escuro);color:var(--texto-muted);border-radius:999px;">
                          <i class="fas fa-flag"></i> Denunciar
                        </button>
                      <?php endif; ?>
                    </div>
                    <div class="comentario-texto"><?= nl2br(h(comentario_texto_publico($com))) ?></div>
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
        <div class="info-card" style="padding:0; overflow:hidden; position:relative;">
          <div id="mini-map-detalhe" style="height:220px; border-radius:var(--radius-lg);"></div>
          <button onclick="<?= $user ? 'abrirMapaFullscreen()' : 'window.location.href=\'' . SITE_URL . '/pages/login.php\'' ?>"
                  style="position:absolute;top:.6rem;right:.6rem;z-index:999;
                        background:var(--verde-escuro);color:#fff;border:none;
                        border-radius:8px;padding:.4rem .65rem;cursor:pointer;
                        font-size:.8rem;display:flex;align-items:center;gap:.35rem;
                        box-shadow:0 2px 8px rgba(0,0,0,.3);">
            <i class="fas fa-expand"></i> Expandir
          </button>
          <div id="mapa-estado" style="position:absolute;bottom:.6rem;left:.6rem;z-index:999;
              background:rgba(26,58,42,.85);color:#c9a84c;font-size:.75rem;
              padding:.3rem .65rem;border-radius:6px;display:none;">
            <i class="fas fa-spinner fa-spin"></i> A obter localização...
          </div>
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
            <div class="rank-avatar">
              <?php if (!empty($local['avatar'])): ?>
                <img src="<?= SITE_URL ?>/uploads/locais/<?= h($local['avatar']) ?>" alt="<?= h($local['autor_nome']) ?>">
              <?php else: ?>
                <?= mb_strtoupper(mb_substr($local['username'],0,1)) ?>
              <?php endif; ?>
            </div>
            <div class="rank-user-info">
              <div style="font-weight:700;"><?= h($local['autor_nome']) ?></div>
              <a href="<?= SITE_URL ?>/pages/perfil.php?id=<?= $local['utilizador_id'] ?>"
                 style="color:var(--verde); font-size:.85rem;"><?= h($local['username']) ?></a>
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
          <?php if ($user): ?>
            <a href="https://www.google.com/maps/dir/?api=1&destination=<?= $local['latitude'] ?>,<?= $local['longitude'] ?>"
              target="_blank" rel="noopener"
              class="btn btn-sm btn-verde" style="margin-top:.75rem; width:100%;">
              <i class="fas fa-external-link-alt"></i> Abrir no Google Maps
            </a>
          <?php else: ?>
            <a href="<?= SITE_URL ?>/pages/login.php" class="btn btn-sm btn-verde" style="margin-top:.75rem; width:100%;">
              <i class="fas fa-sign-in-alt"></i> Inicia sessão para navegar
            </a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- MODAL MAPA FULLSCREEN -->
<div id="modal-mapa" style="display:none;position:fixed;inset:0;z-index:5000;background:#000;flex-direction:column;">
  <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.5rem;padding:.75rem 1rem;background:var(--verde-escuro);color:#fff;">
    <span style="font-family:'Playfair Display',serif;color:var(--dourado);font-weight:700;">
      <i class="fas fa-map"></i> <?= h(local_nome_publico($local)) ?>
    </span>
    <!-- Modos de transporte -->
    <div style="display:flex;gap:.4rem;">
      <button class="btn-modo" data-modo="driving" onclick="mudarModo('driving')"
              style="background:var(--dourado);color:var(--verde-escuro);border:none;border-radius:8px;
                     padding:.35rem .75rem;cursor:pointer;font-size:.82rem;font-weight:700;display:flex;align-items:center;gap:.3rem;">
        <i class="fas fa-car"></i> Carro
      </button>
      <button class="btn-modo" data-modo="foot" onclick="mudarModo('foot')"
              style="background:rgba(255,255,255,.15);color:#fff;border:none;border-radius:8px;
                     padding:.35rem .75rem;cursor:pointer;font-size:.82rem;display:flex;align-items:center;gap:.3rem;">
        <i class="fas fa-walking"></i> A Pé
      </button>
      <button class="btn-modo" data-modo="bike" onclick="mudarModo('bike')"
              style="background:rgba(255,255,255,.15);color:#fff;border:none;border-radius:8px;
                     padding:.35rem .75rem;cursor:pointer;font-size:.82rem;display:flex;align-items:center;gap:.3rem;">
        <i class="fas fa-bicycle"></i> Bicicleta
      </button>
    </div>
    <button onclick="fecharMapaFullscreen()"
            style="background:rgba(255,255,255,.15);border:none;color:#fff;
                   border-radius:8px;padding:.4rem .85rem;cursor:pointer;font-size:.9rem;">
      <i class="fas fa-times"></i> Fechar
    </button>
  </div>
  <!-- Info da rota -->
  <div id="rota-info" style="display:none;background:var(--verde-escuro);color:#fff;
     padding:.5rem 1rem;font-size:.85rem;border-top:1px solid rgba(201,168,76,.3);
     flex-shrink:0;">
    <i class="fas fa-route" style="color:var(--dourado);margin-right:.4rem;"></i>
    <span id="rota-info-texto"></span>
  </div>
  <div id="mapa-fullscreen" style="flex:1;"></div>
</div>

<!-- MODAL DENUNCIA -->
<div id="modal-denuncia" style="display:none; position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:3000;align-items:center;justify-content:center;padding:1rem;">
  <div style="background:#fff;border-radius:var(--radius-lg);padding:2rem;max-width:460px;width:100%;box-shadow:0 8px 32px rgba(0,0,0,.18);">
    <div style="display:flex;align-items:center;gap:.6rem;margin-bottom:1.25rem;">
      <i class="fas fa-flag" style="color:#e74c3c;font-size:1.1rem;"></i>
      <h3 style="margin:0;font-size:1.1rem;"><span id="denuncia-titulo">Denunciar</span></h3>
    </div>
    <form method="POST">
      <input type="hidden" name="denunciar" value="1">
      <input type="hidden" name="tipo" id="denuncia-tipo" value="local">
      <input type="hidden" name="ref_id" id="denuncia-ref-id" value="<?= $id ?>">

      <div style="margin-bottom:1.25rem;">
        <p style="font-size:.85rem;color:var(--texto-muted);margin-bottom:.75rem;">Seleciona o motivo da denúncia:</p>
        <div style="display:flex;flex-direction:column;gap:.5rem;">
          <?php
            $motivo_icones = [
              'spam'             => 'fas fa-ban',
              'discurso_odio'    => 'fas fa-angry',
              'conteudo_sexual'  => 'fas fa-eye-slash',
              'informacao_falsa' => 'fas fa-times-circle',
            ];
          ?>
          <?php foreach ($motivos_denuncia as $valor => $rotulo): ?>
            <label style="display:flex;align-items:center;gap:.75rem;padding:.75rem 1rem;border:1.5px solid var(--creme-escuro);border-radius:var(--radius);cursor:pointer;transition:border-color .15s,background .15s;"
                   onmouseover="this.style.borderColor='var(--verde)';this.style.background='var(--creme)'"
                   onmouseout="this.style.borderColor='var(--creme-escuro)';this.style.background='#fff'"
                   onclick="this.style.borderColor='var(--verde)';this.style.background='var(--creme)'">
              <input type="radio" name="motivo" value="<?= h($valor) ?>" required style="accent-color:var(--verde);width:16px;height:16px;flex-shrink:0;">
              <i class="<?= $motivo_icones[$valor] ?? 'fas fa-flag' ?>" style="color:var(--verde);width:16px;text-align:center;flex-shrink:0;"></i>
              <span style="font-size:.92rem;font-weight:500;"><?= h($rotulo) ?></span>
            </label>
          <?php endforeach; ?>
        </div>
      </div>

      <div style="display:flex;gap:.75rem;">
        <button type="submit" class="btn btn-danger" style="flex:1;justify-content:center;"><i class="fas fa-paper-plane"></i> Enviar Denúncia</button>
        <button type="button" onclick="document.getElementById('modal-denuncia').style.display='none'" class="btn" style="border:1px solid var(--creme-escuro);color:var(--texto-muted);">Cancelar</button>
      </div>
    </form>
  </div>
</div>
</div>

<!-- Mapa mini sidebar -->
<script>
const SITE_URL = "<?= SITE_URL ?>";
function abrirModalDenuncia(tipo, refId, alvo) {
  document.getElementById('denuncia-titulo').textContent = 'Denunciar ' + alvo;
  document.getElementById('denuncia-tipo').value = tipo;
  document.getElementById('denuncia-ref-id').value = refId;
  const radios = document.querySelectorAll('#modal-denuncia input[name="motivo"]');
  radios.forEach((r) => { r.checked = false; });
  document.getElementById('modal-denuncia').style.display = 'flex';
}

document.addEventListener('DOMContentLoaded', () => {
  const destLat = <?= $local['latitude'] ?>;
  const destLng = <?= $local['longitude'] ?>;

  // ── Mapa mini ────────────────────────────────────────────
  const map2 = L.map('mini-map-detalhe', { zoomControl:false, dragging:false, scrollWheelZoom:false })
    .setView([destLat, destLng], 15);
  L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
    attribution: '© CARTO', maxZoom: 18
  }).addTo(map2);
  const iconePersonalizado = L.divIcon({
  className: '',
  html: `<i class="fa-solid fa-location-dot" style="color:#2d6a4f;font-size:2rem;"></i>`,  iconSize: [20, 20],
  iconAnchor: [10, 32]
});
L.marker([destLat, destLng], { icon: iconePersonalizado }).addTo(map2)
  .on('click', () => {
    <?php if ($user): ?>
      window.open(`https://www.google.com/maps?q=${destLat},${destLng}`, '_blank');
    <?php else: ?>
      window.location.href = '<?= SITE_URL ?>/pages/login.php';
    <?php endif; ?>
  });


  

  if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition(pos => {
      L.circleMarker([pos.coords.latitude, pos.coords.longitude], {
        radius: 7, fillColor: '#269b3f', fillOpacity: 1, color: '#fff', weight: 2
      }).addTo(map2);
      map2.fitBounds(L.latLngBounds(
        [pos.coords.latitude, pos.coords.longitude], [destLat, destLng]
      ), { padding: [20, 20] });
    }, () => {});
  }

  // ── Mapa fullscreen ───────────────────────────────────────
  let mapFS = null;
  let rotaLayer = null;
  let userMarker = null;
  let modoAtual = 'driving';
  let userLat = null;
  let userLng = null;

  const osrmModo = { driving: 'driving', foot: 'foot', bike: 'bike' };
  const modoCores = { driving: '#c9a84c', foot: '#2d6a4f', bike: '#3498db' };

  window.abrirMapaFullscreen = function() {
    document.getElementById('modal-mapa').style.display = 'flex';
    if (!mapFS) {
      mapFS = L.map('mapa-fullscreen').setView([destLat, destLng], 13);
      L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
        attribution: '© CARTO', maxZoom: 18
      }).addTo(mapFS);
      L.marker([destLat, destLng], { icon: iconePersonalizado }).addTo(mapFS)
        .bindPopup('<strong><?= h(local_nome_publico($local)) ?></strong><br><?= h($local['regiao_nome']) ?>').openPopup();
    }
    setTimeout(() => mapFS.invalidateSize(), 100);

    if (!userLat) {
      pedirLocalizacao();
    } else {
      tracarRota(userLat, userLng, modoAtual);
    }
  };

  window.fecharMapaFullscreen = function() {
    document.getElementById('modal-mapa').style.display = 'none';
  };

  window.mudarModo = function(modo) {
    modoAtual = modo;
    // Atualizar visual dos botões
    document.querySelectorAll('.btn-modo').forEach(btn => {
      const ativo = btn.dataset.modo === modo;
      btn.style.background = ativo ? 'var(--dourado)' : 'rgba(255,255,255,.15)';
      btn.style.color = ativo ? 'var(--verde-escuro)' : '#fff';
      btn.style.fontWeight = ativo ? '700' : '400';
    });
    if (userLat) tracarRota(userLat, userLng, modo);
  };

  function pedirLocalizacao() {
    const estado = document.getElementById('mapa-estado');
    if (!navigator.geolocation) return;
    estado.style.display = 'block';
    navigator.geolocation.getCurrentPosition(
      pos => {
        estado.style.display = 'none';
        userLat = pos.coords.latitude;
        userLng = pos.coords.longitude;

        if (userMarker) userMarker.remove();
        userMarker = L.circleMarker([userLat, userLng], {
          radius: 8, fillColor: '#2d6a4f', fillOpacity: 1, color: '#fff', weight: 2
        }).addTo(mapFS).bindPopup('A tua localização');

        tracarRota(userLat, userLng, modoAtual);
      },
      () => { estado.style.display = 'none'; }
    );
  }

  function tracarRota(uLat, uLng, modo) {
    const url = `https://router.project-osrm.org/route/v1/${osrmModo[modo]}/${uLng},${uLat};${destLng},${destLat}?overview=full&geometries=geojson`;
    fetch(url)
      .then(r => r.json())
      .then(data => {
        if (!data.routes || !data.routes[0]) return;
        if (rotaLayer) rotaLayer.remove();
        rotaLayer = L.geoJSON(data.routes[0].geometry, {
          style: { color: modoCores[modo], weight: 4, opacity: .9 }
        }).addTo(mapFS);

        mapFS.fitBounds(L.latLngBounds([[uLat, uLng], [destLat, destLng]]), { padding: [50, 50] });

        const dist = (data.routes[0].distance / 1000).toFixed(1);
        const velocidades = { driving: 100, foot: 4, bike: 25 };
        const minsCalc = Math.round((data.routes[0].distance / 1000) / velocidades[modo] * 60);
        const tempo = minsCalc >= 60 ? Math.floor(minsCalc/60) + 'h ' + (minsCalc%60) + 'min' : minsCalc + ' min';
        const icones = { driving: '🚗', foot: '🚶', bike: '🚲' };
        const infoEl = document.getElementById('rota-info');
        //onde aparece o tempo e distancia da rota
        document.getElementById('rota-info-texto').textContent =
          `${icones[modo]} ${dist} km · ⏱ ${tempo}`;
        infoEl.style.display = 'block';
      })
      .catch(() => {
        mapFS.fitBounds([[uLat, uLng], [destLat, destLng]], { padding: [40, 40] });
      });
  }
});
</script>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
