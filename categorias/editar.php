<?php
session_start();
if(!isset($_SESSION['logado']) || !$_SESSION['logado']){ header('Location: ../index.php'); exit; }
include '../conexao.php';

$id = intval($_GET['id'] ?? 0);
$stmt = $conexao->prepare("SELECT * FROM categorias WHERE id=:id");
$stmt->execute([':id'=>$id]);
$c = $stmt->fetch();
if(!$c){ header('Location: index.php'); exit; }

$erro = '';
if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $nome = trim($_POST['nome']);
    $cor  = $_POST['cor'];
    $dias = intval($_POST['dias_alerta_vencimento']);
    if(!$nome){ $erro = 'Nome obrigatório.'; }
    else {
        $conexao->prepare("UPDATE categorias SET nome=:n, cor=:c, dias_alerta_vencimento=:d WHERE id=:id")
            ->execute([':n'=>$nome,':c'=>$cor,':d'=>$dias,':id'=>$id]);
        header('Location: index.php?msg=editado'); exit;
    }
    $c = array_merge($c, $_POST);
}
$pagina_atual = 'configuracoes'; $titulo_pagina = 'Editar Categoria';
include '../_header.php';
?>
<div class="content">
    <div class="page-header">
        <div class="page-header-left"><h2>Editar Categoria</h2><p><?= htmlspecialchars($c['nome']) ?></p></div>
        <a href="index.php" class="btn btn-secondary">← Voltar</a>
    </div>
    <?php if($erro): ?><div class="alert alert-danger"><?= $erro ?></div><?php endif; ?>
    <div class="form-card">
        <form method="POST">
            <div class="form-grid">
                <div class="form-group full">
                    <label>Nome *</label>
                    <input type="text" name="nome" value="<?= htmlspecialchars($c['nome']) ?>" required>
                </div>
                <div class="form-group">
                    <label>Cor</label>
                    <input type="color" name="cor" value="<?= $c['cor'] ?>" style="height:42px;padding:4px 8px">
                </div>
                <div class="form-group">
                    <label>Dias de Alerta</label>
                    <input type="number" name="dias_alerta_vencimento" min="1" max="30" value="<?= $c['dias_alerta_vencimento'] ?>">
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Salvar</button>
                <a href="index.php" class="btn btn-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>
<?php include '../_footer.php'; ?>