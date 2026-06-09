<?php
// Inicia a sessão PHP para verificar se o usuário está devidamente autenticado no sistema
session_start();

// Verifica se a variável de sessão 'logado' não está definida ou possui valor falso.
// Caso afirmativo, o usuário não tem permissão para acessar esta funcionalidade de exclusão.
if(!isset($_SESSION['logado']) || !$_SESSION['logado']){
    // Redireciona o usuário para a página de login que fica no diretório pai
    header('Location: ../index.php');
    // Interrompe imediatamente o processamento do restante do código PHP
    exit;
}

// Requer e inclui o arquivo de conexão com o banco de dados (inicializa o objeto PDO em $conexao)
require_once '../conexao.php';

// Recupera o identificador do produto enviado pela URL através do parâmetro GET 'id'.
// Converte o valor retornado para um número inteiro utilizando 'intval' para maior segurança.
// Se o parâmetro não estiver presente, assume o valor padrão 0.
$id = intval($_GET['id'] ?? 0);

// Verifica se foi fornecido um ID válido (ou seja, maior do que zero)
if($id){
    try {
        // Primeira verificação de integridade referencial:
        // Prepara uma consulta para contar se existem lotes cadastrados vinculados a este produto.
        $checkLotes = $conexao->prepare("SELECT COUNT(*) FROM lotes WHERE produto_id = :id");
        
        // Executa a consulta de checagem passando o parâmetro de ID correspondente
        $checkLotes->execute([':id' => $id]);
        
        // Se o número de lotes associados a este produto for maior que zero:
        if($checkLotes->fetchColumn() > 0){
            // Redireciona de volta para a listagem informando que o produto está vinculado a lotes e não pode ser excluído
            header('Location: index.php?erro=vinculado');
            // Interrompe a execução do script
            exit;
        }

        // Segunda verificação de integridade referencial:
        // Prepara uma consulta para contar se existem registros de descartes vinculados a este produto.
        $checkDescartes = $conexao->prepare("SELECT COUNT(*) FROM descartes WHERE produto_id = :id");
        
        // Executa a consulta de checagem passando o parâmetro de ID do produto
        $checkDescartes->execute([':id' => $id]);
        
        // Se houver algum registro de descarte associado a este produto no banco de dados:
        if($checkDescartes->fetchColumn() > 0){
            // Redireciona de volta para a listagem informando que o produto está vinculado a descartes e não pode ser excluído
            header('Location: index.php?erro=vinculado');
            // Interrompe a execução do script
            exit;
        }

        // Caso o produto não possua vínculos ativos com lotes ou descartes, realiza a exclusão segura:
        // Prepara o comando SQL DELETE para remover o registro correspondente da tabela 'produtos'
        $delete = $conexao->prepare("DELETE FROM produtos WHERE id = :id");
        
        // Executa o comando de exclusão passando o ID do produto
        $delete->execute([':id' => $id]);
        
        // Se a exclusão for efetuada com sucesso, redireciona o usuário para a listagem de produtos com mensagem de sucesso
        header('Location: index.php?msg=excluido');
        // Interrompe a execução do script
        exit;
    } catch (Exception $e) {
        // Se ocorrer qualquer erro imprevisto durante as consultas ou a exclusão no banco de dados,
        // redireciona o usuário de volta com uma mensagem informativa de erro na deleção.
        header('Location: index.php?erro=erro_delecao');
        // Interrompe a execução do script
        exit;
    }
}

// Se o ID fornecido for inválido (por exemplo, 0 ou não numérico), redireciona o usuário para a listagem sem fazer nada.
header('Location: index.php');
// Finaliza a execução do script
exit;