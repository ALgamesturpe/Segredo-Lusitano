<?php
// Script para gerar hash bcrypt válido para a conta admin

$password = '';
$hash = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    if ($password) {
        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerar Hash Bcrypt</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; }
        .container { background: #f1e3c8; padding: 30px; border-radius: 8px; }
        h1 { color: #000000; }
        input, button { padding: 10px; font-size: 14px; margin: 10px 0; width: 100%; box-sizing: border-box; }
        button { background: #e6b326; color: white; border: none; cursor: pointer; border-radius: 4px; }
        button:hover { background: #2b6974; }
        .hash-output { background: white; padding: 15px; margin-top: 20px; border-radius: 4px; border: 1px solid #2b6974; }
        .hash-output h3 { margin-top: 0; }
        code { background: #dddddd; padding: 10px; display: block; word-break: break-all; }
        .info { background: #e8f4f8; padding: 15px; margin-top: 20px; border-left: 4px solid #2b6974; border-radius: 4px; }
    </style>
</head>
<body>
    <div class="container">
        <h1> Hash Bcrypt </h1>
        
        <form method="POST">
            <label for="password"><strong>Password:</strong></label>
            <input type="password" id="password" name="password" placeholder="Ex: MinhaPassword123" value="<?= htmlspecialchars($password) ?>">
            <button type="submit">Gerar Hash</button>
        </form>

        <?php if ($hash): ?>
            <div class="hash-output">
                <p><strong>Password:</strong> <?= htmlspecialchars($password) ?></p>
                <p><strong>Hash:</strong></p>
                <code><?= $hash ?></code>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
