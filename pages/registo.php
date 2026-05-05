<?php
// SEGREDO LUSITANO — Registo
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/mailer.php';

// Limpar códigos expirados (limpeza periódica)
limpar_codigos_expirados();

if (auth_user()) { header('Location: ' . SITE_URL . '/index.php'); exit; }

$erros = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome     = trim($_POST['nome']     ?? '');
    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');
    $confirm  = trim($_POST['confirm']  ?? '');

    if (strlen($nome) < 2)         $erros['nome']     = 'Nome demasiado curto.';
    if (strlen($username) < 3)     $erros['username'] = 'Username com mínimo 3 caracteres.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $erros['email'] = 'Email inválido.';
    if (strlen($password) < 6)     $erros['password'] = 'Password com mínimo 6 caracteres.';
    if ($password !== $confirm)    $erros['confirm']  = 'As passwords não coincidem.';
    if (empty($_POST['aceitar_termos'])) $erros['termos'] = 'Deves aceitar os Termos e Condições para continuar.';

    if (!$erros) {
        // Capturar datetime de aceitação dos termos (vem do JS, validar formato)
        $termos_em = trim($_POST['termos_aceites_em'] ?? '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $termos_em)) {
            $termos_em = date('Y-m-d H:i:s');
        }

        $res = register($nome, $username, $email, $password, $termos_em);
        if (!$res['ok']) {
            $erros['email'] = $res['msg'];
        } else {
            // Gerar e enviar código de verificação
            require_once dirname(__DIR__) . '/includes/mailer.php';

            // Verificar se PHPMailer está disponível
            if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
                // PHPMailer não instalado - auto-verificar e fazer login direto
                db()->prepare('UPDATE utilizadores SET verificado = 1 WHERE id = ?')->execute([$res['id']]);
                $_SESSION['user_id'] = $res['id'];
                flash('success', 'Conta criada com sucesso! Bem-vindo ao Segredo Lusitano! 🎉 (Email não configurado - conta verificada automaticamente)');
                header('Location: ' . SITE_URL . '/index.php');
                exit;
            }

            $codigo = gerar_e_guardar_codigo($res['id'], 'registo');
            $enviado = enviar_codigo_verificacao($email, $nome, $codigo, 'registo');

            // Guardar ID na sessão para a página de verificação
            $_SESSION['verificar_id']   = $res['id'];
            $_SESSION['verificar_tipo'] = 'registo';

            if (!$enviado) {
                // Falha no envio - auto-verificar também
                db()->prepare('UPDATE utilizadores SET verificado = 1 WHERE id = ?')->execute([$res['id']]);
                $_SESSION['user_id'] = $res['id'];
                flash('success', 'Conta criada! Email não enviado (erro SMTP) - conta verificada automaticamente.');
                header('Location: ' . SITE_URL . '/index.php');
                exit;
            }

            header('Location: ' . SITE_URL . '/pages/verificar.php');
            exit;
        }
    }
}

