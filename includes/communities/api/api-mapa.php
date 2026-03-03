<?php

// API
// Rota /wp-json/mapa/v1/comunidades
// Retorna a lista de Comunidades
//
// Parametros
// periodo string [hoje, semana, data]
// tipo_evento string [missa, confissão ...]
// tipo_comunidade string [paroquia, capela, independente]
// lat integer coordenada geografica
// lng integer coordenada geografica
// raio integer Raio de distancia para a busca de comunidades, só funciona se possuir lat e lng
// tag string [libras, tridentina, crianças ...]
// limite integer Quantidade Maxima de comunidades retornadas pela api
// proximidade boolean Ordenada pela maior proximidade do ponto latitude e longitude oferecidos
add_action('rest_api_init', function () {

    register_rest_route('mapa/v1', '/comunidades', [
        'methods'  => 'GET',
        'callback' => 'cc_api_mapa_comunidades',
        'permission_callback' => '__return_true',
        'args' => [
            'lat' => ['validate_callback' => 'is_numeric'],
            'lng' => ['validate_callback' => 'is_numeric'],
        ]
    ]);

    register_rest_route('mapa/v1', '/filtros', [
        'methods'  => 'GET',
        'callback' => 'cc_api_mapa_filtros',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('mapa/v1', '/paroquias', [
        'methods'  => 'GET',
        'callback' => function ($request) {

            $search = sanitize_text_field($request->get_param('search'));

            $query = new WP_Query([
                'post_type' => 'comunidade',
                's' => $search,
                'tax_query' => [
                    [
                        'taxonomy' => 'tipo_comunidade',
                        'field'    => 'slug',
                        'terms'    => 'paroquia',
                    ]
                ]
            ]);

            $resultado = [];

            foreach ($query->posts as $post) {
                $resultado[] = [
                    'id' => $post->ID,
                    'nome' => $post->post_title
                ];
            }

            return $resultado;
        },
        'permission_callback' => '__return_true'
    ]);

});

function cc_api_mapa_filtros() {

    $tipos_comunidade = get_terms([
        'taxonomy' => 'tipo_comunidade',
        'hide_empty' => false,
    ]);

    $tipos_evento = get_terms([
        'taxonomy' => 'tipo_evento',
        'hide_empty' => false,
    ]);

    $tags_taxonomia = get_terms([
        'taxonomy' => 'tags_evento',
        'hide_empty' => false,
    ]);

    $tags_meta = [];
    $eventos = get_posts([
        'post_type' => 'evento',
        'posts_per_page' => -1,
        'post_status' => 'publish',
        'fields' => 'ids',
    ]);

    foreach ($eventos as $evento_id) {
        $tags_evento = get_post_meta($evento_id, 'tags', true);
        $tags_evento = is_array($tags_evento) ? $tags_evento : array_filter(array_map('trim', explode(',', (string) $tags_evento)));

        foreach ($tags_evento as $tag) {
            if ($tag !== '') {
                $tags_meta[] = sanitize_text_field($tag);
            }
        }
    }

    $tags_meta = array_values(array_unique($tags_meta));

    $lista_tipos_comunidade = [];
    foreach ($tipos_comunidade as $termo) {
        $lista_tipos_comunidade[] = [
            'slug' => $termo->slug,
            'nome' => $termo->name,
        ];
    }

    $lista_tipos_evento = [];
    foreach ($tipos_evento as $termo) {
        $lista_tipos_evento[] = [
            'slug' => $termo->slug,
            'nome' => $termo->name,
        ];
    }

    $lista_tags = [];
    foreach ($tags_taxonomia as $termo) {
        $lista_tags[$termo->slug] = [
            'slug' => $termo->slug,
            'nome' => $termo->name,
        ];
    }

    foreach ($tags_meta as $tag) {
        $slug = sanitize_title($tag);
        if (!isset($lista_tags[$slug])) {
            $lista_tags[$slug] = [
                'slug' => $slug,
                'nome' => $tag,
            ];
        }
    }

    $periodos = [
        ['slug' => '', 'nome' => 'Qualquer período'],
        ['slug' => 'hoje', 'nome' => 'Missas hoje'],
        ['slug' => 'semana', 'nome' => 'Missas nesta semana'],
        ['slug' => 'data', 'nome' => 'Missas por dia selecionado'],
    ];

    return rest_ensure_response([
        'periodos' => $periodos,
        'tipos_evento' => $lista_tipos_evento,
        'tipos_comunidade' => $lista_tipos_comunidade,
        'tags' => array_values($lista_tags),
    ]);
}

function cc_api_mapa_comunidades($request) {
    $cache_key = 'mapa_api_' . md5(json_encode($request->get_params()));
    $cache = get_transient($cache_key);

    if ($cache) return $cache;

    $periodo = sanitize_key((string) $request->get_param('periodo'));
    $data_param = sanitize_text_field((string) $request->get_param('data'));
    $tipo_evento = sanitize_text_field($request->get_param('tipo_evento'));
    $tipo_comunidade = sanitize_text_field($request->get_param('tipo_comunidade'));
    $user_lat = $request->get_param('lat');
    $user_lng = $request->get_param('lng');
    $raio = floatval($request->get_param('raio'));
    $tag = sanitize_text_field($request->get_param('tag'));
    $limite = intval($request->get_param('limite'));
    $proximidade = filter_var($request->get_param('proximidade'), FILTER_VALIDATE_BOOLEAN);

    // Buscar comunidades
    $args = [
        'post_type'      => 'comunidade',
        'posts_per_page' => -1,
        'post_status'    => 'publish'
    ];

    if ($tipo_comunidade) {
        $tipos_filtrados = $tipo_comunidade === 'capela'
            ? ['capela', 'paroquia']
            : [$tipo_comunidade];

        // Filtro por Tipo de Comunidade [capela, paroquia ou independente]
        $args['tax_query'] = [[
            'taxonomy' => 'tipo_comunidade',
            'field'    => 'slug',
            'terms'    => $tipos_filtrados
        ]];
    }

    $comunidades = get_posts($args);

    $resultado = [];

    foreach ($comunidades as $c) {

        $lat = get_post_meta($c->ID, 'latitude', true);
        $lng = get_post_meta($c->ID, 'longitude', true);

        // Obrigatorio possuir as coordenadas geograficas
        if ($lat === '' || $lng === '') continue;

        // Buscar eventos da comunidade
        $eventos_args = [
            'post_type'      => 'evento',
            'posts_per_page' => -1,
            'meta_query' => [
                [
                    'key'   => 'comunidade_id',
                    'value' => $c->ID
                ]
            ]
        ];

        $eventos = get_posts($eventos_args);
        $lista_eventos = [];

        foreach ($eventos as $e) {

            $dia_semana = get_post_meta($e->ID, 'dia_semana', true);
            $horario    = get_post_meta($e->ID, 'horario', true);
            $descricao  = get_post_meta($e->ID, 'descricao', true);
            $observacao = get_post_meta($e->ID, 'observacao', true);

            $tipo_evt = wp_get_post_terms($e->ID, 'tipo_evento', ['fields'=>'slugs']);
            $tipo_evt = $tipo_evt[0] ?? '';

            // FILTROS
            if ($periodo !== '' && !cc_evento_ocorre_no_periodo($e->ID, $periodo, $data_param)) continue;

            if ($tipo_evento && $tipo_evt !== $tipo_evento) continue;

            $tags_evento_meta = get_post_meta($e->ID, 'tags', true);
            $tags_evento_meta = is_array($tags_evento_meta) ? $tags_evento_meta : array_filter(array_map('trim', explode(',', (string)$tags_evento_meta)));

            $tags_evento_taxonomia = wp_get_post_terms($e->ID, 'tags_evento', ['fields' => 'slugs']);
            $tags_evento_taxonomia = is_array($tags_evento_taxonomia) ? $tags_evento_taxonomia : [];

            $tags_evento = [];
            foreach (array_merge($tags_evento_taxonomia, $tags_evento_meta) as $tag_evento) {
                $tag_evento_slug = sanitize_title((string) $tag_evento);
                if ($tag_evento_slug !== '') {
                    $tags_evento[] = $tag_evento_slug;
                }
            }

            if ($tag && !in_array(sanitize_title($tag), $tags_evento, true)) continue;

            $lista_eventos[] = [
                'id'        => $e->ID,
                'titulo'    => $e->post_title,
                'tipo'      => $tipo_evt,
                'frequencia'=> get_post_meta($e->ID, 'frequencia', true) ?: 'semanal',
                'dia'       => $dia_semana,
                'dia_mes'   => get_post_meta($e->ID, 'dia_mes', true),
                'numero_semana' => get_post_meta($e->ID, 'numero_semana', true),
                'mes'       => get_post_meta($e->ID, 'mes', true),
                'horario'   => $horario,
                'descricao' => $descricao,
                'observacao'=> $observacao
            ];
        }

        if ((($periodo !== '') || $tipo_evento || $tag) && empty($lista_eventos)) {
            continue;
        }

        $foto = get_the_post_thumbnail_url($c->ID, 'medium');

        $tipo_com = wp_get_post_terms($c->ID, 'tipo_comunidade', ['fields'=>'slugs']);
        $tipo_com = $tipo_com[0] ?? '';

        $distancia = null;

        if ($user_lat && $user_lng) {
            $distancia = cc_calcular_distancia($user_lat, $user_lng, $lat, $lng);

            if ($raio && $distancia > $raio) continue;
        }

        $resultado[] = [
            'id'        => $c->ID,
            'nome'      => $c->post_title,
            'tipo'      => $tipo_com,
            'latitude'  => $lat,
            'longitude' => $lng,
            'endereco'  => get_post_meta($c->ID, 'endereco', true),
            'foto'      => $foto,
            'contatos'  => get_post_meta($c->ID, 'contatos', true),
            'parent_paroquia' => (int) get_post_meta($c->ID, 'parent_paroquia', true),
            'eventos'   => $lista_eventos,
            'distancia_km' => $distancia,
        ];
    }

    if ($proximidade && $user_lat && $user_lng) {
        usort($resultado, function($a, $b) {
            return $a['distancia_km'] <=> $b['distancia_km'];
        });
    }

    if ($limite) {
        $resultado = array_slice($resultado, 0, intval($limite));
    }

    set_transient($cache_key, $resultado, 60);

    return rest_ensure_response($resultado);
}


function cc_evento_ocorre_no_periodo($evento_id, $periodo = 'hoje', $data_param = '') {
    $data_base = cc_normalizar_data_filtro($periodo, $data_param);
    if (!$data_base) {
        $data_base = new DateTimeImmutable('today');
    }

    $frequencia = get_post_meta($evento_id, 'frequencia', true) ?: 'semanal';

    if ($periodo === 'semana') {
        $inicio = $data_base->modify('monday this week');
        for ($i = 0; $i < 7; $i++) {
            $dia = $inicio->modify("+{$i} day");
            if (cc_evento_ocorre_em_data($frequencia, $evento_id, $dia)) return true;
        }
        return false;
    }

    return cc_evento_ocorre_em_data($frequencia, $evento_id, $data_base);
}

function cc_normalizar_data_filtro($periodo, $data_param) {
    if ($periodo === 'data' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $data_param)) {
        return DateTimeImmutable::createFromFormat('Y-m-d', $data_param) ?: null;
    }

    return new DateTimeImmutable('today');
}

function cc_evento_ocorre_em_data($frequencia, $evento_id, DateTimeImmutable $data) {
    $dia_semana = (int) get_post_meta($evento_id, 'dia_semana', true);
    $dia_mes = (int) get_post_meta($evento_id, 'dia_mes', true);
    $numero_semana = (int) get_post_meta($evento_id, 'numero_semana', true);
    $mes = (int) get_post_meta($evento_id, 'mes', true);

    if ($frequencia === 'mensal') {
        return $dia_mes > 0 && (int) $data->format('j') === $dia_mes;
    }

    if ($frequencia === 'numero_semana') {
        if ($dia_semana < 0 || $dia_semana > 6 || $numero_semana < 1 || $numero_semana > 5) return false;
        if ((int) $data->format('w') !== $dia_semana) return false;

        $ordem = intdiv(((int) $data->format('j')) - 1, 7) + 1;
        return $ordem === $numero_semana;
    }

    if ($frequencia === 'anual') {
        return $dia_mes > 0 && $mes > 0
            && (int) $data->format('j') === $dia_mes
            && (int) $data->format('n') === $mes;
    }

    return $dia_semana >= 0 && $dia_semana <= 6 && (int) $data->format('w') === $dia_semana;
}

function cc_calcular_distancia($lat1, $lon1, $lat2, $lon2) {

    $terra = 6371;

    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);

    $a = sin($dLat/2) * sin($dLat/2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($dLon/2) * sin($dLon/2);

    $c = 2 * atan2(sqrt($a), sqrt(1-$a));

    return $terra * $c;
}
