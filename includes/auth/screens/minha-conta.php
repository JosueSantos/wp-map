<?php

function cc_minha_conta_normalizar_nome_duplicidade($nome) {
    $nome = strtolower(remove_accents((string) $nome));
    $nome = preg_replace('/[^a-z0-9\s]/u', ' ', $nome);

    $termos_comuns = [
        'capela', 'paroquia', 'paróquia', 'matriz', 'igreja', 'comunidade', 'santuário', 'santuario',
        'sao', 'são', 'santa', 'santo', 'nossa', 'senhora', 'nsra', 'de', 'da', 'do', 'das', 'dos',
    ];

    $partes = array_filter(preg_split('/\s+/', $nome));
    $partes = array_values(array_filter($partes, function ($parte) use ($termos_comuns) {
        return strlen($parte) > 2 && !in_array($parte, $termos_comuns, true);
    }));

    return trim(implode(' ', $partes));
}

function cc_minha_conta_listar_possiveis_duplicadas($distancia_km = 0.5, $similaridade_minima = 65) {
    $posts = get_posts([
        'post_type' => 'comunidade',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'orderby' => 'title',
        'order' => 'ASC',
    ]);

    $locais = [];
    foreach ($posts as $post) {
        $lat = get_post_meta($post->ID, 'latitude', true);
        $lng = get_post_meta($post->ID, 'longitude', true);

        if ($lat === '' || $lng === '' || !is_numeric($lat) || !is_numeric($lng)) {
            continue;
        }

        $locais[] = [
            'id' => (int) $post->ID,
            'nome' => $post->post_title,
            'nome_normalizado' => cc_minha_conta_normalizar_nome_duplicidade($post->post_title),
            'latitude' => (float) $lat,
            'longitude' => (float) $lng,
            'endereco' => get_post_meta($post->ID, 'endereco', true),
        ];
    }

    $duplicadas = [];
    $total = count($locais);

    for ($i = 0; $i < $total; $i++) {
        for ($j = $i + 1; $j < $total; $j++) {
            $distancia = cc_calcular_distancia($locais[$i]['latitude'], $locais[$i]['longitude'], $locais[$j]['latitude'], $locais[$j]['longitude']);
            if ($distancia > $distancia_km) {
                continue;
            }

            $nome_a = $locais[$i]['nome_normalizado'] ?: $locais[$i]['nome'];
            $nome_b = $locais[$j]['nome_normalizado'] ?: $locais[$j]['nome'];
            similar_text($nome_a, $nome_b, $percentual);

            if ($percentual < $similaridade_minima) {
                continue;
            }

            $duplicadas[] = [
                'principal' => $locais[$i],
                'duplicado' => $locais[$j],
                'distancia_km' => round($distancia, 3),
                'similaridade' => round($percentual, 1),
            ];
        }
    }

    usort($duplicadas, function ($a, $b) {
        if ($a['distancia_km'] === $b['distancia_km']) {
            return $b['similaridade'] <=> $a['similaridade'];
        }
        return $a['distancia_km'] <=> $b['distancia_km'];
    });

    return $duplicadas;
}



function cc_minha_conta_label_post_id($id, $fallback_taxonomy = '') {
    $id = (int) $id;
    if ($id <= 0) {
        return __('Sem vínculo', 'cadastro-comunidades');
    }

    $titulo = get_the_title($id);
    if ($titulo) {
        return sprintf('%s (#%d)', $titulo, $id);
    }

    if ($fallback_taxonomy) {
        $term = get_term($id, $fallback_taxonomy);
        if ($term && !is_wp_error($term)) {
            return sprintf('%s (#%d)', $term->name, $id);
        }
    }

    return sprintf(__('Registro #%d', 'cadastro-comunidades'), $id);
}