$page_title = 'Criar Conta';
include dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-content" style="display:flex; align-items:center; justify-content:center; padding:2rem; min-height:calc(100vh - 72px);">
  <div class="form-container" style="max-width:600px;">
    <div style="display:flex; justify-content:center; margin-bottom:2rem;">
      <img src="<?= SITE_URL ?>/assets/images/logo_icon.png" alt="Segredo Lusitano" style="height:80px;width:80px;object-fit:contain;filter:drop-shadow(0 0 10px rgba(201,168,76,.5));">
    </div>
    <h1 class="form-title" style="text-align:center;">Torna-te um Explorador</h1>
    <p class="form-subtitle" style="text-align:center;">Junta-te à comunidade de Segredos Lusitanos</p>

    <form method="POST" novalidate>
      <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
        <div class="form-group">
          <label for="nome">Nome Completo</label>
          <input type="text" id="nome" name="nome" value="<?= h($_POST['nome'] ?? '') ?>"
                 placeholder="Gonçalo Teixeira" required>
          <?php if (isset($erros['nome'])): ?><div class="form-error"><?= h($erros['nome']) ?></div><?php endif; ?>
        </div>
        <div class="form-group">
          <label for="username">Username</label>
          <input type="text" id="username" name="username" value="<?= h($_POST['username'] ?? '') ?>"
                 placeholder="Gonçalo123" required>
          <?php if (isset($erros['username'])): ?><div class="form-error"><?= h($erros['username']) ?></div><?php endif; ?>
        </div>
      </div>
      <div class="form-group">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" value="<?= h($_POST['email'] ?? '') ?>"
               placeholder="o.teu@email.com" required>
        <?php if (isset($erros['email'])): ?><div class="form-error"><?= h($erros['email']) ?></div><?php endif; ?>
      </div>
      <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
        <div class="form-group">
          <label for="password">Password</label>
          <input type="password" id="password" name="password" placeholder="Mínimo 6 caracteres" required>
          <?php if (isset($erros['password'])): ?><div class="form-error"><?= h($erros['password']) ?></div><?php endif; ?>
        </div>
        <div class="form-group">
          <label for="confirm">Confirmar Password</label>
          <input type="password" id="confirm" name="confirm" placeholder="Repetir password" required>
          <?php if (isset($erros['confirm'])): ?><div class="form-error"><?= h($erros['confirm']) ?></div><?php endif; ?>
        </div>
      </div>
      <!-- Termos e Condições -->
      <div class="form-group" style="margin-bottom:.75rem;">
        <input type="checkbox" id="aceitar-termos" name="aceitar_termos" style="display:none;" <?= isset($_POST['aceitar_termos']) ? 'checked' : '' ?>>
        <input type="hidden" id="termos-aceites-em" name="termos_aceites_em" value="<?= h($_POST['termos_aceites_em'] ?? '') ?>">
        <p style="font-size:.85rem;line-height:1.6;color:var(--texto-muted);margin:0;">
          <a href="#" onclick="document.getElementById('modal-termos').style.display='flex';return false;" class="form-link" style="font-weight:600;">Termos e Condições</a>
          &nbsp;&mdash; Li e aceito os termos. Compreendo que a visita a locais pode envolver riscos e que a entrada em propriedade privada é da responsabilidade exclusiva do utilizador. O Segredo Lusitano não se responsabiliza por qualquer dano, acidente ou invasão de propriedade.
        </p>
        <!-- Checkbox visual de confirmação — marcada automaticamente ao aceitar no modal -->
        <div style="display:flex;align-items:center;gap:.5rem;margin-top:.5rem;">
          <input type="checkbox" id="termos-status-visual" tabindex="-1"
                 style="accent-color:var(--verde);width:15px;height:15px;cursor:default;pointer-events:none;"
                 <?= isset($_POST['aceitar_termos']) ? 'checked' : '' ?>>
          <label style="font-size:.82rem;color:var(--texto-muted);cursor:default;user-select:none;margin:0;">
            Termos e condições aceites
          </label>
        </div>
        <div id="erro-termos-js" style="display:none;" class="form-error">Deves aceitar os Termos e Condições para continuar.</div>
        <?php if (isset($erros['termos'])): ?><div class="form-error"><?= h($erros['termos']) ?></div><?php endif; ?>
      </div>
      <button type="button" id="btn-criar" class="btn btn-primary" style="width:100%; justify-content:center; margin-top:.5rem;" onclick="submeterComTermos()">
        <i class="fas fa-user-plus"></i> Criar Conta
      </button>
    </form>

    <?php if (GOOGLE_CLIENT_ID && GOOGLE_CLIENT_ID !== ''): ?>
    <div class="form-divider">ou entrar com</div>

    <!-- Google Sign-In (Google Identity Services - 2024) -->
    <div style="display:flex;flex-direction:column;align-items:center;gap:.75rem;">
      <div id="g_id_onload"
           data-client_id="<?= GOOGLE_CLIENT_ID ?>"
           data-context="signup"
           data-callback="handleGoogleSignIn"
           data-auto_prompt="false">
      </div>
      <div class="g_id_signin"
           data-type="standard"
           data-shape="rectangular"
           data-theme="outline"
           data-text="signup_with"
           data-size="large"
           data-locale="pt-PT"
           data-width="300">
      </div>
      <p id="google-msg" style="color:#c0392b;font-size:.85rem;display:none;"></p>
    </div>
    <!-- GitHub Sign-In -->
    <div style="display:flex;justify-content:center;">
      <a href="#" onclick="verificarTermosParaSocial('<?= SITE_URL ?>/pages/github_redirect.php'); return false;"
        style="display:flex;align-items:center;justify-content:space-between;
                width:300px;padding:.65rem 1rem;border:1.5px solid #d0d5dd;
                border-radius:3px;background:#fff;color:#1e1e1e;
                font-size:.9rem;font-weight:500;text-decoration:none;
                transition:background .2s;margin-top:.5rem;">
        <div style="display:flex;align-items:center;gap:.65rem;">
          <i class="fab fa-github" style="font-size:1.1rem;color:#24292e;"></i>
          <span>Iniciar sessão com GitHub</span>
        </div>
        <i class="fab fa-github" style="font-size:1.1rem;color:#24292e;"></i>
      </a>
    </div>
    <?php endif; ?>

    <p style="text-align:center; font-size:.9rem;">
      Já tens conta? <a href="<?= SITE_URL ?>/pages/login.php" class="form-link">Iniciar sessão</a>
    </p>
  </div>
