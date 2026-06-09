<?php
// Inicia a sessão PHP para verificar a autenticação e gerenciar a sessão ativa do usuário
session_start();

// Verifica se a variável de sessão 'logado' não está configurada ou é falsa.
// Redireciona o usuário para a página inicial (raiz) caso não esteja logado, impedindo o acesso.
if (!isset($_SESSION['logado']) || !$_SESSION['logado']) {
    header('Location: ../index.php'); // Redireciona para o login
    exit; // Finaliza o processamento da página
}

// Inclui o arquivo de conexão PDO com o banco de dados ($conexao)
require_once '../conexao.php';

// Mecanismo de auto-recuperação (Self-healing) do banco de dados:
// Tenta consultar a coluna 'codigo_barras' para validar sua existência na tabela 'produtos'.
try {
    $conexao->query("SELECT codigo_barras FROM produtos LIMIT 1");
} catch (Exception $e) {
    // Se a consulta lançar uma exceção (a coluna não existe), adiciona a coluna codigo_barras via comando SQL ALTER TABLE.
    try {
        $conexao->query("ALTER TABLE produtos ADD COLUMN codigo_barras VARCHAR(50) NULL UNIQUE");
    } catch (Exception $ex) {
        // Ignora qualquer erro no fallback para não quebrar a página de cadastro
    }
}

// Busca todas as categorias no banco de dados, ordenando-as pelo nome em ordem alfabética para preencher o campo dropdown.
$categorias = $conexao->query("SELECT id, nome FROM categorias ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);

// Inicializa a variável de controle de exibição de erros
$erro = '';

// Verifica se a requisição atual é do tipo POST (envio do formulário de cadastro)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Obtém e sanitiza as variáveis do formulário vindas pelo POST:
    // Remove espaços no início e final do nome do produto.
    $nome = trim($_POST['nome'] ?? '');
    
    // Se o código de barras for preenchido, limpa espaços em branco; senão, define como NULL.
    $codigo_barras = !empty($_POST['codigo_barras']) ? trim($_POST['codigo_barras']) : null;
    
    // Obtém o ID da categoria (inteiro) se houver seleção, caso contrário armazena NULL.
    $cat_id = !empty($_POST['categoria_id']) ? intval($_POST['categoria_id']) : null;
    
    // Unidade de medida padrão, limpa espaços. Padrão: 'kg'
    $unidade = trim($_POST['unidade'] ?? 'kg');
    
    // Converte o valor de estoque mínimo para float. Padrão: 0
    $est_min = floatval($_POST['estoque_minimo'] ?? 0);
    
    // Converte o valor de estoque máximo para float. Padrão: 0
    $est_max = floatval($_POST['estoque_maximo'] ?? 0);
    
    // Converte o valor de custo unitário padrão para float. Padrão: 0
    $custo = floatval($_POST['custo_unitario'] ?? 0);

    // Validações do lado do servidor:
    // O nome é um campo obrigatório.
    if (!$nome) {
        $erro = 'O nome do produto é obrigatório.';
    } 
    // O estoque mínimo não deve superar o estoque máximo se este último for maior que zero.
    elseif ($est_max > 0 && $est_min > $est_max) {
        $erro = 'O estoque mínimo não pode ser maior que o estoque máximo.';
    } else {
        
        // Verifica no banco de dados se já existe algum produto cadastrado com o mesmo nome (ignorando maiúsculas e minúsculas)
        $check = $conexao->prepare("SELECT COUNT(*) FROM produtos WHERE LOWER(nome) = LOWER(:nome)");
        $check->execute([':nome' => $nome]);

        // Inicializa a variável para verificação de duplicidade de código de barras
        $checkBar = 0;
        // Se um código de barras foi informado, verifica se ele já está em uso por outro produto
        if ($codigo_barras !== null) {
            $stmtBar = $conexao->prepare("SELECT COUNT(*) FROM produtos WHERE codigo_barras = :bar");
            $stmtBar->execute([':bar' => $codigo_barras]);
            $checkBar = $stmtBar->fetchColumn(); // Retorna o número de produtos com este código de barras
        }

        // Se o nome do produto já estiver cadastrado no banco, define erro.
        if ($check->fetchColumn() > 0) {
            $erro = 'Já existe um produto cadastrado com este nome.';
        } 
        // Se o código de barras informado já estiver em uso, define erro de duplicidade.
        elseif ($checkBar > 0) {
            $erro = 'Este código de barras já está cadastrado para outro produto.';
        } else {
            
            // Determina o status inicial do estoque com base no estoque mínimo definido.
            // Como o produto está sendo cadastrado agora, o estoque inicial é sempre zero (0.00).
            $status = 'Normal';
            if ($est_min > 0) {
                // Se o estoque mínimo for maior que zero, o estoque de 0.00 se enquadra como 'Crítico' (abaixo do mínimo).
                $status = 'Crítico';
            }

            try {
                // Constrói a query SQL para inserção de um novo produto no banco.
                // O campo estoque_atual é definido diretamente como 0.00 (valor fixo inicial).
                $sql = "INSERT INTO produtos (nome, categoria_id, unidade, estoque_minimo, estoque_maximo, custo_unitario, status, estoque_atual, codigo_barras)
                        VALUES (:nome, :cat, :un, :emin, :emax, :custo, :status, 0.00, :bar)";

                // Prepara a query no banco de dados
                $stmt = $conexao->prepare($sql);
                
                // Executa passando todos os valores correspondentes aos placeholders
                $stmt->execute([
                    ':nome' => $nome,
                    ':cat' => $cat_id,
                    ':un' => $unidade,
                    ':emin' => $est_min,
                    ':emax' => $est_max,
                    ':custo' => $custo,
                    ':status' => $status,
                    ':bar' => $codigo_barras
                ]);

                // Após o sucesso no cadastro, redireciona o usuário para a listagem principal com parâmetro de mensagem de sucesso
                header('Location: index.php?msg=criado');
                exit; // Finaliza o script pós-redirecionamento
            } catch (Exception $e) {
                // Captura e armazena mensagem de erro em caso de falhas de banco de dados
                $erro = 'Erro ao cadastrar produto: ' . $e->getMessage();
            }
        }
    }
}

