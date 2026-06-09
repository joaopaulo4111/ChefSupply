<?php
session_start();
if (!isset($_SESSION['logado']) || !$_SESSION['logado']) {
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

// Fetch all categories for the dropdown
$categorias = $conexao->query("SELECT id, nome FROM categorias ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);
$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and validate inputs
    $nome = trim($_POST['nome'] ?? '');
    $codigo_barras = !empty($_POST['codigo_barras']) ? trim($_POST['codigo_barras']) : null;
    $cat_id = !empty($_POST['categoria_id']) ? intval($_POST['categoria_id']) : null;
    $unidade = trim($_POST['unidade'] ?? 'kg');
    $est_min = floatval($_POST['estoque_minimo'] ?? 0);
    $est_max = floatval($_POST['estoque_maximo'] ?? 0);
    $custo = floatval($_POST['custo_unitario'] ?? 0);

    if (!$nome) {
        $erro = 'O nome do produto é obrigatório.';
    } elseif ($est_max > 0 && $est_min > $est_max) {
        $erro = 'O estoque mínimo não pode ser maior que o estoque máximo.';
    } else {
        // Check if product name already exists
        $check = $conexao->prepare("SELECT COUNT(*) FROM produtos WHERE LOWER(nome) = LOWER(:nome)");
        $check->execute([':nome' => $nome]);

        // Check if barcode is unique (if provided)
        $checkBar = 0;
        if ($codigo_barras !== null) {
            $stmtBar = $conexao->prepare("SELECT COUNT(*) FROM produtos WHERE codigo_barras = :bar");
            $stmtBar->execute([':bar' => $codigo_barras]);
            $checkBar = $stmtBar->fetchColumn();
        }

        if ($check->fetchColumn() > 0) {
            $erro = 'Já existe um produto cadastrado com este nome.';
        } elseif ($checkBar > 0) {
            $erro = 'Este código de barras já está cadastrado para outro produto.';
        } else {
            // Determine initial status based on minimum stock (initial stock is 0.00)
            $status = 'Normal';
            if ($est_min > 0) {
                $status = 'Crítico'; // 0 stock is <= minimum stock
            }

            try {
                $sql = "INSERT INTO produtos (nome, categoria_id, unidade, estoque_minimo, estoque_maximo, custo_unitario, status, estoque_atual, codigo_barras)
                        VALUES (:nome, :cat, :un, :emin, :emax, :custo, :status, 0.00, :bar)";

                $stmt = $conexao->prepare($sql);
                $stmt->execute([
                    ':nome' => $nome,
                    ':cat' => $cat_id,
                    ':un' => $unidade,
                    ':emin' => $est_min,
                    ':emax' => $est_max,
                    ':custo' => $custo,
                    ':status' => $status,
                    ':bar' => $codigo_barras
                ]);

                header('Location: index.php?msg=criado');
                exit;
            } catch (Exception $e) {
                $erro = 'Erro ao cadastrar produto: ' . $e->getMessage();
            }
        }
    }
}

$pagina_atual = 'produtos';
$titulo_pagina = 'Novo Produto';
include '../_header.php';
?>

<div class="content">
    <div class="page-header">
        <div class="page-header-left">
            <h2>Cadastrar Novo Produto</h2>
            <p>Adicione um novo ingrediente, bebida ou insumo ao catálogo de estoque.</p>
        </div>
        <a href="index.php" class="btn btn-secondary">← Voltar para Catálogo</a>
    </div>

    <?php if ($erro): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($erro) ?></div>
    <?php endif; ?>

    <div class="form-card">
        <form method="POST" action="nova.php" autocomplete="off">
            <div class="form-grid">

                <div class="form-group">
                    <label for="codigo_barras">Código de Barras (EAN / Barcode)</label>
                    <div style="display: flex; gap: 8px;">
                        <input type="text" name="codigo_barras" id="codigo_barras" placeholder="Ex: 7891000100103"
                            value="<?= htmlspecialchars($_POST['codigo_barras'] ?? '') ?>" style="flex: 1;">
                        <button type="button" id="btn-consulta-ean" class="btn btn-secondary"
                            style="padding: 0 14px; height: 44px; white-space: nowrap;"
                            title="Autopreencher nome via API Open Food Facts">🔍 Buscar EAN</button>
                    </div>
                </div>

                <div class="form-group">
                    <label for="unidade">Unidade de Medida *</label>
                    <select name="unidade" id="unidade" required>
                        <?php foreach (['kg', 'g', 'L', 'ml', 'UN', 'cx', 'pct', 'saco'] as $u): ?>
                            <option value="<?= $u ?>" <?= (isset($_POST['unidade']) && $_POST['unidade'] === $u) || (!isset($_POST['unidade']) && $u === 'kg') ? 'selected' : '' ?>>
                                <?= $u ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group full">
                    <label for="nome">Nome do Produto *</label>
                    <input type="text" name="nome" id="nome" placeholder="Ex: Filé Mignon Fresco"
                        value="<?= htmlspecialchars($_POST['nome'] ?? '') ?>" required>
                </div>

                <div class="form-group">
                    <label for="categoria_id">Categoria</label>
                    <select name="categoria_id" id="categoria_id">
                        <option value="">— Selecione uma categoria —</option>
                        <?php foreach ($categorias as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= (isset($_POST['categoria_id']) && $_POST['categoria_id'] == $c['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['nome']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="estoque_minimo">Estoque Mínimo (Alerta de Baixo Estoque)</label>
                    <input type="number" name="estoque_minimo" id="estoque_minimo" step="0.01" min="0"
                        placeholder="0,00" value="<?= htmlspecialchars($_POST['estoque_minimo'] ?? '0.00') ?>">
                </div>

                <div class="form-group">
                    <label for="estoque_maximo">Estoque Máximo Recomendado</label>
                    <input type="number" name="estoque_maximo" id="estoque_maximo" step="0.01" min="0"
                        placeholder="0,00" value="<?= htmlspecialchars($_POST['estoque_maximo'] ?? '0.00') ?>">
                </div>

                <div class="form-group">
                    <label for="custo_unitario">Custo Unitário Padrão (R$)</label>
                    <input type="number" name="custo_unitario" id="custo_unitario" step="0.01" min="0"
                        placeholder="0,00" value="<?= htmlspecialchars($_POST['custo_unitario'] ?? '0.00') ?>">
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Cadastrar Produto</button>
                <a href="index.php" class="btn btn-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>

<script>
    document.getElementById('btn-consulta-ean').addEventListener('click', function () {
        const barcode = document.getElementById('codigo_barras').value.replace(/\D/g, '');
        if (!barcode) { alert('Digite um código de barras.'); return; }

        const btn = document.getElementById('btn-consulta-ean');
        const oldHtml = btn.innerHTML;
        btn.innerHTML = '⏳...';
        btn.disabled = true;

        fetch(`https://world.openfoodfacts.org/api/v2/product/${barcode}.json`)
            .then(r => r.json())
            .then(data => {
                if (data.status === 1 && data.product) {
                    const prod = data.product;

                    // Nome
                    let name = prod.product_name_pt || prod.product_name || prod.product_name_en || '';
                    const brand = prod.brands ? prod.brands.split(',')[0].trim() : '';
                    if (brand && name) name = `${brand} - ${name}`;
                    if (name) document.getElementById('nome').value = name;

                    // Unidade de medida
                    const quantity = (prod.quantity || '').toLowerCase();
                    const unidade = document.getElementById('unidade');
                    if (quantity.includes('ml')) unidade.value = 'ml';
                    else if (quantity.includes(' l') || quantity.endsWith('l')) unidade.value = 'L';
                    else if (quantity.includes('kg')) unidade.value = 'kg';
                    else if (quantity.includes('g')) unidade.value = 'g';

                    // Categoria
                    const tags = (prod.categories || '').toLowerCase();
                    const nomeP = name.toLowerCase();
                    const selectCat = document.getElementById('categoria_id');
                    const mapa = {
                        'Massas': ['pasta', 'spaghetti', 'macarrao', 'espaguete', 'noodle', 'lasanha', 'talharim'],
                        'Carnes': ['meat', 'carne', 'frango', 'bovina', 'suina', 'peixe', 'aves', 'salsicha', 'linguica', 'bacon'],
                        'Laticínios': ['dairy', 'laticin', 'leite', 'queijo', 'iogurte', 'manteiga', 'creme', 'requeijao'],
                        'Vegetais': ['vegeta', 'legume', 'hortalica', 'alface', 'tomate', 'cebola', 'alho', 'brocolis'],
                        'Frutas': ['fruit', 'fruta', 'banana', 'maca', 'laranja', 'morango', 'uva'],
                        'Grãos': ['grain', 'cereal', 'arroz', 'feijao', 'milho', 'trigo', 'aveia', 'lentilha', 'graos'],
                        'Óleos': ['oil', 'azeite', 'oleo', 'gordura'],
                        'Bebidas': ['beverage', 'bebida', 'suco', 'refrigerante', 'agua', 'cafe', 'cha', 'achocolatado'],
                        'Temperos': ['spice', 'tempero', 'condiment', 'pimenta', 'oregano', 'colorau', 'canela', 'erva'],
                        'Limpeza': ['cleaning', 'limpeza', 'detergente', 'sabao', 'desinfetante'],
                        'Doces e Açúcar': ['sugar','acucar','açúcar','refinado','demerara','mascavo','mel','geleia','doce','chocolate']
                    };

                    for (const [cat, keywords] of Object.entries(mapa)) {
                        if (keywords.some(k => tags.includes(k) || nomeP.includes(k))) {
                            for (let opt of selectCat.options) {
                                if (opt.text.toLowerCase().includes(cat.toLowerCase())) {
                                    selectCat.value = opt.value;
                                    break;
                                }
                            }
                            break;
                        }
                    }

                } else {
                    alert('Produto não encontrado no Open Food Facts.');
                }
            })
            .catch(() => alert('Erro na consulta. Verifique sua conexão.'))
            .finally(() => {
                btn.innerHTML = oldHtml;
                btn.disabled = false;
            });
    });
</script>


<?php include '../_footer.php'; ?>