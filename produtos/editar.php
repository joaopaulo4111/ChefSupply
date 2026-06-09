<?php
session_start();
if(!isset($_SESSION['logado']) || !$_SESSION['logado']){
    header('Location: ../index.php');
    exit;
}
require_once '../conexao.php';

// Database self-healing: add barcode column if missing
try {
    $conexao->query("SELECT codigo_barras FROM produtos LIMIT 1");
} catch (Exception $e) {
    try {
        $conexao->query("ALTER TABLE produtos ADD COLUMN codigo_barras VARCHAR(50) NULL UNIQUE");
    } catch (Exception $ex) {
        // Fallback
    }
}

// Fetch the product ID
$id = intval($_GET['id'] ?? 0);
if (!$id) {
    header('Location: index.php');
    exit;
}

// Retrieve the product record from database
$stmt = $conexao->prepare("SELECT * FROM produtos WHERE id = :id");
$stmt->execute([':id' => $id]);
$p = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$p) {
    header('Location: index.php?erro=nao_encontrado');
    exit;
}

// Fetch all categories for the dropdown
$categorias = $conexao->query("SELECT id, nome FROM categorias ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);
$erro = '';

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    // Sanitize and validate inputs
    $nome          = trim($_POST['nome'] ?? '');
    $codigo_barras = !empty($_POST['codigo_barras']) ? trim($_POST['codigo_barras']) : null;
    $cat_id        = !empty($_POST['categoria_id']) ? intval($_POST['categoria_id']) : null;
    $unidade       = trim($_POST['unidade'] ?? 'kg');
    $est_min       = floatval($_POST['estoque_minimo'] ?? 0);
    $est_max       = floatval($_POST['estoque_maximo'] ?? 0);
    $custo         = floatval($_POST['custo_unitario'] ?? 0);

    if(!$nome){
        $erro = 'O nome do produto é obrigatório.';
    } elseif ($est_max > 0 && $est_min > $est_max) {
        $erro = 'O estoque mínimo não pode ser maior que o estoque máximo.';
    } else {
        // Check if name is taken by a different product
        $check = $conexao->prepare("SELECT COUNT(*) FROM produtos WHERE LOWER(nome) = LOWER(:nome) AND id != :id");
        $check->execute([':nome' => $nome, ':id' => $id]);
        
        // Check if barcode is unique (if provided) excluding self
        $checkBar = 0;
        if ($codigo_barras !== null) {
            $stmtBar = $conexao->prepare("SELECT COUNT(*) FROM produtos WHERE codigo_barras = :bar AND id != :id");
            $stmtBar->execute([':bar' => $codigo_barras, ':id' => $id]);
            $checkBar = $stmtBar->fetchColumn();
        }

        if ($check->fetchColumn() > 0) {
            $erro = 'Já existe outro produto cadastrado com este nome.';
        } elseif ($checkBar > 0) {
            $erro = 'Este código de barras já está cadastrado para outro produto.';
        } else {
            // Recalculate stock level status based on current estoque_atual and new min/max thresholds
            $status = 'Normal';
            $estoque_atual = floatval($p['estoque_atual']);
            
            if ($estoque_atual <= 0) {
                $status = 'Crítico';
            } elseif ($estoque_atual <= $est_min) {
                $status = 'Baixo';
            } elseif ($est_max > 0 && $estoque_atual >= $est_max) {
                $status = 'Alto';
            }

            try {
                $sql = "UPDATE produtos 
                        SET nome = :nome, 
                            categoria_id = :cat, 
                            unidade = :un, 
                            estoque_minimo = :emin, 
                            estoque_maximo = :emax, 
                            custo_unitario = :custo, 
                            status = :status,
                            codigo_barras = :bar
                        WHERE id = :id";
                
                $stmtUpdate = $conexao->prepare($sql);
                $stmtUpdate->execute([
                    ':nome'   => $nome,
                    ':cat'    => $cat_id,
                    ':un'     => $unidade,
                    ':emin'   => $est_min,
                    ':emax'   => $est_max,
                    ':custo'  => $custo,
                    ':status' => $status,
                    ':bar'    => $codigo_barras,
                    ':id'     => $id
                ]);

                header('Location: index.php?msg=editado');
                exit;
            } catch (Exception $e) {
                $erro = 'Erro ao atualizar produto: ' . $e->getMessage();
            }
        }
    }
}

$pagina_atual = 'produtos';
$titulo_pagina = 'Editar Produto';
include '../_header.php';
?>

