# 🌿 Segredo Lusitano — Guia de Instalação

## Requisitos
- PHP 8.0+
- MySQL 8.0+ / MariaDB 10.4+
- XAMPP ou WAMP Server
- Navegador moderno

---

## 1. Instalar o XAMPP/WAMP
Descarrega e instala o [XAMPP](https://www.apachefriends.org) ou [WAMP](https://www.wampserver.com).

## 2. Copiar o projeto
Copia a pasta `segredo_lusitano` para:
- **XAMPP:** `C:/xampp/htdocs/`
- **WAMP:**  `C:/wamp64/www/`

## 3. Criar a base de dados
1. Inicia o Apache e MySQL no painel do XAMPP/WAMP
2. Abre o **phpMyAdmin** em http://localhost/phpmyadmin
3. Cria uma nova base de dados chamada `segredo_lusitano`
4. Importa o ficheiro `database.sql`

## 4. Configurar a ligação
Edita `includes/config.php` se necessário:

```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');       // o teu utilizador MySQL
define('DB_PASS', '');            // a tua password (em branco no XAMPP por defeito)
define('DB_NAME', 'segredo_lusitano');
define('SITE_URL', 'http://localhost/segredo_lusitano');
```

## 5. Permissões da pasta uploads
A pasta `uploads/locais/` precisa de ter permissão de escrita.
- No XAMPP: normalmente funciona automaticamente.

## 6. Aceder ao site
Abre o navegador em: **http://localhost/segredo_lusitano**

---

## Contas de demonstração

| Role  | Email                     | Password |
|-------|---------------------------|----------|
| Admin | admin@segredolusitano.pt  | admin123 |
| User  | joao@exemplo.pt           | demo123  |

---

## Estrutura de Ficheiros

```
segredo_lusitano/
├── index.php               — Página inicial
├── database.sql            — Script da base de dados
├── includes/
│   ├── config.php          — Configurações e ligação DB
│   ├── auth.php            — Autenticação e sessões
│   ├── functions.php       — Funções auxiliares
│   ├── header.php          — Cabeçalho global
│   ├── footer.php          — Rodapé global
│   └── card_local.php      — Template de card de local
├── pages/
│   ├── explorar.php        — Listagem com filtros e paginação
│   ├── mapa.php            — Mapa interativo (Leaflet)
│   ├── local.php           — Detalhe do local
│   ├── local_novo.php      — Registar novo local
│   ├── local_editar.php    — Editar local
│   ├── local_apagar.php    — Apagar local
│   ├── like.php            — AJAX endpoint para likes
│   ├── login.php           — Login
│   ├── registo.php         — Registo
│   ├── logout.php          — Logout
│   ├── perfil.php          — Perfil do utilizador
│   └── ranking.php         — Ranking de exploradores
├── admin/
│   ├── index.php           — Dashboard de administração
│   ├── locais.php          — Gestão de locais
│   └── utilizadores.php    — Gestão de utilizadores
├── assets/
│   ├── css/style.css       — Folha de estilos principal
│   └── js/main.js          — JavaScript principal
└── uploads/
    └── locais/             — Fotos enviadas pelos utilizadores
```

---

## Tecnologias Utilizadas
- **Frontend:** HTML5, CSS3, JavaScript (vanilla)
- **Backend:** PHP 8 (PDO)
- **Base de Dados:** MySQL / MariaDB
- **Mapa:** Leaflet.js com tiles CartoDB Voyager (estilo metro)
- **Ícones:** Font Awesome 6
- **Fontes:** Google Fonts (Playfair Display + Outfit)

---

## Funcionalidades Implementadas
- ✅ Registo e autenticação de utilizadores
- ✅ Login / Logout
- ✅ Apagar conta
- ✅ Publicação de locais com fotos
- ✅ Upload de fotos adicionais
- ✅ Exploração com pesquisa e filtros (região, categoria, dificuldade, ordem)
- ✅ Paginação de resultados
- ✅ Mapa interativo com marcadores personalizados
- ✅ Sistema de likes (AJAX)
- ✅ Sistema de comentários
- ✅ Ranking de utilizadores com pontuação
- ✅ Perfil de utilizador com histórico
- ✅ Moderação de conteúdo (aprovação/rejeição de locais)
- ✅ Sistema de denúncias
- ✅ Painel de administração completo
- ✅ Design responsivo (mobile/tablet/desktop)
- ✅ HTML mínimo nos ficheiros .php (lógica toda no PHP)

---

*Projeto PAP 2025/2026 — Gonçalo Teixeira — 12ºJ*
*Escola Secundária Fernando Lopes Graça*
