<?php
// Inicia a sessão PHP para verificação e controle de acesso do usuário à aplicação
session_start();

// Verifica se a variável de sessão 'logado' não está definida ou possui valor falso.
// Se não estiver logado, redireciona o usuário para a tela inicial/login do diretório pai.
if(!isset($_SESSION['logado']) || !$_SESSION['logado']){
    header('Location: ../index.php'); // Redirecionamento de segurança
    exit; // Encerra a execução do arquivo
}

// Requer a inclusão do arquivo de conexão com o banco de dados (inicializa a conexão PDO em $conexao)
require_once '../conexao.php';

// Mecanismo de auto-recuperação (Self-healing) do banco de dados:
// Realiza um teste para verificar se a coluna 'codigo_barras' está disponível na tabela 'produtos'.
try {
    $conexao->query("SELECT codigo_barras FROM produtos LIMIT 1");
} catch (Exception $e) {
    // Caso ocorra uma exceção (a coluna não existe), executa o comando ALTER TABLE para adicionar a coluna na tabela.
    try {
        $conexao->query("ALTER TABLE produtos ADD COLUMN codigo_barras VARCHAR(50) NULL UNIQUE");
    } catch (Exception $ex) {
        // Silencia qualquer falha adicional no banco de dados para evitar travar a renderização da tela
    }
}

// ── TRATAMENTO DE FILTROS DA CONSULTA ──────────────────────────
// Inicializa o array de condições SQL com '1=1' para facilitar a concatenação de novas restrições usando 'AND'
$where = ["1=1"];
// Inicializa o array de parâmetros que serão vinculados (bind) na consulta preparada
$params = [];

// Recupera o termo de busca textual (nome ou código de barras) a partir do parâmetro GET 'q' da URL
$search = trim($_GET['q'] ?? '');
// Se houver texto digitado na pesquisa:
if ($search !== '') {
    // Adiciona a cláusula LIKE na consulta para procurar no nome ou no código de barras
    $where[] = "(p.nome LIKE :search OR p.codigo_barras LIKE :search)";
    // Vincula o termo com os caracteres coringa '%' para buscar qualquer correspondência parcial
    $params[':search'] = '%' . $search . '%';
}

// Recupera o filtro de categoria a partir do parâmetro GET 'categoria_id'
$filtro_categoria = intval($_GET['categoria_id'] ?? 0);
// Se for fornecido um ID de categoria válido (maior que 0):
if ($filtro_categoria > 0) {
    // Adiciona o filtro pelo id da categoria na lista de condições SQL
    $where[] = "p.categoria_id = :categoria_id";
    // Vincula o ID da categoria aos parâmetros
    $params[':categoria_id'] = $filtro_categoria;
}

// Recupera o status ou situação do estoque a partir do parâmetro GET 'status'
$filtro_status = trim($_GET['status'] ?? '');
// Se um status de estoque específico for selecionado para filtragem:
if ($filtro_status !== '') {
    // Se o filtro for 'Zerado', a condição SQL busca produtos com estoque atual igual a zero
    if ($filtro_status === 'Zerado') {
        $where[] = "p.estoque_atual = 0";
    } else {
        // Caso contrário, filtra pelo campo de status salvo no banco de dados
        $where[] = "p.status = :status";
        // Vincula a string de status correspondente
        $params[':status'] = $filtro_status;
    }
}

// Junta todas as condições do array $where utilizando o operador lógico 'AND'
$where_clause = implode(" AND ", $where);

// ── CÁLCULO DOS INDICADORES (KPIs) GLOBAIS ───────────────────
// Consulta a contagem total de produtos cadastrados na tabela 'produtos'
$total_produtos = $conexao->query("SELECT COUNT(*) FROM produtos")->fetchColumn();

// Consulta a quantidade de produtos que estão com o status definido como 'Crítico' ou 'Baixo'
$criticos_baixo = $conexao->query("SELECT COUNT(*) FROM produtos WHERE status IN ('Crítico', 'Baixo')")->fetchColumn();

// Consulta o número de produtos cujo estoque atual é exatamente igual a 0 (zerado)
$zerados        = $conexao->query("SELECT COUNT(*) FROM produtos WHERE estoque_atual = 0")->fetchColumn();

