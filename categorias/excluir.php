<?php
// Inicia a sessão PHP para verificar se o usuário está autenticado.
session_start();

// Verifica se a variável de sessão 'logado' não está definida ou se seu valor é falso.
// Caso o usuário não esteja autenticado, redireciona-o para a página de login/índice na raiz e encerra o script de imediato.
if(!isset($_SESSION['logado']) || !$_SESSION['logado']){ 
    header('Location: ../index.php'); 
    exit; 
}

// Inclui o arquivo de conexão com o banco de dados (PDO) para realizar a verificação e exclusão da categoria.
include '../conexao.php';

// Obtém o parâmetro 'id' enviado via URL (método GET), convertendo-o para um número inteiro.
// Se não houver 'id' na URL, define o valor padrão como 0.
$id = intval($_GET['id'] ?? 0);

// Verifica se o ID fornecido é válido (ou seja, se é maior que zero).
if($id){
    // Prepara uma consulta SQL para contar quantos produtos estão vinculados a essa categoria específica.
    // Isso evita a exclusão de categorias que possuem produtos associados, mantendo a integridade referencial.
    $check = $conexao->prepare("SELECT COUNT(*) FROM produtos WHERE categoria_id=:id");
    
    // Executa a consulta de verificação associando o parâmetro ':id' ao ID obtido.
    $check->execute([':id'=>$id]);
    
    // Se a contagem de produtos vinculados for maior que zero, impede a exclusão.
    // Redireciona o usuário para a página principal de categorias passando o parâmetro de erro 'vinculado' e encerra o script.
    if($check->fetchColumn() > 0){ 
        header('Location: index.php?erro=vinculado'); 
        exit; 
    }
    
    // Caso não existam produtos vinculados à categoria, prepara e executa a exclusão física do registro no banco de dados.
    $conexao->prepare("DELETE FROM categorias WHERE id=:id")->execute([':id'=>$id]);
}

// Redireciona o usuário de volta para a lista de categorias com a mensagem de sucesso 'excluido' e encerra o script.
header('Location: index.php?msg=excluido'); 
exit;