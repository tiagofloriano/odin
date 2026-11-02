
<?php

// Impede o acesso de usuários que não sejam administradores.
if (!user_info('admin')) {
    http_response_code(403);
    exit('Acesso negado.');
}

// Função auxiliar para proteger a saída HTML.
$e = static fn($valor) => htmlspecialchars(
    (string) $valor,
    ENT_QUOTES,
    'UTF-8'
);

// Valores padrão do formulário.
$ponto = [
    'id' => '',
    'nome' => '',
    'dominio' => '',
    'logoimg' => '',
    'homepage' => '?home',
    'aceitacadastro' => 0,
    'status' => 1
];

$mensagem = '';
$tipoMensagem = 'success';

// Processa o formulário.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'salvar') {

    $id = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT);

    $nome = trim($_POST['nome'] ?? '');
    $dominio = trim($_POST['dominio'] ?? '');
    $logoimg = trim($_POST['logoimg'] ?? '');
    $homepage = trim($_POST['homepage'] ?? '');

    $aceitacadastro = $_POST['aceitacadastro'] ?? '0';
    $status = $_POST['status'] ?? '1';

    // Recupera os dados enviados para manter o formulário preenchido.
    $ponto = [
        'id' => $id ?: '',
        'nome' => $nome,
        'dominio' => $dominio,
        'logoimg' => $logoimg,
        'homepage' => $homepage,
        'aceitacadastro' => $aceitacadastro,
        'status' => $status
    ];

    // Valida os dados.
    if ($nome === '') {
        $mensagem = 'Informe o nome do ponto de acesso.';
        $tipoMensagem = 'danger';

    } elseif (strlen($logoimg) > 200 || strlen($homepage) > 50) {
        $mensagem = 'Um dos campos ultrapassou o limite de caracteres.';
        $tipoMensagem = 'danger';

    } elseif (!in_array((string) $aceitacadastro, ['0', '1'], true)
        || !in_array((string) $status, ['0', '1'], true)) {

        $mensagem = 'Valor inválido para as opções do ponto de acesso.';
        $tipoMensagem = 'danger';

    } else {

        $dados = [
            'nome' => $nome,
            'dominio' => $dominio !== '' ? $dominio : null,
            'logoimg' => $logoimg !== '' ? $logoimg : null,
            'homepage' => $homepage !== '' ? $homepage : '?home',
            'aceitacadastro' => (int) $aceitacadastro,
            'status' => (int) $status
        ];

        try {

            if ($id) {

                // Atualiza um ponto de acesso existente.
                upd('pontodeacesso', $dados, ['id' => $id]);

                $ponto['id'] = $id;
                $mensagem = 'Ponto de acesso atualizado com sucesso.';

            } else {

                // Insere um novo ponto de acesso.
                $novoId = ins('pontodeacesso', $dados);

                $mensagem = 'Ponto de acesso cadastrado com sucesso.';

                // Limpa o formulário após a inclusão.
                $ponto = [
                    'id' => '',
                    'nome' => '',
                    'dominio' => '',
                    'logoimg' => '',
                    'homepage' => '?home',
                    'aceitacadastro' => 0,
                    'status' => 1
                ];
            }

        } catch (Throwable $erro) {

            $mensagem = 'Não foi possível salvar o ponto de acesso.';
            $tipoMensagem = 'danger';
        }
    }
}

// Carrega um ponto específico para edição.
elseif (isset($_GET['id'])) {

    $id = filter_var($_GET['id'], FILTER_VALIDATE_INT);

    if ($id && $id > 0) {

        $resultado = sel('pontodeacesso', ['id' => $id], false, 1);

        if (!empty($resultado)) {
            $ponto = $resultado[0];
        } else {
            $mensagem = 'Ponto de acesso não encontrado.';
            $tipoMensagem = 'danger';
        }
    }
}

// Lista todos os pontos de acesso.
$pontos = sel('pontodeacesso', [], 'nome ASC');

?>

<h2>Gerenciar pontos de acesso</h2>

<p class="text-body-secondary">
    Cadastre e gerencie os sistemas disponíveis no Odin.
</p>

<?php if ($mensagem !== ''): ?>
    <div class="alert alert-<?= $e($tipoMensagem) ?> alert-dismissible fade show" role="alert">
        <?= $e($mensagem) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
    </div>
<?php endif; ?>

