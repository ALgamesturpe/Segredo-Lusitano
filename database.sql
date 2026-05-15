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
-- DADOS DE EXEMPLO — Utilizadores, Locais, Likes, Comentários
-- (substitui o bloco "Locais de exemplo" no final do database.sql)
-- ============================================================

-- ============================================================
-- UTILIZADORES FICTÍCIOS (IDs 3 a 8)
-- Password de todos: "exemplo123" (hash bcrypt)
-- ============================================================
INSERT INTO utilizadores (id, nome, username, email, password, bio, pontos, role, ativo, verificado, criado_em) VALUES
  (3, 'Ana Ribeiro',     'ana_aventureira',  'ana@exemplo.pt',     '$2y$12$8K4ZmJP1qXhRfYvT3.uW6e9LkW8aD/Hn7zVc1bJqGfMxNp2sR3oYS', 'Apaixonada por trilhos no norte de Portugal.',                145, 'user', 1, 1, NOW() - INTERVAL 90 DAY),
  (4, 'Tiago Marques',   'tiago_explorer',   'tiago@exemplo.pt',   '$2y$12$8K4ZmJP1qXhRfYvT3.uW6e9LkW8aD/Hn7zVc1bJqGfMxNp2sR3oYS', 'Fotógrafo amador à procura dos cantos escondidos do país.',   210, 'user', 1, 1, NOW() - INTERVAL 80 DAY),
  (5, 'Mariana Silva',   'mariana_viaja',    'mariana@exemplo.pt', '$2y$12$8K4ZmJP1qXhRfYvT3.uW6e9LkW8aD/Hn7zVc1bJqGfMxNp2sR3oYS', 'Adoro praias secretas e pôr-do-sol no Algarve.',              180, 'user', 1, 1, NOW() - INTERVAL 75 DAY),
  (6, 'Pedro Costa',     'pedro_trilhos',    'pedro@exemplo.pt',   '$2y$12$8K4ZmJP1qXhRfYvT3.uW6e9LkW8aD/Hn7zVc1bJqGfMxNp2sR3oYS', 'Caminheiro e amante de aldeias históricas.',                   95, 'user', 1, 1, NOW() - INTERVAL 60 DAY),
  (7, 'Sofia Mendes',    'sofia_natureza',   'sofia@exemplo.pt',   '$2y$12$8K4ZmJP1qXhRfYvT3.uW6e9LkW8aD/Hn7zVc1bJqGfMxNp2sR3oYS', 'Bióloga marinha. Sempre à descoberta da costa portuguesa.',   165, 'user', 1, 1, NOW() - INTERVAL 50 DAY),
  (8, 'Rui Almeida',     'rui_descobre',     'rui@exemplo.pt',     '$2y$12$8K4ZmJP1qXhRfYvT3.uW6e9LkW8aD/Hn7zVc1bJqGfMxNp2sR3oYS', 'À procura do Portugal autêntico, longe das massas.',          130, 'user', 1, 1, NOW() - INTERVAL 40 DAY);

-- ============================================================
-- LOCAIS (15) — distribuídos entre os 6 utilizadores
-- Ordem importante para os IDs ficarem corretos para likes/comentários
--   ID 1=Fervença(Ana) | 2=RioOnor(Ana) | 3=CasalSSimão(Pedro) | 4=Piódão(Pedro)
--   ID 5=Pego(Mariana) | 6=Penedo(Tiago) | 7=Mira(Tiago) | 8=Buçaco(Tiago)
--   ID 9=Capuchos(Sofia) | 10=Adraga(Sofia) | 11=Espichel(Sofia)
--   ID 12=Monsaraz(Rui) | 13=Miróbriga(Rui)
--   ID 14=Marinha(Mariana) | 15=Forte(Mariana)
-- ============================================================

