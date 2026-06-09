<?php
// Define o endereço do servidor de banco de dados (neste caso, o localhost através do IP 127.0.0.1)
$host = "127.0.0.1";

// Define o nome de usuário utilizado para se conectar ao banco de dados MySQL (padrão 'root' no XAMPP)
$user = "root";

// Define a porta de rede utilizada pelo serviço do MySQL (a porta padrão é a 3306)
$porta = "3306";

// Define a senha de acesso ao banco de dados (configurada como 'ceub123456')
$password = "ceub123456";

// Define o nome do banco de dados ao qual a aplicação irá se conectar ('chefsupply')
$db = "chefsupply";

// Tenta estabelecer uma conexão com o banco de dados usando a extensão PDO (PHP Data Objects)
// O PDO é uma interface consistente para acessar bancos de dados no PHP, oferecendo segurança e facilidade
$conexao = new PDO(
    // Define a string de conexão (DSN - Data Source Name) contendo o driver do banco (mysql), o host e a porta
    'mysql:host=' . $host . ';
        port=' . $porta . ';
        dbname=' . $db, // Define o nome do banco de dados na string de conexão
    $user,              // Passa o nome de usuário como o segundo parâmetro
    $password           // Passa a senha de acesso como o terceiro parâmetro
);

// Define o modo de erro do PDO para lançar exceções em caso de falha nas operações SQL
// Isso ajuda no tratamento de erros com blocos try/catch e na depuração do código
$conexao->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Define o charset de comunicação padrão para utf8 para evitar problemas de codificação e acentuação no banco
$conexao->exec("set names utf8");
?>