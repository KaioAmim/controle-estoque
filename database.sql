-- UniStock — Script de criação do banco de dados
-- Execute este arquivo no seu MySQL/MariaDB antes de usar o sistema

CREATE DATABASE IF NOT EXISTS unistock CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE unistock;

CREATE TABLE IF NOT EXISTS usuarios (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    usuario    VARCHAR(100) NOT NULL UNIQUE,
    senha      VARCHAR(255) NOT NULL,
    nome       VARCHAR(150) NOT NULL,
    email      VARCHAR(150) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS produtos (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    nome            VARCHAR(255) NOT NULL,
    sku             VARCHAR(100) DEFAULT NULL,
    categoria       VARCHAR(150) DEFAULT NULL,
    numero_ca       VARCHAR(50)  DEFAULT NULL,
    descricao_ca    TEXT,
    validade_ca     DATE         DEFAULT NULL,
    quantidade      INT NOT NULL DEFAULT 0,
    estoque_minimo  INT NOT NULL DEFAULT 5,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Usuário padrão (troque a senha após o primeiro acesso)
INSERT IGNORE INTO usuarios (usuario, senha, nome, email)
VALUES ('admin', '123456', 'Administrador', 'admin@unistock.com');

-- Tabela de tokens de recuperação de senha
CREATE TABLE IF NOT EXISTS reset_tokens (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    token      VARCHAR(64) NOT NULL UNIQUE,
    expira_em  DATETIME NOT NULL,
    usado      TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
);
