<?php
// SEGREDO LUSITANO — Sobre o Site
require_once dirname(__DIR__) . '/includes/functions.php';
$page_title = 'Sobre';
include dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-content">
<section class="section">
  <div class="container" style="max-width:720px;">

    <h2 style="font-family:'Playfair Display',serif;font-size:2rem;margin-bottom:.5rem;">Sobre o Segredo Lusitano</h2>
    <p style="color:var(--texto-muted);font-size:1rem;margin-bottom:2.5rem;border-bottom:2px solid var(--dourado);padding-bottom:1.25rem;">
      Uma plataforma criada por e para exploradores de Portugal.
    </p>

    <div style="display:flex;flex-direction:column;gap:2rem;">

      <div style="padding-bottom:2rem;border-bottom:2px solid var(--dourado);">
        <h3 style="font-size:1.05rem;margin-bottom:.75rem;color:var(--verde-escuro);">O que é?</h3>
        <p style="line-height:1.85;color:var(--texto);text-align:justify;margin:0;">
          O Segredo Lusitano é uma plataforma colaborativa dedicada à descoberta e partilha de locais escondidos e pouco conhecidos de Portugal.
          Acreditamos que o país tem muito mais para oferecer do que os destinos turísticos habituais — existem cascatas, miradouros, aldeias, praias e trilhos que os mapas convencionais simplesmente não mostram.
          Esta plataforma foi criada para dar voz a quem explora e quer partilhar essas descobertas com outros.
        </p>
      </div>

      <div style="padding-bottom:2rem;border-bottom:2px solid var(--dourado);">
        <h3 style="font-size:1.05rem;margin-bottom:.75rem;color:var(--verde-escuro);">Como funciona?</h3>
        <p style="line-height:1.85;color:var(--texto);text-align:justify;margin:0 0 .85rem;">
          Qualquer utilizador registado pode submeter um local com fotografia, descrição, coordenadas GPS e nível de dificuldade de acesso.
          Após uma breve moderação para garantir a qualidade e autenticidade do conteúdo, o local fica disponível para toda a comunidade.
        </p>
        <p style="line-height:1.85;color:var(--texto);text-align:justify;margin:0;">
          Para além de partilhar, é possível explorar o mapa interativo, fazer check-in nos locais que visitas, comentar, guardar favoritos, seguir outros exploradores e acompanhar o teu progresso através do sistema de pontos e ranking.
        </p>
      </div>

      <div>
        <h3 style="font-size:1.05rem;margin-bottom:.75rem;color:var(--verde-escuro);">Contacto</h3>
        <p style="line-height:1.85;color:var(--texto);text-align:justify;margin:0 0 1rem;">
          Tens sugestões, encontraste um erro ou simplesmente queres entrar em contacto? Podes enviar um email diretamente através do endereço abaixo.
        </p>
        <div style="display:flex;flex-direction:column;gap:.4rem;">
          <a href="https://mail.google.com/mail/?view=cm&to=gvg.pt0123@gmail.com&su=Segredo+Lusitano" target="_blank" rel="noopener" style="color:var(--verde);font-weight:600;">gvg.pt0123@gmail.com</a>
          <a href="tel:932703962" style="color:var(--verde);font-weight:600;">932 703 962</a>
          <span style="color:var(--texto-muted);font-size:.9rem;margin-top:.25rem;">Gonçalo Teixeira &mdash; Projeto PAP <?= date('Y') ?></span>
        </div>
      </div>

    </div>

  </div>
</section>
</div>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
