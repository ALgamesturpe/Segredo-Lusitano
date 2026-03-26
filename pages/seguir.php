<?php
// SEGREDO LUSITANO — Seguir/Deixar de seguir (AJAX)
require_once dirname(__DIR__) . '/includes/functions.php';

header('Content-Type: application/json');

$user = auth_user();
if (!$user) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'msg' => 'Não autenticado']);
    exit;
}

$seguido_id = (int)($_POST['id'] ?? 0);
if (!$seguido_id || $seguido_id === $user['id']) {
    echo json_encode(['ok' => false, 'msg' => 'ID inválido']);
    exit;
}

$st = db()->prepare('SELECT id FROM seguidores WHERE seguidor_id = ? AND seguido_id = ?');
$st->execute([$user['id'], $seguido_id]);

if ($st->fetch()) {
    db()->prepare('DELETE FROM seguidores WHERE seguidor_id = ? AND seguido_id = ?')
        ->execute([$user['id'], $seguido_id]);
    $a_seguir = false;
} else {
    db()->prepare('INSERT INTO seguidores (seguidor_id, seguido_id) VALUES (?, ?)')
        ->execute([$user['id'], $seguido_id]);
    $a_seguir = true;
}

$st2 = db()->prepare('SELECT COUNT(*) FROM seguidores WHERE seguido_id = ?');
$st2->execute([$seguido_id]);
$total = (int)$st2->fetchColumn();

echo json_encode(['ok' => true, 'a_seguir' => $a_seguir, 'total' => $total]);