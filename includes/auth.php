<?php

if (!defined('ABSPATH')) exit;

const CC_ROLE_AGENTE_MAPA = 'agente_mapa';

function cc_register_agente_mapa_role() {
    add_role(CC_ROLE_AGENTE_MAPA, __('Agente do Mapa', 'cadastro-comunidades'), ['read' => true]);
}
add_action('init', 'cc_register_agente_mapa_role');

function cc_get_auth_page_url($slug, $fallback = '/') {
    $aliases = [
        'login' => ['login', 'login-mapa'],
        'cadastro' => ['cadastro', 'cadastro-mapa'],
        'minha-conta-mapa' => ['minha-conta-mapa'],
    ];

    $candidate_slugs = $aliases[$slug] ?? [$slug];

    foreach ($candidate_slugs as $candidate) {
        $page = get_page_by_path($candidate);
        if ($page) {
            return get_permalink($page->ID);
        }
    }

    return home_url($fallback);
}

function cc_criar_paginas_auth() {
    $pages = [
        'login' => ['title' => __('Login', 'cadastro-comunidades'), 'content' => '[login-mapa]'],
        'cadastro' => ['title' => __('Cadastro', 'cadastro-comunidades'), 'content' => '[cadastro-mapa]'],
        'minha-conta-mapa' => ['title' => __('Minha Conta Mapa', 'cadastro-comunidades'), 'content' => '[minha-conta-mapa]'],
        'login-mapa' => ['title' => __('Login Mapa (Legado)', 'cadastro-comunidades'), 'content' => '[login-mapa]'],
        'cadastro-mapa' => ['title' => __('Cadastro Mapa (Legado)', 'cadastro-comunidades'), 'content' => '[cadastro-mapa]'],
    ];

    foreach ($pages as $slug => $page_data) {
        if (get_page_by_path($slug)) continue;

        wp_insert_post([
            'post_type' => 'page',
            'post_title' => $page_data['title'],
            'post_name' => $slug,
            'post_status' => 'publish',
            'post_content' => $page_data['content'],
        ]);
    }
}

function cc_maybe_ensure_auth_pages() {
    if (!is_admin() || !current_user_can('manage_options')) {
        return;
    }

    cc_criar_paginas_auth();
}
add_action('admin_init', 'cc_maybe_ensure_auth_pages');

function cc_block_wp_admin_for_non_admins() {
    if ((defined('DOING_AJAX') && DOING_AJAX) || wp_doing_ajax()) return;

    if (is_admin() && !current_user_can('manage_options')) {
        wp_safe_redirect(home_url('/'));
        exit;
    }
}
add_action('admin_init', 'cc_block_wp_admin_for_non_admins');

function cc_get_auth_options() {
    return wp_parse_args(get_option('cc_auth_settings', []), [
        'google_client_id' => '',
        'google_client_secret' => '',
        'facebook_app_id' => '',
        'facebook_app_secret' => '',
        'twitter_client_id' => '',
        'twitter_client_secret' => '',
        'linkedin_client_id' => '',
        'linkedin_client_secret' => '',
        'instagram_client_id' => '',
        'instagram_client_secret' => '',
    ]);
}

function cc_register_auth_settings_page() {
    add_options_page(
        __('Mapa - Login Social', 'cadastro-comunidades'),
        __('Mapa - Login Social', 'cadastro-comunidades'),
        'manage_options',
        'cc-auth-settings',
        'cc_render_auth_settings_page'
    );
}
add_action('admin_menu', 'cc_register_auth_settings_page');

function cc_register_auth_settings() {
    register_setting('cc_auth_settings_group', 'cc_auth_settings', ['sanitize_callback' => 'cc_sanitize_auth_settings']);
}
add_action('admin_init', 'cc_register_auth_settings');

function cc_sanitize_auth_settings($input) {
    $output = [];
    foreach (cc_get_auth_options() as $key => $default) {
        $output[$key] = sanitize_text_field($input[$key] ?? '');
    }
    return $output;
}

function cc_get_oauth_callback_url($provider) {
    return add_query_arg(['cc_oauth_callback' => 1, 'provider' => $provider], home_url('/'));
}

