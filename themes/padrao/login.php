
<?php
/**
 * Tela de autenticação do tema padrão do Odin.
 *
 * Apresenta o formulário e informa falhas de autenticação.
 * O processamento das credenciais é feito pela Bigboss no index.php.
 */

// Define uma mensagem vazia caso não exista erro de login.
$erroLogin = $erroLogin ?? '';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Entrar | Odin</title>

    <!-- Bootstrap 5.3.8 estável. -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <!-- jQuery 4.0.0. -->
    <script src="https://code.jquery.com/jquery-4.0.0.min.js"></script>
</head>

<body class="bg-body-tertiary">

<main class="container min-vh-100 d-flex align-items-center justify-content-center">
    <div class="card shadow-sm w-100" style="max-width: 400px;">
        <div class="card-body p-4">

            <h1 class="h3 text-center mb-2">Odin</h1>
            <p class="text-secondary text-center mb-4">
                Entre com suas credenciais
            </p>

            <!-- Exibe o erro retornado pelo processamento do login. -->
            <?php if ($erroLogin !== ''): ?>
                <div class="alert alert-danger" role="alert">
                    <?= htmlspecialchars($erroLogin, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <!-- Envia as credenciais para o index.php. -->
            <form method="post" action="">
                <div class="mb-3">
                    <label for="usuario" class="form-label">Usuário</label>
                    <input
                        type="text"
                        class="form-control"
                        id="usuario"
                        name="usuario"
                        maxlength="20"
                        autocomplete="username"
                        required
                        autofocus
                    >
                </div>

                <div class="mb-4">
                    <label for="senha" class="form-label">Senha</label>
                    <input
                        type="password"
                        class="form-control"
                        id="senha"
                        name="senha"
                        autocomplete="current-password"
                        required
                    >
                </div>

                <button type="submit" class="btn btn-primary w-100">
                    Entrar
                </button>
            </form>

        </div>
    </div>
</main>

<footer class="text-center text-secondary small py-3">
    Odin &copy; <?= date('Y') ?>
</footer>

</body>
</html>

