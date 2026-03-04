# 📧 GUIA DE CONFIGURAÇÃO - MAILER (Email + SMS)

## 🎯 Objetivo
Configurar o sistema de envio de **códigos de verificação** por **Email** ou **SMS** para o seu projeto Segredo Lusitano.

---

## 📋 ÍNDICE
1. [Opção 1: Enviar por EMAIL (Gmail)](#opção-1-email)
2. [Opção 2: Enviar por SMS (Twilio)](#opção-2-sms)
3. [Como o sistema funciona](#como-funciona)
4. [Testar a configuração](#testar)

---

## OPÇÃO 1: EMAIL {#opção-1-email}

### ✅ PASSO 1: Ativar PHPMailer

Na pasta raiz do projeto (`c:\xampp\htdocs\Segredo-Lusitano`), abra o PowerShell e execute:

```powershell
composer require phpmailer/phpmailer
```

Se não tem `composer` instalado, [descarregue aqui](https://getcomposer.org).

---

### ✅ PASSO 2: Preparar a conta Gmail

#### A) Ativar 2FA (Autenticação de Dois Fatores)
1. Aceda a [myaccount.google.com](https://myaccount.google.com)
2. Menu lateral: **Segurança**
3. Procure "Verificação em duas etapas" e ative-o
4. Escolha o método (SMS ou app autenticador)

#### B) Gerar App Password
1. Volte a **Segurança** (em myaccount.google.com)
2. Procure **"Senhas de Apps"** (só aparece se 2FA ativo)
3. Selecione: **App: Mail** | **Dispositivo: Windows Computer**
4. Google gera uma password com **16 caracteres**
5. **Copie-a** (exemplo: `abcd efgh ijkl mnop`)

---

### ✅ PASSO 3: Configurar em `config.php`

Abra o arquivo: `includes/config.php`

Procure estas linhas (aproximadamente linha 35):

```php
// ============================================================
// CONFIGURAÇÃO DE EMAIL (SMTP)
// ============================================================
define('MAIL_HOST',     'smtp.gmail.com');
define('MAIL_PORT',     587);
define('MAIL_USER',     'o.teu@gmail.com');     // ← ALTERAR
define('MAIL_PASS',     'a_tua_password');      // ← ALTERAR
define('MAIL_FROM',     'o.teu@gmail.com');     // ← ALTERAR
define('MAIL_FROM_NAME','Segredo Lusitano');
```

**Substitua:**
- `o.teu@gmail.com` → seu email Gmail (ex: `jorge.silva@gmail.com`)
- `a_tua_password` → a App Password de 16 caracteres que gerou (sem espaços)

**Exemplo:**
```php
define('MAIL_USER',     'jorge.silva@gmail.com');
define('MAIL_PASS',     'abcdefghijklmnop');  // sem espaços
define('MAIL_FROM',     'jorge.silva@gmail.com');
```

---

### ✅ PASSO 4: Testar
Veja a secção [Testar a Configuração](#testar).

---

## OPÇÃO 2: SMS {#opção-2-sms}

### ✅ PASSO 1: Criar conta Twilio

1. Aceda a [twilio.com](https://www.twilio.com)
2. Clique em **Sign Up** (registo gratuito)
3. Preencha os dados (nome, email, país, etc.)
4. **Confirme o email**
5. Escolha a opção: "SMS"

---

### ✅ PASSO 2: Obter credenciais Twilio

1. No painel Twilio, vá para **Account** → **API Keys & Tokens**
2. Copie:
   - **Account SID** (ex: `ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx`)
   - **Auth Token** (ex: `0a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p`)

3. Na secção **Phone Numbers** → **Manage Phone Numbers** → **Active Numbers**
4. Copie o seu número Twilio (ex: `+12345678901`)

---

### ✅ PASSO 3: Adicionar credenciais em `config.php`

Abra `includes/config.php` e adicione no final (antes da função `db()`):

```php
// ============================================================
// CONFIGURAÇÃO DE SMS (Twilio)
// ============================================================
define('SMS_ENABLED',       true);           // true = activar SMS
define('SMS_PROVIDER',      'twilio');       // 'twilio' ou 'outro'
define('TWILIO_ACCOUNT_SID','ACxxxxxxxx...'); // ← Seu Account SID
define('TWILIO_AUTH_TOKEN', '0a1b2c3d...');   // ← Seu Auth Token
define('TWILIO_PHONE',      '+12345678901');  // ← Seu número Twilio
```

---

### ✅ PASSO 4: Instalar SDK Twilio

Na pasta raiz, execute:

```powershell
composer require twilio/sdk
```

---

## Como funciona {#como-funciona}

### 📧 Fluxo de Email

```
Utilizador faz Registo
    ↓
Sistema gera código 6 dígitos
    ↓
enviar_codigo_verificacao() é chamada
    ↓
PHPMailer conecta a SMTP (Gmail)
    ↓
Email é enviado com o código
    ↓
Utilizador recebe email e insere código
```

### 📱 Fluxo de SMS

```
Utilizador seleciona "Enviar por SMS"
    ↓
Sistema gera código 6 dígitos
    ↓
enviar_codigo_sms() é chamada
    ↓
Twilio envia SMS com o código
    ↓
Utilizador recebe SMS e insere código
```

### 📝 Código de Verificação

- **Formato:** 6 dígitos (ex: `123456`)
- **Validade:** 15 minutos
- **Armazenamento:** Tabela `codigos_verificacao` da BD

---

## Testar a Configuração {#testar}

### Para EMAIL:

1. Crie um arquivo `test_mailer.php` na raiz do projeto:

```php
<?php
require_once 'includes/config.php';
require_once 'includes/mailer.php';

// Simular envio de código
$email = 'seu_email_teste@gmail.com';
$nome = 'João Silva';
$codigo = '123456';

if (enviar_codigo_verificacao($email, $nome, $codigo, 'registo')) {
    echo '✅ Email enviado com sucesso!';
} else {
    echo '❌ Erro ao enviar email. Verifique as configurações SMTP.';
}
?>
```

2. Aceda a: `http://localhost/Segredo-Lusitano/test_mailer.php`

3. Verifique a caixa de entrada

---

### Para SMS:

```php
<?php
require_once 'includes/config.php';
require_once 'includes/mailer.php';

$numero_telemovel = '+351912345678'; // com indicativo +351
$codigo = '123456';

if (enviar_codigo_sms($numero_telemovel, $codigo, 'registo')) {
    echo '✅ SMS enviado com sucesso!';
} else {
    echo '❌ Erro ao enviar SMS.';
}
?>
```

---

## ⚠️ Resolução de Problemas

### Email não é enviado
- ✓ Verifique a App Password (sem espaços)
- ✓ Confirme que 2FA está ativo no Gmail
- ✓ Verifique se PHPMailer está instalado (`composer require phpmailer/phpmailer`)
- ✓ Veja logs em: `error_log()` ou arquivo `php_errors.log`

### SMS não é enviado
- ✓ Confirme credenciais Twilio (SID + Token)
- ✓ Verifique se o número começa com `+` (ex: `+12345678901`)
- ✓ Conta Twilio tem saldo/trial ativo?

---

## 🔒 SEGURANÇA

⚠️ **Nunca guarde credenciais no Git!**

1. Adicione ao `.gitignore`:
```
config.php
.env
```

2. Em produção, use variáveis de ambiente:
```php
define('MAIL_USER', $_SERVER['MAIL_USER'] ?? '');
```

---

## 📱 Integração no Formulário

O utilizador escolhe o método no registo:

```html
<!-- no formulário de registo -->
<label>
  <input type="radio" name="metodo_verificacao" value="email" checked> 📧 Email
</label>
<label>
  <input type="radio" name="metodo_verificacao" value="sms"> 📱 SMS
</label>
```

Depois, no handler PHP:
```php
$metodo = $_POST['metodo_verificacao'] ?? 'email';

if ($metodo === 'sms') {
    enviar_codigo_sms($numero_telemovel, $codigo, 'registo');
} else {
    enviar_codigo_verificacao($email, $nome, $codigo, 'registo');
}
```

---

## ✅ Checklist Final

- [ ] PHPMailer instalado (`composer require phpmailer/phpmailer`)
- [ ] Credenciais Gmail em `config.php`
- [ ] 2FA ativado no Gmail
- [ ] App Password gerada (16 caracteres)
- [ ] Testado envio de email
- [ ] (Opcional) Twilio configurado para SMS
- [ ] (Opcional) Composer instalou Twilio SDK

---

**Dúvidas?** Verifique os logs:
```bash
tail -f storage/logs/error.log
```

Boa sorte! 🍀