function cc_minha_conta_label_select_alteracao($campo, $valor) {
    $valor_string = (string) $valor;
    $dias = ['0' => __('Domingo', 'cadastro-comunidades'), '1' => __('Segunda-feira', 'cadastro-comunidades'), '2' => __('Terça-feira', 'cadastro-comunidades'), '3' => __('Quarta-feira', 'cadastro-comunidades'), '4' => __('Quinta-feira', 'cadastro-comunidades'), '5' => __('Sexta-feira', 'cadastro-comunidades'), '6' => __('Sábado', 'cadastro-comunidades')];
    $frequencias = [
        'missa_dominical' => __('Missa Dominical', 'cadastro-comunidades'),
        'semanal' => __('Semanal', 'cadastro-comunidades'),
        'mensal' => __('Mensal', 'cadastro-comunidades'),
        'numero_semana' => __('Por número da semana', 'cadastro-comunidades'),
        'anual' => __('Anual', 'cadastro-comunidades'),
    ];

    if (preg_match('/(^|\.)dias(\[\d+\])?$/', $campo) || preg_match('/(^|\.)dia$/', $campo)) {
        return $dias[$valor_string] ?? $valor_string;
    }

    if (preg_match('/(^|\.)frequencia$/', $campo)) {
        return $frequencias[$valor_string] ?? $valor_string;
    }

    if (preg_match('/(^|\.)numero_semana$/', $campo)) {
        return $valor_string !== '' ? sprintf(__('%sª semana', 'cadastro-comunidades'), $valor_string) : __('Não informado', 'cadastro-comunidades');
    }

    if (preg_match('/(^|\.)tipo_evento(_id)?$/', $campo)) {
        return cc_minha_conta_label_post_id($valor, 'tipo_evento');
    }

    if (preg_match('/(^|\.)parent_paroquia$/', $campo)) {
        return cc_minha_conta_label_post_id($valor);
    }

    if (preg_match('/(^|\.)comunidade_id$/', $campo)) {
        return cc_minha_conta_label_post_id($valor);
    }

    return null;
}

function cc_minha_conta_obter_valor_por_caminho($dados, $caminho) {
    if (!is_array($dados)) return null;
    $partes = preg_split('/\.(?![^\[]*\])/', (string) $caminho);
    $valor = $dados;
    foreach ($partes as $parte) {
        if (preg_match('/^([^\[]*)\[(\d+)\]$/', $parte, $m)) {
            if ($m[1] !== '') {
                if (!is_array($valor) || !array_key_exists($m[1], $valor)) return null;
                $valor = $valor[$m[1]];
            }
            $idx = (int) $m[2];
            if (!is_array($valor) || !array_key_exists($idx, $valor)) return null;
            $valor = $valor[$idx];
            continue;
        }
        if (!is_array($valor) || !array_key_exists($parte, $valor)) return null;
        $valor = $valor[$parte];
    }
    return $valor;
}

function cc_minha_conta_evento_label_alteracao($chave, $anterior, $atual) {
    if (!preg_match('/^eventos\[(\d+)\]/', (string) $chave, $matches)) return '';
    $idx = (int) $matches[1];
    $evento = cc_minha_conta_obter_valor_por_caminho($atual, 'eventos[' . $idx . ']');
    if (!is_array($evento)) $evento = cc_minha_conta_obter_valor_por_caminho($anterior, 'eventos[' . $idx . ']');
    if (!is_array($evento)) return '';

    $nome = trim((string) ($evento['titulo'] ?? $evento['nome'] ?? $evento['descricao'] ?? ''));
    if ($nome === '' && !empty($evento['tipo_evento'])) {
        $nome = cc_minha_conta_label_post_id($evento['tipo_evento'], 'tipo_evento');
    }
    if ($nome === '' && !empty($evento['tipo_evento_id'])) {
        $nome = cc_minha_conta_label_post_id($evento['tipo_evento_id'], 'tipo_evento');
    }

    return $nome !== '' ? $nome : sprintf(__('Atividade #%d', 'cadastro-comunidades'), $idx + 1);
}

