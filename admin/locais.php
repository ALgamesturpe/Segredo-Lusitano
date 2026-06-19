<?php
// ============================================================
// SEGREDO LUSITANO — Admin: Gerir Locais
// ============================================================
require_once dirname(__DIR__) . '/includes/functions.php';
require_admin();

$page_title = 'Admin · Locais';

// ── Auto-purga: eliminar definitivamente após 30 dias ─────
$expirados = db()->query(
    "SELECT id FROM locais WHERE apagado_em IS NOT NULL AND apagado_em < DATE_SUB(NOW(), INTERVAL 30 DAY)"
)->fetchAll();
foreach ($expirados as $exp) {
    limpar_imagens_local($exp['id']);
    db()->prepare('DELETE FROM locais WHERE id = ?')->execute([$exp['id']]);
}

// ── Apagar local (soft delete — 30 dias standby) ──────────
if (isset($_GET['apagar'])) {
    delete_local((int)$_GET['apagar']);
    flash('success', 'Local movido para a lixeira. Será eliminado em 30 dias.');
    header('Location: ' . SITE_URL . '/admin/locais.php');
    exit;
}

// ── Restaurar local da lixeira ────────────────────────────
if (isset($_GET['restaurar_apagado'])) {
    db()->prepare('UPDATE locais SET apagado_em = NULL WHERE id = ?')->execute([(int)$_GET['restaurar_apagado']]);
    flash('success', 'Local restaurado da lixeira.');
    header('Location: ' . SITE_URL . '/admin/locais.php?apagado=1');
    exit;
}

// ── Purgar local definitivamente ──────────────────────────
if (isset($_GET['purgar'])) {
    $pid = (int)$_GET['purgar'];
    limpar_imagens_local($pid);
    db()->prepare('DELETE FROM locais WHERE id = ?')->execute([$pid]);
    flash('success', 'Local eliminado definitivamente.');
    header('Location: ' . SITE_URL . '/admin/locais.php?apagado=1');
    exit;
}

// ── Bloquear local manualmente ────────────────────────────
if (isset($_GET['bloquear'])) {
    db()->prepare('UPDATE locais SET bloqueado = 1 WHERE id = ?')->execute([(int)$_GET['bloquear']]);
    flash('success', 'Local bloqueado.');
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

// ── Filtros ───────────────────────────────────────────────
$filtro    = $_GET['filtro']    ?? 'aprovado';
$bloqueado = isset($_GET['bloqueado']) && $_GET['bloqueado'] === '1';
$apagado   = isset($_GET['apagado'])   && $_GET['apagado']   === '1';

// Pesquisa
$pesquisa = trim($_GET['q'] ?? '');

if ($apagado) {
    $where = 'WHERE l.apagado_em IS NOT NULL';
} elseif ($bloqueado) {
    $where = 'WHERE l.bloqueado = 1 AND l.apagado_em IS NULL';
} elseif ($filtro === 'aprovado') {
    $where = 'WHERE l.estado = "aprovado" AND l.bloqueado = 0 AND l.apagado_em IS NULL';
} else {
    $where = 'WHERE l.bloqueado = 0 AND l.apagado_em IS NULL';
}

$params = [];
if ($pesquisa) {
    $where .= ' AND (l.nome LIKE ? OR u.username LIKE ?)';
    $params = ["%$pesquisa%", "%$pesquisa%"];
}

$st = db()->prepare(
    "SELECT l.*, c.nome AS categoria_nome, r.nome AS regiao_nome, u.username,
            DATEDIFF(DATE_ADD(l.apagado_em, INTERVAL 30 DAY), NOW()) AS dias_restantes
     FROM locais l
     JOIN categorias c ON c.id = l.categoria_id
     JOIN regioes r ON r.id = l.regiao_id
     JOIN utilizadores u ON u.id = l.utilizador_id
     $where ORDER BY l.criado_em DESC"
);
$st->execute($params);
$locais = $st->fetchAll();

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

    <!-- ── LISTAGEM DE LOCAIS ── -->
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;flex-wrap:wrap;gap:1rem;">
      <h1 class="admin-title" style="margin:0;"><i class="fa-solid fa-location-dot"></i> Locais</h1>
      <!-- Separadores de filtro -->
      <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
        <a href="?filtro=aprovado" class="btn btn-sm <?= ($filtro==='aprovado' && !$apagado) ? 'btn-verde' : '' ?>"
           style="<?= (!($filtro==='aprovado' && !$apagado)) ? 'border:1px solid var(--creme-escuro);color:var(--texto-muted);' : '' ?>">Ativos</a>
        <a href="?apagado=1" class="btn btn-sm"
           style="<?= $apagado ? 'background:#7f8c8d;color:#fff;border:none;' : 'border:1px solid var(--creme-escuro);color:var(--texto-muted);' ?>">Apagados</a>
      </div>
    </div>

    <!-- Barra de pesquisa -->
    <form method="GET" style="margin-bottom:1.25rem;">
      <?php if ($apagado): ?>
        <input type="hidden" name="apagado" value="1">
      <?php elseif ($bloqueado): ?>
        <input type="hidden" name="bloqueado" value="1">
      <?php elseif ($filtro): ?>
        <input type="hidden" name="filtro" value="<?= h($filtro) ?>">
      <?php endif; ?>
      <div style="display:flex;align-items:center;gap:.5rem;background:var(--creme);border:2px solid var(--verde-claro);border-radius:0;padding:.4rem .75rem;max-width:400px;">
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

    <div class="data-table-wrap"<?= count($locais) > 20 ? ' style="max-height:560px;overflow-y:auto;"' : '' ?>>
    <table class="data-table">
      <thead style="position:sticky;top:0;z-index:2;">
        <tr>
          <th>Nome</th>
          <th>Utilizador</th>
          <th>Categoria</th>
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
          <td><?= number_format($l['vistas']) ?></td>
          <td><?= date('d/m/Y', strtotime($l['criado_em'])) ?></td>
          <td style="display:flex;gap:.5rem;flex-wrap:wrap;">
            <?php if ($apagado): ?>
              <!-- Restaurar da lixeira -->
              <a href="?restaurar_apagado=<?= $l['id'] ?>" class="btn btn-sm btn-verde" title="Restaurar local">
                <i class="fas fa-undo"></i> Restaurar
              </a>
              <!-- Purgar definitivamente -->
              <a href="?purgar=<?= $l['id'] ?>" class="btn btn-sm btn-danger"
                 onclick="return confirm('Eliminar este local definitivamente? Esta ação é irreversível.')" title="Eliminar já">
                <i class="fas fa-trash"></i>
              </a>
              <!-- Dias restantes -->
              <span style="font-size:.8rem;color:var(--texto-muted);align-self:center;">
                <?= max(0, (int)$l['dias_restantes']) ?> dias restantes
              </span>
            <?php elseif ($bloqueado): ?>
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
              <!-- Editar local -->
              <a href="<?= SITE_URL ?>/pages/local_editar.php?id=<?= $l['id'] ?>" class="btn btn-sm" style="border:1px solid var(--creme-escuro);color:var(--texto-muted);" title="Editar">
                <i class="fas fa-edit"></i>
              </a>
              <!-- Apagar local (soft delete) -->
              <a href="?apagar=<?= $l['id'] ?>" class="btn btn-sm btn-danger" title="Apagar local">
                <i class="fas fa-trash"></i>
              </a>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$locais): ?>
          <tr><td colspan="7" style="text-align:center;color:var(--texto-muted);padding:2rem;">Sem locais.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
    </div>

  </main>
</div>
</div>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>