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


function cc_seo_comunidade_terms($comunidade_id) {
    $terms = wp_get_post_terms($comunidade_id, 'tipo_comunidade', ['fields' => 'names']);
    return is_wp_error($terms) ? [] : array_values(array_filter($terms));
}

function cc_seo_comunidade_description($comunidade_id, $eventos = null) {
    $nome = get_the_title($comunidade_id);
    $endereco = trim((string) get_post_meta($comunidade_id, 'endereco', true));
    $tipos = cc_seo_comunidade_terms($comunidade_id);
    $partes = [];

    if ($nome) {
        $partes[] = $nome;
    }

    if (!empty($tipos)) {
        $partes[] = implode(', ', $tipos);
    }

    if ($endereco !== '') {
        $partes[] = 'localizado em ' . $endereco;
    }

    if ($eventos === null && function_exists('cc_obter_eventos_comunidade_ordenados')) {
        $eventos = cc_obter_eventos_comunidade_ordenados($comunidade_id);
    }

    $atividades = [];
    foreach ((array) $eventos as $evento) {
        foreach ((array) ($evento['tipos_evento'] ?? []) as $tipo_evento) {
            $tipo_evento = trim((string) $tipo_evento);
            if ($tipo_evento !== '' && !in_array($tipo_evento, $atividades, true)) {
                $atividades[] = $tipo_evento;
            }
        }
        if (count($atividades) >= 3) break;
    }

    if (!empty($atividades)) {
        $partes[] = 'com atividades como ' . implode(', ', $atividades);
    }

    $descricao = trim(implode(', ', $partes));
    if ($descricao === '') {
        $descricao = 'Confira endereço, contatos, mapa e atividades deste local.';
    }

    return wp_html_excerpt(wp_strip_all_tags($descricao), 155, '…');
}

function cc_seo_evento_iso_time($time) {
    $time = trim((string) $time);
    if ($time === '') return '';
    if (preg_match('/^(\d{1,2}):(\d{2})/', $time, $matches)) {
        return sprintf('%02d:%02d:00', (int) $matches[1], (int) $matches[2]);
    }
    return '';
}

function cc_seo_evento_schedule_schema($evento) {
    $frequencia = (string) ($evento['frequencia'] ?? 'semanal');
    $dias = is_array($evento['dias'] ?? null) ? $evento['dias'] : [];
    if (empty($dias) && isset($evento['dia']) && (string) $evento['dia'] !== '') {
        $dias = [(string) $evento['dia']];
    }

    $schema = ['@type' => 'Schedule'];
    $dia_schema = ['0' => 'https://schema.org/Sunday', '1' => 'https://schema.org/Monday', '2' => 'https://schema.org/Tuesday', '3' => 'https://schema.org/Wednesday', '4' => 'https://schema.org/Thursday', '5' => 'https://schema.org/Friday', '6' => 'https://schema.org/Saturday'];

    if ($frequencia === 'mensal') {
        $schema['repeatFrequency'] = 'P1M';
        if (!empty($evento['dia_mes'])) $schema['byMonthDay'] = (int) $evento['dia_mes'];
    } elseif ($frequencia === 'anual') {
        $schema['repeatFrequency'] = 'P1Y';
        if (!empty($evento['dia_mes'])) $schema['byMonthDay'] = (int) $evento['dia_mes'];
        if (!empty($evento['mes'])) $schema['byMonth'] = (int) $evento['mes'];
    } else {
        $schema['repeatFrequency'] = 'P1W';
        $by_day = [];
        foreach ($dias as $dia) {
            $key = (string) $dia;
            if (isset($dia_schema[$key])) $by_day[] = $dia_schema[$key];
        }
        if (!empty($by_day)) $schema['byDay'] = $by_day;
    }

    $start_time = cc_seo_evento_iso_time($evento['horario_inicio'] ?? ($evento['horario'] ?? ''));
    $end_time = cc_seo_evento_iso_time($evento['horario_fim'] ?? '');
    if ($start_time) $schema['startTime'] = $start_time;
    if ($end_time) $schema['endTime'] = $end_time;

    return $schema;
}

