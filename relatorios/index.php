<?php
session_start();
if(!isset($_SESSION['logado']) || !$_SESSION['logado']){
    header('Location: ../index.php');
    exit;
}
require_once '../conexao.php';

$pagina_atual = 'relatorios';
$titulo_pagina = 'Central de Relatórios';
include '../_header.php';
?>

<!-- Injecting page-specific styling -->
<style>
    .reports-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 20px;
        margin-top: 10px;
    }
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
    .report-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 24px rgba(0,0,0,0.06);
        border-color: #2db35d;
    }
    .report-header {
        display: flex;
        align-items: center;
        gap: 16px;
        margin-bottom: 20px;
    }
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
    .report-title-text h3 {
        font-size: 1.1rem;
        font-weight: 700;
        color: #1a1a1a;
    }
    .report-title-text span {
        font-size: 0.72rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        font-weight: 600;
        color: #888;
    }
    .report-body {
        font-size: 0.875rem;
        color: #666;
        line-height: 1.5;
        margin-bottom: 24px;
        flex-grow: 1;
    }
    .report-footer {
        display: flex;
        justify-content: flex-end;
    }

    @media(max-width: 900px) {
        .reports-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }
    @media(max-width: 600px) {
        .reports-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="content">
    <!-- Page Header -->
    <div class="page-header">
        <div class="page-header-left">
            <h2>Central de Relatórios</h2>
            <p>Selecione uma das opções abaixo para auditar movimentações, controlar o vencimento de lotes e prever compras de reposição.</p>
        </div>
    </div>

    <!-- Reports Grid Layout -->
    <div class="reports-grid">
        
        <!-- Report 1: Estoque Baixo -->
        <div class="report-card">
            <div>
                <div class="report-header">
                    <div class="report-icon" style="background: #fee2e2; color: #dc2626;">⚠️</div>
                    <div class="report-title-text">
                        <span>Alerta</span>
                        <h3>Estoque Baixo & Crítico</h3>
                    </div>
                </div>
                <div class="report-body">
                    Lista detalhada de todos os insumos e ingredientes que estão com o saldo atual abaixo do estoque mínimo configurado. Útil para planejar compras imediatas de reposição e evitar desabastecimento na cozinha.
                </div>
            </div>
            <div class="report-footer">
                <a href="estoque_baixo.php" class="btn btn-primary btn-sm" style="width: 100%;">Gerar Relatório</a>
            </div>
        </div>

        <!-- Report 2: Movimentações -->
        <div class="report-card">
            <div>
                <div class="report-header">
                    <div class="report-icon" style="background: #eff6ff; color: #2563eb;">📊</div>
                    <div class="report-title-text">
                        <span>Histórico</span>
                        <h3>Movimentações do Período</h3>
                    </div>
                </div>
                <div class="report-body">
                    Extrato cronológico consolidado de todas as entradas de mercadoria por fornecedor e saídas (descartes por vencimento, avaria ou perdas de produção) dentro de um intervalo de datas customizado.
                </div>
            </div>
            <div class="report-footer">
                <a href="movimentacoes.php" class="btn btn-primary btn-sm" style="width: 100%;">Gerar Relatório</a>
            </div>
        </div>

        <!-- Report 3: Vencimentos de Lotes -->
        <div class="report-card">
            <div>
                <div class="report-header">
                    <div class="report-icon" style="background: #fff7ed; color: #ea580c;">⏳</div>
                    <div class="report-title-text">
                        <span>Validade</span>
                        <h3>Validade de Lotes</h3>
                    </div>
                </div>
                <div class="report-body">
                    Auditoria completa dos lotes ativos armazenados no estoque. Permite visualizar rapidamente quais produtos estão vencidos ou com vencimento próximo (próximos 7 a 30 dias), organizados para aplicação da regra PVPS (Primeiro que Vence, Primeiro que Sai).
                </div>
            </div>
            <div class="report-footer">
                <a href="../estoque/index.php?situacao=vencendo" class="btn btn-primary btn-sm" style="width: 100%;">Visualizar Lotes</a>
            </div>
        </div>

    </div>
</div>

<?php include '../_footer.php'; ?>