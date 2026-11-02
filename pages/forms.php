<?php

// Somente administradores podem acessar o criador de formulários.
if (!user_info('admin')) {
    http_response_code(403);
    exit('Acesso negado.');
}

$erro = '';
$sucesso = '';
$id = (int) ($_GET['id'] ?? 0);

$e = static fn($valor) => htmlspecialchars(
    (string) $valor,
    ENT_QUOTES,
    'UTF-8'
);

// Recupera pontos de acesso e usuários.
$pontos = sel('pontodeacesso', [], 'nome ASC');
$usuarios = sel('usuarios', [], 'nome ASC');

// Valores padrão.
$formulario = [
    'nome' => '',
    'descricao' => '',
    'tabela_destino' => '',
    'where_personalizado' => '',
    'orderby_personalizado' => '',
    'jspersonalizado' => '',
    'permissaoexcluir' => 1,
    'permissaopontodeacesso' => '',
    'permissaousuario' => ''
];

// Carrega formulário existente.
if ($id > 0) {

    $resultado = sel('formularios', ['id' => $id], false, 1);

    if (empty($resultado)) {
        $erro = 'Formulário não encontrado.';
        $id = 0;
    } else {
        $formulario = $resultado[0];
    }
}

// Processamento do formulário.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Proteção CSRF.
    if (!csrf(1)) {
        http_response_code(403);
        exit('Requisição inválida. Atualize a página.');
    }

    $id = (int) ($_POST['id'] ?? 0);

    $nome = trim($_POST['nome'] ?? '');
    $descricao = trim($_POST['descricao'] ?? '');
    $tabela = trim($_POST['tabela_destino'] ?? '');
    $where = trim($_POST['where_personalizado'] ?? '');
    $orderby = trim($_POST['orderby_personalizado'] ?? '');
    $javascript = trim($_POST['jspersonalizado'] ?? '');

    $excluir = (int) ($_POST['permissaoexcluir'] ?? 0);

    // Recupera permissões e transforma em listas de IDs.
    $permissoesPontos = array_values(array_unique(array_filter(
        array_map('intval', $_POST['permissaopontodeacesso'] ?? []),
        fn($v) => $v > 0
    )));

    $permissoesUsuarios = array_values(array_unique(array_filter(
        array_map('intval', $_POST['permissaousuario'] ?? []),
        fn($v) => $v > 0
    )));

    // Mantém os dados preenchidos em caso de erro.
    $formulario = [
        'nome' => $nome,
        'descricao' => $descricao,
        'tabela_destino' => $tabela,
        'where_personalizado' => $where,
        'orderby_personalizado' => $orderby,
        'jspersonalizado' => $javascript,
        'permissaoexcluir' => $excluir,
        'permissaopontodeacesso' => implode(',', $permissoesPontos),
        'permissaousuario' => implode(',', $permissoesUsuarios)
    ];

    // Valida os dados.
    if ($nome === '' || $tabela === '') {
        $erro = 'Informe o nome e a tabela de destino.';
    }
    elseif (!preg_match('/\A[a-zA-Z0-9_]+\z/', $tabela)) {
        $erro = 'Nome de tabela inválido. Utilize apenas letras, números e underline.';
    }
    elseif (!in_array($excluir, [0, 1], true)) {
        $erro = 'Permissão de exclusão inválida.';
    }
    else {

        // Confirma que os IDs selecionados existem.
        $pontosValidos = true;

        foreach ($permissoesPontos as $pontoId) {
            if (empty(sel('pontodeacesso', ['id' => $pontoId], false, 1))) {
                $pontosValidos = false;
                break;
            }
        }

        $usuariosValidos = true;

        foreach ($permissoesUsuarios as $usuarioId) {
            if (empty(sel('usuarios', ['id' => $usuarioId], false, 1))) {
                $usuariosValidos = false;
                break;
            }
        }

        if (!$pontosValidos || !$usuariosValidos) {
            $erro = 'Uma das permissões selecionadas não é válida.';
        } else {

            $dados = [
                'nome' => $nome,
                'descricao' => $descricao,
                'tabela_destino' => $tabela,
                'where_personalizado' => $where,
                'orderby_personalizado' => $orderby,
                'jspersonalizado' => $javascript,
                'permissaoexcluir' => $excluir,
                'permissaopontodeacesso' => implode(',', $permissoesPontos),
                'permissaousuario' => implode(',', $permissoesUsuarios),
                'permissaogrupo' => ''
            ];

            try {

                if ($id > 0) {

                    // Atualiza formulário existente.
                    upd('formularios', $dados, ['id' => $id]);

                    $sucesso = 'Formulário atualizado com sucesso!';

                } else {

                    // Gera chave única para acesso ao formulário.
                    $dados['chave'] = bin2hex(random_bytes(32));

                    $id = ins('formularios', $dados);

                    $sucesso = 'Formulário criado com sucesso!';
                }

                // Mantém o formulário aberto após salvar.
                $resultado = sel('formularios', ['id' => $id], false, 1);

                if (!empty($resultado)) {
                    $formulario = $resultado[0];
                }

            } catch (Throwable $ex) {
                $erro = 'Não foi possível salvar o formulário.';
            }
        }
    }
}

