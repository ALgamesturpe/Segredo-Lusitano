<?php
// ============================================================
// SEGREDO LUSITANO — Ranking de Exploradores
// ============================================================
require_once dirname(__DIR__) . '/includes/functions.php';

$page_title = 'Ranking';
$ranking    = get_ranking(50);
include dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-content">
<section class="section">
  <div class="container" style="max-width:800px;">
    <div class="section-header">
      <span class="label">Comunidade</span>
      <h2>Ranking de Exploradores</h2>
      <p>Os descobridores mais ativos do Portugal secreto.</p>
    </div>

    <div style="background:var(--branco); border-radius:var(--radius-lg); overflow:hidden; box-shadow:var(--sombra-md);">
      <table class="ranking-table">
        <thead>
          <tr>
            <th>#</th>
            <th>Explorador</th>
            <th>Locais</th>
            <th>Comentários</th>
            <th>Pontos</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($ranking as $i => $u): ?>
          <tr>
            <td>
              <span class="rank-pos rank-<?= $i+1 <= 3 ? ($i+1) : '' ?>">
                <?php if ($i === 0): ?>🥇
                <?php elseif ($i === 1): ?>🥈
                <?php elseif ($i === 2): ?>🥉
                <?php else: echo '#' . ($i+1); ?>
                <?php endif; ?>
              </span>
            </td>
            <td>
              <div class="rank-user">
                <div class="rank-avatar">
                  <?= mb_strtoupper(mb_substr($u['username'],0,1)) ?>
                </div>
                <div>
                  <div style="font-weight:600;"><?= h($u['nome']) ?></div>
                  <a href="<?= SITE_URL ?>/pages/perfil.php?id=<?= $u['id'] ?>"
                     style="color:var(--verde); font-size:.82rem;">@<?= h($u['username']) ?></a>
                </div>
              </div>
            </td>
            <td><?= (int)$u['total_locais'] ?></td>
            <td><?= (int)$u['total_comentarios'] ?></td>
            <td><span class="rank-pontos"><?= number_format($u['pontos']) ?></span></td>
          </tr>
          <?php endforeach; ?>
          <?php if (!$ranking): ?>
          <tr><td colspan="5" style="text-align:center; color:var(--texto-muted); padding:3rem;">Ainda sem dados de ranking.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Sistema de pontos -->
    <div style="background:var(--branco); border-radius:var(--radius-lg); padding:1.75rem; box-shadow:var(--sombra-sm); margin-top:2rem;">
      <h3 style="margin-bottom:1.25rem;"><i class="fas fa-star"></i> Como Ganhar Pontos</h3>
      <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(150px,1fr)); gap:1rem; text-align:center;">
        <div style="padding:1rem; background:var(--creme); border-radius:var(--radius);">
          <div style="font-size:1.8rem; color:var(--dourado); font-family:'Playfair Display',serif;"><?= PONTOS_LOCAL ?></div>
          <div style="font-size:.85rem; color:var(--texto-muted);">por Local Aprovado</div>
        </div>
        <div style="padding:1rem; background:var(--creme); border-radius:var(--radius);">
          <div style="font-size:1.8rem; color:var(--dourado); font-family:'Playfair Display',serif;"><?= PONTOS_COMENTARIO ?></div>
          <div style="font-size:.85rem; color:var(--texto-muted);">por Comentário</div>
        </div>
        <div style="padding:1rem; background:var(--creme); border-radius:var(--radius);">
          <div style="font-size:1.8rem; color:var(--dourado); font-family:'Playfair Display',serif;"><?= PONTOS_LIKE ?></div>
          <div style="font-size:.85rem; color:var(--texto-muted);">por Like Dado</div>
        </div>
      </div>
    </div>
  </div>
</section>
</div>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
