<?php

add_shortcode('mapa_form_comunidade', function () {

    wp_enqueue_style('leaflet-css', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css');

    wp_enqueue_script('leaflet-js', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', [], null, true);

    wp_enqueue_script('mapa-form', CC_URL . 'assets/js/form.js', ['leaflet-js'], '1.2', true);

    wp_localize_script('mapa-form', 'MAPA_API', [
        'url'   => rest_url('mapa/v1/comunidade'),
        'nonce' => wp_create_nonce('wp_rest'),
        'is_logged_in' => is_user_logged_in(),
        'current_user_name' => is_user_logged_in() ? wp_get_current_user()->display_name : '',
        'login_url' => function_exists('cc_get_auth_page_url') ? cc_get_auth_page_url('login', '/login') : wp_login_url(get_permalink()),
        'register_url' => function_exists('cc_get_auth_page_url') ? cc_get_auth_page_url('cadastro', '/cadastro') : wp_registration_url()
    ]);

    wp_enqueue_script('tailwind-cdn', 'https://cdn.tailwindcss.com', [], null);

    return cc_render_template('shortcodes/form-comunidade.php');
});
