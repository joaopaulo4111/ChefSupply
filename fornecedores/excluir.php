<?php
// Inicia a sessão para validar a autenticação do usuário logado no sistema.
session_start();

// Verifica se o usuário não está autenticado (variável de sessão 'logado' vazia ou falsa).
if(!isset($_SESSION['logado']) || !$_SESSION['logado']){
    // Redireciona o usuário para a página de login/inicial.
    header('Location: ../index.php');
    // Encerra imediatamente a execução deste script PHP.
    exit;
}

// Inclui o arquivo de conexão com a base de dados utilizando o PDO.
// require_once evita inclusões duplicadas do mesmo arquivo.
require_once '../conexao.php';

// Captura e limpa o ID do fornecedor enviado via método GET da URL, convertendo-o em um número inteiro.
$id = intval($_GET['id'] ?? 0);

// Verifica se um ID de fornecedor válido (diferente de zero) foi informado:
if ($id) {
    try {
        // Regra de Integridade Referencial: Impede a exclusão se houver lotes vinculados a este fornecedor.
        // Prepara a instrução SQL para contar lotes que dependem do ID do fornecedor em questão.
        $checkLotes = $conexao->prepare("SELECT COUNT(*) FROM lotes WHERE fornecedor_id = :id");
        // Executa a verificação passando o parâmetro de forma segura.
        $checkLotes->execute([':id' => $id]);
        
        // Se a quantidade de lotes encontrados for maior que zero (há dependências históricas/insumos em estoque):
        if ($checkLotes->fetchColumn() > 0) {
            // Redireciona para a página de listagem com um parâmetro indicando o erro "vinculado".
            header('Location: index.php?erro=vinculado');
            // Encerra a execução imediata.
            exit;
        }

        // Caso não existam lotes vinculados, realiza a exclusão segura do fornecedor.
        // Prepara a instrução SQL de exclusão pelo ID correspondente.
        $delete = $conexao->prepare("DELETE FROM fornecedores WHERE id = :id");
        // Executa a deleção no banco de dados.
        $delete->execute([':id' => $id]);

        // Redireciona de volta para a listagem enviando uma mensagem de sucesso indicando "excluido".
        header('Location: index.php?msg=excluido');
        // Finaliza o processamento.
        exit;
    } catch (Exception $e) {
        // Se ocorrer qualquer exceção inesperada durante a transação com o banco de dados:
        // Redireciona com um parâmetro de erro de deleção genérico.
        header('Location: index.php?erro=erro_delecao');
        // Encerra a execução.
        exit;
    }
}

// Se nenhum ID de fornecedor válido foi passado via parâmetro GET:
// Redireciona o usuário de volta para a listagem padrão de fornecedores.
header('Location: index.php');
// Finaliza a execução segura do script.
exit;
