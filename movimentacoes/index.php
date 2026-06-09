<?php
session_start();
if(!isset($_SESSION['logado']) || !$_SESSION['logado']){ header('Location: ../index.php'); exit; }
include '../conexao.php';

$lotes = $conexao->query("
    SELECT l.*, p.nome as produto_nome, p.unidade, f.nome as fornecedor_nome,
           DATEDIFF(l.data_vencimento, CURDATE()) as dias_para_vencer
    FROM lotes l
    INNER JOIN produtos p ON p.id = l.produto_id
    LEFT JOIN fornecedores f ON f.id = l.fornecedor_id
    WHERE l.status = 'ativo'
    ORDER BY l.data_vencimento ASC LIMIT 100
")->fetchAll();

$pagina_atual = 'estoque'; $titulo_pagina = 'Movimentações';
include '../_header.php';
?>
<div class="content">
    <div class="page-header">
        <div class="page-header-left"><h2>Movimentações de Estoque</h2><p>Lotes ativos ordenados por vencimento</p></div>
        <div style="display:flex;gap:8px">
            <a href="entrada.php" class="btn btn-primary">+ Entrada</a>
            <a href="saida.php"   class="btn btn-secondary">↑ Saída</a>
        </div>
    </div>
    <?php if(isset($_GET['msg'])): ?>
    <div class="alert alert-success"><?= $_GET['msg']==='entrada'?'Entrada':'Saída' ?> registrada com sucesso!</div>
    <?php endif; ?>
    <div class="table-card">
        <table>
            <thead><tr><th>Produto</th><th>Fornecedor</th><th>Lote</th><th>Qtd. Restante</th><th>Entrada</th><th>Vencimento</th><th>Status</th></tr></thead>
            <tbody>
            <?php if(empty($lotes)): ?>
                <tr><td colspan="7" style="text-align:center;padding:40px;color:#aaa">Nenhum lote ativo. <a href="../entradas/nova.php" style="color:#2db35d">Registrar entrada.</a></td></tr>
            <?php else: foreach($lotes as $l):
                $dias = $l['dias_para_vencer'];
                if($dias===null){ $badge='badge-info';    $label='Sem validade'; }
                elseif($dias<0) { $badge='badge-vencido'; $label='Vencido'; }
                elseif($dias<=3){ $badge='badge-critico'; $label="$dias dias"; }
                elseif($dias<=7){ $badge='badge-atencao'; $label="$dias dias"; }
                else            { $badge='badge-normal';  $label="$dias dias"; }
            ?>
                <tr>
                    <td><strong><?= htmlspecialchars($l['produto_nome']) ?></strong> <span style="color:#aaa;font-size:.8rem"><?= $l['unidade'] ?></span></td>
                    <td><?= htmlspecialchars($l['fornecedor_nome'] ?? '—') ?></td>
                    <td><code style="background:#f5f5f5;padding:2px 6px;border-radius:4px;font-size:.8rem"><?= htmlspecialchars($l['codigo_lote'] ?: '#'.$l['id']) ?></code></td>
                    <td><?= number_format($l['quantidade_restante'],2,',','.') ?></td>
                    <td><?= date('d/m/Y', strtotime($l['data_entrada'])) ?></td>
                    <td><?= $l['data_vencimento'] ? date('d/m/Y', strtotime($l['data_vencimento'])) : '—' ?></td>
                    <td><span class="badge <?= $badge ?>"><?= $label ?></span></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include '../_footer.php'; ?>