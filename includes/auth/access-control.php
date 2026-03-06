<?php

function cc_block_wp_admin_for_non_admins() {
    if ((defined('DOING_AJAX') && DOING_AJAX) || wp_doing_ajax()) return;

    if (is_admin() && !current_user_can('manage_options')) {
        wp_safe_redirect(home_url('/'));
        exit;
    }
}
add_action('admin_init', 'cc_block_wp_admin_for_non_admins');

