<?php

if (!defined('ABSPATH')) exit;

const CC_ROLE_AGENTE_MAPA = 'agente_mapa';

$cc_auth_files = [
    __DIR__ . '/roles.php',
    __DIR__ . '/auth-pages.php',
    __DIR__ . '/access-control.php',
    __DIR__ . '/auth-settings.php',
    __DIR__ . '/oauth.php',
    __DIR__ . '/auth-forms.php',
    __DIR__ . '/auth-ui.php',
    __DIR__ . '/screens/login.php',
    __DIR__ . '/screens/cadastro.php',
    __DIR__ . '/screens/esqueci-senha.php',
    __DIR__ . '/screens/redefinir-senha.php',
    __DIR__ . '/screens/alterar-senha.php',
    __DIR__ . '/minha-conta/data.php',
    __DIR__ . '/screens/minha-conta.php',
];

foreach ($cc_auth_files as $cc_auth_file) {
    require_once $cc_auth_file;
}
