<?php
// Inicia a sessão para permitir o controle de login e autenticação do painel administrativo
session_start();

// Verifica se o usuário não está logado no painel administrativo
if(!isset($_SESSION['logado']) || !$_SESSION['logado']){
    // Caso contrário, redireciona o usuário para o formulário de login na raiz do site
    header('Location: ../index.php');
    // Encerra imediatamente a execução deste script
    exit;
}

// Requer o arquivo de conexão PDO com o banco de dados
require_once '../conexao.php';

/**
 * Função Auxiliar para formatar uma string de CNPJ.
 *
 * @param string $cnpj A string bruta contendo o CNPJ
 * @return string O CNPJ formatado no padrão 00.000.000/0000-00 ou travessão caso vazio.
 */
function formatarCNPJ($cnpj) {
    // Remove qualquer caractere que não seja um número
    $cnpj = preg_replace('/\D/', '', $cnpj);
    // Verifica se a string resultante possui exatamente 14 dígitos
    if (strlen($cnpj) === 14) {
        // Aplica a expressão regular para formatar no padrão CNPJ brasileiro
        return preg_replace("/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/", "$1.$2.$3/$4-$5", $cnpj);
    }
    // Retorna o CNPJ bruto ou um travessão caso o campo seja nulo/vazio
    return $cnpj ?: '—';
}

// ── MANIPULAÇÃO DE FILTROS ──────────────────────────────────────────
// $where: Armazena as cláusulas WHERE que serão aplicadas de forma condicional na query.
// Iniciamos com "1=1" que sempre é verdadeiro, simplificando futuras concatenações com "AND"
$where = ["1=1"];

// $params: Dicionário de parâmetros de consulta que serão injetados de forma segura pelo PDO
$params = [];

// Input de busca de texto livre enviado via GET
$search = trim($_GET['q'] ?? '');
// Se houver texto digitado no campo de busca:
if ($search !== '') {
    // Adiciona filtros para corresponder ao nome, CNPJ, produtos fornecidos ou e-mail
    $where[] = "(f.nome LIKE :search OR f.cnpj LIKE :search OR f.produtos_fornecidos LIKE :search OR f.email LIKE :search)";
    // Associa o parâmetro ':search' com as porcentagens (%) de busca parcial
    $params[':search'] = '%' . $search . '%';
}

// Filtro pelo status operacional (Ativo / Inativo) enviado via GET
$filtro_status = trim($_GET['status'] ?? '');
// Se um status específico for escolhido no seletor:
if ($filtro_status !== '') {
    // Adiciona filtro para a coluna ativo da tabela de fornecedores
    $where[] = "f.ativo = :status";
    // Converte o status para inteiro (0 ou 1) e associa ao parâmetro ':status'
    $params[':status'] = intval($filtro_status);
}

// Une todas as condições construídas em uma única string utilizando o operador lógico AND
$where_clause = implode(" AND ", $where);

// ── CÁLCULOS DOS INDICADORES (KPI) ─────────────────────────────────────────
// Conta a quantidade total de fornecedores cadastrados na base de dados
$total_fornecedores = $conexao->query("SELECT COUNT(*) FROM fornecedores")->fetchColumn();
// Conta a quantidade de fornecedores operacionais ativos no sistema
$ativos             = $conexao->query("SELECT COUNT(*) FROM fornecedores WHERE ativo = 1")->fetchColumn();
// Conta a quantidade de fornecedores temporariamente inativos ou bloqueados
$inativos           = $conexao->query("SELECT COUNT(*) FROM fornecedores WHERE ativo = 0")->fetchColumn();
// Conta quantos lotes totais já foram recebidos e entregues por fornecedores mapeados
$lotes_entregues    = $conexao->query("SELECT COUNT(*) FROM lotes WHERE fornecedor_id IS NOT NULL")->fetchColumn();

// ── CONSULTA DA LISTAGEM DE FORNECEDORES ─────────────────────────────────────
// Constrói a SQL que recupera dados dos fornecedores e conta quantos lotes de mercadoria pertencem a cada um.
// O LEFT JOIN é usado para que fornecedores sem lotes entregues ainda apareçam na listagem.
$query = "
    SELECT f.*, COUNT(l.id) AS total_lotes
    FROM fornecedores f
    LEFT JOIN lotes l ON f.id = l.fornecedor_id
    WHERE $where_clause
    GROUP BY f.id
    ORDER BY f.nome ASC
";
// Prepara a consulta no banco de dados para evitar ataques de SQL Injection
$stmt = $conexao->prepare($query);
// Executa o comando passando os parâmetros dos filtros dinâmicos
$stmt->execute($params);
// Transforma os resultados em um array associativo de dados dos fornecedores
$fornecedores_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Configura variáveis de controle do layout do menu lateral e título do topo da página
$pagina_atual = 'fornecedores';
$titulo_pagina = 'Parceiros Fornecedores';

