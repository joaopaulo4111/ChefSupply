<?php
    // Inicia ou retoma a sessão ativa do PHP para permitir a manipulação das variáveis de sessão existentes
    session_start();
    
    // Destrói todos os dados associados à sessão atual do usuário, limpando o estado de login
    session_destroy();
    
    // Redireciona o usuário para a página de login/entrada (index.php) após encerrar a sessão
    header('Location:index.php');
    
    // Interrompe a execução do script PHP imediatamente para garantir que nenhum código posterior seja executado
    exit;
?>