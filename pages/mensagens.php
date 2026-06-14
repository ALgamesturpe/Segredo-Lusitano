<?php
require_once dirname(__DIR__) . '/includes/functions.php';
require_login();

$user = auth_user();
$uid  = (int)$user['id'];
$conversa_id = isset($_GET['com']) ? (int)$_GET['com'] : 0;

// Marcar como lidas ANTES de carregar a lista (para o badge já sair a zero)
if ($conversa_id) {
    db()->prepare('UPDATE mensagens SET lida=1 WHERE remetente_id=? AND destinatario_id=? AND lida=0')
       ->execute([$conversa_id, $uid]);
}

$st = db()->prepare(
    'SELECT DISTINCT
        u.id, u.nome, u.username, u.avatar,
        (SELECT COUNT(*) FROM mensagens m
         WHERE m.remetente_id = u.id AND m.destinatario_id = ? AND m.lida = 0) AS nao_lidas,
        (SELECT MAX(criado_em) FROM mensagens m
         WHERE (m.remetente_id = u.id AND m.destinatario_id = ?)
            OR (m.remetente_id = ? AND m.destinatario_id = u.id)) AS ultima_msg
     FROM utilizadores u
     JOIN seguidores s1 ON s1.seguidor_id = ? AND s1.seguido_id = u.id
     JOIN seguidores s2 ON s2.seguidor_id = u.id AND s2.seguido_id = ?
     WHERE u.id != ? AND u.ativo = 1 AND u.role != "[deleted]"
     ORDER BY ISNULL(ultima_msg) ASC, ultima_msg DESC, u.nome ASC'
);
$st->execute([$uid, $uid, $uid, $uid, $uid, $uid]);
$conversas = $st->fetchAll();

$outro_user = null;
$mensagens  = [];
if ($conversa_id) {
    $stChk = db()->prepare(
        'SELECT (
            SELECT COUNT(*) FROM seguidores s1
            JOIN seguidores s2 ON s2.seguidor_id=? AND s2.seguido_id=?
            WHERE s1.seguidor_id=? AND s1.seguido_id=?
        ) + (
            SELECT COUNT(*) FROM mensagens
            WHERE (remetente_id=? AND destinatario_id=?)
               OR (remetente_id=? AND destinatario_id=?)
            LIMIT 1
        ) AS total'
    );
    $stChk->execute([$conversa_id, $uid, $uid, $conversa_id, $uid, $conversa_id, $conversa_id, $uid]);

    if ((int)$stChk->fetchColumn() < 1) {
        $conversa_id = 0;
    } else {
        $stU = db()->prepare('SELECT id, nome, username, avatar FROM utilizadores WHERE id = ?');
        $stU->execute([$conversa_id]);
        $outro_user = $stU->fetch() ?: null;

        $stM = db()->prepare(
            'SELECT m.*, u.username AS remetente_username, u.avatar AS remetente_avatar,
                    l.nome AS local_nome, l.foto_capa AS local_foto,
                    r.nome AS local_regiao, c.nome AS local_categoria
             FROM mensagens m
             JOIN utilizadores u ON u.id = m.remetente_id
             LEFT JOIN locais l ON l.id = m.local_id
             LEFT JOIN regioes r ON r.id = l.regiao_id
             LEFT JOIN categorias c ON c.id = l.categoria_id
             WHERE ((m.remetente_id = ? AND m.destinatario_id = ?)
                OR  (m.remetente_id = ? AND m.destinatario_id = ?))
               AND NOT (m.destinatario_id = ? AND m.apagada_por_receptor = 1)
             ORDER BY m.criado_em ASC'
        );
        $stM->execute([$uid, $conversa_id, $conversa_id, $uid, $uid]);
        $mensagens = $stM->fetchAll();

        db()->prepare(
            'UPDATE mensagens SET lida = 1
             WHERE remetente_id = ? AND destinatario_id = ? AND lida = 0'
        )->execute([$conversa_id, $uid]);
    }
}

$tem_conversa_ativa = ($conversa_id > 0 && $outro_user !== null);
$page_title = 'Mensagens';
include dirname(__DIR__) . '/includes/header.php';
?>
<style>
/* Chat: sem padding extra, sem footer, sem scroll na página */
.page-content { padding-top: 0 !important; }
.site-footer   { display: none !important; }
html, body     { overflow: hidden; }

/* Chat cola à navbar em cima e ao fundo do ecrã em baixo */
#chat-layout {
  position: fixed;
  top: var(--nav-h);
  left: 0;
  right: 0;
  bottom: 0;
  display: flex;
  flex-direction: row;
  overflow: hidden;
}

/* Barra de input: segura acima do indicador home no iPhone X+ */
#chat-input-bar {
  padding-bottom: max(1rem, env(safe-area-inset-bottom));
  flex-shrink: 0;
}

