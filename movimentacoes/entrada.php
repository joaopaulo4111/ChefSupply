<?php
// Inicia a sessão PHP para verificar a autenticação de login do usuário no sistema
session_start();

// Verifica se o usuário não está autenticado no sistema.
// Se não estiver logado, redireciona o usuário para a página de login raiz e interrompe a execução.
if(!isset($_SESSION['logado']) || !$_SESSION['logado']){ 
    header('Location: ../index.php'); 
    exit; 
}

// Inclui o arquivo contendo a conexão PDO com o banco de dados.
include '../conexao.php';

// Busca no banco de dados a listagem de todos os produtos ordenados por nome.
// Recupera o ID, nome, unidade de medida e estoque atual de cada produto para alimentar o select do formulário.
$produtos = $conexao->query("SELECT id, nome, unidade, estoque_atual FROM produtos ORDER BY nome")->fetchAll();

// Inicializa a variável $erro como string vazia para armazenar potenciais falhas de validação.
$erro = '';

// Verifica se a requisição do usuário é do tipo POST (ou seja, envio de formulário de entrada de estoque).
if($_SERVER['REQUEST_METHOD'] === 'POST'){
    // Captura e sanitiza os dados enviados:
    // Converte o ID do produto enviado para inteiro.
    $produto_id = intval($_POST['produto_id']);
    // Converte a quantidade informada para um número de ponto flutuante (float).
    $quantidade = floatval($_POST['quantidade']);
    // Captura a data de entrada informada.
    $data       = $_POST['data_entrada'];

    // Validação de segurança: O ID do produto deve ser válido e a quantidade inserida deve ser estritamente maior que 0.
    if(!$produto_id || $quantidade <= 0){ 
        $erro = 'Produto e quantidade são obrigatórios.'; 
    } else {
        // Inicia uma Transação de Banco de Dados (Transaction) para garantir a atomicidade das operações.
        // Se alguma query falhar, nenhuma alteração será salva no banco.
        $conexao->beginTransaction();
        
        // Operação 1: Insere um novo registro de lote na tabela 'lotes'.
        // O campo 'quantidade_restante' inicia idêntico à 'quantidade' inicial do lote.
        $conexao->prepare("INSERT INTO lotes (produto_id, quantidade, quantidade_restante, data_entrada, usuario_id)
            VALUES (:pid,:qtd,:qtd,:dt,:uid)")
            ->execute([
                ':pid' => $produto_id,
                ':qtd' => $quantidade,
                ':dt'  => $data,
                // Associa o ID do usuário atualmente logado na sessão ao registro de entrada
                ':uid' => $_SESSION['usuario_id']
            ]);
            
        // Operação 2: Atualiza a tabela 'produtos' incrementando o campo 'estoque_atual' com a quantidade que deu entrada.
        $conexao->prepare("UPDATE produtos SET estoque_atual = estoque_atual + :qtd WHERE id=:id")
            ->execute([
                ':qtd' => $quantidade,
                ':id'  => $produto_id
            ]);
            
        // Confirma e grava em definitivo todas as operações executadas dentro da transação no banco de dados.
        $conexao->commit();
        
        // Redireciona o usuário para a página de listagem de movimentações com mensagem de sucesso na URL indicando "entrada".
        header('Location: index.php?msg=entrada'); 
        exit;
    }
}

// Configura a identificação visual da página e do menu lateral
$pagina_atual = 'estoque'; 
$titulo_pagina = 'Entrada de Estoque';

// Inclui o arquivo de cabeçalho padrão com estilos e menu lateral do painel
include '../_header.php';
?>
<!-- Estrutura de Conteúdo Principal -->
<div class="content">
    <!-- Cabeçalho da Página -->
    <div class="page-header">
        <div class="page-header-left">
            <h2>Entrada de Estoque</h2>
            <p>Adicione quantidade a um produto</p>
        </div>
        <!-- Botão para cancelar e retornar à listagem de movimentações -->
        <a href="index.php" class="btn btn-secondary">← Voltar</a>
    </div>
    
    <!-- Exibe bloco de alerta em vermelho caso ocorra algum erro de validação -->
    <?php if($erro): ?>
        <div class="alert alert-danger"><?= $erro ?></div>
    <?php endif; ?>
    
    <!-- Card contendo o formulário de entrada de lote -->
    <div class="form-card">
        <form method="POST">
            <div class="form-grid">
                <!-- Campo de Seleção do Produto -->
                <div class="form-group">
                    <label>Produto *</label>
                    <select name="produto_id" required>
                        <option value="">— Selecione —</option>
                        <!-- Itera pela lista de produtos carregada no banco para criar as options -->
                        <?php foreach($produtos as $p): ?>
                            <!-- Mostra o nome do produto e exibe seu saldo atual de estoque e unidade de medida -->
                            <option value="<?= $p['id'] ?>">
                                <?= htmlspecialchars($p['nome']) ?> — Atual: <?= $p['estoque_atual'] ?> <?= $p['unidade'] ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Campo de Entrada da Quantidade (Permite decimais finos por possuir step de 0.001) -->
                <div class="form-group">
                    <label>Quantidade *</label>
                    <input type="number" name="quantidade" step="0.001" min="0.001" placeholder="0.00" required>
                </div>
                
                <!-- Campo de Entrada da Data da movimentação (Por padrão carrega a data atual do servidor) -->
                <div class="form-group">
                    <label>Data *</label>
                    <input type="date" name="data_entrada" value="<?= date('Y-m-d') ?>" required>
                </div>
            </div>
            
            <!-- Botões de confirmação e cancelamento -->
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Confirmar Entrada</button>
                <a href="index.php" class="btn btn-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>
<!-- Inclui as tags de rodapé padrão do painel -->
<?php include '../_footer.php'; ?>