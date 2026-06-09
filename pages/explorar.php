<?php
// ============================================================
// SEGREDO LUSITANO — Explorar Locais / Utilizadores
// ============================================================
require_once dirname(__DIR__) . '/includes/functions.php';

$tipo = 'locais';

$page_title = 'Explorar';
$por_pagina = 12;
$pagina     = max(1, (int)($_GET['pagina'] ?? 1));
$offset     = ($pagina - 1) * $por_pagina;
$stories    = [];

$filtro_lat = (float)($_GET['lat'] ?? 0);
$filtro_lng = (float)($_GET['lng'] ?? 0);

$filtros = [
    'regiao'      => $_GET['regiao']      ?? '',
    'categoria'   => $_GET['categoria']   ?? '',
    'dificuldade' => $_GET['dificuldade'] ?? '',
    'pesquisa'    => $_GET['pesquisa']    ?? '',
    'ordem'       => $_GET['ordem']       ?? 'recente',
    'lat'         => $filtro_lat,
    'lng'         => $filtro_lng,
];

$locais     = get_locais($filtros, $por_pagina, $offset);
$total      = count_locais($filtros);
$total_pag  = (int)ceil($total / $por_pagina);
$categorias = get_categorias();
$regioes    = get_regioes();
$utilizadores = [];

$qs = http_build_query(array_filter(array_diff_key($_GET, ['pagina' => ''])));

$extra_head = '';

include dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-content">
<section class="section">
  <div class="container-lg">
    <div class="section-header">
      <h2>Segredos de Portugal</h2>
      <p><?= $total ?> locais secretos descobertos pela nossa comunidade</p>
    </div>


    <!-- FILTROS -->
    <div class="filtros-bar">
      <form class="filtros-form" method="GET">
        <input type="hidden" name="tipo" value="locais">
        <div class="filtro-group filtro-pesquisa">
          <label for="pesquisa">Pesquisa</label>
          <input type="search" id="pesquisa" name="pesquisa" placeholder="Nome do local..."
                value="<?= h($filtros['pesquisa']) ?>" autocomplete="off" style="width:100%;">
        </div>
        <div class="filtros-grid">
          <div class="filtro-group">
            <label for="regiao">Região</label>
            <select id="regiao" name="regiao">
              <option value="">Todas</option>
              <?php foreach ($regioes as $r): ?>
                <option value="<?= $r['id'] ?>" <?= $filtros['regiao'] == $r['id'] ? 'selected' : '' ?>>
                  <?= h($r['nome']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="filtro-group">
            <label for="categoria">Categoria</label>
            <select id="categoria" name="categoria">
              <option value="">Todas</option>
              <?php foreach ($categorias as $c): ?>
                <option value="<?= $c['id'] ?>" <?= $filtros['categoria'] == $c['id'] ? 'selected' : '' ?>>
                  <?= h($c['nome']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="filtro-group">
            <label for="dificuldade">Dificuldade</label>
            <select id="dificuldade" name="dificuldade">
              <option value="">Todas</option>
              <option value="facil"   <?= $filtros['dificuldade']==='facil'   ? 'selected':'' ?>>Fácil</option>
              <option value="medio"   <?= $filtros['dificuldade']==='medio'   ? 'selected':'' ?>>Médio</option>
              <option value="dificil" <?= $filtros['dificuldade']==='dificil' ? 'selected':'' ?>>Difícil</option>
            </select>
          </div>
          <div class="filtro-group">
            <label for="ordem">Ordenar</label>
            <select id="ordem" name="ordem">
              <option value="recente" <?= $filtros['ordem']==='recente' ? 'selected':'' ?>>Mais Recentes</option>
              <option value="antigo"  <?= $filtros['ordem']==='antigo'  ? 'selected':'' ?>>Mais Antigos</option>
              <option value="likes"   <?= $filtros['ordem']==='likes'   ? 'selected':'' ?>>Mais Curtidos</option>
              <option value="vistas"  <?= $filtros['ordem']==='vistas'  ? 'selected':'' ?>>Mais Vistos</option>
            </select>
          </div>
        </div>
        <div class="filtros-actions">
          <button type="button" id="btn-perto-mim" onclick="filtrarPertoDeMim()"
                  class="btn" style="border:1.5px solid var(--verde);color:var(--verde);background:transparent;white-space:nowrap;<?= $filtro_lat ? 'background:var(--verde);color:#fff;' : '' ?>">
            <i class="fas fa-location-crosshairs"></i> Perto de mim
          </button>
          <button type="submit" class="btn btn-verde" style="border:1.5px solid transparent;justify-content:center;"><i class="fas fa-search"></i> Filtrar</button>
          <a href="<?= SITE_URL ?>/pages/explorar.php" class="btn" style="border:1.5px solid var(--creme-escuro);color:var(--texto-muted);background:transparent;justify-content:center;padding:.6rem 1.75rem;font-size:.9rem;">Limpar</a>
        </div>
      </form>
    </div>

    <?php if ($filtro_lat && $filtro_lng): ?>
    <div style="display:flex;align-items:center;gap:.6rem;padding:.65rem 1rem;background:rgba(45,106,79,.08);border:1.5px solid var(--verde-claro);border-radius:var(--radius);margin-bottom:1rem;font-size:.88rem;color:var(--verde-escuro);">
      <i class="fas fa-location-crosshairs"></i>
      <span>A mostrar <strong><?= $total ?></strong> locais num raio de 50 km à tua volta, ordenados por distância.</span>
      <a href="<?= SITE_URL ?>/pages/explorar.php?tipo=locais" style="margin-left:auto;color:var(--texto-muted);font-size:.8rem;text-decoration:none;white-space:nowrap;"><i class="fas fa-times"></i> Limpar</a>
    </div>
    <?php endif; ?>

    <!-- RESULTADOS LOCAIS -->
    <?php if ($locais): ?>
      <div class="cards-grid">
        <?php foreach ($locais as $local): ?>
          <?php include dirname(__DIR__) . '/includes/card_local.php'; ?>
        <?php endforeach; ?>
      </div>

      <!-- PAGINAÇÃO -->
      <?php if ($total_pag > 1): ?>
      <nav class="pagination">
        <?php if ($pagina > 1): ?>
          <a href="?<?= $qs ?>&pagina=<?= $pagina-1 ?>"><i class="fas fa-chevron-left"></i></a>
        <?php endif; ?>
        <?php for ($p = max(1,$pagina-2); $p <= min($total_pag,$pagina+2); $p++): ?>
          <?php if ($p === $pagina): ?>
            <span class="current"><?= $p ?></span>
          <?php else: ?>
            <a href="?<?= $qs ?>&pagina=<?= $p ?>"><?= $p ?></a>
          <?php endif; ?>
        <?php endfor; ?>
        <?php if ($pagina < $total_pag): ?>
          <a href="?<?= $qs ?>&pagina=<?= $pagina+1 ?>"><i class="fas fa-chevron-right"></i></a>
        <?php endif; ?>
      </nav>
      <?php endif; ?>

    <?php else: ?>
      <div class="empty-state">
        <i class="fas fa-search"></i>
        <h3>Nenhum local encontrado</h3>
        <p>Tenta outros filtros ou sê o primeiro a partilhar este tipo de local!</p>
        <a href="<?= SITE_URL ?>/pages/local_novo.php" class="btn btn-primary" style="margin-top:1rem;">Partilhar Local</a>
      </div>
    <?php endif; ?>

  </div>
</section>
</div>

<!-- Script pesquisa dinâmica -->
<script>
function filtrarPertoDeMim() {
  const btn = document.getElementById('btn-perto-mim');
  if (!navigator.geolocation) { alert('O teu browser não suporta geolocalização.'); return; }
  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> A obter localização...';
  navigator.geolocation.getCurrentPosition(pos => {
    window.location.href = '<?= SITE_URL ?>/pages/explorar.php?tipo=locais&lat=' + pos.coords.latitude + '&lng=' + pos.coords.longitude;
  }, () => {
    alert('Ativa o GPS e tenta novamente.');
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-location-crosshairs"></i> Perto de mim';
  }, { enableHighAccuracy: true, timeout: 10000 });
}
</script>
<script>
(function() {
  const input   = document.getElementById('pesquisa');
  const section = document.querySelector('.cards-grid')?.parentElement || document.querySelector('.empty-state')?.parentElement;
  let timer     = null;

  if (!input || !section) return;

  input.addEventListener('input', function() {
    clearTimeout(timer);
    timer = setTimeout(() => {
      const form   = input.closest('form');
      const params = new URLSearchParams(new FormData(form));
      params.set('ajax', '1');

      fetch('<?= SITE_URL ?>/pages/explorar.php?' + params.toString())
        .then(r => r.text())
        .then(html => {
          const parser    = new DOMParser();
          const doc       = parser.parseFromString(html, 'text/html');
          const novoGrid  = doc.querySelector('.cards-grid');
          const novoEmpty = doc.querySelector('.empty-state');
          const velhoGrid  = section.querySelector('.cards-grid');
          const velhoEmpty = section.querySelector('.empty-state');
          const velhoPag   = section.querySelector('.pagination');

          if (velhoGrid)  velhoGrid.remove();
          if (velhoEmpty) velhoEmpty.remove();
          if (velhoPag)   velhoPag.remove();

          if (novoGrid)  section.appendChild(novoGrid);
          if (novoEmpty) section.appendChild(novoEmpty);
        });
    }, 350);
  });
})();
</script>

<?php if ($tipo === 'stories'): ?>
<script>
function previewStoryFoto(input) {
  if (!input.files || !input.files[0]) return;
  const reader = new FileReader();
  reader.onload = e => {
    document.getElementById('story-foto-img').src = e.target.result;
    document.getElementById('story-foto-preview').style.display = 'block';
  };
  reader.readAsDataURL(input.files[0]);
}
function removerStoryFoto() {
  document.querySelector('input[name="story_foto"]').value = '';
  document.getElementById('story-foto-preview').style.display = 'none';
  document.getElementById('story-foto-img').src = '';
}
</script>
<?php endif; ?>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