.msg-bubble {
  max-width: 70%;
  padding: .65rem 1rem;
  font-size: .92rem;
  line-height: 1.5;
  word-break: break-word;
  position: relative;
}
.msg-bubble-own {
  background: var(--verde);
  color: #fff;
  border-radius: 12px 12px 0 12px;
}
.msg-bubble-own::after {
  content: '';
  position: absolute;
  bottom: 0; right: -8px;
  width: 0; height: 0;
  border-top: 8px solid transparent;
  border-left: 8px solid var(--verde);
}
.msg-bubble-other {
  background: #fff;
  color: var(--texto);
  border-radius: 12px 12px 12px 0;
  box-shadow: 0 1px 2px rgba(0,0,0,.08);
}
.msg-bubble-other::after {
  content: '';
  position: absolute;
  bottom: 0; left: -8px;
  width: 0; height: 0;
  border-top: 8px solid transparent;
  border-right: 8px solid #fff;
}
</style>
<div class="page-content">
<div id="chat-layout" style="-webkit-overflow-scrolling:touch;">

  <!-- ── LISTA DE CONVERSAS ── -->
  <div id="painel-lista" style="width:320px;flex-shrink:0;background:var(--branco);border-right:1px solid var(--creme-escuro);display:flex;flex-direction:column;overflow:hidden;">
    <div style="padding:1.25rem 1.5rem;border-bottom:1px solid var(--creme-escuro);background:var(--verde-escuro);">
      <h2 style="margin:0;font-size:1.1rem;color:var(--creme);display:flex;align-items:center;gap:.5rem;">
        <i class="fas fa-comments" style="color:var(--dourado);"></i> Mensagens
      </h2>
    </div>
    <div style="overflow-y:auto;flex:1;">
      <?php if ($conversas): ?>
        <?php foreach ($conversas as $conv): ?>
          <a href="?com=<?= $conv['id'] ?>"
             style="display:flex;align-items:center;gap:.85rem;padding:1rem 1.25rem;
                    border-bottom:1px solid var(--creme-escuro);text-decoration:none;
                    background:<?= $conv['id'] == $conversa_id ? 'var(--creme)' : '#fff' ?>;transition:background .15s;">
            <div style="width:44px;height:44px;border-radius:50%;flex-shrink:0;background:var(--verde-claro);color:#fff;overflow:hidden;display:flex;align-items:center;justify-content:center;font-weight:700;">
              <?php if (!empty($conv['avatar'])): ?>
                <img src="<?= SITE_URL ?>/uploads/locais/<?= h($conv['avatar']) ?>" style="width:100%;height:100%;object-fit:cover;">
              <?php else: ?>
                <?= mb_strtoupper(mb_substr($conv['username'],0,1)) ?>
              <?php endif; ?>
            </div>
            <div style="flex:1;min-width:0;">
              <div style="font-weight:600;font-size:.92rem;color:var(--texto);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= h($conv['nome']) ?></div>
              <div style="font-size:.8rem;color:var(--texto-muted);">@<?= h($conv['username']) ?></div>
            </div>
            <?php if ($conv['nao_lidas'] > 0): ?>
              <span style="background:#e74c3c;color:#fff;border-radius:0;padding:.15rem .5rem;font-size:.75rem;font-weight:700;flex-shrink:0;"><?= $conv['nao_lidas'] ?></span>
            <?php endif; ?>
          </a>
        <?php endforeach; ?>
      <?php else: ?>
        <div style="padding:2rem;text-align:center;color:var(--texto-muted);font-size:.9rem;">
          <i class="fas fa-user-friends" style="font-size:2rem;margin-bottom:.75rem;display:block;color:var(--verde-brilho);"></i>
          Segue utilizadores e espera que te sigam de volta para poder enviar mensagens.
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- ── ÁREA DE CHAT ── -->
  <div id="painel-chat" style="flex:1;display:flex;flex-direction:column;overflow:hidden;background:var(--creme);">
    <?php if ($outro_user): ?>
      <div style="padding:1rem 1.5rem;background:var(--branco);border-bottom:1px solid var(--creme-escuro);display:flex;align-items:center;gap:.85rem;">
        <button id="btn-voltar" onclick="voltarLista()"
                style="display:none;background:none;border:none;color:var(--verde);font-size:1.2rem;cursor:pointer;padding:.25rem .5rem .25rem 0;flex-shrink:0;">
          <i class="fas fa-arrow-left"></i>
        </button>
        <div style="width:40px;height:40px;border-radius:50%;background:var(--verde-claro);color:#fff;overflow:hidden;display:flex;align-items:center;justify-content:center;font-weight:700;flex-shrink:0;">
          <?php if (!empty($outro_user['avatar'])): ?>
            <img src="<?= SITE_URL ?>/uploads/locais/<?= h($outro_user['avatar']) ?>" style="width:100%;height:100%;object-fit:cover;">
          <?php else: ?>
            <?= mb_strtoupper(mb_substr($outro_user['username'],0,1)) ?>
          <?php endif; ?>
        </div>
        <div>
          <div style="font-weight:700;font-size:.95rem;"><?= h($outro_user['nome']) ?></div>
          <a href="<?= SITE_URL ?>/pages/perfil.php?id=<?= $outro_user['id'] ?>" style="color:var(--verde);font-size:.8rem;">@<?= h($outro_user['username']) ?></a>
        </div>
      </div>

      <div id="chat-mensagens" style="flex:1;overflow-y:auto;padding:1.25rem;display:flex;flex-direction:column;gap:.75rem;">
        <?php foreach ($mensagens as $msg): ?>
          <?php $propria   = ((int)$msg['remetente_id'] === $uid); ?>
          <?php $apagada   = !empty($msg['apagada_para_todos']); ?>
          <?php $isImg     = !$apagada && !empty($msg['ficheiro']) && preg_match('/\.(jpg|jpeg|png|webp|gif)$/i', $msg['ficheiro']); ?>
          <?php $isFich    = !$apagada && !empty($msg['ficheiro']) && !$isImg; ?>
          <?php $isLocal   = !$apagada && !empty($msg['local_id']); ?>
          <div style="display:flex;justify-content:<?= $propria ? 'flex-end' : 'flex-start' ?>;position:relative;align-items:center;gap:6px;"
               data-msg-id="<?= $msg['id'] ?>" data-propria="<?= $propria ? '1' : '0' ?>" data-apagada="<?= $apagada ? '1' : '0' ?>">

            <?php if ($propria && !$apagada): ?>
            <div class="msg-opts-wrap" style="position:relative;order:-1;">
              <button class="btn-msg-opts"
                      style="display:none;background:var(--branco);color:var(--texto-muted);border:1px solid var(--creme-escuro);
                             border-radius:0;width:26px;height:26px;cursor:pointer;font-size:.72rem;
                             align-items:center;justify-content:center;">
                <i class="fas fa-ellipsis-v"></i>
              </button>
            </div>
            <?php elseif (!$propria && !$apagada): ?>
            <div class="msg-opts-wrap" style="position:relative;order:1;">
              <button class="btn-msg-opts"
                      style="display:none;background:var(--branco);color:var(--texto-muted);border:1px solid var(--creme-escuro);
                             border-radius:0;width:26px;height:26px;cursor:pointer;font-size:.72rem;
                             align-items:center;justify-content:center;">
                <i class="fas fa-ellipsis-v"></i>
              </button>
            </div>
            <?php endif; ?>

            <div class="msg-bubble <?= $propria ? 'msg-bubble-own' : 'msg-bubble-other' ?>"
                 <?= $isLocal ? 'style="padding:.5rem;background:transparent;box-shadow:none;"' : '' ?>
                 <?= $apagada ? 'style="opacity:.6;"' : '' ?>>
              <?php if ($apagada): ?>
                <span style="font-style:italic;display:flex;align-items:center;gap:.4rem;font-size:.88rem;">
                  <i class="fas fa-ban" style="font-size:.8rem;"></i> Mensagem apagada
                </span>
                <div style="font-size:.72rem;opacity:.65;text-align:right;margin-top:.3rem;">
                  <?= date('H:i', strtotime($msg['criado_em'])) ?>
                </div>
              <?php elseif ($isLocal): ?>
                <?php
                  $bubble_bg   = $propria ? 'var(--verde-escuro)' : '#fff';
                  $bubble_text = $propria ? '#fff' : 'var(--texto)';
                  $bubble_muted= $propria ? 'rgba(255,255,255,.7)' : 'var(--texto-muted)';
                  $bubble_border = $propria ? 'rgba(255,255,255,.2)' : 'var(--creme-escuro)';
                ?>
                <a href="<?= SITE_URL ?>/pages/local.php?id=<?= (int)$msg['local_id'] ?>"
                   style="display:block;text-decoration:none;border:1.5px solid <?= $bubble_border ?>;border-radius:var(--radius);overflow:hidden;background:<?= $bubble_bg ?>;min-width:200px;max-width:240px;">
                  <?php if (!empty($msg['local_foto'])): ?>
                    <img src="<?= SITE_URL ?>/uploads/locais/<?= h($msg['local_foto']) ?>"
                         style="width:100%;height:120px;object-fit:cover;display:block;">
                  <?php else: ?>
                    <div style="width:100%;height:80px;background:var(--verde);display:flex;align-items:center;justify-content:center;">
                      <i class="fas fa-map-marker-alt" style="color:#fff;font-size:2rem;"></i>
                    </div>
                  <?php endif; ?>
                  <div style="padding:.65rem .85rem;">
                    <div style="font-size:.7rem;font-weight:600;text-transform:uppercase;letter-spacing:.05em;color:<?= $bubble_muted ?>;margin-bottom:.25rem;">
                      <i class="fas fa-map-marker-alt"></i> Local recomendado
                    </div>
                    <div style="font-weight:700;font-size:.9rem;color:<?= $bubble_text ?>;line-height:1.3;margin-bottom:.25rem;"><?= h($msg['local_nome'] ?? 'Local') ?></div>
                    <div style="font-size:.78rem;color:<?= $bubble_muted ?>;"><?= h($msg['local_regiao'] ?? '') ?> &bull; <?= h($msg['local_categoria'] ?? '') ?></div>
                    <?php if (!empty($msg['texto'])): ?>
                      <div style="margin-top:.5rem;padding-top:.5rem;border-top:1px solid <?= $bubble_border ?>;font-size:.85rem;color:<?= $bubble_text ?>;"><?= nl2br(h($msg['texto'])) ?></div>
                    <?php endif; ?>
                    <div style="margin-top:.5rem;font-size:.75rem;color:<?= $bubble_muted ?>;display:flex;align-items:center;gap:.25rem;">
                      <i class="fas fa-external-link-alt"></i> Ver local
                    </div>
                  </div>
                </a>
                <div style="font-size:.72rem;opacity:.65;text-align:right;margin-top:.3rem;color:<?= $propria ? '#fff' : 'var(--texto-muted)' ?>;">
                  <?= date('H:i', strtotime($msg['criado_em'])) ?>
                  <?php if ($propria && $msg['lida']): ?><i class="fas fa-check-double" style="margin-left:.3rem;"></i><?php endif; ?>
                </div>
              <?php elseif ($isImg): ?>
                <img src="<?= SITE_URL ?>/uploads/mensagens/<?= h($msg['ficheiro']) ?>"
                     style="max-width:220px;border-radius:0;display:block;cursor:pointer;"
                     onclick="abrirFotoMsg('<?= SITE_URL ?>/uploads/mensagens/<?= h($msg['ficheiro']) ?>')">
                <div style="font-size:.72rem;opacity:.65;text-align:right;margin-top:.3rem;">
                  <?= date('H:i', strtotime($msg['criado_em'])) ?>
                  <?php if ($propria && $msg['lida']): ?><i class="fas fa-check-double" style="margin-left:.3rem;"></i><?php endif; ?>
                </div>
              <?php elseif ($isFich): ?>
                <a href="<?= SITE_URL ?>/uploads/mensagens/<?= h($msg['ficheiro']) ?>" target="_blank"
                   style="color:inherit;display:flex;align-items:center;gap:.5rem;">
                  <i class="fas fa-file"></i> <?= h($msg['ficheiro']) ?>
                </a>
                <div style="font-size:.72rem;opacity:.65;text-align:right;margin-top:.3rem;">
                  <?= date('H:i', strtotime($msg['criado_em'])) ?>
                  <?php if ($propria && $msg['lida']): ?><i class="fas fa-check-double" style="margin-left:.3rem;"></i><?php endif; ?>
                </div>
              <?php else: ?>
                <?= nl2br(h($msg['texto'])) ?>
                <div style="font-size:.72rem;opacity:.65;text-align:right;margin-top:.3rem;">
                  <?= date('H:i', strtotime($msg['criado_em'])) ?>
                  <?php if ($propria && $msg['lida']): ?><i class="fas fa-check-double" style="margin-left:.3rem;"></i><?php endif; ?>
                </div>
              <?php endif; ?>
            </div>

          </div>
        <?php endforeach; ?>
        <?php if (!$mensagens): ?>
          <div class="chat-vazio" style="text-align:center;color:var(--texto-muted);font-size:.9rem;margin-top:2rem;">
            <i class="fas fa-comments" style="font-size:2rem;margin-bottom:.5rem;display:block;color:var(--verde-brilho);"></i>
            Início da conversa com <?= h($outro_user['nome']) ?>. Diz olá!
          </div>
        <?php endif; ?>
      </div>

      <div id="chat-input-bar" style="padding:1rem 1.25rem;background:var(--branco);border-top:1px solid var(--creme-escuro);">
        <div id="preview-ficheiro" style="display:none;margin-bottom:.75rem;position:relative;width:fit-content;">
          <img id="preview-img" src="" style="max-height:120px;max-width:200px;border-radius:0;display:block;">
          <div id="preview-nome" style="font-size:.8rem;color:var(--texto-muted);margin-top:.25rem;"></div>
          <button onclick="cancelarFicheiro()" style="position:absolute;top:-8px;right:-8px;width:22px;height:22px;
                  border-radius:0;background:#e74c3c;color:#fff;border:none;cursor:pointer;
                  font-size:.7rem;display:flex;align-items:center;justify-content:center;">
            <i class="fas fa-times"></i>
          </button>
        </div>
        <div style="display:flex;gap:.75rem;align-items:flex-end;">
          <label for="msg-ficheiro" style="width:44px;height:44px;border-radius:0;background:var(--creme);
                 border:1.5px solid var(--creme-escuro);display:flex;align-items:center;justify-content:center;
                 cursor:pointer;flex-shrink:0;color:var(--texto-muted);font-size:1rem;" title="Enviar ficheiro">
            <i class="fas fa-paperclip"></i>
          </label>
          <input type="file" id="msg-ficheiro" accept="image/*,.pdf,.doc,.docx,.txt"
                 style="display:none;" onchange="selecionarFicheiro(this)">
          <textarea id="msg-input" placeholder="Escreve uma mensagem..." rows="1"
                    style="flex:1;border:1.5px solid var(--creme-escuro);border-radius:0;padding:.65rem 1.1rem;
                           resize:none;font-size:.95rem;font-family:inherit;background:var(--creme);color:var(--texto);outline:none;
                           max-height:120px;overflow-y:auto;"
                    onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();enviarMensagem();}"></textarea>
          <button onclick="enviarMensagem()" style="width:44px;height:44px;border-radius:0;background:var(--verde);
                  color:#fff;border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:1rem;">
            <i class="fas fa-paper-plane"></i>
          </button>
        </div>
      </div>

    <?php else: ?>
      <div style="flex:1;display:flex;align-items:center;justify-content:flex-end;flex-direction:column;gap:1rem;color:var(--texto-muted);padding-bottom:3rem;">
        <i class="fas fa-comments" style="font-size:4rem;color:var(--verde-brilho);"></i>
        <h3 style="color:var(--verde-escuro);">Seleciona uma conversa</h3>
        <p style="font-size:.9rem;max-width:300px;text-align:center;">Escolhe um utilizador à esquerda para começar a conversar.</p>
      </div>
    <?php endif; ?>
  </div>
