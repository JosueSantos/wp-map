<?php
/**
 * Plugin Name: Cadastro de Comunidades
 * Description: Gestão de Paróquias, Capelas e Eventos
 * Version: 1.0
 */

if (!defined('ABSPATH')) exit;

define('CC_PATH', plugin_dir_path(__FILE__));

require_once CC_PATH . 'includes/post-types.php';
require_once CC_PATH . 'includes/taxonomies.php';
require_once CC_PATH . 'includes/meta-fields.php';
require_once CC_PATH . 'includes/shortcodes.php';
require_once CC_PATH . 'includes/api.php';
