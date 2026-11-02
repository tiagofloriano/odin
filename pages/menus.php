<?php
/**
 * Gerenciamento de grupos e menus dos pontos de acesso.
 *
 * Esta página é incluída pelo index.php do Odin.
 * Não incluir cabeçalho ou rodapé do tema aqui.
 */

// Somente administradores podem gerenciar menus.
if (!user_info('admin')) {
    http_response_code(403);
    exit('Acesso negado.');
}

global $pdo;

// Escape para conteúdo HTML.
$e = static fn($valor) => htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');

// Identifica o ponto de acesso selecionado.
$pontoId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;

// Mensagens da operação.
$mensagem = '';
$tipoMensagem = 'success';

// Processa os formulários.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Valida o token CSRF.
    if (!csrf(1)) {
        http_response_code(403);
        exit('Solicitação inválida. Atualize a página e tente novamente.');
    }

    $acao = $_POST['acao'] ?? '';
    $id = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT) ?: 0;
    $pontoPost = filter_var($_POST['pontodeacesso'] ?? 0, FILTER_VALIDATE_INT) ?: 0;

    try {
        switch ($acao) {

            // Cria um grupo.
            case 'criar_grupo':
                $label = trim($_POST['label'] ?? '');
                $icone = trim($_POST['icone'] ?? '');
                $ordem = filter_var($_POST['ordem'] ?? 0, FILTER_VALIDATE_INT);

                if ($pontoPost < 1 || $label === '' || $ordem === false) {
                    throw new InvalidArgumentException('Preencha corretamente os campos obrigatórios.');
                }

                if (!sel('pontodeacesso', ['id' => $pontoPost], false, 1)) {
                    throw new InvalidArgumentException('Ponto de acesso não encontrado.');
                }

                ins('menus', [
                    'label' => $label,
                    'link' => '',
                    'menusuperior' => 0,
                    'pontodeacesso' => $pontoPost,
                    'icone' => $icone,
                    'ordem' => $ordem
                ]);

                $pontoId = $pontoPost;
                $mensagem = 'Grupo criado com sucesso.';
                break;

            // Cria um menu dentro de um grupo.
            case 'criar_menu':
                $grupoId = filter_var($_POST['menusuperior'] ?? 0, FILTER_VALIDATE_INT) ?: 0;
                $label = trim($_POST['label'] ?? '');
                $link = trim($_POST['link'] ?? '');
                $icone = trim($_POST['icone'] ?? '');
                $ordem = filter_var($_POST['ordem'] ?? 0, FILTER_VALIDATE_INT);

                $grupo = $grupoId ? sel('menus', [
                    'id' => $grupoId,
                    'pontodeacesso' => $pontoPost,
                    'menusuperior' => 0
                ], false, 1) : [];

                if ($pontoPost < 1 || !$grupo || $label === '' || $ordem === false) {
                    throw new InvalidArgumentException('Informe um ponto de acesso, um grupo válido e o nome do menu.');
                }

                ins('menus', [
                    'label' => $label,
                    'link' => $link,
                    'menusuperior' => $grupoId,
                    'pontodeacesso' => $pontoPost,
                    'icone' => $icone,
                    'ordem' => $ordem
                ]);

                $pontoId = $pontoPost;
                $mensagem = 'Menu criado com sucesso.';
                break;

            // Atualiza um grupo.
            case 'editar_grupo':
                $label = trim($_POST['label'] ?? '');
                $icone = trim($_POST['icone'] ?? '');
                $ordem = filter_var($_POST['ordem'] ?? 0, FILTER_VALIDATE_INT);

                $grupo = sel('menus', [
                    'id' => $id,
                    'pontodeacesso' => $pontoPost,
                    'menusuperior' => 0
                ], false, 1);

                if (!$grupo || $label === '' || $ordem === false) {
                    throw new InvalidArgumentException('Grupo não encontrado ou dados inválidos.');
                }

                upd('menus', [
                    'label' => $label,
                    'icone' => $icone,
                    'ordem' => $ordem
                ], ['id' => $id]);

                $pontoId = $pontoPost;
                $mensagem = 'Grupo atualizado com sucesso.';
                break;

            // Atualiza um menu.
            case 'editar_menu':
                $label = trim($_POST['label'] ?? '');
                $link = trim($_POST['link'] ?? '');
                $icone = trim($_POST['icone'] ?? '');
                $ordem = filter_var($_POST['ordem'] ?? 0, FILTER_VALIDATE_INT);
                $grupoId = filter_var($_POST['menusuperior'] ?? 0, FILTER_VALIDATE_INT) ?: 0;

                $menu = sel('menus', [
                    'id' => $id,
                    'pontodeacesso' => $pontoPost
                ], false, 1);

                $grupo = $grupoId ? sel('menus', [
                    'id' => $grupoId,
                    'pontodeacesso' => $pontoPost,
                    'menusuperior' => 0
                ], false, 1) : [];

                if (!$menu || (int) $menu[0]['menusuperior'] === 0 || !$grupo || $label === '' || $ordem === false) {
                    throw new InvalidArgumentException('Menu, grupo ou dados inválidos.');
                }

                upd('menus', [
                    'label' => $label,
                    'link' => $link,
                    'menusuperior' => $grupoId,
                    'icone' => $icone,
                    'ordem' => $ordem
                ], ['id' => $id]);

                $pontoId = $pontoPost;
                $mensagem = 'Menu atualizado com sucesso.';
                break;

            // Exclui um grupo e todos os menus vinculados.
            case 'excluir_grupo':
                $grupo = sel('menus', [
                    'id' => $id,
                    'pontodeacesso' => $pontoPost,
                    'menusuperior' => 0
                ], false, 1);

                if (!$grupo) {
                    throw new InvalidArgumentException('Grupo não encontrado.');
                }

                $pdo->beginTransaction();

                try {
                    $stmt = $pdo->prepare(
                        'DELETE FROM menus WHERE menusuperior = ? AND pontodeacesso = ?'
                    );
                    $stmt->execute([$id, $pontoPost]);

                    $stmt = $pdo->prepare(
                        'DELETE FROM menus WHERE id = ? AND pontodeacesso = ? AND menusuperior = 0'
                    );
                    $stmt->execute([$id, $pontoPost]);

                    if ($stmt->rowCount() !== 1) {
                        throw new RuntimeException('Não foi possível excluir o grupo.');
                    }

                    $pdo->commit();
                } catch (Throwable $erro) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    throw $erro;
                }

                $pontoId = $pontoPost;
                $mensagem = 'Grupo e seus menus foram excluídos.';
                break;

            // Exclui somente um menu.
            case 'excluir_menu':
                $menu = sel('menus', [
                    'id' => $id,
                    'pontodeacesso' => $pontoPost
                ], false, 1);

                if (!$menu || (int) $menu[0]['menusuperior'] === 0) {
                    throw new InvalidArgumentException('Menu não encontrado.');
                }

                $stmt = $pdo->prepare(
                    'DELETE FROM menus WHERE id = ? AND pontodeacesso = ? AND menusuperior <> 0'
                );
                $stmt->execute([$id, $pontoPost]);

                $pontoId = $pontoPost;
                $mensagem = 'Menu excluído com sucesso.';
                break;

            default:
                throw new InvalidArgumentException('Ação não reconhecida.');
        }

    } catch (Throwable $erro) {
        $mensagem = $erro->getMessage();
        $tipoMensagem = 'danger';
    }
}