</div>
</div>

<!-- Modal de confirmação de eliminar -->
<div id="overlay-eliminar" style="display:none;position:fixed;inset:0;z-index:7000;background:rgba(0,0,0,.45);align-items:center;justify-content:center;padding:1rem;" onclick="cancelarEliminar()">
  <div onclick="event.stopPropagation()" style="background:#fff;border-radius:var(--radius-lg);padding:1.75rem 1.5rem;width:100%;max-width:320px;box-shadow:0 8px 32px rgba(0,0,0,.2);text-align:center;">
    <i class="fas fa-trash" style="font-size:1.6rem;color:#c0392b;margin-bottom:.75rem;display:block;"></i>
    <p style="margin:0 0 1.25rem;font-size:.95rem;color:var(--texto);font-weight:600;">Eliminar esta mensagem?</p>
    <div style="display:flex;gap:.75rem;">
      <button onclick="cancelarEliminar()" style="flex:1;padding:.65rem;border:1.5px solid var(--creme-escuro);border-radius:var(--radius);background:#fff;font-size:.9rem;cursor:pointer;color:var(--texto-muted);">Cancelar</button>
      <button onclick="confirmarEliminar()" style="flex:1;padding:.65rem;border:none;border-radius:var(--radius);background:#c0392b;color:#fff;font-size:.9rem;font-weight:600;cursor:pointer;">Eliminar</button>
    </div>
  </div>
