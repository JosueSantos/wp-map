<?php

if (!defined('ABSPATH')) exit;

function cc_criar_tabela_alteracoes() {
    global $wpdb;

    $table = $wpdb->prefix . 'mapa_alteracoes';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table (
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

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
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