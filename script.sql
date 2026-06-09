-- =========================================================================================
-- ChefSupply - Estrutura do Banco de Dados e Carga Inicial de Dados
-- Este script define a modelagem das tabelas do sistema de controle de validades e estoques
-- =========================================================================================

-- Criação do banco de dados chefsupply caso ele não exista no servidor de banco de dados
-- Define o charset 'utf8mb4' para suportar acentos, caracteres especiais e emojis de forma nativa
-- Define a collation 'utf8mb4_unicode_ci' para regras de comparação insensíveis a maiúsculas/minúsculas e acentos
CREATE DATABASE IF NOT EXISTS chefsupply CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Seleciona o banco de dados 'chefsupply' recém-criado para que as próximas tabelas sejam criadas dentro dele
USE chefsupply;

-- ── TABELA DE PERFIS ──
-- Armazena os perfis de acesso dos usuários no sistema (ex: Administrador, Cozinheiro, etc)
CREATE TABLE perfis (
    -- Chave primária auto-incrementada para identificação única do perfil
    id INT AUTO_INCREMENT PRIMARY KEY,
    
    -- Nome descritivo do perfil de usuário (obrigatório, máximo de 50 caracteres)
    nome VARCHAR(50) NOT NULL
);

-- ── TABELA DE USUÁRIOS ──
-- Armazena as contas de usuário autorizadas a acessar o sistema ChefSupply
CREATE TABLE usuarios (
    -- Chave primária auto-incrementada que identifica unicamente cada usuário cadastrado
    id INT AUTO_INCREMENT PRIMARY KEY,
    
    -- Nome completo do usuário (obrigatório, máximo de 100 caracteres)
    nome VARCHAR(100) NOT NULL,
    
    -- Endereço de email do usuário (obrigatório, único no banco de dados para evitar logins duplicados)
    email VARCHAR(150) NOT NULL UNIQUE,
    
    -- Senha criptografada por meio de hash (obrigatório, tamanho de 255 para acomodar diferentes algoritmos)
    senha VARCHAR(255) NOT NULL,
    
    -- Chave estrangeira que referencia a tabela 'perfis' para controlar o nível de privilégio do usuário
    perfil_id INT,
    
    -- Nome do restaurante ou filial à qual o usuário pertence (valor padrão: 'Restaurante Premium')
    restaurante VARCHAR(150) DEFAULT 'Restaurante Premium',
    
    -- Situação da conta do usuário, permitindo apenas os valores 'ativo' ou 'inativo' (padrão: 'ativo')
    status ENUM('ativo', 'inativo') DEFAULT 'ativo',
    
    -- Data e hora do último acesso realizado pelo usuário (pode ser nulo caso nunca tenha logado)
    ultimo_acesso DATETIME,
    
    -- Data e hora em que a conta foi criada, preenchida automaticamente com o horário atual do servidor
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Declaração formal da restrição de chave estrangeira apontando para a tabela 'perfis'
    FOREIGN KEY (perfil_id) REFERENCES perfis(id)
);

