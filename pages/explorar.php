<?php
// ============================================================
// SEGREDO LUSITANO — Explorar Locais
// ============================================================
require_once dirname(__DIR__) . '/includes/functions.php';

$page_title = 'Explorar';
$por_pagina = 12;
$pagina     = max(1, (int)($_GET['pagina'] ?? 1));
$offset     = ($pagina - 1) * $por_pagina;

$filtros = [
    'regiao'      => $_GET['regiao']      ?? '',
    'categoria'   => $_GET['categoria']   ?? '',
    'dificuldade' => $_GET['dificuldade'] ?? '',
    'pesquisa'    => $_GET['pesquisa']    ?? '',
    'ordem'       => $_GET['ordem']       ?? 'recente',
];

$locais     = get_locais($filtros, $por_pagina, $offset);
$total      = count_locais($filtros);
$total_pag  = (int)ceil($total / $por_pagina);
$categorias = get_categorias();
$regioes    = get_regioes();

// Query string sem pagina para links de paginação
$qs = http_build_query(array_filter(array_diff_key($_GET, ['pagina' => ''])));

include dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-content">
<section class="section">
  <div class="container-lg">
    <div class="section-header">
      <span class="label">Explorar</span>
      <h2>Segredos de Portugal</h2>
      <p><?= $total ?> locais secretos descobertos pela nossa comunidade</p>
    </div>

    <!-- FILTROS -->
    <div class="filtros-bar">
      <form class="filtros-form" method="GET">
        <div class="filtro-group" style="flex:2; min-width:200px;">
          <label for="pesquisa">Pesquisa</label>
          <input type="search" id="pesquisa" name="pesquisa" placeholder="Nome do local..."
                 value="<?= h($filtros['pesquisa']) ?>">
        </div>
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
            <option value="likes"   <?= $filtros['ordem']==='likes'   ? 'selected':'' ?>>Mais Curtidos</option>
            <option value="vistas"  <?= $filtros['ordem']==='vistas'  ? 'selected':'' ?>>Mais Vistos</option>
          </select>
        </div>
        <button type="submit" class="btn btn-verde"><i class="fas fa-search"></i> Filtrar</button>
        <a href="<?= SITE_URL ?>/pages/explorar.php" class="btn btn-outline" style="color:var(--texto-muted);border-color:var(--creme-escuro);">
          <i class="fas fa-times"></i>
        </a>
      </form>
    </div>

    <!-- RESULTADOS -->
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

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
