<?php
// ============================================================
// EXEMPLOS DE USO - Sistema de Verificação por Email/SMS
// ============================================================
// Arquivo: EXEMPLOS_INTEGRACAO.md
// 
// Este arquivo mostra como integrar o sistema de Email + SMS
// nas suas páginas de registo, login e verificação.
// ============================================================

?>

# 📝 EXEMPLOS DE INTEGRAÇÃO

## 1️⃣ REGISTO COM ESCOLHA ENTRE EMAIL OU SMS

### Formulário (HTML)

```html
<form method="post" action="pages/registo.php">
    <h2>Registar-se</h2>
    
    <input type="text" name="nome" placeholder="Nome completo" required>
    <input type="email" name="email" placeholder="Email" required>
    <input type="tel" name="telemovel" placeholder="Número de telemóvel" pattern="[0-9\s\-\(\)\+]+" required>
    <input type="password" name="password" placeholder="Palavra-passe" required>
    
    <!-- ESCOLHA DO MÉTODO -->
    <fieldset>
        <legend>Como receber o código de verificação?</legend>
        <label>
            <input type="radio" name="metodo_verificacao" value="email" checked>
            📧 Email
        </label>
        <label>
            <input type="radio" name="metodo_verificacao" value="sms">
            📱 SMS (requer Twilio)
        </label>
    </fieldset>
    
    <button type="submit">Registar</button>
</form>
```

---

### Handler PHP (pages/registo.php)

```php
<?php
require_once '../includes/config.php';
require_once '../includes/mailer.php';
require_once '../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telemovel = trim($_POST['telemovel'] ?? '');
    $password = $_POST['password'] ?? '';
    $metodo = $_POST['metodo_verificacao'] ?? 'email';
    
    // ✅ Validações básicas
    if (empty($nome) || empty($email) || empty($password)) {
        $_SESSION['flash']['error'] = 'Preenche todos os campos obrigatórios.';
        header('Location: ../index.php');
        exit;
    }
    
    if ($metodo === 'sms' && empty($telemovel)) {
        $_SESSION['flash']['error'] = 'Número de telemóvel obrigatório para SMS.';
        header('Location: ../index.php');
        exit;
    }
    
    try {
        // Guardar utilizador na BD (como pendente de verificação)
        $stmt = db()->prepare('
            INSERT INTO utilizadores (nome, email, telemovel, password, verificado)
            VALUES (?, ?, ?, ?, 0)
        ');
        
        $stmt->execute([
            $nome,
            $email,
            $telemovel,
            password_hash($password, PASSWORD_BCRYPT)
        ]);
        
        $utilizador_id = db()->lastInsertId();
        
        // 🔐 Gerar código de 6 dígitos
        $codigo = gerar_e_guardar_codigo($utilizador_id, 'registo');
        
        // 📧 ou 📱 Enviar código
        $sucesso = false;
        if ($metodo === 'sms') {
            $sucesso = enviar_codigo_sms($telemovel, $codigo, 'registo');
            if ($sucesso) {
                $_SESSION['flash']['success'] = "✅ Código enviado por SMS para $telemovel";
            }
        } else {
            $sucesso = enviar_codigo_verificacao($email, $nome, $codigo, 'registo');
            if ($sucesso) {
                $_SESSION['flash']['success'] = "✅ Código enviado por email para $email";
            }
        }
        
        // Guardar ID do utilizador na sessão para verificação
        $_SESSION['utilizador_id'] = $utilizador_id;
        $_SESSION['metodo_verificacao'] = $metodo;
        
        header('Location: verificar.php');
        exit;
        
    } catch (Exception $e) {
        error_log('Erro no registo: ' . $e->getMessage());
        $_SESSION['flash']['error'] = 'Erro ao registar. Tenta novamente.';
        header('Location: ../index.php');
        exit;
    }
}
?>
```

---

## 2️⃣ PÁGINA DE VERIFICAÇÃO

### Formulário (HTML)

```html
<form method="post" action="<?php echo $_SERVER['REQUEST_URI']; ?>">
    <h2>Verifica a tua Conta</h2>
    
    <p>Inseriu um código de 6 dígitos.</p>
    
    <input type="text" 
           name="codigo" 
           placeholder="000000" 
           maxlength="6" 
           pattern="[0-9]{6}" 
           required
           autocomplete="off">
    
    <button type="submit">Verificar</button>
</form>
```

