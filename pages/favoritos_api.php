<?php
error_reporting(0);
ini_set('display_errors', 0);
require_once dirname(__DIR__) . '/includes/functions.php';
require_login();
_migrar_favoritos();

header('Content-Type: application/json');
$user     = auth_user();
$uid      = $user['id'];
$acao     = $_GET['acao'] ?? $_POST['acao'] ?? '';

// ── Toggle guardar / remover ──────────────────────────────
if ($acao === 'toggle') {
    $local_id = (int)($_POST['local_id'] ?? 0);
    if (!$local_id) { echo json_encode(['ok'=>false,'erro'=>'ID inválido']); exit; }

    // Verificar que o local existe e está aprovado
    $stL = db()->prepare('SELECT id FROM locais WHERE id = ? AND estado = "aprovado" AND apagado_em IS NULL');
    $stL->execute([$local_id]);
    if (!$stL->fetch()) { echo json_encode(['ok'=>false,'erro'=>'Local inválido']); exit; }

    $stChk = db()->prepare('SELECT id FROM favoritos WHERE utilizador_id = ? AND local_id = ?');
    $stChk->execute([$uid, $local_id]);
    $existe = $stChk->fetch();

    if ($existe) {
        db()->prepare('DELETE FROM favoritos WHERE utilizador_id = ? AND local_id = ?')->execute([$uid, $local_id]);
        echo json_encode(['ok'=>true, 'guardado'=>false]);
    } else {
        db()->prepare('INSERT INTO favoritos (utilizador_id, local_id) VALUES (?,?)')->execute([$uid, $local_id]);
        echo json_encode(['ok'=>true, 'guardado'=>true]);
    }
    exit;
}

// ── Verificar se está guardado ────────────────────────────
if ($acao === 'estado') {
    $local_id = (int)($_GET['local_id'] ?? 0);
    if (!$local_id) { echo json_encode(['ok'=>false]); exit; }
    $st = db()->prepare('SELECT id FROM favoritos WHERE utilizador_id = ? AND local_id = ?');
    $st->execute([$uid, $local_id]);
    echo json_encode(['ok'=>true, 'guardado'=>(bool)$st->fetch()]);
    exit;
}

echo json_encode(['ok'=>false,'erro'=>'Ação desconhecida']);
