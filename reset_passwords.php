<?php
// ============================================================
// SEGREDO LUSITANO - Corrigir passwords dos utilizadores demo
// APAGAR ESTE FICHEIRO APÓS USAR!
// ============================================================
require_once 'includes/config.php';

$utilizadores = [
    'admin' => 'admin123',
    'joao'  => 'demo123',
];

foreach ($utilizadores as $username => $password) {
    $hash = password_hash($password, PASSWORD_BCRYPT);
    $st = db()->prepare('UPDATE utilizadores SET password = ? WHERE username = ?');
    $st->execute([$hash, $username]);
    echo "✅ Password de <strong>$username</strong> atualizada para <code>$password</code><br>";
}

echo "<br><strong style='color:red'>⚠️ APAGA ESTE FICHEIRO AGORA!</strong>";
echo "<br><a href='pages/login.php'>Ir para o login</a>";
