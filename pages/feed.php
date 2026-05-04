<?php
// SEGREDO LUSITANO — Feed de Seguidos + Pesquisa de Pessoas
require_once dirname(__DIR__) . '/includes/functions.php';
require_login();

$user = auth_user();
$page_title = 'Amigos';

$pesquisa = trim($_GET['pesquisa'] ?? '');

// ── Resultados de pesquisa de utilizadores ────────────────
$resultados_pesquisa = [];
if ($pesquisa !== '') {
    $st = db()->prepare(
        'SELECT u.id, u.nome, u.username, u.avatar, u.pontos,
                (SELECT COUNT(*) FROM locais WHERE utilizador_id = u.id AND estado = "aprovado") AS total_locais,
                (SELECT COUNT(*) FROM seguidores WHERE seguido_id = u.id) AS total_seguidores,
                (SELECT COUNT(*) FROM seguidores WHERE seguidor_id = ? AND seguido_id = u.id) AS ja_segue
         FROM utilizadores u
         WHERE u.ativo = 1 AND u.role = "user" AND u.id != ?
           AND (u.nome LIKE ? OR u.username LIKE ?)
         ORDER BY u.pontos DESC LIMIT 20'
    );
    $termo = '%' . $pesquisa . '%';
    $st->execute([$user['id'], $user['id'], $termo, $termo]);
    $resultados_pesquisa = $st->fetchAll();
}

// ── Feed normal ───────────────────────────────────────────
$st = db()->prepare(
    'SELECT l.*, c.nome AS categoria_nome, c.icone AS categoria_icone,
            r.nome AS regiao_nome, u.username, u.nome AS autor_nome, u.avatar,
            (SELECT COUNT(*) FROM likes WHERE local_id = l.id) AS total_likes,
            (SELECT COUNT(*) FROM comentarios WHERE local_id = l.id) AS total_comentarios
     FROM locais l
     JOIN categorias c ON c.id = l.categoria_id
     JOIN regioes r ON r.id = l.regiao_id
     JOIN utilizadores u ON u.id = l.utilizador_id
     JOIN seguidores s ON s.seguido_id = l.utilizador_id
     WHERE s.seguidor_id = ? AND l.estado = "aprovado" AND l.bloqueado = 0 AND l.apagado_em IS NULL
     ORDER BY l.criado_em DESC LIMIT 48'
);
$st->execute([$user['id']]);
$locais_feed = $st->fetchAll();

$st2 = db()->prepare('SELECT COUNT(*) FROM seguidores WHERE seguidor_id = ?');
$st2->execute([$user['id']]);
$total_seguidos = (int)$st2->fetchColumn();

include dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-content">
<section class="section">
  <div class="container">

    <!-- Barra de pesquisa de utilizadores -->
    <div style="background:var(--branco);border-radius:var(--radius-lg);padding:1.25rem 1.5rem;box-shadow:var(--sombra-sm);margin-bottom:2rem;">
      <form method="GET" style="display:flex;align-items:center;gap:.75rem;">
        <div style="display:flex;align-items:center;gap:.5rem;flex:1;background:var(--creme);
                    border:1.5px solid var(--creme-escuro);border-radius:8px;padding:.45rem .85rem;
                    transition:border-color .2s;"
             onfocusin="this.style.borderColor='var(--verde-claro)'"
             onfocusout="this.style.borderColor='var(--creme-escuro)'">
          <i class="fas fa-search" style="color:var(--texto-muted);font-size:.85rem;flex-shrink:0;"></i>
          <input type="text" name="pesquisa" value="<?= h($pesquisa) ?>"
                 placeholder="Pesquisar exploradores por nome ou @username..."
                 style="border:none;background:transparent;outline:none;font-size:.9rem;width:100%;color:var(--texto);"
                 autocomplete="off">
        </div>
        <?php if ($pesquisa): ?>
          <a href="<?= SITE_URL ?>/pages/feed.php"
             style="padding:.5rem .85rem;border:1.5px solid var(--creme-escuro);border-radius:4px;
                    color:var(--texto-muted);font-size:.85rem;text-decoration:none;white-space:nowrap;">
            <i class="fas fa-times"></i> Limpar
          </a>
        <?php endif; ?>
        <button type="submit" class="btn btn-sm btn-verde" style="white-space:nowrap;">
          <i class="fas fa-search"></i> Pesquisar
        </button>
      </form>
    </div>

    <?php if ($pesquisa !== ''): ?>
      <!-- Resultados de pesquisa -->
      <div class="section-header" style="text-align:left;margin-bottom:1rem;">
        <h2 style="font-size:1.3rem;">
          <i class="fas fa-users"></i> Resultados para "<?= h($pesquisa) ?>"
          <span style="color:var(--texto-muted);font-size:.9rem;font-weight:400;margin-left:.5rem;">(<?= count($resultados_pesquisa) ?>)</span>
        </h2>
      </div>

      <?php if ($resultados_pesquisa): ?>
        <div style="display:flex;flex-direction:column;gap:.75rem;margin-bottom:2.5rem;">
          <?php foreach ($resultados_pesquisa as $u): ?>
            <div style="background:var(--branco);border-radius:var(--radius-lg);padding:1rem 1.25rem;
                        box-shadow:var(--sombra-sm);display:flex;align-items:center;gap:1rem;flex-wrap:wrap;">
              <!-- Avatar -->
              <a href="<?= SITE_URL ?>/pages/perfil.php?id=<?= $u['id'] ?>"
                 style="width:48px;height:48px;border-radius:50%;background:var(--verde-claro);color:#fff;
                        overflow:hidden;display:flex;align-items:center;justify-content:center;
                        font-weight:700;font-size:1.1rem;flex-shrink:0;text-decoration:none;">
                <?php if (!empty($u['avatar'])): ?>
                  <img src="<?= SITE_URL ?>/uploads/locais/<?= h($u['avatar']) ?>" style="width:100%;height:100%;object-fit:cover;">
                <?php else: ?>
                  <?= mb_strtoupper(mb_substr($u['username'],0,1)) ?>
                <?php endif; ?>
              </a>
              <!-- Info -->
              <div style="flex:1;min-width:0;">
                <a href="<?= SITE_URL ?>/pages/perfil.php?id=<?= $u['id'] ?>"
                   style="font-weight:700;color:var(--texto);text-decoration:none;display:block;"><?= h($u['nome']) ?></a>
                <div style="font-size:.83rem;color:var(--verde);">@<?= h($u['username']) ?></div>
                <div style="font-size:.8rem;color:var(--texto-muted);margin-top:.2rem;">
                  <?= $u['total_locais'] ?> locais · <?= $u['total_seguidores'] ?> seguidores · <?= number_format($u['pontos']) ?> pts
                </div>
              </div>
              <!-- Botão seguir -->
              <button class="btn btn-sm btn-seguir <?= $u['ja_segue'] ? '' : 'btn-primary' ?>"
                      data-id="<?= $u['id'] ?>"
                      style="<?= $u['ja_segue'] ? 'border:1.5px solid var(--creme-escuro);color:var(--texto-muted);' : '' ?>;flex-shrink:0;">
                <i class="fas <?= $u['ja_segue'] ? 'fa-user-check' : 'fa-user-plus' ?>"></i>
                <span><?= $u['ja_segue'] ? 'A Seguir' : 'Seguir' ?></span>
              </button>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="empty-state" style="padding:2rem;">
          <i class="fas fa-user-slash"></i>
          <h3>Nenhum explorador encontrado</h3>
          <p>Tenta pesquisar por outro nome ou username.</p>
        </div>
      <?php endif; ?>

      <hr style="border:none;border-top:1px solid var(--creme-escuro);margin:1.5rem 0;">
    <?php endif; ?>

    <!-- Feed normal -->
    <div class="section-header">
      <h2><i class="fas fa-newspaper"></i> Publicações</h2>
      <p>As publicações mais recentes de quem segues.</p>
    </div>

    <?php if ($locais_feed): ?>
      <div class="cards-grid">
        <?php foreach ($locais_feed as $local): ?>
          <?php $ocultar_btn_seguir = true; ?>
          <?php include dirname(__DIR__) . '/includes/card_local.php'; ?>
          <?php $ocultar_btn_seguir = false; ?>
        <?php endforeach; ?>
      </div>
    <?php elseif ($total_seguidos === 0): ?>
      <div class="empty-state">
        <i class="fas fa-user-plus"></i>
        <h3>Ainda não segues ninguém</h3>
        <p>Usa a pesquisa acima para encontrar exploradores a seguir.</p>
        <a href="<?= SITE_URL ?>/pages/explorar.php" class="btn btn-sm btn-primary" style="margin-top:1rem;display:inline-flex;align-items:center;gap:.4rem;">
          <i class="fa-solid fa-earth-americas"></i> Explorar Locais
        </a>
      </div>
    <?php else: ?>
      <div class="empty-state">
        <i class="fas fa-map"></i>
        <h3>Sem publicações recentes</h3>
        <p>Os exploradores que segues ainda não publicaram nada.</p>
      </div>
    <?php endif; ?>

  </div>
</section>
</div>

<script>
// Botões de seguir nos resultados de pesquisa
document.querySelectorAll('.btn-seguir').forEach(btn => {
  btn.addEventListener('click', async () => {
    const id  = btn.dataset.id;
    const res = await fetch('<?= SITE_URL ?>/pages/seguir.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: `id=${id}`
    });
    const data = await res.json();
    if (!data.ok) return;
    const aSeguir = data.a_seguir;
    btn.className = 'btn btn-sm btn-seguir ' + (aSeguir ? '' : 'btn-primary');
    btn.style.cssText = aSeguir ? 'border:1.5px solid var(--creme-escuro);color:var(--texto-muted);flex-shrink:0;' : 'flex-shrink:0;';
    btn.innerHTML = `<i class="fas ${aSeguir ? 'fa-user-check' : 'fa-user-plus'}"></i> <span>${aSeguir ? 'A Seguir' : 'Seguir'}</span>`;
  });
});
</script>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>