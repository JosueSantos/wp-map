<?php

add_shortcode('mapa_form_comunidade', function () {

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
        'mapa-form',
        plugin_dir_url(__FILE__) . '../../assets/js/form.js',
        ['leaflet-js'],
        '1.1',
        true
    );

    wp_localize_script('mapa-form', 'MAPA_API', [
        'url'   => rest_url('mapa/v1/comunidade'),
        'nonce' => wp_create_nonce('wp_rest')
    ]);

    wp_enqueue_script('tailwind-cdn', 'https://cdn.tailwindcss.com', [], null);

    ob_start();
    ?>

    <div class="max-w-5xl mx-auto bg-white shadow-xl rounded-2xl p-4 sm:p-8 space-y-6">

        <div class="space-y-2">
            <h2 class="text-2xl font-bold text-gray-800">Cadastrar Comunidade</h2>
            <p class="text-gray-600 text-base">Fluxo guiado por etapas para facilitar o preenchimento.</p>
        </div>

        <div class="sticky top-4 z-20 bg-white/95 backdrop-blur-sm py-3 border-b border-gray-100 rounded-xl px-2 shadow-sm">
            <div class="h-2 bg-gray-200 rounded-full overflow-hidden">
                <div id="progresso-cadastro" class="h-full bg-indigo-600 rounded-full transition-all duration-300" style="width: 25%;"></div>
            </div>
            <div id="etapas-cadastro" class="mt-3 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-2 text-sm sm:text-base">
                <button type="button" data-step-nav="1" class="step-nav w-full text-left px-3 py-2 rounded-lg border border-indigo-200 bg-indigo-50 text-indigo-700 font-medium">1. Dados principais</button>
                <button type="button" data-step-nav="2" class="step-nav w-full text-left px-3 py-2 rounded-lg border border-gray-200 bg-white text-gray-500">2. Localização</button>
                <button type="button" data-step-nav="3" class="step-nav w-full text-left px-3 py-2 rounded-lg border border-gray-200 bg-white text-gray-500">3. Contatos</button>
                <button type="button" data-step-nav="4" class="step-nav w-full text-left px-3 py-2 rounded-lg border border-gray-200 bg-white text-gray-500">4. Eventos e envio</button>
            </div>
        </div>

        <section id="secao-etapa-1" data-step="1" class="rounded-2xl border border-gray-200 p-4 sm:p-6 space-y-4">
            <h3 class="text-lg font-semibold text-gray-800">1. Dados principais</h3>

            <div class="grid md:grid-cols-2 gap-4 sm:gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Nome</label>
                    <input type="text" id="nome"
                        class="mt-1 w-full rounded-xl border-2 border-gray-200 bg-gray-50 px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 shadow-sm text-base"
                        placeholder="Ex.: Comunidade São José">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Tipo</label>
                    <select id="tipo"
                        class="mt-1 w-full rounded-xl border-2 border-gray-200 bg-gray-50 px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 shadow-sm text-base">
                        <option value="">Carregando...</option>
                    </select>
                </div>

                <div id="campo-paroquia" class="hidden md:col-span-2 rounded-xl border border-amber-200 bg-amber-50 p-4">
                    <label class="block text-sm font-medium text-amber-900">Paróquia Responsável (obrigatório para Capela)</label>
                    <input type="text" id="busca-paroquia"
                        placeholder="Digite para buscar paróquia..."
                        class="mt-1 w-full rounded-xl border-2 border-amber-300 bg-white px-3 py-2 focus:ring-2 focus:ring-amber-500 text-base">
                    <input type="hidden" id="parent_paroquia">
                    <div id="resultado-paroquias" class="bg-white border rounded-xl mt-2 hidden"></div>
                    <p class="mt-2 text-sm sm:text-base text-amber-900 leading-relaxed">
                        Se a paróquia não existir, pare o cadastro da capela e cadastre primeiro a paróquia.
                    </p>
                </div>
            </div>
        </section>

        <section id="secao-etapa-2" data-step="2" class="rounded-2xl border border-gray-200 p-4 sm:p-6 space-y-4">
            <h3 class="text-lg font-semibold text-gray-800">2. Localização</h3>

            <div class="grid md:grid-cols-2 gap-4 sm:gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Latitude</label>
                    <input type="number" step="any" id="latitude"
                        class="mt-1 w-full rounded-xl border-2 border-gray-200 bg-gray-50 px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 shadow-sm text-base">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Longitude</label>
                    <input type="number" step="any" id="longitude"
                        class="mt-1 w-full rounded-xl border-2 border-gray-200 bg-gray-50 px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 shadow-sm text-base">
                </div>

                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700">Endereço</label>
                    <div class="mt-1 flex flex-col sm:flex-row gap-2">
                        <input type="text" id="endereco"
                            class="w-full rounded-xl border-2 border-gray-200 bg-gray-50 px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 shadow-sm text-base"
                            placeholder="Digite o endereço e busque no mapa">
                        <button type="button" id="buscar-endereco-mapa"
                            class="px-5 py-2.5 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200 rounded-xl text-base font-medium whitespace-nowrap transition">
                            Buscar no mapa
                        </button>
                    </div>
                    <p id="mapa-ajuste-msg" class="mt-3 text-base text-gray-700 hidden">Esta certo a marcação no mapa? Clique para ajustar</p>
                    <p id="mapa-endereco-erro" class="mt-3 text-base font-medium text-red-700 hidden"></p>
                    <div id="mapa-cadastro" class="mt-3 rounded-xl border" style="height: 320px;"></div>
                </div>
            </div>
        </section>

        <section id="secao-etapa-3" data-step="3" class="rounded-2xl border border-gray-200 p-4 sm:p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">3. Contatos</h3>

            <div id="contatos-container" class="space-y-3"></div>

            <button type="button"
                onclick="mapaAdicionarContato()"
                class="mt-3 w-full sm:w-auto px-5 py-2.5 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200 rounded-xl text-base font-medium transition">
                + Adicionar contato
            </button>
        </section>

        <section id="secao-etapa-4" data-step="4" class="rounded-2xl border border-gray-200 p-4 sm:p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">4. Eventos e envio</h3>

            <div id="eventos" class="space-y-6"></div>

            <div class="pt-4 mt-4 border-t">
                <button onclick="mapaEnviar()"
                    class="w-full bg-indigo-600 hover:bg-indigo-700 text-white py-3 rounded-xl font-semibold transition">
                    Salvar Comunidade
                </button>
            </div>
        </section>


        <section id="secao-imagem" class="rounded-2xl border border-gray-200 p-4 sm:p-6 space-y-3">
            <h3 class="text-lg font-semibold text-gray-800">Imagem da comunidade (opcional)</h3>
            <p class="text-base text-gray-600">Aceita JPG, PNG, WEBP ou GIF. Máximo sugerido: 5MB.</p>
            <input type="file" id="imagem-comunidade" accept="image/jpeg,image/png,image/webp,image/gif"
                class="block w-full text-base text-gray-700 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-gray-100 file:text-gray-700 hover:file:bg-gray-200">
            <p id="imagem-comunidade-msg" class="text-base hidden"></p>
        </section>

        <div class="pt-1">
            <button type="button"
                onclick="mapaAdicionarEvento()"
                class="w-full sm:w-auto mt-3 px-5 py-2.5 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200 rounded-xl text-base font-medium transition">
                + Adicionar evento
            </button>
        </section>


        <section id="secao-imagem" class="rounded-2xl border border-gray-200 p-4 sm:p-6 space-y-3">
            <h3 class="text-lg font-semibold text-gray-800">Imagem da comunidade (opcional)</h3>
            <p class="text-base text-gray-600">Aceita JPG, PNG, WEBP ou GIF. Máximo sugerido: 5MB.</p>
            <input type="file" id="imagem-comunidade" accept="image/jpeg,image/png,image/webp,image/gif"
                class="block w-full rounded-xl border-2 border-gray-200 bg-gray-50 p-2 text-base text-gray-700 file:mr-4 file:py-2.5 file:px-4 file:rounded-lg file:border-0 file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
            <p id="imagem-comunidade-msg" class="text-base hidden"></p>
        </section>

        <div class="pt-1">
            <div class="pt-4 mt-2 border-t border-gray-200">
                <button onclick="mapaEnviar()"
                    class="w-full bg-indigo-600 hover:bg-indigo-700 text-white py-3 rounded-xl font-semibold transition shadow-sm">
                    Salvar Comunidade
                </button>
            </div>
        </div>

        <div id="mapa-debug" class="text-base text-gray-600"></div>

    </div>

    <?php
    return ob_get_clean();
});
