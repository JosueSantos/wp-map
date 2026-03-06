<?php

function cc_enqueue_auth_ui_assets() {
    wp_enqueue_script('tailwind-cdn', 'https://cdn.tailwindcss.com', [], null);
}

function cc_auth_input_class() {
    return 'mt-1 w-full rounded-xl border-2 border-gray-200 bg-gray-50 px-3 py-2 text-base focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500';
}

function cc_auth_button_class($variant = 'primary') {
    if ($variant === 'secondary') {
        return 'inline-flex items-center justify-center rounded-xl border border-indigo-200 bg-indigo-50 px-4 py-2 font-semibold text-indigo-700 hover:bg-indigo-100 transition';
    }

    if ($variant === 'danger') {
        return 'inline-flex items-center justify-center rounded-xl border border-red-200 bg-red-50 px-4 py-2 font-semibold text-red-700 hover:bg-red-100 transition';
    }

    return 'inline-flex items-center justify-center rounded-xl bg-indigo-600 px-4 py-2 font-semibold text-white hover:bg-indigo-700 transition';
}

function cc_render_auth_notice($notice) {
    $notice_map = [
        'logged_out' => ['type' => 'success', 'message' => __('Você saiu da sua conta com sucesso.', 'cadastro-comunidades')],
        'login_nonce' => ['type' => 'error', 'message' => __('Não foi possível validar sua sessão. Atualize a página e tente novamente.', 'cadastro-comunidades')],
        'login_invalid' => ['type' => 'error', 'message' => __('E-mail/usuário ou senha inválidos. Confira os dados e tente novamente.', 'cadastro-comunidades')],
        'register_nonce' => ['type' => 'error', 'message' => __('Não foi possível validar o cadastro. Atualize a página e tente novamente.', 'cadastro-comunidades')],
        'register_invalid_data' => ['type' => 'error', 'message' => __('Informe nome e um e-mail válido para continuar.', 'cadastro-comunidades')],
        'register_email_exists' => ['type' => 'error', 'message' => __('Este e-mail já está cadastrado. Faça login ou use outro e-mail.', 'cadastro-comunidades')],
        'register_error' => ['type' => 'error', 'message' => __('Não foi possível concluir seu cadastro agora. Tente novamente em instantes.', 'cadastro-comunidades')],
        'reset_ok' => ['type' => 'success', 'message' => __('Senha redefinida com sucesso. Agora você já pode entrar.', 'cadastro-comunidades')],
    ];

    if (empty($notice_map[$notice])) return '';

    $item = $notice_map[$notice];
    $is_success = $item['type'] === 'success';
    $class = $is_success
        ? 'rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-green-800'
        : 'rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-red-700';

    return '<p class="' . esc_attr($class) . '">' . esc_html($item['message']) . '</p>';
}

function cc_render_password_field($name, $label, $required = false, $hint = '', $minlength = 0) {
    $required_attr = $required ? ' required' : '';
    $minlength_attr = $minlength > 0 ? ' minlength="' . (int) $minlength . '"' : '';
    $input_class = cc_auth_input_class() . ' pr-11';
    $id = 'cc-pwd-' . sanitize_html_class($name);

    ob_start();
    ?>
    <div>
        <label class="block text-sm font-medium text-gray-700" for="<?php echo esc_attr($id); ?>"><?php echo esc_html($label); ?></label>
        <div class="relative mt-1">
            <input id="<?php echo esc_attr($id); ?>" type="password" name="<?php echo esc_attr($name); ?>"<?php echo $required_attr; ?><?php echo $minlength_attr; ?> class="<?php echo esc_attr($input_class); ?>" autocomplete="current-password">
            <button type="button" class="cc-password-toggle absolute inset-y-0 right-0 px-3 text-gray-500 hover:text-indigo-700" data-target="<?php echo esc_attr($id); ?>" aria-label="<?php esc_attr_e('Mostrar senha', 'cadastro-comunidades'); ?>" title="<?php esc_attr_e('Mostrar/ocultar senha', 'cadastro-comunidades'); ?>">👁️</button>
        </div>
        <?php if (!empty($hint)): ?>
            <p class="text-xs text-gray-500 mt-1"><?php echo esc_html($hint); ?></p>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

function cc_render_password_toggle_script() {
    static $printed = false;
    if ($printed) return '';
    $printed = true;

    return '<script>document.addEventListener("DOMContentLoaded",function(){document.querySelectorAll(".cc-password-toggle").forEach(function(btn){btn.addEventListener("click",function(){var target=document.getElementById(btn.dataset.target);if(!target)return;var show=target.type==="password";target.type=show?"text":"password";btn.textContent=show?"🙈":"👁️";btn.setAttribute("aria-label",show?"Ocultar senha":"Mostrar senha");});});});</script>';
}

function cc_render_social_buttons($redirect_to = '') {
    $providers = ['google' => 'Google', 'facebook' => 'Facebook', 'linkedin' => 'LinkedIn'];
    $available_providers = [];

    foreach ($providers as $provider => $label) {
        $url = cc_get_social_button_url($provider, $redirect_to);
        if (!$url) {
            continue;
        }

        $available_providers[$provider] = [
            'label' => $label,
            'url' => $url,
        ];
    }

    if (empty($available_providers)) {
        return '';
    }

    ob_start();

    echo '<div class="pt-3 border-t border-gray-100"><p class="text-sm text-gray-600">Ou entre com sua rede social:</p><div class="mt-4 grid grid-cols-1 sm:grid-cols-3 gap-2">';
    foreach ($available_providers as $provider_data) {
        echo '<a class="' . esc_attr(cc_auth_button_class('secondary')) . '" href="' . esc_url($provider_data['url']) . '">' . esc_html(sprintf(__('Entrar com %s', 'cadastro-comunidades'), $provider_data['label'])) . '</a>';
    }
    echo '</div>';

    echo '</div>';

    return ob_get_clean();
}
add_shortcode('mapa-social-buttons', 'cc_render_social_buttons');

