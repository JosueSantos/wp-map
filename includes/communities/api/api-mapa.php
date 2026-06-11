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


function cc_mapa_comunidade_publicada_existe($comunidade_id) {
    $comunidade_id = (int) $comunidade_id;
    if ($comunidade_id <= 0) return false;

    $post = get_post($comunidade_id);
    return $post && $post->post_type === 'comunidade' && $post->post_status === 'publish';
}

function cc_mapa_tipo_evento_corresponde($slug, $categoria) {
    $slug = sanitize_title((string) $slug);
    $categoria = sanitize_key((string) $categoria);

    if ($categoria === 'missa') return strpos($slug, 'missa') !== false;
    if ($categoria === 'confissao') return strpos($slug, 'conf') !== false;
    if ($categoria === 'adoracao_santissimo') return strpos($slug, 'ador') !== false || strpos($slug, 'santissimo') !== false;
    if ($categoria === 'acao_caritativa') return $slug === 'acao-social';

    return false;
}

function cc_mapa_evento_tem_comunidade_publicada($evento_id) {
    return cc_mapa_comunidade_publicada_existe((int) get_post_meta((int) $evento_id, 'comunidade_id', true));
}

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

    $eventos_vinculados = array_values(array_filter(array_map('intval', $eventos), 'cc_mapa_evento_tem_comunidade_publicada'));
    $tipo_evento_slugs_com_eventos = [];

    foreach ($eventos_vinculados as $evento_id) {
        $slugs_evento = wp_get_post_terms($evento_id, 'tipo_evento', ['fields' => 'slugs']);
        foreach ((array) $slugs_evento as $slug_evento) {
            $slug_evento = sanitize_title((string) $slug_evento);
            if ($slug_evento !== '') {
                $tipo_evento_slugs_com_eventos[$slug_evento] = true;
            }
        }
    }

    foreach ($eventos_vinculados as $evento_id) {
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

    usort($lista_tipos_comunidade, function ($a, $b) {
        $prioridade = [
            'capela' => 0,
            'paroquia' => 1,
        ];

        $pa = $prioridade[$a['slug']] ?? 99;
        $pb = $prioridade[$b['slug']] ?? 99;

        if ($pa !== $pb) {
            return $pa <=> $pb;
        }

        return strcasecmp($a['nome'], $b['nome']);
    });

    $lista_tipos_evento = [];
    foreach ($tipos_evento as $termo) {
        if (empty($tipo_evento_slugs_com_eventos[$termo->slug])) {
            continue;
        }

        $lista_tipos_evento[] = [
            'slug' => $termo->slug,
            'nome' => $termo->name,
        ];
    }

    $lista_tags = [];
    $lista_tags_lookup = [];
    foreach ($tags_taxonomia as $termo) {
        $tipo_evento_ids = get_term_meta($termo->term_id, 'exclusive_tipo_evento_ids', true);
        if (!is_array($tipo_evento_ids)) {
            $tipo_evento_ids = array_filter(array_map('intval', explode(',', (string) $tipo_evento_ids)));
        }

        $tipo_evento_slugs = [];
        foreach ($tipo_evento_ids as $tipo_evento_id) {
            $tipo_evento = get_term((int) $tipo_evento_id, 'tipo_evento');
            if ($tipo_evento && !is_wp_error($tipo_evento)) {
                $tipo_evento_slugs[] = $tipo_evento->slug;
            }
        }

        if (empty($tipo_evento_slugs)) {
            $tipo_evento_slugs = [''];
        }

        foreach (array_unique($tipo_evento_slugs) as $tipo_evento_slug) {
            $chave = $termo->slug . '|' . $tipo_evento_slug;
            if (isset($lista_tags_lookup[$chave])) {
                continue;
            }

            $lista_tags[] = [
                'slug' => $termo->slug,
                'nome' => $termo->name,
                'tipo_evento_slug' => $tipo_evento_slug,
            ];
            $lista_tags_lookup[$chave] = true;
        }
    }

    foreach ($tags_meta as $tag) {
        $slug = sanitize_title($tag);
        $chave = $slug . '|';
        if (!isset($lista_tags_lookup[$chave])) {
            $lista_tags[] = [
                'slug' => $slug,
                'nome' => $tag,
                'tipo_evento_slug' => '',
            ];
            $lista_tags_lookup[$chave] = true;
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
        'tags' => $lista_tags,
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
    $tag_especial_obra_caritativa = sanitize_key($tag) === 'com_alguma_obra_caritativa';
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
        $todas_atividades = [];

        foreach ($eventos as $e) {

            $dia_semana = get_post_meta($e->ID, 'dia_semana', true);
            $dias_semana = cc_evento_get_dias_semana($e->ID);
            $horario    = get_post_meta($e->ID, 'horario', true);
            $descricao  = get_post_meta($e->ID, 'descricao', true);
            $observacao = get_post_meta($e->ID, 'observacao', true);

            $tipo_evt_slugs = wp_get_post_terms($e->ID, 'tipo_evento', ['fields'=>'slugs']);
            $tipo_evt_slugs = is_array($tipo_evt_slugs) ? $tipo_evt_slugs : [];
            $tipo_evt = $tipo_evt_slugs[0] ?? '';

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

            $evento_item = [
                'id'        => $e->ID,
                'titulo'    => $e->post_title,
                'titulo_base' => get_post_meta($e->ID, 'titulo_base', true) ?: $e->post_title,
                'tipo'      => $tipo_evt,
                'frequencia'=> get_post_meta($e->ID, 'frequencia', true) ?: 'semanal',
                'dia'       => $dia_semana,
                'dias'      => $dias_semana,
                'dia_mes'   => get_post_meta($e->ID, 'dia_mes', true),
                'numero_semana' => get_post_meta($e->ID, 'numero_semana', true),
                'mes'       => get_post_meta($e->ID, 'mes', true),
                'horario'   => $horario,
                'descricao' => $descricao,
                'observacao'=> $observacao,
                'tags'      => array_values(array_unique($tags_evento)),
            ];

            $todas_atividades[] = $evento_item;

            // FILTROS
            if ($periodo !== '' && !cc_evento_ocorre_no_periodo($e->ID, $periodo, $data_param)) continue;
            if ($tipo_evento && $tipo_evt !== $tipo_evento) continue;
            if ($tag_especial_obra_caritativa) {
                $tem_acao_social = false;
                foreach ($tipo_evt_slugs as $tipo_evt_slug) {
                    if (cc_mapa_tipo_evento_corresponde($tipo_evt_slug, 'acao_social')) {
                        $tem_acao_social = true;
                        break;
                    }
                }
                if (!$tem_acao_social) continue;
            } elseif ($tag && !in_array(sanitize_title($tag), $tags_evento, true)) {
                continue;
            }

            $lista_eventos[] = $evento_item;
        }

        if (!empty($lista_eventos)) {
            usort($lista_eventos, 'cc_comparar_eventos_por_data');
        }
        if (!empty($todas_atividades)) {
            usort($todas_atividades, 'cc_comparar_eventos_por_data');
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
            'todas_atividades' => $todas_atividades,
            'distancia_km' => $distancia,
            'permalink' => get_permalink($c->ID),
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

function cc_comparar_eventos_por_data($evento_a, $evento_b) {
    $agora = new DateTimeImmutable('now');
    $data_a = cc_evento_proxima_ocorrencia($evento_a, $agora);
    $data_b = cc_evento_proxima_ocorrencia($evento_b, $agora);

    return $data_a <=> $data_b;
}

function cc_evento_proxima_ocorrencia($evento, DateTimeImmutable $base) {
    $frequencia = sanitize_key((string) ($evento['frequencia'] ?? 'semanal')) ?: 'semanal';
    $hora = cc_evento_normalizar_horario($evento['horario'] ?? '00:00');

    if ($frequencia === 'missa_dominical') {
        return cc_evento_proxima_data_semanal($base, [0], $hora);
    }

    if ($frequencia === 'mensal') {
        return cc_evento_proxima_data_mensal($base, (int) ($evento['dia_mes'] ?? 1), $hora);
    }

    if ($frequencia === 'numero_semana') {
        return cc_evento_proxima_data_numero_semana($base, (int) ($evento['numero_semana'] ?? 1), (int) ($evento['dia'] ?? 0), $hora);
    }

    if ($frequencia === 'anual') {
        return cc_evento_proxima_data_anual($base, (int) ($evento['dia_mes'] ?? 1), (int) ($evento['mes'] ?? 1), $hora);
    }

    $dias = isset($evento['dias']) && is_array($evento['dias']) ? array_values($evento['dias']) : [];
    if (empty($dias) && isset($evento['dia'])) {
        $dias = [(int) $evento['dia']];
    }

    return cc_evento_proxima_data_semanal($base, $dias, $hora);
}

function cc_evento_normalizar_horario($horario) {
    $hora = trim((string) $horario);
    if (!preg_match('/^(\d{1,2}):(\d{2})/', $hora, $match)) {
        return '00:00';
    }

    $h = max(0, min(23, (int) $match[1]));
    $m = max(0, min(59, (int) $match[2]));
    return sprintf('%02d:%02d', $h, $m);
}

function cc_evento_compor_data_hora(DateTimeImmutable $data, $hora) {
    return DateTimeImmutable::createFromFormat('Y-m-d H:i', $data->format('Y-m-d') . ' ' . $hora) ?: $data;
}

function cc_evento_proxima_data_semanal(DateTimeImmutable $base, array $dias, $hora) {
    $dias_validos = array_values(array_unique(array_filter(array_map('intval', $dias), function($dia) {
        return $dia >= 0 && $dia <= 6;
    })));

    if (empty($dias_validos)) {
        return cc_evento_compor_data_hora($base, $hora);
    }

    sort($dias_validos);
    $hoje_dia = (int) $base->format('w');
    $candidatos = [];

    foreach ($dias_validos as $dia) {
        $delta = ($dia - $hoje_dia + 7) % 7;
        $data = $base->modify("+{$delta} day");
        $data_hora = cc_evento_compor_data_hora($data, $hora);

        if ($data_hora < $base) {
            $data_hora = $data_hora->modify('+7 day');
        }

        $candidatos[] = $data_hora;
    }

    usort($candidatos, function($a, $b) {
        return $a <=> $b;
    });

    return $candidatos[0];
}

function cc_evento_proxima_data_mensal(DateTimeImmutable $base, $dia_mes, $hora) {
    $dia = max(1, min(31, (int) $dia_mes));
    $ano = (int) $base->format('Y');
    $mes = (int) $base->format('n');

    for ($i = 0; $i < 24; $i++) {
        $mes_teste = $mes + $i;
        $ano_teste = $ano + (int) floor(($mes_teste - 1) / 12);
        $mes_normalizado = (($mes_teste - 1) % 12) + 1;
        $ultimo_dia = (int) (new DateTimeImmutable("{$ano_teste}-{$mes_normalizado}-01"))->format('t');
        $dia_normalizado = min($dia, $ultimo_dia);
        $data = new DateTimeImmutable(sprintf('%04d-%02d-%02d', $ano_teste, $mes_normalizado, $dia_normalizado));
        $data_hora = cc_evento_compor_data_hora($data, $hora);

        if ($data_hora >= $base) {
            return $data_hora;
        }
    }

    return cc_evento_compor_data_hora($base, $hora);
}

function cc_evento_proxima_data_numero_semana(DateTimeImmutable $base, $numero_semana, $dia_semana, $hora) {
    $numero = max(1, min(5, (int) $numero_semana));
    $dia = max(0, min(6, (int) $dia_semana));
    $ano = (int) $base->format('Y');
    $mes = (int) $base->format('n');

    for ($i = 0; $i < 24; $i++) {
        $mes_teste = $mes + $i;
        $ano_teste = $ano + (int) floor(($mes_teste - 1) / 12);
        $mes_normalizado = (($mes_teste - 1) % 12) + 1;
        $data = cc_evento_data_numero_semana($ano_teste, $mes_normalizado, $numero, $dia);

        if (!$data) {
            continue;
        }

        $data_hora = cc_evento_compor_data_hora($data, $hora);
        if ($data_hora >= $base) {
            return $data_hora;
        }
    }

    return cc_evento_compor_data_hora($base, $hora);
}

function cc_evento_data_numero_semana($ano, $mes, $numero_semana, $dia_semana) {
    $inicio_mes = new DateTimeImmutable(sprintf('%04d-%02d-01', $ano, $mes));
    $w_inicio = (int) $inicio_mes->format('w');
    $delta = ($dia_semana - $w_inicio + 7) % 7;
    $dia = 1 + $delta + (($numero_semana - 1) * 7);
    $ultimo_dia = (int) $inicio_mes->format('t');

    if ($dia > $ultimo_dia) {
        return null;
    }

    return new DateTimeImmutable(sprintf('%04d-%02d-%02d', $ano, $mes, $dia));
}

function cc_evento_proxima_data_anual(DateTimeImmutable $base, $dia_mes, $mes, $hora) {
    $dia = max(1, min(31, (int) $dia_mes));
    $mes_num = max(1, min(12, (int) $mes));
    $ano = (int) $base->format('Y');

    for ($i = 0; $i < 5; $i++) {
        $ano_teste = $ano + $i;
        $ultimo_dia = (int) (new DateTimeImmutable(sprintf('%04d-%02d-01', $ano_teste, $mes_num)))->format('t');
        $dia_normalizado = min($dia, $ultimo_dia);
        $data = new DateTimeImmutable(sprintf('%04d-%02d-%02d', $ano_teste, $mes_num, $dia_normalizado));
        $data_hora = cc_evento_compor_data_hora($data, $hora);

        if ($data_hora >= $base) {
            return $data_hora;
        }
    }

    return cc_evento_compor_data_hora($base, $hora);
}



function cc_evento_get_dias_semana($evento_id) {
    $dias = get_post_meta($evento_id, 'dias_semana', true);

    if (is_array($dias)) {
        $normalizados = array_values(array_unique(array_filter(array_map('intval', $dias), function($dia) {
            return $dia >= 0 && $dia <= 6;
        })));

        sort($normalizados);
        return $normalizados;
    }

    $dia_unico = get_post_meta($evento_id, 'dia_semana', true);
    if ($dia_unico === '' || $dia_unico === null) {
        return [];
    }

    $dia_unico = (int) $dia_unico;
    return ($dia_unico >= 0 && $dia_unico <= 6) ? [$dia_unico] : [];
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
    $frequencia = sanitize_key((string) $frequencia) ?: 'semanal';
    $dias_semana = cc_evento_get_dias_semana($evento_id);
    $dia_semana = !empty($dias_semana) ? (int) $dias_semana[0] : -1;
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

    if ($frequencia === 'missa_dominical') {
        return (int) $data->format('w') === 0;
    }

    if (empty($dias_semana)) {
        return false;
    }

    return in_array((int) $data->format('w'), $dias_semana, true);
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
