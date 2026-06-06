<?php
// SEGREDO LUSITANO — Detalhe do Local
require_once dirname(__DIR__) . '/includes/functions.php';

$id = (int)($_GET['id'] ?? 0);
$local = $id ? get_local($id) : null;

if (!$local || ($local['estado'] !== 'aprovado' && !is_admin()) || ($local['bloqueado'] && !is_admin())) {
    header('Location: ' . SITE_URL . '/pages/explorar.php');
    exit;
}

incrementar_vistas($id);

$user            = auth_user();
$comentarios     = get_comentarios($id);
$fotos           = get_fotos($id);
$liked           = $user ? user_liked($id, $user['id']) : false;
$motivos_denuncia = motivos_denuncia();
$local_bloqueado  = ((int)($local['bloqueado'] ?? 0) === 1);

$guardado = false;
if ($user) {
    _migrar_favoritos();
    $stFav = db()->prepare('SELECT id FROM favoritos WHERE utilizador_id = ? AND local_id = ?');
    $stFav->execute([$user['id'], $id]);
    $guardado = (bool)$stFav->fetch();
}

$ja_checkin   = $user ? user_fez_checkin($id, $user['id']) : false;
$atualizacoes = get_atualizacoes_local($id);

// Verificar se há fotos de outros utilizadores (para mostrar botão de denúncia)
$tem_fotos_alheias  = false;
$tem_fotos_proprias = false;
if ($user && !is_admin()) {
    foreach ($fotos as $foto) {
        if ((int)$foto['utilizador_id'] !== (int)$user['id']) $tem_fotos_alheias  = true;
        else                                                   $tem_fotos_proprias = true;
        if ($tem_fotos_alheias && $tem_fotos_proprias) break;
    }
}

// --- POST: Comentário ---
$erro_com = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['comentario'])) {
    if (!$user) { header('Location: ' . SITE_URL . '/pages/login.php'); exit; }
    if ($local_bloqueado) {
        flash('error', 'Este post esta bloqueado e nao aceita novos comentarios.');
        header('Location: ' . SITE_URL . '/pages/local.php?id=' . $id . '#comentarios'); exit;
    }
    $texto = trim($_POST['comentario']);
    $ficheiro_com = null;

    if (isset($_FILES['foto_comentario']) && $_FILES['foto_comentario']['error'] === 0) {
        $fc   = $_FILES['foto_comentario'];
        $info = @getimagesize($fc['tmp_name']);
        $mime = $info ? ($info['mime'] ?? '') : '';
        $tipos_c = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if (isset($tipos_c[$mime]) && $fc['size'] <= 10 * 1024 * 1024) {
            $nome_fc = uniqid('com_') . '.' . $tipos_c[$mime];
            if (move_uploaded_file($fc['tmp_name'], UPLOAD_DIR . $nome_fc)) {
                $ficheiro_com = $nome_fc;
            }
        } else {
            $erro_com = 'Foto inválida. Usa JPG, PNG ou WebP (máx. 10MB).';
        }
    }

    if (!$erro_com) {
        if ($texto === '' && !$ficheiro_com) {
            $erro_com = 'Escreve um comentário ou anexa uma foto.';
        } elseif ($texto !== '' && strlen($texto) < 3) {
            $erro_com = 'O comentário é muito curto (mínimo 3 caracteres).';
        } else {
            add_comentario($id, $user['id'], $texto, $ficheiro_com);
            flash('success', 'Comentário publicado!');
            header('Location: ' . SITE_URL . '/pages/local.php?id=' . $id . '#comentarios'); exit;
        }
    }
}

// --- POST: Upload de foto ---
$erro_foto = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['fotos'])) {
    if (!$user) { header('Location: ' . SITE_URL . '/pages/login.php'); exit; }
    if ($local_bloqueado) {
        flash('error', 'Este post esta bloqueado e nao aceita novas imagens.');
        header('Location: ' . SITE_URL . '/pages/local.php?id=' . $id); exit;
    }
    if ((int)$user['id'] !== (int)$local['utilizador_id'] && !is_admin()) {
        flash('error', 'Só o criador do local pode adicionar fotos à galeria.');
        header('Location: ' . SITE_URL . '/pages/local.php?id=' . $id); exit;
    }
    $files = $_FILES['fotos'];
    $count = count($files['name']);
    $enviadas = 0;
    for ($i = 0; $i < $count; $i++) {
        $f = [
            'name'     => $files['name'][$i],
            'type'     => $files['type'][$i],
            'tmp_name' => $files['tmp_name'][$i],
            'error'    => $files['error'][$i],
            'size'     => $files['size'][$i],
        ];
        if ($f['error'] === 0 && upload_foto($f, $id, $user['id'])) $enviadas++;
    }
    if ($enviadas > 0) flash('success', 'Foto(s) adicionada(s)!');
    else flash('error', 'Nenhuma foto foi enviada. Seleciona pelo menos um ficheiro válido.');
    header('Location: ' . SITE_URL . '/pages/local.php?id=' . $id); exit;
}

// --- GET: Apagar foto pelo admin ou pelo dono ---
if (isset($_GET['apagar_foto']) && $user) {
    $fid = (int)$_GET['apagar_foto'];
    $st  = db()->prepare('SELECT ficheiro, utilizador_id FROM fotos WHERE id = ?');
    $st->execute([$fid]);
    $foto = $st->fetch();
    if ($foto && (is_admin() || (int)$foto['utilizador_id'] === (int)$user['id'])) {
        apagar_upload_local($foto['ficheiro']);
        db()->prepare('DELETE FROM fotos WHERE id = ?')->execute([$fid]);
        flash('success', 'Foto eliminada.');
    }
    header('Location: ' . SITE_URL . '/pages/local.php?id=' . $id . '#galeria'); exit;
}

// --- GET: Apagar comentário pelo admin ---
if (isset($_GET['apagar_comentario']) && is_admin()) {
    $cid = (int)$_GET['apagar_comentario'];
    $stC = db()->prepare('SELECT utilizador_id FROM comentarios WHERE id = ?');
    $stC->execute([$cid]);
    $rowC = $stC->fetch();
    if ($rowC) {
        $dono_id = (int)$local['utilizador_id'];
        if ($dono_id && $dono_id !== (int)$rowC['utilizador_id']) {
            add_pontos($dono_id, -PONTOS_COMENTARIO);
        }
    }
    db()->prepare('DELETE FROM comentarios WHERE id = ?')->execute([$cid]);
    flash('success', 'Comentário eliminado.');
    header('Location: ' . SITE_URL . '/pages/local.php?id=' . $id . '#comentarios'); exit;
}

// --- GET: Apagar foto de um comentário (autor ou admin) ---
if (isset($_GET['apagar_foto_comentario']) && $user) {
    $cid = (int)$_GET['apagar_foto_comentario'];
    $st  = db()->prepare('SELECT utilizador_id, ficheiro, texto FROM comentarios WHERE id = ?');
    $st->execute([$cid]);
    $com = $st->fetch();
    if ($com && (is_admin() || (int)$com['utilizador_id'] === (int)$user['id'])) {
        if ($com['ficheiro']) apagar_upload_local($com['ficheiro']);
        if (trim((string)$com['texto']) === '') {
            $dono_id = (int)$local['utilizador_id'];
            if ($dono_id && $dono_id !== (int)$com['utilizador_id']) {
                add_pontos($dono_id, -PONTOS_COMENTARIO);
            }
            db()->prepare('DELETE FROM comentarios WHERE id = ?')->execute([$cid]);
            flash('success', 'Comentário eliminado.');
        } else {
            db()->prepare('UPDATE comentarios SET ficheiro = NULL WHERE id = ?')->execute([$cid]);
            flash('success', 'Foto do comentário removida.');
        }
    }
    header('Location: ' . SITE_URL . '/pages/local.php?id=' . $id . '#comentarios'); exit;
}

// --- POST: Substituir foto de um comentário (autor ou admin) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['substituir_foto_comentario']) && $user) {
    $cid = (int)$_POST['comentario_id_foto'];
    $st  = db()->prepare('SELECT utilizador_id, ficheiro FROM comentarios WHERE id = ?');
    $st->execute([$cid]);
    $com = $st->fetch();
    if ($com && (is_admin() || (int)$com['utilizador_id'] === (int)$user['id'])) {
        if (isset($_FILES['nova_foto_comentario']) && $_FILES['nova_foto_comentario']['error'] === 0) {
            $fc   = $_FILES['nova_foto_comentario'];
            $info = @getimagesize($fc['tmp_name']);
            $mime = $info ? ($info['mime'] ?? '') : '';
            $tipos_c = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
            if (isset($tipos_c[$mime]) && $fc['size'] <= 10 * 1024 * 1024) {
                $nome_novo = uniqid('com_') . '.' . $tipos_c[$mime];
                if (move_uploaded_file($fc['tmp_name'], UPLOAD_DIR . $nome_novo)) {
                    if ($com['ficheiro']) apagar_upload_local($com['ficheiro']);
                    db()->prepare('UPDATE comentarios SET ficheiro = ? WHERE id = ?')->execute([$nome_novo, $cid]);
                    flash('success', 'Foto substituída com sucesso.');
                }
            } else {
                flash('error', 'Formato inválido ou ficheiro demasiado grande (máx. 10MB).');
            }
        }
    }
    header('Location: ' . SITE_URL . '/pages/local.php?id=' . $id . '#comentarios'); exit;
}

// --- POST: Denúncia ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['denunciar'])) {
    if (!$user) { header('Location: ' . SITE_URL . '/pages/login.php'); exit; }
    $ok = reportar(
        (string)($_POST['tipo'] ?? ''),
        (int)($_POST['ref_id'] ?? 0),
        $user['id'],
        (string)($_POST['motivo'] ?? '')
    );
    if ($ok) { flash('success', 'Denuncia registada. Obrigado!'); }
    else      { flash('error', 'Nao foi possivel registar a denuncia (duplicada, invalida ou proibida).'); }
    header('Location: ' . SITE_URL . '/pages/local.php?id=' . $id); exit;
}

