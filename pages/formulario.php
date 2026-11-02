
<?php
/**
 * Motor de formulários dinâmicos do Odin.
 *
 * Permite cadastrar, listar, editar e excluir registros,
 * respeitando as permissões e configurações do formulário.
 *
 */

// ============================================================
// 1. AUTENTICAÇÃO E IDENTIFICAÇÃO DO FORMULÁRIO
// ============================================================

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!is_on()) {
    echo '<div class="alert alert-warning">É necessário estar autenticado.</div>';
    return;
}

$chave = trim((string) ($_GET['c'] ?? ''));

if ($chave === '') {
    echo '<div class="alert alert-danger">Chave do formulário não informada.</div>';
    return;
}

$registroFormulario = sel(
    'formularios',
    ['chave' => $chave],
    false,
    1
);

$formulario = $registroFormulario[0] ?? null;

if (!$formulario) {
    echo '<div class="alert alert-danger">Formulário não encontrado.</div>';
    return;
}

// Dados do usuário autenticado.
$usuarioId = (int) user_info('id');
$pontoId = (int) user_info('pontodeacesso');
$admin = (int) user_info('admin');

// ============================================================
// 2. PERMISSÕES
// ============================================================

$pontosPermitidos = array_filter(
    array_map(
        'intval',
        explode(',', (string) $formulario['permissaopontodeacesso'])
    )
);

$usuariosPermitidos = array_filter(
    array_map(
        'intval',
        explode(',', (string) $formulario['permissaousuario'])
    )
);

if ($admin !== 1) {

    $temPermissao = false;

    if ($pontosPermitidos || $usuariosPermitidos) {
        $temPermissao =
            in_array($pontoId, $pontosPermitidos, true) ||
            in_array($usuarioId, $usuariosPermitidos, true);
    }

    if (!$temPermissao) {
        echo '<div class="alert alert-danger">Você não tem permissão para acessar este formulário.</div>';
        return;
    }
}

// ============================================================
// 3. CONEXÃO E VERIFICAÇÃO DA TABELA DE DESTINO
// ============================================================

$tabelaDestino = (string) $formulario['tabela_destino'];

if (!preg_match('/\A[a-zA-Z0-9_]+\z/', $tabelaDestino)) {
    echo '<div class="alert alert-danger">Tabela de destino inválida.</div>';
    return;
}

global $pdo;

if (!$pdo instanceof PDO) {
    echo '<div class="alert alert-danger">Conexão com o banco indisponível.</div>';
    return;
}

try {
    $consultaColunas = $pdo->query(
        "SHOW COLUMNS FROM `$tabelaDestino`"
    );

    $colunasDestino = array_column(
        $consultaColunas->fetchAll(PDO::FETCH_ASSOC),
        'Field'
    );

} catch (Throwable $e) {
    error_log($e->getMessage());
    echo '<div class="alert alert-danger">Não foi possível verificar a tabela de destino.</div>';
    return;
}

// A tabela precisa possuir uma coluna id para o CRUD.
if (!in_array('id', $colunasDestino, true)) {
    echo '<div class="alert alert-danger">A tabela de destino precisa possuir uma coluna id.</div>';
    return;
}

// ============================================================
// 4. CAMPOS DO FORMULÁRIO
// ============================================================

$campos = sel(
    'formularios_campos',
    ['formulario_id' => (int) $formulario['id']],
    'id ASC'
);

// Verifica se os campos configurados existem na tabela.
foreach ($campos as $campo) {

    $nomeCampo = (string) $campo['campo_destino'];

    if (
        !preg_match('/\A[a-zA-Z0-9_]+\z/', $nomeCampo) ||
        !in_array($nomeCampo, $colunasDestino, true)
    ) {
        echo '<div class="alert alert-danger">';
        echo 'O campo "' . htmlspecialchars($nomeCampo, ENT_QUOTES, 'UTF-8');
        echo '" não existe na tabela de destino.';
        echo '</div>';
        return;
    }
}

