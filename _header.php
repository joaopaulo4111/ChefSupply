<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
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
    <title>ChefSupply — <?= isset($titulo_pagina) ? htmlspecialchars($titulo_pagina) : 'Gestão de Estoque' ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: #f5f6fa; font-family: 'Inter', sans-serif; color: #1a1a1a; }
        
        /* ── HEADER ── */
        .header { background: #1a5c32; position: sticky; top: 0; z-index: 100; }
        .header-top { display: flex; align-items: center; justify-content: space-between; padding: 0 32px; height: 60px; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .logo { display: flex; flex-direction: column; text-decoration: none; }
        .logo h1 { font-size: 1.2rem; font-weight: 700; color: #fff; line-height: 1; }
        .logo span { font-size: 0.7rem; color: rgba(255,255,255,0.6); margin-top: 2px; }
        .header-center { flex: 1; max-width: 480px; margin: 0 32px; }
        .search-box { display: flex; align-items: center; background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.15); border-radius: 8px; padding: 8px 14px; gap: 8px; }
        .search-box input { background: transparent; border: none; outline: none; color: #fff; font-family: 'Inter', sans-serif; font-size: 0.875rem; width: 100%; }
        .search-box input::placeholder { color: rgba(255,255,255,0.5); }
        .header-right { display: flex; align-items: center; gap: 16px; }
        .user-info { display: flex; align-items: center; gap: 10px; }
        .user-info div { text-align: right; }
        .user-name { font-size: 0.875rem; font-weight: 600; color: #fff; }
        .user-sub  { font-size: 0.72rem; color: rgba(255,255,255,0.6); }
        .user-avatar { width: 36px; height: 36px; background: #2db35d; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.9rem; font-weight: 700; color: #fff; text-transform: uppercase; }

        /* ── NAV ── */
        .nav { display: flex; align-items: center; padding: 0 32px; gap: 4px; height: 48px; }
        .nav-item { display: flex; align-items: center; gap: 7px; padding: 8px 14px; border-radius: 6px; text-decoration: none; color: rgba(255,255,255,0.7); font-size: 0.875rem; font-weight: 500; transition: all 0.2s; white-space: nowrap; }
        .nav-item:hover { background: rgba(255,255,255,0.1); color: #fff; }
        .nav-item.active { background: rgba(255,255,255,0.15); color: #fff; }
        .nav-item svg { width: 16px; height: 16px; flex-shrink: 0; }
        .nav-sair { margin-left: auto; color: rgba(255,255,255,0.5); }

        /* ── CONTENT ── */
        .content { padding: 32px; }
        .page-header { margin-bottom: 24px; display: flex; justify-content: space-between; align-items: flex-end; }
        .page-header-left h2 { font-size: 1.6rem; font-weight: 700; }
        .page-header-left p  { color: #666; font-size: 0.9rem; margin-top: 4px; }

        /* ── BUTTONS ── */
        .btn { display: inline-flex; align-items: center; justify-content: center; padding: 10px 18px; border-radius: 8px; font-size: 0.9rem; font-weight: 600; cursor: pointer; transition: all 0.2s; text-decoration: none; font-family: 'Inter', sans-serif; gap: 6px; border: none; }
        .btn-primary { background: #2db35d; color: #fff; }
        .btn-primary:hover { background: #23934b; }
        .btn-secondary { background: #fff; color: #444; border: 1.5px solid #e5e5e5; }
        .btn-secondary:hover { background: #fafafa; border-color: #ccc; }
        .btn-danger { background: #fee2e2; color: #b91c1c; border: 1.5px solid #fecaca; }
        .btn-danger:hover { background: #fca5a5; color: #991b1b; }
        .btn-warning { background: #fef9c3; color: #a16207; border: 1.5px solid #fef08a; }
        .btn-warning:hover { background: #fef08a; }
        .btn-sm { padding: 6px 12px; font-size: 0.8rem; border-radius: 6px; }

        /* ── FORMS ── */
        .form-card { background: #fff; border: 1px solid #e8e8e8; border-radius: 12px; padding: 28px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.02); }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .form-group { display: flex; flex-direction: column; gap: 6px; }
        .form-group.full { grid-column: 1 / -1; }
        .form-group label { display: block; font-size: 0.85rem; font-weight: 500; color: #333; margin-bottom: 2px; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 12px 14px; border: 1.5px solid #e5e5e5; border-radius: 8px; font-family: 'Inter', sans-serif; font-size: 0.95rem; color: #1a1a1a; background: #fafafa; transition: all 0.2s; }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus { outline: none; border-color: #2db35d; background: #fff; box-shadow: 0 0 0 3px rgba(45, 179, 93, 0.1); }
        .form-group textarea { min-height: 110px; resize: vertical; }
        .form-actions { display: flex; gap: 12px; margin-top: 24px; }

        /* ── TABLES ── */
        .table-card { background: #fff; border: 1px solid #e8e8e8; border-radius: 12px; padding: 24px; overflow-x: auto; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.02); }
        table { width: 100%; border-collapse: collapse; min-width: 600px; }
        th { text-align: left; padding: 12px 14px; font-size: 0.78rem; font-weight: 600; color: #888; border-bottom: 1px solid #f0f0f0; text-transform: uppercase; letter-spacing: 0.5px; }
        td { padding: 14px; font-size: 0.875rem; border-bottom: 1px solid #f7f7f7; vertical-align: middle; }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: #fafafa; }

        /* ── BADGES ── */
        .badge { display: inline-flex; align-items: center; padding: 4px 10px; border-radius: 20px; font-size: 0.72rem; font-weight: 600; text-transform: uppercase; }
        .badge-normal  { background: #dcfce7; color: #16a34a; }
        .badge-atencao { background: #fef9c3; color: #ca8a04; }
        .badge-critico { background: #fee2e2; color: #dc2626; }

        /* ── ALERTS ── */
        .alert { padding: 14px 18px; border-radius: 8px; font-size: 0.9rem; margin-bottom: 24px; font-weight: 500; }
        .alert-success { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
        .alert-danger { background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }
        .alert-warning { background: #fef9c3; color: #a16207; border: 1px solid #fef08a; }

        @media(max-width: 768px) {
            .form-grid { grid-template-columns: 1fr; }
            .header-top { padding: 0 16px; }
            .nav { padding: 0 16px; overflow-x: auto; }
            .content { padding: 16px; }
        }
    </style>
</head>
<body>

<header class="header">
    <div class="header-top">
        <a href="../dashboard/index.php" class="logo">
            <h1>ChefSupply</h1>
            <span>Gestão Inteligente</span>
        </a>
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
        <a href="../dashboard/index.php" class="nav-item <?= ($pagina_atual === 'dashboard') ? 'active' : '' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>Dashboard
        </a>
        <a href="../produtos/index.php" class="nav-item <?= ($pagina_atual === 'produtos') ? 'active' : '' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>Produtos
        </a>
        <a href="../estoque/index.php" class="nav-item <?= ($pagina_atual === 'estoque') ? 'active' : '' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 3H8L2 7h20z"/></svg>Estoque
        </a>
        <a href="../entradas/index.php" class="nav-item <?= ($pagina_atual === 'entradas') ? 'active' : '' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v20M2 12h20"/></svg>Entradas
        </a>
        <a href="../fornecedores/index.php" class="nav-item <?= ($pagina_atual === 'fornecedores') ? 'active' : '' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>Fornecedores
        </a>
        <a href="../descartes/index.php" class="nav-item <?= ($pagina_atual === 'descartes') ? 'active' : '' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/></svg>Descartes
        </a>
        <a href="../relatorios/index.php" class="nav-item <?= ($pagina_atual === 'relatorios') ? 'active' : '' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>Relatórios
        </a>
        <a href="../usuarios/index.php" class="nav-item <?= ($pagina_atual === 'usuarios') ? 'active' : '' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>Usuários
        </a>
        <a href="../configuracoes/index.php" class="nav-item <?= ($pagina_atual === 'configuracoes') ? 'active' : '' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14M4.93 4.93a10 10 0 0 0 0 14.14"/></svg>Configurações
        </a>
        <a href="../logout.php" class="nav-item nav-sair">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>Sair
        </a>
    </nav>
</header>
