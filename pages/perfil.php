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

// ── Seguidores ──────────────────────────────────────────────
$st_seg = db()->prepare('SELECT COUNT(*) FROM seguidores WHERE seguido_id = ?');
$st_seg->execute([$id]);
$total_seguidores = (int)$st_seg->fetchColumn();

$st_seg2 = db()->prepare('SELECT COUNT(*) FROM seguidores WHERE seguidor_id = ?');
$st_seg2->execute([$id]);
$total_seguidos = (int)$st_seg2->fetchColumn();

// Verificar se o utilizador autenticado já segue este perfil
$ja_segue = false;
if ($user_auth && $user_auth['id'] !== $id) {
    $st_check = db()->prepare('SELECT id FROM seguidores WHERE seguidor_id = ? AND seguido_id = ?');
    $st_check->execute([$user_auth['id'], $id]);
    $ja_segue = (bool)$st_check->fetch();
}

// Lista de seguidores (avatares)
$st_lista_seg = db()->prepare(
    'SELECT u.id, u.username, u.nome, u.avatar
     FROM seguidores s JOIN utilizadores u ON u.id = s.seguidor_id
     WHERE s.seguido_id = ? AND u.ativo = 1 ORDER BY s.criado_em DESC LIMIT 50'
);
$st_lista_seg->execute([$id]);
$lista_seguidores = $st_lista_seg->fetchAll();

// Lista de seguidos
$st_lista_seguidos = db()->prepare(
    'SELECT u.id, u.username, u.nome, u.avatar
     FROM seguidores s JOIN utilizadores u ON u.id = s.seguido_id
     WHERE s.seguidor_id = ? AND u.ativo = 1 ORDER BY s.criado_em DESC LIMIT 50'
);
$st_lista_seguidos->execute([$id]);
$lista_seguidos = $st_lista_seguidos->fetchAll();
// ────────────────────────────────────────────────────────────

// Apagar conta (só o próprio)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apagar_conta']) && $user_auth && $user_auth['id'] == $id) {
    db()->prepare('UPDATE locais SET utilizador_id = 1 WHERE utilizador_id = ?')->execute([$id]);
    db()->prepare('UPDATE comentarios SET utilizador_id = 1 WHERE utilizador_id = ?')->execute([$id]);
    db()->prepare('UPDATE fotos SET utilizador_id = 1 WHERE utilizador_id = ?')->execute([$id]);
    db()->prepare('DELETE FROM utilizadores WHERE id = ?')->execute([$id]);
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
  <p class="perfil-username"><?= h($perfil['username']) ?> &middot; Explorador nº <?= $rank_pos ?></p>
  <?php if ($perfil['bio']): ?>
    <p class="perfil-bio text-wrap-anywhere"><?= nl2br(h($perfil['bio'])) ?></p>
  <?php endif; ?>

  <!-- Botão Seguir -->
  <?php if ($user_auth && !$is_own): ?>
    <div style="margin-bottom:1rem;">
      <button id="btn-seguir"
              data-id="<?= $id ?>"
              data-seguindo="<?= $ja_segue ? '1' : '0' ?>"
              class="btn btn-sm <?= $ja_segue ? '' : 'btn-primary' ?>"
              style="<?= $ja_segue ? 'border:1.5px solid var(--creme-escuro);color:var(--texto-muted);' : '' ?>">
        <i class="fas <?= $ja_segue ? 'fa-user-check' : 'fa-user-plus' ?>"></i>
        <span><?= $ja_segue ? 'A Seguir' : 'Seguir' ?></span>
      </button>
    </div>
  <?php elseif ($is_own): ?>
    <div style="margin-bottom:1rem;">
      <a href="<?= SITE_URL ?>/pages/perfil_editar.php" class="btn btn-sm btn-primary">
        <i class="fas fa-user-edit"></i> Editar Perfil
      </a>
    </div>
  <?php endif; ?>

  <!-- Stats -->
  <div class="perfil-stats">
    <div class="stat-item"><span class="stat-num"><?= count($locais_perfil) ?></span><span class="stat-label">Locais</span></div>
    <div class="stat-item"><span class="stat-num"><?= $total_likes ?></span><span class="stat-label">Likes Recebidos</span></div>
    <div class="stat-item"><span class="stat-num"><?= number_format($perfil['pontos']) ?></span><span class="stat-label">Pontos</span></div>
    <div class="stat-item"><span class="stat-num"><?= $rank_pos ?>º</span><span class="stat-label">Ranking</span></div>
    <div class="stat-item" style="cursor:pointer;" onclick="abrirModalSeg('seguidores')">
      <span class="stat-num" id="total-seguidores"><?= $total_seguidores ?></span>
      <span class="stat-label">Seguidores</span>
    </div>
    <div class="stat-item" style="cursor:pointer;" onclick="abrirModalSeg('seguidos')">
      <span class="stat-num"><?= $total_seguidos ?></span>
      <span class="stat-label">A seguir</span>
    </div>
  </div>