INSERT INTO locais (utilizador_id, categoria_id, regiao_id, nome, descricao, latitude, longitude, dificuldade, foto_capa, estado, vistas, criado_em) VALUES
  (3, 1, 1, 'Cascata da Fervença', 'Escondida na Serra d''Arga, a Cascata da Fervença é uma das quedas de água mais impressionantes do Minho. As águas frias formam piscinas naturais ideais para um mergulho refrescante no verão, num cenário rodeado de vegetação luxuriante.', 41.8500000, -8.6833000, 'medio', 'cascata_fervenca.jpeg', 'aprovado', 87, NOW() - INTERVAL 45 DAY),
  (3, 3, 1, 'Rio de Onor', 'Aldeia única no concelho de Bragança, partilhada entre Portugal e Espanha, onde a fronteira passa literalmente no meio da povoação. Conhecida pelo seu sistema comunitário ancestral e pelas casas tradicionais em xisto, é um verdadeiro tesouro vivo de Trás-os-Montes.', 41.9408000, -6.6072000, 'facil', 'rio_de_onor.jpeg', 'aprovado', 142, NOW() - INTERVAL 38 DAY),
  (6, 2, 1, 'Miradouro de Casal de São Simão', 'Aldeia de xisto poiso sobre uma encosta com vista deslumbrante sobre o vale do Alge. O miradouro oferece um dos mais belos panoramas do interior de Portugal, especialmente ao pôr do sol quando as pedras de xisto ganham tons dourados.', 39.9667000, -8.3833000, 'facil', 'miradouro_casal_sao_simao.jpeg', 'aprovado', 56, NOW() - INTERVAL 30 DAY),
  (6, 3, 2, 'Aldeia Histórica de Piódão', 'Considerada uma das aldeias mais bonitas de Portugal, Piódão é conhecida pelas suas casas em xisto com portas e janelas pintadas de azul, dispostas em forma de presépio na encosta da serra. Faz parte das Aldeias Históricas de Portugal.', 40.2333000, -7.8167000, 'medio', 'piodao.jpeg', 'aprovado', 312, NOW() - INTERVAL 60 DAY),
  (5, 1, 5, 'Pego do Inferno', 'Apesar do nome assustador, o Pego do Inferno é um paraíso escondido em Tavira. Uma cascata cristalina cai numa lagoa de águas turquesa, rodeada por vegetação densa. Local mágico para escapar ao calor do Algarve.', 37.1500000, -7.6500000, 'medio', 'pego_do_inferno.jpeg', 'aprovado', 215, NOW() - INTERVAL 55 DAY),
  (4, 5, 2, 'Praia Fluvial do Penedo Furado', 'Uma das praias fluviais mais bonitas de Portugal, no concelho de Vila de Rei. As águas calmas do rio Zêzere e o pequeno tobogã natural na rocha tornam este local perfeito para um dia em família. Atravessa uma ponte suspensa para chegar à praia.', 39.6833000, -8.1500000, 'facil', 'penedo_furado.jpeg', 'aprovado', 198, NOW() - INTERVAL 42 DAY),
  (4, 7, 2, 'Grutas de Mira de Aire', 'As maiores grutas turísticas de Portugal, descobertas em 1947 no concelho de Porto de Mós. Com 11 km de extensão (apenas 600m abertos ao público), oferecem um espetáculo natural de estalactites, estalagmites e lagos subterrâneos iluminados.', 39.5333000, -8.7333000, 'facil', 'grutas_mira_aire.jpeg', 'aprovado', 267, NOW() - INTERVAL 25 DAY),
  (4, 6, 2, 'Mata Nacional do Buçaco', 'Floresta sagrada onde os monges Carmelitas Descalços plantaram espécies vindas de todo o mundo no século XVII. O Buçaco esconde fontes, capelas, ermidas e o famoso Palace Hotel num cenário de conto de fadas, rodeado de árvores centenárias.', 40.3833000, -8.3667000, 'facil', 'mata_bucaco.jpeg', 'aprovado', 178, NOW() - INTERVAL 20 DAY),
  (7, 8, 3, 'Convento dos Capuchos', 'Convento franciscano do século XVI escondido na Serra de Sintra, conhecido pela extrema pobreza e simplicidade das suas celas, algumas revestidas a cortiça. Considerado um dos lugares mais místicos e introspetivos de Portugal.', 38.7833000, -9.4500000, 'facil', 'convento_capuchos.jpeg', 'aprovado', 145, NOW() - INTERVAL 35 DAY),
  (7, 5, 3, 'Praia da Adraga', 'Praia selvagem rodeada de imponentes falésias na costa de Sintra. As suas grutas, arcos naturais e areia dourada fazem dela uma das praias mais cinematográficas de Portugal. Ao pôr do sol, o espetáculo é inesquecível.', 38.8000000, -9.4833000, 'medio', 'praia_adraga.jpeg', 'aprovado', 234, NOW() - INTERVAL 50 DAY),
  (7, 2, 3, 'Cabo Espichel', 'Promontório dramático no extremo sul da Península de Setúbal, com falésias abruptas a cair sobre o oceano. O Santuário de Nossa Senhora do Cabo, o farol e a vista infinita sobre o Atlântico fazem deste um dos miradouros mais espetaculares do país.', 38.4167000, -9.2167000, 'facil', 'cabo_espichel.jpeg', 'aprovado', 189, NOW() - INTERVAL 28 DAY),
  (8, 3, 4, 'Aldeia de Monsaraz', 'Vila medieval murada no topo de uma colina com vista panorâmica sobre o Alqueva, o maior lago artificial da Europa. As ruas calcetadas, as casas brancas e o castelo do século XIII transportam o visitante de volta à Idade Média.', 38.4417000, -7.3789000, 'facil', 'monsaraz.png', 'aprovado', 287, NOW() - INTERVAL 65 DAY),
  (8, 4, 4, 'Ruínas de Miróbriga', 'Antiga cidade romana no concelho de Santiago do Cacém, com mais de 2000 anos de história. Conserva o fórum, o hipódromo (único na Península Ibérica) e as termas, oferecendo uma viagem fascinante pela vida quotidiana do Império Romano.', 38.0167000, -8.6833000, 'facil', 'mirobriga.jpeg', 'aprovado', 96, NOW() - INTERVAL 18 DAY),
  (5, 5, 5, 'Praia da Marinha', 'Considerada uma das 10 praias mais bonitas do mundo pela revista Condé Nast Traveler. As formações rochosas em forma de M, as águas turquesa e as falésias douradas fazem desta a imagem postal mais icónica do Algarve.', 37.0900000, -8.4150000, 'medio', 'praia_marinha.jpeg', 'aprovado', 421, NOW() - INTERVAL 70 DAY),
  (5, 8, 5, 'Forte de São João do Arade', 'Fortaleza do século XVII na foz do rio Arade, em Ferragudo. Construída para defender Portimão dos ataques de piratas, é hoje propriedade privada e funciona como cenário emblemático sobre as águas. Vista incrível ao pôr do sol vista da praia da Angrinha.', 37.1233000, -8.5275000, 'facil', 'forte_sao_joao_arade.jpeg', 'aprovado', 134, NOW() - INTERVAL 12 DAY);

