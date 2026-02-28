<?php
// ============================================================
// SEGREDO LUSITANO — Mapa Interativo
// ============================================================
require_once dirname(__DIR__) . '/includes/functions.php';

$page_title = 'Mapa';
$locais = get_locais([], 500); // todos os aprovados para o mapa

// Codificar para JSON de forma segura
$locais_json = json_encode(array_map(fn($l) => [
    'id'             => $l['id'],
    'nome'           => $l['nome'],
    'latitude'       => $l['latitude'],
    'longitude'      => $l['longitude'],
    'categoria_nome' => $l['categoria_nome'],
    'icone'          => $l['categoria_icone'],
    'regiao_nome'    => $l['regiao_nome'],
    'foto_capa'      => $l['foto_capa'],
    'total_likes'    => $l['total_likes'],
], $locais), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

$extra_head = '<style>body { overflow: hidden; } .page-content { height: calc(100vh - var(--nav-h)); display:flex; flex-direction:column; }</style>';
$extra_scripts = '<script>
const SITE_URL = "' . SITE_URL . '";
initMainMap(' . $locais_json . ');
</script>';

include dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-content">
  <!-- Barra de filtro rápido para mapa -->
  <div style="background:var(--verde-escuro); padding:.75rem 1.5rem; display:flex; align-items:center; gap:1rem; flex-wrap:wrap; border-bottom:2px solid var(--dourado);">
    <span style="color:var(--dourado); font-family:'Playfair Display',serif; font-weight:700; flex-shrink:0;">
      <i class="fas fa-map"></i> Mapa Interativo
    </span>
    <span style="color:rgba(245,239,230,.6); font-size:.85rem;">
      <?= count($locais) ?> locais secretos · Clica num marcador para saber mais
    </span>
    <a href="<?= SITE_URL ?>/pages/explorar.php" class="btn btn-sm btn-primary" style="margin-left:auto;">
      <i class="fas fa-list"></i> Ver em Lista
    </a>
  </div>

  <!-- MAPA -->
  <div id="map" style="flex:1; border-radius:0;"></div>
</div>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
