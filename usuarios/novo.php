<?php
session_start();
if(!isset($_SESSION['logado']) || !$_SESSION['logado']){
    header('Location: ../index.php');
    exit;
}
require_once '../conexao.php';

// Fetch available roles (perfis)
$perfis = $conexao->query("SELECT id, nome FROM perfis ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
$erro = '';

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    // Sanitize inputs
    $nome        = trim($_POST['nome'] ?? '');
    $email       = strtolower(trim($_POST['email'] ?? ''));
    $senha       = $_POST['senha'] ?? '';
    $perfil_id   = !empty($_POST['perfil_id']) ? intval($_POST['perfil_id']) : null;
    $restaurante = trim($_POST['restaurante'] ?? 'Restaurante Premium');

    if(!$nome || !$email || !$senha || !$perfil_id){
        $erro = 'Nome completo, e-mail, senha e perfil de acesso são obrigatórios.';
    } elseif (strlen($senha) < 6) {
        $erro = 'A senha deve possuir no mínimo 6 caracteres.';
    } else {
        // Validate email uniqueness
        $check = $conexao->prepare("SELECT COUNT(*) FROM usuarios WHERE email = :email");
        $check->execute([':email' => $email]);
        
        if ($check->fetchColumn() > 0) {
            $erro = 'Este endereço de e-mail já está cadastrado para outro colaborador.';
        } else {
            try {
                $stmt = $conexao->prepare("
                    INSERT INTO usuarios (nome, email, senha, perfil_id, restaurante, status)
                    VALUES (:nome, :email, :senha, :pid, :rest, 'ativo')
                ");
                $stmt->execute([
                    ':nome'  => $nome,
                    ':email' => $email,
                    ':senha' => password_hash($senha, PASSWORD_DEFAULT),
                    ':pid'   => $perfil_id,
                    ':rest'  => $restaurante
                ]);

                header('Location: index.php?msg=criado');
                exit;
            } catch (Exception $e) {
                $erro = 'Erro ao cadastrar novo colaborador: ' . $e->getMessage();
            }
        }
    }
}

$pagina_atual = 'usuarios';
$titulo_pagina = 'Novo Colaborador';
include '../_header.php';
?>

<div class="content">
    <div class="page-header">
        <div class="page-header-left">
            <h2>Cadastrar Novo Colaborador</h2>
            <p>Adicione um novo usuário e configure seu perfil de acesso e privilégios no sistema.</p>
        </div>
        <a href="index.php" class="btn btn-secondary">← Voltar para Listagem</a>
    </div>

    <?php if($erro): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($erro) ?></div>
    <?php endif; ?>

    <div class="form-card">
        <form method="POST" action="novo.php" autocomplete="off">
            <div class="form-grid">
                <div class="form-group full">
                    <label for="nome">Nome Completo *</label>
                    <input type="text" name="nome" id="nome" placeholder="Ex: João da Silva" value="<?= htmlspecialchars($_POST['nome'] ?? '') ?>" required>
                </div>

                <div class="form-group">
                    <label for="email">Endereço de E-mail *</label>
                    <input type="email" name="email" id="email" placeholder="Ex: joao@restaurante.com.br" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                </div>

                <div class="form-group">
                    <label for="senha">Senha de Acesso *</label>
                    <input type="password" name="senha" id="senha" placeholder="Mínimo 6 caracteres" required>
                </div>

                <div class="form-group">
                    <label for="perfil_id">Perfil de Acesso *</label>
                    <select name="perfil_id" id="perfil_id" required>
                        <option value="">— Selecione o perfil —</option>
                        <?php foreach($perfis as $pf): ?>
                            <option value="<?= $pf['id'] ?>" <?= (isset($_POST['perfil_id']) && $_POST['perfil_id'] == $pf['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($pf['nome']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="restaurante">Estabelecimento / Restaurante</label>
                    <input type="text" name="restaurante" id="restaurante" value="<?= htmlspecialchars($_POST['restaurante'] ?? 'Restaurante Premium') ?>">
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Cadastrar Usuário</button>
                <a href="index.php" class="btn btn-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>

<?php include '../_footer.php'; ?>