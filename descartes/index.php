<?php
// Inicia a sessão PHP para verificação e validação de login do usuário.
session_start();

// Verifica se o usuário não está autenticado (variável de sessão 'logado' vazia ou falsa).
// Caso não esteja, redireciona-o para a página de login no nível acima e encerra o fluxo do script.
if(!isset($_SESSION['logado']) || !$_SESSION['logado']){
    header('Location: ../index.php');
    exit;
}

// Inclui o arquivo de conexão com o banco de dados (PDO) usando require_once para garantir que o script seja importado uma única vez.
require_once '../conexao.php';

// ── TRATAMENTO DE FILTROS (FILTER HANDLING) ──────────────────────────────────────────
// Inicializa o array de condições do WHERE com '1=1' para facilitar a concatenação dinâmica de múltiplos filtros com o operador 'AND'.
$where = ["1=1"];
// Inicializa o array que conterá os valores associados aos parâmetros da query (prepared statements).
$params = [];

// Filtro por produto específico: obtém o parâmetro via GET, convertendo-o em número inteiro.
$filtro_produto = intval($_GET['produto_id'] ?? 0);
// Se o filtro do produto for selecionado (maior que 0), adiciona à cláusula WHERE e ao array de parâmetros.
if($filtro_produto > 0){
    $where[] = "d.produto_id = :produto_id";
    $params[':produto_id'] = $filtro_produto;
}

// Filtro por motivo do descarte: obtém o parâmetro via GET e remove espaços extras.
$filtro_motivo = trim($_GET['motivo'] ?? '');
// Se um motivo válido for fornecido, anexa o filtro à cláusula WHERE e armazena o valor nos parâmetros.
if($filtro_motivo !== ''){
    $where[] = "d.motivo = :motivo";
    $params[':motivo'] = $filtro_motivo;
}

// Filtro por data de início do descarte: obtém a data inicial informada pelo usuário.
$filtro_inicio = trim($_GET['data_inicio'] ?? '');
// Se fornecida, adiciona uma condição para buscar registros com data maior ou igual à informada.
if($filtro_inicio !== ''){
    $where[] = "d.data_descarte >= :data_inicio";
    $params[':data_inicio'] = $filtro_inicio;
}

// Filtro por data de fim do descarte: obtém a data limite informada pelo usuário.
$filtro_fim = trim($_GET['data_fim'] ?? '');
// Se fornecida, adiciona uma condição para buscar registros com data menor ou igual à informada.
if($filtro_fim !== ''){
    $where[] = "d.data_descarte <= :data_fim";
    $params[':data_fim'] = $filtro_fim;
}