// --- POST: Publicar atualização de local ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_atualizacao'])) {
    if (!$user) { header('Location: ' . SITE_URL . '/pages/login.php'); exit; }
    if ($local_bloqueado) {
        flash('error', 'Este local está bloqueado e não aceita novas atualizações.');
        header('Location: ' . SITE_URL . '/pages/local.php?id=' . $id); exit;
    }
    $texto = trim($_POST['atualizacao_texto'] ?? '');
    if (strlen($texto) < 3) {
        flash('error', 'A atualização é muito curta (mínimo 3 caracteres).');
    } elseif (strlen($texto) > 280) {
        flash('error', 'Máximo 280 caracteres.');
    } else {
        add_atualizacao_local($id, $user['id'], $texto);
        flash('success', 'Atualização publicada! Fica visível durante 7 dias.');
    }
    header('Location: ' . SITE_URL . '/pages/local.php?id=' . $id . '#atualizacoes'); exit;
}

// --- GET: Apagar atualização (autor ou admin) ---
if (isset($_GET['apagar_atualizacao']) && $user) {
    $aid = (int)$_GET['apagar_atualizacao'];
    $stA = db()->prepare('SELECT utilizador_id FROM atualizacoes_local WHERE id = ?');
    $stA->execute([$aid]);
    $rowA = $stA->fetch();
    if ($rowA && (is_admin() || (int)$rowA['utilizador_id'] === (int)$user['id'])) {
        db()->prepare('DELETE FROM atualizacoes_local WHERE id = ?')->execute([$aid]);
        flash('success', 'Atualização removida.');
    }
    header('Location: ' . SITE_URL . '/pages/local.php?id=' . $id . '#atualizacoes'); exit;
}

$page_title     = local_nome_publico($local);
$og_title       = local_nome_publico($local) . ' — Segredo Lusitano';
$og_description = trim(mb_substr(strip_tags(local_descricao_publica($local)), 0, 160)) ?: 'Descobre este local secreto em Portugal.';
$og_url         = SITE_URL . '/pages/local.php?id=' . $id;
$og_image       = $local['foto_capa']
    ? SITE_URL . '/uploads/locais/' . $local['foto_capa']
    : SITE_URL . '/assets/images/fundo_site.jpeg';
// --- Locais próximos (Haversine) ---
$locais_proximos = [];
if ($local['latitude'] && $local['longitude']) {
    $st_lp = db()->prepare(
        'SELECT l.id, l.nome, l.foto_capa, l.dificuldade, l.latitude, l.longitude,
                c.nome AS categoria_nome, c.icone AS categoria_icone,
                r.nome AS regiao_nome,
                (6371 * acos(
                    cos(radians(?)) * cos(radians(l.latitude)) *
                    cos(radians(l.longitude) - radians(?)) +
                    sin(radians(?)) * sin(radians(l.latitude))
                )) AS distancia
         FROM locais l
         JOIN categorias c ON c.id = l.categoria_id
         JOIN regioes r ON r.id = l.regiao_id
         WHERE l.id != ? AND l.estado = "aprovado" AND l.bloqueado = 0
           AND l.apagado_em IS NULL AND l.latitude IS NOT NULL AND l.longitude IS NOT NULL
         HAVING distancia < 50
         ORDER BY distancia ASC
         LIMIT 4'
    );
    $st_lp->execute([$local['latitude'], $local['longitude'], $local['latitude'], $id]);
    $locais_proximos = $st_lp->fetchAll();
}

$carregar_leaflet = true;
include dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-content">

<!-- HERO DO LOCAL -->
<div class="detalhe-hero">
  <?php if ($local['foto_capa']): ?>
    <img src="<?= SITE_URL ?>/uploads/locais/<?= h($local['foto_capa']) ?>" alt="<?= h(local_nome_publico($local)) ?>">
  <?php endif; ?>
  <div class="detalhe-hero-content">
    <div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-bottom:.75rem;">
      <span class="badge badge-cat"><i class="<?= h($local['categoria_icone']) ?>"></i> <?= h($local['categoria_nome']) ?></span>
      <?php
        $dif_class = ['facil'=>'badge-dif-facil','medio'=>'badge-dif-medio','dificil'=>'badge-dif-dificil'][$local['dificuldade']];
        $dif_label = ['facil'=>'Fácil','medio'=>'Médio','dificil'=>'Difícil'][$local['dificuldade']];
      ?>
      <span class="badge <?= $dif_class ?>"><?= $dif_label ?></span>
    </div>
    <h1><?= h(local_nome_publico($local)) ?></h1>
  </div>
</div>

<!-- BOTÃO VOLTAR -->
<div class="container" style="padding-top:1.5rem;padding-bottom:0;">
  <button onclick="history.back()"
     style="display:inline-flex;align-items:center;gap:.5rem;padding:.55rem .9rem;border:1.5px solid var(--creme-escuro);border-radius:var(--radius);background:var(--branco);color:var(--texto-muted);font-size:.88rem;cursor:pointer;transition:all var(--transition);"
     onmouseover="this.style.borderColor='var(--verde-claro)';this.style.color='var(--verde)'"
     onmouseout="this.style.borderColor='var(--creme-escuro)';this.style.color='var(--texto-muted)'">
    <i class="fas fa-arrow-left"></i> Voltar
  </button>
</div>

