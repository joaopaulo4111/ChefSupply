<?php
session_start();
if(!isset($_SESSION['logado']) || !$_SESSION['logado']){ header('Location: ../index.php'); exit; }
include '../conexao.php';

$categorias = $conexao->query("SELECT c.*, COUNT(p.id) as total_produtos
    FROM categorias c LEFT JOIN produtos p ON p.categoria_id = c.id
    GROUP BY c.id ORDER BY c.nome")->fetchAll();

$pagina_atual = 'configuracoes'; $titulo_pagina = 'Categorias';
include '../_header.php';
?>
<div class="content">
    <div class="page-header">
        <div class="page-header-left"><h2>Categorias</h2><p>Organize seus produtos por categoria</p></div>
        <a href="nova.php" class="btn btn-primary">+ Nova Categoria</a>
    </div>
    <?php if(isset($_GET['msg'])): ?>
    <div class="alert alert-success">Categoria <?= $_GET['msg']==='criado'?'cadastrada':($_GET['msg']==='editado'?'atualizada':'excluída') ?> com sucesso!</div>
    <?php endif; ?>
    <?php if(isset($_GET['erro']) && $_GET['erro']==='vinculado'): ?>
    <div class="alert alert-danger">Não é possível excluir: existem produtos vinculados a esta categoria.</div>
    <?php endif; ?>
    <div class="table-card">
        <table>
            <thead><tr><th>Categoria</th><th>Cor</th><th>Dias de Alerta</th><th>Produtos</th><th>Ações</th></tr></thead>
            <tbody>
            <?php if(empty($categorias)): ?>
                <tr><td colspan="5" style="text-align:center;padding:40px;color:#aaa">Nenhuma categoria. <a href="nova.php" style="color:#2db35d">Criar a primeira.</a></td></tr>
            <?php else: foreach($categorias as $c): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($c['nome']) ?></strong></td>
                    <td><span style="display:inline-block;width:20px;height:20px;border-radius:50%;background:<?= htmlspecialchars($c['cor']) ?>"></span></td>
                    <td><?= $c['dias_alerta_vencimento'] ?> dias</td>
                    <td><?= $c['total_produtos'] ?></td>
                    <td style="display:flex;gap:8px">
                        <a href="editar.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-secondary">Editar</a>
                        <?php if($c['total_produtos'] == 0): ?>
                        <a href="excluir.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Excluir esta categoria?')">Excluir</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include '../_footer.php'; ?>