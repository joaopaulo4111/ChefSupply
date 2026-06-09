<?php
// Verifica se o estado da sessão do PHP é igual a PHP_SESSION_NONE (ou seja, se a sessão ainda não foi iniciada)
// Se não estiver iniciada, inicializa a sessão para permitir o acesso às variáveis globais $_SESSION
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verifica se a variável de sessão 'logado' não está definida ou se o seu valor é falso (usuário não autenticado)
if(!isset($_SESSION['logado']) || !$_SESSION['logado']){
    // Redireciona o usuário não autenticado para a tela de login principal (index.php), localizada uma pasta acima
    header('Location: ../index.php');
    
    // Interrompe imediatamente a execução do script para impedir o carregamento do restante do HTML
    exit;
}
?>
<!DOCTYPE html>
<!-- Define o idioma padrão do documento HTML como português do Brasil -->
<html lang="pt-BR">
<head>
    <!-- Define a codificação de caracteres UTF-8, garantindo a renderização correta de acentos e caracteres especiais -->
    <meta charset="UTF-8">
    
    <!-- Configura a viewport para tornar a página responsiva e se ajustar corretamente a telas de dispositivos móveis -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- Define o título da página dinamicamente. Se a variável $titulo_pagina estiver definida, exibe seu valor tratado com htmlspecialchars para segurança; caso contrário, exibe 'Gestão de Estoque' como padrão -->
    <title>ChefSupply — <?= isset($titulo_pagina) ? htmlspecialchars($titulo_pagina) : 'Gestão de Estoque' ?></title>
    
    <!-- Importa a fonte 'Inter' do repositório Google Fonts com as espessuras de traço 300, 400, 500, 600 e 700 -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Bloco de estilizações gerais em CSS da aplicação -->
    <style>
        /* Reseta margens, preenchimentos e configura o box-sizing de todos os elementos para incluir bordas no tamanho total */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        /* Define o fundo cinza claro para a página, altera a fonte para 'Inter' (ou sans-serif se indisponível) e define a cor escura padrão do texto */
        body { background: #f5f6fa; font-family: 'Inter', sans-serif; color: #1a1a1a; }
        
        /* ── ESTILOS DO CABEÇALHO (HEADER) ── */
        /* Define a cor de fundo verde escuro do header e o mantém fixo no topo da tela durante a rolagem */
        .header { background: #1a5c32; position: sticky; top: 0; z-index: 100; }
        
        /* Alinha horizontalmente o conteúdo superior do cabeçalho (logo, busca e avatar) distribuindo o espaço entre eles */
        .header-top { display: flex; align-items: center; justify-content: space-between; padding: 0 32px; height: 60px; border-bottom: 1px solid rgba(255,255,255,0.1); }
        
        /* Estiliza o link do logotipo para remover o sublinhado padrão e exibi-lo como bloco flexível em coluna */
        .logo { display: flex; flex-direction: column; text-decoration: none; }
        
        /* Define o tamanho, espessura e cor branca para o nome 'ChefSupply' no logotipo */
        .logo h1 { font-size: 1.2rem; font-weight: 700; color: #fff; line-height: 1; }
        
        /* Estiliza o subtítulo 'Gestão Inteligente' com tamanho menor e cor branca semitransparente */
        .logo span { font-size: 0.7rem; color: rgba(255,255,255,0.6); margin-top: 2px; }
        
        /* Define o container do meio do cabeçalho, permitindo que cresça até um máximo de 480px */
        .header-center { flex: 1; max-width: 480px; margin: 0 32px; }
        
        /* Estiliza o campo de busca com bordas arredondadas, fundo transparente e ícone alinhado */
        .search-box { display: flex; align-items: center; background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.15); border-radius: 8px; padding: 8px 14px; gap: 8px; }
        
        /* Remove a borda e fundo do campo input dentro da busca, definindo a cor do texto digitado como branca */
        .search-box input { background: transparent; border: none; outline: none; color: #fff; font-family: 'Inter', sans-serif; font-size: 0.875rem; width: 100%; }
        
        /* Define a cor branca semitransparente para o texto explicativo (placeholder) da busca */
        .search-box input::placeholder { color: rgba(255,255,255,0.5); }
        
        /* Alinha os itens à direita no cabeçalho (dados do usuário logado) */
        .header-right { display: flex; align-items: center; gap: 16px; }
        
        /* Agrupa informações de texto e avatar do usuário alinhando-os horizontalmente */
        .user-info { display: flex; align-items: center; gap: 10px; }
        
        /* Alinha o texto do nome do usuário à direita */
        .user-info div { text-align: right; }
        
        /* Estiliza o nome do usuário ativo com cor branca e espessura em negrito médio */
        .user-name { font-size: 0.875rem; font-weight: 600; color: #fff; }
        
        /* Estiliza o subtítulo do restaurante com tamanho pequeno e cor branca suave */
        .user-sub  { font-size: 0.72rem; color: rgba(255,255,255,0.6); }
        
        /* Cria uma imagem de avatar circular contendo a primeira letra do nome do usuário em caixa alta */
        .user-avatar { width: 36px; height: 36px; background: #2db35d; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.9rem; font-weight: 700; color: #fff; text-transform: uppercase; }
 
        /* ── MENU DE NAVEGAÇÃO (NAV) ── */
        /* Estiliza a barra horizontal de menu abaixo do cabeçalho principal */
        .nav { display: flex; align-items: center; padding: 0 32px; gap: 4px; height: 48px; }
        
        /* Estiliza individualmente os links de menu com cantos arredondados, cor semitransparente e transições suaves ao passar o mouse */
        .nav-item { display: flex; align-items: center; gap: 7px; padding: 8px 14px; border-radius: 6px; text-decoration: none; color: rgba(255,255,255,0.7); font-size: 0.875rem; font-weight: 500; transition: all 0.2s; white-space: nowrap; }
        
        /* Adiciona efeito de hover nos itens de menu colocando fundo branco semitransparente e texto totalmente branco */
        .nav-item:hover { background: rgba(255,255,255,0.1); color: #fff; }
        
        /* Destaca visualmente o item do menu correspondente à página atual em que o usuário se encontra */
        .nav-item.active { background: rgba(255,255,255,0.15); color: #fff; }
        
        /* Define dimensões padrões para os ícones vetoriais (SVG) dentro do menu */
        .nav-item svg { width: 16px; height: 16px; flex-shrink: 0; }
        
        /* Alinha o link de logout (Sair) totalmente para o lado direito do menu */
        .nav-sair { margin-left: auto; color: rgba(255,255,255,0.5); }
 
        /* ── CONTEÚDO PRINCIPAL (CONTENT) ── */
        /* Adiciona espaçamento interno padrão para o container principal das páginas */
        .content { padding: 32px; }
        
        /* Organiza o título da página e botões de ação na mesma linha horizontal, alinhados pela base */
        .page-header { margin-bottom: 24px; display: flex; justify-content: space-between; align-items: flex-end; }
        
        /* Define o estilo do título principal da página atual */
        .page-header-left h2 { font-size: 1.6rem; font-weight: 700; }
        
        /* Define o estilo da descrição ou apoio textual logo abaixo do título da página */
        .page-header-left p  { color: #666; font-size: 0.9rem; margin-top: 4px; }
 
        /* ── BOTÕES ── */
        /* Estilo base compartilhado por todos os botões do sistema, padronizando comportamento e cursor */
        .btn { display: inline-flex; align-items: center; justify-content: center; padding: 10px 18px; border-radius: 8px; font-size: 0.9rem; font-weight: 600; cursor: pointer; transition: all 0.2s; text-decoration: none; font-family: 'Inter', sans-serif; gap: 6px; border: none; }
        
        /* Estiliza o botão principal de confirmação ou inserção na cor verde chamativa */
        .btn-primary { background: #2db35d; color: #fff; }
        .btn-primary:hover { background: #23934b; }
        
        /* Estiliza o botão secundário de cancelamento ou ação alternativa na cor branca com borda cinza */
        .btn-secondary { background: #fff; color: #444; border: 1.5px solid #e5e5e5; }
        .btn-secondary:hover { background: #fafafa; border-color: #ccc; }
        
        /* Estiliza o botão de exclusão ou alerta de perigo em tom vermelho suave com texto vermelho escuro */
        .btn-danger { background: #fee2e2; color: #b91c1c; border: 1.5px solid #fecaca; }
        .btn-danger:hover { background: #fca5a5; color: #991b1b; }
        
        /* Estiliza o botão de advertência ou atenção em tons amarelados */
        .btn-warning { background: #fef9c3; color: #a16207; border: 1.5px solid #fef08a; }
        .btn-warning:hover { background: #fef08a; }
        
        /* Define uma variante compacta e menor para os botões utilizarem em listagens e tabelas */
        .btn-sm { padding: 6px 12px; font-size: 0.8rem; border-radius: 6px; }
 
        /* ── FORMULÁRIOS ── */
        /* Estiliza a caixa/card que envelopa e agrupa formulários no sistema */
        .form-card { background: #fff; border: 1px solid #e8e8e8; border-radius: 12px; padding: 28px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.02); }
        
        /* Define um grid de formulário com duas colunas de tamanhos iguais */
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        
        /* Define um bloco para agrupar rótulo (label) e campo de inserção com pequenos espaçamentos verticais */
        .form-group { display: flex; flex-direction: column; gap: 6px; }
        
        /* Classe utilitária para fazer um campo ocupar as duas colunas do grid do formulário */
        .form-group.full { grid-column: 1 / -1; }
        
        /* Estiliza o rótulo descritivo do campo (label) */
        .form-group label { display: block; font-size: 0.85rem; font-weight: 500; color: #333; margin-bottom: 2px; }
        
        /* Estiliza as caixas de entrada de dados, seletores e áreas de texto */
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 12px 14px; border: 1.5px solid #e5e5e5; border-radius: 8px; font-family: 'Inter', sans-serif; font-size: 0.95rem; color: #1a1a1a; background: #fafafa; transition: all 0.2s; }
        
        /* Adiciona destaque visual com tom verde e sombra suave quando o campo recebe o foco de digitação */
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus { outline: none; border-color: #2db35d; background: #fff; box-shadow: 0 0 0 3px rgba(45, 179, 93, 0.1); }
        
        /* Estipula altura mínima e barra de rolagem vertical para campos de texto longos */
        .form-group textarea { min-height: 110px; resize: vertical; }
        
        /* Agrupa os botões de envio/cancelamento de formulários */
        .form-actions { display: flex; gap: 12px; margin-top: 24px; }
 
        /* ── TABELAS ── */
        /* Card com rolagem horizontal que envolve a tabela para evitar quebras em telas pequenas */
        .table-card { background: #fff; border: 1px solid #e8e8e8; border-radius: 12px; padding: 24px; overflow-x: auto; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.02); }
        
        /* Ocupa a largura total do container e remove o espaçamento duplo das bordas */
        table { width: 100%; border-collapse: collapse; min-width: 600px; }
        
        /* Cabeçalho da tabela com fontes pequenas em caixa alta, cor acinzentada e borda inferior fina */
        th { text-align: left; padding: 12px 14px; font-size: 0.78rem; font-weight: 600; color: #888; border-bottom: 1px solid #f0f0f0; text-transform: uppercase; letter-spacing: 0.5px; }
        
        /* Célula comum da tabela com fonte de tamanho legível e alinhamento vertical centralizado */
        td { padding: 14px; font-size: 0.875rem; border-bottom: 1px solid #f7f7f7; vertical-align: middle; }
        
        /* Remove a borda inferior da última linha da tabela */
        tr:last-child td { border-bottom: none; }
        
        /* Adiciona cor de fundo cinza bem clara ao passar o mouse sobre as linhas da tabela */
        tr:hover td { background: #fafafa; }
 
        /* ── BADGES / ETIQUETAS DE STATUS ── */
        /* Estilo comum para pequenas tags de status (ex: status de validade ou estoque) */
        .badge { display: inline-flex; align-items: center; padding: 4px 10px; border-radius: 20px; font-size: 0.72rem; font-weight: 600; text-transform: uppercase; }
        
        /* Badge para status normal ou seguro (verde suave) */
        .badge-normal  { background: #dcfce7; color: #16a34a; }
        
        /* Badge para status que exige atenção ou pendência (amarelo suave) */
        .badge-atencao { background: #fef9c3; color: #ca8a04; }
        
        /* Badge para status crítico ou de emergência (vermelho suave) */
        .badge-critico { background: #fee2e2; color: #dc2626; }
 
        /* ── ALERTAS DE SISTEMA ── */
        /* Estilo geral para caixas de mensagens de sucesso, erro ou alerta */
        .alert { padding: 14px 18px; border-radius: 8px; font-size: 0.9rem; margin-bottom: 24px; font-weight: 500; }
        
        /* Alerta de operação realizada com sucesso (fundo verde e borda correspondente) */
        .alert-success { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
        
        /* Alerta de erro ou falha em operações (fundo vermelho e borda correspondente) */
        .alert-danger { background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }
        
        /* Alerta de aviso ou cuidado (fundo amarelo e borda correspondente) */
        .alert-warning { background: #fef9c3; color: #a16207; border: 1px solid #fef08a; }
 
        /* Regras de media query para telas com resolução de até 768px (responsividade mobile) */
        @media(max-width: 768px) {
            /* Altera o grid do formulário de duas colunas para uma única coluna */
            .form-grid { grid-template-columns: 1fr; }
            
            /* Diminui o espaçamento interno lateral do cabeçalho */
            .header-top { padding: 0 16px; }
            
            /* Adiciona barra de rolagem horizontal na navegação caso os itens superem a largura da tela */
            .nav { padding: 0 16px; overflow-x: auto; }
            
            /* Reduz o espaçamento interno do container de conteúdo principal da página */
            .content { padding: 16px; }
        }
    </style>
</head>
<body>
 
<!-- Define o cabeçalho superior da página -->
<header class="header">
    <div class="header-top">
        <!-- Logo que aponta de volta para o dashboard principal -->
        <a href="../dashboard/index.php" class="logo">
            <h1>ChefSupply</h1>
            <span>Gestão Inteligente</span>
        </a>
        
        <!-- Bloco de busca rápida do sistema -->
        <div class="header-center">
            <div class="search-box">
                <!-- Ícone SVG representando uma lupa de busca -->
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                <!-- Input de busca global -->
                <input type="text" placeholder="Buscar produtos, lotes, fornecedores...">
            </div>
        </div>
        
        <!-- Informações do usuário ativo exibidas no cabeçalho à direita -->
        <div class="header-right">
            <div class="user-info">
                <div>
                    <!-- Nome do usuário armazenado na sessão. Exibe 'Usuário' caso não esteja definido -->
                    <div class="user-name"><?= htmlspecialchars($_SESSION['usuario_nome'] ?? 'Usuário') ?></div>
                    <div class="user-sub">Restaurante Premium</div>
                </div>
                <!-- Avatar composto pela primeira letra do nome do usuário em caixa alta. Exibe 'U' por padrão -->
                <div class="user-avatar"><?= strtoupper(substr($_SESSION['usuario_nome'] ?? 'U', 0, 1)) ?></div>
            </div>
        </div>
    </div>
 
    <!-- Menu de navegação principal da aplicação -->
    <nav class="nav">
        <!-- Link para o Dashboard: se a página atual for 'dashboard', adiciona a classe 'active' para destaque -->
        <a href="../dashboard/index.php" class="nav-item <?= ($pagina_atual === 'dashboard') ? 'active' : '' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>Dashboard
        </a>
        
        <!-- Link para a Gestão de Produtos: se a página atual for 'produtos', ativa o destaque no menu -->
        <a href="../produtos/index.php" class="nav-item <?= ($pagina_atual === 'produtos') ? 'active' : '' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>Produtos
        </a>
        
        <!-- Link para o Estoque/Lotes: se a página atual for 'estoque', ativa o destaque no menu -->
        <a href="../estoque/index.php" class="nav-item <?= ($pagina_atual === 'estoque') ? 'active' : '' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 3H8L2 7h20z"/></svg>Estoque
        </a>
        
        <!-- Link para Entradas/Movimentações: se a página atual for 'entradas', ativa o destaque no menu -->
        <a href="../entradas/index.php" class="nav-item <?= ($pagina_atual === 'entradas') ? 'active' : '' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v20M2 12h20"/></svg>Entradas
        </a>
        
        <!-- Link para Gestão de Fornecedores: se a página atual for 'fornecedores', ativa o destaque no menu -->
        <a href="../fornecedores/index.php" class="nav-item <?= ($pagina_atual === 'fornecedores') ? 'active' : '' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>Fornecedores
        </a>
        
        <!-- Link para Controle de Descartes: se a página atual for 'descartes', ativa o destaque no menu -->
        <a href="../descartes/index.php" class="nav-item <?= ($pagina_atual === 'descartes') ? 'active' : '' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/></svg>Descartes
        </a>
        
        <!-- Link para Visualização de Relatórios: se a página atual for 'relatorios', ativa o destaque no menu -->
        <a href="../relatorios/index.php" class="nav-item <?= ($pagina_atual === 'relatorios') ? 'active' : '' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>Relatórios
        </a>
        
        <!-- Link para Gerenciamento de Usuários: se a página atual for 'usuarios', ativa o destaque no menu -->
        <a href="../usuarios/index.php" class="nav-item <?= ($pagina_atual === 'usuarios') ? 'active' : '' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>Usuários
        </a>
        
        <!-- Link para as Configurações do Sistema: se a página atual for 'configuracoes', ativa o destaque no menu -->
        <a href="../configuracoes/index.php" class="nav-item <?= ($pagina_atual === 'configuracoes') ? 'active' : '' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14M4.93 4.93a10 10 0 0 0 0 14.14"/></svg>Configurações
        </a>
        
        <!-- Link especial de encerramento de sessão, redireciona para o arquivo logout.php -->
        <a href="../logout.php" class="nav-item nav-sair">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>Sair
        </a>
    </nav>
</header>
