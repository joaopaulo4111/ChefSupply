<?php
// Inicia a sessão para permitir o controle de autenticação do usuário na aplicação
session_start();

// Verifica se a variável de sessão 'logado' não está definida ou é falsa.
// Caso o usuário não esteja logado, ele é redirecionado para a página de login raiz e a execução do script é interrompida.
if(!isset($_SESSION['logado']) || !$_SESSION['logado']){
    header('Location: ../index.php'); // Redireciona o usuário para a página de login
    exit; // Encerra a execução do script para garantir que nenhum código posterior seja executado
}

// Requer o arquivo de conexão com o banco de dados (estabelece a variável $conexao utilizando PDO)
require_once '../conexao.php';

// Mecanismo de auto-recuperação (Self-healing) do banco de dados:
// Tenta realizar uma consulta rápida selecionando a coluna 'codigo_barras' na tabela 'produtos' para verificar se ela existe.
try {
    $conexao->query("SELECT codigo_barras FROM produtos LIMIT 1");
} catch (Exception $e) {
    // Se a consulta falhar (indicando que a coluna 'codigo_barras' não existe na tabela),
    // tenta executar um comando ALTER TABLE para adicionar a coluna 'codigo_barras' com tipo VARCHAR(50), aceitando valores nulos e com restrição de valor único (UNIQUE).
    try {
        $conexao->query("ALTER TABLE produtos ADD COLUMN codigo_barras VARCHAR(50) NULL UNIQUE");
    } catch (Exception $ex) {
        // Bloco de fallback: Se por algum motivo a alteração da tabela falhar, o erro é silenciado para não travar a aplicação.
    }
}

// Recupera e sanitiza o ID do produto enviado via parâmetro GET na URL.
// Se o parâmetro 'id' não estiver presente, assume o valor padrão 0. A função intval garante a conversão para número inteiro.
$id = intval($_GET['id'] ?? 0);

// Se o ID for inválido ou igual a zero, o usuário é redirecionado de volta para a lista (index.php) de produtos e o script é encerrado.
if (!$id) {
    header('Location: index.php'); // Redireciona para o índice de produtos
    exit; // Encerra o processamento do script
}

// Prepara uma consulta SQL para buscar todas as informações do produto que possui o ID correspondente no banco de dados.
$stmt = $conexao->prepare("SELECT * FROM produtos WHERE id = :id");

// Executa a consulta vinculando o parâmetro ':id' ao valor da variável $id
$stmt->execute([':id' => $id]);

// Recupera o registro do produto como um array associativo
$p = $stmt->fetch(PDO::FETCH_ASSOC);

// Verifica se o produto foi encontrado. Se o retorno for falso, redireciona o usuário para a listagem com uma mensagem de erro na URL.
if (!$p) {
    header('Location: index.php?erro=nao_encontrado'); // Redireciona informando que o produto não foi encontrado
    exit; // Encerra a execução do script
}

