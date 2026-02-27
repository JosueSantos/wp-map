<?php

if (!defined('ABSPATH')) exit;

function cc_render_template($template, $context = []) {
    $template_file = CC_PATH . 'templates/' . ltrim($template, '/');

    if (!file_exists($template_file)) {
        return '';
    }

    if (!empty($context) && is_array($context)) {
        extract($context, EXTR_SKIP);
    }

    ob_start();
    include $template_file;

    return ob_get_clean();
}
