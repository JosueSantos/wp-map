<?php

function cc_get_oauth_state($redirect_to = '') {
    $state = wp_generate_password(20, false, false);
    set_transient('cc_oauth_state_' . $state, [
        'redirect_to' => $redirect_to,
    ], 10 * MINUTE_IN_SECONDS);
    return $state;
}

function cc_validate_oauth_state($state) {
    $state = sanitize_text_field($state);
    if (!$state) return false;
    $key = 'cc_oauth_state_' . $state;
    $data = get_transient($key);
    if (!$data) return false;
    delete_transient($key);
    if (!is_array($data)) {
        return ['redirect_to' => ''];
    }

    return [
        'redirect_to' => wp_validate_redirect((string) ($data['redirect_to'] ?? ''), ''),
    ];
}

function cc_get_social_button_url($provider, $redirect_to = '') {
    $settings = cc_get_auth_options();
    $state = cc_get_oauth_state($redirect_to);
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

    $state_data = cc_validate_oauth_state($state);
    if (!$code || !$state_data) {
        wp_die(esc_html__('Falha de segurança OAuth (state inválido).', 'cadastro-comunidades'));
    }

    $user_data = cc_fetch_oauth_user_data($provider, $code);
    if (is_wp_error($user_data)) wp_die(esc_html($user_data->get_error_message()));

    $user = cc_upsert_social_user($user_data, $provider);
    if (is_wp_error($user)) wp_die(esc_html($user->get_error_message()));

    wp_set_current_user($user->ID);
    wp_set_auth_cookie($user->ID, true);
    $redirect_url = $state_data['redirect_to'] ?: cc_get_auth_page_url('minha-conta', '/minha-conta');
    wp_safe_redirect($redirect_url);
    exit;
}
add_action('init', 'cc_maybe_handle_oauth_callback');

