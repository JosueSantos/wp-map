<?php

function cc_shortcode_redefinir_senha_mapa() {
    cc_enqueue_auth_ui_assets();

    $login = sanitize_text_field(wp_unslash($_GET['login'] ?? ''));
    $key = sanitize_text_field(wp_unslash($_GET['key'] ?? ''));
    $is_valid = $login && $key && !is_wp_error(check_password_reset_key($key, $login));

    ob_start();
    ?>
    <div class="max-w-3xl mx-auto bg-white border border-gray-200 shadow-sm rounded-2xl p-6 sm:p-8 space-y-6">
        <div>
            <h2 class="text-2xl font-bold text-gray-800"><?php esc_html_e('Redefinir senha', 'cadastro-comunidades'); ?></h2>
        </div>

        <?php if (!$is_valid): ?>
            <p class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-red-700"><?php esc_html_e('Link inválido ou expirado. Solicite um novo link de redefinição.', 'cadastro-comunidades'); ?></p>
        <?php else: ?>
            <form method="post" class="space-y-4">
                <input type="hidden" name="login" value="<?php echo esc_attr($login); ?>">
                <input type="hidden" name="key" value="<?php echo esc_attr($key); ?>">
                <?php echo cc_render_password_field('nova_senha', __('Nova senha', 'cadastro-comunidades'), true, '', 6); ?>
                <?php echo cc_render_password_field('confirmar_nova_senha', __('Confirmar nova senha', 'cadastro-comunidades'), true, '', 6); ?>
                <?php wp_nonce_field('cc_reset_password', 'cc_reset_password_nonce'); ?>
                <input type="hidden" name="cc_auth_action" value="reset_password">
                <button type="submit" class="<?php echo esc_attr(cc_auth_button_class()); ?>"><?php esc_html_e('Salvar nova senha', 'cadastro-comunidades'); ?></button>
            </form>
            <?php echo cc_render_password_toggle_script(); ?>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('redefinir-senha-mapa', 'cc_shortcode_redefinir_senha_mapa');

