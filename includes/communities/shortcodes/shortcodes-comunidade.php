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

function cc_resumo_cadastros_shortcode() {
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
        'confissoes' => 0,
        'adoracao_santissimo' => 0,
        'obras_caritativas' => 0,
    ];

    foreach ($eventos_ids as $evento_id) {
        $tipos = wp_get_post_terms($evento_id, 'tipo_evento', ['fields' => 'slugs']);
        foreach ((array) $tipos as $slug) {
            $slug_normalizado = sanitize_title($slug);
            if (strpos($slug_normalizado, 'missa') !== false) $contadores['missas']++;
            if (strpos($slug_normalizado, 'conf') !== false) $contadores['confissoes']++;
            if (strpos($slug_normalizado, 'ador') !== false || strpos($slug_normalizado, 'santissimo') !== false) $contadores['adoracao_santissimo']++;
            if (strpos($slug_normalizado, 'carit') !== false || strpos($slug_normalizado, 'acao') !== false) $contadores['obras_caritativas']++;
        }
    }

    $cards = [
        ['titulo' => 'Locais cadastrados', 'valor' => $total_locais],
        ['titulo' => 'Missas', 'valor' => $contadores['missas']],
        ['titulo' => 'Confissões', 'valor' => $contadores['confissoes']],
        ['titulo' => 'Adoração ao Santíssimo', 'valor' => $contadores['adoracao_santissimo']],
        ['titulo' => 'Obras caritativas', 'valor' => $contadores['obras_caritativas']],
    ];

    ob_start();
    ?>
    <div class="cc-resumo-cadastros-grid">
        <?php foreach ($cards as $card) : ?>
            <article class="cc-resumo-cadastro-card">
                <strong class="cc-resumo-cadastro-valor"><?php echo esc_html(number_format_i18n((int) $card['valor'])); ?></strong>
                <span class="cc-resumo-cadastro-titulo"><?php echo esc_html($card['titulo']); ?></span>
            </article>
        <?php endforeach; ?>
    </div>
    <style>
        .cc-resumo-cadastros-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(170px,1fr)); gap:12px; }
        .cc-resumo-cadastro-card { border:1px solid #e2e8f0; border-radius:12px; padding:14px; text-align:center; background:#fff; }
        .cc-resumo-cadastro-valor { display:block; font-size:2rem; line-height:1.1; color:#0f172a; }
        .cc-resumo-cadastro-titulo { display:block; margin-top:6px; color:#334155; font-size:.92rem; }
    </style>
    <?php
    return ob_get_clean();
}

add_shortcode('cc_resumo_cadastros', 'cc_resumo_cadastros_shortcode');
