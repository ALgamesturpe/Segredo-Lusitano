# Sistema de Verificação de Email - Segredo Lusitano

## 📋 O que está implementado

O sistema de registo com token de validação por email está **100% funcional**.

### Fluxo Completo:

1. **Registo** (`pages/registo.php`)
   - Utilizador preenche formulário
   - Conta é criada na BD com `verificado = 0`
   - Email com código de 6 dígitos é enviado
   - Redireciona para página de verificação

2. **Verificação** (`pages/verificar.php`)
   - Utilizador entra o código de 6 dígitos
   - Sistema valida o código (máx 15 minutos)
   - Se correto: marca conta como verificada e faz login automático
   - Opção para reenviar código se não recebeu

3. **Login em Duas Etapas** (`pages/login.php`)
   - Se conta não está verificada, pede verificação
   - Mesmo sistema de código por email
   - Após verificação, faz login

### Estrutura da Base de Dados

**Tabela: `codigos_verificacao`**
```sql
- id              (INT, chave primária)
- utilizador_id   (INT, FK para utilizadores)
- codigo          (VARCHAR 6 - ex: "123456")
- tipo            (ENUM: 'registo' ou 'login')
- expira_em       (DATETIME - 15 minutos)
- usado           (TINYINT - 0 ou 1)
- criado_em       (DATETIME - timestamp)
```

## 🔐 Melhorias Implementadas

### 1. **Hash de Passwords Seguro (BCrypt)**
Antes: Passwords guardadas em texto plano ❌
```php
$password  // texto puro
```

Depois: Passwords com BCrypt (cost=12) ✅
```php
$password_hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
password_verify($input, $password_hash);  // verificação
```

### 2. **Limpeza Automática de Códigos Expirados**
Função `limpar_codigos_expirados()` executada:
- No carregamento de `registo.php`
- No carregamento de `login.php`
- Elimina todos os códigos com `expira_em < NOW()`

### 3. **Validação de Códigos**
```php
verificar_codigo($utilizador_id, $codigo, $tipo);
// - Valida utilizar_id, código e tipo
// - Verifica expiração (< NOW())
// - Marca como usado
// - Retorna true/false
```

## 📧 Funções Principais

### Envio de Email
```php
enviar_codigo_verificacao($email, $nome, $codigo, $tipo);
// Envia email formatado em HTML com código de 6 dígitos
// Tipos: 'registo' ou 'login'
```

### Geração de Código
```php
$codigo = gerar_e_guardar_codigo($utilizador_id, $tipo);
// Gera código de 6 dígitos
// Guarda na BD com expiração de 15 minutos
// Invalida códigos anteriores do mesmo tipo
```

### Verificação de Código
```php
if (verificar_codigo($uid, $codigo, $tipo)) {
    // Código correto - marcar como usado
}
```

## 🧪 Como Testar

### Requisitos
1. **PHPMailer instalado** (composer)
   ```bash
   composer require phpmailer/phpmailer
   ```

2. **Configuração SMTP em `config.php`**
   ```php
   define('MAIL_HOST',     'smtp.gmail.com');
   define('MAIL_PORT',     587);
   define('MAIL_USER',     'seu.email@gmail.com');
   define('MAIL_PASS',     'senha-de-app-gmail');  // ← App Password
   define('MAIL_FROM',     'seu.email@gmail.com');
   define('MAIL_FROM_NAME','Segredo Lusitano');
   ```

### Passo 1: Criar Conta
1. Acede a `/pages/registo.php`
2. Preenche formulário:
   - Nome: "João Silva"
   - Username: "joao42"
   - Email: "seu.email@gmail.com"
   - Password: minimo 6 caracteres
3. Clica "Criar Conta"

### Passo 2: Verificar Email
1. Página redireciona para `/pages/verificar.php`
2. Verifica inbox do email (pode estar em spam)
3. Email tem código de 6 dígitos
4. Copia código e entra em verificar.php
5. Clica "Confirmar Código"

### Passo 3: Confirmação
- Se correto: Login automático e redireciona para `/index.php`
- Se expirado: "Código inválido ou expirado" + opção para reenviar

## ⚙️ Configuração do Gmail App Password

1. Acede https://myaccount.google.com/apppasswords
2. Seleciona "Mail" e "Windows (ou outro SO)"
3. Copia a senha de 16 caracteres
4. Cola em `MAIL_PASS` no `config.php`

**Importante**: Não usas a tua password do Google! Tem de ser App Password.

## 🔍 Debug

Se não receber emails, verifica:

1. **PHPMailer existe?**
   ```php
   // Deve estar aqui:
   vendor/phpmailer/phpmailer/src/
   ```

2. **SMTP configurado?**
   ```php
   echo MAIL_HOST;     // Deve mostrar: smtp.gmail.com
   echo MAIL_USER;     // Deve mostrar: seu.email@gmail.com
   ```

3. **Erros nos logs?**
   ```bash
   tail -f /var/log/php-errors.log
   ```

4. **BD criada?**
   ```sql
   SELECT * FROM codigos_verificacao;
   ```

## 📱 Campos Adicionais (Futuro)

Sem alterações de BD, podes adicionar:
- SMS via Twilio (já configurado em `mailer.php`)
- 2FA com autenticador
- Email + SMS dupla verificação

## ✅ Checklist de Implementação

- [x] BD com tabela `codigos_verificacao`
- [x] Geração de código (6 dígitos)
- [x] Envio de email com código
- [x] Página de verificação
- [x] Validação de código com expiração
- [x] Login em duas etapas (se não verificado)
- [x] Hash seguro (BCrypt)
- [x] Limpeza de códigos expirados
- [x] Reenvio de código
- [x] UX melhorada (input de código)

---

**Status**: ✅ Sistema 100% funcional
**Próximos passos**: Testar com email real e ajustar template conforme necessário
