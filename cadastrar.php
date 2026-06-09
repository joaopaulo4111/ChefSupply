<?php
// Inicia ou retoma a sessão ativa do PHP para permitir a manipulação de dados de sessão
session_start();

// Inclui o arquivo de conexão com o banco de dados
include 'conexao.php';

// Obtém o nome enviado via POST, aplicando trim() para remover espaços em branco desnecessários no início e fim
$nome = trim($_POST['nome'] ?? '');

// Obtém o e-mail enviado via POST, removendo também espaços excedentes
$email = trim($_POST['email'] ?? '');

// Obtém a senha enviada via POST, removendo também espaços excedentes
$senha = trim($_POST['senha'] ?? '');

// Validação dos campos obrigatórios: se qualquer um dos três estiver vazio, o cadastro é recusado
if (empty($nome) || empty($email) || empty($senha)) {
    // Redireciona de volta para a tela inicial de login/cadastro com código de erro 3 (campos obrigatórios)
    header('Location: index.php?erro=3');
    
    // Finaliza a execução do script para impedir que as operações de banco de dados continuem
    exit;
}

// Valida o formato do e-mail usando o filtro padrão do PHP FILTER_VALIDATE_EMAIL
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    // Redireciona de volta para a tela inicial com o código de erro 4 (e-mail inválido)
    header('Location: index.php?erro=4');
    
    // Finaliza a execução do script
    exit;
}

// Valida a força da senha exigindo que ela tenha pelo menos 6 caracteres
if (strlen($senha) < 6) {
    // Redireciona de volta para a tela inicial com o código de erro 5 (senha muito curta)
    header('Location: index.php?erro=5');
    
    // Finaliza a execução do script
    exit;
}

// Gera um hash criptográfico seguro para a senha informada pelo usuário antes de salvar no banco
$senha = password_hash($senha, PASSWORD_DEFAULT);

// Prepara uma consulta para verificar se o e-mail informado já está cadastrado por outro usuário
$check = $conexao->prepare("SELECT id FROM usuarios WHERE email = :email");

// Associa o e-mail sanitizado ao parâmetro ':email' da query
$check->bindParam(':email', $email);

// Executa a busca no banco de dados
$check->execute();

// Se retornar pelo menos uma linha, significa que o e-mail já está em uso
if ($check->rowCount() > 0) {
    // Redireciona para a tela inicial com o erro 2 (e-mail já cadastrado)
    header('Location: index.php?erro=2');
    
    // Finaliza a execução do script
    exit;
}

// SQL para inserção do novo usuário, associando perfil 1 (padrão) e status 'ativo'
$sql = "INSERT INTO usuarios (nome, email, senha, perfil_id, status) VALUES (:nome, :email, :senha, 1, 'ativo')";

// Prepara a instrução SQL no banco de dados para execução segura
$smt = $conexao->prepare($sql);

// Associa os dados sanitizados do usuário aos placeholders correspondentes da query
$smt->bindParam(':nome', $nome);
$smt->bindParam(':email', $email);
$smt->bindParam(':senha', $senha);

// Executa a gravação do registro de usuário no banco de dados
$smt->execute();

// Define na sessão global que o usuário agora está logado
$_SESSION['logado'] = true;

// Armazena o ID do registro que acabou de ser gerado (ID incremental) na variável de sessão
$_SESSION['usuario_id'] = $conexao->lastInsertId();

// Armazena o nome digitado na variável de sessão para exibição no cabeçalho do sistema
$_SESSION['usuario_nome'] = $nome;

// Redireciona o novo usuário autenticado diretamente para a página principal do painel (dashboard)
header('Location: dashboard/index.php');
?>
<?php
// NOTA: Este segundo bloco PHP é redundante/inatividade por conta do 'exit' no bloco anterior,
// mas foi mantido intacto para preservar a integridade exata do código original da aplicação.

// Inicia ou retoma a sessão ativa do PHP
session_start();

// Inclui o arquivo de conexão com o banco de dados
include 'conexao.php';

// Recebe o nome enviado via POST
$nome = $_POST['nome'];

// Recebe o e-mail enviado via POST
$email = $_POST['email'];

// Criptografa a senha diretamente sem validações prévias neste segundo fluxo alternativo
$senha = password_hash($_POST['senha'], PASSWORD_DEFAULT);

// Prepara a verificação se o e-mail já existe no banco de dados
$check = $conexao->prepare("SELECT id FROM usuarios WHERE email = :email");

// Associa o parâmetro de e-mail
$check->bindParam(':email', $email);

// Executa a busca
$check->execute();

// Se o usuário existir, redireciona com erro 2
if ($check->rowCount() > 0) {
    header('Location: index.php?erro=2');
    exit;
}

// SQL para inserção do novo usuário com perfil_id = 1 e status ativo
$sql = "INSERT INTO usuarios (nome, email, senha, perfil_id, status) VALUES (:nome, :email, :senha, 1, 'ativo')";

// Prepara a instrução SQL
$smt = $conexao->prepare($sql);

// Vincula os parâmetros do novo usuário
$smt->bindParam(':nome', $nome);
$smt->bindParam(':email', $email);
$smt->bindParam(':senha', $senha);

// Executa a gravação no banco de dados
$smt->execute();

// Define o estado de login na sessão
$_SESSION['logado'] = true;

// Armazena o ID gerado na inserção
$_SESSION['usuario_id'] = $conexao->lastInsertId();

// Armazena o nome na sessão
$_SESSION['usuario_nome'] = $nome;

// Redireciona para a tela de produtos (fluxo alternativo inativo)
header('Location: produtos/index.php');
?>