// Função auxiliar para escapar conteúdo HTML.
$escape = static function ($valor): string {
    return htmlspecialchars(
        (string) $valor,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
};

// ============================================================
// 5. FILTROS PERSONALIZADOS
// ============================================================

/**
 * Prepara uma expressão WHERE configurada pelo administrador.
 *
 * No legado, a palavra pontodeacesso é substituída pela condição
 * pontodeacesso = ID_DO_USUARIO.
 *
 * Aqui utilizamos parâmetros preparados para o ID.
 *
 * @param string $expressao Expressão WHERE.
 * @param int $pontoId Ponto de acesso do usuário.
 * @return array Expressão SQL e parâmetros.
 */
$prepararFiltro = static function (
    string $expressao,
    int $pontoId
): array {

    $expressao = trim($expressao);
    $parametros = [];

    if ($expressao === '') {
        return ['', []];
    }

    // Impede múltiplas instruções e comentários SQL.
    if (
        str_contains($expressao, ';') ||
        str_contains($expressao, '--') ||
        str_contains($expressao, '/*') ||
        str_contains($expressao, '*/')
    ) {
        throw new RuntimeException('Expressão WHERE inválida.');
    }

    // Substitui o marcador legado pelo filtro do ponto de acesso.
    $expressao = preg_replace_callback(
        '/\bpontodeacesso\b/i',
        static function () use (&$parametros, $pontoId) {
            $parametros[] = $pontoId;
            return 'pontodeacesso = ?';
        },
        $expressao
    );

    return [$expressao, $parametros];
};

try {

    [$filtroSql, $parametrosFiltro] = $prepararFiltro(
        (string) $formulario['where_personalizado'],
        $pontoId
    );

} catch (Throwable $e) {
    error_log($e->getMessage());
    echo '<div class="alert alert-danger">Filtro personalizado inválido.</div>';
    return;
}

// ============================================================
// 6. CARREGAMENTO DAS OPÇÕES DOS CAMPOS
// ============================================================

/**
 * Carrega as opções de um campo select, radio ou checkbox.
 *
 * @param array $campo Configuração do campo.
 * @return array Opções contendo valor e label.
 */
$carregarOpcoes = static function (array $campo) use ($pdo, $pontoId): array {

    // Opções cadastradas manualmente.
    if ($campo['origem_opcoes'] === 'manual') {

        return sel(
            'formularios_campos_opcoes',
            ['campo_id' => (int) $campo['id']],
            'id ASC'
        );
    }

    // Opções carregadas de outra tabela.
    if ($campo['origem_opcoes'] !== 'tabela') {
        return [];
    }

    $tabela = (string) $campo['tabela_origem'];
    $coluna = (string) $campo['coluna'];
    $colunaValor = trim((string) $campo['valor']);

    if ($colunaValor === '') {
        $colunaValor = $coluna;
    }

    // Valida identificadores.
    foreach ([$tabela, $coluna, $colunaValor] as $identificador) {

        if (!preg_match('/\A[a-zA-Z0-9_]+\z/', $identificador)) {
            throw new RuntimeException('Configuração de opções inválida.');
        }
    }

    // Confere a existência da tabela e das colunas externas.
    $consultaEstrutura = $pdo->query(
        "SHOW COLUMNS FROM `$tabela`"
    );

    $colunasOrigem = array_column(
        $consultaEstrutura->fetchAll(PDO::FETCH_ASSOC),
        'Field'
    );

    if (
        !in_array($coluna, $colunasOrigem, true) ||
        !in_array($colunaValor, $colunasOrigem, true)
    ) {
        throw new RuntimeException('Colunas da tabela de opções não encontradas.');
    }

    $sql = "SELECT `$colunaValor` AS valor, `$coluna` AS label FROM `$tabela`";

    // Aplica o filtro personalizado do campo.
    [$where, $parametros] = $prepararFiltro(
        (string) $campo['where_personalizado'],
        $pontoId
    );

    if ($where !== '') {
        $sql .= ' WHERE ' . $where;
    }

    $consulta = $pdo->prepare($sql);
    $consulta->execute($parametros);

    return $consulta->fetchAll(PDO::FETCH_ASSOC);
};

// Carrega todas as opções antes de renderizar o formulário.
$opcoesCampos = [];

try {

    foreach ($campos as $campo) {

        if (
            in_array(
                $campo['tipo'],
                ['select', 'radio', 'checkbox'],
                true
            )
        ) {
            $opcoesCampos[(int) $campo['id']] = $carregarOpcoes($campo);
        }
    }

} catch (Throwable $e) {
    error_log($e->getMessage());
    echo '<div class="alert alert-danger">Não foi possível carregar as opções do formulário.</div>';
    return;
}

// ============================================================
// 7. IDENTIFICAÇÃO DA OPERAÇÃO
// ============================================================

$modoEdicao = (
    ($_GET['p'] ?? '') === 'editar' ||
    isset($_GET['editar'])
);

$registroId = (int) (
    $_GET['i'] ??
    $_GET['editar'] ??
    0
);

$mensagem = '';
$erros = [];
$registroAtual = null;

// ============================================================
// 8. CARREGAMENTO DO REGISTRO PARA EDIÇÃO
// ============================================================

if ($modoEdicao) {

    if ($registroId <= 0) {
        echo '<div class="alert alert-danger">Registro inválido.</div>';
        return;
    }

    try {

        $sqlRegistro = "SELECT * FROM `$tabelaDestino` WHERE id = ?";

        if ($filtroSql !== '') {
            $sqlRegistro .= " AND ($filtroSql)";
        }

        $stmtRegistro = $pdo->prepare($sqlRegistro);

        $stmtRegistro->execute(
            array_merge([$registroId], $parametrosFiltro)
        );

        $registroAtual = $stmtRegistro->fetch();

        if (!$registroAtual) {
            echo '<div class="alert alert-danger">Registro não encontrado.</div>';
            return;
        }

    } catch (Throwable $e) {
        error_log($e->getMessage());
        echo '<div class="alert alert-danger">Não foi possível carregar o registro.</div>';
        return;
    }
}

// ============================================================
// 9. EXCLUSÃO DE REGISTROS
// ============================================================

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    ($_POST['acao'] ?? '') === 'excluir'
) {

    if (!csrf(1)) {
        echo '<div class="alert alert-danger">Sessão expirada. Atualize a página.</div>';
        return;
    }

    if ((int) $formulario['permissaoexcluir'] !== 1) {
        echo '<div class="alert alert-danger">A exclusão não está permitida.</div>';
        return;
    }

    $idExcluir = (int) ($_POST['registro_id'] ?? 0);

    if ($idExcluir <= 0) {
        echo '<div class="alert alert-danger">Registro inválido.</div>';
        return;
    }

    try {

        // Confere se o registro pertence ao conjunto autorizado.
        $sqlExcluir = "SELECT id FROM `$tabelaDestino` WHERE id = ?";

        if ($filtroSql !== '') {
            $sqlExcluir .= " AND ($filtroSql)";
        }

        $stmtExcluir = $pdo->prepare($sqlExcluir);

        $stmtExcluir->execute(
            array_merge([$idExcluir], $parametrosFiltro)
        );

        if (!$stmtExcluir->fetch()) {
            echo '<div class="alert alert-danger">Registro não encontrado.</div>';
            return;
        }

        del(
            $tabelaDestino,
            ['id' => $idExcluir]
        );

        $mensagem = 'Registro excluído com sucesso!';

    } catch (Throwable $e) {
        error_log($e->getMessage());
        $erros[] = 'Não foi possível excluir o registro.';
    }
}

