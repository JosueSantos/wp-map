<?php

add_action('rest_api_init', function () {

    register_rest_route('mapa/v1', '/comunidade', [
        'methods'  => 'POST',
        'callback' => 'cc_api_cadastrar_comunidade',
        'permission_callback' => function () {
            return is_user_logged_in();
        }
    ]);

    register_rest_route('mapa/v1', '/comunidade/(?P<id>\d+)', [
        'methods'  => 'GET',
        'callback' => 'cc_api_obter_comunidade_para_edicao',
        'permission_callback' => function () {
            return is_user_logged_in();
        }
    ]);

});

function cc_api_normalizar_dados_comunidade($request) {

    $data = $request->get_json_params();

    if (empty($data)) {
        $data = $request->get_params();
    }

    if (!is_array($data)) {
        $data = [];
    }

    if (isset($data['contatos']) && is_string($data['contatos'])) {
        $contatos = json_decode(wp_unslash($data['contatos']), true);
        $data['contatos'] = is_array($contatos) ? $contatos : [];
    }

    if (isset($data['eventos']) && is_string($data['eventos'])) {
        $eventos = json_decode(wp_unslash($data['eventos']), true);
        $data['eventos'] = is_array($eventos) ? $eventos : [];
    }

    if (isset($data['eventos_removidos']) && is_string($data['eventos_removidos'])) {
        $eventos_removidos = json_decode(wp_unslash($data['eventos_removidos']), true);
        $data['eventos_removidos'] = is_array($eventos_removidos) ? $eventos_removidos : [];
    }

    return $data;
}

function cc_api_validar_tipo_capela($tipo_id, $parent_paroquia) {

    if (empty($tipo_id)) return true;

    $termo = get_term((int) $tipo_id, 'tipo_comunidade');

    if (is_wp_error($termo) || !$termo) return true;

    $is_capela = stripos($termo->name, 'capela') !== false;

    if ($is_capela && empty($parent_paroquia)) {
        return new WP_Error(
            'paroquia_obrigatoria',
            'Para cadastrar uma Capela é obrigatório selecionar a Paróquia responsável.',
            ['status' => 400]
        );
    }

    return true;
}

function cc_api_processar_upload_imagem($comunidade_id, $file_params) {

    if (empty($file_params['imagem_comunidade'])) {
        return null;
    }

    $arquivo = $file_params['imagem_comunidade'];

    if (!empty($arquivo['error']) && (int) $arquivo['error'] !== UPLOAD_ERR_OK) {
        return new WP_Error('upload_falhou', 'Falha no upload da imagem.', ['status' => 400]);
    }

    $check = wp_check_filetype_and_ext($arquivo['tmp_name'], $arquivo['name']);
    $extensoes = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    if (empty($check['ext']) || !in_array(strtolower($check['ext']), $extensoes, true)) {
        return new WP_Error('tipo_imagem_invalido', 'Arquivo de imagem inválido.', ['status' => 400]);
    }

    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';

    $attachment_id = media_handle_upload('imagem_comunidade', $comunidade_id);

    if (is_wp_error($attachment_id)) {
        return $attachment_id;
    }

    update_post_meta($comunidade_id, 'imagem_id', (int) $attachment_id);
    set_post_thumbnail($comunidade_id, (int) $attachment_id);

    return (int) $attachment_id;
}