function cc_minha_conta_formatar_caminho_alteracao($caminho) {
    $rotulos = [
        'acao' => __('Ação', 'cadastro-comunidades'),
        'nome' => __('Nome', 'cadastro-comunidades'),
        'tipo' => __('Tipo', 'cadastro-comunidades'),
        'endereco' => __('Endereço', 'cadastro-comunidades'),
        'latitude' => __('Latitude', 'cadastro-comunidades'),
        'longitude' => __('Longitude', 'cadastro-comunidades'),
        'parent_paroquia' => __('Paróquia', 'cadastro-comunidades'),
        'contatos' => __('Contatos', 'cadastro-comunidades'),
        'eventos' => __('Eventos', 'cadastro-comunidades'),
        'eventos_removidos' => __('Eventos removidos', 'cadastro-comunidades'),
        'observacao' => __('Observação', 'cadastro-comunidades'),
        'remover_imagem' => __('Remover imagem', 'cadastro-comunidades'),
        'id' => __('Identificador', 'cadastro-comunidades'),
        'tipo_evento' => __('Tipo de atividade', 'cadastro-comunidades'),
        'tipo_evento_id' => __('Tipo de atividade', 'cadastro-comunidades'),
        'frequencia' => __('Frequência', 'cadastro-comunidades'),
        'dias' => __('Dias da semana', 'cadastro-comunidades'),
        'dia' => __('Dia da semana', 'cadastro-comunidades'),
        'dia_mes' => __('Dia do mês', 'cadastro-comunidades'),
        'numero_semana' => __('Semana do mês', 'cadastro-comunidades'),
        'mes' => __('Mês', 'cadastro-comunidades'),
        'horario' => __('Horário', 'cadastro-comunidades'),
    ];

    $partes = array_map(static function ($parte) use ($rotulos) {
        if (preg_match('/^(.+)\[(\d+)\]$/', $parte, $matches)) {
            $base = $rotulos[$matches[1]] ?? ucwords(str_replace('_', ' ', $matches[1]));
            return sprintf('%s #%d', $base, ((int) $matches[2]) + 1);
        }
        return $rotulos[$parte] ?? ucwords(str_replace('_', ' ', $parte));
    }, explode('.', (string) $caminho));

    return implode(' › ', $partes);
}

