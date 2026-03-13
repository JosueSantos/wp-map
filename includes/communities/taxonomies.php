<?php

function cc_register_taxonomies() {

    register_taxonomy('tipo_comunidade', 'comunidade', [
        'label' => 'Tipo de Comunidade',
        'hierarchical' => false,
        'show_in_rest' => true
    ]);

    register_taxonomy('tipo_evento', 'evento', [
        'label' => 'Tipo de Evento',
        'hierarchical' => false,
        'show_in_rest' => true
    ]);

    register_taxonomy('tags_evento', 'evento', [
        'label' => 'Características do Evento',
        'hierarchical' => false,
        'show_in_rest' => true,
    ]);

}

function cc_register_tags_evento_meta() {
    register_term_meta('tags_evento', 'exclusive_tipo_evento_ids', [
        'type' => 'array',
        'single' => true,
        'default' => [],
        'sanitize_callback' => 'cc_sanitize_exclusive_tipo_evento_ids',
        'show_in_rest' => [
            'schema' => [
                'type' => 'array',
                'items' => [
                    'type' => 'integer',
                ],
                'default' => [],
            ],
        ],
        'auth_callback' => '__return_true',
    ]);
}

function cc_sanitize_exclusive_tipo_evento_ids($value) {
    if (!is_array($value)) {
        return [];
    }

    $ids = array_map('intval', $value);
    $ids = array_filter($ids, static function ($id) {
        return $id > 0;
    });

    return array_values(array_unique($ids));
}

function cc_render_exclusive_tipo_evento_field($term = null) {
    $tipos_evento = get_terms([
        'taxonomy' => 'tipo_evento',
        'hide_empty' => false,
    ]);

    if (is_wp_error($tipos_evento)) {
        $tipos_evento = [];
    }

    $selected_ids = [];

    if ($term instanceof WP_Term) {
        $selected_ids = get_term_meta($term->term_id, 'exclusive_tipo_evento_ids', true);
        if (!is_array($selected_ids)) {
            $selected_ids = [];
        }
        $selected_ids = array_map('intval', $selected_ids);
    }

    if ($term instanceof WP_Term) {
        ?>
        <tr class="form-field term-exclusive-tipo-evento-wrap">
            <th scope="row"><label for="exclusive_tipo_evento_ids">Exclusivo para tipos de atividade</label></th>
            <td>
                <select name="exclusive_tipo_evento_ids[]" id="exclusive_tipo_evento_ids" multiple="multiple" style="min-width:260px; min-height:120px;">
                    <?php foreach ($tipos_evento as $tipo) : ?>
                        <option value="<?php echo esc_attr($tipo->term_id); ?>" <?php selected(in_array((int) $tipo->term_id, $selected_ids, true)); ?>>
                            <?php echo esc_html($tipo->name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="description">
                    Selecione os tipos que podem usar esta característica. Deixe vazio para disponibilizar para todas as atividades.
                </p>
            </td>
        </tr>
        <?php

        return;
    }
    ?>
    <div class="form-field term-exclusive-tipo-evento-wrap">
        <label for="exclusive_tipo_evento_ids">Exclusivo para tipos de atividade</label>
        <select name="exclusive_tipo_evento_ids[]" id="exclusive_tipo_evento_ids" multiple="multiple" style="min-width:260px; min-height:120px;">
            <?php foreach ($tipos_evento as $tipo) : ?>
                <option value="<?php echo esc_attr($tipo->term_id); ?>"><?php echo esc_html($tipo->name); ?></option>
            <?php endforeach; ?>
        </select>
        <p>
            Selecione os tipos que podem usar esta característica. Deixe vazio para disponibilizar para todas as atividades.
        </p>
    </div>
    <?php
}

function cc_save_exclusive_tipo_evento_ids($term_id) {
    if (!current_user_can('manage_categories')) {
        return;
    }

    $ids = isset($_POST['exclusive_tipo_evento_ids']) ? (array) $_POST['exclusive_tipo_evento_ids'] : [];
    $ids = cc_sanitize_exclusive_tipo_evento_ids($ids);

    if (!empty($ids)) {
        update_term_meta($term_id, 'exclusive_tipo_evento_ids', $ids);
        return;
    }

    delete_term_meta($term_id, 'exclusive_tipo_evento_ids');
}

add_action('init', 'cc_register_taxonomies');
add_action('init', 'cc_register_tags_evento_meta');

add_action('tags_evento_add_form_fields', 'cc_render_exclusive_tipo_evento_field');
add_action('tags_evento_edit_form_fields', 'cc_render_exclusive_tipo_evento_field');
add_action('created_tags_evento', 'cc_save_exclusive_tipo_evento_ids');
add_action('edited_tags_evento', 'cc_save_exclusive_tipo_evento_ids');