function cc_api_obter_comunidade_para_edicao($request) {
    if (!is_user_logged_in()) {
        return new WP_Error('nao_autorizado', 'Login necessário', ['status' => 401]);
    }

    $comunidade_id = (int) $request['id'];
    $post = get_post($comunidade_id);

    if (!$post || $post->post_type !== 'comunidade') {
        return new WP_Error('comunidade_nao_encontrada', 'Comunidade não encontrada.', ['status' => 404]);
    }

    $tipo_terms = wp_get_post_terms($comunidade_id, 'tipo_comunidade', ['fields' => 'ids']);

    $eventos_query = get_posts([
        'post_type' => 'evento',
        'posts_per_page' => -1,
        'post_status' => 'publish',
        'meta_query' => [
            [
                'key' => 'comunidade_id',
                'value' => $comunidade_id,
            ]
        ]
    ]);

    $eventos = [];
    foreach ($eventos_query as $evento) {
        $tipo_evento_ids = wp_get_post_terms($evento->ID, 'tipo_evento', ['fields' => 'ids']);
        $tags_evento_ids = wp_get_post_terms($evento->ID, 'tags_evento', ['fields' => 'ids']);

        $eventos[] = [
            'id' => $evento->ID,
            'titulo' => $evento->post_title,
            'frequencia' => get_post_meta($evento->ID, 'frequencia', true) ?: 'semanal',
            'dia' => get_post_meta($evento->ID, 'dia_semana', true),
            'dias' => cc_api_get_evento_dias_semana($evento->ID),
            'dia_mes' => get_post_meta($evento->ID, 'dia_mes', true),
            'numero_semana' => get_post_meta($evento->ID, 'numero_semana', true),
            'mes' => get_post_meta($evento->ID, 'mes', true),
            'horario' => get_post_meta($evento->ID, 'horario', true),
            'descricao' => get_post_meta($evento->ID, 'descricao', true),
            'observacao' => get_post_meta($evento->ID, 'observacao', true),
            'tipo_evento_id' => isset($tipo_evento_ids[0]) ? (int) $tipo_evento_ids[0] : null,
            'tags_evento_ids' => array_map('intval', is_array($tags_evento_ids) ? $tags_evento_ids : []),
        ];
    }

    $parent_paroquia_id = (int) get_post_meta($comunidade_id, 'parent_paroquia', true);

    return rest_ensure_response([
        'id' => $comunidade_id,
        'nome' => $post->post_title,
        'tipo_id' => isset($tipo_terms[0]) ? (int) $tipo_terms[0] : null,
        'latitude' => get_post_meta($comunidade_id, 'latitude', true),
        'longitude' => get_post_meta($comunidade_id, 'longitude', true),
        'endereco' => get_post_meta($comunidade_id, 'endereco', true),
        'parent_paroquia_id' => $parent_paroquia_id ?: null,
        'parent_paroquia_nome' => $parent_paroquia_id ? get_the_title($parent_paroquia_id) : '',
        'contatos' => get_post_meta($comunidade_id, 'contatos', true) ?: [],
        'eventos' => $eventos,
        'imagem_url' => get_the_post_thumbnail_url($comunidade_id, 'medium') ?: '',
    ]);
}


function cc_api_get_evento_dias_semana($evento_id) {
    $dias = get_post_meta($evento_id, 'dias_semana', true);

    if (is_array($dias)) {
        $normalizados = array_values(array_unique(array_filter(array_map('intval', $dias), function($dia) {
            return $dia >= 0 && $dia <= 6;
        })));

        sort($normalizados);
        return $normalizados;
    }

    $dia_unico = get_post_meta($evento_id, 'dia_semana', true);
    if ($dia_unico === '' || $dia_unico === null) {
        return [];
    }

    $dia_unico = (int) $dia_unico;
    return ($dia_unico >= 0 && $dia_unico <= 6) ? [$dia_unico] : [];
}

