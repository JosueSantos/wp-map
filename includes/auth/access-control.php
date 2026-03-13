<?php

function cc_is_agente_mapa_user($user = null) {
    $user = $user instanceof WP_User ? $user : wp_get_current_user();

    if (!$user || 0 === (int) $user->ID) {
        return false;
    }

    return in_array(CC_ROLE_AGENTE_MAPA, (array) $user->roles, true);
}

function cc_block_wp_admin_for_agente_mapa() {
    if ((defined('DOING_AJAX') && DOING_AJAX) || wp_doing_ajax()) {
        return;
    }

    if (is_admin() && cc_is_agente_mapa_user()) {
        wp_safe_redirect(home_url('/'));
        exit;
    }
}
add_action('admin_init', 'cc_block_wp_admin_for_agente_mapa');

function cc_hide_admin_bar_for_agente_mapa($show) {
    if (cc_is_agente_mapa_user()) {
        return false;
    }

    return $show;
}
add_filter('show_admin_bar', 'cc_hide_admin_bar_for_agente_mapa');

function cc_hide_profile_interface_preferences_for_agente_mapa() {
    if (!cc_is_agente_mapa_user()) {
        return;
    }
    ?>
    <style>
        .user-comment-shortcuts-wrap,
        .user-admin-bar-front-wrap,
        .user-language-wrap {
            display: none;
        }
    </style>
    <?php
}
add_action('admin_head-profile.php', 'cc_hide_profile_interface_preferences_for_agente_mapa');

add_filter('show_user_language_picker', function ($show) {
    return cc_is_agente_mapa_user() ? false : $show;
});
