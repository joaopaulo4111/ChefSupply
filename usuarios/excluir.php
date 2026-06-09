<?php
session_start();
if(!isset($_SESSION['logado']) || !$_SESSION['logado']){
    header('Location: ../index.php');
    exit;
}
require_once '../conexao.php';

$id = intval($_GET['id'] ?? 0);

if ($id) {
    // Safety check: Prevent deleting your own active session account
    if ($id === intval($_SESSION['usuario_id'] ?? 0)) {
        header('Location: index.php?erro=auto_exclusao');
        exit;
    }

    try {
        // Safe delete: references in lotes, descartes, and relatorios will ON DELETE SET NULL as configured in the DB
        $delete = $conexao->prepare("DELETE FROM usuarios WHERE id = :id");
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
