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
    wp_enqueue_style('leaflet-markercluster-css', 'https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css', ['leaflet-css'], '1.5.3');
    wp_enqueue_style('leaflet-markercluster-default-css', 'https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css', ['leaflet-markercluster-css'], '1.5.3');
    wp_enqueue_script('leaflet-js', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', [], '1.9.4', true);
    wp_enqueue_script('leaflet-markercluster-js', 'https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js', ['leaflet-js'], '1.5.3', true);
    wp_enqueue_style('cc-mapa-css', CC_URL . 'assets/css/mapa.css', [], filemtime(CC_PATH . 'assets/css/mapa.css'));
    wp_enqueue_style(
        'bootstrap-icons',
        'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css',
        [],
        '1.11.3'
    );
});

add_filter('document_title_parts', function ($title_parts) {
    if (!is_singular('comunidade')) {
        return $title_parts;
    }

    $comunidade_id = get_queried_object_id();
    if (!$comunidade_id) {
        return $title_parts;
    }

    $nome_local = get_the_title($comunidade_id);
    if ($nome_local) {
        $title_parts['title'] = $nome_local;
    }

    return $title_parts;
});

add_action('wp_head', function () {
    if (!is_singular('comunidade')) {
        return;
    }

    $comunidade_id = get_queried_object_id();
    if (!$comunidade_id) {
        return;
    }

    $nome_local = get_the_title($comunidade_id);
    $endereco = trim((string) get_post_meta($comunidade_id, 'endereco', true));
    $descricao = $endereco !== '' ? $endereco : 'Confira os detalhes deste local.';
    $url_local = get_permalink($comunidade_id);
    $imagem_local = get_the_post_thumbnail_url($comunidade_id, 'large');
    $twitter_card = $imagem_local ? 'summary_large_image' : 'summary';

    echo "\n" . '<meta name="description" content="' . esc_attr($descricao) . '">' . "\n";
    echo '<meta property="og:type" content="article">' . "\n";
    echo '<meta property="og:title" content="' . esc_attr($nome_local) . '">' . "\n";
    echo '<meta property="og:description" content="' . esc_attr($descricao) . '">' . "\n";
    echo '<meta property="og:url" content="' . esc_url($url_local) . '">' . "\n";
    echo '<meta name="twitter:card" content="' . esc_attr($twitter_card) . '">' . "\n";
    echo '<meta name="twitter:title" content="' . esc_attr($nome_local) . '">' . "\n";
    echo '<meta name="twitter:description" content="' . esc_attr($descricao) . '">' . "\n";

    if ($imagem_local) {
        echo '<meta property="og:image" content="' . esc_url($imagem_local) . '">' . "\n";
        echo '<meta name="twitter:image" content="' . esc_url($imagem_local) . '">' . "\n";
    }
}, 5);

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
        $horario = get_post_meta($evento->ID, 'horario', true);
        $horario_inicio = get_post_meta($evento->ID, 'horario_inicio', true);
        if ($horario_inicio === '') {
            $horario_inicio = preg_match('/^(\d{1,2}:\d{2})/', (string) $horario, $match) ? $match[1] : $horario;
        }
        $lista_eventos[] = [
            'id' => $evento->ID,
            'titulo' => $evento->post_title,
            'titulo_base' => get_post_meta($evento->ID, 'titulo_base', true) ?: $evento->post_title,
            'descricao' => get_post_meta($evento->ID, 'descricao', true),
            'observacao' => get_post_meta($evento->ID, 'observacao', true),
            'horario' => $horario,
            'horario_inicio' => $horario_inicio,
            'horario_fim' => get_post_meta($evento->ID, 'horario_fim', true),
            'frequencia' => get_post_meta($evento->ID, 'frequencia', true) ?: 'semanal',
            'dia' => $dia_semana,
            'dias' => function_exists('cc_evento_get_dias_semana') ? cc_evento_get_dias_semana($evento->ID) : [],
            'dia_mes' => get_post_meta($evento->ID, 'dia_mes', true),
            'numero_semana' => get_post_meta($evento->ID, 'numero_semana', true),
            'mes' => get_post_meta($evento->ID, 'mes', true),
            'tipos_evento' => wp_get_post_terms($evento->ID, 'tipo_evento', ['fields' => 'names']),
            'tipos_evento_slugs' => wp_get_post_terms($evento->ID, 'tipo_evento', ['fields' => 'slugs']),
            'tags_evento' => wp_get_post_terms($evento->ID, 'tags_evento', ['fields' => 'names']),
        ];
    }

    if (function_exists('cc_comparar_eventos_por_data')) {
        usort($lista_eventos, 'cc_comparar_eventos_por_data');
    }

    return $lista_eventos;
}
