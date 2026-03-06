<?php

function cc_get_auth_page_url($slug, $fallback = '/') {
    $aliases = [
        'login' => ['login', 'login-mapa'],
        'cadastro' => ['cadastro', 'cadastro-mapa'],
        'minha-conta' => ['minha-conta', 'minha-conta-mapa'],
        'esqueci-senha' => ['esqueci-senha', 'esqueci-senha-mapa'],
        'redefinir-senha' => ['redefinir-senha', 'redefinir-senha-mapa'],
        'alterar-senha' => ['alterar-senha', 'alterar-senha-mapa'],
        'mapa-comunidades' => ['mapa-comunidades', 'mapa-comunidade'],
        'cadastro-comunidade' => ['cadastro-comunidade'],
    ];

    $candidate_slugs = $aliases[$slug] ?? [$slug];

    foreach ($candidate_slugs as $candidate) {
        $page = get_page_by_path($candidate);
        if ($page) {
            return get_permalink($page->ID);
        }
    }

    return home_url($fallback);
}

function cc_get_safe_redirect_url_from_request() {
    $redirect_to = isset($_REQUEST['redirect_to']) ? wp_unslash($_REQUEST['redirect_to']) : '';
    $redirect_to = esc_url_raw($redirect_to);

    if (!$redirect_to) {
        return '';
    }

    return wp_validate_redirect($redirect_to, '');
}

function cc_with_redirect_to($url, $redirect_to) {
    if (!$redirect_to) {
        return $url;
    }

    return add_query_arg('redirect_to', $redirect_to, $url);
}

function cc_get_auth_success_redirect_url() {
    $redirect_to = cc_get_safe_redirect_url_from_request();

    if ($redirect_to) {
        return $redirect_to;
    }

    return cc_get_auth_page_url('minha-conta', '/minha-conta');
}

function cc_criar_paginas_auth() {
    $pages = [
        'login' => ['title' => __('Login', 'cadastro-comunidades'), 'content' => '[login-mapa]'],
        'cadastro' => ['title' => __('Cadastro', 'cadastro-comunidades'), 'content' => '[cadastro-mapa]'],
        'minha-conta' => ['title' => __('Minha Conta', 'cadastro-comunidades'), 'content' => '[minha-conta-mapa url_editar_comunidade="/cadastro-comunidade/"]'],
        'esqueci-senha' => ['title' => __('Esqueci a senha', 'cadastro-comunidades'), 'content' => '[esqueci-senha-mapa]'],
        'redefinir-senha' => ['title' => __('Redefinir senha', 'cadastro-comunidades'), 'content' => '[redefinir-senha-mapa]'],
        'alterar-senha' => ['title' => __('Alterar senha', 'cadastro-comunidades'), 'content' => '[alterar-senha-mapa]'],
        'mapa-comunidades' => ['title' => __('Mapa de Comunidades', 'cadastro-comunidades'), 'content' => '[mapa_igrejas url_cadastro="/cadastro-comunidade/"]'],
        'cadastro-comunidade' => ['title' => __('Cadastro de Comunidade', 'cadastro-comunidades'), 'content' => '[mapa_form_comunidade]'],
    ];

    foreach ($pages as $slug => $page_data) {
        if (get_page_by_path($slug)) continue;

        wp_insert_post([
            'post_type' => 'page',
            'post_title' => $page_data['title'],
            'post_name' => $slug,
            'post_status' => 'publish',
            'post_content' => $page_data['content'],
        ]);
    }
}

function cc_maybe_ensure_auth_pages() {
    if (!is_admin() || !current_user_can('manage_options')) {
        return;
    }

    cc_criar_paginas_auth();
}
add_action('admin_init', 'cc_maybe_ensure_auth_pages');

function cc_filter_auth_menu_items($items) {
    $is_logged = is_user_logged_in();

    $hidden_for_logged = ['login', 'cadastro'];
    $hidden_for_guest = ['minha-conta', 'alterar-senha', 'logout', 'sair'];

    foreach ($items as $index => $item) {
        $title = sanitize_title((string) $item->title);
        $slug = sanitize_title((string) basename((string) wp_parse_url((string) $item->url, PHP_URL_PATH)));

        if ($is_logged && (in_array($slug, $hidden_for_logged, true) || in_array($title, $hidden_for_logged, true))) {
            unset($items[$index]);
            continue;
        }

        if (!$is_logged && (in_array($slug, $hidden_for_guest, true) || in_array($title, $hidden_for_guest, true))) {
            unset($items[$index]);
        }
    }

    return $items;
}
add_filter('wp_nav_menu_objects', 'cc_filter_auth_menu_items');

