<?php
/**
 * Retorna uma informação do usuário autenticado.
 *
 * @param string $campo Campo desejado.
 * @return mixed|null
 */
function user_info($campo) {
    $dados = [
        'id' => $_SESSION['odin_usuarioid'] ?? null,
        'usuario' => $_SESSION['odin_usuario'] ?? null,
        'nome' => $_SESSION['odin_usuarionome'] ?? null,
        'pontodeacesso' => $_SESSION['odin_pontodeacesso'] ?? null,
        'admin' => $_SESSION['odin_admin'] ?? false
    ];

    return $dados[$campo] ?? null;
}

/**
 * Gera o menu de navegação organizado por categorias.
 *
 * Utiliza o ponto de acesso do usuário autenticado.
 *
 * @return void
 */
function menu() {

    $pontodeacesso = user_info('pontodeacesso');

    // Busca as categorias.
    $categorias = sel(
        'menus',
        [
            'pontodeacesso' => $pontodeacesso,
            'menusuperior' => 0
        ],
        'ordem ASC'
    );

    foreach ($categorias as $categoria) {

        echo '<div class="text-uppercase text-secondary small fw-bold mb-2">';
        echo htmlspecialchars($categoria['label'], ENT_QUOTES, 'UTF-8');
        echo '</div>';

        // Busca os itens pertencentes à categoria.
        $itens = sel(
            'menus',
            [
                'pontodeacesso' => $pontodeacesso,
                'menusuperior' => $categoria['id']
            ],
            'ordem ASC'
        );

        echo '<nav class="nav nav-pills flex-column">';

        foreach ($itens as $item) {

            echo '<a href="' . htmlspecialchars($item['link'], ENT_QUOTES, 'UTF-8') . '" class="nav-link text-white">';

            // Exibe o ícone somente quando estiver preenchido.
            if (!empty($item['icone'])) {
                echo '<i class="' . htmlspecialchars($item['icone'], ENT_QUOTES, 'UTF-8') . ' fa-fw me-2"></i>';
            }

            echo htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8');
            echo '</a>';
        }

        echo '</nav>';
        echo '<div class="mb-3"></div>';
    }
}