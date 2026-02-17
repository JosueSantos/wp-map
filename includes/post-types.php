<?php

function cc_register_post_types() {

    register_post_type('comunidade', [
        'label' => 'Comunidades',
        'public' => true,
        'menu_icon' => 'dashicons-location-alt',
        'supports' => ['title','editor','thumbnail'],
        'show_in_rest' => true
    ]);

    register_post_type('evento', [
        'label' => 'Eventos',
        'public' => true,
        'menu_icon' => 'dashicons-calendar',
        'supports' => ['title','editor'],
        'show_in_rest' => true
    ]);
}

add_action('init', 'cc_register_post_types');
