<?php
session_start();
if(!isset($_SESSION['logado']) || !$_SESSION['logado']){
    header('Location: ../index.php');
    exit;
}
require_once '../conexao.php';

// Fetch all products with active stock to populate the dropdown
$produtos = $conexao->query("
    SELECT id, nome, unidade, estoque_atual, custo_unitario 
    FROM produtos 
    WHERE estoque_atual > 0 
    ORDER BY nome
")->fetchAll(PDO::FETCH_ASSOC);

$erro = '';
$sucesso = '';

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    // Sanitize and validate inputs
    $produto_id = intval($_POST['produto_id'] ?? 0);
    $lote_id    = !empty($_POST['lote_id']) ? intval($_POST['lote_id']) : null;
    $quantidade = floatval($_POST['quantidade'] ?? 0);
    $motivo     = trim($_POST['motivo'] ?? '');
    $obs        = trim($_POST['observacoes'] ?? '');
    $data       = trim($_POST['data_descarte'] ?? '');

    // Basic fields validation
    if(!$produto_id || $quantidade <= 0 || !$motivo || !$data){
        $erro = 'Produto, quantidade, motivo e data do descarte são obrigatórios.';
    } else {
        // Fetch product info to validate stock level and calculate fallback price
        $stmtP = $conexao->prepare("SELECT nome, estoque_atual, custo_unitario FROM produtos WHERE id = :id");
        $stmtP->execute([':id' => $produto_id]);
        $prodInfo = $stmtP->fetch(PDO::FETCH_ASSOC);

        if(!$prodInfo){
            $erro = 'Produto selecionado não existe.';
        } elseif($quantidade > floatval($prodInfo['estoque_atual'])) {
            $erro = "Quantidade descartada ({$quantidade}) não pode ser maior que o estoque atual do produto (" . number_format($prodInfo['estoque_atual'], 2, ',', '.') . ").";
        } else {
            $valor = 0.00;

            // Validate batch if selected
            if($lote_id){
                $stmtL = $conexao->prepare("SELECT preco_custo, quantidade_restante, codigo_lote FROM lotes WHERE id = :id AND produto_id = :pid");
                $stmtL->execute([':id' => $lote_id, ':pid' => $produto_id]);
                $loteInfo = $stmtL->fetch(PDO::FETCH_ASSOC);

                if(!$loteInfo){
                    $erro = 'Lote selecionado inválido para este produto.';
                } elseif($quantidade > floatval($loteInfo['quantidade_restante'])) {
                    $erro = "Quantidade descartada ({$quantidade}) não pode ser maior que a quantidade restante no lote " . htmlspecialchars($loteInfo['codigo_lote'] ?: '#'.$lote_id) . " (" . number_format($loteInfo['quantidade_restante'], 2, ',', '.') . ").";
                } else {
                    // Use batch specific unit cost
                    $valor = $quantidade * floatval($loteInfo['preco_custo']);
                }
            } else {
                // Use product fallback unit cost
                $valor = $quantidade * floatval($prodInfo['custo_unitario']);
            }

            // If no error, proceed with transactional database update
            if(!$erro){
                try {
                    $conexao->beginTransaction();

                    // 1. Insert Descarte record
                    $stmtInsert = $conexao->prepare("
                        INSERT INTO descartes (produto_id, lote_id, quantidade, motivo, valor_perdido, observacoes, data_descarte, usuario_id) 
                        VALUES (:pid, :lid, :qtd, :mot, :val, :obs, :dt, :uid)
                    ");
                    $stmtInsert->execute([
                        ':pid' => $produto_id,
                        ':lid' => $lote_id,
                        ':qtd' => $quantidade,
                        ':mot' => $motivo,
                        ':val' => $valor,
                        ':obs' => $obs,
                        ':dt'  => $data,
                        ':uid' => $_SESSION['usuario_id'] ?? null
                    ]);

                    // 2. Update product stock and status (accounting for limits)
                    $stmtUpdateProd = $conexao->prepare("
                        UPDATE produtos 
                        SET estoque_atual = GREATEST(0, estoque_atual - :qtd),
                            status = CASE 
                                WHEN GREATEST(0, estoque_atual - :qtd_c1) <= 0 THEN 'Crítico'
                                WHEN GREATEST(0, estoque_atual - :qtd_c2) <= estoque_minimo THEN 'Baixo'
                                WHEN estoque_maximo > 0 AND GREATEST(0, estoque_atual - :qtd_c3) >= estoque_maximo THEN 'Alto'
                                ELSE 'Normal'
                            END
                        WHERE id = :id
                    ");
                    $stmtUpdateProd->execute([
                        ':qtd' => $quantidade,
                        ':qtd_c1' => $quantidade,
                        ':qtd_c2' => $quantidade,
                        ':qtd_c3' => $quantidade,
                        ':id' => $produto_id
                    ]);

                    // 3. Update batch stock and status if a batch was selected
                    if($lote_id){
                        $stmtUpdateLote = $conexao->prepare("
                            UPDATE lotes 
                            SET quantidade_restante = GREATEST(0, quantidade_restante - :qtd),
                                status = CASE WHEN GREATEST(0, quantidade_restante - :qtd_c) <= 0 THEN 'descartado' ELSE status END
                            WHERE id = :id
                        ");
                        $stmtUpdateLote->execute([
                            ':qtd' => $quantidade,
                            ':qtd_c' => $quantidade,
                            ':id' => $lote_id
                        ]);
                    }

                    $conexao->commit();
                    header('Location: index.php?msg=criado');
                    exit;

                } catch (Exception $e) {
                    if($conexao->inTransaction()){
                        $conexao->rollBack();
                    }
                    $erro = 'Erro interno ao registrar descarte: ' . $e->getMessage();
                }
            }
        }
    }
}

$pagina_atual = 'descartes';
$titulo_pagina = 'Registrar Descarte';
include '../_header.php';
?>

<div class="content">
    <div class="page-header">
        <div class="page-header-left">
            <h2>Registrar Novo Descarte</h2>
            <p>Selecione o produto, lote correspondente e informe a quantidade da perda.</p>
        </div>
        <a href="index.php" class="btn btn-secondary">← Voltar para Listagem</a>
    </div>

    <?php if($erro): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($erro) ?></div>
    <?php endif; ?>

    <div class="form-card">
        <form method="POST" action="novo.php" autocomplete="off">
            <div class="form-grid">
                <div class="form-group">
                    <label for="produto_id">Produto *</label>
                    <select name="produto_id" id="produto_id" required onchange="carregarLotes(this.value)">
                        <option value="">— Selecione o produto —</option>
                        <?php foreach($produtos as $p): ?>
                            <option value="<?= $p['id'] ?>" <?= ( (isset($_POST['produto_id']) && $_POST['produto_id'] == $p['id']) || (!isset($_POST['produto_id']) && isset($_GET['produto_id']) && $_GET['produto_id'] == $p['id']) ) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($p['nome']) ?> (<?= htmlspecialchars($p['unidade']) ?>) — Disp: <?= number_format($p['estoque_atual'], 2, ',', '.') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="sel-lote">Lote (Opcional)</label>
                    <select name="lote_id" id="sel-lote">
                        <option value="">— Selecione o produto primeiro —</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="quantidade">Quantidade Descartada *</label>
                    <input type="number" name="quantidade" id="quantidade" step="0.001" min="0.001" placeholder="0,00" value="<?= htmlspecialchars($_POST['quantidade'] ?? '') ?>" required>
                </div>

                <div class="form-group">
                    <label for="motivo">Motivo *</label>
                    <select name="motivo" id="motivo" required>
                        <option value="">— Selecione o motivo —</option>
                        <option value="Vencimento" <?= (isset($_POST['motivo']) && $_POST['motivo'] === 'Vencimento') ? 'selected' : '' ?>>Vencimento</option>
                        <option value="Deterioração" <?= (isset($_POST['motivo']) && $_POST['motivo'] === 'Deterioração') ? 'selected' : '' ?>>Deterioração</option>
                        <option value="Excesso de produção" <?= (isset($_POST['motivo']) && $_POST['motivo'] === 'Excesso de produção') ? 'selected' : '' ?>>Excesso de produção</option>
                        <option value="Outros" <?= (isset($_POST['motivo']) && $_POST['motivo'] === 'Outros') ? 'selected' : '' ?>>Outros</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="data_descarte">Data do Descarte *</label>
                    <input type="date" name="data_descarte" id="data_descarte" value="<?= htmlspecialchars($_POST['data_descarte'] ?? date('Y-m-d')) ?>" required>
                </div>

                <div class="form-group full">
                    <label for="observacoes">Observações / Detalhes</label>
                    <textarea name="observacoes" id="observacoes" placeholder="Descreva os detalhes da perda (ex: avaria no transporte, cheiro desagradável, etc.)"><?= htmlspecialchars($_POST['observacoes'] ?? '') ?></textarea>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Registrar Descarte</button>
                <a href="index.php" class="btn btn-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>

<script>
function carregarLotes(produtoId) {
    const sel = document.getElementById('sel-lote');
    if (!produtoId) { 
        sel.innerHTML = '<option value="">— Selecione o produto primeiro —</option>'; 
        return; 
    }
    sel.innerHTML = '<option value="">Carregando lotes...</option>';
    
    fetch('../api/lotes_por_produto.php?produto_id=' + produtoId)
        .then(r => {
            if(!r.ok) throw new Error('Erro na requisição');
            return r.json();
        })
        .then(lotes => {
            if(lotes.length === 0) {
                sel.innerHTML = '<option value="">— Sem lotes disponíveis —</option>';
            } else {
                sel.innerHTML = '<option value="">— Selecione o lote (Sem lote específico) —</option>';
                lotes.forEach(l => {
                    const dataVenc = l.data_vencimento ? formatarData(l.data_vencimento) : 'N/D';
                    sel.innerHTML += `<option value="${l.id}">Lote: ${l.codigo_lote || '#'+l.id} — Vence: ${dataVenc} — Qtd: ${parseFloat(l.quantidade_restante).toLocaleString('pt-BR')}</option>`;
                });
            }
        })
        .catch(err => {
            console.error(err);
            sel.innerHTML = '<option value="">Erro ao carregar lotes</option>';
        });
}

function formatarData(dataStr) {
    if(!dataStr) return 'N/D';
    const partes = dataStr.split('-');
    if(partes.length !== 3) return dataStr;
    return `${partes[2]}/${partes[1]}/${partes[0]}`;
}

// Populate batches on load if product is already selected
document.addEventListener('DOMContentLoaded', () => {
    let pId = document.getElementById('produto_id').value;
    if(!pId) {
        pId = '<?= htmlspecialchars($_GET['produto_id'] ?? '') ?>';
        if(pId) {
            document.getElementById('produto_id').value = pId;
        }
    }
    if(pId) {
        carregarLotes(pId);
        // Wait briefly for options to populate then set selection if old input exists
        setTimeout(() => {
            const oldLoteId = '<?= htmlspecialchars($_POST['lote_id'] ?? $_GET['lote_id'] ?? '') ?>';
            if(oldLoteId) {
                document.getElementById('sel-lote').value = oldLoteId;
            }
        }, 300);
    }
});
</script>

<?php include '../_footer.php'; ?>