<?php

// Somente administradores podem configurar formulários.
if (!user_info('admin')) {
    http_response_code(403);
    exit('Acesso negado.');
}

$erro = '';
$sucesso = '';
$formularioId = (int) ($_GET['id'] ?? 0);
$campoId = (int) ($_GET['campo'] ?? 0);

$e = static fn($valor) => htmlspecialchars(
    (string) $valor,
    ENT_QUOTES,
    'UTF-8'
);

// Tipos permitidos pela estrutura original da tabela.
$tipos = [
    'text' => 'Input',
    'textarea' => 'Textarea',
    'select' => 'Select',
    'radio' => 'Radio',
    'checkbox' => 'Checkbox',
    'imagem' => 'Imagem (upload)',
    'hidden' => 'Hidden',
    'date' => 'Data',
    'datetime' => 'Data e hora'
];

$tiposComOpcoes = ['select', 'radio', 'checkbox'];

// Verifica o formulário.
$resultado = sel(
    'formularios',
    ['id' => $formularioId],
    false,
    1
);

if (empty($resultado)) {
    http_response_code(404);
    exit('Formulário não encontrado.');
}

$formulario = $resultado[0];

// Valores iniciais do campo.
$campoAtual = [
    'campo_destino' => '',
    'label' => '',
    'tipo' => 'text',
    'obrigatorio' => 0,
    'origem_opcoes' => 'manual',
    'tabela_origem' => '',
    'coluna' => '',
    'valor' => '',
    'where_personalizado' => '',
    'listavel' => 1,
    'editavel' => 1
];

$opcoesManuais = '';

// Recupera campo para edição.
if ($campoId > 0) {

    $resultadoCampo = sel(
        'formularios_campos',
        [
            'id' => $campoId,
            'formulario_id' => $formularioId
        ],
        false,
        1
    );

    if (empty($resultadoCampo)) {
        $erro = 'Campo não encontrado.';
        $campoId = 0;
    } else {

        $campoAtual = $resultadoCampo[0];

        // Recupera as opções manuais existentes.
        if ($campoAtual['origem_opcoes'] === 'manual') {

            $opcoes = sel(
                'formularios_campos_opcoes',
                ['campo_id' => $campoId],
                'id ASC'
            );

            $linhas = [];

            foreach ($opcoes as $opcao) {
                $linhas[] = $opcao['valor'];
            }

            $opcoesManuais = implode("\n", $linhas);
        }
    }
}

