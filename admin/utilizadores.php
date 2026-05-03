<?php
// ============================================================
// SEGREDO LUSITANO — Admin: Gerir Utilizadores
// Permite ao administrador ver, suspender, reativar e banir
// utilizadores, bem como consultar o registo de banidos.
// ============================================================
require_once dirname(__DIR__) . '/includes/functions.php';
require_admin();

$page_title = 'Admin · Utilizadores';

// ── Suspender / Reativar utilizador ──────────────────────
// Alterna o campo 'ativo' entre 0 (suspenso) e 1 (ativo)
if (isset($_GET['toggle'])) {
    $uid = (int)$_GET['toggle'];
    $st  = db()->prepare('SELECT ativo FROM utilizadores WHERE id = ?');
    $st->execute([$uid]);
    $row = $st->fetch();
    if ($row) {
        $novo_estado = (int)!$row['ativo'];
        db()->prepare('UPDATE utilizadores SET ativo = ? WHERE id = ?')->execute([$novo_estado, $uid]);
        flash('success', $novo_estado ? 'Conta reativada com sucesso.' : 'Conta suspensa com sucesso.');
    }
    header('Location: ' . SITE_URL . '/admin/utilizadores.php');
    exit;
}

// ── Banir utilizador ──────────────────────────────────────
// Guarda os dados do utilizador na tabela 'banidos',
// transfere o conteúdo para o utilizador fantasma (ID 1)
// e elimina a conta permanentemente da base de dados.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['banir_id'])) {
    $uid    = (int)$_POST['banir_id'];
    $motivo = trim($_POST['motivo'] ?? '');

    $st = db()->prepare('SELECT * FROM utilizadores WHERE id = ?');
    $st->execute([$uid]);
    $row = $st->fetch();

    if ($row && $row['role'] !== 'admin' && $motivo) {
        // Guardar registo do ban antes de eliminar a conta
        db()->prepare(
            'INSERT INTO banidos (nome, username, email, motivo)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE nome = VALUES(nome), username = VALUES(username), motivo = VALUES(motivo), banido_em = NOW()'
        )->execute([$row['nome'], $row['username'], $row['email'], $motivo]);

        // Transferir conteúdo do utilizador para o utilizador fantasma (ID: 1)
        db()->prepare('UPDATE locais      SET utilizador_id = 1 WHERE utilizador_id = ?')->execute([$uid]);
        db()->prepare('UPDATE comentarios SET utilizador_id = 1 WHERE utilizador_id = ?')->execute([$uid]);
        db()->prepare('UPDATE fotos       SET utilizador_id = 1 WHERE utilizador_id = ?')->execute([$uid]);

        // Eliminar a conta da base de dados
        db()->prepare('DELETE FROM utilizadores WHERE id = ?')->execute([$uid]);

        flash('success', 'Utilizador "' . $row['nome'] . '" foi banido com sucesso.');
    }
    header('Location: ' . SITE_URL . '/admin/utilizadores.php');
    exit;
}

// ── Desbanir utilizador ───────────────────────────────────
// Remove o registo da tabela 'banidos', permitindo que o email
// seja usado novamente para criar uma nova conta
if (isset($_GET['desbanir'])) {
    $bid = (int)$_GET['desbanir'];
    db()->prepare('DELETE FROM banidos WHERE id = ?')->execute([$bid]);
    flash('success', 'Utilizador desbanido. O email pode agora ser usado para criar uma nova conta.');
    header('Location: ' . SITE_URL . '/admin/utilizadores.php?filtro=banidos');
    exit;
}

// ── Filtro e pesquisa ─────────────────────────────────────
$filtro   = $_GET['filtro'] ?? 'todos';
$pesquisa = trim($_GET['q'] ?? '');