### Handler PHP (pages/verificar.php)

```php
<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/mailer.php';
require_once '../includes/functions.php';

// Verificar se utilizador está registando
if (empty($_SESSION['utilizador_id'])) {
    header('Location: registo.php');
    exit;
}

$utilizador_id = $_SESSION['utilizador_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $codigo = trim($_POST['codigo'] ?? '');
    
    // ✅ Validar código
    if (empty($codigo) || strlen($codigo) !== 6 || !ctype_digit($codigo)) {
        $_SESSION['flash']['error'] = 'Código inválido (deve ter 6 dígitos).';
    } elseif (verificar_codigo($utilizador_id, $codigo, 'registo')) {
        // ✅ Código válido - marcar como verificado
        db()->prepare('UPDATE utilizadores SET verificado = 1 WHERE id = ?')
            ->execute([$utilizador_id]);
        
        $_SESSION['flash']['success'] = '✅ Conta verificada com sucesso!';
        $_SESSION['utilizador_id_autenticado'] = $utilizador_id;
        
        // Limpar sessão de registo
        unset($_SESSION['utilizador_id']);
        unset($_SESSION['metodo_verificacao']);
        
        header('Location: explorar.php');
        exit;
    } else {
        $_SESSION['flash']['error'] = '❌ Código inválido ou expirado.';
    }
}

$metodo = $_SESSION['metodo_verificacao'] ?? 'email';
?>

<div>
    <p>Método de verificação: <strong><?php echo ($metodo === 'sms' ? '📱 SMS' : '📧 Email'); ?></strong></p>
    
    <form method="post">
        <input type="text" name="codigo" placeholder="000000" maxlength="6" required>
        <button type="submit">Verificar</button>
    </form>
    
    <a href="reenviar_codigo.php">Não recebi o código? Reenviar</a>
</div>
```

---

## 3️⃣ REENVIAR CÓDIGO

### Handler PHP (pages/reenviar_codigo.php)

```php
<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/mailer.php';
require_once '../includes/functions.php';

if (empty($_SESSION['utilizador_id'])) {
    header('Location: registo.php');
    exit;
}

$utilizador_id = $_SESSION['utilizador_id'];
$metodo = $_SESSION['metodo_verificacao'] ?? 'email';

try {
    // Buscar dados do utilizador
    $stmt = db()->prepare('SELECT nome, email, telemovel FROM utilizadores WHERE id = ?');
    $stmt->execute([$utilizador_id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        throw new Exception('Utilizador não encontrado.');
    }
    
    // 🔐 Gerar novo código
    $codigo = gerar_e_guardar_codigo($utilizador_id, 'registo');
    
    // 📧 ou 📱 Enviar
    if ($metodo === 'sms') {
        if (!enviar_codigo_sms($user['telemovel'], $codigo, 'registo')) {
            throw new Exception('Erro ao enviar SMS.');
        }
        $_SESSION['flash']['success'] = '✅ Novo código enviado por SMS!';
    } else {
        if (!enviar_codigo_verificacao($user['email'], $user['nome'], $codigo, 'registo')) {
            throw new Exception('Erro ao enviar email.');
        }
        $_SESSION['flash']['success'] = '✅ Novo código enviado por email!';
    }
    
    header('Location: verificar.php');
    exit;
    
} catch (Exception $e) {
    error_log($e->getMessage());
    $_SESSION['flash']['error'] = 'Erro ao reenviar código.';
    header('Location: verificar.php');
    exit;
}
?>
```

---

## 4️⃣ LOGIN COM CÓDIGO (2FA)

### Formulário (HTML)

```html
<form method="post" action="pages/login.php">
    <h2>Início de Sessão</h2>
    
    <input type="email" name="email" placeholder="Email" required>
    
    <fieldset>
        <legend>Receber código por:</legend>
        <label>
            <input type="radio" name="metodo_codigo" value="email" checked>
            📧 Email
        </label>
        <label>
            <input type="radio" name="metodo_codigo" value="sms">
            📱 SMS
        </label>
    </fieldset>
    
    <button type="submit">Enviar Código</button>
</form>
```

### Handler PHP (pages/login.php)

