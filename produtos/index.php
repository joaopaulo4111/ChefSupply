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

// ── FILTER HANDLING ──────────────────────────────────────────
$where = ["1=1"];
$params = [];

// Text search (Product name or barcode)
$search = trim($_GET['q'] ?? '');
if ($search !== '') {
    $where[] = "(p.nome LIKE :search OR p.codigo_barras LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

// Category filter
$filtro_categoria = intval($_GET['categoria_id'] ?? 0);
if ($filtro_categoria > 0) {
    $where[] = "p.categoria_id = :categoria_id";
    $params[':categoria_id'] = $filtro_categoria;
}

// Status/Level filter
$filtro_status = trim($_GET['status'] ?? '');
if ($filtro_status !== '') {
    if ($filtro_status === 'Zerado') {
        $where[] = "p.estoque_atual = 0";
    } else {
        $where[] = "p.status = :status";
        $params[':status'] = $filtro_status;
    }
}

$where_clause = implode(" AND ", $where);

// ── KPI CALCULATIONS (GLOBAL) ────────────────────────────────
$total_produtos = $conexao->query("SELECT COUNT(*) FROM produtos")->fetchColumn();
$criticos_baixo = $conexao->query("SELECT COUNT(*) FROM produtos WHERE status IN ('Crítico', 'Baixo')")->fetchColumn();
$zerados        = $conexao->query("SELECT COUNT(*) FROM produtos WHERE estoque_atual = 0")->fetchColumn();
$total_valor    = $conexao->query("SELECT COALESCE(SUM(estoque_atual * custo_unitario), 0) FROM produtos")->fetchColumn();

// ── PRODUCTS QUERY ───────────────────────────────────────────
$query = "
    SELECT p.*, c.nome AS categoria_nome, c.cor AS categoria_cor
    FROM produtos p
    LEFT JOIN categorias c ON p.categoria_id = c.id
    WHERE $where_clause
    ORDER BY p.nome ASC
";
$stmt = $conexao->prepare($query);
$stmt->execute($params);
$produtos_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch categories for the filter dropdown
$todas_categorias = $conexao->query("SELECT id, nome FROM categorias ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);

$pagina_atual = 'produtos';
$titulo_pagina = 'Catálogo de Produtos';
include '../_header.php';
?>

<!-- Injecting page-specific styling -->
<style>
    .filters-card {
        background: #fff;
        border: 1px solid #e8e8e8;
        border-radius: 12px;
        padding: 16px 20px;
        margin-bottom: 24px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.02);
    }
    .filters-form {
        display: grid;
        grid-template-columns: 2fr 1.5fr 1.5fr auto;
        gap: 16px;
        align-items: flex-end;
    }
    .filter-group {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }
    .filter-group label {
        font-size: 0.78rem;
        font-weight: 600;
        color: #666;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .filter-group input, .filter-group select {
        padding: 8px 12px;
        border: 1.5px solid #e5e5e5;
        border-radius: 6px;
        font-family: 'Inter', sans-serif;
        font-size: 0.875rem;
        background: #fafafa;
        outline: none;
        transition: all 0.2s;
    }
    .filter-group input:focus, .filter-group select:focus {
        border-color: #2db35d;
        background: #fff;
    }
    .filter-actions-inline {
        display: flex;
        gap: 8px;
    }
    .filter-actions-inline .btn {
        height: 38px;
        padding: 0 16px;
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 16px;
        margin-bottom: 24px;
    }
    .stat-card {
        background: #fff;
        border: 1px solid #e8e8e8;
        border-radius: 12px;
        padding: 20px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.02);
    }
    .stat-label { font-size: 0.78rem; color: #666; margin-bottom: 6px; font-weight: 500; }
    .stat-value { font-size: 1.6rem; font-weight: 700; line-height: 1.1; }
    .stat-subtext { font-size: 0.75rem; color: #888; margin-top: 4px; }
    .stat-icon {
        width: 44px; height: 44px;
        border-radius: 10px;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.2rem;
        flex-shrink: 0;
    }

    /* Progress bar */
    .progress-bar-wrap {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-top: 4px;
    }
    .progress-bar {
        height: 6px;
        background: #f0f0f0;
        border-radius: 4px;
        flex: 1;
        overflow: hidden;
    }
    .progress-fill {
        height: 100%;
        border-radius: 4px;
        transition: width 0.4s;
    }
    .fill-green { background: #2db35d; }
    .fill-yellow { background: #eab308; }
    .fill-red { background: #ef4444; }

    @media(max-width: 1024px) {
        .filters-form {
            grid-template-columns: 1fr 1fr;
        }
        .filter-actions-inline {
            grid-column: span 2;
            justify-content: flex-end;
        }
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }
    @media(max-width: 600px) {
        .filters-form {
            grid-template-columns: 1fr;
        }
        .filter-actions-inline {
            grid-column: span 1;
        }
        .stats-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="content">
    <!-- Page Header -->
    <div class="page-header">
        <div class="page-header-left">
            <h2>Catálogo de Produtos</h2>
            <p>Gerencie ingredientes, insumos e configure as regras de estoque mínimo.</p>
        </div>
        <a href="nova.php" class="btn btn-primary">+ Novo Produto</a>
    </div>

    <!-- Feedback Alerts -->
    <?php if (isset($_GET['msg'])): ?>
        <?php if ($_GET['msg'] === 'criado'): ?>
            <div class="alert alert-success">Produto cadastrado com sucesso!</div>
        <?php elseif ($_GET['msg'] === 'editado'): ?>
            <div class="alert alert-success">Produto atualizado com sucesso!</div>
        <?php elseif ($_GET['msg'] === 'excluido'): ?>
            <div class="alert alert-success">Produto excluído com sucesso!</div>
        <?php endif; ?>
    <?php endif; ?>

    <?php if (isset($_GET['erro'])): ?>
        <?php if ($_GET['erro'] === 'vinculado'): ?>
            <div class="alert alert-danger">Não é possível excluir este produto: existem lotes ou descartes ativos associados a ele.</div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- KPI Dashboard cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div>
                <div class="stat-label">Total do Catálogo</div>
                <div class="stat-value"><?= number_format($total_produtos) ?></div>
                <div class="stat-subtext">Produtos cadastrados</div>
            </div>
            <div class="stat-icon" style="background: #f0fdf4; color: #16a34a;">
                📦
            </div>
        </div>

        <div class="stat-card">
            <div>
                <div class="stat-label">Estoque Crítico / Baixo</div>
                <div class="stat-value" style="color: <?= $criticos_baixo > 0 ? '#dc2626' : '#16a34a' ?>;"><?= number_format($criticos_baixo) ?></div>
                <div class="stat-subtext">Abaixo do limite mínimo</div>
            </div>
            <div class="stat-icon" style="background: #fee2e2; color: #dc2626;">
                ⚠️
            </div>
        </div>

        <div class="stat-card">
            <div>
                <div class="stat-label">Produtos Zerados</div>
                <div class="stat-value" style="color: <?= $zerados > 0 ? '#b91c1c' : '#1a1a1a' ?>;"><?= number_format($zerados) ?></div>
                <div class="stat-subtext font-weight-bold">Estoque completamente vazio</div>
            </div>
            <div class="stat-icon" style="background: #fff7ed; color: #ea580c;">
                ❌
            </div>
        </div>

        <div class="stat-card">
            <div>
                <div class="stat-label">Valor Estimado</div>
                <div class="stat-value">R$ <?= number_format($total_valor, 2, ',', '.') ?></div>
                <div class="stat-subtext">Baseado no custo unitário</div>
            </div>
            <div class="stat-icon" style="background: #eff6ff; color: #2563eb;">
                R$
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="filters-card">
        <form method="GET" action="index.php" class="filters-form">
            <div class="filter-group">
                <label for="q">Buscar por nome</label>
                <input type="text" name="q" id="q" placeholder="Ex: Filé Mignon..." value="<?= htmlspecialchars($search) ?>">
            </div>

            <div class="filter-group">
                <label for="categoria_id">Categoria</label>
                <select name="categoria_id" id="categoria_id">
                    <option value="">Todas as categorias</option>
                    <?php foreach($todas_categorias as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $filtro_categoria == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-group">
                <label for="status">Situação de Estoque</label>
                <select name="status" id="status">
                    <option value="">Todas as situações</option>
                    <option value="Normal" <?= $filtro_status === 'Normal' ? 'selected' : '' ?>>Normal</option>
                    <option value="Baixo" <?= $filtro_status === 'Baixo' ? 'selected' : '' ?>>Baixo</option>
                    <option value="Crítico" <?= $filtro_status === 'Crítico' ? 'selected' : '' ?>>Crítico</option>
                    <option value="Alto" <?= $filtro_status === 'Alto' ? 'selected' : '' ?>>Alto</option>
                    <option value="Zerado" <?= $filtro_status === 'Zerado' ? 'selected' : '' ?>>Zerado (Fora de estoque)</option>
                </select>
            </div>

            <div class="filter-actions-inline">
                <button type="submit" class="btn btn-primary">Filtrar</button>
                <a href="index.php" class="btn btn-secondary">Limpar</a>
            </div>
        </form>
    </div>

    <!-- Table Card -->
    <div class="table-card">
        <table>
            <thead>
                <tr>
                    <th>Nome do Produto</th>
                    <th>Categoria</th>
                    <th>Estoque Atual</th>
                    <th>Mínimo / Máximo</th>
                    <th style="width: 150px;">Nível de Alerta</th>
                    <th>Custo Unitário</th>
                    <th>Valor em Estoque</th>
                    <th style="text-align: center;">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($produtos_list)): ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 48px; color: #888;">
                            Nenhum produto cadastrado ou correspondente aos filtros aplicados.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach($produtos_list as $p): 
                        // Progress bar calculations
                        $pct = 0;
                        if (floatval($p['estoque_minimo']) > 0) {
                            $pct = min(100, round((floatval($p['estoque_atual']) / floatval($p['estoque_minimo'])) * 100));
                        } elseif (floatval($p['estoque_atual']) > 0) {
                            $pct = 100;
                        }

                        // Status badge logic
                        $badgeCls = 'badge-normal';
                        $fillCls = 'fill-green';
                        
                        if (floatval($p['estoque_atual']) == 0) {
                            $badgeCls = 'badge-critico';
                            $fillCls = 'fill-red';
                            $statusText = 'Zerado';
                        } else {
                            $statusText = $p['status'];
                            if ($p['status'] === 'Crítico') {
                                $badgeCls = 'badge-critico';
                                $fillCls = 'fill-red';
                            } elseif ($p['status'] === 'Baixo') {
                                $badgeCls = 'badge-atencao';
                                $fillCls = 'fill-yellow';
                            } elseif ($p['status'] === 'Alto') {
                                $badgeCls = 'badge-normal';
                                $fillCls = 'fill-green';
                            }
                        }

                        $valor_total = floatval($p['estoque_atual']) * floatval($p['custo_unitario']);
                    ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($p['nome']) ?></strong>
                                <?php if (!empty($p['codigo_barras'])): ?>
                                    <br><small style="color: #6b7280; font-size: 0.72rem;">EAN: <?= htmlspecialchars($p['codigo_barras']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($p['categoria_nome']): ?>
                                    <span class="badge" style="background: <?= htmlspecialchars($p['categoria_cor'] ?? '#e8e8e8') ?>15; color: <?= htmlspecialchars($p['categoria_cor'] ?? '#666') ?>; border: 1px solid <?= htmlspecialchars($p['categoria_cor'] ?? '#e8e8e8') ?>40;">
                                        <?= htmlspecialchars($p['categoria_nome']) ?>
                                    </span>
                                <?php else: ?>
                                    <span style="color: #aaa;">Sem categoria</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong style="color: <?= floatval($p['estoque_atual']) == 0 ? '#b91c1c' : '#1a1a1a' ?>;">
                                    <?= number_format($p['estoque_atual'], 2, ',', '.') ?>
                                </strong>
                                <small style="color: #666; font-size: 0.8rem;"><?= htmlspecialchars($p['unidade']) ?></small>
                            </td>
                            <td>
                                <span style="font-size: 0.85rem; color: #555;">
                                    Min: <?= number_format($p['estoque_minimo'], 2, ',', '.') ?><br>
                                    Max: <?= floatval($p['estoque_maximo']) > 0 ? number_format($p['estoque_maximo'], 2, ',', '.') : '—' ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge <?= $badgeCls ?>"><?= htmlspecialchars($statusText) ?></span>
                                <div class="progress-bar-wrap">
                                    <div class="progress-bar">
                                        <div class="progress-fill <?= $fillCls ?>" style="width: <?= $pct ?>%;"></div>
                                    </div>
                                    <span style="font-size: 0.72rem; color: #888; min-width: 32px; text-align: right;"><?= $pct ?>%</span>
                                </div>
                            </td>
                            <td>R$ <?= number_format($p['custo_unitario'], 2, ',', '.') ?></td>
                            <td><strong>R$ <?= number_format($valor_total, 2, ',', '.') ?></strong></td>
                            <td style="text-align: center;">
                                <div style="display: inline-flex; gap: 8px;">
                                    <a href="editar.php?id=<?= $p['id'] ?>" class="btn btn-secondary btn-sm" style="padding: 6px 10px;">
                                        Editar
                                    </a>
                                    <a href="excluir.php?id=<?= $p['id'] ?>" class="btn btn-danger btn-sm" style="padding: 6px 10px;" onclick="return confirm('Deseja realmente excluir o produto <?= htmlspecialchars($p['nome']) ?>?')">
                                        Excluir
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include '../_footer.php'; ?>