// Busca por texto (nome do produto ou observações do descarte).
$search = trim($_GET['q'] ?? '');
// Se o termo de busca não estiver vazio, adiciona a comparação LIKE utilizando curingas '%' em ambas as pontas.
if($search !== ''){
    $where[] = "(p.nome LIKE :search OR d.observacoes LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

// Une todas as condições do array '$where' em uma única string separada por ' AND '.
$where_clause = implode(" AND ", $where);

// ── CÁLCULO DOS INDICADORES CHAVE (KPI CALCULATIONS) ─────────────────────────────────────────
// 1. Custo total acumulado das perdas financeiras.
// Executa a query somando a coluna 'valor_perdido'. Se o resultado for nulo, utiliza COALESCE para retornar 0.
$stmtLoss = $conexao->prepare("
    SELECT COALESCE(SUM(d.valor_perdido), 0) 
    FROM descartes d
    JOIN produtos p ON d.produto_id = p.id
    WHERE $where_clause
");
// Executa a query passando os parâmetros coletados nos filtros.
$stmtLoss->execute($params);
// Recupera a primeira coluna do resultado que representa o valor total das perdas.
$total_perdido = $stmtLoss->fetchColumn();

// 2. Quantidade total de registros de descartes no período/filtros selecionados.
// Prepara o SQL para contar o número de linhas resultantes da filtragem atual.
$stmtCount = $conexao->prepare("
    SELECT COUNT(*) 
    FROM descartes d
    JOIN produtos p ON d.produto_id = p.id
    WHERE $where_clause
");
// Executa a contagem.
$stmtCount->execute($params);
// Recupera o total de registros retornados pela contagem.
$total_registros = $stmtCount->fetchColumn();

// 3. Volume total descartado (soma das quantidades brutas descartadas).
// Prepara o SQL para somar a coluna 'quantidade' dos produtos descartados.
$stmtVol = $conexao->prepare("
    SELECT COALESCE(SUM(d.quantidade), 0) 
    FROM descartes d
    JOIN produtos p ON d.produto_id = p.id
    WHERE $where_clause
");
// Executa a soma.
$stmtVol->execute($params);
// Recupera o volume acumulado retornado.
$total_volume = $stmtVol->fetchColumn();

// 4. Identificação do motivo principal (causa mais recorrente de descartes).
// Prepara a consulta que agrupa os descartes por motivo, conta as ocorrências e ordena de forma decrescente, limitando a 1 resultado.
$stmtMot = $conexao->prepare("
    SELECT d.motivo, COUNT(*) as qtd
    FROM descartes d
    JOIN produtos p ON d.produto_id = p.id
    WHERE $where_clause
    GROUP BY d.motivo
    ORDER BY qtd DESC
    LIMIT 1
");
// Executa a consulta de motivo principal.
$stmtMot->execute($params);
// Recupera o resultado como um array associativo.
$motivo_principal_row = $stmtMot->fetch(PDO::FETCH_ASSOC);
// Se houver algum registro, define o motivo principal; caso contrário, define como 'Nenhum'.
$motivo_principal = $motivo_principal_row ? $motivo_principal_row['motivo'] : 'Nenhum';

// ── CONSULTA DE LISTAGEM DE RESULTADOS (LISTING QUERY) ────────────────────────────────────────────
// Prepara a query SQL principal para buscar os dados dos descartes, nome e unidade do produto, código do lote e nome do usuário.
// Realiza JOIN com produtos, e LEFT JOIN com lotes e usuários, pois estes dois últimos podem ser opcionais/nulos.
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
// Executa a query principal vinculando os parâmetros dinâmicos de filtragem.
$stmtDescartes->execute($params);
// Recupera todos os registros de descartes no formato de array associativo.
$descartes = $stmtDescartes->fetchAll(PDO::FETCH_ASSOC);

// Busca a lista completa de todos os produtos do sistema para popular o menu suspenso de filtro de produtos.
$todos_produtos = $conexao->query("SELECT id, nome FROM produtos ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);

// Define a seção de navegação activa no menu principal.
$pagina_atual = 'descartes';
// Define o título que será exibido no cabeçalho da página e na tag <title>.
$titulo_pagina = 'Descartes e Perdas';

// Inclui o cabeçalho padrão da aplicação.
include '../_header.php';
?>

<!-- Injeção de regras CSS específicas para a estilização dos filtros, cartões estatísticos e responsividade desta página -->
<style>
    /* Estilização do painel de filtros */
    .filters-card {
        background: #fff;
        border: 1px solid #e8e8e8;
        border-radius: 12px;
        padding: 16px 20px;
        margin-bottom: 24px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.02);
    }
    /* Organização dos filtros em uma grade CSS flexível */
    .filters-form {
        display: grid;
        grid-template-columns: 1.5fr 1fr 1fr 1fr 1fr auto;
        gap: 16px;
        align-items: flex-end;
    }
    /* Estilização dos grupos individuais de filtros (label + input) */
    .filter-group {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }
    /* Estilos do rótulo dos campos de filtros */
    .filter-group label {
        font-size: 0.78rem;
        font-weight: 600;
        color: #666;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    /* Estilos comuns para inputs e selects dos filtros */
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
    /* Efeito visual ao focar nos campos de filtros */
    .filter-group input:focus, .filter-group select:focus {
        border-color: #2db35d;
        background: #fff;
    }
    /* Alinhamento dos botões de ação na linha de filtros */
    .filter-actions-inline {
        display: flex;
        gap: 8px;
    }
    /* Altura consistente dos botões da seção de filtros */
    .filter-actions-inline .btn {
        height: 38px;
        padding: 0 16px;
    }

    /* Grid estrutural para os cartões de estatísticas (KPIs) */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 16px;
        margin-bottom: 24px;
    }
    /* Design do cartão individual de KPI */
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
    /* Label de texto menor nos cartões de KPI */
    .stat-label { font-size: 0.78rem; color: #666; margin-bottom: 6px; font-weight: 500; }
    /* Estilo para destacar o valor numérico dos cartões de KPI */
    .stat-value { font-size: 1.6rem; font-weight: 700; line-height: 1.1; }
    /* Texto menor explicativo abaixo do valor */
    .stat-subtext { font-size: 0.75rem; color: #888; margin-top: 4px; }
    /* Estilo e posicionamento do ícone correspondente ao cartão */
    .stat-icon {
        width: 44px; height: 44px;
        border-radius: 10px;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.2rem;
        flex-shrink: 0;
    }

    /* Regras de responsividade para telas menores (tablets) */
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
    /* Regras de responsividade para telas muito pequenas (smartphones) */
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
    <!-- Cabeçalho de Ações da Página -->
    <div class="page-header">
        <div class="page-header-left">
            <h2>Descartes & Perdas</h2>
            <p>Monitore produtos descartados, vencidos ou danificados e as perdas financeiras.</p>
        </div>
        <!-- Botão para abrir o formulário de cadastro de novo descarte -->
        <a href="novo.php" class="btn btn-primary">+ Novo Descarte</a>
    </div>

    <!-- Exibição de Mensagens de Alerta (Sucesso ou Erro) com base nos parâmetros da URL -->
    <?php if(isset($_GET['msg'])): ?>
        <!-- Alerta exibido quando um descarte é registrado com sucesso -->
        <?php if($_GET['msg'] === 'criado'): ?>
            <div class="alert alert-success">Descarte registrado com sucesso! O estoque foi atualizado.</div>
        <!-- Alerta exibido quando um descarte é estornado/excluído com sucesso -->
        <?php elseif($_GET['msg'] === 'estornado'): ?>
            <div class="alert alert-success">Descarte estornado com sucesso! Os produtos foram devolvidos ao estoque.</div>
        <?php endif; ?>
    <?php endif; ?>

    <?php if(isset($_GET['erro'])): ?>
        <!-- Alerta exibido caso o registro solicitado não seja localizado no banco de dados -->
        <?php if($_GET['erro'] === 'nao_encontrado'): ?>
            <div class="alert alert-danger">O registro de descarte solicitado não foi encontrado.</div>
        <!-- Alerta exibido quando ocorre erro na transação de estorno do descarte -->
        <?php elseif($_GET['erro'] === 'falha_estorno'): ?>
            <div class="alert alert-danger">Falha ao realizar estorno do descarte. Tente novamente. Details: <?= htmlspecialchars($_GET['detalhe'] ?? '') ?></div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- Grade de Resumo dos Principais Indicadores (KPIs) -->
    <div class="stats-grid">
        <!-- Cartão 1: Total Financeiro de Perdas -->
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

        <!-- Cartão 2: Contagem de Ocorrências de Descarte -->
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

        <!-- Cartão 3: Volume Físico Total de Itens Descartados -->
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

        <!-- Cartão 4: Motivo Principal das Ocorrências -->
        <div class="stat-card">
            <div>
                <div class="stat-label">Motivo Principal</div>
                <!-- Limita visualmente o tamanho do texto para não estourar o layout responsivo -->
                <div class="stat-value" style="font-size: 1.25rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 160px;"><?= htmlspecialchars($motivo_principal) ?></div>
                <div class="stat-subtext">Causa mais comum de perdas</div>
            </div>
            <div class="stat-icon" style="background: #fdf2f8; color: #db2777;">
                ⚠️
            </div>
        </div>
    </div>

    <!-- Painel de Filtros e Busca Textual -->
    <div class="filters-card">
        <form method="GET" action="index.php" class="filters-form">
            <!-- Input para busca por texto no nome do produto ou observações -->
            <div class="filter-group">
                <label for="q">Buscar</label>
                <input type="text" name="q" id="q" placeholder="Buscar por produto/obs..." value="<?= htmlspecialchars($search) ?>">
            </div>

            <!-- Seleção para filtrar por um produto específico -->
            <div class="filter-group">
                <label for="produto_id">Produto</label>
                <select name="produto_id" id="produto_id">
                    <option value="">Todos os produtos</option>
                    <?php foreach($todos_produtos as $tp): ?>
                        <!-- Verifica se o produto atual do laço é o mesmo selecionado no filtro para marcar como selecionado -->
                        <option value="<?= $tp['id'] ?>" <?= $filtro_produto == $tp['id'] ? 'selected' : '' ?>><?= htmlspecialchars($tp['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Seleção para filtrar por motivo do descarte -->
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

            <!-- Filtro de Data Inicial -->
            <div class="filter-group">
                <label for="data_inicio">De</label>
                <input type="date" name="data_inicio" id="data_inicio" value="<?= htmlspecialchars($filtro_inicio) ?>">
            </div>

            <!-- Filtro de Data Final -->
            <div class="filter-group">
                <label for="data_fim">Até</label>
                <input type="date" name="data_fim" id="data_fim" value="<?= htmlspecialchars($filtro_fim) ?>">
            </div>

            <!-- Botões para submeter os filtros aplicados ou limpar todos os parâmetros -->
            <div class="filter-actions-inline">
                <button type="submit" class="btn btn-primary">Filtrar</button>
                <a href="index.php" class="btn btn-secondary">Limpar</a>
            </div>
        </form>
    </div>

    <!-- Tabela contendo a listagem detalhada dos descartes ocorridos -->
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
                <!-- Se não houver nenhum registro retornado pela consulta filtrada -->
                <?php if(empty($descartes)): ?>
                    <tr>
                        <td colspan="9" style="text-align: center; padding: 48px; color: #888;">
                            Nenhum registro de descarte encontrado para os filtros selecionados.
                        </td>
                    </tr>
                <?php else: ?>
                    <!-- Percorre o array de descartes imprimindo as informações de cada registro em uma linha -->
                    <?php foreach($descartes as $d): ?>
                        <tr>
                            <!-- Formata a data do descarte para o padrão brasileiro (dia/mês/ano) -->
                            <td><strong><?= date('d/m/Y', strtotime($d['data_descarte'])) ?></strong></td>
                            
                            <!-- Nome do produto descartado com proteção contra XSS -->
                            <td><strong><?= htmlspecialchars($d['produto_nome']) ?></strong></td>
                            
                            <!-- Código do lote, se estiver associado a um lote -->
                            <td>
                                <?php if($d['lote_codigo']): ?>
                                    <span class="badge badge-normal" style="background: #e0f2fe; color: #0369a1;"><?= htmlspecialchars($d['lote_codigo']) ?></span>
                                <?php else: ?>
                                    <span style="color: #aaa;">—</span>
                                <?php endif; ?>
                            </td>
                            
                            <!-- Quantidade descartada formatada e unidade de medida correspondente -->
                            <td><?= number_format($d['quantidade'], 2, ',', '.') ?> &nbsp;<small style="color: #666;"><?= htmlspecialchars($d['produto_unidade']) ?></small></td>
                            
                            <!-- Motivo do descarte com destaque visual (badge) diferente conforme a gravidade -->
                            <td>
                                <?php 
                                    $m = $d['motivo'];
                                    $badgeClass = 'badge-normal';
                                    if ($m === 'Vencimento') $badgeClass = 'badge-critico';
                                    elseif ($m === 'Deterioração') $badgeClass = 'badge-atencao';
                                ?>
                                <span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($m) ?></span>
                            </td>
                            
                            <!-- Valor financeiro da perda correspondente ao produto/quantidade descartados -->
                            <td style="color: #dc2626; font-weight: 600;">R$ <?= number_format($d['valor_perdido'], 2, ',', '.') ?></td>
                            
                            <!-- Observação do descarte, limitada visualmente em caracteres com tooltip do texto completo -->
                            <td title="<?= htmlspecialchars($d['observacoes']) ?>">
                                <?= htmlspecialchars(mb_strimwidth($d['observacoes'], 0, 35, '...')) ?>
                            </td>
                            
                            <!-- Nome do usuário responsável pelo registro do descarte -->
                            <td><?= htmlspecialchars($d['usuario_nome'] ?? 'N/D') ?></td>
                            
                            <!-- Botão de Ação: Estornar descarte. Solicita confirmação explícita do operador antes de prosseguir -->
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

<?php 
// Inclui o arquivo de rodapé padrão da aplicação.
include '../_footer.php'; 
?>