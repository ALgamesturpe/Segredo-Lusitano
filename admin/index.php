<?php
// ============================================================
// SEGREDO LUSITANO — Painel de Administração (Dashboard)
// ============================================================
require_once dirname(__DIR__) . '/includes/functions.php';
require_admin();

$page_title = 'Administração';

// ── Estatísticas gerais ───────────────────────────────────
$total_locais      = (int)db()->query('SELECT COUNT(*) FROM locais WHERE estado="aprovado"')->fetchColumn();
$total_users       = (int)db()->query('SELECT COUNT(*) FROM utilizadores WHERE ativo=1 AND role="user"')->fetchColumn();
$total_comentarios = (int)db()->query('SELECT COUNT(*) FROM comentarios')->fetchColumn();
$total_denuncias   = (int)db()->query('SELECT COUNT(*) FROM denuncias WHERE resolvida=0')->fetchColumn();
$total_bloqueados  = (int)db()->query('SELECT COUNT(*) FROM locais WHERE bloqueado=1')->fetchColumn();
$total_suspensos   = (int)db()->query('SELECT COUNT(*) FROM utilizadores WHERE ativo=0 AND role="user"')->fetchColumn();
$total_likes       = (int)db()->query('SELECT COUNT(*) FROM likes')->fetchColumn();

// ── Top utilizadores ─────────────────────────────────────

// Utilizador com mais locais publicados
$top_locais = db()->query(
    'SELECT u.id, u.nome, u.username, u.avatar,
            COUNT(l.id) AS total
     FROM utilizadores u
     JOIN locais l ON l.utilizador_id = u.id AND l.estado = "aprovado"
     WHERE u.role = "user"
     GROUP BY u.id ORDER BY total DESC LIMIT 1'
)->fetch() ?: null;

// Utilizador com mais comentários
$top_comentarios = db()->query(
    'SELECT u.id, u.nome, u.username, u.avatar,
            COUNT(c.id) AS total
     FROM utilizadores u
     JOIN comentarios c ON c.utilizador_id = u.id
     WHERE u.role = "user"
     GROUP BY u.id ORDER BY total DESC LIMIT 1'
)->fetch() ?: null;

// Utilizador com mais likes recebidos nos seus locais
$top_likes = db()->query(
    'SELECT u.id, u.nome, u.username, u.avatar,
            COUNT(lk.id) AS total
     FROM utilizadores u
     JOIN locais l ON l.utilizador_id = u.id
     JOIN likes lk ON lk.local_id = l.id
     WHERE u.role = "user"
     GROUP BY u.id ORDER BY total DESC LIMIT 1'
)->fetch() ?: null;

// ── Gráfico: publicações por mês ─────────────────────────
$ano_atual       = date('Y');
$ano_selecionado = isset($_GET['ano']) ? (int)$_GET['ano'] : $ano_atual;
if ($ano_selecionado < 2020 || $ano_selecionado > $ano_atual) $ano_selecionado = $ano_atual;

$st_g = db()->prepare(
    'SELECT MONTH(criado_em) AS mes, COUNT(*) AS total
     FROM locais WHERE YEAR(criado_em) = ? AND estado = "aprovado"
     GROUP BY MONTH(criado_em) ORDER BY mes ASC'
);
$st_g->execute([$ano_selecionado]);
$dados_g = array_fill(1, 12, 0);
foreach ($st_g->fetchAll() as $r) $dados_g[(int)$r['mes']] = (int)$r['total'];
$grafico_json = json_encode(array_values($dados_g));

