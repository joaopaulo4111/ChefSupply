<?php
session_start();
if(!isset($_SESSION['logado']) || !$_SESSION['logado']){
    header('Location: ../index.php');
    exit;
}
require_once '../conexao.php';

$id = intval($_GET['id'] ?? 0);
if (!$id) {
    header('Location: index.php');
    exit;
}

// Fetch batch (lote) details
$stmt = $conexao->prepare("SELECT * FROM lotes WHERE id = :id");
$stmt->execute([':id' => $id]);
$lote = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$lote) {
    header('Location: index.php?erro=nao_encontrado');
    exit;
}

// Security & Integrity check:
// Block reversal if any quantity from this batch has already been consumed or discarded (remaining < initial)
if (floatval($lote['quantidade_restante']) < floatval($lote['quantidade'])) {
    header('Location: index.php?erro=consumido');
    exit;
}

try {
    $conexao->beginTransaction();

    // 1. Subtract batch quantity from the product stock level and recalculate its status
    $stmtProd = $conexao->prepare("
        UPDATE produtos 
        SET estoque_atual = GREATEST(0, estoque_atual - :qtd),
            status = CASE 
                WHEN GREATEST(0, estoque_atual - :qtd_c1) <= 0 THEN 'Crítico'
                WHEN GREATEST(0, estoque_atual - :qtd_c2) <= estoque_minimo THEN 'Baixo'
                WHEN estoque_maximo > 0 AND GREATEST(0, estoque_atual - :qtd_c3) >= estoque_maximo THEN 'Alto'
                ELSE 'Normal'
            END
        WHERE id = :id
    ");
    $stmtProd->execute([
        ':qtd' => $lote['quantidade'],
        ':qtd_c1' => $lote['quantidade'],
        ':qtd_c2' => $lote['quantidade'],
        ':qtd_c3' => $lote['quantidade'],
        ':id' => $lote['produto_id']
    ]);

    // 2. Delete the batch (lote) record
    $stmtDelete = $conexao->prepare("DELETE FROM lotes WHERE id = :id");
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
