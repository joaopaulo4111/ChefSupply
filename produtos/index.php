<?php
session_start();
if(!isset($_SESSION['logado']) || !$_SESSION['logado']){
    header('Location: ../index.php');
    exit;
}
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
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            background: #f5f6fa;
            font-family: 'Inter', sans-serif;
            color: #1a1a1a;
        }

        /* ── HEADER ── */
        .header {
            background: #1a5c32;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 32px;
            height: 60px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .logo {
            display: flex;
            flex-direction: column;
        }

        .logo h1 {
            font-size: 1.2rem;
            font-weight: 700;
            color: #fff;
            line-height: 1;
        }

        .logo span {
            font-size: 0.7rem;
            color: rgba(255,255,255,0.6);
            margin-top: 2px;
        }

        .header-center {
            flex: 1;
            max-width: 480px;
            margin: 0 32px;
        }

        .search-box {
            display: flex;
            align-items: center;
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.15);
            border-radius: 8px;
            padding: 8px 14px;
            gap: 8px;
        }

        .search-box input {
            background: transparent;
            border: none;
            outline: none;
            color: #fff;
            font-family: 'Inter', sans-serif;
            font-size: 0.875rem;
            width: 100%;
        }

        .search-box input::placeholder { color: rgba(255,255,255,0.5); }

        .header-right {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .notif-btn {
            position: relative;
            background: transparent;
            border: none;
            cursor: pointer;
            color: rgba(255,255,255,0.8);
            padding: 4px;
        }

        .notif-dot {
            position: absolute;
            top: 2px; right: 2px;
            width: 8px; height: 8px;
            background: #e05c5c;
            border-radius: 50%;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .user-info div { text-align: right; }
        .user-name { font-size: 0.875rem; font-weight: 600; color: #fff; }
        .user-sub  { font-size: 0.72rem; color: rgba(255,255,255,0.6); }

        .user-avatar {
            width: 36px; height: 36px;
            background: #2db35d;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 0.9rem; font-weight: 700; color: #fff;
        }

        /* ── NAV ── */
        .nav {
            display: flex;
            align-items: center;
            padding: 0 32px;
            gap: 4px;
            height: 48px;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 7px;
            padding: 8px 14px;
            border-radius: 6px;
            text-decoration: none;
            color: rgba(255,255,255,0.7);
            font-size: 0.875rem;
            font-weight: 500;
            transition: all 0.2s;
            white-space: nowrap;
        }

        .nav-item:hover { background: rgba(255,255,255,0.1); color: #fff; }
        .nav-item.active { background: rgba(255,255,255,0.15); color: #fff; }

        .nav-item svg { width: 16px; height: 16px; flex-shrink: 0; }

        .nav-sair {
            margin-left: auto;
            color: rgba(255,255,255,0.5);
        }

        /* ── CONTENT ── */
        .content { padding: 32px; }

        .page-header { margin-bottom: 24px; }
        .page-header h2 { font-size: 1.6rem; font-weight: 700; }
        .page-header p  { color: #666; font-size: 0.9rem; margin-top: 4px; }

        /* Stats */
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
        }

        .stat-label { font-size: 0.8rem; color: #666; margin-bottom: 6px; }
        .stat-value { font-size: 1.8rem; font-weight: 700; }
        .stat-trend { font-size: 0.78rem; margin-top: 4px; }
        .trend-up   { color: #2db35d; }
        .trend-down { color: #e05c5c; }

        .stat-icon {
            width: 48px; height: 48px;
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.3rem;
            flex-shrink: 0;
        }

        /* Charts */
        .charts-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-bottom: 24px;
        }

        .chart-card {
            background: #fff;
            border: 1px solid #e8e8e8;
            border-radius: 12px;
            padding: 24px;
        }

        .chart-title { font-size: 1rem; font-weight: 600; margin-bottom: 20px; }

        /* Table */
        .table-card {
            background: #fff;
            border: 1px solid #e8e8e8;
            border-radius: 12px;
            padding: 24px;
        }

        .table-title { font-size: 1rem; font-weight: 600; margin-bottom: 16px; }

        table { width: 100%; border-collapse: collapse; }
        th {
            text-align: left;
            padding: 10px 14px;
            font-size: 0.78rem;
            font-weight: 500;
            color: #888;
            border-bottom: 1px solid #f0f0f0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        td {
            padding: 14px;
            font-size: 0.875rem;
            border-bottom: 1px solid #f7f7f7;
        }
        tr:last-child td { border-bottom: none; }

        .badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .badge-normal  { background: #dcfce7; color: #16a34a; }
        .badge-atencao { background: #fef9c3; color: #ca8a04; }
        .badge-critico { background: #fee2e2; color: #dc2626; }

        .btn-ver {
            padding: 6px 14px;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            background: #fff;
            font-size: 0.8rem;
            cursor: pointer;
            font-family: 'Inter', sans-serif;
            transition: border-color 0.2s;
        }
        .btn-ver:hover { border-color: #2db35d; color: #2db35d; }
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
        <a href="../dashboard/index.php" class="nav-item ">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
            Dashboard
        </a>
        <a href="../produtos/index.php" class="nav-item active">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
            Produtos
        </a>
        <a href="../estoque/index.php" class="nav-item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 3H8L2 7h20z"/></svg>
            Estoque
        </a>
        <a href="../entradas/index.php" class="nav-item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v20M2 12h20"/></svg>
            Entradas
        </a>
        <a href="../fornecedores/index.php" class="nav-item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            Fornecedores
        </a>
        <a href="../descartes/index.php" class="nav-item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/></svg>
            Descartes
        </a>
        <a href="../relatorios/index.php" class="nav-item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
            Relatórios
        </a>
        <a href="../usuarios/index.php" class="nav-item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            Usuários
        </a>
        <a href="../configuracoes/index.php" class="nav-item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14M4.93 4.93a10 10 0 0 0 0 14.14"/></svg>
            Configurações
        </a>
        <a href="../logout.php" class="nav-item nav-sair">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
            Sair
        </a>
    </nav>
</header>



<script>

</script>

</body>
</html>