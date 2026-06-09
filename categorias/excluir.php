<?php
session_start();
if(!isset($_SESSION['logado']) || !$_SESSION['logado']){ header('Location: ../index.php'); exit; }
include '../conexao.php';
$id = intval($_GET['id'] ?? 0);
if($id){
    $check = $conexao->prepare("SELECT COUNT(*) FROM produtos WHERE categoria_id=:id");
    $check->execute([':id'=>$id]);
    if($check->fetchColumn() > 0){ header('Location: index.php?erro=vinculado'); exit; }
    $conexao->prepare("DELETE FROM categorias WHERE id=:id")->execute([':id'=>$id]);
}
header('Location: index.php?msg=excluido'); exit;