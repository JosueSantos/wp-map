<?php

function cc_shortcode_cadastro_mapa() {
    cc_enqueue_auth_ui_assets();
    $notice = sanitize_text_field($_GET['cc_auth_notice'] ?? '');
    $redirect_to = cc_get_safe_redirect_url_from_request();

    if (is_user_logged_in()) {
        return '<div class="max-w-3xl mx-auto bg-white border border-gray-200 rounded-2xl p-6"><p class="text-gray-800">' . esc_html__('Você já está logado.', 'cadastro-comunidades') . ' <a class="text-indigo-700 font-semibold" href="' . esc_url(cc_get_auth_page_url('minha-conta', '/minha-conta')) . '">' . esc_html__('Ir para minha conta', 'cadastro-comunidades') . '</a></p></div>';
    }

    $paroquias = cc_get_paroquias_options();

    ob_start();
    ?>
    <div class="max-w-3xl mx-auto bg-white border border-gray-200 shadow-sm rounded-2xl p-6 sm:p-8 space-y-6">
        <div>
            <h2 class="text-2xl font-bold text-gray-800"><?php esc_html_e('Cadastro', 'cadastro-comunidades'); ?></h2>
            <p class="text-gray-600 mt-1"><?php esc_html_e('Preencha os dados abaixo. Se tiver dificuldade, peça ajuda na sua comunidade/paróquia.', 'cadastro-comunidades'); ?></p>
        </div>

        <?php echo cc_render_auth_notice($notice); ?>

        <form method="post" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700"><?php esc_html_e('Nome completo', 'cadastro-comunidades'); ?></label>
                <input type="text" name="nome" required class="<?php echo esc_attr(cc_auth_input_class()); ?>">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700"><?php esc_html_e('E-mail', 'cadastro-comunidades'); ?></label>
                <input type="email" name="email" required class="<?php echo esc_attr(cc_auth_input_class()); ?>">
            </div>

            <?php echo cc_render_password_field('senha', __('Senha (opcional)', 'cadastro-comunidades'), false, __('Se não preencher, o sistema gera uma senha segura automaticamente.', 'cadastro-comunidades')); ?>

            <div>
                <label class="block text-sm font-medium text-gray-700"><?php esc_html_e('Paróquia (opcional)', 'cadastro-comunidades'); ?></label>
                <input id="cc-register-paroquia" type="text" name="paroquia_existente" list="cc-paroquias-datalist" class="<?php echo esc_attr(cc_auth_input_class()); ?>" placeholder="<?php esc_attr_e('Digite para buscar paróquia', 'cadastro-comunidades'); ?>">
                <datalist id="cc-paroquias-datalist">
                    <option value=""><?php esc_html_e('Sem vínculo de paróquia', 'cadastro-comunidades'); ?></option>
                    <?php foreach ($paroquias as $paroquia): ?>
                        <option value="<?php echo esc_attr($paroquia->post_title . ' (#' . (int) $paroquia->ID . ')'); ?>"></option>
                    <?php endforeach; ?>
                </datalist>
            </div>

            <?php wp_nonce_field('cc_register', 'cc_register_nonce'); ?>
            <input type="hidden" name="cc_auth_action" value="register">
            <?php if (!empty($redirect_to)): ?>
                <input type="hidden" name="redirect_to" value="<?php echo esc_url($redirect_to); ?>">
            <?php endif; ?>
            <button type="submit" class="<?php echo esc_attr(cc_auth_button_class()); ?> w-full sm:w-auto"><?php esc_html_e('Cadastrar', 'cadastro-comunidades'); ?></button>
            <a class="ml-0 sm:ml-3 text-indigo-700 underline font-medium" href="<?php echo esc_url(cc_with_redirect_to(cc_get_auth_page_url('login', '/login'), $redirect_to)); ?>"><?php esc_html_e('Já tenho conta', 'cadastro-comunidades'); ?></a>
        </form>

        <?php echo cc_render_social_buttons($redirect_to); ?>
        <?php echo cc_render_password_toggle_script(); ?>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('cadastro-mapa', 'cc_shortcode_cadastro_mapa');

