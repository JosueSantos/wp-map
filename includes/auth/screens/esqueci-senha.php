<?php

function cc_shortcode_esqueci_senha_mapa() {
    cc_enqueue_auth_ui_assets();

    $notice = sanitize_text_field($_GET['cc_auth_notice'] ?? '');

    ob_start();
    ?>
    <div class="max-w-3xl mx-auto bg-white border border-gray-200 shadow-sm rounded-2xl p-6 sm:p-8 space-y-6">
        <div>
            <h2 class="text-2xl font-bold text-gray-800"><?php esc_html_e('Esqueci minha senha', 'cadastro-comunidades'); ?></h2>
            <p class="text-gray-600 mt-1"><?php esc_html_e('Informe seu e-mail para receber o link de redefinição.', 'cadastro-comunidades'); ?></p>
        </div>

        <?php if ($notice === 'forgot_sent'): ?>
            <p class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-green-800"><?php esc_html_e('Se o e-mail existir na base, você receberá um link para redefinir sua senha.', 'cadastro-comunidades'); ?></p>
        <?php elseif ($notice === 'forgot_error'): ?>
            <p class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-red-700"><?php esc_html_e('Informe um e-mail válido para continuar.', 'cadastro-comunidades'); ?></p>
        <?php endif; ?>

        <form method="post" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700"><?php esc_html_e('E-mail', 'cadastro-comunidades'); ?></label>
                <input type="email" name="email" required class="<?php echo esc_attr(cc_auth_input_class()); ?>">
            </div>
            <?php wp_nonce_field('cc_forgot_password', 'cc_forgot_password_nonce'); ?>
            <input type="hidden" name="cc_auth_action" value="forgot_password">
            <button type="submit" class="<?php echo esc_attr(cc_auth_button_class()); ?>"><?php esc_html_e('Enviar link de redefinição', 'cadastro-comunidades'); ?></button>
        </form>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('esqueci-senha-mapa', 'cc_shortcode_esqueci_senha_mapa');

