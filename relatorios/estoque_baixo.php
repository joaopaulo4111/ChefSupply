<?php
// Inicia a sessão PHP para verificação e controle de autenticação do usuário na aplicação
session_start();

// Verifica se o usuário não está autenticado (sessão 'logado' não definida ou falsa).
// Caso não esteja logado, redireciona para a página de login raiz e encerra a execução do script.
if(!isset($_SESSION['logado']) || !$_SESSION['logado']){ 
    header('Location: ../index.php'); // Redirecionamento de segurança
    exit; // Encerra a execução do código PHP
}

// Inclui o arquivo de conexão com o banco de dados (inicializa a conexão PDO em $conexao)
include '../conexao.php';

// Executa uma consulta SQL para buscar os produtos que estão com o estoque atual menor ou igual ao estoque mínimo.
// A consulta utiliza um LEFT JOIN para obter o nome da categoria relacionada ao produto.
// Utiliza a estrutura condicional CASE WHEN para classificar dinamicamente a situação do estoque:
// - 'Zerado' se o estoque atual for exatamente 0.
// - 'Crítico' se o estoque atual for menor ou igual ao estoque mínimo.
// - 'Baixo' caso contrário (fallback opcional para a lógica).
// Ordena os resultados pelo estoque atual em ordem crescente para destacar os mais críticos primeiro.
$produtos = $conexao->query("
    SELECT p.*, c.nome as categoria_nome,
           CASE WHEN p.estoque_atual = 0 THEN 'Zerado'
                WHEN p.estoque_atual <= p.estoque_minimo THEN 'Crítico'
                ELSE 'Baixo' END as situacao
    FROM produtos p
    LEFT JOIN categorias c ON c.id = p.categoria_id
    WHERE p.estoque_atual <= p.estoque_minimo
    ORDER BY p.estoque_atual ASC
")->fetchAll(); // Recupera todos os registros correspondentes em um array

// Define as variáveis de controle da navegação e o título de exibição na aba do navegador
$pagina_atual = 'relatorios'; 
$titulo_pagina = 'Estoque Baixo';

// Inclui o cabeçalho padrão da aplicação
include '../_header.php';
?>

<!-- Container principal que encapsula a estrutura visual da página -->
<div class="content">
    
    <!-- Seção de cabeçalho interno da página -->
    <div class="page-header">
        <div class="page-header-left">
            <h2>Relatório — Estoque Baixo</h2>
            <!-- Conta de forma dinâmica a quantidade de registros no array de produtos em estoque baixo e exibe na tela -->
            <p><?= count($produtos) ?> produto(s) abaixo do mínimo</p>
        </div>
        <!-- Botões de controle no cabeçalho interno -->
        <div style="display:flex;gap:8px">
            <!-- Dispara a janela de impressão nativa do navegador via JavaScript (window.print) -->
            <button onclick="window.print()" class="btn btn-secondary">🖨 Imprimir</button>
            <!-- Link para voltar ao menu ou página inicial de relatórios -->
            <a href="index.php" class="btn btn-secondary">← Voltar</a>
        </div>
    </div>

    <!-- Verifica se o array de produtos com estoque baixo está vazio -->
    <?php if(empty($produtos)): ?>
        <!-- Exibe mensagem de feedback positiva em um card caso o estoque esteja 100% adequado -->
        <div class="table-card">
            <div class="empty-state">
                <p>✅ Todos os produtos estão com estoque adequado!</p>
            </div>
        </div>
    <?php else: ?>
        <!-- Exibe a tabela contendo a lista de produtos com estoque deficiente -->
        <div class="table-card">
            <table>
                <thead>
                    <tr>
                        <th>Produto</th>
                        <th>Categoria</th>
                        <th>Unidade</th>
                        <th>Atual</th>
                        <th>Mínimo</th>
                        <th>Déficit</th>
                        <th>Situação</th>
                    </tr>
                </thead>
                <tbody>
                <!-- Percorre o array de produtos gerando cada linha da tabela -->
                <?php foreach($produtos as $p):
                    // Calcula a quantidade em falta (déficit) para atingir o estoque mínimo estabelecido.
                    // A função 'max(0, ...)' garante que o déficit nunca seja menor do que zero.
                    $deficit = max(0, $p['estoque_minimo'] - $p['estoque_atual']);
                    
                    // Lógica para determinar a classe CSS do badge de acordo com a situação de estoque (Zerado/Crítico)
                    $badge   = $p['situacao'] === 'Zerado' ? 'badge-vencido' : 'badge-critico';
                ?>
                    <tr>
                        <!-- Exibe o nome do produto de maneira segura usando htmlspecialchars -->
                        <td><strong><?= htmlspecialchars($p['nome']) ?></strong></td>
                        <!-- Exibe o nome da categoria ou um traço caso seja nulo -->
                        <td><?= htmlspecialchars($p['categoria_nome'] ?? '—') ?></td>
                        <!-- Exibe a unidade de medida do produto -->
                        <td><?= $p['unidade'] ?></td>
                        <!-- Exibe o estoque atual formatado com duas casas decimais e cor vermelha para destaque -->
                        <td style="color:#dc2626;font-weight:600"><?= number_format($p['estoque_atual'],2,',','.') ?></td>
                        <!-- Exibe o estoque mínimo configurado para o produto -->
                        <td><?= number_format($p['estoque_minimo'],2,',','.') ?></td>
                        <!-- Exibe o déficit (quantidade que falta para o mínimo) com cor de destaque amarela/laranja -->
                        <td style="color:#ca8a04;font-weight:600"><?= number_format($deficit,2,',','.') ?></td>
                        <!-- Exibe o status da situação do estoque dentro de um badge estilizado -->
                        <td><span class="badge <?= $badge ?>"><?= $p['situacao'] ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php 
// Inclui o arquivo de rodapé padrão da página para fechar tags HTML comuns
include '../_footer.php'; 
?>