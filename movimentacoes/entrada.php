<?php
session_start();
if(!isset($_SESSION['logado']) || !$_SESSION['logado']){ header('Location: ../index.php'); exit; }
include '../conexao.php';

$produtos = $conexao->query("SELECT id, nome, unidade, estoque_atual FROM produtos ORDER BY nome")->fetchAll();
$erro = '';

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $produto_id = intval($_POST['produto_id']);
    $quantidade = floatval($_POST['quantidade']);
    $data       = $_POST['data_entrada'];
    if(!$produto_id || $quantidade <= 0){ $erro = 'Produto e quantidade são obrigatórios.'; }
    else {
        $conexao->beginTransaction();
        $conexao->prepare("INSERT INTO lotes (produto_id, quantidade, quantidade_restante, data_entrada, usuario_id)
            VALUES (:pid,:qtd,:qtd,:dt,:uid)")
            ->execute([':pid'=>$produto_id,':qtd'=>$quantidade,':dt'=>$data,':uid'=>$_SESSION['usuario_id']]);
        $conexao->prepare("UPDATE produtos SET estoque_atual = estoque_atual + :qtd WHERE id=:id")
            ->execute([':qtd'=>$quantidade,':id'=>$produto_id]);
        $conexao->commit();
        header('Location: index.php?msg=entrada'); exit;
    }
}

$pagina_atual = 'estoque'; $titulo_pagina = 'Entrada de Estoque';
include '../_header.php';
?>
<div class="content">
    <div class="page-header">
        <div class="page-header-left"><h2>Entrada de Estoque</h2><p>Adicione quantidade a um produto</p></div>
        <a href="index.php" class="btn btn-secondary">← Voltar</a>
    </div>
    <?php if($erro): ?><div class="alert alert-danger"><?= $erro ?></div><?php endif; ?>
    <div class="form-card">
        <form method="POST">
            <div class="form-grid">
                <div class="form-group">
                    <label>Produto *</label>
                    <select name="produto_id" required>
                        <option value="">— Selecione —</option>
                        <?php foreach($produtos as $p): ?>
                        <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['nome']) ?> — Atual: <?= $p['estoque_atual'] ?> <?= $p['unidade'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Quantidade *</label>
                    <input type="number" name="quantidade" step="0.001" min="0.001" placeholder="0.00" required>
                </div>
                <div class="form-group">
                    <label>Data *</label>
                    <input type="date" name="data_entrada" value="<?= date('Y-m-d') ?>" required>
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Confirmar Entrada</button>
                <a href="index.php" class="btn btn-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>
<?php include '../_footer.php'; ?>