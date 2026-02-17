<?php

function cc_register_meta() {

    // Comunidade
    register_post_meta('comunidade', 'latitude', [
        'show_in_rest' => true,
        'single' => true,
        'type' => 'string'
    ]);

    register_post_meta('comunidade', 'longitude', [
        'show_in_rest' => true,
        'single' => true,
        'type' => 'string'
    ]);

    register_post_meta('comunidade', 'parent_paroquia', [
        'show_in_rest' => true,
        'single' => true,
        'type' => 'integer'
    ]);

    register_post_meta('comunidade', 'endereco', [
        'show_in_rest' => true,
        'single' => true,
        'type' => 'string'
    ]);

    // Evento
    register_post_meta('evento', 'comunidade_id', [
        'show_in_rest' => true,
        'single' => true,
        'type' => 'integer'
    ]);

    register_post_meta('evento', 'dia_semana', [
        'show_in_rest' => true,
        'single' => true,
        'type' => 'string'
    ]);

    register_post_meta('evento', 'horario', [
        'show_in_rest' => true,
        'single' => true,
        'type' => 'string'
    ]);

    register_post_meta('evento', 'descricao', [
        'show_in_rest' => true,
        'single' => true,
        'type' => 'string'
    ]);

    register_post_meta('evento', 'observacao', [
        'show_in_rest' => true,
        'single' => true,
        'type' => 'string'
    ]);
}

add_action('init', 'cc_register_meta');

function cc_add_meta_box_comunidade() {
    add_meta_box(
        'cc_dados_comunidade',
        'Dados da Comunidade',
        'cc_render_meta_box_comunidade',
        'comunidade',
        'normal',
        'high'
    );
}

add_action('add_meta_boxes', 'cc_add_meta_box_comunidade');

function cc_render_meta_box_comunidade($post) {
    wp_nonce_field('cc_save_comunidade', 'cc_comunidade_nonce');

    $lat = get_post_meta($post->ID, 'latitude', true);
    $lng = get_post_meta($post->ID, 'longitude', true);
    $parent = get_post_meta($post->ID, 'parent_paroquia', true);
    $endereco = get_post_meta($post->ID, 'endereco', true);

    ?>
    <p>
        <label><strong>Latitude:</strong></label><br>
        <input type="text" name="cc_latitude" value="<?php echo esc_attr($lat); ?>" style="width:100%;">
    </p>

    <p>
        <label><strong>Longitude:</strong></label><br>
        <input type="text" name="cc_longitude" value="<?php echo esc_attr($lng); ?>" style="width:100%;">
    </p>

    <p>
        <label><strong>ID da Paróquia (se for Capela):</strong></label><br>
        <input type="number" name="cc_parent_paroquia" value="<?php echo esc_attr($parent); ?>" style="width:100%;">
    </p>

    <p>
        <label><strong>Endereço:</strong></label><br>
        <input type="text" name="cc_endereco" value="<?php echo esc_attr($endereco); ?>" style="width:100%;">
    </p>
    <?php
}

function cc_save_meta_comunidade($post_id) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (wp_is_post_revision($post_id)) return;
    if (!current_user_can('edit_post', $post_id)) return;

    if (!isset($_POST['cc_comunidade_nonce']) ||
        !wp_verify_nonce($_POST['cc_comunidade_nonce'], 'cc_save_comunidade')) {
        return;
    }

    if (isset($_POST['cc_latitude'])) {
        update_post_meta($post_id, 'latitude', sanitize_text_field($_POST['cc_latitude']));
    }

    if (array_key_exists('cc_longitude', $_POST)) {
        update_post_meta($post_id, 'longitude', sanitize_text_field($_POST['cc_longitude']));
    }

    if (array_key_exists('cc_parent_paroquia', $_POST)) {
        update_post_meta($post_id, 'parent_paroquia', intval($_POST['cc_parent_paroquia']));
    }

    if (array_key_exists('cc_endereco', $_POST)) {
        update_post_meta($post_id, 'endereco', sanitize_text_field($_POST['cc_endereco']));
    }
}