<!-- CONTEUDO -->
<section class="section" style="padding-top:1.5rem;">
  <div class="container">
    <div class="detalhe-grid">

      <!-- COLUNA PRINCIPAL -->
      <div class="detalhe-main-top">
        <!-- Ações -->
        <div class="detalhe-acoes">
          <button class="like-btn <?= $liked ? 'liked' : '' ?>" id="like-btn" data-local="<?= $id ?>">
            <i class="fas fa-heart"></i>
            <span id="like-count"><?= $local['total_likes'] ?></span>
          </button>
          <?php if ($user): ?>
            <a href="<?= SITE_URL ?>/pages/mapa.php?abrir=<?= $id ?>" class="btn btn-sm btn-verde">
              <i class="fas fa-map"></i> Ver no Mapa
            </a>
          <?php else: ?>
            <a href="#" onclick="mostrarAvisoLogin('Precisas de iniciar sessão para ver no mapa.', '<?= SITE_URL ?>/pages/login.php'); return false;" class="btn btn-sm btn-verde">
              <i class="fas fa-map"></i> Ver no Mapa
            </a>
          <?php endif; ?>
          <?php if ($user): ?>
            <button id="btn-guardar" onclick="toggleGuardar()"
                    class="btn btn-sm btn-outline"
                    style="color:<?= $guardado ? 'var(--dourado)' : 'var(--texto-muted)' ?>;border-color:<?= $guardado ? 'var(--dourado)' : 'var(--creme-escuro)' ?>;"
                    title="<?= $guardado ? 'Remover dos guardados' : 'Guardar local' ?>">
              <i class="<?= $guardado ? 'fas' : 'far' ?> fa-bookmark"></i>
              <span id="btn-guardar-texto"><?= $guardado ? 'Guardado' : 'Guardar' ?></span>
            </button>
            <!-- Dropdown Partilhar -->
            <div class="detalhe-acoes-partilhar" style="position:relative;">
              <button onclick="toggleDropPartilhar(event)" class="btn btn-sm btn-outline" style="color:var(--verde);border-color:var(--verde);width:100%;justify-content:center;">
                <i class="fas fa-share-alt"></i> Partilhar <i class="fas fa-chevron-down" style="font-size:.65rem;margin-left:.2rem;"></i>
              </button>
              <div id="drop-partilhar" style="display:none;position:absolute;top:calc(100% + 6px);left:0;background:#fff;border:1.5px solid var(--creme-escuro);border-radius:var(--radius);box-shadow:0 6px 20px rgba(0,0,0,.12);min-width:200px;z-index:500;overflow:hidden;">
                <button onclick="abrirModalRecomendar();fecharDropPartilhar()" style="width:100%;text-align:left;padding:.65rem 1rem;background:none;border:none;border-bottom:1px solid var(--creme-escuro);cursor:pointer;font-size:.88rem;color:var(--texto);display:flex;align-items:center;gap:.6rem;">
                  <i class="fas fa-paper-plane" style="color:var(--verde);width:14px;"></i> Enviar a um seguidor
                </button>
                <button onclick="partilharLocal();fecharDropPartilhar()" style="width:100%;text-align:left;padding:.65rem 1rem;background:none;border:none;cursor:pointer;font-size:.88rem;color:var(--texto);display:flex;align-items:center;gap:.6rem;">
                  <i class="fas fa-arrow-up-from-bracket" style="color:var(--verde);width:14px;"></i> Partilhar externamente
                </button>
              </div>
            </div>
          <?php else: ?>
            <button onclick="mostrarAvisoLogin('Precisas de iniciar sessão para guardar este local.', '<?= SITE_URL ?>/pages/login.php')" class="btn btn-sm btn-outline" style="color:var(--texto-muted);border-color:var(--creme-escuro);">
              <i class="far fa-bookmark"></i> Guardar
            </button>
            <button onclick="mostrarAvisoLogin('Precisas de iniciar sessão para partilhar este local.', '<?= SITE_URL ?>/pages/login.php')" class="btn btn-sm btn-outline" style="color:var(--verde);border-color:var(--verde);">
              <i class="fas fa-share-alt"></i> Partilhar
            </button>
          <?php endif; ?>


          <?php if ($user && ($user['id'] == $local['utilizador_id'] || is_admin())): ?>
            <a href="<?= SITE_URL ?>/pages/local_editar.php?id=<?= $id ?>" class="btn btn-sm btn-outline" style="color:var(--texto-muted);border-color:var(--creme-escuro);">
              <i class="fas fa-edit"></i> Editar
            </a>
          <?php endif; ?>
          <?php if (!$user || ($user['id'] != $local['utilizador_id'] && !is_admin())): ?>
            <button onclick="<?= $user
                ? "abrirModalDenuncia('local', {$id}, 'Local')"
                : "mostrarAvisoLogin('Precisas de iniciar sessão para denunciar este local.', '" . SITE_URL . "/pages/login.php')" ?>"
                    class="btn btn-sm" style="color:var(--texto-muted);border:1px solid var(--creme-escuro);border-radius:0;">
              <i class="fas fa-flag"></i> Denunciar
            </button>
          <?php endif; ?>
          <!-- QR Code -->
          <?php if ($user): ?>
          <button onclick="abrirModalQR()" class="btn btn-sm btn-outline" style="color:var(--texto-muted);border-color:var(--creme-escuro);" title="Gerar QR Code">
            <i class="fas fa-qrcode"></i> QR
          </button>
          <?php else: ?>
          <button onclick="mostrarAvisoLogin('Precisas de iniciar sessão para gerar o QR Code.', '<?= SITE_URL ?>/pages/login.php')" class="btn btn-sm btn-outline" style="color:var(--texto-muted);border-color:var(--creme-escuro);" title="Gerar QR Code">
            <i class="fas fa-qrcode"></i> QR
          </button>
          <?php endif; ?>
        </div>


        <!-- Descrição -->
        <div style="margin-bottom:1.5rem;border:1.5px solid var(--creme-escuro);border-radius:var(--radius);padding:1.25rem;background:var(--branco);">
          <h3 style="font-size:.95rem;margin-bottom:.75rem;color:var(--verde-escuro);"><i class="fas fa-align-left"></i> Descrição</h3>
          <p style="line-height:1.8;color:var(--texto);margin:0;text-align:justify;overflow-wrap:break-word;word-break:normal;"><?= nl2br(h(local_descricao_publica($local))) ?></p>
        </div>

        <!-- Upload compacto + Galeria -->
        <?php if ($fotos || is_admin() || ($user && !$local_bloqueado)): ?>
        <div style="margin-bottom:1.5rem;" id="galeria">

          <!-- Cabeçalho da galeria (fora do flex para alinhar com o topo da caixa) -->
          <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.75rem;">
            <h3 style="margin:0;font-size:1rem;"><i class="fas fa-images"></i> Galeria</h3>
            <?php if ($tem_fotos_alheias): ?>
              <button id="btn-denunciar-foto" onclick="toggleModoDenuncia()"
                      class="btn btn-sm"
                      style="color:var(--texto-muted);border:1px solid var(--creme-escuro);border-radius:0;font-size:.8rem;transition:all .2s;">
                <i class="fas fa-flag"></i> Denunciar foto
              </button>
            <?php endif; ?>
          </div>

          <div class="galeria-wrap">

            <?php if ($user && !$local_bloqueado && ((int)$user['id'] === (int)$local['utilizador_id'] || is_admin())): ?>
            <!-- Upload compacto quadrado -->
            <div class="galeria-upload">
              <form method="POST" enctype="multipart/form-data"
                    onsubmit="if(!document.getElementById('fotos').files.length){alert('Seleciona pelo menos uma foto antes de enviar.');return false;}">
                <div class="upload-area galeria-upload-area" data-input-id="fotos" data-compact="1"
                     style="display:flex;flex-direction:column;align-items:center;justify-content:center;gap:.35rem;padding:.5rem;border-radius:var(--radius);position:relative;overflow:hidden;">
                  <i class="fas fa-plus upload-icon" style="font-size:1.4rem;color:var(--verde-claro);"></i>
                  <p class="upload-label" style="font-size:.75rem;font-weight:500;margin:0;text-align:center;line-height:1.3;">Adicionar fotos</p>
                  <small style="color:var(--texto-muted);font-size:.68rem;text-align:center;">JPG · PNG · WebP<br>máx. 5MB</small>
                </div>
                <input type="file" id="fotos" name="fotos[]" multiple accept="image/*" style="display:none;">
                <button type="submit" class="btn btn-sm btn-verde" style="margin-top:.5rem;width:100%;font-size:.78rem;">
                  <i class="fas fa-upload"></i> Enviar
                </button>
              </form>
              <?php if (!is_admin()): ?>
              <button id="btn-eliminar-foto" onclick="toggleModoEliminar()"
                      class="btn btn-sm"
                      style="margin-top:.4rem;width:100%;font-size:.78rem;color:#c0392b;border:1px solid #c0392b;">
                <i class="fas fa-trash"></i> Eliminar fotos
              </button>
              <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Galeria -->
            <div class="galeria-fotos-wrap">
              <?php if (!$fotos): ?>
              <div style="border:1.5px solid #6b7280;border-radius:var(--radius);background:var(--creme);
                          min-height:160px;height:100%;
                          display:flex;align-items:center;justify-content:center;">
                <div style="text-align:center;color:var(--texto-muted);">
                  <i class="fas fa-images" style="font-size:2rem;margin-bottom:.5rem;display:block;opacity:.35;"></i>
                  <span style="font-size:.85rem;">Galeria vazia</span>
                </div>
              </div>
              <?php endif; ?>
              <div class="galeria" id="galeria-fotos">
                <?php $foto_idx = 0; foreach ($fotos as $foto): ?>
                  <?php
                    $foto_propria = ($user && (int)$foto['utilizador_id'] === (int)$user['id']);
                    $bloqueada    = !$user && $foto_idx >= 4;
                  ?>
                  <div class="galeria-item" style="position:relative;" data-foto-id="<?= $foto['id'] ?>">
                    <img src="<?= SITE_URL ?>/uploads/locais/<?= h($foto['ficheiro']) ?>"
                         alt="Foto do local" loading="lazy"
                         onclick="<?= $bloqueada
                           ? 'mostrarAvisoLogin(\'Inicia sessão para ver todas as fotos.\', \'' . SITE_URL . '/pages/login.php\')'
                           : 'clicarFotoGaleria(this)' ?>"
                         style="cursor:pointer;width:100%;height:100%;object-fit:cover;<?= $bloqueada ? 'filter:blur(10px);' : '' ?>">
                    <?php if ($bloqueada): ?>
                      <div style="position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;
                                   cursor:pointer;background:rgba(0,0,0,.22);"
                           onclick="mostrarAvisoLogin('Inicia sessão para ver todas as fotos.', '<?= SITE_URL ?>/pages/login.php')">
                        <i class="fas fa-lock" style="color:#fff;font-size:1.8rem;filter:drop-shadow(0 2px 6px rgba(0,0,0,.7));"></i>
                      </div>
                    <?php endif; ?>
                    <?php if (!$bloqueada && !$foto_propria && $user && !is_admin()): ?>
                      <div class="foto-denuncia-overlay"
                           style="display:none;position:absolute;inset:0;background:rgba(192,57,43,.45);
                                  border:3px solid #c0392b;border-radius:var(--radius);
                                  align-items:center;justify-content:center;cursor:pointer;flex-direction:column;gap:.35rem;"
                           onclick="confirmarDenunciaFoto(<?= $foto['id'] ?>)">
                        <i class="fas fa-flag" style="color:#fff;font-size:1.6rem;"></i>
                        <span style="color:#fff;font-size:.78rem;font-weight:700;">Denunciar</span>
                      </div>
                    <?php endif; ?>
                    <?php if (!$bloqueada && $foto_propria && $user && !is_admin()): ?>
                      <span style="position:absolute;top:.35rem;left:.35rem;background:var(--verde);color:#fff;
                                   border-radius:0;padding:.15rem .45rem;font-size:.7rem;font-weight:700;z-index:5;">Minha</span>
                      <div class="foto-eliminar-overlay"
                           style="display:none;position:absolute;inset:0;background:rgba(192,57,43,.55);
                                  border:3px solid #c0392b;border-radius:var(--radius);
                                  align-items:center;justify-content:center;cursor:pointer;flex-direction:column;gap:.35rem;"
                           onclick="confirmarEliminarFoto(<?= $foto['id'] ?>)">
                        <i class="fas fa-trash" style="color:#fff;font-size:1.6rem;"></i>
                        <span style="color:#fff;font-size:.78rem;font-weight:700;">Eliminar</span>
                      </div>
                    <?php endif; ?>
                    <?php if (is_admin()): ?>
                      <a href="<?= SITE_URL ?>/pages/local.php?id=<?= $id ?>&apagar_foto=<?= $foto['id'] ?>"
                         onclick="return confirm('Eliminar esta foto?')"
                         style="position:absolute;top:.35rem;right:.35rem;background:#c0392b;color:#fff;
                                 border-radius:0;padding:.2rem .45rem;font-size:.75rem;text-decoration:none;z-index:10;">
                        <i class="fas fa-trash"></i>
                      </a>
                    <?php endif; ?>
                  </div>
                <?php $foto_idx++; endforeach; ?>
              </div>
              <div id="aviso-modo-denuncia" style="display:none;margin-top:.75rem;padding:.6rem 1rem;
                   background:rgba(192,57,43,.08);border:1px solid #c0392b;border-radius:0;
                   font-size:.85rem;color:#c0392b;text-align:center;">
                <i class="fas fa-hand-pointer"></i> Clica na foto que queres denunciar
              </div>
              <div id="aviso-modo-eliminar" style="display:none;margin-top:.75rem;padding:.6rem 1rem;
                   background:rgba(192,57,43,.08);border:1px solid #c0392b;border-radius:0;
                   font-size:.85rem;color:#c0392b;text-align:center;">
                <i class="fas fa-hand-pointer"></i> Clica numa das tuas fotos para a eliminar
              </div>
            </div>
          </div>
        </div>
        <?php endif; ?>

        <?php if ($locais_proximos): ?>
        <div style="margin-bottom:1.5rem;">
          <h3 style="font-size:1rem;margin-bottom:.75rem;"><i class="fas fa-location-dot"></i> Locais Perto Deste</h3>
          <div class="locais-prox-grid">
            <?php foreach ($locais_proximos as $lp):
              $dif_lp = ['facil'=>'badge-dif-facil','medio'=>'badge-dif-medio','dificil'=>'badge-dif-dificil'][$lp['dificuldade']] ?? 'badge-dif-medio';
              $dist_km = $lp['distancia'] < 1 ? number_format($lp['distancia'] * 1000).' m' : number_format($lp['distancia'], 1).' km';
            ?>
            <a href="<?= SITE_URL ?>/pages/local.php?id=<?= $lp['id'] ?>"
               style="text-decoration:none;border:1.5px solid var(--creme-escuro);border-radius:var(--radius);overflow:hidden;background:var(--branco);display:block;transition:border-color .15s;"
               onmouseover="this.style.borderColor='var(--verde)'" onmouseout="this.style.borderColor='var(--creme-escuro)'">
              <div style="height:100px;overflow:hidden;background:var(--creme);position:relative;">
                <?php if ($lp['foto_capa']): ?>
                  <img src="<?= SITE_URL ?>/uploads/locais/<?= h($lp['foto_capa']) ?>" alt="" style="width:100%;height:100%;object-fit:cover;">
                <?php else: ?>
                  <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;">
                    <i class="<?= h($lp['categoria_icone']) ?>" style="font-size:2rem;opacity:.25;color:var(--verde-escuro);"></i>
                  </div>
                <?php endif; ?>
                <span style="position:absolute;bottom:.35rem;right:.35rem;background:rgba(26,58,42,.85);color:#c9a84c;font-size:.7rem;font-weight:700;padding:.15rem .4rem;border-radius:0;">
                  <i class="fas fa-location-dot"></i> <?= $dist_km ?>
                </span>
              </div>
              <div style="padding:.55rem .7rem;">
                <div style="font-size:.72rem;color:var(--texto-muted);margin-bottom:.15rem;"><?= h($lp['regiao_nome']) ?></div>
                <div style="font-weight:600;font-size:.85rem;color:var(--texto);line-height:1.3;overflow:hidden;text-overflow:ellipsis;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;"><?= h($lp['nome']) ?></div>
                <div style="margin-top:.3rem;">
                  <span class="badge badge-cat" style="font-size:.66rem;padding:.1rem .38rem;"><i class="<?= h($lp['categoria_icone']) ?>"></i> <?= h($lp['categoria_nome']) ?></span>
                </div>
              </div>
            </a>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>

      </div><!-- /detalhe-main-top -->

      <div class="detalhe-mapa">
        <!-- Mini Mapa -->
        <div style="margin-bottom:1.5rem;border-radius:var(--radius-lg);overflow:hidden;position:relative;">
          <p style="font-size:.75rem;color:var(--texto-muted);margin-bottom:.4rem;display:flex;align-items:center;gap:.35rem;">
            <i class="fas fa-info-circle" style="color:var(--verde);"></i>
            Clica no <strong>PINO</strong> para obter direções via Maps
          </p>
          <div style="position:relative;">
            <div id="mini-map-detalhe" style="height:320px;border-radius:var(--radius);"></div>
            <button onclick="<?= $user ? 'abrirMapaFullscreen()' : 'mostrarAvisoLogin(\'Precisas de iniciar sessão para expandir o mapa.\', \'' . SITE_URL . '/pages/login.php\')' ?>"
                    style="position:absolute;top:.6rem;right:.6rem;z-index:999;background:var(--verde-escuro);color:#fff;border:none;
                           border-radius:var(--radius);padding:.4rem .65rem;cursor:pointer;font-size:.8rem;display:flex;align-items:center;gap:.35rem;">
              <i class="fas fa-expand"></i> Expandir
            </button>
            <div id="mapa-estado" style="position:absolute;bottom:.6rem;left:.6rem;z-index:999;
                 background:rgba(26,58,42,.85);color:#c9a84c;font-size:.75rem;padding:.3rem .65rem;border-radius:0;display:none;">
              <i class="fas fa-spinner fa-spin"></i> A obter localização...
            </div>
          </div>
        </div>
      </div><!-- /detalhe-mapa -->

      <div class="detalhe-comentarios">
        <!-- Comentários -->
        <div id="comentarios" style="padding-top:1.5rem;border-top:1px solid var(--creme-escuro);margin-top:1rem;">
          <h3 style="display:flex;align-items:center;gap:.5rem;margin-bottom:1.25rem;"><i class="fas fa-comment" style="font-size:1rem;color:var(--texto-muted);"></i> Comentários <span style="color:var(--texto-muted);font-size:.9rem;font-weight:400;">(<?= count($comentarios) ?>)</span></h3>

          <?php if ($local_bloqueado): ?>
            <p style="margin-bottom:1.5rem;color:var(--texto-muted);font-size:.9rem;">Este post esta bloqueado. Novos comentarios estao desativados.</p>
          <?php elseif ($user): ?>
            <form method="POST" enctype="multipart/form-data" style="margin-bottom:1.5rem;">
              <!-- Preview da foto selecionada -->
              <div id="com-foto-preview" style="display:none;margin-bottom:.5rem;position:relative;width:fit-content;">
                <img id="com-foto-preview-img" src="" alt="" style="max-height:120px;max-width:100%;border-radius:var(--radius);border:1.5px solid var(--creme-escuro);">
                <button type="button" onclick="removerFotoComentario()"
                        style="position:absolute;top:-.4rem;right:-.4rem;background:#c0392b;color:#fff;border:none;border-radius:50%;width:1.3rem;height:1.3rem;font-size:.7rem;cursor:pointer;display:flex;align-items:center;justify-content:center;line-height:1;">
                  <i class="fas fa-times"></i>
                </button>
              </div>
              <div style="display:flex;gap:.5rem;align-items:flex-start;">
                <!-- Botão câmara -->
                <label for="foto_comentario" title="Anexar foto"
                       style="flex-shrink:0;width:2.3rem;height:2.3rem;display:flex;align-items:center;justify-content:center;border:1.5px solid var(--creme-escuro);border-radius:var(--radius);background:var(--branco);cursor:pointer;color:var(--texto-muted);transition:all .15s;margin-top:.15rem;"
                       onmouseover="this.style.borderColor='var(--verde)';this.style.color='var(--verde)'"
                       onmouseout="this.style.borderColor='var(--creme-escuro)';this.style.color='var(--texto-muted)'">
                  <i class="fas fa-camera" style="font-size:.9rem;"></i>
                </label>
                <input type="file" id="foto_comentario" name="foto_comentario" accept="image/*" style="display:none;"
                       onchange="previewFotoComentario(this)">
                <input type="text" id="comentario-local" name="comentario" placeholder="Deixa um comentário..."
                       value="<?= h($_POST['comentario'] ?? '') ?>"
                       style="flex:1;padding:.65rem 1rem;border:1.5px solid var(--creme-escuro);border-radius:var(--radius);background:var(--branco);font-size:.93rem;color:var(--texto);">
                <button type="submit" class="btn btn-primary btn-sm" style="white-space:nowrap;border-radius:var(--radius);">
                  <i class="fas fa-paper-plane"></i> Publicar
                </button>
              </div>
              <?php if ($erro_com): ?><div class="form-error" style="margin-top:.4rem;"><?= h($erro_com) ?></div><?php endif ?>
            </form>
            <script>
            function previewFotoComentario(input) {
              const preview = document.getElementById('com-foto-preview');
              const img     = document.getElementById('com-foto-preview-img');
              if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = e => { img.src = e.target.result; preview.style.display = 'block'; };
                reader.readAsDataURL(input.files[0]);
              }
            }
            function removerFotoComentario() {
              document.getElementById('foto_comentario').value = '';
              document.getElementById('com-foto-preview').style.display = 'none';
              document.getElementById('com-foto-preview-img').src = '';
            }
            </script>
          <?php else: ?>
            <p style="margin-bottom:1.5rem;">
              <a href="<?= SITE_URL ?>/pages/login.php" class="form-link">Inicia sessão</a>
            </p>
          <?php endif; ?>

          <?php if ($comentarios): ?>
            <div id="lista-comentarios">
              <?php foreach ($comentarios as $com): ?>
                <?php $comentario_bloqueado = ((int)$com['denunciado'] === 1); ?>
                <?php if ($comentario_bloqueado && !is_admin()) continue; ?>
                <div class="comentario">
                  <div class="comentario-avatar">
                    <?php if (!$comentario_bloqueado && !empty($com['avatar'])): ?>
                      <img src="<?= SITE_URL ?>/uploads/locais/<?= h($com['avatar']) ?>" alt="<?= h($com['autor_nome']) ?>">
                    <?php else: ?>
                      <?= $comentario_bloqueado ? '!' : mb_strtoupper(mb_substr($com['username'],0,1)) ?>
                    <?php endif; ?>
                  </div>
                  <div class="comentario-body">
                    <div class="comentario-meta" style="display:flex;align-items:center;gap:.45rem;flex-wrap:wrap;">
                      <strong><?= h(comentario_autor_publico($com)) ?></strong>
                      &bull; <?= date('d M Y', strtotime($com['criado_em'])) ?>
                      <?php if (is_admin()): ?>
                        <a href="?id=<?= $id ?>&apagar_comentario=<?= $com['id'] ?>"
                           onclick="return confirm('Eliminar este comentário permanentemente?')"
                           style="margin-left:auto;color:#c0392b;font-size:.8rem;text-decoration:none;">
                          <i class="fas fa-trash"></i>
                        </a>
                      <?php endif; ?>
                      <?php if ($user && !$comentario_bloqueado && $user['id'] !== (int)$com['utilizador_id'] && !is_admin()): ?>
                        <button type="button"
                                onclick="abrirModalDenuncia('comentario', <?= (int)$com['id'] ?>, 'Comentario')"
                                class="btn btn-sm"
                                style="margin-left:.35rem;padding:.2rem .55rem;border:1px solid var(--creme-escuro);color:var(--texto-muted);border-radius:0;">
                          <i class="fas fa-flag"></i> Denunciar
                        </button>
                      <?php endif; ?>
                    </div>
                    <?php if (!$comentario_bloqueado && !empty($com['texto'])): ?>
                    <div class="comentario-texto"><?= nl2br(h(comentario_texto_publico($com))) ?></div>
                    <?php endif; ?>
                    <?php if (!$comentario_bloqueado && !empty($com['ficheiro'])): ?>
                    <div style="margin-top:.5rem;display:inline-block;position:relative;">
                      <img src="<?= SITE_URL ?>/uploads/locais/<?= h($com['ficheiro']) ?>"
                           alt="Foto do comentário"
                           onclick="abrirFoto('<?= SITE_URL ?>/uploads/locais/<?= h($com['ficheiro']) ?>')"
                           style="max-width:280px;max-height:220px;object-fit:cover;border-radius:var(--radius);cursor:zoom-in;border:1.5px solid var(--creme-escuro);display:block;">
                      <?php if ($user && ((int)$user['id'] === (int)$com['utilizador_id'] || is_admin())): ?>
                      <div style="display:flex;gap:.35rem;margin-top:.35rem;">
                        <!-- Substituir foto -->
                        <label title="Substituir foto" style="cursor:pointer;">
                          <form method="POST" enctype="multipart/form-data" id="form-subst-<?= (int)$com['id'] ?>">
                            <input type="hidden" name="substituir_foto_comentario" value="1">
                            <input type="hidden" name="comentario_id_foto" value="<?= (int)$com['id'] ?>">
                            <input type="file" name="nova_foto_comentario" accept="image/*" style="display:none;"
                                   onchange="document.getElementById('form-subst-<?= (int)$com['id'] ?>').submit()">
                          </form>
                          <span style="display:inline-flex;align-items:center;gap:.3rem;padding:.25rem .6rem;border:1px solid var(--creme-escuro);border-radius:var(--radius);background:var(--branco);font-size:.75rem;color:var(--texto-muted);">
                            <i class="fas fa-camera"></i> Substituir
                          </span>
                        </label>
                        <!-- Apagar foto -->
                        <a href="<?= SITE_URL ?>/pages/local.php?id=<?= $id ?>&apagar_foto_comentario=<?= (int)$com['id'] ?>"
                           onclick="return confirm('Remover a foto deste comentário?')"
                           style="display:inline-flex;align-items:center;gap:.3rem;padding:.25rem .6rem;border:1px solid #c0392b;border-radius:var(--radius);background:var(--branco);font-size:.75rem;color:#c0392b;text-decoration:none;">
                          <i class="fas fa-trash"></i> Apagar foto
                        </a>
                      </div>
                      <?php endif; ?>
                    </div>
                    <?php endif; ?>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <p style="color:var(--texto-muted);font-size:.9rem;">Ainda sem comentários. Sê o primeiro!</p>
          <?php endif; ?>
        </div>
      </div>

      <!-- SIDEBAR -->
      <div class="detalhe-sidebar">

        <!-- Informações + Explorador + GPS (card único) -->
        <div style="border:1.5px solid var(--creme-escuro);border-radius:var(--radius);padding:1rem;background:var(--branco);">
          <h3 style="font-size:.95rem;margin-bottom:.75rem;">Informações</h3>
          <div class="info-row"><span class="label"><i class="fas fa-map-marker-alt"></i> Região</span><span class="val"><?= h($local['regiao_nome']) ?></span></div>
          <div class="info-row"><span class="label"><i class="fas fa-tag"></i> Categoria</span><span class="val"><?= h($local['categoria_nome']) ?></span></div>
          <div class="info-row"><span class="label"><i class="fas fa-hiking"></i> Dificuldade</span><span class="val"><?= $dif_label ?></span></div>
          <div class="info-row"><span class="label"><i class="fas fa-eye"></i> Visualizações</span><span class="val"><?= number_format($local['vistas']) ?></span></div>
          <div class="info-row" style="border-bottom:none;"><span class="label"><i class="fas fa-calendar"></i> Publicado a</span><span class="val"><?= date('d/m/Y', strtotime($local['criado_em'])) ?></span></div>

          <!-- Explorador -->
          <div style="margin-top:.85rem;padding-top:.85rem;border-top:1px solid var(--creme-escuro);display:flex;align-items:center;gap:.75rem;">
            <div class="rank-avatar">
              <?php if (!empty($local['avatar'])): ?>
                <img src="<?= SITE_URL ?>/uploads/locais/<?= h($local['avatar']) ?>" alt="<?= h($local['autor_nome']) ?>">
              <?php else: ?>
                <?= mb_strtoupper(mb_substr($local['username'],0,1)) ?>
              <?php endif; ?>
            </div>
            <div class="rank-user-info">
              <div style="font-weight:700;font-size:.9rem;"><?= h($local['autor_nome']) ?></div>
              <a href="<?= SITE_URL ?>/pages/perfil.php?id=<?= $local['utilizador_id'] ?>" style="color:var(--verde);font-size:.82rem;"><?= h($local['username']) ?></a>
            </div>
          </div>

          <!-- Coordenadas GPS -->
          <div style="margin-top:.85rem;padding-top:.85rem;border-top:1px solid var(--creme-escuro);">
            <div style="font-size:.75rem;font-weight:600;text-transform:uppercase;letter-spacing:.06em;color:var(--texto-muted);margin-bottom:.5rem;">
              <i class="fas fa-crosshairs" style="margin-right:.3rem;color:var(--verde);"></i>Coordenadas GPS
            </div>
            <?php if ($user): ?>
              <a href="https://www.google.com/maps/dir/?api=1&destination=<?= $local['latitude'] ?>,<?= $local['longitude'] ?>"
                 target="_blank" rel="noopener" class="btn btn-sm btn-verde" style="width:100%;">
                <i class="fas fa-external-link-alt"></i> Abrir no Google Maps
              </a>
            <?php else: ?>
              <a href="<?= SITE_URL ?>/pages/login.php" class="btn btn-sm btn-verde" style="width:100%;">
                <i class="fas fa-sign-in-alt"></i> Inicia sessão para navegar
              </a>
            <?php endif; ?>
          </div>
        </div>

      </div>
    </div>
  </div>
