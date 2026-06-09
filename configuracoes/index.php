<?php
// Inicia ou retoma a sessão ativa do PHP para permitir a verificação das credenciais de login
session_start();

// Verifica se o usuário não está autenticado no sistema
if(!isset($_SESSION['logado']) || !$_SESSION['logado']){
    // Redireciona o usuário não autenticado para a página inicial de login (index.php) localizada uma pasta acima
    header('Location: ../index.php');
    
    // Interrompe imediatamente a execução do script para impedir que dados confidenciais sejam mostrados
    exit;
}

// Requer de forma única a inclusão do arquivo de conexão com o banco de dados
require_once '../conexao.php';

/**
 * Função auxiliar para salvar ou atualizar com segurança um registro de configuração na tabela.
 * Utiliza o recurso ON DUPLICATE KEY UPDATE para fazer o "upsert" (insere se não existir, atualiza se já existir).
 * 
 * @param PDO $conexao - Instância da conexão ativa com o banco de dados.
 * @param string $chave - A chave de configuração identificadora única (ex: 'nome_restaurante').
 * @param string $valor - O valor associado que se deseja gravar.
 */
function salvarConfig($conexao, $chave, $valor) {
    // Prepara a instrução SQL de inserção com a cláusula de atualização em caso de duplicidade de chave primária/única
    $stmt = $conexao->prepare("
        INSERT INTO configuracoes (chave, valor) 
        VALUES (:chave, :valor) 
        ON DUPLICATE KEY UPDATE valor = :valor_update
    ");
    
    // Executa a query vinculando os parâmetros adequados de forma segura
    $stmt->execute([
        ':chave'        => $chave,
        ':valor'        => $valor,
        ':valor_update' => $valor
    ]);
}

// Inicializa a variável de mensagem vazia para controlar os alertas de feedback
$msg = '';

// Verifica se a requisição atual é do tipo POST (indica envio do formulário de configurações)
if($_SERVER['REQUEST_METHOD'] === 'POST'){
    // Recebe e higieniza o nome do restaurante enviado pelo formulário. Define um valor padrão se não informado
    $nome_restaurante   = trim($_POST['nome_restaurante'] ?? 'Restaurante Premium');
    
    // Recebe e converte para número inteiro o valor de dias de antecedência para alertas de vencimento (padrão: 3 dias)
    $alerta_dias_padrao = intval($_POST['alerta_dias_padrao'] ?? 3);

    // Salva a configuração correspondente ao nome do restaurante no banco de dados
    salvarConfig($conexao, 'nome_restaurante', $nome_restaurante);
    
    // Salva a configuração de dias padrão de alerta no banco, convertendo o inteiro para string
    salvarConfig($conexao, 'alerta_dias_padrao', strval($alerta_dias_padrao));

    // Define a variável de mensagem de sucesso para exibir o alerta ao usuário
    $msg = 'salvo';
}

// Executa uma consulta direta na tabela 'configuracoes' buscando todas as chaves e valores
// PDO::FETCH_KEY_PAIR recupera o resultado como um array associativo unidimensional mapeando [chave => valor]
$configs = $conexao->query("SELECT chave, valor FROM configuracoes")->fetchAll(PDO::FETCH_KEY_PAIR);

// Define as variáveis de contexto para que o cabeçalho (_header.php) destaque a aba correta e defina o título da página
$pagina_atual = 'configuracoes';
$titulo_pagina = 'Configurações do Sistema';

// Inclui o arquivo de cabeçalho padrão do painel administrativo
include '../_header.php';
?>

<!-- Bloco de estilo CSS exclusivo da página de configurações -->
<style>
    /* Grid de layout dividido em duas colunas (coluna maior para formulário e menor para tabelas e links) */
    .grid-2 {
        display: grid;
        grid-template-columns: 1.2fr 1fr;
        gap: 24px;
        align-items: start;
    }
    
    /* Adiciona margem inferior nos cards de configuração */
    .config-card {
        margin-bottom: 24px;
    }
    
    /* Título das seções internas dos cards com borda inferior discreta */
    .section-title {
        font-size: 1rem;
        font-weight: 600;
        margin-bottom: 20px;
        padding-bottom: 12px;
        border-bottom: 1px solid #f0f0f0;
        color: #1a1a1a;
    }
    
    /* Organização em pilha (coluna) dos botões administrativos auxiliares */
    .admin-links {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }
    
    /* Alinha o conteúdo dos botões secundários à esquerda */
    .admin-links .btn {
        justify-content: flex-start;
        text-align: left;
    }
    
    /* Tabela interna estilizada para exibição de informações técnicas */
    .info-table {
        width: 100%;
        border-collapse: collapse;
    }
    
    /* Adiciona preenchimento, borda fina e fonte média nas células de informação */
    .info-table td {
        padding: 12px 0;
        font-size: 0.875rem;
        border-bottom: 1px solid #f7f7f7;
    }
    
    /* Estilo da primeira coluna contendo os nomes das propriedades (texto cinza e peso médio) */
    .info-table td:first-child {
        color: #666;
        font-weight: 500;
    }
    
    /* Estilo da segunda coluna contendo os valores das propriedades (texto escuro alinhado à direita) */
    .info-table td:last-child {
        font-weight: 600;
        text-align: right;
        color: #1a1a1a;
    }
    
    /* Remove a borda inferior das células da última linha da tabela */
    .info-table tr:last-child td {
        border-bottom: none;
    }

    /* Regras de responsividade para telas de tablets ou celulares (com largura máxima de 900px) */
    @media(max-width: 900px) {
        /* Altera o grid de duas colunas para empilhamento em apenas uma coluna */
        .grid-2 {
            grid-template-columns: 1fr;
        }
    }
</style>

<!-- Container principal de conteúdo da página -->
<div class="content">
    
    <!-- Cabeçalho descritivo da página atual -->
    <div class="page-header">
        <div class="page-header-left">
            <h2>Configurações Gerais</h2>
            <p>Ajuste os parâmetros globais de funcionamento do sistema e gerencie cadastros auxiliares.</p>
        </div>
    </div>

    <!-- Bloco condicional que exibe uma mensagem de sucesso verde caso os dados tenham sido gravados com êxito -->
    <?php if($msg === 'salvo'): ?>
        <div class="alert alert-success">✅ Configurações salvas com sucesso!</div>
    <?php endif; ?>

    <!-- Início do grid de layout -->
    <div class="grid-2">
        
        <!-- Formulário de Ajustes Gerais do Sistema -->
        <div class="form-card config-card">
            <div class="section-title">⚙️ Configurações Gerais</div>
            <!-- Formulário que submete as alterações via POST para si mesmo (index.php) -->
            <form method="POST" action="index.php">
                <!-- Campo de edição do Nome do Estabelecimento -->
                <div class="form-group" style="margin-bottom: 20px;">
                    <label for="nome_restaurante">Nome do Restaurante</label>
                    <input type="text" name="nome_restaurante" id="nome_restaurante" placeholder="Ex: ChefSupply Bistro" value="<?= htmlspecialchars($configs['nome_restaurante'] ?? 'Restaurante Premium') ?>" required>
                </div>
                
                <!-- Campo de edição da quantidade de dias padrão para alertas de validades -->
                <div class="form-group" style="margin-bottom: 24px;">
                    <label for="alerta_dias_padrao">Dias de Alerta de Vencimento (Padrão)</label>
                    <input type="number" name="alerta_dias_padrao" id="alerta_dias_padrao" min="1" max="60" value="<?= htmlspecialchars($configs['alerta_dias_padrao'] ?? '3') ?>" required>
                    <small style="color: #666; font-size: 0.8rem; margin-top: 4px;">Dias de antecedência recomendados para avisar sobre vencimento de lotes no estoque.</small>
                </div>
                
                <!-- Botão de submissão do formulário de atualizações -->
                <button type="submit" class="btn btn-primary">Salvar Configurações</button>
            </form>
        </div>

        <!-- Coluna da Direita: Atalhos Auxiliares e Dados Técnicos -->
        <div>
            <!-- Links Rápidos para Acesso e Cadastro de Tabelas Auxiliares do Sistema -->
            <div class="form-card config-card">
                <div class="section-title">🗂️ Tabelas Auxiliares e Permissões</div>
                <div class="admin-links">
                    <a href="../categorias/index.php" class="btn btn-secondary">📋 Gerenciar Categorias</a>
                    <a href="../usuarios/index.php" class="btn btn-secondary">👤 Gerenciar Colaboradores (Usuários)</a>
                    <a href="../fornecedores/index.php" class="btn btn-secondary">🏭 Gerenciar Fornecedores</a>
                    <a href="../relatorios/index.php" class="btn btn-secondary">📈 Central de Relatórios</a>
                </div>
            </div>

            <!-- Card informativo que exibe metadados de status e ambiente da aplicação -->
            <div class="form-card config-card">
                <div class="section-title">ℹ️ Informações da Aplicação</div>
                <table class="info-table">
                    <tbody>
                        <!-- Linha com a versão ativa do ChefSupply -->
                        <tr>
                            <td>Versão do Sistema</td>
                            <td><?= htmlspecialchars($configs['versao'] ?? '1.0.0') ?></td>
                        </tr>
                        <!-- Linha de moeda padrão adotada para preços e perdas financeiras -->
                        <tr>
                            <td>Moeda Padrão</td>
                            <td>R$ (Real Brasileiro)</td>
                        </tr>
                        <!-- Exibe o nome do usuário logado na sessão ativa -->
                        <tr>
                            <td>Operador Atual</td>
                            <td><?= htmlspecialchars($_SESSION['usuario_nome'] ?? 'Operador') ?></td>
                        </tr>
                        <!-- Informa o banco de dados utilizado -->
                        <tr>
                            <td>Servidor de Banco de Dados</td>
                            <td>MySQL 8.0 (localhost)</td>
                        </tr>
                        <!-- Exibe a data e a hora corrente do servidor web no formato brasileiro -->
                        <tr>
                            <td>Data/Hora do Servidor</td>
                            <td><?= date('d/m/Y H:i') ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<?php 
// Inclui o arquivo de rodapé padrão da aplicação
include '../_footer.php'; 
?>