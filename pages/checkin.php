<?php
// SEGREDO LUSITANO — Check-in AJAX endpoint
require_once dirname(__DIR__) . '/includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'erro' => 'Método não permitido']);
    exit;
}

$user = auth_user();
if (!$user) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'erro' => 'Sem sessão']);
    exit;
}

$local_id = (int)($_POST['local_id'] ?? 0);
if (!$local_id) {
    echo json_encode(['ok' => false, 'erro' => 'Local inválido']);
    exit;
}

$st = db()->prepare('SELECT id FROM locais WHERE id = ? AND estado = "aprovado" AND bloqueado = 0 AND apagado_em IS NULL');
$st->execute([$local_id]);
if (!$st->fetch()) {
    echo json_encode(['ok' => false, 'erro' => 'Local não encontrado']);
    exit;
}

_migrar_checkins();

if (user_fez_checkin($local_id, $user['id'])) {
    echo json_encode(['ok' => false, 'ja_fez' => true]);
    exit;
}

try {
    $st = db()->prepare('INSERT INTO checkins (utilizador_id, local_id) VALUES (?, ?)');
    $st->execute([$user['id'], $local_id]);
    $stDono = db()->prepare('SELECT utilizador_id FROM locais WHERE id=?');
    $stDono->execute([$local_id]);
    $dono_id = (int)$stDono->fetchColumn();
    if ($dono_id) criar_notificacao($dono_id, $user['id'], 'checkin', $local_id);
    echo json_encode(['ok' => true]);
} catch (\PDOException $e) {
    echo json_encode(['ok' => false, 'erro' => 'Erro ao registar']);
}
