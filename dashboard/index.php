<?php
session_start();
if(!isset($_SESSION['logado']) || !$_SESSION['logado']){
    header('Location: ../index.php');
    exit;
}

require_once '../conexao.php';

// ── KPIs principais ──────────────────────────────────────────
$totalProdutos = $conexao->query("SELECT COUNT(*) FROM produtos")->fetchColumn();

$estoqueValor = $conexao->query("
    SELECT COALESCE(SUM(estoque_atual * custo_unitario), 0) FROM produtos
")->fetchColumn();

$criticosCount = $conexao->query("
    SELECT COUNT(*) FROM produtos WHERE status IN ('Crítico','Baixo')
")->fetchColumn();

$descarteMes = $conexao->query("
    SELECT COALESCE(SUM(valor_perdido), 0) FROM descartes
    WHERE MONTH(data_descarte) = MONTH(CURDATE())
      AND YEAR(data_descarte)  = YEAR(CURDATE())
")->fetchColumn();

// ── Vencimentos próximos (7 dias) ────────────────────────────
$vencProximos = $conexao->query("
    SELECT COUNT(*) FROM lotes
    WHERE status = 'ativo'
      AND data_vencimento IS NOT NULL
      AND data_vencimento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
")->fetchColumn();

// ── Entradas dos últimos 6 meses (gráfico) ───────────────────
$entradasMeses = $conexao->query("
    SELECT
        DATE_FORMAT(data_entrada,'%b/%y') AS mes,
        MONTH(data_entrada) AS num_mes,
        YEAR(data_entrada)  AS num_ano,
        COALESCE(SUM(preco_custo * quantidade), 0) AS total
    FROM lotes
    WHERE data_entrada >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY YEAR(data_entrada), MONTH(data_entrada), DATE_FORMAT(data_entrada,'%b/%y')
    ORDER BY num_ano, num_mes
")->fetchAll(PDO::FETCH_ASSOC);

// ── Descartes dos últimos 6 meses (gráfico) ──────────────────
$descartesMeses = $conexao->query("
    SELECT
        DATE_FORMAT(data_descarte,'%b/%y') AS mes,
        MONTH(data_descarte) AS num_mes,
        YEAR(data_descarte)  AS num_ano,
        COALESCE(SUM(valor_perdido), 0) AS total
    FROM descartes
    WHERE data_descarte >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY YEAR(data_descarte), MONTH(data_descarte), DATE_FORMAT(data_descarte,'%b/%y')
    ORDER BY num_ano, num_mes
")->fetchAll(PDO::FETCH_ASSOC);

// ── Distribuição por categoria (donut) ───────────────────────
$porCategoria = $conexao->query("
    SELECT c.nome, COUNT(p.id) AS qtd
    FROM produtos p
    LEFT JOIN categorias c ON p.categoria_id = c.id
    GROUP BY c.nome
    ORDER BY qtd DESC
    LIMIT 6
")->fetchAll(PDO::FETCH_ASSOC);

// ── Produtos críticos / baixo estoque ────────────────────────
$produtosCriticos = $conexao->query("
    SELECT p.nome,
           c.nome AS categoria,
           p.estoque_atual,
           p.estoque_minimo,
           p.unidade,
           p.status,
           p.id
    FROM produtos p
    LEFT JOIN categorias c ON p.categoria_id = c.id
    WHERE p.status IN ('Crítico','Baixo')
    ORDER BY (p.estoque_atual / NULLIF(p.estoque_minimo,0)) ASC
    LIMIT 8
")->fetchAll(PDO::FETCH_ASSOC);

// ── Lotes a vencer em breve ───────────────────────────────────
$lotesVencer = $conexao->query("
    SELECT l.codigo_lote, p.nome AS produto, l.quantidade_restante,
           p.unidade, l.data_vencimento,
           DATEDIFF(l.data_vencimento, CURDATE()) AS dias_restantes
    FROM lotes l
    JOIN produtos p ON l.produto_id = p.id
    WHERE l.status = 'ativo'
      AND l.data_vencimento IS NOT NULL
      AND l.data_vencimento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
    ORDER BY l.data_vencimento ASC
    LIMIT 6
")->fetchAll(PDO::FETCH_ASSOC);

// ── Tendência de valor de estoque (KPI trend) ────────────────
$estoqueAnterior = $conexao->query("
    SELECT COALESCE(SUM(quantidade * preco_custo), 0) FROM lotes
    WHERE MONTH(data_entrada) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
      AND YEAR(data_entrada)  = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
      AND status = 'ativo'
")->fetchColumn();

$trendEstoque = $estoqueAnterior > 0
    ? round((($estoqueValor - $estoqueAnterior) / $estoqueAnterior) * 100, 1)
    : 0;

// JSON para charts
$labelsEntradas   = json_encode(array_column($entradasMeses, 'mes'));
$valoresEntradas  = json_encode(array_map('floatval', array_column($entradasMeses, 'total')));
$labelsDescartes  = json_encode(array_column($descartesMeses, 'mes'));
$valoresDescartes = json_encode(array_map('floatval', array_column($descartesMeses, 'total')));
$labelsCat        = json_encode(array_column($porCategoria, 'nome'));
$valoresCat       = json_encode(array_map('intval',   array_column($porCategoria, 'qtd')));
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ChefSupply — Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{background:#f5f6fa;font-family:'Inter',sans-serif;color:#1a1a1a}

        /* ── HEADER ── */
        .header{background:#1a5c32;position:sticky;top:0;z-index:100}
        .header-top{display:flex;align-items:center;justify-content:space-between;padding:0 32px;height:60px;border-bottom:1px solid rgba(255,255,255,.1)}
        .logo h1{font-size:1.2rem;font-weight:700;color:#fff;line-height:1}
        .logo span{font-size:.7rem;color:rgba(255,255,255,.6);margin-top:2px;display:block}
        .header-center{flex:1;max-width:480px;margin:0 32px}
        .search-box{display:flex;align-items:center;background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.15);border-radius:8px;padding:8px 14px;gap:8px}
        .search-box input{background:transparent;border:none;outline:none;color:#fff;font-family:'Inter',sans-serif;font-size:.875rem;width:100%}
        .search-box input::placeholder{color:rgba(255,255,255,.5)}
        .header-right{display:flex;align-items:center;gap:16px}
        .user-info{display:flex;align-items:center;gap:10px}
        .user-info div{text-align:right}
        .user-name{font-size:.875rem;font-weight:600;color:#fff}
        .user-sub{font-size:.72rem;color:rgba(255,255,255,.6)}
        .user-avatar{width:36px;height:36px;background:#2db35d;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.9rem;font-weight:700;color:#fff}

        /* ── NAV ── */
        .nav{display:flex;align-items:center;padding:0 32px;gap:4px;height:48px}
        .nav-item{display:flex;align-items:center;gap:7px;padding:8px 14px;border-radius:6px;text-decoration:none;color:rgba(255,255,255,.7);font-size:.875rem;font-weight:500;transition:all .2s;white-space:nowrap}
        .nav-item:hover{background:rgba(255,255,255,.1);color:#fff}
        .nav-item.active{background:rgba(255,255,255,.15);color:#fff}
        .nav-item svg{width:16px;height:16px;flex-shrink:0}
        .nav-sair{margin-left:auto;color:rgba(255,255,255,.5)}

        /* ── CONTENT ── */
        .content{padding:28px 32px}
        .page-header{margin-bottom:24px;display:flex;justify-content:space-between;align-items:flex-end}
        .page-header h2{font-size:1.5rem;font-weight:700}
        .page-header p{color:#666;font-size:.875rem;margin-top:3px}
        .data-atualizacao{font-size:.78rem;color:#999}

        /* ── ALERTAS ── */
        .alertas-bar{display:flex;gap:12px;margin-bottom:20px;flex-wrap:wrap}
        .alerta-pill{display:flex;align-items:center;gap:8px;padding:8px 14px;border-radius:8px;font-size:.8rem;font-weight:500;cursor:pointer;text-decoration:none;transition:opacity .2s}
        .alerta-pill:hover{opacity:.85}
        .alerta-pill svg{width:14px;height:14px;flex-shrink:0}
        .alerta-venc{background:#fff8e6;color:#b45309;border:1px solid #fde68a}
        .alerta-crit{background:#fef2f2;color:#dc2626;border:1px solid #fecaca}
        .alerta-ok{background:#f0fdf4;color:#16a34a;border:1px solid #bbf7d0}

        /* ── STATS ── */
        .stats-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:20px}
        .stat-card{background:#fff;border:1px solid #e8e8e8;border-radius:12px;padding:20px;display:flex;align-items:center;justify-content:space-between;transition:box-shadow .2s}
        .stat-card:hover{box-shadow:0 4px 16px rgba(0,0,0,.08)}
        .stat-label{font-size:.78rem;color:#666;margin-bottom:6px;font-weight:500}
        .stat-value{font-size:1.7rem;font-weight:700;line-height:1.1}
        .stat-trend{font-size:.75rem;margin-top:5px;display:flex;align-items:center;gap:3px}
        .trend-up{color:#2db35d}
        .trend-down{color:#e05c5c}
        .trend-neut{color:#888}
        .stat-icon{width:48px;height:48px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.3rem;flex-shrink:0}

        /* ── CHARTS ── */
        .charts-row{display:grid;grid-template-columns:1fr 1fr 380px;gap:16px;margin-bottom:20px}
        .chart-card{background:#fff;border:1px solid #e8e8e8;border-radius:12px;padding:22px}
        .chart-title{font-size:.95rem;font-weight:600;margin-bottom:4px}
        .chart-sub{font-size:.78rem;color:#888;margin-bottom:18px}
        .chart-wrap{position:relative}

        /* ── BOTTOM ROW ── */
        .bottom-row{display:grid;grid-template-columns:1fr 380px;gap:16px}
        .table-card{background:#fff;border:1px solid #e8e8e8;border-radius:12px;padding:22px}
        .section-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:16px}
        .section-title{font-size:.95rem;font-weight:600}
        .link-ver-todos{font-size:.78rem;color:#2db35d;text-decoration:none;font-weight:500}
        .link-ver-todos:hover{text-decoration:underline}

        table{width:100%;border-collapse:collapse}
        th{text-align:left;padding:9px 12px;font-size:.72rem;font-weight:600;color:#888;border-bottom:1px solid #f0f0f0;text-transform:uppercase;letter-spacing:.4px}
        td{padding:12px;font-size:.845rem;border-bottom:1px solid #f7f7f7;vertical-align:middle}
        tr:last-child td{border-bottom:none}
        tr:hover td{background:#fafafa}

        .badge{display:inline-flex;align-items:center;padding:3px 9px;border-radius:20px;font-size:.72rem;font-weight:600}
        .badge-normal {background:#dcfce7;color:#16a34a}
        .badge-baixo  {background:#fef9c3;color:#ca8a04}
        .badge-critico{background:#fee2e2;color:#dc2626}

        .progress-bar-wrap{display:flex;align-items:center;gap:8px}
        .progress-bar{height:6px;background:#f0f0f0;border-radius:4px;flex:1;overflow:hidden}
        .progress-fill{height:100%;border-radius:4px;transition:width .4s}
        .fill-green{background:#2db35d}
        .fill-yellow{background:#eab308}
        .fill-red{background:#ef4444}

        /* ── LOTES VENCER ── */
        .lote-item{padding:12px 0;border-bottom:1px solid #f7f7f7;display:flex;flex-direction:column;gap:4px}
        .lote-item:last-child{border-bottom:none}
        .lote-top{display:flex;justify-content:space-between;align-items:center}
        .lote-nome{font-size:.845rem;font-weight:500}
        .lote-dias{font-size:.75rem;font-weight:600;padding:2px 8px;border-radius:12px}
        .dias-urgente{background:#fee2e2;color:#dc2626}
        .dias-atencao{background:#fef9c3;color:#ca8a04}
        .dias-ok{background:#dcfce7;color:#16a34a}
        .lote-info{font-size:.75rem;color:#888}

        /* ── EMPTY STATE ── */
        .empty-state{text-align:center;padding:32px 16px;color:#aaa}
        .empty-state svg{width:40px;height:40px;margin-bottom:8px;opacity:.4}
        .empty-state p{font-size:.845rem}

        @media(max-width:1200px){
            .charts-row{grid-template-columns:1fr 1fr}
            .stats-grid{grid-template-columns:repeat(2,1fr)}
            .bottom-row{grid-template-columns:1fr}
        }
    </style>
</head>
<body>

<header class="header">
    <div class="header-top">
        <div class="logo">
            <h1>ChefSupply</h1>
            <span>Gestão Inteligente</span>
        </div>
        <div class="header-center">
            <div class="search-box">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                <input type="text" placeholder="Buscar produtos, lotes, fornecedores...">
            </div>
        </div>
        <div class="header-right">
            <div class="user-info">
                <div>
                    <div class="user-name"><?= htmlspecialchars($_SESSION['usuario_nome'] ?? 'Usuário') ?></div>
                    <div class="user-sub">Restaurante Premium</div>
                </div>
                <div class="user-avatar"><?= strtoupper(substr($_SESSION['usuario_nome'] ?? 'U', 0, 1)) ?></div>
            </div>
        </div>
    </div>
    <nav class="nav">
        <a href="../dashboard/index.php" class="nav-item active">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>Dashboard
        </a>
        <a href="../produtos/index.php" class="nav-item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>Produtos
        </a>
        <a href="../estoque/index.php" class="nav-item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 3H8L2 7h20z"/></svg>Estoque
        </a>
        <a href="../entradas/index.php" class="nav-item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v20M2 12h20"/></svg>Entradas
        </a>
        <a href="../fornecedores/index.php" class="nav-item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>Fornecedores
        </a>
        <a href="../descartes/index.php" class="nav-item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/></svg>Descartes
        </a>
        <a href="../relatorios/index.php" class="nav-item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>Relatórios
        </a>
        <a href="../usuarios/index.php" class="nav-item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>Usuários
        </a>
        <a href="../configuracoes/index.php" class="nav-item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14M4.93 4.93a10 10 0 0 0 0 14.14"/></svg>Configurações
        </a>
        <a href="../logout.php" class="nav-item nav-sair">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>Sair
        </a>
    </nav>
</header>

<div class="content">

    <!-- ── HEADER ── -->
    <div class="page-header">
        <div>
            <h2>Dashboard</h2>
            <p>Visão geral do estoque e operações</p>
        </div>
        <span class="data-atualizacao">Atualizado em <?= date('d/m/Y \à\s H:i') ?></span>
    </div>

    <!-- ── ALERTAS ── -->
    <?php if ($vencProximos > 0 || $criticosCount > 0): ?>
    <div class="alertas-bar">
        <?php if ($vencProximos > 0): ?>
        <a href="../estoque/index.php?filtro=vencendo" class="alerta-pill alerta-venc">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <?= $vencProximos ?> lote<?= $vencProximos > 1 ? 's' : '' ?> vencendo nos próximos 7 dias
        </a>
        <?php endif; ?>
        <?php if ($criticosCount > 0): ?>
        <a href="../estoque/index.php?filtro=critico" class="alerta-pill alerta-crit">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            <?= $criticosCount ?> produto<?= $criticosCount > 1 ? 's' : '' ?> com estoque crítico ou baixo
        </a>
        <?php endif; ?>
        <?php if ($criticosCount == 0 && $vencProximos == 0): ?>
        <span class="alerta-pill alerta-ok">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
            Estoque em situação normal
        </span>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- ── KPI CARDS ── -->
    <div class="stats-grid">
        <div class="stat-card">
            <div>
                <div class="stat-label">Total de Produtos</div>
                <div class="stat-value"><?= number_format($totalProdutos) ?></div>
                <div class="stat-trend trend-neut">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    cadastrados no sistema
                </div>
            </div>
            <div class="stat-icon" style="background:#f0fdf4;color:#16a34a">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
            </div>
        </div>

        <div class="stat-card">
            <div>
                <div class="stat-label">Valor em Estoque</div>
                <div class="stat-value">R$ <?= number_format($estoqueValor, 0, ',', '.') ?></div>
                <div class="stat-trend <?= $trendEstoque >= 0 ? 'trend-up' : 'trend-down' ?>">
                    <?php if($trendEstoque > 0): ?>
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="18 15 12 9 6 15"/></svg>
                    +<?= $trendEstoque ?>% vs mês anterior
                    <?php elseif($trendEstoque < 0): ?>
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
                    <?= $trendEstoque ?>% vs mês anterior
                    <?php else: ?>
                    sem dados do mês anterior
                    <?php endif; ?>
                </div>
            </div>
            <div class="stat-icon" style="background:#eff6ff;color:#2563eb">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
            </div>
        </div>

        <div class="stat-card">
            <div>
                <div class="stat-label">Estoque Crítico / Baixo</div>
                <div class="stat-value" style="color:<?= $criticosCount > 0 ? '#dc2626' : '#16a34a' ?>"><?= $criticosCount ?></div>
                <div class="stat-trend <?= $criticosCount > 0 ? 'trend-down' : 'trend-up' ?>">
                    <?php if($criticosCount > 0): ?>
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/></svg>
                    requer atenção imediata
                    <?php else: ?>
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                    todos os estoques ok
                    <?php endif; ?>
                </div>
            </div>
            <div class="stat-icon" style="background:#fef2f2;color:#dc2626">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            </div>
        </div>

        <div class="stat-card">
            <div>
                <div class="stat-label">Perdas no Mês</div>
                <div class="stat-value" style="color:<?= $descarteMes > 0 ? '#dc2626' : '#1a1a1a' ?>">
                    R$ <?= number_format($descarteMes, 0, ',', '.') ?>
                </div>
                <div class="stat-trend trend-neut">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>
                    em descartes em <?= date('M/Y') ?>
                </div>
            </div>
            <div class="stat-icon" style="background:#fff7ed;color:#ea580c">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/></svg>
            </div>
        </div>
    </div>

    <!-- ── GRÁFICOS ── -->
    <div class="charts-row">
        <!-- Entradas x Descartes -->
        <div class="chart-card">
            <div class="chart-title">Entradas vs Descartes</div>
            <div class="chart-sub">Últimos 6 meses — valor em R$</div>
            <div class="chart-wrap"><canvas id="chartEntradas" height="200"></canvas></div>
        </div>

        <!-- Distribuição por categoria -->
        <div class="chart-card">
            <div class="chart-title">Produtos por Categoria</div>
            <div class="chart-sub">Distribuição do catálogo</div>
            <div class="chart-wrap" style="max-width:320px;margin:0 auto"><canvas id="chartCategoria" height="220"></canvas></div>
        </div>

        <!-- Lotes a vencer -->
        <div class="chart-card" style="overflow-y:auto;max-height:340px">
            <div class="section-header">
                <div>
                    <div class="chart-title">Vencimentos Próximos</div>
                    <div class="chart-sub" style="margin-bottom:0">Próximos 30 dias</div>
                </div>
                <a href="../estoque/index.php?filtro=vencendo" class="link-ver-todos">Ver todos</a>
            </div>
            <?php if(empty($lotesVencer)): ?>
            <div class="empty-state">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><polyline points="20 6 9 17 4 12"/></svg>
                <p>Nenhum lote vencendo<br>nos próximos 30 dias</p>
            </div>
            <?php else: ?>
            <?php foreach($lotesVencer as $lv):
                $dias = (int)$lv['dias_restantes'];
                $cls  = $dias <= 3 ? 'dias-urgente' : ($dias <= 10 ? 'dias-atencao' : 'dias-ok');
                $label = $dias == 0 ? 'Hoje!' : ($dias == 1 ? '1 dia' : $dias.' dias');
            ?>
            <div class="lote-item">
                <div class="lote-top">
                    <span class="lote-nome"><?= htmlspecialchars($lv['produto']) ?></span>
                    <span class="lote-dias <?= $cls ?>"><?= $label ?></span>
                </div>
                <div class="lote-info">
                    Lote <?= htmlspecialchars($lv['codigo_lote'] ?: 'S/N') ?> &nbsp;·&nbsp;
                    <?= number_format($lv['quantidade_restante'], 2, ',', '.') ?> <?= $lv['unidade'] ?> &nbsp;·&nbsp;
                    Vence <?= date('d/m/Y', strtotime($lv['data_vencimento'])) ?>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── BOTTOM: CRÍTICOS + (espaço para mais widgets) ── -->
    <div class="bottom-row">
        <div class="table-card">
            <div class="section-header">
                <span class="section-title">Produtos com Estoque Crítico / Baixo</span>
                <a href="../estoque/index.php?filtro=critico" class="link-ver-todos">Ver todos</a>
            </div>
            <?php if(empty($produtosCriticos)): ?>
            <div class="empty-state">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><polyline points="20 6 9 17 4 12"/></svg>
                <p>Nenhum produto com estoque crítico ou baixo</p>
            </div>
            <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Produto</th>
                        <th>Categoria</th>
                        <th>Situação</th>
                        <th>Estoque atual</th>
                        <th>Nível</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach($produtosCriticos as $pc):
                    $pct = $pc['estoque_minimo'] > 0
                        ? min(100, round(($pc['estoque_atual'] / $pc['estoque_minimo']) * 100))
                        : 100;
                    $fillCls = $pc['status'] === 'Crítico' ? 'fill-red' : 'fill-yellow';
                    $badgeCls = $pc['status'] === 'Crítico' ? 'badge-critico' : 'badge-baixo';
                ?>
                <tr>
                    <td><strong><?= htmlspecialchars($pc['nome']) ?></strong></td>
                    <td style="color:#666"><?= htmlspecialchars($pc['categoria'] ?? '—') ?></td>
                    <td><span class="badge <?= $badgeCls ?>"><?= $pc['status'] ?></span></td>
                    <td><?= number_format($pc['estoque_atual'], 2, ',', '.') ?> <?= $pc['unidade'] ?></td>
                    <td>
                        <div class="progress-bar-wrap">
                            <div class="progress-bar">
                                <div class="progress-fill <?= $fillCls ?>" style="width:<?= $pct ?>%"></div>
                            </div>
                            <span style="font-size:.72rem;color:#888;min-width:32px"><?= $pct ?>%</span>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <!-- Resumo de descartes por motivo -->
        <div class="table-card">
            <div class="section-header">
                <span class="section-title">Descartes por Motivo</span>
                <a href="../descartes/index.php" class="link-ver-todos">Ver todos</a>
            </div>
            <?php
            $descarteMotivo = $conexao->query("
                SELECT motivo, COUNT(*) AS qtd, COALESCE(SUM(valor_perdido),0) AS total
                FROM descartes
                WHERE YEAR(data_descarte) = YEAR(CURDATE())
                GROUP BY motivo
                ORDER BY total DESC
            ")->fetchAll(PDO::FETCH_ASSOC);
            ?>
            <?php if(empty($descarteMotivo)): ?>
            <div class="empty-state">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><polyline points="20 6 9 17 4 12"/></svg>
                <p>Nenhum descarte registrado<br>neste ano</p>
            </div>
            <?php else: ?>
            <?php
            $totalDescAnual = array_sum(array_column($descarteMotivo, 'total'));
            $cores = ['#ef4444','#f97316','#eab308','#6366f1'];
            foreach($descarteMotivo as $i => $dm):
                $pct = $totalDescAnual > 0 ? round(($dm['total'] / $totalDescAnual) * 100) : 0;
                $cor = $cores[$i % count($cores)];
            ?>
            <div style="margin-bottom:14px">
                <div style="display:flex;justify-content:space-between;margin-bottom:5px">
                    <span style="font-size:.845rem;font-weight:500"><?= htmlspecialchars($dm['motivo']) ?></span>
                    <span style="font-size:.78rem;color:#666"><?= $dm['qtd'] ?>× · R$ <?= number_format($dm['total'],0,',','.') ?></span>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill" style="width:<?= $pct ?>%;background:<?= $cor ?>"></div>
                </div>
            </div>
            <?php endforeach; ?>
            <div style="margin-top:16px;padding-top:14px;border-top:1px solid #f0f0f0;display:flex;justify-content:space-between">
                <span style="font-size:.845rem;font-weight:600">Total no ano</span>
                <span style="font-size:.845rem;font-weight:700;color:#dc2626">R$ <?= number_format($totalDescAnual,0,',','.') ?></span>
            </div>
            <?php endif; ?>
        </div>
    </div>

</div><!-- /content -->

<script>
// ── Gráfico Entradas vs Descartes ──────────────────────────────
(function(){
    const labelsE = <?= $labelsEntradas ?>;
    const valE    = <?= $valoresEntradas ?>;
    const labelsD = <?= $labelsDescartes ?>;
    const valD    = <?= $valoresDescartes ?>;

    // Unir labels
    const allLabels = [...new Set([...labelsE, ...labelsD])];
    const dataE = allLabels.map(l => { const i = labelsE.indexOf(l); return i>=0 ? valE[i] : 0; });
    const dataD = allLabels.map(l => { const i = labelsD.indexOf(l); return i>=0 ? valD[i] : 0; });

    new Chart(document.getElementById('chartEntradas'), {
        type: 'bar',
        data: {
            labels: allLabels.length ? allLabels : ['Sem dados'],
            datasets: [
                {
                    label: 'Entradas',
                    data: dataE,
                    backgroundColor: 'rgba(45,179,93,0.8)',
                    borderRadius: 6,
                    borderSkipped: false,
                },
                {
                    label: 'Descartes',
                    data: dataD,
                    backgroundColor: 'rgba(239,68,68,0.75)',
                    borderRadius: 6,
                    borderSkipped: false,
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: { position: 'top', labels: { font: { family: 'Inter', size: 12 }, boxWidth: 12 } },
                tooltip: {
                    callbacks: {
                        label: ctx => ' R$ ' + ctx.parsed.y.toLocaleString('pt-BR', {minimumFractionDigits:2})
                    }
                }
            },
            scales: {
                x: { grid: { display: false }, ticks: { font: { family: 'Inter', size: 11 } } },
                y: {
                    grid: { color: '#f0f0f0' },
                    ticks: {
                        font: { family: 'Inter', size: 11 },
                        callback: v => 'R$ ' + (v >= 1000 ? (v/1000).toFixed(0)+'k' : v)
                    }
                }
            }
        }
    });
})();

// ── Gráfico Categorias ─────────────────────────────────────────
(function(){
    const labels = <?= $labelsCat ?>;
    const data   = <?= $valoresCat ?>;
    const colors = ['#2db35d','#3b82f6','#f59e0b','#ef4444','#8b5cf6','#06b6d4'];

    new Chart(document.getElementById('chartCategoria'), {
        type: 'doughnut',
        data: {
            labels: labels.length ? labels : ['Sem dados'],
            datasets: [{
                data: data.length ? data : [1],
                backgroundColor: colors,
                borderWidth: 2,
                borderColor: '#fff',
                hoverOffset: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            cutout: '62%',
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { font: { family: 'Inter', size: 11 }, boxWidth: 10, padding: 12 }
                },
                tooltip: {
                    callbacks: {
                        label: ctx => ' ' + ctx.label + ': ' + ctx.parsed + ' produto' + (ctx.parsed > 1 ? 's' : '')
                    }
                }
            }
        }
    });
})();
</script>
</body>
</html>