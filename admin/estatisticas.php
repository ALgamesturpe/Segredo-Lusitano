@ -1,414 +1,440 @@
﻿<?php
// ============================================================
// SEGREDO LUSITANO — Admin: Estatísticas Detalhadas
// ============================================================
require_once dirname(__DIR__) . '/includes/functions.php';
require_admin();

$page_title = 'Admin · Estatísticas';

// ── Filtro de mês, ano e top ──────────────────────────────
$mes_sel = isset($_GET['mes']) ? (int)$_GET['mes'] : (int)date('m');
$ano_sel = isset($_GET['ano']) ? (int)$_GET['ano'] : (int)date('Y');
if ($mes_sel < 1 || $mes_sel > 12) $mes_sel = (int)date('m');
if ($ano_sel < 2024 || $ano_sel > (int)date('Y')) $ano_sel = (int)date('Y');

$top_sel = isset($_GET['top']) ? (int)$_GET['top'] : 10;
if (!in_array($top_sel, [1,3,5,10,20])) $top_sel = 10;

$nomes_meses = ['Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];

// ── Estatísticas do mês selecionado ──────────────────────
$stats = [];

$st = db()->prepare('SELECT COUNT(*) FROM utilizadores WHERE role="user" AND MONTH(criado_em)=? AND YEAR(criado_em)=?');
$st->execute([$mes_sel, $ano_sel]);
$stats['registos_mes'] = (int)$st->fetchColumn();

$st = db()->prepare('SELECT COUNT(*) FROM locais WHERE estado="aprovado" AND MONTH(criado_em)=? AND YEAR(criado_em)=?');
$st->execute([$mes_sel, $ano_sel]);
$stats['locais_mes'] = (int)$st->fetchColumn();

$st = db()->prepare('SELECT COUNT(*) FROM comentarios WHERE MONTH(criado_em)=? AND YEAR(criado_em)=?');
$st->execute([$mes_sel, $ano_sel]);
$stats['comentarios_mes'] = (int)$st->fetchColumn();

$st = db()->prepare('SELECT COUNT(*) FROM likes WHERE MONTH(criado_em)=? AND YEAR(criado_em)=?');
$st->execute([$mes_sel, $ano_sel]);
$stats['total_likes'] = (int)$st->fetchColumn();


$st = db()->prepare('SELECT AVG(pontos) FROM utilizadores WHERE role="user" AND ativo=1 AND MONTH(criado_em)=? AND YEAR(criado_em)=?');
$st->execute([$mes_sel, $ano_sel]);
$stats['media_pontos'] = (float)$st->fetchColumn();

$st = db()->prepare('SELECT COUNT(*) FROM mensagens WHERE MONTH(criado_em)=? AND YEAR(criado_em)=?');
$st->execute([$mes_sel, $ano_sel]);
$stats['total_mensagens'] = (int)$st->fetchColumn();

// ── Rankings ──────────────────────────────────────────────

$st = db()->prepare('SELECT u.id, u.nome, u.username, u.avatar, u.pontos,
        COUNT(l.id) AS total_locais
     FROM utilizadores u
     LEFT JOIN locais l ON l.utilizador_id = u.id AND l.estado = "aprovado"
     WHERE u.role = "user" AND u.ativo = 1
     GROUP BY u.id HAVING total_locais > 0 ORDER BY total_locais DESC LIMIT ?');
$st->execute([$top_sel]);
$rank_locais = $st->fetchAll();

$st = db()->prepare('SELECT u.id, u.nome, u.username, u.avatar,
        COUNT(c.id) AS total_comentarios
     FROM utilizadores u
     LEFT JOIN comentarios c ON c.utilizador_id = u.id
     WHERE u.role = "user" AND u.ativo = 1
     GROUP BY u.id HAVING total_comentarios > 0 ORDER BY total_comentarios DESC LIMIT ?');
$st->execute([$top_sel]);
$rank_comentarios = $st->fetchAll();

$st = db()->prepare('SELECT u.id, u.nome, u.username, u.avatar,
        COUNT(lk.id) AS total_likes
     FROM utilizadores u
     LEFT JOIN locais l ON l.utilizador_id = u.id
     LEFT JOIN likes lk ON lk.local_id = l.id
     WHERE u.role = "user" AND u.ativo = 1
     GROUP BY u.id HAVING total_likes > 0 ORDER BY total_likes DESC LIMIT ?');
$st->execute([$top_sel]);
$rank_likes = $st->fetchAll();

$st = db()->prepare('SELECT u.id, u.nome, u.username, u.avatar, u.pontos
     FROM utilizadores u
     WHERE u.role = "user" AND u.ativo = 1 AND u.pontos > 0
     ORDER BY u.pontos DESC LIMIT ?');
$st->execute([$top_sel]);
$rank_pontos = $st->fetchAll();


$st = db()->prepare('SELECT u.id, u.nome, u.username, u.avatar,
        COUNT(f.id) AS total_fotos
     FROM utilizadores u
     LEFT JOIN locais l ON l.utilizador_id = u.id AND l.estado = "aprovado"
     LEFT JOIN fotos f ON f.local_id = l.id
     WHERE u.role = "user" AND u.ativo = 1
     GROUP BY u.id HAVING total_fotos > 0 ORDER BY total_fotos DESC LIMIT ?');
$st->execute([$top_sel]);
$rank_utilizadores_fotos = $st->fetchAll();

$st = db()->prepare('SELECT u.id, u.nome, u.username, u.avatar,
        SUM(l.vistas) AS total_vistas
     FROM utilizadores u
     JOIN locais l ON l.utilizador_id = u.id AND l.estado = "aprovado" AND l.bloqueado = 0
     WHERE u.role = "user" AND u.ativo = 1
     GROUP BY u.id HAVING total_vistas > 0 ORDER BY total_vistas DESC LIMIT ?');
$st->execute([$top_sel]);
$rank_vistas_totais = $st->fetchAll();

$st = db()->prepare('SELECT l.id, l.nome, l.vistas,
        u.username, c.nome AS categoria_nome
     FROM locais l
     JOIN utilizadores u ON u.id = l.utilizador_id
     JOIN categorias c ON c.id = l.categoria_id
     WHERE l.estado = "aprovado" AND l.bloqueado = 0
       AND MONTH(l.criado_em) = ? AND YEAR(l.criado_em) = ?
     ORDER BY l.vistas DESC LIMIT ?');
$st->execute([$mes_sel, $ano_sel, $top_sel]);
$rank_locais_vistos = $st->fetchAll();

$st = db()->prepare('SELECT l.id, l.nome,
        u.username, c.nome AS categoria_nome,
        COUNT(lk.id) AS total_likes
     FROM locais l
     JOIN utilizadores u ON u.id = l.utilizador_id
     JOIN categorias c ON c.id = l.categoria_id
     LEFT JOIN likes lk ON lk.local_id = l.id
                       AND MONTH(lk.criado_em) = ? AND YEAR(lk.criado_em) = ?
     WHERE l.estado = "aprovado" AND l.bloqueado = 0
     GROUP BY l.id HAVING total_likes > 0 ORDER BY total_likes DESC LIMIT ?');
$st->execute([$mes_sel, $ano_sel, $top_sel]);
$rank_locais_likes = $st->fetchAll();

$st = db()->prepare('SELECT l.id, l.nome,
        u.username, c.nome AS categoria_nome,
        COUNT(f.id) AS total_fotos
     FROM locais l
     JOIN utilizadores u ON u.id = l.utilizador_id
     JOIN categorias c ON c.id = l.categoria_id
     LEFT JOIN fotos f ON f.local_id = l.id
                      AND MONTH(f.criado_em) = ? AND YEAR(f.criado_em) = ?
     WHERE l.estado = "aprovado" AND l.bloqueado = 0
     GROUP BY l.id HAVING total_fotos > 0 ORDER BY total_fotos DESC LIMIT ?');
$st->execute([$mes_sel, $ano_sel, $top_sel]);
$rank_locais_fotografados = $st->fetchAll();

$st = db()->prepare('SELECT l.id, l.nome, l.criado_em,
        u.username, c.nome AS categoria_nome
     FROM locais l
     JOIN utilizadores u ON u.id = l.utilizador_id
     JOIN categorias c ON c.id = l.categoria_id
     WHERE l.estado = "aprovado" AND l.bloqueado = 0
       AND MONTH(l.criado_em) = ? AND YEAR(l.criado_em) = ?
     ORDER BY l.criado_em DESC LIMIT ?');
$st->execute([$mes_sel, $ano_sel, $top_sel]);
$rank_recentes = $st->fetchAll();

include dirname(__DIR__) . '/includes/header.php';

function avatar_cell(array $u): string {
    $av = !empty($u['avatar'])
        ? '<img src="' . SITE_URL . '/uploads/locais/' . h($u['avatar']) . '" style="width:100%;height:100%;object-fit:cover;">'
        : '<span style="font-size:.75rem;font-weight:700;">' . mb_strtoupper(mb_substr($u['username'],0,1)) . '</span>';
    return '<div style="width:30px;height:30px;border-radius:50%;background:var(--verde-claro);color:#fff;
                display:flex;align-items:center;justify-content:center;flex-shrink:0;overflow:hidden;">' . $av . '</div>';
}
?>

<div class="page-content">
<div class="admin-wrapper">

  <aside class="admin-sidebar">
    <div style="color:var(--dourado);font-family:'Playfair Display',serif;font-size:1.1rem;font-weight:700;margin-bottom:1.5rem;padding:.5rem .85rem;">
      <i class="fas fa-shield-alt"></i> Administração
    </div>
    <nav class="admin-nav">
      <div class="nav-section">Geral</div>
      <a href="<?= SITE_URL ?>/admin/index.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
      <a href="<?= SITE_URL ?>/admin/locais.php"><i class="fa-solid fa-location-dot"></i> Locais</a>
      <a href="<?= SITE_URL ?>/admin/utilizadores.php"><i class="fas fa-users"></i> Utilizadores</a>
      <a href="<?= SITE_URL ?>/admin/estatisticas.php" class="active"><i class="fas fa-chart-bar"></i> Estatísticas</a>
    </nav>
  </aside>

  <main class="admin-content">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;margin-bottom:1.5rem;">
      <h1 class="admin-title" style="margin:0;"><i class="fas fa-chart-bar"></i> Estatísticas</h1>
      <form method="GET" style="display:flex;align-items:center;gap:.5rem;flex-wrap:wrap;">
        <select name="mes" style="padding:.45rem .75rem;border:1.5px solid var(--creme-escuro);border-radius:0;background:var(--creme);font-size:.9rem;color:var(--texto);">
          <?php foreach ($nomes_meses as $i => $nome): ?>
            <option value="<?= $i+1 ?>" <?= ($i+1) == $mes_sel ? 'selected' : '' ?>><?= $nome ?></option>
          <?php endforeach; ?>
        </select>
        <select name="ano" style="padding:.45rem .75rem;border:1.5px solid var(--creme-escuro);border-radius:0;background:var(--creme);font-size:.9rem;color:var(--texto);">
          <?php for ($y = (int)date('Y'); $y >= 2024; $y--): ?>
            <option value="<?= $y ?>" <?= $y == $ano_sel ? 'selected' : '' ?>><?= $y ?></option>
          <?php endfor; ?>
        </select>
        <select name="top" style="padding:.45rem .75rem;border:1.5px solid var(--creme-escuro);border-radius:0;background:var(--creme);font-size:.9rem;color:var(--texto);">
          <?php foreach ([1,3,5,10,20] as $t): ?>
            <option value="<?= $t ?>" <?= $top_sel == $t ? 'selected' : '' ?>>Top <?= $t ?></option>
          <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-sm btn-verde"><i class="fas fa-filter"></i> Filtrar</button>
        <?php $is_hoje = ($mes_sel == (int)date('m') && $ano_sel == (int)date('Y')); ?>
        <a href="<?= SITE_URL ?>/admin/estatisticas.php?mes=<?= date('m') ?>&ano=<?= date('Y') ?>&top=<?= $top_sel ?>"
           class="btn btn-sm <?= $is_hoje ? 'btn-verde' : '' ?>"
           style="<?= !$is_hoje ? 'border:1px solid var(--creme-escuro);color:var(--texto-muted);' : '' ?>">
          <i class="fas fa-calendar-day"></i> Hoje
        </a>
      </form>
    </div>

    <!-- CARDS DO MÊS -->
    <div style="margin-bottom:2rem;">
      <h2 style="font-size:.9rem;color:var(--texto-muted);text-transform:uppercase;letter-spacing:.06em;margin-bottom:.85rem;">
        <i class="fas fa-calendar" style="color:var(--dourado);margin-right:.4rem;"></i>
        <?= $nomes_meses[$mes_sel-1] ?> <?= $ano_sel ?>
      </h2>
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:1rem;">
        <div class="admin-stat-card" style="border-color:var(--dourado);">
          <div class="num" style="color:var(--dourado);"><?= $stats['registos_mes'] ?></div>
          <div class="lbl">Novos Utilizadores</div>
        </div>
        <div class="admin-stat-card" style="border-color:var(--verde);">
          <div class="num" style="color:var(--verde);"><?= $stats['locais_mes'] ?></div>
          <div class="lbl">Locais Publicados</div>
        </div>
        <div class="admin-stat-card" style="border-color:var(--verde-claro);">
          <div class="num" style="color:var(--verde-claro);"><?= $stats['comentarios_mes'] ?></div>
          <div class="lbl">Comentários</div>
        </div>
        <div class="admin-stat-card" style="border-color:#e74c3c;">
          <div class="num" style="color:#e74c3c;"><?= number_format($stats['total_likes']) ?></div>
          <div class="lbl">Total de Likes</div>
        </div>

        <div class="admin-stat-card" style="border-color:#8e44ad;">
          <div class="num" style="color:#8e44ad;"><?= number_format($stats['media_pontos'], 0) ?></div>
          <div class="lbl">Média de Pontos</div>
        </div>
        <div class="admin-stat-card" style="border-color:#e67e22;">
          <div class="num" style="color:#e67e22;"><?= number_format($stats['total_mensagens']) ?></div>
          <div class="lbl">Mensagens Enviadas</div>
        </div>
      </div>
    </div>

    <!-- RANKINGS DE UTILIZADORES -->
    <h2 style="font-size:.9rem;color:var(--texto-muted);text-transform:uppercase;letter-spacing:.06em;margin-bottom:.85rem;">
      <i class="fas fa-users" style="color:var(--verde);margin-right:.4rem;"></i> Rankings de Utilizadores
    </h2>
    <div class="admin-rankings-grid" style="display:grid;gap:1.5rem;margin-bottom:1.5rem;grid-template-columns:1fr 1fr;">

      <div>
        <h3 style="font-size:1rem;margin-bottom:.75rem;"><i class="fas fa-map-marker-alt" style="color:var(--verde);margin-right:.35rem;"></i> Mais Locais Publicados</h3>
        <table class="data-table">
          <thead><tr><th>#</th><th>Explorador</th><th>Locais</th><th>Pontos</th></tr></thead>
          <tbody>
            <?php foreach ($rank_locais as $i => $u): ?>
            <tr>
              <td style="font-weight:700;color:var(--dourado);"><?= $i+1 ?>º</td>
              <td style="display:flex;align-items:center;gap:.5rem;"><?= avatar_cell($u) ?><a href="<?= SITE_URL ?>/pages/perfil.php?id=<?= $u['id'] ?>" style="color:var(--verde);font-size:.85rem;">@<?= h($u['username']) ?></a></td>
              <td style="font-weight:700;"><?= $u['total_locais'] ?></td>
              <td style="color:var(--dourado);font-weight:600;"><?= number_format($u['pontos']) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$rank_locais): ?><tr><td colspan="4" style="text-align:center;color:var(--texto-muted);padding:1.5rem;">Sem dados.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>

      <div>
        <h3 style="font-size:1rem;margin-bottom:.75rem;"><i class="fas fa-comments" style="color:var(--verde-claro);margin-right:.35rem;"></i> Mais Comentários</h3>
        <table class="data-table">
          <thead><tr><th>#</th><th>Explorador</th><th>Comentários</th></tr></thead>
          <tbody>
            <?php foreach ($rank_comentarios as $i => $u): ?>
            <tr>
              <td style="font-weight:700;color:var(--dourado);"><?= $i+1 ?>º</td>
              <td style="display:flex;align-items:center;gap:.5rem;"><?= avatar_cell($u) ?><a href="<?= SITE_URL ?>/pages/perfil.php?id=<?= $u['id'] ?>" style="color:var(--verde);font-size:.85rem;">@<?= h($u['username']) ?></a></td>
              <td style="font-weight:700;"><?= $u['total_comentarios'] ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$rank_comentarios): ?><tr><td colspan="3" style="text-align:center;color:var(--texto-muted);padding:1.5rem;">Sem dados.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>

      <div>
        <h3 style="font-size:1rem;margin-bottom:.75rem;"><i class="fas fa-heart" style="color:#e74c3c;margin-right:.35rem;"></i> Mais Likes Recebidos</h3>
        <table class="data-table">
          <thead><tr><th>#</th><th>Explorador</th><th>Likes</th></tr></thead>
          <tbody>
            <?php foreach ($rank_likes as $i => $u): ?>
            <tr>
              <td style="font-weight:700;color:var(--dourado);"><?= $i+1 ?>º</td>
              <td style="display:flex;align-items:center;gap:.5rem;"><?= avatar_cell($u) ?><a href="<?= SITE_URL ?>/pages/perfil.php?id=<?= $u['id'] ?>" style="color:var(--verde);font-size:.85rem;">@<?= h($u['username']) ?></a></td>
              <td style="font-weight:700;color:#e74c3c;"><?= $u['total_likes'] ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$rank_likes): ?><tr><td colspan="3" style="text-align:center;color:var(--texto-muted);padding:1.5rem;">Sem dados.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>

      <div>
        <h3 style="font-size:1rem;margin-bottom:.75rem;"><i class="fas fa-star" style="color:var(--dourado);margin-right:.35rem;"></i> Ranking de Pontos</h3>
        <table class="data-table">
          <thead><tr><th>#</th><th>Explorador</th><th>Pontos</th></tr></thead>
          <tbody>
            <?php foreach ($rank_pontos as $i => $u): ?>
            <tr>
              <td style="font-weight:700;color:var(--dourado);"><?= $i+1 ?>º</td>
              <td style="display:flex;align-items:center;gap:.5rem;"><?= avatar_cell($u) ?><a href="<?= SITE_URL ?>/pages/perfil.php?id=<?= $u['id'] ?>" style="color:var(--verde);font-size:.85rem;">@<?= h($u['username']) ?></a></td>
              <td style="font-weight:700;color:var(--dourado);"><?= number_format($u['pontos']) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$rank_pontos): ?><tr><td colspan="3" style="text-align:center;color:var(--texto-muted);padding:1.5rem;">Sem dados.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>

      <div>
        <h3 style="font-size:1rem;margin-bottom:.75rem;"><i class="fas fa-camera" style="color:#e67e22;margin-right:.35rem;"></i> Mais Fotos Publicadas</h3>
        <table class="data-table">
          <thead><tr><th>#</th><th>Explorador</th><th>Fotos</th></tr></thead>
          <tbody>
            <?php foreach ($rank_utilizadores_fotos as $i => $u): ?>
            <tr>
              <td style="font-weight:700;color:var(--dourado);"><?= $i+1 ?>º</td>
              <td style="display:flex;align-items:center;gap:.5rem;"><?= avatar_cell($u) ?><a href="<?= SITE_URL ?>/pages/perfil.php?id=<?= $u['id'] ?>" style="color:var(--verde);font-size:.85rem;">@<?= h($u['username']) ?></a></td>
              <td style="font-weight:700;color:#e67e22;"><?= number_format($u['total_fotos']) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$rank_utilizadores_fotos): ?><tr><td colspan="3" style="text-align:center;color:var(--texto-muted);padding:1.5rem;">Sem dados.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>

      <div>
        <h3 style="font-size:1rem;margin-bottom:.75rem;"><i class="fas fa-eye" style="color:#8e44ad;margin-right:.35rem;"></i> Mais Vistas Totais</h3>
        <table class="data-table">
          <thead><tr><th>#</th><th>Explorador</th><th>Vistas</th></tr></thead>
          <tbody>
            <?php foreach ($rank_vistas_totais as $i => $u): ?>
            <tr>
              <td style="font-weight:700;color:var(--dourado);"><?= $i+1 ?>º</td>
              <td style="display:flex;align-items:center;gap:.5rem;"><?= avatar_cell($u) ?><a href="<?= SITE_URL ?>/pages/perfil.php?id=<?= $u['id'] ?>" style="color:var(--verde);font-size:.85rem;">@<?= h($u['username']) ?></a></td>
              <td style="font-weight:700;color:#8e44ad;"><?= number_format($u['total_vistas']) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$rank_vistas_totais): ?><tr><td colspan="3" style="text-align:center;color:var(--texto-muted);padding:1.5rem;">Sem dados.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>

    </div>

    <!-- RANKINGS DE LOCAIS -->
    <h2 style="font-size:.9rem;color:var(--texto-muted);text-transform:uppercase;letter-spacing:.06em;margin-bottom:.85rem;">
      <i class="fas fa-location-dot" style="color:var(--verde);margin-right:.4rem;"></i> Rankings de Locais
    </h2>
    <div class="admin-rankings-grid" style="display:grid;gap:1.5rem;grid-template-columns:1fr 1fr;">

      <div>
        <h3 style="font-size:1rem;margin-bottom:.75rem;"><i class="fas fa-eye" style="color:#8e44ad;margin-right:.35rem;"></i> Mais Vistos</h3>
        <table class="data-table">
          <thead><tr><th>#</th><th>Local</th><th>Vistas</th></tr></thead>
          <tbody>
            <?php foreach ($rank_locais_vistos as $i => $l): ?>
            <tr>
              <td style="font-weight:700;color:var(--dourado);"><?= $i+1 ?>º</td>
              <td><a href="<?= SITE_URL ?>/pages/local.php?id=<?= $l['id'] ?>" style="color:var(--verde);font-weight:600;font-size:.85rem;"><?= h($l['nome']) ?></a><div style="font-size:.76rem;color:var(--texto-muted);">@<?= h($l['username']) ?> · <?= h($l['categoria_nome']) ?></div></td>
              <td style="font-weight:700;color:#8e44ad;"><?= number_format($l['vistas']) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$rank_locais_vistos): ?><tr><td colspan="3" style="text-align:center;color:var(--texto-muted);padding:1.5rem;">Sem dados.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>

      <div>
        <h3 style="font-size:1rem;margin-bottom:.75rem;"><i class="fas fa-heart" style="color:#e74c3c;margin-right:.35rem;"></i> Mais Curtidos</h3>
        <table class="data-table">
          <thead><tr><th>#</th><th>Local</th><th>Likes</th></tr></thead>
          <tbody>
            <?php foreach ($rank_locais_likes as $i => $l): ?>
            <tr>
              <td style="font-weight:700;color:var(--dourado);"><?= $i+1 ?>º</td>
              <td><a href="<?= SITE_URL ?>/pages/local.php?id=<?= $l['id'] ?>" style="color:var(--verde);font-weight:600;font-size:.85rem;"><?= h($l['nome']) ?></a><div style="font-size:.76rem;color:var(--texto-muted);">@<?= h($l['username']) ?> · <?= h($l['categoria_nome']) ?></div></td>
              <td style="font-weight:700;color:#e74c3c;"><?= number_format($l['total_likes']) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$rank_locais_likes): ?><tr><td colspan="3" style="text-align:center;color:var(--texto-muted);padding:1.5rem;">Sem dados.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>

      <div>
        <h3 style="font-size:1rem;margin-bottom:.75rem;"><i class="fas fa-camera" style="color:#8e44ad;margin-right:.35rem;"></i> Mais Fotografados</h3>
        <table class="data-table">
          <thead><tr><th>#</th><th>Local</th><th>Fotos</th></tr></thead>
          <tbody>
            <?php foreach ($rank_locais_fotografados as $i => $l): ?>
            <tr>
              <td style="font-weight:700;color:var(--dourado);"><?= $i+1 ?>º</td>
              <td><a href="<?= SITE_URL ?>/pages/local.php?id=<?= $l['id'] ?>" style="color:var(--verde);font-weight:600;font-size:.85rem;"><?= h($l['nome']) ?></a><div style="font-size:.76rem;color:var(--texto-muted);">@<?= h($l['username']) ?> · <?= h($l['categoria_nome']) ?></div></td>
              <td style="font-weight:700;color:#8e44ad;"><?= number_format($l['total_fotos']) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$rank_locais_fotografados): ?><tr><td colspan="3" style="text-align:center;color:var(--texto-muted);padding:1.5rem;">Sem dados.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>

      <div>
        <h3 style="font-size:1rem;margin-bottom:.75rem;"><i class="fas fa-clock" style="color:var(--dourado);margin-right:.35rem;"></i> Mais Recentes</h3>
        <table class="data-table">
          <thead><tr><th>#</th><th>Local</th><th>Data</th></tr></thead>
          <tbody>
            <?php foreach ($rank_recentes as $i => $l): ?>
            <tr>
              <td style="font-weight:700;color:var(--dourado);"><?= $i+1 ?>º</td>
              <td><a href="<?= SITE_URL ?>/pages/local.php?id=<?= $l['id'] ?>" style="color:var(--verde);font-weight:600;font-size:.85rem;"><?= h($l['nome']) ?></a><div style="font-size:.76rem;color:var(--texto-muted);">@<?= h($l['username']) ?> · <?= h($l['categoria_nome']) ?></div></td>
              <td style="font-weight:700;color:var(--dourado);font-size:.82rem;"><?= date('d/m/Y', strtotime($l['criado_em'])) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$rank_recentes): ?><tr><td colspan="3" style="text-align:center;color:var(--texto-muted);padding:1.5rem;">Sem dados.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>

    </div>
  </main>
</div>
</div>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>