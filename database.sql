-- SEGREDO LUSITANO - Base de Dados
CREATE DATABASE IF NOT EXISTS segredo_lusitano CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE segredo_lusitano;

-- Limpar tabelas existentes (ordem inversa por causa das FK)
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS banidos;
DROP TABLE IF EXISTS denuncias;
DROP TABLE IF EXISTS comentarios;
DROP TABLE IF EXISTS likes;
DROP TABLE IF EXISTS fotos;
DROP TABLE IF EXISTS locais;
DROP TABLE IF EXISTS regioes;
DROP TABLE IF EXISTS categorias;
DROP TABLE IF EXISTS utilizadores;
DROP TABLE IF EXISTS seguidores;
DROP TABLE IF EXISTS mensagens;
DROP TABLE IF EXISTS codigos_verificacao;
SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- TABELA: utilizadores
-- Guarda todos os utilizadores registados na plataforma
-- ============================================================
CREATE TABLE utilizadores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    avatar VARCHAR(255) DEFAULT NULL,
    bio TEXT DEFAULT NULL,
    pontos INT DEFAULT 0,
    role ENUM('user','admin', '[deleted]') DEFAULT 'user',
    ativo TINYINT(1) DEFAULT 1,
    verificado TINYINT(1) DEFAULT 0,
    privado TINYINT(1) DEFAULT 0,
    tipo_auth ENUM('email','google','github') DEFAULT 'email',
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- TABELA: banidos
-- Guarda o registo de utilizadores banidos pelo administrador.
-- Quando um utilizador é banido, a sua conta é eliminada da
-- tabela utilizadores mas os seus dados ficam aqui guardados.
-- O email é usado para impedir que o utilizador se registe novamente.
-- ============================================================
CREATE TABLE banidos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    username VARCHAR(50) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    motivo ENUM('spam','comportamento_abusivo','conteudo_inapropriado','fraude','outro') NOT NULL,
    banido_em DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- TABELA: seguidores
-- Regista as relações de seguimento entre utilizadores
-- ============================================================
CREATE TABLE seguidores (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    seguidor_id  INT NOT NULL,
    seguido_id   INT NOT NULL,
    criado_em    DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_seguidor (seguidor_id, seguido_id),
    FOREIGN KEY (seguidor_id) REFERENCES utilizadores(id) ON DELETE CASCADE,
    FOREIGN KEY (seguido_id)  REFERENCES utilizadores(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- TABELA: categorias
-- Categorias disponíveis para os locais
-- ============================================================
CREATE TABLE categorias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(80) NOT NULL,
    icone VARCHAR(50) DEFAULT 'fas fa-map-pin'
) ENGINE=InnoDB;

INSERT INTO categorias (nome, icone) VALUES
  ('Cascata','fas fa-water'),
  ('Miradouro','fas fa-mountain'),
  ('Aldeia','fas fa-home'),
  ('Ruínas','fas fa-landmark'),
  ('Praia Secreta','fas fa-umbrella-beach'),
  ('Floresta','fas fa-tree'),
  ('Gruta','fas fa-dungeon'),
  ('Monumento','fas fa-chess-rook');

-- ============================================================
-- TABELA: regioes
-- Regiões de Portugal disponíveis para os locais
-- ============================================================
CREATE TABLE regioes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(80) NOT NULL
) ENGINE=InnoDB;

INSERT INTO regioes (nome) VALUES
  ('Norte'),('Centro'),('Lisboa e Vale do Tejo'),('Alentejo'),('Algarve'),('Açores'),('Madeira');

