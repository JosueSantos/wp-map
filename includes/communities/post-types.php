<?php

function cc_get_comunidade_capabilities() {
    return [
        'edit_post' => 'edit_comunidade',
        'read_post' => 'read_comunidade',
        'delete_post' => 'delete_comunidade',
        'edit_posts' => 'edit_comunidades',
        'edit_others_posts' => 'edit_others_comunidades',
        'publish_posts' => 'publish_comunidades',
        'read_private_posts' => 'read_private_comunidades',
        'delete_posts' => 'delete_comunidades',
        'delete_private_posts' => 'delete_private_comunidades',
        'delete_published_posts' => 'delete_published_comunidades',
        'delete_others_posts' => 'delete_others_comunidades',
        'edit_private_posts' => 'edit_private_comunidades',
        'edit_published_posts' => 'edit_published_comunidades',
        'create_posts' => 'create_comunidades',
    ];
}

function cc_get_evento_capabilities() {
    return [
        'edit_post' => 'edit_evento',
        'read_post' => 'read_evento',
        'delete_post' => 'delete_evento',
        'edit_posts' => 'edit_eventos',
        'edit_others_posts' => 'edit_others_eventos',
        'publish_posts' => 'publish_eventos',
        'read_private_posts' => 'read_private_eventos',
        'delete_posts' => 'delete_eventos',
        'delete_private_posts' => 'delete_private_eventos',
        'delete_published_posts' => 'delete_published_eventos',
        'delete_others_posts' => 'delete_others_eventos',
        'edit_private_posts' => 'edit_private_eventos',
        'edit_published_posts' => 'edit_published_eventos',
        'create_posts' => 'create_eventos',
    ];
}

function cc_register_post_types() {

    register_post_type('comunidade', [
        'label' => 'Comunidades',
        'public' => true,
        'menu_icon' => 'dashicons-location-alt',
        'supports' => ['title','editor','thumbnail'],
        'show_in_rest' => true,
        'map_meta_cap' => true,
        'capabilities' => cc_get_comunidade_capabilities(),
    ]);

    register_post_type('evento', [
        'label' => 'Eventos',
        'public' => true,
        'menu_icon' => 'dashicons-calendar',
        'supports' => ['title','editor'],
        'show_in_rest' => true,
        'map_meta_cap' => true,
        'capabilities' => cc_get_evento_capabilities(),
    ]);
}

add_action('init', 'cc_register_post_types');

function cc_grant_mapa_caps_to_logged_roles() {
    if (!function_exists('wp_roles')) {
        return;
    }

    $capabilities = array_unique(array_merge(
        array_values(cc_get_comunidade_capabilities()),
        array_values(cc_get_evento_capabilities())
    ));

    foreach (wp_roles()->role_objects as $role) {
        if (!$role || !$role->has_cap('read')) {
            continue;
        }

        foreach ($capabilities as $capability) {
            $role->add_cap($capability);
        }
    }
}
add_action('init', 'cc_grant_mapa_caps_to_logged_roles', 20);
