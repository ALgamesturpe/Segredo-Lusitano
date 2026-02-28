<?php
// ============================================================
// SEGREDO LUSITANO — Apagar Local
// ============================================================
require_once dirname(__DIR__) . '/includes/functions.php';
require_login();

$user = auth_user();
$id   = (int)($_GET['id'] ?? 0);
$local = $id ? get_local($id) : null;

if (!$local || ($local['utilizador_id'] != $user['id'] && !is_admin())) {
    flash('error', 'Sem permissão.');
    header('Location: ' . SITE_URL . '/index.php');
    exit;
}

delete_local($id);
flash('success', 'Local apagado com sucesso.');
header('Location: ' . SITE_URL . '/pages/explorar.php');
exit;
