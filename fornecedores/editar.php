<?php
session_start();
if(!isset($_SESSION['logado']) || !$_SESSION['logado']){
    header('Location: ../index.php');
    exit;
}
require_once '../conexao.php';

$id = intval($_GET['id'] ?? 0);
if (!$id) {
    header('Location: index.php');
    exit;
}

// Fetch the supplier to edit
$stmt = $conexao->prepare("SELECT * FROM fornecedores WHERE id = :id");
$stmt->execute([':id' => $id]);
$f = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$f) {
    header('Location: index.php?erro=nao_encontrado');
    exit;
}

$erro = '';

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    // Sanitize and normalize inputs
    $nome     = trim($_POST['nome'] ?? '');
    $cnpj     = preg_replace('/\D/', '', $_POST['cnpj'] ?? '');
    $telefone = trim($_POST['telefone'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $produtos = trim($_POST['produtos_fornecidos'] ?? '');
    $ativo    = intval($_POST['ativo'] ?? 1);

    if(!$nome){
        $erro = 'A razão social / nome fantasia é obrigatória.';
    } else {
        // Validate name uniqueness excluding self
        $checkName = $conexao->prepare("SELECT COUNT(*) FROM fornecedores WHERE LOWER(nome) = LOWER(:nome) AND id != :id");
        $checkName->execute([':nome' => $nome, ':id' => $id]);
        
        if ($checkName->fetchColumn() > 0) {
            $erro = 'Já existe outro fornecedor cadastrado com este nome/razão social.';
        } elseif ($cnpj !== '') {
            // Validate CNPJ uniqueness excluding self
            $checkCnpj = $conexao->prepare("SELECT COUNT(*) FROM fornecedores WHERE cnpj = :cnpj AND id != :id");
            $checkCnpj->execute([':cnpj' => $cnpj, ':id' => $id]);
            
            if ($checkCnpj->fetchColumn() > 0) {
                $erro = 'Este CNPJ já está cadastrado para outro fornecedor.';
            }
        }

        if (!$erro) {
            try {
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
                $stmtUpdate->execute([
                    ':nome'  => $nome,
                    ':cnpj'  => $cnpj ?: null,
                    ':tel'   => $telefone ?: null,
                    ':email' => $email ?: null,
                    ':prod'  => $produtos ?: null,
                    ':ativo' => $ativo,
                    ':id'    => $id
                ]);

                header('Location: index.php?msg=editado');
                exit;
            } catch (Exception $e) {
                $erro = 'Erro ao atualizar fornecedor: ' . $e->getMessage();
            }
        }
    }
}

$pagina_atual = 'fornecedores';
$titulo_pagina = 'Editar Fornecedor';
include '../_header.php';
?>

<div class="content">
    <div class="page-header">
        <div class="page-header-left">
            <h2>Editar Fornecedor</h2>
            <p>Atualize as informações de cadastro e contato do fornecedor: <strong><?= htmlspecialchars($f['nome']) ?></strong></p>
        </div>
        <a href="index.php" class="btn btn-secondary">← Voltar para Listagem</a>
    </div>

    <?php if($erro): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($erro) ?></div>
    <?php endif; ?>

    <div class="form-card">
        <form method="POST" action="editar.php?id=<?= $id ?>" autocomplete="off">
            <div class="form-grid">
                <div class="form-group">
                    <label for="cnpj">CNPJ (Apenas números ou formatado)</label>
                    <div style="display: flex; gap: 8px;">
                        <input type="text" name="cnpj" id="cnpj" placeholder="00.000.000/0000-00" maxlength="18" value="<?= htmlspecialchars($_POST['cnpj'] ?? $f['cnpj']) ?>" style="flex: 1;">
                        <button type="button" id="btn-consulta-cnpj" class="btn btn-secondary" style="padding: 0 14px; height: 44px; white-space: nowrap;" title="Autopreencher dados usando a API BrasilAPI">🔍 Buscar API</button>
                    </div>
                </div>

                <div class="form-group">
                    <label for="telefone">Telefone / WhatsApp</label>
                    <input type="text" name="telefone" id="telefone" value="<?= htmlspecialchars($_POST['telefone'] ?? $f['telefone']) ?>">
                </div>

                <div class="form-group full">
                    <label for="nome">Razão Social / Nome Fantasia *</label>
                    <input type="text" name="nome" id="nome" value="<?= htmlspecialchars($_POST['nome'] ?? $f['nome']) ?>" required>
                </div>

                <div class="form-group full">
                    <label for="email">E-mail de Contato Comercial</label>
                    <input type="email" name="email" id="email" value="<?= htmlspecialchars($_POST['email'] ?? $f['email']) ?>">
                </div>

                <div class="form-group full">
                    <label for="produtos_fornecidos">Produtos Fornecidos (Insumos / Categorias)</label>
                    <textarea name="produtos_fornecidos" id="produtos_fornecidos"><?= htmlspecialchars($_POST['produtos_fornecidos'] ?? $f['produtos_fornecidos']) ?></textarea>
                </div>

                <div class="form-group">
                    <label for="ativo">Status de Operação</label>
                    <select name="ativo" id="ativo">
                        <option value="1" <?= (isset($_POST['ativo']) && $_POST['ativo'] == 1) || (!isset($_POST['ativo']) && $f['ativo'] == 1) ? 'selected' : '' ?>>Ativo (Disponível para compras)</option>
                        <option value="0" <?= (isset($_POST['ativo']) && $_POST['ativo'] == 0) || (!isset($_POST['ativo']) && $f['ativo'] == 0) ? 'selected' : '' ?>>Inativo (Bloqueado/Sem atividades)</option>
                    </select>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Salvar Alterações</button>
                <a href="index.php" class="btn btn-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>

<script>
// Simple CNPJ input formatting mask helper
function formatarCampoCNPJ(el) {
    let x = el.value.replace(/\D/g, '').match(/(\d{0,2})(\d{0,3})(\d{0,3})(\d{0,4})(\d{0,2})/);
    el.value = !x[2] ? x[1] : x[1] + '.' + x[2] + '.' + x[3] + '/' + x[4] + (x[5] ? '-' + x[5] : '');
}

const cnpjEl = document.getElementById('cnpj');
cnpjEl.addEventListener('input', function () {
    formatarCampoCNPJ(cnpjEl);
});

// Format on load
document.addEventListener('DOMContentLoaded', () => {
    formatarCampoCNPJ(cnpjEl);
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