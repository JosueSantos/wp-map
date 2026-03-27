<?php

function cc_shortcode_minha_conta_mapa($atts = []) {
    cc_enqueue_auth_ui_assets();

    $atts = shortcode_atts([
        'url_editar_comunidade' => '',
    ], $atts, 'minha-conta-mapa');

    $url_editar_comunidade = esc_url_raw($atts['url_editar_comunidade']);

    if (!is_user_logged_in()) {
        return '<div class="max-w-3xl mx-auto bg-white border border-gray-200 rounded-2xl p-6"><p class="text-gray-800">' . esc_html__('Faça login para acessar sua conta.', 'cadastro-comunidades') . ' <a class="text-indigo-700 font-semibold" href="' . esc_url(cc_get_auth_page_url('login', '/login')) . '">' . esc_html__('Entrar', 'cadastro-comunidades') . '</a></p></div>';
    }

    $user = wp_get_current_user();
    $paroquias = cc_get_paroquias_options();

    $filtros = [
        'comunidade_id' => absint($_GET['f_comunidade'] ?? 0),
        'data_inicio' => sanitize_text_field($_GET['f_data_inicio'] ?? ''),
        'data_fim' => sanitize_text_field($_GET['f_data_fim'] ?? ''),
    ];

    $pagina_criadas = max(1, absint($_GET['pg_criadas'] ?? 1));
    $pagina_observadas = max(1, absint($_GET['pg_observadas'] ?? 1));
    $pagina_alteracoes = max(1, absint($_GET['pg_alteracoes'] ?? 1));
    $por_pagina_padrao = 10;

    $build_page_url = static function ($overrides = [], $anchor = '') {
        $base = strtok($_SERVER['REQUEST_URI'] ?? '', '?');
        $query = $_GET;

        foreach ($overrides as $key => $value) {
            if ($value === null || $value === '' || $value === false) {
                unset($query[$key]);
                continue;
            }
            $query[$key] = $value;
        }

        $url = $base ?: '';
        if (!empty($query)) {
            $url .= '?' . http_build_query($query);
        }

        if ($anchor) {
            $url .= '#' . ltrim($anchor, '#');
        }

        return $url;
    };

    $render_pagination = static function ($current_page, $total_pages, $base_key, $anchor, $build_url) {
        if ($total_pages <= 1) {
            return;
        }

        echo '<nav class="mt-4 flex flex-wrap items-center gap-2" aria-label="' . esc_attr__('Paginação', 'cadastro-comunidades') . '">';

        $prev_disabled = $current_page <= 1;
        $next_disabled = $current_page >= $total_pages;
        $prev_url = $build_url([$base_key => max(1, $current_page - 1)], $anchor);
        $next_url = $build_url([$base_key => min($total_pages, $current_page + 1)], $anchor);

        echo $prev_disabled
            ? '<span class="inline-flex items-center justify-center px-3 py-2 rounded-lg border border-gray-200 text-gray-400">' . esc_html__('Anterior', 'cadastro-comunidades') . '</span>'
            : '<a href="' . esc_url($prev_url) . '" class="inline-flex items-center justify-center px-3 py-2 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50">' . esc_html__('Anterior', 'cadastro-comunidades') . '</a>';

        for ($i = 1; $i <= $total_pages; $i++) {
            $active = (int) $i === (int) $current_page;
            $classes = $active
                ? 'inline-flex items-center justify-center px-3 py-2 rounded-lg border border-indigo-600 bg-indigo-600 text-white'
                : 'inline-flex items-center justify-center px-3 py-2 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50';
            echo '<a href="' . esc_url($build_url([$base_key => $i], $anchor)) . '" class="' . esc_attr($classes) . '">' . (int) $i . '</a>';
        }

        echo $next_disabled
            ? '<span class="inline-flex items-center justify-center px-3 py-2 rounded-lg border border-gray-200 text-gray-400">' . esc_html__('Próxima', 'cadastro-comunidades') . '</span>'
            : '<a href="' . esc_url($next_url) . '" class="inline-flex items-center justify-center px-3 py-2 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50">' . esc_html__('Próxima', 'cadastro-comunidades') . '</a>';

        echo '</nav>';
    };

    $comunidades_criadas = cc_get_comunidades_do_usuario($user->ID);
    $total_criadas = count($comunidades_criadas);
    $total_paginas_criadas = max(1, (int) ceil($total_criadas / $por_pagina_padrao));
    $pagina_criadas = min($pagina_criadas, $total_paginas_criadas);
    $comunidades_criadas_paginadas = array_slice($comunidades_criadas, ($pagina_criadas - 1) * $por_pagina_padrao, $por_pagina_padrao);

    $comunidades_observadas = cc_get_comunidades_observadas($user->ID);
    $total_observadas = count($comunidades_observadas);
    $total_paginas_observadas = max(1, (int) ceil($total_observadas / $por_pagina_padrao));
    $pagina_observadas = min($pagina_observadas, $total_paginas_observadas);
    $comunidades_observadas_paginadas = array_slice($comunidades_observadas, ($pagina_observadas - 1) * $por_pagina_padrao, $por_pagina_padrao);

    $alteracoes_paginadas = cc_get_alteracoes_do_usuario($user->ID, $filtros, [
        'page' => $pagina_alteracoes,
        'per_page' => $por_pagina_padrao,
    ]);
    $alteracoes = $alteracoes_paginadas['items'] ?? [];
    $pagina_alteracoes = (int) ($alteracoes_paginadas['page'] ?? 1);
    $total_paginas_alteracoes = (int) ($alteracoes_paginadas['total_pages'] ?? 1);

    $all_comunidades = get_posts([
        'post_type' => 'comunidade',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'orderby' => 'title',
        'order' => 'ASC',
    ]);

    ob_start();
    ?>
    <div class="max-w-5xl mx-auto space-y-6">
        <section id="sec-locais-cadastrados" class="bg-white border border-gray-200 rounded-2xl p-6 sm:p-8">
            <h3 class="text-2xl font-bold text-gray-800"><?php esc_html_e('Minha Conta', 'cadastro-comunidades'); ?></h3>
            <p class="text-gray-600 mt-1"><?php esc_html_e('Aqui você atualiza seus dados e acompanha seus locais registrados.', 'cadastro-comunidades'); ?></p>

            <form method="post" class="mt-5 grid md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700"><?php esc_html_e('Nome', 'cadastro-comunidades'); ?></label>
                    <input type="text" name="nome" value="<?php echo esc_attr($user->display_name); ?>" required class="<?php echo esc_attr(cc_auth_input_class()); ?>">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700"><?php esc_html_e('E-mail', 'cadastro-comunidades'); ?></label>
                    <input type="email" name="email" value="<?php echo esc_attr($user->user_email); ?>" required class="<?php echo esc_attr(cc_auth_input_class()); ?>">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700"><?php esc_html_e('Paróquia (opcional)', 'cadastro-comunidades'); ?></label>
                    <?php $current_paroquia = (int) get_user_meta($user->ID, 'cc_paroquia_id', true); ?>
                    <?php $current_paroquia_nome = $current_paroquia > 0 ? get_the_title($current_paroquia) : ''; ?>
                    <input id="cc-profile-paroquia" type="text" name="paroquia_existente" list="cc-paroquias-datalist" class="<?php echo esc_attr(cc_auth_input_class()); ?>" placeholder="<?php esc_attr_e('Digite para buscar paróquia', 'cadastro-comunidades'); ?>" value="<?php echo esc_attr($current_paroquia_nome ? ($current_paroquia_nome . ' (#' . $current_paroquia . ')') : ''); ?>">
                </div>
                <div class="md:col-span-2 border-t border-gray-200 pt-4 mt-2">
                    <?php wp_nonce_field('cc_profile', 'cc_profile_nonce'); ?>
                    <input type="hidden" name="cc_auth_action" value="update_profile">
                    <div class="flex flex-col sm:flex-row sm:flex-wrap gap-3">
                        <button type="submit" class="<?php echo esc_attr(cc_auth_button_class()); ?> w-full sm:w-auto"><?php esc_html_e('Salvar perfil', 'cadastro-comunidades'); ?></button>
                        <a class="inline-flex items-center justify-center px-5 py-3 rounded-xl border border-indigo-200 bg-indigo-50 text-indigo-700 font-semibold w-full sm:w-auto" href="<?php echo esc_url(cc_get_auth_page_url('alterar-senha', '/alterar-senha')); ?>"><?php esc_html_e('Alterar senha', 'cadastro-comunidades'); ?></a>
                    </div>
                </div>
            </form>

            <form method="post" class="mt-3">
                <?php wp_nonce_field('cc_logout', 'cc_logout_nonce'); ?>
                <input type="hidden" name="cc_auth_action" value="logout">
                <button type="submit" class="<?php echo esc_attr(cc_auth_button_class('danger')); ?> w-full sm:w-auto"><?php esc_html_e('Sair da conta', 'cadastro-comunidades'); ?></button>
            </form>
        </section>

        <section class="bg-white border border-gray-200 rounded-2xl p-6 sm:p-8">
            <h4 class="text-xl font-semibold text-gray-800"><?php esc_html_e('Locais cadastrados por você', 'cadastro-comunidades'); ?></h4>
            <ul class="mt-3 space-y-2">
                <?php foreach ($comunidades_criadas_paginadas as $comunidade): ?>
                    <li class="flex items-center justify-between gap-3 rounded-xl border border-gray-200 p-3 text-gray-800">
                        <span><?php echo esc_html($comunidade->post_title); ?> (#<?php echo (int) $comunidade->ID; ?>)</span>
                        <div class="flex items-center gap-2">
                            <a href="<?php echo esc_url(get_permalink($comunidade->ID)); ?>" target="_blank" rel="noopener noreferrer" class="<?php echo esc_attr(cc_auth_button_class('secondary')); ?>"><?php esc_html_e('Ver detalhes', 'cadastro-comunidades'); ?></a>
                            <a href="<?php echo esc_url(cc_get_editar_comunidade_url_custom($comunidade->ID, $url_editar_comunidade)); ?>" class="<?php echo esc_attr(cc_auth_button_class('secondary')); ?>"><?php esc_html_e('Editar', 'cadastro-comunidades'); ?></a>
                        </div>
                    </li>
                <?php endforeach; ?>
                <?php if (empty($comunidades_criadas_paginadas)): ?><li><?php esc_html_e('Nenhum local cadastrado ainda.', 'cadastro-comunidades'); ?></li><?php endif; ?>
            </ul>
            <?php $render_pagination($pagina_criadas, $total_paginas_criadas, 'pg_criadas', 'sec-locais-cadastrados', $build_page_url); ?>
        </section>

        <section id="sec-observacao-locais" class="bg-white border border-gray-200 rounded-2xl p-6 sm:p-8 space-y-4">
            <h4 class="text-xl font-semibold text-gray-800"><?php esc_html_e('Observação de Locais', 'cadastro-comunidades'); ?></h4>
            <p class="text-gray-600"><?php esc_html_e('Você pode acompanhar alterações em locais mesmo sem ser o criador.', 'cadastro-comunidades'); ?></p>

            <form method="post" class="flex flex-col sm:flex-row gap-3 items-stretch sm:items-end">
                <div class="flex-1">
                    <label class="block text-sm font-medium text-gray-700"><?php esc_html_e('Selecionar comunidade', 'cadastro-comunidades'); ?></label>
                    <input id="cc-observe-comunidade-nome" type="text" name="comunidade_nome" list="cc-comunidades-datalist" required class="<?php echo esc_attr(cc_auth_input_class()); ?>" placeholder="<?php esc_attr_e('Digite para buscar', 'cadastro-comunidades'); ?>">
                    <input id="cc-observe-comunidade-id" type="hidden" name="comunidade_id">
                </div>
                <div>
                    <?php wp_nonce_field('cc_observe', 'cc_observe_nonce'); ?>
                    <input type="hidden" name="cc_auth_action" value="observe_add">
                    <button type="submit" class="<?php echo esc_attr(cc_auth_button_class()); ?> w-full sm:w-auto"><?php esc_html_e('Adicionar observação', 'cadastro-comunidades'); ?></button>
                </div>
            </form>

            <ul class="space-y-2">
                <?php foreach ($comunidades_observadas_paginadas as $comunidade): ?>
                    <li class="rounded-xl border border-gray-200 p-3">
                        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3">
                            <span class="text-gray-800 font-medium"><?php echo esc_html($comunidade->post_title); ?></span>
                            <div class="flex flex-wrap items-center gap-2">
                                <a href="<?php echo esc_url(get_permalink($comunidade->ID)); ?>" target="_blank" rel="noopener noreferrer" class="<?php echo esc_attr(cc_auth_button_class('secondary')); ?>"><?php esc_html_e('Ver detalhes', 'cadastro-comunidades'); ?></a>
                                <a href="<?php echo esc_url(cc_get_editar_comunidade_url_custom($comunidade->ID, $url_editar_comunidade)); ?>" class="<?php echo esc_attr(cc_auth_button_class('secondary')); ?>"><?php esc_html_e('Editar', 'cadastro-comunidades'); ?></a>
                                <form method="post" class="inline">
                                    <input type="hidden" name="comunidade_id" value="<?php echo (int) $comunidade->ID; ?>">
                                    <?php wp_nonce_field('cc_observe', 'cc_observe_nonce'); ?>
                                    <input type="hidden" name="cc_auth_action" value="observe_remove">
                                    <button type="submit" class="<?php echo esc_attr(cc_auth_button_class('danger')); ?>"><?php esc_html_e('Remover', 'cadastro-comunidades'); ?></button>
                                </form>
                            </div>
                        </div>
                    </li>
                <?php endforeach; ?>
                <?php if (empty($comunidades_observadas_paginadas)): ?><li class="text-gray-600"><?php esc_html_e('Nenhum local observado.', 'cadastro-comunidades'); ?></li><?php endif; ?>
            </ul>
            <?php $render_pagination($pagina_observadas, $total_paginas_observadas, 'pg_observadas', 'sec-observacao-locais', $build_page_url); ?>
        </section>

        <section id="sec-observacao-alteracoes" class="bg-white border border-gray-200 rounded-2xl p-6 sm:p-8 space-y-4">
            <h4 class="text-xl font-semibold text-gray-800"><?php esc_html_e('Observação de alterações', 'cadastro-comunidades'); ?></h4>
            <p class="text-gray-600"><?php esc_html_e('Filtre por local e período para encontrar atualizações com facilidade.', 'cadastro-comunidades'); ?></p>

            <form method="get" class="grid md:grid-cols-4 gap-3 items-end">
                <input type="hidden" name="pg_alteracoes" value="1">
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700"><?php esc_html_e('Local', 'cadastro-comunidades'); ?></label>
                    <?php $filtro_label = ''; ?>
                    <?php if ($filtros['comunidade_id'] > 0) { foreach ($all_comunidades as $comunidade_item) { if ((int) $comunidade_item->ID === (int) $filtros['comunidade_id']) { $filtro_label = $comunidade_item->post_title . ' (#' . (int) $comunidade_item->ID . ')'; break; } } } ?>
                    <input id="cc-filtro-comunidade-nome" type="text" name="f_comunidade_nome" list="cc-comunidades-datalist" class="<?php echo esc_attr(cc_auth_input_class()); ?>" placeholder="<?php esc_attr_e('Todas', 'cadastro-comunidades'); ?>" value="<?php echo esc_attr($filtro_label); ?>">
                    <input id="cc-filtro-comunidade-id" type="hidden" name="f_comunidade" value="<?php echo (int) $filtros['comunidade_id']; ?>">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700"><?php esc_html_e('Data início', 'cadastro-comunidades'); ?></label>
                    <input type="date" name="f_data_inicio" value="<?php echo esc_attr($filtros['data_inicio']); ?>" class="<?php echo esc_attr(cc_auth_input_class()); ?>">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700"><?php esc_html_e('Data fim', 'cadastro-comunidades'); ?></label>
                    <input type="date" name="f_data_fim" value="<?php echo esc_attr($filtros['data_fim']); ?>" class="<?php echo esc_attr(cc_auth_input_class()); ?>">
                </div>
                <div class="md:col-span-4">
                    <button type="submit" class="<?php echo esc_attr(cc_auth_button_class()); ?>"><?php esc_html_e('Aplicar filtros', 'cadastro-comunidades'); ?></button>
                    <a href="<?php echo esc_url($build_page_url([
                        'f_comunidade_nome' => null,
                        'f_comunidade' => null,
                        'f_data_inicio' => null,
                        'f_data_fim' => null,
                        'pg_alteracoes' => 1,
                    ], 'sec-observacao-alteracoes')); ?>" class="inline-flex items-center justify-center px-5 py-3 rounded-xl border border-gray-300 bg-white text-gray-700 font-semibold ml-2"><?php esc_html_e('Limpar filtros', 'cadastro-comunidades'); ?></a>
                </div>
            </form>

            <ul class="space-y-2">
                <?php foreach ($alteracoes as $alteracao): ?>
                    <li class="rounded-xl border border-gray-200 p-3 text-gray-800 space-y-2">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                            <p class="min-w-0">
                                <strong><?php echo esc_html($alteracao->comunidade_nome ?: 'Local removida'); ?></strong>
                                <span class="text-gray-500 break-words"> — <?php echo esc_html($alteracao->usuario_nome ?: 'Usuário removido'); ?> — <?php echo esc_html(mysql2date('d/m/Y H:i', $alteracao->created_at)); ?></span>
                            </p>
                            <?php if (!empty($alteracao->comunidade_id)): ?>
                                <a href="<?php echo esc_url(get_permalink((int) $alteracao->comunidade_id)); ?>" target="_blank" rel="noopener noreferrer" class="<?php echo esc_attr(cc_auth_button_class('secondary')); ?>"><?php esc_html_e('Ver detalhes', 'cadastro-comunidades'); ?></a>
                            <?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
                <?php if (empty($alteracoes)): ?><li class="text-gray-600"><?php esc_html_e('Sem alterações para os filtros selecionados.', 'cadastro-comunidades'); ?></li><?php endif; ?>
            </ul>
            <?php $render_pagination($pagina_alteracoes, $total_paginas_alteracoes, 'pg_alteracoes', 'sec-observacao-alteracoes', $build_page_url); ?>
        </section>


        <datalist id="cc-paroquias-datalist">
            <option value=""><?php esc_html_e('Sem vínculo de paróquia', 'cadastro-comunidades'); ?></option>
            <?php foreach ($paroquias as $paroquia): ?>
                <option value="<?php echo esc_attr($paroquia->post_title . ' (#' . (int) $paroquia->ID . ')'); ?>"></option>
            <?php endforeach; ?>
        </datalist>

        <datalist id="cc-comunidades-datalist">
            <?php foreach ($all_comunidades as $comunidade): ?>
                <option value="<?php echo esc_attr($comunidade->post_title . ' (#' . (int) $comunidade->ID . ')'); ?>"></option>
            <?php endforeach; ?>
        </datalist>

        <script>
            document.addEventListener('DOMContentLoaded', function () {
                if (window.location.hash) {
                    const anchor = document.querySelector(window.location.hash);
                    if (anchor) {
                        anchor.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }
                }

                function extractComunidadeId(value) {
                    const match = String(value || '').match(/#(\d+)\)/);
                    return match ? match[1] : '';
                }

                const observeName = document.getElementById('cc-observe-comunidade-nome');
                const observeId = document.getElementById('cc-observe-comunidade-id');
                observeName?.closest('form')?.addEventListener('submit', function () {
                    if (observeId) observeId.value = extractComunidadeId(observeName?.value);
                });

                const filtroName = document.getElementById('cc-filtro-comunidade-nome');
                const filtroId = document.getElementById('cc-filtro-comunidade-id');
                filtroName?.closest('form')?.addEventListener('submit', function () {
                    if (filtroId) filtroId.value = extractComunidadeId(filtroName?.value) || '0';
                });
            });
        </script>
    </div>
    <?php

    return ob_get_clean();
}
add_shortcode('minha-conta-mapa', 'cc_shortcode_minha_conta_mapa');