// ── Ações de moderação ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['moderar'])) {
        moderar_local((int)$_POST['local_id'], $_POST['estado']);
        flash('success', 'Local ' . $_POST['estado'] . ' com sucesso!');
        header('Location: ' . SITE_URL . '/admin/index.php');
        exit;
    }
    if (isset($_POST['devolver_denuncia'])) {
        db()->prepare('UPDATE denuncias SET resolvida=1 WHERE id=?')->execute([(int)$_POST['den_id']]);
        flash('success', 'Denúncia devolvida — conteúdo mantido.');
        header('Location: ' . SITE_URL . '/admin/index.php#denuncias');
        exit;
    }
    if (isset($_POST['moderar_denuncia_item'])) {
        $tipo   = (string)($_POST['tipo']   ?? '');
        $ref_id = (int)($_POST['ref_id']    ?? 0);
        $acao   = (string)($_POST['acao']   ?? '');
        $ok     = moderar_denuncias_item($tipo, $ref_id, $acao === 'bloquear');
        if ($ok) {
            flash('success', $acao === 'bloquear' ? 'Conteúdo bloqueado e denúncias resolvidas.' : 'Conteúdo permitido e denúncias resolvidas.');
        } else {
            flash('error', 'Não foi possível moderar este item.');
        }
        header('Location: ' . SITE_URL . '/admin/index.php#denuncias');
        exit;
    }
}

$pendentes = get_pendentes();
$denuncias = get_denuncias();

include dirname(__DIR__) . '/includes/header.php';

// Função auxiliar para renderizar card de top utilizador
function render_top_user(?array $u, string $valor_label): string {
    if (!$u) return '<div style="color:var(--texto-muted);font-size:.85rem;padding:.5rem 0;">Sem dados ainda.</div>';
    $avatar = !empty($u['avatar'])
        ? '<img src="' . SITE_URL . '/uploads/locais/' . h($u['avatar']) . '" style="width:100%;height:100%;object-fit:cover;">'
        : '<span style="font-size:.9rem;font-weight:700;">' . mb_strtoupper(mb_substr($u['username'],0,1)) . '</span>';
    return '
    <div style="display:flex;align-items:center;gap:.75rem;">
      <div style="width:38px;height:38px;border-radius:50%;background:var(--verde-claro);color:#fff;
                  display:flex;align-items:center;justify-content:center;flex-shrink:0;overflow:hidden;">
        ' . $avatar . '
      </div>
      <div style="flex:1;min-width:0;">
        <div style="font-weight:600;font-size:.88rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">' . h($u['nome']) . '</div>
        <a href="' . SITE_URL . '/pages/perfil.php?id=' . $u['id'] . '" style="color:var(--verde);font-size:.78rem;">@' . h($u['username']) . '</a>
      </div>
      <div style="font-family:\'Playfair Display\',serif;font-size:1.15rem;font-weight:700;color:var(--dourado);flex-shrink:0;">
        ' . $u['total'] . ' <span style="font-family:\'Outfit\',sans-serif;font-size:.72rem;color:var(--texto-muted);font-weight:400;">' . $valor_label . '</span>
      </div>
    </div>';
}
?>

