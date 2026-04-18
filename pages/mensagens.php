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

$page_title = 'Mensagens';
include dirname(__DIR__) . '/includes/header.php';
?>
<div class="page-content">
<div style="display:flex;height:calc(100vh - var(--nav-h));overflow:hidden;">

  <div style="width:320px;flex-shrink:0;background:var(--branco);border-right:1px solid var(--creme-escuro);display:flex;flex-direction:column;overflow:hidden;">
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
              <span style="background:#e74c3c;color:#fff;border-radius:50px;padding:.15rem .5rem;font-size:.75rem;font-weight:700;flex-shrink:0;"><?= $conv['nao_lidas'] ?></span>
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

  <div style="flex:1;display:flex;flex-direction:column;overflow:hidden;background:var(--creme);">
    <?php if ($outro_user): ?>
      <div style="padding:1rem 1.5rem;background:var(--branco);border-bottom:1px solid var(--creme-escuro);display:flex;align-items:center;gap:.85rem;">
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
          <div style="display:flex;justify-content:<?= $propria ? 'flex-end' : 'flex-start' ?>;">
            <div style="max-width:70%;background:<?= $propria ? 'var(--verde)' : 'var(--branco)' ?>;color:<?= $propria ? '#fff' : 'var(--texto)' ?>;padding:.65rem 1rem;border-radius:<?= $propria ? '18px 18px 4px 18px' : '18px 18px 18px 4px' ?>;box-shadow:var(--sombra-sm);font-size:.92rem;line-height:1.5;word-break:break-word;">
              <?= nl2br(h($msg['texto'])) ?>
              <div style="font-size:.72rem;opacity:.65;text-align:right;margin-top:.3rem;">
                <?= date('H:i', strtotime($msg['criado_em'])) ?>
                <?php if ($propria && $msg['lida']): ?><i class="fas fa-check-double" style="margin-left:.3rem;"></i><?php endif; ?>
              </div>
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

      <div style="padding:1rem 1.25rem;background:var(--branco);border-top:1px solid var(--creme-escuro);">
        <div style="display:flex;gap:.75rem;align-items:flex-end;">
          <textarea id="msg-input" placeholder="Escreve uma mensagem..." rows="1"
                    style="flex:1;border:1.5px solid var(--creme-escuro);border-radius:24px;padding:.65rem 1.1rem;resize:none;font-size:.95rem;font-family:inherit;background:var(--creme);outline:none;max-height:120px;overflow-y:auto;"
                    onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();enviarMensagem();}"></textarea>
          <button onclick="enviarMensagem()" style="width:44px;height:44px;border-radius:50%;background:var(--verde);color:#fff;border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:1rem;">
            <i class="fas fa-paper-plane"></i>
          </button>
        </div>
      </div>

    <?php else: ?>
      <div style="flex:1;display:flex;align-items:center;justify-content:center;flex-direction:column;gap:1rem;color:var(--texto-muted);">
        <i class="fas fa-comments" style="font-size:4rem;color:var(--verde-brilho);"></i>
        <h3 style="color:var(--verde-escuro);">Seleciona uma conversa</h3>
        <p style="font-size:.9rem;max-width:300px;text-align:center;">Escolhe um utilizador à esquerda para começar a conversar.</p>
      </div>
    <?php endif; ?>
  </div>
</div>
</div>

<?php if ($outro_user): ?>
<script>
const CONVERSA_COM = <?= $conversa_id ?>;
const MEU_ID = <?= $uid ?>;

function scrollFundo() {
  const chat = document.getElementById('chat-mensagens');
  chat.scrollTop = chat.scrollHeight;
}
scrollFundo();

async function enviarMensagem() {
  const input = document.getElementById('msg-input');
  const texto = input.value.trim();
  if (!texto) return;
  input.value = '';
  input.style.height = 'auto';
  const res = await fetch('<?= SITE_URL ?>/pages/mensagens_api.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: `acao=enviar&destinatario_id=${CONVERSA_COM}&texto=${encodeURIComponent(texto)}`
  });
  const data = await res.json();
  if (data.ok) {
    adicionarMensagem(data.mensagem, true);
    ultimaMsg = data.mensagem.criado_em;
    scrollFundo();
  }
}

function adicionarMensagem(msg, propria) {
  const chat = document.getElementById('chat-mensagens');
  const vazio = chat.querySelector('.chat-vazio');
  if (vazio) vazio.remove();
  const hora = new Date(msg.criado_em).toLocaleTimeString('pt-PT', { hour: '2-digit', minute: '2-digit' });
  const texto = msg.texto.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/\n/g,'<br>');
  const div = document.createElement('div');
  div.style.cssText = `display:flex;justify-content:${propria ? 'flex-end' : 'flex-start'};`;
  div.innerHTML = `<div style="max-width:70%;background:${propria ? 'var(--verde)' : 'var(--branco)'};color:${propria ? '#fff' : 'var(--texto)'};padding:.65rem 1rem;border-radius:${propria ? '18px 18px 4px 18px' : '18px 18px 18px 4px'};box-shadow:var(--sombra-sm);font-size:.92rem;line-height:1.5;word-break:break-word;">${texto}<div style="font-size:.72rem;opacity:.65;text-align:right;margin-top:.3rem;">${hora}</div></div>`;
  chat.appendChild(div);
}

let ultimaMsg = <?= $mensagens ? '"' . end($mensagens)['criado_em'] . '"' : '"0"' ?>;

setInterval(async () => {
  const res = await fetch(`<?= SITE_URL ?>/pages/mensagens_api.php?acao=novas&com=${CONVERSA_COM}&desde=${encodeURIComponent(ultimaMsg)}`);
  const data = await res.json();
  if (data.mensagens && data.mensagens.length > 0) {
    const aoFundo = estaAoFundo();
    data.mensagens.forEach(msg => {
      adicionarMensagem(msg, msg.remetente_id == MEU_ID);
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