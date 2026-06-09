<?php
// Inicia a sessão do PHP para permitir o uso de variáveis de sessão, mantendo o estado do usuário logado
session_start();

// Verifica se a variável de sessão 'logado' não está configurada ou se é falsa.
// Se o usuário não estiver autenticado, redireciona para a página de login/inicial localizada um nível acima.
if(!isset($_SESSION['logado']) || !$_SESSION['logado']){
    // Redireciona o navegador do usuário para o arquivo index.php da raiz do projeto
    header('Location: ../index.php');
    // Encerra imediatamente a execução do script PHP para evitar processamento indevido
    exit;
}

// Requer o arquivo de conexão com o banco de dados (PDO).
// O require_once garante que o arquivo seja importado apenas uma vez e lance erro fatal se não for encontrado.
require_once '../conexao.php';

// ── MANIPULAÇÃO DE FILTROS ──────────────────────────────────────────
// $where: Vetor (array) que armazenará as condições SQL a serem aplicadas dinamicamente na query.
// Iniciamos com "1=1" (verdadeiro por padrão) para facilitar a concatenação subsequente de filtros usando o operador AND.
$where = ["1=1"];

// $params: Vetor associativo que guardará os parâmetros e valores que serão vinculados (binded) na consulta SQL preparada, 
// prevenindo ataques de injeção de SQL (SQL Injection).
$params = [];

// Busca de texto (nome do produto ou código do lote).
// Obtém o valor do parâmetro 'q' enviado via método GET (URL). Se não existir, define como string vazia.
// O trim() é utilizado para remover espaços em branco no início e fim do texto.
$search = trim($_GET['q'] ?? '');
// Se a pesquisa por texto não estiver vazia:
if ($search !== '') {
    // Adiciona a condição que busca no nome do produto (p.nome) ou no código do lote (l.codigo_lote) usando o operador LIKE.
    $where[] = "(p.nome LIKE :search OR l.codigo_lote LIKE :search)";
    // Associa o parâmetro ':search' com curingas '%' para buscar qualquer parte do texto correspondente.
    $params[':search'] = '%' . $search . '%';
}

// Filtro por produto específico.
// Obtém o ID do produto da URL (GET) e converte para um número inteiro (intval). Se não existir, assume 0.
$filtro_produto = intval($_GET['produto_id'] ?? 0);
// Se um ID de produto válido foi passado (maior que 0):
if ($filtro_produto > 0) {
    // Adiciona a condição de igualdade para a coluna produto_id da tabela de lotes (l.produto_id).
    $where[] = "l.produto_id = :produto_id";
    // Associa o parâmetro ':produto_id' ao valor inteiro do filtro selecionado.
    $params[':produto_id'] = $filtro_produto;
}

// Filtro de validade / status do lote.
// Obtém a situação desejada da URL (GET). Se não for fornecida, define 'ativos' como padrão.
$filtro_situacao = trim($_GET['situacao'] ?? 'ativos');

// Estrutura condicional para validar qual tipo de situação de estoque o usuário deseja visualizar:
if ($filtro_situacao === 'vencidos') {
    // Se a opção selecionada for 'vencidos', filtra lotes onde a data de vencimento é menor que a data atual,
    // que tenham quantidade restante maior que zero e com status 'ativo' (lotes que expiraram e continuam no estoque).
    $where[] = "l.data_vencimento < CURDATE() AND l.quantidade_restante > 0 AND l.status = 'ativo'";
} elseif ($filtro_situacao === 'vencendo') {
    // Se for 'vencendo', filtra lotes cuja data de vencimento esteja entre a data atual e os próximos 7 dias úteis/corridos,
    // com quantidade restante maior que zero e com status 'ativo'.
    $where[] = "l.data_vencimento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) AND l.quantidade_restante > 0 AND l.status = 'ativo'";
} elseif ($filtro_situacao === 'ativos') {
    // Se for 'ativos' (padrão), exibe apenas lotes que possuem saldo (quantidade_restante > 0) e que estão ativos comercialmente.
    $where[] = "l.quantidade_restante > 0 AND l.status = 'ativo'";
} elseif ($filtro_situacao === 'esgotados') {
    // Se for 'esgotados', exibe lotes que zeraram a quantidade restante ou cujo status foi marcado explicitamente como 'consumido'.
    $where[] = "(l.quantidade_restante = 0 OR l.status = 'consumido')";
} elseif ($filtro_situacao === 'descartados') {
    // Se for 'descartados', filtra apenas os lotes que foram baixados administrativamente com o status 'descartado' (perdas).
    $where[] = "l.status = 'descartado'";
}