<div class="content">
    <div class="page-header">
        <div class="page-header-left">
            <h2>Editar Produto</h2>
            <p>Atualize as configurações e informações cadastrais de: <strong><?= htmlspecialchars($p['nome']) ?></strong></p>
        </div>
        <a href="index.php" class="btn btn-secondary">← Voltar para Catálogo</a>
    </div>

    <?php if($erro): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($erro) ?></div>
    <?php endif; ?>

    <div class="form-card">
        <form method="POST" action="editar.php?id=<?= $id ?>" autocomplete="off">
            <div class="form-grid">
                
                <div class="form-group">
                    <label for="codigo_barras">Código de Barras (EAN / Barcode)</label>
                    <div style="display: flex; gap: 8px;">
                        <input type="text" name="codigo_barras" id="codigo_barras" placeholder="Ex: 7891000100103" value="<?= htmlspecialchars($_POST['codigo_barras'] ?? $p['codigo_barras'] ?? '') ?>" style="flex: 1;">
                        <button type="button" id="btn-consulta-ean" class="btn btn-secondary" style="padding: 0 14px; height: 44px; white-space: nowrap;" title="Autopreencher nome via API Open Food Facts">🔍 Buscar EAN</button>
                    </div>
                </div>

                <div class="form-group">
                    <label for="unidade">Unidade de Medida *</label>
                    <select name="unidade" id="unidade" required>
                        <?php foreach(['kg','g','L','ml','UN','cx','pct','saco'] as $u): ?>
                            <option value="<?= $u ?>" <?= (isset($_POST['unidade']) && $_POST['unidade'] === $u) || (!isset($_POST['unidade']) && $p['unidade'] === $u) ? 'selected' : '' ?>>
                                <?= $u ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group full">
                    <label for="nome">Nome do Produto *</label>
                    <input type="text" name="nome" id="nome" value="<?= htmlspecialchars($_POST['nome'] ?? $p['nome']) ?>" required>
                </div>

                <div class="form-group">
                    <label for="categoria_id">Categoria</label>
                    <select name="categoria_id" id="categoria_id">
                        <option value="">— Selecione uma categoria —</option>
                        <?php foreach($categorias as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= (isset($_POST['categoria_id']) && $_POST['categoria_id'] == $c['id']) || (!isset($_POST['categoria_id']) && $p['categoria_id'] == $c['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['nome']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="estoque_minimo">Estoque Mínimo (Alerta de Baixo Estoque)</label>
                    <input type="number" name="estoque_minimo" id="estoque_minimo" step="0.01" min="0" value="<?= htmlspecialchars($_POST['estoque_minimo'] ?? $p['estoque_minimo']) ?>">
                </div>

                <div class="form-group">
                    <label for="estoque_maximo">Estoque Máximo Recomendado</label>
                    <input type="number" name="estoque_maximo" id="estoque_maximo" step="0.01" min="0" value="<?= htmlspecialchars($_POST['estoque_maximo'] ?? $p['estoque_maximo']) ?>">
                </div>

                <div class="form-group">
                    <label for="custo_unitario">Custo Unitário Padrão (R$)</label>
                    <input type="number" name="custo_unitario" id="custo_unitario" step="0.01" min="0" value="<?= htmlspecialchars($_POST['custo_unitario'] ?? $p['custo_unitario']) ?>">
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Salvar Alterações</button>
                <a href="index.php" class="btn btn-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>

<script>
// Open Food Facts API Integration
document.getElementById('btn-consulta-ean').addEventListener('click', function() {
    const barcode = document.getElementById('codigo_barras').value.replace(/\D/g, '');
    if (!barcode) {
        alert('Por favor, digite um código de barras para consultar.');
        return;
    }
    
    const btn = document.getElementById('btn-consulta-ean');
    const oldHtml = btn.innerHTML;
    btn.innerHTML = '⏳...';
    btn.disabled = true;

    fetch(`https://world.openfoodfacts.org/api/v2/product/${barcode}.json`)
        .then(response => {
            if (!response.ok) throw new Error('Produto não localizado ou falha de conexão.');
            return response.json();
        })
        .then(data => {
            if (data.status === 1 && data.product) {
                const prod = data.product;
                // Try to find the name in Portuguese, English, or default name
                let name = prod.product_name_pt || prod.product_name || prod.product_name_en || '';
                const brand = prod.brands ? prod.brands.split(',')[0].trim() : '';
                
                if (brand && name) {
                    name = `${brand} - ${name}`;
                }
                
                if (name) {
                    document.getElementById('nome').value = name;
                } else {
                    alert('Código de barras encontrado, mas o nome do produto está em branco.');
                }
            } else {
                alert('Produto não encontrado na base do Open Food Facts.');
            }
        })
        .catch(err => {
            alert('Falha na consulta: ' + err.message);
        })
        .finally(() => {
            btn.innerHTML = oldHtml;
            btn.disabled = false;
        });
});
</script>

<?php include '../_footer.php'; ?>