</section>

<?php if ($user): ?>
<!-- MODAL RECOMENDAR LOCAL -->
<div id="modal-recomendar" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:4000;align-items:center;justify-content:center;padding:1rem;">
  <div style="background:#fff;border-radius:var(--radius-lg);padding:2rem;max-width:420px;width:100%;box-shadow:0 8px 32px rgba(0,0,0,.18);max-height:90vh;overflow-y:auto;">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.25rem;">
      <div style="display:flex;align-items:center;gap:.6rem;">
        <i class="fas fa-share-alt" style="color:var(--verde);font-size:1.1rem;"></i>
        <h3 style="margin:0;font-size:1.1rem;">Recomendar Local</h3>
      </div>
      <button onclick="fecharModalRecomendar()" style="background:none;border:none;font-size:1.2rem;cursor:pointer;color:var(--texto-muted);line-height:1;">
        <i class="fas fa-times"></i>
      </button>
    </div>

    <!-- Mini-card do local -->
    <div style="display:flex;align-items:center;gap:.85rem;padding:.85rem;border:1.5px solid var(--verde);border-radius:var(--radius);background:var(--creme);margin-bottom:1.25rem;">
      <?php if ($local['foto_capa']): ?>
        <img src="<?= SITE_URL ?>/uploads/locais/<?= h($local['foto_capa']) ?>"
             style="width:56px;height:56px;object-fit:cover;border-radius:var(--radius);flex-shrink:0;">
      <?php else: ?>
        <div style="width:56px;height:56px;border-radius:var(--radius);background:var(--verde-claro);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
          <i class="fas fa-map-marker-alt" style="color:#fff;font-size:1.5rem;"></i>
        </div>
      <?php endif; ?>
      <div style="min-width:0;">
        <div style="font-weight:700;font-size:.95rem;color:var(--verde-escuro);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= h(local_nome_publico($local)) ?></div>
        <div style="font-size:.8rem;color:var(--texto-muted);"><?= h($local['regiao_nome']) ?> &bull; <?= h($local['categoria_nome']) ?></div>
      </div>
    </div>

    <!-- Mensagem opcional -->
    <div style="margin-bottom:1rem;">
      <label style="font-size:.85rem;font-weight:600;color:var(--texto-muted);display:block;margin-bottom:.4rem;">Mensagem (opcional)</label>
      <textarea id="recomendar-texto" placeholder="Deixa uma nota sobre este local..." rows="2"
                style="width:100%;border:1.5px solid var(--creme-escuro);border-radius:var(--radius);padding:.65rem 1rem;
                       font-size:.9rem;font-family:inherit;background:var(--creme);resize:none;outline:none;box-sizing:border-box;"></textarea>
    </div>

    <!-- Pesquisa + Lista de utilizadores -->
    <div style="margin-bottom:1.25rem;">
      <div style="position:relative;margin-bottom:.65rem;">
        <i class="fas fa-search" style="position:absolute;left:.85rem;top:50%;transform:translateY(-50%);color:var(--texto-muted);font-size:.85rem;pointer-events:none;"></i>
        <input type="text" id="recomendar-search" placeholder="Pesquisar utilizador..."
               style="width:100%;padding:.65rem 1rem .65rem 2.4rem;border:1.5px solid var(--creme-escuro);border-radius:var(--radius);
                      font-size:.9rem;font-family:inherit;background:var(--creme);outline:none;box-sizing:border-box;"
               oninput="filtrarUtilizadores(this.value)"
               onfocus="this.style.borderColor='var(--verde)'"
               onblur="this.style.borderColor='var(--creme-escuro)'">
      </div>
      <div id="recomendar-lista" style="display:flex;flex-direction:column;gap:.5rem;max-height:240px;overflow-y:auto;">
        <div style="text-align:center;padding:1.5rem;color:var(--texto-muted);font-size:.9rem;">
          <i class="fas fa-spinner fa-spin"></i> A carregar...
        </div>
      </div>
    </div>

    <div id="recomendar-feedback" style="display:none;padding:.65rem 1rem;border-radius:var(--radius);font-size:.88rem;margin-bottom:1rem;"></div>

    <button onclick="fecharModalRecomendar()" style="width:100%;padding:.75rem;border:1.5px solid var(--creme-escuro);border-radius:var(--radius);background:#fff;font-size:.95rem;cursor:pointer;color:var(--texto-muted);">
      Fechar
    </button>
  </div>