```php
<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/mailer.php';
require_once '../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $metodo_codigo = $_POST['metodo_codigo'] ?? 'email';
    
    try {
        // 🔍 Procurar utilizador
        $stmt = db()->prepare('SELECT id, nome, telemovel FROM utilizadores WHERE email = ? AND verificado = 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if (!$user) {
            throw new Exception('Email não encontrado ou não verificado.');
        }
        
        // 🔐 Gerar código
        $codigo = gerar_e_guardar_codigo($user['id'], 'login');
        
        // 📧 ou 📱 Enviar
        if ($metodo_codigo === 'sms') {
            enviar_codigo_sms($user['telemovel'], $codigo, 'login');
        } else {
            enviar_codigo_verificacao($email, $user['nome'], $codigo, 'login');
        }
        
        // Guardar na sessão
        $_SESSION['login_id'] = $user['id'];
        $_SESSION['login_metodo'] = $metodo_codigo;
        
        $_SESSION['flash']['success'] = '✅ Código enviado!';
        header('Location: login_verificar.php');
        exit;
        
    } catch (Exception $e) {
        $_SESSION['flash']['error'] = $e->getMessage();
    }
}
?>
```

---

## 5️⃣ USAR A FUNÇÃO GENÉRICA `enviar_codigo()`

Se preferir simplificar, existe uma função wrapper:

```php
<?php
// Verificar preferência do utilizador
$metodo = $_GET['metodo'] ?? 'email'; // email ou sms

$codigo = gerar_e_guardar_codigo($user['id'], 'registo');

// Uma única chamada para ambos os métodos
$sucesso = enviar_codigo(
    email: $user['email'],
    nome: $user['nome'],
    numero_sms: $user['telemovel'],
    codigo: $codigo,
    metodo: $metodo,  // 'email' ou 'sms'
    tipo: 'registo'   // 'registo' ou 'login'
);

if ($sucesso) {
    echo "Código enviado!";
} else {
    echo "Erro ao enviar código.";
}
?>
```

---

## 📊 Fluxo Completo de Registo

```
┌─────────────────┐
│  Utilizador     │
│  Registo        │
└────────┬────────┘
         │
    ┌────▼─────────────────────────┐
    │ Escolher: Email ou SMS?       │
    └────┬────────────────┬────────┘
         │                │
    Email│                │SMS
         │                │
    ┌────▼──────┐    ┌────▼──────┐
    │ Gerar     │    │ Gerar     │
    │ Código    │    │ Código    │
    └────┬──────┘    └────┬──────┘
         │                │
    ┌────▼──────┐    ┌────▼──────┐
    │ Guardar BD│    │ Guardar BD│
    └────┬──────┘    └────┬──────┘
         │                │
    ┌────▼──────┐    ┌────▼──────┐
    │ Enviar    │    │ Enviar    │
    │ via       │    │ via       │
    │ PHPMailer │    │ Twilio    │
    └────┬──────┘    └────┬──────┘
         │                │
         └────┬───────────┘
              │
         ┌────▼──────────────┐
         │ Página de         │
         │ Verificação       │
         │ (Inserir código)  │
         └────┬──────────────┘
              │
         ┌────▼──────────────┐
         │ Verificar código  │
         │ na BD             │
         └────┬──────────────┘
              │
         ┌────▼──────────────┐
         │ Marcar como       │
         │ Verificado        │
         └────┬──────────────┘
              │
         ┌────▼──────────────┐
         │ Sucesso!          │
         │ Conta criada      │
         └───────────────────┘
```

---

## 🛠️ Variáveis de Sessão Úteis

```php
// Durante Registo
$_SESSION['utilizador_id']        // ID do utilizador a fazer registo
$_SESSION['metodo_verificacao']   // 'email' ou 'sms'

// Durante Login
$_SESSION['login_id']             // ID do utilizador a fazer login
$_SESSION['login_metodo']         // 'email' ou 'sms'

// Após Verificação
$_SESSION['utilizador_id_autenticado'] // ID do utilizador autenticado
```

---

## ⚠️ Checklist de Implementação

- [ ] Arquivo `config.php` atualizado com credenciais Email
- [ ] (Optional) Arquivo `config.php` atualizado com credenciais Twilio
- [ ] Formulários atualizados com Radio Buttons (Email/SMS)
- [ ] Handlers PHP atualizados com a lógica de $metodo
- [ ] Base de dados tem tabela `codigos_verificacao`
- [ ] Base de dados tem tabela `utilizadores`
- [ ] Testado envio de Email
- [ ] (Optional) Testado envio de SMS

---

**Dúvidas?** Vê o arquivo `CONFIGURACAO_MAILER.md` para mais informações!
