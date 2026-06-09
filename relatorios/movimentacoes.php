<?php
// Inicia a sessão PHP para verificação e controle de autenticação do operador na plataforma
session_start();

// Verifica se a variável de sessão 'logado' não está definida ou possui valor falso.
// Caso afirmativo, o acesso a esta página é bloqueado e o usuário é enviado para a tela de login na raiz.
if(!isset($_SESSION['logado']) || !$_SESSION['logado']){
    header('Location: ../index.php'); // Redireciona para o login
    exit; // Finaliza o script para segurança
}

// Requer o arquivo que fornece a conexão PDO com o banco de dados (variável $conexao)
require_once '../conexao.php';

// Validação e sanitização segura de datas fornecidas por parâmetros GET:
// Verifica se a data de início ('data_inicio') está definida e corresponde ao padrão AAAA-MM-DD usando expressão regular.
// Caso contrário, assume o valor padrão como o primeiro dia do mês atual (ex: '2026-06-01').
$data_inicio = isset($_GET['data_inicio']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['data_inicio']) ? $_GET['data_inicio'] : date('Y-m-01');

// Verifica se a data de fim ('data_fim') está definida e segue o padrão AAAA-MM-DD.
// Se não, assume a data atual do sistema (hoje).
$data_fim    = isset($_GET['data_fim']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['data_fim']) ? $_GET['data_fim'] : date('Y-m-d');

