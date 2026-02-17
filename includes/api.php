<?php

add_action('rest_api_init', function () {

    register_rest_route('mapa/v1', '/comunidades', [
        'methods'  => 'GET',
        'callback' => 'cc_api_mapa_comunidades',
        'permission_callback' => '__return_true'
    ]);

});

function cc_api_mapa_comunidades($request) {

    $dia            = $request->get_param('dia');
    $tipo_evento    = $request->get_param('tipo_evento');
    $tipo_comunidade = $request->get_param('tipo_comunidade');
    $lat   = $request->get_param('lat');
    $lng   = $request->get_param('lng');
    $raio  = $request->get_param('raio');
    $tag   = $request->get_param('tag');
    $limite = $request->get_param('limite');
    $proximidade = $request->get_param('proximidade');

    // Buscar comunidades
    $args = [
        'post_type'      => 'comunidade',
        'posts_per_page' => -1,
        'post_status'    => 'publish'
    ];

    if ($tipo_comunidade) {
        $args['tax_query'] = [[
            'taxonomy' => 'tipo_comunidade',
            'field'    => 'slug',
            'terms'    => $tipo_comunidade
        ]];
    }

    $comunidades = get_posts($args);

    $resultado = [];

    foreach ($comunidades as $c) {

        $lat = get_post_meta($c->ID, 'latitude', true);
        $lng = get_post_meta($c->ID, 'longitude', true);

        if (!$lat || !$lng) continue;

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
            if ($dia === 'hoje') {
                $dia = date('w'); // 0 domingo - 6 sábado
            }

            if ($dia !== null && $dia_semana !== $dia) continue;

            if ($tipo_evento && $tipo_evt !== $tipo_evento) continue;

            $tags_evento = get_post_meta($e->ID, 'tags', true);
            $tags_evento = is_array($tags_evento) ? $tags_evento : explode(',', $tags_evento);

            if ($tag && !in_array($tag, $tags_evento)) continue;


            $lista_eventos[] = [
                'id'        => $e->ID,
                'titulo'    => $e->post_title,
                'tipo'      => $tipo_evt,
                'dia'       => $dia_semana,
                'horario'   => $horario,
                'descricao' => $descricao,
                'observacao'=> $observacao
            ];
        }

        if (empty($lista_eventos)) continue;

        $foto = get_the_post_thumbnail_url($c->ID, 'medium');

        $tipo_com = wp_get_post_terms($c->ID, 'tipo_comunidade', ['fields'=>'slugs']);
        $tipo_com = $tipo_com[0] ?? '';

        $distancia = null;

        if ($lat && $lng) {
            $distancia = cc_calcular_distancia($lat, $lng, $c->latitude, $c->longitude);

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
            'eventos'   => $lista_eventos,
            'distancia_km' => $distancia,
        ];
    }

    if ($proximidade && $lat && $lng) {
        usort($resultado, function($a, $b) {
            return $a['distancia_km'] <=> $b['distancia_km'];
        });
    }

    if ($limite) {
        $resultado = array_slice($resultado, 0, intval($limite));
    }

    return rest_ensure_response($resultado);
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