// ============================================================
// 10. CADASTRO E EDIÇÃO
// ============================================================

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    ($_POST['acao'] ?? '') === 'salvar'
) {

    if (!csrf(1)) {
        echo '<div class="alert alert-danger">Sessão expirada. Atualize a página.</div>';
        return;
    }

    // Na edição, confirma novamente que o registro continua autorizado.
    if ($modoEdicao) {

        if (!$registroAtual) {
            echo '<div class="alert alert-danger">Registro não encontrado.</div>';
            return;
        }
    }

    $dados = [];
    $arquivosPendentes = [];

    foreach ($campos as $campo) {

        $nome = (string) $campo['campo_destino'];
        $tipo = (string) $campo['tipo'];

        // O ponto de acesso é sempre definido pelo servidor no cadastro.
        if ($nome === 'pontodeacesso') {

            if (!$modoEdicao) {
                $dados[$nome] = $pontoId;
            }

            continue;
        }

        // Na edição, campos não editáveis permanecem inalterados.
        if (
            $modoEdicao &&
            (int) $campo['editavel'] !== 1
        ) {
            continue;
        }

        $valor = $_POST[$nome] ?? null;

        // ----------------------------------------------------
        // CHECKBOX
        // ----------------------------------------------------

        if ($tipo === 'checkbox') {

            if (!is_array($valor)) {
                $valor = [];
            }

            $valoresPermitidos = array_map(
                'strval',
                array_column(
                    $opcoesCampos[(int) $campo['id']] ?? [],
                    'valor'
                )
            );

            $valoresSelecionados = [];

            foreach ($valor as $item) {

                if (
                    !is_scalar($item) ||
                    !in_array((string) $item, $valoresPermitidos, true)
                ) {
                    $erros[] = 'Opção inválida no campo ' . $campo['label'] . '.';
                    continue;
                }

                $valoresSelecionados[] = (string) $item;
            }

            $valor = implode(',', $valoresSelecionados);
        }

        // ----------------------------------------------------
        // SELECT E RADIO
        // ----------------------------------------------------

        if (in_array($tipo, ['select', 'radio'], true)) {

            $valoresPermitidos = array_map(
                'strval',
                array_column(
                    $opcoesCampos[(int) $campo['id']] ?? [],
                    'valor'
                )
            );

            if (
                $valor !== null &&
                $valor !== '' &&
                (
                    !is_scalar($valor) ||
                    !in_array((string) $valor, $valoresPermitidos, true)
                )
            ) {
                $erros[] = 'Opção inválida no campo ' . $campo['label'] . '.';
                continue;
            }
        }

        // ----------------------------------------------------
        // CAMPOS OBRIGATÓRIOS
        // ----------------------------------------------------

        if ($tipo !== 'imagem') {

            if ($valor === null) {
                $valor = '';
            }

            if (!is_scalar($valor)) {
                $erros[] = 'Valor inválido no campo ' . $campo['label'] . '.';
                continue;
            }

            if (
                $campo['obrigatorio'] == 1 &&
                trim((string) $valor) === ''
            ) {
                $erros[] = 'O campo ' . $campo['label'] . ' é obrigatório.';
                continue;
            }
        }

        // ----------------------------------------------------
        // CAMPOS HIDDEN
        // ----------------------------------------------------

        if ($tipo === 'hidden') {

            // Nunca confia no valor enviado pelo navegador.
            if ($modoEdicao) {
                $valor = $registroAtual[$nome];
            } else {
                $valor = $campo['valor'] ?? '';
            }
        }

        // ----------------------------------------------------
        // DATA
        // ----------------------------------------------------

        if ($tipo === 'date' && $valor !== '') {

            $data = DateTimeImmutable::createFromFormat(
                '!Y-m-d',
                (string) $valor
            );

            if (
                !$data ||
                $data->format('Y-m-d') !== $valor
            ) {
                $erros[] = 'Data inválida no campo ' . $campo['label'] . '.';
                continue;
            }
        }

        // ----------------------------------------------------
        // DATA E HORA
        // ----------------------------------------------------

        if ($tipo === 'datetime' && $valor !== '') {

            $data = DateTimeImmutable::createFromFormat(
                '!Y-m-d\TH:i',
                (string) $valor
            );

            if (
                !$data ||
                $data->format('Y-m-d\TH:i') !== $valor
            ) {
                $erros[] = 'Data e hora inválidas no campo ' . $campo['label'] . '.';
                continue;
            }

            $valor = $data->format('Y-m-d H:i:s');
        }

        // ----------------------------------------------------
        // NÚMEROS NO FORMATO BRASILEIRO
        // ----------------------------------------------------

        if ($tipo === 'text') {

            $valorTrim = trim((string) $valor);

            if (
                preg_match('/^-?\d{1,3}(\.\d{3})*,\d+$/', $valorTrim) ||
                preg_match('/^-?\d+,\d+$/', $valorTrim)
            ) {
                $valor = str_replace('.', '', $valorTrim);
                $valor = str_replace(',', '.', $valor);
            }
        }

        // ----------------------------------------------------
        // IMAGENS
        // ----------------------------------------------------

        if ($tipo === 'imagem') {

            $arquivo = $_FILES[$nome] ?? null;

            // Nenhuma imagem nova foi enviada.
            if (
                !$arquivo ||
                $arquivo['error'] === UPLOAD_ERR_NO_FILE
            ) {

                if ($modoEdicao) {

                    // Preserva a imagem existente.
                    $valor = $registroAtual[$nome] ?? '';

                    if (
                        $campo['obrigatorio'] == 1 &&
                        $valor === ''
                    ) {
                        $erros[] = 'O campo ' . $campo['label'] . ' é obrigatório.';
                    }

                    $dados[$nome] = $valor;

                } elseif ($campo['obrigatorio'] == 1) {

                    $erros[] = 'O campo ' . $campo['label'] . ' é obrigatório.';
                }

                continue;
            }

            if ($arquivo['error'] !== UPLOAD_ERR_OK) {
                $erros[] = 'Erro no envio da imagem do campo ' . $campo['label'] . '.';
                continue;
            }

            if ($arquivo['size'] > 5 * 1024 * 1024) {
                $erros[] = 'A imagem do campo ' . $campo['label'] . ' ultrapassa 5 MB.';
                continue;
            }

            $mime = (new finfo(FILEINFO_MIME_TYPE))->file(
                $arquivo['tmp_name']
            );

            $formatosPermitidos = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/gif' => 'gif',
                'image/webp' => 'webp'
            ];

            if (!isset($formatosPermitidos[$mime])) {
                $erros[] = 'Formato de imagem não permitido no campo ' . $campo['label'] . '.';
                continue;
            }

            $arquivosPendentes[$nome] = [
                'arquivo' => $arquivo,
                'extensao' => $formatosPermitidos[$mime]
            ];

            continue;
        }

        $dados[$nome] = $valor;
    }

    // --------------------------------------------------------
    // MOVIMENTAÇÃO DOS ARQUIVOS
    // --------------------------------------------------------

    if (!$erros && $arquivosPendentes) {

        // Mantém o diretório utilizado pelo sistema legado.
        $diretorio = dirname(__DIR__) . '/uploads/';

        if (
            !is_dir($diretorio) &&
            !mkdir($diretorio, 0755, true)
        ) {
            $erros[] = 'Não foi possível preparar o diretório de imagens.';
        }

        foreach ($arquivosPendentes as $nome => $item) {

            $nomeArquivo = bin2hex(random_bytes(24))
                . '.'
                . $item['extensao'];

            $destino = $diretorio . $nomeArquivo;

            if (
                !move_uploaded_file(
                    $item['arquivo']['tmp_name'],
                    $destino
                )
            ) {
                $erros[] = 'Não foi possível salvar uma das imagens.';
                break;
            }

            $dados[$nome] = '/uploads/' . $nomeArquivo;
        }
    }

    // --------------------------------------------------------
    // GRAVAÇÃO NO BANCO
    // --------------------------------------------------------

    if (!$erros) {

        try {

            if ($modoEdicao) {

                upd(
                    $tabelaDestino,
                    $dados,
                    ['id' => $registroId]
                );

                $mensagem = 'Registro atualizado com sucesso!';

                // Retorna à listagem após atualizar.
                $modoEdicao = false;
                $registroAtual = null;

            } else {

                $resultado = ins(
                    $tabelaDestino,
                    $dados
                );

                if ($resultado === false) {
                    throw new RuntimeException('Falha na inserção.');
                }

                $mensagem = 'Registro inserido com sucesso!';
            }

        } catch (Throwable $e) {

            error_log($e->getMessage());
            $erros[] = 'Ocorreu um erro ao salvar os dados.';
        }
    }
}

