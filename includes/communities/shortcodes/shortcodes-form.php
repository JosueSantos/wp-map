<?php

add_shortcode('mapa_form_comunidade', function () {

    $redirect_to = get_permalink();

    if (!empty($_SERVER['REQUEST_URI'])) {
        $request_uri = wp_unslash($_SERVER['REQUEST_URI']);
        $request_url = home_url($request_uri);
        $redirect_to = esc_url_raw($request_url) ?: $redirect_to;
    }

    wp_enqueue_style('leaflet-css', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css');

    wp_enqueue_script('leaflet-js', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', [], null, true);

    wp_enqueue_script('mapa-form', CC_URL . 'assets/js/form.js', ['leaflet-js'], filemtime(CC_PATH . 'assets/js/form.js'), true);

    wp_enqueue_style(
        'bootstrap-icons',
        'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css',
        [],
        '1.11.3'
    );

    wp_localize_script('mapa-form', 'MAPA_API', [
        'url'   => rest_url('mapa/v1/comunidade'),
        'nonce' => wp_create_nonce('wp_rest'),
        'is_logged_in' => is_user_logged_in(),
        'current_user_name' => is_user_logged_in() ? wp_get_current_user()->display_name : '',
        'login_url' => function_exists('cc_with_redirect_to')
            ? cc_with_redirect_to(cc_get_auth_page_url('login', '/login'), $redirect_to)
            : wp_login_url($redirect_to),
        'register_url' => function_exists('cc_with_redirect_to')
            ? cc_with_redirect_to(cc_get_auth_page_url('cadastro', '/cadastro'), $redirect_to)
            : wp_registration_url(),
        'map_url' => home_url('/mapa-comunidades/'),
        'form_url' => get_permalink()
    ]);

    wp_enqueue_script('tailwind-cdn', 'https://cdn.tailwindcss.com', [], null);

    return cc_render_template('shortcodes/form-comunidade.php');
});
