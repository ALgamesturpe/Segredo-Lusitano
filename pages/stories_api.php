<?php
// ============================================================
// SEGREDO LUSITANO — API de Stories
// ============================================================
error_reporting(0);
ini_set('display_errors', 0);
require_once dirname(__DIR__) . '/includes/functions.php';

header('Content-Type: application/json');
$user = auth_user();
$acao = $_GET['acao'] ?? $_POST['acao'] ?? '';

// ── Pesquisa de locais (autocomplete) ────────────────────────
if ($acao === 'pesquisa_locais') {
    $q = trim($_GET['q'] ?? '');
    if (strlen($q) < 2) { echo json_encode(['ok' => true, 'locais' => []]); exit; }
    $st = db()->prepare("SELECT id, nome FROM locais WHERE estado = 'aprovado' AND nome LIKE ? LIMIT 10");
    $st->execute(["%$q%"]);
    echo json_encode(['ok' => true, 'locais' => $st->fetchAll()]);
    exit;
}

// ── Stories de um utilizador (para modal) ────────────────────
if ($acao === 'stories_user') {
    $uid = (int)($_GET['user_id'] ?? 0);
    if (!$uid) { echo json_encode(['ok' => false]); exit; }
    $stories = get_stories_por_user($uid);
    // Enrich with user's own reaction
    $user_id = $user ? $user['id'] : null;
    foreach ($stories as &$s) {
        $s['reacoes']     = get_story_reacoes((int)$s['id']);
        $s['minha_reacao'] = $user_id ? get_minha_reacao_story((int)$s['id'], $user_id) : null;
    }
    unset($s);
    echo json_encode(['ok' => true, 'stories' => $stories]);
    exit;
}

// ── Mais stories (infinite scroll) ───────────────────────────
if ($acao === 'mais') {
    $offset = max(0, (int)($_GET['offset'] ?? 0));
    $stories = get_stories(10, $offset);
    $total   = count_stories();
    $user_id = $user ? $user['id'] : null;
    foreach ($stories as &$s) {
        $s['reacoes']     = get_story_reacoes((int)$s['id']);
        $s['minha_reacao'] = $user_id ? get_minha_reacao_story((int)$s['id'], $user_id) : null;
    }
    unset($s);
    echo json_encode(['ok' => true, 'stories' => $stories, 'total' => $total]);
    exit;
}

// ── Reações (GET: listar) ─────────────────────────────────────
if ($acao === 'reacoes') {
    $story_id = (int)($_GET['story_id'] ?? 0);
    $reacoes  = get_story_reacoes($story_id);
    $minha    = $user ? get_minha_reacao_story($story_id, $user['id']) : null;
    echo json_encode(['ok' => true, 'reacoes' => $reacoes, 'minha_reacao' => $minha]);
    exit;
}

// ── Comentários (GET: listar) ─────────────────────────────────
if ($acao === 'comentarios') {
    $story_id    = (int)($_GET['story_id'] ?? 0);
    $comentarios = get_story_comentarios($story_id);
    echo json_encode(['ok' => true, 'comentarios' => $comentarios]);
    exit;
}

// ── Ações autenticadas ────────────────────────────────────────
if (!$user) {
    echo json_encode(['ok' => false, 'erro' => 'login']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();
}

$uid = $user['id'];

if ($acao === 'reagir') {
    $story_id = (int)($_POST['story_id'] ?? 0);
    $emoji    = $_POST['emoji'] ?? '';
    echo json_encode(toggle_story_reacao($story_id, $uid, $emoji));
    exit;
}

if ($acao === 'comentar') {
    $story_id = (int)($_POST['story_id'] ?? 0);
    $texto    = trim($_POST['texto'] ?? '');
    echo json_encode(add_story_comentario($story_id, $uid, $texto));
    exit;
}

echo json_encode(['ok' => false, 'erro' => 'Ação inválida']);
