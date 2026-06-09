<?php
session_start();
if(!isset($_SESSION['logado']) || !$_SESSION['logado']){
    header('Location: ../index.php');
    exit;
}
require_once '../conexao.php';

$erro = '';

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    // Sanitize and normalize inputs
    $nome     = trim($_POST['nome'] ?? '');
    $cnpj     = preg_replace('/\D/', '', $_POST['cnpj'] ?? '');
    $telefone = trim($_POST['telefone'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $produtos = trim($_POST['produtos_fornecidos'] ?? '');

    if(!$nome){
        $erro = 'A razão social / nome fantasia é obrigatória.';
    } else {
        // Validate name uniqueness
        $checkName = $conexao->prepare("SELECT COUNT(*) FROM fornecedores WHERE LOWER(nome) = LOWER(:nome)");
        $checkName->execute([':nome' => $nome]);
        
        if ($checkName->fetchColumn() > 0) {
            $erro = 'Já existe um fornecedor cadastrado com este nome/razão social.';
        } elseif ($cnpj !== '') {
            // Validate CNPJ uniqueness if provided
            $checkCnpj = $conexao->prepare("SELECT COUNT(*) FROM fornecedores WHERE cnpj = :cnpj");
            $checkCnpj->execute([':cnpj' => $cnpj]);
            
            if ($checkCnpj->fetchColumn() > 0) {
                $erro = 'Este CNPJ já está cadastrado para outro fornecedor.';
            }
        }

        if (!$erro) {
            try {
                $stmt = $conexao->prepare("
                    INSERT INTO fornecedores (nome, cnpj, telefone, email, produtos_fornecidos, ativo)
                    VALUES (:nome, :cnpj, :tel, :email, :prod, 1)
                ");
                $stmt->execute([
                    ':nome'  => $nome,
                    ':cnpj'  => $cnpj ?: null,
                    ':tel'   => $telefone ?: null,
                    ':email' => $email ?: null,
                    ':prod'  => $produtos ?: null
                ]);

                header('Location: index.php?msg=criado');
                exit;
            } catch (Exception $e) {
                $erro = 'Erro ao cadastrar fornecedor: ' . $e->getMessage();
            }
        }
    }
}

$pagina_atual = 'fornecedores';
$titulo_pagina = 'Novo Fornecedor';
include '../_header.php';
?>

<div class="content">
    <div class="page-header">
        <div class="page-header-left">
            <h2>Cadastrar Novo Fornecedor</h2>
            <p>Adicione um parceiro comercial ou distribuidor de suprimentos no sistema.</p>
        </div>
        <a href="index.php" class="btn btn-secondary">← Voltar para Listagem</a>
    </div>

    <?php if($erro): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($erro) ?></div>
    <?php endif; ?>

    <div class="form-card">
        <form method="POST" action="novo.php" autocomplete="off">
            <div class="form-grid">
                <div class="form-group">
                    <label for="cnpj">CNPJ (Apenas números ou formatado)</label>
                    <div style="display: flex; gap: 8px;">
                        <input type="text" name="cnpj" id="cnpj" placeholder="00.000.000/0000-00" maxlength="18" value="<?= htmlspecialchars($_POST['cnpj'] ?? '') ?>" style="flex: 1;">
                        <button type="button" id="btn-consulta-cnpj" class="btn btn-secondary" style="padding: 0 14px; height: 44px; white-space: nowrap;" title="Autopreencher dados usando a API BrasilAPI">🔍 Buscar API</button>
                    </div>
                </div>

                <div class="form-group">
                    <label for="telefone">Telefone / WhatsApp</label>
                    <input type="text" name="telefone" id="telefone" placeholder="Ex: (11) 99999-9999" value="<?= htmlspecialchars($_POST['telefone'] ?? '') ?>">
                </div>

                <div class="form-group full">
                    <label for="nome">Razão Social / Nome Fantasia *</label>
                    <input type="text" name="nome" id="nome" placeholder="Ex: Distribuidora Silva de Alimentos Ltda" value="<?= htmlspecialchars($_POST['nome'] ?? '') ?>" required>
                </div>

                <div class="form-group full">
                    <label for="email">E-mail de Contato Comercial</label>
                    <input type="email" name="email" id="email" placeholder="Ex: comercial@silva.com.br" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                </div>

                <div class="form-group full">
                    <label for="produtos_fornecidos">Produtos Fornecidos (Insumos / Categorias)</label>
                    <textarea name="produtos_fornecidos" id="produtos_fornecidos" placeholder="Descreva os produtos que este fornecedor distribui (ex: Laticínios, Queijos, Leite, etc.)"><?= htmlspecialchars($_POST['produtos_fornecidos'] ?? '') ?></textarea>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Cadastrar Fornecedor</button>
                <a href="index.php" class="btn btn-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>

<script>
// Simple CNPJ input formatting mask
function aplicarMascaraCNPJ(el) {
    let x = el.value.replace(/\D/g, '').match(/(\d{0,2})(\d{0,3})(\d{0,3})(\d{0,4})(\d{0,2})/);
    el.value = !x[2] ? x[1] : x[1] + '.' + x[2] + '.' + x[3] + '/' + x[4] + (x[5] ? '-' + x[5] : '');
}

const cnpjEl = document.getElementById('cnpj');
cnpjEl.addEventListener('input', function () {
    aplicarMascaraCNPJ(cnpjEl);
});

// BrasilAPI CNPJ Integration
document.getElementById('btn-consulta-cnpj').addEventListener('click', function() {
    const cnpj = cnpjEl.value.replace(/\D/g, '');
    if (cnpj.length !== 14) {
        alert('Por favor, digite um CNPJ válido com 14 dígitos para consultar.');
        return;
    }
    
    const btn = document.getElementById('btn-consulta-cnpj');
    const oldHtml = btn.innerHTML;
    btn.innerHTML = '⏳...';
    btn.disabled = true;

    fetch(`https://brasilapi.com.br/api/cnpj/v1/${cnpj}`)
        .then(response => {
            if (!response.ok) {
                throw new Error('Fornecedor não cadastrado na base da Receita Federal ou API indisponível.');
            }
            return response.json();
        })
        .then(data => {
            // Populate fields
            if (data.razao_social) {
                document.getElementById('nome').value = data.razao_social;
            } else if (data.nome_fantasia) {
                document.getElementById('nome').value = data.nome_fantasia;
            }
            
            if (data.ddd_telefone_1) {
                let tel = data.ddd_telefone_1.replace(/\D/g, '');
                if (tel.length === 10) {
                    tel = `(${tel.substring(0,2)}) ${tel.substring(2,6)}-${tel.substring(6)}`;
                } else if (tel.length === 11) {
                    tel = `(${tel.substring(0,2)}) ${tel.substring(2,7)}-${tel.substring(7)}`;
                }
                document.getElementById('telefone').value = tel;
            }
            
            if (data.email) {
                document.getElementById('email').value = data.email.toLowerCase();
            }
        })
        .catch(err => {
            alert('Falha na consulta: ' + err.message);
        })
        .finally(() => {
            btn.innerHTML = oldHtml;
            btn.disabled = false;
        });
});
</script>

<?php include '../_footer.php'; ?>