function cc_render_auth_settings_page() {
    $settings = cc_get_auth_options();
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Configurações de Login Social', 'cadastro-comunidades'); ?></h1>
        <p><?php esc_html_e('Configure os Apps OAuth com os callbacks abaixo.', 'cadastro-comunidades'); ?></p>
        <ul>
            <li><strong>Google:</strong> <?php echo esc_html(cc_get_oauth_callback_url('google')); ?></li>
            <li><strong>Facebook:</strong> <?php echo esc_html(cc_get_oauth_callback_url('facebook')); ?></li>
            <li><strong>LinkedIn:</strong> <?php echo esc_html(cc_get_oauth_callback_url('linkedin')); ?></li>
            <li><strong>Instagram (informativo):</strong> <?php echo esc_html__('Instagram não fornece e-mail no OAuth básico, então não é recomendado para login nativo de usuários WP.', 'cadastro-comunidades'); ?></li>
        </ul>

        <form method="post" action="options.php">
            <?php settings_fields('cc_auth_settings_group'); ?>
            <table class="form-table" role="presentation">
                <tr><th colspan="2"><h2>Google</h2></th></tr>
                <tr><th><label for="google_client_id">Client ID</label></th><td><input class="regular-text" id="google_client_id" name="cc_auth_settings[google_client_id]" value="<?php echo esc_attr($settings['google_client_id']); ?>"></td></tr>
                <tr><th><label for="google_client_secret">Client Secret</label></th><td><input class="regular-text" id="google_client_secret" name="cc_auth_settings[google_client_secret]" value="<?php echo esc_attr($settings['google_client_secret']); ?>"></td></tr>

                <tr><th colspan="2"><h2>Facebook</h2></th></tr>
                <tr><th><label for="facebook_app_id">App ID</label></th><td><input class="regular-text" id="facebook_app_id" name="cc_auth_settings[facebook_app_id]" value="<?php echo esc_attr($settings['facebook_app_id']); ?>"></td></tr>
                <tr><th><label for="facebook_app_secret">App Secret</label></th><td><input class="regular-text" id="facebook_app_secret" name="cc_auth_settings[facebook_app_secret]" value="<?php echo esc_attr($settings['facebook_app_secret']); ?>"></td></tr>

                <tr><th colspan="2"><h2>LinkedIn (opcional)</h2></th></tr>
                <tr><th><label for="linkedin_client_id">Client ID</label></th><td><input class="regular-text" id="linkedin_client_id" name="cc_auth_settings[linkedin_client_id]" value="<?php echo esc_attr($settings['linkedin_client_id']); ?>"></td></tr>
                                <tr><th><label for="linkedin_client_secret">Client Secret</label></th><td><input class="regular-text" id="linkedin_client_secret" name="cc_auth_settings[linkedin_client_secret]" value="<?php echo esc_attr($settings['linkedin_client_secret']); ?>"></td></tr>
                <tr><th colspan="2"><h2>Instagram (experimental)</h2></th></tr>
                <tr><th><label for="instagram_client_id">Client ID</label></th><td><input class="regular-text" id="instagram_client_id" name="cc_auth_settings[instagram_client_id]" value="<?php echo esc_attr($settings['instagram_client_id']); ?>"></td></tr>
                <tr><th><label for="instagram_client_secret">Client Secret</label></th><td><input class="regular-text" id="instagram_client_secret" name="cc_auth_settings[instagram_client_secret]" value="<?php echo esc_attr($settings['instagram_client_secret']); ?>"></td></tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

function cc_get_oauth_state() {
    $state = wp_generate_password(20, false, false);
    set_transient('cc_oauth_state_' . $state, 1, 10 * MINUTE_IN_SECONDS);
    return $state;
}

function cc_validate_oauth_state($state) {
    $state = sanitize_text_field($state);
    if (!$state) return false;
    $key = 'cc_oauth_state_' . $state;
    $ok = get_transient($key);
    if (!$ok) return false;
    delete_transient($key);
    return true;
}

function cc_get_social_button_url($provider) {
    $settings = cc_get_auth_options();
    $state = cc_get_oauth_state();
    $redirect_uri = cc_get_oauth_callback_url($provider);

    if ($provider === 'google' && !empty($settings['google_client_id'])) {
        return add_query_arg([
            'client_id' => $settings['google_client_id'],
            'redirect_uri' => $redirect_uri,
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'prompt' => 'select_account',
        ], 'https://accounts.google.com/o/oauth2/v2/auth');
    }

    if ($provider === 'facebook' && !empty($settings['facebook_app_id'])) {
        return add_query_arg([
            'client_id' => $settings['facebook_app_id'],
            'redirect_uri' => $redirect_uri,
            'state' => $state,
            'scope' => 'email,public_profile',
            'response_type' => 'code',
        ], 'https://www.facebook.com/v19.0/dialog/oauth');
    }

    if ($provider === 'linkedin' && !empty($settings['linkedin_client_id'])) {
        return add_query_arg([
            'response_type' => 'code',
            'client_id' => $settings['linkedin_client_id'],
            'redirect_uri' => $redirect_uri,
            'scope' => 'openid profile email',
            'state' => $state,
        ], 'https://www.linkedin.com/oauth/v2/authorization');
    }

    return '';
}

function cc_parse_oauth_profile($token_response, $profile_url) {
    if (is_wp_error($token_response)) return $token_response;

    $token_body = json_decode(wp_remote_retrieve_body($token_response), true);
    $access_token = sanitize_text_field($token_body['access_token'] ?? '');
    if (!$access_token) return new WP_Error('oauth_token', __('Falha ao obter access token.', 'cadastro-comunidades'));

    $profile_response = wp_remote_get($profile_url, [
        'headers' => ['Authorization' => 'Bearer ' . $access_token],
    ]);

    if (is_wp_error($profile_response)) return $profile_response;

    $profile = json_decode(wp_remote_retrieve_body($profile_response), true);
    $email = sanitize_email($profile['email'] ?? '');
    $name = sanitize_text_field($profile['name'] ?? ($profile['given_name'] ?? 'Usuário Mapa'));

    if (!$email) {
        return new WP_Error('oauth_email', __('O provedor não retornou e-mail.', 'cadastro-comunidades'));
    }

    return ['email' => $email, 'name' => $name];
}

function cc_fetch_oauth_user_data($provider, $code) {
    $settings = cc_get_auth_options();
    $redirect_uri = cc_get_oauth_callback_url($provider);

    if ($provider === 'google') {
        $token = wp_remote_post('https://oauth2.googleapis.com/token', [
            'body' => [
                'code' => $code,
                'client_id' => $settings['google_client_id'],
                'client_secret' => $settings['google_client_secret'],
                'redirect_uri' => $redirect_uri,
                'grant_type' => 'authorization_code',
            ]
        ]);
        return cc_parse_oauth_profile($token, 'https://openidconnect.googleapis.com/v1/userinfo');
    }

    if ($provider === 'facebook') {
        $token = wp_remote_get(add_query_arg([
            'client_id' => $settings['facebook_app_id'],
            'client_secret' => $settings['facebook_app_secret'],
            'redirect_uri' => $redirect_uri,
            'code' => $code,
        ], 'https://graph.facebook.com/v19.0/oauth/access_token'));
        return cc_parse_oauth_profile($token, 'https://graph.facebook.com/me?fields=id,name,email');
    }

    if ($provider === 'linkedin') {
        $token = wp_remote_post('https://www.linkedin.com/oauth/v2/accessToken', [
            'body' => [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'client_id' => $settings['linkedin_client_id'],
                'client_secret' => $settings['linkedin_client_secret'],
                'redirect_uri' => $redirect_uri,
            ],
        ]);
        return cc_parse_oauth_profile($token, 'https://api.linkedin.com/v2/userinfo');
    }

    return new WP_Error('provider_invalido', __('Provedor OAuth inválido.', 'cadastro-comunidades'));
}

function cc_upsert_social_user($user_data, $provider) {
    $user = get_user_by('email', $user_data['email']);

    if (!$user) {
        $base_login = sanitize_user(current(explode('@', $user_data['email'])));
        $login = $base_login;
        $i = 1;

        while (username_exists($login)) {
            $login = $base_login . $i;
            $i++;
        }

        $user_id = wp_insert_user([
            'user_login' => $login,
            'user_email' => $user_data['email'],
            'display_name' => $user_data['name'],
            'user_pass' => wp_generate_password(24, true),
            'role' => CC_ROLE_AGENTE_MAPA,
        ]);

        if (is_wp_error($user_id)) return $user_id;
        $user = get_user_by('id', $user_id);
    }

    if (!in_array('administrator', (array) $user->roles, true)) {
        $user->set_role(CC_ROLE_AGENTE_MAPA);
    }

    update_user_meta($user->ID, 'cc_social_provider', $provider);
    return $user;
}

function cc_maybe_handle_oauth_callback() {
    if (!isset($_GET['cc_oauth_callback'], $_GET['provider'])) return;

    $provider = sanitize_key($_GET['provider']);
    $code = sanitize_text_field($_GET['code'] ?? '');
    $state = sanitize_text_field($_GET['state'] ?? '');

    if (!$code || !cc_validate_oauth_state($state)) {
        wp_die(esc_html__('Falha de segurança OAuth (state inválido).', 'cadastro-comunidades'));
    }

    $user_data = cc_fetch_oauth_user_data($provider, $code);
    if (is_wp_error($user_data)) wp_die(esc_html($user_data->get_error_message()));

    $user = cc_upsert_social_user($user_data, $provider);
    if (is_wp_error($user)) wp_die(esc_html($user->get_error_message()));

    wp_set_current_user($user->ID);
    wp_set_auth_cookie($user->ID, true);
    wp_safe_redirect(cc_get_auth_page_url('minha-conta-mapa', '/minha-conta-mapa'));
    exit;
}
add_action('init', 'cc_maybe_handle_oauth_callback');

function cc_handle_custom_auth_forms() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['cc_auth_action'])) return;

    $action = sanitize_key($_POST['cc_auth_action']);

    if ($action === 'login') {
        if (!isset($_POST['cc_login_nonce']) || !wp_verify_nonce($_POST['cc_login_nonce'], 'cc_login')) {
            wp_die(__('Nonce inválido no login.', 'cadastro-comunidades'));
        }

        $creds = [
            'user_login' => sanitize_text_field($_POST['email'] ?? ''),
            'user_password' => $_POST['senha'] ?? '',
            'remember' => true,
        ];

        $user = wp_signon($creds, is_ssl());
        if (is_wp_error($user)) wp_die(esc_html($user->get_error_message()));

        wp_safe_redirect(cc_get_auth_page_url('minha-conta-mapa', '/minha-conta-mapa'));
        exit;
    }

    if ($action === 'register') {
        if (!isset($_POST['cc_register_nonce']) || !wp_verify_nonce($_POST['cc_register_nonce'], 'cc_register')) {
            wp_die(__('Nonce inválido no cadastro.', 'cadastro-comunidades'));
        }

        $nome = sanitize_text_field($_POST['nome'] ?? '');
        $email = sanitize_email($_POST['email'] ?? '');
        $senha = $_POST['senha'] ?? '';
        $paroquia_id = absint($_POST['paroquia_existente'] ?? 0);

        if (!$nome || !$email || !is_email($email)) {
            wp_die(__('Nome e e-mail válidos são obrigatórios.', 'cadastro-comunidades'));
        }

        if (email_exists($email)) wp_die(__('Este e-mail já está cadastrado.', 'cadastro-comunidades'));
        if (!$senha) $senha = wp_generate_password(12, true);

        $login_base = sanitize_user(current(explode('@', $email)));
        $login = $login_base;
        $i = 1;
        while (username_exists($login)) {
            $login = $login_base . $i;
            $i++;
        }

        $user_id = wp_insert_user([
            'user_login' => $login,
            'display_name' => $nome,
            'user_email' => $email,
            'user_pass' => $senha,
            'role' => CC_ROLE_AGENTE_MAPA,
        ]);

        if (is_wp_error($user_id)) wp_die(esc_html($user_id->get_error_message()));

        if ($paroquia_id > 0) {
            update_user_meta($user_id, 'cc_paroquia_id', $paroquia_id);
        }

        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id, true);

        wp_safe_redirect(cc_get_auth_page_url('minha-conta-mapa', '/minha-conta-mapa'));
        exit;
    }

    if ($action === 'update_profile' && is_user_logged_in()) {
        if (!isset($_POST['cc_profile_nonce']) || !wp_verify_nonce($_POST['cc_profile_nonce'], 'cc_profile')) {
            wp_die(__('Nonce inválido no perfil.', 'cadastro-comunidades'));
        }

        $user_id = get_current_user_id();
        $nome = sanitize_text_field($_POST['nome'] ?? '');
        $email = sanitize_email($_POST['email'] ?? '');
        $paroquia_id = absint($_POST['paroquia_existente'] ?? 0);

        if (!$nome || !is_email($email)) wp_die(__('Nome e e-mail válidos são obrigatórios.', 'cadastro-comunidades'));

        wp_update_user(['ID' => $user_id, 'display_name' => $nome, 'user_email' => $email]);

        if ($paroquia_id > 0) {
            update_user_meta($user_id, 'cc_paroquia_id', $paroquia_id);
        } else {
            delete_user_meta($user_id, 'cc_paroquia_id');
        }

        wp_safe_redirect(cc_get_auth_page_url('minha-conta-mapa', '/minha-conta-mapa'));
        exit;
    }

    if ($action === 'observe_add' && is_user_logged_in()) {
        if (!isset($_POST['cc_observe_nonce']) || !wp_verify_nonce($_POST['cc_observe_nonce'], 'cc_observe')) {
            wp_die(__('Nonce inválido na observação.', 'cadastro-comunidades'));
        }

        $comunidade_id = absint($_POST['comunidade_id'] ?? 0);
        $post = get_post($comunidade_id);

        if (!$post || $post->post_type !== 'comunidade') {
            wp_die(__('Comunidade inválida.', 'cadastro-comunidades'));
        }

        cc_observar_comunidade(get_current_user_id(), $comunidade_id);
        wp_safe_redirect(cc_get_auth_page_url('minha-conta-mapa', '/minha-conta-mapa'));
        exit;
    }

    if ($action === 'observe_remove' && is_user_logged_in()) {
        if (!isset($_POST['cc_observe_nonce']) || !wp_verify_nonce($_POST['cc_observe_nonce'], 'cc_observe')) {
            wp_die(__('Nonce inválido na observação.', 'cadastro-comunidades'));
        }

        $comunidade_id = absint($_POST['comunidade_id'] ?? 0);
        cc_remover_observacao_comunidade(get_current_user_id(), $comunidade_id);

        wp_safe_redirect(cc_get_auth_page_url('minha-conta-mapa', '/minha-conta-mapa'));
        exit;
    }
}
add_action('init', 'cc_handle_custom_auth_forms');

