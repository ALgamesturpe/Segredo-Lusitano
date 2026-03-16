<?php
// SEGREDO LUSITANO — Admin: Utilizadores
require_once dirname(__DIR__) . '/includes/functions.php';
require_admin();

$page_title = 'Admin · Utilizadores';

// Suspender/ativar
if (isset($_GET['toggle'])) {
    $uid = (int)$_GET['toggle'];
    $st  = db()->prepare('SELECT ativo FROM utilizadores WHERE id=?');
    $st->execute([$uid]);
    $row = $st->fetch();
    if ($row) {
        db()->prepare('UPDATE utilizadores SET ativo=? WHERE id=?')->execute([!$row['ativo'], $uid]);
    }
    flash('success', 'Utilizador atualizado.');
    $redirect = isset($_GET['suspensos']) ? '?suspensos=1' : '';
    header('Location: ' . SITE_URL . '/admin/utilizadores.php' . $redirect);
    exit;
}

$suspensos = isset($_GET['suspensos']) && $_GET['suspensos'] === '1';
$where = $suspensos ? 'WHERE u.ativo = 0 AND u.role = "user"' : '';

$st = db()->query(
    "SELECT u.*, (SELECT COUNT(*) FROM locais WHERE utilizador_id=u.id AND estado='aprovado') AS total_locais
     FROM utilizadores u
     $where
     ORDER BY u.criado_em DESC"
);
$users = $st->fetchAll();

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
      <a href="<?= SITE_URL ?>/admin/locais.php"><i class="fas fa-map-pin"></i> Locais</a>
      <a href="<?= SITE_URL ?>/admin/utilizadores.php" class="active"><i class="fas fa-users"></i> Utilizadores</a>
      <a href="<?= SITE_URL ?>/index.php"><i class="fas fa-external-link-alt"></i> Ver Site</a>
    </nav>
  </aside>
  <main class="admin-content">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;flex-wrap:wrap;gap:1rem;">
      <h1 class="admin-title" style="margin:0;"><i class="fas fa-users"></i> Gerir Utilizadores</h1>
      <div style="display:flex;gap:.5rem;">
        <a href="?" class="btn btn-sm <?= !$suspensos ? 'btn-verde' : '' ?>"
           style="<?= $suspensos ? 'border:1px solid var(--creme-escuro);color:var(--texto-muted);' : '' ?>">Todos</a>
        <a href="?suspensos=1" class="btn btn-sm"
           style="<?= $suspensos ? 'background:#e67e22;color:#fff;border:none;' : 'border:1px solid var(--creme-escuro);color:var(--texto-muted);' ?>">Suspensos</a>
      </div>
    </div>

    <table class="data-table">
      <thead><tr><th>Nome</th><th>Username</th><th>Email</th><th>Pontos</th><th>Locais</th><th>Role</th><th>Estado</th><th>Ações</th></tr></thead>
      <tbody>
        <?php foreach ($users as $u): ?>
        <tr>
          <td><?= h($u['nome']) ?></td>
          <td>@<?= h($u['username']) ?></td>
          <td><?= h($u['email']) ?></td>
          <td><?= number_format((int)$u['pontos']) ?></td>
          <td><?= $u['total_locais'] ?></td>
          <td>
            <span class="badge <?= $u['role']==='admin' ? 'badge-cat' : '' ?>"><?= ucfirst($u['role']) ?></span>
          </td>
          <td>
            <span style="color:<?= $u['ativo'] ? '#27ae60' : '#e74c3c' ?>;font-weight:700;">
              <?= $u['ativo'] ? 'Ativo' : 'Suspenso' ?>
            </span>
          </td>
          <td>
            <a href="<?= SITE_URL ?>/pages/perfil.php?id=<?= $u['id'] ?>" class="btn btn-sm btn-verde"><i class="fas fa-eye"></i></a>
            <?php if ($u['role'] !== 'admin'): ?>
            <a href="?toggle=<?= $u['id'] ?><?= $suspensos ? '&suspensos=1' : '' ?>"
               class="btn btn-sm <?= $u['ativo'] ? 'btn-danger' : 'btn-primary' ?>">
              <i class="fas fa-<?= $u['ativo'] ? 'ban' : 'check' ?>"></i>
            </a>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$users): ?>
          <tr><td colspan="8" style="text-align:center;color:var(--texto-muted);padding:2rem;">Sem utilizadores.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </main>
</div>
</div>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>