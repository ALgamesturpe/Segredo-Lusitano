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
    <p class="footer-tagline">Descobre o Portugal que os mapas não mostram.</p>
    <nav class="footer-links">
      <a href="<?= SITE_URL ?>/index.php">Início</a>
      <a href="<?= SITE_URL ?>/pages/explorar.php">Explorar</a>
      <a href="<?= SITE_URL ?>/pages/mapa.php">Mapa</a>
      <a href="<?= SITE_URL ?>/pages/ranking.php">Ranking</a>
    </nav>
    <p class="footer-copy">&copy; <?= date('Y') ?> Segredo Lusitano &mdash; Projeto PAP &mdash; Gonçalo Teixeira</p>
  </div>
</footer>

<!-- Scripts globais -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="<?= SITE_URL ?>/assets/js/main.js"></script>
<?= $extra_scripts ?? '' ?>
</body>
</html>
