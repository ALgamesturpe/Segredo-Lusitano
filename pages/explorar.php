<?php
// ============================================================
// SEGREDO LUSITANO — Explorar Locais / Utilizadores
// ============================================================
require_once dirname(__DIR__) . '/includes/functions.php';

$tipo = $_GET['tipo'] ?? 'locais';
if (!in_array($tipo, ['locais', 'utilizadores'])) $tipo = 'locais';

$page_title = 'Explorar';
$por_pagina = 12;
$pagina     = max(1, (int)($_GET['pagina'] ?? 1));
$offset     = ($pagina - 1) * $por_pagina;

if ($tipo === 'utilizadores') {
    $pesquisa = trim($_GET['pesquisa'] ?? '');
    $ordem    = $_GET['ordem'] ?? 'pontos';
    if (!in_array($ordem, ['pontos', 'locais', 'recente'])) $ordem = 'pontos';

    $order_sql = match($ordem) {
        'locais'  => 'total_locais DESC',
        'recente' => 'u.criado_em DESC',
        default   => 'u.pontos DESC',
    };

    $where  = 'WHERE u.role = "user" AND u.ativo = 1';
    $params = [];
    if ($pesquisa) {
        $where  .= ' AND (u.nome LIKE ? OR u.username LIKE ?)';
        $params  = ["%$pesquisa%", "%$pesquisa%"];
    }

    $count_st = db()->prepare("SELECT COUNT(*) FROM utilizadores u $where");
    $count_st->execute($params);
    $total     = (int)$count_st->fetchColumn();
    $total_pag = (int)ceil($total / $por_pagina);

    $st = db()->prepare(
        "SELECT u.id, u.nome, u.username, u.avatar, u.pontos, u.bio,
                (SELECT COUNT(*) FROM locais WHERE utilizador_id = u.id AND estado = 'aprovado') AS total_locais
         FROM utilizadores u $where
         ORDER BY $order_sql LIMIT ? OFFSET ?"
    );
    $st->execute([...$params, $por_pagina, $offset]);
    $utilizadores = $st->fetchAll();

    $auth_user = auth_user();
    $seguindo_ids = [];
    if ($auth_user) {
        $st2 = db()->prepare('SELECT seguido_id FROM seguidores WHERE seguidor_id = ?');
        $st2->execute([$auth_user['id']]);
        $seguindo_ids = array_column($st2->fetchAll(), 'seguido_id');
    }

    $locais = [];
    $filtros = [];
    $categorias = [];
    $regioes    = [];
} else {
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
    $utilizadores = [];
}

$qs = http_build_query(array_filter(array_diff_key($_GET, ['pagina' => ''])));