<div class="page-content">
<div class="admin-wrapper">

  <!-- SIDEBAR -->
  <aside class="admin-sidebar">
    <div style="color:var(--dourado); font-family:'Playfair Display',serif; font-size:1.1rem; font-weight:700; margin-bottom:1.5rem; padding:.5rem .85rem;">
      <i class="fas fa-shield-alt"></i> Administração
    </div>
    <nav class="admin-nav">
      <div class="nav-section">Geral</div>
      <a href="<?= SITE_URL ?>/admin/index.php" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
      <a href="<?= SITE_URL ?>/admin/locais.php"><i class="fa-solid fa-location-dot"></i> Locais</a>
      <a href="<?= SITE_URL ?>/admin/utilizadores.php"><i class="fas fa-users"></i> Utilizadores</a>
      <a href="<?= SITE_URL ?>/admin/estatisticas.php"><i class="fas fa-chart-bar"></i> Estatísticas</a>
      <div class="nav-section">Moderação</div>
      <a href="#denuncias">
        <i class="fas fa-flag"></i> Denúncias
        <span style="background:#e74c3c;color:#fff;padding:.1rem .4rem;border-radius:50px;font-size:.7rem;margin-left:.25rem;"><?= $total_denuncias ?></span>
      </a>
    </nav>
  </aside>

  <!-- CONTEÚDO PRINCIPAL -->
  <main class="admin-content">
    <h1 class="admin-title">Dashboard</h1>

    <!-- GRÁFICO (60%) + TOP UTILIZADORES (40%) -->
    <div style="display:grid;grid-template-columns:3fr 2fr;gap:1.5rem;margin-bottom:2rem;">

      <!-- Gráfico -->
      <div style="background:var(--branco);border-radius:var(--radius-lg);box-shadow:var(--sombra-sm);padding:1.75rem;">
        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;margin-bottom:1.25rem;">
          <div style="display:flex;align-items:center;gap:.6rem;">
            <i class="fa-solid fa-chart-column" style="color:var(--dourado);font-size:1.1rem;"></i>
            <h2 style="font-size:1.15rem;margin:0;">Publicações por Mês</h2>
          </div>
          <form method="GET" style="display:flex;align-items:center;gap:.5rem;">
            <label style="font-size:.85rem;color:var(--texto-muted);">Ano:</label>
            <select name="ano" onchange="this.form.submit()"
                    style="padding:.3rem .75rem;border:1.5px solid var(--creme-escuro);border-radius:8px;background:var(--creme);font-size:.9rem;color:var(--texto);cursor:pointer;">
              <?php for ($y = $ano_atual; $y >= 2024; $y--): ?>
                <option value="<?= $y ?>" <?= $y === $ano_selecionado ? 'selected' : '' ?>><?= $y ?></option>
              <?php endfor; ?>
            </select>
          </form>
        </div>
        <canvas id="grafico-publicacoes" height="80"></canvas>
      </div>

      <!-- Painel Top Utilizadores -->
      <div style="background:var(--branco);border-radius:var(--radius-lg);box-shadow:var(--sombra-sm);padding:1.75rem;display:flex;flex-direction:column;gap:1rem;">

        <!-- Total likes -->
        <div style="display:flex;align-items:center;gap:.75rem;padding:.85rem 1rem;background:var(--creme);border-radius:var(--radius);border-left:4px solid #e74c3c;">
          <i class="fas fa-heart" style="color:#e74c3c;font-size:1.2rem;"></i>
          <div>
            <div style="font-family:'Playfair Display',serif;font-size:1.5rem;color:#e74c3c;font-weight:700;line-height:1;"><?= number_format($total_likes) ?></div>
            <div style="font-size:.72rem;color:var(--texto-muted);text-transform:uppercase;letter-spacing:.05em;">Total de Likes</div>
          </div>
        </div>

        <!-- Título -->
        <div style="font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--texto-muted);padding-top:.25rem;">
          <i class="fas fa-trophy" style="color:var(--dourado);margin-right:.35rem;"></i> Top Exploradores
        </div>

        <!-- Top locais publicados -->
        <div>
          <div style="font-size:.76rem;color:var(--texto-muted);margin-bottom:.45rem;">
            <i class="fas fa-map-marker-alt" style="color:var(--verde);margin-right:.3rem;"></i>Mais locais publicados
          </div>
          <?= render_top_user($top_locais ?? null, 'locais') ?>
        </div>

        <hr style="border:none;border-top:1px solid var(--creme-escuro);">

        <!-- Top comentários -->
        <div>
          <div style="font-size:.76rem;color:var(--texto-muted);margin-bottom:.45rem;">
            <i class="fas fa-comments" style="color:var(--verde-claro);margin-right:.3rem;"></i>Mais comentários
          </div>
          <?= render_top_user($top_comentarios ?? null, 'comentários') ?>
        </div>

        <hr style="border:none;border-top:1px solid var(--creme-escuro);">

        <!-- Top likes recebidos -->
        <div>
          <div style="font-size:.76rem;color:var(--texto-muted);margin-bottom:.45rem;">
            <i class="fas fa-heart" style="color:#e74c3c;margin-right:.3rem;"></i>Mais likes recebidos
          </div>
          <?= render_top_user($top_likes ?? null, 'likes') ?>
        </div>

        <!-- Link estatísticas -->
        <a href="<?= SITE_URL ?>/admin/estatisticas.php" class="btn btn-sm btn-verde" style="justify-content:center;margin-top:auto;">
          <i class="fas fa-chart-bar"></i> Estatísticas Completas
        </a>
      </div>
    </div>

    <!-- CARDS DE ESTATÍSTICAS -->
    <div class="admin-cards" style="grid-template-columns: repeat(6, 1fr); margin-bottom:2rem;">
      <a href="<?= SITE_URL ?>/admin/locais.php" style="text-decoration:none;">
        <div class="admin-stat-card" style="cursor:pointer;">
          <div class="num"><?= $total_locais ?></div>
          <div class="lbl">Locais Aprovados</div>
        </div>
      </a>
      <a href="<?= SITE_URL ?>/admin/utilizadores.php" style="text-decoration:none;">
        <div class="admin-stat-card" style="border-color:var(--dourado);cursor:pointer;">
          <div class="num" style="color:var(--dourado);"><?= $total_users ?></div>
          <div class="lbl">Utilizadores</div>
        </div>
      </a>
      <div class="admin-stat-card" style="border-color:var(--verde-claro);">
        <div class="num" style="color:var(--verde-claro);"><?= $total_comentarios ?></div>
        <div class="lbl">Comentários</div>
      </div>
      <a href="#denuncias" style="text-decoration:none;">
        <div class="admin-stat-card" style="border-color:#e74c3c;cursor:pointer;">
          <div class="num" style="color:#e74c3c;"><?= $total_denuncias ?></div>
          <div class="lbl">Denúncias Abertas</div>
        </div>
      </a>
      <a href="<?= SITE_URL ?>/admin/locais.php?bloqueado=1" style="text-decoration:none;">
        <div class="admin-stat-card" style="border-color:#8e44ad;cursor:pointer;">
          <div class="num" style="color:#8e44ad;"><?= $total_bloqueados ?></div>
          <div class="lbl">Locais Bloqueados</div>
        </div>
      </a>
      <a href="<?= SITE_URL ?>/admin/utilizadores.php?filtro=suspensos" style="text-decoration:none;">
        <div class="admin-stat-card" style="border-color:#e67e22;cursor:pointer;">
          <div class="num" style="color:#e67e22;"><?= $total_suspensos ?></div>
          <div class="lbl">Utilizadores Suspensos</div>
        </div>
      </a>
    </div>

    <!-- DENÚNCIAS ABERTAS -->
    <h2 style="font-size:1.3rem;margin-bottom:1rem;" id="denuncias">
      <i class="fas fa-flag"></i> Denúncias Abertas
    </h2>

    <?php if ($denuncias): ?>
    <table class="data-table">
      <thead>
        <tr>
          <th>Tipo</th><th>Ref.</th><th>Conteúdo</th><th>Motivo</th><th>Estado</th><th>Data</th><th>Ação Rápida</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($denuncias as $den): ?>
        <?php $bloqueado = ((int)$den['alvo_bloqueado'] === 1); ?>
        <tr>
          <td><span class="badge badge-cat"><?= h($den['tipo']) ?></span></td>
          <td>#<?= (int)$den['referencia_id'] ?></td>
          <td>
            <button type="button" class="btn btn-sm btn-verde"
                    onclick="abrirModalDenuncia(this)"
                    data-id="<?= (int)$den['id'] ?>"
                    data-tipo="<?= h($den['tipo']) ?>"
                    data-ref="#<?= (int)$den['referencia_id'] ?>"
                    data-conteudo="<?= h((string)($den['alvo_conteudo_completo'] ?? '[indisponível]')) ?>"
                    data-motivo="<?= h(motivo_denuncia_label((string)$den['motivo'])) ?>"
                    data-denunciante="<?= h($den['denunciante_username']) ?>"
                    data-bloqueado="<?= $bloqueado ? '1' : '0' ?>"
                    data-link="<?= !empty($den['alvo_local_id']) ? h(SITE_URL . '/pages/local.php?id=' . (int)$den['alvo_local_id']) : '' ?>">
              <i class="fas fa-eye"></i> Ver
            </button>
          </td>
          <td><?= h(motivo_denuncia_label((string)$den['motivo'])) ?></td>
          <td>
            <span class="badge <?= $bloqueado ? 'badge-rejeitado' : 'badge-aprovado' ?>">
              <?= $bloqueado ? 'Bloqueado' : 'Permitido' ?>
            </span>
          </td>
          <td><?= date('d/m/Y', strtotime($den['criado_em'])) ?></td>
          <td>
            <form method="POST" style="display:inline;">
              <input type="hidden" name="den_id" value="<?= (int)$den['id'] ?>">
              <button type="submit" name="devolver_denuncia" class="btn btn-sm"
                      style="border:1px solid var(--creme-escuro);color:var(--texto-muted);">
                <i class="fas fa-undo"></i> Devolver
              </button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php else: ?>
      <div class="empty-state" style="padding:2rem;">
        <i class="fas fa-shield-alt"></i>
        <h3>Sem denúncias abertas</h3>
      </div>
    <?php endif; ?>

  </main>