</div>

<!-- MODAL SEGUIDORES / SEGUIDOS -->
<div id="modal-seg" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:4000;align-items:center;justify-content:center;padding:1rem;">
  <div style="background:#fff;border-radius:var(--radius-lg);width:100%;max-width:420px;max-height:80vh;display:flex;flex-direction:column;">
    <div style="display:flex;align-items:center;justify-content:space-between;padding:1rem 1.25rem;border-bottom:1px solid var(--creme-escuro);">
      <h3 style="margin:0;font-size:1rem;" id="modal-seg-titulo">Seguidores</h3>
      <button onclick="document.getElementById('modal-seg').style.display='none'"
              style="background:none;border:none;font-size:1.2rem;cursor:pointer;color:var(--texto-muted);">
        <i class="fas fa-times"></i>
      </button>
    </div>
    <div style="padding:.75rem 1.25rem;border-bottom:1px solid var(--creme-escuro);">
      <div style="display:flex;align-items:center;gap:.5rem;background:var(--creme);border:1.5px solid var(--creme-escuro);border-radius:8px;padding:.4rem .75rem;">
        <i class="fas fa-search" style="color:var(--texto-muted);font-size:.85rem;"></i>
        <input type="text" id="modal-seg-pesquisa" placeholder="Pesquisar..."
              style="border:none;background:transparent;outline:none;font-size:.9rem;width:100%;">
      </div>
    </div>
    <div style="overflow-y:auto;padding:1rem 1.25rem;display:flex;flex-direction:column;gap:.75rem;" id="modal-seg-lista"></div>
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
          if ($is_own || is_admin()): ?>
            <article class="card" style="<?= $local['estado'] !== 'aprovado' ? 'opacity:.75' : '' ?>">
              <a href="<?= SITE_URL ?>/pages/local.php?id=<?= $local['id'] ?>" class="card-img" style="display:block;">
                <?php if ($local['foto_capa']): ?>
                  <img src="<?= SITE_URL ?>/uploads/locais/<?= h($local['foto_capa']) ?>" alt="<?= h(local_nome_publico($local)) ?>">
                <?php else: ?>
                  <div class="card-img-placeholder"><i class="<?= h($local['categoria_icone']) ?>"></i></div>
                <?php endif; ?>
                <div class="card-badges">
                  <span class="badge badge-<?= $local['estado'] ?>"><?= ucfirst($local['estado']) ?></span>
                </div>
              </a>
              <div class="card-body">
                <h3 class="card-title"><a href="<?= SITE_URL ?>/pages/local.php?id=<?= $local['id'] ?>"><?= h(local_nome_publico($local)) ?></a></h3>
                <div class="card-meta">
                  <span style="font-size:.82rem;color:var(--texto-muted);"><?= h($local['regiao_nome']) ?></span>
                  <div class="card-meta-stats">
                    <span><i class="fas fa-heart"></i> <?= $local['total_likes'] ?></span>
                  </div>
                </div>
              </div>
            </article>
          <?php else: ?>
            <?php $ocultar_btn_seguir = !$is_own; ?>
            <?php include dirname(__DIR__) . '/includes/card_local.php'; ?>
            <?php $ocultar_btn_seguir = false; ?>
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
    <div style="margin-top:4rem; padding:1.5rem; border:1.5px solid #e74c3c; border-radius:var(--radius); max-width:300px;">
      <h3 style="color:#c0392b; margin-bottom:.5rem;">Zona de Perigo</h3>
      <p style="font-size:.9rem; color:var(--texto-muted); margin-bottom:1rem;">Todos os teus dados serão apagados</p>
      <form method="POST" onsubmit="return confirm('Tens a certeza?');">
        <input type="hidden" name="apagar_conta" value="1">
        <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i> Apagar a Minha Conta</button>
      </form>
    </div>
    <?php endif; ?>
  </div>
