<?php
// ============================================================
// SEGREDO LUSITANO — Partial: Card de Local
// ============================================================
$dif_class = [
    'facil'   => 'badge-dif-facil',
    'medio'   => 'badge-dif-medio',
    'dificil' => 'badge-dif-dificil',
][$local['dificuldade']] ?? 'badge-dif-medio';

$dif_label = [
    'facil'   => 'Fácil',
    'medio'   => 'Médio',
    'dificil' => 'Difícil',
][$local['dificuldade']] ?? 'Médio';

// Verificar se o utilizador autenticado segue o autor deste local
$_card_user = auth_user();
$_card_segue = false;
$_card_e_proprio = $_card_user && $_card_user['id'] == $local['utilizador_id'];
if ($_card_user && !$_card_e_proprio) {
    $__st = db()->prepare('SELECT id FROM seguidores WHERE seguidor_id = ? AND seguido_id = ?');
    $__st->execute([$_card_user['id'], $local['utilizador_id']]);
    $_card_segue = (bool)$__st->fetch();
}
?>
<article class="card">
  <a href="<?= SITE_URL ?>/pages/local.php?id=<?= $local['id'] ?>" class="card-img" style="display:block;">
    <?php if ($local['foto_capa']): ?>
      <img src="<?= SITE_URL ?>/uploads/locais/<?= h($local['foto_capa']) ?>"
           alt="<?= h(local_nome_publico($local)) ?>" loading="lazy">
    <?php else: ?>
      <div class="card-img-placeholder"><i class="<?= h($local['categoria_icone']) ?>"></i></div>
    <?php endif; ?>
    <div class="card-badges">
      <span class="badge badge-cat"><i class="<?= h($local['categoria_icone']) ?>"></i> <?= h($local['categoria_nome']) ?></span>
      <span class="badge <?= $dif_class ?>"><?= $dif_label ?></span>
    </div>
  </a>
  <div class="card-body">
    <div class="card-region"><i class="fas fa-map-marker-alt"></i> <?= h($local['regiao_nome']) ?></div>
    <h3 class="card-title">
      <a href="<?= SITE_URL ?>/pages/local.php?id=<?= $local['id'] ?>"><?= h(local_nome_publico($local)) ?></a>
    </h3>
    <p class="card-desc"><?= h(local_descricao_publica($local)) ?></p>
    <div class="card-meta">
      <div style="display:flex;align-items:center;gap:.5rem;flex-wrap:wrap;">
        <a href="<?= SITE_URL ?>/pages/perfil.php?id=<?= $local['utilizador_id'] ?>"
           style="color:var(--verde);font-weight:600;font-size:.82rem;">
          @<?= h($local['username']) ?>
        </a>
        <?php if ($_card_user && !$_card_e_proprio): ?>
          <button class="btn-seguir-card"
                  data-id="<?= $local['utilizador_id'] ?>"
                  data-seguindo="<?= $_card_segue ? '1' : '0' ?>"
                  style="background:none;border:1px solid <?= $_card_segue ? 'var(--creme-escuro)' : 'var(--verde)' ?>;
                         color:<?= $_card_segue ? 'var(--texto-muted)' : 'var(--verde)' ?>;
                         border-radius:20px;padding:.1rem .55rem;font-size:.72rem;cursor:pointer;
                         display:inline-flex;align-items:center;gap:.25rem;transition:all .15s;">
            <i class="fas <?= $_card_segue ? 'fa-user-check' : 'fa-user-plus' ?>"></i>
            <?= $_card_segue ? 'A seguir' : 'Seguir' ?>
          </button>
        <?php endif; ?>
      </div>
      <div class="card-meta-stats">
        <span><i class="fas fa-heart"></i> <?= (int)$local['total_likes'] ?></span>
        <span><i class="fas fa-comment"></i> <?= (int)$local['total_comentarios'] ?></span>
      </div>
    </div>
  </div>
</article>