</div>

<!-- Modal de ampliação de foto -->
<div id="modal-foto-msg" onclick="fecharFotoMsg()"
     style="display:none;position:fixed;inset:0;z-index:6000;background:rgba(0,0,0,.92);
            align-items:center;justify-content:center;cursor:zoom-out;">
  <img id="modal-foto-msg-img" src="" alt=""
       style="max-width:90vw;max-height:90vh;object-fit:contain;border-radius:var(--radius);">
</div>

<!-- ── SCRIPTS globais ── -->
<script>
const TEM_CONVERSA = <?= $tem_conversa_ativa ? 'true' : 'false' ?>;
const SITE_URL_JS  = '<?= SITE_URL ?>';
// iOS Safari: quando o teclado abre, ajusta o bottom do chat para não ficar tapado
if (window.visualViewport) {
  function ajustarTecladoIOS() {
    const diff = window.innerHeight - window.visualViewport.height - window.visualViewport.offsetTop;
    document.getElementById('chat-layout').style.bottom = Math.max(0, diff) + 'px';
  }
  window.visualViewport.addEventListener('resize', ajustarTecladoIOS);
  window.visualViewport.addEventListener('scroll', ajustarTecladoIOS);
}

function isMobile() { return window.innerWidth <= 768; }

function voltarLista() {
  document.getElementById('painel-lista').style.cssText = 'width:100%;flex-shrink:0;background:var(--branco);border-right:1px solid var(--creme-escuro);display:flex;flex-direction:column;overflow:hidden;';
  document.getElementById('painel-chat').style.display = 'none';
  const btn = document.getElementById('btn-voltar');
  if (btn) btn.style.display = 'none';
}

