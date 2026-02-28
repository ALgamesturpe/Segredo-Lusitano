<?php
// ============================================================
// SEGREDO LUSITANO — Perfil de Utilizador
// ============================================================
require_once dirname(__DIR__) . '/includes/functions.php';

$user_auth = auth_user();
$id = (int)($_GET['id'] ?? ($user_auth['id'] ?? 0));

if (!$id) { header('Location: ' . SITE_URL . '/pages/login.php'); exit; }

$st = db()->prepare('SELECT * FROM utilizadores WHERE id = ? AND ativo = 1');
$st->execute([$id]);
$perfil = $st->fetch();

if (!$perfil) { header('Location: ' . SITE_URL . '/index.php'); exit; }

// Locais do utilizador
$st2 = db()->prepare(
    'SELECT l.*, c.nome AS categoria_nome, c.icone AS categoria_icone, r.nome AS regiao_nome,
            u.username, u.nome AS autor_nome,
            (SELECT COUNT(*) FROM likes WHERE local_id = l.id) AS total_likes,
            (SELECT COUNT(*) FROM comentarios WHERE local_id = l.id) AS total_comentarios
     FROM locais l
     JOIN categorias c ON c.id = l.categoria_id
     JOIN regioes r    ON r.id = l.regiao_id
     JOIN utilizadores u ON u.id = l.utilizador_id
     WHERE l.utilizador_id = ?' . (($user_auth && ($user_auth['id'] == $id || is_admin())) ? '' : ' AND l.estado = "aprovado"') . '
     ORDER BY l.criado_em DESC'
);
$st2->execute([$id]);
$locais_perfil = $st2->fetchAll();

$total_likes = 0;
foreach ($locais_perfil as $lp) $total_likes += $lp['total_likes'];

// Rank
$st3 = db()->prepare('SELECT COUNT(*) + 1 FROM utilizadores WHERE pontos > ? AND ativo = 1 AND role = "user"');
$st3->execute([$perfil['pontos']]);
$rank_pos = (int)$st3->fetchColumn();

// Apagar conta (só o próprio)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apagar_conta']) && $user_auth && $user_auth['id'] == $id) {
    db()->prepare('UPDATE utilizadores SET ativo = 0 WHERE id = ?')->execute([$id]);
    logout();
}

$is_own = $user_auth && $user_auth['id'] == $id;
$page_title = $perfil['nome'];
include dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-content">

<!-- HERO PERFIL -->
<div class="perfil-hero">
  <div class="perfil-avatar">
    <?php if ($perfil['avatar']): ?>
      <img src="<?= SITE_URL ?>/uploads/locais/<?= h($perfil['avatar']) ?>" alt="">
    <?php else: ?>
      <?= mb_strtoupper(mb_substr($perfil['username'],0,1)) ?>
    <?php endif; ?>
  </div>
  <h1 class="perfil-nome"><?= h($perfil['nome']) ?></h1>
  <p class="perfil-username">@<?= h($perfil['username']) ?> &middot; Explorador #<?= $rank_pos ?></p>
  <?php if ($perfil['bio']): ?>
    <p class="perfil-bio"><?= h($perfil['bio']) ?></p>
  <?php endif; ?>
  <div class="perfil-stats">
    <div class="stat-item"><span class="stat-num"><?= count($locais_perfil) ?></span><span class="stat-label">Locais</span></div>
    <div class="stat-item"><span class="stat-num"><?= $total_likes ?></span><span class="stat-label">Likes Recebidos</span></div>
    <div class="stat-item"><span class="stat-num"><?= number_format($perfil['pontos']) ?></span><span class="stat-label">Pontos</span></div>
    <div class="stat-item"><span class="stat-num">#<?= $rank_pos ?></span><span class="stat-label">Ranking</span></div>
  </div>
</div>

<!-- LOCAIS -->
<section class="section">
  <div class="container">
    <h2 style="margin-bottom:1.5rem;">
      <?= $is_own ? 'Os Meus Locais' : 'Locais de ' . h($perfil['nome']) ?>
    </h2>

    <?php if ($locais_perfil): ?>
      <div class="cards-grid">
        <?php foreach ($locais_perfil as $local):
          // mostrar badge de estado se for o próprio ou admin
          if ($is_own || is_admin()): ?>
            <article class="card" style="<?= $local['estado'] !== 'aprovado' ? 'opacity:.75' : '' ?>">
              <a href="<?= SITE_URL ?>/pages/local.php?id=<?= $local['id'] ?>" class="card-img" style="display:block;">
                <?php if ($local['foto_capa']): ?>
                  <img src="<?= SITE_URL ?>/uploads/locais/<?= h($local['foto_capa']) ?>" alt="<?= h($local['nome']) ?>">
                <?php else: ?>
                  <div class="card-img-placeholder"><i class="<?= h($local['categoria_icone']) ?>"></i></div>
                <?php endif; ?>
                <div class="card-badges">
                  <span class="badge badge-<?= $local['estado'] ?>"><?= ucfirst($local['estado']) ?></span>
                </div>
              </a>
              <div class="card-body">
                <h3 class="card-title"><a href="<?= SITE_URL ?>/pages/local.php?id=<?= $local['id'] ?>"><?= h($local['nome']) ?></a></h3>
                <div class="card-meta">
                  <span style="font-size:.82rem;color:var(--texto-muted);"><?= h($local['regiao_nome']) ?></span>
                  <div class="card-meta-stats">
                    <span><i class="fas fa-heart"></i> <?= $local['total_likes'] ?></span>
                  </div>
                </div>
              </div>
            </article>
          <?php else: ?>
            <?php include dirname(__DIR__) . '/includes/card_local.php'; ?>
          <?php endif;
        endforeach; ?>
      </div>
    <?php else: ?>
      <div class="empty-state">
        <i class="fas fa-map"></i>
        <h3>Ainda sem locais</h3>
        <?php if ($is_own): ?>
          <a href="<?= SITE_URL ?>/pages/local_novo.php" class="btn btn-primary" style="margin-top:1rem;">Partilhar o Primeiro Local</a>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <!-- Apagar conta -->
    <?php if ($is_own): ?>
    <div style="margin-top:4rem; padding:1.5rem; border:1.5px solid #e74c3c; border-radius:var(--radius); max-width:500px;">
      <h3 style="color:#c0392b; margin-bottom:.5rem;"><i class="fas fa-exclamation-triangle"></i> Zona de Perigo</h3>
      <p style="font-size:.9rem; color:var(--texto-muted); margin-bottom:1rem;">Apagar a tua conta é irreversível. Todos os teus dados serão removidos.</p>
      <form method="POST" onsubmit="return confirm('Tens a certeza absoluta? Esta ação não pode ser desfeita!');">
        <input type="hidden" name="apagar_conta" value="1">
        <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i> Apagar a Minha Conta</button>
      </form>
    </div>
    <?php endif; ?>
  </div>
</section>
</div>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