// Carrega os pontos de acesso para os formulários.
$pontos = sel('pontodeacesso', [], 'nome ASC');

// Mapeia os nomes dos pontos de acesso.
$nomesPontos = [];
foreach ($pontos as $ponto) {
    $nomesPontos[(int) $ponto['id']] = $ponto['nome'];
}

// Carrega os grupos existentes para os formulários.
$gruposTodos = sel('menus', ['menusuperior' => 0], 'pontodeacesso ASC, ordem ASC');

// Se um ponto foi selecionado, carrega seus dados e sua árvore de menus.
$pontoAtual = [];
$grupos = [];

if ($pontoId > 0) {
    $pontoAtual = sel('pontodeacesso', ['id' => $pontoId], false, 1);

    if (!$pontoAtual) {
        $pontoId = 0;
    } else {
        $grupos = sel('menus', [
            'pontodeacesso' => $pontoId,
            'menusuperior' => 0
        ], 'ordem ASC, label ASC');

        foreach ($grupos as &$grupo) {
            $grupo['itens'] = sel('menus', [
                'pontodeacesso' => $pontoId,
                'menusuperior' => $grupo['id']
            ], 'ordem ASC, label ASC');
        }
        unset($grupo);
    }
}

// Registros usados nos formulários de edição.
$editarGrupo = [];
$editarMenu = [];