// ── Buscar utilizadores conforme o filtro ────────────────
if ($filtro === 'banidos') {
    // Mostrar lista de banidos (tabela separada)
    $where  = '';
    $params = [];
    if ($pesquisa) {
        $where  = 'WHERE nome LIKE ? OR username LIKE ? OR email LIKE ?';
        $params = ["%$pesquisa%", "%$pesquisa%", "%$pesquisa%"];
    }
    $st = db()->prepare("SELECT * FROM banidos $where ORDER BY banido_em DESC");
    $st->execute($params);
    $banidos = $st->fetchAll();
    $users   = [];
} else {
    $banidos = [];

    // Filtrar por estado: suspensos ou todos os utilizadores normais
    if ($filtro === 'suspensos') {
        $where = 'WHERE u.ativo = 0 AND u.role = "user"';
    } else {
        $where = 'WHERE u.role = "user"';
    }

    $params = [];
    if ($pesquisa) {
        $where  .= ' AND (u.nome LIKE ? OR u.username LIKE ? OR u.email LIKE ?)';
        $params  = ["%$pesquisa%", "%$pesquisa%", "%$pesquisa%"];
    }

    $st = db()->prepare(
        "SELECT u.*,
                (SELECT COUNT(*) FROM locais WHERE utilizador_id = u.id AND estado = 'aprovado') AS total_locais
         FROM utilizadores u
         $where
         ORDER BY u.criado_em DESC"
    );
    $st->execute($params);
    $users = $st->fetchAll();
}

// ── Mapa de motivos de ban para texto legível ─────────────
$motivos_ban = [
    'spam'                  => 'Spam',
    'comportamento_abusivo' => 'Comportamento abusivo',
    'conteudo_inapropriado' => 'Conteúdo inapropriado',
    'fraude'                => 'Fraude',
    'outro'                 => 'Outro',
];

include dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-content">
<div class="admin-wrapper">

  <!-- ── SIDEBAR ── -->
  <aside class="admin-sidebar">
    <div style="color:var(--dourado);font-family:'Playfair Display',serif;font-size:1.1rem;font-weight:700;margin-bottom:1.5rem;padding:.5rem .85rem;">
      <i class="fas fa-shield-alt"></i> Administração
    </div>
    <nav class="admin-nav">
      <div class="nav-section">Geral</div>
      <a href="<?= SITE_URL ?>/admin/index.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
      <a href="<?= SITE_URL ?>/admin/locais.php"><i class="fa-solid fa-location-dot"></i> Locais</a>
      <a href="<?= SITE_URL ?>/admin/utilizadores.php" class="active"><i class="fas fa-users"></i> Utilizadores</a>
      <a href="<?= SITE_URL ?>/admin/estatisticas.php"><i class="fas fa-chart-bar"></i> Estatísticas</a>
    </nav>
  </aside>

  <!-- ── CONTEÚDO PRINCIPAL ── -->
  <main class="admin-content">

    <!-- Cabeçalho com título e filtros -->
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;flex-wrap:wrap;gap:1rem;">
      <h1 class="admin-title" style="margin:0;"><i class="fas fa-users"></i>  Utilizadores</h1>

      <!-- Botões de filtro: Todos / Suspensos / Banidos -->
      <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
        <a href="?" class="btn btn-sm <?= $filtro === 'todos' ? 'btn-verde' : '' ?>"
           style="<?= $filtro !== 'todos' ? 'border:1px solid var(--creme-escuro);color:var(--texto-muted);' : '' ?>">
          Todos
        </a>
        <a href="?filtro=suspensos" class="btn btn-sm"
           style="<?= $filtro === 'suspensos' ? 'background:#e67e22;color:#fff;border:none;' : 'border:1px solid var(--creme-escuro);color:var(--texto-muted);' ?>">
          Suspensos
        </a>
        <a href="?filtro=banidos" class="btn btn-sm"
           style="<?= $filtro === 'banidos' ? 'background:#7d0000;color:#fff;border:none;' : 'border:1px solid var(--creme-escuro);color:var(--texto-muted);' ?>">
          Banidos
        </a>
      </div>
    </div>

    <!-- Barra de pesquisa por nome, username ou email -->
    <form method="GET" style="margin-bottom:1.25rem;">
      <?php if ($filtro !== 'todos'): ?>
        <input type="hidden" name="filtro" value="<?= h($filtro) ?>">
      <?php endif; ?>
      <div style="display:flex;align-items:center;gap:.5rem;background:var(--creme);border:2px solid var(--verde-claro);border-radius:8px;padding:.4rem .75rem;max-width:400px;">
        <i class="fas fa-search" style="color:var(--texto-muted);font-size:.85rem;"></i>
        <input type="text" name="q" value="<?= h($pesquisa) ?>" placeholder="Pesquisar utilizadores..."
               style="border:none;background:transparent;outline:none;font-size:.9rem;width:100%;">
        <?php if ($pesquisa): ?>
          <a href="?<?= $filtro !== 'todos' ? 'filtro=' . h($filtro) : '' ?>"
             style="color:var(--texto-muted);font-size:.85rem;text-decoration:none;flex-shrink:0;">
            <i class="fas fa-times"></i>
          </a>
        <?php endif; ?>
      </div>
    </form>

    <!-- ── TABELA DE BANIDOS ── -->
    <?php if ($filtro === 'banidos'): ?>
    <table class="data-table">
      <thead>
        <tr>
          <th>Nome</th>
          <th>Username</th>
          <th>Email</th>
          <th>Motivo</th>
          <th>Banido em</th>
          <th>Ações</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($banidos as $b): ?>
        <tr>
          <td><?= h($b['nome']) ?></td>
          <td>@<?= h($b['username']) ?></td>
          <td><?= h($b['email']) ?></td>
          <td><?= h($motivos_ban[$b['motivo']] ?? $b['motivo']) ?></td>
          <td><?= date('d/m/Y H:i', strtotime($b['banido_em'])) ?></td>
          <td>
            <!-- Desbanir: remove da tabela banidos para permitir novo registo -->
            <a href="?desbanir=<?= $b['id'] ?>"
               class="btn btn-sm btn-primary"
               onclick="return confirm('Tens a certeza que queres desbanir <?= h($b['nome']) ?>?')"
               title="Desbanir">
              <i class="fas fa-user-check"></i> Desbanir
            </a>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$banidos): ?>
          <tr><td colspan="6" style="text-align:center;color:var(--texto-muted);padding:2rem;">Nenhum utilizador banido.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>

    <!-- ── TABELA DE UTILIZADORES NORMAIS ── -->
    <?php else: ?>
    <table class="data-table">
      <thead>
        <tr>
          <th>Nome</th>
          <th>Username</th>
          <th>Email</th>
          <th>Pontos</th>
          <th>Locais</th>
          <th>Estado</th>
          <th>Ações</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($users as $u): ?>
        <tr>
          <td>
            <a href="<?= SITE_URL ?>/pages/perfil.php?id=<?= $u['id'] ?>" style="color:var(--verde);font-weight:600;">
              <?= h($u['nome']) ?>
            </a>
          </td>
          <td>@<?= h($u['username']) ?></td>
          <td><?= h($u['email']) ?></td>
          <td><?= number_format((int)$u['pontos']) ?></td>
          <td><?= (int)$u['total_locais'] ?></td>
          <td>
            <span style="color:<?= $u['ativo'] ? '#27ae60' : '#e74c3c' ?>;font-weight:700;">
              <?= $u['ativo'] ? 'Ativo' : 'Suspenso' ?>
            </span>
          </td>
          <td>
            <!-- Ações disponíveis: ver perfil, suspender/reativar, banir -->
            <div style="display:flex;gap:.35rem;flex-wrap:wrap;align-items:center;">
              <!-- Ver perfil público do utilizador -->
              <a href="<?= SITE_URL ?>/pages/perfil.php?id=<?= $u['id'] ?>"
                 class="btn btn-sm btn-verde" title="Ver perfil">
                <i class="fas fa-eye"></i>
              </a>
              <?php if ($u['role'] !== 'admin'): ?>
                <!-- Suspender (se ativo) ou Reativar (se suspenso) conta -->
                <a href="?toggle=<?= $u['id'] ?>&filtro=<?= h($filtro) ?><?= $pesquisa ? '&q=' . urlencode($pesquisa) : '' ?>"
                   class="btn btn-sm <?= $u['ativo'] ? 'btn-danger' : 'btn-primary' ?>"
                   title="<?= $u['ativo'] ? 'Suspender conta' : 'Reativar conta' ?>">
                  <i class="fas fa-<?= $u['ativo'] ? 'ban' : 'check' ?>"></i>
                </a>
                <!-- Banir utilizador permanentemente — abre modal para escolher motivo -->
                <button onclick="abrirModalBan(<?= $u['id'] ?>, '<?= h($u['nome']) ?>')"
                        class="btn btn-sm"
                        title="Banir utilizador"
                        style="background:#7d0000;color:#fff;display:inline-flex;align-items:center;justify-content:center;">
                  <i class="fas fa-user-alt-slash"></i>
                </button>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$users): ?>
          <tr><td colspan="7" style="text-align:center;color:var(--texto-muted);padding:2rem;">Sem utilizadores.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
    <?php endif; ?>

  </main>
