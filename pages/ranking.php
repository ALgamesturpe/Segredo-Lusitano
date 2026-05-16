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
  <div class="container" style="max-width:90%;">
    <div class="section-header">
      <h2>Ranking de Exploradores</h2>
    </div>
    <div>
      <div class="row">
        <div class="col-12 col-lg-3"> <!--das 12 divisões estou a usar 6 (do lado esquerdo), que é o "col-6"-->
          <!-- Sistema de pontos -->
          <div style="background:var(--branco); border-radius:var(--radius-lg); padding:1.75rem; margin-top:2rem;">
            <h3 style="margin-bottom:1.25rem;"><i class="fas fa-star"></i> Como Ganhar Pontos</h3>
            <div style="display:flex; flex-direction:column; gap:1rem;">
              <div style="padding:1rem; background:var(--creme); border-radius:var(--radius); text-align:center;">
                <div style="font-size:1.8rem; color:var(--dourado); font-family:'Playfair Display',serif;"><?= PONTOS_LOCAL ?></div>
                <div style="font-size:.85rem; color:var(--texto-muted);">por Local Aprovado</div>
              </div>
              <div style="padding:1rem; background:var(--creme); border-radius:var(--radius); text-align:center;">
                <div style="font-size:1.8rem; color:var(--dourado); font-family:'Playfair Display',serif;"><?= PONTOS_COMENTARIO ?></div>
                <div style="font-size:.85rem; color:var(--texto-muted);">por Comentário</div>
              </div>
              <div style="padding:1rem; background:var(--creme); border-radius:var(--radius); text-align:center;">
                <div style="font-size:1.8rem; color:var(--dourado); font-family:'Playfair Display',serif;"><?= PONTOS_LIKE ?></div>
                <div style="font-size:.85rem; color:var(--texto-muted);">por Like Recebido</div>
              </div>
            </div>
          </div>
        </div>
        <div class="col-12 col-lg-6">
          <div style="background:var(--branco); border-radius:var(--radius-lg); overflow:hidden; margin-top:2rem; margin-left:auto; margin-right:auto;">
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
                      <?php else: echo ($i+1) . 'º'; ?>
                      <?php endif; ?>
                    </span>
                  </td>
                  <td>
                    <div class="rank-user">
                      <div class="rank-avatar">
                        <?php if (!empty($u['avatar'])): ?>
                          <img src="<?= SITE_URL ?>/uploads/locais/<?= h($u['avatar']) ?>" alt="<?= h($u['nome']) ?>">
                        <?php else: ?>
                          <?= mb_strtoupper(mb_substr($u['username'],0,1)) ?>
                        <?php endif; ?>
                      </div>
                      <div class="rank-user-info">
                        <div style="font-weight:600;"><?= h($u['nome']) ?></div>
                        <a href="<?= SITE_URL ?>/pages/perfil.php?id=<?= $u['id'] ?>"
                          style="color:var(--verde); font-size:.82rem;"><?= h($u['username']) ?></a>
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
        </div>
        <div class="col-12 col-lg-3"></div>
      </div>
    </div>
  </div>
</section>
</div>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
