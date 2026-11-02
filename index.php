<?php
session_start();
/**
 * Arquivo principal do Odin.
 *
 * Carrega a Bigboss, inicializa o banco de dados,
 * processa o login e apresenta o tema conforme a autenticação.
 */
 
 // Verifica se o Odin já foi instalado.
if (!is_file(__DIR__ . '/inc/installed.lock')) {
    require __DIR__ . '/install.php';
    exit;
}

// Carrega as funções da Bigboss.
require_once __DIR__ . '/inc/bigboss.php';

// Carrega a configuração do banco de dados.
// Esse arquivo deve definir a conexão PDO e selecionar o banco.
require_once __DIR__ . '/inc/config.php';

// Carrega as classes e funções específicas do Odin
require_once __DIR__ . '/inc/odin.php';

// Processa o formulário de login enviado por POST.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = $_POST['usuario'] ?? '';
    $senha = $_POST['senha'] ?? '';

    // A função login() valida as credenciais e redireciona em caso de sucesso.
    $loginValido = login($usuario, $senha, '?home');

    // Se as credenciais forem inválidas, retorna à tela de login com erro.
    if (!$loginValido) {
        $erroLogin = 'Usuário ou senha inválidos.';
    }
}

// Define o tema ativo.
$tema = __DIR__ . '/themes/padrao';

// Apresenta o sistema para usuários autenticados.
if (is_on()) {
    require $tema . '/header.php';

    // Captura a parte da URL após a barra.
    $path = $_SERVER['QUERY_STRING'] ?? 'home';

    // Carrega a página solicitada
    url($path);

    require $tema . '/footer.php';
} else {
    // Apresenta a tela de login para usuários não autenticados.
    require $tema . '/login.php';
}