</div>
</div>

<!-- MODAL DENÚNCIA -->
<div id="modal-denuncia" style="display:none;position:fixed;inset:0;z-index:5000;background:rgba(0,0,0,.5);align-items:center;justify-content:center;padding:1rem;">
  <div style="background:#fff;border-radius:var(--radius-lg);width:100%;max-width:560px;box-shadow:0 8px 32px rgba(0,0,0,.2);display:flex;flex-direction:column;max-height:90vh;overflow:hidden;">
    <div style="display:flex;align-items:center;justify-content:space-between;padding:1.25rem 1.5rem;border-bottom:1px solid var(--creme-escuro);">
      <div>
        <h3 style="margin:0;font-size:1.05rem;">
          <i class="fas fa-flag" style="color:#e74c3c;margin-right:.4rem;"></i>
          Denúncia <span id="modal-den-ref" style="color:var(--texto-muted);font-size:.9rem;"></span>
        </h3>
        <div style="font-size:.82rem;color:var(--texto-muted);margin-top:.2rem;" id="modal-den-meta"></div>
      </div>
      <button onclick="fecharModalDenuncia()" style="background:none;border:none;font-size:1.2rem;cursor:pointer;color:var(--texto-muted);">
        <i class="fas fa-times"></i>
      </button>
    </div>
    <div style="padding:1.25rem 1.5rem;overflow-y:auto;flex:1;">
      <div style="display:flex;align-items:center;gap:.5rem;margin-bottom:1rem;">
        <span style="font-size:.8rem;font-weight:600;text-transform:uppercase;letter-spacing:.06em;color:var(--texto-muted);">Motivo:</span>
        <span id="modal-den-motivo" style="font-size:.9rem;font-weight:600;color:#e74c3c;"></span>
      </div>
      <div style="font-size:.8rem;font-weight:600;text-transform:uppercase;letter-spacing:.06em;color:var(--texto-muted);margin-bottom:.5rem;">Conteúdo denunciado</div>
      <div id="modal-den-conteudo" style="background:var(--creme);border:1.5px solid var(--creme-escuro);border-radius:10px;padding:1rem;font-size:.95rem;white-space:pre-wrap;line-height:1.6;margin-bottom:1.25rem;"></div>
      <a id="modal-den-link" href="#" target="_blank" rel="noopener" class="btn btn-sm btn-verde" style="display:none;">
        <i class="fas fa-external-link-alt"></i> Abrir local
      </a>
    </div>
    <div style="padding:1rem 1.5rem;border-top:1px solid var(--creme-escuro);display:flex;gap:.75rem;">
      <form method="POST" style="flex:1;">
        <input type="hidden" name="den_id" id="modal-input-den-id">
        <button type="submit" name="devolver_denuncia" class="btn btn-sm"
                style="width:100%;justify-content:center;border:1.5px solid var(--creme-escuro);color:var(--texto-muted);">
          <i class="fas fa-undo"></i> Devolver
        </button>
      </form>
      <form method="POST" style="flex:1;">
        <input type="hidden" name="tipo"   id="modal-input-tipo">
        <input type="hidden" name="ref_id" id="modal-input-ref-id">
        <input type="hidden" name="acao"   id="modal-input-acao">
        <button type="submit" name="moderar_denuncia_item" id="modal-btn-moderar"
                class="btn btn-sm btn-danger" style="width:100%;justify-content:center;"></button>
      </form>
    </div>
  </div>
