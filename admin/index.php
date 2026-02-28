<?php
// ============================================================
// SEGREDO LUSITANO — Painel de Administração
// ============================================================
require_once dirname(__DIR__) . '/includes/functions.php';
require_admin();

$page_title = 'Administração';

// Stats
$total_locais     = (int)db()->query('SELECT COUNT(*) FROM locais WHERE estado="aprovado"')->fetchColumn();
$total_pendentes  = (int)db()->query('SELECT COUNT(*) FROM locais WHERE estado="pendente"')->fetchColumn();
$total_users      = (int)db()->query('SELECT COUNT(*) FROM utilizadores WHERE ativo=1 AND role="user"')->fetchColumn();
$total_comentarios= (int)db()->query('SELECT COUNT(*) FROM comentarios')->fetchColumn();
$total_denuncias  = (int)db()->query('SELECT COUNT(*) FROM denuncias WHERE resolvida=0')->fetchColumn();

$pendentes = get_pendentes();

// Ações de moderação
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['moderar'])) {
        moderar_local((int)$_POST['local_id'], $_POST['estado']);
        flash('success', 'Local ' . $_POST['estado'] . ' com sucesso!');
        header('Location: ' . SITE_URL . '/admin/index.php');
        exit;
    }
    if (isset($_POST['resolver_denuncia'])) {
        db()->prepare('UPDATE denuncias SET resolvida=1 WHERE id=?')->execute([(int)$_POST['den_id']]);
        flash('success', 'Denúncia resolvida!');
        header('Location: ' . SITE_URL . '/admin/index.php');
        exit;
    }
}

$denuncias = get_denuncias();
include dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-content">
<div class="admin-wrapper">

  <!-- SIDEBAR -->
  <aside class="admin-sidebar">
    <div style="color:var(--dourado); font-family:'Playfair Display',serif; font-size:1.1rem; font-weight:700; margin-bottom:1.5rem; padding:.5rem .85rem;">
      <i class="fas fa-shield-alt"></i> Administração
    </div>
    <nav class="admin-nav">
      <div class="nav-section">Geral</div>
      <a href="<?= SITE_URL ?>/admin/index.php" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
      <a href="<?= SITE_URL ?>/admin/locais.php"><i class="fas fa-map-pin"></i> Locais</a>
      <a href="<?= SITE_URL ?>/admin/utilizadores.php"><i class="fas fa-users"></i> Utilizadores</a>
      <div class="nav-section">Moderação</div>
      <a href="#pendentes"><i class="fas fa-clock"></i> Pendentes <span style="background:#e74c3c;color:#fff;padding:.1rem .4rem;border-radius:50px;font-size:.7rem;margin-left:.25rem;"><?= $total_pendentes ?></span></a>
      <a href="#denuncias"><i class="fas fa-flag"></i> Denúncias <span style="background:#e74c3c;color:#fff;padding:.1rem .4rem;border-radius:50px;font-size:.7rem;margin-left:.25rem;"><?= $total_denuncias ?></span></a>
      <div class="nav-section">Site</div>
      <a href="<?= SITE_URL ?>/index.php"><i class="fas fa-external-link-alt"></i> Ver Site</a>
    </nav>
  </aside>

  <!-- CONTEÚDO -->
  <main class="admin-content">
    <h1 class="admin-title"><i class="fas fa-tachometer-alt"></i> Dashboard</h1>

    <!-- Stats -->
    <div class="admin-cards">
      <div class="admin-stat-card">
        <div class="num"><?= $total_locais ?></div>
        <div class="lbl">Locais Aprovados</div>
      </div>
      <div class="admin-stat-card" style="border-color:#e67e22;">
        <div class="num" style="color:#e67e22;"><?= $total_pendentes ?></div>
        <div class="lbl">Pendentes</div>
      </div>
      <div class="admin-stat-card" style="border-color:var(--dourado);">
        <div class="num" style="color:var(--dourado);"><?= $total_users ?></div>
        <div class="lbl">Utilizadores</div>
      </div>
      <div class="admin-stat-card" style="border-color:var(--verde-claro);">
        <div class="num" style="color:var(--verde-claro);"><?= $total_comentarios ?></div>
        <div class="lbl">Comentários</div>
      </div>
      <div class="admin-stat-card" style="border-color:#e74c3c;">
        <div class="num" style="color:#e74c3c;"><?= $total_denuncias ?></div>
        <div class="lbl">Denúncias Abertas</div>
      </div>
    </div>

    <!-- LOCAIS PENDENTES -->
    <h2 style="font-size:1.3rem; margin-bottom:1rem;" id="pendentes">
      <i class="fas fa-clock"></i> Locais Pendentes de Aprovação
    </h2>
    <?php if ($pendentes): ?>
    <table class="data-table" style="margin-bottom:2.5rem;">
      <thead>
        <tr>
          <th>Nome</th><th>Utilizador</th><th>Categoria</th><th>Região</th><th>Data</th><th>Ações</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($pendentes as $p): ?>
        <tr>
          <td><a href="<?= SITE_URL ?>/pages/local.php?id=<?= $p['id'] ?>" style="color:var(--verde);font-weight:600;"><?= h($p['nome']) ?></a></td>
          <td><?= h($p['username']) ?></td>
          <td><?= h($p['categoria_nome']) ?></td>
          <td><?= h($p['regiao_nome']) ?></td>
          <td><?= date('d/m/Y', strtotime($p['criado_em'])) ?></td>
          <td>
            <form method="POST" style="display:inline;">
              <input type="hidden" name="local_id" value="<?= $p['id'] ?>">
              <input type="hidden" name="estado" value="aprovado">
              <button type="submit" name="moderar" class="btn btn-sm btn-verde"><i class="fas fa-check"></i> Aprovar</button>
            </form>
            <form method="POST" style="display:inline;">
              <input type="hidden" name="local_id" value="<?= $p['id'] ?>">
              <input type="hidden" name="estado" value="rejeitado">
              <button type="submit" name="moderar" class="btn btn-sm btn-danger" data-confirm="Rejeitar este local?"><i class="fas fa-times"></i> Rejeitar</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php else: ?>
      <div class="empty-state" style="padding:2rem;"><i class="fas fa-check-circle"></i><h3>Sem locais pendentes</h3></div>
    <?php endif; ?>

    <!-- DENÚNCIAS -->
    <h2 style="font-size:1.3rem; margin-bottom:1rem;" id="denuncias">
      <i class="fas fa-flag"></i> Denúncias Abertas
    </h2>
    <?php if ($denuncias): ?>
    <table class="data-table">
      <thead><tr><th>Tipo</th><th>Ref. ID</th><th>Motivo</th><th>Data</th><th>Ação</th></tr></thead>
      <tbody>
        <?php foreach ($denuncias as $den): ?>
        <tr>
          <td><span class="badge badge-cat"><?= h($den['tipo']) ?></span></td>
          <td>#<?= $den['referencia_id'] ?></td>
          <td><?= h(mb_substr($den['motivo'] ?? '',0,80)) ?>...</td>
          <td><?= date('d/m/Y', strtotime($den['criado_em'])) ?></td>
          <td>
            <form method="POST" style="display:inline;">
              <input type="hidden" name="den_id" value="<?= $den['id'] ?>">
              <button type="submit" name="resolver_denuncia" class="btn btn-sm btn-verde">Resolver</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php else: ?>
      <div class="empty-state" style="padding:2rem;"><i class="fas fa-shield-alt"></i><h3>Sem denúncias abertas</h3></div>
    <?php endif; ?>
  </main>
</div>
</div>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
