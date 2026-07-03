<?php

// Listar todas as comunidades
function cc_lista_comunidades() {

    $q = new WP_Query([
        'post_type' => 'comunidade',
        'posts_per_page' => -1
    ]);

    ob_start();

    while ($q->have_posts()) {
        $q->the_post();

        echo cc_render_template('shortcodes/item-comunidade.php', [
            'titulo' => get_the_title(),
            'resumo' => get_the_excerpt(),
        ]);
    }

    wp_reset_postdata();

    return ob_get_clean();
}

add_shortcode('cc_comunidades', 'cc_lista_comunidades');

// Listar Eventos de uma Comunidade
// Parametro Id da Comunidade
function cc_eventos_comunidade($atts) {

    $atts = shortcode_atts(['id' => 0], $atts);

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

        echo cc_render_template('shortcodes/item-evento.php', [
            'titulo' => get_the_title(),
            'dia' => get_post_meta(get_the_ID(), 'dia_semana', true),
            'hora' => get_post_meta(get_the_ID(), 'horario', true),
        ]);
    }

    wp_reset_postdata();

    return ob_get_clean();
}

add_shortcode('cc_eventos', 'cc_eventos_comunidade');


if (!function_exists('cc_resumo_comunidade_publicada_existe')) {
    function cc_resumo_comunidade_publicada_existe($comunidade_id) {
        $comunidade_id = (int) $comunidade_id;
        if ($comunidade_id <= 0) return false;

        $post = get_post($comunidade_id);
        return $post && $post->post_type === 'comunidade' && $post->post_status === 'publish';
    }
}

if (!function_exists('cc_resumo_tipo_evento_corresponde')) {
    function cc_resumo_tipo_evento_corresponde($slug, $categoria) {
        $slug = sanitize_title((string) $slug);
        $categoria = sanitize_key((string) $categoria);

        if ($categoria === 'confissao') return strpos($slug, 'conf') !== false;
        if ($categoria === 'adoracao_santissimo') return strpos($slug, 'ador') !== false || strpos($slug, 'santissimo') !== false;
        if ($categoria === 'acao_caritativa') return $slug === 'acao-social';

        return false;
    }
}

function cc_resumo_cadastros_shortcode($atts = []) {
    $atts = shortcode_atts([
        'exceto' => '',
    ], $atts, 'cc_resumo_cadastros');

    $exceto = array_map(
        'sanitize_key',
        array_filter(array_map('trim', explode(',', $atts['exceto'])))
    );
    
    $total_locais = wp_count_posts('comunidade');
    $total_locais = isset($total_locais->publish) ? (int) $total_locais->publish : 0;

    $eventos_ids = get_posts([
        'post_type' => 'evento',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'fields' => 'ids',
    ]);

    $contadores = [
        'missas' => 0,
        'confissoes' => [],
        'adoracao_santissimo' => [],
        'obras_caritativas' => [],
    ];

    foreach ($eventos_ids as $evento_id) {
        $comunidade_id = (int) get_post_meta($evento_id, 'comunidade_id', true);
        $tem_comunidade_publicada = cc_resumo_comunidade_publicada_existe($comunidade_id);
        if (!$tem_comunidade_publicada) continue;

        $tipos = wp_get_post_terms($evento_id, 'tipo_evento', ['fields' => 'slugs']);
        $evento_contabilizado_como_missa = false;

        foreach ((array) $tipos as $slug) {
            $slug_normalizado = sanitize_title($slug);
            if (!$evento_contabilizado_como_missa && strpos($slug_normalizado, 'missa') !== false) {
                $contadores['missas']++;
                $evento_contabilizado_como_missa = true;
            }
            if (cc_resumo_tipo_evento_corresponde($slug_normalizado, 'confissao')) $contadores['confissoes'][$comunidade_id] = true;
            if (cc_resumo_tipo_evento_corresponde($slug_normalizado, 'adoracao_santissimo')) $contadores['adoracao_santissimo'][$comunidade_id] = true;
            if (cc_resumo_tipo_evento_corresponde($slug_normalizado, 'acao_caritativa')) $contadores['obras_caritativas'][$comunidade_id] = true;
        }
    }

    $cards = [
        'locais' => [
            'titulo' => 'Locais cadastrados',
            'valor' => $total_locais,
            'icone' => CC_URL . 'assets/img/resumo-cadastros/locais-cadastrados.svg',
        ],
        'missas' => [
            'titulo' => 'Horários de missas',
            'valor' => $contadores['missas'],
            'icone' => CC_URL . 'assets/img/resumo-cadastros/horarios-missas.svg',
        ],
        'confissoes' => [
            'titulo' => 'Locais de confissão',
            'valor' => count($contadores['confissoes']),
            'icone' => CC_URL . 'assets/img/resumo-cadastros/locais-confissao.svg',
        ],
        'adoracao_santissimo' => [
            'titulo' => 'Locais de adoração',
            'valor' => count($contadores['adoracao_santissimo']),
            'icone' => CC_URL . 'assets/img/resumo-cadastros/locais-adoracao.svg',
        ],
        'obras_caritativas' => [
            'titulo' => 'Locais com obras caritativas',
            'valor' => count($contadores['obras_caritativas']),
            'icone' => CC_URL . 'assets/img/resumo-cadastros/obras-caritativas.svg',
        ],
    ];

    foreach ($exceto as $slug) {
        unset($cards[$slug]);
    }

    ob_start();
    ?>
    <div class="cc-resumo-cadastros-grid">
        <?php foreach ($cards as $card) : ?>
            <article class="cc-resumo-cadastro-card">
                <img class="cc-resumo-cadastro-icone" src="<?php echo esc_url($card['icone']); ?>" alt="" aria-hidden="true" loading="lazy" decoding="async">
                <strong class="cc-resumo-cadastro-valor"><?php echo esc_html(number_format_i18n((int) $card['valor'])); ?></strong>
                <span class="cc-resumo-cadastro-titulo"><?php echo esc_html($card['titulo']); ?></span>
            </article>
        <?php endforeach; ?>
    </div>
    <style>
        .cc-resumo-cadastros-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(170px,1fr)); gap:12px; }
        .cc-resumo-cadastro-card { border:1px solid #e2e8f0; border-radius:12px; padding:16px 14px; text-align:center; background:#fff; transition:box-shadow .2s ease, transform .2s ease; }
        .cc-resumo-cadastro-card:hover { box-shadow:0 12px 28px rgba(15,23,42,.14); transform:translateY(-1px); }
        .cc-resumo-cadastro-icone { display:block; width:48px; height:48px; object-fit:contain; margin:0 auto 8px; }
        .cc-resumo-cadastro-valor { display:block; font-size:2rem; line-height:1.1; color:#0f172a; }
        .cc-resumo-cadastro-titulo { display:block; margin-top:6px; color:#0b1f52; font-size:.92rem; font-weight:700; }
    </style>
    <?php
    return ob_get_clean();
}

add_shortcode('cc_resumo_cadastros', 'cc_resumo_cadastros_shortcode');