</div>
</div>

<!-- ── MODAL DE BAN ── -->
<!-- Aparece ao clicar no botão de banir, permite escolher o motivo -->
<div id="modal-ban" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:5000;align-items:center;justify-content:center;padding:1rem;">
  <div style="background:#fff;border-radius:var(--radius-lg);padding:1.25rem 1.5rem;max-width:390px;width:100%;box-shadow:0 8px 32px rgba(0,0,0,.2);">

    <!-- Cabeçalho do modal -->
    <div style="display:flex;align-items:center;gap:.6rem;margin-bottom:1rem;">
      <i class="fas fa-user-alt-slash" style="color:#7d0000;font-size:1.2rem;"></i>
      <h3 style="margin:0;font-size:1.1rem;">Banir Utilizador</h3>
    </div>

    <!-- Mensagem de confirmação com o nome do utilizador -->
    <p id="modal-ban-nome" style="margin-bottom:1.25rem;color:var(--texto-muted);font-size:.9rem;word-break:break-word;overflow-wrap:break-word;"></p>

    <form method="POST">
      <input type="hidden" name="banir_id" id="modal-ban-id">

      <!-- Seleção de motivo do ban (obrigatório) -->
      <div style="margin-bottom:1.25rem;">
        <label style="font-weight:600;margin-bottom:.5rem;display:block;">Motivo do ban</label>
        <div style="display:flex;flex-direction:column;gap:.5rem;">
          <?php foreach ($motivos_ban as $valor => $rotulo): ?>
            <label style="display:flex;align-items:center;gap:.75rem;padding:.65rem .75rem;
                          border:1.5px solid var(--creme-escuro);border-radius:var(--radius);cursor:pointer;font-weight:normal;font-size:.9rem;width:100%;box-sizing:border-box;"
                   onmouseover="this.style.borderColor='var(--verde)';this.style.background='var(--creme)'"
                   onmouseout="this.style.borderColor='var(--creme-escuro)';this.style.background='#fff'">
              <input type="radio" name="motivo" value="<?= $valor ?>" required style="accent-color:#7d0000;flex-shrink:0;width:auto;">
              <span><?= $rotulo ?></span>
            </label>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Botões de ação: confirmar ban ou cancelar -->
      <div style="display:flex;gap:.75rem;margin-top:1.25rem;align-items:center;">
        <button type="submit" class="btn btn-danger"
                style="flex:1;justify-content:center;background:#7d0000;border:none;">
          <i class="fas fa-user-alt-slash"></i> Confirmar Ban
        </button>
        <button type="button"
                onclick="document.getElementById('modal-ban').style.display='none'"
                class="btn btn-sm"
                style="border:1.5px solid var(--creme-escuro);color:var(--texto-muted);white-space:nowrap;">
          Cancelar
        </button>
      </div>
    </form>
  </div>
</div>

<script>
// Preenche e abre o modal de ban com os dados do utilizador selecionado
function abrirModalBan(id, nome) {
  document.getElementById('modal-ban-id').value = id;
  document.getElementById('modal-ban-nome').textContent =
    'Tens a certeza que queres banir "' + nome + '"? Esta ação é irreversível.';
  // Limpar seleção anterior de motivo para evitar ban acidental com motivo errado
  document.querySelectorAll('#modal-ban input[name="motivo"]').forEach(r => r.checked = false);
  document.getElementById('modal-ban').style.display = 'flex';
}
</script>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>