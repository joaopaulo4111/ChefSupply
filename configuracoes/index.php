<?php
session_start();
if(!isset($_SESSION['logado']) || !$_SESSION['logado']){
    header('Location: ../index.php');
    exit;
}
require_once '../conexao.php';

// Safe configuration upsert helper function
function salvarConfig($conexao, $chave, $valor) {
    $stmt = $conexao->prepare("
        INSERT INTO configuracoes (chave, valor) 
        VALUES (:chave, :valor) 
        ON DUPLICATE KEY UPDATE valor = :valor_update
    ");
    $stmt->execute([
        ':chave'        => $chave,
        ':valor'        => $valor,
        ':valor_update' => $valor
    ]);
}

$msg = '';

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    // Read and sanitize inputs
    $nome_restaurante   = trim($_POST['nome_restaurante'] ?? 'Restaurante Premium');
    $alerta_dias_padrao = intval($_POST['alerta_dias_padrao'] ?? 3);

    // Save configurations
    salvarConfig($conexao, 'nome_restaurante', $nome_restaurante);
    salvarConfig($conexao, 'alerta_dias_padrao', strval($alerta_dias_padrao));

    $msg = 'salvo';
}

// Fetch current configurations
$configs = $conexao->query("SELECT chave, valor FROM configuracoes")->fetchAll(PDO::FETCH_KEY_PAIR);

$pagina_atual = 'configuracoes';
$titulo_pagina = 'Configurações do Sistema';
include '../_header.php';
?>

<!-- Injecting page-specific styling -->
<style>
    .grid-2 {
        display: grid;
        grid-template-columns: 1.2fr 1fr;
        gap: 24px;
        align-items: start;
    }
    .config-card {
        margin-bottom: 24px;
    }
    .section-title {
        font-size: 1rem;
        font-weight: 600;
        margin-bottom: 20px;
        padding-bottom: 12px;
        border-bottom: 1px solid #f0f0f0;
        color: #1a1a1a;
    }
    .admin-links {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }
    .admin-links .btn {
        justify-content: flex-start;
        text-align: left;
    }
    .info-table {
        width: 100%;
        border-collapse: collapse;
    }
    .info-table td {
        padding: 12px 0;
        font-size: 0.875rem;
        border-bottom: 1px solid #f7f7f7;
    }
    .info-table td:first-child {
        color: #666;
        font-weight: 500;
    }
    .info-table td:last-child {
        font-weight: 600;
        text-align: right;
        color: #1a1a1a;
    }
    .info-table tr:last-child td {
        border-bottom: none;
    }

    @media(max-width: 900px) {
        .grid-2 {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="content">
    <!-- Page Header -->
    <div class="page-header">
        <div class="page-header-left">
            <h2>Configurações Gerais</h2>
            <p>Ajuste os parâmetros globais de funcionamento do sistema e gerencie cadastros auxiliares.</p>
        </div>
    </div>

    <!-- Feedback Alerts -->
    <?php if($msg === 'salvo'): ?>
        <div class="alert alert-success">✅ Configurações salvas com sucesso!</div>
    <?php endif; ?>

    <div class="grid-2">
        
        <!-- General Settings Form -->
        <div class="form-card config-card">
            <div class="section-title">⚙️ Configurações Gerais</div>
            <form method="POST" action="index.php">
                <div class="form-group" style="margin-bottom: 20px;">
                    <label for="nome_restaurante">Nome do Restaurante</label>
                    <input type="text" name="nome_restaurante" id="nome_restaurante" placeholder="Ex: ChefSupply Bistro" value="<?= htmlspecialchars($configs['nome_restaurante'] ?? 'Restaurante Premium') ?>" required>
                </div>
                <div class="form-group" style="margin-bottom: 24px;">
                    <label for="alerta_dias_padrao">Dias de Alerta de Vencimento (Padrão)</label>
                    <input type="number" name="alerta_dias_padrao" id="alerta_dias_padrao" min="1" max="60" value="<?= htmlspecialchars($configs['alerta_dias_padrao'] ?? '3') ?>" required>
                    <small style="color: #666; font-size: 0.8rem; margin-top: 4px;">Dias de antecedência recomendados para avisar sobre vencimento de lotes no estoque.</small>
                </div>
                <button type="submit" class="btn btn-primary">Salvar Configurações</button>
            </form>
        </div>

        <div>
            <!-- Administration Quick Links -->
            <div class="form-card config-card">
                <div class="section-title">🗂️ Tabelas Auxiliares e Permissões</div>
                <div class="admin-links">
                    <a href="../categorias/index.php" class="btn btn-secondary">📋 Gerenciar Categorias</a>
                    <a href="../usuarios/index.php" class="btn btn-secondary">👤 Gerenciar Colaboradores (Usuários)</a>
                    <a href="../fornecedores/index.php" class="btn btn-secondary">🏭 Gerenciar Fornecedores</a>
                    <a href="../relatorios/index.php" class="btn btn-secondary">📈 Central de Relatórios</a>
                </div>
            </div>

            <!-- System Information -->
            <div class="form-card config-card">
                <div class="section-title">ℹ️ Informações da Aplicação</div>
                <table class="info-table">
                    <tbody>
                        <tr>
                            <td>Versão do Sistema</td>
                            <td><?= htmlspecialchars($configs['versao'] ?? '1.0.0') ?></td>
                        </tr>
                        <tr>
                            <td>Moeda Padrão</td>
                            <td>R$ (Real Brasileiro)</td>
                        </tr>
                        <tr>
                            <td>Operador Atual</td>
                            <td><?= htmlspecialchars($_SESSION['usuario_nome'] ?? 'Operador') ?></td>
                        </tr>
                        <tr>
                            <td>Servidor de Banco de Dados</td>
                            <td>MySQL 8.0 (localhost)</td>
                        </tr>
                        <tr>
                            <td>Data/Hora do Servidor</td>
                            <td><?= date('d/m/Y H:i') ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<?php include '../_footer.php'; ?>