$extra_head = '<style>
  .explorar-tabs { display:flex; border-bottom:2px solid var(--creme-escuro); margin-bottom:1.5rem; }
  .explorar-tab {
    padding:.65rem 1.5rem; font-size:.9rem; font-weight:600; color:var(--texto-muted);
    text-decoration:none; border-bottom:2px solid transparent; margin-bottom:-2px;
    display:inline-flex; align-items:center; gap:.4rem; transition:color .15s;
  }
  .explorar-tab.active { color:var(--verde); border-bottom-color:var(--verde); }
  .explorar-tab:hover:not(.active) { color:var(--texto); }
  .card-user .card-avatar {
    height:110px; background:linear-gradient(135deg,var(--verde-escuro),var(--verde));
    display:flex; align-items:center; justify-content:center;
  }
  .card-user .avatar-circle {
    width:72px; height:72px; border-radius:50%; border:3px solid #fff;
    overflow:hidden; background:var(--verde-claro);
    display:flex; align-items:center; justify-content:center; flex-shrink:0;
  }
  .card-user .avatar-circle img { width:100%; height:100%; object-fit:cover; }
  .card-user .avatar-circle span { font-size:1.6rem; font-weight:700; color:#fff; }
  .card-user .card-body { text-align:center; }
  .card-user .user-bio {
    font-size:.82rem; color:var(--texto-muted); margin-bottom:.75rem;
    overflow:hidden; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical;
  }
  .card-user .user-stats { display:flex; justify-content:center; gap:1.5rem; margin-bottom:.85rem; font-size:.82rem; }
  .btn-seguir-user {
    border-radius:50px; padding:.3rem 1rem; font-size:.8rem; font-weight:600;
    cursor:pointer; display:inline-flex; align-items:center; gap:.3rem;
    transition:all .15s;
  }
</style>';

include dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-content">
<section class="section">
  <div class="container-lg">
    <div class="section-header">
      <h2>Segredos de Portugal</h2>
      <?php if ($tipo === 'utilizadores'): ?>
        <p><?= $total ?> exploradores na comunidade</p>
      <?php else: ?>
        <p><?= $total ?> locais secretos descobertos pela nossa comunidade</p>
      <?php endif; ?>
    </div>

    <!-- TABS -->
    <div class="explorar-tabs">
      <a href="<?= SITE_URL ?>/pages/explorar.php?tipo=locais"
         class="explorar-tab <?= $tipo === 'locais' ? 'active' : '' ?>">
        <i class="fas fa-map-marker-alt"></i> Locais
      </a>
      <a href="<?= SITE_URL ?>/pages/explorar.php?tipo=utilizadores"
         class="explorar-tab <?= $tipo === 'utilizadores' ? 'active' : '' ?>">
        <i class="fas fa-users"></i> Utilizadores
      </a>
    </div>

    <!-- FILTROS -->
    <?php if ($tipo === 'utilizadores'): ?>
    <div class="filtros-bar">
      <form class="filtros-form" method="GET">
        <input type="hidden" name="tipo" value="utilizadores">
        <div class="filtro-group" style="flex:2; min-width:200px;">
          <label for="pesquisa">Pesquisa</label>
          <input type="search" id="pesquisa" name="pesquisa"
                 placeholder="Nome ou username..."
                 value="<?= h($pesquisa) ?>" autocomplete="off">
        </div>
        <div class="filtros-grid">
          <div class="filtro-group">
            <label for="ordem">Ordenar</label>
            <select id="ordem" name="ordem">
              <option value="pontos"  <?= $ordem === 'pontos'  ? 'selected' : '' ?>>Mais Pontos</option>
              <option value="locais"  <?= $ordem === 'locais'  ? 'selected' : '' ?>>Mais Locais</option>
              <option value="recente" <?= $ordem === 'recente' ? 'selected' : '' ?>>Mais Recentes</option>
            </select>
          </div>
        </div>
        <div class="filtros-actions">
          <button type="submit" class="btn btn-verde" style="border:1.5px solid transparent;justify-content:center;">
            <i class="fas fa-search"></i> Filtrar
          </button>
          <a href="<?= SITE_URL ?>/pages/explorar.php?tipo=utilizadores"
             class="btn" style="border:1.5px solid var(--creme-escuro);color:var(--texto-muted);background:transparent;justify-content:center;padding:.6rem 1.75rem;font-size:.9rem;">
            Limpar
          </a>
        </div>
      </form>
    </div>

    <!-- CARDS UTILIZADORES -->
    <?php if ($utilizadores): ?>
      <div class="cards-grid">
        <?php foreach ($utilizadores as $u):
          $e_proprio  = $auth_user && $auth_user['id'] == $u['id'];
          $ja_segue   = in_array($u['id'], $seguindo_ids);
          $inicial    = mb_strtoupper(mb_substr($u['username'], 0, 1));
        ?>
        <div class="card card-user">
          <a href="<?= SITE_URL ?>/pages/perfil.php?id=<?= $u['id'] ?>" style="display:block;text-decoration:none;">
            <div class="card-avatar">
              <div class="avatar-circle">
                <?php if (!empty($u['avatar'])): ?>
                  <img src="<?= SITE_URL ?>/uploads/locais/<?= h($u['avatar']) ?>"
                       alt="<?= h($u['nome']) ?>" loading="lazy">
                <?php else: ?>
                  <span><?= h($inicial) ?></span>
                <?php endif; ?>
              </div>
            </div>
          </a>
          <div class="card-body">
            <h3 class="card-title" style="font-size:1rem;margin-bottom:.15rem;">
              <a href="<?= SITE_URL ?>/pages/perfil.php?id=<?= $u['id'] ?>"><?= h($u['nome']) ?></a>
            </h3>
            <div style="font-size:.82rem;color:var(--verde);margin-bottom:.5rem;">@<?= h($u['username']) ?></div>
            <?php if (!empty($u['bio'])): ?>
              <p class="user-bio"><?= h($u['bio']) ?></p>
            <?php endif; ?>
            <div class="user-stats">
              <span><strong style="color:var(--dourado);"><?= number_format($u['pontos']) ?></strong> pontos</span>
              <span><strong style="color:var(--verde);"><?= (int)$u['total_locais'] ?></strong> locais</span>
            </div>
            <?php if ($auth_user && !$e_proprio): ?>
              <button class="btn-seguir-card btn-seguir-user"
                      data-id="<?= $u['id'] ?>"
                      data-seguindo="<?= $ja_segue ? '1' : '0' ?>"
                      style="background:none;border:1px solid <?= $ja_segue ? 'var(--creme-escuro)' : 'var(--verde)' ?>;
                             color:<?= $ja_segue ? 'var(--texto-muted)' : 'var(--verde)' ?>;">
                <i class="fas <?= $ja_segue ? 'fa-user-check' : 'fa-user-plus' ?>"></i>
                <?= $ja_segue ? 'A seguir' : 'Seguir' ?>
              </button>
            <?php endif; ?>
          </div>
        </div>
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
        <i class="fas fa-users"></i>
        <h3>Nenhum utilizador encontrado</h3>
        <p>Tenta outro nome ou username.</p>
      </div>
    <?php endif; ?>

    <?php else: /* ── LOCAIS ── */ ?>

    <div class="filtros-bar">
      <form class="filtros-form" method="GET">
        <input type="hidden" name="tipo" value="locais">
        <div class="filtro-group" style="flex:2; min-width:200px;">
          <label for="pesquisa">Pesquisa</label>
          <input type="search" id="pesquisa" name="pesquisa" placeholder="Nome do local..."
                value="<?= h($filtros['pesquisa']) ?>" autocomplete="off">
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
          <button type="submit" class="btn btn-verde" style="border:1.5px solid transparent;justify-content:center;"><i class="fas fa-search"></i> Filtrar</button>
          <a href="<?= SITE_URL ?>/pages/explorar.php" class="btn" style="border:1.5px solid var(--creme-escuro);color:var(--texto-muted);background:transparent;justify-content:center;padding:.6rem 1.75rem;font-size:.9rem;">Limpar</a>
        </div>
      </form>
    </div>

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

    <?php endif; /* fim tabs */ ?>

  </div>
</section>
</div>

<!-- Script pesquisa dinâmica (só para locais) -->
<?php if ($tipo === 'locais'): ?>
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
<?php endif; ?>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
