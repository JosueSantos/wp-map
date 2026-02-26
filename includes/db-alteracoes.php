<?php

if (!defined('ABSPATH')) exit;

function cc_criar_tabela_alteracoes() {
    global $wpdb;

    $charset_collate = $wpdb->get_charset_collate();

    $alteracoes_table = $wpdb->prefix . 'mapa_alteracoes';
    $observadores_table = $wpdb->prefix . 'mapa_observadores';

    $sql_alteracoes = "CREATE TABLE $alteracoes_table (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        comunidade_id BIGINT UNSIGNED NOT NULL,
        paroquia_id BIGINT UNSIGNED NULL,
        user_id BIGINT UNSIGNED NOT NULL,
        dados_json LONGTEXT NOT NULL,
        ip_address VARCHAR(45) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY comunidade_id (comunidade_id),
        KEY paroquia_id (paroquia_id),
        KEY user_id (user_id),
        KEY created_at (created_at)
    ) $charset_collate;";

    $sql_observadores = "CREATE TABLE $observadores_table (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT UNSIGNED NOT NULL,
        comunidade_id BIGINT UNSIGNED NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY user_comunidade (user_id, comunidade_id),
        KEY user_id (user_id),
        KEY comunidade_id (comunidade_id),
        KEY created_at (created_at)
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql_alteracoes);
    dbDelta($sql_observadores);
}

function cc_registrar_alteracao($comunidade_id, $paroquia_id, $dados_json) {
    global $wpdb;

    $table = $wpdb->prefix . 'mapa_alteracoes';

    $wpdb->insert($table, [
        'comunidade_id' => intval($comunidade_id),
        'paroquia_id'   => intval($paroquia_id),
        'user_id'       => get_current_user_id(),
        'dados_json'    => wp_json_encode($dados_json),
        'ip_address'    => $_SERVER['REMOTE_ADDR'] ?? null,
        'created_at'    => current_time('mysql')
    ]);
}

function cc_observar_comunidade($user_id, $comunidade_id) {
    global $wpdb;

    $table = $wpdb->prefix . 'mapa_observadores';

    return $wpdb->replace($table, [
        'user_id' => (int) $user_id,
        'comunidade_id' => (int) $comunidade_id,
        'created_at' => current_time('mysql'),
    ], ['%d', '%d', '%s']);
}

function cc_remover_observacao_comunidade($user_id, $comunidade_id) {
    global $wpdb;

    $table = $wpdb->prefix . 'mapa_observadores';

    return $wpdb->delete($table, [
        'user_id' => (int) $user_id,
        'comunidade_id' => (int) $comunidade_id,
    ], ['%d', '%d']);
}

function cc_listar_comunidades_observadas_ids($user_id) {
    global $wpdb;

    $table = $wpdb->prefix . 'mapa_observadores';

    return array_map('intval', (array) $wpdb->get_col($wpdb->prepare(
        "SELECT comunidade_id FROM $table WHERE user_id = %d",
        (int) $user_id
    )));
}
