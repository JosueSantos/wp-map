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

    // Tailwind (fallback para temas que não carregam utilitários)
    wp_enqueue_script('tailwind-cdn', 'https://cdn.tailwindcss.com', [], null);

    // CSS de fallback para garantir layout e altura do mapa sem Tailwind
    wp_enqueue_style(
        'cc-mapa-css',
        plugin_dir_url(__FILE__) . '../../assets/css/mapa.css',
        [],
        '1.0'
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
        '1.3',
        true
    );

    ob_start();

    ?>
    <section id="mapa-igrejas"
        data-dominio="<?php echo esc_attr($dominio); ?>"
        class="relative isolate overflow-hidden rounded-2xl border border-slate-200 bg-slate-100 shadow-sm">

        <div id="mapa-canvas" class="h-[75vh] min-h-[520px] w-full"></div>

        <div class="pointer-events-none absolute inset-0 z-[400] flex items-start justify-between p-3 sm:p-4">
            <button id="mapa-toggle-filtros"
                type="button"
                class="pointer-events-auto inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white/95 px-3 py-2 text-sm font-medium text-slate-800 shadow lg:hidden">
                <span>Filtros</span>
                <span class="text-xs text-slate-500">☰</span>
            </button>

            <div class="pointer-events-auto hidden w-full max-w-xl px-2 lg:block">
                <label class="block">
                    <span class="sr-only">Pesquisar comunidade</span>
                    <div class="flex items-center gap-2 rounded-full border border-slate-300 bg-white/95 px-4 py-2 shadow">
                        <input id="filtro-busca" type="search" placeholder="Pesquisar comunidade no mapa" class="w-full bg-transparent text-sm text-slate-800 placeholder:text-slate-500 focus:outline-none" />
                        <span class="text-slate-500">🔍</span>
                    </div>
                </label>
            </div>
        </div>

        <aside id="mapa-sidebar"
            class="absolute left-0 top-0 z-[500] flex h-full w-[92%] max-w-[350px] -translate-x-full flex-col border-r border-slate-200 bg-white/95 shadow-2xl backdrop-blur transition-transform duration-300 lg:translate-x-0">

            <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3">
                <h2 class="text-xl font-semibold text-blue-700">Filtros</h2>
                <button id="mapa-fechar-filtros" type="button" class="rounded-md p-1 text-slate-500 hover:bg-slate-100 lg:hidden">✕</button>
            </div>

            <div class="overflow-y-auto px-4 py-4">
                <p class="mb-4 text-sm text-slate-600">Selecione os filtros para refinar comunidades e eventos no mapa.</p>

                <div class="mb-4 lg:hidden">
                    <label class="block">
                        <span class="mb-1 block text-sm font-medium text-slate-700">Busca rápida</span>
                        <div class="flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-3 py-2">
                            <input id="filtro-busca-mobile" type="search" placeholder="Nome da comunidade" class="w-full bg-transparent text-sm text-slate-800 placeholder:text-slate-500 focus:outline-none" />
                            <span class="text-slate-500">🔎</span>
                        </div>
                    </label>
                </div>

                <form id="mapa-filtros" class="space-y-3">
                    <label class="block text-sm text-slate-700">
                        <span class="mb-1 block font-medium">Dia</span>
                        <select id="filtro-dia" name="dia" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-slate-800 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100"></select>
                    </label>

                    <label class="block text-sm text-slate-700">
                        <span class="mb-1 block font-medium">Tipo de evento</span>
                        <select id="filtro-tipo-evento" name="tipo_evento" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-slate-800 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100"></select>
                    </label>

                    <label class="block text-sm text-slate-700">
                        <span class="mb-1 block font-medium">Tipo de comunidade</span>
                        <select id="filtro-tipo-comunidade" name="tipo_comunidade" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-slate-800 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100"></select>
                    </label>

                    <label class="block text-sm text-slate-700">
                        <span class="mb-1 block font-medium">Tag</span>
                        <select id="filtro-tag" name="tag" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-slate-800 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100"></select>
                    </label>

                    <label class="block text-sm text-slate-700">
                        <span class="mb-1 block font-medium">Raio (km)</span>
                        <select id="filtro-raio" name="raio" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-slate-800 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100">
                            <option value="">Sem limite</option>
                            <option value="2">Até 2 km</option>
                            <option value="5">Até 5 km</option>
                            <option value="10">Até 10 km</option>
                            <option value="20">Até 20 km</option>
                            <option value="50">Até 50 km</option>
                        </select>
                    </label>

                    <label class="flex items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700">
                        <input id="filtro-proximidade" name="proximidade" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-300" />
                        <span>Ordenar por proximidade</span>
                    </label>
                </form>

                <div class="mt-4 flex gap-2">
                    <button id="mapa-aplicar-filtros" type="button" class="flex-1 rounded-xl bg-blue-700 px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-800">Aplicar filtros</button>
                    <button id="mapa-limpar-filtros" type="button" class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">Limpar</button>
                </div>

                <aside id="mapa-detalhes" class="mt-5 rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700">
                    <h3 class="mb-1 text-sm font-semibold uppercase tracking-wide text-slate-700">Comunidade selecionada</h3>
                    <p>Toque em um pino para ver detalhes e eventos.</p>
                </aside>
            </div>
        </aside>
    </section>
    <?php

    return ob_get_clean();
}

add_shortcode('mapa_igrejas', 'cc_mapa_shortcode');