</div>

<!-- Google Identity Services (nova biblioteca 2024) -->
<?php if (GOOGLE_CLIENT_ID && GOOGLE_CLIENT_ID !== ''): ?>
<script src="https://accounts.google.com/gsi/client" async defer></script>
<script>
window._pendingGoogleResponse = null;

function handleGoogleSignIn(response) {
  const termos = document.getElementById('aceitar-termos');
  if (!termos || !termos.checked) {
    window._pendingGoogleResponse = response;
    document.getElementById('modal-termos').style.display = 'flex';
    return;
  }
  _executarGoogleLogin(response);
}

function _executarGoogleLogin(response) {
  const msg = document.getElementById('google-msg');
  const termosEm = document.getElementById('termos-aceites-em')?.value || '';
  fetch('<?= SITE_URL ?>/pages/google_auth.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: 'id_token=' + encodeURIComponent(response.credential) + '&termos_aceites_em=' + encodeURIComponent(termosEm)
  })
  .then(r => r.json())
  .then(data => {
    if (data.ok) {
      window.location.href = data.redirect;
    } else {
      msg.textContent = data.msg || 'Erro ao entrar com Google.';
      msg.style.display = 'block';
    }
  })
  .catch(() => {
    msg.textContent = 'Erro de ligação. Tenta novamente.';
    msg.style.display = 'block';
  });
}

function verificarTermosParaSocial(url) {
  const termos = document.getElementById('aceitar-termos');
  if (termos && termos.checked) {
    const termosEm = document.getElementById('termos-aceites-em')?.value || '';
    window.location.href = url + (url.includes('?') ? '&' : '?') + 'termos_aceites_em=' + encodeURIComponent(termosEm);
  } else {
    window._pendingGithubUrl = url;
    document.getElementById('modal-termos').style.display = 'flex';
  }
}
</script>
<?php endif; ?>