</div>

<script>
const LOCAL_ID_RECOMENDAR = <?= $id ?>;
const SITE_URL_RECOMENDAR = '<?= SITE_URL ?>';

let _followsMutuos = [];
let _followsCarregados = false;

function toggleDropPartilhar(e) {
  e.stopPropagation();
  const d = document.getElementById('drop-partilhar');
  if (!d) return;
  d.style.display = d.style.display === 'none' ? 'block' : 'none';
}
function fecharDropPartilhar() {
  const d = document.getElementById('drop-partilhar');
  if (d) d.style.display = 'none';
}
document.addEventListener('click', fecharDropPartilhar);

function partilharLocal() {
  if (!IS_LOGGED_IN) {
    mostrarAvisoLogin('Precisas de iniciar sessão para partilhar este local.', '<?= SITE_URL ?>/pages/login.php');
    return;
  }
  const url  = '<?= SITE_URL ?>/pages/local.php?id=<?= $id ?>';
  const nome = <?= json_encode(local_nome_publico($local)) ?>;
  if (navigator.share) {
    navigator.share({
      title: nome + ' — Segredo Lusitano',
      text:  'Descobre este local incrível em Portugal!',
      url:   url
    }).catch(() => {});
  } else {
    navigator.clipboard.writeText(url).then(() => {
      const btn = event.currentTarget;
      const orig = btn.innerHTML;
      btn.innerHTML = '<i class="fas fa-check"></i> Link copiado!';
      setTimeout(() => { btn.innerHTML = orig; }, 2000);
    }).catch(() => {
      abrirModalRecomendar();
    });
  }
}

