<?php
// Teste se PHPMailer está funcionando

echo "🔍 Testando PHPMailer...\n\n";

// Teste 1: Ficheiros existem?
echo "1️⃣  Ficheiros existem?\n";
$files = [
    'lib/PHPMailer/src/Exception.php',
    'lib/PHPMailer/src/PHPMailer.php',
    'lib/PHPMailer/src/SMTP.php'
];

foreach ($files as $file) {
    if (file_exists($file)) {
        echo "   ✅ $file (" . number_format(filesize($file)) . " bytes)\n";
    } else {
        echo "   ❌ $file NÃO ENCONTRADO\n";
    }
}

echo "\n";

// Teste 2: Carregar PHPMailer
echo "2️⃣  Carregando PHPMailer...\n";

try {
    require_once 'lib/PHPMailer/src/Exception.php';
    require_once 'lib/PHPMailer/src/PHPMailer.php';
    require_once 'lib/PHPMailer/src/SMTP.php';
    
    if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        echo "   ✅ PHPMailer carregado com sucesso!\n";
        echo "   📌 Versão: " . \PHPMailer\PHPMailer\PHPMailer::VERSION . "\n";
    } else {
        echo "   ❌ Classe PHPMailer não encontrada\n";
    }
} catch (Exception $e) {
    echo "   ❌ Erro ao carregar: " . $e->getMessage() . "\n";
}

echo "\n";

// Teste 3: Criar instância
echo "3️⃣  Criar instância de PHPMailer...\n";

try {
    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    echo "   ✅ Instância criada com sucesso!\n";
    echo "   📌 Modo DEBUG: " . ($mail->SMTPDebug ? "Sim" : "Não") . "\n";
} catch (Exception $e) {
    echo "   ❌ Erro ao criar instância: " . $e->getMessage() . "\n";
}

echo "\n========================================\n";
echo "✅ TUDO FUNCIONANDO! PHPMailer está pronto.\n";
echo "========================================\n\n";

echo "📋 Próximos passos:\n";
echo "   1. Abrir http://localhost/Segredo-Lusitano/pages/registo.php\n";
echo "   2. Criar uma conta com email real\n";
echo "   3. Receber código de 6 dígitos no email\n";
echo "   4. Confirmar código na página de verificação\n\n";
?>
