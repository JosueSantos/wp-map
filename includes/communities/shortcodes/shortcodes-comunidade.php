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
    wp_enqueue_style(
        'bootstrap-icons',
        'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css',
        [],
        '1.11.3'
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
        $tipos = wp_get_post_terms($evento_id, 'tipo_evento', ['fields' => 'slugs']);
        foreach ((array) $tipos as $slug) {
            $slug_normalizado = sanitize_title($slug);
            if (strpos($slug_normalizado, 'missa') !== false) $contadores['missas']++;
            if ($comunidade_id && strpos($slug_normalizado, 'conf') !== false) $contadores['confissoes'][$comunidade_id] = true;
            if ($comunidade_id && (strpos($slug_normalizado, 'ador') !== false || strpos($slug_normalizado, 'santissimo') !== false)) $contadores['adoracao_santissimo'][$comunidade_id] = true;
            if ($comunidade_id && (strpos($slug_normalizado, 'carit') !== false || strpos($slug_normalizado, 'acao') !== false)) $contadores['obras_caritativas'][$comunidade_id] = true;
        }
    }

    $cards = [
        ['titulo' => 'Locais cadastrados', 'valor' => $total_locais, 'icone' => 'bi-geo-alt-fill'],
        ['titulo' => 'Horários de missas', 'valor' => $contadores['missas'], 'icone' => 'bi-brightness-high-fill'],
        ['titulo' => 'Locais de confissão', 'valor' => count($contadores['confissoes']), 'icone' => 'bi-person-fill'],
        ['titulo' => 'Locais de adoração', 'valor' => count($contadores['adoracao_santissimo']), 'icone' => 'bi-stars'],
        ['titulo' => 'Locais com obras caritativas', 'valor' => count($contadores['obras_caritativas']), 'icone' => 'bi-heart-fill'],
    ];

    ob_start();
    ?>
    <div class="cc-resumo-cadastros-grid">
        <?php foreach ($cards as $card) : ?>
            <article class="cc-resumo-cadastro-card">
                <i class="cc-resumo-cadastro-icone bi <?php echo esc_attr($card['icone']); ?>" aria-hidden="true"></i>
                <strong class="cc-resumo-cadastro-valor"><?php echo esc_html(number_format_i18n((int) $card['valor'])); ?></strong>
                <span class="cc-resumo-cadastro-titulo"><?php echo esc_html($card['titulo']); ?></span>
            </article>
        <?php endforeach; ?>
    </div>
    <style>
        .cc-resumo-cadastros-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(170px,1fr)); gap:12px; }
        .cc-resumo-cadastro-card { border:1px solid #e2e8f0; border-radius:12px; padding:16px 14px; text-align:center; background:#fff; transition:box-shadow .2s ease, transform .2s ease; }
        .cc-resumo-cadastro-card:hover { box-shadow:0 12px 28px rgba(15,23,42,.14); transform:translateY(-1px); }
        .cc-resumo-cadastro-icone { display:block; margin-bottom:8px; color:#0b1f52; font-size:2.15rem; line-height:1; }
        .cc-resumo-cadastro-valor { display:block; font-size:2rem; line-height:1.1; color:#0f172a; }
        .cc-resumo-cadastro-titulo { display:block; margin-top:6px; color:#0b1f52; font-size:.92rem; font-weight:700; }
    </style>
    <?php
    return ob_get_clean();
}

add_shortcode('cc_resumo_cadastros', 'cc_resumo_cadastros_shortcode');