</section>
</div>

<script>
// Dados das listas para o modal
const dadosSeguidores = <?= json_encode($lista_seguidores) ?>;
const dadosSeguidos   = <?= json_encode($lista_seguidos) ?>;
const SITE_URL_JS     = "<?= SITE_URL ?>";

function abrirModalSeg(tipo) {
  const lista = tipo === 'seguidores' ? dadosSeguidores : dadosSeguidos;
  const titulo = tipo === 'seguidores' ? 'Seguidores' : 'A Seguir';
  document.getElementById('modal-seg-titulo').textContent = titulo;
  const el = document.getElementById('modal-seg-lista');
  if (lista.length === 0) {
    el.innerHTML = '<p style="color:var(--texto-muted);text-align:center;padding:1rem;">Nenhum utilizador.</p>';
  } else {
    el.innerHTML = lista.map(u => `
      <a href="${SITE_URL_JS}/pages/perfil.php?id=${u.id}"
         style="display:flex;align-items:center;gap:.75rem;text-decoration:none;color:inherit;
                padding:.5rem;border-radius:var(--radius);transition:background .15s;"
         onmouseover="this.style.background='var(--creme)'"
         onmouseout="this.style.background='transparent'">
        <div style="width:40px;height:40px;border-radius:50%;background:var(--verde-escuro);
                    color:var(--dourado);display:flex;align-items:center;justify-content:center;
                    font-weight:700;font-size:1rem;flex-shrink:0;overflow:hidden;">
          ${u.avatar
            ? `<img src="${SITE_URL_JS}/uploads/locais/${u.avatar}" style="width:100%;height:100%;object-fit:cover;">`
            : u.username.charAt(0).toUpperCase()}
        </div>
        <div>
          <div style="font-weight:600;">${u.nome}</div>
          <div style="font-size:.82rem;color:var(--texto-muted);">@${u.username}</div>
        </div>
      </a>
    `).join('');
  }
  document.getElementById('modal-seg').style.display = 'flex';
  // Pesquisar utilizadores na lista
  const inputSeg = document.getElementById('modal-seg-pesquisa');
  if (inputSeg) {
    inputSeg.value = '';
    inputSeg.oninput = function() {
      const termo = this.value.toLowerCase();
      document.querySelectorAll('#modal-seg-lista a').forEach(item => {
        const nome = item.textContent.toLowerCase();
        item.style.display = nome.includes(termo) ? 'flex' : 'none';
      });
    };
  }
}

// Botão Seguir
const btnSeguir = document.getElementById('btn-seguir');
if (btnSeguir) {
  btnSeguir.addEventListener('click', async () => {
    const id = btnSeguir.dataset.id;
    const res = await fetch(`${SITE_URL_JS}/pages/seguir.php`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: `id=${id}`
    });
    const data = await res.json();
    if (!data.ok) return;

    const aSeguir = data.a_seguir;
    btnSeguir.dataset.seguindo = aSeguir ? '1' : '0';
    btnSeguir.className = 'btn btn-sm ' + (aSeguir ? '' : 'btn-primary');
    btnSeguir.style.cssText = aSeguir ? 'border:1.5px solid var(--creme-escuro);color:var(--texto-muted);' : '';
    btnSeguir.innerHTML = `<i class="fas ${aSeguir ? 'fa-user-check' : 'fa-user-plus'}"></i> <span>${aSeguir ? 'A Seguir' : 'Seguir'}</span>`;

    // Atualizar contador
    const el = document.getElementById('total-seguidores');
    if (el) el.textContent = data.total;
  });
}
</script>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>