// Realiza uma consulta para buscar todas as categorias cadastradas, ordenando-as pelo nome em ordem alfabética.
// O resultado é retornado como um array contendo todas as categorias (id e nome).
$categorias = $conexao->query("SELECT id, nome FROM categorias ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);

// Inicializa a variável de controle de mensagens de erro como uma string vazia.
$erro = '';

// Verifica se a requisição atual é do tipo POST (ou seja, se o formulário de edição foi submetido pelo usuário).
if($_SERVER['REQUEST_METHOD'] === 'POST'){
    
    // Obtém e sanitiza os dados enviados pelo formulário via método POST:
    // Remove espaços em branco nas extremidades do nome do produto.
    $nome          = trim($_POST['nome'] ?? '');
    
    // Se o código de barras for fornecido, remove espaços em branco; caso contrário, define como NULL.
    $codigo_barras = !empty($_POST['codigo_barras']) ? trim($_POST['codigo_barras']) : null;
    
    // Se a categoria for fornecida, converte para inteiro; caso contrário, define como NULL.
    $cat_id        = !empty($_POST['categoria_id']) ? intval($_POST['categoria_id']) : null;
    
    // Remove espaços em branco da unidade de medida (padrão 'kg' caso não fornecida).
    $unidade       = trim($_POST['unidade'] ?? 'kg');
    
    // Converte o estoque mínimo para número de ponto flutuante (float), padrão 0.
    $est_min       = floatval($_POST['estoque_minimo'] ?? 0);
    
    // Converte o estoque máximo para número de ponto flutuante (float), padrão 0.
    $est_max       = floatval($_POST['estoque_maximo'] ?? 0);
    
    // Converte o custo unitário para número de ponto flutuante (float), padrão 0.
    $custo         = floatval($_POST['custo_unitario'] ?? 0);

    // Validação básica do formulário:
    // O nome do produto não pode estar vazio.
    if(!$nome){
        $erro = 'O nome do produto é obrigatório.';
    } 
    // O estoque mínimo não pode ser maior que o estoque máximo (se o estoque máximo for maior que zero).
    elseif ($est_max > 0 && $est_min > $est_max) {
        $erro = 'O estoque mínimo não pode ser maior que o estoque máximo.';
    } else {
        
        // Verifica no banco de dados se já existe outro produto cadastrado com o mesmo nome (ignorando maiúsculas/minúsculas)
        // e que tenha um ID diferente do produto que está sendo editado no momento.
        $check = $conexao->prepare("SELECT COUNT(*) FROM produtos WHERE LOWER(nome) = LOWER(:nome) AND id != :id");
        $check->execute([':nome' => $nome, ':id' => $id]);
        
        // Inicializa a variável de checagem do código de barras
        $checkBar = 0;
        
        // Caso um código de barras tenha sido fornecido, verifica se já existe outro produto utilizando o mesmo código de barras,
        // desconsiderando o produto atual sob edição.
        if ($codigo_barras !== null) {
            $stmtBar = $conexao->prepare("SELECT COUNT(*) FROM produtos WHERE codigo_barras = :bar AND id != :id");
            $stmtBar->execute([':bar' => $codigo_barras, ':id' => $id]);
            $checkBar = $stmtBar->fetchColumn(); // Obtém a contagem de registros com o mesmo código de barras
        }

        // Se a contagem de produtos com o mesmo nome for maior que zero, define uma mensagem de erro.
        if ($check->fetchColumn() > 0) {
            $erro = 'Já existe outro produto cadastrado com este nome.';
        } 
        // Se a contagem de produtos com o mesmo código de barras for maior que zero, define uma mensagem de erro correspondente.
        elseif ($checkBar > 0) {
            $erro = 'Este código de barras já está cadastrado para outro produto.';
        } else {
            
            // Recalcula o status do nível de estoque com base no estoque_atual e nos novos limites mínimo e máximo fornecidos.
            $status = 'Normal'; // Define o status padrão como Normal
            $estoque_atual = floatval($p['estoque_atual']); // Obtém o estoque atual a partir dos dados originais do produto
            
            // Se o estoque atual for menor ou igual a 0, define o status como Crítico.
            if ($estoque_atual <= 0) {
                $status = 'Crítico';
            } 
            // Se o estoque atual for menor ou igual ao estoque mínimo, define o status como Baixo.
            elseif ($estoque_atual <= $est_min) {
                $status = 'Baixo';
            } 
            // Se o estoque máximo estiver configurado (maior que 0) e o estoque atual for maior ou igual a ele, define como Alto.
            elseif ($est_max > 0 && $estoque_atual >= $est_max) {
                $status = 'Alto';
            }

            try {
                // Prepara a consulta SQL de atualização dos dados do produto na tabela.
                $sql = "UPDATE produtos 
                        SET nome = :nome, 
                            categoria_id = :cat, 
                            unidade = :un, 
                            estoque_minimo = :emin, 
                            estoque_maximo = :emax, 
                            custo_unitario = :custo, 
                            status = :status,
                            codigo_barras = :bar
                        WHERE id = :id";
                
                // Prepara a query PDO para execução
                $stmtUpdate = $conexao->prepare($sql);
                
                // Executa a query vinculando todos os dados limpos e validados aos respectivos placeholders da query SQL.
                $stmtUpdate->execute([
                    ':nome'   => $nome,
                    ':cat'    => $cat_id,
                    ':un'     => $unidade,
                    ':emin'   => $est_min,
                    ':emax'   => $est_max,
                    ':custo'  => $custo,
                    ':status' => $status,
                    ':bar'    => $codigo_barras,
                    ':id'     => $id
                ]);

                // Em caso de sucesso na atualização, redireciona para a página principal de produtos com uma mensagem de sucesso na URL.
                header('Location: index.php?msg=editado');
                exit; // Encerra o script após o redirecionamento
            } catch (Exception $e) {
                // Em caso de exceção/erro no banco de dados, captura a mensagem e a exibe no formulário.
                $erro = 'Erro ao atualizar produto: ' . $e->getMessage();
            }
        }
    }
}

// Configura as variáveis globais que serão utilizadas pelo arquivo de cabeçalho (_header.php) para renderizar a página.
$pagina_atual = 'produtos'; // Define a aba/página ativa na barra de navegação lateral ou superior
$titulo_pagina = 'Editar Produto'; // Define o título exibido na aba do navegador

// Inclui o arquivo de cabeçalho padrão da aplicação
include '../_header.php';
?>

<!-- Container principal da página de edição de produto -->
<div class="content">
    <!-- Cabeçalho interno da página contendo o título e botão de voltar -->
    <div class="page-header">
        <div class="page-header-left">
            <h2>Editar Produto</h2>
            <!-- Exibe de forma segura o nome do produto que está sendo editado usando htmlspecialchars para evitar ataques XSS -->
            <p>Atualize as configurações e informações cadastrais de: <strong><?= htmlspecialchars($p['nome']) ?></strong></p>
        </div>
        <!-- Link de retorno para a listagem geral de produtos -->
        <a href="index.php" class="btn btn-secondary">← Voltar para Catálogo</a>
    </div>

    <!-- Se houver alguma mensagem de erro definida, exibe um alerta vermelho para o usuário com a descrição do erro -->
    <?php if($erro): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($erro) ?></div>
    <?php endif; ?>

    <!-- Card contendo o formulário de edição do produto -->
    <div class="form-card">
        <!-- O formulário envia os dados via POST para editar.php passando o id do produto na query string -->
        <form method="POST" action="editar.php?id=<?= $id ?>" autocomplete="off">
            <!-- Grid de layout para organizar os campos do formulário de maneira responsiva -->
            <div class="form-grid">
                
                <!-- Campo para preenchimento e consulta do Código de Barras (EAN) -->
                <div class="form-group">
                    <label for="codigo_barras">Código de Barras (EAN / Barcode)</label>
                    <div style="display: flex; gap: 8px;">
                        <!-- O valor padrão do campo de texto é preenchido com o valor enviado por POST anteriormente (se houver erro), ou com o valor salvo no banco de dados, ou vazio -->
                        <input type="text" name="codigo_barras" id="codigo_barras" placeholder="Ex: 7891000100103" value="<?= htmlspecialchars($_POST['codigo_barras'] ?? $p['codigo_barras'] ?? '') ?>" style="flex: 1;">
                        <!-- Botão que aciona uma busca via JavaScript à API pública Open Food Facts -->
                        <button type="button" id="btn-consulta-ean" class="btn btn-secondary" style="padding: 0 14px; height: 44px; white-space: nowrap;" title="Autopreencher nome via API Open Food Facts">🔍 Buscar EAN</button>
                    </div>
                </div>

                <!-- Campo de seleção da Unidade de Medida -->
                <div class="form-group">
                    <label for="unidade">Unidade de Medida *</label>
                    <select name="unidade" id="unidade" required>
                        <!-- Laço de repetição que cria as opções de unidade de medida comuns em restaurantes/estoques -->
                        <?php foreach(['kg','g','L','ml','UN','cx','pct','saco'] as $u): ?>
                            <!-- Verifica se a opção atual corresponde ao valor anteriormente enviado por POST ou ao valor original do produto para marcá-la como 'selected' -->
                            <option value="<?= $u ?>" <?= (isset($_POST['unidade']) && $_POST['unidade'] === $u) || (!isset($_POST['unidade']) && $p['unidade'] === $u) ? 'selected' : '' ?>>
                                <?= $u ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Campo de texto para o Nome do Produto, ocupando a largura total (classe 'full') na grid de campos -->
                <div class="form-group full">
                    <label for="nome">Nome do Produto *</label>
                    <!-- Preenche o valor com a submissão anterior ou com o valor salvo no banco -->
                    <input type="text" name="nome" id="nome" value="<?= htmlspecialchars($_POST['nome'] ?? $p['nome']) ?>" required>
                </div>

                <!-- Campo de seleção da Categoria do Produto -->
                <div class="form-group">
                    <label for="categoria_id">Categoria</label>
                    <select name="categoria_id" id="categoria_id">
                        <option value="">— Selecione uma categoria —</option>
                        <!-- Percorre a lista de categorias buscadas no banco de dados -->
                        <?php foreach($categorias as $c): ?>
                            <!-- Verifica se a categoria percorrida é a mesma que foi selecionada no POST ou a que está salva no banco de dados para marcá-la como 'selected' -->
                            <option value="<?= $c['id'] ?>" <?= (isset($_POST['categoria_id']) && $_POST['categoria_id'] == $c['id']) || (!isset($_POST['categoria_id']) && $p['categoria_id'] == $c['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['nome']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Campo numérico para definir o Estoque Mínimo do Produto, aceitando valores decimais (step=0.01) e mínimo de 0 -->
                <div class="form-group">
                    <label for="estoque_minimo">Estoque Mínimo (Alerta de Baixo Estoque)</label>
                    <input type="number" name="estoque_minimo" id="estoque_minimo" step="0.01" min="0" value="<?= htmlspecialchars($_POST['estoque_minimo'] ?? $p['estoque_minimo']) ?>">
                </div>

                <!-- Campo numérico para definir o Estoque Máximo do Produto, aceitando valores decimais (step=0.01) e mínimo de 0 -->
                <div class="form-group">
                    <label for="estoque_maximo">Estoque Máximo Recomendado</label>
                    <input type="number" name="estoque_maximo" id="estoque_maximo" step="0.01" min="0" value="<?= htmlspecialchars($_POST['estoque_maximo'] ?? $p['estoque_maximo']) ?>">
                </div>

                <!-- Campo numérico para definir o Custo Unitário Padrão do Produto, aceitando valores decimais (step=0.01) e mínimo de 0 -->
                <div class="form-group">
                    <label for="custo_unitario">Custo Unitário Padrão (R$)</label>
                    <input type="number" name="custo_unitario" id="custo_unitario" step="0.01" min="0" value="<?= htmlspecialchars($_POST['custo_unitario'] ?? $p['custo_unitario']) ?>">
                </div>
            </div>

            <!-- Botões de ação do formulário: Enviar/Salvar e Cancelar/Voltar -->
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Salvar Alterações</button>
                <a href="index.php" class="btn btn-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>

<script>
// Integração com a API pública Open Food Facts para buscar dados do produto a partir do código de barras
document.getElementById('btn-consulta-ean').addEventListener('click', function() {
    // Recupera o valor do campo de código de barras, removendo qualquer caractere que não seja número (\D)
    const barcode = document.getElementById('codigo_barras').value.replace(/\D/g, '');
    
    // Se o código de barras estiver vazio, exibe um alerta e cancela a consulta
    if (!barcode) {
        alert('Por favor, digite um código de barras para consultar.');
        return;
    }
    
    // Obtém o elemento do botão de consulta
    const btn = document.getElementById('btn-consulta-ean');
    // Salva o conteúdo HTML original do botão para poder restaurá-lo depois
    const oldHtml = btn.innerHTML;
    // Modifica o texto do botão indicando que a consulta está carregando
    btn.innerHTML = '⏳...';
    // Desabilita o botão para evitar cliques duplicados durante a requisição assíncrona
    btn.disabled = true;

    // Faz uma requisição fetch assíncrona para a API v2 do Open Food Facts usando o código de barras
    fetch(`https://world.openfoodfacts.org/api/v2/product/${barcode}.json`)
        .then(response => {
            // Se a resposta HTTP não for bem-sucedida, lança um erro
            if (!response.ok) throw new Error('Produto não localizado ou falha de conexão.');
            // Converte a resposta em formato JSON
            return response.json();
        })
        .then(data => {
            // Se o status retornado pela API for 1 (produto encontrado) e o objeto do produto existir
            if (data.status === 1 && data.product) {
                const prod = data.product;
                // Tenta recuperar o nome do produto prioritariamente em português, depois em inglês, ou o nome geral
                let name = prod.product_name_pt || prod.product_name || prod.product_name_en || '';
                // Recupera a marca do produto, pegando o primeiro nome se houver mais de uma marca separada por vírgula
                const brand = prod.brands ? prod.brands.split(',')[0].trim() : '';
                
                // Se a marca e o nome forem localizados, combina-os no formato: "Marca - Nome"
                if (brand && name) {
                    name = `${brand} - ${name}`;
                }
                
                // Se o nome foi encontrado/construído, atribui ao valor do campo 'nome' do formulário
                if (name) {
                    document.getElementById('nome').value = name;
                } else {
                    alert('Código de barras encontrado, mas o nome do produto está em branco.');
                }
            } else {
                // Se o status retornado for diferente de 1, informa que o produto não consta na base de dados da API
                alert('Produto não encontrado na base do Open Food Facts.');
            }
        })
        .catch(err => {
            // Captura erros de rede ou processamento e exibe em um alerta para o usuário
            alert('Falha na consulta: ' + err.message);
        })
        .finally(() => {
            // Bloco que é sempre executado ao fim da requisição (seja sucesso ou falha).
            // Restaura o texto original do botão e o habilita novamente para interações.
            btn.innerHTML = oldHtml;
            btn.disabled = false;
        });
});
</script>

<?php 
// Inclui o arquivo de rodapé padrão da aplicação
include '../_footer.php'; 
?>