// Configura as variáveis de navegação e título para a página
$pagina_atual = 'produtos';
$titulo_pagina = 'Novo Produto';

// Inclui o cabeçalho padrão
include '../_header.php';
?>

<!-- Container principal da página de cadastro de produtos -->
<div class="content">
    
    <!-- Cabeçalho contendo o título da página e botão de voltar -->
    <div class="page-header">
        <div class="page-header-left">
            <h2>Cadastrar Novo Produto</h2>
            <p>Adicione um novo ingrediente, bebida ou insumo ao catálogo de estoque.</p>
        </div>
        <a href="index.php" class="btn btn-secondary">← Voltar para Catálogo</a>
    </div>

    <!-- Se houver algum erro de validação ou de banco de dados, exibe a mensagem de alerta vermelha -->
    <?php if ($erro): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($erro) ?></div>
    <?php endif; ?>

    <!-- Card contendo o formulário de cadastro -->
    <div class="form-card">
        <!-- O formulário envia dados via POST para a própria página 'nova.php' -->
        <form method="POST" action="nova.php" autocomplete="off">
            <!-- Grid de organização visual para os campos do formulário -->
            <div class="form-grid">

                <!-- Campo do código de barras EAN com funcionalidade de busca automática -->
                <div class="form-group">
                    <label for="codigo_barras">Código de Barras (EAN / Barcode)</label>
                    <div style="display: flex; gap: 8px;">
                        <!-- Armazena o código de barras digitado, mantendo o valor caso ocorra erro no envio -->
                        <input type="text" name="codigo_barras" id="codigo_barras" placeholder="Ex: 7891000100103"
                            value="<?= htmlspecialchars($_POST['codigo_barras'] ?? '') ?>" style="flex: 1;">
                        <!-- Botão que executa a consulta via API externa -->
                        <button type="button" id="btn-consulta-ean" class="btn btn-secondary"
                            style="padding: 0 14px; height: 44px; white-space: nowrap;"
                            title="Autopreencher nome via API Open Food Facts">🔍 Buscar EAN</button>
                    </div>
                </div>

                <!-- Campo para seleção da Unidade de Medida -->
                <div class="form-group">
                    <label for="unidade">Unidade de Medida *</label>
                    <select name="unidade" id="unidade" required>
                        <!-- Itera sobre as unidades de medida suportadas -->
                        <?php foreach (['kg', 'g', 'L', 'ml', 'UN', 'cx', 'pct', 'saco'] as $u): ?>
                            <!-- Seleciona por padrão o 'kg' ou a unidade previamente enviada pelo usuário no POST -->
                            <option value="<?= $u ?>" <?= (isset($_POST['unidade']) && $_POST['unidade'] === $u) || (!isset($_POST['unidade']) && $u === 'kg') ? 'selected' : '' ?>>
                                <?= $u ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Campo para preenchimento do Nome do Produto, ocupando a largura total da linha (classe 'full') -->
                <div class="form-group full">
                    <label for="nome">Nome do Produto *</label>
                    <input type="text" name="nome" id="nome" placeholder="Ex: Filé Mignon Fresco"
                        value="<?= htmlspecialchars($_POST['nome'] ?? '') ?>" required>
                </div>

                <!-- Campo de seleção de Categoria -->
                <div class="form-group">
                    <label for="categoria_id">Categoria</label>
                    <select name="categoria_id" id="categoria_id">
                        <option value="">— Selecione uma categoria —</option>
                        <!-- Itera sobre todas as categorias buscadas no banco de dados para popular as opções -->
                        <?php foreach ($categorias as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= (isset($_POST['categoria_id']) && $_POST['categoria_id'] == $c['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['nome']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Campo para preenchimento do estoque mínimo com valor padrão 0.00 -->
                <div class="form-group">
                    <label for="estoque_minimo">Estoque Mínimo (Alerta de Baixo Estoque)</label>
                    <input type="number" name="estoque_minimo" id="estoque_minimo" step="0.01" min="0"
                        placeholder="0,00" value="<?= htmlspecialchars($_POST['estoque_minimo'] ?? '0.00') ?>">
                </div>

                <!-- Campo para preenchimento do estoque máximo recomendado com valor padrão 0.00 -->
                <div class="form-group">
                    <label for="estoque_maximo">Estoque Máximo Recomendado</label>
                    <input type="number" name="estoque_maximo" id="estoque_maximo" step="0.01" min="0"
                        placeholder="0,00" value="<?= htmlspecialchars($_POST['estoque_maximo'] ?? '0.00') ?>">
                </div>

                <!-- Campo para preenchimento do custo unitário do produto com valor padrão 0.00 -->
                <div class="form-group">
                    <label for="custo_unitario">Custo Unitário Padrão (R$)</label>
                    <input type="number" name="custo_unitario" id="custo_unitario" step="0.01" min="0"
                        placeholder="0,00" value="<?= htmlspecialchars($_POST['custo_unitario'] ?? '0.00') ?>">
                </div>
            </div>

            <!-- Seção de botões de controle para Envio e Cancelamento do formulário -->
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Cadastrar Produto</button>
                <a href="index.php" class="btn btn-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>

<script>
    // Adiciona escuta ao clique do botão de consulta de código de barras
    document.getElementById('btn-consulta-ean').addEventListener('click', function () {
        // Recupera o valor do campo e extrai todos os caracteres não numéricos
        const barcode = document.getElementById('codigo_barras').value.replace(/\D/g, '');
        
        // Alerta o usuário caso o campo esteja em branco
        if (!barcode) { alert('Digite um código de barras.'); return; }

        const btn = document.getElementById('btn-consulta-ean');
        const oldHtml = btn.innerHTML;
        // Modifica visualmente o botão indicando a espera
        btn.innerHTML = '⏳...';
        // Desativa o botão para evitar requisições múltiplas
        btn.disabled = true;

        // Efetua a requisição fetch na API internacional Open Food Facts para buscar dados do produto cadastrado
        fetch(`https://world.openfoodfacts.org/api/v2/product/${barcode}.json`)
            .then(r => r.json()) // Converte a resposta em JSON
            .then(data => {
                // Se a API retornar que o produto foi localizado (status === 1)
                if (data.status === 1 && data.product) {
                    const prod = data.product;

                    // Busca o nome do produto priorizando português, depois nome geral ou inglês
                    let name = prod.product_name_pt || prod.product_name || prod.product_name_en || '';
                    // Extrai a primeira marca listada no produto
                    const brand = prod.brands ? prod.brands.split(',')[0].trim() : '';
                    // Se houver marca e nome, junta-os para formar uma string descritiva
                    if (brand && name) name = `${brand} - ${name}`;
                    // Preenche o campo de texto do Nome do produto com a informação encontrada
                    if (name) document.getElementById('nome').value = name;

                    // Tenta adivinhar a Unidade de medida correta com base na quantidade descrita na API
                    const quantity = (prod.quantity || '').toLowerCase();
                    const unidade = document.getElementById('unidade');
                    if (quantity.includes('ml')) unidade.value = 'ml';
                    else if (quantity.includes(' l') || quantity.endsWith('l')) unidade.value = 'L';
                    else if (quantity.includes('kg')) unidade.value = 'kg';
                    else if (quantity.includes('g')) unidade.value = 'g';

                    // Tenta categorizar o produto automaticamente varrendo as categorias da API e o nome do produto
                    const tags = (prod.categories || '').toLowerCase();
                    const nomeP = name.toLowerCase();
                    const selectCat = document.getElementById('categoria_id');
                    
                    // Mapeamento de termos-chave em português e inglês para associar a categorias do banco
                    const mapa = {
                        'Massas': ['pasta', 'spaghetti', 'macarrao', 'espaguete', 'noodle', 'lasanha', 'talharim'],
                        'Carnes': ['meat', 'carne', 'frango', 'bovina', 'suina', 'peixe', 'aves', 'salsicha', 'linguica', 'bacon'],
                        'Laticínios': ['dairy', 'laticin', 'leite', 'queijo', 'iogurte', 'manteiga', 'creme', 'requeijao'],
                        'Vegetais': ['vegeta', 'legume', 'hortalica', 'alface', 'tomate', 'cebola', 'alho', 'brocolis'],
                        'Frutas': ['fruit', 'fruta', 'banana', 'maca', 'laranja', 'morango', 'uva'],
                        'Grãos': ['grain', 'cereal', 'arroz', 'feijao', 'milho', 'trigo', 'aveia', 'lentilha', 'graos'],
                        'Óleos': ['oil', 'azeite', 'oleo', 'gordura'],
                        'Bebidas': ['beverage', 'bebida', 'suco', 'refrigerante', 'agua', 'cafe', 'cha', 'achocolatado'],
                        'Temperos': ['spice', 'tempero', 'condiment', 'pimenta', 'oregano', 'colorau', 'canela', 'erva'],
                        'Limpeza': ['cleaning', 'limpeza', 'detergente', 'sabao', 'desinfetante'],
                        'Doces e Açúcar': ['sugar','acucar','açúcar','refinado','demerara','mascavo','mel','geleia','doce','chocolate']
                    };

                    // Varre o mapeamento para ver se encontra algum termo nas tags ou no nome do produto
                    for (const [cat, keywords] of Object.entries(mapa)) {
                        if (keywords.some(k => tags.includes(k) || nomeP.includes(k))) {
                            // Se encontrou termo, percorre as opções do select de categoria para selecionar a correspondente
                            for (let opt of selectCat.options) {
                                if (opt.text.toLowerCase().includes(cat.toLowerCase())) {
                                    selectCat.value = opt.value; // Define o valor selecionado no select
                                    break;
                                }
                            }
                            break; // Sai do laço de categorias
                        }
                    }

                } else {
                    // Exibe alerta caso o produto não seja localizado no banco de dados da API
                    alert('Produto não encontrado no Open Food Facts.');
                }
            })
            // Exibe mensagem genérica em caso de falha de conexão ou erro interno no fetch
            .catch(() => alert('Erro na consulta. Verifique sua conexão.'))
            .finally(() => {
                // Bloco executado sempre, restaurando o botão à sua forma ativa original
                btn.innerHTML = oldHtml;
                btn.disabled = false;
            });
    });
</script>

<?php 
// Inclui o arquivo de rodapé padrão da página
include '../_footer.php'; 
?>