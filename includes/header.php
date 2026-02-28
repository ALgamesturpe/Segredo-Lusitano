<?php
// ============================================================
// SEGREDO LUSITANO - Cabeçalho (include)
// ============================================================
require_once dirname(__DIR__) . '/includes/auth.php';
$user = auth_user();
$page_title = $page_title ?? SITE_NAME;
?>
<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= h($page_title) ?> &mdash; <?= SITE_NAME ?></title>
<meta name="description" content="Descobre os segredos escondidos de Portugal.">

<!-- Favicon -->
<link rel="icon" type="image/x-icon" href="<?= SITE_URL ?>/favicon.ico">
<link rel="icon" type="image/png" sizes="32x32" href="<?= SITE_URL ?>/assets/images/favicon-32.png">

<!-- Fontes -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,700;1,400&family=Outfit:wght@300;400;600&display=swap" rel="stylesheet">

<!-- Ícones -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<!-- Leaflet -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">

<!-- CSS próprio -->
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">

<?= $extra_head ?? '' ?>
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
      <li><a href="<?= SITE_URL ?>/index.php"><i class="fas fa-home"></i> Início</a></li>
      <li><a href="<?= SITE_URL ?>/pages/explorar.php"><i class="fas fa-compass"></i> Explorar</a></li>
      <li><a href="<?= SITE_URL ?>/pages/mapa.php"><i class="fas fa-map"></i> Mapa</a></li>
      <li><a href="<?= SITE_URL ?>/pages/ranking.php"><i class="fas fa-trophy"></i> Ranking</a></li>

      <?php if ($user): ?>
        <li class="nav-dropdown" id="nav-user-dropdown">
          <button class="nav-avatar-btn" id="dropdown-toggle" type="button" aria-expanded="false">
            <?php if ($user['avatar']): ?>
              <img src="<?= SITE_URL ?>/uploads/locais/<?= h($user['avatar']) ?>" alt="" class="nav-avatar">
            <?php else: ?>
              <span class="nav-avatar-placeholder"><?= mb_strtoupper(mb_substr($user['username'],0,1)) ?></span>
            <?php endif; ?>
            <?= h($user['username']) ?> <i class="fas fa-chevron-down" id="dropdown-chevron"></i>
          </button>
          <ul class="dropdown-menu" id="user-dropdown-menu">
            <li><a href="<?= SITE_URL ?>/pages/perfil.php"><i class="fas fa-user"></i> O meu Perfil</a></li>
            <li><a href="<?= SITE_URL ?>/pages/local_novo.php"><i class="fas fa-plus-circle"></i> Partilhar Local</a></li>
            <?php if (is_admin()): ?>
            <li style="border-top:1px solid rgba(201,168,76,.2); margin-top:.25rem; padding-top:.25rem;">
              <a href="<?= SITE_URL ?>/admin/index.php"><i class="fas fa-shield-alt"></i> Administração</a>
            </li>
            <?php endif; ?>
            <li style="border-top:1px solid rgba(201,168,76,.2); margin-top:.25rem; padding-top:.25rem;">
              <a href="<?= SITE_URL ?>/pages/logout.php"><i class="fas fa-sign-out-alt"></i> Sair</a>
            </li>
          </ul>
        </li>
      <?php else: ?>
        <li><a href="<?= SITE_URL ?>/pages/login.php" class="btn-nav-login">Entrar</a></li>
        <li><a href="<?= SITE_URL ?>/pages/registo.php" class="btn-nav-register">Registar</a></li>
      <?php endif; ?>
    </ul>
  </div>
</nav>

<?php
// Flash messages globais
$flash_success = flash('success');
$flash_error   = flash('error');
if ($flash_success): ?>
<div class="flash flash-success"><i class="fas fa-check-circle"></i> <?= h($flash_success) ?></div>
<?php endif; ?>
<?php if ($flash_error): ?>
<div class="flash flash-error"><i class="fas fa-exclamation-circle"></i> <?= h($flash_error) ?></div>
<?php endif;