// ============================================================
// 11. RENDERIZAÇÃO DO FORMULÁRIO
// ============================================================

?>

<div class="card">

    <div class="card-header">
        <h4 class="mb-0">
            <?= $escape($formulario['nome']) ?>

            <?php if ($modoEdicao): ?>
                <small class="text-muted"> / Editar registro #<?= $registroId ?></small>
            <?php endif; ?>
        </h4>
    </div>

    <div class="card-body">

        <?php if ($mensagem !== ''): ?>
            <div class="alert alert-success">
                <?= $escape($mensagem) ?>
            </div>
        <?php endif; ?>

        <?php if ($erros): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ($erros as $erro): ?>
                        <li><?= $escape($erro) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (!$modoEdicao): ?>

            <button
                type="button"
                class="btn btn-success mb-3"
                data-bs-toggle="collapse"
                data-bs-target="#novoRegistro"
            >
                <i class="fa-solid fa-plus"></i> Novo registro
            </button>

        <?php endif; ?>

        <div
            class="<?= $modoEdicao ? '' : 'collapse' ?>"
            id="novoRegistro"
            <?= $modoEdicao ? '' : '' ?>
        >

            <form method="post" enctype="multipart/form-data">

                <?= csrf() ?>

                <input type="hidden" name="acao" value="salvar">

                <?php foreach ($campos as $campo): ?>

                    <?php

                    $nome = (string) $campo['campo_destino'];
                    $tipo = (string) $campo['tipo'];

                    $obrigatorio = (bool) $campo['obrigatorio'];

                    $desabilitado = (
                        $modoEdicao &&
                        (int) $campo['editavel'] !== 1
                    );

                    // Na edição, prioriza os dados enviados em caso de erro.
                    // Caso contrário, utiliza os valores do banco.
                    $valorAnterior = $_POST[$nome] ??
                        ($registroAtual[$nome] ?? '');

                    // O ponto de acesso é controlado pelo servidor.
                    if ($nome === 'pontodeacesso') {
                        continue;
                    }

                    // Converte data e hora para o formato do input HTML.
                    if (
                        $tipo === 'datetime' &&
                        $valorAnterior !== '' &&
                        !isset($_POST[$nome])
                    ) {
                        $valorAnterior = date(
                            'Y-m-d\TH:i',
                            strtotime($valorAnterior)
                        );
                    }

                    // Prepara os valores selecionados.
                    if ($tipo === 'checkbox') {

                        $selecionados = is_array($valorAnterior)
                            ? array_map('strval', $valorAnterior)
                            : explode(',', (string) $valorAnterior);

                    } else {

                        $selecionados = [(string) $valorAnterior];
                    }

                    ?>

                    <div class="mb-3">

                        <label class="form-label">

                            <?= $escape($campo['label']) ?>

                            <?php if ($obrigatorio): ?>
                                <span class="text-danger">*</span>
                            <?php endif; ?>

                        </label>

                        <?php if ($tipo === 'text'): ?>

                            <input
                                type="text"
                                name="<?= $escape($nome) ?>"
                                class="form-control"
                                value="<?= $escape(is_scalar($valorAnterior) ? $valorAnterior : '') ?>"
                                <?= $obrigatorio ? 'required' : '' ?>
                                <?= $desabilitado ? 'disabled' : '' ?>
                            >

                        <?php elseif ($tipo === 'textarea'): ?>

                            <textarea
                                name="<?= $escape($nome) ?>"
                                class="form-control"
                                rows="4"
                                <?= $obrigatorio ? 'required' : '' ?>
                                <?= $desabilitado ? 'disabled' : '' ?>
                            ><?= $escape(is_scalar($valorAnterior) ? $valorAnterior : '') ?></textarea>

                        <?php elseif ($tipo === 'date'): ?>

                            <input
                                type="date"
                                name="<?= $escape($nome) ?>"
                                class="form-control"
                                value="<?= $escape(is_scalar($valorAnterior) ? $valorAnterior : '') ?>"
                                <?= $obrigatorio ? 'required' : '' ?>
                                <?= $desabilitado ? 'disabled' : '' ?>
                            >

                        <?php elseif ($tipo === 'datetime'): ?>

                            <input
                                type="datetime-local"
                                name="<?= $escape($nome) ?>"
                                class="form-control"
                                value="<?= $escape(is_scalar($valorAnterior) ? $valorAnterior : '') ?>"
                                <?= $obrigatorio ? 'required' : '' ?>
                                <?= $desabilitado ? 'disabled' : '' ?>
                            >

                        <?php elseif ($tipo === 'hidden'): ?>

                            <input
                                type="hidden"
                                name="<?= $escape($nome) ?>"
                                value="<?= $escape(
                                    $modoEdicao
                                        ? ($registroAtual[$nome] ?? '')
                                        : ($campo['valor'] ?? '')
                                ) ?>"
                            >

                        <?php elseif ($tipo === 'imagem'): ?>

                            <?php if ($modoEdicao && !empty($registroAtual[$nome])): ?>

                                <div class="mb-2">
                                    <a
                                        href="<?= $escape($registroAtual[$nome]) ?>"
                                        target="_blank"
                                        rel="noopener"
                                    >
                                        Abrir imagem atual
                                    </a>
                                </div>

                            <?php endif; ?>

                            <input
                                type="file"
                                name="<?= $escape($nome) ?>"
                                class="form-control"
                                accept="image/jpeg,image/png,image/gif,image/webp"
                                <?= ($obrigatorio && !$modoEdicao) ? 'required' : '' ?>
                                <?= $desabilitado ? 'disabled' : '' ?>
                            >

                            <small class="text-muted">
                                JPG, PNG, GIF ou WEBP. Máximo: 5 MB.
                            </small>

                        <?php elseif ($tipo === 'select'): ?>

                            <select
                                name="<?= $escape($nome) ?>"
                                class="form-select"
                                <?= $obrigatorio ? 'required' : '' ?>
                                <?= $desabilitado ? 'disabled' : '' ?>
                            >

                                <option value="">-- selecione --</option>

                                <?php foreach ($opcoesCampos[(int) $campo['id']] ?? [] as $opcao): ?>

                                    <option
                                        value="<?= $escape($opcao['valor']) ?>"
                                        <?= (
                                            (string) $valorAnterior ===
                                            (string) $opcao['valor']
                                        ) ? 'selected' : '' ?>
                                    >
                                        <?= $escape($opcao['label']) ?>
                                    </option>

                                <?php endforeach; ?>

                            </select>

                        <?php elseif (in_array($tipo, ['radio', 'checkbox'], true)): ?>

                            <?php

                            $nomeInput = $tipo === 'checkbox'
                                ? $nome . '[]'
                                : $nome;

                            ?>

                            <?php foreach ($opcoesCampos[(int) $campo['id']] ?? [] as $indice => $opcao): ?>

                                <div class="form-check">

                                    <input
                                        type="<?= $tipo ?>"
                                        name="<?= $escape($nomeInput) ?>"
                                        id="campo_<?= (int) $campo['id'] ?>_<?= (int) $indice ?>"
                                        class="form-check-input"
                                        value="<?= $escape($opcao['valor']) ?>"
                                        <?= in_array(
                                            (string) $opcao['valor'],
                                            $selecionados,
                                            true
                                        ) ? 'checked' : '' ?>
                                        <?= (
                                            $obrigatorio &&
                                            $tipo === 'radio' &&
                                            $indice === 0
                                        ) ? 'required' : '' ?>
                                        <?= $desabilitado ? 'disabled' : '' ?>
                                    >

                                    <label
                                        class="form-check-label"
                                        for="campo_<?= (int) $campo['id'] ?>_<?= (int) $indice ?>"
                                    >
                                        <?= $escape($opcao['label']) ?>
                                    </label>

                                </div>

                            <?php endforeach; ?>

                        <?php endif; ?>

                    </div>

                <?php endforeach; ?>

                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-floppy-disk"></i>
                    <?= $modoEdicao ? 'Atualizar registro' : 'Salvar registro' ?>
                </button>

                <?php if ($modoEdicao): ?>

                    <a
                        href="?formulario&c=<?= $escape($chave) ?>"
                        class="btn btn-secondary"
                    >
                        Voltar
                    </a>

                <?php endif; ?>

            </form>

        </div>

    </div>

