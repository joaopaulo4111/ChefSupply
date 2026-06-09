<?php
// Inicia a sessão do PHP para permitir a verificação e manipulação de variáveis de login/sessão persistentes.
session_start();

// Verifica se a variável de sessão 'logado' não está definida ou é falsa.
// Caso o usuário não esteja devidamente autenticado no painel administrativo:
if(!isset($_SESSION['logado']) || !$_SESSION['logado']){
    // Redireciona o navegador do usuário para a página de login/inicial localizada na pasta raiz do projeto.
    header('Location: ../index.php');
    // Aborta de imediato a execução deste script para garantir a segurança dos dados.
    exit;
}

// Requer o arquivo de conexão com o banco de dados (que fornece a variável $conexao baseada em PDO).
// O require_once previne múltiplas inclusões do mesmo script na mesma requisição.
require_once '../conexao.php';

// Obtém o parâmetro 'id' enviado através da URL (método GET), convertendo-o para um número inteiro.
// Se o parâmetro não estiver definido, atribui o valor padrão de 0.
$id = intval($_GET['id'] ?? 0);

// Se o ID for inválido ou igual a zero (não fornecido):
if (!$id) {
    // Redireciona o usuário imediatamente para a página principal da listagem de fornecedores.
    header('Location: index.php');
    // Encerra a execução do script para que o redirecionamento ocorra com sucesso.
    exit;
}

// Prepara uma instrução SQL no banco de dados para buscar o fornecedor correspondente ao ID informado.
$stmt = $conexao->prepare("SELECT * FROM fornecedores WHERE id = :id");
// Executa a instrução preparada vinculando o valor do parâmetro :id de forma segura.
$stmt->execute([':id' => $id]);
// Recupera o registro retornado como um array associativo.
$f = $stmt->fetch(PDO::FETCH_ASSOC);

// Verifica se nenhum fornecedor com o ID fornecido foi encontrado no banco de dados.
if (!$f) {
    // Redireciona o usuário para a listagem enviando uma mensagem de erro na URL indicando "nao_encontrado".
    header('Location: index.php?erro=nao_encontrado');
    // Interrompe o processamento adicional da página.
    exit;
}

// Inicializa a variável $erro como uma string vazia para armazenar eventuais mensagens de validação mal sucedidas.
$erro = '';

