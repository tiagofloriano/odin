
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
                <h5 class="offcanvas-title" id="tituloSistema">
                    <i class="fa-solid fa-mug-hot"></i>
                    Odin
                </h5>

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

                <!-- Categoria -->
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

					<?php
					if(user_info('admin')){
					?>
                <!-- Categoria -->
                <div class="text-uppercase text-secondary small fw-bold mb-2">
                    Administração
                </div>

                <nav class="nav nav-pills flex-column mb-4">
                    <a href="?usuarios" class="nav-link text-white">
                        <i class="fa-solid fa-users fa-fw me-2"></i>
                        Usuários
                    </a>
                    <a href="?acessos" class="nav-link text-white">
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
                    <a href="?formbuilder" class="nav-link text-white">
                        <i class="fa-solid fa-database fa-fw me-2"></i>
                        Form builder
                    </a>
                </nav>
					<?php
					}
					menu();
					?>

            </div>
        </aside>

        <!-- ÁREA PRINCIPAL -->
        <div class="col-md-9 col-lg-10 p-0">

            <!-- BARRA SUPERIOR -->
            <header class="navbar bg-body border-bottom px-3 py-2">

                <div class="d-flex align-items-center gap-3">

                    <!-- Botão mobile -->
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
                        Tiago
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

            <!-- CONTEÚDO -->
            <main class="p-3 p-md-4">