<!-- Modal Termos e Condições -->
<div id="modal-termos" style="display:none;position:fixed;inset:0;z-index:9000;background:rgba(0,0,0,.55);align-items:center;justify-content:center;padding:1rem;">
  <div style="background:#fff;width:100%;max-width:600px;max-height:88vh;display:flex;flex-direction:column;box-shadow:0 8px 32px rgba(0,0,0,.2);">

    <div style="display:flex;align-items:center;justify-content:space-between;padding:1.25rem 1.75rem;border-bottom:1px solid #e5e7eb;flex-shrink:0;">
      <h3 style="font-size:1rem;font-weight:700;color:var(--verde-escuro);margin:0;">Termos e Condições de Utilização</h3>
      <button onclick="document.getElementById('modal-termos').style.display='none'" style="background:none;border:none;font-size:1.1rem;cursor:pointer;color:#9ca3af;padding:.2rem .4rem;">&#x2715;</button>
    </div>

    <div style="overflow-y:auto;flex:1;padding:1.5rem 1.75rem;font-size:.85rem;line-height:1.75;color:#374151;">

      <p style="margin:0 0 1.25rem;color:#6b7280;">Última atualização: <?= date('d/m/Y') ?>. Lê atentamente antes de criares conta no Segredo Lusitano. O registo implica a aceitação integral destes termos.</p>

      <p style="font-weight:700;margin:0 0 .3rem;">1. Aceitação dos Termos</p>
      <p style="margin:0 0 1rem;">Ao criares conta ou iniciares sessão no Segredo Lusitano, declaras ter lido, compreendido e aceite os presentes Termos e Condições ("Termos"), bem como a nossa Política de Privacidade. Caso não concordes, deves abster-te de utilizar a plataforma. A aceitação é obrigatória e constitui um contrato vinculativo.</p>

      <p style="font-weight:700;margin:0 0 .3rem;">2. Elegibilidade e Registo</p>
      <p style="margin:0 0 1rem;">O registo está disponível a maiores de 16 anos. Menores entre os 13 e os 15 anos necessitam do consentimento expresso do titular da responsabilidade parental, nos termos do art.º 8.º do RGPD (Reg. UE 2016/679) e da Lei n.º 58/2019. O utilizador compromete-se a fornecer informações verdadeiras, atualizadas e completas, e a manter a confidencialidade das suas credenciais de acesso. Cada pessoa singular pode deter apenas uma conta.</p>

      <p style="font-weight:700;margin:0 0 .3rem;">3. Conteúdo Gerado pelo Utilizador</p>
      <p style="margin:0 0 1rem;">O utilizador é o único responsável pelo conteúdo que publica (locais, fotografias, comentários e descrições). Ao partilhares conteúdo, concedes ao Segredo Lusitano uma licença não exclusiva, gratuita e mundial para o exibir, reproduzir e distribuir na plataforma. Garantes que és titular dos direitos sobre esse conteúdo ou tens autorização para o partilhar. É expressamente proibido publicar conteúdo falso, enganoso, difamatório, que viole direitos de terceiros ou que incentive atividades ilegais.</p>

      <p style="font-weight:700;margin:0 0 .3rem;">4. Propriedade Privada e Legalidade</p>
      <p style="margin:0 0 1rem;">O Segredo Lusitano não verifica a legalidade do acesso aos locais publicados. Muitos locais podem situar-se em propriedade privada ou de acesso condicionado. O utilizador é exclusivamente responsável por verificar e cumprir as disposições legais aplicáveis, nomeadamente o regime da violação de domicílio e perturbação da vida privada (art.os 190.º e 192.º do Código Penal) e as normas de acesso a reservas naturais e zonas protegidas. O Segredo Lusitano não incentiva, apoia, nem se responsabiliza por qualquer violação de propriedade privada ou de normas de acesso.</p>

      <p style="font-weight:700;margin:0 0 .3rem;">5. Riscos e Isenção de Responsabilidade por Segurança</p>
      <p style="margin:0 0 1rem;">A prática de atividades ao ar livre e a visita a locais remotos ou de difícil acesso envolve riscos físicos inerentes, incluindo quedas, condições climatéricas adversas, afogamento e outros perigos. O utilizador assume esses riscos na totalidade. O Segredo Lusitano não se responsabiliza por acidentes, lesões, morte, danos materiais ou qualquer outra consequência resultante da visita a locais partilhados na plataforma. Recomendamos que informes alguém da tua localização, levas equipamento adequado e avalias as condições antes de qualquer visita.</p>

      <p style="font-weight:700;margin:0 0 .3rem;">6. Proteção de Dados Pessoais (RGPD)</p>
      <p style="margin:0 0 1rem;">Os teus dados pessoais são tratados em conformidade com o Regulamento Geral sobre a Proteção de Dados (RGPD) e a Lei n.º 58/2019. Os dados recolhidos (nome, email, localização de publicações) destinam-se exclusivamente ao funcionamento da plataforma. Tens direito de acesso, retificação, apagamento, portabilidade e oposição ao tratamento. Para exerceres esses direitos, podes apagar a tua conta em Perfil → Zona de Perigo, ou contactar-nos através dos meios disponíveis na plataforma.</p>

      <p style="font-weight:700;margin:0 0 .3rem;">7. Conduta Proibida</p>
      <p style="margin:0 0 1rem;">É estritamente proibido: (a) publicar conteúdo que incite à prática de crimes ou violência; (b) fazer-se passar por outra pessoa ou entidade; (c) tentar obter acesso não autorizado à plataforma; (d) usar meios automatizados (bots, scrapers) sem autorização; (e) assediar, ameaçar ou discriminar outros utilizadores. O incumprimento pode resultar na suspensão imediata da conta e, se aplicável, participação às autoridades competentes.</p>

      <p style="font-weight:700;margin:0 0 .3rem;">8. Limitação de Responsabilidade</p>
      <p style="margin:0 0 1rem;">A plataforma é disponibilizada "tal como está". O Segredo Lusitano não garante a exatidão, integridade ou atualidade da informação publicada pelos utilizadores, e não se responsabiliza por danos diretos, indiretos, incidentais ou consequentes resultantes do uso da plataforma ou da visita a locais nela partilhados, na máxima extensão permitida pela lei portuguesa.</p>

      <p style="font-weight:700;margin:0 0 .3rem;">9. Propriedade Intelectual</p>
      <p style="margin:0 0 1rem;">O código, design, logótipo e demais elementos da plataforma são propriedade dos seus criadores e estão protegidos pela legislação de direitos de autor e propriedade intelectual. É proibida a reprodução total ou parcial sem autorização expressa.</p>

      <p style="font-weight:700;margin:0 0 .3rem;">10. Suspensão e Rescisão</p>
      <p style="margin:0 0 1rem;">O Segredo Lusitano reserva-se o direito de suspender ou encerrar contas que violem estes Termos, sem aviso prévio e sem qualquer responsabilidade. O utilizador pode apagar a sua conta a qualquer momento através do respetivo perfil.</p>

      <p style="font-weight:700;margin:0 0 .3rem;">11. Alterações aos Termos</p>
      <p style="margin:0 0 1rem;">O Segredo Lusitano pode atualizar estes Termos a qualquer momento. A data de "última atualização" será revista em conformidade. A continuação da utilização da plataforma após a publicação de alterações constitui aceitação das mesmas.</p>

      <p style="font-weight:700;margin:0 0 .3rem;">12. Lei Aplicável e Foro</p>
      <p style="margin:0 0 0;">Estes Termos são regidos pela lei portuguesa. Para a resolução de quaisquer litígios emergentes da interpretação ou execução dos presentes Termos, é competente o foro da comarca de Lisboa, com expressa renúncia a qualquer outro.</p>

    </div>

    <div style="padding:1rem 1.75rem;border-top:1px solid #e5e7eb;display:flex;justify-content:flex-end;gap:.75rem;flex-shrink:0;">
      <button onclick="document.getElementById('modal-termos').style.display='none'"
              style="background:none;border:1px solid #d1d5db;padding:.55rem 1.1rem;cursor:pointer;font-size:.85rem;color:#6b7280;">
        Fechar
      </button>
      <button onclick="aceitarTermos()" class="btn btn-primary btn-sm">
        <i class="fas fa-check"></i> Li e Aceito os Termos
      </button>
    </div>

  </div>