</div>

<?php

// ============================================================
// 12. LISTAGEM DOS REGISTROS
// ============================================================

if (!$modoEdicao):

    try {

        $sqlLista = "SELECT * FROM `$tabelaDestino`";

        if ($filtroSql !== '') {
            $sqlLista .= " WHERE $filtroSql";
        }

        // Ordenação configurada pelo administrador.
        $ordem = trim((string) $formulario['orderby_personalizado']);

        if ($ordem === '') {
            $ordem = 'id DESC';
        }

        if (!preg_match(
            '/\A[a-zA-Z0-9_]+(?:\s+(?:ASC|DESC))?(?:\s*,\s*[a-zA-Z0-9_]+(?:\s+(?:ASC|DESC))?)*\z/i',
            $ordem
        )) {
            throw new RuntimeException('Ordenação inválida.');
        }

        $sqlLista .= " ORDER BY $ordem";

        $stmtLista = $pdo->prepare($sqlLista);
        $stmtLista->execute($parametrosFiltro);

        $registros = $stmtLista->fetchAll(PDO::FETCH_ASSOC);

    } catch (Throwable $e) {

        error_log($e->getMessage());

        $registros = [];

        echo '<div class="alert alert-danger">Não foi possível carregar a listagem.</div>';
    }

?>