// Carrega o arquivo padrão de cabeçalho HTML e layout geral
include '../_header.php';
?>

<!-- Injeção de CSS específico para os componentes visuais de listagem e estatísticas dos fornecedores -->
<style>
    /* Cartão de filtros de busca e status */
    .filters-card {
        background: #fff;
        border: 1px solid #e8e8e8;
        border-radius: 12px;
        padding: 16px 20px;
        margin-bottom: 24px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.02);
    }
    /* Alinhamento e distribuição em grid das entradas dos filtros */
    .filters-form {
        display: grid;
        grid-template-columns: 2fr 1.5fr auto; /* 3 colunas de proporções diferentes */
        gap: 16px;
        align-items: flex-end;
    }
    /* Estilo dos blocos de campos de entrada e seletores */
    .filter-group {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }
    /* Estilo das etiquetas dos filtros */
    .filter-group label {
        font-size: 0.78rem;
        font-weight: 600;
        color: #666;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    /* Inputs e selects estilizados com fontes modernas */
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
    /* Efeito de foco alterando borda e fundo do input */
    .filter-group input:focus, .filter-group select:focus {
        border-color: #2db35d;
        background: #fff;
    }
    /* Flex container para agrupar botões de filtro e limpar lateralmente */
    .filter-actions-inline {
        display: flex;
        gap: 8px;
    }
    /* Define tamanho estrito para os botões do formulário */
    .filter-actions-inline .btn {
        height: 38px;
        padding: 0 16px;
    }

    /* Distribuição em grid dos KPIs no topo da tela */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr); /* 4 colunas de tamanho idêntico */
        gap: 16px;
        margin-bottom: 24px;
    }
    /* Design individual de cada indicador do painel */
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
    /* Texto menor identificando o KPI */
    .stat-label { font-size: 0.78rem; color: #666; margin-bottom: 6px; font-weight: 500; }
    /* Destaque para o valor quantitativo principal do KPI */
    .stat-value { font-size: 1.6rem; font-weight: 700; line-height: 1.1; }
    /* Legenda de apoio de tamanho reduzido */
    .stat-subtext { font-size: 0.75rem; color: #888; margin-top: 4px; }
    /* Ícone visual estilizado em box colorido */
    .stat-icon {
        width: 44px; height: 44px;
        border-radius: 10px;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.2rem;
        flex-shrink: 0;
    }

    /* Ajustes responsivos para telas médias (tablets) */
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
    /* Ajustes responsivos para telas muito pequenas (smartphones) */
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

<!-- Bloco contendo todo o conteúdo estruturado da página -->
<div class="content">
    <!-- Cabeçalho do Painel de Ações -->
    <div class="page-header">
        <div class="page-header-left">
            <h2>Gestão de Fornecedores</h2>
            <p>Cadastre parceiros, armazene dados de contato e acompanhe os lotes fornecidos.</p>
        </div>
        <!-- Link de atalho rápido para cadastrar um novo fornecedor comercial -->
        <a href="novo.php" class="btn btn-primary">+ Novo Fornecedor</a>
    </div>

    <!-- Bloco de Alertas e Mensagens de Feedback ao Usuário -->
    <?php if (isset($_GET['msg'])): ?>
        <!-- Se o parâmetro 'msg' da URL for correspondente a uma operação realizada com sucesso -->
        <?php if ($_GET['msg'] === 'criado'): ?>
            <div class="alert alert-success">Fornecedor cadastrado com sucesso!</div>
        <?php elseif ($_GET['msg'] === 'editado'): ?>
            <div class="alert alert-success">Fornecedor atualizado com sucesso!</div>
        <?php elseif ($_GET['msg'] === 'excluido'): ?>
            <div class="alert alert-success">Fornecedor excluído com sucesso!</div>
        <?php endif; ?>
    <?php endif; ?>

    <?php if (isset($_GET['erro'])): ?>
        <!-- Se houver algum erro registrado na URL, trata cada código individualmente -->
        <?php if ($_GET['erro'] === 'vinculado'): ?>
            <div class="alert alert-danger">Não é possível excluir este fornecedor: existem entradas ou lotes de mercadoria vinculados a ele.</div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- Seção de KPIs (Quadro Geral com Estatísticas) -->
    <div class="stats-grid">
        <!-- KPI: Total de fornecedores cadastrados -->
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

        <!-- KPI: Quantidade de fornecedores com status "Ativo" -->
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

        <!-- KPI: Quantidade de fornecedores com status "Inativo" -->
        <div class="stat-card">
            <div>
                <div class="stat-label">Inativos / Bloqueados</div>
                <!-- Altera a cor do valor numérico para vermelho caso haja algum inativo -->
                <div class="stat-value" style="color: <?= $inativos > 0 ? '#dc2626' : '#1a1a1a' ?>;"><?= number_format($inativos) ?></div>
                <div class="stat-subtext">Fora de operação</div>
            </div>
            <div class="stat-icon" style="background: #fee2e2; color: #dc2626;">
                🔴
            </div>
        </div>

        <!-- KPI: Quantidade de lotes recebidos associados a fornecedores -->
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

    <!-- Seção contendo o formulário de filtros -->
    <div class="filters-card">
        <form method="GET" action="index.php" class="filters-form">
            <!-- Campo de busca de texto livre -->
            <div class="filter-group">
                <label for="q">Buscar fornecedor</label>
                <input type="text" name="q" id="q" placeholder="Razão social, CNPJ ou produtos..." value="<?= htmlspecialchars($search) ?>">
            </div>

            <!-- Seleção para status do fornecedor -->
            <div class="filter-group">
                <label for="status">Situação</label>
                <select name="status" id="status">
                    <option value="">Todos os status</option>
                    <option value="1" <?= $filtro_status === '1' ? 'selected' : '' ?>>Ativos</option>
                    <option value="0" <?= $filtro_status === '0' ? 'selected' : '' ?>>Inativos</option>
                </select>
            </div>

            <!-- Botões de ação para filtrar ou resetar campos -->
            <div class="filter-actions-inline">
                <button type="submit" class="btn btn-primary">Filtrar</button>
                <a href="index.php" class="btn btn-secondary">Limpar</a>
            </div>
        </form>
    </div>

    <!-- Tabela principal com listagem dos dados dos fornecedores -->
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
                <!-- Verifica se o array retornado de fornecedores está vazio -->
                <?php if (empty($fornecedores_list)): ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 48px; color: #888;">
                            Nenhum fornecedor correspondente aos filtros foi encontrado.
                        </td>
                    </tr>
                <?php else: ?>
                    <!-- Itera sobre a listagem de fornecedores e imprime uma linha da tabela para cada registro -->
                    <?php foreach ($fornecedores_list as $f): ?>
                        <tr>
                            <td>
                                <!-- Nome fantasia / Razão social do fornecedor em negrito -->
                                <strong><?= htmlspecialchars($f['nome']) ?></strong>
                            </td>
                            <!-- Exibe o CNPJ formatado através da função formatarCNPJ declarada no topo -->
                            <td><?= htmlspecialchars(formatarCNPJ($f['cnpj'])) ?></td>
                            <!-- Exibe o telefone ou um travessão caso seja nulo/vazio -->
                            <td><?= htmlspecialchars($f['telefone'] ?: '—') ?></td>
                            <td>
                                <!-- Se houver e-mail comercial preenchido, gera um link 'mailto' estilizado -->
                                <?php if ($f['email']): ?>
                                    <a href="mailto:<?= htmlspecialchars($f['email']) ?>" style="color: #2db35d; text-decoration: none;"><?= htmlspecialchars($f['email']) ?></a>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <!-- Exibe a lista de produtos fornecidos truncada a no máximo 40 caracteres com reticências (...) para evitar quebras de layout -->
                            <td title="<?= htmlspecialchars($f['produtos_fornecidos'] ?? '') ?>">
                                <?= htmlspecialchars(mb_strimwidth($f['produtos_fornecidos'] ?? '', 0, 40, '...')) ?>
                            </td>
                            <td>
                                <!-- Exibe a contagem de lotes fornecidos dentro de um badge cinza sutil -->
                                <span class="badge" style="background: #f3f4f6; color: #374151; font-weight: 500;">
                                    <?= number_format($f['total_lotes']) ?>
                                </span>
                            </td>
                            <td>
                                <!-- Se estiver ativo, exibe badge padrão de sucesso verde, caso contrário, exibe badge vermelho de inativo -->
                                <?php if ($f['ativo']): ?>
                                    <span class="badge badge-normal">Ativo</span>
                                <?php else: ?>
                                    <span class="badge badge-critico" style="background: #f3f4f6; color: #7f1d1d;">Inativo</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: center;">
                                <div style="display: inline-flex; gap: 8px;">
                                    <!-- Botão para encaminhar à página de edição do fornecedor corrente -->
                                    <a href="editar.php?id=<?= $f['id'] ?>" class="btn btn-secondary btn-sm" style="padding: 6px 10px;">
                                        Editar
                                    </a>
                                    <!-- Botão para encaminhar à rotina de exclusão com prompt de confirmação do JavaScript -->
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

<!-- Inclui as tags de rodapé padrão da aplicação -->
<?php include '../_footer.php'; ?>