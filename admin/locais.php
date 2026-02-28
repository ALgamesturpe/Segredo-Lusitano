<?php
// ============================================================
// SEGREDO LUSITANO — Admin: Gerir Locais
// ============================================================
require_once dirname(__DIR__) . '/includes/functions.php';
require_admin();

$page_title = 'Admin · Locais';

// Apagar
if (isset($_GET['apagar'])) {
    delete_local((int)$_GET['apagar']);
    flash('success', 'Local apagado.');
    header('Location: ' . SITE_URL . '/admin/locais.php');
    exit;
}

$estado = $_GET['estado'] ?? '';
$where  = $estado ? 'WHERE l.estado = "' . $estado . '"' : '';

$st = db()->query(
    "SELECT l.*, c.nome AS categoria_nome, r.nome AS regiao_nome, u.username
     FROM locais l
     JOIN categorias c ON c.id = l.categoria_id
     JOIN regioes r ON r.id = l.regiao_id
     JOIN utilizadores u ON u.id = l.utilizador_id
     $where ORDER BY l.criado_em DESC"
);
$locais = $st->fetchAll();

include dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-content">
<div class="admin-wrapper">
  <aside class="admin-sidebar">
    <div style="color:var(--dourado);font-family:'Playfair Display',serif;font-size:1.1rem;font-weight:700;margin-bottom:1.5rem;padding:.5rem .85rem;">
      <i class="fas fa-shield-alt"></i> Administração
    </div>
    <nav class="admin-nav">
      <a href="<?= SITE_URL ?>/admin/index.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
      <a href="<?= SITE_URL ?>/admin/locais.php" class="active"><i class="fas fa-map-pin"></i> Locais</a>
      <a href="<?= SITE_URL ?>/admin/utilizadores.php"><i class="fas fa-users"></i> Utilizadores</a>
      <a href="<?= SITE_URL ?>/index.php"><i class="fas fa-external-link-alt"></i> Ver Site</a>
    </nav>
  </aside>
  <main class="admin-content">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;flex-wrap:wrap;gap:1rem;">
      <h1 class="admin-title" style="margin:0;"><i class="fas fa-map-pin"></i> Gerir Locais</h1>
      <div style="display:flex;gap:.5rem;">
        <a href="?" class="btn btn-sm <?= !$estado ? 'btn-verde' : '' ?>" style="<?= $estado ? 'border:1px solid var(--creme-escuro);color:var(--texto-muted);' : '' ?>">Todos</a>
        <a href="?estado=aprovado"  class="btn btn-sm <?= $estado==='aprovado' ? 'btn-verde' : '' ?>" style="<?= $estado!=='aprovado' ? 'border:1px solid var(--creme-escuro);color:var(--texto-muted);' : '' ?>">Aprovados</a>
        <a href="?estado=pendente"  class="btn btn-sm <?= $estado==='pendente' ? 'btn-primary' : '' ?>" style="<?= $estado!=='pendente' ? 'border:1px solid var(--creme-escuro);color:var(--texto-muted);' : '' ?>">Pendentes</a>
        <a href="?estado=rejeitado" class="btn btn-sm <?= $estado==='rejeitado' ? 'btn-danger' : '' ?>" style="<?= $estado!=='rejeitado' ? 'border:1px solid var(--creme-escuro);color:var(--texto-muted);' : '' ?>">Rejeitados</a>
      </div>
    </div>

    <table class="data-table">
      <thead><tr><th>Nome</th><th>Utilizador</th><th>Categoria</th><th>Estado</th><th>Vistas</th><th>Data</th><th>Ações</th></tr></thead>
      <tbody>
        <?php foreach ($locais as $l): ?>
        <tr>
          <td><a href="<?= SITE_URL ?>/pages/local.php?id=<?= $l['id'] ?>" style="color:var(--verde);font-weight:600;"><?= h($l['nome']) ?></a></td>
          <td>@<?= h($l['username']) ?></td>
          <td><?= h($l['categoria_nome']) ?></td>
          <td><span class="badge badge-<?= $l['estado'] ?>"><?= ucfirst($l['estado']) ?></span></td>
          <td><?= number_format($l['vistas']) ?></td>
          <td><?= date('d/m/Y', strtotime($l['criado_em'])) ?></td>
          <td style="display:flex;gap:.5rem;flex-wrap:wrap;">
            <a href="<?= SITE_URL ?>/pages/local_editar.php?id=<?= $l['id'] ?>" class="btn btn-sm btn-verde"><i class="fas fa-edit"></i></a>
            <a href="?apagar=<?= $l['id'] ?>" class="btn btn-sm btn-danger" data-confirm="Apagar este local permanentemente?"><i class="fas fa-trash"></i></a>
            <?php if ($l['estado'] === 'pendente'): ?>
              <form method="POST" action="<?= SITE_URL ?>/admin/index.php" style="display:inline;">
                <input type="hidden" name="local_id" value="<?= $l['id'] ?>">
                <input type="hidden" name="estado" value="aprovado">
                <button type="submit" name="moderar" class="btn btn-sm btn-primary btn-sm"><i class="fas fa-check"></i></button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$locais): ?>
          <tr><td colspan="7" style="text-align:center;color:var(--texto-muted);padding:2rem;">Sem locais.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </main>
</div>
</div>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
