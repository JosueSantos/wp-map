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

function cc_mapa_scripts() {

    if (!is_singular() && !is_page()) return;

    // Leaflet CSS
    wp_enqueue_style(
        'leaflet-css',
        'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css'
    );

    // Leaflet JS
    wp_enqueue_script(
        'leaflet-js',
        'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js',
        [],
        null,
        true
    );

    // Nosso mapa
    wp_enqueue_script(
        'cc-mapa',
        plugin_dir_url(__FILE__) . 'assets/js/mapa.js',
        ['leaflet-js'],
        '1.0',
        true
    );
}

add_action('wp_enqueue_scripts', 'cc_mapa_scripts');
