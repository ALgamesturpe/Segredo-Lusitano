<?php
// ============================================================
// SEGREDO LUSITANO — Like (AJAX)
// ============================================================
require_once dirname(__DIR__) . '/includes/functions.php';

header('Content-Type: application/json');

$user = auth_user();
if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autenticado']);
    exit;
}

$local_id = (int)($_POST['local_id'] ?? 0);
if (!$local_id) {
    http_response_code(400);
    echo json_encode(['error' => 'ID inválido']);
    exit;
}

$result = toggle_like($local_id, $user['id']);
echo json_encode($result);