function abrirModalRecomendar() {
  document.getElementById('modal-recomendar').style.display = 'flex';
  document.getElementById('recomendar-feedback').style.display = 'none';
  document.getElementById('recomendar-search').value = '';
  if (!_followsCarregados) carregarFollowsMutuos();
  else renderizarUtilizadores(_followsMutuos);
  setTimeout(() => document.getElementById('recomendar-search').focus(), 120);
}

function fecharModalRecomendar() {
  document.getElementById('modal-recomendar').style.display = 'none';
}

function filtrarUtilizadores(termo) {
  const t = termo.trim().toLowerCase();
  const filtrados = t
    ? _followsMutuos.filter(u => u.nome.toLowerCase().includes(t) || u.username.toLowerCase().includes(t))
    : _followsMutuos;
  renderizarUtilizadores(filtrados, t);
}

function renderizarUtilizadores(lista, termoPesquisa = '') {
  const el = document.getElementById('recomendar-lista');
  if (!lista.length) {
    el.innerHTML = `<div style="text-align:center;padding:1.5rem;color:var(--texto-muted);font-size:.9rem;">
      <i class="fas fa-user-slash" style="display:block;font-size:1.6rem;margin-bottom:.5rem;"></i>
      ${termoPesquisa ? 'Nenhum utilizador encontrado.' : 'Não tens ninguém que te siga de volta.'}
    </div>`;
    return;
  }
  el.innerHTML = '';
  lista.forEach(u => {
    const item = document.createElement('div');
    item.style.cssText = 'display:flex;align-items:center;gap:.85rem;padding:.75rem 1rem;border:1.5px solid var(--creme-escuro);border-radius:var(--radius);cursor:pointer;transition:all .15s;background:#fff;';
    item.onmouseover = () => { item.style.borderColor = 'var(--verde)'; item.style.background = 'var(--creme)'; };
    item.onmouseout  = () => { item.style.borderColor = 'var(--creme-escuro)'; item.style.background = '#fff'; };

    const avatarEl = u.avatar
      ? `<img src="${SITE_URL_RECOMENDAR}/uploads/locais/${u.avatar}" style="width:40px;height:40px;border-radius:50%;object-fit:cover;flex-shrink:0;">`
      : `<div style="width:40px;height:40px;border-radius:50%;background:var(--verde-claro);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;flex-shrink:0;">${u.nome.charAt(0).toUpperCase()}</div>`;

    const destacar = (texto) => {
      if (!termoPesquisa) return texto.replace(/</g,'&lt;').replace(/>/g,'&gt;');
      const safe = texto.replace(/</g,'&lt;').replace(/>/g,'&gt;');
      const t    = termoPesquisa.replace(/</g,'&lt;').replace(/>/g,'&gt;');
      return safe.replace(new RegExp(`(${t.replace(/[.*+?^${}()|[\]\\]/g,'\\$&')})`, 'gi'),
        '<mark style="background:rgba(45,106,79,.18);color:var(--verde-escuro);border-radius:2px;padding:0 1px;">$1</mark>');
    };

    item.innerHTML = `
      ${avatarEl}
      <div style="flex:1;min-width:0;">
        <div style="font-weight:600;font-size:.92rem;color:var(--texto);">${destacar(u.nome)}</div>
        <div style="font-size:.8rem;color:var(--texto-muted);">@${destacar(u.username)}</div>
      </div>
      <i class="fas fa-paper-plane" style="color:var(--verde);font-size:.9rem;flex-shrink:0;"></i>`;
    item.addEventListener('click', () => enviarRecomendacao(u.id, u.nome, item));
    el.appendChild(item);
  });
}

async function carregarFollowsMutuos() {
  const lista = document.getElementById('recomendar-lista');
  lista.innerHTML = '<div style="text-align:center;padding:1.5rem;color:var(--texto-muted);font-size:.9rem;"><i class="fas fa-spinner fa-spin"></i> A carregar...</div>';
  try {
    const res  = await fetch(SITE_URL_RECOMENDAR + '/pages/mensagens_api.php?acao=follows_mutuos');
    const data = await res.json();
    _followsMutuos    = data.ok ? data.utilizadores : [];
    _followsCarregados = true;
    renderizarUtilizadores(_followsMutuos);
  } catch(e) {
    lista.innerHTML = '<div style="text-align:center;padding:1rem;color:#c0392b;font-size:.9rem;">Erro ao carregar utilizadores.</div>';
  }
}

async function enviarRecomendacao(destId, destNome, itemEl) {
  const texto    = document.getElementById('recomendar-texto').value.trim();
  const feedback = document.getElementById('recomendar-feedback');
  const lista    = document.getElementById('recomendar-lista');

  // Desativar todos os items durante o envio
  lista.querySelectorAll('div[style]').forEach(el => el.style.pointerEvents = 'none');

  try {
    const res  = await fetch(SITE_URL_RECOMENDAR + '/pages/mensagens_api.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: `acao=recomendar&destinatario_id=${destId}&local_id=${LOCAL_ID_RECOMENDAR}&texto=${encodeURIComponent(texto)}`
    });
    const data = await res.json();
    if (data.ok) {
      feedback.style.display = 'block';
      feedback.style.background = 'rgba(45,106,79,.1)';
      feedback.style.border = '1px solid var(--verde)';
      feedback.style.color = 'var(--verde-escuro)';
      feedback.innerHTML = `<i class="fas fa-check-circle"></i> Local enviado para <strong>${destNome}</strong>!`;
      document.getElementById('recomendar-texto').value = '';
    } else {
      throw new Error(data.erro || 'Erro desconhecido');
    }
  } catch(e) {
    feedback.style.display = 'block';
    feedback.style.background = 'rgba(192,57,43,.08)';
    feedback.style.border = '1px solid #c0392b';
    feedback.style.color = '#c0392b';
    feedback.innerHTML = `<i class="fas fa-exclamation-circle"></i> ${e.message}`;
  } finally {
    lista.querySelectorAll('div[style]').forEach(el => el.style.pointerEvents = '');
  }
}

document.addEventListener('keydown', e => {
  if (e.key === 'Escape') fecharModalRecomendar();
});

// ── Guardar / bookmark ────────────────────────────────────
let _guardando = false;
async function toggleGuardar() {
  if (_guardando) return;
  _guardando = true;
  const btn     = document.getElementById('btn-guardar');
  const icone   = btn.querySelector('i');
  const texto   = document.getElementById('btn-guardar-texto');
  try {
    const res  = await fetch(SITE_URL_RECOMENDAR + '/pages/favoritos_api.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: `acao=toggle&local_id=${LOCAL_ID_RECOMENDAR}`
    });
    const data = await res.json();
    if (data.ok) {
      if (data.guardado) {
        icone.className = 'fas fa-bookmark';
        texto.textContent = 'Guardado';
        btn.style.color       = 'var(--dourado)';
        btn.style.borderColor = 'var(--dourado)';
        btn.title = 'Remover dos guardados';
      } else {
        icone.className = 'far fa-bookmark';
        texto.textContent = 'Guardar';
        btn.style.color       = 'var(--texto-muted)';
        btn.style.borderColor = 'var(--creme-escuro)';
        btn.title = 'Guardar local';
      }
    }
  } catch(e) { console.error(e); }
  finally { _guardando = false; }
}
</script>
<?php endif; ?>

<!-- MODAL MAPA FULLSCREEN -->
<div id="modal-mapa" style="display:none;position:fixed;inset:0;z-index:5000;background:#000;flex-direction:column;">
  <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.5rem;padding:.75rem 1rem;background:var(--verde-escuro);color:#fff;">
    <span style="font-family:'Playfair Display',serif;color:var(--dourado);font-weight:700;">
      <i class="fas fa-map"></i> <?= h(local_nome_publico($local)) ?>
    </span>
    <div style="display:flex;gap:.4rem;">
      <button class="btn-modo" data-modo="driving" onclick="mudarModo('driving')"
              style="background:var(--dourado);color:var(--verde-escuro);border:none;border-radius:0;padding:.35rem .75rem;cursor:pointer;font-size:.82rem;font-weight:700;display:flex;align-items:center;gap:.3rem;">
        <i class="fas fa-car"></i> Carro
      </button>
      <button class="btn-modo" data-modo="foot" onclick="mudarModo('foot')"
              style="background:rgba(255,255,255,.15);color:#fff;border:none;border-radius:0;padding:.35rem .75rem;cursor:pointer;font-size:.82rem;display:flex;align-items:center;gap:.3rem;">
        <i class="fas fa-walking"></i> A Pé
      </button>
      <button class="btn-modo" data-modo="bike" onclick="mudarModo('bike')"
              style="background:rgba(255,255,255,.15);color:#fff;border:none;border-radius:0;padding:.35rem .75rem;cursor:pointer;font-size:.82rem;display:flex;align-items:center;gap:.3rem;">
        <i class="fas fa-bicycle"></i> Bicicleta
      </button>
    </div>
    <button onclick="fecharMapaFullscreen()"
            style="background:rgba(255,255,255,.15);border:none;color:#fff;border-radius:0;padding:.4rem .85rem;cursor:pointer;font-size:.9rem;">
      <i class="fas fa-times"></i> Fechar
    </button>
  </div>
  <div id="rota-info" style="display:none;background:var(--verde-escuro);color:#fff;padding:.5rem 1rem;font-size:.85rem;border-top:1px solid rgba(201,168,76,.3);flex-shrink:0;">
    <i class="fas fa-route" style="color:var(--dourado);margin-right:.4rem;"></i>
    <span id="rota-info-texto"></span>
  </div>
  <div id="mapa-fullscreen" style="flex:1;"></div>
</div>

