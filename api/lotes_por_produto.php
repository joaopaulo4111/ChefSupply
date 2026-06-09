<?php
// Inicia ou retoma a sessão ativa do PHP para permitir a verificação das variáveis do usuário logado
session_start();

// Verifica se a variável de sessão 'logado' não está definida ou é falsa
if(!isset($_SESSION['logado']) || !$_SESSION['logado']){ 
    // Define o código de resposta HTTP do servidor como 401 (Não Autorizado) para fins de segurança de API
    http_response_code(401); 
    
    // Interrompe imediatamente a execução do script
    exit; 
}

// Inclui o arquivo de conexão com o banco de dados local (navegando uma pasta acima)
include '../conexao.php';

// Define o cabeçalho (header) HTTP da resposta para que o cliente saiba que está recebendo dados em formato JSON
header('Content-Type: application/json');

// Obtém o parâmetro 'produto_id' enviado na URL via método GET. Usa a função intval() para garantir que seja um valor inteiro seguro
$pid = intval($_GET['produto_id'] ?? 0);

// Se o ID do produto ($pid) for igual a zero ou inválido, retorna um array JSON vazio e interrompe o script
if(!$pid){ 
    echo '[]'; 
    exit; 
}

// Prepara a instrução SQL para selecionar os lotes correspondentes ao produto informado
// Apenas seleciona lotes com status 'ativo' e com quantidade restante em estoque maior que zero
// Ordena a listagem por data de vencimento de forma ascendente (ASC) - exibindo primeiro os lotes mais próximos do vencimento
$smt = $conexao->prepare("SELECT id, codigo_lote, quantidade_restante, data_vencimento
    FROM lotes WHERE produto_id=:pid AND status='ativo' AND quantidade_restante>0
    ORDER BY data_vencimento ASC");

// Executa a instrução preparada no banco de dados, mapeando o placeholder ':pid' para a variável $pid
$smt->execute([':pid'=>$pid]);

// Recupera todas as linhas encontradas como um array associativo (PDO::FETCH_ASSOC)
// Em seguida, codifica esse array em formato JSON e o imprime na resposta HTTP
echo json_encode($smt->fetchAll(PDO::FETCH_ASSOC));
?>