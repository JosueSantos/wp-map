<?php

// Exibir Mapa
function cc_mapa_shortcode($atts) {

    $atts = shortcode_atts([
        'dominio' => ''
    ], $atts);

    $dominio = esc_url_raw($atts['dominio']);

    wp_enqueue_style(
        'leaflet-css',
        'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css'
    );

    wp_enqueue_script(
        'leaflet-js',
        'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js',
        [],
        null,
        true
    );

    wp_enqueue_script(
        'cc-mapa',
        plugin_dir_url(__FILE__) . '../../assets/js/mapa.js',
        ['leaflet-js'],
        '1.0',
        true
    );

    ob_start();

    ?>
    <div id="mapa-igrejas"
         data-dominio="<?php echo esc_attr($dominio); ?>"
         style="width:100%; height:500px;">
    </div>
    <?php

    return ob_get_clean();
}

add_shortcode('mapa_igrejas', 'cc_mapa_shortcode');
