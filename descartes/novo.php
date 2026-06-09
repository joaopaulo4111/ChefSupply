<?php
// Inicia a sessão PHP para verificar a autenticação e gerenciar permissões do usuário logado.
session_start();

// Verifica se a variável de sessão 'logado' não está definida ou é falsa.
// Caso o usuário não esteja autenticado, redireciona para a página de login/índice na raiz e encerra o script.
if(!isset($_SESSION['logado']) || !$_SESSION['logado']){
    header('Location: ../index.php');
    exit;
}

// Inclui o arquivo de conexão com o banco de dados (PDO) usando require_once para evitar múltiplas inclusões.
require_once '../conexao.php';

// Busca no banco de dados todos os produtos que possuem estoque atual maior do que zero,
// ordenando-os alfabeticamente para exibir na caixa de seleção (dropdown).
$produtos = $conexao->query("
    SELECT id, nome, unidade, estoque_atual, custo_unitario 
    FROM produtos 
    WHERE estoque_atual > 0 
    ORDER BY nome
")->fetchAll(PDO::FETCH_ASSOC);

// Inicializa as variáveis de controle para armazenar mensagens de erro ou de sucesso.
$erro = '';
$sucesso = '';

// Verifica se a requisição é do tipo POST, indicando a submissão do formulário.
if($_SERVER['REQUEST_METHOD'] === 'POST'){
    // Sanitiza e valida as variáveis enviadas pelo formulário
    // Converte o ID do produto para número inteiro.
    $produto_id = intval($_POST['produto_id'] ?? 0);
    
    // Verifica se o ID do lote foi fornecido; se sim, converte para inteiro, senão define como nulo.
    $lote_id    = !empty($_POST['lote_id']) ? intval($_POST['lote_id']) : null;
    
    // Converte a quantidade descartada para um número decimal (float).
    $quantidade = floatval($_POST['quantidade'] ?? 0);
    
    // Limpa espaços no início e fim do motivo e das observações informadas.
    $motivo     = trim($_POST['motivo'] ?? '');
    $obs        = trim($_POST['observacoes'] ?? '');
    
    // Obtém a data em que o descarte foi realizado.
    $data       = trim($_POST['data_descarte'] ?? '');

    // Validação básica dos campos obrigatórios: produto, quantidade maior que 0, motivo e data do descarte.
    if(!$produto_id || $quantidade <= 0 || !$motivo || !$data){
        $erro = 'Produto, quantidade, motivo e data do descarte são obrigatórios.';
    } else {
        // Prepara uma consulta para buscar os detalhes do produto selecionado para validar estoque e obter preço de custo reserva.
        $stmtP = $conexao->prepare("SELECT nome, estoque_atual, custo_unitario FROM produtos WHERE id = :id");
        $stmtP->execute([':id' => $produto_id]);
        $prodInfo = $stmtP->fetch(PDO::FETCH_ASSOC);

        // Se o produto selecionado não existir no banco de dados.
        if(!$prodInfo){
            $erro = 'Produto selecionado não existe.';
        } 
        // Verifica se a quantidade que se quer descartar é superior ao total disponível em estoque físico geral.
        elseif($quantidade > floatval($prodInfo['estoque_atual'])) {
            $erro = "Quantidade descartada ({$quantidade}) não pode ser maior que o estoque atual do produto (" . number_format($prodInfo['estoque_atual'], 2, ',', '.') . ").";
        } else {
            // Inicializa a variável del valor monetário perdido.
            $valor = 0.00;

            // Se um lote específico tiver sido selecionado no formulário, valida as regras do lote.
            if($lote_id){
                // Prepara a consulta para obter as informações do lote, garantindo que ele pertença ao produto selecionado.
                $stmtL = $conexao->prepare("SELECT preco_custo, quantidade_restante, codigo_lote FROM lotes WHERE id = :id AND produto_id = :pid");
                $stmtL->execute([':id' => $lote_id, ':pid' => $produto_id]);
                $loteInfo = $stmtL->fetch(PDO::FETCH_ASSOC);

                // Verifica se o lote é válido e pertence de fato àquele produto.
                if(!$loteInfo){
                    $erro = 'Lote selecionado inválido para este produto.';
                } 
                // Verifica se a quantidade que se deseja descartar é superior ao que resta disponível especificamente naquele lote.
                elseif($quantidade > floatval($loteInfo['quantidade_restante'])) {
                    $erro = "Quantidade descartada ({$quantidade}) não pode ser maior que a quantidade restante no lote " . htmlspecialchars($loteInfo['codigo_lote'] ?: '#'.$lote_id) . " (" . number_format($loteInfo['quantidade_restante'], 2, ',', '.') . ").";
                } else {
                    // Calcula o valor perdido baseado na quantidade descartada multiplicada pelo preço de custo unitário específico do lote.
                    $valor = $quantidade * floatval($loteInfo['preco_custo']);
                }
            } else {
                // Caso não tenha sido escolhido um lote específico, calcula a perda com base no custo unitário padrão do produto.
                $valor = $quantidade * floatval($prodInfo['custo_unitario']);
            }

            // Se não houver nenhum erro de validação até aqui, prossegue para as atualizações no banco dentro de uma transação.
            if(!$erro){
                try {
                    // Inicia a transação com o banco de dados.
                    $conexao->beginTransaction();

                    // 1. Prepara e executa a inserção do novo registro na tabela de descartes.
                    $stmtInsert = $conexao->prepare("
                        INSERT INTO descartes (produto_id, lote_id, quantidade, motivo, valor_perdido, observacoes, data_descarte, usuario_id) 
                        VALUES (:pid, :lid, :qtd, :mot, :val, :obs, :dt, :uid)
                    ");
                    $stmtInsert->execute([
                        ':pid' => $produto_id,
                        ':lid' => $lote_id,
                        ':qtd' => $quantidade,
                        ':mot' => $motivo,
                        ':val' => $valor,
                        ':obs' => $obs,
                        ':dt'  => $data,
                        ':uid' => $_SESSION['usuario_id'] ?? null // Salva o ID do usuário da sessão, se disponível.
                    ]);

                    // 2. Prepara e executa a atualização do estoque geral do produto na tabela de produtos.
                    // Utiliza GREATEST(0, estoque_atual - :qtd) para garantir que o estoque nunca fique negativo.
                    // Além disso, recalcula o status do produto com base no novo saldo.
                    $stmtUpdateProd = $conexao->prepare("
                        UPDATE produtos 
                        SET estoque_atual = GREATEST(0, estoque_atual - :qtd),
                            status = CASE 
                                WHEN GREATEST(0, estoque_atual - :qtd_c1) <= 0 THEN 'Crítico'
                                WHEN GREATEST(0, estoque_atual - :qtd_c2) <= estoque_minimo THEN 'Baixo'
                                WHEN estoque_maximo > 0 AND GREATEST(0, estoque_atual - :qtd_c3) >= estoque_maximo THEN 'Alto'
                                ELSE 'Normal'
                            END
                        WHERE id = :id
                    ");
                    $stmtUpdateProd->execute([
                        ':qtd' => $quantidade,
                        ':qtd_c1' => $quantidade,
                        ':qtd_c2' => $quantidade,
                        ':qtd_c3' => $quantidade,
                        ':id' => $produto_id
                    ]);

                    // 3. Atualiza o estoque do lote na tabela de lotes, caso um lote específico tenha sido selecionado.
                    // Subtrai a quantidade do estoque restante do lote e, se o saldo zerar, define seu status como 'descartado'.
                    if($lote_id){
                        $stmtUpdateLote = $conexao->prepare("
                            UPDATE lotes 
                            SET quantidade_restante = GREATEST(0, quantidade_restante - :qtd),
                                status = CASE WHEN GREATEST(0, quantidade_restante - :qtd_c) <= 0 THEN 'descartado' ELSE status END
                            WHERE id = :id
                        ");
                        $stmtUpdateLote->execute([
                            ':qtd' => $quantidade,
                            ':qtd_c' => $quantidade,
                            ':id' => $lote_id
                        ]);
                    }

                    // Se tudo deu certo, efetiva as alterações no banco de dados (commit).
                    $conexao->commit();
                    
                    // Redireciona o usuário para a página de listagem com mensagem de sucesso.
                    header('Location: index.php?msg=criado');
                    exit;

                } catch (Exception $e) {
                    // Se houver qualquer falha durante as operações do banco de dados, cancela todas as alterações feitas (rollback).
                    if($conexao->inTransaction()){
                        $conexao->rollBack();
                    }
                    // Define a mensagem de erro para exibição no formulário.
                    $erro = 'Erro interno ao registrar descarte: ' . $e->getMessage();
                }
            }
        }
    }
}

// Define o item de menu ativo no cabeçalho.
$pagina_atual = 'descartes';
// Define o título da página para renderização na aba/cabeçalho do navegador.
$titulo_pagina = 'Registrar Descarte';

// Inclui o arquivo de cabeçalho padrão.
include '../_header.php';
?>

<div class="content">
    <!-- Cabeçalho da Seção -->
    <div class="page-header">
        <div class="page-header-left">
            <h2>Registrar Novo Descarte</h2>
            <p>Selecione o produto, lote correspondente e informe a quantidade da perda.</p>
        </div>
        <!-- Link para retornar à tela de listagem de descartes -->
        <a href="index.php" class="btn btn-secondary">← Voltar para Listagem</a>
    </div>

    <!-- Exibe a mensagem de erro caso ocorra alguma falha na validação ou no cadastro -->
    <?php if($erro): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($erro) ?></div>
    <?php endif; ?>

    <!-- Cartão contendo o formulário de cadastro de descarte -->
    <div class="form-card">
        <!-- Formulário que envia os dados via POST para si mesmo -->
        <form method="POST" action="novo.php" autocomplete="off">
            <div class="form-grid">
                <!-- Seleção do Produto a ser descartado -->
                <div class="form-group">
                    <label for="produto_id">Produto *</label>
                    <!-- Executa a função JavaScript carregarLotes() ao alterar o produto selecionado -->
                    <select name="produto_id" id="produto_id" required onchange="carregarLotes(this.value)">
                        <option value="">— Selecione o produto —</option>
                        <?php foreach($produtos as $p): ?>
                            <!-- Mantém o produto selecionado caso o formulário seja recarregado após um erro ou passado via GET -->
                            <option value="<?= $p['id'] ?>" <?= ( (isset($_POST['produto_id']) && $_POST['produto_id'] == $p['id']) || (!isset($_POST['produto_id']) && isset($_GET['produto_id']) && $_GET['produto_id'] == $p['id']) ) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($p['nome']) ?> (<?= htmlspecialchars($p['unidade']) ?>) — Disp: <?= number_format($p['estoque_atual'], 2, ',', '.') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Seleção do Lote (opcional, preenchido via AJAX baseado no produto selecionado) -->
                <div class="form-group">
                    <label for="sel-lote">Lote (Opcional)</label>
                    <select name="lote_id" id="sel-lote">
                        <option value="">— Selecione o produto primeiro —</option>
                    </select>
                </div>

                <!-- Entrada da Quantidade Descartada -->
                <div class="form-group">
                    <label for="quantidade">Quantidade Descartada *</label>
                    <!-- Permite entrada de até 3 casas decimais (step=0.001) para produtos pesados/medidos -->
                    <input type="number" name="quantidade" id="quantidade" step="0.001" min="0.001" placeholder="0,00" value="<?= htmlspecialchars($_POST['quantidade'] ?? '') ?>" required>
                </div>

                <!-- Seleção do Motivo da Perda/Descarte -->
                <div class="form-group">
                    <label for="motivo">Motivo *</label>
                    <select name="motivo" id="motivo" required>
                        <option value="">— Selecione o motivo —</option>
                        <option value="Vencimento" <?= (isset($_POST['motivo']) && $_POST['motivo'] === 'Vencimento') ? 'selected' : '' ?>>Vencimento</option>
                        <option value="Deterioração" <?= (isset($_POST['motivo']) && $_POST['motivo'] === 'Deterioração') ? 'selected' : '' ?>>Deterioração</option>
                        <option value="Excesso de produção" <?= (isset($_POST['motivo']) && $_POST['motivo'] === 'Excesso de produção') ? 'selected' : '' ?>>Excesso de produção</option>
                        <option value="Outros" <?= (isset($_POST['motivo']) && $_POST['motivo'] === 'Outros') ? 'selected' : '' ?>>Outros</option>
                    </select>
                </div>

                <!-- Data em que a perda ocorreu (por padrão preenche com a data atual) -->
                <div class="form-group">
                    <label for="data_descarte">Data do Descarte *</label>
                    <input type="date" name="data_descarte" id="data_descarte" value="<?= htmlspecialchars($_POST['data_descarte'] ?? date('Y-m-d')) ?>" required>
                </div>

                <!-- Campo de Observações Gerais sobre a perda (largura cheia) -->
                <div class="form-group full">
                    <label for="observacoes">Observações / Detalhes</label>
                    <textarea name="observacoes" id="observacoes" placeholder="Descreva os detalhes da perda (ex: avaria no transporte, cheiro desagradável, etc.)"><?= htmlspecialchars($_POST['observacoes'] ?? '') ?></textarea>
                </div>
            </div>

            <!-- Botões de Ação do Formulário -->
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Registrar Descarte</button>
                <a href="index.php" class="btn btn-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>

<script>
/**
 * Busca assincronamente os lotes ativos associados ao produto selecionado e popula o dropdown correspondente.
 * @param {number} produtoId - ID do produto selecionado
 */
function carregarLotes(produtoId) {
    const sel = document.getElementById('sel-lote');
    // Se nenhum produto estiver selecionado, exibe mensagem pedindo a seleção preliminar.
    if (!produtoId) { 
        sel.innerHTML = '<option value="">— Selecione o produto primeiro —</option>'; 
        return; 
    }
    sel.innerHTML = '<option value="">Carregando lotes...</option>';
    
    // Faz a chamada GET para a API interna de busca de lotes por produto
    fetch('../api/lotes_por_produto.php?produto_id=' + produtoId)
        .then(r => {
            if(!r.ok) throw new Error('Erro na requisição');
            return r.json();
        })
        .then(lotes => {
            // Se o retorno for um array vazio, exibe mensagem que o produto não possui lotes cadastrados ativos.
            if(lotes.length === 0) {
                sel.innerHTML = '<option value="">— Sem lotes disponíveis —</option>';
            } else {
                // Se houver lotes, popula as opções exibindo o código do lote, data de vencimento formatada e quantidade restante.
                sel.innerHTML = '<option value="">— Selecione o lote (Sem lote específico) —</option>';
                lotes.forEach(l => {
                    const dataVenc = l.data_vencimento ? formatarData(l.data_vencimento) : 'N/D';
                    sel.innerHTML += `<option value="${l.id}">Lote: ${l.codigo_lote || '#'+l.id} — Vence: ${dataVenc} — Qtd: ${parseFloat(l.quantidade_restante).toLocaleString('pt-BR')}</option>`;
                });
            }
        })
        .catch(err => {
            console.error(err);
            sel.innerHTML = '<option value="">Erro ao carregar lotes</option>';
        });
}

/**
 * Formata uma string de data 'YYYY-MM-DD' para o formato brasileiro 'DD/MM/YYYY'.
 * @param {string} dataStr - String de data original
 * @returns {string} Data formatada
 */
function formatarData(dataStr) {
    if(!dataStr) return 'N/D';
    const partes = dataStr.split('-');
    if(partes.length !== 3) return dataStr;
    return `${partes[2]}/${partes[1]}/${partes[0]}`;
}

// Quando o documento HTML é totalmente carregado na página
document.addEventListener('DOMContentLoaded', () => {
    // Tenta pegar o ID do produto pré-selecionado no formulário
    let pId = document.getElementById('produto_id').value;
    if(!pId) {
        // Se estiver em branco, tenta obter o produto_id enviado via parâmetro GET na URL
        pId = '<?= htmlspecialchars($_GET['produto_id'] ?? '') ?>';
        if(pId) {
            document.getElementById('produto_id').value = pId;
        }
    }
    // Se encontrou um produto selecionado, carrega os lotes relativos a ele
    if(pId) {
        carregarLotes(pId);
        // Aguarda um pequeno intervalo (300ms) para o preenchimento das opções antes de aplicar a seleção antiga
        setTimeout(() => {
            const oldLoteId = '<?= htmlspecialchars($_POST['lote_id'] ?? $_GET['lote_id'] ?? '') ?>';
            if(oldLoteId) {
                document.getElementById('sel-lote').value = oldLoteId;
            }
        }, 300);
    }
});
</script>

<?php 
// Inclui o arquivo de rodapé padrão da aplicação.
include '../_footer.php'; 
?>