</div>

<script>
function abrirModalDenuncia(btn) {
  const id = btn.getAttribute('data-id');
  const tipo = btn.getAttribute('data-tipo') || '';
  const ref = btn.getAttribute('data-ref') || '';
  const conteudo = btn.getAttribute('data-conteudo') || '[indisponível]';
  const motivo = btn.getAttribute('data-motivo') || '';
  const denunciante = btn.getAttribute('data-denunciante') || '';
  const bloqueado = btn.getAttribute('data-bloqueado') === '1';
  const link = btn.getAttribute('data-link') || '';

  document.getElementById('modal-den-ref').textContent = ref;
  document.getElementById('modal-den-meta').textContent = tipo.charAt(0).toUpperCase() + tipo.slice(1) + ' · Denunciado por @' + denunciante;
  document.getElementById('modal-den-conteudo').textContent = conteudo;
  document.getElementById('modal-den-motivo').textContent = motivo;

  const linkEl = document.getElementById('modal-den-link');
  if (link) { linkEl.href = link; linkEl.style.display = 'inline-flex'; }
  else { linkEl.style.display = 'none'; }

  document.getElementById('modal-input-den-id').value = id;
  document.getElementById('modal-input-tipo').value = tipo;
  document.getElementById('modal-input-ref-id').value = ref.replace('#', '');

  const btnModerar = document.getElementById('modal-btn-moderar');
  if (bloqueado) {
    document.getElementById('modal-input-acao').value = 'permitir';
    btnModerar.innerHTML = '<i class="fas fa-check"></i> Permitir Conteúdo';
    btnModerar.className = 'btn btn-sm btn-verde';
  } else {
    document.getElementById('modal-input-acao').value = 'bloquear';
    btnModerar.innerHTML = '<i class="fas fa-ban"></i> Bloquear Conteúdo';
    btnModerar.className = 'btn btn-sm btn-danger';
  }
  btnModerar.style.width = '100%';
  btnModerar.style.justifyContent = 'center';
  document.getElementById('modal-denuncia').style.display = 'flex';
}
function fecharModalDenuncia() {
  document.getElementById('modal-denuncia').style.display = 'none';
}
document.getElementById('modal-denuncia').addEventListener('click', function(e) {
  if (e.target === this) fecharModalDenuncia();
});
</script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
(function() {
  const meses = ['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];
  const dados = <?= $grafico_json ?>;
  const ctx = document.getElementById('grafico-publicacoes').getContext('2d');
  new Chart(ctx, {
    type: 'bar',
    data: {
      labels: meses,
      datasets: [{
        label: 'Publicações',
        data: dados,
        backgroundColor: 'rgba(201,168,76,0.25)',
        borderColor: '#c9a84c',
        borderWidth: 2,
        borderRadius: 6,
        borderSkipped: false,
      }]
    },
    options: {
      responsive: true,
      plugins: { legend: { display: false }, tooltip: { callbacks: { label: ctx => ' ' + ctx.parsed.y + (ctx.parsed.y === 1 ? ' publicação' : ' publicações') } } },
      scales: {
        x: { grid: { display: false }, ticks: { font: { family: 'Outfit', size: 12 }, color: '#6b7280' } },
        y: { beginAtZero: true, ticks: { stepSize: 1, precision: 0, font: { family: 'Outfit', size: 12 }, color: '#6b7280' }, grid: { color: 'rgba(0,0,0,.06)' } }
      }
    }
  });
})();
</script>

<style>
a:has(.admin-stat-card) { display:block; border-radius:inherit; }
a:has(.admin-stat-card) .admin-stat-card { transition: transform .18s ease, box-shadow .18s ease; }
a:has(.admin-stat-card):hover .admin-stat-card { transform: translateY(-5px); box-shadow: 0 8px 24px rgba(0,0,0,.13); }
</style>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>