function cc_get_paroquias_options() {
    return get_posts([
        'post_type' => 'comunidade',
        'posts_per_page' => -1,
        'post_status' => 'publish',
        'meta_query' => [[
            'key' => 'parent_paroquia',
            'compare' => 'NOT EXISTS',
        ]],
        'orderby' => 'title',
        'order' => 'ASC',
    ]);
}


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

function cc_render_social_buttons() {
    $providers = ['google' => 'Google', 'facebook' => 'Facebook', 'linkedin' => 'LinkedIn'];
    ob_start();

    $rendered = 0;
    
    echo '<div class="pt-3 border-t border-gray-100"><p class="text-sm text-gray-600">Ou entre com sua rede social:</p><div class="mt-4 grid grid-cols-1 sm:grid-cols-3 gap-2">';
    foreach ($providers as $provider => $label) {
        $url = cc_get_social_button_url($provider);
        if (!$url) continue;
        $rendered++;
        echo '<a class="' . esc_attr(cc_auth_button_class('secondary')) . '" href="' . esc_url($url) . '">' . esc_html(sprintf(__('Entrar com %s', 'cadastro-comunidades'), $label)) . '</a>';
    }
    echo '</div>';

    if ($rendered === 0) {
        echo '<p class="mt-3 text-sm text-amber-700">' . esc_html__('Nenhum login social configurado ainda. Preencha Client ID/Secret em Configurações > Mapa - Login Social.', 'cadastro-comunidades') . '</p>';
    }

    echo '</div>';

    return ob_get_clean();
}
add_shortcode('mapa-social-buttons', 'cc_render_social_buttons');