function aplicarLayout() {
  const lista     = document.getElementById('painel-lista');
  const chat      = document.getElementById('painel-chat');
  const btnVoltar = document.getElementById('btn-voltar');
  if (!lista || !chat) return;
  if (isMobile()) {
    lista.style.width = '100%';
    if (TEM_CONVERSA) {
      lista.style.display = 'none';
      chat.style.display  = 'flex';
      if (btnVoltar) btnVoltar.style.display = 'block';
    } else {
      lista.style.display = 'flex';
      chat.style.display  = 'none';
    }
  } else {
    lista.style.width   = '320px';
    lista.style.display = 'flex';
    chat.style.display  = 'flex';
    if (btnVoltar) btnVoltar.style.display = 'none';
  }
}
aplicarLayout();
window.addEventListener('resize', aplicarLayout);

function abrirFotoMsg(src) {
  document.getElementById('modal-foto-msg-img').src = src;
  document.getElementById('modal-foto-msg').style.display = 'flex';
}
function fecharFotoMsg() {
  document.getElementById('modal-foto-msg').style.display = 'none';
}
document.addEventListener('keydown', e => { if (e.key === 'Escape') fecharFotoMsg(); });

// ── Menu 3 pontos ─────────────────────────────────────────
function toggleMenu(btnEl, msgId, propria) {
  const existente = btnEl.parentElement.querySelector('.msg-menu');
  document.querySelectorAll('.msg-menu').forEach(m => m.remove());
  if (existente) return;

  const menu = document.createElement('div');
  menu.className = 'msg-menu';
  menu.style.cssText = `position:absolute;bottom:calc(100% + 4px);left:50%;transform:translateX(-50%);
      background:var(--branco);border:1px solid var(--creme-escuro);border-radius:0;
      box-shadow:var(--sombra-md);z-index:500;min-width:180px;overflow:hidden;white-space:nowrap;`;

  const btnEliminar = document.createElement('button');
  btnEliminar.style.cssText = `display:flex;align-items:center;gap:.6rem;width:100%;padding:.75rem 1rem;
      border:none;background:none;cursor:pointer;font-size:.88rem;color:#e74c3c;font-family:inherit;`;
  btnEliminar.innerHTML = propria
    ? '<i class="fas fa-trash" style="width:14px;"></i> Apagar para todos'
    : '<i class="fas fa-eye-slash" style="width:14px;"></i> Apagar para mim';
  btnEliminar.addEventListener('mouseover', () => btnEliminar.style.background = 'var(--creme)');
  btnEliminar.addEventListener('mouseout',  () => btnEliminar.style.background = 'none');
  btnEliminar.addEventListener('click', (e) => {
    e.stopPropagation();
    document.querySelectorAll('.msg-menu').forEach(m => m.remove());
    mostrarConfirmEliminar(msgId, propria ? 'todos' : 'mim');
  });
  menu.appendChild(btnEliminar);
  btnEl.parentElement.appendChild(menu);
}

