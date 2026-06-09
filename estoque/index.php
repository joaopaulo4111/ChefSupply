<?php
session_start();
if(!isset($_SESSION['logado']) || !$_SESSION['logado']){
    header('Location: ../index.php');
    exit;
}
require_once '../conexao.php';

// ── FILTER HANDLING ──────────────────────────────────────────
$where = ["1=1"];
$params = [];

// Text search (Product name, batch code)
$search = trim($_GET['q'] ?? '');
if ($search !== '') {
    $where[] = "(p.nome LIKE :search OR l.codigo_lote LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

// Product filter
$filtro_produto = intval($_GET['produto_id'] ?? 0);
if ($filtro_produto > 0) {
    $where[] = "l.produto_id = :produto_id";
    $params[':produto_id'] = $filtro_produto;
}

// Expiration / Batch status filter
$filtro_situacao = trim($_GET['situacao'] ?? 'ativos');
if ($filtro_situacao === 'vencidos') {
    $where[] = "l.data_vencimento < CURDATE() AND l.quantidade_restante > 0 AND l.status = 'ativo'";
} elseif ($filtro_situacao === 'vencendo') {
    $where[] = "l.data_vencimento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) AND l.quantidade_restante > 0 AND l.status = 'ativo'";
} elseif ($filtro_situacao === 'ativos') {
    $where[] = "l.quantidade_restante > 0 AND l.status = 'ativo'";
} elseif ($filtro_situacao === 'esgotados') {
    $where[] = "(l.quantidade_restante = 0 OR l.status = 'consumido')";
} elseif ($filtro_situacao === 'descartados') {
    $where[] = "l.status = 'descartado'";
}

$where_clause = implode(" AND ", $where);

// ── GLOBAL KPI CALCULATIONS ──────────────────────────────────
$lotes_vencidos = $conexao->query("
    SELECT COUNT(*) FROM lotes 
    WHERE status = 'ativo' 
      AND data_vencimento IS NOT NULL 
      AND data_vencimento < CURDATE() 
      AND quantidade_restante > 0
")->fetchColumn();

$lotes_vencendo = $conexao->query("
    SELECT COUNT(*) FROM lotes 
    WHERE status = 'ativo' 
      AND data_vencimento IS NOT NULL 
      AND data_vencimento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
      AND quantidade_restante > 0
")->fetchColumn();

$total_itens = $conexao->query("
    SELECT COALESCE(SUM(quantidade_restante), 0) FROM lotes 
    WHERE status = 'ativo' AND quantidade_restante > 0
")->fetchColumn();

$total_valor_estoque = $conexao->query("
    SELECT COALESCE(SUM(quantidade_restante * preco_custo), 0) FROM lotes 
    WHERE status = 'ativo' AND quantidade_restante > 0
")->fetchColumn();

// ── BATCHES QUERY ────────────────────────────────────────────
$query = "
    SELECT l.*, p.nome AS produto_nome, p.unidade AS produto_unidade,
           f.nome AS fornecedor_nome
    FROM lotes l
    JOIN produtos p ON l.produto_id = p.id
    LEFT JOIN fornecedores f ON l.fornecedor_id = f.id
    WHERE $where_clause
    ORDER BY 
        CASE WHEN l.data_vencimento IS NULL THEN 1 ELSE 0 END, 
        l.data_vencimento ASC, 
        p.nome ASC
";
$stmt = $conexao->prepare($query);
$stmt->execute($params);
$lotes_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch all products for filter dropdown
$todos_produtos = $conexao->query("SELECT id, nome FROM produtos ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);

$pagina_atual = 'estoque';
$titulo_pagina = 'Lotes em Estoque';
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

    .days-badge {
        display: inline-flex;
        align-items: center;
        padding: 2px 8px;
        border-radius: 12px;
        font-size: 0.75rem;
        font-weight: 600;
    }
    .days-expired { background: #fee2e2; color: #dc2626; }
    .days-warning { background: #fef9c3; color: #ca8a04; }
    .days-ok { background: #dcfce7; color: #16a34a; }
    .days-none { background: #f3f4f6; color: #6b7280; }

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
            <h2>Lotes & Vencimentos</h2>
            <p>Gerencie o estoque de forma fracionada por data de vencimento e controle o giro de insumos.</p>
        </div>
    </div>

    <!-- KPI Dashboard cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div>
                <div class="stat-label">Valor do Estoque Ativo</div>
                <div class="stat-value" style="color: #2db35d;">R$ <?= number_format($total_valor_estoque, 2, ',', '.') ?></div>
                <div class="stat-subtext">Valor total em lotes ativos</div>
            </div>
            <div class="stat-icon" style="background: #eafaf1; color: #2db35d;">
                R$
            </div>
        </div>

        <div class="stat-card">
            <div>
                <div class="stat-label">Lotes Vencidos</div>
                <div class="stat-value" style="color: <?= $lotes_vencidos > 0 ? '#dc2626' : '#16a34a' ?>;"><?= number_format($lotes_vencidos) ?></div>
                <div class="stat-subtext font-weight-bold">Bloqueados para consumo</div>
            </div>
            <div class="stat-icon" style="background: #fee2e2; color: #dc2626;">
                ⚠️
            </div>
        </div>

        <div class="stat-card">
            <div>
                <div class="stat-label">Vencimentos Próximos</div>
                <div class="stat-value" style="color: <?= $lotes_vencendo > 0 ? '#ca8a04' : '#1a1a1a' ?>;"><?= number_format($lotes_vencendo) ?></div>
                <div class="stat-subtext">Vencendo nos próximos 7 dias</div>
            </div>
            <div class="stat-icon" style="background: #fef9c3; color: #ca8a04;">
                ⏳
            </div>
        </div>

        <div class="stat-card">
            <div>
                <div class="stat-label">Volume de Insumos</div>
                <div class="stat-value"><?= number_format($total_itens, 2, ',', '.') ?></div>
                <div class="stat-subtext">Soma das quantidades restantes</div>
            </div>
            <div class="stat-icon" style="background: #eff6ff; color: #2563eb;">
                ⚖️
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="filters-card">
        <form method="GET" action="index.php" class="filters-form">
            <div class="filter-group">
                <label for="q">Buscar lote / produto</label>
                <input type="text" name="q" id="q" placeholder="Nome do produto ou código do lote..." value="<?= htmlspecialchars($search) ?>">
            </div>

            <div class="filter-group">
                <label for="produto_id">Filtrar Produto</label>
                <select name="produto_id" id="produto_id">
                    <option value="">Todos os produtos</option>
                    <?php foreach($todos_produtos as $tp): ?>
                        <option value="<?= $tp['id'] ?>" <?= $filtro_produto == $tp['id'] ? 'selected' : '' ?>><?= htmlspecialchars($tp['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-group">
                <label for="situacao">Validade / Status</label>
                <select name="situacao" id="situacao">
                    <option value="ativos" <?= $filtro_situacao === 'ativos' ? 'selected' : '' ?>>Lotes Ativos (Com saldo)</option>
                    <option value="vencidos" <?= $filtro_situacao === 'vencidos' ? 'selected' : '' ?>>Vencidos</option>
                    <option value="vencendo" <?= $filtro_situacao === 'vencendo' ? 'selected' : '' ?>>Vencendo (Próximos 7 dias)</option>
                    <option value="esgotados" <?= $filtro_situacao === 'esgotados' ? 'selected' : '' ?>>Esgotados (Saldo zero)</option>
                    <option value="descartados" <?= $filtro_situacao === 'descartados' ? 'selected' : '' ?>>Descartados</option>
                </select>
            </div>

            <div class="filter-actions-inline">
                <button type="submit" class="btn btn-primary">Filtrar</button>
                <a href="index.php" class="btn btn-secondary">Limpar</a>
            </div>
        </form>
    </div>

    <!-- Batches List Table -->
    <div class="table-card">
        <table>
            <thead>
                <tr>
                    <th>Lote</th>
                    <th>Produto</th>
                    <th>Fornecedor</th>
                    <th>Data de Entrada</th>
                    <th>Data Vencimento</th>
                    <th>Dias Restantes</th>
                    <th>Qtd. Inicial</th>
                    <th>Qtd. Restante</th>
                    <th>Custo Unit.</th>
                    <th>Valor do Saldo</th>
                    <th style="text-align: center;">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($lotes_list)): ?>
                    <tr>
                        <td colspan="11" style="text-align: center; padding: 48px; color: #888;">
                            Nenhum lote de mercadoria correspondente aos filtros foi encontrado.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($lotes_list as $l): 
                        $saldo_valor = floatval($l['quantidade_restante']) * floatval($l['preco_custo']);
                        
                        // Remaining days calculations
                        $days_text = '—';
                        $days_cls = 'days-none';
                        $show_discard_button = false;

                        if ($l['status'] === 'descartado') {
                            $days_text = 'Descartado';
                            $days_cls = 'days-expired';
                        } elseif (floatval($l['quantidade_restante']) == 0) {
                            $days_text = 'Esgotado';
                            $days_cls = 'days-none';
                        } elseif ($l['data_vencimento']) {
                            $dataVenc = strtotime($l['data_vencimento']);
                            $dataHoje = strtotime(date('Y-m-d'));
                            $diasRestantes = ($dataVenc - $dataHoje) / 86400;

                            if ($diasRestantes < 0) {
                                $diasPassados = abs($diasRestantes);
                                $days_text = "Vencido há {$diasPassados} dia(s)";
                                $days_cls = "days-expired";
                                $show_discard_button = true;
                            } elseif ($diasRestantes == 0) {
                                $days_text = "Vence Hoje!";
                                $days_cls = "days-expired";
                                $show_discard_button = true;
                            } elseif ($diasRestantes <= 7) {
                                $days_text = "{$diasRestantes} dia(s) restante(s)";
                                $days_cls = "days-warning";
                                $show_discard_button = true;
                            } else {
                                $days_text = "{$diasRestantes} dia(s)";
                                $days_cls = "days-ok";
                                $show_discard_button = true;
                            }
                        } else {
                            $days_text = 'Sem vencimento';
                            $days_cls = 'days-ok';
                            $show_discard_button = true;
                        }
                    ?>
                        <tr>
                            <td>
                                <?php if ($l['codigo_lote']): ?>
                                    <span class="badge" style="background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; font-weight: 600;"><?= htmlspecialchars($l['codigo_lote']) ?></span>
                                <?php else: ?>
                                    <span class="badge" style="background: #f3f4f6; color: #4b5563;">#<?= $l['id'] ?></span>
                                <?php endif; ?>
                            </td>
                            <td><strong><?= htmlspecialchars($l['produto_nome']) ?></strong></td>
                            <td><?= htmlspecialchars($l['fornecedor_nome'] ?? '—') ?></td>
                            <td><?= date('d/m/Y', strtotime($l['data_entrada'])) ?></td>
                            <td>
                                <?= $l['data_vencimento'] ? date('d/m/Y', strtotime($l['data_vencimento'])) : '<span style="color: #aaa;">Não informado</span>' ?>
                            </td>
                            <td>
                                <span class="days-badge <?= $days_cls ?>"><?= $days_text ?></span>
                            </td>
                            <td><?= number_format($l['quantidade'], 2, ',', '.') ?> &nbsp;<small style="color: #666;"><?= htmlspecialchars($l['produto_unidade']) ?></small></td>
                            <td>
                                <strong style="color: <?= floatval($l['quantidade_restante']) == 0 ? '#999' : '#1a1a1a' ?>;">
                                    <?= number_format($l['quantidade_restante'], 2, ',', '.') ?>
                                </strong>
                                <small style="color: #666;"><?= htmlspecialchars($l['produto_unidade']) ?></small>
                            </td>
                            <td>R$ <?= number_format($l['preco_custo'], 2, ',', '.') ?></td>
                            <td><strong>R$ <?= number_format($saldo_valor, 2, ',', '.') ?></strong></td>
                            <td style="text-align: center;">
                                <?php if ($show_discard_button): ?>
                                    <a href="../descartes/novo.php?produto_id=<?= $l['produto_id'] ?>&lote_id=<?= $l['id'] ?>" class="btn btn-danger btn-sm" style="padding: 6px 10px;">
                                        Registrar Perda
                                    </a>
                                <?php else: ?>
                                    <span style="color: #ccc; font-size: 0.85rem;">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include '../_footer.php'; ?>