-- ============================================================
-- LIKES — cada par (local_id, utilizador_id) é único
-- ============================================================
INSERT INTO likes (local_id, utilizador_id, criado_em) VALUES
  -- Local 1 - Cascata da Fervença (4 likes)
  (1, 2, NOW() - INTERVAL 40 DAY),
  (1, 4, NOW() - INTERVAL 35 DAY),
  (1, 5, NOW() - INTERVAL 20 DAY),
  (1, 7, NOW() - INTERVAL 10 DAY),

  -- Local 2 - Rio de Onor (5 likes)
  (2, 2, NOW() - INTERVAL 35 DAY),
  (2, 4, NOW() - INTERVAL 32 DAY),
  (2, 5, NOW() - INTERVAL 28 DAY),
  (2, 6, NOW() - INTERVAL 22 DAY),
  (2, 8, NOW() - INTERVAL 15 DAY),

  -- Local 3 - Casal de São Simão (3 likes)
  (3, 2, NOW() - INTERVAL 25 DAY),
  (3, 3, NOW() - INTERVAL 20 DAY),
  (3, 7, NOW() - INTERVAL 12 DAY),

  -- Local 4 - Piódão (6 likes — popular)
  (4, 2, NOW() - INTERVAL 55 DAY),
  (4, 3, NOW() - INTERVAL 50 DAY),
  (4, 4, NOW() - INTERVAL 45 DAY),
  (4, 5, NOW() - INTERVAL 40 DAY),
  (4, 7, NOW() - INTERVAL 35 DAY),
  (4, 8, NOW() - INTERVAL 30 DAY),

  -- Local 5 - Pego do Inferno (6 likes)
  (5, 2, NOW() - INTERVAL 50 DAY),
  (5, 3, NOW() - INTERVAL 45 DAY),
  (5, 4, NOW() - INTERVAL 40 DAY),
  (5, 6, NOW() - INTERVAL 35 DAY),
  (5, 7, NOW() - INTERVAL 28 DAY),
  (5, 8, NOW() - INTERVAL 22 DAY),

  -- Local 6 - Penedo Furado (5 likes)
  (6, 2, NOW() - INTERVAL 38 DAY),
  (6, 3, NOW() - INTERVAL 32 DAY),
  (6, 5, NOW() - INTERVAL 25 DAY),
  (6, 7, NOW() - INTERVAL 18 DAY),
  (6, 8, NOW() - INTERVAL 10 DAY),

  -- Local 7 - Mira de Aire (6 likes)
  (7, 2, NOW() - INTERVAL 22 DAY),
  (7, 3, NOW() - INTERVAL 18 DAY),
  (7, 5, NOW() - INTERVAL 15 DAY),
  (7, 6, NOW() - INTERVAL 12 DAY),
  (7, 7, NOW() - INTERVAL 10 DAY),
  (7, 8, NOW() - INTERVAL 8 DAY),

  -- Local 8 - Buçaco (5 likes)
  (8, 2, NOW() - INTERVAL 18 DAY),
  (8, 3, NOW() - INTERVAL 15 DAY),
  (8, 5, NOW() - INTERVAL 12 DAY),
  (8, 6, NOW() - INTERVAL 8 DAY),
  (8, 8, NOW() - INTERVAL 5 DAY),

  -- Local 9 - Capuchos (5 likes)
  (9, 2, NOW() - INTERVAL 30 DAY),
  (9, 3, NOW() - INTERVAL 25 DAY),
  (9, 4, NOW() - INTERVAL 20 DAY),
  (9, 5, NOW() - INTERVAL 15 DAY),
  (9, 6, NOW() - INTERVAL 10 DAY),

  -- Local 10 - Adraga (6 likes)
  (10, 2, NOW() - INTERVAL 45 DAY),
  (10, 3, NOW() - INTERVAL 40 DAY),
  (10, 4, NOW() - INTERVAL 35 DAY),
  (10, 5, NOW() - INTERVAL 30 DAY),
  (10, 6, NOW() - INTERVAL 22 DAY),
  (10, 8, NOW() - INTERVAL 15 DAY),

  -- Local 11 - Cabo Espichel (5 likes)
  (11, 2, NOW() - INTERVAL 25 DAY),
  (11, 3, NOW() - INTERVAL 22 DAY),
  (11, 4, NOW() - INTERVAL 18 DAY),
  (11, 5, NOW() - INTERVAL 14 DAY),
  (11, 6, NOW() - INTERVAL 10 DAY),

  -- Local 12 - Monsaraz (6 likes)
  (12, 2, NOW() - INTERVAL 60 DAY),
  (12, 3, NOW() - INTERVAL 55 DAY),
  (12, 4, NOW() - INTERVAL 50 DAY),
  (12, 5, NOW() - INTERVAL 45 DAY),
  (12, 6, NOW() - INTERVAL 40 DAY),
  (12, 7, NOW() - INTERVAL 30 DAY),

  -- Local 13 - Miróbriga (3 likes)
  (13, 2, NOW() - INTERVAL 15 DAY),
  (13, 4, NOW() - INTERVAL 12 DAY),
  (13, 7, NOW() - INTERVAL 8 DAY),

  -- Local 14 - Praia da Marinha (6 likes — top)
  (14, 2, NOW() - INTERVAL 65 DAY),
  (14, 3, NOW() - INTERVAL 60 DAY),
  (14, 4, NOW() - INTERVAL 55 DAY),
  (14, 6, NOW() - INTERVAL 50 DAY),
  (14, 7, NOW() - INTERVAL 45 DAY),
  (14, 8, NOW() - INTERVAL 40 DAY),

  -- Local 15 - Forte São João do Arade (4 likes)
  (15, 2, NOW() - INTERVAL 10 DAY),
  (15, 4, NOW() - INTERVAL 8 DAY),
  (15, 7, NOW() - INTERVAL 6 DAY),
  (15, 8, NOW() - INTERVAL 4 DAY);