if (($_GET['editar'] ?? '') === 'grupo' && !empty($_GET['registro'])) {
    $registroId = filter_var($_GET['registro'], FILTER_VALIDATE_INT) ?: 0;
    $editarGrupo = $registroId ? sel('menus', [
        'id' => $registroId,
        'pontodeacesso' => $pontoId,
        'menusuperior' => 0
    ], false, 1) : [];
    $editarGrupo = $editarGrupo[0] ?? [];
}

if (($_GET['editar'] ?? '') === 'menu' && !empty($_GET['registro'])) {
    $registroId = filter_var($_GET['registro'], FILTER_VALIDATE_INT) ?: 0;
    $editarMenu = $registroId ? sel('menus', [
        'id' => $registroId,
        'pontodeacesso' => $pontoId
    ], false, 1) : [];
    $editarMenu = $editarMenu[0] ?? [];

    if ($editarMenu && (int) $editarMenu['menusuperior'] === 0) {
        $editarMenu = [];
    }
}
?>

<div class="container-fluid">

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h2 class="mb-1">Gerenciamento de menus</h2>
            <p class="text-body-secondary mb-0">
                Organize os grupos e menus dos pontos de acesso do Odin.
            </p>
        </div>

        <div class="d-flex flex-wrap gap-2">
            <?php if ($pontoId > 0): ?>
                <a href="?menus" class="btn btn-outline-secondary">
                    <i class="fa-solid fa-arrow-left me-1"></i> Pontos de acesso
                </a>
            <?php endif; ?>

            <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#modalGrupo">
                <i class="fa-solid fa-folder-plus me-1"></i> Novo grupo
            </button>

            <button class="btn btn-outline-primary" type="button" data-bs-toggle="modal" data-bs-target="#modalMenu">
                <i class="fa-solid fa-plus me-1"></i> Novo menu
            </button>
        </div>
    </div>

    <?php if ($mensagem !== ''): ?>
        <div class="alert alert-<?= $e($tipoMensagem) ?> alert-dismissible fade show" role="alert">
            <?= $e($mensagem) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
        </div>
    <?php endif; ?>

    <?php if ($pontoId === 0): ?>

        <?php
        // Lista somente pontos de acesso que possuem ao menos um grupo.
        $pontosComMenus = [];
        foreach ($gruposTodos as $grupo) {
            $pid = (int) $grupo['pontodeacesso'];
            if (!isset($pontosComMenus[$pid])) {
                $pontosComMenus[$pid] = [
                    'id' => $pid,
                    'nome' => $nomesPontos[$pid] ?? 'Ponto de acesso #' . $pid,
                    'grupos' => 0
                ];
            }
            $pontosComMenus[$pid]['grupos']++;
        }
        ?>

        <?php if (!$pontosComMenus): ?>
            <div class="alert alert-info">
                Ainda não existem grupos de menus cadastrados.
            </div>
        <?php else: ?>
            <div class="row g-3">
                <?php foreach ($pontosComMenus as $item): ?>
                    <div class="col-12 col-md-6 col-xl-4">
                        <a href="?menus&id=<?= (int) $item['id'] ?>" class="text-decoration-none">
                            <div class="card h-100 shadow-sm">
                                <div class="card-body d-flex align-items-center gap-3">
                                    <div class="fs-2 text-primary">
                                        <i class="fa-solid fa-window-maximize"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <h5 class="card-title mb-1"><?= $e($item['nome']) ?></h5>
                                        <p class="card-text text-body-secondary mb-0">
                                            <?= (int) $item['grupos'] ?>
                                            <?= $item['grupos'] === 1 ? 'grupo' : 'grupos' ?> de menus
                                        </p>
                                    </div>
                                    <i class="fa-solid fa-chevron-right text-body-secondary"></i>
                                </div>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    <?php else: ?>

        <div class="mb-4">
            <h4 class="mb-1"><?= $e($pontoAtual[0]['nome']) ?></h4>
            <p class="text-body-secondary mb-0">Grupos e menus cadastrados neste ponto de acesso.</p>
        </div>

        <?php if (!$grupos): ?>
            <div class="alert alert-info">
                Este ponto de acesso ainda não possui grupos de menus.
            </div>
        <?php else: ?>

            <div class="accordion" id="listaGrupos">
                <?php foreach ($grupos as $indice => $grupo): ?>
                    <?php
                    $collapseId = 'grupo' . (int) $grupo['id'];
                    $aberto = $indice === 0;
                    ?>
                    <div class="accordion-item mb-3 border rounded">
                        <h2 class="accordion-header">
                            <button class="accordion-button <?= $aberto ? '' : 'collapsed' ?>" type="button"
                                    data-bs-toggle="collapse" data-bs-target="#<?= $e($collapseId) ?>"
                                    aria-expanded="<?= $aberto ? 'true' : 'false' ?>">
                                <?php if (!empty($grupo['icone'])): ?>
                                    <i class="<?= $e($grupo['icone']) ?> me-2"></i>
                                <?php else: ?>
                                    <i class="fa-solid fa-folder me-2"></i>
                                <?php endif; ?>
                                <strong><?= $e($grupo['label']) ?></strong>
                                <span class="badge text-bg-secondary ms-2"><?= count($grupo['itens']) ?></span>
                            </button>
                        </h2>

                        <div id="<?= $e($collapseId) ?>" class="accordion-collapse collapse <?= $aberto ? 'show' : '' ?>"
                             data-bs-parent="#listaGrupos">
                            <div class="accordion-body">

                                <div class="d-flex flex-wrap gap-2 mb-3">
                                    <a href="?menus&id=<?= $pontoId ?>&editar=grupo&registro=<?= (int) $grupo['id'] ?>"
                                       class="btn btn-sm btn-outline-primary">
                                        <i class="fa-solid fa-pen me-1"></i> Editar grupo
                                    </a>

                                    <form method="post" onsubmit="return confirm('Excluir este grupo e todos os menus vinculados? Esta ação não poderá ser desfeita.');">
                                        <?= csrf() ?>
                                        <input type="hidden" name="acao" value="excluir_grupo">
                                        <input type="hidden" name="id" value="<?= (int) $grupo['id'] ?>">
                                        <input type="hidden" name="pontodeacesso" value="<?= $pontoId ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                            <i class="fa-solid fa-trash me-1"></i> Excluir grupo
                                        </button>
                                    </form>
                                </div>

                                <?php if (!$grupo['itens']): ?>
                                    <p class="text-body-secondary mb-0">Nenhum menu neste grupo.</p>
                                <?php else: ?>
                                    <div class="list-group list-group-flush">
                                        <?php foreach ($grupo['itens'] as $menu): ?>
                                            <div class="list-group-item px-0 d-flex flex-wrap justify-content-between align-items-center gap-2">
                                                <div>
                                                    <?php if (!empty($menu['icone'])): ?>
                                                        <i class="<?= $e($menu['icone']) ?> me-2 text-body-secondary"></i>
                                                    <?php else: ?>
                                                        <i class="fa-solid fa-link me-2 text-body-secondary"></i>
                                                    <?php endif; ?>
                                                    <strong><?= $e($menu['label']) ?></strong>
                                                    <?php if (!empty($menu['link'])): ?>
                                                        <div class="small text-body-secondary ms-4"><?= $e($menu['link']) ?></div>
                                                    <?php endif; ?>
                                                </div>

                                                <div class="d-flex gap-2">
                                                    <a href="?menus&id=<?= $pontoId ?>&editar=menu&registro=<?= (int) $menu['id'] ?>"
                                                       class="btn btn-sm btn-outline-primary" title="Editar menu">
                                                        <i class="fa-solid fa-pen"></i>
                                                    </a>

                                                    <form method="post" onsubmit="return confirm('Excluir este menu?');">
                                                        <?= csrf() ?>
                                                        <input type="hidden" name="acao" value="excluir_menu">
                                                        <input type="hidden" name="id" value="<?= (int) $menu['id'] ?>">
                                                        <input type="hidden" name="pontodeacesso" value="<?= $pontoId ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Excluir menu">
                                                            <i class="fa-solid fa-trash"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>

                                <button type="button" class="btn btn-sm btn-outline-secondary mt-3"
                                        data-bs-toggle="modal" data-bs-target="#modalMenu"
                                        data-grupo="<?= (int) $grupo['id'] ?>">
                                    <i class="fa-solid fa-plus me-1"></i> Adicionar menu a este grupo
                                </button>

                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

        <?php endif; ?>

        <?php if ($editarGrupo): ?>
            <div class="card mt-4" id="formEdicao">
                <div class="card-header"><strong>Editar grupo</strong></div>
                <div class="card-body">
                    <form method="post">
                        <?= csrf() ?>
                        <input type="hidden" name="acao" value="editar_grupo">
                        <input type="hidden" name="id" value="<?= (int) $editarGrupo['id'] ?>">
                        <input type="hidden" name="pontodeacesso" value="<?= $pontoId ?>">

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Nome do grupo</label>
                                <input type="text" name="label" class="form-control" required maxlength="255"
                                       value="<?= $e($editarGrupo['label']) ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Ícone (classe Font Awesome)</label>
                                <input type="text" name="icone" class="form-control"
                                       value="<?= $e($editarGrupo['icone']) ?>" placeholder="fa-solid fa-folder">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Ordem</label>
                                <input type="number" name="ordem" class="form-control" value="<?= (int) $editarGrupo['ordem'] ?>">
                            </div>
                        </div>

                        <div class="mt-3 d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Salvar alterações</button>
                            <a href="?menus&id=<?= $pontoId ?>" class="btn btn-outline-secondary">Cancelar</a>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($editarMenu): ?>
            <div class="card mt-4" id="formEdicao">
                <div class="card-header"><strong>Editar menu</strong></div>
                <div class="card-body">
                    <form method="post">
                        <?= csrf() ?>
                        <input type="hidden" name="acao" value="editar_menu">
                        <input type="hidden" name="id" value="<?= (int) $editarMenu['id'] ?>">
                        <input type="hidden" name="pontodeacesso" value="<?= $pontoId ?>">

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Nome do menu</label>
                                <input type="text" name="label" class="form-control" required maxlength="255"
                                       value="<?= $e($editarMenu['label']) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Link</label>
                                <input type="text" name="link" class="form-control"
                                       value="<?= $e($editarMenu['link']) ?>" placeholder="pagina ou endereço">
                            </div>
                            <div class="col-md-5">
                                <label class="form-label">Grupo</label>
                                <select name="menusuperior" class="form-select" required>
                                    <?php foreach ($grupos as $grupo): ?>
                                        <option value="<?= (int) $grupo['id'] ?>"
                                            <?= (int) $editarMenu['menusuperior'] === (int) $grupo['id'] ? 'selected' : '' ?>>
                                            <?= $e($grupo['label']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label">Ícone (classe Font Awesome)</label>
                                <input type="text" name="icone" class="form-control"
                                       value="<?= $e($editarMenu['icone']) ?>" placeholder="fa-solid fa-house">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Ordem</label>
                                <input type="number" name="ordem" class="form-control" value="<?= (int) $editarMenu['ordem'] ?>">
                            </div>
                        </div>

                        <div class="mt-3 d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Salvar alterações</button>
                            <a href="?menus&id=<?= $pontoId ?>" class="btn btn-outline-secondary">Cancelar</a>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>

    <?php endif; ?>

</div>

<!-- Modal: criar grupo -->
<div class="modal fade" id="modalGrupo" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <?= csrf() ?>
                <input type="hidden" name="acao" value="criar_grupo">

                <div class="modal-header">
                    <h5 class="modal-title">Novo grupo de menus</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>

                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Ponto de acesso</label>
                        <select name="pontodeacesso" class="form-select" required>
                            <option value="">Selecione...</option>
                            <?php foreach ($pontos as $ponto): ?>
                                <option value="<?= (int) $ponto['id'] ?>"
                                    <?= $pontoId === (int) $ponto['id'] ? 'selected' : '' ?>>
                                    <?= $e($ponto['nome']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Nome do grupo</label>
                        <input type="text" name="label" class="form-control" required maxlength="255">
                    </div>

                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label">Ícone (classe Font Awesome)</label>
                            <input type="text" name="icone" class="form-control" placeholder="fa-solid fa-folder">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Ordem</label>
                            <input type="number" name="ordem" class="form-control" value="0">
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Criar grupo</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: criar menu -->
<div class="modal fade" id="modalMenu" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <?= csrf() ?>
                <input type="hidden" name="acao" value="criar_menu">

                <div class="modal-header">
                    <h5 class="modal-title">Novo menu</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>

                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Ponto de acesso</label>
                        <select name="pontodeacesso" id="menuPonto" class="form-select" required>
                            <option value="">Selecione...</option>
                            <?php foreach ($pontos as $ponto): ?>
                                <option value="<?= (int) $ponto['id'] ?>"
                                    <?= $pontoId === (int) $ponto['id'] ? 'selected' : '' ?>>
                                    <?= $e($ponto['nome']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Grupo</label>
                        <select name="menusuperior" id="menuGrupo" class="form-select" required>
                            <option value="">Selecione o grupo...</option>
                            <?php foreach ($gruposTodos as $grupo): ?>
                                <option value="<?= (int) $grupo['id'] ?>"
                                        data-ponto="<?= (int) $grupo['pontodeacesso'] ?>"
                                    <?= $pontoId > 0 && (int) $grupo['pontodeacesso'] === $pontoId ? '' : '' ?>>
                                    <?= $e($nomesPontos[(int) $grupo['pontodeacesso']] ?? 'Ponto #' . $grupo['pontodeacesso']) ?>
                                    — <?= $e($grupo['label']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Nome do menu</label>
                        <input type="text" name="label" class="form-control" required maxlength="255">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Link</label>
                        <input type="text" name="link" class="form-control" placeholder="pagina ou endereço">
                    </div>

                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label">Ícone (classe Font Awesome)</label>
                            <input type="text" name="icone" class="form-control" placeholder="fa-solid fa-house">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Ordem</label>
                            <input type="number" name="ordem" class="form-control" value="0">
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Criar menu</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Mantém no seletor apenas grupos pertencentes ao ponto de acesso escolhido.
(() => {
    const ponto = document.getElementById('menuPonto');
    const grupo = document.getElementById('menuGrupo');
    const modal = document.getElementById('modalMenu');

    if (!ponto || !grupo) return;

    const filtrarGrupos = (grupoInicial = '') => {
        const pontoSelecionado = ponto.value;

        Array.from(grupo.options).forEach((opcao, indice) => {
            if (indice === 0) {
                opcao.hidden = false;
                return;
            }

            opcao.hidden = opcao.dataset.ponto !== pontoSelecionado;
        });

        if (grupoInicial) {
            grupo.value = grupoInicial;
        } else if (grupo.selectedOptions[0]?.hidden) {
            grupo.value = '';
        }
    };

    ponto.addEventListener('change', () => filtrarGrupos());

    modal?.addEventListener('show.bs.modal', evento => {
        const botao = evento.relatedTarget;
        const grupoInicial = botao?.dataset.grupo || '';
        filtrarGrupos(grupoInicial);
    });

    filtrarGrupos();
})();
</script>
