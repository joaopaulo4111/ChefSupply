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

// Supplier filter
$filtro_fornecedor = intval($_GET['fornecedor_id'] ?? 0);
if ($filtro_fornecedor > 0) {
    $where[] = "l.fornecedor_id = :fornecedor_id";
    $params[':fornecedor_id'] = $filtro_fornecedor;
}

// Start Date
$filtro_inicio = trim($_GET['data_inicio'] ?? '');
if ($filtro_inicio !== '') {
    $where[] = "l.data_entrada >= :data_inicio";
    $params[':data_inicio'] = $filtro_inicio;
}

// End Date
$filtro_fim = trim($_GET['data_fim'] ?? '');
if ($filtro_fim !== '') {
    $where[] = "l.data_entrada <= :data_fim";
    $params[':data_fim'] = $filtro_fim;
}

$where_clause = implode(" AND ", $where);

// ── KPI CALCULATIONS ─────────────────────────────────────────
$stmtTotal = $conexao->prepare("
    SELECT COALESCE(SUM(l.preco_custo * l.quantidade), 0) 
    FROM lotes l
    JOIN produtos p ON l.produto_id = p.id
    WHERE $where_clause
");
$stmtTotal->execute($params);
$total_compra = $stmtTotal->fetchColumn();

$stmtCount = $conexao->prepare("
    SELECT COUNT(*) 
    FROM lotes l
    JOIN produtos p ON l.produto_id = p.id
    WHERE $where_clause
");
$stmtCount->execute($params);
$total_operacoes = $stmtCount->fetchColumn();

$stmtVol = $conexao->prepare("
    SELECT COALESCE(SUM(l.quantidade), 0) 
    FROM lotes l
    JOIN produtos p ON l.produto_id = p.id
    WHERE $where_clause
");
$stmtVol->execute($params);
$total_volume = $stmtVol->fetchColumn();

$custo_medio = $total_operacoes > 0 ? $total_compra / $total_operacoes : 0;

// ── ENTRIES HISTORY QUERY ────────────────────────────────────
$query = "
    SELECT l.*, p.nome AS produto_nome, p.unidade AS produto_unidade,
           f.nome AS fornecedor_nome, u.nome AS usuario_nome
    FROM lotes l
    JOIN produtos p ON l.produto_id = p.id
    LEFT JOIN fornecedores f ON l.fornecedor_id = f.id
    LEFT JOIN usuarios u ON l.usuario_id = u.id
    WHERE $where_clause
    ORDER BY l.data_entrada DESC, l.id DESC
";
$stmt = $conexao->prepare($query);
$stmt->execute($params);
$entradas_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch suppliers for filter dropdown
$todos_fornecedores = $conexao->query("SELECT id, nome FROM fornecedores ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);

$pagina_atual = 'entradas';
$titulo_pagina = 'Histórico de Entradas';
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
        grid-template-columns: 1.8fr 1.5fr 1fr 1fr auto;
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
            <h2>Histórico de Entradas</h2>
            <p>Monitore os recebimentos de mercadoria e gerencie o custo de aquisição dos insumos.</p>
        </div>
        <a href="nova.php" class="btn btn-primary">+ Registrar Entrada</a>
    </div>

    <!-- Feedback Alerts -->
    <?php if (isset($_GET['msg'])): ?>
        <?php if ($_GET['msg'] === 'criado'): ?>
            <div class="alert alert-success">Entrada registrada com sucesso! O estoque foi adicionado.</div>
        <?php elseif ($_GET['msg'] === 'estornado'): ?>
            <div class="alert alert-success">Entrada estornada com sucesso! As quantidades foram removidas do estoque.</div>
        <?php endif; ?>
    <?php endif; ?>

    <?php if (isset($_GET['erro'])): ?>
        <?php if ($_GET['erro'] === 'nao_encontrado'): ?>
            <div class="alert alert-danger">O registro de entrada solicitado não foi encontrado.</div>
        <?php elseif ($_GET['erro'] === 'consumido'): ?>
            <div class="alert alert-danger">Não é possível estornar esta entrada: partes deste lote já foram consumidas ou descartadas do estoque.</div>
        <?php elseif ($_GET['erro'] === 'falha_estorno'): ?>
            <div class="alert alert-danger">Falha ao realizar estorno da entrada. Detalhes: <?= htmlspecialchars($_GET['detalhe'] ?? '') ?></div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- KPI Summary Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div>
                <div class="stat-label">Custo Total Recebido</div>
                <div class="stat-value" style="color: #2db35d;">R$ <?= number_format($total_compra, 2, ',', '.') ?></div>
                <div class="stat-subtext">Valor total investido</div>
            </div>
            <div class="stat-icon" style="background: #eafaf1; color: #2db35d;">
                R$
            </div>
        </div>

        <div class="stat-card">
            <div>
                <div class="stat-label">Recebimentos</div>
                <div class="stat-value"><?= number_format($total_operacoes) ?></div>
                <div class="stat-subtext">Operações de entrada registradas</div>
            </div>
            <div class="stat-icon" style="background: #eff6ff; color: #2563eb;">
                📥
            </div>
        </div>

        <div class="stat-card">
            <div>
                <div class="stat-label">Custo Médio por Lote</div>
                <div class="stat-value">R$ <?= number_format($custo_medio, 2, ',', '.') ?></div>
                <div class="stat-subtext">Valor médio por lote</div>
            </div>
            <div class="stat-icon" style="background: #fff7ed; color: #ea580c;">
                ⚖️
            </div>
        </div>

        <div class="stat-card">
            <div>
                <div class="stat-label">Volume de Insumos</div>
                <div class="stat-value"><?= number_format($total_volume, 2, ',', '.') ?></div>
                <div class="stat-subtext">Quantidade física de entradas</div>
            </div>
            <div class="stat-icon" style="background: #fdf2f8; color: #db2777;">
                🚚
            </div>
        </div>
    </div>

    <!-- Filters and Search -->
    <div class="filters-card">
        <form method="GET" action="index.php" class="filters-form">
            <div class="filter-group">
                <label for="q">Buscar produto / lote</label>
                <input type="text" name="q" id="q" placeholder="Nome do produto ou código do lote..." value="<?= htmlspecialchars($search) ?>">
            </div>

            <div class="filter-group">
                <label for="fornecedor_id">Fornecedor</label>
                <select name="fornecedor_id" id="fornecedor_id">
                    <option value="">Todos os fornecedores</option>
                    <?php foreach($todos_fornecedores as $tf): ?>
                        <option value="<?= $tf['id'] ?>" <?= $filtro_fornecedor == $tf['id'] ? 'selected' : '' ?>><?= htmlspecialchars($tf['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-group">
                <label for="data_inicio">Entrada De</label>
                <input type="date" name="data_inicio" id="data_inicio" value="<?= htmlspecialchars($filtro_inicio) ?>">
            </div>

            <div class="filter-group">
                <label for="data_fim">Até</label>
                <input type="date" name="data_fim" id="data_fim" value="<?= htmlspecialchars($filtro_fim) ?>">
            </div>

            <div class="filter-actions-inline">
                <button type="submit" class="btn btn-primary">Filtrar</button>
                <a href="index.php" class="btn btn-secondary">Limpar</a>
            </div>
        </form>
    </div>

    <!-- Entries Table -->
    <div class="table-card">
        <table>
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Produto</th>
                    <th>Identificação / Lote</th>
                    <th>Fornecedor</th>
                    <th>Qtd. Recebida</th>
                    <th>Custo Unit.</th>
                    <th>Valor Total</th>
                    <th>Vencimento</th>
                    <th>Operador</th>
                    <th style="text-align: center;">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($entradas_list)): ?>
                    <tr>
                        <td colspan="10" style="text-align: center; padding: 48px; color: #888;">
                            Nenhum registro de entrada de mercadoria encontrado para os filtros selecionados.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($entradas_list as $l): 
                        $valor_operacao = floatval($l['quantidade']) * floatval($l['preco_custo']);
                    ?>
                        <tr>
                            <td><strong><?= date('d/m/Y', strtotime($l['data_entrada'])) ?></strong></td>
                            <td><strong><?= htmlspecialchars($l['produto_nome']) ?></strong></td>
                            <td>
                                <?php if ($l['codigo_lote']): ?>
                                    <span class="badge" style="background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd;"><?= htmlspecialchars($l['codigo_lote']) ?></span>
                                <?php else: ?>
                                    <span class="badge" style="background: #f3f4f6; color: #4b5563;">#<?= $l['id'] ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($l['fornecedor_name'] ?? $l['fornecedor_nome'] ?? '—') ?></td>
                            <td>
                                <strong><?= number_format($l['quantidade'], 2, ',', '.') ?></strong>
                                <small style="color: #666;"><?= htmlspecialchars($l['produto_unidade']) ?></small>
                            </td>
                            <td>R$ <?= number_format($l['preco_custo'], 2, ',', '.') ?></td>
                            <td style="color: #16a34a; font-weight: 600;">R$ <?= number_format($valor_operacao, 2, ',', '.') ?></td>
                            <td>
                                <?php if ($l['data_vencimento']): 
                                    $dataVenc = strtotime($l['data_vencimento']);
                                    $diasRestantes = ceil(($dataVenc - time()) / 86400);
                                    $vencCls = '';
                                    if ($diasRestantes < 0) {
                                        $vencCls = 'color: #dc2626; font-weight: 600;';
                                    } elseif ($diasRestantes <= 7) {
                                        $vencCls = 'color: #ca8a04; font-weight: 600;';
                                    }
                                ?>
                                    <span style="<?= $vencCls ?>"><?= date('d/m/Y', $dataVenc) ?></span>
                                <?php else: ?>
                                    <span style="color: #aaa;">Não informado</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($l['usuario_nome'] ?? '—') ?></td>
                            <td style="text-align: center;">
                                <a href="excluir.php?id=<?= $l['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Deseja realmente estornar esta entrada de estoque? O lote correspondente será removido e a quantidade subtraída do estoque.')">
                                    Estornar
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include '../_footer.php'; ?>