// Fechar menus ao clicar fora
document.addEventListener('click', (e) => {
  if (!e.target.closest('.btn-msg-opts') && !e.target.closest('.msg-menu')) {
    document.querySelectorAll('.msg-menu').forEach(m => m.remove());
  }
});

// ── Overlay de confirmação ────────────────────────────────
let _msgIdParaEliminar = null;
let _tipoEliminar = 'mim';

function mostrarConfirmEliminar(msgId, tipo) {
  _msgIdParaEliminar = msgId;
  _tipoEliminar = tipo;
  const p = document.querySelector('#overlay-eliminar p');
  if (p) p.textContent = tipo === 'todos'
    ? 'Apagar para todos? Esta ação não pode ser desfeita.'
    : 'Apagar para ti? O outro utilizador continua a ver a mensagem.';
  const btnConf = document.querySelector('#overlay-eliminar button:last-child');
  if (btnConf) btnConf.textContent = tipo === 'todos' ? 'Apagar para todos' : 'Apagar para mim';
  document.getElementById('overlay-eliminar').style.display = 'flex';
}
function cancelarEliminar() {
  _msgIdParaEliminar = null;
  document.getElementById('overlay-eliminar').style.display = 'none';
}
async function confirmarEliminar() {
  document.getElementById('overlay-eliminar').style.display = 'none';
  if (_msgIdParaEliminar) await eliminarMensagem(_msgIdParaEliminar, _tipoEliminar);
  _msgIdParaEliminar = null;
}

// ── Eliminar mensagem ─────────────────────────────────────
async function eliminarMensagem(msgId, tipo) {
  document.querySelectorAll('.msg-menu').forEach(m => m.remove());
  const wrapper = document.querySelector(`[data-msg-id="${msgId}"]`);
  try {
    const res = await fetch(SITE_URL_JS + '/pages/mensagens_api.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': CSRF_TOKEN },
      body: `acao=eliminar&msg_id=${msgId}&tipo=${tipo}`
    });
    if (!res.ok) return;
    const data = await res.json();
    if (!data.ok) return;
    if (tipo === 'todos') {
      // Substitui conteúdo pelo placeholder "apagada"
      if (wrapper) {
        const bubble = wrapper.querySelector('.msg-bubble');
        if (bubble) {
          bubble.style.opacity = '.6';
          bubble.innerHTML = `<span style="font-style:italic;display:flex;align-items:center;gap:.4rem;font-size:.88rem;"><i class="fas fa-ban" style="font-size:.8rem;"></i> Mensagem apagada</span>`;
        }
        // Remove o botão de opções
        wrapper.querySelectorAll('.msg-opts-wrap').forEach(el => el.remove());
        wrapper.dataset.apagada = '1';
      }
    } else {
      // Apagar para mim — remove da vista
      if (wrapper) wrapper.remove();
    }
  } catch(err) {
    console.error('Fetch error:', err);
  }
}
</script>

<?php if ($outro_user): ?>
<script>
const CONVERSA_COM = <?= $conversa_id ?>;
const MEU_ID       = <?= $uid ?>;

function scrollFundo() {
  const chat = document.getElementById('chat-mensagens');
  chat.scrollTop = chat.scrollHeight;
}
scrollFundo();

// Hover + long press nas mensagens PHP
document.querySelectorAll('[data-msg-id]').forEach(wrapper => {
  if (wrapper.dataset.apagada === '1') return; // não adicionar menu a mensagens apagadas
  const btn   = wrapper.querySelector('.btn-msg-opts');
  if (!btn) return;
  const msgId  = wrapper.dataset.msgId;
  const propria = wrapper.dataset.propria === '1';
  const tipo    = propria ? 'todos' : 'mim';

  btn.addEventListener('click', (e) => {
    e.stopPropagation();
    toggleMenu(btn, msgId, propria);
  });

  wrapper.addEventListener('mouseenter', () => btn.style.display = 'flex');
  wrapper.addEventListener('mouseleave', (e) => {
    if (!wrapper.contains(e.relatedTarget)) btn.style.display = 'none';
  });

  const bubble = wrapper.querySelector('.msg-bubble');
  let pressTimer;
  if (bubble) {
    bubble.addEventListener('touchstart', () => {
      pressTimer = setTimeout(() => mostrarConfirmEliminar(msgId, tipo), 600);
    }, { passive: true });
    bubble.addEventListener('touchend',  () => clearTimeout(pressTimer));
    bubble.addEventListener('touchmove', () => clearTimeout(pressTimer));
  }
});

