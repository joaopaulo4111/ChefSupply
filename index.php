<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ChefSupply — Login</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background-image: url(fundo.jpg);
            background-repeat: no-repeat;
            background-size: cover;
            background-position: center;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Inter', sans-serif;
        }

        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.45);
        }

        .card {
            position: relative;
            background: #fff;
            border-radius: 16px;
            padding: 32px 28px;
            width: 100%;
            max-width: 420px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }

        .logo {
            text-align: center;
            margin-bottom: 24px;
        }

        .logo h1 {
            font-size: 1.8rem;
            font-weight: 700;
            color: #1a6b3a;
        }

        .logo p {
            font-size: 0.85rem;
            color: #777;
            margin-top: 4px;
        }

        /* Abas */
        .tabs {
            display: flex;
            background: #f0f0f0;
            border-radius: 10px;
            padding: 4px;
            margin-bottom: 24px;
        }

        .tab {
            flex: 1;
            padding: 10px;
            text-align: center;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            border: none;
            background: transparent;
            color: #777;
            transition: all 0.2s;
        }

        .tab.active {
            background: #fff;
            color: #1a1a1a;
            box-shadow: 0 1px 4px rgba(0, 0, 0, 0.12);
        }

        .form-group {
            margin-bottom: 16px;
        }

        label {
            display: block;
            font-size: 0.85rem;
            font-weight: 500;
            color: #333;
            margin-bottom: 6px;
        }

        input[type="text"],
        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 12px 14px;
            border: 1.5px solid #e5e5e5;
            border-radius: 8px;
            font-family: 'Inter', sans-serif;
            font-size: 0.95rem;
            color: #1a1a1a;
            background: #fafafa;
            transition: border-color 0.2s;
        }

        input[type="text"]:focus,
        input[type="email"]:focus,
        input[type="password"]:focus {
            outline: none;
            border-color: #2db35d;
            background: #fff;
        }

        .row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
        }

        .checkbox-label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.85rem;
            color: #555;
            cursor: pointer;
        }

        .checkbox-label input {
            width: auto;
        }

        .link {
            font-size: 0.85rem;
            color: #2db35d;
            text-decoration: none;
            font-weight: 500;
        }

        .link:hover {
            text-decoration: underline;
        }

        .btn-primary {
            width: 100%;
            padding: 13px;
            background: #2db35d;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-family: 'Inter', sans-serif;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }

        .btn-primary:hover {
            background: #25964e;
        }

        .divider {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 20px 0;
            color: #aaa;
            font-size: 0.8rem;
        }

        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: #e5e5e5;
        }

        .social-btns {
            display: flex;
            gap: 12px;
            margin-bottom: 20px;
        }

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

        .btn-social:hover {
            border-color: #aaa;
        }

        .footer-text {
            text-align: center;
            font-size: 0.75rem;
            color: #aaa;
        }

        /* Painel Criar Conta */
        #painel-criar {
            display: none;
        }
    </style>
</head>

<body>

    <div class="card">
        <div class="logo">
            <h1>ChefSupply</h1>
            <p>Sistema de Gestão de Estoque</p>
        </div>

        <div class="tabs">
            <button class="tab active" onclick="trocarAba('entrar')">Entrar</button>
            <button class="tab" onclick="trocarAba('criar')">Criar Conta</button>
        </div>

        <!-- Painel Entrar -->
        <div id="painel-entrar">
            <form action="login.php" method="post">
                <div class="form-group">
                    <label>E-mail</label>
                    <input type="email" name="login" placeholder="seu@email.com">
                </div>
                <div class="form-group">
                    <label>Senha</label>
                    <input type="password" name="senha" placeholder="••••••••">
                </div>
                <div class="row">
                    <label class="checkbox-label">
                        <input type="checkbox" name="lembrar"> Lembrar acesso
                    </label>
                    <a href="#" class="link">Esqueci minha senha</a>
                </div>
                <button type="submit" class="btn-primary">Entrar no Sistema</button>
            </form>

            <div class="divider">Ou continue com</div>

            <div class="social-btns">
                <button class="btn-social">
                    <svg width="18" height="18" viewBox="0 0 24 24">
                        <path fill="#4285F4"
                            d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" />
                        <path fill="#34A853"
                            d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" />
                        <path fill="#FBBC05"
                            d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z" />
                        <path fill="#EA4335"
                            d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" />
                    </svg>
                    Google
                </button>
                <button class="btn-social">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="#1877F2">
                        <path
                            d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z" />
                    </svg>
                    Facebook
                </button>
            </div>
        </div>

        <!-- Painel Criar Conta -->
        <div id="painel-criar">
            <form action="cadastrar.php" method="post">
                <div class="form-group">
                    <label>Nome completo</label>
                    <input type="text" name="nome" placeholder="Seu nome">
                </div>
                <div class="form-group">
                    <label>E-mail</label>
                    <input type="email" name="email" placeholder="seu@email.com">
                </div>
                <div class="form-group">
                    <label>Senha</label>
                    <input type="password" name="senha" placeholder="••••••••">
                </div>
                <button type="submit" class="btn-primary">Criar Conta</button>
            </form>
        </div>


        <div class="footer-text" style="margin-top:16px">
            ChefSupply © 2026 - Gestão Inteligente de Estoque
        </div>
    </div>

    <script>
        function trocarAba(aba) {
            document.getElementById('painel-entrar').style.display = aba === 'entrar' ? 'block' : 'none';
            document.getElementById('painel-criar').style.display = aba === 'criar' ? 'block' : 'none';
            document.querySelectorAll('.tab').forEach((t, i) => {
                t.classList.toggle('active', (aba === 'entrar' && i === 0) || (aba === 'criar' && i === 1));
            });
        }
    </script>

</body>

</html>