function cc_minha_conta_normalizar_valor_alteracao($valor) {
    if (is_bool($valor)) {
        return $valor ? __('Sim', 'cadastro-comunidades') : __('Não', 'cadastro-comunidades');
    }

    if ($valor === null || $valor === '') {
        return __('Vazio', 'cadastro-comunidades');
    }

    if (is_array($valor)) {
        return wp_json_encode($valor, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    return (string) $valor;
}

function cc_minha_conta_achatar_dados_alteracao($dados, $prefixo = '') {
    $resultado = [];

    if (!is_array($dados)) {
        return $prefixo === '' ? ['valor' => $dados] : [$prefixo => $dados];
    }

    if (empty($dados)) {
        if ($prefixo !== '') {
            $resultado[$prefixo] = [];
        }
        return $resultado;
    }

    foreach ($dados as $chave => $valor) {
        $segmento = is_int($chave) ? '[' . $chave . ']' : (string) $chave;
        $caminho = $prefixo === '' ? $segmento : (is_int($chave) ? $prefixo . $segmento : $prefixo . '.' . $segmento);

        if (is_array($valor)) {
            $resultado += cc_minha_conta_achatar_dados_alteracao($valor, $caminho);
            continue;
        }

        $resultado[$caminho] = $valor;
    }

    return $resultado;
}

function cc_minha_conta_analisar_alteracao($dados_json_anterior, $dados_json_atual) {
    $anterior = json_decode((string) $dados_json_anterior, true);
    $atual = json_decode((string) $dados_json_atual, true);

    if (!is_array($atual)) {
        return [[
            'tipo' => __('Aviso', 'cadastro-comunidades'),
            'campo' => __('Dados da alteração', 'cadastro-comunidades'),
            'antes' => '',
            'depois' => __('Não foi possível ler os dados desta alteração.', 'cadastro-comunidades'),
        ]];
    }

    if (!is_array($anterior)) {
        return [[
            'tipo' => __('Primeiro registro', 'cadastro-comunidades'),
            'campo' => __('Primeiro registro deste local', 'cadastro-comunidades'),
            'mensagem' => __('Este é o primeiro registro salvo para este local. Ainda não existe uma versão anterior para comparar; as próximas alterações serão exibidas com antes e depois.', 'cadastro-comunidades'),
            'primeiro_registro' => true,
        ]];
    }

    $antes = cc_minha_conta_achatar_dados_alteracao($anterior);
    $depois = cc_minha_conta_achatar_dados_alteracao($atual);
    $chaves = array_unique(array_merge(array_keys($antes), array_keys($depois)));
    sort($chaves, SORT_NATURAL | SORT_FLAG_CASE);

    $mudancas = [];
    foreach ($chaves as $chave) {
        $existe_antes = array_key_exists($chave, $antes);
        $existe_depois = array_key_exists($chave, $depois);
        $valor_antes = $existe_antes ? (cc_minha_conta_label_select_alteracao($chave, $antes[$chave]) ?? cc_minha_conta_normalizar_valor_alteracao($antes[$chave])) : '';
        $valor_depois = $existe_depois ? (cc_minha_conta_label_select_alteracao($chave, $depois[$chave]) ?? cc_minha_conta_normalizar_valor_alteracao($depois[$chave])) : '';

        if ($existe_antes && $existe_depois && $valor_antes === $valor_depois) {
            continue;
        }

        $tipo_mudanca = !$existe_antes ? __('Adicionado', 'cadastro-comunidades') : (!$existe_depois ? __('Removido', 'cadastro-comunidades') : __('Alterado', 'cadastro-comunidades'));
        $campo = cc_minha_conta_formatar_caminho_alteracao($chave);
        $evento_label = cc_minha_conta_evento_label_alteracao($chave, $anterior, $atual);

        if ($evento_label !== '') {
            $campo = preg_replace('/^' . preg_quote(__('Eventos', 'cadastro-comunidades'), '/') . ' #\d+/', sprintf(__('Atividade: %s', 'cadastro-comunidades'), $evento_label), $campo);
        }

        if (!$existe_antes && preg_match('/^eventos\[(\d+)\]\.id$/', $chave)) {
            $tipo_mudanca = __('Atividade adicionada', 'cadastro-comunidades');
            $campo = sprintf(__('Atividade %s adicionada', 'cadastro-comunidades'), $evento_label ?: $valor_depois);
        }

        $mudancas[] = [
            'tipo' => $tipo_mudanca,
            'campo' => $campo,
            'antes' => $existe_antes ? $valor_antes : __('Não existia', 'cadastro-comunidades'),
            'depois' => $existe_depois ? $valor_depois : __('Removido', 'cadastro-comunidades'),
        ];
    }

    if (empty($mudancas)) {
        $mudancas[] = [
            'tipo' => __('Sem diferença', 'cadastro-comunidades'),
            'campo' => __('Dados enviados', 'cadastro-comunidades'),
            'antes' => __('A versão anterior e esta alteração possuem os mesmos dados registrados.', 'cadastro-comunidades'),
            'depois' => '',
        ];
    }

    return $mudancas;
}

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
    $pagina_duplicadas = max(1, absint($_GET['pg_duplicadas'] ?? 1));
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

        $start_page = max(1, (int) $current_page - 3);
        $end_page = min((int) $total_pages, (int) $current_page + 3);

        for ($i = $start_page; $i <= $end_page; $i++) {
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

    $duplicadas = current_user_can('manage_options') ? cc_minha_conta_listar_possiveis_duplicadas() : [];
    $total_duplicadas = count($duplicadas);
    $total_paginas_duplicadas = max(1, (int) ceil($total_duplicadas / $por_pagina_padrao));
    $pagina_duplicadas = min($pagina_duplicadas, $total_paginas_duplicadas);
    $duplicadas_paginadas = array_slice($duplicadas, ($pagina_duplicadas - 1) * $por_pagina_padrao, $por_pagina_padrao);

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
                            <div class="flex flex-wrap items-center gap-2">
                                <?php $analise_alteracao = cc_minha_conta_analisar_alteracao($alteracao->dados_json_anterior ?? '', $alteracao->dados_json ?? ''); ?>
                                <button
                                    type="button"
                                    class="<?php echo esc_attr(cc_auth_button_class('secondary')); ?> cc-analisar-alteracao"
                                    data-local="<?php echo esc_attr($alteracao->comunidade_nome ?: __('Local removida', 'cadastro-comunidades')); ?>"
                                    data-data="<?php echo esc_attr(mysql2date('d/m/Y H:i', $alteracao->created_at)); ?>"
                                    data-mudancas="<?php echo esc_attr(wp_json_encode($analise_alteracao, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?>"
                                ><?php esc_html_e('Analisar mudanças', 'cadastro-comunidades'); ?></button>
                                <?php if (!empty($alteracao->comunidade_id)): ?>
                                    <a href="<?php echo esc_url(get_permalink((int) $alteracao->comunidade_id)); ?>" target="_blank" rel="noopener noreferrer" class="<?php echo esc_attr(cc_auth_button_class('secondary')); ?>"><?php esc_html_e('Ver detalhes', 'cadastro-comunidades'); ?></a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </li>
                <?php endforeach; ?>
                <?php if (empty($alteracoes)): ?><li class="text-gray-600"><?php esc_html_e('Sem alterações para os filtros selecionados.', 'cadastro-comunidades'); ?></li><?php endif; ?>
            </ul>
            <?php $render_pagination($pagina_alteracoes, $total_paginas_alteracoes, 'pg_alteracoes', 'sec-observacao-alteracoes', $build_page_url); ?>
        </section>


        <?php if (current_user_can('manage_options')): ?>
            <section id="sec-duplicadas" class="bg-white border border-gray-200 rounded-2xl p-6 sm:p-8 space-y-4">
                <div>
                    <h4 class="text-xl font-semibold text-gray-800"><?php esc_html_e('Possíveis locais duplicados', 'cadastro-comunidades'); ?></h4>
                    <p class="text-gray-600"><?php esc_html_e('A lista compara locais publicados em até 500 metros e nomes parecidos após remover termos comuns.', 'cadastro-comunidades'); ?></p>
                </div>

                <ul class="space-y-2">
                    <?php foreach ($duplicadas_paginadas as $par): ?>
                        <li class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-gray-800 space-y-3">
                            <div class="grid md:grid-cols-2 gap-3">
                                <?php foreach (['principal', 'duplicado'] as $chave_local): ?>
                                    <?php $local = $par[$chave_local]; ?>
                                    <div class="rounded-lg bg-white border border-amber-100 p-3">
                                        <p class="font-semibold"><?php echo esc_html($local['nome']); ?> (#<?php echo (int) $local['id']; ?>)</p>
                                        <p class="text-sm text-gray-600"><?php echo esc_html($local['endereco'] ?: __('Sem endereço cadastrado', 'cadastro-comunidades')); ?></p>
                                        <a href="<?php echo esc_url(get_permalink((int) $local['id'])); ?>" target="_blank" rel="noopener noreferrer" class="<?php echo esc_attr(cc_auth_button_class('secondary')); ?> mt-2"><?php esc_html_e('Ver detalhes', 'cadastro-comunidades'); ?></a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <p class="text-sm text-amber-900"><?php echo esc_html(sprintf(__('Distância: %s km • Similaridade: %s%%', 'cadastro-comunidades'), number_format_i18n($par['distancia_km'], 3), number_format_i18n($par['similaridade'], 1))); ?></p>
                        </li>
                    <?php endforeach; ?>
                    <?php if (empty($duplicadas_paginadas)): ?><li class="text-gray-600"><?php esc_html_e('Nenhum possível local duplicado encontrado.', 'cadastro-comunidades'); ?></li><?php endif; ?>
                </ul>
                <?php $render_pagination($pagina_duplicadas, $total_paginas_duplicadas, 'pg_duplicadas', 'sec-duplicadas', $build_page_url); ?>
            </section>
        <?php endif; ?>

        <div id="cc-modal-analise-alteracao" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4" role="dialog" aria-modal="true" aria-labelledby="cc-modal-analise-titulo">
            <div class="w-full max-w-3xl rounded-2xl bg-white shadow-xl border border-gray-200 max-h-[85vh] overflow-hidden">
                <div class="flex items-start justify-between gap-4 border-b border-gray-200 p-5">
                    <div>
                        <h5 id="cc-modal-analise-titulo" class="text-xl font-semibold text-gray-900"><?php esc_html_e('Análise de mudanças', 'cadastro-comunidades'); ?></h5>
                        <p id="cc-modal-analise-subtitulo" class="mt-1 text-sm text-gray-600"></p>
                    </div>
                    <button type="button" class="cc-fechar-modal-analise rounded-lg px-3 py-2 text-gray-500 hover:bg-gray-100" aria-label="<?php esc_attr_e('Fechar análise de mudanças', 'cadastro-comunidades'); ?>">&times;</button>
                </div>
                <div id="cc-modal-analise-conteudo" class="max-h-[65vh] overflow-auto p-5 space-y-3"></div>
            </div>
        </div>

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

                const modalAnalise = document.getElementById('cc-modal-analise-alteracao');
                const modalSubtitulo = document.getElementById('cc-modal-analise-subtitulo');
                const modalConteudo = document.getElementById('cc-modal-analise-conteudo');

                function escapeHtml(value) {
                    return String(value ?? '').replace(/[&<>"']/g, function (char) {
                        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[char];
                    });
                }

                function fecharModalAnalise() {
                    modalAnalise?.classList.add('hidden');
                    modalAnalise?.classList.remove('flex');
                }

                document.querySelectorAll('.cc-analisar-alteracao').forEach(function (botao) {
                    botao.addEventListener('click', function () {
                        let mudancas = [];
                        try {
                            mudancas = JSON.parse(botao.dataset.mudancas || '[]');
                        } catch (error) {
                            mudancas = [];
                        }

                        if (modalSubtitulo) {
                            modalSubtitulo.textContent = (botao.dataset.local || '') + ' — ' + (botao.dataset.data || '');
                        }

                        if (modalConteudo) {
                            modalConteudo.innerHTML = mudancas.length ? mudancas.map(function (mudanca) {
                                if (mudanca.primeiro_registro) {
                                    return `
                                        <article class="rounded-xl border border-indigo-100 bg-indigo-50 p-4 space-y-2">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <span class="rounded-full bg-white px-3 py-1 text-xs font-semibold text-indigo-700">${escapeHtml(mudanca.tipo)}</span>
                                                <strong class="text-gray-900">${escapeHtml(mudanca.campo)}</strong>
                                            </div>
                                            <p class="text-sm text-gray-700">${escapeHtml(mudanca.mensagem || '<?php echo esc_js(__('Este foi o primeiro registro deste local.', 'cadastro-comunidades')); ?>')}</p>
                                        </article>
                                    `;
                                }

                                return `
                                    <article class="rounded-xl border border-gray-200 p-4 space-y-2">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="rounded-full bg-indigo-50 px-3 py-1 text-xs font-semibold text-indigo-700">${escapeHtml(mudanca.tipo)}</span>
                                            <strong class="text-gray-900">${escapeHtml(mudanca.campo)}</strong>
                                        </div>
                                        <div class="grid md:grid-cols-2 gap-3 text-sm">
                                            <div class="rounded-lg bg-red-50 border border-red-100 p-3">
                                                <p class="font-semibold text-red-800"><?php echo esc_js(__('Antes', 'cadastro-comunidades')); ?></p>
                                                <p class="mt-1 whitespace-pre-wrap break-words text-gray-700">${escapeHtml(mudanca.antes)}</p>
                                            </div>
                                            <div class="rounded-lg bg-green-50 border border-green-100 p-3">
                                                <p class="font-semibold text-green-800"><?php echo esc_js(__('Depois', 'cadastro-comunidades')); ?></p>
                                                <p class="mt-1 whitespace-pre-wrap break-words text-gray-700">${escapeHtml(mudanca.depois)}</p>
                                            </div>
                                        </div>
                                    </article>
                                `;
                            }).join('') : '<p class="text-gray-600"><?php echo esc_js(__('Nenhuma mudança encontrada para exibir.', 'cadastro-comunidades')); ?></p>';
                        }

                        modalAnalise?.classList.remove('hidden');
                        modalAnalise?.classList.add('flex');
                    });
                });

                document.querySelectorAll('.cc-fechar-modal-analise').forEach(function (botao) {
                    botao.addEventListener('click', fecharModalAnalise);
                });

                modalAnalise?.addEventListener('click', function (event) {
                    if (event.target === modalAnalise) fecharModalAnalise();
                });

                document.addEventListener('keydown', function (event) {
                    if (event.key === 'Escape') fecharModalAnalise();
                });
            });
        </script>
    </div>
    <?php

    return ob_get_clean();
}
add_shortcode('minha-conta-mapa', 'cc_shortcode_minha_conta_mapa');