function cc_shortcode_login_mapa() {
    cc_enqueue_auth_ui_assets();

    if (is_user_logged_in()) {
        return '<div class="max-w-3xl mx-auto bg-white border border-gray-200 rounded-2xl p-6"><p class="text-gray-800">' . esc_html__('Você já está logado.', 'cadastro-comunidades') . ' <a class="text-indigo-700 font-semibold" href="' . esc_url(cc_get_auth_page_url('minha-conta-mapa', '/minha-conta-mapa')) . '">' . esc_html__('Ir para minha conta', 'cadastro-comunidades') . '</a></p></div>';
    }

    ob_start();
    ?>
    <div class="max-w-3xl mx-auto bg-white border border-gray-200 shadow-sm rounded-2xl p-6 sm:p-8 space-y-6">
        <div>
            <h2 class="text-2xl font-bold text-gray-800"><?php esc_html_e('Entrar no Mapa', 'cadastro-comunidades'); ?></h2>
            <p class="text-gray-600 mt-1"><?php esc_html_e('Use seu e-mail/usuário e senha para acessar sua conta.', 'cadastro-comunidades'); ?></p>
        </div>

        <form method="post" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700"><?php esc_html_e('E-mail ou usuário', 'cadastro-comunidades'); ?></label>
                <input type="text" name="email" required class="<?php echo esc_attr(cc_auth_input_class()); ?>">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700"><?php esc_html_e('Senha', 'cadastro-comunidades'); ?></label>
                <input type="password" name="senha" required class="<?php echo esc_attr(cc_auth_input_class()); ?>">
            </div>

            <?php wp_nonce_field('cc_login', 'cc_login_nonce'); ?>
            <input type="hidden" name="cc_auth_action" value="login">

            <button type="submit" class="<?php echo esc_attr(cc_auth_button_class()); ?> w-full sm:w-auto"><?php esc_html_e('Entrar', 'cadastro-comunidades'); ?></button>
        </form>

        <?php echo cc_render_social_buttons(); ?>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('login-mapa', 'cc_shortcode_login_mapa');

function cc_shortcode_cadastro_mapa() {
    cc_enqueue_auth_ui_assets();

    if (is_user_logged_in()) {
        return '<div class="max-w-3xl mx-auto bg-white border border-gray-200 rounded-2xl p-6"><p class="text-gray-800">' . esc_html__('Você já está logado.', 'cadastro-comunidades') . '</p></div>';
    }

    $paroquias = cc_get_paroquias_options();

    ob_start();
    ?>
    <div class="max-w-3xl mx-auto bg-white border border-gray-200 shadow-sm rounded-2xl p-6 sm:p-8 space-y-6">
        <div>
            <h2 class="text-2xl font-bold text-gray-800"><?php esc_html_e('Criar conta no Mapa', 'cadastro-comunidades'); ?></h2>
            <p class="text-gray-600 mt-1"><?php esc_html_e('Preencha os dados abaixo. Se tiver dificuldade, peça ajuda na sua comunidade/paróquia.', 'cadastro-comunidades'); ?></p>
        </div>

        <form method="post" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700"><?php esc_html_e('Nome completo', 'cadastro-comunidades'); ?></label>
                <input type="text" name="nome" required class="<?php echo esc_attr(cc_auth_input_class()); ?>">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700"><?php esc_html_e('E-mail', 'cadastro-comunidades'); ?></label>
                <input type="email" name="email" required class="<?php echo esc_attr(cc_auth_input_class()); ?>">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700"><?php esc_html_e('Senha (opcional)', 'cadastro-comunidades'); ?></label>
                <input type="password" name="senha" class="<?php echo esc_attr(cc_auth_input_class()); ?>">
                <p class="text-xs text-gray-500 mt-1"><?php esc_html_e('Se não preencher, o sistema gera uma senha segura automaticamente.', 'cadastro-comunidades'); ?></p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700"><?php esc_html_e('Paróquia (opcional)', 'cadastro-comunidades'); ?></label>
                <select name="paroquia_existente" class="<?php echo esc_attr(cc_auth_input_class()); ?>">
                    <option value=""><?php esc_html_e('Sem vínculo de paróquia', 'cadastro-comunidades'); ?></option>
                    <?php foreach ($paroquias as $paroquia): ?>
                        <option value="<?php echo esc_attr($paroquia->ID); ?>"><?php echo esc_html($paroquia->post_title); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <?php wp_nonce_field('cc_register', 'cc_register_nonce'); ?>
            <input type="hidden" name="cc_auth_action" value="register">
            <button type="submit" class="<?php echo esc_attr(cc_auth_button_class()); ?> w-full sm:w-auto"><?php esc_html_e('Cadastrar', 'cadastro-comunidades'); ?></button>
        </form>

        <div class="pt-3 border-t border-gray-100">
            <p class="text-sm text-gray-600"><?php esc_html_e('Ou cadastre-se/entre com rede social:', 'cadastro-comunidades'); ?></p>
            <?php echo cc_render_social_buttons(); ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('cadastro-mapa', 'cc_shortcode_cadastro_mapa');

function cc_get_comunidades_do_usuario($user_id) {
    return get_posts([
        'post_type' => 'comunidade',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'author' => $user_id,
        'orderby' => 'date',
        'order' => 'DESC',
    ]);
}

function cc_get_comunidades_observadas($user_id) {
    $ids = cc_listar_comunidades_observadas_ids($user_id);
    if (empty($ids)) return [];

    return get_posts([
        'post_type' => 'comunidade',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'post__in' => $ids,
        'orderby' => 'title',
        'order' => 'ASC',
    ]);
}

function cc_get_alteracoes_do_usuario($user_id, $filtros = []) {
    global $wpdb;

    $table = $wpdb->prefix . 'mapa_alteracoes';
    $paroquia_id = (int) get_user_meta($user_id, 'cc_paroquia_id', true);
    $observadas_ids = cc_listar_comunidades_observadas_ids($user_id);
    $is_admin = user_can($user_id, 'manage_options');

    $query = "
        SELECT a.id, a.comunidade_id, p.post_title AS comunidade_nome, u.display_name AS usuario_nome, a.created_at
        FROM $table a
        LEFT JOIN {$wpdb->posts} p ON p.ID = a.comunidade_id
        LEFT JOIN {$wpdb->users} u ON u.ID = a.user_id
        WHERE 1=1
    ";

    $params = [];

    if (!$is_admin) {
        $conds = ['a.user_id = %d'];
        $params[] = (int) $user_id;

        if ($paroquia_id > 0) {
            $conds[] = 'a.paroquia_id = %d';
            $params[] = $paroquia_id;
            $conds[] = 'EXISTS (SELECT 1 FROM {$wpdb->postmeta} pm WHERE pm.post_id = a.comunidade_id AND pm.meta_key = %s AND pm.meta_value = %d)';
            $params[] = 'parent_paroquia';
            $params[] = $paroquia_id;
        }

        if (!empty($observadas_ids)) {
            $in = implode(',', array_fill(0, count($observadas_ids), '%d'));
            $conds[] = "a.comunidade_id IN ($in)";
            $params = array_merge($params, $observadas_ids);
        }

        $query .= ' AND (' . implode(' OR ', $conds) . ')';
    }

    $comunidade_id = absint($filtros['comunidade_id'] ?? 0);
    if ($comunidade_id > 0) {
        $query .= ' AND a.comunidade_id = %d';
        $params[] = $comunidade_id;
    }

    $data_inicio = sanitize_text_field($filtros['data_inicio'] ?? '');
    if (!empty($data_inicio)) {
        $query .= ' AND DATE(a.created_at) >= %s';
        $params[] = $data_inicio;
    }

    $data_fim = sanitize_text_field($filtros['data_fim'] ?? '');
    if (!empty($data_fim)) {
        $query .= ' AND DATE(a.created_at) <= %s';
        $params[] = $data_fim;
    }

    $query .= ' ORDER BY a.created_at DESC LIMIT 200';

    return empty($params) ? $wpdb->get_results($query) : $wpdb->get_results($wpdb->prepare($query, ...$params));
}

function cc_shortcode_minha_conta_mapa() {
    cc_enqueue_auth_ui_assets();

    if (!is_user_logged_in()) {
        return '<div class="max-w-3xl mx-auto bg-white border border-gray-200 rounded-2xl p-6"><p class="text-gray-800">' . esc_html__('Faça login para acessar sua conta.', 'cadastro-comunidades') . ' <a class="text-indigo-700 font-semibold" href="' . esc_url(cc_get_auth_page_url('login', '/login')) . '">' . esc_html__('Entrar', 'cadastro-comunidades') . '</a></p></div>';
    }

    $user = wp_get_current_user();
    $paroquias = cc_get_paroquias_options();

    $filtros = [
        'comunidade_id' => absint($_GET['f_comunidade'] ?? 0),
        'data_inicio' => sanitize_text_field($_GET['f_data_inicio'] ?? ''),
        'data_fim' => sanitize_text_field($_GET['f_data_fim'] ?? ''),
    ];

    $comunidades_criadas = cc_get_comunidades_do_usuario($user->ID);
    $comunidades_observadas = cc_get_comunidades_observadas($user->ID);
    $alteracoes = cc_get_alteracoes_do_usuario($user->ID, $filtros);
    $all_comunidades = get_posts([
        'post_type' => 'comunidade',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'orderby' => 'title',
        'order' => 'ASC',
    ]);

    ob_start();
    ?>
    <div class="max-w-5xl mx-auto space-y-6">
        <section class="bg-white border border-gray-200 rounded-2xl p-6 sm:p-8">
            <h3 class="text-2xl font-bold text-gray-800"><?php esc_html_e('Minha Conta Mapa', 'cadastro-comunidades'); ?></h3>
            <p class="text-gray-600 mt-1"><?php esc_html_e('Aqui você atualiza seus dados e acompanha suas comunidades.', 'cadastro-comunidades'); ?></p>

            <form method="post" class="mt-5 grid md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700"><?php esc_html_e('Nome', 'cadastro-comunidades'); ?></label>
                    <input type="text" name="nome" value="<?php echo esc_attr($user->display_name); ?>" required class="<?php echo esc_attr(cc_auth_input_class()); ?>">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700"><?php esc_html_e('E-mail', 'cadastro-comunidades'); ?></label>
                    <input type="email" name="email" value="<?php echo esc_attr($user->user_email); ?>" required class="<?php echo esc_attr(cc_auth_input_class()); ?>">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700"><?php esc_html_e('Paróquia (opcional)', 'cadastro-comunidades'); ?></label>
                    <select name="paroquia_existente" class="<?php echo esc_attr(cc_auth_input_class()); ?>">
                        <option value=""><?php esc_html_e('Sem vínculo de paróquia', 'cadastro-comunidades'); ?></option>
                        <?php $current_paroquia = (int) get_user_meta($user->ID, 'cc_paroquia_id', true); ?>
                        <?php foreach ($paroquias as $paroquia): ?>
                            <option value="<?php echo esc_attr($paroquia->ID); ?>" <?php selected($current_paroquia, (int) $paroquia->ID); ?>><?php echo esc_html($paroquia->post_title); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <?php wp_nonce_field('cc_profile', 'cc_profile_nonce'); ?>
                    <input type="hidden" name="cc_auth_action" value="update_profile">
                    <button type="submit" class="<?php echo esc_attr(cc_auth_button_class()); ?>"><?php esc_html_e('Salvar perfil', 'cadastro-comunidades'); ?></button>
                </div>
            </form>
        </section>

        <section class="bg-white border border-gray-200 rounded-2xl p-6 sm:p-8">
            <h4 class="text-xl font-semibold text-gray-800"><?php esc_html_e('Comunidades cadastradas por você', 'cadastro-comunidades'); ?></h4>
            <ul class="mt-3 space-y-2 text-gray-700 list-disc pl-5">
                <?php foreach ($comunidades_criadas as $comunidade): ?>
                    <li><?php echo esc_html($comunidade->post_title); ?> (#<?php echo (int) $comunidade->ID; ?>)</li>
                <?php endforeach; ?>
                <?php if (empty($comunidades_criadas)): ?><li><?php esc_html_e('Nenhuma comunidade cadastrada ainda.', 'cadastro-comunidades'); ?></li><?php endif; ?>
            </ul>
        </section>

        <section class="bg-white border border-gray-200 rounded-2xl p-6 sm:p-8 space-y-4">
            <h4 class="text-xl font-semibold text-gray-800"><?php esc_html_e('Observação de comunidades', 'cadastro-comunidades'); ?></h4>
            <p class="text-gray-600"><?php esc_html_e('Você pode acompanhar alterações em comunidades mesmo sem ser o criador.', 'cadastro-comunidades'); ?></p>

            <form method="post" class="flex flex-col sm:flex-row gap-3 items-stretch sm:items-end">
                <div class="flex-1">
                    <label class="block text-sm font-medium text-gray-700"><?php esc_html_e('Selecionar comunidade', 'cadastro-comunidades'); ?></label>
                    <select name="comunidade_id" required class="<?php echo esc_attr(cc_auth_input_class()); ?>">
                        <option value=""><?php esc_html_e('Selecione uma comunidade', 'cadastro-comunidades'); ?></option>
                        <?php foreach ($all_comunidades as $comunidade): ?>
                            <option value="<?php echo esc_attr($comunidade->ID); ?>"><?php echo esc_html($comunidade->post_title); ?> (#<?php echo (int) $comunidade->ID; ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <?php wp_nonce_field('cc_observe', 'cc_observe_nonce'); ?>
                    <input type="hidden" name="cc_auth_action" value="observe_add">
                    <button type="submit" class="<?php echo esc_attr(cc_auth_button_class()); ?> w-full sm:w-auto"><?php esc_html_e('Adicionar observação', 'cadastro-comunidades'); ?></button>
                </div>
            </form>

            <ul class="space-y-2">
                <?php foreach ($comunidades_observadas as $comunidade): ?>
                    <li class="flex items-center justify-between gap-3 rounded-xl border border-gray-200 p-3">
                        <span class="text-gray-800"><?php echo esc_html($comunidade->post_title); ?></span>
                        <form method="post">
                            <input type="hidden" name="comunidade_id" value="<?php echo (int) $comunidade->ID; ?>">
                            <?php wp_nonce_field('cc_observe', 'cc_observe_nonce'); ?>
                            <input type="hidden" name="cc_auth_action" value="observe_remove">
                            <button type="submit" class="<?php echo esc_attr(cc_auth_button_class('danger')); ?>"><?php esc_html_e('Remover', 'cadastro-comunidades'); ?></button>
                        </form>
                    </li>
                <?php endforeach; ?>
                <?php if (empty($comunidades_observadas)): ?><li class="text-gray-600"><?php esc_html_e('Nenhuma comunidade observada.', 'cadastro-comunidades'); ?></li><?php endif; ?>
            </ul>
        </section>

        <section class="bg-white border border-gray-200 rounded-2xl p-6 sm:p-8 space-y-4">
            <h4 class="text-xl font-semibold text-gray-800"><?php esc_html_e('Observação de alterações', 'cadastro-comunidades'); ?></h4>
            <p class="text-gray-600"><?php esc_html_e('Filtre por comunidade e período para encontrar atualizações com facilidade.', 'cadastro-comunidades'); ?></p>

            <form method="get" class="grid md:grid-cols-4 gap-3 items-end">
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700"><?php esc_html_e('Comunidade', 'cadastro-comunidades'); ?></label>
                    <select name="f_comunidade" class="<?php echo esc_attr(cc_auth_input_class()); ?>">
                        <option value="0"><?php esc_html_e('Todas', 'cadastro-comunidades'); ?></option>
                        <?php foreach ($all_comunidades as $comunidade): ?>
                            <option value="<?php echo (int) $comunidade->ID; ?>" <?php selected($filtros['comunidade_id'], (int) $comunidade->ID); ?>><?php echo esc_html($comunidade->post_title); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700"><?php esc_html_e('Data início', 'cadastro-comunidades'); ?></label>
                    <input type="date" name="f_data_inicio" value="<?php echo esc_attr($filtros['data_inicio']); ?>" class="<?php echo esc_attr(cc_auth_input_class()); ?>">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700"><?php esc_html_e('Data fim', 'cadastro-comunidades'); ?></label>
                    <input type="date" name="f_data_fim" value="<?php echo esc_attr($filtros['data_fim']); ?>" class="<?php echo esc_attr(cc_auth_input_class()); ?>">
                </div>
                <div class="md:col-span-4">
                    <button type="submit" class="<?php echo esc_attr(cc_auth_button_class()); ?>"><?php esc_html_e('Aplicar filtros', 'cadastro-comunidades'); ?></button>
                </div>
            </form>

            <ul class="space-y-2">
                <?php foreach ($alteracoes as $alteracao): ?>
                    <li class="rounded-xl border border-gray-200 p-3 text-gray-800">
                        <strong><?php echo esc_html($alteracao->comunidade_nome ?: 'Comunidade removida'); ?></strong>
                        <span class="text-gray-500"> — <?php echo esc_html($alteracao->usuario_nome ?: 'Usuário removido'); ?> — <?php echo esc_html(mysql2date('d/m/Y H:i', $alteracao->created_at)); ?></span>
                    </li>
                <?php endforeach; ?>
                <?php if (empty($alteracoes)): ?><li class="text-gray-600"><?php esc_html_e('Sem alterações para os filtros selecionados.', 'cadastro-comunidades'); ?></li><?php endif; ?>
            </ul>
        </section>
    </div>
    <?php

    return ob_get_clean();
}
add_shortcode('minha-conta-mapa', 'cc_shortcode_minha_conta_mapa');
