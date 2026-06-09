<?php
session_start();
if(!isset($_SESSION['logado']) || !$_SESSION['logado']){
    header('Location: ../index.php');
    exit;
}
require_once '../conexao.php';

$id = intval($_GET['id'] ?? 0);

if ($id) {
    try {
        // Prevent deletion if the supplier has delivered batches in the system
        $checkLotes = $conexao->prepare("SELECT COUNT(*) FROM lotes WHERE fornecedor_id = :id");
        $checkLotes->execute([':id' => $id]);
        
        if ($checkLotes->fetchColumn() > 0) {
            header('Location: index.php?erro=vinculado');
            exit;
        }

        // Safe delete supplier
        $delete = $conexao->prepare("DELETE FROM fornecedores WHERE id = :id");
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