// ── Criar wrapper JS ──────────────────────────────────────
function criarWrapper(msgId, propria, innerHtml, semBubble = false) {
  const tipo = propria ? 'todos' : 'mim';
  const wrapper = document.createElement('div');
  wrapper.style.cssText = `display:flex;justify-content:${propria ? 'flex-end' : 'flex-start'};position:relative;align-items:center;gap:6px;`;
  wrapper.dataset.msgId  = msgId;
  wrapper.dataset.propria = propria ? '1' : '0';

  const optsWrap = document.createElement('div');
  optsWrap.className = 'msg-opts-wrap';
  optsWrap.style.cssText = 'position:relative;';
  if (propria) optsWrap.style.order = '-1';

  const btnOpts = document.createElement('button');
  btnOpts.className = 'btn-msg-opts';
  btnOpts.innerHTML = '<i class="fas fa-ellipsis-v"></i>';
  btnOpts.style.cssText = `display:none;background:var(--branco);color:var(--texto-muted);
      border:1px solid var(--creme-escuro);border-radius:0;width:26px;height:26px;
      cursor:pointer;font-size:.72rem;align-items:center;justify-content:center;`;
  btnOpts.addEventListener('click', (e) => { e.stopPropagation(); toggleMenu(btnOpts, msgId, propria); });
  optsWrap.appendChild(btnOpts);

  const bubble = document.createElement('div');
  if (semBubble) {
    bubble.style.cssText = 'padding:.5rem;background:transparent;box-shadow:none;position:relative;';
  } else {
    bubble.className = `msg-bubble ${propria ? 'msg-bubble-own' : 'msg-bubble-other'}`;
  }
  bubble.innerHTML = innerHtml;

  wrapper.addEventListener('mouseenter', () => btnOpts.style.display = 'flex');
  wrapper.addEventListener('mouseleave', (e) => {
    if (!wrapper.contains(e.relatedTarget)) btnOpts.style.display = 'none';
  });

  let pressTimer;
  bubble.addEventListener('touchstart', () => {
    pressTimer = setTimeout(() => mostrarConfirmEliminar(msgId, tipo), 600);
  }, { passive: true });
  bubble.addEventListener('touchend',  () => clearTimeout(pressTimer));
  bubble.addEventListener('touchmove', () => clearTimeout(pressTimer));

  if (propria) { wrapper.appendChild(optsWrap); wrapper.appendChild(bubble); }
  else         { wrapper.appendChild(bubble);   wrapper.appendChild(optsWrap); }
  return wrapper;
}

function adicionarMensagem(msg, propria) {
  const chat  = document.getElementById('chat-mensagens');
  const vazio = chat.querySelector('.chat-vazio');
  if (vazio) vazio.remove();
  const hora  = new Date(msg.criado_em).toLocaleTimeString('pt-PT', { hour: '2-digit', minute: '2-digit' });

  if (msg.local_id) {
    chat.appendChild(criarWrapper(msg.id, propria, construirCardLocal(msg, propria, hora), true));
    return;
  }

  const texto = msg.texto.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/\n/g,'<br>');
  chat.appendChild(criarWrapper(msg.id, propria,
    `${texto}<div style="font-size:.72rem;opacity:.65;text-align:right;margin-top:.3rem;">${hora}</div>`
  ));
}

function construirCardLocal(msg, propria, hora) {
  const bg      = propria ? 'var(--verde-escuro)' : '#fff';
  const cor     = propria ? '#fff' : 'var(--texto)';
  const muted   = propria ? 'rgba(255,255,255,.7)' : 'var(--texto-muted)';
  const border  = propria ? 'rgba(255,255,255,.2)' : 'var(--creme-escuro)';
  const fotoHtml = msg.local_foto
    ? `<img src="${SITE_URL_JS}/uploads/locais/${msg.local_foto}" style="width:100%;height:120px;object-fit:cover;display:block;">`
    : `<div style="width:100%;height:80px;background:var(--verde);display:flex;align-items:center;justify-content:center;"><i class="fas fa-map-marker-alt" style="color:#fff;font-size:2rem;"></i></div>`;
  const textoExtra = msg.texto
    ? `<div style="margin-top:.5rem;padding-top:.5rem;border-top:1px solid ${border};font-size:.85rem;color:${cor};">${msg.texto.replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/\n/g,'<br>')}</div>`
    : '';
  const checkDouble = propria && msg.lida ? '<i class="fas fa-check-double" style="margin-left:.3rem;"></i>' : '';
  return `
    <a href="${SITE_URL_JS}/pages/local.php?id=${msg.local_id}"
       style="display:block;text-decoration:none;border:1.5px solid ${border};border-radius:var(--radius);overflow:hidden;background:${bg};min-width:200px;max-width:240px;">
      ${fotoHtml}
      <div style="padding:.65rem .85rem;">
        <div style="font-size:.7rem;font-weight:600;text-transform:uppercase;letter-spacing:.05em;color:${muted};margin-bottom:.25rem;"><i class="fas fa-map-marker-alt"></i> Local recomendado</div>
        <div style="font-weight:700;font-size:.9rem;color:${cor};line-height:1.3;margin-bottom:.25rem;">${(msg.local_nome||'Local').replace(/</g,'&lt;').replace(/>/g,'&gt;')}</div>
        <div style="font-size:.78rem;color:${muted};">${(msg.local_regiao||'').replace(/</g,'&lt;')} &bull; ${(msg.local_categoria||'').replace(/</g,'&lt;')}</div>
        ${textoExtra}
        <div style="margin-top:.5rem;font-size:.75rem;color:${muted};display:flex;align-items:center;gap:.25rem;"><i class="fas fa-external-link-alt"></i> Ver local</div>
      </div>
    </a>
    <div style="font-size:.72rem;opacity:.65;text-align:right;margin-top:.3rem;color:${propria?'#fff':'var(--texto-muted)'};">${hora}${checkDouble}</div>`;
}

