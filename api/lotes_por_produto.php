<?php
session_start();
if(!isset($_SESSION['logado']) || !$_SESSION['logado']){ http_response_code(401); exit; }
include '../conexao.php';

header('Content-Type: application/json');
$pid = intval($_GET['produto_id'] ?? 0);
if(!$pid){ echo '[]'; exit; }

$smt = $conexao->prepare("SELECT id, codigo_lote, quantidade_restante, data_vencimento
    FROM lotes WHERE produto_id=:pid AND status='ativo' AND quantidade_restante>0
    ORDER BY data_vencimento ASC");
$smt->execute([':pid'=>$pid]);
echo json_encode($smt->fetchAll(PDO::FETCH_ASSOC));