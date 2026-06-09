<?php
// Inicia a sessão PHP para verificar a autenticação do usuário.
session_start();

// Verifica se a variável de sessão 'logado' não está definida ou se seu valor é falso.
// Se o usuário não estiver autenticado, redireciona para a página de login/índice no diretório raiz e finaliza a execução.
if(!isset($_SESSION['logado']) || !$_SESSION['logado']){
    header('Location: ../index.php');
    exit;
}

// Inclui o arquivo de conexão com o banco de dados (PDO) usando require_once para garantir que o arquivo seja importado uma única vez.
require_once '../conexao.php';

// Obtém o ID do descarte a ser excluído/estornado via parâmetro URL (GET), convertendo para número inteiro.
$id = intval($_GET['id'] ?? 0);

// Se o ID for inválido (igual a 0), redireciona o usuário para a página de listagem de descartes e finaliza a execução.
if(!$id){
    header('Location: index.php');
    exit;
}

// Prepara uma consulta SQL para buscar os detalhes do registro de descarte que se deseja estornar.
$stmt = $conexao->prepare("SELECT * FROM descartes WHERE id = :id");

// Executa a consulta vinculando o parâmetro ':id' com o ID do descarte obtido.
$stmt->execute([':id' => $id]);

// Recupera os dados do descarte como um array associativo.
$descarte = $stmt->fetch(PDO::FETCH_ASSOC);

// Caso o descarte não seja localizado no banco de dados, redireciona o usuário com uma mensagem de erro e encerra o script.
if(!$descarte){
    header('Location: index.php?erro=nao_encontrado');
    exit;
}

// Inicia um bloco try-catch para gerenciar a transação no banco de dados e garantir a integridade dos dados em caso de falhas.
try {
    // Inicia a transação. Qualquer erro a partir daqui cancelará todas as alterações feitas nesta execução (rollback).
    $conexao->beginTransaction();

    // 1. Reverte a redução de estoque do produto associado ao descarte e atualiza o seu status operacional.
    // O estoque atual do produto é incrementado com a quantidade que havia sido descartada.
    // O status do produto é recalculado com base no novo estoque em comparação com os limites mínimo e máximo.
    $stmtProd = $conexao->prepare("
        UPDATE produtos 
        SET estoque_atual = estoque_atual + :qtd,
            status = CASE 
                WHEN (estoque_atual + :qtd_c1) <= 0 THEN 'Crítico'
                WHEN (estoque_atual + :qtd_c2) <= estoque_minimo THEN 'Baixo'
                WHEN estoque_maximo > 0 AND (estoque_atual + :qtd_c3) >= estoque_maximo THEN 'Alto'
                ELSE 'Normal'
            END
        WHERE id = :id
    ");
    
    // Executa a atualização do produto passando o ID do produto e a quantidade estornada em múltiplas ligações para o CASE.
    $stmtProd->execute([
        ':qtd' => $descarte['quantidade'],
        ':qtd_c1' => $descarte['quantidade'],
        ':qtd_c2' => $descarte['quantidade'],
        ':qtd_c3' => $descarte['quantidade'],
        ':id' => $descarte['produto_id']
    ]);

    // 2. Reverte a redução de estoque no lote do produto, se houver um lote associado ao descarte.
    // Incrementa a quantidade restante no lote e altera o status do lote de volta para 'ativo'.
    if ($descarte['lote_id']) {
        $stmtLote = $conexao->prepare("
            UPDATE lotes 
            SET quantidade_restante = quantidade_restante + :qtd,
                status = 'ativo'
            WHERE id = :id
        ");
        
        // Executa a reativação/atualização do lote.
        $stmtLote->execute([
            ':qtd' => $descarte['quantidade'],
            ':id' => $descarte['lote_id']
        ]);
    }

    // 3. Exclui definitivamente o registro de descarte da tabela correspondente.
    $stmtDelete = $conexao->prepare("DELETE FROM descartes WHERE id = :id");
    
    // Executa a deleção informando o ID do descarte.
    $stmtDelete->execute([':id' => $id]);

    // Confirma todas as operações da transação no banco de dados (commit).
    $conexao->commit();
    
    // Redireciona o usuário para a listagem com mensagem de sucesso indicando que o descarte foi estornado.
    header('Location: index.php?msg=estornado');
    exit;
    
} catch (Exception $e) {
    // Caso ocorra qualquer exceção durante a execução das queries, verifica se há uma transação activa.
    if ($conexao->inTransaction()) {
        // Desfaz todas as alterações realizadas desde o beginTransaction() para evitar dados corrompidos.
        $conexao->rollBack();
    }
    
    // Redireciona o usuário para a página de listagem com mensagem de erro contendo os detalhes do problema.
    header('Location: index.php?erro=falha_estorno&detalhe=' . urlencode($e->getMessage()));
    exit;
}
