# 📧 CONFIGURAR EMAIL PARA REGISTO

## ✅ Resumo do que está pronto:

- ✓ Quando um utilizador se registar → recebe email com código
- ✓ Quando confirma o código → conta é verificada
- ✓ Código válido por 15 minutos
- ✓ Interface de verificação já existe

**O que falta:** Apenas preencher as credenciais do Gmail no arquivo de configuração.

---

## 🎯 PASSO A PASSO

### **PASSO 1: Ativar PHPMailer** (caso ainda não tenha)

Na pasta do projeto (`c:\xampp\htdocs\Segredo-Lusitano`), abra **PowerShell** e execute:

```powershell
composer require phpmailer/phpmailer
```

Se aparecer erro, certifique-se que:
- Tem **Composer** instalado ([descarregar aqui](https://getcomposer.org))
- Está na pasta correta do projeto
- Tem conexão à Internet

**Resultado esperado:** Criar pasta `vendor/` com as bibliotecas

---

### **PASSO 2: Ativar 2FA no Gmail** ⚙️

1. Aceda a: **[myaccount.google.com](https://myaccount.google.com)**
2. Clique em **Segurança** (menu lateral)
3. Procure "**Verificação em duas etapas**"
4. Clique em **Ativar** (ou **Configurar**)
5. Siga as instruções (pode usar SMS ou app autenticador)
6. Depois de completar, volte à página de Segurança

---

### **PASSO 3: Gerar App Password** 🔐

Depois de 2FA ativo, continue em Segurança:

1. Em **myaccount.google.com/security**, procure **"Senhas de Apps"**
   - Se não vê, significa que 2FA ainda não está 100% ativo
2. Clique em **Senhas de Apps**
3. Selecione:
   - **App:** `Mail` (via dropdown)
   - **Dispositivo:** `Windows Computer` (via dropdown)
4. Clique em **Gerar**

**Verá:**
```
abcd efgh ijkl mnop
```

Este é o código que vai usar no config.php. **Copie e guarde!**

---

### **PASSO 4: Atualizar `config.php`**

Abra o arquivo: `includes/config.php`

Procure esta secção (aproximadamente linha 35):

```php
// ============================================================
// CONFIGURAÇÃO DE EMAIL (SMTP)
// ============================================================
define('MAIL_HOST',     'smtp.gmail.com');
define('MAIL_PORT',     587);
define('MAIL_USER',     'o.teu@gmail.com');      // ← ALTERAR
define('MAIL_PASS',     'a_tua_password');       // ← ALTERAR
define('MAIL_FROM',     'o.teu@gmail.com');      // ← ALTERAR
define('MAIL_FROM_NAME','Segredo Lusitano');
```

**Altere para:**

```php
define('MAIL_HOST',     'smtp.gmail.com');
define('MAIL_PORT',     587);
define('MAIL_USER',     'seu.email@gmail.com');           // SEU EMAIL GMAIL
define('MAIL_PASS',     'abcdefghijklmnop');              // APP PASSWORD (sem espaços)
define('MAIL_FROM',     'seu.email@gmail.com');           // MESMO EMAIL
define('MAIL_FROM_NAME','Segredo Lusitano');
```

### Exemplo Real:

Se o seu email é `joao.silva@gmail.com` e a App Password é `qwer tyui asdf ghjk`:

```php
define('MAIL_USER',     'joao.silva@gmail.com');
define('MAIL_PASS',     'qwertyuiasdfghjk');         // ⚠️ SEM ESPAÇOS
define('MAIL_FROM',     'joao.silva@gmail.com');
```

**⚠️ Importante:** 
- A password tem 16 caracteres
- **Remova os espaços** (se tiver)
- Não use `'` dentro da password

---

## 🧪 TESTAR A CONFIGURAÇÃO

### Opção A: Página de Teste (Recomendado)

Aceda a: **http://localhost/Segredo-Lusitano/test_mailer.php**

1. Clique em "📧 Enviar Email de Teste"
2. Insira um email para receber o teste
3. Clique em "Enviar"
4. Verifique a caixa de entrada ✅

---

### Opção B: Testar no Próprio Registo

1. Aceda a: **http://localhost/Segredo-Lusitano/pages/registo.php**
2. Preencha o formulário:
   - Nome: `João Silva`
   - Username: `joao123`
   - Email: **seu email real**
   - Password: `senha123`
3. Clique em "**Criar Conta**"
4. Se configurado corretamente → vai ser redirecionado para página de verificação
5. Verifique o email que recebeu

---

## 📊 Fluxo do Registo com Email

```
┌─────────────────────────────────┐
│ Utilizador preenche formulário  │
│ registo.php                     │
└────────────┬────────────────────┘
             │
       ┌─────▼──────────┐
       │ Validar dados  │
       └─────┬──────────┘
             │
       ┌─────▼─────────┐
       │ Guardar na BD │
       └─────┬─────────┘
             │
       ┌─────▼──────────────────┐
       │ Gerar código 6 dígitos │
       │ (válido 15 minutos)    │
       └─────┬──────────────────┘
             │
       ┌─────▼──────────────────────┐
       │ Chamar enviar_codigo_...() │
       │ (mailer.php)               │
       └─────┬──────────────────────┘
             │
       ┌─────▼──────────────────┐
       │ PHPMailer conecta a    │
       │ smtp.gmail.com:587     │
       │ Com suas credenciais   │
       └─────┬──────────────────┘
             │
       ┌─────▼──────────────────┐
       │ Email é ENVIADO ✅     │
       │ Com o código           │
       └─────┬──────────────────┘
             │
       ┌─────▼────────────────────────┐
       │ Redireciona para verificar.php
       │ (Página para inserir código)  │
       └──────────────────────────────┘
             │
       ┌─────▼──────────────────┐
       │ Utilizador recebe      │
       │ email + copia código   │
       └─────┬──────────────────┘
             │
       ┌─────▼────────────────┐
       │ Insere código        │
       │ e clica "Verificar"  │
       └─────┬────────────────┘
             │
       ┌─────▼─────────────────┐
       │ Código é validado     │
       │ na base de dados      │
       └─────┬───────────────┐
             │               │
    Válido   │               │ Inválido/Expirado
             │               │
        ┌────▼──────┐   ┌────▼──────┐
        │ Marcar    │   │ Mostrar   │
        │ verificado│   │ erro      │
        │ na BD     │   │ tentar    │
        └────┬──────┘   │ novamente │
             │          └───────────┘
        ┌────▼──────────┐
        │ SUCESSO! ✅   │
        │ Redirecionar  │
        │ para página   │
        │ principal     │
        └───────────────┘
```

---

## ❌ PROBLEMAS COMUNS

### ❌ Email não é enviado

**Erro:** "SMTP connection failed" ou "Authentication failed"

**Solução:**
1. ✓ Verifique se `MAIL_USER` é o seu email Gmail **correto**
2. ✓ Confirme se a App Password está **sem espaços**
3. ✓ Valide que 2FA está **100% ativo** (verifique em myaccount.google.com)
4. ✓ Tente outra "App Password" (delete a antiga e crie uma nova)
5. ✓ Verifique firewall/antivírus (porta 587)

---

### ❌ "PHPMailer não encontrado"

**Erro:** Na página de registo aparece mensagem sobre PHPMailer

**Solução:**
1. Abra PowerShell na pasta do projeto
2. Execute: `composer require phpmailer/phpmailer`
3. Verifique se criou pasta `vendor/`

---

### ❌ Código não funciona em Produção

Se em localhost funciona mas em produção não:
1. Verifique se credenciais Gmail estão corretas no servidor
2. Confirme se porta 587 **não está bloqueada** (contacte hosting)
3. Valide App Password novamente
4. Veja logs do servidor (`error_log`)

---

## 🔐 SEGURANÇA

⚠️ **Importante:**

1. **Nunca guarde `config.php` no Git!** Adicione ao `.gitignore`:
   ```
   config.php
   vendor/
   .env
   ```

2. **Em produção**, use **variáveis de ambiente** em vez de hardcode:
   ```php
   define('MAIL_USER', getenv('MAIL_USER') ?: '');
   define('MAIL_PASS', getenv('MAIL_PASS') ?: '');
   ```

3. **Delete o arquivo `test_mailer.php`** em produção

---

## ✅ CHECKLIST FINAL

- [ ] Composer instalado
- [ ] PHPMailer instalado: `composer require phpmailer/phpmailer`
- [ ] 2FA ativado no Gmail
- [ ] App Password gerada (16 caracteres)
- [ ] `MAIL_USER` preenchido com o email Gmail
- [ ] `MAIL_PASS` preenchido com a App Password (**sem espaços**)
- [ ] `MAIL_FROM` preenchido com o email Gmail
- [ ] Testado em: http://localhost/Segredo-Lusitano/test_mailer.php ✅
- [ ] Testado no formulário de registo ✅
- [ ] Recebeu email com código de verificação ✅

---

## 📞 SUPORTE RÁPIDO

| Problema | Check |
|----------|-------|
| Não recebe email | Verifique pasta SPAM / Email correto em config.php |
| "Authentication failed" | App Password com espaços? Ou credenciais erradas? |
| "Connection timeout" | Firewall bloqueia porta 587? |
| PHPMailer não encontrado | Rodou `composer require phpmailer/phpmailer`? |

---

## 🎓 O que Acontece nos Bastidores

1. **Registo:** `pages/registo.php` chama `register($nome, $username, $email, $password)`
2. **Gera Código:** `gerar_e_guardar_codigo()` cria 6 dígitos na tabela `codigos_verificacao`
3. **Envia Email:** `enviar_codigo_verificacao()` usa PHPMailer para enviar
4. **Verificação:** `pages/verificar.php` valida o código inserido
5. **Confirma:** Marca `utilizadores.verificado = 1` e inicia sessão

---

**Pronto? Comece pelo Passo 1!** 🚀

Dúvidas? Veja o arquivo `test_mailer.php` para diagnosticar problemas.
