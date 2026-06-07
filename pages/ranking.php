<?php
// ============================================================
// SEGREDO LUSITANO — Ranking de Exploradores
// ============================================================
require_once dirname(__DIR__) . '/includes/functions.php';

$page_title  = 'Ranking';
$ranking     = get_ranking(50);
$user_auth   = auth_user();

$minha_posicao   = null;
$meus_pontos     = null;
$pontos_proximo  = null;

if ($user_auth && $user_auth['role'] === 'user') {
    $meus_pontos = (int)$user_auth['pontos'];

    $st_pos = db()->prepare(
        'SELECT COUNT(*) FROM utilizadores WHERE pontos > ? AND ativo = 1 AND role = "user"'
    );
    $st_pos->execute([$meus_pontos]);
    $minha_posicao = (int)$st_pos->fetchColumn() + 1;

    // Pontos do utilizador imediatamente acima (para mostrar "faltam X pontos")
    $st_prox = db()->prepare(
        'SELECT pontos FROM utilizadores WHERE pontos > ? AND ativo = 1 AND role = "user" ORDER BY pontos ASC LIMIT 1'
    );
    $st_prox->execute([$meus_pontos]);
    $row_prox = $st_prox->fetchColumn();
    $pontos_proximo = $row_prox !== false ? (int)$row_prox : null;
}

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
        <div class="col-12 col-lg-3">
          <!-- Sistema de pontos -->
          <div style="background:var(--branco); border-radius:var(--radius-lg); padding:1.75rem; margin-top:2rem;">
            <h3 style="margin-bottom:1.25rem;"><i class="fas fa-star"></i> Como Ganhar Pontos</h3>
            <div style="display:flex; flex-direction:column; gap:1rem;">
              <div style="padding:1rem; background:var(--creme); border-radius:var(--radius); text-align:center;">
                <div style="font-size:1.8rem; color:var(--dourado); font-family:'Playfair Display',serif;"><?= PONTOS_LOCAL ?></div>
                <div style="font-size:.85rem; color:var(--texto-muted);">por Local Aprovado</div>
              </div>
              <div style="padding:1rem; background:var(--creme); border-radius:var(--radius); text-align:center;">
                <div style="font-size:1.8rem; color:var(--dourado); font-family:'Playfair Display',serif;"><?= PONTOS_LIKE ?></div>
                <div style="font-size:.85rem; color:var(--texto-muted);">por Like Recebido</div>
              </div>
              <div style="padding:1rem; background:var(--creme); border-radius:var(--radius); text-align:center;">
                <div style="font-size:1.8rem; color:var(--dourado); font-family:'Playfair Display',serif;"><?= PONTOS_COMENTARIO ?></div>
                <div style="font-size:.85rem; color:var(--texto-muted);">por Comentário Recebido</div>
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
                  <th>Coment. Recebidos</th>
                  <th>Pontos</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($ranking as $i => $u): ?>
                <tr <?= ($user_auth && (int)$u['id'] === (int)$user_auth['id']) ? 'style="background:rgba(201,168,76,.12); outline:2px solid var(--dourado);"' : '' ?>>
                  <td>
                    <span class="rank-pos rank-<?= $i+1 <= 3 ? ($i+1) : '' ?>">
                      <?php if ($i === 0): ?>1º
                      <?php elseif ($i === 1): ?>2º
                      <?php elseif ($i === 2): ?>3º
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
        <div class="col-12 col-lg-3">
          <?php if ($minha_posicao !== null): ?>
          <div style="background:var(--branco); border-radius:var(--radius-lg); padding:1.75rem; margin-top:2rem; border:2px solid var(--dourado);">
            <h3 style="margin-bottom:1.25rem;"><i class="fas fa-trophy"></i> A tua posição</h3>
            <?php if ($meus_pontos > 0): ?>
            <div style="text-align:center; padding:1rem; background:var(--creme); border-radius:var(--radius); margin-bottom:.75rem;">
              <div style="font-size:2.5rem; font-weight:700; color:var(--dourado); font-family:'Playfair Display',serif;"><?= $minha_posicao ?>º</div>
              <div style="font-size:.85rem; color:var(--texto-muted);">entre todos os exploradores</div>
            </div>
            <div style="text-align:center; padding:.75rem; background:var(--creme); border-radius:var(--radius); margin-bottom:.75rem;">
              <div style="font-size:1.4rem; font-weight:700; color:var(--verde-escuro);"><?= number_format($meus_pontos) ?></div>
              <div style="font-size:.85rem; color:var(--texto-muted);">pontos</div>
            </div>
            <?php if ($pontos_proximo !== null): ?>
            <div style="font-size:.8rem; color:var(--texto-muted); text-align:center;">
              Faltam <strong style="color:var(--verde-escuro);"><?= number_format($pontos_proximo - $meus_pontos) ?> pontos</strong> para subires uma posição
            </div>
            <?php else: ?>
            <div style="font-size:.8rem; color:var(--verde); text-align:center; font-weight:600;">
              <i class="fas fa-crown"></i> Estás no topo!
            </div>
            <?php endif; ?>
            <?php else: ?>
            <div style="text-align:center; padding:1.25rem; background:var(--creme); border-radius:var(--radius);">
              <i class="fas fa-star" style="font-size:1.8rem; color:var(--dourado); opacity:.4; display:block; margin-bottom:.6rem;"></i>
              <p style="font-size:.88rem; color:var(--texto-muted); margin:0; line-height:1.6;">
                Ganha pontos para entrares no ranking!
              </p>
              <a href="<?= SITE_URL ?>/pages/local_novo.php" class="btn btn-sm btn-primary" style="margin-top:.85rem;">
                <i class="fas fa-plus"></i> Partilhar local
              </a>
            </div>
            <?php endif; ?>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</section>
</div>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
