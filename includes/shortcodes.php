<?php

function cc_lista_comunidades() {

    $q = new WP_Query([
        'post_type' => 'comunidade',
        'posts_per_page' => -1
    ]);

    ob_start();

    while ($q->have_posts()) {
        $q->the_post();
        echo "<div class='comunidade'>";
        echo "<h3>" . get_the_title() . "</h3>";
        echo "<p>" . get_the_excerpt() . "</p>";
        echo "</div>";
    }

    return ob_get_clean();
}

add_shortcode('cc_comunidades', 'cc_lista_comunidades');

function cc_eventos_comunidade($atts) {

    $atts = shortcode_atts(['id'=>0], $atts);

    $q = new WP_Query([
        'post_type' => 'evento',
        'meta_query' => [
            [
                'key' => 'comunidade_id',
                'value' => $atts['id']
            ]
        ]
    ]);

    ob_start();

    while ($q->have_posts()) {
        $q->the_post();
        $dia = get_post_meta(get_the_ID(),'dia_semana',true);
        $hora = get_post_meta(get_the_ID(),'horario',true);

        echo "<div class='evento'>";
        echo "<strong>" . get_the_title() . "</strong>";
        echo "<p>$dia às $hora</p>";
        echo "</div>";
    }

    return ob_get_clean();
}

add_shortcode('cc_eventos', 'cc_eventos_comunidade');