</div>
<script>
function submeterComTermos() {
  const termos = document.getElementById('aceitar-termos');
  const erroEl = document.getElementById('erro-termos-js');
  if (!termos.checked) {
    erroEl.style.display = 'block';
    erroEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
    return;
  }
  erroEl.style.display = 'none';
  document.querySelector('form[method="POST"]').submit();
}

function aceitarTermos() {
  document.getElementById('modal-termos').style.display = 'none';

  // Marcar checkbox oculto (para POST) e checkbox visual
  document.getElementById('aceitar-termos').checked = true;
  document.getElementById('termos-status-visual').checked = true;
  document.getElementById('erro-termos-js').style.display = 'none';

  // Guardar data/hora de aceitação na variável (hidden input)
  const now = new Date();
  const pad = n => String(n).padStart(2, '0');
  const dt = now.getFullYear() + '-' + pad(now.getMonth()+1) + '-' + pad(now.getDate())
           + ' ' + pad(now.getHours()) + ':' + pad(now.getMinutes()) + ':' + pad(now.getSeconds());
  document.getElementById('termos-aceites-em').value = dt;

  if (typeof _executarGoogleLogin === 'function' && window._pendingGoogleResponse) {
    _executarGoogleLogin(window._pendingGoogleResponse);
    window._pendingGoogleResponse = null;
  } else if (window._pendingGithubUrl) {
    const url = window._pendingGithubUrl;
    window._pendingGithubUrl = null;
    window.location.href = url + (url.includes('?') ? '&' : '?') + 'termos_aceites_em=' + encodeURIComponent(dt);
  }
  // Para registo por email: NÃO submeter automaticamente.
  // O utilizador deve clicar "Criar Conta" com todos os campos válidos.
}
</script>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
