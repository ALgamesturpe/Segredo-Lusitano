<?php
// ============================================================
// SEGREDO LUSITANO — API de Mensagens
// ============================================================
error_reporting(0);
ini_set('display_errors', 0);
require_once dirname(__DIR__) . '/includes/functions.php';
require_login();

header('Content-Type: application/json');
$user = auth_user();
$uid  = $user['id'];
$acao = $_GET['acao'] ?? $_POST['acao'] ?? '';

// ── Listar follows mútuos (para modal de recomendação) ────
if ($acao === 'follows_mutuos') {
    $st = db()->prepare(
        'SELECT u.id, u.nome, u.username, u.avatar
         FROM utilizadores u
         JOIN seguidores s1 ON s1.seguidor_id = ? AND s1.seguido_id = u.id
         JOIN seguidores s2 ON s2.seguidor_id = u.id AND s2.seguido_id = ?
         WHERE u.ativo = 1 AND u.role != "[deleted]"
         ORDER BY u.nome ASC'
    );
    $st->execute([$uid, $uid]);
    echo json_encode(['ok' => true, 'utilizadores' => $st->fetchAll()]);
    exit;
}

// ── Recomendar local por mensagem ────────────────────────
if ($acao === 'recomendar') {
    $dest_id  = (int)($_POST['destinatario_id'] ?? 0);
    $local_id = (int)($_POST['local_id'] ?? 0);
    $texto    = trim($_POST['texto'] ?? '');

    if (!$dest_id || !$local_id) { echo json_encode(['ok'=>false,'erro'=>'Dados inválidos']); exit; }

    // Verificar acesso: seguimento mútuo OU mensagem prévia
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
    $stChk->execute([$dest_id, $uid, $uid, $dest_id, $uid, $dest_id, $dest_id, $uid]);
    if ((int)$stChk->fetchColumn() < 1) {
        echo json_encode(['ok'=>false,'erro'=>'Não se seguem mutuamente']); exit;
    }

    // Verificar que o local existe e está aprovado
    $stL = db()->prepare('SELECT id FROM locais WHERE id = ? AND estado = "aprovado" AND apagado_em IS NULL');
    $stL->execute([$local_id]);
    if (!$stL->fetch()) { echo json_encode(['ok'=>false,'erro'=>'Local inválido']); exit; }

    $st = db()->prepare('INSERT INTO mensagens (remetente_id, destinatario_id, texto, local_id) VALUES (?,?,?,?)');
    $st->execute([$uid, $dest_id, $texto, $local_id]);
    $id = (int)db()->lastInsertId();

    $stMsg = db()->prepare(
        'SELECT m.*, l.nome AS local_nome, l.foto_capa AS local_foto,
                r.nome AS local_regiao, c.nome AS local_categoria
         FROM mensagens m
         LEFT JOIN locais l ON l.id = m.local_id
         LEFT JOIN regioes r ON r.id = l.regiao_id
         LEFT JOIN categorias c ON c.id = l.categoria_id
         WHERE m.id = ?'
    );
    $stMsg->execute([$id]);
    echo json_encode(['ok'=>true, 'mensagem'=>$stMsg->fetch()]);
    exit;
}

// ── Enviar mensagem ───────────────────────────────────────
if ($acao === 'enviar') {
    $dest_id = (int)($_POST['destinatario_id'] ?? 0);
    $texto   = trim($_POST['texto'] ?? '');

    if (!$dest_id || !$texto) { echo json_encode(['ok'=>false,'erro'=>'Dados inválidos']); exit; }
    if (strlen($texto) > 1000) { echo json_encode(['ok'=>false,'erro'=>'Mensagem demasiado longa']); exit; }

    // Verificar acesso: seguimento mútuo OU mensagem prévia entre os dois
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
    $stChk->execute([$dest_id, $uid, $uid, $dest_id, $uid, $dest_id, $dest_id, $uid]);
    if ((int)$stChk->fetchColumn() < 1) {
        echo json_encode(['ok'=>false,'erro'=>'Não se seguem mutuamente']); exit;
    }

    $st = db()->prepare('INSERT INTO mensagens (remetente_id, destinatario_id, texto) VALUES (?,?,?)');
    $st->execute([$uid, $dest_id, $texto]);
    $id = (int)db()->lastInsertId();

    $stMsg = db()->prepare('SELECT * FROM mensagens WHERE id = ?');
    $stMsg->execute([$id]);
    $msg = $stMsg->fetch();

    echo json_encode(['ok'=>true, 'mensagem'=>$msg]);
    exit;
}

// ── Novas mensagens (polling) ─────────────────────────────
if ($acao === 'novas') {
    $com   = (int)($_GET['com'] ?? 0);
    $desde = $_GET['desde'] ?? '0';

    if (!$com) { echo json_encode(['mensagens'=>[]]); exit; }

    $st = db()->prepare(
        'SELECT m.*, u.username AS remetente_username, u.avatar AS remetente_avatar,
                l.nome AS local_nome, l.foto_capa AS local_foto,
                r.nome AS local_regiao, c.nome AS local_categoria
         FROM mensagens m
         JOIN utilizadores u ON u.id = m.remetente_id
         LEFT JOIN locais l ON l.id = m.local_id
         LEFT JOIN regioes r ON r.id = l.regiao_id
         LEFT JOIN categorias c ON c.id = l.categoria_id
         WHERE ((m.remetente_id=? AND m.destinatario_id=?)
             OR (m.remetente_id=? AND m.destinatario_id=?))
           AND m.criado_em > ?
           AND NOT (m.destinatario_id=? AND m.apagada_por_receptor=1)
         ORDER BY m.criado_em ASC'
    );
    $st->execute([$uid, $com, $com, $uid, $desde, $uid]);
    $msgs = $st->fetchAll();

    // Marcar como lidas
    db()->prepare('UPDATE mensagens SET lida=1 WHERE remetente_id=? AND destinatario_id=? AND lida=0')
        ->execute([$com, $uid]);

    echo json_encode(['mensagens'=>$msgs]);
    exit;
}

