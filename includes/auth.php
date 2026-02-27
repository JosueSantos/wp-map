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
        'esqueci-senha' => ['esqueci-senha-mapa', 'esqueci-senha'],
        'redefinir-senha' => ['redefinir-senha-mapa', 'redefinir-senha'],
        'alterar-senha' => ['alterar-senha-mapa', 'alterar-senha'],
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
        'esqueci-senha-mapa' => ['title' => __('Esqueci a senha', 'cadastro-comunidades'), 'content' => '[esqueci-senha-mapa]'],
        'redefinir-senha-mapa' => ['title' => __('Redefinir senha', 'cadastro-comunidades'), 'content' => '[redefinir-senha-mapa]'],
        'alterar-senha-mapa' => ['title' => __('Alterar senha', 'cadastro-comunidades'), 'content' => '[alterar-senha-mapa]'],
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
            wp_safe_redirect(add_query_arg('cc_auth_notice', 'login_nonce', cc_get_auth_page_url('login', '/login')));
            exit;
        }

        $creds = [
            'user_login' => sanitize_text_field($_POST['email'] ?? ''),
            'user_password' => $_POST['senha'] ?? '',
            'remember' => true,
        ];

        $user = wp_signon($creds, is_ssl());
        if (is_wp_error($user)) {
            wp_safe_redirect(add_query_arg('cc_auth_notice', 'login_invalid', cc_get_auth_page_url('login', '/login')));
            exit;
        }

        wp_safe_redirect(cc_get_auth_page_url('minha-conta-mapa', '/minha-conta-mapa'));
        exit;
    }

    if ($action === 'register') {
        if (!isset($_POST['cc_register_nonce']) || !wp_verify_nonce($_POST['cc_register_nonce'], 'cc_register')) {
            wp_safe_redirect(add_query_arg('cc_auth_notice', 'register_nonce', cc_get_auth_page_url('cadastro', '/cadastro')));
            exit;
        }

        $nome = sanitize_text_field($_POST['nome'] ?? '');
        $email = sanitize_email($_POST['email'] ?? '');
        $senha = $_POST['senha'] ?? '';
        $paroquia_id = absint($_POST['paroquia_existente'] ?? 0);

        if (!$nome || !$email || !is_email($email)) {
            wp_safe_redirect(add_query_arg('cc_auth_notice', 'register_invalid_data', cc_get_auth_page_url('cadastro', '/cadastro')));
            exit;
        }

        if (email_exists($email)) {
            wp_safe_redirect(add_query_arg('cc_auth_notice', 'register_email_exists', cc_get_auth_page_url('cadastro', '/cadastro')));
            exit;
        }
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

        if (is_wp_error($user_id)) {
            wp_safe_redirect(add_query_arg('cc_auth_notice', 'register_error', cc_get_auth_page_url('cadastro', '/cadastro')));
            exit;
        }

        if ($paroquia_id > 0) {
            update_user_meta($user_id, 'cc_paroquia_id', $paroquia_id);
            cc_observar_comunidade_com_vinculos($user_id, $paroquia_id);
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
            cc_observar_comunidade_com_vinculos($user_id, $paroquia_id);
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

        cc_observar_comunidade_com_vinculos(get_current_user_id(), $comunidade_id);
        wp_safe_redirect(cc_get_auth_page_url('minha-conta-mapa', '/minha-conta-mapa'));
        exit;
    }

    if ($action === 'forgot_password') {
        if (!isset($_POST['cc_forgot_password_nonce']) || !wp_verify_nonce($_POST['cc_forgot_password_nonce'], 'cc_forgot_password')) {
            wp_die(__('Nonce inválido na recuperação de senha.', 'cadastro-comunidades'));
        }

        $email = sanitize_email($_POST['email'] ?? '');
        $redirect_url = cc_get_auth_page_url('esqueci-senha', '/esqueci-senha');
        $notice = 'forgot_sent';

        if (!$email || !is_email($email)) {
            $notice = 'forgot_error';
        } else {
            $user = get_user_by('email', $email);
            if ($user) {
                $key = get_password_reset_key($user);
                if (!is_wp_error($key)) {
                    $reset_url = add_query_arg([
                        'login' => rawurlencode($user->user_login),
                        'key' => rawurlencode($key),
                    ], cc_get_auth_page_url('redefinir-senha', '/redefinir-senha'));

                    $message = sprintf(
                        "Olá, %s.\n\nRecebemos um pedido para redefinir sua senha no Mapa.\n\nClique no link para criar uma nova senha:\n%s\n\nSe você não solicitou, pode ignorar este e-mail.",
                        $user->display_name ?: $user->user_login,
                        $reset_url
                    );

                    wp_mail($user->user_email, __('Redefinição de senha - Mapa', 'cadastro-comunidades'), $message);
                }
            }
        }

        wp_safe_redirect(add_query_arg('cc_auth_notice', $notice, $redirect_url));
        exit;
    }

    if ($action === 'reset_password') {
        if (!isset($_POST['cc_reset_password_nonce']) || !wp_verify_nonce($_POST['cc_reset_password_nonce'], 'cc_reset_password')) {
            wp_die(__('Nonce inválido na redefinição de senha.', 'cadastro-comunidades'));
        }

        $login = sanitize_text_field(wp_unslash($_POST['login'] ?? ''));
        $key = sanitize_text_field(wp_unslash($_POST['key'] ?? ''));
        $new_password = (string) ($_POST['nova_senha'] ?? '');
        $new_password_confirm = (string) ($_POST['confirmar_nova_senha'] ?? '');

        $user = check_password_reset_key($key, $login);
        if (is_wp_error($user)) {
            wp_die(__('Link de redefinição inválido ou expirado.', 'cadastro-comunidades'));
        }

        if (strlen($new_password) < 6 || $new_password !== $new_password_confirm) {
            wp_die(__('As senhas devem coincidir e ter pelo menos 6 caracteres.', 'cadastro-comunidades'));
        }

        reset_password($user, $new_password);
        wp_safe_redirect(add_query_arg('cc_auth_notice', 'reset_ok', cc_get_auth_page_url('login', '/login')));
        exit;
    }

    if ($action === 'change_password' && is_user_logged_in()) {
        if (!isset($_POST['cc_change_password_nonce']) || !wp_verify_nonce($_POST['cc_change_password_nonce'], 'cc_change_password')) {
            wp_die(__('Nonce inválido na alteração de senha.', 'cadastro-comunidades'));
        }

        $user = wp_get_current_user();
        $current_password = (string) ($_POST['senha_atual'] ?? '');
        $new_password = (string) ($_POST['nova_senha'] ?? '');
        $new_password_confirm = (string) ($_POST['confirmar_nova_senha'] ?? '');

        if (!wp_check_password($current_password, $user->user_pass, $user->ID)) {
            wp_die(__('Senha atual inválida.', 'cadastro-comunidades'));
        }

        if (strlen($new_password) < 6 || $new_password !== $new_password_confirm) {
            wp_die(__('As senhas devem coincidir e ter pelo menos 6 caracteres.', 'cadastro-comunidades'));
        }

        wp_set_password($new_password, $user->ID);
        wp_set_current_user($user->ID);
        wp_set_auth_cookie($user->ID, true);

        wp_safe_redirect(add_query_arg('cc_auth_notice', 'password_changed', cc_get_auth_page_url('minha-conta-mapa', '/minha-conta-mapa')));
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

function cc_handle_logout() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['cc_auth_action'])) return;
    if (sanitize_key($_POST['cc_auth_action']) !== 'logout') return;

    if (!is_user_logged_in()) {
        wp_safe_redirect(cc_get_auth_page_url('login', '/login'));
        exit;
    }

    if (!isset($_POST['cc_logout_nonce']) || !wp_verify_nonce($_POST['cc_logout_nonce'], 'cc_logout')) {
        wp_die(__('Nonce inválido no logout.', 'cadastro-comunidades'));
    }

    wp_logout();

    wp_safe_redirect(add_query_arg('cc_auth_notice', 'logged_out', cc_get_auth_page_url('login', '/login')));
    exit;
}
add_action('init', 'cc_handle_logout');