<!-- MODAL DENUNCIA -->
<div id="modal-denuncia" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:3000;align-items:center;justify-content:center;padding:1rem;">
  <div style="background:#fff;border-radius:var(--radius-lg);padding:2rem;max-width:460px;width:100%;box-shadow:0 8px 32px rgba(0,0,0,.18);">
    <div style="display:flex;align-items:center;gap:.6rem;margin-bottom:1.25rem;">
      <i class="fas fa-flag" style="color:#e74c3c;font-size:1.1rem;"></i>
      <h3 style="margin:0;font-size:1.1rem;"><span id="denuncia-titulo">Denunciar</span></h3>
    </div>
    <form method="POST">
      <input type="hidden" name="denunciar" value="1">
      <input type="hidden" name="tipo" id="denuncia-tipo" value="local">
      <input type="hidden" name="ref_id" id="denuncia-ref-id" value="<?= $id ?>">
      <div style="margin-bottom:1.25rem;">
        <p style="font-size:.85rem;color:var(--texto-muted);margin-bottom:.75rem;">Seleciona o motivo da denúncia:</p>
        <div style="display:flex;flex-direction:column;gap:.5rem;">
          <?php
            $motivo_icones = [
              'spam'             => 'fas fa-ban',
              'discurso_odio'    => 'fas fa-angry',
              'conteudo_sexual'  => 'fas fa-eye-slash',
              'informacao_falsa' => 'fas fa-times-circle',
            ];
          ?>
          <?php foreach ($motivos_denuncia as $valor => $rotulo): ?>
            <label style="display:flex;align-items:center;gap:.75rem;padding:.75rem 1rem;border:1.5px solid var(--creme-escuro);border-radius:var(--radius);cursor:pointer;transition:border-color .15s,background .15s;"
                   onmouseover="this.style.borderColor='var(--verde)';this.style.background='var(--creme)'"
                   onmouseout="this.style.borderColor='var(--creme-escuro)';this.style.background='#fff'"
                   onclick="this.style.borderColor='var(--verde)';this.style.background='var(--creme)'">
              <input type="radio" name="motivo" value="<?= h($valor) ?>" required style="accent-color:var(--verde);width:16px;height:16px;flex-shrink:0;">
              <i class="<?= $motivo_icones[$valor] ?? 'fas fa-flag' ?>" style="color:var(--verde);width:16px;text-align:center;flex-shrink:0;"></i>
              <span style="font-size:.92rem;font-weight:500;"><?= h($rotulo) ?></span>
            </label>
          <?php endforeach; ?>
        </div>
      </div>
      <div style="display:flex;gap:.75rem;">
        <button type="submit" class="btn btn-danger" style="flex:1;justify-content:center;"><i class="fas fa-paper-plane"></i> Enviar Denúncia</button>
        <button type="button" onclick="document.getElementById('modal-denuncia').style.display='none'" class="btn" style="border:1px solid var(--creme-escuro);color:var(--texto-muted);">Cancelar</button>
      </div>
    </form>
  </div>
</div>
</div>

<!-- Modal para ampliar foto da galeria -->
<div id="modal-foto" onclick="fecharFoto()"
     style="display:none;position:fixed;inset:0;z-index:6000;background:rgba(0,0,0,.92);align-items:center;justify-content:center;cursor:zoom-out;">
  <img id="modal-foto-img" src="" alt="" style="max-width:90vw;max-height:90vh;object-fit:contain;border-radius:var(--radius);">
</div>

<script>
// ── Denúncia de foto — modo de seleção ────────────────────
let modoDenunciaFoto = false;

function toggleModoDenuncia() {
  modoDenunciaFoto = !modoDenunciaFoto;
  const btn      = document.getElementById('btn-denunciar-foto');
  const overlays = document.querySelectorAll('.foto-denuncia-overlay');
  const aviso    = document.getElementById('aviso-modo-denuncia');
  if (modoDenunciaFoto) {
    btn.style.background  = '#c0392b';
    btn.style.color       = '#fff';
    btn.style.borderColor = '#c0392b';
    btn.innerHTML         = '<i class="fas fa-times"></i> Cancelar';
    overlays.forEach(o => o.style.display = 'flex');
    if (aviso) aviso.style.display = 'block';
  } else {
    btn.style.background  = '';
    btn.style.color       = 'var(--texto-muted)';
    btn.style.borderColor = 'var(--creme-escuro)';
    btn.innerHTML         = '<i class="fas fa-flag"></i> Denunciar foto';
    overlays.forEach(o => o.style.display = 'none');
    if (aviso) aviso.style.display = 'none';
  }
}

function confirmarDenunciaFoto(fotoId) {
  if (!confirm('Tens a certeza que queres denunciar esta fotografia?')) return;
  toggleModoDenuncia();
  abrirModalDenuncia('foto', fotoId, 'Fotografia');
}

// ── Eliminar foto própria — modo de seleção ───────────────
let modoEliminarFoto = false;

function toggleModoEliminar() {
  modoEliminarFoto = !modoEliminarFoto;
  const btn      = document.getElementById('btn-eliminar-foto');
  const overlays = document.querySelectorAll('.foto-eliminar-overlay');
  const aviso    = document.getElementById('aviso-modo-eliminar');
  if (modoEliminarFoto) {
    btn.style.background  = '#c0392b';
    btn.style.color       = '#fff';
    btn.style.borderColor = '#c0392b';
    btn.innerHTML         = '<i class="fas fa-times"></i> Cancelar';
    overlays.forEach(o => o.style.display = 'flex');
    if (aviso) aviso.style.display = 'block';
  } else {
    btn.style.background  = '';
    btn.style.color       = 'var(--texto-muted)';
    btn.style.borderColor = 'var(--creme-escuro)';
    btn.innerHTML         = '<i class="fas fa-trash"></i> Eliminar fotos';
    overlays.forEach(o => o.style.display = 'none');
    if (aviso) aviso.style.display = 'none';
  }
}

function confirmarEliminarFoto(fotoId) {
  if (!confirm('Tens a certeza que queres eliminar esta fotografia? Esta ação é irreversível.')) return;
  window.location.href = '<?= SITE_URL ?>/pages/local.php?id=<?= $id ?>&apagar_foto=' + fotoId + '#galeria';
}

function clicarFotoGaleria(img) {
  if (modoDenunciaFoto || modoEliminarFoto) return;
  abrirFoto(img.src);
}

function abrirModalDenuncia(tipo, refId, alvo) {
  document.getElementById('denuncia-titulo').textContent = 'Denunciar ' + alvo;
  document.getElementById('denuncia-tipo').value  = tipo;
  document.getElementById('denuncia-ref-id').value = refId;
  document.querySelectorAll('#modal-denuncia input[name="motivo"]').forEach(r => r.checked = false);
  document.getElementById('modal-denuncia').style.display = 'flex';
}

function abrirFoto(src) {
  document.getElementById('modal-foto-img').src = src;
  document.getElementById('modal-foto').style.display = 'flex';
}
function fecharFoto() {
  document.getElementById('modal-foto').style.display = 'none';
}
document.addEventListener('keydown', e => { if (e.key === 'Escape') { fecharFoto(); document.getElementById('modal-denuncia').style.display = 'none'; } });

document.addEventListener('DOMContentLoaded', () => {
  const destLat = <?= $local['latitude'] ?>;
  const destLng = <?= $local['longitude'] ?>;

  const map2 = L.map('mini-map-detalhe', { zoomControl:false, dragging:false, scrollWheelZoom:false })
    .setView([destLat, destLng], 15);
  L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', { attribution:'© CARTO', maxZoom:18 }).addTo(map2);

  const iconePersonalizado = L.divIcon({
    className: '',
    html: `<i class="fa-solid fa-location-dot" style="color:#2d6a4f;font-size:2rem;"></i>`,
    iconSize: [20,20], iconAnchor: [10,32]
  });

  L.marker([destLat, destLng], { icon: iconePersonalizado }).addTo(map2)
    .on('click', function() {
      const a = document.createElement('a');
      a.href = `https://maps.google.com/?q=${destLat},${destLng}`;
      a.target = '_blank';
      a.rel = 'noopener noreferrer';
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
    });

  if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition(pos => {
      L.circleMarker([pos.coords.latitude, pos.coords.longitude], { radius:7, fillColor:'#269b3f', fillOpacity:1, color:'#fff', weight:2 }).addTo(map2);
      map2.fitBounds(L.latLngBounds([pos.coords.latitude, pos.coords.longitude], [destLat, destLng]), { padding:[20,20] });
    }, () => {});
  }

  let mapFS = null, rotaLayer = null, userMarker = null, modoAtual = 'driving', userLat = null, userLng = null;
  const osrmModo  = { driving:'driving', foot:'foot', bike:'bike' };
  const modoCores = { driving:'#c9a84c', foot:'#2d6a4f', bike:'#3498db' };

  window.abrirMapaFullscreen = function() {
    document.getElementById('modal-mapa').style.display = 'flex';
    if (!mapFS) {
      mapFS = L.map('mapa-fullscreen').setView([destLat, destLng], 13);
      L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', { attribution:'© CARTO', maxZoom:18 }).addTo(mapFS);
      L.marker([destLat, destLng], { icon: iconePersonalizado }).addTo(mapFS)
        .bindPopup('<strong><?= h(local_nome_publico($local)) ?></strong><br><?= h($local['regiao_nome']) ?>').openPopup();
    }
    setTimeout(() => mapFS.invalidateSize(), 100);
    if (!userLat) pedirLocalizacao(); else tracarRota(userLat, userLng, modoAtual);
  };

  window.fecharMapaFullscreen = function() { document.getElementById('modal-mapa').style.display = 'none'; };

  window.mudarModo = function(modo) {
    modoAtual = modo;
    document.querySelectorAll('.btn-modo').forEach(btn => {
      const ativo = btn.dataset.modo === modo;
      btn.style.background = ativo ? 'var(--dourado)' : 'rgba(255,255,255,.15)';
      btn.style.color      = ativo ? 'var(--verde-escuro)' : '#fff';
      btn.style.fontWeight = ativo ? '700' : '400';
    });
    if (userLat) tracarRota(userLat, userLng, modo);
  };

  function pedirLocalizacao() {
    const estado = document.getElementById('mapa-estado');
    if (!navigator.geolocation) return;
    estado.style.display = 'block';
    navigator.geolocation.getCurrentPosition(pos => {
      estado.style.display = 'none';
      userLat = pos.coords.latitude; userLng = pos.coords.longitude;
      if (userMarker) userMarker.remove();
      userMarker = L.circleMarker([userLat, userLng], { radius:8, fillColor:'#2d6a4f', fillOpacity:1, color:'#fff', weight:2 }).addTo(mapFS).bindPopup('A tua localização');
      tracarRota(userLat, userLng, modoAtual);
    }, () => { estado.style.display = 'none'; });
  }

  function tracarRota(uLat, uLng, modo) {
    fetch(`https://router.project-osrm.org/route/v1/${osrmModo[modo]}/${uLng},${uLat};${destLng},${destLat}?overview=full&geometries=geojson`)
      .then(r => r.json())
      .then(data => {
        if (!data.routes || !data.routes[0]) return;
        if (rotaLayer) rotaLayer.remove();
        rotaLayer = L.geoJSON(data.routes[0].geometry, { style:{ color:modoCores[modo], weight:4, opacity:.9 } }).addTo(mapFS);
        mapFS.fitBounds(L.latLngBounds([[uLat,uLng],[destLat,destLng]]), { padding:[50,50] });
        const dist = (data.routes[0].distance/1000).toFixed(1);
        const velocidades = { driving:100, foot:4, bike:25 };
        const minsCalc = Math.round((data.routes[0].distance/1000)/velocidades[modo]*60);
        const tempo = minsCalc >= 60 ? Math.floor(minsCalc/60)+'h '+(minsCalc%60)+'min' : minsCalc+' min';
        const icones = { driving:'🚗', foot:'🚶', bike:'🚲' };
        document.getElementById('rota-info-texto').textContent = `${icones[modo]} ${dist} km · ⏱ ${tempo}`;
        document.getElementById('rota-info').style.display = 'block';
      })
      .catch(() => { mapFS.fitBounds([[uLat,uLng],[destLat,destLng]], { padding:[40,40] }); });
  }
});