-- ── TABELA DE CATEGORIAS ──
-- Organiza os produtos em grupos (ex: carnes, laticínios, etc) e define regras de alerta de vencimento por categoria
CREATE TABLE categorias (
    -- Chave primária auto-incrementada
    id INT AUTO_INCREMENT PRIMARY KEY,
    
    -- Nome descritivo da categoria de produtos (obrigatório)
    nome VARCHAR(100) NOT NULL,
    
    -- Cor em formato hexadecimal para fins visuais e estilização de badges nos painéis (padrão verde)
    cor VARCHAR(20) DEFAULT '#2db35d',
    
    -- Define a quantidade padrão de dias antes do vencimento para disparar o aviso de atenção (padrão: 3 dias)
    dias_alerta_vencimento INT DEFAULT 3,
    
    -- Registro da data de criação da categoria com preenchimento automático
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ── TABELA DE FORNECEDORES ──
-- Cadastro dos parceiros comerciais que fornecem insumos ao restaurante
CREATE TABLE fornecedores (
    -- Chave primária auto-incrementada
    id INT AUTO_INCREMENT PRIMARY KEY,
    
    -- Nome da empresa/fornecedor (obrigatório)
    nome VARCHAR(150) NOT NULL,
    
    -- CNPJ do fornecedor (opcional, máximo 20 caracteres)
    cnpj VARCHAR(20),
    
    -- Número de telefone do fornecedor (opcional)
    telefone VARCHAR(20),
    
    -- Endereço de e-mail para contato (opcional)
    email VARCHAR(150),
    
    -- Lista textual curta ou tags dos produtos fornecidos pela empresa
    produtos_fornecidos VARCHAR(255),
    
    -- Define se o cadastro do fornecedor está ativo (1) ou inativo (0) (padrão: ativo)
    ativo TINYINT(1) DEFAULT 1,
    
    -- Registro automático da data e hora do cadastro do fornecedor
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ── TABELA DE PRODUTOS ──
-- Tabela principal de produtos cadastrados na despensa/cozinha
CREATE TABLE produtos (
    -- Chave primária auto-incrementada
    id INT AUTO_INCREMENT PRIMARY KEY,
    
    -- Nome descritivo do produto (obrigatório)
    nome VARCHAR(150) NOT NULL,
    
    -- Chave estrangeira que conecta o produto a uma categoria específica
    categoria_id INT,
    
    -- Unidade de medida padrão do produto, ex: kg, L, un (padrão 'kg')
    unidade VARCHAR(20) NOT NULL DEFAULT 'kg',
    
    -- Estoque atual consolidado do produto (soma de todos os lotes ativos, decimal com 2 casas de precisão)
    estoque_atual DECIMAL(10,2) DEFAULT 0,
    
    -- Limite mínimo recomendado do estoque do produto (usado para sinalizar necessidade de compras)
    estoque_minimo DECIMAL(10,2) DEFAULT 0,
    
    -- Limite máximo recomendado de estocagem do produto
    estoque_maximo DECIMAL(10,2) DEFAULT 0,
    
    -- Custo médio ou custo unitário padrão do produto (usado em cálculos financeiros de perdas)
    custo_unitario DECIMAL(10,2) DEFAULT 0.00,
    
    -- Status de nível de estoque do produto no sistema
    status ENUM('Normal', 'Baixo', 'Crítico', 'Alto') DEFAULT 'Normal',
    
    -- Tendência do fluxo de consumo do produto (estável, descendo ou subindo)
    tendencia ENUM('subindo', 'descendo', 'estavel') DEFAULT 'estavel',
    
    -- Atualizado automaticamente com a data e hora sempre que qualquer coluna do produto for modificada
    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Registro permanente da data de criação do produto
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Restrição de chave estrangeira apontando para categorias. Se a categoria for apagada, este campo vira NULL
    FOREIGN KEY (categoria_id) REFERENCES categorias(id) ON DELETE SET NULL
);

-- ── TABELA DE LOTES ──
-- Gerencia os lotes de entrega individuais de cada produto, permitindo controle fino de datas de validade
CREATE TABLE lotes (
    -- Chave primária auto-incrementada do lote
    id INT AUTO_INCREMENT PRIMARY KEY,
    
    -- Chave estrangeira obrigatória indicando a qual produto este lote pertence
    produto_id INT NOT NULL,
    
    -- Chave estrangeira opcional apontando para o fornecedor do lote
    fornecedor_id INT,
    
    -- Código alfanumérico identificador do lote impresso na embalagem (opcional)
    codigo_lote VARCHAR(50),
    
    -- Quantidade total que deu entrada inicialmente neste lote
    quantidade DECIMAL(10,2) NOT NULL,
    
    -- Quantidade que ainda resta no estoque para este lote específico
    quantidade_restante DECIMAL(10,2) NOT NULL,
    
    -- Preço unitário pago por este lote (preço de custo)
    preco_custo DECIMAL(10,2) DEFAULT 0.00,
    
    -- Data de entrada/recebimento do lote no estabelecimento (obrigatória)
    data_entrada DATE NOT NULL,
    
    -- Data de expiração/vencimento informada pelo fabricante do lote
    data_vencimento DATE,
    
    -- Status do lote, controlando se ele está ativo, totalmente consumido, vencido ou descartado
    status ENUM('ativo', 'consumido', 'vencido', 'descartado') DEFAULT 'ativo',
    
    -- Chave estrangeira apontando para o usuário que cadastrou ou deu entrada no lote
    usuario_id INT,
    
    -- Registro da data de criação do lote no sistema
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Restrições de chaves estrangeiras:
    -- ON DELETE CASCADE: se o produto for excluído, todos os seus lotes também serão excluídos automaticamente
    -- ON DELETE SET NULL: se o fornecedor ou usuário cadastrador forem deletados, o lote permanece mas com valores nulos
    FOREIGN KEY (produto_id) REFERENCES produtos(id) ON DELETE CASCADE,
    FOREIGN KEY (fornecedor_id) REFERENCES fornecedores(id) ON DELETE SET NULL,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
);

-- ── TABELA DE DESCARTES ──
-- Controla as perdas de estoque por vencimento, deterioração ou outros motivos
CREATE TABLE descartes (
    -- Chave primária auto-incrementada
    id INT AUTO_INCREMENT PRIMARY KEY,
    
    -- Chave estrangeira que aponta para o produto que sofreu descarte
    produto_id INT NOT NULL,
    
    -- Chave estrangeira opcional que especifica de qual lote exato o produto foi descartado
    lote_id INT,
    
    -- Quantidade de itens descartados
    quantidade DECIMAL(10,2) NOT NULL,
    
    -- Motivo do descarte limitado às opções predefinidas
    motivo ENUM('Vencimento', 'Deterioração', 'Excesso de produção', 'Outros') NOT NULL,
    
    -- Valor total perdido calculado financeiramente (quantidade de descarte * preço de custo do lote/produto)
    valor_perdido DECIMAL(10,2) DEFAULT 0.00,
    
    -- Observações textuais detalhando os motivos do descarte
    observacoes TEXT,
    
    -- Data exata em que ocorreu o descarte
    data_descarte DATE NOT NULL,
    
    -- Usuário responsável por realizar ou registrar o descarte do produto
    usuario_id INT,
    
    -- Registro cronológico automático de quando a ação foi criada no sistema
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Restrições de chave estrangeira do descarte
    FOREIGN KEY (produto_id) REFERENCES produtos(id) ON DELETE CASCADE,
    FOREIGN KEY (lote_id) REFERENCES lotes(id) ON DELETE SET NULL,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
);

-- ── TABELA DE RELATÓRIOS ──
-- Histórico e logs de relatórios gerados pelos usuários no sistema
CREATE TABLE relatorios (
    -- Chave primária auto-incrementada
    id INT AUTO_INCREMENT PRIMARY KEY,
    
    -- Título descritivo do relatório gerado
    titulo VARCHAR(200) NOT NULL,
    
    -- Categoria do relatório que define quais dados foram extraídos
    tipo ENUM('Produtos Vencidos','Perdas Financeiras','Inventário Completo','Entradas de Estoque','Produtos por Categoria','Fornecedores') NOT NULL,
    
    -- Caminho ou nome do arquivo PDF/Planilha gerado e guardado no servidor
    arquivo VARCHAR(255),
    
    -- Usuário que comandou a geração do relatório
    usuario_id INT,
    
    -- Data e hora em que o relatório foi gerado
    gerado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Chave estrangeira ligando ao usuário gerador do relatório
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
);

-- ── TABELA DE CONFIGURAÇÕES ──
-- Armazena configurações gerais de comportamento da aplicação no formato chave/valor
CREATE TABLE configuracoes (
    -- Chave primária auto-incrementada
    id INT AUTO_INCREMENT PRIMARY KEY,
    
    -- Identificador único de configuração (ex: 'limite_dias_validade')
    chave VARCHAR(100) NOT NULL UNIQUE,
    
    -- Valor configurado
    valor VARCHAR(255) NOT NULL,
    
    -- Descrição legível para o usuário sobre o que essa configuração faz
    descricao VARCHAR(255)
);

-- ── CONSULTA DE TESTE ──
-- Seleciona todos os usuários cadastrados (mantido igual ao script original para compatibilidade de fluxo)
select * from usuarios;

-- Força o uso do banco de dados chefsupply para as inserções a seguir
USE chefsupply;

-- Insere as categorias de alimentos iniciais padrão para popular o sistema
INSERT INTO categorias (nome) VALUES
('Carnes'),
('Laticínios'),
('Vegetais'),
('Frutas'),
('Grãos'),
('Óleos'),
('Massas'),
('Bebidas'),
('Temperos'),
('Limpeza');

-- Insere mais uma categoria adicional no catálogo
INSERT INTO categorias (nome) VALUES ('Doces e Açúcar');