function adicionarMensagemFicheiro(msg, propria) {
  const chat  = document.getElementById('chat-mensagens');
  const vazio = chat.querySelector('.chat-vazio');
  if (vazio) vazio.remove();
  const hora  = new Date(msg.criado_em).toLocaleTimeString('pt-PT', { hour: '2-digit', minute: '2-digit' });
  const url   = `${SITE_URL_JS}/uploads/mensagens/${msg.ficheiro}`;
  const isImg = /\.(jpg|jpeg|png|webp|gif)$/i.test(msg.ficheiro);
  const inner = (isImg
    ? `<img src="${url}" style="max-width:220px;border-radius:0;display:block;cursor:pointer;" onclick="abrirFotoMsg('${url}')">`
    : `<a href="${url}" target="_blank" style="color:inherit;display:flex;align-items:center;gap:.5rem;"><i class="fas fa-file"></i> ${msg.ficheiro}</a>`)
    + `<div style="font-size:.72rem;opacity:.65;text-align:right;margin-top:.3rem;">${hora}</div>`;
  chat.appendChild(criarWrapper(msg.id, propria, inner));
}

async function enviarMensagem() {
  if (ficheiroSelecionado) { await enviarFicheiroConfirmado(); return; }
  const input = document.getElementById('msg-input');
  const texto = input.value.trim();
  if (!texto) return;
  input.value = '';
  input.style.height = 'auto';
  const res  = await fetch(SITE_URL_JS + '/pages/mensagens_api.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': CSRF_TOKEN },
    body: `acao=enviar&destinatario_id=${CONVERSA_COM}&texto=${encodeURIComponent(texto)}`
  });
  const data = await res.json();
  if (data.ok) { adicionarMensagem(data.mensagem, true); ultimaMsg = data.mensagem.criado_em; scrollFundo(); }
}

let ficheiroSelecionado = null;

function selecionarFicheiro(input) {
  const file = input.files[0];
  if (!file) return;
  if (file.size > 10 * 1024 * 1024) { alert('Ficheiro demasiado grande (máx. 10MB).'); input.value = ''; return; }
  ficheiroSelecionado = file;
  const preview = document.getElementById('preview-ficheiro');
  const img     = document.getElementById('preview-img');
  const nome    = document.getElementById('preview-nome');
  if (file.type.startsWith('image/')) {
    img.src = URL.createObjectURL(file);
    img.style.display = 'block';
    nome.textContent  = '';
  } else {
    img.style.display = 'none';
    nome.textContent  = '📎 ' + file.name;
  }
  preview.style.display = 'block';
}

function cancelarFicheiro() {
  ficheiroSelecionado = null;
  document.getElementById('preview-ficheiro').style.display = 'none';
  document.getElementById('msg-ficheiro').value = '';
}

async function enviarFicheiroConfirmado() {
  if (!ficheiroSelecionado) return;
  const form = new FormData();
  form.append('acao', 'ficheiro');
  form.append('destinatario_id', CONVERSA_COM);
  form.append('ficheiro', ficheiroSelecionado);
  const res  = await fetch(SITE_URL_JS + '/pages/mensagens_api.php', { method: 'POST', headers: { 'X-CSRF-Token': CSRF_TOKEN }, body: form });
  const data = await res.json();
  if (data.ok) { adicionarMensagemFicheiro(data.mensagem, true); ultimaMsg = data.mensagem.criado_em; scrollFundo(); }
  cancelarFicheiro();
}

let ultimaMsg = <?= $mensagens ? '"' . end($mensagens)['criado_em'] . '"' : '"0"' ?>;

// Zerar badge da navbar imediatamente ao abrir a conversa
(function() {
  const badge = document.getElementById('msg-badge');
  if (badge) badge.style.display = 'none';
})();

setInterval(async () => {//de 3 em 3s pergunta ao servidor se há mensagens novas
  const res  = await fetch(`${SITE_URL_JS}/pages/mensagens_api.php?acao=novas&com=${CONVERSA_COM}&desde=${encodeURIComponent(ultimaMsg)}`);
  const data = await res.json();
  if (data.mensagens && data.mensagens.length > 0) {
    const aoFundo = estaAoFundo();
    data.mensagens.forEach(msg => {
      if (msg.ficheiro) adicionarMensagemFicheiro(msg, msg.remetente_id == MEU_ID);
      else              adicionarMensagem(msg, msg.remetente_id == MEU_ID);
      ultimaMsg = msg.criado_em;//envia 'ultimaMsg' (hora da última mensagem) e o servidor só devolve o que é novo
    });                         //evita duplicados e poupa dados; conceito de polling visto em Java (11º)
    if (aoFundo) scrollFundo();
  }
}, 3000);

function estaAoFundo() {
  const chat = document.getElementById('chat-mensagens');
  return chat.scrollHeight - chat.scrollTop - chat.clientHeight < 50;
}

document.getElementById('msg-input').addEventListener('input', function() {
  this.style.height = 'auto';
  this.style.height = Math.min(this.scrollHeight, 120) + 'px';
});
</script>
<?php endif; ?>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>