<?php

add_shortcode('mapa_form_comunidade', function () {

    wp_enqueue_script(
        'mapa-form',
        plugin_dir_url(__FILE__) . '../assets/js/form.js',
        [],
        '1.0',
        true
    );

    wp_localize_script('mapa-form', 'MAPA_API', [
        'url'   => rest_url('mapa/v1/comunidade'),
        'nonce' => wp_create_nonce('wp_rest')
    ]);

    ob_start();
    ?>

    <div id="mapa-form-comunidade">
        <h3>Cadastrar Comunidade</h3>

        <input type="text" id="nome" placeholder="Nome da comunidade">
        <input type="text" id="tipo" placeholder="paroquia | capela | independente">
        <input type="text" id="latitude" placeholder="Latitude">
        <input type="text" id="longitude" placeholder="Longitude">
        <input type="text" id="endereco" placeholder="Endereço">
        
        <h4>Contatos</h4>
        <button onclick="mapaAdicionarContato()">+ Contato</button>
        <pre id="contatos-lista"></pre>

        <h4>Eventos</h4>

        <div id="eventos"></div>

        <button onclick="mapaAdicionarEvento()">+ Evento</button>
        <button onclick="mapaEnviar()">Salvar Comunidade</button>

        <pre id="mapa-debug"></pre>
    </div>

    <?php
    return ob_get_clean();
});