function cc_api_salvar_eventos($comunidade_id, $eventos = []) {
    if (empty($eventos) || !is_array($eventos)) {
        return;
    }

    foreach ($eventos as $evt) {
        $titulo_evento = sanitize_text_field($evt['titulo'] ?? '');
        if (empty($titulo_evento)) continue;

        $evento_id = !empty($evt['id']) ? (int) $evt['id'] : 0;

        if ($evento_id > 0) {
            $post_existente = get_post($evento_id);
            if (!$post_existente || $post_existente->post_type !== 'evento') {
                continue;
            }

            wp_update_post([
                'ID' => $evento_id,
                'post_title' => $titulo_evento,
            ]);
        } else {
            $evento_id = wp_insert_post([
                'post_type'   => 'evento',
                'post_status' => 'publish',
                'post_title'  => $titulo_evento,
            ]);

            if (is_wp_error($evento_id)) continue;
        }

        $frequencias_validas = ['semanal', 'mensal', 'numero_semana', 'anual'];
        $frequencia = sanitize_key($evt['frequencia'] ?? 'semanal');
        if (!in_array($frequencia, $frequencias_validas, true)) {
            $frequencia = 'semanal';
        }

        $dias_semana = [];
        if (isset($evt['dias']) && is_array($evt['dias'])) {
            foreach ($evt['dias'] as $dia_item) {
                if ($dia_item === '' || $dia_item === null) continue;
                $dia_item = (int) $dia_item;
                if ($dia_item >= 0 && $dia_item <= 6) {
                    $dias_semana[] = $dia_item;
                }
            }
        } elseif (isset($evt['dia']) && $evt['dia'] !== '') {
            $dia_item = (int) $evt['dia'];
            if ($dia_item >= 0 && $dia_item <= 6) {
                $dias_semana[] = $dia_item;
            }
        }

        $dias_semana = array_values(array_unique($dias_semana));
        sort($dias_semana);
        $dia_semana = !empty($dias_semana) ? $dias_semana[0] : '';

        $dia_mes = isset($evt['dia_mes']) && $evt['dia_mes'] !== '' ? max(1, min(31, (int) $evt['dia_mes'])) : '';
        $numero_semana = isset($evt['numero_semana']) && $evt['numero_semana'] !== '' ? max(1, min(5, (int) $evt['numero_semana'])) : '';
        $mes = isset($evt['mes']) && $evt['mes'] !== '' ? max(1, min(12, (int) $evt['mes'])) : '';
        $horario = sanitize_text_field($evt['horario'] ?? '');

        update_post_meta($evento_id, 'comunidade_id', $comunidade_id);
        update_post_meta($evento_id, 'frequencia', $frequencia);
        if ($dia_semana === '') {
            delete_post_meta($evento_id, 'dia_semana');
            delete_post_meta($evento_id, 'dias_semana');
        } else {
            update_post_meta($evento_id, 'dia_semana', $dia_semana);
            update_post_meta($evento_id, 'dias_semana', $dias_semana);
        }

        if ($dia_mes === '') {
            delete_post_meta($evento_id, 'dia_mes');
        } else {
            update_post_meta($evento_id, 'dia_mes', $dia_mes);
        }

        if ($numero_semana === '') {
            delete_post_meta($evento_id, 'numero_semana');
        } else {
            update_post_meta($evento_id, 'numero_semana', $numero_semana);
        }

        if ($mes === '') {
            delete_post_meta($evento_id, 'mes');
        } else {
            update_post_meta($evento_id, 'mes', $mes);
        }
        update_post_meta($evento_id, 'horario', $horario);
        update_post_meta($evento_id, 'descricao', sanitize_textarea_field($evt['descricao'] ?? ''));
        update_post_meta($evento_id, 'observacao', sanitize_textarea_field($evt['observacao'] ?? ''));

        if (!empty($evt['tipo_evento'])) {
            wp_set_object_terms($evento_id, [(int) $evt['tipo_evento']], 'tipo_evento');
        } else {
            wp_set_object_terms($evento_id, [], 'tipo_evento');
        }

        if (!empty($evt['tags_evento']) && is_array($evt['tags_evento'])) {
            $tags_evento_ids = cc_api_filtrar_tags_evento_por_tipo(
                array_map('intval', $evt['tags_evento']),
                !empty($evt['tipo_evento']) ? (int) $evt['tipo_evento'] : 0
            );

            wp_set_object_terms($evento_id, $tags_evento_ids, 'tags_evento');
        } else {
            wp_set_object_terms($evento_id, [], 'tags_evento');
        }
    }
}

function cc_api_filtrar_tags_evento_por_tipo($tags_ids = [], $tipo_evento_id = 0) {
    if (empty($tags_ids) || !is_array($tags_ids)) {
        return [];
    }

    $tipo_evento_id = (int) $tipo_evento_id;
    $tags_validas = [];

    foreach ($tags_ids as $tag_id) {
        $tag_id = (int) $tag_id;
        if ($tag_id <= 0) continue;

        $exclusivos = get_term_meta($tag_id, 'exclusive_tipo_evento_ids', true);
        $exclusivos = is_array($exclusivos) ? array_map('intval', $exclusivos) : [];

        if (empty($exclusivos) || ($tipo_evento_id > 0 && in_array($tipo_evento_id, $exclusivos, true))) {
            $tags_validas[] = $tag_id;
        }
    }

    return array_values(array_unique($tags_validas));
}

