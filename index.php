<!DOCTYPE html>
<!-- Define o tipo do documento como HTML5 e define o idioma da página como Português do Brasil -->
<html lang="pt-BR">

<head>
    <!-- Define a codificação de caracteres como UTF-8 para exibição correta de acentos e caracteres especiais -->
    <meta charset="UTF-8">
    
    <!-- Configura a janela de exibição (viewport) para garantir boa visualização em telas móveis (responsivo) -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- Título exibido na aba do navegador -->
    <title>ChefSupply — Login</title>
    
    <!-- Importação externa da fonte de texto 'Inter' a partir do serviço Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Seção de estilos em CSS internos para layout da página de autenticação/cadastro -->
    <style>
        /* Seleciona todos os elementos para resetar as margens, preenchimentos e definir box-sizing como border-box */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        /* Define as regras visuais do corpo da página (body), como imagem de fundo, centralização vertical e horizontal */
        body {
            background-image: url(fundo.jpg); /* Define a imagem 'fundo.jpg' como plano de fundo */
            background-repeat: no-repeat;      /* Evita a repetição da imagem de fundo */
            background-size: cover;            /* Redimensiona a imagem para cobrir toda a tela */
            background-position: center;        /* Centraliza a imagem na tela */
            min-height: 100vh;                 /* Garante que o corpo tenha no mínimo a altura total da janela do navegador */
            display: flex;                     /* Utiliza o layout flexbox para alinhar os elementos internos */
            align-items: center;               /* Centraliza o card de login verticalmente na tela */
            justify-content: center;           /* Centraliza o card de login horizontalmente na tela */
            font-family: 'Inter', sans-serif;  /* Aplica a fonte 'Inter' importada do Google Fonts */
        }

        /* Cria uma camada de sobreposição escura sobre a imagem de fundo para dar mais contraste ao formulário */
        body::before {
            content: '';
            position: fixed;
            inset: 0;                          /* Ocupa toda a extensão da janela */
            background: rgba(0, 0, 0, 0.45);   /* Cor preta com 45% de opacidade */
        }

        /* Estiliza o card principal onde os formulários de login e cadastro são exibidos */
        .card {
            position: relative;                /* Posicionamento relativo para ficar acima do pseudo-elemento do body */
            background: #fff;                  /* Cor de fundo branca */
            border-radius: 16px;               /* Cantos arredondados do card */
            padding: 32px 28px;                /* Espaçamento interno vertical e horizontal */
            width: 100%;                       /* Ocupa 100% da largura do elemento pai */
            max-width: 420px;                  /* Limita a largura máxima do card em 420px */
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3); /* Aplica uma sombra projetada suave e realista */
        }

        /* Estiliza o cabeçalho do logotipo no topo do card */
        .logo {
            text-align: center;                /* Centraliza os textos do logotipo */
            margin-bottom: 24px;               /* Margem inferior para afastar as abas de alternância */
        }

        /* Estiliza o título principal do logotipo */
        .logo h1 {
            font-size: 1.8rem;                 /* Tamanho da fonte do título */
            font-weight: 700;                  /* Texto em negrito forte */
            color: #1a6b3a;                    /* Tom de verde escuro característico do sistema */
        }

        /* Estiliza o texto de apoio ou descrição do logotipo */
        .logo p {
            font-size: 0.85rem;                /* Fonte de tamanho menor */
            color: #777;                       /* Cor cinza média para contraste suave */
            margin-top: 4px;                   /* Margem superior para separar do título */
        }

        /* Estiliza a barra de abas (tabs) para alternar entre login e cadastro */
        .tabs {
            display: flex;                     /* Organiza as abas lado a lado usando flexbox */
            background: #f0f0f0;               /* Fundo cinza claro para a barra de abas */
            border-radius: 10px;               /* Cantos arredondados */
            padding: 4px;                      /* Espaçamento interno da barra */
            margin-bottom: 24px;               /* Margem abaixo das abas */
        }

        /* Estiliza individualmente cada botão/aba de controle */
        .tab {
            flex: 1;                           /* Faz cada aba ocupar exatamente metade da largura disponível */
            padding: 10px;                     /* Espaçamento interno */
            text-align: center;                /* Centraliza o texto dentro da aba */
            border-radius: 8px;                /* Arredondamento suave nos cantos internos */
            font-size: 0.9rem;                 /* Tamanho de fonte intermediário */
            font-weight: 500;                  /* Espessura de fonte média */
            cursor: pointer;                   /* Muda o cursor do mouse para indicar elemento clicável */
            border: none;                      /* Remove a borda padrão do botão */
            background: transparent;           /* Fundo transparente por padrão */
            color: #777;                       /* Cor cinza do texto inativo */
            transition: all 0.2s;              /* Transição suave de estados */
        }

        /* Classe de status ativo da aba selecionada */
        .tab.active {
            background: #fff;                  /* Fundo branco para sobressair da barra cinza */
            color: #1a1a1a;                    /* Texto escuro indicando elemento selecionado */
            box-shadow: 0 1px 4px rgba(0, 0, 0, 0.12); /* Pequena sombra para efeito de elevação */
        }

        /* Estiliza os grupos de campos do formulário */
        .form-group {
            margin-bottom: 16px;               /* Adiciona espaçamento inferior em cada campo */
        }

        /* Estiliza os rótulos (labels) acima de cada input */
        label {
            display: block;                    /* Força o rótulo a ocupar uma linha cheia acima do campo */
            font-size: 0.85rem;                /* Tamanho pequeno */
            font-weight: 500;                  /* Peso médio */
            color: #333;                       /* Cor cinza escura */
            margin-bottom: 6px;                /* Distanciamento do input */
        }

        /* Estiliza todos os campos de inserção de dados do formulário */
        input[type="text"],
        input[type="email"],
        input[type="password"] {
            width: 100%;                       /* Ocupa a largura inteira do card */
            padding: 12px 14px;                /* Espaçamento interno para digitação confortável */
            border: 1.5px solid #e5e5e5;       /* Borda fina cinza claro */
            border-radius: 8px;                /* Cantos arredondados */
            font-family: 'Inter', sans-serif;  /* Aplica a fonte da página */
            font-size: 0.95rem;                /* Tamanho de fonte legível */
            color: #1a1a1a;                    /* Cor do texto digitado */
            background: #fafafa;               /* Cor cinza muito clara para fundo de input inativo */
            transition: border-color 0.2s;      /* Suaviza a mudança de cor da borda ao focar */
        }

        /* Modificações estéticas nos inputs quando recebem foco (clique do usuário) */
        input[type="text"]:focus,
        input[type="email"]:focus,
        input[type="password"]:focus {
            outline: none;                     /* Remove o contorno azul ou preto padrão do navegador */
            border-color: #2db35d;             /* Altera a cor da borda para o verde padrão */
            background: #fff;                  /* Modifica o fundo do campo para branco puro */
        }

        /* Linha que agrupa a caixa de seleção e o link de esquecimento de senha */
        .row {
            display: flex;                     /* Utiliza flexbox */
            align-items: center;               /* Centraliza verticalmente o checkbox e o link */
            justify-content: space-between;    /* Empurra o checkbox para esquerda e o link para a direita */
            margin-bottom: 20px;               /* Margem inferior antes do botão de ação */
        }

        /* Estiliza o rótulo e checkbox de lembrar o acesso */
        .checkbox-label {
            display: flex;                     /* Coloca a caixinha e o texto de apoio alinhados lado a lado */
            align-items: center;
            gap: 8px;                          /* Espaço entre a caixinha de marcação e a frase descritiva */
            font-size: 0.85rem;                /* Tamanho pequeno */
            color: #555;                       /* Cor cinza escuro */
            cursor: pointer;                   /* Cursor indicando clique */
        }

        /* Ajusta o comportamento de tamanho padrão dos campos do tipo checkbox */
        .checkbox-label input {
            width: auto;
        }

        /* Estiliza links gerais da página (ex: esqueci minha senha) */
        .link {
            font-size: 0.85rem;
            color: #2db35d;                    /* Texto na cor verde padrão */
            text-decoration: none;             /* Remove o sublinhado padrão */
            font-weight: 500;                  /* Espessura média */
        }

        /* Adiciona sublinhado apenas ao passar o mouse sobre o link */
        .link:hover {
            text-decoration: underline;
        }

        /* Estiliza o botão principal de submissão do formulário */
        .btn-primary {
            width: 100%;                       /* Largura total */
            padding: 13px;                     /* Espaçamento interno */
            background: #2db35d;               /* Cor verde padrão */
            color: #fff;                       /* Texto na cor branca */
            border: none;                      /* Remove borda */
            border-radius: 8px;                /* Arredondamento suave */
            font-family: 'Inter', sans-serif;
            font-size: 1rem;                   /* Tamanho padrão */
            font-weight: 600;                  /* Peso em negrito */
            cursor: pointer;                   /* Cursor em pointer */
            transition: background 0.2s;       /* Transição de cor de fundo no hover */
        }

        /* Efeito visual ao passar o mouse no botão principal (verde ligeiramente mais escuro) */
        .btn-primary:hover {
            background: #25964e;
        }

        /* Estiliza um divisor visual textual (não utilizado nesta versão mas mantido intacto) */
        .divider {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 20px 0;
            color: #aaa;
            font-size: 0.8rem;
        }

        /* Linhas laterais decorativas do divisor */
        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: #e5e5e5;
        }

        /* Estilo para agrupamento de botões de login social */
        .social-btns {
            display: flex;
            gap: 12px;
            margin-bottom: 20px;
        }

        /* Estilização individual para os botões sociais */
        .btn-social {
            flex: 1;
            padding: 11px;
            border: 1.5px solid #e5e5e5;
            border-radius: 8px;
            background: #fff;
            font-family: 'Inter', sans-serif;
            font-size: 0.875rem;
            font-weight: 500;
            color: #333;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: border-color 0.2s;
        }

        /* Hover para botões de login social */
        .btn-social:hover {
            border-color: #aaa;
        }

        /* Estilização do texto de copyright no rodapé do card */
        .footer-text {
            text-align: center;
            font-size: 0.75rem;
            color: #aaa;
        }

        /* Regra inicial para o painel de criação de conta: começa invisível por padrão */
        #painel-criar {
            display: none;
        }
    </style>