-- ============================================================
-- TABELA: locais
-- Locais secretos partilhados pelos utilizadores
-- ===========================================================
CREATE TABLE locais (
    id INT AUTO_INCREMENT PRIMARY KEY,
    utilizador_id INT NOT NULL,
    categoria_id INT NOT NULL,
    regiao_id INT NOT NULL,
    nome VARCHAR(150) NOT NULL,
    descricao TEXT NOT NULL,
    latitude DECIMAL(10,7) NOT NULL,
    longitude DECIMAL(10,7) NOT NULL,
    dificuldade ENUM('facil','medio','dificil') DEFAULT 'medio',
    foto_capa VARCHAR(255) DEFAULT NULL,
    estado ENUM('pendente','aprovado','rejeitado') DEFAULT 'aprovado',
    bloqueado TINYINT(1) DEFAULT 0,
    vistas INT DEFAULT 0,
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (utilizador_id) REFERENCES utilizadores(id) ON DELETE CASCADE,
    FOREIGN KEY (categoria_id)  REFERENCES categorias(id) ON DELETE RESTRICT,
    FOREIGN KEY (regiao_id)     REFERENCES regioes(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ============================================================
-- TABELA: fotos
-- Fotos adicionadas pelos utilizadores aos locais
-- ============================================================
CREATE TABLE fotos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    local_id INT NOT NULL,
    utilizador_id INT NOT NULL,
    ficheiro VARCHAR(255) NOT NULL,
    denunciada TINYINT(1) DEFAULT 0,
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (local_id)        REFERENCES locais(id) ON DELETE CASCADE,
    FOREIGN KEY (utilizador_id)   REFERENCES utilizadores(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- TABELA: likes
-- Regista os likes dados pelos utilizadores aos locais
-- ============================================================
CREATE TABLE likes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    local_id INT NOT NULL,
    utilizador_id INT NOT NULL,
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_like (local_id, utilizador_id),
    FOREIGN KEY (local_id)       REFERENCES locais(id) ON DELETE CASCADE,
    FOREIGN KEY (utilizador_id)  REFERENCES utilizadores(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- TABELA: comentarios
-- Comentários deixados pelos utilizadores nos locais
-- ============================================================
CREATE TABLE comentarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    local_id INT NOT NULL,
    utilizador_id INT NOT NULL,
    texto TEXT NOT NULL,
    denunciado TINYINT(1) DEFAULT 0,
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (local_id)       REFERENCES locais(id) ON DELETE CASCADE,
    FOREIGN KEY (utilizador_id)  REFERENCES utilizadores(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- TABELA: denuncias
-- Denúncias feitas pelos utilizadores sobre locais, comentários ou fotos
-- ============================================================
CREATE TABLE denuncias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tipo ENUM('foto','local','comentario') NOT NULL,
    referencia_id INT NOT NULL,
    utilizador_id INT NOT NULL,
    motivo TEXT,
    resolvida TINYINT(1) DEFAULT 0,
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
    KEY idx_denuncias_abertas (resolvida, tipo, referencia_id),
    FOREIGN KEY (utilizador_id) REFERENCES utilizadores(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- TABELA: mensagens
-- Mensagens privadas entre utilizadores que se seguem mutuamente
-- ============================================================
CREATE TABLE mensagens (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    remetente_id    INT NOT NULL,
    destinatario_id INT NOT NULL,
    texto           TEXT NOT NULL,
    lida            TINYINT(1) DEFAULT 0,
    criado_em       DATETIME DEFAULT CURRENT_TIMESTAMP,
    KEY idx_conversa (remetente_id, destinatario_id),
    KEY idx_nao_lidas (destinatario_id, lida),
    FOREIGN KEY (remetente_id)    REFERENCES utilizadores(id) ON DELETE CASCADE,
    FOREIGN KEY (destinatario_id) REFERENCES utilizadores(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- TABELA: codigos_verificacao
-- Códigos de 6 dígitos enviados por email para verificar contas
-- ============================================================
CREATE TABLE codigos_verificacao (
    id INT AUTO_INCREMENT PRIMARY KEY,
    utilizador_id INT NOT NULL,
    codigo VARCHAR(6) NOT NULL,
    tipo ENUM('registo','login') DEFAULT 'registo',
    expira_em DATETIME NOT NULL,
    usado TINYINT(1) DEFAULT 0,
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (utilizador_id) REFERENCES utilizadores(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- DADOS INICIAIS
-- ============================================================

-- Utilizador fantasma (ID: 1) — usado para conteúdo de contas eliminadas
INSERT INTO utilizadores (id, nome, username, email, password, pontos, role, ativo, verificado) VALUES
  (1, '[deleted]', '[deleted]', '[deleted]', '¯\_(ツ)_/¯', NULL, '[deleted]', 1, 1);

-- Conta do Administrador (ID: 2)
INSERT INTO utilizadores (nome, username, email, password, pontos, role, verificado) VALUES
  ('Administrador', 'admin', 'admin@segredolusitano.pt', '$2y$12$HAvXHYiIvDXx0JOX9.ghfeimbCy9fHUHU7ZW78RDW31Z.lUeEd1sW', 9999, 'admin', 1);

-- ============================================================
-- LOCAIS DE EXEMPLO — 15 locais reais de Portugal Continental
-- (substitui os 3 locais de exemplo anteriores)
-- Autor de todos: Administrador (utilizador_id = 2)
-- ============================================================

-- NORTE
INSERT INTO locais (utilizador_id, categoria_id, regiao_id, nome, descricao, latitude, longitude, dificuldade, foto_capa, estado, vistas, criado_em) VALUES
  (2, 1, 1, 'Cascata da Fervença', 'Escondida na Serra d''Arga, a Cascata da Fervença é uma das quedas de água mais impressionantes do Minho. As águas frias formam piscinas naturais ideais para um mergulho refrescante no verão, num cenário rodeado de vegetação luxuriante.', 41.8500000, -8.6833000, 'medio', 'cascata_fervenca.jpeg', 'aprovado', 87, NOW() - INTERVAL 45 DAY),
  (2, 3, 1, 'Rio de Onor', 'Aldeia única no concelho de Bragança, partilhada entre Portugal e Espanha, onde a fronteira passa literalmente no meio da povoação. Conhecida pelo seu sistema comunitário ancestral e pelas casas tradicionais em xisto, é um verdadeiro tesouro vivo de Trás-os-Montes.', 41.9408000, -6.6072000, 'facil', 'rio_de_onor.jpeg', 'aprovado', 142, NOW() - INTERVAL 38 DAY),
  (2, 2, 1, 'Miradouro de Casal de São Simão', 'Aldeia de xisto poiso sobre uma encosta com vista deslumbrante sobre o vale do Alge. O miradouro oferece um dos mais belos panoramas do interior de Portugal, especialmente ao pôr do sol quando as pedras de xisto ganham tons dourados.', 39.9667000, -8.3833000, 'facil', 'miradouro_casal_sao_simao.jpeg', 'aprovado', 56, NOW() - INTERVAL 30 DAY),
  (2, 1, 5, 'Pego do Inferno', 'Apesar do nome assustador, o Pego do Inferno é um paraíso escondido em Tavira. Uma cascata cristalina cai numa lagoa de águas turquesa, rodeada por vegetação densa. Local mágico para escapar ao calor do Algarve.', 37.1500000, -7.6500000, 'medio', 'pego_do_inferno.jpeg', 'aprovado', 215, NOW() - INTERVAL 55 DAY),

-- CENTRO
  (2, 5, 2, 'Praia Fluvial do Penedo Furado', 'Uma das praias fluviais mais bonitas de Portugal, no concelho de Vila de Rei. As águas calmas do rio Zêzere e o pequeno tobogã natural na rocha tornam este local perfeito para um dia em família. Atravessa uma ponte suspensa para chegar à praia.', 39.6833000, -8.1500000, 'facil', 'penedo_furado.jpeg', 'aprovado', 198, NOW() - INTERVAL 42 DAY),
  (2, 3, 2, 'Aldeia Histórica de Piódão', 'Considerada uma das aldeias mais bonitas de Portugal, Piódão é conhecida pelas suas casas em xisto com portas e janelas pintadas de azul, dispostas em forma de presépio na encosta da serra. Faz parte das Aldeias Históricas de Portugal.', 40.2333000, -7.8167000, 'medio', 'piodao.jpeg', 'aprovado', 312, NOW() - INTERVAL 60 DAY),
  (2, 7, 2, 'Grutas de Mira de Aire', 'As maiores grutas turísticas de Portugal, descobertas em 1947 no concelho de Porto de Mós. Com 11 km de extensão (apenas 600m abertos ao público), oferecem um espetáculo natural de estalactites, estalagmites e lagos subterrâneos iluminados.', 39.5333000, -8.7333000, 'facil', 'grutas_mira_aire.jpeg', 'aprovado', 267, NOW() - INTERVAL 25 DAY),
  (2, 6, 2, 'Mata Nacional do Buçaco', 'Floresta sagrada onde os monges Carmelitas Descalços plantaram espécies vindas de todo o mundo no século XVII. O Buçaco esconde fontes, capelas, ermidas e o famoso Palace Hotel num cenário de conto de fadas, rodeado de árvores centenárias.', 40.3833000, -8.3667000, 'facil', 'mata_bucaco.jpeg', 'aprovado', 178, NOW() - INTERVAL 20 DAY),

-- LISBOA E VALE DO TEJO
  (2, 8, 3, 'Convento dos Capuchos', 'Convento franciscano do século XVI escondido na Serra de Sintra, conhecido pela extrema pobreza e simplicidade das suas celas, algumas revestidas a cortiça. Considerado um dos lugares mais místicos e introspetivos de Portugal.', 38.7833000, -9.4500000, 'facil', 'convento_capuchos.jpeg', 'aprovado', 145, NOW() - INTERVAL 35 DAY),
  (2, 5, 3, 'Praia da Adraga', 'Praia selvagem rodeada de imponentes falésias na costa de Sintra. As suas grutas, arcos naturais e areia dourada fazem dela uma das praias mais cinematográficas de Portugal. Ao pôr do sol, o espetáculo é inesquecível.', 38.8000000, -9.4833000, 'medio', 'praia_adraga.jpeg', 'aprovado', 234, NOW() - INTERVAL 50 DAY),
  (2, 2, 3, 'Cabo Espichel', 'Promontório dramático no extremo sul da Península de Setúbal, com falésias abruptas a cair sobre o oceano. O Santuário de Nossa Senhora do Cabo, o farol e a vista infinita sobre o Atlântico fazem deste um dos miradouros mais espetaculares do país.', 38.4167000, -9.2167000, 'facil', 'cabo_espichel.jpeg', 'aprovado', 189, NOW() - INTERVAL 28 DAY),

-- ALENTEJO
  (2, 3, 4, 'Aldeia de Monsaraz', 'Vila medieval murada no topo de uma colina com vista panorâmica sobre o Alqueva, o maior lago artificial da Europa. As ruas calcetadas, as casas brancas e o castelo do século XIII transportam o visitante de volta à Idade Média.', 38.4417000, -7.3789000, 'facil', 'monsaraz.png', 'aprovado', 287, NOW() - INTERVAL 65 DAY),
  (2, 4, 4, 'Ruínas de Miróbriga', 'Antiga cidade romana no concelho de Santiago do Cacém, com mais de 2000 anos de história. Conserva o fórum, o hipódromo (único na Península Ibérica) e as termas, oferecendo uma viagem fascinante pela vida quotidiana do Império Romano.', 38.0167000, -8.6833000, 'facil', 'mirobriga.jpeg', 'aprovado', 96, NOW() - INTERVAL 18 DAY),

-- ALGARVE
  (2, 5, 5, 'Praia da Marinha', 'Considerada uma das 10 praias mais bonitas do mundo pela revista Condé Nast Traveler. As formações rochosas em forma de M, as águas turquesa e as falésias douradas fazem desta a imagem postal mais icónica do Algarve.', 37.0900000, -8.4150000, 'medio', 'praia_marinha.jpeg', 'aprovado', 421, NOW() - INTERVAL 70 DAY),
  (2, 8, 5, 'Forte de São João do Arade', 'Fortaleza do século XVII na foz do rio Arade, em Ferragudo. Construída para defender Portimão dos ataques de piratas, é hoje propriedade privada e funciona como cenário emblemático sobre as águas. Vista incrível ao pôr do sol vista da praia da Angrinha.', 37.1233000, -8.5275000, 'facil', 'forte_sao_joao_arade.jpeg', 'aprovado', 134, NOW() - INTERVAL 12 DAY);