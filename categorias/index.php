<?php
// Inicia a sessão para controle de autenticação do usuário.
session_start();

// Verifica se a variável de sessão 'logado' não está definida ou se seu valor é falso.
// Caso o usuário não esteja autenticado, redireciona para a página de login/índice no diretório raiz e encerra a execução do script.
if(!isset($_SESSION['logado']) || !$_SESSION['logado']){ 
    header('Location: ../index.php'); 
    exit; 
}

// Inclui o arquivo de conexão com o banco de dados (PDO) para realizar consultas.
include '../conexao.php';

// Realiza uma consulta SQL para obter todas as categorias cadastradas, agregando a contagem de produtos vinculados a cada uma.
// Utiliza LEFT JOIN com a tabela de produtos para incluir mesmo as categorias que não possuem produtos vinculados.
// Agrupa pelo ID da categoria e ordena alfabeticamente pelo nome.
// fetchAll() recupera todos os registros de uma vez e os armazena na variável '$categorias'.
$categorias = $conexao->query("SELECT c.*, COUNT(p.id) as total_produtos
    FROM categorias c LEFT JOIN produtos p ON p.categoria_id = c.id
    GROUP BY c.id ORDER BY c.nome")->fetchAll();

// Define a variável '$pagina_atual' para destacar o menu de configurações na navegação do cabeçalho.
$pagina_atual = 'configuracoes'; 

// Define o título da página para renderização no cabeçalho/aba do navegador.
$titulo_pagina = 'Categorias';

// Inclui o arquivo de cabeçalho padrão do painel administrativo.
include '../_header.php';
?>
<!-- Estrutura principal do conteúdo da página de listagem de categorias -->
<div class="content">
    <!-- Cabeçalho da seção com o título e botão para criar uma nova categoria -->
    <div class="page-header">
        <div class="page-header-left">
            <h2>Categorias</h2>
            <p>Organize seus produtos por categoria</p>
        </div>
        <!-- Link (botão) que leva para o formulário de cadastro de nova categoria -->
        <a href="nova.php" class="btn btn-primary">+ Nova Categoria</a>
    </div>
    
    <!-- Verifica se o parâmetro 'msg' foi enviado via URL (GET) para exibir um alerta de sucesso correspondente -->
    <?php if(isset($_GET['msg'])): ?>
        <div class="alert alert-success">
            <!-- Condicional ternária para exibir a mensagem correta baseada no valor de 'msg' ('criado', 'editado' ou outro/excluido) -->
            Categoria <?= $_GET['msg']==='criado'?'cadastrada':($_GET['msg']==='editado'?'atualizada':'excluída') ?> com sucesso!
        </div>
    <?php endif; ?>
    
    <!-- Verifica se o parâmetro 'erro' foi enviado via URL (GET) e é igual a 'vinculado' -->
    <!-- Caso positivo, exibe um alerta de perigo informando que a categoria não pôde ser excluída por ter produtos associados -->
    <?php if(isset($_GET['erro']) && $_GET['erro']==='vinculado'): ?>
        <div class="alert alert-danger">Não é possível excluir: existem produtos vinculados a esta categoria.</div>
    <?php endif; ?>
    
    <!-- Cartão contendo a tabela com a listagem de todas as categorias -->
    <div class="table-card">
        <table>
            <thead>
                <!-- Linha de cabeçalho da tabela contendo os nomes das colunas -->
                <tr>
                    <th>Categoria</th>
                    <th>Cor</th>
                    <th>Dias de Alerta</th>
                    <th>Produtos</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
            <!-- Verifica se o array de categorias está vazio -->
            <?php if(empty($categorias)): ?>
                <!-- Caso não exista nenhuma categoria cadastrada, exibe uma linha informativa com um link para criar a primeira -->
                <tr>
                    <td colspan="5" style="text-align:center;padding:40px;color:#aaa">
                        Nenhuma categoria. <a href="nova.php" style="color:#2db35d">Criar a primeira.</a>
                    </td>
                </tr>
            <?php else: ?>
                <!-- Caso existam categorias, percorre cada registro do array e renderiza uma linha na tabela -->
                <?php foreach($categorias as $c): ?>
                    <tr>
                        <!-- Nome da categoria com sanitização para proteção contra injeções de script (XSS) -->
                        <td><strong><?= htmlspecialchars($c['nome']) ?></strong></td>
                        
                        <!-- Coluna que exibe uma bolinha colorida representando a cor definida para a categoria -->
                        <td>
                            <span style="display:inline-block;width:20px;height:20px;border-radius:50%;background:<?= htmlspecialchars($c['cor']) ?>"></span>
                        </td>
                        
                        <!-- Quantidade de dias definidos para alerta de vencimento dos produtos da categoria -->
                        <td><?= $c['dias_alerta_vencimento'] ?> dias</td>
                        
                        <!-- Quantidade total de produtos cadastrados sob esta categoria -->
                        <td><?= $c['total_produtos'] ?></td>
                        
                        <!-- Coluna com as ações disponíveis para a categoria (Editar e Excluir) -->
                        <td style="display:flex;gap:8px">
                            <!-- Link para editar a categoria, enviando o ID via parâmetro GET -->
                            <a href="editar.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-secondary">Editar</a>
                            
                            <!-- A exclusão só é permitida visualmente se a categoria não possuir produtos vinculados (total_produtos == 0) -->
                            <?php if($c['total_produtos'] == 0): ?>
                                <!-- Link para excluir a categoria, enviando o ID e solicitando confirmação JS antes de prosseguir -->
                                <a href="excluir.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Excluir esta categoria?')">Excluir</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php 
// Inclui o arquivo de rodapé padrão do painel administrativo.
include '../_footer.php'; 
?>