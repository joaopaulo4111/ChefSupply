<?php
session_start();
if(!isset($_SESSION['logado']) || !$_SESSION['logado']){
    header('Location: ../index.php');
    exit;
}
require_once '../conexao.php';

$id = intval($_GET['id'] ?? 0);

if($id){
    try {
        // Check if there are active batches (lotes) referencing this product
        $checkLotes = $conexao->prepare("SELECT COUNT(*) FROM lotes WHERE produto_id = :id");
        $checkLotes->execute([':id' => $id]);
        
        if($checkLotes->fetchColumn() > 0){
            header('Location: index.php?erro=vinculado');
            exit;
        }

        // Check if there are discard history records referencing this product
        $checkDescartes = $conexao->prepare("SELECT COUNT(*) FROM descartes WHERE produto_id = :id");
        $checkDescartes->execute([':id' => $id]);
        
        if($checkDescartes->fetchColumn() > 0){
            header('Location: index.php?erro=vinculado');
            exit;
        }

        // Safe delete product since no dependencies exist
        $delete = $conexao->prepare("DELETE FROM produtos WHERE id = :id");
        $delete->execute([':id' => $id]);
        
        header('Location: index.php?msg=excluido');
        exit;
    } catch (Exception $e) {
        header('Location: index.php?erro=erro_delecao');
        exit;
    }
}

header('Location: index.php');
exit;