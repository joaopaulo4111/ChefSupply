<?php
session_start();
include 'conexao.php';

$login = $_POST['login'];
$senha = $_POST['senha'];

$sql = "SELECT * FROM usuarios WHERE email = :login";
$smt = $conexao->prepare($sql);
$smt->bindParam(':login', $login);
$smt->execute();

if($smt->rowCount() > 0){
    $usuario = $smt->fetch();
    if(password_verify($senha, $usuario['senha'])){
        $_SESSION['logado']       = true;
        $_SESSION['usuario_id']   = $usuario['id'];
        $_SESSION['usuario_nome'] = $usuario['nome'];
        header('Location: dashboard/index.php');
    } else {
        header('Location: index.php?erro=1');
    }
} else {
    header('Location: index.php?erro=1');
}
?>