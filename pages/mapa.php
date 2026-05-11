<?php
// ============================================================
// SEGREDO LUSITANO — Mapa Interativo
// ============================================================
require_once dirname(__DIR__) . '/includes/functions.php';

$page_title = 'Mapa';

// Ler filtros da URL apenas para pre-selecionar os selects — a filtragem é feita no cliente
$filtro_regiao      = $_GET['regiao']      ?? '';
$filtro_categoria   = $_GET['categoria']   ?? '';
$filtro_dificuldade = $_GET['dificuldade'] ?? '';
$filtro_ordem       = $_GET['ordem']       ?? 'recente';

$categorias = get_categorias();
$regioes    = get_regioes();
// Carregar TODOS os locais — filtragem é feita no JS sem recarregar a página
$locais = get_locais(['excluir_bloqueados' => 1, 'ordem' => 'recente'], 500);

$locais_json = json_encode(array_map(fn($l) => [
    'id'             => $l['id'],
    'nome'           => local_nome_publico($l),
    'latitude'       => $l['latitude'],
    'longitude'      => $l['longitude'],
    'categoria_id'   => $l['categoria_id'],
    'categoria_nome' => $l['categoria_nome'],
    'icone'          => $l['categoria_icone'],
    'regiao_id'      => $l['regiao_id'],
    'regiao_nome'    => $l['regiao_nome'],
    'dificuldade'    => $l['dificuldade'],
    'foto_capa'      => $l['foto_capa'],
    'total_likes'    => $l['total_likes'],
], $locais), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

$tem_filtros = $filtro_regiao || $filtro_categoria || $filtro_dificuldade;

$extra_head = '<style>
body { overflow: hidden; margin:0; padding:0; }
.page-content { height: calc(100vh - var(--nav-h)); display:flex; flex-direction:column; margin:0; padding:0; background:var(--verde-escuro); }
footer, .site-footer { display:none !important; height:0 !important; }
#map { flex:1; min-height:0; }
.mapa-select {
  background: rgba(255,255,255,.08);
  color: var(--creme);
  border: 1px solid rgba(201,168,76,.25);
  border-radius:0;
  padding: .3rem .55rem;
  font-size: .8rem;
  cursor: pointer;
  outline: none;
  min-width: 110px;
  transition: border-color .2s;
}
.mapa-select:focus { border-color: var(--dourado); }
.mapa-select option { background: var(--verde-escuro); color: var(--creme); }
.mapa-filtro-label { font-size: .68rem; color: rgba(245,239,230,.45); letter-spacing: .05em; text-transform: uppercase; display: block; margin-bottom: .2rem; }
.mapa-filtros-bar { background: var(--verde-escuro); border-bottom: 2px solid var(--dourado); padding: .45rem 1.25rem .55rem; flex-shrink: 0; }
.mapa-filtros-top { display: flex; align-items: center; justify-content: space-between; gap: .75rem; margin-bottom: .35rem; }
#form-filtro-mapa { display: flex; align-items: flex-end; gap: .5rem; }
@media (max-width: 600px) {
  .mapa-filtros-bar { padding: .4rem .75rem .45rem; }
  #form-filtro-mapa { overflow-x: auto; gap: .35rem; padding-bottom: .1rem; }
  .mapa-select { min-width: 82px; font-size: .76rem; padding: .22rem .3rem; }
}
</style>';
include dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-content">

  <!-- Barra de filtros -->
  <div class="mapa-filtros-bar">

    <!-- Linha 1: Título + Ver em Lista -->
    <div class="mapa-filtros-top">
      <div style="display:flex;align-items:center;gap:.5rem;">
        <span style="color:var(--dourado);font-family:'Playfair Display',serif;font-weight:700;font-size:.95rem;line-height:1;">
          <i class="fas fa-map"></i> Mapa Interativo
        </span>
        <span id="mapa-count" style="color:rgba(245,239,230,.5);font-size:.75rem;"><?= count($locais) ?> locais</span>
      </div>
      <a href="<?= SITE_URL ?>/pages/explorar.php" class="btn btn-sm btn-primary">
        <i class="fas fa-list"></i> Ver em Lista
      </a>
    </div>

    <!-- Linha 2: Filtros -->
    <form id="form-filtro-mapa" onsubmit="return aplicarFiltrosMapa(event)">

      <div>
        <label class="mapa-filtro-label">Região</label>
        <select name="regiao" class="mapa-select">
          <option value="">Todas</option>
          <?php foreach ($regioes as $r): ?>
            <option value="<?= $r['id'] ?>" <?= $filtro_regiao == $r['id'] ? 'selected' : '' ?>>
              <?= h($r['nome']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div>
        <label class="mapa-filtro-label">Categoria</label>
        <select name="categoria" class="mapa-select">
          <option value="">Todas</option>
          <?php foreach ($categorias as $c): ?>
            <option value="<?= $c['id'] ?>" <?= $filtro_categoria == $c['id'] ? 'selected' : '' ?>>
              <?= h($c['nome']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div>
        <label class="mapa-filtro-label">Dificuldade</label>
        <select name="dificuldade" class="mapa-select">
          <option value="">Todas</option>
          <option value="facil"   <?= $filtro_dificuldade==='facil'   ? 'selected':'' ?>>Fácil</option>
          <option value="medio"   <?= $filtro_dificuldade==='medio'   ? 'selected':'' ?>>Médio</option>
          <option value="dificil" <?= $filtro_dificuldade==='dificil' ? 'selected':'' ?>>Difícil</option>
        </select>
      </div>

      <button type="submit" style="background:var(--dourado);color:var(--verde-escuro);border:none;border-radius:0;padding:.2rem .55rem;font-size:.75rem;font-weight:700;cursor:pointer;display:flex;align-items:center;gap:.3rem;white-space:nowrap;align-self:flex-end;">
        <i class="fas fa-search"></i> Filtrar
      </button>
      <a id="btn-limpar-filtro" href="#" onclick="limparFiltrosMapa(); return false;"
         style="display:<?= $tem_filtros ? 'flex' : 'none' ?>;color:rgba(245,239,230,.5);font-size:.75rem;text-decoration:none;padding:.2rem .4rem;border:1px solid rgba(245,239,230,.15);border-radius:0;align-items:center;align-self:flex-end;" title="Limpar filtros">
        <i class="fas fa-times"></i>
      </a>
    </form>

  </div>

  <!-- MAPA -->
  <div id="map" style="flex:1;"></div>
</div>

<!-- Scripts sem footer visual -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="<?= SITE_URL ?>/assets/js/main.js"></script>
<script>
initMainMap(<?= $locais_json ?>);

function aplicarFiltrosMapa(e) {
  e.preventDefault();
  const form = document.getElementById('form-filtro-mapa');
  const filtros = {
    categoria:   form.categoria.value,
    regiao:      form.regiao.value,
    dificuldade: form.dificuldade.value,
  };
  const params = new URLSearchParams();
  if (filtros.categoria)   params.set('categoria',   filtros.categoria);
  if (filtros.regiao)      params.set('regiao',      filtros.regiao);
  if (filtros.dificuldade) params.set('dificuldade', filtros.dificuldade);
  history.pushState({}, '', window.location.pathname + (params.toString() ? '?' + params.toString() : ''));
  const temFiltros = !!(filtros.categoria || filtros.regiao || filtros.dificuldade);
  document.getElementById('btn-limpar-filtro').style.display = temFiltros ? 'flex' : 'none';
  window._mapFilterLocais(filtros);
  return false;
}

function limparFiltrosMapa() {
  const form = document.getElementById('form-filtro-mapa');
  form.regiao.value = '';
  form.categoria.value = '';
  form.dificuldade.value = '';
  history.pushState({}, '', window.location.pathname);
  document.getElementById('btn-limpar-filtro').style.display = 'none';
  window._mapFilterLocais({ categoria: '', regiao: '', dificuldade: '' });
}
</script>
</body>
</html>