function cc_observar_comunidade_com_vinculos($user_id, $comunidade_id) {
    $comunidade_id = (int) $comunidade_id;
    if ($comunidade_id <= 0) return;

    cc_observar_comunidade($user_id, $comunidade_id);

    $tipo_comunidade = wp_get_post_terms($comunidade_id, 'tipo_comunidade', ['fields' => 'slugs']);
    $is_paroquia = in_array('paroquia', (array) $tipo_comunidade, true);

    if (!$is_paroquia) return;

    $capelas_vinculadas = get_posts([
        'post_type' => 'comunidade',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'fields' => 'ids',
        'meta_query' => [[
            'key' => 'parent_paroquia',
            'value' => $comunidade_id,
            'compare' => '=',
        ]],
    ]);

    foreach ($capelas_vinculadas as $capela_id) {
        cc_observar_comunidade($user_id, (int) $capela_id);
    }
}

function cc_get_editar_comunidade_url($comunidade_id) {
    return cc_get_editar_comunidade_url_custom($comunidade_id, '');
}

function cc_get_editar_comunidade_url_custom($comunidade_id, $base_url = '') {
    $base_url = esc_url_raw((string) $base_url);
    if (!$base_url) {
        $base_url = function_exists('get_permalink') ? get_permalink(get_page_by_path('cadastro-comunidade')) : '';
    }

    if (!$base_url) {
        $base_url = home_url('/cadastro-comunidade/');
    }

    return add_query_arg('editar_comunidade', (int) $comunidade_id, $base_url);
}

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

