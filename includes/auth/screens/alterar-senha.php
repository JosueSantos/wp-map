<?php

function cc_shortcode_alterar_senha_mapa() {
    cc_enqueue_auth_ui_assets();

    if (!is_user_logged_in()) {
        return '<div class="max-w-3xl mx-auto bg-white border border-gray-200 rounded-2xl p-6"><p class="text-gray-800">' . esc_html__('Faça login para alterar sua senha.', 'cadastro-comunidades') . '</p></div>';
    }

    ob_start();
    ?>
    <div class="max-w-3xl mx-auto bg-white border border-gray-200 shadow-sm rounded-2xl p-6 sm:p-8 space-y-6">
        <h2 class="text-2xl font-bold text-gray-800"><?php esc_html_e('Alterar senha', 'cadastro-comunidades'); ?></h2>
        <form method="post" class="space-y-4">
            <?php echo cc_render_password_field('senha_atual', __('Senha atual', 'cadastro-comunidades'), true); ?>
            <?php echo cc_render_password_field('nova_senha', __('Nova senha', 'cadastro-comunidades'), true, '', 6); ?>
            <?php echo cc_render_password_field('confirmar_nova_senha', __('Confirmar nova senha', 'cadastro-comunidades'), true, '', 6); ?>
            <?php wp_nonce_field('cc_change_password', 'cc_change_password_nonce'); ?>
            <input type="hidden" name="cc_auth_action" value="change_password">
            <button type="submit" class="<?php echo esc_attr(cc_auth_button_class()); ?>"><?php esc_html_e('Alterar senha', 'cadastro-comunidades'); ?></button>
        </form>
        <?php echo cc_render_password_toggle_script(); ?>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('alterar-senha-mapa', 'cc_shortcode_alterar_senha_mapa');

