<?php

function cc_handle_custom_auth_forms() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['cc_auth_action'])) return;

    $action = sanitize_key($_POST['cc_auth_action']);

    if ($action === 'login') {
        if (!isset($_POST['cc_login_nonce']) || !wp_verify_nonce($_POST['cc_login_nonce'], 'cc_login')) {
            $login_url = cc_with_redirect_to(cc_get_auth_page_url('login', '/login'), cc_get_safe_redirect_url_from_request());
            wp_safe_redirect(add_query_arg('cc_auth_notice', 'login_nonce', $login_url));
            exit;
        }

        $creds = [
            'user_login' => sanitize_text_field($_POST['email'] ?? ''),
            'user_password' => $_POST['senha'] ?? '',
            'remember' => true,
        ];

        $user = wp_signon($creds, is_ssl());
        if (is_wp_error($user)) {
            $login_url = cc_with_redirect_to(cc_get_auth_page_url('login', '/login'), cc_get_safe_redirect_url_from_request());
            wp_safe_redirect(add_query_arg('cc_auth_notice', 'login_invalid', $login_url));
            exit;
        }

        wp_safe_redirect(cc_get_auth_success_redirect_url());
        exit;
    }

    if ($action === 'register') {
        if (!isset($_POST['cc_register_nonce']) || !wp_verify_nonce($_POST['cc_register_nonce'], 'cc_register')) {
            $register_url = cc_with_redirect_to(cc_get_auth_page_url('cadastro', '/cadastro'), cc_get_safe_redirect_url_from_request());
            wp_safe_redirect(add_query_arg('cc_auth_notice', 'register_nonce', $register_url));
            exit;
        }

        $nome = sanitize_text_field($_POST['nome'] ?? '');
        $email = sanitize_email($_POST['email'] ?? '');
        $senha = $_POST['senha'] ?? '';
        $paroquia_id = cc_extract_id_from_autocomplete($_POST['paroquia_existente'] ?? 0);

        if (!$nome || !$email || !is_email($email)) {
            $register_url = cc_with_redirect_to(cc_get_auth_page_url('cadastro', '/cadastro'), cc_get_safe_redirect_url_from_request());
            wp_safe_redirect(add_query_arg('cc_auth_notice', 'register_invalid_data', $register_url));
            exit;
        }

        if (email_exists($email)) {
            $register_url = cc_with_redirect_to(cc_get_auth_page_url('cadastro', '/cadastro'), cc_get_safe_redirect_url_from_request());
            wp_safe_redirect(add_query_arg('cc_auth_notice', 'register_email_exists', $register_url));
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
            $register_url = cc_with_redirect_to(cc_get_auth_page_url('cadastro', '/cadastro'), cc_get_safe_redirect_url_from_request());
            wp_safe_redirect(add_query_arg('cc_auth_notice', 'register_error', $register_url));
            exit;
        }

        if ($paroquia_id > 0) {
            update_user_meta($user_id, 'cc_paroquia_id', $paroquia_id);
            cc_observar_comunidade_com_vinculos($user_id, $paroquia_id);
        }

        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id, true);

        wp_safe_redirect(cc_get_auth_success_redirect_url());
        exit;
    }

    if ($action === 'update_profile' && is_user_logged_in()) {
        if (!isset($_POST['cc_profile_nonce']) || !wp_verify_nonce($_POST['cc_profile_nonce'], 'cc_profile')) {
            wp_die(__('Nonce inválido no perfil.', 'cadastro-comunidades'));
        }

        $user_id = get_current_user_id();
        $nome = sanitize_text_field($_POST['nome'] ?? '');
        $email = sanitize_email($_POST['email'] ?? '');
        $paroquia_id = cc_extract_id_from_autocomplete($_POST['paroquia_existente'] ?? 0);

        if (!$nome || !is_email($email)) wp_die(__('Nome e e-mail válidos são obrigatórios.', 'cadastro-comunidades'));

        wp_update_user(['ID' => $user_id, 'display_name' => $nome, 'user_email' => $email]);

        if ($paroquia_id > 0) {
            update_user_meta($user_id, 'cc_paroquia_id', $paroquia_id);
            cc_observar_comunidade_com_vinculos($user_id, $paroquia_id);
        } else {
            delete_user_meta($user_id, 'cc_paroquia_id');
        }

        wp_safe_redirect(cc_get_auth_page_url('minha-conta', '/minha-conta'));
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
        wp_safe_redirect(cc_get_auth_page_url('minha-conta', '/minha-conta'));
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

        wp_safe_redirect(add_query_arg('cc_auth_notice', 'password_changed', cc_get_auth_page_url('minha-conta', '/minha-conta')));
        exit;
    }

    if ($action === 'observe_remove' && is_user_logged_in()) {
        if (!isset($_POST['cc_observe_nonce']) || !wp_verify_nonce($_POST['cc_observe_nonce'], 'cc_observe')) {
            wp_die(__('Nonce inválido na observação.', 'cadastro-comunidades'));
        }

        $comunidade_id = absint($_POST['comunidade_id'] ?? 0);
        cc_remover_observacao_comunidade(get_current_user_id(), $comunidade_id);

        wp_safe_redirect(cc_get_auth_page_url('minha-conta', '/minha-conta'));
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

function cc_extract_id_from_autocomplete($raw_value) {
    if (is_numeric($raw_value)) {
        return absint($raw_value);
    }

    $value = sanitize_text_field((string) $raw_value);
    if (preg_match('/#(\d+)\)$/', $value, $matches)) {
        return absint($matches[1]);
    }

    return 0;
}