// Junta todas as condições do array $where em uma única string SQL, separando-as com a palavra-chave " AND ".
$where_clause = implode(" AND ", $where);

// ── CÁLCULOS DE INDICADORES (KPI) GLOBAIS ──────────────────────────────────
// Consulta para contar o número de lotes vencidos atualmente ativos no estoque.
// Executa uma query simples usando o PDO e obtém o resultado da primeira coluna (COUNT(*)).
$lotes_vencidos = $conexao->query("
    SELECT COUNT(*) FROM lotes 
    WHERE status = 'ativo' 
      AND data_vencimento IS NOT NULL 
      AND data_vencimento < CURDATE() 
      AND quantidade_restante > 0
")->fetchColumn();

// Consulta para contar o número de lotes ativos que vencem nos próximos 7 dias.
// Usa o método query do PDO e retorna a coluna de contagem usando fetchColumn().
$lotes_vencendo = $conexao->query("
    SELECT COUNT(*) FROM lotes 
    WHERE status = 'ativo' 
      AND data_vencimento IS NOT NULL 
      AND data_vencimento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
      AND quantidade_restante > 0
")->fetchColumn();

// Consulta para calcular a soma total das quantidades restantes de todos os insumos de lotes ativos.
// A função COALESCE garante que, se o resultado da soma for nulo (estoque vazio), retorne 0.
$total_itens = $conexao->query("
    SELECT COALESCE(SUM(quantidade_restante), 0) FROM lotes 
    WHERE status = 'ativo' AND quantidade_restante > 0
")->fetchColumn();

// Consulta para calcular o valor monetário total do estoque baseado no custo e quantidade de cada lote ativo.
// Multiplica o saldo de cada lote (quantidade_restante) por seu preço de custo (preco_custo).
// COALESCE evita valores nulos no retorno da operação.
$total_valor_estoque = $conexao->query("
    SELECT COALESCE(SUM(quantidade_restante * preco_custo), 0) FROM lotes 
    WHERE status = 'ativo' AND quantidade_restante > 0
")->fetchColumn();

// ── CONSULTA DA LISTAGEM DE LOTES ────────────────────────────────────────────
// Constrói a consulta SQL para selecionar os dados dos lotes, trazendo o nome e unidade do produto (da tabela produtos)
// e o nome do fornecedor associado (fazendo LEFT JOIN caso o fornecedor tenha sido excluído ou não esteja informado).
$query = "
    SELECT l.*, p.nome AS produto_nome, p.unidade AS produto_unidade,
           f.nome AS fornecedor_nome
    FROM lotes l
    JOIN produtos p ON l.produto_id = p.id
    LEFT JOIN fornecedores f ON l.fornecedor_id = f.id
    WHERE $where_clause
    ORDER BY 
        -- Ordena primeiro os lotes que têm data de vencimento (valor 0) antes dos que não têm validade definida (valor 1)
        CASE WHEN l.data_vencimento IS NULL THEN 1 ELSE 0 END, 
        -- Ordena em ordem cronológica de vencimento mais próximo ao mais distante
        l.data_vencimento ASC, 
        -- Em caso de empate, ordena alfabeticamente pelo nome do produto
        p.nome ASC
";

// Prepara a instrução SQL no banco de dados para evitar injeções de código.
$stmt = $conexao->prepare($query);
// Executa a instrução preparada passando o vetor de parâmetros preenchidos dinamicamente nos filtros.
$stmt->execute($params);
// Recupera todos os registros correspondentes em um array associativo.
$lotes_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Busca a lista completa de produtos ordenada por nome para alimentar o elemento de seleção (dropdown) do filtro.
// Executa uma query simples direta e extrai todos os resultados associados.
$todos_produtos = $conexao->query("SELECT id, nome FROM produtos ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);

// Define a variável $pagina_atual como 'estoque' para marcar o menu de navegação lateral ativo.
$pagina_atual = 'estoque';
// Define o título da página exibido no cabeçalho.
$titulo_pagina = 'Lotes em Estoque';
// Inclui o arquivo contendo o cabeçalho padrão e a estrutura inicial do painel.
include '../_header.php';
?>

<!-- Injeção de CSS específico para estilizar os cartões de filtros, indicadores (KPIs) e os emblemas (badges) -->
<style>
    /* Estilização do container principal de filtros */
    .filters-card {
        background: #fff; /* Fundo branco do card */
        border: 1px solid #e8e8e8; /* Borda cinza suave */
        border-radius: 12px; /* Cantos arredondados */
        padding: 16px 20px; /* Espaçamento interno */
        margin-bottom: 24px; /* Espaçamento inferior em relação à tabela */
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.02); /* Sombra sutil nas bordas */
    }
    /* Grid layout para organizar o formulário de filtros */
    .filters-form {
        display: grid;
        /* Define proporções das colunas (4 colunas com larguras variáveis e a última auto-ajustável) */
        grid-template-columns: 2fr 1.5fr 1.5fr auto;
        gap: 16px; /* Espaçamento entre os elementos do formulário */
        align-items: flex-end; /* Alinha os botões ao final da altura dos inputs */
    }
    /* Grupo individual de cada campo de entrada e seu rótulo */
    .filter-group {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }
    /* Estilo dos rótulos explicativos dos campos de busca */
    .filter-group label {
        font-size: 0.78rem;
        font-weight: 600;
        color: #666;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    /* Configuração dos campos de entrada de texto e seletores */
    .filter-group input, .filter-group select {
        padding: 8px 12px;
        border: 1.5px solid #e5e5e5;
        border-radius: 6px;
        font-family: 'Inter', sans-serif;
        font-size: 0.875rem;
        background: #fafafa;
        outline: none;
        transition: all 0.2s; /* Transição suave na alteração de propriedades no foco */
    }
    /* Efeito visual ao focar no campo de entrada */
    .filter-group input:focus, .filter-group select:focus {
        border-color: #2db35d; /* Altera a borda para a cor verde de destaque */
        background: #fff; /* Altera o fundo do campo para branco puro */
    }
    /* Alinhamento dos botões de ação (Filtrar e Limpar) */
    .filter-actions-inline {
        display: flex;
        gap: 8px;
    }
    /* Altura consistente com os inputs adjacentes */
    .filter-actions-inline .btn {
        height: 38px;
        padding: 0 16px;
    }

    /* Grid para exibir os cartões indicadores (dashboard de KPIs) */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr); /* 4 colunas de tamanho igual */
        gap: 16px;
        margin-bottom: 24px;
    }
    /* Estilização individual de cada cartão de estatística */
    .stat-card {
        background: #fff;
        border: 1px solid #e8e8e8;
        border-radius: 12px;
        padding: 20px;
        display: flex;
        align-items: center;
        justify-content: space-between; /* Espaça o texto e o ícone nas extremidades */
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.02);
    }
    /* Estilo do rótulo da estatística (ex: "Lotes Vencidos") */
    .stat-label { font-size: 0.78rem; color: #666; margin-bottom: 6px; font-weight: 500; }
    /* Estilo do valor numérico de destaque do indicador */
    .stat-value { font-size: 1.6rem; font-weight: 700; line-height: 1.1; }
    /* Pequeno texto explicativo de apoio no rodapé do cartão */
    .stat-subtext { font-size: 0.75rem; color: #888; margin-top: 4px; }
    /* Ícone visual do cartão */
    .stat-icon {
        width: 44px; height: 44px;
        border-radius: 10px;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.2rem;
        flex-shrink: 0; /* Garante que o ícone não encolha caso a tela reduza */
    }

    /* Badges / Emblemas de sinalização de validade do produto */
    .days-badge {
        display: inline-flex;
        align-items: center;
        padding: 2px 8px;
        border-radius: 12px;
        font-size: 0.75rem;
        font-weight: 600;
    }
    /* Lote vencido: Vermelho escuro com fundo vermelho suave */
    .days-expired { background: #fee2e2; color: #dc2626; }
    /* Lote com vencimento próximo (alerta): Amarelo escuro com fundo amarelo suave */
    .days-warning { background: #fef9c3; color: #ca8a04; }
    /* Lote regular dentro da validade: Verde com fundo verde suave */
    .days-ok { background: #dcfce7; color: #16a34a; }
    /* Lote esgotado ou indefinido: Cinza */
    .days-none { background: #f3f4f6; color: #6b7280; }

    /* Media queries para responsividade em telas médias (tablets) */
    @media(max-width: 1024px) {
        .filters-form {
            grid-template-columns: 1fr 1fr; /* Passa para duas colunas */
        }
        .filter-actions-inline {
            grid-column: span 2; /* Faz os botões ocuparem as duas colunas inteiras abaixo */
            justify-content: flex-end;
        }
        .stats-grid {
            grid-template-columns: repeat(2, 1fr); /* 2 colunas para os KPIs */
        }
    }
    /* Media queries para telas pequenas (smartphones) */
    @media(max-width: 600px) {
        .filters-form {
            grid-template-columns: 1fr; /* Todo o formulário de filtros passa a ter coluna única */
        }
        .filter-actions-inline {
            grid-column: span 1;
        }
        .stats-grid {
            grid-template-columns: 1fr; /* KPIs empilhados verticalmente */
        }
    }
</style>

<!-- Bloco de conteúdo principal da página -->
<div class="content">
    <!-- Cabeçalho Principal -->
    <div class="page-header">
        <div class="page-header-left">
            <h2>Lotes & Vencimentos</h2>
            <p>Gerencie o estoque de forma fracionada por data de vencimento e controle o giro de insumos.</p>
        </div>
    </div>

    <!-- Painel de Indicadores Gerais (KPI Dashboard) -->
    <div class="stats-grid">
        <!-- Indicador de Valor Financeiro Total em Estoque -->
        <div class="stat-card">
            <div>
                <div class="stat-label">Valor do Estoque Ativo</div>
                <!-- Exibe o valor do estoque ativo formatado no padrão de moeda Real Brasileiro (R$) -->
                <div class="stat-value" style="color: #2db35d;">R$ <?= number_format($total_valor_estoque, 2, ',', '.') ?></div>
                <div class="stat-subtext">Valor total em lotes ativos</div>
            </div>
            <div class="stat-icon" style="background: #eafaf1; color: #2db35d;">
                R$
            </div>
        </div>

        <!-- Indicador de Lotes Vencidos (Requer atenção urgente) -->
        <div class="stat-card">
            <div>
                <div class="stat-label">Lotes Vencidos</div>
                <!-- Altera a cor do texto para vermelho caso haja algum lote vencido no estoque, senão exibe em verde -->
                <div class="stat-value" style="color: <?= $lotes_vencidos > 0 ? '#dc2626' : '#16a34a' ?>;"><?= number_format($lotes_vencidos) ?></div>
                <div class="stat-subtext font-weight-bold">Bloqueados para consumo</div>
            </div>
            <!-- Ícone de alerta visual -->
            <div class="stat-icon" style="background: #fee2e2; color: #dc2626;">
                ⚠️
            </div>
        </div>

        <!-- Indicador de Lotes Vencendo nos próximos 7 dias -->
        <div class="stat-card">
            <div>
                <div class="stat-label">Vencimentos Próximos</div>
                <!-- Altera para amarelo/marrom caso haja lotes próximos do vencimento -->
                <div class="stat-value" style="color: <?= $lotes_vencendo > 0 ? '#ca8a04' : '#1a1a1a' ?>;"><?= number_format($lotes_vencendo) ?></div>
                <div class="stat-subtext">Vencendo nos próximos 7 dias</div>
            </div>
            <div class="stat-icon" style="background: #fef9c3; color: #ca8a04;">
                ⏳
            </div>
        </div>

        <!-- Indicador de Volume Total Físico no Estoque -->
        <div class="stat-card">
            <div>
                <div class="stat-label">Volume de Insumos</div>
                <!-- Exibe a soma de todas as unidades restantes dos lotes ativos -->
                <div class="stat-value"><?= number_format($total_itens, 2, ',', '.') ?></div>
                <div class="stat-subtext">Soma das quantidades restantes</div>
            </div>
            <div class="stat-icon" style="background: #eff6ff; color: #2563eb;">
                ⚖️
            </div>
        </div>
    </div>

    <!-- Bloco com o formulário de filtros de busca -->
    <div class="filters-card">
        <!-- O formulário envia os parâmetros via GET para a própria página index.php -->
        <form method="GET" action="index.php" class="filters-form">
            <!-- Input de busca por texto livre (nome do produto ou código do lote) -->
            <div class="filter-group">
                <label for="q">Buscar lote / produto</label>
                <input type="text" name="q" id="q" placeholder="Nome do produto ou código do lote..." value="<?= htmlspecialchars($search) ?>">
            </div>

            <!-- Seleção para filtrar por um produto específico -->
            <div class="filter-group">
                <label for="produto_id">Filtrar Produto</label>
                <select name="produto_id" id="produto_id">
                    <option value="">Todos os produtos</option>
                    <!-- Itera pela lista de todos os produtos do banco de dados para popular as opções -->
                    <?php foreach($todos_produtos as $tp): ?>
                        <!-- Verifica se o ID do produto iterado é igual ao do filtro ativo para marcar a opção como selecionada (selected) -->
                        <option value="<?= $tp['id'] ?>" <?= $filtro_produto == $tp['id'] ? 'selected' : '' ?>><?= htmlspecialchars($tp['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Seleção para filtrar pelo status de vencimento ou se está ativo/esgotado -->
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

            <!-- Botões de ação para submeter ou resetar os filtros -->
            <div class="filter-actions-inline">
                <button type="submit" class="btn btn-primary">Filtrar</button>
                <a href="index.php" class="btn btn-secondary">Limpar</a>
            </div>
        </form>
    </div>

    <!-- Tabela principal que exibe a listagem de lotes -->
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
                <!-- Verifica se a lista de lotes recuperada do banco de dados está vazia -->
                <?php if (empty($lotes_list)): ?>
                    <tr>
                        <!-- Exibe uma mensagem amigável ocupando todas as 11 colunas da tabela -->
                        <td colspan="11" style="text-align: center; padding: 48px; color: #888;">
                            Nenhum lote de mercadoria correspondente aos filtros foi encontrado.
                        </td>
                    </tr>
                <?php else: ?>
                    <!-- Caso contenha registros, inicia a iteração (foreach) para desenhar cada linha da tabela -->
                    <?php foreach ($lotes_list as $l): 
                        // Calcula o valor financeiro do saldo restante do lote (Qtd. Restante * Preço de custo unitário)
                        $saldo_valor = floatval($l['quantidade_restante']) * floatval($l['preco_custo']);
                        
                        // Inicializa variáveis padrão para controle de prazos e exibição visual de alertas de vencimento
                        $days_text = '—'; // Texto padrão caso não tenha vencimento
                        $days_cls = 'days-none'; // Classe CSS padrão
                        $show_discard_button = false; // Controle se o botão de descarte (Registrar Perda) deve ser exibido

                        // Lógica de determinação do status visual do lote:
                        if ($l['status'] === 'descartado') {
                            // Se o status for descartado, define texto e classe específicos para descarte
                            $days_text = 'Descartado';
                            $days_cls = 'days-expired';
                        } elseif (floatval($l['quantidade_restante']) == 0) {
                            // Se o estoque restante for 0, marca como esgotado
                            $days_text = 'Esgotado';
                            $days_cls = 'days-none';
                        } elseif ($l['data_vencimento']) {
                            // Se o lote possui data de vencimento registrada:
                            $dataVenc = strtotime($l['data_vencimento']); // Converte data de vencimento para timestamp
                            $dataHoje = strtotime(date('Y-m-d')); // Converte a data de hoje para timestamp
                            // Calcula a diferença em dias (segundos divididos por 86400, que corresponde a 24 * 60 * 60)
                            $diasRestantes = ($dataVenc - $dataHoje) / 86400;

                            if ($diasRestantes < 0) {
                                // Se a diferença for negativa, o lote está vencido
                                $diasPassados = abs($diasRestantes); // Obtém o valor absoluto dos dias passados
                                $days_text = "Vencido há {$diasPassados} dia(s)";
                                $days_cls = "days-expired"; // Aplica o estilo de erro/alerta vermelho
                                $show_discard_button = true; // Permite registrar o descarte do produto vencido
                            } elseif ($diasRestantes == 0) {
                                // Se vence no dia de hoje
                                $days_text = "Vence Hoje!";
                                $days_cls = "days-expired";
                                $show_discard_button = true;
                            } elseif ($diasRestantes <= 7) {
                                // Se vence em até 7 dias, aplica classe amarela de aviso de atenção
                                $days_text = "{$diasRestantes} dia(s) restante(s)";
                                $days_cls = "days-warning";
                                $show_discard_button = true;
                            } else {
                                // Se está dentro do prazo normal (mais de 7 dias), aplica a classe verde
                                $days_text = "{$diasRestantes} dia(s)";
                                $days_cls = "days-ok";
                                $show_discard_button = true;
                            }
                        } else {
                            // Se não tiver data de vencimento definida (ex: produtos não perecíveis)
                            $days_text = 'Sem vencimento';
                            $days_cls = 'days-ok';
                            $show_discard_button = true;
                        }
                    ?>
                        <!-- Renderiza a linha HTML da tabela para o lote atual da iteração -->
                        <tr>
                            <td>
                                <!-- Se houver código de lote definido pelo fornecedor, exibe de forma destacada -->
                                <?php if ($l['codigo_lote']): ?>
                                    <span class="badge" style="background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; font-weight: 600;"><?= htmlspecialchars($l['codigo_lote']) ?></span>
                                <?php else: ?>
                                    <!-- Caso contrário, exibe o ID sequencial do lote no banco de dados -->
                                    <span class="badge" style="background: #f3f4f6; color: #4b5563;">#<?= $l['id'] ?></span>
                                <?php endif; ?>
                            </td>
                            <!-- Nome do produto principal em negrito -->
                            <td><strong><?= htmlspecialchars($l['produto_nome']) ?></strong></td>
                            <!-- Nome do fornecedor associado ao lote. Se nulo, exibe travessão (—) -->
                            <td><?= htmlspecialchars($l['fornecedor_nome'] ?? '—') ?></td>
                            <!-- Data de entrada formatada para o padrão brasileiro (d/m/Y) -->
                            <td><?= date('d/m/Y', strtotime($l['data_entrada'])) ?></td>
                            <!-- Data de vencimento formatada. Caso nulo, exibe "Não informado" -->
                            <td>
                                <?= $l['data_vencimento'] ? date('d/m/Y', strtotime($l['data_vencimento'])) : '<span style="color: #aaa;">Não informado</span>' ?>
                            </td>
                            <td>
                                <!-- Insere a badge de tempo restante com a classe e texto calculados dinamicamente no bloco PHP acima -->
                                <span class="days-badge <?= $days_cls ?>"><?= $days_text ?></span>
                            </td>
                            <!-- Quantidade inicial que deu entrada no lote e sua unidade de medida correspondente -->
                            <td><?= number_format($l['quantidade'], 2, ',', '.') ?> &nbsp;<small style="color: #666;"><?= htmlspecialchars($l['produto_unidade']) ?></small></td>
                            <td>
                                <!-- Destaca o saldo atual restante. Se o saldo for igual a zero, aplica uma cor cinza opaca -->
                                <strong style="color: <?= floatval($l['quantidade_restante']) == 0 ? '#999' : '#1a1a1a' ?>;">
                                    <?= number_format($l['quantidade_restante'], 2, ',', '.') ?>
                                </strong>
                                <small style="color: #666;"><?= htmlspecialchars($l['produto_unidade']) ?></small>
                            </td>
                            <!-- Preço de custo unitário formatado em Real -->
                            <td>R$ <?= number_format($l['preco_custo'], 2, ',', '.') ?></td>
                            <!-- Valor financeiro restante calculado no bloco PHP em negrito -->
                            <td><strong>R$ <?= number_format($saldo_valor, 2, ',', '.') ?></strong></td>
                            <td style="text-align: center;">
                                <!-- Se o botão de perda puder ser exibido (lote não descartado ou esgotado), fornece link para registrar o descarte -->
                                <?php if ($show_discard_button): ?>
                                    <a href="../descartes/novo.php?produto_id=<?= $l['produto_id'] ?>&lote_id=<?= $l['id'] ?>" class="btn btn-danger btn-sm" style="padding: 6px 10px;">
                                        Registrar Perda
                                    </a>
                                <?php else: ?>
                                    <!-- Caso contrário, exibe apenas um travessão desabilitado -->
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

<!-- Inclui o rodapé padrão da página com o fechamento das tags HTML e scripts globais -->
<?php include '../_footer.php'; ?>