// Verifica se o formulário foi submetido usando o método HTTP POST (atualização do registro).
if($_SERVER['REQUEST_METHOD'] === 'POST'){
    // Sanitização e normalização dos dados enviados pelo formulário:
    // Remove espaços vazios do início e fim do nome fantasia/razão social.
    $nome     = trim($_POST['nome'] ?? '');
    // Remove todos os caracteres não numéricos do CNPJ enviado (deixando apenas números).
    $cnpj     = preg_replace('/\D/', '', $_POST['cnpj'] ?? '');
    // Remove espaços vazios do início e fim do número de telefone.
    $telefone = trim($_POST['telefone'] ?? '');
    // Remove espaços vazios do início e fim do e-mail comercial.
    $email    = trim($_POST['email'] ?? '');
    // Remove espaços vazios do início e fim da descrição dos produtos fornecidos.
    $produtos = trim($_POST['produtos_fornecidos'] ?? '');
    // Converte o valor de atividade enviado para um número inteiro (1 para ativo, 0 para inativo).
    $ativo    = intval($_POST['ativo'] ?? 1);

    // Validação obrigatória: o fornecedor necessita possuir um nome ou razão social.
    if(!$nome){
        // Armazena a mensagem de erro a ser exibida no formulário.
        $erro = 'A razão social / nome fantasia é obrigatória.';
    } else {
        // Validação da unicidade do nome fantasia/razão social.
        // Prepara uma consulta para contar se já existe outro fornecedor com o mesmo nome (ignorando maiúsculas/minúsculas),
        // excluindo o próprio fornecedor atual da verificação (id != :id).
        $checkName = $conexao->prepare("SELECT COUNT(*) FROM fornecedores WHERE LOWER(nome) = LOWER(:nome) AND id != :id");
        // Executa passando os parâmetros correspondentes.
        $checkName->execute([':nome' => $nome, ':id' => $id]);
        
        // Se a contagem for maior que 0, significa que o nome já está sendo utilizado por outro cadastro.
        if ($checkName->fetchColumn() > 0) {
            $erro = 'Já existe outro fornecedor cadastrado com este nome/razão social.';
        } elseif ($cnpj !== '') {
            // Se o CNPJ foi informado, valida a unicidade dele no banco de dados.
            // Prepara a consulta de contagem ignorando o fornecedor atual (id != :id).
            $checkCnpj = $conexao->prepare("SELECT COUNT(*) FROM fornecedores WHERE cnpj = :cnpj AND id != :id");
            // Executa a busca pelo CNPJ informado.
            $checkCnpj->execute([':cnpj' => $cnpj, ':id' => $id]);
            
            // Se a contagem for maior que 0, significa que o CNPJ já está vinculado a outro fornecedor.
            if ($checkCnpj->fetchColumn() > 0) {
                $erro = 'Este CNPJ já está cadastrado para outro fornecedor.';
            }
        }

        // Se não houver nenhum erro de validação (nome e CNPJ válidos/únicos):
        if (!$erro) {
            try {
                // Prepara a instrução SQL de atualização dos dados do fornecedor no banco de dados.
                $stmtUpdate = $conexao->prepare("
                    UPDATE fornecedores 
                    SET nome = :nome, 
                        cnpj = :cnpj, 
                        telefone = :tel, 
                        email = :email, 
                        produtos_fornecidos = :prod, 
                        ativo = :ativo 
                    WHERE id = :id
                ");
                // Executa a instrução vinculando os valores. Se os campos opcionais estiverem vazios, 
                // insere valor nulo (null) no banco para manter a integridade dos dados.
                $stmtUpdate->execute([
                    ':nome'  => $nome,
                    ':cnpj'  => $cnpj ?: null, // Salva nulo caso a string esteja vazia
                    ':tel'   => $telefone ?: null, // Salva nulo caso a string esteja vazia
                    ':email' => $email ?: null, // Salva nulo caso a string esteja vazia
                    ':prod'  => $produtos ?: null, // Salva nulo caso a string esteja vazia
                    ':ativo' => $ativo,
                    ':id'    => $id
                ]);

                // Redireciona de volta para a index com uma mensagem de sucesso na URL indicando "editado".
                header('Location: index.php?msg=editado');
                // Finaliza a execução do script.
                exit;
            } catch (Exception $e) {
                // Em caso de falhas de banco ou conexões, captura a exceção e exibe a mensagem amigável de erro.
                $erro = 'Erro ao atualizar fornecedor: ' . $e->getMessage();
            }
        }
    }
}

// Define qual página está ativa para realce no menu de navegação lateral.
$pagina_atual = 'fornecedores';
// Define o título da página na tag title e nos cabeçalhos padrão.
$titulo_pagina = 'Editar Fornecedor';
// Carrega o layout e estrutura HTML comuns do cabeçalho da página.
include '../_header.php';
?>

<!-- Container principal de conteúdo da página -->
<div class="content">
    <!-- Cabeçalho de Navegação e Ações da Página -->
    <div class="page-header">
        <div class="page-header-left">
            <h2>Editar Fornecedor</h2>
            <!-- Exibe o nome atual do fornecedor que está sendo editado no subtítulo -->
            <p>Atualize as informações de cadastro e contato do fornecedor: <strong><?= htmlspecialchars($f['nome']) ?></strong></p>
        </div>
        <!-- Botão para retornar à página principal de listagem sem salvar alterações -->
        <a href="index.php" class="btn btn-secondary">← Voltar para Listagem</a>
    </div>

    <!-- Bloco condicional para exibição de mensagens de erro ocorridas no processamento -->
    <?php if($erro): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($erro) ?></div>
    <?php endif; ?>

    <!-- Card contendo o formulário de edição de dados do fornecedor -->
    <div class="form-card">
        <!-- O formulário aponta via POST para o próprio arquivo passando o ID do fornecedor na URL -->
        <form method="POST" action="editar.php?id=<?= $id ?>" autocomplete="off">
            <div class="form-grid">
                
                <!-- Campo de Entrada: CNPJ do Fornecedor -->
                <div class="form-group">
                    <label for="cnpj">CNPJ (Apenas números ou formatado)</label>
                    <div style="display: flex; gap: 8px;">
                        <!-- O input recebe o valor previamente enviado por POST (se houver erro) ou o valor original do banco de dados -->
                        <input type="text" name="cnpj" id="cnpj" placeholder="00.000.000/0000-00" maxlength="18" value="<?= htmlspecialchars($_POST['cnpj'] ?? $f['cnpj']) ?>" style="flex: 1;">
                        <!-- Botão com ação JS para consulta rápida na Receita Federal via BrasilAPI -->
                        <button type="button" id="btn-consulta-cnpj" class="btn btn-secondary" style="padding: 0 14px; height: 44px; white-space: nowrap;" title="Autopreencher dados usando a API BrasilAPI">🔍 Buscar API</button>
                    </div>
                </div>

                <!-- Campo de Entrada: Telefone ou WhatsApp de Contato -->
                <div class="form-group">
                    <label for="telefone">Telefone / WhatsApp</label>
                    <input type="text" name="telefone" id="telefone" value="<?= htmlspecialchars($_POST['telefone'] ?? $f['telefone']) ?>">
                </div>

                <!-- Campo de Entrada: Razão Social ou Nome Fantasia (Campo obrigatório marcado com asterisco) -->
                <div class="form-group full">
                    <label for="nome">Razão Social / Nome Fantasia *</label>
                    <input type="text" name="nome" id="nome" value="<?= htmlspecialchars($_POST['nome'] ?? $f['nome']) ?>" required>
                </div>

                <!-- Campo de Entrada: E-mail Comercial de Contato -->
                <div class="form-group full">
                    <label for="email">E-mail de Contato Comercial</label>
                    <input type="email" name="email" id="email" value="<?= htmlspecialchars($_POST['email'] ?? $f['email']) ?>">
                </div>

                <!-- Campo de Entrada: Lista de Insumos ou Categorias que o fornecedor comercializa -->
                <div class="form-group full">
                    <label for="produtos_fornecidos">Produtos Fornecidos (Insumos / Categorias)</label>
                    <textarea name="produtos_fornecidos" id="produtos_fornecidos"><?= htmlspecialchars($_POST['produtos_fornecidos'] ?? $f['produtos_fornecidos']) ?></textarea>
                </div>

                <!-- Campo de Seleção: Status Operacional do Fornecedor -->
                <div class="form-group">
                    <label for="ativo">Status de Operação</label>
                    <select name="ativo" id="ativo">
                        <!-- Compara o valor enviado por POST ou armazenado no banco para definir a opção selecionada correspondente -->
                        <option value="1" <?= (isset($_POST['ativo']) && $_POST['ativo'] == 1) || (!isset($_POST['ativo']) && $f['ativo'] == 1) ? 'selected' : '' ?>>Ativo (Disponível para compras)</option>
                        <option value="0" <?= (isset($_POST['ativo']) && $_POST['ativo'] == 0) || (!isset($_POST['ativo']) && $f['ativo'] == 0) ? 'selected' : '' ?>>Inativo (Bloqueado/Sem atividades)</option>
                    </select>
                </div>
            </div>

            <!-- Botões de Ações Finais do Formulário (Salvar e Cancelar) -->
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Salvar Alterações</button>
                <a href="index.php" class="btn btn-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>

<!-- Scripts em Javascript para melhorias na experiência de usuário (máscara de CNPJ e integração com API externa) -->
<script>
// Função auxiliar de formatação para aplicar máscara de CNPJ dinamicamente enquanto o usuário digita
function formatarCampoCNPJ(el) {
    // Remove qualquer caractere que não seja número e aplica a separação de pontos, barra e traço
    let x = el.value.replace(/\D/g, '').match(/(\d{0,2})(\d{0,3})(\d{0,3})(\d{0,4})(\d{0,2})/);
    // Reconstrói a string do input baseada nos matches de grupos numéricos encontrados
    el.value = !x[2] ? x[1] : x[1] + '.' + x[2] + '.' + x[3] + '/' + x[4] + (x[5] ? '-' + x[5] : '');
}

// Vincula o elemento CNPJ à variável cnpjEl
const cnpjEl = document.getElementById('cnpj');
// Adiciona um listener para escutar cada tecla ou caractere digitado no campo e aplicar a máscara em tempo real
cnpjEl.addEventListener('input', function () {
    formatarCampoCNPJ(cnpjEl);
});

// Aplica a formatação do CNPJ assim que o conteúdo DOM da página estiver completamente carregado
document.addEventListener('DOMContentLoaded', () => {
    formatarCampoCNPJ(cnpjEl);
});

// Integração de busca automatizada de CNPJ utilizando a API pública "BrasilAPI"
document.getElementById('btn-consulta-cnpj').addEventListener('click', function() {
    // Extrai o CNPJ digitado removendo a formatação e os caracteres não numéricos
    const cnpj = cnpjEl.value.replace(/\D/g, '');
    // Verifica se possui o tamanho exato de um CNPJ (14 dígitos)
    if (cnpj.length !== 14) {
        alert('Por favor, digite um CNPJ válido com 14 dígitos para consultar.');
        return;
    }
    
    // Altera o estado do botão para indicar processamento em andamento
    const btn = document.getElementById('btn-consulta-cnpj');
    const oldHtml = btn.innerHTML;
    btn.innerHTML = '⏳...';
    btn.disabled = true;

    // Faz uma requisição HTTP via Fetch API para o serviço da BrasilAPI
    fetch(`https://brasilapi.com.br/api/cnpj/v1/${cnpj}`)
        .then(response => {
            // Se a resposta HTTP for de erro (como CNPJ não encontrado ou indisponibilidade de rede), dispara um erro
            if (!response.ok) {
                throw new Error('Fornecedor não cadastrado na base da Receita Federal ou API indisponível.');
            }
            return response.json();
        })
        .then(data => {
            // Autopreenchimento inteligente dos dados no formulário baseado no retorno da API
            // Define o nome fantasia ou razão social prioritariamente
            if (data.razao_social) {
                document.getElementById('nome').value = data.razao_social;
            } else if (data.nome_fantasia) {
                document.getElementById('nome').value = data.nome_fantasia;
            }
            
            // Se o fornecedor possuir telefone cadastrado, formata o telefone (10 ou 11 dígitos) e insere no campo
            if (data.ddd_telefone_1) {
                let tel = data.ddd_telefone_1.replace(/\D/g, '');
                if (tel.length === 10) {
                    tel = `(${tel.substring(0,2)}) ${tel.substring(2,6)}-${tel.substring(6)}`;
                } else if (tel.length === 11) {
                    tel = `(${tel.substring(0,2)}) ${tel.substring(2,7)}-${tel.substring(7)}`;
                }
                document.getElementById('telefone').value = tel;
            }
            
            // Se possuir e-mail cadastrado, converte para minúsculas e insere no campo
            if (data.email) {
                document.getElementById('email').value = data.email.toLowerCase();
            }
        })
        .catch(err => {
            // Exibe mensagem de aviso caso ocorra alguma falha na requisição ou tratamento
            alert('Falha na consulta: ' + err.message);
        })
        .finally(() => {
            // Restaura o estado original do botão de consulta de CNPJ
            btn.innerHTML = oldHtml;
            btn.disabled = false;
        });
});
</script>

<!-- Inclui as tags de fechamento de corpo e scripts globais do painel -->
<?php include '../_footer.php'; ?>