// Processa os formulários.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Verifica CSRF.
    if (!csrf(1)) {
        http_response_code(403);
        exit('Requisição inválida. Atualize a página.');
    }

    $acao = $_POST['acao'] ?? '';

    // Exclusão de campo.
    if ($acao === 'excluir') {

        $campoExcluir = (int) ($_POST['campo_id'] ?? 0);

        $campoExiste = sel(
            'formularios_campos',
            [
                'id' => $campoExcluir,
                'formulario_id' => $formularioId
            ],
            false,
            1
        );

        if (empty($campoExiste)) {
            $erro = 'Campo não encontrado.';
        } else {

            global $pdo;

            if (!isset($pdo) || !($pdo instanceof PDO)) {
                con();
            }

            try {

                $pdo->beginTransaction();

                // Remove primeiro as opções associadas.
                del(
                    'formularios_campos_opcoes',
                    ['campo_id' => $campoExcluir]
                );

                // Remove o campo.
                del(
                    'formularios_campos',
                    ['id' => $campoExcluir]
                );

                $pdo->commit();

                $sucesso = 'Campo removido com sucesso!';

                $campoId = 0;

            } catch (Throwable $ex) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                $erro = 'Não foi possível remover o campo.';
            }
        }
    }

    // Cadastro ou edição de campo.
    elseif ($acao === 'salvar') {

        $campoId = (int) ($_POST['campo_id'] ?? 0);

        $campoDestino = trim($_POST['campo_destino'] ?? '');
        $label = trim($_POST['label'] ?? '');
        $tipo = $_POST['tipo'] ?? '';

        $obrigatorio = (int) ($_POST['obrigatorio'] ?? 0);
        $listavel = (int) ($_POST['listavel'] ?? 1);
        $editavel = (int) ($_POST['editavel'] ?? 1);

        $origemOpcoes = $_POST['origem_opcoes'] ?? 'manual';

        $tabelaOrigem = trim($_POST['tabela_origem'] ?? '');
        $coluna = trim($_POST['coluna'] ?? '');
        $valor = trim($_POST['valor'] ?? '');
        $wherePersonalizado = trim($_POST['where_personalizado'] ?? '');

        $opcoesTexto = trim($_POST['opcoes_manuais'] ?? '');

        // Mantém os dados preenchidos.
        $campoAtual = [
            'campo_destino' => $campoDestino,
            'label' => $label,
            'tipo' => $tipo,
            'obrigatorio' => $obrigatorio,
            'origem_opcoes' => $origemOpcoes,
            'tabela_origem' => $tabelaOrigem,
            'coluna' => $coluna,
            'valor' => $valor,
            'where_personalizado' => $wherePersonalizado,
            'listavel' => $listavel,
            'editavel' => $editavel
        ];

        $opcoesManuais = $opcoesTexto;

        // Valida os dados básicos.
        if ($campoDestino === '' || $label === '') {
            $erro = 'Informe o label e a coluna de destino.';
        }
        elseif (!preg_match('/\A[a-zA-Z0-9_]+\z/', $campoDestino)) {
            $erro = 'Nome da coluna de destino inválido.';
        }
        elseif (!array_key_exists($tipo, $tipos)) {
            $erro = 'Tipo de campo inválido.';
        }
        elseif (!in_array($obrigatorio, [0, 1], true)
            || !in_array($listavel, [0, 1], true)
            || !in_array($editavel, [0, 1], true)) {
            $erro = 'Uma das configurações do campo é inválida.';
        }
        else {

            $usaOpcoes = in_array($tipo, $tiposComOpcoes, true);

            // Valida a configuração das opções.
            if ($usaOpcoes && !in_array($origemOpcoes, ['manual', 'tabela'], true)) {
                $erro = 'Selecione uma origem válida para as opções.';
            }
            elseif ($usaOpcoes && $origemOpcoes === 'manual' && $opcoesTexto === '') {
                $erro = 'Informe pelo menos uma opção.';
            }
            elseif ($usaOpcoes && $origemOpcoes === 'tabela'
                && ($tabelaOrigem === '' || $coluna === '')) {
                $erro = 'Informe a tabela de origem e a coluna de exibição.';
            }
            elseif ($usaOpcoes && $origemOpcoes === 'tabela'
                && (
                    !preg_match('/\A[a-zA-Z0-9_]+\z/', $tabelaOrigem)
                    || !preg_match('/\A[a-zA-Z0-9_]+\z/', $coluna)
                    || ($valor !== '' && !preg_match('/\A[a-zA-Z0-9_]+\z/', $valor))
                )) {
                $erro = 'Nome de tabela ou coluna de origem inválido.';
            }
            else {

                // Verifica se a coluna já está configurada no formulário.
                $camposExistentes = sel(
                    'formularios_campos',
                    ['formulario_id' => $formularioId],
                    'id ASC'
                );

                $colunaDuplicada = false;

                foreach ($camposExistentes as $existente) {

                    if (
                        $existente['campo_destino'] === $campoDestino
                        && (int) $existente['id'] !== $campoId
                    ) {
                        $colunaDuplicada = true;
                        break;
                    }
                }

                if ($colunaDuplicada) {
                    $erro = 'Esta coluna já está configurada no formulário.';
                } else {

                    // Prepara os dados respeitando a estrutura legada.
                    $dados = [
                        'formulario_id' => $formularioId,
                        'campo_destino' => $campoDestino,
                        'tipo' => $tipo,
                        'label' => $label,
                        'obrigatorio' => $obrigatorio,
                        'origem_opcoes' => $usaOpcoes ? $origemOpcoes : null,
                        'tabela_origem' => $usaOpcoes && $origemOpcoes === 'tabela'
                            ? $tabelaOrigem : null,
                        'coluna' => $usaOpcoes && $origemOpcoes === 'tabela'
                            ? $coluna : null,
                        'valor' => $usaOpcoes && $origemOpcoes === 'tabela'
                            ? $valor : null,
                        'where_personalizado' => $usaOpcoes && $origemOpcoes === 'tabela'
                            ? $wherePersonalizado : null,
                        'listavel' => $listavel,
                        'editavel' => $editavel
                    ];

                    // Transforma as opções manuais em linhas.
                    $listaOpcoes = [];

                    if ($usaOpcoes && $origemOpcoes === 'manual') {

                        $linhas = preg_split('/\R/u', $opcoesTexto);

                        foreach ($linhas as $linha) {

                            $linha = trim($linha);

                            if ($linha !== '') {
                                $listaOpcoes[] = $linha;
                            }
                        }
                    }

                    if ($usaOpcoes && $origemOpcoes === 'manual' && empty($listaOpcoes)) {
                        $erro = 'Informe pelo menos uma opção válida.';
                    } else {

                        global $pdo;

                        if (!isset($pdo) || !($pdo instanceof PDO)) {
                            con();
                        }

                        try {

                            $pdo->beginTransaction();

                            if ($campoId > 0) {

                                // Atualiza campo existente.
                                upd(
                                    'formularios_campos',
                                    $dados,
                                    [
                                        'id' => $campoId,
                                        'formulario_id' => $formularioId
                                    ]
                                );

                            } else {

                                // Insere novo campo.
                                $campoId = ins(
                                    'formularios_campos',
                                    $dados
                                );
                            }

                            // Remove as opções anteriores.
                            del(
                                'formularios_campos_opcoes',
                                ['campo_id' => $campoId]
                            );

                            // Grava novamente as opções manuais.
                            foreach ($listaOpcoes as $opcao) {

                                ins(
                                    'formularios_campos_opcoes',
                                    [
                                        'campo_id' => $campoId,
                                        'valor' => $opcao,
                                        'label' => $opcao
                                    ]
                                );
                            }

                            $pdo->commit();

                            $sucesso = 'Campo salvo com sucesso!';
                            $campoId = 0;

                            // Limpa o formulário após salvar.
                            $campoAtual = [
                                'campo_destino' => '',
                                'label' => '',
                                'tipo' => 'text',
                                'obrigatorio' => 0,
                                'origem_opcoes' => 'manual',
                                'tabela_origem' => '',
                                'coluna' => '',
                                'valor' => '',
                                'where_personalizado' => '',
                                'listavel' => 1,
                                'editavel' => 1
                            ];

                            $opcoesManuais = '';

                        } catch (Throwable $ex) {

                            if ($pdo->inTransaction()) {
                                $pdo->rollBack();
                            }

                            $erro = 'Não foi possível salvar o campo.';
                        }
                    }
                }
            }
        }
    }
}

