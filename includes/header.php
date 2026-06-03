<?php
// ============================================================
// SEGREDO LUSITANO - Cabeçalho (include)
// ============================================================
require_once dirname(__DIR__) . '/includes/auth.php';
$user = auth_user();
$page_title = $page_title ?? SITE_NAME;

// Contador de mensagens não lidas
$nao_lidas_msg = 0;
if ($user) {
    $stNL = db()->prepare('SELECT COUNT(*) FROM mensagens WHERE destinatario_id=? AND lida=0');
    $stNL->execute([$user['id']]);
    $nao_lidas_msg = (int)$stNL->fetchColumn();
}
// Contador de notificações não lidas
$nao_lidas_notif = 0;
if ($user) {
    $nao_lidas_notif = count_notificacoes_nao_lidas($user['id']);
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= h($page_title) ?> &mdash; <?= SITE_NAME ?></title>
<meta name="description" content="<?= h($og_description ?? 'Descobre os segredos escondidos de Portugal.') ?>">

<!-- Open Graph (WhatsApp, Telegram, Facebook, etc.) -->
<meta property="og:site_name"   content="Segredo Lusitano">
<meta property="og:type"        content="website">
<meta property="og:title"       content="<?= h($og_title ?? ($page_title . ' — Segredo Lusitano')) ?>">
<meta property="og:description" content="<?= h($og_description ?? 'Descobre os segredos escondidos de Portugal.') ?>">
<meta property="og:url"         content="<?= h($og_url ?? SITE_URL) ?>">
<meta property="og:image"       content="<?= h($og_image ?? SITE_URL . '/assets/images/logo.png') ?>">
<meta name="twitter:card"       content="summary_large_image">
<meta name="twitter:image"      content="<?= h($og_image ?? SITE_URL . '/assets/images/logo.png') ?>">

<!-- Favicon -->
<link rel="icon" type="image/x-icon" href="<?= SITE_URL ?>/favicon.ico">
<link rel="icon" type="image/png" sizes="32x32" href="<?= SITE_URL ?>/assets/images/favicon-32.png">

<!-- PWA -->
<link rel="manifest" href="<?= SITE_URL ?>/manifest.php">
<meta name="theme-color" content="#1a3a2a">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="Segredo">
<link rel="apple-touch-icon" href="<?= SITE_URL ?>/assets/images/logo_icon.png">

<!-- Fontes -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,700;1,400&family=Outfit:wght@400;600&family=Inter:wght@300;700&display=swap" rel="stylesheet">

<!-- Ícones (só solid + regular + brands, sem light/thin/duotone que são pro) -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/fontawesome.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/solid.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/regular.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/brands.min.css">

<?php if ($carregar_leaflet ?? false): ?>
<!-- Leaflet -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<?php endif; ?>

<!-- Bootstrap (base) -->
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/bootstrap.min.css?v=<?= filemtime(dirname(__DIR__).'/assets/css/bootstrap.min.css') ?>">

<!-- CSS próprio (sobrepõe Bootstrap) -->
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css?v=<?= filemtime(dirname(__DIR__).'/assets/css/style.css') ?>">

<?= $extra_head ?? '' ?>
<script>const SITE_URL = "<?= SITE_URL ?>"; const IS_LOGGED_IN = <?= $user ? 'true' : 'false' ?>;</script>
</head>
<body>

<nav class="navbar" id="navbar">
  <div class="nav-inner">

    <a href="<?= SITE_URL ?>/index.php" class="nav-logo">
      <img src="<?= SITE_URL ?>/assets/images/logo_icon.png" alt="Segredo Lusitano" class="nav-logo-img" style="height:42px;width:42px;max-height:42px;max-width:42px;object-fit:contain;display:block;">
      <span style="font-family:'Playfair Display',serif;color:var(--creme);line-height:1.2;font-size:.9rem;">Segredo<br><strong style="color:var(--dourado);font-size:1rem;">Lusitano</strong></span>
    </a>

    <button class="hamburger" id="hamburger" aria-label="Menu">
      <span></span><span></span><span></span>
    </button>

    <ul class="nav-links" id="nav-links">
      <li><a href="<?= SITE_URL ?>/pages/explorar.php"><i class="fa-solid fa-earth-americas"></i> Explorar</a></li>
      <li><a href="<?= SITE_URL ?>/pages/local_novo.php"><i class="fas fa-plus-circle"></i> Partilhar Local</a></li>
      <li><a href="<?= SITE_URL ?>/pages/mapa.php"><i class="fas fa-map"></i> Mapa</a></li>
      <li><a href="<?= SITE_URL ?>/pages/ranking.php"><i class="fas fa-trophy"></i> Ranking</a></li>
      <li>
        <?php if ($user): ?>
          <a href="<?= SITE_URL ?>/pages/feed.php"><i class="fas fa-users"></i> Amigos</a>
        <?php else: ?>
          <a href="#" onclick="mostrarAvisoLogin('Inicia sessão para veres os teus amigos e o teu feed.', '<?= SITE_URL ?>/pages/login.php'); return false;">
            <i class="fas fa-users"></i> Amigos
          </a>
        <?php endif; ?>
      </li>
      <?php if ($user): ?>
      <li>
        <a href="<?= SITE_URL ?>/pages/notificacoes.php" style="position:relative;">
          <i class="fas fa-bell"></i> Notificações
          <?php if ($nao_lidas_notif > 0): ?>
            <span id="notif-badge" style="position:absolute;top:-6px;right:-8px;background:#e74c3c;color:#fff;border-radius:0;padding:.1rem .4rem;font-size:.7rem;font-weight:700;line-height:1.4;"><?= $nao_lidas_notif ?></span>
          <?php endif; ?>
        </a>
      </li>
      <?php endif; ?>
      <li>
        <a href="<?= $user ? SITE_URL . '/pages/mensagens.php' : '#' ?>"
           <?= !$user ? 'onclick="mostrarAvisoLogin(\'Inicia sessão para acederes às tuas mensagens.\', \'' . SITE_URL . '/pages/login.php\'); return false;"' : '' ?>
           style="position:relative;">
          <i class="fas fa-comments"></i> Mensagens
          <?php if ($nao_lidas_msg > 0): ?>
            <span id="msg-badge" style="position:absolute;top:-6px;right:-8px;background:#e74c3c;color:#fff;
                   border-radius:0;padding:.1rem .4rem;font-size:.7rem;font-weight:700;line-height:1.4;">
              <?= $nao_lidas_msg ?>
            </span>
          <?php else: ?>
            <span id="msg-badge" style="position:absolute;top:-6px;right:-8px;background:#e74c3c;color:#fff;
                   border-radius:0;padding:.1rem .4rem;font-size:.7rem;font-weight:700;line-height:1.4;display:none;">
              0
            </span>
          <?php endif; ?>
        </a>
      </li>

      <?php if ($user): ?>
        <?php if (is_admin()): ?>
        <li><a href="<?= SITE_URL ?>/admin/index.php"><i class="fas fa-shield-alt"></i> Administração</a></li>
        <?php endif; ?>
        <li>
          <a href="<?= SITE_URL ?>/pages/perfil.php" class="nav-profile-link">
            <?php if ($user['avatar']): ?>
              <img src="<?= SITE_URL ?>/uploads/locais/<?= h($user['avatar']) ?>" alt="" class="nav-avatar">
            <?php else: ?>
              <span class="nav-avatar-placeholder"><?= mb_strtoupper(mb_substr($user['username'],0,1)) ?></span>
            <?php endif; ?>
            <?= h($user['username']) ?>
          </a>
        </li>
        <li>
          <a href="<?= SITE_URL ?>/pages/logout.php" class="btn-nav-sair">
            <i class="fas fa-sign-out-alt"></i> Sair
          </a>
        </li>
      <?php else: ?>
        <li><a href="<?= SITE_URL ?>/pages/login.php" class="btn-nav-login">Entrar</a></li>
        <li><a href="<?= SITE_URL ?>/pages/registo.php" class="btn-nav-register">Registar</a></li>
      <?php endif; ?>
    </ul>
  </div>
</nav>

<?php
$flash_success = flash('success');
$flash_error   = flash('error');
if ($flash_success): ?>
<div class="flash flash-success"><i class="fas fa-check-circle"></i> <?= h($flash_success) ?></div>
<?php endif; ?>
<?php if ($flash_error): ?>
<div class="flash flash-error"><i class="fas fa-exclamation-circle"></i> <?= h($flash_error) ?></div>
<?php endif;

// Atualizar badge de mensagens a cada 30 segundos
if ($user): ?>
<script>
setInterval(async () => {
  const res = await fetch(`${SITE_URL}/pages/mensagens_api.php?acao=nao_lidas`);
  const data = await res.json();
  const badge = document.getElementById('msg-badge');
  if (!badge) return;
  if (data.total > 0) {
    badge.textContent = data.total;
    badge.style.display = '';
  } else {
    badge.style.display = 'none';
  }
}, 30000);
</script>
<?php endif;