<div class="card mt-4">

    <div class="card-header">
        <h5 class="mb-0">Registros cadastrados</h5>
    </div>

    <div class="card-body">

        <div class="mb-3">
            <input
                type="search"
                id="pesquisarRegistros"
                class="form-control"
                placeholder="Pesquisar registros..."
            >
        </div>

        <div class="table-responsive">

            <table class="table table-hover align-middle">

                <thead>
                    <tr>

                        <th>ID</th>

                        <?php foreach ($campos as $campo): ?>

                            <?php if (
                                $campo['listavel'] == 1 &&
                                $campo['campo_destino'] !== 'pontodeacesso'
                            ): ?>

                                <th><?= $escape($campo['label']) ?></th>

                            <?php endif; ?>

                        <?php endforeach; ?>

                        <th>Ações</th>

                    </tr>
                </thead>

                <tbody id="listaRegistros">

                    <?php foreach ($registros as $registro): ?>

                        <tr>

                            <td>#<?= (int) $registro['id'] ?></td>

                            <?php foreach ($campos as $campo): ?>

                                <?php if (
                                    $campo['listavel'] == 1 &&
                                    $campo['campo_destino'] !== 'pontodeacesso'
                                ): ?>

                                    <?php

                                    $nome = $campo['campo_destino'];
                                    $tipo = $campo['tipo'];

                                    $valor = $registro[$nome] ?? '';

                                    ?>

                                    <td>

                                        <?php if ($tipo === 'imagem' && $valor !== ''): ?>

                                            <a
                                                href="<?= $escape($valor) ?>"
                                                target="_blank"
                                                rel="noopener"
                                            >
                                                <img
                                                    src="<?= $escape($valor) ?>"
                                                    alt="Imagem"
                                                    style="width:70px;height:70px;object-fit:cover"
                                                >
                                            </a>

                                        <?php elseif ($tipo === 'date' && $valor !== ''): ?>

                                            <?= $escape(date('d/m/Y', strtotime($valor))) ?>

                                        <?php elseif ($tipo === 'datetime' && $valor !== ''): ?>

                                            <?= $escape(date('d/m/Y H:i:s', strtotime($valor))) ?>

                                        <?php elseif (
                                            in_array($tipo, ['select', 'radio'], true)
                                        ): ?>

                                            <?php

                                            $opcoes = $opcoesCampos[(int) $campo['id']] ?? [];

                                            $rotulo = $valor;

                                            foreach ($opcoes as $opcao) {

                                                if ((string) $opcao['valor'] === (string) $valor) {
                                                    $rotulo = $opcao['label'];
                                                    break;
                                                }
                                            }

                                            ?>

                                            <?= $escape($rotulo) ?>

                                        <?php elseif ($tipo === 'checkbox'): ?>

                                            <?php

                                            $valores = explode(',', (string) $valor);
                                            $rotulos = [];

                                            foreach ($valores as $valorOpcao) {

                                                foreach ($opcoesCampos[(int) $campo['id']] ?? [] as $opcao) {

                                                    if ((string) $opcao['valor'] === (string) $valorOpcao) {
                                                        $rotulos[] = $opcao['label'];
                                                        break;
                                                    }
                                                }
                                            }

                                            ?>

                                            <?= $escape(implode(', ', $rotulos)) ?>

                                        <?php else: ?>

                                            <?= $escape($valor) ?>

                                        <?php endif; ?>

                                    </td>

                                <?php endif; ?>

                            <?php endforeach; ?>

                            <td class="text-nowrap">

                                <a
                                    href="?formulario&c=<?= $escape($chave) ?>&p=editar&i=<?= (int) $registro['id'] ?>"
                                    class="btn btn-sm btn-primary"
                                >
                                    <i class="fa-solid fa-pen"></i>
                                    Editar
                                </a>

                                <?php if ((int) $formulario['permissaoexcluir'] === 1): ?>

                                    <form
                                        method="post"
                                        class="d-inline"
                                        onsubmit="return confirm('Deseja realmente excluir este registro?');"
                                    >

                                        <?= csrf() ?>

                                        <input
                                            type="hidden"
                                            name="acao"
                                            value="excluir"
                                        >

                                        <input
                                            type="hidden"
                                            name="registro_id"
                                            value="<?= (int) $registro['id'] ?>"
                                        >

                                        <button
                                            type="submit"
                                            class="btn btn-sm btn-danger"
                                        >
                                            <i class="fa-solid fa-trash"></i>
                                            Excluir
                                        </button>

                                    </form>

                                <?php endif; ?>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                    <?php if (!$registros): ?>

                        <tr>
                            <td colspan="100%" class="text-center text-muted">
                                Nenhum registro encontrado.
                            </td>
                        </tr>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {

    const pesquisa = document.getElementById('pesquisarRegistros');
    const linhas = document.querySelectorAll('#listaRegistros tr');

    if (!pesquisa) {
        return;
    }

    pesquisa.addEventListener('input', function () {

        const termo = this.value.toLowerCase();

        linhas.forEach(function (linha) {

            const texto = linha.textContent.toLowerCase();

            linha.style.display = texto.includes(termo) ? '' : 'none';

        });

    });

});
</script>

<?php endif; ?>