// Consulta preparada para obter dados de Entrada de mercadoria (Lotes):
// Prepara a instrução SQL associando lotes aos respectivos produtos e fornecedores.
// Filtra pela data de entrada contida no intervalo delimitado por :start_date e :end_date.
// Calcula o valor total multiplicando a quantidade recebida pelo preço de custo.
$stmtEntradas = $conexao->prepare("
    SELECT l.*, p.nome as produto_nome, p.unidade, f.nome as fornecedor_nome,
           (l.quantidade * l.preco_custo) as valor_total
    FROM lotes l
    INNER JOIN produtos p ON p.id = l.produto_id
    LEFT JOIN fornecedores f ON f.id = l.fornecedor_id
    WHERE l.data_entrada BETWEEN :start_date AND :end_date
    ORDER BY l.data_entrada DESC
");
// Executa a consulta de entradas passando as datas sanitizadas como parâmetros nomeados
$stmtEntradas->execute([':start_date' => $data_inicio, ':end_date' => $data_fim]);
// Recupera o resultado como um array contendo as entradas do período
$entradas = $stmtEntradas->fetchAll(PDO::FETCH_ASSOC);

// Consulta preparada para obter dados de Saídas/Perdas (Descartes):
// Prepara a instrução SQL relacionando descartes aos produtos e aos usuários (operadores) que os registraram.
// Filtra pelo intervalo de datas do descarte.
$stmtSaidas = $conexao->prepare("
    SELECT d.*, p.nome as produto_nome, p.unidade, u.nome as usuario_nome
    FROM descartes d
    INNER JOIN produtos p ON p.id = d.produto_id
    LEFT JOIN usuarios u ON u.id = d.usuario_id
    WHERE d.data_descarte BETWEEN :start_date AND :end_date
    ORDER BY d.data_descarte DESC
");
// Executa a consulta de descartes associando os valores das datas informadas
$stmtSaidas->execute([':start_date' => $data_inicio, ':end_date' => $data_fim]);
// Recupera o resultado em formato de array associativo contendo as saídas
$saidas = $stmtSaidas->fetchAll(PDO::FETCH_ASSOC);

// Cálculos financeiros consolidados de maneira segura usando funções nativas do PHP:
// Soma todos os valores da coluna 'valor_total' presentes no array de entradas.
$total_entradas = array_sum(array_column($entradas, 'valor_total'));
// Soma todos os valores de prejuízo da coluna 'valor_perdido' presentes no array de descartes.
$total_perdas   = array_sum(array_column($saidas,   'valor_perdido'));

// ── AGREGAÇÃO DIÁRIA DOS DADOS PARA O GRÁFICO (Chart.js) ──
// Inicializa o array para armazenar a soma dos valores de entrada por dia
$entradas_diarias = [];
// Itera por cada entrada para agrupar e somar os valores com base na data do evento
foreach ($entradas as $e) {
    $dia = $e['data_entrada']; // Chave com a data da entrada
    if (!isset($entradas_diarias[$dia])) {
        $entradas_diarias[$dia] = 0; // Inicializa o dia com zero caso não exista
    }
    // Soma o valor total correspondente ao dia
    $entradas_diarias[$dia] += floatval($e['valor_total']);
}

// Inicializa o array para acumular os valores de descarte diários
$saidas_diarias = [];
// Percorre cada registro de descarte para agrupar e somar os prejuízos por dia
foreach ($saidas as $s) {
    $dia = $s['data_descarte']; // Chave com a data do descarte
    if (!isset($saidas_diarias[$dia])) {
        $saidas_diarias[$dia] = 0; // Inicializa o dia com zero
    }
    // Soma o prejuízo correspondente ao dia no acumulador
    $saidas_diarias[$dia] += floatval($s['valor_perdido']);
}

// Mescla as chaves (datas) de entradas e saídas, remove duplicidades e as ordena cronologicamente
$todas_datas = array_unique(array_merge(array_keys($entradas_diarias), array_keys($saidas_diarias)));
sort($todas_datas); // Ordena as datas de forma crescente

// Inicializa os arrays que serão convertidos para preencher o gráfico
$labels_chart = [];
$valores_entradas_chart = [];
$valores_saidas_chart = [];

// Popula as variáveis de plotagem do gráfico iterando pelo conjunto de datas ordenadas
foreach ($todas_datas as $dt) {
    // Formata a data para formato amigável (Dia/Mês)
    $labels_chart[] = date('d/m', strtotime($dt));
    // Associa o valor financeiro do dia para as entradas (ou zero se não houve movimentação)
    $valores_entradas_chart[] = $entradas_diarias[$dt] ?? 0;
    // Associa o prejuízo do dia para os descartes (ou zero se não houve descarte)
    $valores_saidas_chart[] = $saidas_diarias[$dt] ?? 0;
}

// Codifica os arrays PHP para o formato JSON para que possam ser consumidos diretamente pelo Chart.js no JavaScript
$labels_json = json_encode($labels_chart);
$entradas_json = json_encode($valores_entradas_chart);
$saidas_json = json_encode($valores_saidas_chart);

// Configuração das propriedades de template da página para o header
$pagina_atual = 'relatorios';
$titulo_pagina = 'Relatório de Movimentações';

// Inclui o cabeçalho padrão da aplicação
include '../_header.php';
?>

<!-- Estilos CSS específicos para esta página de relatório -->
<style>
    /* Estilo do bloco que engloba os campos do filtro de datas */
    .filters-card {
        background: #fff;
        border: 1px solid #e8e8e8;
        border-radius: 12px;
        padding: 16px 20px;
        margin-bottom: 24px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.02);
    }
    /* Alinhamento dos campos de entrada de filtros na mesma linha */
    .filters-form {
        display: flex;
        gap: 16px;
        align-items: flex-end;
        flex-wrap: wrap;
    }
    /* Estilo individual de rotulagem e posicionamento de input */
    .filter-group {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }
    /* Formatação do label em letras maiúsculas e tamanho reduzido */
    .filter-group label {
        font-size: 0.78rem;
        font-weight: 600;
        color: #666;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    /* Formatação dos inputs de tipo data */
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
    /* Efeito visual de foco no input de datas */
    .filter-group input:focus {
        border-color: #2db35d;
        background: #fff;
    }

    /* Grid que distribui os blocos de resumo de forma igualitária na tela */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 16px;
        margin-bottom: 24px;
    }
    /* Estilização individual do card de estatística */
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
    /* Rótulo superior do card descritivo */
    .stat-label { font-size: 0.78rem; color: #666; margin-bottom: 6px; font-weight: 500; }
    /* Exibição do valor em destaque */
    .stat-value { font-size: 1.5rem; font-weight: 700; line-height: 1.1; }
    /* Estilo do container do ícone representativo no cartão estatístico */
    .stat-icon {
        width: 44px; height: 44px;
        border-radius: 10px;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.2rem;
        flex-shrink: 0;
    }
    /* Div do cabeçalho de tabelas com dados auxiliares na direita */
    .table-card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 16px;
    }
    /* Estilização para o título de identificação das tabelas */
    .table-title {
        font-size: 1rem;
        font-weight: 600;
        color: #1a1a1a;
    }

    /* Regras de responsividade para telas móveis e tablets */
    @media(max-width: 768px) {
        .stats-grid {
            grid-template-columns: 1fr; /* Transforma em coluna única */
        }
        .filters-form {
            flex-direction: column;
            align-items: stretch;
        }
    }

    /* Regras de formatação especiais voltadas para a impressão em papel (Ctrl+P) */
    @media print {
        body { background: #fff; color: #000; }
        /* Oculta menus, cabeçalhos do site, botões, formulário de filtro e o gráfico na versão impressa */
        .header, nav, .filters-card, .btn, .page-header a, .chart-container-card { display: none !important; }
        .content { padding: 0; }
        .table-card { border: none; box-shadow: none; padding: 0; }
        th, td { border-bottom: 1px solid #ddd; }
    }
</style>

<!-- Importa a biblioteca externa Chart.js via CDN para renderização do gráfico de tendências -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<!-- Container principal com os elementos da página -->
<div class="content">
    
    <!-- Cabeçalho contendo o título do relatório e o intervalo de datas selecionado -->
    <div class="page-header">
        <div class="page-header-left">
            <h2>Relatório de Movimentações</h2>
            <p>Monitore o fluxo de entrada e descarte (saída) de mercadoria entre <strong><?= date('d/m/Y', strtotime($data_inicio)) ?></strong> e <strong><?= date('d/m/Y', strtotime($data_fim)) ?></strong>.</p>
        </div>
        <!-- Botões de ação rápida -->
        <div style="display: flex; gap: 8px;">
            <!-- Aciona a funcionalidade nativa do navegador para imprimir ou salvar em PDF -->
            <button onclick="window.print()" class="btn btn-secondary">🖨 Imprimir Relatório</button>
            <a href="index.php" class="btn btn-secondary">← Voltar</a>
        </div>
    </div>

    <!-- Seção de formulário de filtros para seleção e alteração do período de busca -->
    <div class="filters-card">
        <form method="GET" action="movimentacoes.php" class="filters-form">
            <!-- Campo de seleção de data inicial -->
            <div class="filter-group">
                <label for="data_inicio">Data de Início</label>
                <input type="date" name="data_inicio" id="data_inicio" value="<?= htmlspecialchars($data_inicio) ?>" required>
            </div>
            <!-- Campo de seleção de data final -->
            <div class="filter-group">
                <label for="data_fim">Data de Fim</label>
                <input type="date" name="data_fim" id="data_fim" value="<?= htmlspecialchars($data_fim) ?>" required>
            </div>
            <!-- Botão de submissão do filtro de datas -->
            <button type="submit" class="btn btn-primary" style="height: 38px;">Filtrar Período</button>
        </form>
    </div>

    <!-- Grid de métricas estatísticas rápidas do período filtrado -->
    <div class="stats-grid">
        <!-- Quantidade de lotes recebidos no período -->
        <div class="stat-card">
            <div>
                <div class="stat-label">Lotes Recebidos</div>
                <div class="stat-value"><?= count($entradas) ?> registros</div>
            </div>
            <div class="stat-icon" style="background: #e0f2fe; color: #0369a1;">📦</div>
        </div>
        <!-- Custo total consolidado das entradas no período -->
        <div class="stat-card">
            <div>
                <div class="stat-label">Custo Total das Entradas</div>
                <div class="stat-value" style="color: #2db35d;">R$ <?= number_format($total_entradas, 2, ',', '.') ?></div>
            </div>
            <div class="stat-icon" style="background: #eafaf1; color: #2db35d;">💰</div>
        </div>
        <!-- Custo total consolidado das perdas/descartes no período -->
        <div class="stat-card">
            <div>
                <div class="stat-label">Valor Total das Perdas</div>
                <div class="stat-value" style="color: #dc2626;">R$ <?= number_format($total_perdas, 2, ',', '.') ?></div>
            </div>
            <div class="stat-icon" style="background: #fef2f2; color: #dc2626;">🗑️</div>
        </div>
    </div>

    <!-- Bloco contendo o gráfico de linhas de tendência (será ocultado na impressão) -->
    <div class="filters-card chart-container-card" style="margin-bottom: 24px;">
        <div class="table-card-header" style="margin-bottom: 12px;">
            <span class="table-title">Tendência Diária de Fluxo Financeiro (R$)</span>
            <span style="font-size: 0.85rem; color: #888;">Análise diária de Entradas vs Perdas no período</span>
        </div>
        <div style="width: 100%; height: 260px; position: relative;">
            <!-- Elemento Canvas onde o Chart.js desenhará o gráfico -->
            <canvas id="chartMovimentos"></canvas>
        </div>
    </div>

    <!-- Tabela contendo as entradas de mercadorias no período -->
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
                <!-- Se o array de entradas estiver vazio no período informado, exibe linha informativa -->
                <?php if(empty($entradas)): ?>
                    <tr><td colspan="7" style="text-align: center; padding: 24px; color: #aaa;">Nenhuma entrada registrada neste período.</td></tr>
                <?php else: ?>
                    <!-- Percorre o array e exibe os dados formatados de cada entrada -->
                    <?php foreach($entradas as $e): ?>
                        <tr>
                            <td><?= date('d/m/Y', strtotime($e['data_entrada'])) ?></td>
                            <td><strong><?= htmlspecialchars($e['produto_nome']) ?></strong></td>
                            <td><?= htmlspecialchars($e['fornecedor_nome'] ?? '—') ?></td>
                            <td>
                                <!-- Se houver código físico do lote cadastrado, exibe um badge colorido; senão, o ID interno -->
                                <?php if ($e['codigo_lote']): ?>
                                    <span class="badge" style="background: #e0f2fe; color: #0369a1;"><?= htmlspecialchars($e['codigo_lote']) ?></span>
                                <?php else: ?>
                                    <span style="color: #aaa;">#<?= $e['id'] ?></span>
                                <?php endif; ?>
                            </td>
                            <!-- Quantidade formatada juntamente com a respectiva unidade de medida -->
                            <td><?= number_format($e['quantidade'], 2, ',', '.') ?> &nbsp;<small style="color: #666;"><?= htmlspecialchars($e['unidade']) ?></small></td>
                            <td>R$ <?= number_format($e['preco_custo'], 2, ',', '.') ?></td>
                            <!-- Exibe o custo total em destaque na cor verde -->
                            <td style="font-weight: 600; color: #16a34a;">R$ <?= number_format($e['valor_total'], 2, ',', '.') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Tabela contendo as perdas e descartes no período -->
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
                <!-- Se o array de descartes estiver vazio no período, exibe linha informativa -->
                <?php if(empty($saidas)): ?>
                    <tr><td colspan="6" style="text-align: center; padding: 24px; color: #aaa;">Nenhuma perda ou descarte registrado neste período.</td></tr>
                <?php else: ?>
                    <!-- Itera por todos os descartes e plota na tabela -->
                    <?php foreach($saidas as $s): ?>
                        <tr>
                            <td><?= date('d/m/Y', strtotime($s['data_descarte'])) ?></td>
                            <td><strong><?= htmlspecialchars($s['produto_nome']) ?></strong></td>
                            <td>
                                <!-- Define badge estilizado dependendo do motivo da perda do insumo -->
                                <?php 
                                    $m = $s['motivo'];
                                    $badgeClass = 'badge-normal';
                                    if ($m === 'Vencimento') $badgeClass = 'badge-critico';
                                    elseif ($m === 'Deterioração') $badgeClass = 'badge-atencao';
                                ?>
                                <span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($m) ?></span>
                            </td>
                            <td><?= number_format($s['quantidade'], 2, ',', '.') ?> &nbsp;<small style="color: #666;"><?= htmlspecialchars($s['unidade']) ?></small></td>
                            <!-- Exibe valor perdido formatado com destaque em cor vermelha -->
                            <td style="color: #dc2626; font-weight: 600;">R$ <?= number_format($s['valor_perdido'], 2, ',', '.') ?></td>
                            <!-- Exibe o nome do usuário que registrou a movimentação -->
                            <td><?= htmlspecialchars($s['usuario_nome'] ?? '—') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
// ── Configuração e renderização do gráfico linear usando Chart.js ──
document.addEventListener('DOMContentLoaded', () => {
    // Obtém a referência de contexto bidimensional para desenho do canvas
    const ctx = document.getElementById('chartMovimentos').getContext('2d');
    
    // Alimenta as labels (eixo X) e dados dos datasets a partir dos JSONs codificados pelo PHP
    const labels = <?= $labels_json ?>;
    const dataEntradas = <?= $entradas_json ?>;
    const dataSaidas = <?= $saidas_json ?>;

    // Inicialização da instância do gráfico Chart
    new Chart(ctx, {
        type: 'line', // Tipo do gráfico: gráfico de linha
        data: {
            // Se não houver datas no período, exibe uma label indicando ausência de movimentos
            labels: labels.length ? labels : ['Sem movimentos'],
            datasets: [
                {
                    label: 'Valor das Entradas',
                    data: dataEntradas,
                    borderColor: '#2db35d', // Linha verde para as entradas
                    backgroundColor: 'rgba(45, 179, 93, 0.05)', // Fundo verde translúcido
                    borderWidth: 3,
                    fill: true,
                    tension: 0.3, // Curvatura da linha (suavidade)
                    pointRadius: 4,
                    pointHoverRadius: 6
                },
                {
                    label: 'Valor das Perdas (Descartes)',
                    data: dataSaidas,
                    borderColor: '#ef4444', // Linha vermelha para descartes
                    backgroundColor: 'rgba(239, 68, 68, 0.05)', // Fundo vermelho translúcido
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
            maintainAspectRatio: false, // Permite que a altura seja controlada pela div externa
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
                        // Customização do formato do preço exibido no tooltip
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
                        // Formata os valores numéricos do eixo Y como moeda (R$)
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

<?php 
// Inclui o arquivo de rodapé padrão da aplicação para fechar as tags comuns
include '../_footer.php'; 
?>