add_action('save_post_comunidade', 'cc_save_meta_comunidade');

function cc_add_meta_box_evento() {
    add_meta_box(
        'cc_dados_evento',
        'Dados do Evento',
        'cc_render_meta_box_evento',
        'evento',
        'normal',
        'high'
    );
}

add_action('add_meta_boxes', 'cc_add_meta_box_evento');

function cc_render_meta_box_evento($post) {
    wp_nonce_field('cc_save_evento', 'cc_evento_nonce');

    $comunidade_id = get_post_meta($post->ID, 'comunidade_id', true);
    $dia = get_post_meta($post->ID, 'dia_semana', true);
    $hora = get_post_meta($post->ID, 'horario', true);
    $descricao = get_post_meta($post->ID, 'descricao', true);
    $observacao = get_post_meta($post->ID, 'observacao', true);

    // Buscar todas as comunidades
    $comunidades = get_posts([
        'post_type' => 'comunidade',
        'posts_per_page' => -1,
        'orderby' => 'title',
        'order' => 'ASC'
    ]);
    ?>

    <p>
        <label><strong>Comunidade:</strong></label><br>
        <select name="cc_comunidade_id" style="width:100%">
            <option value="">Selecione</option>
            <?php foreach ($comunidades as $c): ?>
                <option value="<?php echo $c->ID; ?>" <?php selected($comunidade_id, $c->ID); ?>>
                    <?php echo $c->post_title; ?>
                </option>
            <?php endforeach; ?>
        </select>
    </p>

    <p>
        <label><strong>Dia da Semana:</strong></label><br>
        <select name="cc_dia_semana" style="width:100%">
            <?php
            $dias = ['Domingo','Segunda','Terça','Quarta','Quinta','Sexta','Sábado'];
            foreach ($dias as $d) {
                echo "<option value='$d' ".selected($dia,$d,false).">$d</option>";
            }
            ?>
        </select>
    </p>

    <p>
        <label><strong>Horário:</strong></label><br>
        <input type="time" name="cc_horario" value="<?php echo esc_attr($hora); ?>" style="width:100%">
    </p>

    <p>
        <label><strong>Descrição:</strong></label><br>
        <textarea name="cc_descricao" style="width:100%; height:80px;"><?php echo esc_textarea($descricao); ?></textarea>
    </p>

    <p>
        <label><strong>Observação:</strong></label><br>
        <input type="text" name="cc_observacao" value="<?php echo esc_attr($observacao); ?>" style="width:100%;">
    </p>

    <?php
}

function cc_save_meta_evento($post_id) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (wp_is_post_revision($post_id)) return;
    if (!current_user_can('edit_post', $post_id)) return;

    if (!isset($_POST['cc_evento_nonce']) ||
        !wp_verify_nonce($_POST['cc_evento_nonce'], 'cc_save_evento')) {
        return;
    }

    if (array_key_exists('cc_comunidade_id', $_POST)) {
        update_post_meta($post_id, 'comunidade_id', intval($_POST['cc_comunidade_id']));
    }

    if (array_key_exists('cc_dia_semana', $_POST)) {
        update_post_meta($post_id, 'dia_semana', sanitize_text_field($_POST['cc_dia_semana']));
    }

    if (array_key_exists('cc_horario', $_POST)) {
        update_post_meta($post_id, 'horario', sanitize_text_field($_POST['cc_horario']));
    }

    if (array_key_exists('cc_descricao', $_POST)) {
        update_post_meta($post_id, 'descricao', sanitize_textarea_field($_POST['cc_descricao']));
    }

    if (array_key_exists('cc_observacao', $_POST)) {
        update_post_meta($post_id, 'observacao', sanitize_text_field($_POST['cc_observacao']));
    }
}

add_action('save_post_evento', 'cc_save_meta_evento');
