<?php

// Processa os formulários do perfil.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Identifica qual configuração está sendo alterada.
    $acao = $_POST['acao'] ?? '';

    // Alteração da senha.
    if ($acao === 'senha') {

        $senha = $_POST['senha'] ?? '';
        $confirmar = $_POST['confirmar'] ?? '';

        if (empty($senha) || empty($confirmar)) {
            $erro = 'Preencha os dois campos de senha.';
        }
        elseif ($senha !== $confirmar) {
            $erro = 'As senhas informadas não são iguais.';
        }
        elseif (strlen($senha) < 8) {
            $erro = 'A senha deve ter pelo menos 8 caracteres.';
        }
        else {

            upd(
                'usuarios',
                [
                    'senha' => password_hash($senha, PASSWORD_DEFAULT)
                ],
                [
                    'id' => $_SESSION['odin_usuarioid']
                ]
            );

            $sucesso = 'Senha alterada com sucesso!';
        }
    }

    // Alteração da escala das fontes.
    elseif ($acao === 'fonte') {

        $escala = (int) ($_POST['escala_fonte'] ?? 100);

        // Limita a escala aos valores permitidos.
        $escala = max(70, min(120, $escala));

        upd(
            'usuarios',
            [
                'escala_fonte' => $escala
            ],
            [
                'id' => $_SESSION['odin_usuarioid']
            ]
        );

        $sucessoFonte = 'Tamanho das fontes atualizado! Atualize a página para ver as alterações!';
    }
}

// Recupera a escala atual do usuário.
$usuario = sel(
    'usuarios',
    ['id' => $_SESSION['odin_usuarioid']],
    false,
    1
);

$escalaFonte = (int) ($usuario[0]['escala_fonte'] ?? 100);

?>

<h2>Meu perfil</h2>

<p class="text-body-secondary">
    Gerencie seus dados de acesso e preferências do Odin.
</p>

<div class="row g-3">

    <!-- Alteração de senha -->
    <div class="col-md-6 col-xl-4">

        <div class="card">
            <div class="card-body">

                <h5 class="card-title">Alterar senha</h5>

                <?php if (!empty($erro)): ?>
                    <div class="alert alert-danger" role="alert">
                        <?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($sucesso)): ?>
                    <div class="alert alert-success" role="alert">
                        <?= htmlspecialchars($sucesso, ENT_QUOTES, 'UTF-8') ?>
                    </div>
                <?php endif; ?>

                <form method="POST">

                    <input type="hidden" name="acao" value="senha">

                    <div class="mb-3">
                        <label for="senha" class="form-label">
                            Nova senha
                        </label>

                        <input
                            type="password"
                            class="form-control"
                            id="senha"
                            name="senha"
                            minlength="8"
                            autocomplete="new-password"
                            required
                        >
                    </div>

                    <div class="mb-3">
                        <label for="confirmar" class="form-label">
                            Repetir nova senha
                        </label>

                        <input
                            type="password"
                            class="form-control"
                            id="confirmar"
                            name="confirmar"
                            minlength="8"
                            autocomplete="new-password"
                            required
                        >
                    </div>

                    <button type="submit" class="btn btn-padrao">
                        <i class="fa-solid fa-key me-1"></i>
                        Alterar senha
                    </button>

                </form>

            </div>
        </div>

    </div>

    <!-- Preferência de fontes -->
    <div class="col-md-6 col-xl-4">

        <div class="card">
            <div class="card-body">

                <h5 class="card-title">Tamanho das fontes</h5>

                <?php if (!empty($sucessoFonte)): ?>
                    <div class="alert alert-success" role="alert">
                        <?= htmlspecialchars($sucessoFonte, ENT_QUOTES, 'UTF-8') ?>
                    </div>
                <?php endif; ?>

                <form method="POST">

                    <input type="hidden" name="acao" value="fonte">

                    <label for="escala_fonte" class="form-label">
                        Escala:
                        <strong id="valorEscala"><?= $escalaFonte ?>%</strong>
                    </label>

                    <input
                        type="range"
                        class="form-range"
                        id="escala_fonte"
                        name="escala_fonte"
                        min="70"
                        max="120"
                        step="5"
                        value="<?= $escalaFonte ?>"
                        oninput="document.getElementById('valorEscala').textContent = this.value + '%'"
                    >

                    <div class="d-flex justify-content-between text-body-secondary small mb-3">
                        <span>70% — Menor</span>
                        <span>120% — Maior</span>
                    </div>

                    <button type="submit" class="btn btn-padrao">
                        <i class="fa-solid fa-text-height me-1"></i>
                        Salvar preferência
                    </button>

                </form>

            </div>
        </div>

    </div>

</div>
