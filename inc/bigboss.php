<?php
/**
 * @name bigboss
 * @description Biblioteca de funções auxiliares para desenvolvimento PHP.
 * @author Tiago Floriano <tiago@abfisica.org.br>
 * @copyright 2021-2026 Tiago Floriano
 * @license GPL v2
 * @link https://github.com/tiagofloriano/bigboss
 */

/**
 * Cria a conexão com o servidor MySQL.
 *
 * @param string $usuario Usuário do banco de dados.
 * @param string $senha   Senha do banco de dados.
 * @param string $host    Endereço do servidor MySQL.
 *
 * @return PDO Retorna o objeto de conexão.
 */
function con($usuario, $senha, $host = 'localhost') {

    // Define o endereço do servidor MySQL.
    $dsn = "mysql:host=$host;charset=utf8mb4";

    // Cria e retorna a conexão.
    return new PDO($dsn, $usuario, $senha, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
}

/**
 * Seleciona o banco de dados a ser utilizado.
 *
 * @param string $banco Nome do banco de dados.
 * @param PDO|false $con Conexão alternativa (opcional).
 *
 * @return PDO Retorna a conexão utilizada.
 */
function db($banco, $con = false) {

    // Se uma conexão foi informada, utiliza-a.
    // Caso contrário, utiliza a conexão padrão do Bigboss.
    if ($con === false) {
        global $pdo;
        $con = $pdo ?? null;
    }

    // Verifica se existe uma conexão disponível.
    if (!$con instanceof PDO) {
        throw new RuntimeException('Nenhuma conexão com o banco de dados foi definida.');
    }

    // Valida o nome do banco de dados.
    if (!preg_match('/\A[a-zA-Z0-9_]+\z/', $banco)) {
        throw new InvalidArgumentException('Nome de banco de dados inválido.');
    }

    // Seleciona o banco na conexão escolhida.
    $con->exec("USE `$banco`");

    // Retorna a conexão para uso posterior.
    return $con;
}

/**
 * Consulta registros de uma tabela.
 *
 * @param string       $tabela    Nome da tabela.
 * @param array        $condicoes Condições no formato campo => valor.
 * @param string|false $ordem     Campo(s) para ordenação.
 * @param int|false    $limite    Número máximo de registros.
 * @param PDO|false    $con       Conexão alternativa, opcional.
 *
 * @return array Registros encontrados.
 */
function sel($tabela, $condicoes = [], $ordem = false, $limite = false, $con = false) {

    // Se nenhuma conexão foi informada, utiliza a conexão padrão.
    if ($con === false) {
        global $pdo;
        $con = $pdo ?? null;
    }

    // Verifica se existe uma conexão PDO válida.
    if (!$con instanceof PDO) {
        throw new RuntimeException('Nenhuma conexão com o banco de dados foi definida.');
    }

    // Valida o nome da tabela.
    if (!preg_match('/\A[a-zA-Z0-9_]+\z/', $tabela)) {
        throw new InvalidArgumentException('Nome de tabela inválido.');
    }

    // Monta a consulta inicial.
    $query = "SELECT * FROM `$tabela`";

    // Armazena os valores que serão enviados à consulta preparada.
    $valores = [];

    // Monta as condições WHERE, se houver.
    if (!empty($condicoes)) {
        $where = [];
        $i = 0;

        foreach ($condicoes as $campo => $valor) {

            // Valida o nome de cada campo.
            if (!preg_match('/\A[a-zA-Z0-9_]+\z/', $campo)) {
                throw new InvalidArgumentException('Nome de campo inválido.');
            }

            // Cria um parâmetro para cada condição.
            $parametro = ':p' . $i++;

            $where[] = "`$campo` = $parametro";
            $valores[$parametro] = $valor;
        }

        $query .= ' WHERE ' . implode(' AND ', $where);
    }
    // Adiciona a ordenação, validando os campos permitidos.
    if ($ordem !== false && $ordem !== '') {
        if (!preg_match(
            '/\A[a-zA-Z0-9_]+(?:\s+(?:ASC|DESC))?(?:\s*,\s*[a-zA-Z0-9_]+(?:\s+(?:ASC|DESC))?)*\z/i',
            $ordem
        )) {
            throw new InvalidArgumentException('Ordenação inválida.');
        }

        $query .= " ORDER BY $ordem";
    }

    // Adiciona o limite, se informado.
    if ($limite !== false && $limite !== '') {
        if (filter_var($limite, FILTER_VALIDATE_INT) === false || $limite < 0) {
            throw new InvalidArgumentException('Limite inválido.');
        }

        $query .= " LIMIT " . (int) $limite;
    }

    // Prepara a consulta SQL.
    $stmt = $con->prepare($query);

    // Executa a consulta enviando os valores separadamente.
    $stmt->execute($valores);

    // Retorna todos os registros encontrados.
    return $stmt->fetchAll();
}

/**
 * Insere um registro em uma tabela.
 *
 * @param string    $tabela Nome da tabela.
 * @param array     $dados  Campos e valores a inserir.
 * @param PDO|false $con    Conexão alternativa, opcional.
 *
 * @return string|false ID do registro inserido ou false.
 */
function ins($tabela, $dados, $con = false) {

    // Se nenhuma conexão foi informada, utiliza a conexão padrão.
    if ($con === false) {
        global $pdo;
        $con = $pdo ?? null;
    }

    // Verifica se existe uma conexão PDO válida.
    if (!$con instanceof PDO) {
        throw new RuntimeException('Nenhuma conexão com o banco de dados foi definida.');
    }

    // Valida o nome da tabela.
    if (!preg_match('/\A[a-zA-Z0-9_]+\z/', $tabela)) {
        throw new InvalidArgumentException('Nome de tabela inválido.');
    }

    // Verifica se foram informados dados para inserir.
    if (empty($dados)) {
        throw new InvalidArgumentException('Nenhum dado foi informado para inserção.');
    }

    // Monta a lista de campos e valida seus nomes.
    foreach (array_keys($dados) as $campo) {
        if (!preg_match('/\A[a-zA-Z0-9_]+\z/', $campo)) {
            throw new InvalidArgumentException('Nome de campo inválido.');
        }
    }

    // Prepara os nomes dos campos e os marcadores dos valores.
    $campos = array_keys($dados);
    $colunas = '`' . implode('`, `', $campos) . '`';
    $marcadores = implode(', ', array_fill(0, count($dados), '?'));

    // Monta a consulta SQL.
    $query = "INSERT INTO `$tabela` ($colunas) VALUES ($marcadores)";

    // Prepara a consulta.
    $stmt = $con->prepare($query);

    // Executa a inserção com os valores separados do SQL.
    $stmt->execute(array_values($dados));

    // Retorna o ID gerado pelo banco, se houver.
    return $con->lastInsertId();
}

/**
 * Atualiza registros de uma tabela.
 *
 * @param string    $tabela    Nome da tabela.
 * @param array     $dados     Campos e valores a atualizar.
 * @param array     $condicoes Condições para identificar os registros.
 * @param PDO|false $con       Conexão alternativa, opcional.
 *
 * @return int Número de registros afetados.
 */
function upd($tabela, $dados, $condicoes, $con = false) {

    // Se nenhuma conexão foi informada, utiliza a conexão padrão.
    if ($con === false) {
        global $pdo;
        $con = $pdo ?? null;
    }

    // Verifica se existe uma conexão PDO válida.
    if (!$con instanceof PDO) {
        throw new RuntimeException('Nenhuma conexão com o banco de dados foi definida.');
    }

    // Valida o nome da tabela.
    if (!preg_match('/\A[a-zA-Z0-9_]+\z/', $tabela)) {
        throw new InvalidArgumentException('Nome de tabela inválido.');
    }

    // Impede uma atualização sem campos ou sem condições.
    if (empty($dados)) {
        throw new InvalidArgumentException('Nenhum dado informado para atualização.');
    }

    if (empty($condicoes)) {
        throw new InvalidArgumentException('Informe condições para identificar os registros.');
    }

    // Valida os nomes dos campos que serão atualizados.
    foreach (array_keys($dados) as $campo) {
        if (!preg_match('/\A[a-zA-Z0-9_]+\z/', $campo)) {
            throw new InvalidArgumentException('Nome de campo inválido.');
        }
    }

    // Valida os nomes dos campos usados nas condições.
    foreach (array_keys($condicoes) as $campo) {
        if (!preg_match('/\A[a-zA-Z0-9_]+\z/', $campo)) {
            throw new InvalidArgumentException('Nome de campo inválido.');
        }
    }

    // Monta a cláusula SET.
    $set = [];
    foreach (array_keys($dados) as $campo) {
        $set[] = "`$campo` = ?";
    }

    // Monta a cláusula WHERE.
    $where = [];
    foreach (array_keys($condicoes) as $campo) {
        $where[] = "`$campo` = ?";
    }

    // Monta a consulta SQL.
    $query = "UPDATE `$tabela` SET "
           . implode(', ', $set)
           . " WHERE "
           . implode(' AND ', $where);

    // Prepara a consulta.
    $stmt = $con->prepare($query);

    // Executa usando os valores separados do SQL.
    $stmt->execute(array_merge(
        array_values($dados),
        array_values($condicoes)
    ));

    // Retorna a quantidade de registros afetados.
    return $stmt->rowCount();
}


/**
 * Exclui registros de uma tabela utilizando condições.
 *
 * @param string $tabela Nome da tabela.
 * @param array $condicoes Condições associativas para o WHERE.
 * @param PDO|false $con Conexão PDO opcional.
 * @return int Quantidade de registros excluídos.
 * @throws InvalidArgumentException Se os parâmetros forem inválidos.
 * @throws RuntimeException Se a conexão não estiver disponível.
 */
function del($tabela, $condicoes, $con = false) {

    // Recupera a conexão padrão.
    if ($con === false) {
        global $pdo;
        $con = $pdo ?? null;
    }

    if (!$con instanceof PDO) {
        throw new RuntimeException('Conexão com o banco de dados indisponível.');
    }

    // Valida o nome da tabela.
    if (!is_string($tabela) || !preg_match('/\A[a-zA-Z0-9_]+\z/', $tabela)) {
        throw new InvalidArgumentException('Nome de tabela inválido.');
    }

    // Impede exclusões sem condições.
    if (!is_array($condicoes) || empty($condicoes)) {
        throw new InvalidArgumentException('É necessário informar condições para excluir registros.');
    }

    $where = [];
    $valores = [];

    // Monta as condições do WHERE.
    foreach ($condicoes as $campo => $valor) {

        // Valida o nome da coluna.
        if (!is_string($campo) || !preg_match('/\A[a-zA-Z0-9_]+\z/', $campo)) {
            throw new InvalidArgumentException('Nome de coluna inválido.');
        }

        // Trata valores nulos.
        if ($valor === null) {
            $where[] = "`$campo` IS NULL";
        } else {
            $where[] = "`$campo` = ?";
            $valores[] = $valor;
        }
    }

    // Monta e executa a exclusão.
    $sql = "DELETE FROM `$tabela` WHERE " . implode(' AND ', $where);

    $stmt = $con->prepare($sql);
    $stmt->execute($valores);

    return $stmt->rowCount();
}



/**
 * Autentica um usuário e cria uma sessão.
 *
 * @param string $usuario Nome do usuário.
 * @param string $senha   Senha informada no login.
 * @param string $destino Página para redirecionar após o login.
 *
 * @return bool Retorna false se a autenticação falhar.
 */
function login($usuario, $senha, $destino) {

    // Acessa a conexão padrão do Bigboss.
    global $pdo;

    // Inicia a sessão PHP, caso ainda não esteja iniciada.
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    // Busca o usuário pelo nome.
    $stmt = $pdo->prepare(
        'SELECT id, nome, usuario, senha, pontodeacesso, admin, escala_fonte, status FROM usuarios WHERE usuario = ? AND status = 1 LIMIT 1'
    );
    $stmt->execute([$usuario]);

    // Obtém os dados do usuário.
    $dados = $stmt->fetch();

    // Verifica se o usuário existe.
    if (!$dados) {
        return false;
    }

    // Verifica a senha armazenada.
    if (!password_verify($senha, $dados['senha'])) {

        // Compatibilidade temporária com senhas antigas em texto puro.
        if (!hash_equals((string) $dados['senha'], (string) $senha)) {
            return false;
        }

        // Converte a senha antiga para um hash seguro.
        $hash = password_hash($senha, PASSWORD_DEFAULT);

        // Atualiza a senha no banco.
        $stmt = $pdo->prepare(
            'UPDATE usuarios SET senha = ? WHERE usuario = ?'
        );
        $stmt->execute([$hash, $usuario]);

    } elseif (password_needs_rehash($dados['senha'], PASSWORD_DEFAULT)) {

        // Atualiza o hash se o algoritmo ou o custo recomendado mudou.
        $hash = password_hash($senha, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare(
            'UPDATE usuarios SET senha = ? WHERE usuario = ?'
        );
        $stmt->execute([$hash, $usuario]);
    }

    // Gera um novo identificador para evitar fixação de sessão.
    session_regenerate_id(true);

    // Gera um token aleatório para identificar a sessão.
    $token = bin2hex(random_bytes(32));

    // Armazena somente o hash do token no banco de dados.
    $tokenHash = hash('sha256', $token);

    // Registra a sessão na tabela.
    $stmt = $pdo->prepare(
        'INSERT INTO sessoes (usuario, sessao) VALUES (?, ?)'
    );
    $stmt->execute([$usuario, $tokenHash]);

    // Armazena os dados da autenticação na sessão PHP.
    $_SESSION['odin_usuarioid'] = $dados['id'];
    $_SESSION['odin_usuario'] = $usuario;
    $_SESSION['odin_usuarionome'] = $dados['nome'];
    $_SESSION['odin_pontodeacesso'] = $dados['pontodeacesso'];
    $_SESSION['odin_admin'] = $dados['admin'];
    $_SESSION['odin_escalafonte'] = $dados['escala_fonte'];
    $_SESSION['odin_token'] = $token;

    // Redireciona para a página indicada.
    header('Location: ' . $destino);
    exit;
}



/**
 * Verifica se existe uma sessão autenticada e válida.
 *
 * @return bool Retorna true se estiver autenticado.
 */
function is_on() {

    // Acessa a conexão padrão do Bigboss.
    global $pdo;

    // Inicia a sessão PHP, caso ainda não esteja iniciada.
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    // Verifica se os dados de autenticação existem na sessão.
    if (
        empty($_SESSION['odin_usuario']) ||
        empty($_SESSION['odin_token'])
    ) {
        return false;
    }

    // Calcula o hash do token armazenado na sessão PHP.
    $tokenHash = hash('sha256', $_SESSION['odin_token']);

    // Verifica se o usuário e o token estão registrados no banco.
    $stmt = $pdo->prepare(
        'SELECT id FROM sessoes
         WHERE usuario = ? AND sessao = ?
         LIMIT 1'
    );

    $stmt->execute([
        $_SESSION['odin_usuario'],
        $tokenHash
    ]);

    // Retorna true se a sessão estiver registrada.
    return (bool) $stmt->fetch();
}

/**
 * Encerra a sessão autenticada.
 *
 * @param string|false $destino Página para redirecionar após sair.
 *
 * @return void
 */
function logoff($destino = false) {

    // Acessa a conexão padrão do Bigboss.
    global $pdo;

    // Inicia a sessão PHP, caso ainda não esteja iniciada.
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    // Verifica se existe um token de autenticação.
    if (!empty($_SESSION['odin_token'])) {

        // Calcula o hash do token para localizar a sessão no banco.
        $tokenHash = hash('sha256', $_SESSION['odin_token']);

        // Remove somente a sessão atual.
        $stmt = $pdo->prepare(
            'DELETE FROM sessoes WHERE sessao = ?'
        );
        $stmt->execute([$tokenHash]);
    }

    // Limpa as variáveis da sessão PHP.
    $_SESSION = [];

    // Invalida o cookie da sessão, se estiver sendo utilizado.
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();

        setcookie(
            session_name(),
            '',
            [
                'expires' => time() - 42000,
                'path' => $params['path'],
                'domain' => $params['domain'],
                'secure' => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => $params['samesite'] ?? 'Lax'
            ]
        );
    }

    // Encerra a sessão PHP.
    session_destroy();

    // Redireciona se um destino tiver sido informado.
    if ($destino !== false && $destino !== '') {
        //header('Location: ' . $destino);
        echo "<meta http-equiv='refresh' content='0;URL=$destino'>";
        exit;
    }
}


/**
 * Redimensiona uma imagem preservando sua proporção.
 *
 * Formatos suportados: JPEG, PNG, GIF e WebP.
 *
 * @param string $origem   Caminho da imagem original.
 * @param string $destino Caminho para salvar a imagem redimensionada.
 * @param int    $largura Largura máxima desejada, em pixels.
 * @param int    $altura  Altura máxima desejada, em pixels.
 * @param int    $qualidade Qualidade de saída (0 a 100).
 *
 * @return bool Retorna true se a imagem for criada com sucesso.
 *
 * @throws RuntimeException Se ocorrer um erro ao processar a imagem.
 */
function img($origem, $destino, $largura, $altura, $qualidade = 85) {

    // Verifica se a extensão GD está disponível.
    if (!extension_loaded('gd')) {
        throw new RuntimeException('A extensão GD do PHP não está habilitada.');
    }

    // Verifica se o arquivo original existe.
    if (!is_file($origem) || !is_readable($origem)) {
        throw new RuntimeException('Imagem original não encontrada ou inacessível.');
    }

    // Verifica se as dimensões informadas são válidas.
    if ($largura < 1 || $altura < 1) {
        throw new InvalidArgumentException('Largura e altura devem ser maiores que zero.');
    }

    // Garante que a qualidade esteja entre 0 e 100.
    if ($qualidade < 0 || $qualidade > 100) {
        throw new InvalidArgumentException('A qualidade deve estar entre 0 e 100.');
    }

    // Obtém as dimensões e o tipo da imagem original.
    $info = getimagesize($origem);

    if ($info === false) {
        throw new RuntimeException('O arquivo informado não é uma imagem válida.');
    }

    // Carrega a imagem original na memória.
    $imagem = imagecreatefromstring(file_get_contents($origem));

    if ($imagem === false) {
        throw new RuntimeException('Não foi possível carregar a imagem.');
    }

    // Obtém as dimensões originais.
    $larguraOriginal = imagesx($imagem);
    $alturaOriginal = imagesy($imagem);

    // Calcula a proporção necessária para caber nas dimensões máximas.
    $proporcao = min(
        $largura / $larguraOriginal,
        $altura / $alturaOriginal,
        1
    );

    // Calcula as dimensões finais, sem ampliar imagens pequenas.
    $novaLargura = max(1, (int) round($larguraOriginal * $proporcao));
    $novaAltura = max(1, (int) round($alturaOriginal * $proporcao));

    // Cria a imagem de destino.
    $novaImagem = imagecreatetruecolor($novaLargura, $novaAltura);

    // Identifica o formato pelo nome do arquivo de destino.
    $formato = strtolower(pathinfo($destino, PATHINFO_EXTENSION));

    if (!in_array($formato, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
        imagedestroy($imagem);
        imagedestroy($novaImagem);

        throw new InvalidArgumentException('Formato de destino não suportado.');
    }

    // Preserva a transparência nos formatos que a suportam.
    if (in_array($formato, ['png', 'gif', 'webp'], true)) {
        imagealphablending($novaImagem, false);
        imagesavealpha($novaImagem, true);

        $transparente = imagecolorallocatealpha($novaImagem, 0, 0, 0, 127);
        imagefill($novaImagem, 0, 0, $transparente);
    } else {
        // Preenche o fundo com branco ao salvar em JPEG.
        $branco = imagecolorallocate($novaImagem, 255, 255, 255);
        imagefill($novaImagem, 0, 0, $branco);
    }

    // Redimensiona a imagem com interpolação de qualidade.
    imagecopyresampled(
        $novaImagem,
        $imagem,
        0, 0, 0, 0,
        $novaLargura, $novaAltura,
        $larguraOriginal, $alturaOriginal
    );

    // Salva a imagem no formato indicado pela extensão do destino.
    $sucesso = match ($formato) {
        'jpg', 'jpeg' => imagejpeg($novaImagem, $destino, $qualidade),
        'png'         => imagepng($novaImagem, $destino, (int) round((100 - $qualidade) * 9 / 100)),
        'gif'         => imagegif($novaImagem, $destino),
        'webp'        => imagewebp($novaImagem, $destino, $qualidade)
    };

    // Libera a memória utilizada pelas imagens.
    imagedestroy($imagem);
    imagedestroy($novaImagem);

    // Informa se a gravação foi bem-sucedida.
    if (!$sucesso) {
        throw new RuntimeException('Não foi possível salvar a imagem redimensionada.');
    }

    return true;
}

/**
 * Identifica e inclui a página solicitada pela URL.
 *
 * Recebe o caminho capturado pelo index.php, identifica o nome
 * da página antes dos parâmetros adicionais e inclui o arquivo
 * correspondente dentro da pasta pages.
 *
 * @param string $path Caminho capturado da URL.
 * @return void
 */
function url($path = 'home') {
    // Separa a página dos parâmetros adicionais da URL.
    $partes = explode('&', $path);

    // Obtém o nome da página antes dos parâmetros.
    $pagina = explode('?', $partes[0])[0];

    // Permite apenas letras, números, hífens e sublinhados.
    if (!preg_match('/\A[a-zA-Z0-9_-]+\z/', $pagina)) {
        $pagina = '404';
    }

    // Monta o caminho do arquivo dentro da pasta pages.
    // $arquivo = __DIR__ . '/pages/' . $pagina . '.php';
    $arquivo = "pages/$pagina.php";

    // Inclui a página solicitada ou a página 404.
    if (is_file($arquivo)) {
        include $arquivo;
    } else {
        include __DIR__ . '/pages/404.php';
    }
}

/**
 * Remove acentos, espaços e caracteres especiais de uma string.
 *
 * Converte letras acentuadas para seus equivalentes sem acento
 * e mantém somente letras de A a Z e números de 0 a 9.
 *
 * @param string $string Texto original.
 * @return string Texto sem acentos ou caracteres especiais.
 */
function remCE($string) {
    // Converte caracteres acentuados para seus equivalentes ASCII.
    $string = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $string);

    // Remove tudo que não seja letra ou número.
    return preg_replace('/[^a-zA-Z0-9]/', '', $string);
}

/**
 * Retorna o nome de um mês em português do Brasil.
 *
 * @param int $mes Número do mês, de 1 a 12.
 * @return string|false Nome do mês ou false se o número for inválido.
 */
function mes($mes) {
    // Lista dos meses, indexada pelo número correspondente.
    $meses = [
        1  => 'janeiro',
        2  => 'fevereiro',
        3  => 'março',
        4  => 'abril',
        5  => 'maio',
        6  => 'junho',
        7  => 'julho',
        8  => 'agosto',
        9  => 'setembro',
        10 => 'outubro',
        11 => 'novembro',
        12 => 'dezembro'
    ];

    // Retorna o mês solicitado ou false se ele não existir.
    return $meses[(int) $mes] ?? false;
}


/**
 * Converte e formata datas entre os formatos brasileiro e SQL.
 *
 * Tipo 0: AAAA-MM-DD para DD/MM/AAAA.
 * Tipo 1: AAAA-MM-DD para DD de mês de AAAA.
 * Outros: DD/MM/AAAA para AAAA-MM-DD.
 *
 * @param string $data Data a ser convertida.
 * @param int $tipo Formato desejado.
 * @return string|false Data convertida ou false se for inválida.
 */
function data($data, $tipo = 0) {
    // Define o formato de entrada conforme o tipo solicitado.
    $formatoEntrada = ($tipo == 0 || $tipo == 1) ? '!Y-m-d' : '!d/m/Y';

    // Interpreta a data no formato esperado.
    $dataFormatada = DateTimeImmutable::createFromFormat($formatoEntrada, $data);

    // Rejeita datas inválidas ou que não correspondam ao formato esperado.
    if (
        $dataFormatada === false ||
        $dataFormatada->format(substr($formatoEntrada, 1)) !== $data
    ) {
        return false;
    }

    // Converte para o formato brasileiro com barras.
    if ($tipo == 0) {
        return $dataFormatada->format('d/m/Y');
    }

    // Converte para uma data escrita por extenso.
    if ($tipo == 1) {
        return $dataFormatada->format('d') . ' de ' .
            mes((int) $dataFormatada->format('m')) . ' de ' .
            $dataFormatada->format('Y');
    }

    // Converte a data brasileira para o formato SQL.
    return $dataFormatada->format('Y-m-d');
}

/**
 * Verifica se os campos obrigatórios estão preenchidos.
 *
 * @param array $campos Array com os valores a verificar.
 * @param int $num Número de campos obrigatórios.
 * @return bool true se todos estiverem preenchidos; false caso contrário.
 */
function is_clear($campos, $num) {
    // Confere se a variável é um array e se contém campos suficientes.
    if (!is_array($campos) || count($campos) < $num || $num < 1) {
        return false;
    }

    // Verifica os primeiros campos obrigatórios.
    foreach (array_slice($campos, 0, $num) as $campo) {
        // Considera vazio um valor nulo ou uma string sem conteúdo.
        if ($campo === null || (is_string($campo) && trim($campo) === '')) {
            return false;
        }
    }

    // Todos os campos obrigatórios estão preenchidos.
    return true;
}

/**
 * Gera as opções de unidades federativas brasileiras para um select HTML.
 *
 * @param string $tipo Use "full" para gerar o select completo.
 * @param string $selecione Texto opcional da primeira opção.
 * @param string $selecionada Sigla da UF que deve ficar selecionada.
 * @return void
 */
function gerauf($tipo = 'options', $selecione = '', $selecionada = '') {
    // Lista das 27 unidades federativas brasileiras.
    $ufs = [
        'AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO',
        'MA', 'MT', 'MS', 'MG', 'PA', 'PB', 'PR', 'PE', 'PI',
        'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO'
    ];

    // Abre o select quando solicitado.
    if ($tipo === 'full') {
        echo '<select name="uf">';

        // Exibe uma opção inicial, se fornecida.
        if ($selecione !== '') {
            echo '<option value="">' .
                htmlspecialchars($selecione, ENT_QUOTES, 'UTF-8') .
                '</option>';
        }
    }

    // Gera uma opção para cada UF.
    foreach ($ufs as $uf) {
        // Marca como selecionada a UF correspondente.
        $selected = ($uf === strtoupper($selecionada)) ? ' selected' : '';

        echo '<option value="' . $uf . '"' . $selected . '>' .
            $uf . '</option>';
    }

    // Fecha o select quando ele foi aberto pela própria função.
    if ($tipo === 'full') {
        echo '</select>';
    }
}

/**
 * Gera ou verifica o token de proteção CSRF.
 *
 * @param int $form 0 para gerar campo oculto, 1 para validar envio.
 * @return string|bool Campo HTML ou resultado da validação.
 */
function csrf($form = 0) {

    // Garante a existência do token na sessão.
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    // Gera o campo oculto para o formulário.
    if ($form === 0) {
        return '<input type="hidden" name="csrf_token" value="' .
            htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') .
            '">';
    }

    // Verifica o token enviado pelo formulário.
    if ($form === 1) {

        $token = $_POST['csrf_token'] ?? '';

        return is_string($token) &&
            hash_equals($_SESSION['csrf_token'], $token);
    }

    return false;
}
