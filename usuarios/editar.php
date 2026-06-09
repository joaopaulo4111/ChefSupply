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

// Fetch the user record
$stmt = $conexao->prepare("SELECT * FROM usuarios WHERE id = :id");
$stmt->execute([':id' => $id]);
$u = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$u) {
    header('Location: index.php?erro=nao_encontrado');
    exit;
}

// Fetch available roles (perfis)
$perfis = $conexao->query("SELECT id, nome FROM perfis ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
$erro = '';

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    // Sanitize inputs
    $nome        = trim($_POST['nome'] ?? '');
    $email       = strtolower(trim($_POST['email'] ?? ''));
    $perfil_id   = !empty($_POST['perfil_id']) ? intval($_POST['perfil_id']) : null;
    $restaurante = trim($_POST['restaurante'] ?? '');
    $status      = trim($_POST['status'] ?? 'ativo');
    $senha       = $_POST['senha'] ?? '';

    if(!$nome || !$email || !$perfil_id){
        $erro = 'Nome completo, e-mail e perfil de acesso são obrigatórios.';
    } elseif ($senha !== '' && strlen($senha) < 6) {
        $erro = 'A nova senha deve possuir no mínimo 6 caracteres.';
    } elseif ($id === intval($_SESSION['usuario_id'] ?? 0) && $status === 'inativo') {
        // Prevent self desactivation
        $erro = 'Segurança: Não é permitido desativar/bloquear a sua própria conta ativa em uso.';
    } else {
        // Validate email uniqueness excluding self
        $check = $conexao->prepare("SELECT COUNT(*) FROM usuarios WHERE email = :email AND id != :id");
        $check->execute([':email' => $email, ':id' => $id]);
        
        if ($check->fetchColumn() > 0) {
            $erro = 'Este endereço de e-mail já está cadastrado para outro colaborador.';
        } else {
            try {
                if ($senha !== '') {
                    // Update with password change
                    $stmtUpdate = $conexao->prepare("
                        UPDATE usuarios 
                        SET nome = :nome, 
                            email = :email, 
                            senha = :senha, 
                            perfil_id = :pid, 
                            restaurante = :rest, 
                            status = :status 
                        WHERE id = :id
                    ");
                    $stmtUpdate->execute([
                        ':nome'   => $nome,
                        ':email'  => $email,
                        ':senha'  => password_hash($senha, PASSWORD_DEFAULT),
                        ':pid'    => $perfil_id,
                        ':rest'   => $restaurante,
                        ':status' => $status,
                        ':id'     => $id
                    ]);
                } else {
                    // Update without password change
                    $stmtUpdate = $conexao->prepare("
                        UPDATE usuarios 
                        SET nome = :nome, 
                            email = :email, 
                            perfil_id = :pid, 
                            restaurante = :rest, 
                            status = :status 
                        WHERE id = :id
                    ");
                    $stmtUpdate->execute([
                        ':nome'   => $nome,
                        ':email'  => $email,
                        ':pid'    => $perfil_id,
                        ':rest'   => $restaurante,
                        ':status' => $status,
                        ':id'     => $id
                    ]);
                }

                // If editing self, update active session name for immediate header change
                if ($id === intval($_SESSION['usuario_id'] ?? 0)) {
                    $_SESSION['usuario_nome'] = $nome;
                }

                header('Location: index.php?msg=editado');
                exit;
            } catch (Exception $e) {
                $erro = 'Erro ao atualizar informações do colaborador: ' . $e->getMessage();
            }
        }
    }
}

$pagina_atual = 'usuarios';
$titulo_pagina = 'Editar Colaborador';
include '../_header.php';
?>

<div class="content">
    <div class="page-header">
        <div class="page-header-left">
            <h2>Editar Colaborador</h2>
            <p>Atualize as permissões de acesso, dados de contato ou troque a senha do colaborador: <strong><?= htmlspecialchars($u['nome']) ?></strong></p>
        </div>
        <a href="index.php" class="btn btn-secondary">← Voltar para Listagem</a>
    </div>

    <?php if($erro): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($erro) ?></div>
    <?php endif; ?>

    <div class="form-card">
        <form method="POST" action="editar.php?id=<?= $id ?>" autocomplete="off">
            <div class="form-grid">
                <div class="form-group full">
                    <label for="nome">Nome Completo *</label>
                    <input type="text" name="nome" id="nome" value="<?= htmlspecialchars($_POST['nome'] ?? $u['nome']) ?>" required>
                </div>

                <div class="form-group">
                    <label for="email">Endereço de E-mail *</label>
                    <input type="email" name="email" id="email" value="<?= htmlspecialchars($_POST['email'] ?? $u['email']) ?>" required>
                </div>

                <div class="form-group">
                    <label for="senha">Nova Senha <small style="color: #888;">(Deixe em branco para manter a atual)</small></label>
                    <input type="password" name="senha" id="senha" placeholder="••••••••">
                </div>

                <div class="form-group">
                    <label for="perfil_id">Perfil de Acesso *</label>
                    <select name="perfil_id" id="perfil_id" required>
                        <?php foreach($perfis as $pf): ?>
                            <option value="<?= $pf['id'] ?>" <?= (isset($_POST['perfil_id']) && $_POST['perfil_id'] == $pf['id']) || (!isset($_POST['perfil_id']) && $u['perfil_id'] == $pf['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($pf['nome']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="status">Situação da Conta</label>
                    <select name="status" id="status">
                        <option value="ativo" <?= (isset($_POST['status']) && $_POST['status'] === 'ativo') || (!isset($_POST['status']) && $u['status'] === 'ativo') ? 'selected' : '' ?>>Ativo (Acesso autorizado)</option>
                        <option value="inativo" <?= (isset($_POST['status']) && $_POST['status'] === 'inativo') || (!isset($_POST['status']) && $u['status'] === 'inativo') ? 'selected' : '' ?>>Inativo (Bloqueado)</option>
                    </select>
                </div>

                <div class="form-group full">
                    <label for="restaurante">Estabelecimento / Restaurante</label>
                    <input type="text" name="restaurante" id="restaurante" value="<?= htmlspecialchars($_POST['restaurante'] ?? $u['restaurante'] ?? '') ?>">
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Salvar Alterações</button>
                <a href="index.php" class="btn btn-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>

<?php include '../_footer.php'; ?>