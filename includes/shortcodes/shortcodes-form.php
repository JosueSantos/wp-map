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
        '1.0',
        true
    );

    wp_localize_script('mapa-form', 'MAPA_API', [
        'url'   => rest_url('mapa/v1/comunidade'),
        'nonce' => wp_create_nonce('wp_rest')
    ]);

    wp_enqueue_script('tailwind-cdn', 'https://cdn.tailwindcss.com', [], null);

    ob_start();
    ?>

    <div class="max-w-4xl mx-auto bg-white shadow-xl rounded-2xl p-8 space-y-8">

        <div>
            <h2 class="text-2xl font-bold text-gray-800">Cadastrar Comunidade</h2>
            <p class="text-gray-500 text-sm">Preencha as informações abaixo</p>
        </div>

        <!-- Informações principais -->
        <div class="grid md:grid-cols-2 gap-6">
            
            <div>
                <label class="block text-sm font-medium text-gray-700">Nome</label>
                <input type="text" id="nome"
                    class="mt-1 w-full rounded-xl border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 shadow-sm">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Tipo</label>
                <select id="tipo"
                    class="mt-1 w-full rounded-xl border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 shadow-sm">
                    <option value="">Carregando...</option>
                </select>
            </div>

            <div id="campo-paroquia" class="hidden md:col-span-2">
                <label class="block text-sm font-medium text-gray-700">Paróquia Responsável</label>
                <input type="text" id="busca-paroquia"
                    placeholder="Digite para buscar paróquia..."
                    class="mt-1 w-full rounded-xl border-gray-300 focus:ring-2 focus:ring-blue-500">
                <input type="hidden" id="parent_paroquia">
                <div id="resultado-paroquias" class="bg-white border rounded-xl mt-2 hidden"></div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Latitude</label>
                <input type="number" step="any" id="latitude"
                    class="mt-1 w-full rounded-xl border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 shadow-sm">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Longitude</label>
                <input type="number" step="any" id="longitude"
                    class="mt-1 w-full rounded-xl border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 shadow-sm">
            </div>

            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700">Endereço</label>
                <div class="mt-1 flex flex-col sm:flex-row gap-2">
                    <input type="text" id="endereco"
                        class="w-full rounded-xl border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 shadow-sm"
                        placeholder="Digite o endereço e busque no mapa">
                    <button type="button" id="buscar-endereco-mapa"
                        class="px-4 py-2 bg-gray-100 hover:bg-gray-200 rounded-xl text-sm whitespace-nowrap">
                        Buscar no mapa
                    </button>
                </div>
                <p id="mapa-ajuste-msg" class="mt-2 text-sm text-gray-600 hidden">Esta certo a marcação no mapa? Clique para ajustar</p>
                <p id="mapa-endereco-erro" class="mt-2 text-sm text-red-600 hidden"></p>
                <div id="mapa-cadastro" class="mt-3 rounded-xl border" style="height: 320px;"></div>
            </div>

        </div>

        <!-- Contatos -->
        <div>
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Contatos</h3>

            <div id="contatos-container" class="space-y-3"></div>

            <button type="button"
                onclick="mapaAdicionarContato()"
                class="mt-3 px-4 py-2 bg-gray-100 hover:bg-gray-200 rounded-xl text-sm">
                + Adicionar contato
            </button>
        </div>

        <!-- Eventos -->
        <div>
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Eventos</h3>

            <div id="eventos" class="space-y-6"></div>

            <button type="button"
                onclick="mapaAdicionarEvento()"
                class="mt-3 px-4 py-2 bg-gray-100 hover:bg-gray-200 rounded-xl text-sm">
                + Adicionar evento
            </button>
        </div>

        <!-- Botão -->
        <div class="pt-4 border-t">
            <button onclick="mapaEnviar()"
                class="w-full bg-blue-600 hover:bg-blue-700 text-white py-3 rounded-xl font-semibold transition">
                Salvar Comunidade
            </button>
        </div>

        <div id="mapa-debug" class="text-sm text-gray-500"></div>

    </div>

    <?php
    return ob_get_clean();
});
