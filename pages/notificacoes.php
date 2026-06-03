<?php
// SEGREDO LUSITANO — Notificações
require_once dirname(__DIR__) . '/includes/functions.php';

$user = auth_user();
if (!$user) { header('Location: ' . SITE_URL . '/pages/login.php'); exit; }

// Marcar todas como lidas ao visitar a página
marcar_notificacoes_lidas($user['id']);

$notificacoes = get_notificacoes($user['id'], 60);

// Agrupamento por data
function grupo_data(string $data): string {
    $ts   = strtotime($data);
    $hoje = strtotime('today');
    $ont  = strtotime('yesterday');
    if ($ts >= $hoje)  return 'Hoje';
    if ($ts >= $ont)   return 'Ontem';
    if ($ts >= $hoje - 6 * 86400) return 'Esta semana';
    return 'Anteriores';
}

$page_title = 'Notificações';
include dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-content">
<section class="section">
<div class="container" style="max-width:640px;">


  <?php if (!$notificacoes): ?>
  <div class="empty-state" style="padding:4rem 0;">
    <i class="fas fa-bell-slash" style="font-size:2.5rem;opacity:.3;"></i>
    <h3 style="margin-top:.75rem;">Sem notificações</h3>
    <p style="font-size:.9rem;">Quando alguém interagir com os teus locais aparece aqui.</p>
  </div>
  <?php else: ?>

  <?php
  $grupo_atual = null;
  foreach ($notificacoes as $n):
    $grupo = grupo_data($n['criado_em']);
    if ($grupo !== $grupo_atual):
      if ($grupo_atual !== null) echo '</div>';
      $grupo_atual = $grupo;
  ?>
  <div style="margin-bottom:.35rem;font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--texto-muted);padding-top:1.25rem;"><?= $grupo_atual ?></div>
  <div>
  <?php endif; ?>

  <?php
  // Definir ícone, texto e link
  $icone = match($n['tipo']) {
      'like'       => ['fas fa-heart',       '#e74c3c'],
      'comentario' => ['fas fa-comment',     '#2d6a4f'],
      'seguidor'   => ['fas fa-user-plus',   '#3b82f6'],
      'checkin'    => ['fas fa-location-dot','#c9a84c'],
      default      => ['fas fa-bell',        '#888'],
  };
  $texto = match($n['tipo']) {
      'like'       => '<strong>' . h($n['ator_nome']) . '</strong> deu like no teu local <strong>' . h($n['local_nome'] ?? '') . '</strong>',
      'comentario' => '<strong>' . h($n['ator_nome']) . '</strong> comentou em <strong>' . h($n['local_nome'] ?? '') . '</strong>',
      'seguidor'   => '<strong>' . h($n['ator_nome']) . '</strong> começou a seguir-te',
      'checkin'    => '<strong>' . h($n['ator_nome']) . '</strong> fez check-in em <strong>' . h($n['local_nome'] ?? '') . '</strong>',
      default      => 'Nova notificação',
  };
  $link = match($n['tipo']) {
      'like', 'comentario', 'checkin' => SITE_URL . '/pages/local.php?id=' . $n['local_id'],
      'seguidor'                       => SITE_URL . '/pages/perfil.php?id=' . $n['ator_id'],
      default                          => '#',
  };
  ?>
  <a href="<?= $link ?>" style="display:flex;align-items:center;gap:.9rem;padding:.85rem .75rem;border-radius:var(--radius);text-decoration:none;color:inherit;transition:background .15s;margin-bottom:.25rem;"
     onmouseover="this.style.background='var(--creme)'" onmouseout="this.style.background='transparent'">

    <!-- Avatar do ator -->
    <div style="position:relative;flex-shrink:0;">
      <div style="width:44px;height:44px;border-radius:50%;overflow:hidden;background:var(--verde-escuro);display:flex;align-items:center;justify-content:center;color:var(--dourado);font-weight:700;font-size:1.1rem;">
        <?php if ($n['ator_avatar']): ?>
          <img src="<?= SITE_URL ?>/uploads/locais/<?= h($n['ator_avatar']) ?>" alt="" style="width:100%;height:100%;object-fit:cover;">
        <?php else: ?>
          <?= mb_strtoupper(mb_substr($n['ator_username'], 0, 1)) ?>
        <?php endif; ?>
      </div>
      <span style="position:absolute;bottom:-2px;right:-2px;background:<?= $icone[1] ?>;border-radius:50%;width:18px;height:18px;display:flex;align-items:center;justify-content:center;border:2px solid #fff;">
        <i class="<?= $icone[0] ?>" style="font-size:.55rem;color:#fff;"></i>
      </span>
    </div>

    <!-- Texto -->
    <div style="flex:1;min-width:0;">
      <p style="margin:0;font-size:.9rem;line-height:1.4;color:var(--texto);"><?= $texto ?></p>
      <span style="font-size:.75rem;color:var(--texto-muted);margin-top:.2rem;display:block;"><?= tempo_atras($n['criado_em']) ?></span>
    </div>

  </a>
  <?php endforeach; ?>
  <?php if ($grupo_atual !== null) echo '</div>'; ?>

  <?php endif; ?>

</div>
</section>
</div>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
