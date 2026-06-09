<?php
// Inicia a sessão PHP para verificar a autenticação de login do usuário administrador no painel
session_start();

// Verifica se a variável de sessão 'logado' não está configurada ou se é falsa.
// Caso o usuário não esteja logado, redireciona o navegador do mesmo para o arquivo index.php de login.
if(!isset($_SESSION['logado']) || !$_SESSION['logado']){ 
    header('Location: ../index.php'); 
    // Encerra imediatamente a execução do script para que ocorra o redirecionamento
    exit; 
}

// Importa o arquivo contendo a conexão PDO com o banco de dados
include '../conexao.php';

// Executa uma consulta SQL para obter todos os lotes de estoque ativos.
// A consulta recupera dados do lote, traz o nome e unidade do produto (através do INNER JOIN),
// o nome do fornecedor correspondente (usando o LEFT JOIN) e calcula no próprio banco de dados
// a diferença em dias entre a data de vencimento do lote e a data atual (DATEDIFF) como 'dias_para_vencer'.
// A ordenação é feita de forma crescente pela data de vencimento limitando aos primeiros 100 lotes.
$lotes = $conexao->query("
    SELECT l.*, p.nome as produto_nome, p.unidade, f.nome as fornecedor_nome,
           DATEDIFF(l.data_vencimento, CURDATE()) as dias_para_vencer
    FROM lotes l
    INNER JOIN produtos p ON p.id = l.produto_id
    LEFT JOIN fornecedores f ON f.id = l.fornecedor_id
    WHERE l.status = 'ativo'
    ORDER BY l.data_vencimento ASC LIMIT 100
")->fetchAll();

// Define a variável de página atual para sinalização do item correspondente no menu
$pagina_atual = 'estoque'; 
// Define o título que será impresso no topo da aba do navegador
$titulo_pagina = 'Movimentações';

// Inclui o arquivo de cabeçalho padrão com o início do layout HTML e a navegação lateral
include '../_header.php';
?>
<!-- Container com o Conteúdo Principal da Página -->
<div class="content">
    <!-- Cabeçalho de Navegação e Ações Rápidas -->
    <div class="page-header">
        <div class="page-header-left">
            <h2>Movimentações de Estoque</h2>
            <p>Lotes ativos ordenados por vencimento</p>
        </div>
        <!-- Botões de ação para abrir o cadastro de nova entrada (compra/lote) ou nova saída (baixa) -->
        <div style="display:flex;gap:8px">
            <a href="entrada.php" class="btn btn-primary">+ Entrada</a>
            <a href="saida.php"   class="btn btn-secondary">↑ Saída</a>
        </div>
    </div>

    <!-- Bloco condicional para exibição de alerta de confirmação de sucesso de entrada/saída -->
    <?php if(isset($_GET['msg'])): ?>
        <!-- Compara se a mensagem recebida via GET é igual a 'entrada' para exibir a palavra 'Entrada' ou 'Saída' -->
        <div class="alert alert-success"><?= $_GET['msg']==='entrada'?'Entrada':'Saída' ?> registrada com sucesso!</div>
    <?php endif; ?>

    <!-- Cartão contendo a tabela de exibição dos lotes -->
    <div class="table-card">
        <table>
            <thead>
                <tr>
                    <th>Produto</th>
                    <th>Fornecedor</th>
                    <th>Lote</th>
                    <th>Qtd. Restante</th>
                    <th>Entrada</th>
                    <th>Vencimento</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
            <!-- Verifica se o array de lotes ativos retornado do banco está vazio -->
            <?php if(empty($lotes)): ?>
                <tr>
                    <!-- Se vazio, exibe linha informativa com link para registrar nova entrada -->
                    <td colspan="7" style="text-align:center;padding:40px;color:#aaa">
                        Nenhum lote ativo. <a href="../entradas/nova.php" style="color:#2db35d">Registrar entrada.</a>
                    </td>
                </tr>
            <?php else: ?>
                <!-- Caso possua registros, faz o loop iterativo pelos lotes carregados -->
                <?php foreach($lotes as $l):
                    // Armazena a contagem de dias restantes até o vencimento calculada na SQL
                    $dias = $l['dias_para_vencer'];
                    
                    // Estrutura condicional para determinar a classe CSS e texto do indicador de vencimento
                    if($dias===null){ 
                        // Se não possuir data de validade associada
                        $badge='badge-info';    
                        $label='Sem validade'; 
                    }
                    elseif($dias<0) { 
                        // Se a diferença em dias for negativa, o lote já expirou
                        $badge='badge-vencido'; 
                        $label='Vencido'; 
                    }
                    elseif($dias<=3){ 
                        // Se vencer em 3 dias ou menos, sinaliza com estado crítico
                        $badge='badge-critico'; 
                        $label="$dias dias"; 
                    }
                    elseif($dias<=7){ 
                        // Se vencer em 7 dias ou menos, sinaliza com estado de atenção
                        $badge='badge-atencao'; 
                        $label="$dias dias"; 
                    }
                    else { 
                        // Caso esteja dentro do prazo saudável de vencimento (acima de 7 dias)
                        $badge='badge-normal';  
                        $label="$dias dias"; 
                    }
                ?>
                    <!-- Linha individual correspondente a um lote da iteração -->
                    <tr>
                        <td>
                            <!-- Exibe o nome do produto principal em negrito e sua respectiva unidade de medida ao lado em cinza menor -->
                            <strong><?= htmlspecialchars($l['produto_nome']) ?></strong> 
                            <span style="color:#aaa;font-size:.8rem"><?= $l['unidade'] ?></span>
                        </td>
                        <!-- Nome do fornecedor associado. Se nulo ou vazio, exibe travessão (—) -->
                        <td><?= htmlspecialchars($l['fornecedor_nome'] ?? '—') ?></td>
                        <td>
                            <!-- Exibe o código do lote do fornecedor ou senão o número do ID sequencial do lote com estilo monospaced (code) -->
                            <code style="background:#f5f5f5;padding:2px 6px;border-radius:4px;font-size:.8rem">
                                <?= htmlspecialchars($l['codigo_lote'] ?: '#'.$l['id']) ?>
                            </code>
                        </td>
                        <!-- Quantidade física restante do lote formatada com duas casas decimais e vírgula como separador -->
                        <td><?= number_format($l['quantidade_restante'],2,',','.') ?></td>
                        <!-- Data de entrada no estoque formatada para o padrão brasileiro (d/m/Y) -->
                        <td><?= date('d/m/Y', strtotime($l['data_entrada'])) ?></td>
                        <!-- Data de vencimento formatada. Se nula, exibe travessão -->
                        <td><?= $l['data_vencimento'] ? date('d/m/Y', strtotime($l['data_vencimento'])) : '—' ?></td>
                        <!-- Emblema de sinalização visual de proximidade do vencimento determinado no bloco condicional acima -->
                        <td><span class="badge <?= $badge ?>"><?= $label ?></span></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<!-- Inclui as tags de fechamento de corpo e scripts globais do rodapé -->
<?php include '../_footer.php'; ?>