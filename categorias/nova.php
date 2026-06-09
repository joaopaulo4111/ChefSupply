<?php
// Inicia a sessão PHP para verificar a autenticação do usuário.
session_start();

// Verifica se a sessão 'logado' não está ativa. Se não estiver, redireciona o usuário para o login na raiz e encerra a execução do script.
if(!isset($_SESSION['logado']) || !$_SESSION['logado']){ 
    header('Location: ../index.php'); 
    exit; 
}

// Inclui o arquivo de conexão com o banco de dados (PDO) para persistência dos dados da categoria.
include '../conexao.php';

// Inicializa a variável de erro como string vazia.
$erro = '';

// Verifica se a requisição HTTP foi enviada via POST (submissão do formulário).
if($_SERVER['REQUEST_METHOD'] === 'POST'){
    // Limpa os espaços em branco no início e final do nome enviado pelo formulário.
    $nome = trim($_POST['nome']);
    
    // Obtém a cor enviada pelo formulário ou define '#2db35d' (verde) como valor padrão caso não seja especificada.
    $cor  = $_POST['cor'] ?? '#2db35d';
    
    // Converte os dias de alerta de vencimento para um número inteiro.
    $dias = intval($_POST['dias_alerta_vencimento']);
    
    // Verifica se o campo obrigatório 'nome' está vazio. Se sim, define a mensagem de erro.
    if(!$nome){ 
        $erro = 'O nome da categoria é obrigatório.'; 
    }
    // Se o nome foi preenchido, insere a nova categoria no banco de dados.
    else {
        // Prepara a query SQL para inserção de um novo registro de categoria.
        $conexao->prepare("INSERT INTO categorias (nome, cor, dias_alerta_vencimento) VALUES (:n,:c,:d)")
            // Executa a instrução preparada vinculando os respectivos parâmetros.
            ->execute([':n'=>$nome,':c'=>$cor,':d'=>$dias]);
        
        // Redireciona para a página principal das categorias indicando que o registro foi criado e encerra o script.
        header('Location: index.php?msg=criado'); 
        exit;
    }
}

// Define o valor da variável '$pagina_atual' para controle do item ativo no menu do cabeçalho.
$pagina_atual = 'configuracoes'; 

// Define o título da página exibido na barra do navegador.
$titulo_pagina = 'Nova Categoria';

// Inclui o arquivo de cabeçalho padrão da aplicação.
include '../_header.php';
?>
<!-- Estrutura principal da página de criação de categoria -->
<div class="content">
    <!-- Cabeçalho da página contendo o título e botão para voltar à lista -->
    <div class="page-header">
        <div class="page-header-left">
            <h2>Nova Categoria</h2>
            <p>Crie uma categoria para organizar os produtos</p>
        </div>
        <!-- Link de retorno para a listagem das categorias -->
        <a href="index.php" class="btn btn-secondary">← Voltar</a>
    </div>
    
    <!-- Exibe a mensagem de erro de validação em um banner, caso exista -->
    <?php if($erro): ?>
        <div class="alert alert-danger"><?= $erro ?></div>
    <?php endif; ?>
    
    <!-- Cartão estrutural para o formulário de cadastro -->
    <div class="form-card">
        <!-- Formulário que submete as informações usando o método POST -->
        <form method="POST">
            <!-- Grid de organização dos campos do formulário -->
            <div class="form-grid">
                <!-- Campo para preenchimento do Nome da Categoria (largura inteira) -->
                <div class="form-group full">
                    <label>Nome da Categoria *</label>
                    <!-- Campo de entrada de texto que mantém o valor preenchido em caso de erro, com proteção XSS -->
                    <input type="text" name="nome" placeholder="Ex: Carnes, Laticínios, Secos..." value="<?= htmlspecialchars($_POST['nome'] ?? '') ?>" required>
                </div>
                
                <!-- Campo para a Cor de Identificação visual -->
                <div class="form-group">
                    <label>Cor de Identificação</label>
                    <!-- Campo de seleção de cores HTML5 que mantém a cor selecionada caso ocorra erro -->
                    <input type="color" name="cor" value="<?= $_POST['cor'] ?? '#2db35d' ?>" style="height:42px;padding:4px 8px">
                </div>
                
                <!-- Campo para os Dias de Alerta antes do vencimento do lote do produto -->
                <div class="form-group">
                    <label>Dias de Alerta Antes do Vencimento</label>
                    <!-- Campo do tipo número limitando os dias entre 1 e 30, com valor padrão de 3 dias caso não especificado -->
                    <input type="number" name="dias_alerta_vencimento" min="1" max="30" value="<?= $_POST['dias_alerta_vencimento'] ?? 3 ?>">
                </div>
            </div>
            
            <!-- Ações do formulário (Salvar ou Cancelar) -->
            <div class="form-actions">
                <!-- Botão de submissão do formulário -->
                <button type="submit" class="btn btn-primary">Salvar Categoria</button>
                <!-- Link de cancelamento que retorna para a listagem de categorias -->
                <a href="index.php" class="btn btn-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>
<?php 
// Inclui o rodapé padrão do painel administrativo.
include '../_footer.php'; 
?>