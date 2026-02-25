<?php

// Exibir Mapa
function cc_mapa_shortcode($atts) {

    $atts = shortcode_atts([
        'dominio' => '',
        'url_cadastro' => '/cadastro-comunidade/'
    ], $atts);

    $dominio = esc_url_raw($atts['dominio']);
    $url_cadastro = esc_url_raw($atts['url_cadastro']);

    wp_enqueue_style(
        'leaflet-css',
        'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css'
    );

    // Tailwind (fallback para temas que não carregam utilitários)
    wp_enqueue_script('tailwind-cdn', 'https://cdn.tailwindcss.com', [], null);

    // CSS de fallback para garantir layout e altura do mapa sem Tailwind
    wp_enqueue_style(
        'cc-mapa-css',
        plugin_dir_url(__FILE__) . '../../assets/css/mapa.css',
        [],
        '1.1'
    );


    wp_enqueue_style(
        'bootstrap-icons',
        'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css',
        [],
        '1.11.3'
    );

    wp_enqueue_script(
        'leaflet-js',
        'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js',
        [],
        null,
        true
    );

    wp_enqueue_script(
        'cc-mapa',
        plugin_dir_url(__FILE__) . '../../assets/js/mapa.js',
        ['leaflet-js'],
        '1.7',
        true
    );

    ob_start();

    ?>
    <section id="mapa-igrejas" data-dominio="<?php echo esc_attr($dominio); ?>" data-url-cadastro="<?php echo esc_attr($url_cadastro); ?>" class="cc-mapa-fullwidth">
        <div class="cc-mapa-layout">
            <aside id="mapa-sidebar" class="cc-sidebar">
                <div class="cc-sidebar-header">
                    <h2>Filtros</h2>
                    <button id="mapa-fechar-filtros" type="button" class="cc-mobile-only">✕</button>
                </div>

                <div class="cc-sidebar-body">
                    <p class="cc-filtro-texto">Selecione os filtros para refinar comunidades e eventos.</p>

                    <form id="mapa-filtros" class="cc-filtros-form">
                        <label>
                            <span>Dia</span>
                            <select id="filtro-dia" name="dia"></select>
                        </label>

                        <label>
                            <span>Tipo de evento</span>
                            <select id="filtro-tipo-evento" name="tipo_evento"></select>
                        </label>

                        <label>
                            <span>Tipo de comunidade</span>
                            <select id="filtro-tipo-comunidade" name="tipo_comunidade"></select>
                        </label>

                        <label>
                            <span>Tag</span>
                            <select id="filtro-tag" name="tag"></select>
                        </label>
                    </form>

                    <div class="cc-filtros-acoes">
                        <button id="mapa-aplicar-filtros" type="button">Aplicar filtros</button>
                        <button id="mapa-limpar-filtros" type="button">Limpar</button>
                    </div>

                    <aside id="mapa-detalhes" class="cc-detalhes-card">
                        <h3>Comunidade selecionada</h3>
                        <p>Toque em um pino para ver detalhes e eventos.</p>
                    </aside>
                </div>
            </aside>

            <div class="cc-mapa-main">
                <div class="cc-mapa-topbar">
                    <button id="mapa-toggle-filtros" type="button" class="cc-mobile-only">Filtros ☰</button>
                    <label class="cc-busca-wrap" for="filtro-busca">
                        <span>Pesquisar comunidade</span>
                        <input id="filtro-busca" list="mapa-comunidades-list" type="search" placeholder="Digite o nome da comunidade" autocomplete="off" />
                        <datalist id="mapa-comunidades-list"></datalist>
                    </label>
                </div>

                <div id="mapa-canvas"></div>
            </div>
        </div>
    </section>
    <?php

    return ob_get_clean();
}

add_shortcode('mapa_igrejas', 'cc_mapa_shortcode');
