<?php
$host = "127.0.0.1";
$user = "root";
$porta = "3306";
$password = "Dev@12345";
$db = "chefsupply";


$conexao = new PDO(
    'mysql:host=' . $host . ';
        port=' . $porta . ';
        dbname=' . $db,
    $user,
    $password
);


?>