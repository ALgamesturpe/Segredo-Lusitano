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
$local_total_guardados = (int)($local['total_guardados'] ?? 0);

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

$_card_user = auth_user();
// Guardados carregados uma única vez por request (evita N+1 queries)
if (!isset($GLOBALS['_card_guardados_ids'])) {
    $GLOBALS['_card_guardados_ids'] = [];
    if ($_card_user) {
        _migrar_favoritos();
        $__stF = db()->prepare('SELECT local_id FROM favoritos WHERE utilizador_id = ?');
        $__stF->execute([$_card_user['id']]);
        $GLOBALS['_card_guardados_ids'] = array_column($__stF->fetchAll(), 'local_id');
    }
}
$_card_guardou = in_array($local_id, $GLOBALS['_card_guardados_ids']);
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
      </div>
      <div class="card-meta-stats">
        <span><i class="fas fa-heart"></i> <?= $local_total_likes ?></span>
        <span><i class="fas fa-comment"></i> <?= $local_total_comentarios ?></span>
        <?php if ($_card_user): ?>
          <button class="btn-guardar-card"
                  data-id="<?= $local_id ?>"
                  data-guardou="<?= $_card_guardou ? '1' : '0' ?>"
                  onclick="toggleGuardar(this)"
                  title="<?= $_card_guardou ? 'Remover dos guardados' : 'Guardar local' ?>"
                  style="background:none;border:none;cursor:pointer;padding:0;display:inline-flex;align-items:center;gap:.25rem;color:<?= $_card_guardou ? 'var(--dourado)' : 'var(--texto-muted)' ?>;font-size:.82rem;">
            <i class="<?= $_card_guardou ? 'fas' : 'far' ?> fa-bookmark"></i>
            <span class="guardados-count"><?= $local_total_guardados ?></span>
          </button>
        <?php else: ?>
          <span style="color:var(--texto-muted);font-size:.82rem;">
            <i class="far fa-bookmark"></i> <?= $local_total_guardados ?>
          </span>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>