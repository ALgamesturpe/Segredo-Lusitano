<?php
// ============================================================
// SEGREDO LUSITANO — Admin: Gerir Locais
// ============================================================
require_once dirname(__DIR__) . '/includes/functions.php';
require_admin();

$page_title = 'Admin · Locais';

// ── Apagar local permanentemente ─────────────────────────
if (isset($_GET['apagar'])) {
    delete_local((int)$_GET['apagar']);
    flash('success', 'Local apagado.');
    header('Location: ' . SITE_URL . '/admin/locais.php');
    exit;
}

// ── Restaurar local bloqueado (desbloquear) ───────────────
if (isset($_GET['restaurar'])) {
    db()->prepare('UPDATE locais SET bloqueado = 0 WHERE id = ?')->execute([(int)$_GET['restaurar']]);
    flash('success', 'Local restaurado com sucesso.');
    header('Location: ' . SITE_URL . '/admin/locais.php?bloqueado=1');
    exit;
}

// ── Eliminar comentário permanentemente ───────────────────
if (isset($_GET['apagar_comentario'])) {
    $cid = (int)$_GET['apagar_comentario'];
    $lid = (int)($_GET['local_id'] ?? 0);
    db()->prepare('DELETE FROM comentarios WHERE id = ?')->execute([$cid]);
    flash('success', 'Comentário eliminado.');
    header('Location: ' . SITE_URL . '/admin/locais.php?gerir=' . $lid);
    exit;
}

// ── Eliminar foto ─────────────────────────────────────────
if (isset($_GET['apagar_foto'])) {
    $fid = (int)$_GET['apagar_foto'];
    $lid = (int)($_GET['local_id'] ?? 0);
    // Buscar ficheiro para apagar do disco
    $st = db()->prepare('SELECT ficheiro FROM fotos WHERE id = ?');
    $st->execute([$fid]);
    $foto = $st->fetch();
    if ($foto) {
        apagar_upload_local($foto['ficheiro']);
        db()->prepare('DELETE FROM fotos WHERE id = ?')->execute([$fid]);
    }
    flash('success', 'Foto eliminada.');
    header('Location: ' . SITE_URL . '/admin/locais.php?gerir=' . $lid);
    exit;
}

// ── Upload de foto pelo admin ─────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['foto_admin']) && isset($_POST['local_id_upload'])) {
    $lid  = (int)$_POST['local_id_upload'];
    $user = auth_user();
    $f    = $_FILES['foto_admin'];
    if ($f['error'] === 0) {
        upload_foto($f, $lid, $user['id']);
        flash('success', 'Foto adicionada.');
    }
    header('Location: ' . SITE_URL . '/admin/locais.php?gerir=' . $lid);
    exit;
}

// ── Filtros ───────────────────────────────────────────────
$filtro    = $_GET['filtro']    ?? '';
$bloqueado = isset($_GET['bloqueado']) && $_GET['bloqueado'] === '1';
$gerir_id  = isset($_GET['gerir']) ? (int)$_GET['gerir'] : 0;

// Pesquisa
$pesquisa = trim($_GET['q'] ?? '');

if ($bloqueado) {
    $where = 'WHERE l.bloqueado = 1';
} elseif ($filtro === 'aprovado') {
    $where = 'WHERE l.estado = "aprovado" AND l.bloqueado = 0';
} else {
    $where = 'WHERE l.bloqueado = 0';
}

$params = [];
if ($pesquisa) {
    $where .= ' AND (l.nome LIKE ? OR u.username LIKE ?)';
    $params = ["%$pesquisa%", "%$pesquisa%"];
}

$st = db()->prepare(
    "SELECT l.*, c.nome AS categoria_nome, r.nome AS regiao_nome, u.username
     FROM locais l
     JOIN categorias c ON c.id = l.categoria_id
     JOIN regioes r ON r.id = l.regiao_id
     JOIN utilizadores u ON u.id = l.utilizador_id
     $where ORDER BY l.criado_em DESC"
);
$st->execute($params);
$locais = $st->fetchAll();

// ── Carregar dados do local a gerir (comentários e fotos) ─
$local_gerir     = null;
$comentarios_gerir = [];
$fotos_gerir     = [];
if ($gerir_id) {
    $local_gerir = get_local($gerir_id);
    if ($local_gerir) {
        $comentarios_gerir = get_comentarios($gerir_id);
        $fotos_gerir       = get_fotos($gerir_id);
    }
}

include dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-content">
<div class="admin-wrapper">
  <aside class="admin-sidebar">
    <div style="color:var(--dourado); font-family:'Playfair Display',serif; font-size:1.1rem; font-weight:700; margin-bottom:1.5rem; padding:.5rem .85rem;">
      <i class="fas fa-shield-alt"></i> Administração
    </div>
    <nav class="admin-nav">
      <div class="nav-section">Geral</div>
      <a href="<?= SITE_URL ?>/admin/index.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
      <a href="<?= SITE_URL ?>/admin/locais.php" class="active"><i class="fa-solid fa-location-dot"></i> Locais</a>
      <a href="<?= SITE_URL ?>/admin/utilizadores.php"><i class="fas fa-users"></i> Utilizadores</a>
    </nav>
  </aside>

  <main class="admin-content">

    <?php if ($gerir_id && $local_gerir): ?>
    <!-- ── MODO GERIR LOCAL (comentários + fotos) ── -->
    <div style="display:flex;align-items:center;gap:1rem;margin-bottom:1.5rem;flex-wrap:wrap;">
      <a href="<?= SITE_URL ?>/admin/locais.php" class="btn btn-sm" style="border:1px solid var(--creme-escuro);color:var(--texto-muted);">
        <i class="fas fa-arrow-left"></i> Voltar
      </a>
      <h1 class="admin-title" style="margin:0;">Gerir: <?= h($local_gerir['nome']) ?></h1>
    </div>

    <!-- Fotos -->
    <div style="background:var(--branco);border-radius:var(--radius-lg);padding:1.5rem;box-shadow:var(--sombra-sm);margin-bottom:1.5rem;">
      <h3 style="margin-bottom:1.25rem;"><i class="fas fa-images"></i> Fotos da Galeria</h3>

      <!-- Upload nova foto -->
      <form method="POST" enctype="multipart/form-data" style="margin-bottom:1.25rem;display:flex;gap:.75rem;align-items:center;flex-wrap:wrap;">
        <input type="hidden" name="local_id_upload" value="<?= $gerir_id ?>">
        <input type="file" name="foto_admin" accept="image/*" required
               style="border:1.5px solid var(--creme-escuro);border-radius:8px;padding:.4rem .75rem;background:var(--creme);font-size:.9rem;">
        <button type="submit" class="btn btn-sm btn-verde">
          <i class="fas fa-upload"></i> Adicionar Foto
        </button>
      </form>

      <!-- Lista de fotos existentes -->
      <?php if ($fotos_gerir): ?>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:.75rem;">
          <?php foreach ($fotos_gerir as $foto): ?>
            <div style="position:relative;border-radius:var(--radius);overflow:hidden;aspect-ratio:4/3;background:var(--creme-escuro);">
              <img src="<?= SITE_URL ?>/uploads/locais/<?= h($foto['ficheiro']) ?>" alt=""
                   style="width:100%;height:100%;object-fit:cover;">
              <!-- Botão eliminar foto -->
              <a href="?apagar_foto=<?= $foto['id'] ?>&local_id=<?= $gerir_id ?>"
                 onclick="return confirm('Eliminar esta foto permanentemente?')"
                 style="position:absolute;top:.4rem;right:.4rem;background:#c0392b;color:#fff;
                        border-radius:6px;padding:.2rem .45rem;font-size:.8rem;text-decoration:none;">
                <i class="fas fa-trash"></i>
              </a>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <p style="color:var(--texto-muted);font-size:.9rem;">Sem fotos neste local.</p>
      <?php endif; ?>
    </div>

    <!-- Comentários -->
    <div>
      <h3 style="margin-bottom:1.25rem;"><i class="fas fa-comments"></i> Comentários (<?= count($comentarios_gerir) ?>)</h3>
      <?php if ($comentarios_gerir): ?>
        <table class="data-table">
          <thead>
            <tr>
              <th>Utilizador</th>
              <th>Comentário</th>
              <th>Data</th>
              <th>Ação</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($comentarios_gerir as $com): ?>
            <tr>
              <td>@<?= h($com['username']) ?></td>
              <td style="max-width:300px;word-break:break-word;"><?= h($com['texto']) ?></td>
              <td><?= date('d/m/Y', strtotime($com['criado_em'])) ?></td>
              <td>
                <!-- Eliminar comentário permanentemente -->
                <a href="?apagar_comentario=<?= $com['id'] ?>&local_id=<?= $gerir_id ?>"
                   onclick="return confirm('Eliminar este comentário permanentemente?')"
                   class="btn btn-sm btn-danger" title="Eliminar comentário">
                  <i class="fas fa-trash"></i>
                </a>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <p style="color:var(--texto-muted);font-size:.9rem;">Sem comentários neste local.</p>
      <?php endif; ?>
    </div>

    <?php else: ?>
    <!-- ── MODO LISTAGEM NORMAL ── -->
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;flex-wrap:wrap;gap:1rem;">
      <h1 class="admin-title" style="margin:0;">Gerir Locais</h1>
      <!-- Separadores de filtro -->
      <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
        <a href="?" class="btn btn-sm <?= (!$filtro && !$bloqueado) ? 'btn-verde' : '' ?>"
           style="<?= ($filtro || $bloqueado) ? 'border:1px solid var(--creme-escuro);color:var(--texto-muted);' : '' ?>">Todos</a>
        <a href="?filtro=aprovado" class="btn btn-sm <?= $filtro==='aprovado' ? 'btn-verde' : '' ?>"
           style="<?= $filtro!=='aprovado' ? 'border:1px solid var(--creme-escuro);color:var(--texto-muted);' : '' ?>">Ativos</a>
        <a href="?bloqueado=1" class="btn btn-sm"
           style="<?= $bloqueado ? 'background:#c0392b;color:#fff;border:none;' : 'border:1px solid var(--creme-escuro);color:var(--texto-muted);' ?>">Bloqueados</a>
      </div>
    </div>

    <!-- Barra de pesquisa -->
    <form method="GET" style="margin-bottom:1.25rem;">
      <?php if ($bloqueado): ?>
        <input type="hidden" name="bloqueado" value="1">
      <?php elseif ($filtro): ?>
        <input type="hidden" name="filtro" value="<?= h($filtro) ?>">
      <?php endif; ?>
      <div style="display:flex;align-items:center;gap:.5rem;background:var(--creme);border:2px solid var(--verde-claro);border-radius:8px;padding:.4rem .75rem;max-width:400px;">
        <i class="fas fa-search" style="color:var(--texto-muted);font-size:.85rem;"></i>
        <input type="text" name="q" value="<?= h($pesquisa) ?>" placeholder="Pesquisar por nome ou utilizador..."
               style="border:none;background:transparent;outline:none;font-size:.9rem;width:100%;">
        <?php if ($pesquisa): ?>
          <a href="?<?= $bloqueado ? 'bloqueado=1' : ($filtro ? 'filtro=' . h($filtro) : '') ?>"
             style="color:var(--texto-muted);font-size:.85rem;text-decoration:none;flex-shrink:0;">
            <i class="fas fa-times"></i>
          </a>
        <?php endif; ?>
      </div>
    </form>

    <table class="data-table">
      <thead>
        <tr>
          <th>Nome</th>
          <th>Utilizador</th>
          <th>Categoria</th>
          <th>Estado</th>
          <th>Bloqueado</th>
          <th>Vistas</th>
          <th>Data</th>
          <th>Ações</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($locais as $l): ?>
        <tr>
          <td>
            <a href="<?= SITE_URL ?>/pages/local.php?id=<?= $l['id'] ?>" style="color:var(--verde);font-weight:600;">
              <?= h($l['nome']) ?>
            </a>
          </td>
          <td>@<?= h($l['username']) ?></td>
          <td><?= h($l['categoria_nome']) ?></td>
          <td><span class="badge badge-<?= $l['estado'] ?>"><?= ucfirst($l['estado']) ?></span></td>
          <td>
            <?php if ((int)$l['bloqueado'] === 1): ?>
              <span class="badge badge-rejeitado">Bloqueado</span>
            <?php else: ?>
              <span style="color:var(--texto-muted);font-size:.85rem;">—</span>
            <?php endif; ?>
          </td>
          <td><?= number_format($l['vistas']) ?></td>
          <td><?= date('d/m/Y', strtotime($l['criado_em'])) ?></td>
          <td style="display:flex;gap:.5rem;flex-wrap:wrap;">
            <?php if ($bloqueado): ?>
              <!-- Restaurar local bloqueado -->
              <a href="?restaurar=<?= $l['id'] ?>"
                 onclick="return confirm('Restaurar este local?')"
                 class="btn btn-sm btn-primary" title="Restaurar local">
                <i class="fas fa-undo"></i> Restaurar
              </a>
            <?php else: ?>
              <!-- Ver local -->
              <a href="<?= SITE_URL ?>/pages/local.php?id=<?= $l['id'] ?>" class="btn btn-sm btn-verde" title="Ver local">
                <i class="fas fa-eye"></i>
              </a>
              <!-- Gerir comentários e fotos -->
              <a href="?gerir=<?= $l['id'] ?>" class="btn btn-sm btn-primary" title="Gerir fotos e comentários">
                <i class="fas fa-cog"></i>
              </a>
              <!-- Editar local -->
              <a href="<?= SITE_URL ?>/pages/local_editar.php?id=<?= $l['id'] ?>" class="btn btn-sm" style="border:1px solid var(--creme-escuro);color:var(--texto-muted);" title="Editar">
                <i class="fas fa-edit"></i>
              </a>
              <!-- Apagar local -->
              <a href="?apagar=<?= $l['id'] ?>" class="btn btn-sm btn-danger"
                 data-confirm="Apagar este local permanentemente?" title="Apagar">
                <i class="fas fa-trash"></i>
              </a>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$locais): ?>
          <tr><td colspan="8" style="text-align:center;color:var(--texto-muted);padding:2rem;">Sem locais.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
    <?php endif; ?>

  </main>
</div>
</div>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>