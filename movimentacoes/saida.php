<?php
session_start();
if(!isset($_SESSION['logado']) || !$_SESSION['logado']){ header('Location: ../index.php'); exit; }
include '../conexao.php';

$produtos = $conexao->query("SELECT id, nome, unidade, estoque_atual FROM produtos WHERE estoque_atual > 0 ORDER BY nome")->fetchAll();
$erro = '';

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $produto_id = intval($_POST['produto_id']);
    $quantidade = floatval($_POST['quantidade']);
    $data       = $_POST['data_saida'];
    $est = $conexao->prepare("SELECT estoque_atual FROM produtos WHERE id=:id");
    $est->execute([':id'=>$produto_id]);
    $estoque_atual = floatval($est->fetchColumn());
    if(!$produto_id || $quantidade <= 0){ $erro = 'Produto e quantidade são obrigatórios.'; }
    elseif($quantidade > $estoque_atual){ $erro = "Quantidade insuficiente. Estoque atual: $estoque_atual."; }
    else {
        $conexao->prepare("UPDATE produtos SET estoque_atual = GREATEST(0, estoque_atual - :qtd) WHERE id=:id")
            ->execute([':qtd'=>$quantidade,':id'=>$produto_id]);
        header('Location: index.php?msg=saida'); exit;
    }
}

$pagina_atual = 'estoque'; $titulo_pagina = 'Saída de Estoque';
include '../_header.php';
?>
<div class="content">
    <div class="page-header">
        <div class="page-header-left"><h2>Saída de Estoque</h2><p>Registre o consumo de um produto</p></div>
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
                        <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['nome']) ?> — Disponível: <?= $p['estoque_atual'] ?> <?= $p['unidade'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Quantidade *</label>
                    <input type="number" name="quantidade" step="0.001" min="0.001" placeholder="0.00" required>
                </div>
                <div class="form-group">
                    <label>Data *</label>
                    <input type="date" name="data_saida" value="<?= date('Y-m-d') ?>" required>
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Confirmar Saída</button>
                <a href="index.php" class="btn btn-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>
<?php include '../_footer.php'; ?>