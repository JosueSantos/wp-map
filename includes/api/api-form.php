<?php

add_action('rest_api_init', function () {

    register_rest_route('mapa/v1', '/comunidade', [
        'methods'  => 'POST',
        'callback' => 'cc_api_cadastrar_comunidade',
        'permission_callback' => '__return_true'
        // 'permission_callback' => function () {
        //     return is_user_logged_in();
        // }
    ]);

});

function cc_api_cadastrar_comunidade($request) {
    // if (!is_user_logged_in()) {
    //     return new WP_Error('nao_autorizado', 'Login necessário', ['status' => 401]);
    // }

    $data = $request->get_json_params();

    if (empty($data)) {
        $raw = $request->get_body();
        $data = json_decode($raw, true);
    }

    if (empty($data)) {
        return new WP_Error('sem_dados', 'JSON vazio ou inválido', ['status' => 400]);
    }

    if (empty($data['nome'])) {
        return new WP_Error('nome_obrigatorio', 'Nome da comunidade é obrigatório', ['status' => 400]);
    }

    $latitude  = isset($data['latitude']) ? floatval($data['latitude']) : null;
    $longitude = isset($data['longitude']) ? floatval($data['longitude']) : null;

    if ($latitude === null || $longitude === null) {
        return new WP_Error('coords_invalidas', 'Latitude e longitude obrigatórias', ['status' => 400]);
    }


    // ========================
    // 1. Criar Comunidade
    // ========================

    $comunidade_id = wp_insert_post([
        'post_type'   => 'comunidade',
        'post_status' => 'publish',
        'post_title'  => sanitize_text_field($data['nome'] ?? 'Sem nome'),
    ]);

    if (is_wp_error($comunidade_id)) {
        return $comunidade_id;
    }

    // ========================
    // 2. Taxonomia tipo_comunidade
    // ========================

    if (!empty($data['tipo']) && taxonomy_exists('tipo_comunidade')) {

        $termo = term_exists($data['tipo'], 'tipo_comunidade');

        if (!$termo) {
            $termo = wp_insert_term($data['tipo'], 'tipo_comunidade');
        }

        if (!is_wp_error($termo)) {
            wp_set_post_terms($comunidade_id, [(int)$termo['term_id']], 'tipo_comunidade');
        }
    }

    // ========================
    // 3. Metadados Comunidade
    // ========================

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

    
    // ========================
    // Contatos estruturados
    // ========================

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

    // ========================
    // 4. Criar Eventos
    // ========================

    if (!empty($data['eventos']) && is_array($data['eventos'])) {

        foreach ($data['eventos'] as $evt) {

            $evento_id = wp_insert_post([
                'post_type'   => 'evento',
                'post_status' => 'publish',
                'post_title'  => sanitize_text_field($evt['titulo'] ?? 'Evento'),
            ]);

            if (is_wp_error($evento_id)) continue;

            // liga evento à comunidade
            update_post_meta($evento_id, 'comunidade_id', $comunidade_id);

            update_post_meta($evento_id, 'dia_semana', intval($evt['dia'] ?? 0));
            update_post_meta($evento_id, 'horario', sanitize_text_field($evt['horario'] ?? ''));
            update_post_meta($evento_id, 'descricao', sanitize_textarea_field($evt['descricao'] ?? ''));
            update_post_meta($evento_id, 'observacao', sanitize_textarea_field($evt['observacao'] ?? ''));

            if (!empty($evt['tipo'])) {
                wp_set_post_terms($evento_id, [$evt['tipo']], 'tipo_evento');
            }

            if (!empty($evt['tags'])) {
                update_post_meta($evento_id, 'tags', array_map('sanitize_text_field', $evt['tags']));
            }
        }
    }

    return rest_ensure_response([
        'status' => 'ok',
        'comunidade_id' => $comunidade_id
    ]);
}
