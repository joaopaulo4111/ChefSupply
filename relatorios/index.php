<?php
// Inicia a sessão PHP para verificar a autenticação e gerenciar a sessão ativa do usuário
session_start();

// Verifica se a variável de sessão 'logado' não está definida ou é falsa.
// Caso o usuário não esteja logado, ele é redirecionado para a página de login raiz e a execução do script é interrompida.
if(!isset($_SESSION['logado']) || !$_SESSION['logado']){
    header('Location: ../index.php'); // Redirecionamento de segurança
    exit; // Encerra a execução do código PHP
}

// Requer o arquivo de conexão com o banco de dados (estabelece a variável $conexao utilizando PDO)
require_once '../conexao.php';

// Configura as variáveis globais que serão utilizadas pelo arquivo de cabeçalho (_header.php) para renderizar a página.
$pagina_atual = 'relatorios'; // Identifica o menu ativo na navegação
$titulo_pagina = 'Central de Relatórios'; // Define o título na aba do navegador

// Inclui o cabeçalho padrão da aplicação
include '../_header.php';
?>

<!-- Estilos CSS específicos para a página de Central de Relatórios -->
<style>
    /* Estilo do container que envolve o grid de cartões de relatórios */
    .reports-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 20px;
        margin-top: 10px;
    }
    /* Estilo individual para cada cartão (card) de relatório */
    .report-card {
        background: #fff;
        border: 1px solid #e8e8e8;
        border-radius: 12px;
        padding: 28px 24px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.02);
        transition: all 0.2s;
    }
    /* Efeito visual de hover ao passar o cursor do mouse sobre o cartão */
    .report-card:hover {
        transform: translateY(-4px); /* Desloca o card levemente para cima */
        box-shadow: 0 8px 24px rgba(0,0,0,0.06); /* Aumenta a intensidade da sombra */
        border-color: #2db35d; /* Altera a cor da borda para verde */
    }
    /* Alinhamento do cabeçalho do cartão de relatório */
    .report-header {
        display: flex;
        align-items: center;
        gap: 16px;
        margin-bottom: 20px;
    }
    /* Estilo do container do ícone representativo dentro do cabeçalho do cartão */
    .report-icon {
        width: 52px;
        height: 52px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        flex-shrink: 0;
    }
    /* Estilo do texto do título principal do cartão */
    .report-title-text h3 {
        font-size: 1.1rem;
        font-weight: 700;
        color: #1a1a1a;
    }
    /* Estilo do subtexto (categoria/tipo) do cartão */
    .report-title-text span {
        font-size: 0.72rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        font-weight: 600;
        color: #888;
    }
    /* Estilo para a descrição textual do relatório */
    .report-body {
        font-size: 0.875rem;
        color: #666;
        line-height: 1.5;
        margin-bottom: 24px;
        flex-grow: 1; /* Permite que o corpo do texto cresça para preencher o espaço restante */
    }
    /* Alinhamento do rodapé do cartão que contém o botão de ação */
    .report-footer {
        display: flex;
        justify-content: flex-end;
    }

    /* Regras de responsividade para telas menores (computadores pequenos/tablets) */
    @media(max-width: 900px) {
        .reports-grid {
            grid-template-columns: repeat(2, 1fr); /* Altera o grid para exibir 2 colunas */
        }
    }
    /* Regras de responsividade para telas pequenas (celulares) */
    @media(max-width: 600px) {
        .reports-grid {
            grid-template-columns: 1fr; /* Altera o grid para exibir apenas 1 coluna */
        }
    }
</style>

<!-- Container principal da interface do usuário -->
<div class="content">
    
    <!-- Seção de cabeçalho da página -->
    <div class="page-header">
        <div class="page-header-left">
            <h2>Central de Relatórios</h2>
            <p>Selecione uma das opções abaixo para auditar movimentações, controlar o vencimento de lotes e prever compras de reposição.</p>
        </div>
    </div>

    <!-- Layout em grid contendo os cartões (cards) de acesso aos relatórios específicos -->
    <div class="reports-grid">
        
        <!-- Cartão de Relatório 1: Estoque Baixo & Crítico -->
        <div class="report-card">
            <div>
                <!-- Cabeçalho contendo ícone vermelho e títulos descritivos -->
                <div class="report-header">
                    <div class="report-icon" style="background: #fee2e2; color: #dc2626;">⚠️</div>
                    <div class="report-title-text">
                        <span>Alerta</span>
                        <h3>Estoque Baixo & Crítico</h3>
                    </div>
                </div>
                <!-- Corpo textual com a explicação da finalidade deste relatório -->
                <div class="report-body">
                    Lista detalhada de todos os insumos e ingredientes que estão com o saldo atual abaixo do estoque mínimo configurado. Útil para planejar compras imediatas de reposição e evitar desabastecimento na cozinha.
                </div>
            </div>
            <!-- Rodapé com botão de link direto para gerar o relatório correspondente -->
            <div class="report-footer">
                <a href="estoque_baixo.php" class="btn btn-primary btn-sm" style="width: 100%;">Gerar Relatório</a>
            </div>
        </div>

        <!-- Cartão de Relatório 2: Movimentações do Período -->
        <div class="report-card">
            <div>
                <!-- Cabeçalho contendo ícone azul e títulos descritivos -->
                <div class="report-header">
                    <div class="report-icon" style="background: #eff6ff; color: #2563eb;">📊</div>
                    <div class="report-title-text">
                        <span>Histórico</span>
                        <h3>Movimentações do Período</h3>
                    </div>
                </div>
                <!-- Corpo descritivo do relatório de histórico de entradas e saídas -->
                <div class="report-body">
                    Extrato cronológico consolidado de todas as entradas de mercadoria por fornecedor e saídas (descartes por vencimento, avaria ou perdas de produção) dentro de um intervalo de datas customizado.
                </div>
            </div>
            <!-- Rodapé com botão de link direto para gerar o relatório correspondente -->
            <div class="report-footer">
                <a href="movimentacoes.php" class="btn btn-primary btn-sm" style="width: 100%;">Gerar Relatório</a>
            </div>
        </div>

        <!-- Cartão de Relatório 3: Validade de Lotes -->
        <div class="report-card">
            <div>
                <!-- Cabeçalho contendo ícone laranja e títulos descritivos -->
                <div class="report-header">
                    <div class="report-icon" style="background: #fff7ed; color: #ea580c;">⏳</div>
                    <div class="report-title-text">
                        <span>Validade</span>
                        <h3>Validade de Lotes</h3>
                    </div>
                </div>
                <!-- Corpo descritivo explicativo sobre regras de controle do PVPS (PEPS) -->
                <div class="report-body">
                    Auditoria completa dos lotes ativos armazenados no estoque. Permite visualizar rapidamente quais produtos estão vencidos ou com vencimento próximo (próximos 7 a 30 dias), organizados para aplicação da regra PVPS (Primeiro que Vence, Primeiro que Sai).
                </div>
            </div>
            <!-- Rodapé com botão que redireciona para a aba de estoque filtrando pelos lotes vencendo/vencidos -->
            <div class="report-footer">
                <a href="../estoque/index.php?situacao=vencendo" class="btn btn-primary btn-sm" style="width: 100%;">Visualizar Lotes</a>
            </div>
        </div>

    </div>
</div>

<?php 
// Inclui o arquivo de rodapé padrão da página para fechar tags HTML comuns
include '../_footer.php'; 
?>