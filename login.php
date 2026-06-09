<?php
// Inicia ou retoma a sessão ativa do PHP para permitir a gravação e leitura de variáveis de sessão
session_start();

// Inclui o arquivo de conexão com o banco de dados para que possamos realizar a busca do usuário
include 'conexao.php';

// Recebe o valor do campo de login enviado via método POST a partir do formulário de login (geralmente o email)
$login = $_POST['login'];

// Recebe a senha digitada pelo usuário enviada via método POST
$senha = $_POST['senha'];

// Prepara a consulta SQL para buscar todas as informações do usuário cujo email seja igual ao parâmetro informado
// O uso do placeholder :login é uma boa prática para evitar falhas de segurança do tipo SQL Injection
$sql = "SELECT * FROM usuarios WHERE email = :login";

// Prepara a query no banco de dados através da conexão PDO
$smt = $conexao->prepare($sql);

// Associa o valor recebido na variável $login ao parâmetro de substituição :login na query SQL
$smt->bindParam(':login', $login);

// Executa a instrução SQL no banco de dados
$smt->execute();

// Verifica se a consulta retornou ao menos um registro de usuário correspondente ao email informado
if($smt->rowCount() > 0){
    // Recupera a linha retornada como um array associativo contendo os dados do usuário
    $usuario = $smt->fetch();
    
    // Compara a senha digitada pelo usuário com o hash de senha salvo de forma segura no banco de dados
    if(password_verify($senha, $usuario['senha'])){
        // Se a senha estiver correta, define a variável de sessão 'logado' como true para liberar o acesso ao sistema
        $_SESSION['logado']       = true;
        
        // Armazena o ID do usuário na sessão para identificar quem está logado em outras páginas
        $_SESSION['usuario_id']   = $usuario['id'];
        
        // Armazena o nome do usuário na sessão para personalizações de interface (boas-vindas, etc)
        $_SESSION['usuario_nome'] = $usuario['nome'];
        
        // Redireciona o fluxo de navegação do usuário para a página principal do dashboard do sistema
        header('Location: dashboard/index.php');
    } else {
        // Caso a senha seja inválida, redireciona o usuário de volta para a página inicial com o código de erro 1 via GET
        header('Location: index.php?erro=1');
    }
} else {
    // Caso o email digitado não exista no banco de dados, redireciona o usuário para a página inicial com erro 1 via GET
    header('Location: index.php?erro=1');
}
?>