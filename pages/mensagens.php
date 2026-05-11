<?php
require_once dirname(__DIR__) . '/includes/functions.php';
require_login();

$user = auth_user();
$uid  = $user['id'];
$conversa_id = isset($_GET['com']) ? (int)$_GET['com'] : 0;

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
            'SELECT m.*, u.username AS remetente_username, u.avatar AS remetente_avatar
             FROM mensagens m
             JOIN utilizadores u ON u.id = m.remetente_id
             WHERE (m.remetente_id = ? AND m.destinatario_id = ?)
                OR (m.remetente_id = ? AND m.destinatario_id = ?)
             ORDER BY m.criado_em ASC'
        );
        $stM->execute([$uid, $conversa_id, $conversa_id, $uid]);
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
<div class="page-content">
<div style="display:flex;height:calc(100vh - var(--nav-h));overflow:hidden;-webkit-overflow-scrolling:touch;" id="chat-layout">

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
          <?php $propria = ((int)$msg['remetente_id'] === $uid); ?>
          <?php $isImg  = !empty($msg['ficheiro']) && preg_match('/\.(jpg|jpeg|png|webp|gif)$/i', $msg['ficheiro']); ?>
          <?php $isFich = !empty($msg['ficheiro']) && !$isImg; ?>
          <div style="display:flex;justify-content:<?= $propria ? 'flex-end' : 'flex-start' ?>;position:relative;align-items:center;gap:6px;" data-msg-id="<?= $msg['id'] ?>">

            <?php if ($propria): ?>
            <div class="msg-opts-wrap" style="position:relative;order:-1;">
              <button class="btn-msg-opts"
                      style="display:none;background:var(--branco);color:var(--texto-muted);border:1px solid var(--creme-escuro);
                             border-radius:0;width:26px;height:26px;cursor:pointer;font-size:.72rem;
                             align-items:center;justify-content:center;">
                <i class="fas fa-ellipsis-v"></i>
              </button>
            </div>
            <?php endif; ?>

            <div style="max-width:70%;background:<?= $propria ? 'var(--verde)' : 'var(--branco)' ?>;color:<?= $propria ? '#fff' : 'var(--texto)' ?>;
                        padding:.65rem 1rem;border-radius:0;
                        font-size:.92rem;line-height:1.5;word-break:break-word;">
              <?php if ($isImg): ?>
                <img src="<?= SITE_URL ?>/uploads/mensagens/<?= h($msg['ficheiro']) ?>"
                     style="max-width:220px;border-radius:0;display:block;cursor:pointer;"
                     onclick="abrirFotoMsg('<?= SITE_URL ?>/uploads/mensagens/<?= h($msg['ficheiro']) ?>')">
              <?php elseif ($isFich): ?>
                <a href="<?= SITE_URL ?>/uploads/mensagens/<?= h($msg['ficheiro']) ?>" target="_blank"
                   style="color:inherit;display:flex;align-items:center;gap:.5rem;">
                  <i class="fas fa-file"></i> <?= h($msg['ficheiro']) ?>
                </a>
              <?php else: ?>
                <?= nl2br(h($msg['texto'])) ?>
              <?php endif; ?>
              <div style="font-size:.72rem;opacity:.65;text-align:right;margin-top:.3rem;">
                <?= date('H:i', strtotime($msg['criado_em'])) ?>
                <?php if ($propria && $msg['lida']): ?><i class="fas fa-check-double" style="margin-left:.3rem;"></i><?php endif; ?>
              </div>
            </div>

            <?php if (!$propria): ?>
            <div class="msg-opts-wrap" style="position:relative;">
              <button class="btn-msg-opts"
                      style="display:none;background:var(--branco);color:var(--texto-muted);border:1px solid var(--creme-escuro);
                             border-radius:0;width:26px;height:26px;cursor:pointer;font-size:.72rem;
                             align-items:center;justify-content:center;">
                <i class="fas fa-ellipsis-v"></i>
              </button>
            </div>
            <?php endif; ?>

          </div>
        <?php endforeach; ?>
        <?php if (!$mensagens): ?>
          <div class="chat-vazio" style="text-align:center;color:var(--texto-muted);font-size:.9rem;margin-top:2rem;">
            <i class="fas fa-comments" style="font-size:2rem;margin-bottom:.5rem;display:block;color:var(--verde-brilho);"></i>
            Início da conversa com <?= h($outro_user['nome']) ?>. Diz olá!
          </div>
        <?php endif; ?>
      </div>

      <div style="padding:1rem 1.25rem;background:var(--branco);border-top:1px solid var(--creme-escuro);">
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
                           resize:none;font-size:.95rem;font-family:inherit;background:var(--creme);outline:none;
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
function toggleMenu(btnEl, msgId) {
  const existente = btnEl.parentElement.querySelector('.msg-menu');
  document.querySelectorAll('.msg-menu').forEach(m => m.remove());
  if (existente) return;

  const menu = document.createElement('div');
  menu.className = 'msg-menu';
  menu.style.cssText = `position:absolute;bottom:calc(100% + 4px);left:50%;transform:translateX(-50%);
      background:var(--branco);border:1px solid var(--creme-escuro);border-radius:0;
      box-shadow:var(--sombra-md);z-index:500;min-width:140px;overflow:hidden;white-space:nowrap;`;

  const btnEliminar = document.createElement('button');
  btnEliminar.style.cssText = `display:flex;align-items:center;gap:.6rem;width:100%;padding:.75rem 1rem;
      border:none;background:none;cursor:pointer;font-size:.88rem;color:#e74c3c;font-family:inherit;`;
  btnEliminar.innerHTML = '<i class="fas fa-trash" style="width:14px;"></i> Eliminar';
  btnEliminar.addEventListener('mouseover', () => btnEliminar.style.background = 'var(--creme)');
  btnEliminar.addEventListener('mouseout',  () => btnEliminar.style.background = 'none');
  btnEliminar.addEventListener('click', (e) => {
    e.stopPropagation();
    eliminarMensagem(msgId);
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

// ── Eliminar mensagem ─────────────────────────────────────
async function eliminarMensagem(msgId) {
  document.querySelectorAll('.msg-menu').forEach(m => m.remove());
  const wrapper = document.querySelector(`[data-msg-id="${msgId}"]`);
  try {
    const res = await fetch(SITE_URL_JS + '/pages/mensagens_api.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: `acao=eliminar&msg_id=${msgId}`
    });
    if (!res.ok) { console.error('HTTP erro:', res.status); return; }
    const text = await res.text();
    console.log('Resposta raw:', text);
    const data = JSON.parse(text);
    if (data.ok && wrapper) wrapper.remove();
    else console.error('Erro ao eliminar:', data);
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
  const btn   = wrapper.querySelector('.btn-msg-opts');
  if (!btn) return;
  const msgId = wrapper.dataset.msgId;

  btn.addEventListener('click', (e) => {
    e.stopPropagation();
    toggleMenu(btn, msgId);
  });

  wrapper.addEventListener('mouseenter', () => btn.style.display = 'flex');
  wrapper.addEventListener('mouseleave', (e) => {
    if (!wrapper.contains(e.relatedTarget)) btn.style.display = 'none';
  });

  const bubble = wrapper.querySelector('div:not(.msg-opts-wrap)');
  let pressTimer, tocando = false;
  if (bubble) {
    bubble.addEventListener('touchstart', () => {
      tocando = true;
      pressTimer = setTimeout(() => { btn.style.display = 'flex'; }, 2000);
    }, { passive: true });
    bubble.addEventListener('touchend',  () => { tocando = false; clearTimeout(pressTimer); });
    bubble.addEventListener('touchmove', () => { tocando = false; clearTimeout(pressTimer); });
  }
});

// ── Criar wrapper JS ──────────────────────────────────────
function criarWrapper(msgId, propria, innerHtml) {
  const wrapper = document.createElement('div');
  wrapper.style.cssText = `display:flex;justify-content:${propria ? 'flex-end' : 'flex-start'};position:relative;align-items:center;gap:6px;`;
  wrapper.dataset.msgId = msgId;

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
  btnOpts.addEventListener('click', (e) => { e.stopPropagation(); toggleMenu(btnOpts, msgId); });
  optsWrap.appendChild(btnOpts);

  const bubble = document.createElement('div');
  bubble.style.cssText = `max-width:70%;background:${propria ? 'var(--verde)' : 'var(--branco)'};
      color:${propria ? '#fff' : 'var(--texto)'};padding:.65rem 1rem;
      border-radius:0;
      font-size:.92rem;line-height:1.5;word-break:break-word;`;
  bubble.innerHTML = innerHtml;

  wrapper.addEventListener('mouseenter', () => btnOpts.style.display = 'flex');
  wrapper.addEventListener('mouseleave', (e) => {
    if (!wrapper.contains(e.relatedTarget)) btnOpts.style.display = 'none';
  });

  let pressTimer, tocando = false;
  bubble.addEventListener('touchstart', () => {
    tocando = true;
    pressTimer = setTimeout(() => { btnOpts.style.display = 'flex'; }, 2000);
  }, { passive: true });
  bubble.addEventListener('touchend',  () => { tocando = false; clearTimeout(pressTimer); });
  bubble.addEventListener('touchmove', () => { tocando = false; clearTimeout(pressTimer); });

  if (propria) { wrapper.appendChild(optsWrap); wrapper.appendChild(bubble); }
  else         { wrapper.appendChild(bubble);   wrapper.appendChild(optsWrap); }
  return wrapper;
}

function adicionarMensagem(msg, propria) {
  const chat  = document.getElementById('chat-mensagens');
  const vazio = chat.querySelector('.chat-vazio');
  if (vazio) vazio.remove();
  const hora  = new Date(msg.criado_em).toLocaleTimeString('pt-PT', { hour: '2-digit', minute: '2-digit' });
  const texto = msg.texto.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/\n/g,'<br>');
  chat.appendChild(criarWrapper(msg.id, propria,
    `${texto}<div style="font-size:.72rem;opacity:.65;text-align:right;margin-top:.3rem;">${hora}</div>`
  ));
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
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
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
  const res  = await fetch(SITE_URL_JS + '/pages/mensagens_api.php', { method: 'POST', body: form });
  const data = await res.json();
  if (data.ok) { adicionarMensagemFicheiro(data.mensagem, true); ultimaMsg = data.mensagem.criado_em; scrollFundo(); }
  cancelarFicheiro();
}

let ultimaMsg = <?= $mensagens ? '"' . end($mensagens)['criado_em'] . '"' : '"0"' ?>;

setInterval(async () => {
  const res  = await fetch(`${SITE_URL_JS}/pages/mensagens_api.php?acao=novas&com=${CONVERSA_COM}&desde=${encodeURIComponent(ultimaMsg)}`);
  const data = await res.json();
  if (data.mensagens && data.mensagens.length > 0) {
    const aoFundo = estaAoFundo();
    data.mensagens.forEach(msg => {
      if (msg.ficheiro) adicionarMensagemFicheiro(msg, msg.remetente_id == MEU_ID);
      else              adicionarMensagem(msg, msg.remetente_id == MEU_ID);
      ultimaMsg = msg.criado_em;
    });
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