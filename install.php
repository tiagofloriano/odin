<?php
session_start();

$arquivoConfig = __DIR__ . '/inc/config.php';
$arquivoBloqueio = __DIR__ . '/inc/installed.lock';

// Impede que o instalador seja executado novamente.
if (is_file($arquivoBloqueio)) {
    exit('O Odin já está instalado.');
}

// Token de segurança do formulário.
if (empty($_SESSION['install_token'])) {
    $_SESSION['install_token'] = bin2hex(random_bytes(32));
}

$erro = '';
$sucesso = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Verifica o token enviado pelo formulário.
    if (!hash_equals($_SESSION['install_token'], $_POST['token'] ?? '')) {
        $erro = 'Solicitação inválida. Atualize a página e tente novamente.';
    } else {

        // Recebe os dados do formulário.
        $banco = trim($_POST['banco'] ?? '');
        $usuarioBanco = trim($_POST['usuario_banco'] ?? '');
        $senhaBanco = $_POST['senha_banco'] ?? '';
        $nomeAdmin = trim($_POST['nome_admin'] ?? '');
        $usuarioAdmin = trim($_POST['usuario_admin'] ?? '');
        $senhaAdmin = $_POST['senha_admin'] ?? '';

        // Valida os campos obrigatórios.
        if (
            $banco === '' ||
            $usuarioBanco === '' ||
            $nomeAdmin === '' ||
            $usuarioAdmin === '' ||
            $senhaAdmin === ''
        ) {
            $erro = 'Preencha todos os campos obrigatórios.';
        } elseif (!preg_match('/\A[a-zA-Z0-9_]+\z/', $banco)) {
            $erro = 'Nome do banco de dados inválido.';
        } elseif (strlen($senhaAdmin) < 8) {
            $erro = 'A senha do administrador deve ter pelo menos 8 caracteres.';
        } else {

            try {
                // Carrega a biblioteca Bigboss.
                require_once __DIR__ . '/inc/bigboss.php';

                // Conecta ao servidor e seleciona o banco informado.
                $pdo = con($usuarioBanco, $senhaBanco);
                db($banco);

                // Cria a tabela de usuários.
                $pdo->exec("
                    CREATE TABLE IF NOT EXISTS usuarios (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        nome VARCHAR(100) NOT NULL,
                        usuario VARCHAR(50) NOT NULL UNIQUE,
                        senha VARCHAR(255) NOT NULL,
                        pontodeacesso INT(4) NOT NULL,
                        meuspontos TEXT NULL,
                        admin TINYINT(1) NOT NULL DEFAULT 0,
                        escala_fonte INT(11) NOT NULL DEFAULT 90,
                        status INT(1) NOT NULL DEFAULT 1
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                ");

                // Cria a tabela de sessões.
                $pdo->exec("
                    CREATE TABLE IF NOT EXISTS sessoes (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        usuario VARCHAR(50) NOT NULL,
                        sessao CHAR(64) NOT NULL
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                ");

                // Cria a tabela de menus.
                $pdo->exec("
							CREATE TABLE IF NOT EXISTS menus (
							    id INT AUTO_INCREMENT PRIMARY KEY,
							    label TEXT NULL,
							    link TEXT NULL,
							    menusuperior INT(11) NOT NULL DEFAULT 0,
							    pontodeacesso INT(11) NULL,
							    icone VARCHAR(100) NULL
							) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
                ");

                // Cria a tabela de pontos de acesso.
                $pdo->exec("
							CREATE TABLE IF NOT EXISTS pontodeacesso (
							    id INT AUTO_INCREMENT PRIMARY KEY,
							    nome TEXT NOT NULL,
							    dominio TEXT NOT NULL,
							    logoimg VARCHAR(200) NULL,
							    homepage VARCHAR(50) DEFAULT '?home',
							    aceitacadastro INT(1) NOT NULL DEFAULT 0,
							    status INT(1) NOT NULL DEFAULT 1
							) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
                ");

                // Cria a tabela de formulários
                $pdo->exec("
							CREATE TABLE IF NOT EXISTS formularios (
							    id INT AUTO_INCREMENT PRIMARY KEY,
								 chave varchar(128) NOT NULL,
								 nome varchar(100) NOT NULL,
								 descricao text,
								 tabela_destino varchar(100) NOT NULL,
								 where_personalizado text,
								 orderby_personalizado text,
								 criado_em timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
								 jspersonalizado text,
								 permissaoexcluir int(1) DEFAULT '1',
								 permissaopontodeacesso text,
								 permissaogrupo text,
								 permissaousuario text,
								 urlpersonalizada text
							) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
                ");

                // Cria a tabela de campos de formulários
                $pdo->exec("
							CREATE TABLE IF NOT EXISTS formularios (
							    id INT AUTO_INCREMENT PRIMARY KEY,
								 formulario_id int(11) NOT NULL,
								 campo_destino varchar(100) NOT NULL,
								 tipo enum('text','textarea','select','radio','checkbox','date','imagem','hidden','datetime') NOT NULL,
								 label varchar(100) NOT NULL,
								 obrigatorio tinyint(1) DEFAULT '0',
								 origem_opcoes text,
								 tabela_origem varchar(100) DEFAULT NULL,
								 coluna varchar(100) DEFAULT NULL,
								 valor text,
								 where_personalizado text,
								 listavel int(1) DEFAULT 1,
								 editavel int(1) DEFAULT 1
							) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
                ");

                // Cria a tabela de opcoes campos de formulários do tipo select, radio ou checkbox
                $pdo->exec("
							CREATE TABLE IF NOT EXISTS formularios (
							    id INT AUTO_INCREMENT PRIMARY KEY,
								 campo_id int(11) DEFAULT NULL,
								 valor text,
								 label text
							) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
                ");
                
                
                $consulta = $pdo->prepare("
                    INSERT INTO pontodeacesso (nome, dominio)
                    VALUES (:nome, :dominio)
                ");

                $consulta->execute([
                    'nome' => 'Odin',
                    'dominio' => 'localhost'
                ]);

                // Verifica se o usuário administrador já existe.
                $consulta = $pdo->prepare(
                    "SELECT id FROM usuarios WHERE usuario = :usuario LIMIT 1"
                );
                $consulta->execute(['usuario' => $usuarioAdmin]);

                if ($consulta->fetch()) {
                    throw new Exception('Esse usuário já existe no banco de dados.');
                }

                // Cria o administrador com senha protegida.
                $senhaHash = password_hash($senhaAdmin, PASSWORD_DEFAULT);

                $consulta = $pdo->prepare("
                    INSERT INTO usuarios (nome, usuario, senha, pontodeacesso, admin)
                    VALUES (:nome, :usuario, :senha, 1, 1)
                ");

                $consulta->execute([
                    'nome' => $nomeAdmin,
                    'usuario' => $usuarioAdmin,
                    'senha' => $senhaHash
                ]);

                // Monta o arquivo de configuração.
                $conteudo = "<?php\n\n";
                $conteudo .= "define('ODIN_INSTALADO', true);\n\n";
                $conteudo .= "require_once __DIR__ . '/bigboss.php';\n\n";
                $conteudo .= "// Conexão com o banco de dados.\n";
                $conteudo .= '$pdo = con(' . var_export($usuarioBanco, true) . ', ' . var_export($senhaBanco, true) . ");\n\n";
                $conteudo .= "// Banco utilizado pelo Odin.\n";
                $conteudo .= 'db(' . var_export($banco, true) . ");\n";

                // Grava primeiro em arquivo temporário.
                $temporario = $arquivoConfig . '.tmp';

                if (file_put_contents($temporario, $conteudo, LOCK_EX) === false) {
                    throw new Exception('Não foi possível gravar o arquivo config.php. Verifique as permissões da pasta inc.');
                }

                // Substitui o arquivo de configuração.
                if (!rename($temporario, $arquivoConfig)) {
                    @unlink($temporario);
                    throw new Exception('Não foi possível finalizar a gravação do config.php.');
                }

                // Cria o marcador de instalação concluída.
                if (file_put_contents($arquivoBloqueio, 'Instalado em ' . date('Y-m-d H:i:s'), LOCK_EX) === false) {
                    throw new Exception('Configuração gravada, mas não foi possível criar o arquivo de bloqueio.');
                }

                $sucesso = 'O Odin foi instalado com sucesso! Remova o arquivo install.php e acesse o sistema.';
                unset($_SESSION['install_token']);

            } catch (Throwable $e) {
                $erro = 'Erro na instalação: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Instalação do Odin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-7 col-lg-6">

            <div class="card shadow-sm">
                <div class="card-body p-4">

                    <h2 class="mb-4 text-center">Instalação do Odin</h2>

                    <?php if ($erro): ?>
                        <div class="alert alert-danger">
                            <?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($sucesso): ?>
                        <div class="alert alert-success">
                            <?= htmlspecialchars($sucesso, ENT_QUOTES, 'UTF-8') ?>
                        </div>
                    <?php else: ?>

                        <form method="post">

                            <input type="hidden" name="token"
                                   value="<?= htmlspecialchars($_SESSION['install_token'], ENT_QUOTES, 'UTF-8') ?>">

                            <h5>Banco de dados</h5>
                            <p><i>Aqui você irá informar os dados do banco que você já criou, 
                            e informar os dados de usuário já incluídos em seu banco.</i></p>
                            <div class="mb-3">
                                <label class="form-label">Nome do banco</label>
                                <input type="text" name="banco" class="form-control" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Usuário do banco</label>
                                <input type="text" name="usuario_banco" class="form-control" required>
                            </div>

                            <div class="mb-4">
                                <label class="form-label">Senha do banco</label>
                                <input type="password" name="senha_banco" class="form-control">
                            </div>

                            <hr>

                            <h5>Administrador do Odin</h5>
                            
                            <p><i>Estes são os dados de acesso que você quer criar para o usuário administrador do sistema.</i></p>

                            <div class="mb-3">
                                <label class="form-label">Nome completo</label>
                                <input type="text" name="nome_admin" class="form-control" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Usuário</label>
                                <input type="text" name="usuario_admin" class="form-control" required>
                            </div>

                            <div class="mb-4">
                                <label class="form-label">Senha</label>
                                <input type="password" name="senha_admin" class="form-control" minlength="8" required>
                            </div>

                            <button type="submit" class="btn btn-primary w-100">
                                Instalar Odin
                            </button>

                        </form>

                    <?php endif; ?>

                </div>
            </div>

        </div>
    </div>
</div>

</body>
</html>