// Recupera permissões selecionadas.
$pontosSelecionados = array_filter(
    array_map('intval', explode(',', $formulario['permissaopontodeacesso'] ?? ''))
);

$usuariosSelecionados = array_filter(
    array_map('intval', explode(',', $formulario['permissaousuario'] ?? ''))
);

// Lista todos os formulários.
$lista = sel('formularios', [], 'criado_em DESC');

?>

<h2>Criador de formulários</h2>

<p class="text-body-secondary">
    Configure formulários dinâmicos para administrar os dados das aplicações.
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

    <!-- Configuração do formulário -->
    <div class="col-xl-5">

        <div class="card">
            <div class="card-body">

                <h5 class="card-title">
                    <?= $id > 0 ? 'Editar formulário' : 'Novo formulário' ?>
                </h5>

                <form method="POST">

                    <?= csrf() ?>

                    <input type="hidden" name="id" value="<?= $id ?>">

                    <div class="mb-3">
                        <label for="nome" class="form-label">Nome do formulário</label>
                        <input
                            type="text"
                            class="form-control"
                            id="nome"
                            name="nome"
                            maxlength="100"
                            value="<?= $e($formulario['nome']) ?>"
                            required
                        >
                    </div>

                    <div class="mb-3">
                        <label for="descricao" class="form-label">Descrição</label>
                        <textarea
                            class="form-control"
                            id="descricao"
                            name="descricao"
                            rows="3"
                        ><?= $e($formulario['descricao']) ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="tabela_destino" class="form-label">
                            Tabela de destino
                        </label>
                        <input
                            type="text"
                            class="form-control"
                            id="tabela_destino"
                            name="tabela_destino"
                            maxlength="100"
                            value="<?= $e($formulario['tabela_destino']) ?>"
                            placeholder="Ex.: produtos"
                            required
                        >
                        <div class="form-text">
                            A tabela deve estar previamente criada no banco de dados.
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
                            placeholder="Ex.: status = 1"
                        ><?= $e($formulario['where_personalizado']) ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="orderby_personalizado" class="form-label">
                            ORDER BY personalizado
                        </label>
                        <input
                            type="text"
                            class="form-control"
                            id="orderby_personalizado"
                            name="orderby_personalizado"
                            value="<?= $e($formulario['orderby_personalizado']) ?>"
                            placeholder="Ex.: nome ASC"
                        >
                    </div>

                    <div class="mb-3">
                        <label for="jspersonalizado" class="form-label">
                            JavaScript personalizado
                        </label>
                        <textarea
                            class="form-control font-monospace"
                            id="jspersonalizado"
                            name="jspersonalizado"
                            rows="8"
                        ><?= $e($formulario['jspersonalizado']) ?></textarea>
                        <div class="form-text">
                            Código JavaScript para personalizar a listagem de registros.
                        </div>
                    </div>

                    <hr>

                    <h5>Permissões</h5>

                    <div class="mb-3">
                        <label for="permissaoexcluir" class="form-label">
                            Permitir exclusão de registros?
                        </label>
                        <select
                            class="form-select"
                            id="permissaoexcluir"
                            name="permissaoexcluir"
                        >
                            <option value="0" <?= (int) $formulario['permissaoexcluir'] === 0 ? 'selected' : '' ?>>
                                Não
                            </option>
                            <option value="1" <?= (int) $formulario['permissaoexcluir'] === 1 ? 'selected' : '' ?>>
                                Sim
                            </option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Pontos de acesso autorizados</label>

                        <div class="border rounded p-3" style="max-height: 200px; overflow-y: auto;">

                            <?php if (empty($pontos)): ?>
                                <span class="text-body-secondary">
                                    Nenhum ponto de acesso cadastrado.
                                </span>
                            <?php else: ?>

                                <?php foreach ($pontos as $ponto): ?>

                                    <div class="form-check">
                                        <input
                                            class="form-check-input"
                                            type="checkbox"
                                            name="permissaopontodeacesso[]"
                                            value="<?= (int) $ponto['id'] ?>"
                                            id="ponto<?= (int) $ponto['id'] ?>"
                                            <?= in_array((int) $ponto['id'], $pontosSelecionados, true) ? 'checked' : '' ?>
                                        >
                                        <label
                                            class="form-check-label"
                                            for="ponto<?= (int) $ponto['id'] ?>"
                                        >
                                            <?= $e($ponto['nome']) ?>
                                        </label>
                                    </div>

                                <?php endforeach; ?>

                            <?php endif; ?>

                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Usuários específicos autorizados</label>

                        <div class="border rounded p-3" style="max-height: 250px; overflow-y: auto;">

                            <?php if (empty($usuarios)): ?>
                                <span class="text-body-secondary">
                                    Nenhum usuário cadastrado.
                                </span>
                            <?php else: ?>

                                <?php foreach ($usuarios as $usuario): ?>

                                    <div class="form-check">
                                        <input
                                            class="form-check-input"
                                            type="checkbox"
                                            name="permissaousuario[]"
                                            value="<?= (int) $usuario['id'] ?>"
                                            id="usuario<?= (int) $usuario['id'] ?>"
                                            <?= in_array((int) $usuario['id'], $usuariosSelecionados, true) ? 'checked' : '' ?>
                                        >
                                        <label
                                            class="form-check-label"
                                            for="usuario<?= (int) $usuario['id'] ?>"
                                        >
                                            <?= $e($usuario['nome']) ?>
                                            <small class="text-body-secondary">
                                                (<?= $e($usuario['usuario']) ?>)
                                            </small>
                                        </label>
                                    </div>

                                <?php endforeach; ?>

                            <?php endif; ?>

                        </div>
                    </div>

                    <div class="d-flex gap-2">

                        <button type="submit" class="btn btn-padrao">
                            <i class="fa-solid fa-floppy-disk me-1"></i>
                            Salvar formulário
                        </button>

                        <?php if ($id > 0): ?>
                            <a href="?forms" class="btn btn-outline-secondary">
                                Cancelar
                            </a>
                        <?php endif; ?>

                    </div>

                </form>

                <?php if ($id > 0): ?>

                    <hr>

                    <a
                        href="?formcampos&id=<?= $id ?>"
                        class="btn btn-outline-primary"
                    >
                        <i class="fa-solid fa-list-check me-1"></i>
                        Configurar campos
                    </a>

                <?php endif; ?>

            </div>
        </div>

    </div>

    <!-- Listagem -->
    <div class="col-xl-7">

        <div class="card">
            <div class="card-body">

                <h5 class="card-title">
                    Formulários cadastrados
                    <span class="badge text-bg-secondary">
                        <?= count($lista) ?>
                    </span>
                </h5>

                <div class="table-responsive">

                    <table class="table table-hover align-middle">

                        <thead>
                            <tr>
                                <th>Nome</th>
                                <th>Tabela</th>
                                <th>Criação</th>
                                <th class="text-end">Ações</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php if (empty($lista)): ?>

                                <tr>
                                    <td colspan="4" class="text-center text-body-secondary">
                                        Nenhum formulário cadastrado.
                                    </td>
                                </tr>

                            <?php else: ?>

                                <?php foreach ($lista as $item): ?>

                                    <tr>
                                        <td>
                                            <strong><?= $e($item['nome']) ?></strong>
                                            <div class="small text-body-secondary">
                                                <?= $e($item['descricao']) ?>
                                            </div>
                                        </td>

                                        <td>
                                            <code><?= $e($item['tabela_destino']) ?></code>
                                        </td>

                                        <td>
                                            <?= $e($item['criado_em']) ?>
                                        </td>

                                        <td class="text-end">
                                            <div class="btn-group">

                                                <a
                                                    href="?formulario&c=<?= $item['chave'] ?>"
                                                    class="btn btn-sm btn-outline-primary"
                                                    title="Acessar formulário"
                                                >
                                                    <i class="fa-solid fa-link"></i>
                                                </a>

                                                <a
                                                    href="?forms&id=<?= (int) $item['id'] ?>"
                                                    class="btn btn-sm btn-outline-primary"
                                                    title="Editar formulário"
                                                >
                                                    <i class="fa-solid fa-pen"></i>
                                                </a>

                                                <a
                                                    href="?formcampos&id=<?= (int) $item['id'] ?>"
                                                    class="btn btn-sm btn-outline-primary"
                                                    title="Configurar campos"
                                                >
                                                    <i class="fa-solid fa-list-check"></i>
                                                </a>

                                            </div>
                                        </td>
                                    </tr>

                                <?php endforeach; ?>

                            <?php endif; ?>

                        </tbody>

                    </table>

                </div>

            </div>
        </div>

    </div>

</div>
