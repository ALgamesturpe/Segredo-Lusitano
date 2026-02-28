<?php
// ============================================================
// SEGREDO LUSITANO — Página Inicial
// ============================================================
require_once __DIR__ . '/includes/functions.php';

$page_title = 'Início';
$locais_destaque = get_locais(['ordem' => 'likes'], 6);
$stats_locais = (int)(db()->query('SELECT COUNT(*) FROM locais WHERE estado="aprovado"')->fetchColumn());
$stats_users  = (int)(db()->query('SELECT COUNT(*) FROM utilizadores WHERE ativo=1 AND role="user"')->fetchColumn());
$stats_regioes = 7;

include __DIR__ . '/includes/header.php';
?>

<main>
  <!-- HERO -->
  <section class="hero" style="background: url('<?= SITE_URL ?>/assets/images/hero_bg.jpg?v=15') center/cover no-repeat;">
    <div class="hero-content">
      <div class="hero-logo-wrap">
        <img src="<?= SITE_URL ?>/assets/images/logo_icon.png" alt="Segredo Lusitano" class="hero-logo-img" style="height:180px;width:180px;max-width:180px;object-fit:contain;display:block;margin:0 auto;">
      </div>
      <h1>O Portugal que os<br><em>mapas não mostram</em></h1>
      <p>Locais secretos, trilhos perdidos, aldeias esquecidas — partilhados por quem os conhece de verdade.</p>
      <div class="hero-actions">
        <a href="<?= SITE_URL ?>/pages/explorar.php" class="btn btn-primary"><i class="fas fa-compass"></i> Explorar Locais</a>
        <a href="<?= SITE_URL ?>/pages/mapa.php"     class="btn btn-outline"><i class="fas fa-map"></i> Ver no Mapa</a>
      </div>
    </div>
  </section>

  <!-- STATS -->
  <section class="section section-dark">
    <div class="container">
      <div class="stats-row">
        <div class="stat">
          <span class="num"><?= $stats_locais ?>+</span>
          <span class="lbl">Locais Secretos</span>
        </div>
        <div class="stat">
          <span class="num"><?= $stats_users ?>+</span>
          <span class="lbl">Exploradores</span>
        </div>
        <div class="stat">
          <span class="num"><?= $stats_regioes ?></span>
          <span class="lbl">Regiões</span>
        </div>
        <div class="stat">
          <span class="num">∞</span>
          <span class="lbl">Descobertas</span>
        </div>
      </div>
    </div>
  </section>

  <!-- LOCAIS EM DESTAQUE -->
  <section class="section">
    <div class="container">
      <div class="section-header">
        <span class="label">Mais Populares</span>
        <h2>Locais em Destaque</h2>
        <p>Os segredos mais amados pela nossa comunidade de exploradores.</p>
      </div>

      <?php if ($locais_destaque): ?>
      <div class="cards-grid">
        <?php foreach ($locais_destaque as $local): ?>
          <?php include __DIR__ . '/includes/card_local.php'; ?>
        <?php endforeach; ?>
      </div>
      <div style="text-align:center; margin-top:2.5rem;">
        <a href="<?= SITE_URL ?>/pages/explorar.php" class="btn btn-verde">Ver Todos os Locais <i class="fas fa-arrow-right"></i></a>
      </div>
      <?php else: ?>
        <div class="empty-state">
          <i class="fas fa-map"></i>
          <h3>Ainda sem locais aprovados</h3>
          <p>Sê o primeiro a partilhar um segredo lusitano!</p>
          <a href="<?= SITE_URL ?>/pages/local_novo.php" class="btn btn-primary" style="margin-top:1rem;">Partilhar Local</a>
        </div>
      <?php endif; ?>
    </div>
  </section>

  <!-- COMO FUNCIONA -->
  <section class="section section-alt">
    <div class="container">
      <div class="section-header">
        <span class="label">Como Funciona</span>
        <h2>Simples como um trilho</h2>
        <p>Em três passos descobres e partilhas os melhores segredos de Portugal.</p>
      </div>
      <div class="steps-grid">
        <div class="step-card">
          <div class="step-icon"><i class="fas fa-user-plus"></i></div>
          <h3>1. Cria a tua conta</h3>
          <p>Regista-te gratuitamente e junta-te à comunidade de exploradores lusitanos.</p>
        </div>
        <div class="step-card">
          <div class="step-icon"><i class="fas fa-camera"></i></div>
          <h3>2. Partilha um Local</h3>
          <p>Fotografa, descreve e marca no mapa o teu segredo favorito de Portugal.</p>
        </div>
        <div class="step-card">
          <div class="step-icon"><i class="fas fa-trophy"></i></div>
          <h3>3. Ganha Pontos</h3>
          <p>Cada publicação, comentário e like conta para o teu ranking de explorador.</p>
        </div>
        <div class="step-card">
          <div class="step-icon"><i class="fas fa-compass"></i></div>
          <h3>4. Descobre Portugal</h3>
          <p>Usa o mapa interativo para encontrar segredos perto de ti ou onde viajares.</p>
        </div>
      </div>
    </div>
  </section>

  <!-- CTA -->
  <section class="section section-dark" style="text-align:center;">
    <div class="container" style="max-width:640px;">
      <div class="section-header">
        <span class="label">Começa já</span>
        <h2>Tens um segredo lusitano?</h2>
        <p>Partilha-o com milhares de viajantes curiosos e ajuda a preservar o que torna Portugal único.</p>
      </div>
      <?php if (!auth_user()): ?>
      <a href="<?= SITE_URL ?>/pages/registo.php" class="btn btn-primary" style="font-size:1.05rem;">
        <i class="fas fa-rocket"></i> Criar Conta Grátis
      </a>
      <?php else: ?>
      <a href="<?= SITE_URL ?>/pages/local_novo.php" class="btn btn-primary" style="font-size:1.05rem;">
        <i class="fas fa-plus-circle"></i> Partilhar Novo Local
      </a>
      <?php endif; ?>
    </div>
  </section>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
