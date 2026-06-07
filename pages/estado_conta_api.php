<?php
require_once dirname(__DIR__) . '/includes/config.php';

if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['ok' => true]);
    exit;
}

$uid = (int)$_SESSION['user_id'];
$st  = db()->prepare('SELECT ativo FROM utilizadores WHERE id = ? AND role != "[deleted]"');
$st->execute([$uid]);
$user = $st->fetch();

if (!$user) {
    echo json_encode(['ok' => false, 'motivo' => 'banido']);
} elseif (!$user['ativo']) {
    echo json_encode(['ok' => false, 'motivo' => 'suspenso']);
} else {
    echo json_encode(['ok' => true]);
}