function cc_api_remover_eventos($comunidade_id, $eventos_removidos = []) {
    if (empty($eventos_removidos) || !is_array($eventos_removidos)) {
        return;
    }

    foreach ($eventos_removidos as $evento_id) {
        $evento_id = (int) $evento_id;
        if ($evento_id <= 0) continue;

        $evento_comunidade_id = (int) get_post_meta($evento_id, 'comunidade_id', true);
        if ($evento_comunidade_id !== (int) $comunidade_id) continue;

        wp_delete_post($evento_id, true);
    }
}

function cc_api_cadastrar_comunidade($request) {
    if (!is_user_logged_in()) {
        return new WP_Error('nao_autorizado', 'Login necessário', ['status' => 401]);
    }

    $data = cc_api_normalizar_dados_comunidade($request);

    if (empty($data)) {
        return new WP_Error('sem_dados', 'Dados vazios ou inválidos', ['status' => 400]);
    }

    if (empty($data['nome'])) {
        return new WP_Error('nome_obrigatorio', 'Nome do local é obrigatório', ['status' => 400]);
    }

    if (!isset($data['latitude']) || !is_numeric($data['latitude']) || !isset($data['longitude']) || !is_numeric($data['longitude'])) {
        return new WP_Error('coords_invalidas', 'Latitude e longitude obrigatórias', ['status' => 400]);
    }

    $latitude  = (float) $data['latitude'];
    $longitude = (float) $data['longitude'];

    if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
        return new WP_Error('coords_fora_do_intervalo', 'Latitude/longitude fora do intervalo válido.', ['status' => 400]);
    }

    $validacao_capela = cc_api_validar_tipo_capela($data['tipo'] ?? null, $data['parent_paroquia'] ?? null);
    if (is_wp_error($validacao_capela)) {
        return $validacao_capela;
    }

    $comunidade_id = isset($data['comunidade_id']) ? (int) $data['comunidade_id'] : 0;
    $is_edicao = $comunidade_id > 0;

    if ($is_edicao) {
        $post_existente = get_post($comunidade_id);
        if (!$post_existente || $post_existente->post_type !== 'comunidade') {
            return new WP_Error('comunidade_nao_encontrada', 'Local para edição não encontrado.', ['status' => 404]);
        }

        wp_update_post([
            'ID' => $comunidade_id,
            'post_title' => sanitize_text_field($data['nome'] ?? 'Sem nome'),
        ]);
    } else {
        $comunidade_id = wp_insert_post([
            'post_type'   => 'comunidade',
            'post_status' => 'publish',
            'post_title'  => sanitize_text_field($data['nome'] ?? 'Sem nome'),
            'post_author' => get_current_user_id(),
        ]);

        if (is_wp_error($comunidade_id)) {
            return $comunidade_id;
        }
    }

    if (!empty($data['tipo'])) {
        wp_set_object_terms($comunidade_id, [(int) $data['tipo']], 'tipo_comunidade');
    }

    update_post_meta($comunidade_id, 'latitude', $latitude);
    update_post_meta($comunidade_id, 'longitude', $longitude);
    update_post_meta($comunidade_id, 'endereco', sanitize_text_field($data['endereco'] ?? ''));

    if (!empty($data['parent_paroquia'])) {
        update_post_meta($comunidade_id, 'parent_paroquia', intval($data['parent_paroquia']));
    } else {
        delete_post_meta($comunidade_id, 'parent_paroquia');
    }

    if (!empty($data['contatos']) && is_array($data['contatos'])) {

        $contatos_limpos = [];

        foreach ($data['contatos'] as $c) {
            if (empty($c['tipo']) || empty($c['valor'])) continue;

            $contatos_limpos[] = [
                'tipo'  => sanitize_text_field($c['tipo']),
                'valor' => sanitize_text_field($c['valor'])
            ];
        }

        update_post_meta($comunidade_id, 'contatos', $contatos_limpos);
    }

    cc_api_salvar_eventos($comunidade_id, $data['eventos'] ?? []);
    cc_api_remover_eventos($comunidade_id, $data['eventos_removidos'] ?? []);

    $dados_alteracao = $data;
    $dados_alteracao['acao'] = $is_edicao ? 'edicao' : 'criacao';
    cc_registrar_alteracao(
        $comunidade_id,
        $data['parent_paroquia'] ?? null,
        $dados_alteracao
    );

    $imagem_id = cc_api_processar_upload_imagem($comunidade_id, $request->get_file_params());

    if (is_wp_error($imagem_id)) {
        return $imagem_id;
    }

    return rest_ensure_response([
        'status' => 'ok',
        'comunidade_id' => $comunidade_id,
        'imagem_id' => $imagem_id,
        'modo' => $is_edicao ? 'edicao' : 'criacao'
    ]);
}

