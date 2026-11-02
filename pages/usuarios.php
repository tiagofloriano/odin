<?php

// Somente administradores podem gerenciar usuários.
if (!user_info('admin')) {
    http_response_code(403);
    exit('Acesso negado.');
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$erro = '';
$sucesso = '';
$idEdicao = (int) ($_GET['id'] ?? 0);

global $pdo;

// Escapa valores para exibição no HTML.
$e = static fn($valor) => htmlspecialchars(
    (string) $valor,
    ENT_QUOTES,
    'UTF-8'
);

// Gera o token CSRF compartilhado com o cabeçalho.
if (empty($_SESSION['odin_csrf_ponto'])) {
    $_SESSION['odin_csrf_ponto'] = bin2hex(random_bytes(32));
}

// Recupera os pontos de acesso disponíveis.
$pontos = sel('pontodeacesso', [], 'nome ASC');

// Cria um índice dos pontos para facilitar a consulta dos nomes.
$mapaPontos = [];

foreach ($pontos as $ponto) {
    $mapaPontos[(int) $ponto['id']] = $ponto['nome'];
}

// Dados que serão exibidos no formulário.
$usuarioEdicao = [];

// Processa o formulário.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $acao = $_POST['acao'] ?? '';

    if ($acao === 'salvar') {

        $token = (string) ($_POST['csrf_token'] ?? '');

        if (
            !hash_equals(
                (string) $_SESSION['odin_csrf_ponto'],
                $token
            )
        ) {
            $erro = 'Solicitação inválida. Atualize a página e tente novamente.';
        } else {

            $id = (int) ($_POST['id'] ?? 0);
            $nome = trim((string) ($_POST['nome'] ?? ''));
            $usuario = trim((string) ($_POST['usuario'] ?? ''));
            $senha = (string) ($_POST['senha'] ?? '');

            $pontodeacesso = (int) ($_POST['pontodeacesso'] ?? 0);

            $pontosSelecionados = $_POST['pontos'] ?? [];

            if (!is_array($pontosSelecionados)) {
                $pontosSelecionados = [];
            }

            $pontosSelecionados = array_values(
                array_unique(
                    array_filter(
                        array_map('intval', $pontosSelecionados),
                        static fn($valor) => $valor > 0
                    )
                )
            );

            $admin = (int) ($_POST['admin'] ?? 0);
            $escalaFonte = (int) ($_POST['escala_fonte'] ?? 90);
            $status = (int) ($_POST['status'] ?? 1);

            // O ponto atual obrigatoriamente precisa estar na lista de autorizados.
            if ($pontodeacesso > 0) {
                $pontosSelecionados[] = $pontodeacesso;
            }

            $pontosSelecionados = array_values(
                array_unique($pontosSelecionados)
            );

            // Valida os dados básicos.
            if ($nome === '' || $usuario === '') {
                $erro = 'Informe o nome e o usuário.';
            }
            elseif ($pontodeacesso <= 0) {
                $erro = 'Selecione o ponto de acesso atual.';
            }
            elseif (empty($pontosSelecionados)) {
                $erro = 'Selecione pelo menos um ponto de acesso.';
            }
            elseif (!in_array($admin, [0, 1], true) || !in_array($status, [0, 1], true)) {
                $erro = 'Opção de perfil ou status inválida.';
            }
            elseif ($escalaFonte < 70 || $escalaFonte > 120) {
                $erro = 'A escala de fonte deve estar entre 70% e 120%.';
            }
            elseif ($id === 0 && $senha === '') {
                $erro = 'Informe uma senha para o novo usuário.';
            }
            elseif ($senha !== '' && strlen($senha) < 8) {
                $erro = 'A senha deve ter pelo menos 8 caracteres.';
            }
            else {

                // Confirma que todos os pontos selecionados existem.
                $idsExistentes = array_map(
                    static fn($ponto) => (int) $ponto['id'],
                    $pontos
                );

                $pontosInvalidos = array_diff(
                    $pontosSelecionados,
                    $idsExistentes
                );

                if (!empty($pontosInvalidos)) {
                    $erro = 'Um ou mais pontos de acesso selecionados não existem.';
                } else {

                    // O ponto atual fica separado dos demais.
                    $demaisPontos = array_values(
                        array_diff($pontosSelecionados, [$pontodeacesso])
                    );

                    $dados = [
                        'nome' => $nome,
                        'usuario' => $usuario,
                        'pontodeacesso' => $pontodeacesso,
                        'meuspontos' => implode(';', $demaisPontos),
                        'admin' => $admin,
                        'escala_fonte' => $escalaFonte,
                        'status' => $status
                    ];

                    // Inclui a senha somente quando foi informada.
                    if ($senha !== '') {
                        $dados['senha'] = password_hash(
                            $senha,
                            PASSWORD_DEFAULT
                        );
                    }

                    try {

                        if ($id > 0) {

                            // Atualiza usuário existente.
                            upd(
                                'usuarios',
                                $dados,
                                ['id' => $id]
                            );

                            $idEdicao = $id;
                            $sucesso = 'Usuário atualizado com sucesso!';

                            // Se o administrador alterou o próprio cadastro,
                            // atualiza os dados relevantes da sessão.
                            if ($id === (int) user_info('id')) {

                                $_SESSION['odin_escalafonte'] = $escalaFonte;

                                // Se o ponto ativo foi alterado, atualiza a sessão.
                                $_SESSION['odin_pontodeacesso'] = $pontodeacesso;
                            }

                        } else {

                            // Cadastra novo usuário.
                            $idEdicao = ins('usuarios', $dados);

                            $sucesso = 'Usuário cadastrado com sucesso!';
                        }

                    } catch (Throwable $ex) {

                        // Trata possíveis duplicidades de login.
                        if ($ex instanceof PDOException && $ex->getCode() === '23000') {
                            $erro = 'Este nome de usuário já está cadastrado.';
                        } else {
                            $erro = 'Não foi possível salvar o usuário.';
                        }
                    }
                }
            }

            // Mantém os dados informados no formulário em caso de erro.
            if ($erro !== '') {
                $usuarioEdicao = [
                    'id' => $id,
                    'nome' => $nome,
                    'usuario' => $usuario,
                    'pontodeacesso' => $pontodeacesso,
                    'meuspontos' => implode(
                        ';',
                        array_diff($pontosSelecionados, [$pontodeacesso])
                    ),
                    'admin' => $admin,
                    'escala_fonte' => $escalaFonte,
                    'status' => $status
                ];

                $idEdicao = $id;
            }
        }
    }
}

