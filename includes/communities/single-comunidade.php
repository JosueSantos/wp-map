<?php

if (!defined('ABSPATH')) exit;

add_filter('single_template', function ($template) {
    if (is_singular('comunidade')) {
        $custom_template = CC_PATH . 'templates/single-comunidade.php';
        if (file_exists($custom_template)) {
            return $custom_template;
        }
    }

    return $template;
});

add_action('wp_enqueue_scripts', function () {
    if (!is_singular('comunidade')) {
        return;
    }

    wp_enqueue_script('tailwind-cdn', 'https://cdn.tailwindcss.com', [], null);
    wp_enqueue_style('leaflet-css', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css', [], '1.9.4');
    wp_enqueue_script('leaflet-js', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', [], '1.9.4', true);
    wp_enqueue_style('cc-mapa-css', CC_URL . 'assets/css/mapa.css', [], '1.2');
    wp_enqueue_style(
        'bootstrap-icons',
        'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css',
        [],
        '1.11.3'
    );
});

function cc_obter_eventos_comunidade_ordenados($comunidade_id) {
    $eventos = get_posts([
        'post_type' => 'evento',
        'posts_per_page' => -1,
        'post_status' => 'publish',
        'meta_query' => [
            [
                'key' => 'comunidade_id',
                'value' => (int) $comunidade_id,
            ]
        ]
    ]);

    $lista_eventos = [];

    foreach ($eventos as $evento) {
        $dia_semana = get_post_meta($evento->ID, 'dia_semana', true);

        $lista_eventos[] = [
            'id' => $evento->ID,
            'titulo' => $evento->post_title,
            'descricao' => get_post_meta($evento->ID, 'descricao', true),
            'observacao' => get_post_meta($evento->ID, 'observacao', true),
            'horario' => get_post_meta($evento->ID, 'horario', true),
            'frequencia' => get_post_meta($evento->ID, 'frequencia', true) ?: 'semanal',
            'dia' => $dia_semana,
            'dias' => function_exists('cc_evento_get_dias_semana') ? cc_evento_get_dias_semana($evento->ID) : [],
            'dia_mes' => get_post_meta($evento->ID, 'dia_mes', true),
            'numero_semana' => get_post_meta($evento->ID, 'numero_semana', true),
            'mes' => get_post_meta($evento->ID, 'mes', true),
            'tipos_evento' => wp_get_post_terms($evento->ID, 'tipo_evento', ['fields' => 'names']),
            'tags_evento' => wp_get_post_terms($evento->ID, 'tags_evento', ['fields' => 'names']),
        ];
    }

    if (function_exists('cc_comparar_eventos_por_data')) {
        usort($lista_eventos, 'cc_comparar_eventos_por_data');
    }

    return $lista_eventos;
}
