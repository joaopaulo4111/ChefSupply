<?php
session_start();
if(!isset($_SESSION['logado']) || !$_SESSION['logado']){
    header('Location: ../index.php');
    exit;
}
require_once '../conexao.php';

// Get the descarte ID from the URL
$id = intval($_GET['id'] ?? 0);
if(!$id){
    header('Location: index.php');
    exit;
}

// Fetch the discard record to get quantities, product, and batch IDs
$stmt = $conexao->prepare("SELECT * FROM descartes WHERE id = :id");
$stmt->execute([':id' => $id]);
$descarte = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$descarte){
    header('Location: index.php?erro=nao_encontrado');
    exit;
}

try {
    $conexao->beginTransaction();

    // 1. Revert the product's stock reduction and update its status
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
    $stmtProd->execute([
        ':qtd' => $descarte['quantidade'],
        ':qtd_c1' => $descarte['quantidade'],
        ':qtd_c2' => $descarte['quantidade'],
        ':qtd_c3' => $descarte['quantidade'],
        ':id' => $descarte['produto_id']
    ]);

    // 2. Revert the batch's stock reduction and reactivate it if applicable
    if ($descarte['lote_id']) {
        $stmtLote = $conexao->prepare("
            UPDATE lotes 
            SET quantidade_restante = quantidade_restante + :qtd,
                status = 'ativo'
            WHERE id = :id
        ");
        $stmtLote->execute([
            ':qtd' => $descarte['quantidade'],
            ':id' => $descarte['lote_id']
        ]);
    }

    // 3. Delete the discard record
    $stmtDelete = $conexao->prepare("DELETE FROM descartes WHERE id = :id");
    $stmtDelete->execute([':id' => $id]);

    $conexao->commit();
    header('Location: index.php?msg=estornado');
    exit;
} catch (Exception $e) {
    if ($conexao->inTransaction()) {
        $conexao->rollBack();
    }
    header('Location: index.php?erro=falha_estorno&detalhe=' . urlencode($e->getMessage()));
    exit;
}
