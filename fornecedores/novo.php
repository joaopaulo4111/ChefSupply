<?php
// Inicia a sessão PHP para verificar a autenticação de login do usuário administrador
session_start();

// Verifica se a variável de sessão 'logado' não está definida ou não é verdadeira
if(!isset($_SESSION['logado']) || !$_SESSION['logado']){
    // Caso o usuário não esteja logado, redireciona para a tela inicial/login
    header('Location: ../index.php');
    // Aborta imediatamente a execução do script subsequente
    exit;
}

// Requer o arquivo de conexão com o banco de dados (PDO)
require_once '../conexao.php';

// Inicializa a variável $erro como uma string vazia para armazenar mensagens de erro de validação
$erro = '';

// Verifica se a requisição atual é do tipo POST, indicando que o formulário de cadastro foi submetido
if($_SERVER['REQUEST_METHOD'] === 'POST'){
    // Sanitização e normalização de variáveis recebidas via POST:
    // Remove espaços em branco do início e fim da Razão Social/Nome Fantasia
    $nome     = trim($_POST['nome'] ?? '');
    // Remove todos os caracteres não numéricos do CNPJ, mantendo apenas dígitos decimais
    $cnpj     = preg_replace('/\D/', '', $_POST['cnpj'] ?? '');
    // Remove espaços em branco do início e fim do telefone informado
    $telefone = trim($_POST['telefone'] ?? '');
    // Remove espaços em branco do início e fim do e-mail comercial
    $email    = trim($_POST['email'] ?? '');
    // Remove espaços em branco do início e fim da listagem de produtos fornecidos
    $produtos = trim($_POST['produtos_fornecidos'] ?? '');

    // Validação obrigatória: O nome fantasia/razão social deve ser informado
    if(!$nome){
        $erro = 'A razão social / nome fantasia é obrigatória.';
    } else {
        // Validação da unicidade do nome fantasia/razão social do fornecedor:
        // Prepara uma instrução para contar quantos fornecedores já existem com este nome (em minúsculas)
        $checkName = $conexao->prepare("SELECT COUNT(*) FROM fornecedores WHERE LOWER(nome) = LOWER(:nome)");
        // Executa a busca associando o parâmetro correspondente
        $checkName->execute([':nome' => $nome]);
        
        // Se a contagem for maior que 0, significa que o nome já foi cadastrado
        if ($checkName->fetchColumn() > 0) {
            $erro = 'Já existe um fornecedor cadastrado com este nome/razão social.';
        } elseif ($cnpj !== '') {
            // Se o CNPJ foi informado, valida a unicidade deste CNPJ na base de dados
            // Prepara a consulta SQL de contagem de correspondências
            $checkCnpj = $conexao->prepare("SELECT COUNT(*) FROM fornecedores WHERE cnpj = :cnpj");
            // Executa a verificação
            $checkCnpj->execute([':cnpj' => $cnpj]);
            
            // Se encontrar algum registro ativo com este CNPJ, gera o erro correspondente
            if ($checkCnpj->fetchColumn() > 0) {
                $erro = 'Este CNPJ já está cadastrado para outro fornecedor.';
            }
        }

        // Se nenhuma falha de validação ocorreu nos blocos de verificação anteriores:
        if (!$erro) {
            try {
                // Prepara a query SQL para inserir o novo fornecedor na tabela correspondente.
                // Todo novo fornecedor cadastrado por padrão inicia ativo (status 1).
                $stmt = $conexao->prepare("
                    INSERT INTO fornecedores (nome, cnpj, telefone, email, produtos_fornecidos, ativo)
                    VALUES (:nome, :cnpj, :tel, :email, :prod, 1)
                ");
                // Executa a inserção substituindo as variáveis sanitizadas.
                // Se o campo opcional estiver vazio, passa null para salvar nulo na base de dados.
                $stmt->execute([
                    ':nome'  => $nome,
                    ':cnpj'  => $cnpj ?: null,
                    ':tel'   => $telefone ?: null,
                    ':email' => $email ?: null,
                    ':prod'  => $produtos ?: null
                ]);

                // Após o sucesso no cadastro, redireciona o usuário para a listagem principal com mensagem de confirmação
                header('Location: index.php?msg=criado');
                // Encerra a execução deste script
                exit;
            } catch (Exception $e) {
                // Captura qualquer exceção do banco de dados e repassa a mensagem de erro
                $erro = 'Erro ao cadastrar fornecedor: ' . $e->getMessage();
            }
        }
    }
}

// Define o identificador da página atual para sinalizar no menu lateral
$pagina_atual = 'fornecedores';
// Define o título que será renderizado no header padrão
$titulo_pagina = 'Novo Fornecedor';
// Importa o arquivo de cabeçalho contendo a estrutura de layout e navegação
include '../_header.php';
?>

<!-- Container principal da página -->
<div class="content">
    <!-- Cabeçalho de Navegação -->
    <div class="page-header">
        <div class="page-header-left">
            <h2>Cadastrar Novo Fornecedor</h2>
            <p>Adicione um parceiro comercial ou distribuidor de suprimentos no sistema.</p>
        </div>
        <!-- Botão para voltar à visualização de listagem geral -->
        <a href="index.php" class="btn btn-secondary">← Voltar para Listagem</a>
    </div>

    <!-- Bloco de feedback visual para exibição de erros do formulário -->
    <?php if($erro): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($erro) ?></div>
    <?php endif; ?>

    <!-- Card contendo o formulário de cadastro -->
    <div class="form-card">
        <form method="POST" action="novo.php" autocomplete="off">
            <div class="form-grid">
                <!-- Entrada: CNPJ do Fornecedor e Ação de Consulta na API -->
                <div class="form-group">
                    <label for="cnpj">CNPJ (Apenas números ou formatado)</label>
                    <div style="display: flex; gap: 8px;">
                        <!-- O input carrega o valor digitado anteriormente em caso de recarga por erro de validação -->
                        <input type="text" name="cnpj" id="cnpj" placeholder="00.000.000/0000-00" maxlength="18" value="<?= htmlspecialchars($_POST['cnpj'] ?? '') ?>" style="flex: 1;">
                        <!-- Botão que executa a consulta CNPJ chamando a BrasilAPI -->
                        <button type="button" id="btn-consulta-cnpj" class="btn btn-secondary" style="padding: 0 14px; height: 44px; white-space: nowrap;" title="Autopreencher dados usando a API BrasilAPI">🔍 Buscar API</button>
                    </div>
                </div>

                <!-- Entrada: Telefone ou WhatsApp de Contato -->
                <div class="form-group">
                    <label for="telefone">Telefone / WhatsApp</label>
                    <input type="text" name="telefone" id="telefone" placeholder="Ex: (11) 99999-9999" value="<?= htmlspecialchars($_POST['telefone'] ?? '') ?>">
                </div>

                <!-- Entrada: Razão Social/Nome Fantasia (Obrigatório) -->
                <div class="form-group full">
                    <label for="nome">Razão Social / Nome Fantasia *</label>
                    <input type="text" name="nome" id="nome" placeholder="Ex: Distribuidora Silva de Alimentos Ltda" value="<?= htmlspecialchars($_POST['nome'] ?? '') ?>" required>
                </div>

                <!-- Entrada: E-mail Comercial do Fornecedor -->
                <div class="form-group full">
                    <label for="email">E-mail de Contato Comercial</label>
                    <input type="email" name="email" id="email" placeholder="Ex: comercial@silva.com.br" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                </div>

                <!-- Entrada: Descrição detalhada ou categorias de produtos fornecidos pelo parceiro -->
                <div class="form-group full">
                    <label for="produtos_fornecidos">Produtos Fornecidos (Insumos / Categorias)</label>
                    <textarea name="produtos_fornecidos" id="produtos_fornecidos" placeholder="Descreva os produtos que este fornecedor distribui (ex: Laticínios, Queijos, Leite, etc.)"><?= htmlspecialchars($_POST['produtos_fornecidos'] ?? '') ?></textarea>
                </div>
            </div>

            <!-- Botões de Ações Finais (Salvar e Cancelar) -->
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Cadastrar Fornecedor</button>
                <a href="index.php" class="btn btn-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>

<!-- Bloco de Javascript para controle da máscara de CNPJ e busca na BrasilAPI -->
<script>
// Função para aplicar máscara visual no campo CNPJ (ex: 00.000.000/0000-00)
function aplicarMascaraCNPJ(el) {
    let x = el.value.replace(/\D/g, '').match(/(\d{0,2})(\d{0,3})(\d{0,3})(\d{0,4})(\d{0,2})/);
    el.value = !x[2] ? x[1] : x[1] + '.' + x[2] + '.' + x[3] + '/' + x[4] + (x[5] ? '-' + x[5] : '');
}

// Vincula o input CNPJ e escuta eventos de digitação/alteração no campo
const cnpjEl = document.getElementById('cnpj');
cnpjEl.addEventListener('input', function () {
    aplicarMascaraCNPJ(cnpjEl);
});

// Evento de consulta de CNPJ externo integrado à BrasilAPI
document.getElementById('btn-consulta-cnpj').addEventListener('click', function() {
    // Remove formatação do CNPJ para deixar apenas números puros
    const cnpj = cnpjEl.value.replace(/\D/g, '');
    // Valida se o CNPJ possui os 14 dígitos regulamentares
    if (cnpj.length !== 14) {
        alert('Por favor, digite um CNPJ válido com 14 dígitos para consultar.');
        return;
    }
    
    // Altera o estado visual do botão indicando carregamento
    const btn = document.getElementById('btn-consulta-cnpj');
    const oldHtml = btn.innerHTML;
    btn.innerHTML = '⏳...';
    btn.disabled = true;

    // Efetua a busca na URL da API pública para o CNPJ informado
    fetch(`https://brasilapi.com.br/api/cnpj/v1/${cnpj}`)
        .then(response => {
            // Caso ocorra falha de resposta HTTP na requisição
            if (!response.ok) {
                throw new Error('Fornecedor não cadastrado na base da Receita Federal ou API indisponível.');
            }
            return response.json();
        })
        .then(data => {
            // Autopreenchimento inteligente nos inputs correspondentes
            // Escolhe razão social ou nome fantasia para preencher o campo Nome
            if (data.razao_social) {
                document.getElementById('nome').value = data.razao_social;
            } else if (data.nome_fantasia) {
                document.getElementById('nome').value = data.nome_fantasia;
            }
            
            // Caso possua telefone, processa a formatação e preenche no campo Telefone
            if (data.ddd_telefone_1) {
                let tel = data.ddd_telefone_1.replace(/\D/g, '');
                if (tel.length === 10) {
                    tel = `(${tel.substring(0,2)}) ${tel.substring(2,6)}-${tel.substring(6)}`;
                } else if (tel.length === 11) {
                    tel = `(${tel.substring(0,2)}) ${tel.substring(2,7)}-${tel.substring(7)}`;
                }
                document.getElementById('telefone').value = tel;
            }
            
            // Caso possua e-mail cadastrado, coloca-o em minúsculas e preenche no campo E-mail
            if (data.email) {
                document.getElementById('email').value = data.email.toLowerCase();
            }
        })
        .catch(err => {
            // Captura qualquer erro disparado nas promessas e avisa o usuário via alert
            alert('Falha na consulta: ' + err.message);
        })
        .finally(() => {
            // Restaura o botão de consulta ao estado padrão
            btn.innerHTML = oldHtml;
            btn.disabled = false;
        });
});
</script>

<!-- Inclui as tags de encerramento do layout principal e tags HTML -->
<?php include '../_footer.php'; ?>