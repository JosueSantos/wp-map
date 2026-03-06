<?php

function cc_shortcode_login_mapa() {
    cc_enqueue_auth_ui_assets();
    $notice = sanitize_text_field($_GET['cc_auth_notice'] ?? '');
    $redirect_to = cc_get_safe_redirect_url_from_request();

    if (is_user_logged_in()) {
        return '<div class="max-w-3xl mx-auto bg-white border border-gray-200 rounded-2xl p-6"><p class="text-gray-800">' . esc_html__('Você já está logado.', 'cadastro-comunidades') . ' <a class="text-indigo-700 font-semibold" href="' . esc_url(cc_get_auth_page_url('minha-conta', '/minha-conta')) . '">' . esc_html__('Ir para minha conta', 'cadastro-comunidades') . '</a></p></div>';
    }

    ob_start();
    ?>
    <div class="max-w-3xl mx-auto bg-white border border-gray-200 shadow-sm rounded-2xl p-6 sm:p-8 space-y-6">
        <div>
            <h2 class="text-2xl font-bold text-gray-800"><?php esc_html_e('Entrar', 'cadastro-comunidades'); ?></h2>
            <p class="text-gray-600 mt-1"><?php esc_html_e('Use seu e-mail/usuário e senha para acessar sua conta.', 'cadastro-comunidades'); ?></p>
        </div>

        <?php echo cc_render_auth_notice($notice); ?>

        <form method="post" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700"><?php esc_html_e('E-mail ou usuário', 'cadastro-comunidades'); ?></label>
                <input type="text" name="email" required class="<?php echo esc_attr(cc_auth_input_class()); ?>">
            </div>

            <?php echo cc_render_password_field('senha', __('Senha', 'cadastro-comunidades'), true); ?>

            <?php wp_nonce_field('cc_login', 'cc_login_nonce'); ?>
            <input type="hidden" name="cc_auth_action" value="login">
            <?php if (!empty($redirect_to)): ?>
                <input type="hidden" name="redirect_to" value="<?php echo esc_url($redirect_to); ?>">
            <?php endif; ?>

            <button type="submit" class="<?php echo esc_attr(cc_auth_button_class()); ?> w-full sm:w-auto"><?php esc_html_e('Entrar', 'cadastro-comunidades'); ?></button>
            
            <div class="flex flex-col gap-2 pt-2 sm:flex-row sm:items-center sm:gap-4">

                <a class="text-indigo-700 underline font-medium"
                href="<?php echo esc_url(cc_get_auth_page_url('esqueci-senha', '/esqueci-senha')); ?>">
                    <?php esc_html_e('Esqueci minha senha', 'cadastro-comunidades'); ?>
                </a>

                <a class="text-indigo-700 underline font-medium"
                href="<?php echo esc_url(cc_with_redirect_to(cc_get_auth_page_url('cadastro', '/cadastro'), $redirect_to)); ?>">
                    <?php esc_html_e('Criar cadastro', 'cadastro-comunidades'); ?>
                </a>

            </div>
        </form>

        <?php echo cc_render_social_buttons($redirect_to); ?>
        <?php echo cc_render_password_toggle_script(); ?>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('login-mapa', 'cc_shortcode_login_mapa');

