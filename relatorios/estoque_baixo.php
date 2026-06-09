<?php
session_start();
if(!isset($_SESSION['logado']) || !$_SESSION['logado']){ header('Location: ../index.php'); exit; }
include '../conexao.php';

$produtos = $conexao->query("
    SELECT p.*, c.nome as categoria_nome,
           CASE WHEN p.estoque_atual = 0 THEN 'Zerado'
                WHEN p.estoque_atual <= p.estoque_minimo THEN 'Crítico'
                ELSE 'Baixo' END as situacao
    FROM produtos p
    LEFT JOIN categorias c ON c.id = p.categoria_id
    WHERE p.estoque_atual <= p.estoque_minimo
    ORDER BY p.estoque_atual ASC
")->fetchAll();

$pagina_atual = 'relatorios'; $titulo_pagina = 'Estoque Baixo';
include '../_header.php';
?>
<div class="content">
    <div class="page-header">
        <div class="page-header-left"><h2>Relatório — Estoque Baixo</h2><p><?= count($produtos) ?> produto(s) abaixo do mínimo</p></div>
        <div style="display:flex;gap:8px">
            <button onclick="window.print()" class="btn btn-secondary">🖨 Imprimir</button>
            <a href="index.php" class="btn btn-secondary">← Voltar</a>
        </div>
    </div>
    <?php if(empty($produtos)): ?>
    <div class="table-card"><div class="empty-state"><p>✅ Todos os produtos estão com estoque adequado!</p></div></div>
    <?php else: ?>
    <div class="table-card">
        <table>
            <thead><tr><th>Produto</th><th>Categoria</th><th>Unidade</th><th>Atual</th><th>Mínimo</th><th>Déficit</th><th>Situação</th></tr></thead>
            <tbody>
            <?php foreach($produtos as $p):
                $deficit = max(0, $p['estoque_minimo'] - $p['estoque_atual']);
                $badge   = $p['situacao'] === 'Zerado' ? 'badge-vencido' : 'badge-critico';
            ?>
                <tr>
                    <td><strong><?= htmlspecialchars($p['nome']) ?></strong></td>
                    <td><?= htmlspecialchars($p['categoria_nome'] ?? '—') ?></td>
                    <td><?= $p['unidade'] ?></td>
                    <td style="color:#dc2626;font-weight:600"><?= number_format($p['estoque_atual'],2,',','.') ?></td>
                    <td><?= number_format($p['estoque_minimo'],2,',','.') ?></td>
                    <td style="color:#ca8a04;font-weight:600"><?= number_format($deficit,2,',','.') ?></td>
                    <td><span class="badge <?= $badge ?>"><?= $p['situacao'] ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
<?php include '../_footer.php'; ?>