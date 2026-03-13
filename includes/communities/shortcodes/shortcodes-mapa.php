<?php

// Exibir Mapa
function cc_mapa_shortcode($atts) {

    $atts = shortcode_atts([
        'dominio' => '',
        'url_cadastro' => '/cadastro-comunidade/'
    ], $atts);

    $dominio = esc_url_raw($atts['dominio']);
    $url_cadastro = esc_url_raw($atts['url_cadastro']);

    wp_enqueue_style('leaflet-css', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css');
    wp_enqueue_style('leaflet-markercluster-css', 'https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css', ['leaflet-css'], '1.5.3');
    wp_enqueue_style('leaflet-markercluster-default-css', 'https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css', ['leaflet-markercluster-css'], '1.5.3');

    // Tailwind (fallback para temas que não carregam utilitários)
    wp_enqueue_script('tailwind-cdn', 'https://cdn.tailwindcss.com', [], null);

    // CSS de fallback para garantir layout e altura do mapa sem Tailwind
    wp_enqueue_style('cc-mapa-css', CC_URL . 'assets/css/mapa.css', [], '1.2');

    wp_enqueue_style(
        'bootstrap-icons',
        'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css',
        [],
        '1.11.3'
    );

    wp_enqueue_script('leaflet-js', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', [], null, true);
    wp_enqueue_script('leaflet-markercluster-js', 'https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js', ['leaflet-js'], '1.5.3', true);

    wp_enqueue_script('cc-mapa', CC_URL . 'assets/js/mapa.js', ['leaflet-js', 'leaflet-markercluster-js'], '1.9', true);

    return cc_render_template('shortcodes/mapa.php', [
        'dominio' => $dominio,
        'url_cadastro' => $url_cadastro,
        'is_user_logged_in' => is_user_logged_in(),
    ]);
}

add_shortcode('mapa_igrejas', 'cc_mapa_shortcode');