<div class="row g-3">

    <!-- Formulário -->
    <div class="col-lg-4">

        <div class="card">
            <div class="card-body">

                <h5 class="card-title">
                    <?= $ponto['id'] ? 'Editar ponto de acesso' : 'Novo ponto de acesso' ?>
                </h5>

                <form method="post">

                    <input type="hidden" name="acao" value="salvar">
                    <input type="hidden" name="id" value="<?= $e($ponto['id']) ?>">

                    <div class="mb-3">
                        <label for="nome" class="form-label">Nome</label>
                        <input
                            type="text"
                            class="form-control"
                            id="nome"
                            name="nome"
                            value="<?= $e($ponto['nome']) ?>"
                            required
                        >
                    </div>

                    <div class="mb-3">
                        <label for="dominio" class="form-label">Domínio</label>
                        <input
                            type="text"
                            class="form-control"
                            id="dominio"
                            name="dominio"
                            value="<?= $e($ponto['dominio']) ?>"
                            placeholder="exemplo.com.br"
                        >
                    </div>

                    <div class="mb-3">
                        <label for="logoimg" class="form-label">Logotipo</label>
                        <input
                            type="text"
                            class="form-control"
                            id="logoimg"
                            name="logoimg"
                            maxlength="200"
                            value="<?= $e($ponto['logoimg']) ?>"
                            placeholder="caminho/da/imagem.png"
                        >
                    </div>

                    <div class="mb-3">
                        <label for="homepage" class="form-label">Página inicial</label>
                        <input
                            type="text"
                            class="form-control"
                            id="homepage"
                            name="homepage"
                            maxlength="50"
                            value="<?= $e($ponto['homepage']) ?>"
                        >
                    </div>

                    <div class="mb-3">
                        <label for="aceitacadastro" class="form-label">
                            Aceita novos cadastros?
                        </label>
                        <select class="form-select" id="aceitacadastro" name="aceitacadastro">
                            <option value="1" <?= (string) $ponto['aceitacadastro'] === '1' ? 'selected' : '' ?>>
                                Sim
                            </option>
                            <option value="0" <?= (string) $ponto['aceitacadastro'] === '0' ? 'selected' : '' ?>>
                                Não
                            </option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="1" <?= (string) $ponto['status'] === '1' ? 'selected' : '' ?>>
                                Ativo
                            </option>
                            <option value="0" <?= (string) $ponto['status'] === '0' ? 'selected' : '' ?>>
                                Inativo
                            </option>
                        </select>
                    </div>

                    <div class="d-flex gap-2">

                        <button type="submit" class="btn btn-padrao">
                            <?= $ponto['id'] ? 'Salvar alterações' : 'Cadastrar' ?>
                        </button>

                        <?php if ($ponto['id']): ?>
                            <a href="?pontosdeacesso" class="btn btn-outline-secondary">
                                Cancelar/Voltar
                            </a>
                        <?php endif; ?>

                    </div>

                </form>

            </div>
        </div>

    </div>

    <!-- Listagem -->
    <div class="col-lg-8">

        <div class="card">
            <div class="card-body">

                <h5 class="card-title">Pontos cadastrados</h5>

                <?php if (empty($pontos)): ?>

                    <p class="text-body-secondary mb-0">
                        Nenhum ponto de acesso cadastrado.
                    </p>

                <?php else: ?>

                    <div class="table-responsive">

                        <table class="table table-hover align-middle mb-0">

                            <thead>
                                <tr>
                                    <th>Nome</th>
                                    <th>Domínio</th>
                                    <th>Status</th>
                                    <th class="text-end">Ação</th>
                                </tr>
                            </thead>

                            <tbody>

                                <?php foreach ($pontos as $item): ?>

                                    <tr>
                                        <td><?= $e($item['nome']) ?></td>

                                        <td><?= $e($item['dominio']) ?></td>

                                        <td>
                                            <?php if ((int) $item['status'] === 1): ?>
                                                <span class="badge text-bg-success">Ativo</span>
                                            <?php else: ?>
                                                <span class="badge text-bg-secondary">Inativo</span>
                                            <?php endif; ?>
                                        </td>

                                        <td class="text-end">
                                            <a
                                                href="?pontosdeacesso&id=<?= (int) $item['id'] ?>"
                                                class="btn btn-sm btn-outline-padrao"
                                            >
                                                <i class="fa-solid fa-pen-to-square"></i>
                                                Editar
                                            </a>
                                        </td>
                                    </tr>

                                <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>

                <?php endif; ?>

            </div>
        </div>

    </div>

</div>
