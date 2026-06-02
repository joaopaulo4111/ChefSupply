<?php
session_start();
include 'conexao.php';

$nome  = $_POST['nome'];
$email = $_POST['email'];
$senha = password_hash($_POST['senha'], PASSWORD_DEFAULT);

// Verifica se email já existe
$check = $conexao->prepare("SELECT id FROM usuarios WHERE email = :email");
$check->bindParam(':email', $email);
$check->execute();

if($check->rowCount() > 0){
    header('Location: index.php?erro=2');
    exit;
}

// Insere novo usuário com perfil padrão (5 = Cozinheiro, ou mude para o que quiser)
$sql = "INSERT INTO usuarios (nome, email, senha, perfil_id, status) VALUES (:nome, :email, :senha, 1, 'ativo')";
$smt = $conexao->prepare($sql);
$smt->bindParam(':nome',  $nome);
$smt->bindParam(':email', $email);
$smt->bindParam(':senha', $senha);
$smt->execute();

$_SESSION['logado']       = true;
$_SESSION['usuario_id']   = $conexao->lastInsertId();
$_SESSION['usuario_nome'] = $nome;

header('Location: produtos/index.php');
?>