// Lista os campos cadastrados.
$campos = sel(
    'formularios_campos',
    ['formulario_id' => $formularioId],
    'id ASC'
);

?>

<h2>Campos do formulário</h2>

<p class="text-body-secondary">
    Formulário: <strong><?= $e($formulario['nome']) ?></strong>
    <br>
    Tabela de destino: <code><?= $e($formulario['tabela_destino']) ?></code>
</p>

<?php if ($erro !== ''): ?>
    <div class="alert alert-danger">
        <?= $e($erro) ?>
    </div>
<?php endif; ?>

<?php if ($sucesso !== ''): ?>
    <div class="alert alert-success">
        <?= $e($sucesso) ?>
    </div>
<?php endif; ?>

<div class="row g-3">

    <!-- Cadastro e edição -->
    <div class="col-xl-5">

        <div class="card">
            <div class="card-body">

                <h5 class="card-title">
                    <?= $campoId > 0 ? 'Editar campo' : 'Adicionar campo' ?>
                </h5>

                <form method="POST">

                    <?= csrf() ?>

                    <input type="hidden" name="acao" value="salvar">
                    <input type="hidden" name="campo_id" value="<?= $campoId ?>">

                    <div class="mb-3">
                        <label for="label" class="form-label">Label do campo</label>
                        <input
                            type="text"
                            class="form-control"
                            id="label"
                            name="label"
                            maxlength="100"
                            value="<?= $e($campoAtual['label']) ?>"
                            required
                        >
                    </div>

                    <div class="mb-3">
                        <label for="campo_destino" class="form-label">
                            Coluna da tabela destino
                        </label>
                        <input
                            type="text"
                            class="form-control"
                            id="campo_destino"
                            name="campo_destino"
                            maxlength="100"
                            value="<?= $e($campoAtual['campo_destino']) ?>"
                            required
                        >
                    </div>

                    <div class="mb-3">
                        <label for="tipo" class="form-label">Tipo de campo</label>
                        <select
                            class="form-select"
                            id="tipo"
                            name="tipo"
                            required
                        >
                            <?php foreach ($tipos as $chave => $descricao): ?>
                                <option
                                    value="<?= $e($chave) ?>"
                                    <?= $campoAtual['tipo'] === $chave ? 'selected' : '' ?>
                                >
                                    <?= $e($descricao) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Configurações das opções -->
                    <div id="configuracaoOpcoes">

                        <hr>

                        <h6>Configuração das opções</h6>

                        <div class="mb-3">
                            <label for="origem_opcoes" class="form-label">
                                Origem das opções
                            </label>
                            <select
                                class="form-select"
                                id="origem_opcoes"
                                name="origem_opcoes"
                            >
                                <option
                                    value="manual"
                                    <?= $campoAtual['origem_opcoes'] === 'manual' ? 'selected' : '' ?>
                                >
                                    Informar manualmente
                                </option>
                                <option
                                    value="tabela"
                                    <?= $campoAtual['origem_opcoes'] === 'tabela' ? 'selected' : '' ?>
                                >
                                    Buscar em outra tabela
                                </option>
                            </select>
                        </div>

                        <div id="opcoesManuais" class="mb-3">
                            <label for="opcoes_manuais" class="form-label">
                                Opções
                            </label>
                            <textarea
                                class="form-control"
                                id="opcoes_manuais"
                                name="opcoes_manuais"
                                rows="6"
                                placeholder="Uma opção por linha"
                            ><?= $e($opcoesManuais) ?></textarea>
                            <div class="form-text">
                                Cada linha será uma opção. O valor armazenado será igual ao texto apresentado.
                            </div>
                        </div>

                        <div id="opcoesTabela">

                            <div class="mb-3">
                                <label for="tabela_origem" class="form-label">
                                    Tabela de origem
                                </label>
                                <input
                                    type="text"
                                    class="form-control"
                                    id="tabela_origem"
                                    name="tabela_origem"
                                    value="<?= $e($campoAtual['tabela_origem']) ?>"
                                >
                            </div>

                            <div class="mb-3">
                                <label for="coluna" class="form-label">
                                    Coluna a exibir
                                </label>
                                <input
                                    type="text"
                                    class="form-control"
                                    id="coluna"
                                    name="coluna"
                                    value="<?= $e($campoAtual['coluna']) ?>"
                                >
                            </div>

                            <div class="mb-3">
                                <label for="valor" class="form-label">
                                    Coluna do valor
                                </label>
                                <input
                                    type="text"
                                    class="form-control"
                                    id="valor"
                                    name="valor"
                                    value="<?= $e($campoAtual['valor']) ?>"
                                >
                                <div class="form-text">
                                    Se ficar vazio, será utilizada a mesma coluna de exibição.
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="where_personalizado" class="form-label">
                                    WHERE personalizado
                                </label>
                                <textarea
                                    class="form-control"
                                    id="where_personalizado"
                                    name="where_personalizado"
                                    rows="2"
                                ><?= $e($campoAtual['where_personalizado']) ?></textarea>
                            </div>

                        </div>

                    </div>

                    <hr>

                    <div class="mb-3">
                        <label for="obrigatorio" class="form-label">
                            Preenchimento obrigatório?
                        </label>
                        <select
                            class="form-select"
                            id="obrigatorio"
                            name="obrigatorio"
                        >
                            <option value="0" <?= (int) $campoAtual['obrigatorio'] === 0 ? 'selected' : '' ?>>
                                Não
                            </option>
                            <option value="1" <?= (int) $campoAtual['obrigatorio'] === 1 ? 'selected' : '' ?>>
                                Sim
                            </option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="listavel" class="form-label">
                            Aparecer na listagem?
                        </label>
                        <select
                            class="form-select"
                            id="listavel"
                            name="listavel"
                        >
                            <option value="0" <?= (int) $campoAtual['listavel'] === 0 ? 'selected' : '' ?>>
                                Não
                            </option>
                            <option value="1" <?= (int) $campoAtual['listavel'] === 1 ? 'selected' : '' ?>>
                                Sim
                            </option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="editavel" class="form-label">
                            Permitir edição?
                        </label>
                        <select
                            class="form-select"
                            id="editavel"
                            name="editavel"
                        >
                            <option value="0" <?= (int) $campoAtual['editavel'] === 0 ? 'selected' : '' ?>>
                                Não
                            </option>
                            <option value="1" <?= (int) $campoAtual['editavel'] === 1 ? 'selected' : '' ?>>
                                Sim
                            </option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-padrao">
                        <i class="fa-solid fa-floppy-disk me-1"></i>
                        Salvar campo
                    </button>

                    <?php if ($campoId > 0): ?>
                        <a
                            href="?formcampos&id=<?= $formularioId ?>"
                            class="btn btn-outline-secondary"
                        >
                            Cancelar
                        </a>
                    <?php endif; ?>

                </form>

            </div>
        </div>

    </div>

    <!-- Listagem dos campos -->
    <div class="col-xl-7">

        <div class="card">
            <div class="card-body">

                <h5 class="card-title">
                    Campos cadastrados
                    <span class="badge text-bg-secondary">
                        <?= count($campos) ?>
                    </span>
                </h5>

                <div class="table-responsive">

                    <table class="table table-hover align-middle">

                        <thead>
                            <tr>
                                <th>Campo</th>
                                <th>Tipo</th>
                                <th>Obrigatório</th>
                                <th>Listável</th>
                                <th>Editável</th>
                                <th class="text-end">Ações</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php if (empty($campos)): ?>

                                <tr>
                                    <td colspan="6" class="text-center text-body-secondary">
                                        Nenhum campo cadastrado.
                                    </td>
                                </tr>

                            <?php else: ?>

                                <?php foreach ($campos as $campo): ?>

                                    <tr>
                                        <td>
                                            <strong><?= $e($campo['label']) ?></strong>
                                            <div class="small text-body-secondary">
                                                <?= $e($campo['campo_destino']) ?>
                                            </div>
                                        </td>

                                        <td>
                                            <?= $e($tipos[$campo['tipo']] ?? $campo['tipo']) ?>
                                        </td>

                                        <td><?= (int) $campo['obrigatorio'] === 1 ? 'Sim' : 'Não' ?></td>
                                        <td><?= (int) $campo['listavel'] === 1 ? 'Sim' : 'Não' ?></td>
                                        <td><?= (int) $campo['editavel'] === 1 ? 'Sim' : 'Não' ?></td>

                                        <td class="text-end">

                                            <a
                                                href="?formcampos&id=<?= $formularioId ?>&campo=<?= (int) $campo['id'] ?>"
                                                class="btn btn-sm btn-outline-primary"
                                                title="Editar campo"
                                            >
                                                <i class="fa-solid fa-pen"></i>
                                            </a>

                                            <form
                                                method="POST"
                                                class="d-inline"
                                                onsubmit="return confirm('Deseja realmente remover este campo e suas opções?');"
                                            >
                                                <?= csrf() ?>

                                                <input type="hidden" name="acao" value="excluir">
                                                <input type="hidden" name="campo_id" value="<?= (int) $campo['id'] ?>">

                                                <button
                                                    type="submit"
                                                    class="btn btn-sm btn-outline-danger"
                                                    title="Remover campo"
                                                >
                                                    <i class="fa-solid fa-trash"></i>
                                                </button>
                                            </form>

                                        </td>
                                    </tr>

                                <?php endforeach; ?>

                            <?php endif; ?>

                        </tbody>

                    </table>

                </div>

            </div>
        </div>

        <div class="mt-3">
            <a href="?forms&id=<?= $formularioId ?>" class="btn btn-outline-secondary">
                <i class="fa-solid fa-arrow-left me-1"></i>
                Voltar ao formulário
            </a>
        </div>

    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {

    const tipo = document.getElementById('tipo');
    const origem = document.getElementById('origem_opcoes');

    const configuracao = document.getElementById('configuracaoOpcoes');
    const manuais = document.getElementById('opcoesManuais');
    const tabela = document.getElementById('opcoesTabela');

    const tiposComOpcoes = ['select', 'radio', 'checkbox'];

    function atualizarConfiguracao() {

        const usaOpcoes = tiposComOpcoes.includes(tipo.value);

        configuracao.style.display = usaOpcoes ? 'block' : 'none';

        manuais.style.display =
            usaOpcoes && origem.value === 'manual' ? 'block' : 'none';

        tabela.style.display =
            usaOpcoes && origem.value === 'tabela' ? 'block' : 'none';
    }

    tipo.addEventListener('change', atualizarConfiguracao);
    origem.addEventListener('change', atualizarConfiguracao);

    atualizarConfiguracao();

});
</script>