// Calcula o valor estimado total em estoque somando a multiplicação do estoque_atual pelo custo_unitario.
// Utiliza a função SQL COALESCE para retornar 0 caso o valor da soma seja nulo.
$total_valor    = $conexao->query("SELECT COALESCE(SUM(estoque_atual * custo_unitario), 0) FROM produtos")->fetchColumn();

// ── CONSULTA DA LISTA DE PRODUTOS FILTRADA ──────────────────────
// Constrói a query SQL selecionando todos os dados do produto, juntando as informações da categoria correspondente (nome e cor)
$query = "
    SELECT p.*, c.nome AS categoria_nome, c.cor AS categoria_cor
    FROM produtos p
    LEFT JOIN categorias c ON p.categoria_id = c.id
    WHERE $where_clause
    ORDER BY p.nome ASC
";
// Prepara a query no banco de dados para evitar injeções de SQL
$stmt = $conexao->prepare($query);
// Executa a consulta vinculando os parâmetros obtidos nos filtros
$stmt->execute($params);
// Recupera a lista resultante como um array associativo
$produtos_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Busca todas as categorias disponíveis no banco para preencher o campo de seleção (dropdown) de filtros
$todas_categorias = $conexao->query("SELECT id, nome FROM categorias ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);

// Define as variáveis de identificação do menu e o título que serão utilizados no cabeçalho comum
$pagina_atual = 'produtos';
$titulo_pagina = 'Catálogo de Produtos';

// Inclui o cabeçalho padrão da aplicação
include '../_header.php';
?>

<!-- Estilos CSS específicos da página de catálogo de produtos -->
<style>
    /* Estilo do container que envolve o formulário de filtros */
    .filters-card {
        background: #fff;
        border: 1px solid #e8e8e8;
        border-radius: 12px;
        padding: 16px 20px;
        margin-bottom: 24px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.02);
    }
    /* Organização dos elementos do formulário em uma grid moderna */
    .filters-form {
        display: grid;
        grid-template-columns: 2fr 1.5fr 1.5fr auto;
        gap: 16px;
        align-items: flex-end;
    }
    /* Estilização individual de cada grupo de campo de filtro (rótulo + input) */
    .filter-group {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }
    /* Rótulo de texto descritivo dos filtros */
    .filter-group label {
        font-size: 0.78rem;
        font-weight: 600;
        color: #666;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    /* Campos de input de texto e select dentro dos filtros */
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
    /* Efeito de foco quando o usuário interage com o input ou select */
    .filter-group input:focus, .filter-group select:focus {
        border-color: #2db35d;
        background: #fff;
    }
    /* Container para alinhar os botões de filtrar e limpar */
    .filter-actions-inline {
        display: flex;
        gap: 8px;
    }
    /* Botões de filtro em linha com altura padrão fixa */
    .filter-actions-inline .btn {
        height: 38px;
        padding: 0 16px;
    }

    /* Grid responsiva contendo os cards de indicadores estatísticos (KPIs) */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 16px;
        margin-bottom: 24px;
    }
    /* Estilo visual individual do card de indicador */
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
    /* Rótulo superior do card de indicador */
    .stat-label { font-size: 0.78rem; color: #666; margin-bottom: 6px; font-weight: 500; }
    /* Valor numérico em destaque no card de indicador */
    .stat-value { font-size: 1.6rem; font-weight: 700; line-height: 1.1; }
    /* Texto de apoio inferior do card de indicador */
    .stat-subtext { font-size: 0.75rem; color: #888; margin-top: 4px; }
    /* Estilo do ícone representativo exibido no canto do card de indicador */
    .stat-icon {
        width: 44px; height: 44px;
        border-radius: 10px;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.2rem;
        flex-shrink: 0;
    }

    /* Elementos visuais para barra de progresso do nível do estoque */
    .progress-bar-wrap {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-top: 4px;
    }
    /* Fundo da barra de progresso */
    .progress-bar {
        height: 6px;
        background: #f0f0f0;
        border-radius: 4px;
        flex: 1;
        overflow: hidden;
    }
    /* Preenchimento ativo da barra de progresso */
    .progress-fill {
        height: 100%;
        border-radius: 4px;
        transition: width 0.4s;
    }
    /* Cores do preenchimento da barra dependendo do status */
    .fill-green { background: #2db35d; }
    .fill-yellow { background: #eab308; }
    .fill-red { background: #ef4444; }

    /* Media queries para responsividade em telas menores (tablets e notebooks) */
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
    /* Media queries para responsividade em celulares */
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

<!-- Container principal com o conteúdo da página -->
<div class="content">
    
    <!-- Cabeçalho da página de catálogo -->
    <div class="page-header">
        <div class="page-header-left">
            <h2>Catálogo de Produtos</h2>
            <p>Gerencie ingredientes, insumos e configure as regras de estoque mínimo.</p>
        </div>
        <!-- Botão para navegação até a página de criação de novos produtos -->
        <a href="nova.php" class="btn btn-primary">+ Novo Produto</a>
    </div>

    <!-- Alertas de Feedback: mensagens de sucesso com base em parâmetros GET -->
    <?php if (isset($_GET['msg'])): ?>
        <?php if ($_GET['msg'] === 'criado'): ?>
            <div class="alert alert-success">Produto cadastrado com sucesso!</div>
        <?php elseif ($_GET['msg'] === 'editado'): ?>
            <div class="alert alert-success">Produto atualizado com sucesso!</div>
        <?php elseif ($_GET['msg'] === 'excluido'): ?>
            <div class="alert alert-success">Produto excluído com sucesso!</div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- Alertas de Feedback: mensagens de erro com base em parâmetros GET -->
    <?php if (isset($_GET['erro'])): ?>
        <?php if ($_GET['erro'] === 'vinculado'): ?>
            <div class="alert alert-danger">Não é possível excluir este produto: existem lotes ou descartes ativos associados a ele.</div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- Seção de Cartões estatísticos (Dashboard rápido) -->
    <div class="stats-grid">
        <!-- Card: Total de Produtos -->
        <div class="stat-card">
            <div>
                <div class="stat-label">Total do Catálogo</div>
                <div class="stat-value"><?= number_format($total_produtos) ?></div>
                <div class="stat-subtext">Produtos cadastrados</div>
            </div>
            <div class="stat-icon" style="background: #f0fdf4; color: #16a34a;">
                📦
            </div>
        </div>

        <!-- Card: Estoque Crítico ou Baixo -->
        <div class="stat-card">
            <div>
                <div class="stat-label">Estoque Crítico / Baixo</div>
                <!-- Altera dinamicamente a cor do texto do indicador se a quantidade for maior que zero -->
                <div class="stat-value" style="color: <?= $criticos_baixo > 0 ? '#dc2626' : '#16a34a' ?>;"><?= number_format($criticos_baixo) ?></div>
                <div class="stat-subtext">Abaixo do limite mínimo</div>
            </div>
            <div class="stat-icon" style="background: #fee2e2; color: #dc2626;">
                ⚠️
            </div>
        </div>

        <!-- Card: Produtos Zerados (estoque completamente zerado) -->
        <div class="stat-card">
            <div>
                <div class="stat-label">Produtos Zerados</div>
                <!-- Altera a cor do texto para vermelho forte se existirem produtos com estoque zerado -->
                <div class="stat-value" style="color: <?= $zerados > 0 ? '#b91c1c' : '#1a1a1a' ?>;"><?= number_format($zerados) ?></div>
                <div class="stat-subtext font-weight-bold">Estoque completamente vazio</div>
            </div>
            <div class="stat-icon" style="background: #fff7ed; color: #ea580c;">
                ❌
            </div>
        </div>

        <!-- Card: Valor Total Estimado em Estoque -->
        <div class="stat-card">
            <div>
                <div class="stat-label">Valor Estimado</div>
                <div class="stat-value">R$ <?= number_format($total_valor, 2, ',', '.') ?></div>
                <div class="stat-subtext">Baseado no custo unitário</div>
            </div>
            <div class="stat-icon" style="background: #eff6ff; color: #2563eb;">
                R$
            </div>
        </div>
    </div>

    <!-- Formulário de Filtros para busca de produtos -->
    <div class="filters-card">
        <!-- Utiliza método GET para que os filtros permaneçam na URL facilitando o compartilhamento e navegação -->
        <form method="GET" action="index.php" class="filters-form">
            <!-- Campo de busca por Nome do produto -->
            <div class="filter-group">
                <label for="q">Buscar por nome</label>
                <input type="text" name="q" id="q" placeholder="Ex: Filé Mignon..." value="<?= htmlspecialchars($search) ?>">
            </div>

            <!-- Campo de seleção de Categoria -->
            <div class="filter-group">
                <label for="categoria_id">Categoria</label>
                <select name="categoria_id" id="categoria_id">
                    <option value="">Todas as categorias</option>
                    <!-- Percorre dinamicamente todas as categorias trazidas do banco de dados -->
                    <?php foreach($todas_categorias as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $filtro_categoria == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Campo de seleção de Situação/Alerta do estoque -->
            <div class="filter-group">
                <label for="status">Situação de Estoque</label>
                <select name="status" id="status">
                    <option value="">Todas as situações</option>
                    <option value="Normal" <?= $filtro_status === 'Normal' ? 'selected' : '' ?>>Normal</option>
                    <option value="Baixo" <?= $filtro_status === 'Baixo' ? 'selected' : '' ?>>Baixo</option>
                    <option value="Crítico" <?= $filtro_status === 'Crítico' ? 'selected' : '' ?>>Crítico</option>
                    <option value="Alto" <?= $filtro_status === 'Alto' ? 'selected' : '' ?>>Alto</option>
                    <option value="Zerado" <?= $filtro_status === 'Zerado' ? 'selected' : '' ?>>Zerado (Fora de estoque)</option>
                </select>
            </div>

            <!-- Botões de ação do filtro -->
            <div class="filter-actions-inline">
                <button type="submit" class="btn btn-primary">Filtrar</button>
                <a href="index.php" class="btn btn-secondary">Limpar</a>
            </div>
        </form>
    </div>

    <!-- Tabela para exibição dos produtos -->
    <div class="table-card">
        <table>
            <thead>
                <tr>
                    <th>Nome do Produto</th>
                    <th>Categoria</th>
                    <th>Estoque Atual</th>
                    <th>Mínimo / Máximo</th>
                    <th style="width: 150px;">Nível de Alerta</th>
                    <th>Custo Unitário</th>
                    <th>Valor em Estoque</th>
                    <th style="text-align: center;">Ações</th>
                </tr>
            </thead>
            <tbody>
                <!-- Se a lista de produtos retornada do banco estiver vazia, exibe linha informativa -->
                <?php if (empty($produtos_list)): ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 48px; color: #888;">
                            Nenhum produto cadastrado ou correspondente aos filtros aplicados.
                        </td>
                    </tr>
                <?php else: ?>
                    <!-- Percorre a lista de produtos encontrados na consulta -->
                    <?php foreach($produtos_list as $p): 
                        
                        // Lógica de cálculo da porcentagem da barra de progresso
                        $pct = 0;
                        // Se houver limite mínimo configurado e maior que zero
                        if (floatval($p['estoque_minimo']) > 0) {
                            // Calcula a razão entre o estoque atual e o mínimo, limitando em 100% no máximo
                            $pct = min(100, round((floatval($p['estoque_atual']) / floatval($p['estoque_minimo'])) * 100));
                        } elseif (floatval($p['estoque_atual']) > 0) {
                            // Se o estoque atual for positivo e não houver limite mínimo, exibe como 100% preenchido
                            $pct = 100;
                        }

                        // Lógica para definição das classes de estilo do badge de status de estoque
                        $badgeCls = 'badge-normal';
                        $fillCls = 'fill-green';
                        
                        // Se o estoque atual for igual a 0, define como Zerado (Crítico / Vermelho)
                        if (floatval($p['estoque_atual']) == 0) {
                            $badgeCls = 'badge-critico';
                            $fillCls = 'fill-red';
                            $statusText = 'Zerado';
                        } else {
                            $statusText = $p['status'];
                            // Trata os status com suas respectivas classes CSS de cor
                            if ($p['status'] === 'Crítico') {
                                $badgeCls = 'badge-critico';
                                $fillCls = 'fill-red';
                            } elseif ($p['status'] === 'Baixo') {
                                $badgeCls = 'badge-atencao';
                                $fillCls = 'fill-yellow';
                            } elseif ($p['status'] === 'Alto') {
                                $badgeCls = 'badge-normal';
                                $fillCls = 'fill-green';
                            }
                        }

                        // Calcula o valor total financeiro daquele produto em estoque (quantidade * custo)
                        $valor_total = floatval($p['estoque_atual']) * floatval($p['custo_unitario']);
                    ?>
                        <tr>
                            <!-- Nome do produto e código de barras associado -->
                            <td>
                                <strong><?= htmlspecialchars($p['nome']) ?></strong>
                                <?php if (!empty($p['codigo_barras'])): ?>
                                    <!-- Exibe o código EAN formatado abaixo do nome, caso exista -->
                                    <br><small style="color: #6b7280; font-size: 0.72rem;">EAN: <?= htmlspecialchars($p['codigo_barras']) ?></small>
                                <?php endif; ?>
                            </td>
                            <!-- Exibição da categoria com badge colorido dinamicamente se cadastrado -->
                            <td>
                                <?php if ($p['categoria_nome']): ?>
                                    <!-- Aplica transparência à cor da categoria para o fundo (background) e define bordas correspondentes -->
                                    <span class="badge" style="background: <?= htmlspecialchars($p['categoria_cor'] ?? '#e8e8e8') ?>15; color: <?= htmlspecialchars($p['categoria_cor'] ?? '#666') ?>; border: 1px solid <?= htmlspecialchars($p['categoria_cor'] ?? '#e8e8e8') ?>40;">
                                        <?= htmlspecialchars($p['categoria_nome']) ?>
                                    </span>
                                <?php else: ?>
                                    <span style="color: #aaa;">Sem categoria</span>
                                <?php endif; ?>
                            </td>
                            <!-- Quantidade em estoque atual formatada com a unidade de medida -->
                            <td>
                                <!-- Se o estoque atual for igual a 0, destaca o valor numérico em vermelho -->
                                <strong style="color: <?= floatval($p['estoque_atual']) == 0 ? '#b91c1c' : '#1a1a1a' ?>;">
                                    <?= number_format($p['estoque_atual'], 2, ',', '.') ?>
                                </strong>
                                <small style="color: #666; font-size: 0.8rem;"><?= htmlspecialchars($p['unidade']) ?></small>
                            </td>
                            <!-- Valores limites de estoque mínimo e máximo cadastrados -->
                            <td>
                                <span style="font-size: 0.85rem; color: #555;">
                                    Min: <?= number_format($p['estoque_minimo'], 2, ',', '.') ?><br>
                                    Max: <?= floatval($p['estoque_maximo']) > 0 ? number_format($p['estoque_maximo'], 2, ',', '.') : '—' ?>
                                </span>
                            </td>
                            <!-- Coluna com badge e barra de progresso visual de alerta de estoque -->
                            <td>
                                <span class="badge <?= $badgeCls ?>"><?= htmlspecialchars($statusText) ?></span>
                                <div class="progress-bar-wrap">
                                    <div class="progress-bar">
                                        <!-- O tamanho da div de preenchimento é controlado dinamicamente pela porcentagem calculada em PHP -->
                                        <div class="progress-fill <?= $fillCls ?>" style="width: <?= $pct ?>%;"></div>
                                    </div>
                                    <span style="font-size: 0.72rem; color: #888; min-width: 32px; text-align: right;"><?= $pct ?>%</span>
                                </div>
                            </td>
                            <!-- Custo unitário padrão formatado em moeda nacional -->
                            <td>R$ <?= number_format($p['custo_unitario'], 2, ',', '.') ?></td>
                            <!-- Valor total financeiro em estoque para este item específico -->
                            <td><strong>R$ <?= number_format($valor_total, 2, ',', '.') ?></strong></td>
                            <!-- Botões de ação para Edição e Exclusão -->
                            <td style="text-align: center;">
                                <div style="display: inline-flex; gap: 8px;">
                                    <!-- Link de atalho para página de edição passando o ID via GET -->
                                    <a href="editar.php?id=<?= $p['id'] ?>" class="btn btn-secondary btn-sm" style="padding: 6px 10px;">
                                        Editar
                                    </a>
                                    <!-- Link para exclusão contendo validação de segurança JS para confirmar exclusão antes do redirecionamento -->
                                    <a href="excluir.php?id=<?= $p['id'] ?>" class="btn btn-danger btn-sm" style="padding: 6px 10px;" onclick="return confirm('Deseja realmente excluir o produto <?= htmlspecialchars($p['nome']) ?>?')">
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

<?php 
// Inclui o rodapé padrão com finalização de tags HTML
include '../_footer.php'; 
?>