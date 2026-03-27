<?php

function cc_get_comunidades_do_usuario($user_id) {
    return get_posts([
        'post_type' => 'comunidade',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'author' => $user_id,
        'orderby' => 'date',
        'order' => 'DESC',
    ]);
}

function cc_get_comunidades_observadas($user_id) {
    $ids = cc_listar_comunidades_observadas_ids($user_id);
    if (empty($ids)) return [];

    return get_posts([
        'post_type' => 'comunidade',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'post__in' => $ids,
        'orderby' => 'title',
        'order' => 'ASC',
    ]);
}

function cc_get_alteracoes_do_usuario($user_id, $filtros = [], $paginacao = []) {
    global $wpdb;

    $table = $wpdb->prefix . 'mapa_alteracoes';
    $paroquia_id = (int) get_user_meta($user_id, 'cc_paroquia_id', true);
    $observadas_ids = cc_listar_comunidades_observadas_ids($user_id);
    $is_admin = user_can($user_id, 'manage_options');

    $query = "
        SELECT a.id, a.comunidade_id, p.post_title AS comunidade_nome, u.display_name AS usuario_nome, a.created_at
        FROM $table a
        LEFT JOIN {$wpdb->posts} p ON p.ID = a.comunidade_id
        LEFT JOIN {$wpdb->users} u ON u.ID = a.user_id
        WHERE 1=1
    ";

    $params = [];

    if (!$is_admin) {
        $conds = ['a.user_id = %d'];
        $params[] = (int) $user_id;

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

    $comunidade_id = absint($filtros['comunidade_id'] ?? 0);
    if ($comunidade_id > 0) {
        $query .= ' AND a.comunidade_id = %d';
        $params[] = $comunidade_id;
    }

    $data_inicio = sanitize_text_field($filtros['data_inicio'] ?? '');
    if (!empty($data_inicio)) {
        $query .= ' AND DATE(a.created_at) >= %s';
        $params[] = $data_inicio;
    }

    $data_fim = sanitize_text_field($filtros['data_fim'] ?? '');
    if (!empty($data_fim)) {
        $query .= ' AND DATE(a.created_at) <= %s';
        $params[] = $data_fim;
    }

    $page = max(1, (int) ($paginacao['page'] ?? 1));
    $per_page = max(1, min(100, (int) ($paginacao['per_page'] ?? 10)));
    $offset = ($page - 1) * $per_page;

    $count_query = "SELECT COUNT(*) FROM ($query) AS totais";
    $total = (int) (empty($params)
        ? $wpdb->get_var($count_query)
        : $wpdb->get_var($wpdb->prepare($count_query, ...$params)));

    $query .= ' ORDER BY a.created_at DESC LIMIT %d OFFSET %d';
    $params_data = array_merge($params, [$per_page, $offset]);
    $itens = empty($params_data)
        ? $wpdb->get_results($query)
        : $wpdb->get_results($wpdb->prepare($query, ...$params_data));

    return [
        'items' => is_array($itens) ? $itens : [],
        'total' => $total,
        'per_page' => $per_page,
        'page' => $page,
        'total_pages' => max(1, (int) ceil($total / $per_page)),
    ];
}
