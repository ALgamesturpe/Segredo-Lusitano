<?php
// ============================================================
// SEGREDO LUSITANO — Partial: Card de Local
// Variável esperada: $local (array)
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
?>
<article class="card">
  <a href="<?= SITE_URL ?>/pages/local.php?id=<?= $local['id'] ?>" class="card-img" style="display:block;">
    <?php if ($local['foto_capa']): ?>
      <img src="<?= SITE_URL ?>/uploads/locais/<?= h($local['foto_capa']) ?>"
           alt="<?= h($local['nome']) ?>" loading="lazy">
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
      <a href="<?= SITE_URL ?>/pages/local.php?id=<?= $local['id'] ?>"><?= h($local['nome']) ?></a>
    </h3>
    <p class="card-desc"><?= h($local['descricao']) ?></p>
    <div class="card-meta">
      <span style="font-size:.82rem;">
        <a href="<?= SITE_URL ?>/pages/perfil.php?id=<?= $local['utilizador_id'] ?>" style="color:var(--verde);font-weight:600;">
          @<?= h($local['username']) ?>
        </a>
      </span>
      <div class="card-meta-stats">
        <span><i class="fas fa-heart"></i> <?= (int)$local['total_likes'] ?></span>
        <span><i class="fas fa-comment"></i> <?= (int)$local['total_comentarios'] ?></span>
      </div>
    </div>
  </div>
</article>
