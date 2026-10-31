<?php
/**
 * Cabeçalho e estrutura principal do tema padrão do Odin.
 *
 * Exibe o menu lateral, a identificação do sistema e o início
 * da área de conteúdo. O rodapé fecha a estrutura HTML.
 */
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Odin | Painel</title>

    <!-- Bootstrap 5.3.8: versão estável. -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >
    
	 <!-- Font Awesome -->
	 <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
	 
	 <!-- CSS do tema -->
	 <link rel="stylesheet"
        href="themes/padrao/css.css">

    <!-- Carrega o jQuery. -->
    <script src="https://code.jquery.com/jquery-4.0.0.min.js"></script>

    <!-- Componentes JavaScript do Bootstrap. -->
    <script
        src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"
        defer
    ></script>
</head>

<body>

<div class="container-fluid p-0">
    <div class="row g-0 min-vh-100">

        <!-- Menu lateral esquerdo. -->
        <aside class="col-md-3 col-lg-2 odin-sidebar">

            <!-- Marca do sistema. Substitua o O por uma imagem se desejar. -->
            <a href="?home" class="odin-brand">
                <span>
                    <span class="odin-brand-name">Odin</span>
                    <span class="odin-brand-subtitle">Sistema de gestão</span>
                </span>
            </a>

            <!-- Navegação principal. -->
            <nav aria-label="Menu principal">

                <div class="odin-menu-label">Menu</div>

                <a href="?home" class="odin-menu-link active">
                    <i class="fa-solid fa-house"></i>
                    <span>Início</span>
                </a>

                <a href="?sair" class="odin-menu-link">
                    <i class="fa-solid fa-user-gear"></i>
                    <span>Meus dados</span>
                </a>

                <a href="?sair" class="odin-menu-link">
                    <i class="fa-solid fa-right-from-bracket"></i>
                    <span>Sair do sistema</span>
                </a>

                <!--
                    Insira aqui os menus dinâmicos do Odin,
                    conforme as permissões do usuário.
                -->

            </nav>

        </aside>

        <!-- Coluna direita: cabeçalho, conteúdo e rodapé. -->
        <div class="col-md-9 col-lg-10 odin-content-column">

            <header class="odin-topbar">
                <div>
                    <h1 class="odin-page-heading">Painel de controle</h1>
                    <p class="odin-page-subtitle">
                        Bem-vindo ao Odin
                    </p>
                </div>

                <div class="odin-status">
                    <span class="odin-status-dot"></span>
                        <?= $_SESSION["odin_usuario"] ?>
                </div>
            </header>
			
            <!-- O conteúdo de url() será inserido abaixo. -->
            <main class="odin-main">
