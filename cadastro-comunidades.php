<?php
/**
 * Plugin Name: Cadastro de Comunidades
 * Description: Gestão de Paróquias, Capelas e Eventos
 * Version: 1.1
 * Text Domain: cadastro-comunidades
 */

if (!defined('ABSPATH')) exit;

define('CC_PATH', plugin_dir_path(__FILE__));
define('CC_URL', plugin_dir_url(__FILE__));

function cc_load_textdomain() {
    load_plugin_textdomain('cadastro-comunidades', false, dirname(plugin_basename(__FILE__)) . '/languages');
}

add_action('plugins_loaded', 'cc_load_textdomain');

require_once CC_PATH . 'includes/meta-fields.php';
require_once CC_PATH . 'includes/post-types.php';
require_once CC_PATH . 'includes/taxonomies.php';
require_once CC_PATH . 'includes/permissions.php';

require_once CC_PATH . 'includes/db-alteracoes.php';
require_once CC_PATH . 'includes/auth.php';

require_once CC_PATH . 'includes/api/api-mapa.php';
require_once CC_PATH . 'includes/api/api-form.php';

require_once CC_PATH . 'includes/shortcodes/shortcodes-comunidade.php';
require_once CC_PATH . 'includes/shortcodes/shortcodes-mapa.php';
require_once CC_PATH . 'includes/shortcodes/shortcodes-form.php';

function cc_plugin_activate() {
    cc_criar_tabela_alteracoes();
    cc_register_agente_mapa_role();
    cc_criar_paginas_auth();
    flush_rewrite_rules();
}

register_activation_hook(__FILE__, 'cc_plugin_activate');

register_deactivation_hook(__FILE__, 'flush_rewrite_rules');
