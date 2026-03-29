<?php
// SEGREDO LUSITANO — Feed de Seguidos
require_once dirname(__DIR__) . '/includes/functions.php';
require_login();

$user = auth_user();
$page_title = 'Feed';

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
     WHERE s.seguidor_id = ? AND l.estado = "aprovado" AND l.bloqueado = 0
     ORDER BY l.criado_em DESC
     LIMIT 48'
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
    <div class="section-header">
      <h2><i class="fas fa-users"></i> Publicações</h2>
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
        <p>Explora perfis e segue exploradores para ver as suas publicações aqui.</p>
        <a href="<?= SITE_URL ?>/pages/explorar.php" class="btn btn-sm btn-primary" style="margin-top:1rem; display:inline-flex; align-items:center; gap:.4rem;">
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

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>