-- ============================================================
-- COMENTÁRIOS — em 11 dos 15 locais
-- Sem comentários: 3 (Casal SSimão), 8 (Buçaco), 13 (Miróbriga), 15 (Forte)
-- ============================================================
INSERT INTO comentarios (local_id, utilizador_id, texto, criado_em) VALUES
  (1, 4, 'Estive lá no verão passado, é um paraíso! O acesso pelo trilho é um pouco apertado mas vale cada passo.', NOW() - INTERVAL 30 DAY),
  (1, 7, 'A água é gelada mas o local compensa tudo. Levem calçado adequado.', NOW() - INTERVAL 15 DAY),

  (2, 6, 'Aldeia única em Portugal! Vale muito a pena visitar e falar com os habitantes locais.', NOW() - INTERVAL 25 DAY),
  (2, 8, 'Estive lá no festival da aldeia, foi uma experiência inesquecível.', NOW() - INTERVAL 18 DAY),
  (2, 5, 'A fronteira a passar no meio da rua é surreal. Recomendo!', NOW() - INTERVAL 10 DAY),

  (4, 3, 'Uma das aldeias mais bonitas que já vi em Portugal. As casas em xisto são uma obra de arte.', NOW() - INTERVAL 50 DAY),
  (4, 4, 'Fui no Natal e estava nevado, parecia um postal!', NOW() - INTERVAL 38 DAY),
  (4, 7, 'A subida é puxada mas o panorama no topo compensa.', NOW() - INTERVAL 25 DAY),
  (4, 8, 'Recomendo dormir uma noite na aldeia para sentir a calma ao anoitecer.', NOW() - INTERVAL 15 DAY),

  (5, 3, 'A cor da água é mesmo turquesa como nas fotos. Difícil de acreditar que é Portugal!', NOW() - INTERVAL 40 DAY),
  (5, 7, 'Foste lá recentemente? Ouvi dizer que o acesso fechou por questões de segurança.', NOW() - INTERVAL 20 DAY),
  (5, 5, 'Sim Sofia, ainda está acessível mas com cuidado. Não saltem das rochas.', NOW() - INTERVAL 19 DAY),
  (5, 8, 'Excelente partilha, vou visitar nas próximas férias.', NOW() - INTERVAL 12 DAY),

  (6, 2, 'Levei a família no verão, as crianças adoraram o tobogã natural.', NOW() - INTERVAL 35 DAY),
  (6, 6, 'A ponte suspensa é uma aventura à parte. Bom para um dia inteiro.', NOW() - INTERVAL 22 DAY),

  (7, 5, 'Levámos as crianças e adoraram. Os jogos de luz nas estalactites são incríveis.', NOW() - INTERVAL 18 DAY),
  (7, 6, 'Vale o preço do bilhete. Levem casaco, lá dentro está frio.', NOW() - INTERVAL 10 DAY),

  (9, 3, 'Lugar muito místico, parece que paramos no tempo. As celas em cortiça são impressionantes.', NOW() - INTERVAL 28 DAY),
  (9, 5, 'Combinem com a visita à Pena para um dia completo em Sintra.', NOW() - INTERVAL 18 DAY),
  (9, 8, 'Adorei a simplicidade do espaço. Vale a pena ir cedo para evitar grupos turísticos.', NOW() - INTERVAL 8 DAY),

  (10, 4, 'Uma das praias mais bonitas da costa portuguesa. As grutas são impressionantes na maré baixa.', NOW() - INTERVAL 42 DAY),
  (10, 6, 'O restaurante na praia tem peixe fresco delicioso.', NOW() - INTERVAL 30 DAY),
  (10, 8, 'Cuidado com as marés, o areal desaparece na maré cheia.', NOW() - INTERVAL 16 DAY),

  (11, 4, 'O contraste entre as falésias e o oceano é de cortar a respiração. Local mágico ao pôr do sol.', NOW() - INTERVAL 22 DAY),
  (11, 6, 'O santuário tem uma história fascinante. Não percam!', NOW() - INTERVAL 14 DAY),

  (12, 3, 'Vista sobre o Alqueva ao anoitecer é dos espetáculos mais bonitos do Alentejo.', NOW() - INTERVAL 55 DAY),
  (12, 4, 'Adorei jantar dentro das muralhas. Comida tradicional excelente.', NOW() - INTERVAL 42 DAY),
  (12, 5, 'O céu noturno em Monsaraz é único - faz parte do Dark Sky Reserve.', NOW() - INTERVAL 30 DAY),
  (12, 7, 'Cheguei lá ao final do dia e fiquei sem palavras. Voltarei!', NOW() - INTERVAL 18 DAY),

  (14, 3, 'Sem dúvida uma das praias mais bonitas que já vi. Vão cedo para evitar multidões.', NOW() - INTERVAL 60 DAY),
  (14, 4, 'Levem snorkel, a água é cristalina e há muitos peixes.', NOW() - INTERVAL 48 DAY),
  (14, 6, 'O passeio pelos miradouros em cima das falésias também vale muito a pena.', NOW() - INTERVAL 35 DAY),
  (14, 7, 'É verdadeiramente um postal vivo. Recomendo visita ao nascer do sol.', NOW() - INTERVAL 22 DAY),
  (14, 8, 'Estive lá em Setembro, água ainda quente e menos gente. Perfeito!', NOW() - INTERVAL 12 DAY);

