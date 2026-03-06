<?php

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

