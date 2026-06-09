<?php
session_start();
if(!isset($_SESSION['logado']) || !$_SESSION['logado']){
    header('Location: ../index.php');
    exit;
}
require_once '../conexao.php';

// CNPJ formatter helper function
function formatarCNPJ($cnpj) {
    $cnpj = preg_replace('/\D/', '', $cnpj);
    if (strlen($cnpj) === 14) {
        return preg_replace("/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/", "$1.$2.$3/$4-$5", $cnpj);
    }
    return $cnpj ?: '—';
}

// ── FILTER HANDLING ──────────────────────────────────────────
$where = ["1=1"];
$params = [];

// Search input
$search = trim($_GET['q'] ?? '');
if ($search !== '') {
    $where[] = "(f.nome LIKE :search OR f.cnpj LIKE :search OR f.produtos_fornecidos LIKE :search OR f.email LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

// Status input
$filtro_status = trim($_GET['status'] ?? '');
if ($filtro_status !== '') {
    $where[] = "f.ativo = :status";
    $params[':status'] = intval($filtro_status);
}

$where_clause = implode(" AND ", $where);

// ── KPI CALCULATIONS ─────────────────────────────────────────
$total_fornecedores = $conexao->query("SELECT COUNT(*) FROM fornecedores")->fetchColumn();
$ativos             = $conexao->query("SELECT COUNT(*) FROM fornecedores WHERE ativo = 1")->fetchColumn();
$inativos           = $conexao->query("SELECT COUNT(*) FROM fornecedores WHERE ativo = 0")->fetchColumn();
$lotes_entregues    = $conexao->query("SELECT COUNT(*) FROM lotes WHERE fornecedor_id IS NOT NULL")->fetchColumn();

// ── SUPPLIERS LIST QUERY ─────────────────────────────────────
$query = "
    SELECT f.*, COUNT(l.id) AS total_lotes
    FROM fornecedores f
    LEFT JOIN lotes l ON f.id = l.fornecedor_id
    WHERE $where_clause
    GROUP BY f.id
    ORDER BY f.nome ASC
";
$stmt = $conexao->prepare($query);
$stmt->execute($params);
$fornecedores_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pagina_atual = 'fornecedores';
$titulo_pagina = 'Parceiros Fornecedores';
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
        grid-template-columns: 2fr 1.5fr auto;
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
            <h2>Gestão de Fornecedores</h2>
            <p>Cadastre parceiros, armazene dados de contato e acompanhe os lotes fornecidos.</p>
        </div>
        <a href="novo.php" class="btn btn-primary">+ Novo Fornecedor</a>
    </div>

    <!-- Alert Messaging -->
    <?php if (isset($_GET['msg'])): ?>
        <?php if ($_GET['msg'] === 'criado'): ?>
            <div class="alert alert-success">Fornecedor cadastrado com sucesso!</div>
        <?php elseif ($_GET['msg'] === 'editado'): ?>
            <div class="alert alert-success">Fornecedor atualizado com sucesso!</div>
        <?php elseif ($_GET['msg'] === 'excluido'): ?>
            <div class="alert alert-success">Fornecedor excluído com sucesso!</div>
        <?php endif; ?>
    <?php endif; ?>

    <?php if (isset($_GET['erro'])): ?>
        <?php if ($_GET['erro'] === 'vinculado'): ?>
            <div class="alert alert-danger">Não é possível excluir este fornecedor: existem entradas ou lotes de mercadoria vinculados a ele.</div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- KPI Summary Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div>
                <div class="stat-label">Total Cadastrado</div>
                <div class="stat-value"><?= number_format($total_fornecedores) ?></div>
                <div class="stat-subtext">Parceiros mapeados</div>
            </div>
            <div class="stat-icon" style="background: #eff6ff; color: #2563eb;">
                🤝
            </div>
        </div>

        <div class="stat-card">
            <div>
                <div class="stat-label">Fornecedores Ativos</div>
                <div class="stat-value" style="color: #16a34a;"><?= number_format($ativos) ?></div>
                <div class="stat-subtext font-weight-bold">Aptos a entregar</div>
            </div>
            <div class="stat-icon" style="background: #f0fdf4; color: #16a34a;">
                🟢
            </div>
        </div>

        <div class="stat-card">
            <div>
                <div class="stat-label">Inativos / Bloqueados</div>
                <div class="stat-value" style="color: <?= $inativos > 0 ? '#dc2626' : '#1a1a1a' ?>;"><?= number_format($inativos) ?></div>
                <div class="stat-subtext">Fora de operação</div>
            </div>
            <div class="stat-icon" style="background: #fee2e2; color: #dc2626;">
                🔴
            </div>
        </div>

        <div class="stat-card">
            <div>
                <div class="stat-label">Lotes Fornecidos</div>
                <div class="stat-value"><?= number_format($lotes_entregues) ?></div>
                <div class="stat-subtext">Entradas registradas</div>
            </div>
            <div class="stat-icon" style="background: #fff7ed; color: #ea580c;">
                🚚
            </div>
        </div>
    </div>

    <!-- Filters and Search -->
    <div class="filters-card">
        <form method="GET" action="index.php" class="filters-form">
            <div class="filter-group">
                <label for="q">Buscar fornecedor</label>
                <input type="text" name="q" id="q" placeholder="Razão social, CNPJ ou produtos..." value="<?= htmlspecialchars($search) ?>">
            </div>

            <div class="filter-group">
                <label for="status">Situação</label>
                <select name="status" id="status">
                    <option value="">Todos os status</option>
                    <option value="1" <?= $filtro_status === '1' ? 'selected' : '' ?>>Ativos</option>
                    <option value="0" <?= $filtro_status === '0' ? 'selected' : '' ?>>Inativos</option>
                </select>
            </div>

            <div class="filter-actions-inline">
                <button type="submit" class="btn btn-primary">Filtrar</button>
                <a href="index.php" class="btn btn-secondary">Limpar</a>
            </div>
        </form>
    </div>

    <!-- Suppliers Table Card -->
    <div class="table-card">
        <table>
            <thead>
                <tr>
                    <th>Razão Social / Nome Fantasia</th>
                    <th>CNPJ</th>
                    <th>Telefone</th>
                    <th>E-mail</th>
                    <th style="max-width: 200px;">Produtos Fornecidos</th>
                    <th>Lotes Entregues</th>
                    <th>Status</th>
                    <th style="text-align: center;">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($fornecedores_list)): ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 48px; color: #888;">
                            Nenhum fornecedor correspondente aos filtros foi encontrado.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($fornecedores_list as $f): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($f['nome']) ?></strong>
                            </td>
                            <td><?= htmlspecialchars(formatarCNPJ($f['cnpj'])) ?></td>
                            <td><?= htmlspecialchars($f['telefone'] ?: '—') ?></td>
                            <td>
                                <?php if ($f['email']): ?>
                                    <a href="mailto:<?= htmlspecialchars($f['email']) ?>" style="color: #2db35d; text-decoration: none;"><?= htmlspecialchars($f['email']) ?></a>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td title="<?= htmlspecialchars($f['produtos_fornecidos'] ?? '') ?>">
                                <?= htmlspecialchars(mb_strimwidth($f['produtos_fornecidos'] ?? '', 0, 40, '...')) ?>
                            </td>
                            <td>
                                <span class="badge" style="background: #f3f4f6; color: #374151; font-weight: 500;">
                                    <?= number_format($f['total_lotes']) ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($f['ativo']): ?>
                                    <span class="badge badge-normal">Ativo</span>
                                <?php else: ?>
                                    <span class="badge badge-critico" style="background: #f3f4f6; color: #7f1d1d;">Inativo</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: center;">
                                <div style="display: inline-flex; gap: 8px;">
                                    <a href="editar.php?id=<?= $f['id'] ?>" class="btn btn-secondary btn-sm" style="padding: 6px 10px;">
                                        Editar
                                    </a>
                                    <a href="excluir.php?id=<?= $f['id'] ?>" class="btn btn-danger btn-sm" style="padding: 6px 10px;" onclick="return confirm('Deseja realmente excluir este fornecedor?')">
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