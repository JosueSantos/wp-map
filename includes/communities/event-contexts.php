<?php

if (!defined('ABSPATH')) exit;

function cc_get_comunidade_card_context($comunidade_id) {
    $comunidade_id = (int) $comunidade_id;
    $post = get_post($comunidade_id);

    if (!$post || $post->post_type !== 'comunidade' || $post->post_status !== 'publish') {
        return null;
    }

    return [
        'id' => $comunidade_id,
        'titulo' => get_the_title($comunidade_id),
        'resumo' => get_the_excerpt($comunidade_id),
        'endereco' => get_post_meta($comunidade_id, 'endereco', true),
        'imagem' => get_the_post_thumbnail_url($comunidade_id, 'medium_large'),
        'url' => get_permalink($comunidade_id),
    ];
}

function cc_render_comunidade_card($comunidade_id) {
    $context = cc_get_comunidade_card_context($comunidade_id);

    if (!$context) {
        return '';
    }

    return cc_render_template('shortcodes/item-comunidade.php', $context);
}

function cc_get_comunidade_ids_by_event_taxonomy($taxonomy, $term_id) {
    $taxonomy = sanitize_key((string) $taxonomy);
    $term_id = (int) $term_id;

    if (!in_array($taxonomy, ['tipo_evento', 'tags_evento'], true) || $term_id <= 0) {
        return [];
    }

    $eventos_ids = get_posts([
        'post_type' => 'evento',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'fields' => 'ids',
        'tax_query' => [
            [
                'taxonomy' => $taxonomy,
                'field' => 'term_id',
                'terms' => [$term_id],
            ],
        ],
    ]);

    $comunidade_ids = [];

    foreach ($eventos_ids as $evento_id) {
        $comunidade_id = (int) get_post_meta($evento_id, 'comunidade_id', true);
        if ($comunidade_id > 0 && cc_get_comunidade_card_context($comunidade_id)) {
            $comunidade_ids[$comunidade_id] = $comunidade_id;
        }
    }

    return array_values($comunidade_ids);
}

function cc_event_context_template($template) {
    if (is_singular('evento')) {
        $custom_template = CC_PATH . 'templates/single-evento.php';
        return file_exists($custom_template) ? $custom_template : $template;
    }

    if (is_author()) {
        $custom_template = CC_PATH . 'templates/archive-author-comunidades.php';
        return file_exists($custom_template) ? $custom_template : $template;
    }

    if (is_tax('tags_evento')) {
        $custom_template = CC_PATH . 'templates/taxonomy-tags_evento.php';
        return file_exists($custom_template) ? $custom_template : $template;
    }

    if (is_tax('tipo_evento')) {
        $custom_template = CC_PATH . 'templates/taxonomy-tipo_evento.php';
        return file_exists($custom_template) ? $custom_template : $template;
    }

    return $template;
}

add_filter('template_include', 'cc_event_context_template');