// Recupera o usuário selecionado para edição, caso ainda não tenha dados do POST.
if ($idEdicao > 0 && empty($usuarioEdicao)) {

    $resultado = sel(
        'usuarios',
        ['id' => $idEdicao],
        false,
        1
    );

    if (!empty($resultado)) {
        $usuarioEdicao = $resultado[0];
    } else {
        $erro = 'Usuário não encontrado.';
        $idEdicao = 0;
    }
}

// Recupera os pontos selecionados para marcar as caixas de seleção.
$pontosDoUsuario = [];

if (!empty($usuarioEdicao)) {

    $pontosDoUsuario = array_filter(
        array_map(
            'intval',
            explode(';', (string) ($usuarioEdicao['meuspontos'] ?? ''))
        )
    );

    $pontosDoUsuario[] = (int) ($usuarioEdicao['pontodeacesso'] ?? 0);

    $pontosDoUsuario = array_values(
        array_unique($pontosDoUsuario)
    );
}

// Lista todos os usuários, independentemente do ponto de acesso.
$usuarios = sel('usuarios', [], 'nome ASC');

?>

<h2>Gerenciar usuários</h2>

<p class="text-body-secondary">
    Cadastre e gerencie os usuários de todos os pontos de acesso do Odin.
</p>

<div class="row g-3">

    <!-- Formulário de cadastro e edição -->
    <div class="col-xl-4">

        <div class="card">
            <div class="card-body">

                <h5 class="card-title">
                    <?= $idEdicao > 0 ? 'Editar usuário' : 'Novo usuário' ?>
                </h5>

                <?php if ($erro !== ''): ?>
                    <div class="alert alert-danger" role="alert">
                        <?= $e($erro) ?>
                    </div>
                <?php endif; ?>

                <?php if ($sucesso !== ''): ?>
                    <div class="alert alert-success" role="alert">
                        <?= $e($sucesso) ?>
                    </div>
                <?php endif; ?>

                <form method="POST">

                    <input type="hidden" name="acao" value="salvar">

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= $e($_SESSION['odin_csrf_ponto']) ?>"
                    >

                    <input
                        type="hidden"
                        name="id"
                        value="<?= (int) ($usuarioEdicao['id'] ?? 0) ?>"
                    >

                    <div class="mb-3">
                        <label for="nome" class="form-label">Nome completo</label>
                        <input
                            type="text"
                            class="form-control"
                            id="nome"
                            name="nome"
                            maxlength="100"
                            value="<?= $e($usuarioEdicao['nome'] ?? '') ?>"
                            required
                        >
                    </div>

                    <div class="mb-3">
                        <label for="usuario" class="form-label">Nome de usuário</label>
                        <input
                            type="text"
                            class="form-control"
                            id="usuario"
                            name="usuario"
                            maxlength="50"
                            value="<?= $e($usuarioEdicao['usuario'] ?? '') ?>"
                            required
                        >
                    </div>

                    <div class="mb-3">
                        <label for="senha" class="form-label">
                            <?= $idEdicao > 0 ? 'Nova senha (opcional)' : 'Senha' ?>
                        </label>

                        <input
                            type="password"
                            class="form-control"
                            id="senha"
                            name="senha"
                            minlength="8"
                            autocomplete="new-password"
                            <?= $idEdicao === 0 ? 'required' : '' ?>
                        >

                        <?php if ($idEdicao > 0): ?>
                            <div class="form-text">
                                Deixe em branco para manter a senha atual.
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label for="pontodeacesso" class="form-label">
                            Ponto atual
                        </label>

                        <select
                            class="form-select"
                            id="pontodeacesso"
                            name="pontodeacesso"
                            required
                        >
                            <option value="">Selecione...</option>

                            <?php foreach ($pontos as $ponto): ?>
                                <option
                                    value="<?= (int) $ponto['id'] ?>"
                                    <?= (int) ($usuarioEdicao['pontodeacesso'] ?? 0) === (int) $ponto['id'] ? 'selected' : '' ?>
                                >
                                    <?= $e($ponto['nome']) ?>
                                </option>
                            <?php endforeach; ?>

                        </select>

                        <div class="form-text">
                            Este será o ponto selecionado inicialmente no próximo login.
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">
                            Pontos de acesso autorizados
                        </label>

                        <div class="border rounded p-3">

                            <?php if (empty($pontos)): ?>

                                <div class="text-body-secondary">
                                    Nenhum ponto de acesso cadastrado.
                                </div>

                            <?php else: ?>

                                <?php foreach ($pontos as $ponto): ?>

                                    <div class="form-check mb-2">
                                        <input
                                            class="form-check-input"
                                            type="checkbox"
                                            name="pontos[]"
                                            id="ponto_<?= (int) $ponto['id'] ?>"
                                            value="<?= (int) $ponto['id'] ?>"
                                            <?= in_array((int) $ponto['id'], $pontosDoUsuario, true) ? 'checked' : '' ?>
                                        >

                                        <label
                                            class="form-check-label"
                                            for="ponto_<?= (int) $ponto['id'] ?>"
                                        >
                                            <?= $e($ponto['nome']) ?>
                                        </label>
                                    </div>

                                <?php endforeach; ?>

                            <?php endif; ?>

                        </div>

                        <div class="form-text">
                            O ponto atual também precisa estar selecionado. Se não estiver,
                            ele será incluído automaticamente ao salvar.
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="admin" class="form-label">Perfil de acesso</label>

                        <select class="form-select" id="admin" name="admin">
                            <option value="0" <?= (int) ($usuarioEdicao['admin'] ?? 0) === 0 ? 'selected' : '' ?>>
                                Usuário comum
                            </option>

                            <option value="1" <?= (int) ($usuarioEdicao['admin'] ?? 0) === 1 ? 'selected' : '' ?>>
                                Administrador
                            </option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="escala_fonte" class="form-label">
                            Escala das fontes
                        </label>

                        <select class="form-select" id="escala_fonte" name="escala_fonte">

                            <?php foreach ([70, 75, 80, 85, 90, 95, 100, 105, 110, 115, 120] as $escala): ?>

                                <option
                                    value="<?= $escala ?>"
                                    <?= (int) ($usuarioEdicao['escala_fonte'] ?? 90) === $escala ? 'selected' : '' ?>
                                >
                                    <?= $escala ?>%
                                </option>

                            <?php endforeach; ?>

                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="status" class="form-label">Status</label>

                        <select class="form-select" id="status" name="status">

                            <option value="1" <?= (int) ($usuarioEdicao['status'] ?? 1) === 1 ? 'selected' : '' ?>>
                                Ativo
                            </option>

                            <option value="0" <?= (int) ($usuarioEdicao['status'] ?? 1) === 0 ? 'selected' : '' ?>>
                                Inativo
                            </option>

                        </select>
                    </div>

                    <button type="submit" class="btn btn-padrao">
                        <i class="fa-solid fa-floppy-disk me-1"></i>
                        Salvar usuário
                    </button>

                    <?php if ($idEdicao > 0): ?>
                        <a href="?usuarios" class="btn btn-outline-secondary">
                            Cancelar
                        </a>
                    <?php endif; ?>

                </form>

            </div>
        </div>

    </div>

    <!-- Listagem de usuários -->
    <div class="col-xl-8">

        <div class="card">
            <div class="card-body">

                <h5 class="card-title">
                    Usuários cadastrados
                    <span class="badge text-bg-secondary">
                        <?= count($usuarios) ?>
                    </span>
                </h5>

                <div class="table-responsive">

                    <table class="table table-hover align-middle">

                        <thead>
                            <tr>
                                <th>Nome</th>
                                <th>Usuário</th>
                                <th>Pontos de acesso</th>
                                <th>Perfil</th>
                                <th>Status</th>
                                <th class="text-end">Ação</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php if (empty($usuarios)): ?>

                                <tr>
                                    <td colspan="6" class="text-center text-body-secondary">
                                        Nenhum usuário cadastrado.
                                    </td>
                                </tr>

                            <?php else: ?>

                                <?php foreach ($usuarios as $u): ?>

                                    <?php
                                    $idsDoUsuario = array_filter(
                                        array_map(
                                            'intval',
                                            explode(';', (string) ($u['meuspontos'] ?? ''))
                                        )
                                    );

                                    $idsDoUsuario[] = (int) $u['pontodeacesso'];
                                    $idsDoUsuario = array_values(array_unique($idsDoUsuario));

                                    $nomesDosPontos = [];

                                    foreach ($idsDoUsuario as $idPonto) {
                                        if (isset($mapaPontos[$idPonto])) {
                                            $nomesDosPontos[] = $mapaPontos[$idPonto];
                                        }
                                    }
                                    ?>

                                    <tr>
                                        <td><?= $e($u['nome']) ?></td>
                                        <td><?= $e($u['usuario']) ?></td>

                                        <td>
                                            <?= $e(implode(', ', $nomesDosPontos)) ?>
                                        </td>

                                        <td>
                                            <?php if ((int) $u['admin'] === 1): ?>
                                                <span class="badge text-bg-primary">
                                                    Administrador
                                                </span>
                                            <?php else: ?>
                                                <span class="badge text-bg-secondary">
                                                    Usuário
                                                </span>
                                            <?php endif; ?>
                                        </td>

                                        <td>
                                            <?php if ((int) $u['status'] === 1): ?>
                                                <span class="badge text-bg-success">
                                                    Ativo
                                                </span>
                                            <?php else: ?>
                                                <span class="badge text-bg-danger">
                                                    Inativo
                                                </span>
                                            <?php endif; ?>
                                        </td>

                                        <td class="text-end">
                                            <a
                                                href="?usuarios&id=<?= (int) $u['id'] ?>"
                                                class="btn btn-sm btn-outline-primary"
                                                title="Editar usuário"
                                            >
                                                <i class="fa-solid fa-pen"></i>
                                            </a>
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