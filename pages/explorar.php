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
} elseif ($tipo === 'locais') {
    $filtros = [
        'regiao'      => $_GET['regiao']      ?? '',
        'categoria'   => $_GET['categoria']   ?? '',
        'dificuldade' => $_GET['dificuldade'] ?? '',
        'pesquisa'    => $_GET['pesquisa']    ?? '',
        'ordem'       => $_GET['ordem']       ?? 'recente',
    ];

    $filtro_lat = (float)($_GET['lat'] ?? 0);
    $filtro_lng = (float)($_GET['lng'] ?? 0);

    if ($filtro_lat && $filtro_lng) {
        $raio_prox = 50;
        $st_prox = db()->prepare(
            'SELECT l.*, c.nome AS categoria_nome, c.icone AS categoria_icone, r.nome AS regiao_nome,
                    u.username, u.nome AS autor_nome, u.avatar,
                    (SELECT COUNT(*) FROM likes WHERE local_id = l.id) AS total_likes,
                    (SELECT COUNT(*) FROM comentarios WHERE local_id = l.id) AS total_comentarios,
                    (6371 * acos(cos(radians(?)) * cos(radians(l.latitude)) * cos(radians(l.longitude) - radians(?)) + sin(radians(?)) * sin(radians(l.latitude)))) AS distancia
             FROM locais l
             JOIN categorias c ON c.id = l.categoria_id
             JOIN regioes r ON r.id = l.regiao_id
             JOIN utilizadores u ON u.id = l.utilizador_id
             WHERE l.estado = "aprovado" AND l.bloqueado = 0 AND l.apagado_em IS NULL
               AND l.latitude IS NOT NULL AND l.longitude IS NOT NULL
             HAVING distancia < ?
             ORDER BY distancia ASC
             LIMIT ? OFFSET ?'
        );
        $st_prox->execute([$filtro_lat, $filtro_lng, $filtro_lat, $raio_prox, $por_pagina, $offset]);
        $locais = $st_prox->fetchAll();

        $st_cnt = db()->prepare(
            'SELECT COUNT(*) FROM (
                SELECT l.id,
                       (6371 * acos(cos(radians(?)) * cos(radians(l.latitude)) * cos(radians(l.longitude) - radians(?)) + sin(radians(?)) * sin(radians(l.latitude)))) AS distancia
                FROM locais l
                WHERE l.estado = "aprovado" AND l.bloqueado = 0 AND l.apagado_em IS NULL
                  AND l.latitude IS NOT NULL AND l.longitude IS NOT NULL
                HAVING distancia < ?
            ) AS sub'
        );
        $st_cnt->execute([$filtro_lat, $filtro_lng, $filtro_lat, $raio_prox]);
        $total = (int)$st_cnt->fetchColumn();
        $total_pag = (int)ceil($total / $por_pagina);
    } else {
        $locais    = get_locais($filtros, $por_pagina, $offset);
        $total     = count_locais($filtros);
        $total_pag = (int)ceil($total / $por_pagina);
    }

    $categorias = get_categorias();
    $regioes    = get_regioes();
    $utilizadores = [];
} elseif ($tipo === 'stories') {
    if (!$auth_user) { header('Location: ' . SITE_URL . '/pages/login.php'); exit; }
    _migrar_stories();
    $stories    = get_stories($por_pagina, $offset);
    $total      = count_stories();
    $total_pag  = (int)ceil($total / $por_pagina);
    $locais     = [];
    $utilizadores = [];
    $filtros    = [];
    $categorias = [];
    $regioes    = [];
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
  /* Stories */
  .story-card {
    background:#fff;border:1.5px solid var(--creme-escuro);border-radius:var(--radius);
    padding:1rem 1.25rem;margin-bottom:1rem;
  }
  .story-card-header { display:flex;align-items:center;gap:.65rem;margin-bottom:.65rem; }
  .story-avatar {
    width:38px;height:38px;border-radius:50%;background:var(--verde-escuro);
    color:var(--dourado);font-weight:700;font-size:1rem;flex-shrink:0;overflow:hidden;
    display:flex;align-items:center;justify-content:center;
  }
  .story-avatar img { width:100%;height:100%;object-fit:cover; }
  .story-card img.story-foto { width:100%;max-height:400px;object-fit:cover;border-radius:var(--radius);margin:.65rem 0; }
  .story-form { background:var(--creme);border:1.5px solid var(--creme-escuro);border-radius:var(--radius);padding:1.25rem;margin-bottom:1.5rem; }
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
         class="explorar-tab <?= $tipo === 'utilizadores' ? 'active' : '' ?>"
         <?= !$auth_user ? 'onclick="mostrarAvisoLogin(\'Inicia sessão para explorares a comunidade.\', \'' . SITE_URL . '/pages/login.php\'); return false;"' : '' ?>>
        <i class="fas fa-users"></i> Utilizadores
      </a>
      <a href="<?= SITE_URL ?>/pages/explorar.php?tipo=stories"
         class="explorar-tab <?= $tipo === 'stories' ? 'active' : '' ?>"
         <?= !$auth_user ? 'onclick="mostrarAvisoLogin(\'Inicia sessão para veres os stories da comunidade.\', \'' . SITE_URL . '/pages/login.php\'); return false;"' : '' ?>>
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
        <div class="filtro-group" style="flex:0 0 180px;">
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

    <?php elseif ($tipo === 'stories'): ?>

      <!-- FORMULÁRIO PUBLICAR STORY -->
      <?php if ($auth_user): ?>
      <form method="POST" enctype="multipart/form-data" class="story-form">
        <input type="hidden" name="add_story" value="1">
        <h3 style="font-size:.9rem;margin:0 0 .75rem;color:var(--verde-escuro);font-weight:600;">
          <i class="fas fa-pen"></i> Publicar Story
          <span style="font-weight:400;color:var(--texto-muted);font-size:.78rem;">&nbsp;&middot; visivel 7 dias</span>
        </h3>
        <textarea name="story_texto" id="story-texto" maxlength="500" data-maxlength="500" rows="3"
                  placeholder="Partilha um momento, uma descoberta, uma dica..."
                  style="width:100%;border:1.5px solid var(--creme-escuro);border-radius:var(--radius);padding:.6rem .85rem;font-size:.9rem;resize:vertical;box-sizing:border-box;background:#fff;"></textarea>
        <div style="display:flex;align-items:center;justify-content:space-between;margin-top:.5rem;gap:.75rem;flex-wrap:wrap;">
          <span style="font-size:.75rem;color:var(--texto-muted);" data-counter-for="story-texto">0/500</span>
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
          <button type="button" onclick="removerStoryFoto()" style="position:absolute;top:4px;left:4px;background:rgba(0,0,0,.55);color:#fff;border:none;border-radius:50%;width:22px;height:22px;font-size:.75rem;cursor:pointer;display:flex;align-items:center;justify-content:center;">
            <i class="fas fa-times"></i>
          </button>
        </div>
      </form>
      <?php else: ?>
      <div style="background:var(--creme);border:1.5px solid var(--creme-escuro);border-radius:var(--radius);padding:1.25rem;margin-bottom:1.5rem;text-align:center;">
        <p style="margin:0;font-size:.9rem;color:var(--texto-muted);">
          <a href="<?= SITE_URL ?>/pages/login.php" style="color:var(--verde);font-weight:600;">Inicia sessao</a> para publicar um story.
        </p>
      </div>
      <?php endif; ?>

      <!-- FEED DE STORIES -->
      <?php if (!empty($stories)): ?>
      <div style="max-width:680px;">
        <?php foreach ($stories as $s): ?>
        <div class="story-card">
          <div class="story-card-header">
            <a href="<?= SITE_URL ?>/pages/perfil.php?id=<?= $s['utilizador_id'] ?>" style="text-decoration:none;display:contents;">
              <div class="story-avatar">
                <?php if ($s['autor_avatar']): ?>
                  <img src="<?= SITE_URL ?>/uploads/locais/<?= h($s['autor_avatar']) ?>" alt="">
                <?php else: ?>
                  <?= mb_strtoupper(mb_substr($s['username'], 0, 1)) ?>
                <?php endif; ?>
              </div>
            </a>
            <div style="flex:1;min-width:0;">
              <a href="<?= SITE_URL ?>/pages/perfil.php?id=<?= $s['utilizador_id'] ?>" style="font-weight:700;font-size:.9rem;color:var(--texto);text-decoration:none;"><?= h($s['autor_nome']) ?></a>
              <div style="font-size:.75rem;color:var(--texto-muted);">@<?= h($s['username']) ?> &middot; <?= tempo_atras($s['criado_em']) ?></div>
            </div>
            <?php if ($auth_user && ((int)$auth_user['id'] === (int)$s['utilizador_id'] || is_admin())): ?>
            <a href="?tipo=stories&apagar_story=<?= $s['id'] ?>"
               onclick="return confirm('Remover este story?')"
               style="color:var(--texto-muted);font-size:.8rem;padding:.2rem .4rem;text-decoration:none;" title="Remover">
              <i class="fas fa-trash"></i>
            </a>
            <?php endif; ?>
          </div>
          <?php if ($s['foto']): ?>
            <img src="<?= SITE_URL ?>/uploads/stories/<?= h($s['foto']) ?>" alt="" class="story-foto">
          <?php endif; ?>
          <?php if (trim($s['texto'])): ?>
            <p style="margin:0;font-size:.92rem;line-height:1.7;color:var(--texto);word-break:break-word;"><?= nl2br(h($s['texto'])) ?></p>
          <?php endif; ?>
          <?php if ($s['local_nome']): ?>
            <div style="margin-top:.5rem;font-size:.78rem;color:var(--verde);"><i class="fas fa-map-marker-alt"></i> <?= h($s['local_nome']) ?></div>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
      <?php else: ?>
      <div class="empty-state">
        <i class="fas fa-camera" style="font-size:2.5rem;opacity:.3;"></i>
        <h3 style="margin-top:.75rem;">Ainda sem stories</h3>
        <p style="font-size:.9rem;">Sê o primeiro a partilhar um momento.</p>
      </div>
      <?php endif; ?>

    <?php endif; /* fim tabs */ ?>

  </div>
</section>
</div>

<!-- Script perto de mim + pesquisa dinâmica (só para locais) -->
<?php if ($tipo === 'locais'): ?>
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
<?php endif; ?>

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
