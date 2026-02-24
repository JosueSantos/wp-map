<?php

add_action('rest_api_init', function () {

    register_rest_route('mapa/v1', '/comunidade', [
        'methods'  => 'POST',
        'callback' => 'cc_api_cadastrar_comunidade',
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

function cc_api_cadastrar_comunidade($request) {
    if (!is_user_logged_in()) {
        return new WP_Error('nao_autorizado', 'Login necessário', ['status' => 401]);
    }

    $data = cc_api_normalizar_dados_comunidade($request);

    if (empty($data)) {
        return new WP_Error('sem_dados', 'Dados vazios ou inválidos', ['status' => 400]);
    }

    if (empty($data['nome'])) {
        return new WP_Error('nome_obrigatorio', 'Nome da comunidade é obrigatório', ['status' => 400]);
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

    $comunidade_id = wp_insert_post([
        'post_type'   => 'comunidade',
        'post_status' => 'publish',
        'post_title'  => sanitize_text_field($data['nome'] ?? 'Sem nome'),
    ]);

    if (is_wp_error($comunidade_id)) {
        return $comunidade_id;
    }

    if (!empty($data['tipo'])) {
        wp_set_object_terms(
            $comunidade_id,
            [(int) $data['tipo']],
            'tipo_comunidade'
        );
    }

    update_post_meta($comunidade_id, 'latitude', $latitude);
    update_post_meta($comunidade_id, 'longitude', $longitude);
    update_post_meta($comunidade_id, 'endereco', sanitize_text_field($data['endereco'] ?? ''));

    if (!empty($data['parent_paroquia'])) {
        update_post_meta($comunidade_id, 'parent_paroquia', intval($data['parent_paroquia']));
    }

    cc_registrar_alteracao(
        $comunidade_id,
        $data['parent_paroquia'] ?? null,
        $data
    );

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

    if (!empty($data['eventos']) && is_array($data['eventos'])) {

        foreach ($data['eventos'] as $evt) {

            $titulo_evento = sanitize_text_field($evt['titulo'] ?? '');
            if (empty($titulo_evento)) continue;

            $evento_id = wp_insert_post([
                'post_type'   => 'evento',
                'post_status' => 'publish',
                'post_title'  => $titulo_evento,
            ]);

            if (is_wp_error($evento_id)) continue;

            $dia_semana = isset($evt['dia']) ? max(0, min(6, (int) $evt['dia'])) : 0;
            $horario = sanitize_text_field($evt['horario'] ?? '');

            update_post_meta($evento_id, 'comunidade_id', $comunidade_id);
            update_post_meta($evento_id, 'dia_semana', $dia_semana);
            update_post_meta($evento_id, 'horario', $horario);
            update_post_meta($evento_id, 'descricao', sanitize_textarea_field($evt['descricao'] ?? ''));
            update_post_meta($evento_id, 'observacao', sanitize_textarea_field($evt['observacao'] ?? ''));

            if (!empty($evt['tipo_evento'])) {
                wp_set_object_terms(
                    $evento_id,
                    [(int) $evt['tipo_evento']],
                    'tipo_evento'
                );
            }

            if (!empty($evt['tags_evento']) && is_array($evt['tags_evento'])) {
                wp_set_object_terms(
                    $evento_id,
                    array_map('intval', $evt['tags_evento']),
                    'tags_evento'
                );
            }
        }
    }

    $imagem_id = cc_api_processar_upload_imagem($comunidade_id, $request->get_file_params());

    if (is_wp_error($imagem_id)) {
        return $imagem_id;
    }

    return rest_ensure_response([
        'status' => 'ok',
        'comunidade_id' => $comunidade_id,
        'imagem_id' => $imagem_id
    ]);
}

add_action('rest_api_init', function () {

    register_rest_route('mapa/v1', '/alteracoes', [
        'methods'  => 'GET',
        'callback' => 'cc_api_listar_alteracoes',
        'permission_callback' => '__return_true'
    ]);

});

function cc_api_listar_alteracoes() {
    global $wpdb;

    $table = $wpdb->prefix . 'mapa_alteracoes';

    $resultados = $wpdb->get_results(
        "
        SELECT
            a.id,
            a.comunidade_id,
            p.post_title as comunidade_nome,
            u.display_name as usuario_nome,
            a.created_at
        FROM $table a
        LEFT JOIN {$wpdb->posts} p ON p.ID = a.comunidade_id
        LEFT JOIN {$wpdb->users} u ON u.ID = a.user_id
        ORDER BY a.created_at DESC
        "
    );

    return rest_ensure_response($resultados);
}
