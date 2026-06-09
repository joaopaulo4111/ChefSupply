<?php
// Inicia a sessão para permitir a validação e o controle do estado de login do usuário no sistema.
session_start();

// Verifica se a variável de sessão 'logado' não está definida ou se seu valor é falso. 
// Caso o usuário não esteja autenticado, redireciona-o para a página de login/índice na raiz e interrompe a execução do script.
if(!isset($_SESSION['logado']) || !$_SESSION['logado']){ 
    header('Location: ../index.php'); 
    exit; 
}

// Inclui o arquivo de conexão com o banco de dados (PDO) para realizar as operações de leitura e gravação.
include '../conexao.php';

// Obtém o parâmetro 'id' enviado via URL (método GET), convertendo-o para um número inteiro.
// Se não houver 'id' na URL, define o valor padrão como 0.
$id = intval($_GET['id'] ?? 0);

// Prepara uma consulta SQL para selecionar todas as informações da categoria correspondente ao ID informado.
$stmt = $conexao->prepare("SELECT * FROM categorias WHERE id=:id");

// Executa a consulta preparada associando o parâmetro ':id' ao valor da variável '$id'.
$stmt->execute([':id'=>$id]);

// Recupera a primeira linha do resultado da consulta e a armazena na variável '$c'.
$c = $stmt->fetch();

// Se nenhuma categoria for encontrada com o ID especificado, redireciona o usuário para a página de listagem de categorias e encerra o script.
if(!$c){ 
    header('Location: index.php'); 
    exit; 
}

// Inicializa a variável de erro como uma string vazia para armazenar eventuais mensagens de validação.
$erro = '';

// Verifica se o método de requisição HTTP é POST, o que indica que o formulário de edição foi submetido.
if($_SERVER['REQUEST_METHOD'] === 'POST'){
    // Obtém o nome da categoria enviado pelo formulário, removendo espaços em branco no início e no fim.
    $nome = trim($_POST['nome']);
    
    // Obtém a cor associada à categoria enviada pelo formulário.
    $cor  = $_POST['cor'];
    
    // Obtém a quantidade de dias para alerta de vencimento do produto da categoria, convertendo para inteiro.
    $dias = intval($_POST['dias_alerta_vencimento']);
    
    // Valida se o campo 'nome' está vazio. Se estiver, define uma mensagem de erro.
    if(!$nome){ 
        $erro = 'Nome obrigatório.'; 
    }
    // Caso o nome tenha sido preenchido corretamente, executa a atualização no banco de dados.
    else {
        // Prepara a instrução SQL para atualizar o nome, a cor e os dias de alerta de vencimento da categoria específica.
        $conexao->prepare("UPDATE categorias SET nome=:n, cor=:c, dias_alerta_vencimento=:d WHERE id=:id")
            // Executa a instrução preparada vinculando os parâmetros e efetuando a alteração.
            ->execute([':n'=>$nome,':c'=>$cor,':d'=>$dias,':id'=>$id]);
        
        // Redireciona o usuário de volta para a lista de categorias com um parâmetro de mensagem de sucesso e encerra o script.
        header('Location: index.php?msg=editado'); 
        exit;
    }
    
    // Mescla os dados atuais da categoria com os dados enviados via POST para preservar o preenchimento do formulário em caso de erro de validação.
    $c = array_merge($c, $_POST);
}

// Define a página atual como 'configuracoes' para controle de menu ativo no cabeçalho.
$pagina_atual = 'configuracoes'; 

// Define o título da página que será renderizado na aba do navegador.
$titulo_pagina = 'Editar Categoria';

// Inclui o arquivo de cabeçalho padrão do painel administrativo.
include '../_header.php';
?>
<!-- Estrutura principal de conteúdo da página -->
<div class="content">
    <!-- Cabeçalho da página contendo o título e botão de retorno -->
    <div class="page-header">
        <!-- Lado esquerdo com o título e o nome da categoria que está sendo editada -->
        <div class="page-header-left">
            <h2>Editar Categoria</h2>
            <!-- Exibe o nome da categoria sanitizado para evitar ataques XSS -->
            <p><?= htmlspecialchars($c['nome']) ?></p>
        </div>
        <!-- Link de navegação para voltar para a lista de categorias -->
        <a href="index.php" class="btn btn-secondary">← Voltar</a>
    </div>
    
    <!-- Verifica se existe alguma mensagem de erro de validação e a exibe em um alerta HTML -->
    <?php if($erro): ?>
        <div class="alert alert-danger"><?= $erro ?></div>
    <?php endif; ?>
    
    <!-- Cartão contendo o formulário de edição de dados -->
    <div class="form-card">
        <!-- Formulário que submete os dados via método POST para a mesma página -->
        <form method="POST">
            <!-- Grid estrutural de campos do formulário -->
            <div class="form-grid">
                <!-- Campo para preenchimento do Nome da Categoria (ocupa largura total) -->
                <div class="form-group full">
                    <label>Nome *</label>
                    <!-- Campo de texto com o valor atual preenchido e sanitizado contra XSS -->
                    <input type="text" name="nome" value="<?= htmlspecialchars($c['nome']) ?>" required>
                </div>
                
                <!-- Campo para escolha da Cor de identificação da categoria -->
                <div class="form-group">
                    <label>Cor</label>
                    <!-- Seletor de cor HTML5 com estilo embutido para altura e espaçamento -->
                    <input type="color" name="cor" value="<?= $c['cor'] ?>" style="height:42px;padding:4px 8px">
                </div>
                
                <!-- Campo numérico para definir quantos dias antes do vencimento o alerta deve ser ativado -->
                <div class="form-group">
                    <label>Dias de Alerta</label>
                    <!-- Campo de número com restrição de mínimo de 1 e máximo de 30 dias -->
                    <input type="number" name="dias_alerta_vencimento" min="1" max="30" value="<?= $c['dias_alerta_vencimento'] ?>">
                </div>
            </div>
            
            <!-- Botões de ação do formulário -->
            <div class="form-actions">
                <!-- Botão para enviar os dados e salvar as alterações -->
                <button type="submit" class="btn btn-primary">Salvar</button>
                <!-- Botão para cancelar a edição e retornar para a lista de categorias -->
                <a href="index.php" class="btn btn-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>
<?php 
// Inclui o arquivo de rodapé padrão do painel administrativo.
include '../_footer.php'; 
?>