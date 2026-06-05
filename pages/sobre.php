<?php
// SEGREDO LUSITANO — Sobre o Site
require_once dirname(__DIR__) . '/includes/functions.php';
$page_title = 'Sobre';
include dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-content">
<section class="section">
  <div class="container" style="max-width:860px;">

    <!-- Cabeçalho -->
    <div style="margin-bottom:2rem;">
      <h2 style="margin-bottom:.4rem;">Sobre o Segredo Lusitano</h2>
      <p style="color:var(--texto-muted);margin:0;">Uma plataforma criada por e para exploradores de Portugal.</p>
    </div>

    <hr style="border:none;border-top:2px solid var(--dourado);margin-bottom:2rem;">

    <!-- O que é -->
    <div style="margin-bottom:2rem;">
      <h3 style="font-size:1.1rem;margin-bottom:1rem;">O que é?</h3>
      <p style="color:var(--texto);line-height:1.8;margin:0;text-align:justify;">
        O <strong>Segredo Lusitano</strong> é uma plataforma colaborativa dedicada à descoberta e partilha de locais
        escondidos e pouco conhecidos de Portugal. Acreditamos que o país tem muito mais para oferecer do
        que os destinos turísticos habituais — existem cascatas, miradouros, aldeias, praias e trilhos que os
        mapas convencionais simplesmente não mostram. Esta plataforma foi criada para dar voz a quem
        explora e quer partilhar essas descobertas com outros.
      </p>
    </div>

    <hr style="border:none;border-top:2px solid var(--dourado);margin-bottom:2rem;">

    <!-- Como funciona -->
    <div style="margin-bottom:2rem;">
      <h3 style="font-size:1.1rem;margin-bottom:1rem;">Como funciona?</h3>
      <p style="color:var(--texto);line-height:1.8;margin:0 0 1rem;text-align:justify;">
        Qualquer utilizador registado pode submeter um local com fotografia, descrição, coordenadas GPS e
        nível de dificuldade de acesso. Após uma breve moderação para garantir a qualidade e autenticidade
        do conteúdo, o local fica disponível para toda a comunidade.
      </p>
      <p style="color:var(--texto);line-height:1.8;margin:0;text-align:justify;">
        Para além de partilhar, é possível explorar o mapa interativo, fazer check-in nos locais que visitas,
        comentar, guardar favoritos, seguir outros exploradores e acompanhar o teu progresso através do
        sistema de pontos e ranking.
      </p>
    </div>

    <hr style="border:none;border-top:2px solid var(--dourado);margin-bottom:2rem;">

    <!-- Contacto -->
    <div>
      <h3 style="font-size:1.1rem;margin-bottom:1rem;">Contacto</h3>
      <p style="color:var(--texto);line-height:1.8;margin:0 0 1.25rem;text-align:justify;">
        Tens sugestões, encontraste um erro ou simplesmente queres entrar em contacto? Podes enviar um
        email diretamente através do endereço abaixo.
      </p>
      <div style="display:flex;flex-direction:column;gap:.5rem;">
        <a href="mailto:gvg.pt0123@gmail.com" style="color:var(--verde);font-weight:600;font-size:.95rem;">gvg.pt0123@gmail.com</a>
        <a href="tel:932703962" style="color:var(--verde);font-weight:600;font-size:.95rem;">932 703 962</a>
        <span style="color:var(--texto-muted);font-size:.88rem;">Gonçalo Teixeira &mdash; Projeto PAP 2026</span>
      </div>
    </div>

  </div>
</section>
</div>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