</head>

<body>

    <!-- Card centralizador de conteúdo da página de autenticação -->
    <div class="card">
        <!-- Logotipo e título de introdução -->
        <div class="logo">
            <h1>ChefSupply</h1>
            <p>Sistema de Gestão de Estoque</p>
        </div>

        <!-- Abas de alternância de visualização entre formulário de Login e Cadastro -->
        <div class="tabs">
            <button class="tab active" onclick="trocarAba('entrar')">Entrar</button>
            <button class="tab" onclick="trocarAba('criar')">Criar Conta</button>
        </div>

        <!-- Painel de Login (Entrar) -->
        <div id="painel-entrar">
            <!-- Formulário que envia os dados digitados ao script PHP de validação (login.php) via POST -->
            <form action="login.php" method="post">
                <!-- Campo de Entrada para o Email/Login do Usuário -->
                <div class="form-group">
                    <label>E-mail</label>
                    <input type="email" name="login" placeholder="seu@email.com">
                </div>
                <!-- Campo de Entrada para a Senha do Usuário -->
                <div class="form-group">
                    <label>Senha</label>
                    <input type="password" name="senha" placeholder="••••••••">
                </div>
                <!-- Opções adicionais de login -->
                <div class="row">
                    <label class="checkbox-label">
                        <input type="checkbox" name="lembrar"> Lembrar acesso
                    </label>
                    <!-- Link fictício para redefinição de senhas esquecidas -->
                    <a href="#" class="link">Esqueci minha senha</a>
                </div>
                
                <!-- Bloco condicional em PHP que exibe mensagem de erro se a query string 'erro' for igual a 1 (dados incorretos) -->
                <?php if (isset($_GET['erro']) && $_GET['erro'] == 1): ?>
                    <div
                        style="background:#fee2e2;border:1px solid #fca5a5;border-radius:8px;padding:10px 14px;margin-bottom:14px;display:flex;align-items:center;gap:8px;font-size:0.85rem;color:#dc2626;">
                        <!-- Ícone vetorial SVG de aviso/erro -->
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10" />
                            <line x1="12" y1="8" x2="12" y2="12" />
                            <line x1="12" y1="16" x2="12.01" y2="16" />
                        </svg>
                        E-mail ou senha incorretos. Tente novamente.
                    </div>
                <?php endif; ?>
                
                <!-- Botão de envio para validar as credenciais informadas no formulário -->
                <button type="submit" class="btn-primary">Entrar no Sistema</button>
            </form>
        </div>

        <!-- Painel de Cadastro (Criar Conta) -->
        <div id="painel-criar">
            <!-- Formulário que envia os novos dados ao script PHP de cadastro (cadastrar.php) via POST -->
            <form action="cadastrar.php" method="post">
                <!-- Campo para inserção do nome completo do novo usuário -->
                <div class="form-group">
                    <label>Nome completo</label>
                    <input type="text" name="nome" placeholder="Seu nome">
                </div>
                <!-- Campo para inserção do endereço de e-mail -->
                <div class="form-group">
                    <label>E-mail</label>
                    <input type="email" name="email" placeholder="seu@email.com">
                </div>
                <!-- Campo para inserção da senha da nova conta -->
                <div class="form-group">
                    <label>Senha</label>
                    <input type="password" name="senha" placeholder="••••••••">
                </div>

                <!-- Bloco condicional em PHP que exibe erros relacionados ao fluxo de cadastro (erros 2, 3, 4 e 5) -->
                <?php if (isset($_GET['erro']) && in_array($_GET['erro'], ['2', '3', '4', '5'])): ?>
                    <?php
                    // Mapeia os códigos numéricos de erro com suas respectivas descrições amigáveis em português
                    $msgs = [
                        '2' => 'E-mail já cadastrado.',
                        '3' => 'Todos os campos são obrigatórios.',
                        '4' => 'E-mail inválido.',
                        '5' => 'A senha deve ter no mínimo 6 caracteres.'
                    ];
                    ?>
                    <!-- Bloco HTML contendo a caixa de mensagem de erro -->
                    <div
                        style="background:#fee2e2;border:1px solid #fca5a5;border-radius:8px;padding:10px 14px;margin-bottom:14px;display:flex;align-items:center;gap:8px;font-size:0.85rem;color:#dc2626;">
                        <!-- Ícone de alerta em formato vetorial SVG -->
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10" />
                            <line x1="12" y1="8" x2="12" y2="12" />
                            <line x1="12" y1="16" x2="12.01" y2="16" />
                        </svg>
                        <!-- Exibe dinamicamente a mensagem com base no mapeamento feito na variável $msgs -->
                        <?= $msgs[$_GET['erro']] ?? 'Erro ao criar conta.' ?>
                    </div>
                <?php endif; ?>
                
                <!-- Botão de submissão do formulário de cadastro de nova conta -->
                <button type="submit" class="btn-primary">Criar Conta</button>
            </form>
        </div>

        <!-- Texto explicativo de rodapé sobre o copyright do sistema -->
        <div class="footer-text" style="margin-top:16px">
            ChefSupply © 2026 - Gestão Inteligente de Estoque
        </div>
    </div>

    <!-- Bloco de código Javascript para gerenciamento de troca de painéis -->
    <script>
        /**
         * Alterna a visualização entre o formulário de login (Entrar) e o de cadastro (Criar Conta).
         * @param {string} aba - O identificador do painel a ser mostrado ('entrar' ou 'criar').
         */
        function trocarAba(aba) {
            // Se o parâmetro for 'entrar', exibe o painel correspondente alterando o display para 'block'; senão oculta com 'none'
            document.getElementById('painel-entrar').style.display = aba === 'entrar' ? 'block' : 'none';
            // Se o parâmetro for 'criar', exibe o painel correspondente com display 'block'; senão oculta com 'none'
            document.getElementById('painel-criar').style.display = aba === 'criar' ? 'block' : 'none';
            
            // Localiza os botões das abas e atribui a classe 'active' apenas para o elemento ativo no momento
            document.querySelectorAll('.tab').forEach((t, i) => {
                t.classList.toggle('active', (aba === 'entrar' && i === 0) || (aba === 'criar' && i === 1));
            });
        }
        
        // Verifica se há alguma mensagem de erro de cadastro vinda via GET na URL
        // Em caso afirmativo, faz a tela iniciar automaticamente exibindo a aba de cadastro ('criar')
        <?php if (isset($_GET['erro']) && in_array($_GET['erro'], ['2', '3', '4', '5'])): ?>
                trocarAba('criar');
        <?php endif; ?>
    </script>

</body>

</html>