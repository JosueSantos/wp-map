<?php

function cc_register_agente_mapa_role() {
    add_role(
        CC_ROLE_AGENTE_MAPA,
        __('Agente do Mapa', 'cadastro-comunidades'),
        get_role('editor')->capabilities
    );
}