// ── Contador de não lidas ────────────────────────────────
if ($acao === 'nao_lidas') {
    $st = db()->prepare('SELECT COUNT(*) FROM mensagens WHERE destinatario_id=? AND lida=0');
    $st->execute([$uid]);
    echo json_encode(['total'=>(int)$st->fetchColumn()]);
    exit;
}

// ── Enviar ficheiro ───────────────────────────────────────
if ($acao === 'ficheiro') {
    $dest_id = (int)($_POST['destinatario_id'] ?? 0);
    if (!$dest_id || empty($_FILES['ficheiro'])) {
        echo json_encode(['ok'=>false,'erro'=>'Dados inválidos']); exit;
    }

    // Verificar acesso
    $stChk = db()->prepare(
        'SELECT (SELECT COUNT(*) FROM seguidores s1
         JOIN seguidores s2 ON s2.seguidor_id=? AND s2.seguido_id=?
         WHERE s1.seguidor_id=? AND s1.seguido_id=?) +
        (SELECT COUNT(*) FROM mensagens
         WHERE (remetente_id=? AND destinatario_id=?)
            OR (remetente_id=? AND destinatario_id=?) LIMIT 1) AS total'
    );
    $stChk->execute([$dest_id,$uid,$uid,$dest_id,$uid,$dest_id,$dest_id,$uid]);
    if ((int)$stChk->fetchColumn() < 1) {
        echo json_encode(['ok'=>false,'erro'=>'Sem permissão']); exit;
    }

    $f = $_FILES['ficheiro'];
    if ($f['error'] !== 0 || $f['size'] > 10*1024*1024) {
        echo json_encode(['ok'=>false,'erro'=>'Erro no ficheiro']); exit;
    }

    $ext  = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    $nome = uniqid('msg_') . '.' . $ext;
    $dir  = dirname(__DIR__) . '/uploads/mensagens/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    if (!move_uploaded_file($f['tmp_name'], $dir . $nome)) {
        echo json_encode(['ok'=>false,'erro'=>'Erro ao guardar']); exit;
    }

    // Guardar na BD com ficheiro no campo texto e flag na coluna ficheiro
    $st = db()->prepare('INSERT INTO mensagens (remetente_id, destinatario_id, texto, ficheiro) VALUES (?,?,?,?)');
    $st->execute([$uid, $dest_id, '', $nome]);
    $id = (int)db()->lastInsertId();

    $stMsg = db()->prepare('SELECT * FROM mensagens WHERE id = ?');
    $stMsg->execute([$id]);
    echo json_encode(['ok'=>true, 'mensagem'=>$stMsg->fetch()]);
    exit;
}

// ── Eliminar mensagem ─────────────────────────────────────
if ($acao === 'eliminar') {
    $msg_id = (int)($_POST['msg_id'] ?? 0);
    $tipo   = $_POST['tipo'] ?? 'mim'; // 'todos' ou 'mim'
    if (!$msg_id) { echo json_encode(['ok'=>false]); exit; }

    $st = db()->prepare('SELECT * FROM mensagens WHERE id = ?');
    $st->execute([$msg_id]);
    $msg = $st->fetch();
    if (!$msg) { echo json_encode(['ok'=>false,'erro'=>'Mensagem não encontrada']); exit; }

    $e_remetente    = ((int)$msg['remetente_id']    === $uid);
    $e_destinatario = ((int)$msg['destinatario_id'] === $uid);

    if (!$e_remetente && !$e_destinatario) {
        echo json_encode(['ok'=>false,'erro'=>'Sem permissão']); exit;
    }

    if ($tipo === 'todos') {
        // Só o remetente pode apagar para todos
        if (!$e_remetente) { echo json_encode(['ok'=>false,'erro'=>'Só o remetente pode apagar para todos']); exit; }
        // Apagar ficheiro do disco (conteúdo removido)
        if (!empty($msg['ficheiro'])) {
            $path = dirname(__DIR__) . '/uploads/mensagens/' . $msg['ficheiro'];
            if (is_file($path)) @unlink($path);
        }
        db()->prepare('UPDATE mensagens SET apagada_para_todos=1, texto="", ficheiro=NULL, local_id=NULL WHERE id=?')
            ->execute([$msg_id]);
        echo json_encode(['ok'=>true, 'tipo'=>'todos']);
    } else {
        // Apagar só para mim — só o destinatário pode apagar do seu lado
        if (!$e_destinatario) { echo json_encode(['ok'=>false,'erro'=>'Só o destinatário pode apagar para si']); exit; }
        db()->prepare('UPDATE mensagens SET apagada_por_receptor=1 WHERE id=?')
            ->execute([$msg_id]);
        echo json_encode(['ok'=>true, 'tipo'=>'mim']);
    }
    exit;
}

echo json_encode(['ok'=>false,'erro'=>'Ação desconhecida']);