<?php
session_start();
include 'conexao.php';

$nome = trim($_POST['nome'] ?? '');
$email = trim($_POST['email'] ?? '');
$senha = trim($_POST['senha'] ?? '');

// Validação de campos obrigatórios
if (empty($nome) || empty($email) || empty($senha)) {
    header('Location: index.php?erro=3');
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Location: index.php?erro=4');
    exit;
}

if (strlen($senha) < 6) {
    header('Location: index.php?erro=5');
    exit;
}

$senha = password_hash($senha, PASSWORD_DEFAULT);

$check = $conexao->prepare("SELECT id FROM usuarios WHERE email = :email");
$check->bindParam(':email', $email);
$check->execute();

if ($check->rowCount() > 0) {
    header('Location: index.php?erro=2');
    exit;
}

$sql = "INSERT INTO usuarios (nome, email, senha, perfil_id, status) VALUES (:nome, :email, :senha, 1, 'ativo')";
$smt = $conexao->prepare($sql);
$smt->bindParam(':nome', $nome);
$smt->bindParam(':email', $email);
$smt->bindParam(':senha', $senha);
$smt->execute();

$_SESSION['logado'] = true;
$_SESSION['usuario_id'] = $conexao->lastInsertId();
$_SESSION['usuario_nome'] = $nome;

header('Location: dashboard/index.php');
?>
<?php
session_start();
include 'conexao.php';

$nome = $_POST['nome'];
$email = $_POST['email'];
$senha = password_hash($_POST['senha'], PASSWORD_DEFAULT);

// Verifica se email já existe
$check = $conexao->prepare("SELECT id FROM usuarios WHERE email = :email");
$check->bindParam(':email', $email);
$check->execute();

if ($check->rowCount() > 0) {
    header('Location: index.php?erro=2');
    exit;
}

// Insere novo usuário com perfil padrão (5 = Cozinheiro, ou mude para o que quiser)
$sql = "INSERT INTO usuarios (nome, email, senha, perfil_id, status) VALUES (:nome, :email, :senha, 1, 'ativo')";
$smt = $conexao->prepare($sql);
$smt->bindParam(':nome', $nome);
$smt->bindParam(':email', $email);
$smt->bindParam(':senha', $senha);
$smt->execute();

$_SESSION['logado'] = true;
$_SESSION['usuario_id'] = $conexao->lastInsertId();
$_SESSION['usuario_nome'] = $nome;

header('Location: produtos/index.php');
?>