function cc_render_social_buttons() {
    $providers = ['google' => 'Google', 'facebook' => 'Facebook', 'linkedin' => 'LinkedIn'];
    $available_providers = [];

    foreach ($providers as $provider => $label) {
        $url = cc_get_social_button_url($provider);
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

function cc_shortcode_login_mapa() {
    cc_enqueue_auth_ui_assets();
    $notice = sanitize_text_field($_GET['cc_auth_notice'] ?? '');

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

        <?php echo cc_render_auth_notice($notice); ?>

        <form method="post" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700"><?php esc_html_e('E-mail ou usuário', 'cadastro-comunidades'); ?></label>
                <input type="text" name="email" required class="<?php echo esc_attr(cc_auth_input_class()); ?>">
            </div>

            <?php echo cc_render_password_field('senha', __('Senha', 'cadastro-comunidades'), true); ?>

            <?php wp_nonce_field('cc_login', 'cc_login_nonce'); ?>
            <input type="hidden" name="cc_auth_action" value="login">

            <button type="submit" class="<?php echo esc_attr(cc_auth_button_class()); ?> w-full sm:w-auto"><?php esc_html_e('Entrar', 'cadastro-comunidades'); ?></button>
            <a class="ml-0 sm:ml-3 text-indigo-700 underline font-medium" href="<?php echo esc_url(cc_get_auth_page_url('esqueci-senha', '/esqueci-senha')); ?>"><?php esc_html_e('Esqueci minha senha', 'cadastro-comunidades'); ?></a>
        </form>

        <?php echo cc_render_social_buttons(); ?>
        <?php echo cc_render_password_toggle_script(); ?>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('login-mapa', 'cc_shortcode_login_mapa');

function cc_shortcode_cadastro_mapa() {
    cc_enqueue_auth_ui_assets();
    $notice = sanitize_text_field($_GET['cc_auth_notice'] ?? '');

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

        <?php echo cc_render_social_buttons(); ?>
        <?php echo cc_render_password_toggle_script(); ?>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('cadastro-mapa', 'cc_shortcode_cadastro_mapa');

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

function cc_shortcode_minha_conta_mapa($atts = []) {
    cc_enqueue_auth_ui_assets();

    $atts = shortcode_atts([
        'url_editar_comunidade' => '',
    ], $atts, 'minha-conta-mapa');

    $url_editar_comunidade = esc_url_raw($atts['url_editar_comunidade']);

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
                    <a class="ml-0 sm:ml-3 text-indigo-700 underline font-medium" href="<?php echo esc_url(cc_get_auth_page_url('alterar-senha', '/alterar-senha')); ?>"><?php esc_html_e('Alterar senha', 'cadastro-comunidades'); ?></a>
                </div>
            </form>

            <form method="post" class="mt-4">
                <?php wp_nonce_field('cc_logout', 'cc_logout_nonce'); ?>
                <input type="hidden" name="cc_auth_action" value="logout">
                <button type="submit" class="<?php echo esc_attr(cc_auth_button_class('danger')); ?>"><?php esc_html_e('Sair da conta', 'cadastro-comunidades'); ?></button>
            </form>
        </section>

        <section class="bg-white border border-gray-200 rounded-2xl p-6 sm:p-8">
            <h4 class="text-xl font-semibold text-gray-800"><?php esc_html_e('Comunidades cadastradas por você', 'cadastro-comunidades'); ?></h4>
            <ul class="mt-3 space-y-2">
                <?php foreach ($comunidades_criadas as $comunidade): ?>
                    <li class="flex items-center justify-between gap-3 rounded-xl border border-gray-200 p-3 text-gray-800">
                        <span><?php echo esc_html($comunidade->post_title); ?> (#<?php echo (int) $comunidade->ID; ?>)</span>
                        <a href="<?php echo esc_url(cc_get_editar_comunidade_url_custom($comunidade->ID, $url_editar_comunidade)); ?>" class="<?php echo esc_attr(cc_auth_button_class('secondary')); ?>"><?php esc_html_e('Editar', 'cadastro-comunidades'); ?></a>
                    </li>
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
                    <input id="cc-observe-comunidade-nome" type="text" name="comunidade_nome" list="cc-comunidades-datalist" required class="<?php echo esc_attr(cc_auth_input_class()); ?>" placeholder="<?php esc_attr_e('Digite para buscar comunidade', 'cadastro-comunidades'); ?>">
                    <input id="cc-observe-comunidade-id" type="hidden" name="comunidade_id">
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
                        <div class="flex items-center gap-2">
                            <a href="<?php echo esc_url(cc_get_editar_comunidade_url_custom($comunidade->ID, $url_editar_comunidade)); ?>" class="<?php echo esc_attr(cc_auth_button_class('secondary')); ?>"><?php esc_html_e('Editar', 'cadastro-comunidades'); ?></a>
                            <form method="post">
                                <input type="hidden" name="comunidade_id" value="<?php echo (int) $comunidade->ID; ?>">
                                <?php wp_nonce_field('cc_observe', 'cc_observe_nonce'); ?>
                                <input type="hidden" name="cc_auth_action" value="observe_remove">
                                <button type="submit" class="<?php echo esc_attr(cc_auth_button_class('danger')); ?>"><?php esc_html_e('Remover', 'cadastro-comunidades'); ?></button>
                            </form>
                        </div>
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
                    <?php $filtro_label = ''; ?>
                    <?php if ($filtros['comunidade_id'] > 0) { foreach ($all_comunidades as $comunidade_item) { if ((int) $comunidade_item->ID === (int) $filtros['comunidade_id']) { $filtro_label = $comunidade_item->post_title . ' (#' . (int) $comunidade_item->ID . ')'; break; } } } ?>
                    <input id="cc-filtro-comunidade-nome" type="text" name="f_comunidade_nome" list="cc-comunidades-datalist" class="<?php echo esc_attr(cc_auth_input_class()); ?>" placeholder="<?php esc_attr_e('Todas', 'cadastro-comunidades'); ?>" value="<?php echo esc_attr($filtro_label); ?>">
                    <input id="cc-filtro-comunidade-id" type="hidden" name="f_comunidade" value="<?php echo (int) $filtros['comunidade_id']; ?>">
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

        <datalist id="cc-comunidades-datalist">
            <?php foreach ($all_comunidades as $comunidade): ?>
                <option value="<?php echo esc_attr($comunidade->post_title . ' (#' . (int) $comunidade->ID . ')'); ?>"></option>
            <?php endforeach; ?>
        </datalist>

        <script>
            document.addEventListener('DOMContentLoaded', function () {
                function extractComunidadeId(value) {
                    const match = String(value || '').match(/#(\d+)\)/);
                    return match ? match[1] : '';
                }

                const observeName = document.getElementById('cc-observe-comunidade-nome');
                const observeId = document.getElementById('cc-observe-comunidade-id');
                observeName?.closest('form')?.addEventListener('submit', function () {
                    if (observeId) observeId.value = extractComunidadeId(observeName?.value);
                });

                const filtroName = document.getElementById('cc-filtro-comunidade-nome');
                const filtroId = document.getElementById('cc-filtro-comunidade-id');
                filtroName?.closest('form')?.addEventListener('submit', function () {
                    if (filtroId) filtroId.value = extractComunidadeId(filtroName?.value) || '0';
                });
            });
        </script>
    </div>
    <?php

    return ob_get_clean();
}
add_shortcode('minha-conta-mapa', 'cc_shortcode_minha_conta_mapa');
