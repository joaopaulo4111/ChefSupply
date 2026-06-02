-- ============================================
-- ChefSupply - Estrutura do Banco de Dados
-- ============================================

CREATE DATABASE IF NOT EXISTS chefsupply CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE chefsupply;

CREATE TABLE perfis (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(50) NOT NULL
);

CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    senha VARCHAR(255) NOT NULL,
    perfil_id INT,
    restaurante VARCHAR(150) DEFAULT 'Restaurante Premium',
    status ENUM('ativo', 'inativo') DEFAULT 'ativo',
    ultimo_acesso DATETIME,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (perfil_id) REFERENCES perfis(id)
);

CREATE TABLE categorias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    cor VARCHAR(20) DEFAULT '#2db35d',
    dias_alerta_vencimento INT DEFAULT 3,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE fornecedores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(150) NOT NULL,
    cnpj VARCHAR(20),
    telefone VARCHAR(20),
    email VARCHAR(150),
    produtos_fornecidos VARCHAR(255),
    ativo TINYINT(1) DEFAULT 1,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE produtos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(150) NOT NULL,
    categoria_id INT,
    unidade VARCHAR(20) NOT NULL DEFAULT 'kg',
    estoque_atual DECIMAL(10,2) DEFAULT 0,
    estoque_minimo DECIMAL(10,2) DEFAULT 0,
    estoque_maximo DECIMAL(10,2) DEFAULT 0,
    custo_unitario DECIMAL(10,2) DEFAULT 0.00,
    status ENUM('Normal', 'Baixo', 'Crítico', 'Alto') DEFAULT 'Normal',
    tendencia ENUM('subindo', 'descendo', 'estavel') DEFAULT 'estavel',
    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (categoria_id) REFERENCES categorias(id) ON DELETE SET NULL
);

CREATE TABLE lotes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    produto_id INT NOT NULL,
    fornecedor_id INT,
    codigo_lote VARCHAR(50),
    quantidade DECIMAL(10,2) NOT NULL,
    quantidade_restante DECIMAL(10,2) NOT NULL,
    preco_custo DECIMAL(10,2) DEFAULT 0.00,
    data_entrada DATE NOT NULL,
    data_vencimento DATE,
    status ENUM('ativo', 'consumido', 'vencido', 'descartado') DEFAULT 'ativo',
    usuario_id INT,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (produto_id) REFERENCES produtos(id) ON DELETE CASCADE,
    FOREIGN KEY (fornecedor_id) REFERENCES fornecedores(id) ON DELETE SET NULL,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
);

CREATE TABLE descartes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    produto_id INT NOT NULL,
    lote_id INT,
    quantidade DECIMAL(10,2) NOT NULL,
    motivo ENUM('Vencimento', 'Deterioração', 'Excesso de produção', 'Outros') NOT NULL,
    valor_perdido DECIMAL(10,2) DEFAULT 0.00,
    observacoes TEXT,
    data_descarte DATE NOT NULL,
    usuario_id INT,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (produto_id) REFERENCES produtos(id) ON DELETE CASCADE,
    FOREIGN KEY (lote_id) REFERENCES lotes(id) ON DELETE SET NULL,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
);

CREATE TABLE relatorios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(200) NOT NULL,
    tipo ENUM('Produtos Vencidos','Perdas Financeiras','Inventário Completo','Entradas de Estoque','Produtos por Categoria','Fornecedores') NOT NULL,
    arquivo VARCHAR(255),
    usuario_id INT,
    gerado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
);

CREATE TABLE configuracoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    chave VARCHAR(100) NOT NULL UNIQUE,
    valor VARCHAR(255) NOT NULL,
    descricao VARCHAR(255)
);
select * from usuarios