function cc_seo_comunidade_schema($comunidade_id, $eventos = null) {
    $nome = get_the_title($comunidade_id);
    $url = get_permalink($comunidade_id);
    $endereco = trim((string) get_post_meta($comunidade_id, 'endereco', true));
    $lat = get_post_meta($comunidade_id, 'latitude', true);
    $lng = get_post_meta($comunidade_id, 'longitude', true);
    $imagem = get_the_post_thumbnail_url($comunidade_id, 'large');
    $descricao = cc_seo_comunidade_description($comunidade_id, $eventos);
    $contatos = get_post_meta($comunidade_id, 'contatos', true);
    $contatos = is_array($contatos) ? $contatos : [];

    $local = [
        '@type' => 'Church',
        '@id' => trailingslashit($url) . '#local',
        'name' => $nome,
        'url' => $url,
        'description' => $descricao,
    ];

    if ($endereco !== '') $local['address'] = $endereco;
    if ($imagem) $local['image'] = $imagem;
    if ($lat !== '' && $lng !== '') {
        $local['geo'] = ['@type' => 'GeoCoordinates', 'latitude' => (float) $lat, 'longitude' => (float) $lng];
        $local['hasMap'] = 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($endereco ?: ($lat . ',' . $lng));
    }

    foreach ($contatos as $contato) {
        $tipo = sanitize_key((string) ($contato['tipo'] ?? ''));
        $valor = trim((string) ($contato['valor'] ?? ''));
        if ($valor === '') continue;
        if ($tipo === 'telefone' || $tipo === 'whatsapp') {
            $local['telephone'] = $valor;
            break;
        }
    }

    $graph = [$local, [
        '@type' => 'BreadcrumbList',
        '@id' => trailingslashit($url) . '#breadcrumb',
        'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => get_bloginfo('name'), 'item' => home_url('/')],
            ['@type' => 'ListItem', 'position' => 2, 'name' => $nome, 'item' => $url],
        ],
    ]];

    $eventos = $eventos === null && function_exists('cc_obter_eventos_comunidade_ordenados') ? cc_obter_eventos_comunidade_ordenados($comunidade_id) : (array) $eventos;
    foreach (array_slice($eventos, 0, 12) as $evento) {
        $titulo = trim((string) ($evento['titulo_base'] ?? ($evento['titulo'] ?? '')));
        if ($titulo === '') continue;
        $event_schema = [
            '@type' => 'Event',
            'name' => $titulo . ' - ' . $nome,
            'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
            'eventStatus' => 'https://schema.org/EventScheduled',
            'location' => ['@id' => trailingslashit($url) . '#local'],
            'eventSchedule' => cc_seo_evento_schedule_schema($evento),
        ];
        if (!empty($evento['descricao'])) $event_schema['description'] = wp_strip_all_tags((string) $evento['descricao']);
        $graph[] = $event_schema;
    }

    return ['@context' => 'https://schema.org', '@graph' => $graph];
}

add_filter('document_title_parts', function ($title_parts) {
    if (!is_singular('comunidade')) {
        return $title_parts;
    }

    $comunidade_id = get_queried_object_id();
    if (!$comunidade_id) {
        return $title_parts;
    }

    $nome_local = get_the_title($comunidade_id);
    $endereco = trim((string) get_post_meta($comunidade_id, 'endereco', true));
    if ($nome_local) {
        $title_parts['title'] = $endereco !== '' ? $nome_local . ' | Endereço, horários e mapa' : $nome_local . ' | Informações e atividades';
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
    $eventos = function_exists('cc_obter_eventos_comunidade_ordenados') ? cc_obter_eventos_comunidade_ordenados($comunidade_id) : [];
    $descricao = cc_seo_comunidade_description($comunidade_id, $eventos);
    $url_local = get_permalink($comunidade_id);
    $imagem_local = get_the_post_thumbnail_url($comunidade_id, 'large');
    $twitter_card = $imagem_local ? 'summary_large_image' : 'summary';
    $schema = cc_seo_comunidade_schema($comunidade_id, $eventos);

    echo "\n" . '<link rel="canonical" href="' . esc_url($url_local) . '">' . "\n";
    echo '<meta name="description" content="' . esc_attr($descricao) . '">' . "\n";
    echo '<meta property="og:type" content="place">' . "\n";
    echo '<meta property="og:locale" content="pt_BR">' . "\n";
    echo '<meta property="og:site_name" content="' . esc_attr(get_bloginfo('name')) . '">' . "\n";
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

    echo '<script type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>' . "\n";
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
