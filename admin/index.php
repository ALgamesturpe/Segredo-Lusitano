<?php

// SEGREDO LUSITANO — Painel de Administração
require_once dirname(__DIR__) . '/includes/functions.php';
require_admin();

$page_title = 'Administração';

// Stats
$total_locais     = (int)db()->query('SELECT COUNT(*) FROM locais WHERE estado="aprovado"')->fetchColumn();
$total_users      = (int)db()->query('SELECT COUNT(*) FROM utilizadores WHERE ativo=1 AND role="user"')->fetchColumn();
$total_comentarios= (int)db()->query('SELECT COUNT(*) FROM comentarios')->fetchColumn();
$total_denuncias  = (int)db()->query('SELECT COUNT(*) FROM denuncias WHERE resolvida=0')->fetchColumn();
$total_bloqueados = (int)db()->query('SELECT COUNT(*) FROM locais WHERE bloqueado=1')->fetchColumn();
$total_suspensos  = (int)db()->query('SELECT COUNT(*) FROM utilizadores WHERE ativo=0 AND role="user"')->fetchColumn();

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
        flash('success', 'Denuncia resolvida!');
        header('Location: ' . SITE_URL . '/admin/index.php');
        exit;
    }
    if (isset($_POST['moderar_denuncia_item'])) {
        $tipo   = (string)($_POST['tipo']   ?? '');
        $ref_id = (int)($_POST['ref_id']    ?? 0);
        $acao   = (string)($_POST['acao']   ?? '');
        $ok     = moderar_denuncias_item($tipo, $ref_id, $acao === 'bloquear');
        if ($ok) {
            flash('success', $acao === 'bloquear' ? 'Conteudo bloqueado e denuncias resolvidas.' : 'Conteudo permitido e denuncias resolvidas.');
        } else {
            flash('error', 'Nao foi possivel moderar este item.');
        }
        header('Location: ' . SITE_URL . '/admin/index.php#denuncias');
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
      <a href="#denuncias"><i class="fas fa-flag"></i> Denúncias <span style="background:#e74c3c;color:#fff;padding:.1rem .4rem;border-radius:50px;font-size:.7rem;margin-left:.25rem;"><?= $total_denuncias ?></span></a>
      <div class="nav-section">Site</div>
      <a href="<?= SITE_URL ?>/index.php"><i class="fas fa-external-link-alt"></i> Ver Site</a>
    </nav>
  </aside>

  <!-- CONTEÚDO -->
  <main class="admin-content">
    <h1 class="admin-title"><i class="fas fa-tachometer-alt"></i> Dashboard</h1>

    <!-- Stats — 6 cards -->
    <div class="admin-cards" style="grid-template-columns: repeat(6, 1fr);">

      <div class="admin-stat-card">
        <div class="num"><?= $total_locais ?></div>
        <div class="lbl">Locais Aprovados</div>
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

      <a href="<?= SITE_URL ?>/admin/locais.php?bloqueado=1" style="text-decoration:none;">
        <div class="admin-stat-card" style="border-color:#8e44ad; cursor:pointer;">
          <div class="num" style="color:#8e44ad;"><?= $total_bloqueados ?></div>
          <div class="lbl">Locais Bloqueados</div>
        </div>
      </a>

      <a href="<?= SITE_URL ?>/admin/utilizadores.php?suspensos=1" style="text-decoration:none;">
        <div class="admin-stat-card" style="border-color:#e67e22; cursor:pointer;">
          <div class="num" style="color:#e67e22;"><?= $total_suspensos ?></div>
          <div class="lbl">Utilizadores Suspensos</div>
        </div>
      </a>

    </div>

    <!-- DENÚNCIAS -->
    <h2 style="font-size:1.3rem; margin-bottom:1rem;" id="denuncias">
      <i class="fas fa-flag"></i> Denúncias Abertas
    </h2>
    <?php if ($denuncias): ?>
    <table class="data-table">
      <thead><tr><th>Tipo</th><th>Ref. ID</th><th>Alvo</th><th>Motivo</th><th>Estado</th><th>Data</th><th>Acao</th></tr></thead>
      <tbody>
        <?php foreach ($denuncias as $den): ?>
        <?php $bloqueado = ((int)$den['alvo_bloqueado'] === 1); ?>
        <tr>
          <td><span class="badge badge-cat"><?= h($den['tipo']) ?></span></td>
          <td>#<?= $den['referencia_id'] ?></td>
          <td>
            <button type="button"
                    class="btn btn-sm"
                    style="padding:.2rem .55rem;border:1px solid var(--creme-escuro);color:var(--texto-muted);"
                    onclick="abrirConteudoDenuncia(this)"
                    data-tipo="<?= h($den['tipo']) ?>"
                    data-ref="#<?= (int)$den['referencia_id'] ?>"
                    data-preview="<?= h((string)($den['alvo_conteudo'] ?? '[indisponivel]')) ?>"
                    data-conteudo="<?= h((string)($den['alvo_conteudo_completo'] ?? '[indisponivel]')) ?>"
                    data-link="<?= !empty($den['alvo_local_id']) ? h(SITE_URL . '/pages/local.php?id=' . (int)$den['alvo_local_id']) : '' ?>">
              Ver Conteudo
            </button>
          </td>
          <td><?= h(motivo_denuncia_label((string)$den['motivo'])) ?></td>
          <td>
            <span class="badge <?= $bloqueado ? 'badge-rejeitado' : 'badge-aprovado' ?>">
              <?= $bloqueado ? 'Bloqueado' : 'Permitido' ?>
            </span>
          </td>
          <td><?= date('d/m/Y', strtotime($den['criado_em'])) ?></td>
          <td>
            <form method="POST" style="display:inline;">
              <input type="hidden" name="tipo"   value="<?= h($den['tipo']) ?>">
              <input type="hidden" name="ref_id" value="<?= (int)$den['referencia_id'] ?>">
              <input type="hidden" name="acao"   value="<?= $bloqueado ? 'permitir' : 'bloquear' ?>">
              <button type="submit" name="moderar_denuncia_item" class="btn btn-sm <?= $bloqueado ? 'btn-verde' : 'btn-danger' ?>">
                <?= $bloqueado ? 'Permitir' : 'Bloquear' ?>
              </button>
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

<div id="modal-conteudo-denuncia" style="display:none; position:fixed; inset:0; z-index:4000; background:rgba(0,0,0,.45); align-items:center; justify-content:center; padding:1rem;">
  <div style="background:#fff; border-radius:var(--radius-lg); width:100%; max-width:720px; padding:1.2rem 1.2rem 1rem;">
    <div style="display:flex; align-items:center; justify-content:space-between; gap:1rem; margin-bottom:.6rem;">
      <h3 style="margin:0;"><i class="fas fa-file-alt"></i> Conteudo denunciado</h3>
      <button type="button" class="btn btn-sm" style="border:1px solid var(--creme-escuro);" onclick="fecharConteudoDenuncia()">Fechar</button>
    </div>
    <div style="font-size:.86rem; color:var(--texto-muted); margin-bottom:.6rem;" id="denuncia-meta"></div>
    <div style="background:var(--creme); border:1px solid var(--creme-escuro); border-radius:10px; padding:.9rem; margin-bottom:.8rem;">
      <div style="font-size:.85rem; color:var(--texto-muted); margin-bottom:.35rem;">Resumo</div>
      <div id="denuncia-preview" style="white-space:pre-wrap;"></div>
    </div>
    <div style="background:var(--creme); border:1px solid var(--creme-escuro); border-radius:10px; padding:.9rem; margin-bottom:.8rem;">
      <div style="font-size:.85rem; color:var(--texto-muted); margin-bottom:.35rem;">Conteudo completo</div>
      <div id="denuncia-conteudo" style="white-space:pre-wrap;"></div>
    </div>
    <a id="denuncia-link" href="#" target="_blank" rel="noopener" class="btn btn-sm btn-verde" style="display:none;">
      <i class="fas fa-external-link-alt"></i> Abrir post
    </a>
  </div>
</div>

<script>
function abrirConteudoDenuncia(btn) {
  const tipo     = btn.getAttribute('data-tipo')    || '';
  const ref      = btn.getAttribute('data-ref')     || '';
  const preview  = btn.getAttribute('data-preview') || '[indisponivel]';
  const conteudo = btn.getAttribute('data-conteudo')|| '[indisponivel]';
  const link     = btn.getAttribute('data-link')    || '';

  document.getElementById('denuncia-meta').textContent     = tipo + ' ' + ref;
  document.getElementById('denuncia-preview').textContent  = preview;
  document.getElementById('denuncia-conteudo').textContent = conteudo;

  const linkEl = document.getElementById('denuncia-link');
  if (link) { linkEl.href = link; linkEl.style.display = 'inline-flex'; }
  else       { linkEl.style.display = 'none'; }

  document.getElementById('modal-conteudo-denuncia').style.display = 'flex';
}
function fecharConteudoDenuncia() {
  document.getElementById('modal-conteudo-denuncia').style.display = 'none';
}
</script>

<style>
a:has(.admin-stat-card) {
  display: block;
  border-radius: inherit;
}
a:has(.admin-stat-card) .admin-stat-card {
  transition: transform .18s ease, box-shadow .18s ease;
}
a:has(.admin-stat-card):hover .admin-stat-card {
  transform: translateY(-5px);
  box-shadow: 0 8px 24px rgba(0,0,0,.13);
}
</style>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>