<?php

// Exibir Mapa
function cc_mapa_shortcode($atts) {

    $atts = shortcode_atts([
        'dominio' => ''
    ], $atts);

    $dominio = esc_url_raw($atts['dominio']);

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