add_action('rest_api_init', function () {

    register_rest_route('mapa/v1', '/alteracoes', [
        'methods'  => 'GET',
        'callback' => 'cc_api_listar_alteracoes',
        'permission_callback' => function () {
            return is_user_logged_in();
        }
    ]);

});

function cc_api_listar_alteracoes($request = null) {
    global $wpdb;

    if (!is_user_logged_in()) {
        return new WP_Error('nao_autorizado', 'Login necessário', ['status' => 401]);
    }

    $user_id = get_current_user_id();
    $is_admin = current_user_can('manage_options');
    $paroquia_id = (int) get_user_meta($user_id, 'cc_paroquia_id', true);
    $observadas_ids = function_exists('cc_listar_comunidades_observadas_ids')
        ? cc_listar_comunidades_observadas_ids($user_id)
        : [];

    $comunidade_filter = $request ? absint($request->get_param('comunidade_id')) : 0;
    $data_inicio = $request ? sanitize_text_field($request->get_param('data_inicio')) : '';
    $data_fim = $request ? sanitize_text_field($request->get_param('data_fim')) : '';

    $table = $wpdb->prefix . 'mapa_alteracoes';

    $query = "
        SELECT
            a.id,
            a.comunidade_id,
            p.post_title as comunidade_nome,
            u.display_name as usuario_nome,
            a.created_at
        FROM $table a
        LEFT JOIN {$wpdb->posts} p ON p.ID = a.comunidade_id
        LEFT JOIN {$wpdb->users} u ON u.ID = a.user_id
        WHERE 1=1
    ";

    $params = [];

    if (!$is_admin) {
        $conds = ['a.user_id = %d'];
        $params[] = $user_id;

        if ($paroquia_id > 0) {
            $conds[] = 'a.paroquia_id = %d';
            $params[] = $paroquia_id;
            $conds[] = "EXISTS (SELECT 1 FROM {$wpdb->postmeta} pm WHERE pm.post_id = a.comunidade_id AND pm.meta_key = %s AND pm.meta_value = %d)";
            $params[] = 'parent_paroquia';
            $params[] = $paroquia_id;
        }

        if (!empty($observadas_ids)) {
            $in = implode(',', array_fill(0, count($observadas_ids), '%d'));
            $conds[] = "a.comunidade_id IN ($in)";
            $params = array_merge($params, $observadas_ids);
        }

        $query .= ' AND (' . implode(' OR ', $conds) . ')';
    }

    if ($comunidade_filter > 0) {
        $query .= ' AND a.comunidade_id = %d';
        $params[] = $comunidade_filter;
    }

    if (!empty($data_inicio)) {
        $query .= ' AND DATE(a.created_at) >= %s';
        $params[] = $data_inicio;
    }

    if (!empty($data_fim)) {
        $query .= ' AND DATE(a.created_at) <= %s';
        $params[] = $data_fim;
    }

    $query .= ' ORDER BY a.created_at DESC LIMIT 200';

    if (!empty($params)) {
        $resultados = $wpdb->get_results($wpdb->prepare($query, ...$params));
    } else {
        $resultados = $wpdb->get_results($query);
    }

    return rest_ensure_response($resultados);
}
