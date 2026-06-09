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

// Filter by product
$filtro_produto = intval($_GET['produto_id'] ?? 0);
if($filtro_produto > 0){
    $where[] = "d.produto_id = :produto_id";
    $params[':produto_id'] = $filtro_produto;
}

// Filter by motive
$filtro_motivo = trim($_GET['motivo'] ?? '');
if($filtro_motivo !== ''){
    $where[] = "d.motivo = :motivo";
    $params[':motivo'] = $filtro_motivo;
}

// Filter by start date
$filtro_inicio = trim($_GET['data_inicio'] ?? '');
if($filtro_inicio !== ''){
    $where[] = "d.data_descarte >= :data_inicio";
    $params[':data_inicio'] = $filtro_inicio;
}

// Filter by end date
$filtro_fim = trim($_GET['data_fim'] ?? '');
if($filtro_fim !== ''){
    $where[] = "d.data_descarte <= :data_fim";
    $params[':data_fim'] = $filtro_fim;
}

// Text search (Product name or observations)
$search = trim($_GET['q'] ?? '');
if($search !== ''){
    $where[] = "(p.nome LIKE :search OR d.observacoes LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

$where_clause = implode(" AND ", $where);

// ── KPI CALCULATIONS ─────────────────────────────────────────
// 1. Total financial loss value
$stmtLoss = $conexao->prepare("
    SELECT COALESCE(SUM(d.valor_perdido), 0) 
    FROM descartes d
    JOIN produtos p ON d.produto_id = p.id
    WHERE $where_clause
");
$stmtLoss->execute($params);
$total_perdido = $stmtLoss->fetchColumn();

// 2. Total number of records (descartes)
$stmtCount = $conexao->prepare("
    SELECT COUNT(*) 
    FROM descartes d
    JOIN produtos p ON d.produto_id = p.id
    WHERE $where_clause
");
$stmtCount->execute($params);
$total_registros = $stmtCount->fetchColumn();

// 3. Total volume/quantity sum
$stmtVol = $conexao->prepare("
    SELECT COALESCE(SUM(d.quantidade), 0) 
    FROM descartes d
    JOIN produtos p ON d.produto_id = p.id
    WHERE $where_clause
");
$stmtVol->execute($params);
$total_volume = $stmtVol->fetchColumn();

// 4. Primary motive
$stmtMot = $conexao->prepare("
    SELECT d.motivo, COUNT(*) as qtd
    FROM descartes d
    JOIN produtos p ON d.produto_id = p.id
    WHERE $where_clause
    GROUP BY d.motivo
    ORDER BY qtd DESC
    LIMIT 1
");
$stmtMot->execute($params);
$motivo_principal_row = $stmtMot->fetch(PDO::FETCH_ASSOC);
$motivo_principal = $motivo_principal_row ? $motivo_principal_row['motivo'] : 'Nenhum';

// ── LISTING QUERY ────────────────────────────────────────────
$query = "
    SELECT d.*, p.nome AS produto_nome, p.unidade AS produto_unidade,
           l.codigo_lote AS lote_codigo, u.nome AS usuario_nome
    FROM descartes d
    JOIN produtos p ON d.produto_id = p.id
    LEFT JOIN lotes l ON d.lote_id = l.id
    LEFT JOIN usuarios u ON d.usuario_id = u.id
    WHERE $where_clause
    ORDER BY d.data_descarte DESC, d.id DESC
";
$stmtDescartes = $conexao->prepare($query);
$stmtDescartes->execute($params);
$descartes = $stmtDescartes->fetchAll(PDO::FETCH_ASSOC);

// Fetch products for filter dropdown
$todos_produtos = $conexao->query("SELECT id, nome FROM produtos ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);

// Page titles and active state
$pagina_atual = 'descartes';
$titulo_pagina = 'Descartes e Perdas';
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
        grid-template-columns: 1.5fr 1fr 1fr 1fr 1fr auto;
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
    <!-- Header Page Actions -->
    <div class="page-header">
        <div class="page-header-left">
            <h2>Descartes & Perdas</h2>
            <p>Monitore produtos descartados, vencidos ou danificados e as perdas financeiras.</p>
        </div>
        <a href="novo.php" class="btn btn-primary">+ Novo Descarte</a>
    </div>

    <!-- Alert Messaging -->
    <?php if(isset($_GET['msg'])): ?>
        <?php if($_GET['msg'] === 'criado'): ?>
            <div class="alert alert-success">Descarte registrado com sucesso! O estoque foi atualizado.</div>
        <?php elseif($_GET['msg'] === 'estornado'): ?>
            <div class="alert alert-success">Descarte estornado com sucesso! Os produtos foram devolvidos ao estoque.</div>
        <?php endif; ?>
    <?php endif; ?>

    <?php if(isset($_GET['erro'])): ?>
        <?php if($_GET['erro'] === 'nao_encontrado'): ?>
            <div class="alert alert-danger">O registro de descarte solicitado não foi encontrado.</div>
        <?php elseif($_GET['erro'] === 'falha_estorno'): ?>
            <div class="alert alert-danger">Falha ao realizar estorno do descarte. Tente novamente. Details: <?= htmlspecialchars($_GET['detalhe'] ?? '') ?></div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- KPI Summary Grid -->
    <div class="stats-grid">
        <div class="stat-card">
            <div>
                <div class="stat-label">Custo Total Perdido</div>
                <div class="stat-value" style="color: #dc2626;">R$ <?= number_format($total_perdido, 2, ',', '.') ?></div>
                <div class="stat-subtext">Valor financeiro descartado</div>
            </div>
            <div class="stat-icon" style="background: #fef2f2; color: #dc2626;">
                R$
            </div>
        </div>

        <div class="stat-card">
            <div>
                <div class="stat-label">Descartes Registrados</div>
                <div class="stat-value"><?= number_format($total_registros) ?></div>
                <div class="stat-subtext">Ocorrências no período</div>
            </div>
            <div class="stat-icon" style="background: #fff7ed; color: #ea580c;">
                📋
            </div>
        </div>

        <div class="stat-card">
            <div>
                <div class="stat-label">Volume Descartado</div>
                <div class="stat-value"><?= number_format($total_volume, 2, ',', '.') ?></div>
                <div class="stat-subtext">Total de itens/pesos</div>
            </div>
            <div class="stat-icon" style="background: #eff6ff; color: #2563eb;">
                ⚖️
            </div>
        </div>

        <div class="stat-card">
            <div>
                <div class="stat-label">Motivo Principal</div>
                <div class="stat-value" style="font-size: 1.25rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 160px;"><?= htmlspecialchars($motivo_principal) ?></div>
                <div class="stat-subtext">Causa mais comum de perdas</div>
            </div>
            <div class="stat-icon" style="background: #fdf2f8; color: #db2777;">
                ⚠️
            </div>
        </div>
    </div>

    <!-- Filter and Search Form -->
    <div class="filters-card">
        <form method="GET" action="index.php" class="filters-form">
            <div class="filter-group">
                <label for="q">Buscar</label>
                <input type="text" name="q" id="q" placeholder="Buscar por produto/obs..." value="<?= htmlspecialchars($search) ?>">
            </div>

            <div class="filter-group">
                <label for="produto_id">Produto</label>
                <select name="produto_id" id="produto_id">
                    <option value="">Todos os produtos</option>
                    <?php foreach($todos_produtos as $tp): ?>
                        <option value="<?= $tp['id'] ?>" <?= $filtro_produto == $tp['id'] ? 'selected' : '' ?>><?= htmlspecialchars($tp['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-group">
                <label for="motivo">Motivo</label>
                <select name="motivo" id="motivo">
                    <option value="">Todos os motivos</option>
                    <option value="Vencimento" <?= $filtro_motivo === 'Vencimento' ? 'selected' : '' ?>>Vencimento</option>
                    <option value="Deterioração" <?= $filtro_motivo === 'Deterioração' ? 'selected' : '' ?>>Deterioração</option>
                    <option value="Excesso de produção" <?= $filtro_motivo === 'Excesso de produção' ? 'selected' : '' ?>>Excesso de produção</option>
                    <option value="Outros" <?= $filtro_motivo === 'Outros' ? 'selected' : '' ?>>Outros</option>
                </select>
            </div>

            <div class="filter-group">
                <label for="data_inicio">De</label>
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

    <!-- Table of Results -->
    <div class="table-card">
        <table>
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Produto</th>
                    <th>Lote</th>
                    <th>Quantidade</th>
                    <th>Motivo</th>
                    <th>Valor Perdido</th>
                    <th>Observações</th>
                    <th>Operador</th>
                    <th style="text-align: center;">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($descartes)): ?>
                    <tr>
                        <td colspan="9" style="text-align: center; padding: 48px; color: #888;">
                            Nenhum registro de descarte encontrado para os filtros selecionados.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach($descartes as $d): ?>
                        <tr>
                            <td><strong><?= date('d/m/Y', strtotime($d['data_descarte'])) ?></strong></td>
                            <td><strong><?= htmlspecialchars($d['produto_nome']) ?></strong></td>
                            <td>
                                <?php if($d['lote_codigo']): ?>
                                    <span class="badge badge-normal" style="background: #e0f2fe; color: #0369a1;"><?= htmlspecialchars($d['lote_codigo']) ?></span>
                                <?php else: ?>
                                    <span style="color: #aaa;">—</span>
                                <?php endif; ?>
                            </td>
                            <td><?= number_format($d['quantidade'], 2, ',', '.') ?> &nbsp;<small style="color: #666;"><?= htmlspecialchars($d['produto_unidade']) ?></small></td>
                            <td>
                                <?php 
                                    $m = $d['motivo'];
                                    $badgeClass = 'badge-normal';
                                    if ($m === 'Vencimento') $badgeClass = 'badge-critico';
                                    elseif ($m === 'Deterioração') $badgeClass = 'badge-atencao';
                                ?>
                                <span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($m) ?></span>
                            </td>
                            <td style="color: #dc2626; font-weight: 600;">R$ <?= number_format($d['valor_perdido'], 2, ',', '.') ?></td>
                            <td title="<?= htmlspecialchars($d['observacoes']) ?>">
                                <?= htmlspecialchars(mb_strimwidth($d['observacoes'], 0, 35, '...')) ?>
                            </td>
                            <td><?= htmlspecialchars($d['usuario_nome'] ?? 'N/D') ?></td>
                            <td style="text-align: center;">
                                <a href="excluir.php?id=<?= $d['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Deseja realmente estornar este descarte? Esta ação devolverá <?= number_format($d['quantidade'], 2, ',', '.') ?> <?= htmlspecialchars($d['produto_unidade']) ?> ao estoque.')">
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