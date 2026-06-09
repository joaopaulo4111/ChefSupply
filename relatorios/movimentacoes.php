<?php
session_start();
if(!isset($_SESSION['logado']) || !$_SESSION['logado']){
    header('Location: ../index.php');
    exit;
}
require_once '../conexao.php';

// Safe date validation/sanitization
$data_inicio = isset($_GET['data_inicio']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['data_inicio']) ? $_GET['data_inicio'] : date('Y-m-01');
$data_fim    = isset($_GET['data_fim']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['data_fim']) ? $_GET['data_fim'] : date('Y-m-d');

// Secure Query using Prepared Statements for Entradas
$stmtEntradas = $conexao->prepare("
    SELECT l.*, p.nome as produto_nome, p.unidade, f.nome as fornecedor_nome,
           (l.quantidade * l.preco_custo) as valor_total
    FROM lotes l
    INNER JOIN produtos p ON p.id = l.produto_id
    LEFT JOIN fornecedores f ON f.id = l.fornecedor_id
    WHERE l.data_entrada BETWEEN :start_date AND :end_date
    ORDER BY l.data_entrada DESC
");
$stmtEntradas->execute([':start_date' => $data_inicio, ':end_date' => $data_fim]);
$entradas = $stmtEntradas->fetchAll(PDO::FETCH_ASSOC);

// Secure Query using Prepared Statements for Saidas (Descartes)
$stmtSaidas = $conexao->prepare("
    SELECT d.*, p.nome as produto_nome, p.unidade, u.nome as usuario_nome
    FROM descartes d
    INNER JOIN produtos p ON p.id = d.produto_id
    LEFT JOIN usuarios u ON u.id = d.usuario_id
    WHERE d.data_descarte BETWEEN :start_date AND :end_date
    ORDER BY d.data_descarte DESC
");
$stmtSaidas->execute([':start_date' => $data_inicio, ':end_date' => $data_fim]);
$saidas = $stmtSaidas->fetchAll(PDO::FETCH_ASSOC);

// Calculate totals safely
$total_entradas = array_sum(array_column($entradas, 'valor_total'));
$total_perdas   = array_sum(array_column($saidas,   'valor_perdido'));

// ── Daily data aggregation for Chart.js ──
$entradas_diarias = [];
foreach ($entradas as $e) {
    $dia = $e['data_entrada'];
    if (!isset($entradas_diarias[$dia])) {
        $entradas_diarias[$dia] = 0;
    }
    $entradas_diarias[$dia] += floatval($e['valor_total']);
}

$saidas_diarias = [];
foreach ($saidas as $s) {
    $dia = $s['data_descarte'];
    if (!isset($saidas_diarias[$dia])) {
        $saidas_diarias[$dia] = 0;
    }
    $saidas_diarias[$dia] += floatval($s['valor_perdido']);
}

// Merge all unique dates and sort them
$todas_datas = array_unique(array_merge(array_keys($entradas_diarias), array_keys($saidas_diarias)));
sort($todas_datas);

// Build chart variables
$labels_chart = [];
$valores_entradas_chart = [];
$valores_saidas_chart = [];

foreach ($todas_datas as $dt) {
    $labels_chart[] = date('d/m', strtotime($dt));
    $valores_entradas_chart[] = $entradas_diarias[$dt] ?? 0;
    $valores_saidas_chart[] = $saidas_diarias[$dt] ?? 0;
}

$labels_json = json_encode($labels_chart);
$entradas_json = json_encode($valores_entradas_chart);
$saidas_json = json_encode($valores_saidas_chart);

$pagina_atual = 'relatorios';
$titulo_pagina = 'Relatório de Movimentações';
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
        display: flex;
        gap: 16px;
        align-items: flex-end;
        flex-wrap: wrap;
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
    .filter-group input {
        padding: 8px 12px;
        border: 1.5px solid #e5e5e5;
        border-radius: 6px;
        font-family: 'Inter', sans-serif;
        font-size: 0.875rem;
        background: #fafafa;
        outline: none;
        transition: all 0.2s;
    }
    .filter-group input:focus {
        border-color: #2db35d;
        background: #fff;
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
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
    .stat-value { font-size: 1.5rem; font-weight: 700; line-height: 1.1; }
    .stat-icon {
        width: 44px; height: 44px;
        border-radius: 10px;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.2rem;
        flex-shrink: 0;
    }
    .table-card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 16px;
    }
    .table-title {
        font-size: 1rem;
        font-weight: 600;
        color: #1a1a1a;
    }

    @media(max-width: 768px) {
        .stats-grid {
            grid-template-columns: 1fr;
        }
        .filters-form {
            flex-direction: column;
            align-items: stretch;
        }
    }

    @media print {
        body { background: #fff; color: #000; }
        .header, nav, .filters-card, .btn, .page-header a, .chart-container-card { display: none !important; }
        .content { padding: 0; }
        .table-card { border: none; box-shadow: none; padding: 0; }
        th, td { border-bottom: 1px solid #ddd; }
    }
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="content">
    <!-- Page Header -->
    <div class="page-header">
        <div class="page-header-left">
            <h2>Relatório de Movimentações</h2>
            <p>Monitore o fluxo de entrada e descarte (saída) de mercadoria entre <strong><?= date('d/m/Y', strtotime($data_inicio)) ?></strong> e <strong><?= date('d/m/Y', strtotime($data_fim)) ?></strong>.</p>
        </div>
        <div style="display: flex; gap: 8px;">
            <button onclick="window.print()" class="btn btn-secondary">🖨 Imprimir Relatório</button>
            <a href="index.php" class="btn btn-secondary">← Voltar</a>
        </div>
    </div>

    <!-- Period Filters -->
    <div class="filters-card">
        <form method="GET" action="movimentacoes.php" class="filters-form">
            <div class="filter-group">
                <label for="data_inicio">Data de Início</label>
                <input type="date" name="data_inicio" id="data_inicio" value="<?= htmlspecialchars($data_inicio) ?>" required>
            </div>
            <div class="filter-group">
                <label for="data_fim">Data de Fim</label>
                <input type="date" name="data_fim" id="data_fim" value="<?= htmlspecialchars($data_fim) ?>" required>
            </div>
            <button type="submit" class="btn btn-primary" style="height: 38px;">Filtrar Período</button>
        </form>
    </div>

    <!-- Summary Metrics -->
    <div class="stats-grid">
        <div class="stat-card">
            <div>
                <div class="stat-label">Lotes Recebidos</div>
                <div class="stat-value"><?= count($entradas) ?> registros</div>
            </div>
            <div class="stat-icon" style="background: #e0f2fe; color: #0369a1;">📦</div>
        </div>
        <div class="stat-card">
            <div>
                <div class="stat-label">Custo Total das Entradas</div>
                <div class="stat-value" style="color: #2db35d;">R$ <?= number_format($total_entradas, 2, ',', '.') ?></div>
            </div>
            <div class="stat-icon" style="background: #eafaf1; color: #2db35d;">💰</div>
        </div>
        <div class="stat-card">
            <div>
                <div class="stat-label">Valor Total das Perdas</div>
                <div class="stat-value" style="color: #dc2626;">R$ <?= number_format($total_perdas, 2, ',', '.') ?></div>
            </div>
            <div class="stat-icon" style="background: #fef2f2; color: #dc2626;">🗑️</div>
        </div>
    </div>

    <!-- Chart Block for Trends -->
    <div class="filters-card chart-container-card" style="margin-bottom: 24px;">
        <div class="table-card-header" style="margin-bottom: 12px;">
            <span class="table-title">Tendência Diária de Fluxo Financeiro (R$)</span>
            <span style="font-size: 0.85rem; color: #888;">Análise diária de Entradas vs Perdas no período</span>
        </div>
        <div style="width: 100%; height: 260px; position: relative;">
            <canvas id="chartMovimentos"></canvas>
        </div>
    </div>

    <!-- Inputs/Arrivals Table -->
    <div class="table-card" style="margin-bottom: 24px;">
        <div class="table-card-header">
            <span class="table-title">Entradas de Mercadorias</span>
            <span style="font-size: 0.85rem; color: #888;"><?= count($entradas) ?> registro(s)</span>
        </div>
        <table>
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Produto</th>
                    <th>Fornecedor</th>
                    <th>Lote</th>
                    <th>Qtd. Recebida</th>
                    <th>Custo Unit.</th>
                    <th>Valor Total</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($entradas)): ?>
                    <tr><td colspan="7" style="text-align: center; padding: 24px; color: #aaa;">Nenhuma entrada registrada neste período.</td></tr>
                <?php else: foreach($entradas as $e): ?>
                    <tr>
                        <td><?= date('d/m/Y', strtotime($e['data_entrada'])) ?></td>
                        <td><strong><?= htmlspecialchars($e['produto_nome']) ?></strong></td>
                        <td><?= htmlspecialchars($e['fornecedor_nome'] ?? '—') ?></td>
                        <td>
                            <?php if ($e['codigo_lote']): ?>
                                <span class="badge" style="background: #e0f2fe; color: #0369a1;"><?= htmlspecialchars($e['codigo_lote']) ?></span>
                            <?php else: ?>
                                <span style="color: #aaa;">#<?= $e['id'] ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?= number_format($e['quantidade'], 2, ',', '.') ?> &nbsp;<small style="color: #666;"><?= htmlspecialchars($e['unidade']) ?></small></td>
                        <td>R$ <?= number_format($e['preco_custo'], 2, ',', '.') ?></td>
                        <td style="font-weight: 600; color: #16a34a;">R$ <?= number_format($e['valor_total'], 2, ',', '.') ?></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Outputs/Discards Table -->
    <div class="table-card">
        <div class="table-card-header">
            <span class="table-title">Descartes e Perdas (Saídas)</span>
            <span style="font-size: 0.85rem; color: #888;"><?= count($saidas) ?> registro(s)</span>
        </div>
        <table>
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Produto</th>
                    <th>Motivo</th>
                    <th>Qtd. Descartada</th>
                    <th>Valor Perdido</th>
                    <th>Operador</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($saidas)): ?>
                    <tr><td colspan="6" style="text-align: center; padding: 24px; color: #aaa;">Nenhuma perda ou descarte registrado neste período.</td></tr>
                <?php else: foreach($saidas as $s): ?>
                    <tr>
                        <td><?= date('d/m/Y', strtotime($s['data_descarte'])) ?></td>
                        <td><strong><?= htmlspecialchars($s['produto_nome']) ?></strong></td>
                        <td>
                            <?php 
                                $m = $s['motivo'];
                                $badgeClass = 'badge-normal';
                                if ($m === 'Vencimento') $badgeClass = 'badge-critico';
                                elseif ($m === 'Deterioração') $badgeClass = 'badge-atencao';
                            ?>
                            <span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($m) ?></span>
                        </td>
                        <td><?= number_format($s['quantidade'], 2, ',', '.') ?> &nbsp;<small style="color: #666;"><?= htmlspecialchars($s['unidade']) ?></small></td>
                        <td style="color: #dc2626; font-weight: 600;">R$ <?= number_format($s['valor_perdido'], 2, ',', '.') ?></td>
                        <td><?= htmlspecialchars($s['usuario_nome'] ?? '—') ?></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
// ── Chart.js Movements Trend line chart ──
document.addEventListener('DOMContentLoaded', () => {
    const ctx = document.getElementById('chartMovimentos').getContext('2d');
    const labels = <?= $labels_json ?>;
    const dataEntradas = <?= $entradas_json ?>;
    const dataSaidas = <?= $saidas_json ?>;

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels.length ? labels : ['Sem movimentos'],
            datasets: [
                {
                    label: 'Valor das Entradas',
                    data: dataEntradas,
                    borderColor: '#2db35d',
                    backgroundColor: 'rgba(45, 179, 93, 0.05)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.3,
                    pointRadius: 4,
                    pointHoverRadius: 6
                },
                {
                    label: 'Valor das Perdas (Descartes)',
                    data: dataSaidas,
                    borderColor: '#ef4444',
                    backgroundColor: 'rgba(239, 68, 68, 0.05)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.3,
                    pointRadius: 4,
                    pointHoverRadius: 6
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                    labels: {
                        font: { family: 'Inter', size: 12, weight: '500' },
                        usePointStyle: true,
                        boxWidth: 8
                    }
                },
                tooltip: {
                    padding: 12,
                    bodyFont: { family: 'Inter' },
                    titleFont: { family: 'Inter', weight: 'bold' },
                    callbacks: {
                        label: function(ctx) {
                            return ' ' + ctx.dataset.label + ': R$ ' + ctx.parsed.y.toLocaleString('pt-BR', { minimumFractionDigits: 2 });
                        }
                    }
                }
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: { font: { family: 'Inter', size: 11, color: '#666' } }
                },
                y: {
                    grid: { color: '#f3f4f6' },
                    ticks: {
                        font: { family: 'Inter', size: 11, color: '#666' },
                        callback: function(v) {
                            return 'R$ ' + v.toLocaleString('pt-BR');
                        }
                    }
                }
            }
        }
    });
});
</script>

<?php include '../_footer.php'; ?>