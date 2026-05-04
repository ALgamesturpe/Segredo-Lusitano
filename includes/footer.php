<?php
// ============================================================
// SEGREDO LUSITANO - Rodapé (include)
// ============================================================
?>
<footer class="site-footer">
  <div class="footer-inner">
    <div class="footer-brand">
      <img src="<?= SITE_URL ?>/assets/images/logo_icon.png" alt="Segredo Lusitano" style="height:36px;width:36px;object-fit:contain;filter:drop-shadow(0 0 6px rgba(201,168,76,.4));">
      <span style="font-family:'Playfair Display',serif;color:var(--creme);font-size:1rem;">Segredo <strong style="color:var(--dourado);">Lusitano</strong></span>
    </div>
    <p class="footer-tagline">Descobre o Portugal de forma fácil!</p>
    <nav class="footer-links">
      <a href="<?= SITE_URL ?>/index.php">Início</a>
      <a href="<?= SITE_URL ?>/pages/explorar.php">Explorar</a>
      <a href="<?= SITE_URL ?>/pages/mapa.php">Mapa</a>
      <a href="<?= SITE_URL ?>/pages/ranking.php">Ranking</a>
    </nav>
    <p class="footer-copy">&copy; <?= date('Y') ?> Segredo Lusitano &mdash; Projeto PAP &mdash; Gonçalo Teixeira</p>
  </div>
</footer>

<!-- Modal de aviso para utilizadores não autenticados -->
<div id="modal-login-aviso" style="display:none;position:fixed;inset:0;z-index:9000;background:rgba(0,0,0,.5);align-items:center;justify-content:center;padding:1rem;">
  <div style="background:#fff;border-radius:4px;padding:2rem;max-width:380px;width:100%;text-align:center;box-shadow:0 8px 32px rgba(0,0,0,.2);">
    <i class="fas fa-lock" style="font-size:2.5rem;color:var(--verde);margin-bottom:1rem;display:block;"></i>
    <h3 style="margin-bottom:.5rem;">Precisas de uma conta</h3>
    <p style="color:var(--texto-muted);font-size:.95rem;margin-bottom:1.5rem;" id="modal-login-aviso-msg">
      Para interagir precisas de iniciar sessão.
    </p>
    <div style="display:flex;gap:.75rem;justify-content:center;">
      <a id="modal-login-btn" href="<?= SITE_URL ?>/pages/login.php" class="btn btn-primary">
        <i class="fas fa-sign-in-alt"></i> Iniciar Sessão
      </a>
      <button onclick="fecharAvisoLogin()" class="btn btn-sm"
              style="border:1px solid var(--creme-escuro);color:var(--texto-muted);">
        Cancelar
      </button>
    </div>
  </div>
</div>

<!-- Scripts globais -->


<script>
function mostrarAvisoLogin(msg, redirectUrl) {
  const modal = document.getElementById('modal-login-aviso');
  const msgEl = document.getElementById('modal-login-aviso-msg');
  const btn   = document.getElementById('modal-login-btn');
  if (msgEl) msgEl.textContent = msg || 'Para interagir precisas de iniciar sessão.';
  if (btn)   btn.href = redirectUrl || '<?= SITE_URL ?>/pages/login.php';
  if (modal) modal.style.display = 'flex';
}
function fecharAvisoLogin() {
  document.getElementById('modal-login-aviso').style.display = 'none';
}
</script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="<?= SITE_URL ?>/assets/js/main.js"></script>
<?= $extra_scripts ?? '' ?>
<!-- Script botões seguir nos cards -->
<script>
(function() {
  const SITE = "<?= SITE_URL ?>";
  document.addEventListener('click', async function(e) {
    const btn = e.target.closest('.btn-seguir-card');
    if (!btn) return;
    e.preventDefault();
    const id = btn.dataset.id;
    const res = await fetch(`${SITE}/pages/seguir.php`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: `id=${id}`
    });
    if (res.status === 401) { mostrarAvisoLogin('Precisas de iniciar sessão para seguir utilizadores.', `${SITE}/pages/login.php`); return; }
    const data = await res.json();
    if (!data.ok) return;
    const aSeguir = data.a_seguir;
    btn.dataset.seguindo = aSeguir ? '1' : '0';
    btn.style.borderColor = aSeguir ? 'var(--creme-escuro)' : 'var(--verde)';
    btn.style.color = aSeguir ? 'var(--texto-muted)' : 'var(--verde)';
    document.querySelectorAll(`.btn-seguir-card[data-id="${id}"]`).forEach(b => {
    //quando estou a seguir, quero que todos os botões desse utilizador fiquem com o estado "A seguir", e quando deixar de seguir, que fiquem "Seguir"
    b.dataset.seguindo = aSeguir ? '1' : '0';
    b.style.borderColor = aSeguir ? 'var(--creme-escuro)' : 'var(--verde)';
    b.style.color = aSeguir ? 'var(--texto-muted)' : 'var(--verde)';
    b.innerHTML = `<i class="fas ${aSeguir ? 'fa-user-check' : 'fa-user-plus'}"></i> ${aSeguir ? 'A seguir' : 'Seguir'}`;
});
  });
})();
</script>
</body>
</html>