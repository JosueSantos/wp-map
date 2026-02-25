<?php

// Exibir Mapa
function cc_mapa_shortcode($atts) {

    $atts = shortcode_atts([
        'dominio' => ''
    ], $atts);

    $dominio = esc_url_raw($atts['dominio']);

    wp_enqueue_style(
        'leaflet-css',
        'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css'
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
        '1.1',
        true
    );

    ob_start();

    ?>
    <section id="mapa-igrejas"
        data-dominio="<?php echo esc_attr($dominio); ?>"
        class="w-full rounded-2xl border border-slate-200 bg-white p-4 shadow-sm lg:p-6">

        <div class="mb-4 rounded-xl border border-slate-100 bg-slate-50 p-4">
            <div class="mb-3 flex items-center justify-between gap-3">
                <h2 class="text-lg font-semibold text-slate-800">Busque por comunidades e eventos</h2>
                <button id="mapa-limpar-filtros" type="button" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm text-slate-700 transition hover:bg-slate-100">Limpar</button>
            </div>

            <form id="mapa-filtros" class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
                <label class="flex flex-col gap-1 text-sm text-slate-700">
                    <span>Dia</span>
                    <select id="filtro-dia" name="dia" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-slate-800 focus:border-sky-400 focus:outline-none focus:ring-2 focus:ring-sky-100"></select>
                </label>

                <label class="flex flex-col gap-1 text-sm text-slate-700">
                    <span>Tipo de evento</span>
                    <select id="filtro-tipo-evento" name="tipo_evento" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-slate-800 focus:border-sky-400 focus:outline-none focus:ring-2 focus:ring-sky-100"></select>
                </label>

                <label class="flex flex-col gap-1 text-sm text-slate-700">
                    <span>Tipo de comunidade</span>
                    <select id="filtro-tipo-comunidade" name="tipo_comunidade" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-slate-800 focus:border-sky-400 focus:outline-none focus:ring-2 focus:ring-sky-100"></select>
                </label>

                <label class="flex flex-col gap-1 text-sm text-slate-700">
                    <span>Tag</span>
                    <select id="filtro-tag" name="tag" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-slate-800 focus:border-sky-400 focus:outline-none focus:ring-2 focus:ring-sky-100"></select>
                </label>

                <label class="flex flex-col gap-1 text-sm text-slate-700">
                    <span>Raio (km)</span>
                    <select id="filtro-raio" name="raio" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-slate-800 focus:border-sky-400 focus:outline-none focus:ring-2 focus:ring-sky-100">
                        <option value="">Sem limite</option>
                        <option value="2">Até 2 km</option>
                        <option value="5">Até 5 km</option>
                        <option value="10">Até 10 km</option>
                        <option value="20">Até 20 km</option>
                        <option value="50">Até 50 km</option>
                    </select>
                </label>

                <label class="flex items-end gap-2 rounded-lg border border-slate-200 bg-white p-3 text-sm text-slate-700">
                    <input id="filtro-proximidade" name="proximidade" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-sky-600 focus:ring-sky-300" />
                    <span>Ordenar por proximidade</span>
                </label>
            </form>
        </div>

        <div class="grid grid-cols-1 gap-4 xl:grid-cols-[minmax(0,2fr)_minmax(260px,1fr)]">
            <div id="mapa-canvas" class="h-[500px] w-full overflow-hidden rounded-xl border border-slate-200"></div>

            <aside id="mapa-detalhes" class="h-[500px] overflow-y-auto rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700">
                <h3 class="mb-2 text-base font-semibold text-slate-800">Detalhes da comunidade</h3>
                <p>Selecione um pino no mapa para visualizar informações da comunidade e dos eventos.</p>
            </aside>
        </div>
    </section>
    <?php

    return ob_get_clean();
}

add_shortcode('mapa_igrejas', 'cc_mapa_shortcode');
