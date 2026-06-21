<?php
// ============================================================
// SEGREDO LUSITANO — Explorar Locais / Utilizadores
// ============================================================
require_once dirname(__DIR__) . '/includes/functions.php';

$auth_user = auth_user();

// --- POST: Publicar Story ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_story'])) {
    if (!$auth_user) { header('Location: ' . SITE_URL . '/pages/login.php'); exit; }
    $texto    = trim($_POST['story_texto'] ?? '');
    $local_id = ((int)($_POST['story_local_id'] ?? 0)) ?: null;
    $foto     = null;
    if (isset($_FILES['story_foto']) && $_FILES['story_foto']['error'] === 0) {
        $f    = $_FILES['story_foto'];
        $info = @getimagesize($f['tmp_name']);
        $mime = $info ? ($info['mime'] ?? '') : '';
        $tipos = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if (isset($tipos[$mime]) && $f['size'] <= 10 * 1024 * 1024) {
            _migrar_stories();
            $dir = dirname(UPLOAD_DIR) . '/stories/';
            if (!is_dir($dir)) @mkdir($dir, 0777, true);
            $nome = uniqid('story_') . '.' . $tipos[$mime];
            if (move_uploaded_file($f['tmp_name'], $dir . $nome)) $foto = $nome;
        }
    }
    if (strlen($texto) < 3 && !$foto) {
        flash('error', 'Escreve algo ou adiciona uma foto para publicar.');
    } elseif (strlen($texto) > 500) {
        flash('error', 'Máximo 500 caracteres.');
    } else {
        add_story($auth_user['id'], $texto, $local_id, $foto);
        flash('success', 'Story publicado! Fica visível durante 7 dias.');
    }
    header('Location: ' . SITE_URL . '/pages/explorar.php?tipo=stories'); exit;
}

// --- GET: Apagar Story ---
if (isset($_GET['apagar_story']) && $auth_user) {
    $sid = (int)$_GET['apagar_story'];
    _migrar_stories();
    $stS = db()->prepare('SELECT utilizador_id, foto FROM stories WHERE id = ?');
    $stS->execute([$sid]);
    $rowS = $stS->fetch();
    if ($rowS && (is_admin() || (int)$rowS['utilizador_id'] === (int)$auth_user['id'])) {
        if ($rowS['foto']) {
            $fp = dirname(UPLOAD_DIR) . '/stories/' . $rowS['foto'];
            if (file_exists($fp)) unlink($fp);
        }
        db()->prepare('DELETE FROM stories WHERE id = ?')->execute([$sid]);
        flash('success', 'Story removido.');
    }
    header('Location: ' . SITE_URL . '/pages/explorar.php?tipo=stories'); exit;
}

$tipo = $_GET['tipo'] ?? 'locais';
if (!in_array($tipo, ['locais', 'utilizadores', 'stories'])) $tipo = 'locais';

$page_title = 'Explorar';
$por_pagina = 12;
$pagina     = max(1, (int)($_GET['pagina'] ?? 1));
$offset     = ($pagina - 1) * $por_pagina;
$stories    = [];

$filtro_lat  = (float)($_GET['lat']  ?? 0);
$filtro_lng  = (float)($_GET['lng']  ?? 0);
$filtro_raio = (int)($_GET['raio'] ?? 50);
if (!in_array($filtro_raio, [10, 25, 50, 100, 200], true)) $filtro_raio = 50;

