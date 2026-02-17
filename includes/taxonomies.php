<?php

function cc_register_taxonomies() {

    register_taxonomy('tipo_comunidade', 'comunidade', [
        'label' => 'Tipo de Comunidade',
        'hierarchical' => false,
        'show_in_rest' => true
    ]);

    register_taxonomy('tipo_evento', 'evento', [
        'label' => 'Tipo de Evento',
        'hierarchical' => false,
        'show_in_rest' => true
    ]);

    register_taxonomy('tags_evento', 'evento', [
        'label' => 'Características do Evento',
        'hierarchical' => false,
        'show_in_rest' => true,
    ]);

}

add_action('init', 'cc_register_taxonomies');
