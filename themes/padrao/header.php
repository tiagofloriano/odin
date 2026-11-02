<?php

// Garante que a sessão esteja disponível antes de qualquer saída HTML.
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

global $pdo;

$erroTrocaPonto = '';
$idUsuarioAtual = (int) user_info('id');
$pontoAtual = (int) user_info('pontodeacesso');

// Gera o token CSRF utilizado na troca de ponto.
if (empty($_SESSION['odin_csrf_ponto'])) {
    $_SESSION['odin_csrf_ponto'] = bin2hex(random_bytes(32));
}

// Processa a troca de ponto antes de iniciar o HTML.
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    ($_POST['acao_header'] ?? '') === 'trocar_ponto'
) {
    $token = (string) ($_POST['csrf_token'] ?? '');
    $novoPonto = (int) ($_POST['novo_ponto'] ?? 0);

    if (
        !hash_equals(
            (string) $_SESSION['odin_csrf_ponto'],
            $token
        )
    ) {
        $erroTrocaPonto = 'Solicitação inválida. Atualize a página e tente novamente.';
    } else {

        // Recupera os dados atualizados do usuário diretamente do banco.
        $dadosUsuario = sel(
            'usuarios',
            ['id' => $idUsuarioAtual],
            false,
            1
        );

        if (empty($dadosUsuario)) {
            $erroTrocaPonto = 'Usuário não encontrado.';
        } else {

            $dadosUsuario = $dadosUsuario[0];

            // Monta a relação de pontos autorizados.
            $pontosAutorizados = array_filter(
                array_map(
                    'intval',
                    explode(';', (string) ($dadosUsuario['meuspontos'] ?? ''))
                )
            );

            $pontosAutorizados[] = (int) $dadosUsuario['pontodeacesso'];
            $pontosAutorizados = array_values(array_unique($pontosAutorizados));

            // Confirma que o novo ponto pertence ao usuário.
            if (!in_array($novoPonto, $pontosAutorizados, true)) {
                $erroTrocaPonto = 'Você não tem acesso a esse ponto.';
            } else {

                // Confirma que o ponto ainda existe.
                $pontoEncontrado = sel(
                    'pontodeacesso',
                    ['id' => $novoPonto],
                    false,
                    1
                );

                if (empty($pontoEncontrado)) {
                    $erroTrocaPonto = 'Ponto de acesso não encontrado.';
                } else {

                    // O ponto anterior passa a integrar a lista dos demais pontos.
                    $demaisPontos = array_values(
                        array_diff($pontosAutorizados, [$novoPonto])
                    );

                    try {

                        upd(
                            'usuarios',
                            [
                                'pontodeacesso' => $novoPonto,
                                'meuspontos' => implode(';', $demaisPontos)
                            ],
                            ['id' => $idUsuarioAtual]
                        );

                        // Atualiza imediatamente o ponto ativo da sessão.
                        $_SESSION['odin_pontodeacesso'] = $novoPonto;

                        // Evita reenviar o formulário ao atualizar a página.
                        $destino = $_SERVER['REQUEST_URI'] ?? '?home';

                        if (!str_starts_with($destino, '/')) {
                            $destino = '/' . ltrim($destino, '/');
                        }

                        header('Location: ' . $destino);
                        exit;

                    } catch (Throwable $ex) {
                        $erroTrocaPonto = 'Não foi possível alterar o ponto de acesso.';
                    }
                }
            }
        }
    }
}

// Recupera os dados do ponto atualmente ativo.
$pontoAtualDados = sel(
    'pontodeacesso',
    ['id' => $pontoAtual],
    false,
    1
);

$nomePontoAtual = $pontoAtualDados[0]['nome'] ?? 'Ponto não identificado';

// Recupera os pontos autorizados para exibir no seletor.
$opcoesPontos = [];

$dadosUsuario = sel(
    'usuarios',
    ['id' => $idUsuarioAtual],
    false,
    1
);

if (!empty($dadosUsuario)) {

    $idsPontos = array_filter(
        array_map(
            'intval',
            explode(';', (string) ($dadosUsuario[0]['meuspontos'] ?? ''))
        )
    );

    $idsPontos[] = $pontoAtual;
    $idsPontos = array_values(array_unique($idsPontos));

    foreach ($idsPontos as $idPonto) {

        $resultadoPonto = sel(
            'pontodeacesso',
            ['id' => $idPonto],
            false,
            1
        );

        if (!empty($resultadoPonto)) {
            $opcoesPontos[] = $resultadoPonto[0];
        }
    }
}

?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Odin</title>

    <!-- Bootstrap 5.3.8 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Tema de cores -->
    <link href="themes/padrao/css.css" rel="stylesheet">

    <!-- Tema de cores específicas do layout -->
    <link href="themes/padrao/layout.css" rel="stylesheet">

    <!-- Font Awesome -->
    <script src="https://kit.fontawesome.com/90c0082b73.js" crossorigin="anonymous"></script>

    <?php
    $escalaFonte = (int) user_info('escala_fonte');
    ?>

    <style>
        :root {
            --odin-font-scale: <?= $escalaFonte ?>%;
        }
    </style>
</head>

