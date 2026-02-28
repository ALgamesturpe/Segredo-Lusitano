-- ============================================================
-- SEGREDO LUSITANO - Base de Dados
-- ============================================================

CREATE DATABASE IF NOT EXISTS segredo_lusitano
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE segredo_lusitano;

-- Limpar tabelas existentes (ordem inversa por causa das FK)
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS denuncias;
DROP TABLE IF EXISTS comentarios;
DROP TABLE IF EXISTS likes;
DROP TABLE IF EXISTS fotos;
DROP TABLE IF EXISTS locais;
DROP TABLE IF EXISTS regioes;
DROP TABLE IF EXISTS categorias;
DROP TABLE IF EXISTS utilizadores;
SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- TABELA: utilizadores
-- ============================================================
CREATE TABLE utilizadores (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    nome        VARCHAR(100)        NOT NULL,
    username    VARCHAR(50)         NOT NULL UNIQUE,
    email       VARCHAR(150)        NOT NULL UNIQUE,
    password    VARCHAR(255)        NOT NULL,
    avatar      VARCHAR(255)        DEFAULT NULL,
    bio         TEXT                DEFAULT NULL,
    pontos      INT                 DEFAULT 0,
    role        ENUM('user','admin') DEFAULT 'user',
    ativo       TINYINT(1)          DEFAULT 1,
    verificado  TINYINT(1)          DEFAULT 0,
    criado_em   DATETIME            DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- TABELA: categorias
-- ============================================================
CREATE TABLE categorias (
    id      INT AUTO_INCREMENT PRIMARY KEY,
    nome    VARCHAR(80) NOT NULL,
    icone   VARCHAR(50) DEFAULT 'fas fa-map-pin'
) ENGINE=InnoDB;

INSERT INTO categorias (nome, icone) VALUES
  ('Cascata',       'fas fa-water'),
  ('Miradouro',     'fas fa-mountain'),
  ('Aldeia',        'fas fa-home'),
  ('Ruínas',        'fas fa-landmark'),
  ('Praia Secreta', 'fas fa-umbrella-beach'),
  ('Floresta',      'fas fa-tree'),
  ('Gruta',         'fas fa-dungeon'),
  ('Monumento',     'fas fa-chess-rook');

-- ============================================================
-- TABELA: regioes
-- ============================================================
CREATE TABLE regioes (
    id   INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(80) NOT NULL
) ENGINE=InnoDB;

INSERT INTO regioes (nome) VALUES
  ('Norte'),('Centro'),('Lisboa e Vale do Tejo'),
  ('Alentejo'),('Algarve'),('Açores'),('Madeira');

-- ============================================================
-- TABELA: locais
-- ============================================================
CREATE TABLE locais (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    utilizador_id INT          NOT NULL,
    categoria_id  INT          NOT NULL,
    regiao_id     INT          NOT NULL,
    nome          VARCHAR(150) NOT NULL,
    descricao     TEXT         NOT NULL,
    latitude      DECIMAL(10,7) NOT NULL,
    longitude     DECIMAL(10,7) NOT NULL,
    dificuldade   ENUM('facil','medio','dificil') DEFAULT 'medio',
    foto_capa     VARCHAR(255)  DEFAULT NULL,
    estado        ENUM('pendente','aprovado','rejeitado') DEFAULT 'aprovado',
    vistas        INT           DEFAULT 0,
    criado_em     DATETIME      DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (utilizador_id) REFERENCES utilizadores(id) ON DELETE CASCADE,
    FOREIGN KEY (categoria_id)  REFERENCES categorias(id)   ON DELETE RESTRICT,
    FOREIGN KEY (regiao_id)     REFERENCES regioes(id)      ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ============================================================
-- TABELA: fotos
-- ============================================================
CREATE TABLE fotos (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    local_id     INT          NOT NULL,
    utilizador_id INT         NOT NULL,
    ficheiro     VARCHAR(255) NOT NULL,
    denunciada   TINYINT(1)   DEFAULT 0,
    criado_em    DATETIME     DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (local_id)      REFERENCES locais(id)       ON DELETE CASCADE,
    FOREIGN KEY (utilizador_id) REFERENCES utilizadores(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- TABELA: likes
-- ============================================================
CREATE TABLE likes (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    local_id      INT NOT NULL,
    utilizador_id INT NOT NULL,
    criado_em     DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_like (local_id, utilizador_id),
    FOREIGN KEY (local_id)      REFERENCES locais(id)       ON DELETE CASCADE,
    FOREIGN KEY (utilizador_id) REFERENCES utilizadores(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- TABELA: comentarios
-- ============================================================
CREATE TABLE comentarios (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    local_id      INT  NOT NULL,
    utilizador_id INT  NOT NULL,
    texto         TEXT NOT NULL,
    denunciado    TINYINT(1) DEFAULT 0,
    criado_em     DATETIME   DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (local_id)      REFERENCES locais(id)       ON DELETE CASCADE,
    FOREIGN KEY (utilizador_id) REFERENCES utilizadores(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- TABELA: denuncias
-- ============================================================
CREATE TABLE denuncias (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    tipo          ENUM('foto','local','comentario') NOT NULL,
    referencia_id INT NOT NULL,
    utilizador_id INT NOT NULL,
    motivo        TEXT,
    resolvida     TINYINT(1) DEFAULT 0,
    criado_em     DATETIME   DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (utilizador_id) REFERENCES utilizadores(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- TABELA: codigos_verificacao
-- ============================================================
CREATE TABLE codigos_verificacao (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    utilizador_id INT          NOT NULL,
    codigo        VARCHAR(6)   NOT NULL,
    tipo          ENUM('registo','login') DEFAULT 'registo',
    expira_em     DATETIME     NOT NULL,
    usado         TINYINT(1)   DEFAULT 0,
    criado_em     DATETIME     DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (utilizador_id) REFERENCES utilizadores(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- DADOS DE EXEMPLO
-- ============================================================
-- Admin: password = admin123
INSERT INTO utilizadores (nome, username, email, password, pontos, role, verificado) VALUES
  ('Administrador', 'admin', 'admin@segredolusitano.pt',
   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 9999, 'admin', 1);

INSERT INTO utilizadores (nome, username, email, password, pontos, role, verificado) VALUES
  ('João Explorador', 'joao', 'joao@exemplo.pt',
   '$2y$10$TKh8H1.PkssRqmgl.gHPZuEDVbMla7At7M8nnSf8YjZoxsQPYubiC', 150, 'user', 1);

-- Locais de exemplo
INSERT INTO locais (utilizador_id, categoria_id, regiao_id, nome, descricao, latitude, longitude, dificuldade, estado) VALUES
  (2, 1, 1, 'Cascata do Arado', 'Uma das mais belas cascatas do Gerês, escondida entre penedos graníticos. Acesso apenas por trilho não sinalizado.', 41.7745, -8.1621, 'medio', 'aprovado'),
  (2, 2, 2, 'Miradouro dos Três Reinos', 'Ponto onde se avistam três distritos em simultâneo. Pouco conhecido dos turistas mas muito frequentado pelos locais.', 40.3421, -7.5312, 'facil', 'aprovado'),
  (2, 4, 3, 'Ruínas da Quinta do Marquês', 'Antiga quinta abandonada do séc. XVIII, com azulejos intactos e jardim selvagem.', 38.7543, -9.1823, 'facil', 'aprovado');
