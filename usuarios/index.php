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

// Text search (Name, Email, Restaurant)
$search = trim($_GET['q'] ?? '');
if ($search !== '') {
    $where[] = "(u.nome LIKE :search OR u.email LIKE :search OR u.restaurante LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

// Profile filter
$filtro_perfil = intval($_GET['perfil_id'] ?? 0);
if ($filtro_perfil > 0) {
    $where[] = "u.perfil_id = :perfil_id";
    $params[':perfil_id'] = $filtro_perfil;
}

// Status filter
$filtro_status = trim($_GET['status'] ?? '');
if ($filtro_status !== '') {
    $where[] = "u.status = :status";
    $params[':status'] = $filtro_status;
}

$where_clause = implode(" AND ", $where);

// ── KPI CALCULATIONS ─────────────────────────────────────────
$total_usuarios = $conexao->query("SELECT COUNT(*) FROM usuarios")->fetchColumn();
$ativos             = $conexao->query("SELECT COUNT(*) FROM usuarios WHERE status = 'ativo'")->fetchColumn();
$inativos           = $conexao->query("SELECT COUNT(*) FROM usuarios WHERE status = 'inativo'")->fetchColumn();
$total_perfis       = $conexao->query("SELECT COUNT(*) FROM perfis")->fetchColumn();

// ── USERS LIST QUERY ─────────────────────────────────────────
$query = "
    SELECT u.*, p.nome AS perfil_nome
    FROM usuarios u
    LEFT JOIN perfis p ON u.perfil_id = p.id
    WHERE $where_clause
    ORDER BY u.nome ASC
";
$stmt = $conexao->prepare($query);
$stmt->execute($params);
$usuarios_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch all profiles for dropdown
$todos_perfis = $conexao->query("SELECT id, nome FROM perfis ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);

$pagina_atual = 'usuarios';
$titulo_pagina = 'Colaboradores do Sistema';
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

    .user-table-avatar {
        width: 32px;
        height: 32px;
        background: #1a5c32;
        color: #fff;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 0.8rem;
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
            <h2>Usuários do Sistema</h2>
            <p>Gerencie o acesso de colaboradores, perfis de permissão e audite as contas ativas.</p>
        </div>
        <a href="novo.php" class="btn btn-primary">+ Novo Usuário</a>
    </div>

    <!-- Alert Messaging -->
    <?php if (isset($_GET['msg'])): ?>
        <?php if ($_GET['msg'] === 'criado'): ?>
            <div class="alert alert-success">Colaborador cadastrado com sucesso!</div>
        <?php elseif ($_GET['msg'] === 'editado'): ?>
            <div class="alert alert-success">Usuário atualizado com sucesso!</div>
        <?php elseif ($_GET['msg'] === 'excluido'): ?>
            <div class="alert alert-success">Usuário excluído com sucesso!</div>
        <?php endif; ?>
    <?php endif; ?>

    <?php if (isset($_GET['erro'])): ?>
        <?php if ($_GET['erro'] === 'auto_exclusao'): ?>
            <div class="alert alert-danger">Erro de Segurança: Não é permitido excluir a sua própria conta ativa em uso.</div>
        <?php elseif ($_GET['erro'] === 'erro_delecao'): ?>
            <div class="alert alert-danger">Falha ao tentar excluir colaborador do sistema.</div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- KPI Summary Grid -->
    <div class="stats-grid">
        <div class="stat-card">
            <div>
                <div class="stat-label">Total Cadastrado</div>
                <div class="stat-value"><?= number_format($total_usuarios) ?></div>
                <div class="stat-subtext">Usuários mapeados</div>
            </div>
            <div class="stat-icon" style="background: #eff6ff; color: #2563eb;">
                👥
            </div>
        </div>

        <div class="stat-card">
            <div>
                <div class="stat-label">Usuários Ativos</div>
                <div class="stat-value" style="color: #16a34a;"><?= number_format($ativos) ?></div>
                <div class="stat-subtext">Acesso permitido</div>
            </div>
            <div class="stat-icon" style="background: #f0fdf4; color: #16a34a;">
                🟢
            </div>
        </div>

        <div class="stat-card">
            <div>
                <div class="stat-label">Inativos</div>
                <div class="stat-value" style="color: <?= $inativos > 0 ? '#dc2626' : '#1a1a1a' ?>;"><?= number_format($inativos) ?></div>
                <div class="stat-subtext">Acesso bloqueado</div>
            </div>
            <div class="stat-icon" style="background: #fee2e2; color: #dc2626;">
                🔴
            </div>
        </div>

        <div class="stat-card">
            <div>
                <div class="stat-label">Níveis de Permissão</div>
                <div class="stat-value"><?= number_format($total_perfis) ?></div>
                <div class="stat-subtext">Perfis de acesso ativos</div>
            </div>
            <div class="stat-icon" style="background: #fff7ed; color: #ea580c;">
                🛡️
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="filters-card">
        <form method="GET" action="index.php" class="filters-form">
            <div class="filter-group">
                <label for="q">Buscar usuário</label>
                <input type="text" name="q" id="q" placeholder="Nome, e-mail ou restaurante..." value="<?= htmlspecialchars($search) ?>">
            </div>

            <div class="filter-group">
                <label for="perfil_id">Perfil de Permissão</label>
                <select name="perfil_id" id="perfil_id">
                    <option value="">Todos os perfis</option>
                    <?php foreach($todos_perfis as $tp): ?>
                        <option value="<?= $tp['id'] ?>" <?= $filtro_perfil == $tp['id'] ? 'selected' : '' ?>><?= htmlspecialchars($tp['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-group">
                <label for="status">Situação da Conta</label>
                <select name="status" id="status">
                    <option value="">Todas as situações</option>
                    <option value="ativo" <?= $filtro_status === 'ativo' ? 'selected' : '' ?>>Ativos</option>
                    <option value="inativo" <?= $filtro_status === 'inativo' ? 'selected' : '' ?>>Inativos (Bloqueados)</option>
                </select>
            </div>

            <div class="filter-actions-inline">
                <button type="submit" class="btn btn-primary">Filtrar</button>
                <a href="index.php" class="btn btn-secondary">Limpar</a>
            </div>
        </form>
    </div>

    <!-- Users Table -->
    <div class="table-card">
        <table>
            <thead>
                <tr>
                    <th style="width: 50px;">Avatar</th>
                    <th>Nome Completo</th>
                    <th>E-mail</th>
                    <th>Nível de Acesso</th>
                    <th>Estabelecimento / Restaurante</th>
                    <th>Criado Em</th>
                    <th>Status</th>
                    <th style="text-align: center;">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($usuarios_list)): ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 48px; color: #888;">
                            Nenhum usuário cadastrado ou correspondente aos filtros foi encontrado.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($usuarios_list as $u): 
                        $is_self = ($u['id'] === ($_SESSION['usuario_id'] ?? 0));
                    ?>
                        <tr>
                            <td>
                                <div class="user-table-avatar" style="background: <?= $is_self ? '#2db35d' : '#1a5c32' ?>;">
                                    <?= strtoupper(substr($u['nome'], 0, 1)) ?>
                                </div>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($u['nome']) ?></strong>
                                <?php if ($is_self): ?>
                                    <span class="badge" style="background: #eef2ff; color: #4f46e5; border: 1px solid #c7d2fe; margin-left: 6px;">Você</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($u['email']) ?></td>
                            <td>
                                <span class="badge" style="background: #f3f4f6; color: #374151; font-weight: 600;">
                                    <?= htmlspecialchars($u['perfil_nome'] ?: 'Sem perfil') ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($u['restaurante'] ?: 'Restaurante Premium') ?></td>
                            <td><?= date('d/m/Y', strtotime($u['criado_em'])) ?></td>
                            <td>
                                <?php if ($u['status'] === 'ativo'): ?>
                                    <span class="badge badge-normal">Ativo</span>
                                <?php else: ?>
                                    <span class="badge badge-critico" style="background: #f3f4f6; color: #7f1d1d;">Bloqueado</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: center;">
                                <div style="display: inline-flex; gap: 8px;">
                                    <a href="editar.php?id=<?= $u['id'] ?>" class="btn btn-secondary btn-sm" style="padding: 6px 10px;">
                                        Editar
                                    </a>
                                    <?php if (!$is_self): ?>
                                        <a href="excluir.php?id=<?= $u['id'] ?>" class="btn btn-danger btn-sm" style="padding: 6px 10px;" onclick="return confirm('Deseja realmente remover o colaborador <?= htmlspecialchars($u['nome']) ?> do sistema?')">
                                            Excluir
                                        </a>
                                    <?php else: ?>
                                        <span class="btn btn-secondary btn-sm" style="opacity: 0.5; cursor: not-allowed; padding: 6px 10px;" title="Não é possível remover a própria conta em uso.">Excluir</span>
                                    <?php endif; ?>
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