<body>
<div class="container-fluid">
    <div class="row min-vh-100">

        <!-- MENU LATERAL -->
        <aside
            class="col-md-3 col-lg-2 offcanvas-md offcanvas-start bg-dark text-white p-0"
            tabindex="-1"
            id="menuLateral"
            aria-labelledby="tituloSistema"
        >

            <!-- Cabeçalho da lateral -->
            <div class="offcanvas-header border-bottom border-secondary p-3">
                <div class="offcanvas-title" id="tituloSistema">
                    <h5 class="mb-1">
                        <i class="fa-solid fa-mug-hot"></i>
                        Odin
                    </h5>

					     <?php if (count($opcoesPontos) > 1): ?>
					
					        <small
					            class="text-white-50"
					            id="nomePontoAtual"
					            role="button"
					            style="cursor: pointer;"
					            onclick="
					                document.getElementById('nomePontoAtual').classList.add('d-none');
					                document.getElementById('seletorPonto').classList.remove('d-none');
					                document.getElementById('seletorPonto').focus();
					            "
					        >
					            <?= htmlspecialchars($nomePontoAtual, ENT_QUOTES, 'UTF-8') ?>
					            <i class="fa-solid fa-circle-chevron-down"></i>
					        </small>
					
					        <form method="POST" id="seletorPonto" class="d-none">
					            <input type="hidden" name="acao_header" value="trocar_ponto">
					            <input
					                type="hidden"
					                name="csrf_token"
					                value="<?= htmlspecialchars($_SESSION['odin_csrf_ponto'], ENT_QUOTES, 'UTF-8') ?>"
					            >
					
					            <select
					                name="novo_ponto"
					                class="form-select form-select-sm"
					                aria-label="Selecionar ponto de acesso"
					                onchange="this.form.submit()"
					            >
					                <?php foreach ($opcoesPontos as $ponto): ?>
					                    <option
					                        value="<?= (int) $ponto['id'] ?>"
					                        <?= (int) $ponto['id'] === $pontoAtual ? 'selected' : '' ?>
					                    >
					                        <?= htmlspecialchars($ponto['nome'], ENT_QUOTES, 'UTF-8') ?>
					                    </option>
					                <?php endforeach; ?>
					            </select>
					        </form>
					
					    <?php else: ?>
					
					        <small class="text-white-50">
					            <?= htmlspecialchars($nomePontoAtual, ENT_QUOTES, 'UTF-8') ?>
					        </small>
					
					    <?php endif; ?>
    
                </div>

                <button
                    type="button"
                    class="btn-close btn-close-white d-md-none"
                    data-bs-dismiss="offcanvas"
                    data-bs-target="#menuLateral"
                    aria-label="Fechar menu"
                ></button>
            </div>

            <!-- Conteúdo do menu -->
            <div class="offcanvas-body d-block p-3">

                <div class="text-uppercase text-secondary small fw-bold mb-2">
                    Geral
                </div>

                <nav class="nav nav-pills flex-column mb-4">
                    <a href="?home" class="nav-link text-white active">
                        <i class="fa-solid fa-house fa-fw me-2"></i>
                        Início
                    </a>
                    <a href="?perfil" class="nav-link text-white">
                        <i class="fa-solid fa-user fa-fw me-2"></i>
                        Meu perfil
                    </a>
                    <a href="?sair" class="nav-link text-white">
                        <i class="fa-solid fa-user fa-fw me-2"></i>
                        Sair do sistema
                    </a>
                </nav>

                <?php if (user_info('admin')): ?>

                    <div class="text-uppercase text-secondary small fw-bold mb-2">
                        Administração
                    </div>

                    <nav class="nav nav-pills flex-column mb-4">
                        <a href="?usuarios" class="nav-link text-white">
                            <i class="fa-solid fa-users fa-fw me-2"></i>
                            Usuários
                        </a>
                        <a href="?pontosdeacesso" class="nav-link text-white">
                            <i class="fa-solid fa-key fa-fw me-2"></i>
                            Pontos de acesso
                        </a>
                        <a href="?menus" class="nav-link text-white">
                            <i class="fa-solid fa-list fa-fw me-2"></i>
                            Grupos de menus
                        </a>
                        <a href="?editor" class="nav-link text-white">
                            <i class="fa-solid fa-code fa-fw me-2"></i>
                            Editor de código
                        </a>
                        <a href="?forms" class="nav-link text-white">
                            <i class="fa-solid fa-database fa-fw me-2"></i>
                            Form builder
                        </a>
                    </nav>

                <?php endif; ?>

                <?php
                menu();
                ?>

            </div>
        </aside>

        <!-- ÁREA PRINCIPAL -->
        <div class="col-md-9 col-lg-10 p-0">

            <!-- BARRA SUPERIOR -->
            <header class="navbar bg-body border-bottom px-3 py-2">

                <div class="d-flex align-items-center gap-3">

                    <button
                        class="btn btn-outline-padrao d-md-none"
                        type="button"
                        data-bs-toggle="offcanvas"
                        data-bs-target="#menuLateral"
                        aria-controls="menuLateral"
                        aria-label="Abrir menu"
                    >
                        <i class="fa-solid fa-bars"></i>
                    </button>

                    <h5 class="mb-0">Painel de controle</h5>

                </div>

                <div class="dropdown">
                    <button
                        class="btn btn-outline-padrao dropdown-toggle"
                        type="button"
                        data-bs-toggle="dropdown"
                        aria-expanded="false"
                    >
                        <i class="fa-solid fa-user-circle me-1"></i>
                        <?= htmlspecialchars((string) ($_SESSION['odin_usuarionome'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                    </button>

                    <ul class="dropdown-menu dropdown-menu-end">
                        <li>
                            <a class="dropdown-item" href="?perfil">
                                Meu perfil
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item" href="?sair">
                                Sair
                            </a>
                        </li>
                    </ul>
                </div>

            </header>

            <?php if ($erroTrocaPonto !== ''): ?>
                <div class="alert alert-danger m-3" role="alert">
                    <?= htmlspecialchars($erroTrocaPonto, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <!-- CONTEÚDO -->
            <main class="p-3 p-md-4">