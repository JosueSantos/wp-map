<?php
/**
 * Plugin Name: Cadastro de Comunidades
 * Description: Cadastro Colaborativo de Igrejas Católicas
 * Version: 1.1.0
 * Author: SERCOM - Serviço de Comunicação da Arquidiocese de Fortaleza
 * Text Domain: cadastro-comunidades
 * License: GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if (!defined('ABSPATH')) exit;

define('CC_PATH', plugin_dir_path(__FILE__));
define('CC_URL', plugin_dir_url(__FILE__));

function cc_load_textdomain() {
    load_plugin_textdomain('cadastro-comunidades', false, dirname(plugin_basename(__FILE__)) . '/languages');
}

add_action('plugins_loaded', 'cc_load_textdomain');

require_once CC_PATH . 'includes/core/template.php';

require_once CC_PATH . 'includes/communities/meta-fields.php';
require_once CC_PATH . 'includes/communities/post-types.php';
require_once CC_PATH . 'includes/communities/taxonomies.php';

require_once CC_PATH . 'includes/database/db-alteracoes.php';

require_once CC_PATH . 'includes/admin/permissions.php';
require_once CC_PATH . 'includes/auth/auth.php';

require_once CC_PATH . 'includes/communities/api/api-mapa.php';
require_once CC_PATH . 'includes/communities/api/api-form.php';

require_once CC_PATH . 'includes/communities/shortcodes/shortcodes-comunidade.php';
require_once CC_PATH . 'includes/communities/shortcodes/shortcodes-mapa.php';
require_once CC_PATH . 'includes/communities/shortcodes/shortcodes-form.php';

function cc_plugin_activate() {
    cc_criar_tabela_alteracoes();
    cc_criar_paginas_auth();
    cc_register_agente_mapa_role();
    flush_rewrite_rules();
}

register_activation_hook(__FILE__, 'cc_plugin_activate');

register_deactivation_hook(__FILE__, 'flush_rewrite_rules');
