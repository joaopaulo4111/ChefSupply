<?php
session_start();
if(!isset($_SESSION['logado']) || !$_SESSION['logado']){ header('Location: ../index.php'); exit; }
include '../conexao.php';

$erro = '';
if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $nome = trim($_POST['nome']);
    $cor  = $_POST['cor'] ?? '#2db35d';
    $dias = intval($_POST['dias_alerta_vencimento']);
    if(!$nome){ $erro = 'O nome da categoria é obrigatório.'; }
    else {
        $conexao->prepare("INSERT INTO categorias (nome, cor, dias_alerta_vencimento) VALUES (:n,:c,:d)")
            ->execute([':n'=>$nome,':c'=>$cor,':d'=>$dias]);
        header('Location: index.php?msg=criado'); exit;
    }
}
$pagina_atual = 'configuracoes'; $titulo_pagina = 'Nova Categoria';
include '../_header.php';
?>
<div class="content">
    <div class="page-header">
        <div class="page-header-left"><h2>Nova Categoria</h2><p>Crie uma categoria para organizar os produtos</p></div>
        <a href="index.php" class="btn btn-secondary">← Voltar</a>
    </div>
    <?php if($erro): ?><div class="alert alert-danger"><?= $erro ?></div><?php endif; ?>
    <div class="form-card">
        <form method="POST">
            <div class="form-grid">
                <div class="form-group full">
                    <label>Nome da Categoria *</label>
                    <input type="text" name="nome" placeholder="Ex: Carnes, Laticínios, Secos..." value="<?= htmlspecialchars($_POST['nome'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label>Cor de Identificação</label>
                    <input type="color" name="cor" value="<?= $_POST['cor'] ?? '#2db35d' ?>" style="height:42px;padding:4px 8px">
                </div>
                <div class="form-group">
                    <label>Dias de Alerta Antes do Vencimento</label>
                    <input type="number" name="dias_alerta_vencimento" min="1" max="30" value="<?= $_POST['dias_alerta_vencimento'] ?? 3 ?>">
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Salvar Categoria</button>
                <a href="index.php" class="btn btn-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>
<?php include '../_footer.php'; ?>