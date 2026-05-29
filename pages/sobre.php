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
    <div class="section-header" style="margin-bottom:2.5rem;">
      <h2>Sobre o Segredo Lusitano</h2>
      <p>Uma plataforma criada por e para exploradores de Portugal.</p>
    </div>

    <!-- O que é -->
    <div style="background:var(--branco);border-radius:var(--radius-lg);padding:2rem;margin-bottom:1.5rem;box-shadow:var(--sombra-sm);">
      <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:1rem;">
        <i class="fas fa-compass" style="font-size:1.4rem;color:var(--dourado);"></i>
        <h3 style="margin:0;font-size:1.2rem;">O que é o Segredo Lusitano?</h3>
      </div>
      <p style="color:var(--texto);line-height:1.8;margin:0;">
        O <strong>Segredo Lusitano</strong> é uma plataforma colaborativa de descoberta de locais escondidos e pouco conhecidos de Portugal.
        Acreditamos que o país tem muito mais para oferecer do que os destinos turísticos habituais —
        há cascatas, miradouros, aldeias, praias e trilhos que os mapas convencionais simplesmente não mostram.
        Aqui, a comunidade é quem guarda e partilha esses segredos.
      </p>
    </div>

    <!-- Objetivo -->
    <div style="background:var(--branco);border-radius:var(--radius-lg);padding:2rem;margin-bottom:1.5rem;box-shadow:var(--sombra-sm);">
      <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:1rem;">
        <i class="fas fa-bullseye" style="font-size:1.4rem;color:var(--verde);"></i>
        <h3 style="margin:0;font-size:1.2rem;">Qual o Nosso Objetivo?</h3>
      </div>
      <p style="color:var(--texto);line-height:1.8;margin:0 0 1rem;">
        O objetivo principal é criar um mapa vivo de Portugal, construído pela comunidade de exploradores, onde qualquer pessoa pode:
      </p>
      <ul style="list-style:none;display:flex;flex-direction:column;gap:.65rem;padding:0;margin:0;">
        <?php
        $items = [
          ['fas fa-map-marker-alt', 'var(--verde)',  'Partilhar locais únicos e autênticos que descobriu em Portugal'],
          ['fas fa-camera',         'var(--dourado)','Publicar fotografias e descrições detalhadas para ajudar outros viajantes'],
          ['fas fa-comments',       'var(--verde)',  'Comentar, recomendar e interagir com a comunidade de exploradores'],
          ['fas fa-trophy',         'var(--dourado)','Ganhar pontos e subir no ranking de exploradores mais ativos'],
        ];
        foreach ($items as [$icon, $color, $text]):
        ?>
        <li style="display:flex;align-items:flex-start;gap:.75rem;">
          <i class="<?= $icon ?>" style="color:<?= $color ?>;margin-top:.2rem;flex-shrink:0;"></i>
          <span style="color:var(--texto);font-size:.95rem;line-height:1.7;"><?= $text ?></span>
        </li>
        <?php endforeach; ?>
      </ul>
    </div>

    <!-- Como funciona -->
    <div style="background:var(--branco);border-radius:var(--radius-lg);padding:2rem;margin-bottom:1.5rem;box-shadow:var(--sombra-sm);">
      <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:1.25rem;">
        <i class="fas fa-cogs" style="font-size:1.4rem;color:var(--verde);"></i>
        <h3 style="margin:0;font-size:1.2rem;">Como Funciona?</h3>
      </div>
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:1rem;">
        <?php
        $passos = [
          ['fas fa-user-plus',    'Cria uma conta',      'Regista-te gratuitamente com email, Google ou GitHub.'],
          ['fas fa-location-dot', 'Partilha um local',   'Submete um local com foto, descrição e localização no mapa.'],
          ['fas fa-check-circle', 'Aprovação',           'A equipa de moderação aprova o local para garantir qualidade.'],
          ['fas fa-star',         'Ganha pontos',        'Cada local aprovado, comentário e like recebido valem pontos.'],
        ];
        foreach ($passos as [$icon, $titulo, $desc]):
        ?>
        <div style="background:var(--creme);border-radius:var(--radius);padding:1rem;text-align:center;">
          <i class="<?= $icon ?>" style="font-size:1.6rem;color:var(--verde);margin-bottom:.5rem;display:block;"></i>
          <div style="font-weight:700;font-size:.9rem;margin-bottom:.35rem;"><?= $titulo ?></div>
          <div style="font-size:.82rem;color:var(--texto-muted);line-height:1.5;"><?= $desc ?></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Projeto PAP -->
    <div style="background:var(--verde-escuro);border-radius:var(--radius-lg);padding:2rem;margin-bottom:1.5rem;color:var(--creme);">
      <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:1rem;">
        <i class="fas fa-graduation-cap" style="font-size:1.4rem;color:var(--dourado);"></i>
        <h3 style="margin:0;font-size:1.2rem;color:var(--creme);">Projeto PAP</h3>
      </div>
      <p style="line-height:1.8;margin:0;color:rgba(245,239,230,.85);">
        O Segredo Lusitano foi desenvolvido como projeto de <strong style="color:var(--dourado);">Prova de Aptidão Profissional (PAP)</strong>
        no âmbito do curso de Técnico de Gestão e Programação de Sistemas Informáticos.
        O projeto tem como objetivo demonstrar competências no desenvolvimento de aplicações web completas,
        incluindo base de dados, autenticação, sistema de pontos, moderação de conteúdo e design responsivo.
      </p>
    </div>

    <!-- Contacto -->
    <div style="background:var(--branco);border-radius:var(--radius-lg);padding:2rem;box-shadow:var(--sombra-sm);">
      <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:1.25rem;">
        <i class="fas fa-envelope" style="font-size:1.4rem;color:var(--dourado);"></i>
        <h3 style="margin:0;font-size:1.2rem;">Contacto</h3>
      </div>
      <p style="color:var(--texto-muted);line-height:1.8;margin:0 0 1rem;">
        Tens sugestões, encontraste um erro ou queres entrar em contacto connosco? Fala connosco:
      </p>
      <div style="display:flex;flex-direction:column;gap:.65rem;">
        <div style="display:flex;align-items:center;gap:.75rem;">
          <i class="fas fa-envelope" style="color:var(--verde);width:1.1rem;text-align:center;flex-shrink:0;"></i>
          <a href="mailto:gvg.pt0123@gmail.com" style="color:var(--verde);font-weight:600;font-size:.95rem;">gvg.pt0123@gmail.com</a>
        </div>
        <div style="display:flex;align-items:center;gap:.75rem;">
          <i class="fab fa-github" style="color:var(--texto-muted);width:1.1rem;text-align:center;flex-shrink:0;"></i>
          <span style="color:var(--texto-muted);font-size:.9rem;">Gonçalo Teixeira &mdash; Projeto PAP <?= date('Y') ?></span>
        </div>
      </div>
    </div>

  </div>
</section>
</div>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
