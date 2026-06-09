<?php
session_start();
if(!isset($_SESSION['logado']) || !$_SESSION['logado']){
    header('Location: ../index.php');
    exit;
}
require_once '../conexao.php';

// Fetch products and active suppliers to populate dropdowns
$produtos = $conexao->query("SELECT id, nome, unidade, estoque_atual FROM produtos ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);
$fornecedores = $conexao->query("SELECT id, nome FROM fornecedores WHERE ativo = 1 ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);
$erro = '';

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    // Sanitize and validate inputs
    $produto_id      = intval($_POST['produto_id'] ?? 0);
    $fornecedor_id   = !empty($_POST['fornecedor_id']) ? intval($_POST['fornecedor_id']) : null;
    $codigo_lote     = trim($_POST['codigo_lote'] ?? '');
    $quantidade      = floatval($_POST['quantidade'] ?? 0);
    $preco_custo     = floatval($_POST['preco_custo'] ?? 0);
    $data_entrada    = trim($_POST['data_entrada'] ?? '');
    $data_vencimento = !empty($_POST['data_vencimento']) ? trim($_POST['data_vencimento']) : null;

    if(!$produto_id || $quantidade <= 0 || !$data_entrada){
        $erro = 'Produto, quantidade e data de entrada são obrigatórios.';
    } elseif ($data_vencimento && $data_vencimento < $data_entrada) {
        $erro = 'A data de vencimento não pode ser anterior à data de entrada do lote.';
    } else {
        try {
            $conexao->beginTransaction();

            // 1. Insert the new batch (lote) record
            $stmtInsert = $conexao->prepare("
                INSERT INTO lotes (produto_id, fornecedor_id, codigo_lote, quantidade, quantidade_restante, preco_custo, data_entrada, data_vencimento, status, usuario_id)
                VALUES (:pid, :fid, :lote, :qtd, :qtd, :custo, :dt_in, :dt_ven, 'ativo', :uid)
            ");
            $stmtInsert->execute([
                ':pid'    => $produto_id,
                ':fid'    => $fornecedor_id,
                ':lote'   => $codigo_lote ?: null,
                ':qtd'    => $quantidade,
                ':custo'  => $preco_custo,
                ':dt_in'  => $data_entrada,
                ':dt_ven' => $data_vencimento,
                ':uid'    => $_SESSION['usuario_id'] ?? null
            ]);

            // 2. Update the product stock level and status, updating the unit cost if provided
            $custo_sql = "";
            $custo_params = [];
            if ($preco_custo > 0) {
                $custo_sql = ", custo_unitario = :new_custo";
                $custo_params[':new_custo'] = $preco_custo;
            }

            $stmtUpdate = $conexao->prepare("
                UPDATE produtos 
                SET estoque_atual = estoque_atual + :qtd,
                    status = CASE 
                        WHEN (estoque_atual + :qtd_c1) <= 0 THEN 'Crítico'
                        WHEN (estoque_atual + :qtd_c2) <= estoque_minimo THEN 'Baixo'
                        WHEN estoque_maximo > 0 AND (estoque_atual + :qtd_c3) >= estoque_maximo THEN 'Alto'
                        ELSE 'Normal'
                    END
                    $custo_sql
                WHERE id = :id
            ");

            $execute_params = array_merge([
                ':qtd'    => $quantidade,
                ':qtd_c1' => $quantidade,
                ':qtd_c2' => $quantidade,
                ':qtd_c3' => $quantidade,
                ':id'      => $produto_id
            ], $custo_params);

            $stmtUpdate->execute($execute_params);

            $conexao->commit();
            header('Location: index.php?msg=criado');
            exit;

        } catch (Exception $e) {
            if ($conexao->inTransaction()) {
                $conexao->rollBack();
            }
            $erro = 'Erro ao processar entrada de estoque: ' . $e->getMessage();
        }
    }
}

$pagina_atual = 'entradas';
$titulo_pagina = 'Nova Entrada de Estoque';
include '../_header.php';
?>

<div class="content">
    <div class="page-header">
        <div class="page-header-left">
            <h2>Registrar Entrada de Mercadoria</h2>
            <p>Adicione novos itens ao estoque criando um novo lote com rastreamento.</p>
        </div>
        <a href="index.php" class="btn btn-secondary">← Voltar para Listagem</a>
    </div>

    <?php if($erro): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($erro) ?></div>
    <?php endif; ?>

    <div class="form-card">
        <form method="POST" action="nova.php" autocomplete="off">
            <div class="form-grid">
                <div class="form-group">
                    <label for="produto_id">Produto *</label>
                    <select name="produto_id" id="produto_id" required>
                        <option value="">— Selecione o produto —</option>
                        <?php foreach($produtos as $p): ?>
                            <option value="<?= $p['id'] ?>" <?= (isset($_POST['produto_id']) && $_POST['produto_id'] == $p['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($p['nome']) ?> (<?= htmlspecialchars($p['unidade']) ?>) — Atual: <?= number_format($p['estoque_atual'], 2, ',', '.') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="fornecedor_id">Fornecedor (Opcional)</label>
                    <select name="fornecedor_id" id="fornecedor_id">
                        <option value="">— Selecione o fornecedor —</option>
                        <?php foreach($fornecedores as $f): ?>
                            <option value="<?= $f['id'] ?>" <?= (isset($_POST['fornecedor_id']) && $_POST['fornecedor_id'] == $f['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($f['nome']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="codigo_lote">Identificação / Código do Lote</label>
                    <input type="text" name="codigo_lote" id="codigo_lote" placeholder="Ex: LOTE-12345, NFE-987" value="<?= htmlspecialchars($_POST['codigo_lote'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label for="quantidade">Quantidade Recebida *</label>
                    <input type="number" name="quantidade" id="quantidade" step="0.001" min="0.001" placeholder="0,00" value="<?= htmlspecialchars($_POST['quantidade'] ?? '') ?>" required>
                </div>

                <div class="form-group">
                    <label for="preco_custo">Preço de Custo Unitário (R$)</label>
                    <input type="number" name="preco_custo" id="preco_custo" step="0.01" min="0" placeholder="0,00" value="<?= htmlspecialchars($_POST['preco_custo'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label for="data_entrada">Data de Entrada *</label>
                    <input type="date" name="data_entrada" id="data_entrada" value="<?= htmlspecialchars($_POST['data_entrada'] ?? date('Y-m-d')) ?>" required>
                </div>

                <div class="form-group">
                    <label for="data_vencimento">Data de Vencimento</label>
                    <input type="date" name="data_vencimento" id="data_vencimento" value="<?= htmlspecialchars($_POST['data_vencimento'] ?? '') ?>">
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
