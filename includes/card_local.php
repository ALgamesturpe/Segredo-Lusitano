<?php
// ============================================================
// SEGREDO LUSITANO — Partial: Card de Local
// ============================================================

// Verificar que $local está definido e tem os dados necessários
if (empty($local) || !is_array($local)) {
    return;
}

// Definir valores padrão se faltarem chaves
$local_id = (int)($local['id'] ?? 0);
$local_nome = (string)($local['nome'] ?? 'Sem título');
$local_desc = (string)($local['descricao'] ?? '');
$local_dif = (string)($local['dificuldade'] ?? 'medio');
$local_foto = $local['foto_capa'] ?? null;
$local_categoria_nome = (string)($local['categoria_nome'] ?? 'Sem categoria');
$local_categoria_icone = (string)($local['categoria_icone'] ?? 'fas fa-map-pin');
$local_regiao_nome = (string)($local['regiao_nome'] ?? 'Desconhecida');
$local_username = (string)($local['username'] ?? 'Anónimo');
$local_utilizador_id = (int)($local['utilizador_id'] ?? 0);
$local_total_likes = (int)($local['total_likes'] ?? 0);
$local_total_comentarios = (int)($local['total_comentarios'] ?? 0);

// Validar ID
if ($local_id <= 0) {
    return;
}

$dif_class = [
    'facil'   => 'badge-dif-facil',
    'medio'   => 'badge-dif-medio',
    'dificil' => 'badge-dif-dificil',
][$local_dif] ?? 'badge-dif-medio';

$dif_label = [
    'facil'   => 'Fácil',
    'medio'   => 'Médio',
    'dificil' => 'Difícil',
][$local_dif] ?? 'Médio';

// Verificar se o utilizador autenticado segue o autor deste local
$_card_user = auth_user();
$_card_segue = false;
$_card_e_proprio = $_card_user && $_card_user['id'] == $local_utilizador_id;
if ($_card_user && !$_card_e_proprio && $local_utilizador_id > 0) {
    $__st = db()->prepare('SELECT id FROM seguidores WHERE seguidor_id = ? AND seguido_id = ?');
    $__st->execute([$_card_user['id'], $local_utilizador_id]);
    $_card_segue = (bool)$__st->fetch();
}
?>
<div class="card">
  <a href="<?= SITE_URL ?>/pages/local.php?id=<?= $local_id ?>" class="card-img" style="display:block;">
    <?php if ($local_foto): ?>
      <img src="<?= SITE_URL ?>/uploads/locais/<?= h($local_foto) ?>"
           alt="<?= h($local_nome) ?>" loading="lazy">
    <?php else: ?>
      <div class="card-img-placeholder"><i class="<?= h($local_categoria_icone) ?>"></i></div>
    <?php endif; ?>
    <div class="card-badges">
      <span class="badge badge-cat"><i class="<?= h($local_categoria_icone) ?>"></i> <?= h($local_categoria_nome) ?></span>
      <span class="badge <?= $dif_class ?>"><?= $dif_label ?></span>
    </div>
  </a>
  <div class="card-body">
    <div class="card-region"><i class="fas fa-map-marker-alt"></i> <?= h($local_regiao_nome) ?></div>
    <h3 class="card-title">
      <a href="<?= SITE_URL ?>/pages/local.php?id=<?= $local_id ?>"><?= h($local_nome) ?></a>
    </h3>
    <p class="card-desc"><?= h($local_desc) ?></p>
    <div class="card-meta">
      <div style="display:flex;align-items:center;gap:.5rem;flex-wrap:wrap;">
        <a href="<?= SITE_URL ?>/pages/perfil.php?id=<?= $local_utilizador_id ?>"
           style="color:var(--verde);font-weight:600;font-size:.82rem;">
          @<?= h($local_username) ?>
        </a>
        <?php if ($_card_user && !$_card_e_proprio && empty($ocultar_btn_seguir)): ?>
          <button class="btn-seguir-card"
                  data-id="<?= $local_utilizador_id ?>"
                  data-seguindo="<?= $_card_segue ? '1' : '0' ?>"
                  style="background:none;border:1px solid <?= $_card_segue ? 'var(--creme-escuro)' : 'var(--verde)' ?>;
                         color:<?= $_card_segue ? 'var(--texto-muted)' : 'var(--verde)' ?>;
                         border-radius:50px;padding:.1rem .55rem;font-size:.72rem;cursor:pointer;
                         display:inline-flex;align-items:center;gap:.25rem;transition:all .15s;">
            <i class="fas <?= $_card_segue ? 'fa-user-check' : 'fa-user-plus' ?>"></i>
            <?= $_card_segue ? 'A seguir' : 'Seguir' ?>
          </button>
        <?php endif; ?>
      </div>
      <div class="card-meta-stats">
        <span><i class="fas fa-heart"></i> <?= $local_total_likes ?></span>
        <span><i class="fas fa-comment"></i> <?= $local_total_comentarios ?></span>
      </div>
    </div>
  </div>
</div>