// ── Check-in GPS ─────────────────────────────────────────
function haversineMetros(lat1, lng1, lat2, lng2) {
  const R = 6371000;
  const toRad = x => x * Math.PI / 180;
  const dLat = toRad(lat2 - lat1), dLng = toRad(lng2 - lng1);
  const a = Math.sin(dLat/2)**2 + Math.cos(toRad(lat1))*Math.cos(toRad(lat2))*Math.sin(dLng/2)**2;
  return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
}

<?php if ($user && !$ja_checkin && $local['latitude'] && $local['longitude']): ?>
// Verificar proximidade ao carregar a página (100m)
(function() {
  if (!navigator.geolocation) return;
  setTimeout(function() {
    navigator.geolocation.getCurrentPosition(function(pos) {
      const dist = haversineMetros(pos.coords.latitude, pos.coords.longitude,
                                   <?= (float)$local['latitude'] ?>, <?= (float)$local['longitude'] ?>);
      if (dist <= 100) {
        const notif = document.getElementById('notif-proximidade');
        if (notif) notif.style.display = 'flex';
      }
    }, null, { enableHighAccuracy: true, timeout: 10000 });
  }, 1500);
})();

window.confirmarCheckinProximo = function() {
  const notif = document.getElementById('notif-proximidade');
  notif.innerHTML = '<i class="fas fa-spinner fa-spin" style="color:var(--dourado);font-size:1.3rem;flex-shrink:0;"></i><span style="margin-left:.75rem;">A registar visita...</span>';
  fetch(`${SITE_URL}/pages/checkin.php`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: `local_id=<?= $id ?>`
  })
  .then(r => r.json())
  .then(data => {
    if (data.ok || data.ja_fez) {
      notif.innerHTML = '<i class="fas fa-check-circle" style="color:var(--dourado);font-size:1.3rem;flex-shrink:0;"></i><span style="margin-left:.75rem;font-weight:600;">Visita registada! Obrigado.</span>';
      setTimeout(() => { notif.style.transition='opacity .5s'; notif.style.opacity='0'; setTimeout(()=>notif.remove(),500); }, 2500);
    } else {
      notif.style.display = 'none';
      alert(data.erro || 'Erro ao registar.');
    }
  })
  .catch(() => { notif.style.display = 'none'; alert('Erro de ligação.'); });
};
<?php endif; ?>

// ── Ver mais / menos comentários ─────────────────────────
document.addEventListener('DOMContentLoaded', function() {
  const LIMITE = 7;
  const lista  = document.getElementById('lista-comentarios');
  if (!lista) return;
  const items = Array.from(lista.querySelectorAll(':scope > .comentario'));
  if (items.length <= LIMITE) return;

  const extra = document.createElement('div');
  extra.id = 'comentarios-extra';
  extra.style.cssText = 'display:none;max-height:480px;overflow-y:auto;padding-right:.5rem;scrollbar-width:thin;';
  items.slice(LIMITE).forEach(el => extra.appendChild(el));

  const btnMenos = document.createElement('button');
  btnMenos.className = 'btn btn-sm';
  btnMenos.style.cssText = 'margin-top:.75rem;color:var(--texto-muted);border:1px solid var(--creme-escuro);width:100%;justify-content:center;';
  btnMenos.innerHTML = '<i class="fas fa-chevron-up"></i> Mostrar menos comentários';
  btnMenos.onclick = toggleComentarios;
  extra.appendChild(btnMenos);
  lista.appendChild(extra);

  const btnMais = document.createElement('button');
  btnMais.id = 'btn-ver-mais-com';
  btnMais.className = 'btn btn-sm';
  btnMais.style.cssText = 'margin-top:.75rem;color:var(--texto-muted);border:1px solid var(--creme-escuro);width:100%;justify-content:center;';
  btnMais.innerHTML = '<i class="fas fa-chevron-down"></i> Ver mais comentários (' + (items.length - LIMITE) + ')';
  btnMais.onclick = toggleComentarios;
  lista.after(btnMais);

  function toggleComentarios() {
    const aberto = extra.style.display !== 'none';
    extra.style.display = aberto ? 'none' : 'block';
    btnMais.style.display = aberto ? '' : 'none';
  }
});
</script>

<!-- MODAL QR CODE -->
<div id="modal-qr" style="display:none;position:fixed;inset:0;z-index:6000;background:rgba(0,0,0,.55);align-items:center;justify-content:center;padding:1rem;">
  <div style="background:#fff;border-radius:var(--radius-lg);padding:2rem;max-width:360px;width:100%;text-align:center;box-shadow:0 8px 40px rgba(0,0,0,.25);">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.25rem;">
      <h3 style="margin:0;font-size:1rem;color:var(--verde-escuro);"><?= h(local_nome_publico($local)) ?></h3>
      <button onclick="fecharModalQR()" style="background:none;border:none;font-size:1.2rem;cursor:pointer;color:var(--texto-muted);"><i class="fas fa-times"></i></button>
    </div>
    <div style="position:relative;width:240px;height:240px;margin:0 auto 1.25rem;">
      <img id="qr-img" src="" alt="QR Code" style="width:100%;height:100%;display:block;">
      <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);background:#fff;padding:1px;border-radius:50%;line-height:0;">
        <img src="<?= SITE_URL ?>/assets/images/logo_icon_qr.png" alt="" style="width:44px;height:44px;object-fit:contain;display:block;border-radius:50%;">
      </div>
    </div>
    <p style="font-size:.8rem;color:var(--texto-muted);margin-bottom:1.25rem;">Aponta a câmara para aceder diretamente a este local.</p>
    <div style="display:flex;gap:.75rem;justify-content:center;">
      <button onclick="downloadQR()" class="btn btn-sm btn-primary"><i class="fas fa-download"></i> Descarregar</button>
      <button onclick="fecharModalQR()" class="btn btn-sm" style="border:1px solid var(--creme-escuro);color:var(--texto-muted);">Fechar</button>
    </div>
  </div>
</div>

<script>
(function() {
  const localUrl = encodeURIComponent('<?= SITE_URL ?>/pages/local.php?id=<?= $id ?>');
  const localNome = '<?= addslashes(local_nome_publico($local)) ?>';

  window.abrirModalQR = function() {
    const modal = document.getElementById('modal-qr');
    const img   = document.getElementById('qr-img');
    img.src = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&margin=12&data=' + localUrl;
    modal.style.display = 'flex';
  };

  window.fecharModalQR = function() {
    document.getElementById('modal-qr').style.display = 'none';
  };

  window.downloadQR = function() {
    const url = 'https://api.qrserver.com/v1/create-qr-code/?size=600x600&margin=20&data=' + localUrl;
    fetch(url)
      .then(r => r.blob())
      .then(blob => {
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = 'qr-' + localNome.toLowerCase().replace(/[^a-z0-9]+/g, '-') + '.png';
        a.click();
        URL.revokeObjectURL(a.href);
      })
      .catch(() => window.open(url, '_blank'));
  };

  document.getElementById('modal-qr').addEventListener('click', function(e) {
    if (e.target === this) fecharModalQR();
  });
})();
</script>

<?php if ($user && !$ja_checkin && $local['latitude'] && $local['longitude']): ?>
<div id="notif-proximidade" style="display:none;position:fixed;bottom:0;left:0;right:0;z-index:9000;
     background:var(--verde-escuro);color:var(--creme);padding:1rem 1.25rem;
     align-items:center;gap:.75rem;box-shadow:0 -4px 24px rgba(0,0,0,.35);">
  <i class="fas fa-location-dot" style="color:var(--dourado);font-size:1.4rem;flex-shrink:0;"></i>
  <div style="flex:1;min-width:0;">
    <div style="font-weight:700;font-size:.92rem;margin-bottom:.1rem;">Estás perto deste local!</div>
    <div style="font-size:.8rem;opacity:.75;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
      Queres registar a tua visita a <strong><?= h(local_nome_publico($local)) ?></strong>?
    </div>
  </div>
  <button onclick="confirmarCheckinProximo()"
          style="background:var(--dourado);color:var(--verde-escuro);border:none;padding:.5rem 1.1rem;
                 border-radius:var(--radius);font-weight:700;font-size:.88rem;cursor:pointer;white-space:nowrap;flex-shrink:0;">
    <i class="fas fa-check"></i> Sim!
  </button>
  <button onclick="document.getElementById('notif-proximidade').style.display='none'"
          style="background:none;border:none;color:rgba(245,239,230,.5);font-size:1.3rem;cursor:pointer;flex-shrink:0;padding:.25rem .5rem;line-height:1;">
    <i class="fas fa-times"></i>
  </button>
</div>
<?php endif; ?>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>