if ($tipo === 'utilizadores') {
    if (!$auth_user) { header('Location: ' . SITE_URL . '/pages/login.php'); exit; }
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
} elseif ($tipo === 'locais') {
    $filtros = [
        'regiao'      => $_GET['regiao']      ?? '',
        'categoria'   => $_GET['categoria']   ?? '',
        'dificuldade' => $_GET['dificuldade'] ?? '',
        'pesquisa'    => $_GET['pesquisa']    ?? '',
        'ordem'       => $_GET['ordem']       ?? 'recente',
        'lat'         => $filtro_lat,
        'lng'         => $filtro_lng,
        'raio'        => $filtro_raio,
    ];

    $locais     = get_locais($filtros, $por_pagina, $offset);
    $total      = count_locais($filtros);
    $total_pag  = (int)ceil($total / $por_pagina);
    $categorias = get_categorias();
    $regioes    = get_regioes();
    $utilizadores = [];
} elseif ($tipo === 'stories') {
    if (!$auth_user) { header('Location: ' . SITE_URL . '/pages/login.php'); exit; }
    _migrar_stories();
    _migrar_story_interacoes();
    $stories       = get_stories(10, 0);
    $total         = count_stories();
    $total_pag     = (int)ceil($total / 10);
    $story_bubbles = get_story_bubbles();
    $locais        = [];
    $utilizadores  = [];
    $filtros       = [];
    $categorias    = [];
    $regioes       = [];
    // Pre-load reactions for initial stories
    foreach ($stories as &$s) {
        $s['reacoes']      = get_story_reacoes((int)$s['id']);
        $s['minha_reacao'] = get_minha_reacao_story((int)$s['id'], $auth_user['id']);
    }
    unset($s);
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
    cursor:pointer; display:inline-flex; align-items:center; gap:.3rem; transition:all .15s;
  }

  /* ── Stories ── */
  .stories-bubbles-wrap {
    display:flex; gap:.75rem; overflow-x:auto; padding:.25rem .1rem 1rem;
    scrollbar-width:none; margin-bottom:1.25rem;
  }
  .stories-bubbles-wrap::-webkit-scrollbar { display:none; }
  .story-bubble {
    display:flex; flex-direction:column; align-items:center; gap:.3rem;
    background:none; border:none; cursor:pointer; flex-shrink:0; padding:0;
  }
  .story-bubble-avatar {
    width:58px; height:58px; border-radius:50%; overflow:hidden;
    display:flex; align-items:center; justify-content:center;
    font-size:1.4rem; font-weight:700; color:#fff; background:var(--verde-escuro);
  }
  .story-bubble-avatar img { width:100%; height:100%; object-fit:cover; }
  .story-bubble > span { font-size:.7rem; color:var(--texto-muted); max-width:62px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
  .story-bubble-novo { box-shadow:0 0 0 3px var(--dourado); }
  .story-bubble-visto { box-shadow:0 0 0 3px var(--creme-escuro); }

  .story-card {
    background:#fff; border:1.5px solid var(--creme-escuro); border-radius:var(--radius);
    padding:1rem 1.25rem; margin-bottom:1rem;
  }
  .story-card-header { display:flex; align-items:center; gap:.65rem; margin-bottom:.65rem; }
  .story-avatar {
    width:38px; height:38px; border-radius:50%; background:var(--verde-escuro);
    color:var(--dourado); font-weight:700; font-size:1rem; flex-shrink:0; overflow:hidden;
    display:flex; align-items:center; justify-content:center;
  }
  .story-avatar img { width:100%; height:100%; object-fit:cover; }
  .story-card img.story-foto { width:100%; max-height:420px; object-fit:cover; border-radius:var(--radius); margin:.65rem 0; }

  .story-reacao-btn {
    background:var(--creme); border:1.5px solid var(--creme-escuro); border-radius:50px;
    padding:.2rem .55rem; font-size:.88rem; cursor:pointer; display:inline-flex;
    align-items:center; gap:.25rem; transition:all .15s; color:var(--texto-muted);
  }
  .story-reacao-btn .reacao-count { font-size:.75rem; font-weight:600; }
  .story-reacao-btn.ativo { border-color:var(--dourado); background:rgba(212,175,55,.12); color:var(--texto); }
  .story-reacao-btn:hover { border-color:var(--verde); }

  .emoji-picker-popup {
    position:fixed;background:#1e1e1e;border:1px solid #383838;border-radius:14px;
    padding:.5rem;display:grid;grid-template-columns:repeat(8,1fr);gap:.2rem;
    z-index:10010;box-shadow:0 8px 28px rgba(0,0,0,.6);
  }
  .emoji-picker-popup button {
    background:none;border:none;cursor:pointer;font-size:1.3rem;
    padding:.25rem;border-radius:8px;line-height:1;transition:background .1s;
  }
  .emoji-picker-popup button:hover { background:#333; }
  .story-picker-btn {
    background:var(--creme);border:1.5px solid var(--creme-escuro);border-radius:50px;
    padding:.2rem .55rem;font-size:.85rem;cursor:pointer;color:var(--texto-muted);
    line-height:1;display:inline-flex;align-items:center;transition:all .15s;
  }
  .story-picker-btn:hover { border-color:var(--verde); }
  .story-picker-btn.modal-dark { background:#222;border-color:#444;color:#aaa; }

  .story-comentario { display:flex; gap:.5rem; margin-bottom:.5rem; font-size:.82rem; }
  .story-comentario .sc-avatar {
    width:26px; height:26px; border-radius:50%; background:var(--verde-escuro);
    color:var(--dourado); font-weight:700; font-size:.7rem; flex-shrink:0; overflow:hidden;
    display:flex; align-items:center; justify-content:center;
  }
  .story-comentario .sc-avatar img { width:100%; height:100%; object-fit:cover; }

  /* Modal */
  #story-modal { display:none; }
  #story-modal.aberto { display:flex !important; }
  #story-modal-inner { scrollbar-width:thin; overflow:hidden; }
  .modal-story-content { padding:1.25rem; }
  .modal-story-foto { width:100%; max-height:55vh; object-fit:cover; display:block; }
  .modal-progress { display:flex; gap:3px; padding:.65rem .75rem .4rem; position:absolute; top:0; left:0; right:0; z-index:3; }
  .modal-progress-bar { height:3px; flex:1; background:rgba(255,255,255,.25); border-radius:2px; overflow:hidden; position:relative; }
  .modal-progress-bar.done { background:rgba(255,255,255,.85); }
  .modal-progress-fill {
    position:absolute; left:0; top:0; bottom:0; width:0; background:#fff;
    animation: bar-fill var(--dur,5s) linear forwards;
  }
  @keyframes bar-fill { to { width:100%; } }
  /* Slide vertical */
  @keyframes slideFromBottom { from{transform:translateY(60px);opacity:0;} to{transform:translateY(0);opacity:1;} }
  @keyframes slideFromTop    { from{transform:translateY(-60px);opacity:0;} to{transform:translateY(0);opacity:1;} }
  .slide-from-bottom { animation:slideFromBottom .3s cubic-bezier(.22,1,.36,1) forwards; }
  .slide-from-top    { animation:slideFromTop .3s cubic-bezier(.22,1,.36,1) forwards; }
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
      <?php elseif ($tipo === 'stories'): ?>
        <p><?= $total ?> stories partilhados</p>
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
      <a href="<?= SITE_URL ?>/pages/explorar.php?tipo=stories"
         class="explorar-tab <?= $tipo === 'stories' ? 'active' : '' ?>">
        <i class="fas fa-camera"></i> Stories
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

    <?php elseif ($tipo === 'locais'): /* ── LOCAIS ── */ ?>

    <div class="filtros-bar">
      <form class="filtros-form" method="GET">
        <input type="hidden" name="tipo" value="locais">
        <input type="hidden" name="lat"  id="hidden-lat"  value="<?= $filtro_lat  ?: '' ?>">
        <input type="hidden" name="lng"  id="hidden-lng"  value="<?= $filtro_lng  ?: '' ?>">
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
          <div id="raio-wrap" class="filtro-group" style="min-width:90px;<?= $filtro_lat ? '' : 'display:none;' ?>">
            <label for="raio" style="font-size:.75rem;">Raio</label>
            <select id="raio" name="raio">
              <option value="10"  <?= $filtro_raio===10  ? 'selected':'' ?>>10 km</option>
              <option value="25"  <?= $filtro_raio===25  ? 'selected':'' ?>>25 km</option>
              <option value="50"  <?= $filtro_raio===50  ? 'selected':'' ?>>50 km</option>
              <option value="100" <?= $filtro_raio===100 ? 'selected':'' ?>>100 km</option>
              <option value="200" <?= $filtro_raio===200 ? 'selected':'' ?>>200 km</option>
            </select>
          </div>
          <button type="submit" class="btn btn-verde" style="border:1.5px solid transparent;justify-content:center;"><i class="fas fa-search"></i> Filtrar</button>
          <a href="<?= SITE_URL ?>/pages/explorar.php" class="btn" style="border:1.5px solid var(--creme-escuro);color:var(--texto-muted);background:transparent;justify-content:center;padding:.6rem 1.75rem;font-size:.9rem;">Limpar</a>
        </div>
      </form>
    </div>

    <?php if ($filtro_lat && $filtro_lng): ?>
    <div style="display:flex;align-items:center;gap:.6rem;padding:.65rem 1rem;background:rgba(45,106,79,.08);border:1.5px solid var(--verde-claro);border-radius:var(--radius);margin-bottom:1rem;font-size:.88rem;color:var(--verde-escuro);">
      <i class="fas fa-location-crosshairs"></i>
      <span>A mostrar <strong><?= $total ?></strong> locais num raio de <strong><?= $filtro_raio ?> km</strong> à tua volta, ordenados por distância.</span>
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

    <?php elseif ($tipo === 'stories'): ?>

    <!-- ── BUBBLES ROW ─────────────────────────────────────── -->
    <div class="stories-bubbles-wrap">
      <!-- Bubble: publicar novo story -->
      <button class="story-bubble story-bubble-add" onclick="abrirFormStory()" title="Publicar story">
        <div class="story-bubble-avatar" style="background:var(--verde);border:2.5px dashed var(--dourado);">
          <i class="fas fa-plus" style="color:var(--dourado);font-size:1.1rem;"></i>
        </div>
        <span>Novo</span>
      </button>
      <?php foreach ($story_bubbles as $b):
        $e_novo = strtotime($b['ultimo_story']) > (time() - 86400);
        $inicial = mb_strtoupper(mb_substr($b['username'], 0, 1));
      ?>
      <button class="story-bubble" onclick="abrirModalStories(<?= $b['id'] ?>)" title="<?= h($b['nome']) ?>">
        <div class="story-bubble-avatar <?= $e_novo ? 'story-bubble-novo' : 'story-bubble-visto' ?>">
          <?php if ($b['avatar']): ?>
            <img src="<?= SITE_URL ?>/uploads/locais/<?= h($b['avatar']) ?>" alt="">
          <?php else: ?>
            <span><?= $inicial ?></span>
          <?php endif; ?>
        </div>
        <span><?= h(mb_substr($b['username'], 0, 10)) ?></span>
      </button>
      <?php endforeach; ?>
    </div>

    <!-- ── MODAL VIEWER ────────────────────────────────────── -->
    <div id="story-modal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.92);align-items:center;justify-content:center;">
      <button onclick="fecharModal()" style="position:absolute;top:1rem;right:1rem;background:rgba(255,255,255,.15);border:none;color:#fff;width:40px;height:40px;border-radius:50%;font-size:1.2rem;cursor:pointer;z-index:10;"><i class="fas fa-times"></i></button>
      <div style="position:absolute;bottom:1rem;left:50%;transform:translateX(-50%);color:rgba(255,255,255,.45);font-size:.72rem;pointer-events:none;white-space:nowrap;">
        <i class="fas fa-arrows-up-down" style="margin-right:.3rem;"></i>scroll para navegar
      </div>
      <div id="story-modal-inner" style="max-width:520px;width:100%;max-height:90vh;border-radius:var(--radius);background:#1a1a1a;position:relative;overflow:hidden;">
        <!-- preenchido via JS -->
      </div>
    </div>

    <!-- ── FORM PUBLICAR (painel deslizante) ───────────────── -->
    <div id="story-form-panel" style="display:none;background:var(--creme);border:1.5px solid var(--creme-escuro);border-radius:var(--radius);padding:1.25rem;margin-bottom:1.5rem;">
      <form method="POST" enctype="multipart/form-data" id="story-form-el">
        <input type="hidden" name="add_story" value="1">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.75rem;">
          <h3 style="font-size:.9rem;margin:0;color:var(--verde-escuro);font-weight:600;">
            <i class="fas fa-pen"></i> Publicar Story
            <span style="font-weight:400;color:var(--texto-muted);font-size:.78rem;">&nbsp;&middot; visível 7 dias</span>
          </h3>
          <button type="button" onclick="fecharFormStory()" style="background:none;border:none;color:var(--texto-muted);font-size:1rem;cursor:pointer;"><i class="fas fa-times"></i></button>
        </div>
        <textarea name="story_texto" id="story-texto" maxlength="500" rows="3"
                  placeholder="Partilha um momento, uma descoberta, uma dica..."
                  style="width:100%;border:1.5px solid var(--creme-escuro);border-radius:var(--radius);padding:.6rem .85rem;font-size:.9rem;resize:vertical;box-sizing:border-box;background:#fff;"></textarea>

        <!-- Autocomplete de local -->
        <div style="position:relative;margin-top:.5rem;">
          <input type="text" id="story-local-search" placeholder="Associar local (opcional)..."
                 autocomplete="off"
                 style="width:100%;border:1.5px solid var(--creme-escuro);border-radius:var(--radius);padding:.5rem .85rem;font-size:.85rem;box-sizing:border-box;background:#fff;">
          <input type="hidden" name="story_local_id" id="story-local-id">
          <div id="story-local-dropdown" style="display:none;position:absolute;left:0;right:0;background:#fff;border:1.5px solid var(--creme-escuro);border-top:none;border-radius:0 0 var(--radius) var(--radius);z-index:100;max-height:180px;overflow-y:auto;box-shadow:0 4px 12px rgba(0,0,0,.1);"></div>
        </div>

        <div style="display:flex;align-items:center;justify-content:space-between;margin-top:.65rem;gap:.75rem;flex-wrap:wrap;">
          <span style="font-size:.75rem;color:var(--texto-muted);" id="story-counter">0/500</span>
          <div style="display:flex;align-items:center;gap:.5rem;">
            <label style="cursor:pointer;display:inline-flex;align-items:center;gap:.35rem;font-size:.82rem;color:var(--verde);font-weight:600;padding:.3rem .65rem;border:1.5px solid var(--verde);border-radius:var(--radius);">
              <i class="fas fa-image"></i> Foto
              <input type="file" name="story_foto" accept="image/jpeg,image/png,image/webp" style="display:none;" onchange="previewStoryFoto(this)">
            </label>
            <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-paper-plane"></i> Publicar</button>
          </div>
        </div>
        <div id="story-foto-preview" style="display:none;margin-top:.6rem;position:relative;">
          <img id="story-foto-img" src="" alt="" style="max-height:200px;border-radius:var(--radius);border:1.5px solid var(--creme-escuro);">
          <button type="button" onclick="removerStoryFoto()" style="position:absolute;top:4px;left:4px;background:rgba(0,0,0,.55);color:#fff;border:none;border-radius:50%;width:22px;height:22px;font-size:.75rem;cursor:pointer;display:flex;align-items:center;justify-content:center;"><i class="fas fa-times"></i></button>
        </div>
      </form>
    </div>

    <!-- ── FEED DE STORIES ─────────────────────────────────── -->
    <div id="stories-feed" style="max-width:680px;">
      <?php if (!empty($stories)): ?>
        <?php foreach ($stories as $s): ?>
        <div class="story-card" id="story-<?= $s['id'] ?>" data-id="<?= $s['id'] ?>">
          <div class="story-card-header">
            <a href="<?= SITE_URL ?>/pages/perfil.php?id=<?= $s['utilizador_id'] ?>" style="text-decoration:none;">
              <div class="story-avatar">
                <?php if ($s['autor_avatar']): ?>
                  <img src="<?= SITE_URL ?>/uploads/locais/<?= h($s['autor_avatar']) ?>" alt="" loading="lazy">
                <?php else: ?>
                  <?= mb_strtoupper(mb_substr($s['username'], 0, 1)) ?>
                <?php endif; ?>
              </div>
            </a>
            <div style="flex:1;min-width:0;">
              <a href="<?= SITE_URL ?>/pages/perfil.php?id=<?= $s['utilizador_id'] ?>" style="font-weight:700;font-size:.9rem;color:var(--texto);text-decoration:none;"><?= h($s['autor_nome']) ?></a>
              <div style="font-size:.75rem;color:var(--texto-muted);">@<?= h($s['username']) ?> &middot; <?= tempo_atras($s['criado_em']) ?></div>
            </div>
            <div style="display:flex;align-items:center;gap:.5rem;">
              <!-- Partilhar -->
              <button onclick="copiarLinkStory(<?= $s['id'] ?>)"
                      style="background:none;border:none;color:var(--texto-muted);font-size:.8rem;padding:.2rem .4rem;cursor:pointer;" title="Copiar link">
                <i class="fas fa-share-nodes"></i>
              </button>
              <!-- Apagar (próprio ou admin) -->
              <?php if ($auth_user && ((int)$auth_user['id'] === (int)$s['utilizador_id'] || is_admin())): ?>
              <a href="?tipo=stories&apagar_story=<?= $s['id'] ?>"
                 onclick="return confirm('Remover este story?')"
                 style="color:var(--texto-muted);font-size:.8rem;padding:.2rem .4rem;text-decoration:none;" title="Remover">
                <i class="fas fa-trash"></i>
              </a>
              <?php endif; ?>
            </div>
          </div>

          <?php if ($s['foto']): ?>
            <img src="<?= SITE_URL ?>/uploads/stories/<?= h($s['foto']) ?>" alt="" class="story-foto" loading="lazy"
                 onclick="abrirModalStoriesPorId(<?= $s['id'] ?>, <?= $s['utilizador_id'] ?>)" style="cursor:pointer;">
          <?php endif; ?>

          <?php if (trim($s['texto'])): ?>
            <p style="margin:0;font-size:.92rem;line-height:1.7;color:var(--texto);word-break:break-word;"><?= nl2br(h($s['texto'])) ?></p>
          <?php endif; ?>

          <?php if ($s['local_nome']): ?>
            <div style="margin-top:.4rem;font-size:.78rem;color:var(--verde);"><i class="fas fa-map-marker-alt"></i> <?= h($s['local_nome']) ?></div>
          <?php endif; ?>

          <!-- Barra de reações -->
          <div class="story-reacoes-bar" style="display:flex;align-items:center;gap:.35rem;margin-top:.65rem;flex-wrap:wrap;">
            <?php foreach (['❤️','🔥','😂','👍'] as $emoji): ?>
            <button class="story-reacao-btn <?= $s['minha_reacao'] === $emoji ? 'ativo' : '' ?>"
                    data-story="<?= $s['id'] ?>" data-emoji="<?= h($emoji) ?>"
                    onclick="reagir(this)">
              <?= $emoji ?>
              <span class="reacao-count">
                <?= array_sum(array_column(array_filter($s['reacoes'], fn($r) => $r['emoji'] === $emoji), 'total')) ?: '' ?>
              </span>
            </button>
            <?php endforeach; ?>
            <button class="story-picker-btn" onclick="_abrirEmojiPicker(this,<?= $s['id'] ?>)" title="Mais emojis">+</button>
            <!-- Comentários toggle -->
            <button class="story-comentarios-toggle" data-story="<?= $s['id'] ?>"
                    onclick="toggleComentarios(this)"
                    style="margin-left:auto;background:none;border:none;color:var(--texto-muted);font-size:.8rem;cursor:pointer;display:flex;align-items:center;gap:.3rem;">
              <i class="fas fa-comment"></i>
              <span class="coment-count"><?= (int)$s['total_comentarios_count'] ?></span>
            </button>
          </div>

          <!-- Secção de comentários (colapsável) -->
          <div class="story-comentarios-section" data-story="<?= $s['id'] ?>" style="display:none;margin-top:.65rem;border-top:1px solid var(--creme-escuro);padding-top:.65rem;">
            <div class="story-comentarios-lista"></div>
            <form class="story-comment-form" data-story="<?= $s['id'] ?>" onsubmit="enviarComentario(event,this)" style="display:flex;gap:.4rem;margin-top:.5rem;">
              <input type="text" name="texto" placeholder="Adiciona um comentário..."
                     style="flex:1;border:1.5px solid var(--creme-escuro);border-radius:50px;padding:.35rem .75rem;font-size:.82rem;background:#fff;" maxlength="500">
              <button type="submit" style="background:var(--verde);color:#fff;border:none;border-radius:50px;padding:.35rem .85rem;font-size:.82rem;cursor:pointer;"><i class="fas fa-paper-plane"></i></button>
            </form>
          </div>
        </div>
        <?php endforeach; ?>
      <?php else: ?>
        <div class="empty-state" id="stories-empty">
          <i class="fas fa-camera" style="font-size:2.5rem;opacity:.3;"></i>
          <h3 style="margin-top:.75rem;">Ainda sem stories</h3>
          <p style="font-size:.9rem;">Sê o primeiro a partilhar um momento.</p>
        </div>
      <?php endif; ?>
    </div>

    <!-- Sentinel para infinite scroll -->
    <div id="stories-sentinel" style="height:1px;"></div>
    <?php if ($total > 10): ?>
    <div id="stories-loading" style="text-align:center;padding:1rem;color:var(--texto-muted);font-size:.85rem;display:none;">
      <i class="fas fa-spinner fa-spin"></i> A carregar mais...
    </div>
    <?php endif; ?>

    <?php endif; /* fim tabs */ ?>

  </div>
</section>
</div>

<!-- Script pesquisa dinâmica (só para locais) -->
<?php if ($tipo === 'locais'): ?>
<script>
function filtrarPertoDeMim() {
  const btn = document.getElementById('btn-perto-mim');
  if (!navigator.geolocation) { alert('O teu browser não suporta geolocalização.'); return; }
  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> A obter localização...';
  navigator.geolocation.getCurrentPosition(
    pos => {
      const { latitude, longitude, accuracy } = pos.coords;
      if (accuracy > 5000) {
        alert('A precisão do GPS é insuficiente (' + Math.round(accuracy / 1000) + ' km). Ativa o GPS e tenta num local com melhor sinal.');
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-location-crosshairs"></i> Perto de mim';
        return;
      }
      document.getElementById('hidden-lat').value = latitude;
      document.getElementById('hidden-lng').value = longitude;
      document.getElementById('raio-wrap').style.display = '';
      btn.closest('form').submit();
    },
    () => {
      alert('Ativa o GPS e tenta novamente.');
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-location-crosshairs"></i> Perto de mim';
    },
    { enableHighAccuracy: true, timeout: 10000 }
  );
}

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

<?php if ($tipo === 'stories'): ?>
<script>
const STORIES_TOTAL = <?= $total ?>;
const USER_LOGADO   = <?= $auth_user ? 'true' : 'false' ?>;
let storiesOffset   = <?= count($stories) ?>;
let storiesLoading  = false;
let modalStories    = [];
let modalIdx        = 0;

// ── Formulário ──────────────────────────────────────────────
function abrirFormStory() {
  const p = document.getElementById('story-form-panel');
  p.style.display = p.style.display === 'none' ? 'block' : 'none';
  if (p.style.display === 'block') document.getElementById('story-texto').focus();
}
function fecharFormStory() { document.getElementById('story-form-panel').style.display = 'none'; }

// Preview foto
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

// Contador de caracteres
document.getElementById('story-texto')?.addEventListener('input', function() {
  document.getElementById('story-counter').textContent = this.value.length + '/500';
});

// ── Autocomplete de local ────────────────────────────────────
(function() {
  const input    = document.getElementById('story-local-search');
  const hiddenId = document.getElementById('story-local-id');
  const dropdown = document.getElementById('story-local-dropdown');
  if (!input) return;
  let timer;
  input.addEventListener('input', function() {
    clearTimeout(timer);
    hiddenId.value = '';
    const q = this.value.trim();
    if (q.length < 2) { dropdown.style.display = 'none'; return; }
    timer = setTimeout(async () => {
      const r = await fetch(`${SITE_URL}/pages/stories_api.php?acao=pesquisa_locais&q=` + encodeURIComponent(q));
      const d = await r.json();
      dropdown.innerHTML = '';
      if (!d.locais.length) { dropdown.style.display = 'none'; return; }
      d.locais.forEach(l => {
        const div = document.createElement('div');
        div.textContent = l.nome;
        div.style.cssText = 'padding:.5rem .85rem;cursor:pointer;font-size:.85rem;border-bottom:1px solid var(--creme-escuro);';
        div.onmouseenter = () => div.style.background = 'var(--creme)';
        div.onmouseleave = () => div.style.background = '';
        div.onclick = () => {
          input.value = l.nome;
          hiddenId.value = l.id;
          dropdown.style.display = 'none';
        };
        dropdown.appendChild(div);
      });
      dropdown.style.display = 'block';
    }, 300);
  });
  document.addEventListener('click', e => { if (!input.contains(e.target)) dropdown.style.display = 'none'; });
})();

// ── Modal viewer ─────────────────────────────────────────────
const STORY_DUR = 5000;
let _modalTimer = null;


function _avançar() {
  if (!document.getElementById('story-modal').classList.contains('aberto')) return;
  if (modalIdx < modalStories.length - 1) { modalIdx++; renderModalStory('next'); }
  else fecharModal();
}

function _startTimer() {
  _stopTimer();
  _modalTimer = setTimeout(_avançar, STORY_DUR);
}

function _stopTimer() {
  if (_modalTimer) { clearTimeout(_modalTimer); _modalTimer = null; }
}

async function abrirModalStories(userId) {
  const modal = document.getElementById('story-modal');
  modal.classList.add('aberto');
  document.getElementById('story-modal-inner').innerHTML =
    '<div style="padding:3rem;text-align:center;color:#fff;"><i class="fas fa-spinner fa-spin fa-lg"></i></div>';
  document.body.style.overflow = 'hidden';
  const r = await fetch(`${SITE_URL}/pages/stories_api.php?acao=stories_user&user_id=${userId}`);
  const d = await r.json();
  if (!d.ok || !d.stories.length) { fecharModal(); return; }
  modalStories = d.stories;
  modalIdx = 0;
  renderModalStory('next');
}

async function abrirModalStoriesPorId(storyId, userId) {
  await abrirModalStories(userId);
  const idx = modalStories.findIndex(s => s.id == storyId);
  if (idx >= 0 && idx !== 0) { modalIdx = idx; renderModalStory('next'); }
}

function renderModalStory(dir) {
  _stopTimer();
  const s = modalStories[modalIdx];
  const inner = document.getElementById('story-modal-inner');

  // Barra de progresso (animated fill na barra activa)
  const bars = modalStories.map((_, i) => {
    if (i < modalIdx) return `<div class="modal-progress-bar done"></div>`;
    if (i === modalIdx) return `<div class="modal-progress-bar"><div class="modal-progress-fill" style="--dur:${STORY_DUR}ms;"></div></div>`;
    return `<div class="modal-progress-bar"></div>`;
  }).join('');

  const foto = s.foto
    ? `<img src="${SITE_URL}/uploads/stories/${escHtml(s.foto)}" alt="" class="modal-story-foto" loading="lazy">`
    : '';

  const reacoesBtns = ['❤️','🔥','😂','👍'].map(e => {
    const cnt = (s.reacoes || []).find(r => r.emoji === e);
    const ativo = s.minha_reacao === e ? 'ativo' : '';
    return `<button class="story-reacao-btn ${ativo}" data-story="${s.id}" data-emoji="${e}" onclick="reagir(this)" style="border-color:#444;background:#222;color:#ddd;">${e}<span class="reacao-count">${cnt ? cnt.total : ''}</span></button>`;
  }).join('') + `<button class="story-picker-btn modal-dark" onclick="_abrirEmojiPicker(this,${s.id})" title="Mais emojis">+</button>`;

  const slideClass = dir === 'next' ? 'slide-from-bottom' : 'slide-from-top';

  inner.innerHTML = `
    <div class="modal-progress">${bars}</div>
    <div class="${slideClass}">
      ${foto}
      <div class="modal-story-content">
        <div style="display:flex;align-items:center;gap:.6rem;margin-bottom:.75rem;margin-top:${foto ? '0' : '2rem'};">
          <a href="${SITE_URL}/pages/perfil.php?id=${s.utilizador_id}" style="text-decoration:none;display:flex;align-items:center;gap:.6rem;flex:1;">
            <div class="story-avatar">
              ${s.autor_avatar ? `<img src="${SITE_URL}/uploads/locais/${escHtml(s.autor_avatar)}" alt="">` : escHtml(s.username.charAt(0).toUpperCase())}
            </div>
            <div>
              <div style="font-weight:700;font-size:.9rem;color:#fff;">${escHtml(s.autor_nome)}</div>
              <div style="font-size:.72rem;color:#aaa;">@${escHtml(s.username)}</div>
            </div>
          </a>
          <button onclick="copiarLinkStory(${s.id})" style="background:none;border:none;color:#aaa;font-size:.85rem;cursor:pointer;" title="Copiar link"><i class="fas fa-share-nodes"></i></button>
        </div>
        ${s.texto ? `<p style="margin:0 0 .75rem;font-size:.92rem;line-height:1.7;color:#e0e0e0;word-break:break-word;">${escHtml(s.texto).replace(/\n/g,'<br>')}</p>` : ''}
        ${s.local_nome ? `<div style="font-size:.78rem;color:var(--dourado);margin-bottom:.75rem;"><i class="fas fa-map-marker-alt"></i> ${escHtml(s.local_nome)}</div>` : ''}
        <div class="story-reacoes-bar" style="display:flex;gap:.35rem;flex-wrap:wrap;">${reacoesBtns}</div>
      </div>
    </div>`;

  _startTimer();
}

function modalNav(dir) {
  const next = modalIdx + dir;
  if (next < 0 || next >= modalStories.length) return;
  modalIdx = next;
  renderModalStory(dir > 0 ? 'next' : 'prev');
}

function fecharModal() {
  _stopTimer();
  document.getElementById('story-modal').classList.remove('aberto');
  document.body.style.overflow = '';
  modalStories = [];
}

// Fechar ao clicar fora do card (backdrop)
document.getElementById('story-modal').addEventListener('click', function(e) {
  const inner = document.getElementById('story-modal-inner');
  if (!inner.contains(e.target)) fecharModal();
});

// Toque no card: metade esquerda = anterior, metade direita = próximo
document.getElementById('story-modal-inner').addEventListener('click', e => {
  if (e.target.closest('button, a')) return;
  const r = document.getElementById('story-modal-inner').getBoundingClientRect();
  modalNav(e.clientX - r.left < r.width / 2 ? -1 : 1);
});

// Scroll do rato → navegar (no document, só quando modal aberto)
let _wheelCooldown = false;
document.addEventListener('wheel', e => {
  if (!document.getElementById('story-modal').classList.contains('aberto')) return;
  e.preventDefault();
  if (_wheelCooldown) return;
  _wheelCooldown = true;
  setTimeout(() => { _wheelCooldown = false; }, 500);
  modalNav(e.deltaY > 0 ? 1 : -1);
}, { passive: false });

// Teclado: ↑↓ Esc
document.addEventListener('keydown', e => {
  if (!document.getElementById('story-modal').classList.contains('aberto')) return;
  if (e.key === 'ArrowDown' || e.key === 'ArrowRight') modalNav(1);
  if (e.key === 'ArrowUp'   || e.key === 'ArrowLeft')  modalNav(-1);
  if (e.key === 'Escape') fecharModal();
});

// Touch swipe vertical no modal (no document para não ser bloqueado pelo conteúdo)
(function() {
  let startY = 0, startT = 0;
  document.addEventListener('touchstart', e => {
    if (!document.getElementById('story-modal').classList.contains('aberto')) return;
    startY = e.touches[0].clientY;
    startT = Date.now();
  }, { passive: true });
  document.addEventListener('touchend', e => {
    if (!document.getElementById('story-modal').classList.contains('aberto')) return;
    const diff = startY - e.changedTouches[0].clientY;
    const dt   = Date.now() - startT;
    if (Math.abs(diff) > 40 && dt < 600) modalNav(diff > 0 ? 1 : -1);
  }, { passive: true });
})();

// ── Reações ──────────────────────────────────────────────────
async function reagir(btn) {
  if (!USER_LOGADO) { mostrarAvisoLogin('Inicia sessão para reagir.', `${SITE_URL}/pages/login.php`); return; }
  const storyId = btn.dataset.story;
  const emoji   = btn.dataset.emoji;
  const form    = new FormData();
  form.append('story_id', storyId);
  form.append('emoji', emoji);
  const r = await fetch(`${SITE_URL}/pages/stories_api.php?acao=reagir`, {
    method: 'POST',
    headers: { 'X-CSRF-Token': CSRF_TOKEN },
    body: form
  });
  const d = await r.json();
  if (!d.ok) return;

  // Atualizar todos os botões deste story (feed + modal)
  document.querySelectorAll(`.story-reacao-btn[data-story="${storyId}"]`).forEach(b => {
    b.classList.remove('ativo');
    const cnt = (d.reacoes || []).find(re => re.emoji === b.dataset.emoji);
    b.querySelector('.reacao-count').textContent = cnt ? cnt.total : '';
    if (d.reagiu && d.emoji === b.dataset.emoji) b.classList.add('ativo');
  });
  // Remover botões dinâmicos do picker que ficaram com 0 reações
  document.querySelectorAll(`.story-reacao-btn[data-story="${storyId}"][data-dynamic]`).forEach(b => {
    const cnt = (d.reacoes || []).find(re => re.emoji === b.dataset.emoji);
    if (!cnt || parseInt(cnt.total) === 0) b.remove();
  });
  // Actualizar no array do modal
  const ms = modalStories.find(s => s.id == storyId);
  if (ms) { ms.reacoes = d.reacoes; ms.minha_reacao = d.emoji; }
}

// ── Emoji Picker ──────────────────────────────────────────────
const _EMOJIS_PICKER = [
  '❤️','🧡','💛','💚','💙','💜','🖤','🤍',
  '🔥','⭐','✨','💯','🎉','🥳','🎊','💥',
  '😂','😍','😮','😱','🥹','🤩','😎','😜',
  '👍','👏','🙌','💪','🫶','🤝','😭','🥰',
];
let _emojiPickerEl = null;

function _fecharPicker() {
  if (!_emojiPickerEl) return;
  _emojiPickerEl.popup.remove();
  _emojiPickerEl.overlay.remove();
  _emojiPickerEl = null;
  if (document.getElementById('story-modal').classList.contains('aberto')) _startTimer();
}

function _abrirEmojiPicker(btn, storyId) {
  if (_emojiPickerEl) { _fecharPicker(); return; }
  _stopTimer();

  // Overlay transparente que fecha o picker ao clicar fora
  const overlay = document.createElement('div');
  overlay.style.cssText = 'position:fixed;inset:0;z-index:10009;';
  overlay.onclick = _fecharPicker;

  const popup = document.createElement('div');
  popup.className = 'emoji-picker-popup';
  _EMOJIS_PICKER.forEach(e => {
    const b = document.createElement('button');
    b.textContent = e;
    b.onclick = () => { _fecharPicker(); _reagirEmoji(storyId, e, btn); };
    popup.appendChild(b);
  });

  document.body.appendChild(overlay);
  document.body.appendChild(popup);
  _emojiPickerEl = { popup, overlay };

  const rect = btn.getBoundingClientRect();
  const popH = 210;
  let top = rect.top - popH - 8;
  if (top < 8) top = rect.bottom + 8;
  popup.style.top  = top + 'px';
  popup.style.left = Math.max(8, Math.min(rect.left, window.innerWidth - 276)) + 'px';
}

async function _reagirEmoji(storyId, emoji, pickerBtn) {
  if (!USER_LOGADO) { mostrarAvisoLogin('Inicia sessão para reagir.', `${SITE_URL}/pages/login.php`); return; }
  const form = new FormData();
  form.append('story_id', storyId);
  form.append('emoji', emoji);
  const r = await fetch(`${SITE_URL}/pages/stories_api.php?acao=reagir`, {
    method: 'POST',
    headers: { 'X-CSRF-Token': CSRF_TOKEN },
    body: form
  });
  const d = await r.json();
  if (!d.ok) return;

  // Atualizar quick buttons existentes
  document.querySelectorAll(`.story-reacao-btn[data-story="${storyId}"]`).forEach(b => {
    b.classList.remove('ativo');
    const cnt = (d.reacoes || []).find(re => re.emoji === b.dataset.emoji);
    if (b.querySelector('.reacao-count')) b.querySelector('.reacao-count').textContent = cnt ? cnt.total : '';
    if (d.reagiu && d.emoji === b.dataset.emoji) b.classList.add('ativo');
  });
  // Remover botões dinâmicos do picker que ficaram com 0 reações
  document.querySelectorAll(`.story-reacao-btn[data-story="${storyId}"][data-dynamic]`).forEach(b => {
    const cnt = (d.reacoes || []).find(re => re.emoji === b.dataset.emoji);
    if (!cnt || parseInt(cnt.total) === 0) b.remove();
  });

  // Se o emoji escolhido não está na barra, inserir um botão
  if (d.reagiu) {
    const bar = pickerBtn ? pickerBtn.closest('.story-reacoes-bar') : null;
    if (bar && !bar.querySelector(`.story-reacao-btn[data-emoji="${emoji}"]`)) {
      const nb = document.createElement('button');
      nb.className = 'story-reacao-btn ativo';
      nb.dataset.story = storyId;
      nb.dataset.emoji = emoji;
      nb.dataset.dynamic = '1';
      nb.onclick = function() { reagir(this); };
      const cnt = (d.reacoes || []).find(re => re.emoji === emoji);
      const isDark = pickerBtn.classList.contains('modal-dark');
      if (isDark) nb.style.cssText = 'border-color:#444;background:#222;color:#ddd;border:1.5px solid var(--dourado);background:rgba(212,175,55,.12);';
      nb.innerHTML = `${emoji}<span class="reacao-count">${cnt ? cnt.total : ''}</span>`;
      pickerBtn.before(nb);
    }
  }

  const ms = modalStories.find(s => s.id == storyId);
  if (ms) { ms.reacoes = d.reacoes; ms.minha_reacao = d.reagiu ? d.emoji : null; }
}

// ── Comentários ──────────────────────────────────────────────
async function toggleComentarios(btn) {
  const storyId = btn.dataset.story;
  const section = document.querySelector(`.story-comentarios-section[data-story="${storyId}"]`);
  if (section.style.display === 'none') {
    section.style.display = 'block';
    if (!section.dataset.loaded) {
      section.dataset.loaded = '1';
      await carregarComentarios(storyId, section.querySelector('.story-comentarios-lista'));
    }
  } else {
    section.style.display = 'none';
  }
}

async function carregarComentarios(storyId, lista) {
  lista.innerHTML = '<div style="color:var(--texto-muted);font-size:.8rem;"><i class="fas fa-spinner fa-spin"></i></div>';
  const r = await fetch(`${SITE_URL}/pages/stories_api.php?acao=comentarios&story_id=${storyId}`);
  const d = await r.json();
  lista.innerHTML = '';
  if (!d.comentarios.length) {
    lista.innerHTML = '<div style="font-size:.8rem;color:var(--texto-muted);margin-bottom:.5rem;">Sem comentários ainda.</div>';
    return;
  }
  d.comentarios.forEach(c => lista.appendChild(renderComentario(c)));
}

function renderComentario(c) {
  const div = document.createElement('div');
  div.className = 'story-comentario';
  const inicial = c.username ? c.username.charAt(0).toUpperCase() : '?';
  div.innerHTML = `
    <div class="sc-avatar">${c.avatar ? `<img src="${SITE_URL}/uploads/locais/${escHtml(c.avatar)}" alt="">` : inicial}</div>
    <div style="flex:1;min-width:0;">
      <a href="${SITE_URL}/pages/perfil.php?id=${c.utilizador_id}" style="font-weight:600;color:var(--verde);text-decoration:none;">${escHtml(c.username)}</a>
      <span style="color:var(--texto);word-break:break-word;"> ${escHtml(c.texto)}</span>
      <div style="font-size:.7rem;color:var(--texto-muted);margin-top:.1rem;">${escHtml(c.criado_em?.slice(0,16).replace('T',' ') || '')}</div>
    </div>`;
  return div;
}

async function enviarComentario(e, form) {
  e.preventDefault();
  if (!USER_LOGADO) { mostrarAvisoLogin('Inicia sessão para comentar.', `${SITE_URL}/pages/login.php`); return; }
  const storyId = form.dataset.story;
  const input   = form.querySelector('input[name="texto"]');
  const texto   = input.value.trim();
  if (!texto) return;
  const fd = new FormData(form);
  fd.append('story_id', storyId);
  const r = await fetch(`${SITE_URL}/pages/stories_api.php?acao=comentar`, {
    method: 'POST',
    headers: { 'X-CSRF-Token': CSRF_TOKEN },
    body: fd
  });
  const d = await r.json();
  if (!d.ok) return;
  input.value = '';
  const lista = form.previousElementSibling;
  const placeholder = lista.querySelector('div');
  if (placeholder && placeholder.textContent.includes('Sem comentários')) placeholder.remove();
  lista.appendChild(renderComentario(d.comentario));
  // Atualizar contagem
  const badge = document.querySelector(`.story-comentarios-toggle[data-story="${storyId}"] .coment-count`);
  if (badge) badge.textContent = parseInt(badge.textContent || 0) + 1;
}

// ── Partilhar link ───────────────────────────────────────────
function copiarLinkStory(storyId) {
  const url = `${SITE_URL}/pages/explorar.php?tipo=stories#story-${storyId}`;
  navigator.clipboard.writeText(url).then(() => {
    // Mini toast
    const t = document.createElement('div');
    t.textContent = 'Link copiado!';
    t.style.cssText = 'position:fixed;bottom:1.5rem;left:50%;transform:translateX(-50%);background:var(--verde);color:#fff;padding:.5rem 1.25rem;border-radius:50px;font-size:.85rem;z-index:99999;pointer-events:none;';
    document.body.appendChild(t);
    setTimeout(() => t.remove(), 2000);
  });
}

// ── Infinite scroll ──────────────────────────────────────────
if (STORIES_TOTAL > storiesOffset) {
  const sentinel = document.getElementById('stories-sentinel');
  const loading  = document.getElementById('stories-loading');
  const feed     = document.getElementById('stories-feed');

  const obs = new IntersectionObserver(async entries => {
    if (!entries[0].isIntersecting || storiesLoading) return;
    if (storiesOffset >= STORIES_TOTAL) { obs.disconnect(); return; }
    storiesLoading = true;
    if (loading) loading.style.display = 'block';

    const r = await fetch(`${SITE_URL}/pages/stories_api.php?acao=mais&offset=${storiesOffset}`);
    const d = await r.json();
    if (loading) loading.style.display = 'none';
    storiesLoading = false;
    if (!d.ok) return;

    d.stories.forEach(s => feed.appendChild(buildStoryCard(s)));
    storiesOffset += d.stories.length;
    if (storiesOffset >= d.total) obs.disconnect();
  }, { rootMargin: '300px' });

  obs.observe(sentinel);
}

function buildStoryCard(s) {
  const wrap = document.createElement('div');
  wrap.className = 'story-card';
  wrap.dataset.id = s.id;

  const isOwner = USER_LOGADO && <?= $auth_user ? 'window.__MY_ID === s.utilizador_id' : 'false' ?>;

  const foto = s.foto
    ? `<img src="${SITE_URL}/uploads/stories/${escHtml(s.foto)}" alt="" class="story-foto" loading="lazy" onclick="abrirModalStoriesPorId(${s.id},${s.utilizador_id})" style="cursor:pointer;">`
    : '';

  const reacoesBtns = ['❤️','🔥','😂','👍'].map(e => {
    const cnt = (s.reacoes||[]).find(r=>r.emoji===e);
    const ativo = s.minha_reacao === e ? 'ativo' : '';
    return `<button class="story-reacao-btn ${ativo}" data-story="${s.id}" data-emoji="${e}" onclick="reagir(this)">${e}<span class="reacao-count">${cnt?cnt.total:''}</span></button>`;
  }).join('') + `<button class="story-picker-btn" onclick="_abrirEmojiPicker(this,${s.id})" title="Mais emojis">+</button>`;

  wrap.innerHTML = `
    <div class="story-card-header">
      <a href="${SITE_URL}/pages/perfil.php?id=${s.utilizador_id}" style="text-decoration:none;">
        <div class="story-avatar">
          ${s.autor_avatar ? `<img src="${SITE_URL}/uploads/locais/${escHtml(s.autor_avatar)}" alt="" loading="lazy">` : escHtml(s.username.charAt(0).toUpperCase())}
        </div>
      </a>
      <div style="flex:1;min-width:0;">
        <a href="${SITE_URL}/pages/perfil.php?id=${s.utilizador_id}" style="font-weight:700;font-size:.9rem;color:var(--texto);text-decoration:none;">${escHtml(s.autor_nome)}</a>
        <div style="font-size:.75rem;color:var(--texto-muted);">@${escHtml(s.username)}</div>
      </div>
      <button onclick="copiarLinkStory(${s.id})" style="background:none;border:none;color:var(--texto-muted);font-size:.8rem;padding:.2rem .4rem;cursor:pointer;" title="Copiar link"><i class="fas fa-share-nodes"></i></button>
    </div>
    ${foto}
    ${s.texto ? `<p style="margin:0;font-size:.92rem;line-height:1.7;color:var(--texto);word-break:break-word;">${escHtml(s.texto).replace(/\n/g,'<br>')}</p>` : ''}
    ${s.local_nome ? `<div style="margin-top:.4rem;font-size:.78rem;color:var(--verde);"><i class="fas fa-map-marker-alt"></i> ${escHtml(s.local_nome)}</div>` : ''}
    <div class="story-reacoes-bar" style="display:flex;align-items:center;gap:.35rem;margin-top:.65rem;flex-wrap:wrap;">
      ${reacoesBtns}
      <button class="story-comentarios-toggle" data-story="${s.id}" onclick="toggleComentarios(this)"
              style="margin-left:auto;background:none;border:none;color:var(--texto-muted);font-size:.8rem;cursor:pointer;display:flex;align-items:center;gap:.3rem;">
        <i class="fas fa-comment"></i><span class="coment-count">${s.total_comentarios_count||0}</span>
      </button>
    </div>
    <div class="story-comentarios-section" data-story="${s.id}" style="display:none;margin-top:.65rem;border-top:1px solid var(--creme-escuro);padding-top:.65rem;">
      <div class="story-comentarios-lista"></div>
      <form class="story-comment-form" data-story="${s.id}" onsubmit="enviarComentario(event,this)" style="display:flex;gap:.4rem;margin-top:.5rem;">
        <input type="text" name="texto" placeholder="Adiciona um comentário..." maxlength="500"
               style="flex:1;border:1.5px solid var(--creme-escuro);border-radius:50px;padding:.35rem .75rem;font-size:.82rem;background:#fff;">
        <button type="submit" style="background:var(--verde);color:#fff;border:none;border-radius:50px;padding:.35rem .85rem;font-size:.82rem;cursor:pointer;"><i class="fas fa-paper-plane"></i></button>
      </form>
    </div>`;
  return wrap;
}

// Util
function escHtml(s) {
  return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

<?php if ($auth_user): ?>
window.__MY_ID = <?= (int)$auth_user['id'] ?>;
<?php endif; ?>
</script>
<?php endif; ?>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