-- ============================================================
-- SEGUIDORES — para o ranking "Mais Seguidores"
-- ============================================================
INSERT INTO seguidores (seguidor_id, seguido_id, criado_em) VALUES
  -- Mariana (5) — 5 seguidores
  (3, 5, NOW() - INTERVAL 40 DAY),
  (4, 5, NOW() - INTERVAL 35 DAY),
  (6, 5, NOW() - INTERVAL 30 DAY),
  (7, 5, NOW() - INTERVAL 25 DAY),
  (8, 5, NOW() - INTERVAL 20 DAY),
  -- Tiago (4) — 4 seguidores
  (3, 4, NOW() - INTERVAL 38 DAY),
  (5, 4, NOW() - INTERVAL 32 DAY),
  (7, 4, NOW() - INTERVAL 28 DAY),
  (8, 4, NOW() - INTERVAL 22 DAY),
  -- Sofia (7) — 3 seguidores
  (3, 7, NOW() - INTERVAL 30 DAY),
  (4, 7, NOW() - INTERVAL 25 DAY),
  (8, 7, NOW() - INTERVAL 15 DAY),
  -- Ana (3) — 3 seguidores
  (4, 3, NOW() - INTERVAL 28 DAY),
  (5, 3, NOW() - INTERVAL 20 DAY),
  (6, 3, NOW() - INTERVAL 12 DAY),
  -- Pedro (6) — 2 seguidores
  (3, 6, NOW() - INTERVAL 25 DAY),
  (7, 6, NOW() - INTERVAL 15 DAY),
  -- Rui (8) — 2 seguidores
  (5, 8, NOW() - INTERVAL 18 DAY),
  (6, 8, NOW() - INTERVAL 10 DAY);