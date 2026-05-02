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

-- Locais de exemplo
INSERT INTO locais (utilizador_id, categoria_id, regiao_id, nome, descricao, latitude, longitude, dificuldade, estado) VALUES
  (2, 1, 1, 'Cascata do Arado', 'Uma das mais belas cascatas do Gerês, escondida entre penedos graníticos. Acesso apenas por trilho não sinalizado.', 41.7745, -8.1621, 'medio', 'aprovado'),
  (2, 2, 2, 'Miradouro dos Três Reinos', 'Ponto onde se avistam três distritos em simultâneo. Pouco conhecido dos turistas mas muito frequentado pelos locais.', 40.3421, -7.5312, 'facil', 'aprovado'),
  (2, 4, 3, 'Ruínas da Quinta do Marquês', 'Antiga quinta abandonada do séc. XVIII, com azulejos intactos e jardim selvagem.', 38.7543, -9.1823, 'facil', 'aprovado');