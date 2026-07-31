<section id="mapa-igrejas" data-dominio="<?php echo esc_attr($dominio); ?>" data-url-cadastro="<?php echo esc_attr($url_cadastro); ?>" data-user-logado="<?php echo $is_user_logged_in ? '1' : '0'; ?>" data-filtros-completos="<?php echo !empty($exibir_filtros_completos) ? '1' : '0'; ?>" class="cc-mapa-fullwidth">
    <div class="cc-mapa-layout">
        <div class="cc-mapa-main">
            <div class="cc-mapa-topbar">
                <div class="cc-filtros-rapidos" role="group" aria-label="Filtros rápidos">
                    <button type="button" class="cc-btn-rapido" data-quick-filter="missa_hoje"><img src="<?php echo esc_url(CC_URL . 'assets/img/resumo-cadastros/horarios-missas.svg'); ?>" alt="" aria-hidden="true"><span>Missa Hoje</span></button>
                    <button type="button" class="cc-btn-rapido" data-quick-filter="confissao_hoje"><img src="<?php echo esc_url(CC_URL . 'assets/img/resumo-cadastros/locais-confissao.svg'); ?>" alt="" aria-hidden="true"><span>Confissão Hoje</span></button>
                    <button type="button" class="cc-btn-rapido" data-quick-filter="adoracao_semana"><img src="<?php echo esc_url(CC_URL . 'assets/img/resumo-cadastros/locais-adoracao.svg'); ?>" alt="" aria-hidden="true"><span>Adoração ao Santíssimo</span></button>
                    <button type="button" class="cc-btn-rapido cc-btn-rapido--filtros" data-toggle-filtros><i class="bi bi-funnel-fill"></i><span>Busca</span></button>
                </div>
                <div class="cc-filtros-mapa-fixo cc-filtros-mapa-fixo--oculto" data-filtros-fixo>
                    <div class="cc-overlay-panels">
                        <aside class="cc-overlay-panel cc-overlay-panel--filtros" data-panel="filtros">
                            <button type="button" class="cc-panel-toggle" aria-expanded="false" aria-controls="cc-panel-filtros-body">
                                <span>Filtros do mapa</span>
                                <span class="cc-panel-toggle-icon" aria-hidden="true"><i class="bi bi-chevron-down"></i></span>
                            </button>

                            <div class="cc-panel-body" id="cc-panel-filtros-body" hidden>
                                <p class="cc-filtro-texto font-bold">Selecione os filtros para refinar os locais e as atividades.</p>

                                <form id="mapa-filtros" class="cc-filtros-form">
                                    <label>
                                        <span>Filtro rápido de missas, confissões e adoração ao Santíssimo</span>
                                        <select id="filtro-evento-periodo"></select>
                                    </label>

                                    <?php if (!empty($exibir_filtros_completos)) : ?>
                                        <label id="filtro-data-wrap">
                                            <span>Data específica</span>
                                            <input type="date" id="filtro-data" name="data">
                                        </label>
                                    <?php endif; ?>

                                    <input type="hidden" id="filtro-periodo" name="periodo" value="">
                                    <input type="hidden" id="filtro-tipo-evento" name="tipo_evento" value="">

                                    <?php if (!empty($exibir_filtros_completos)) : ?>
                                        <label>
                                            <span>Característica de Missa</span>
                                            <select id="filtro-tag-missa" name="tag_missa"></select>
                                        </label>

                                        <label>
                                            <span>Tipos de Ações Caritativas</span>
                                            <select id="filtro-tag-acao-caritativa" name="tag_acao_caritativa"></select>
                                        </label>
                                    <?php endif; ?>

                                    <input type="hidden" id="filtro-tag" name="tag" value="">

                                    <label>
                                        <span>Nome do Local</span>
                                        <input type="search" id="filtro-nome-local" name="nome" list="mapa-comunidades-list" placeholder="Digite o nome do local" autocomplete="off">
                                        <datalist id="mapa-comunidades-list"></datalist>
                                    </label>

                                    <label>
                                        <span>Endereço ou bairro</span>
                                        <input type="search" id="filtro-endereco-bairro" placeholder="Digite um endereço ou bairro" autocomplete="off">
                                        <small id="mapa-endereco-status" class="cc-campo-ajuda" aria-live="polite"></small>
                                    </label>

                                    <?php if (!empty($exibir_filtros_completos)) : ?>
                                        <label>
                                            <span>Tipo de Local</span>
                                            <select id="filtro-tipo-comunidade" name="tipo_comunidade"></select>
                                        </label>
                                    <?php endif; ?>
                                </form>

                                <div class="cc-filtros-acoes">
                                    <button id="mapa-aplicar-filtros" type="button">Aplicar filtros</button>
                                    <button id="mapa-limpar-filtros" type="button">Limpar</button>
                                </div>
                            </div>
                        </aside>
                    </div>
                </div>
            </div>

            <div class="cc-mapa-content" aria-live="polite">
                <div class="cc-mapa-stage">
                    <div class="cc-view-toggle" role="group" aria-label="Visualização">
                        <button type="button" class="cc-view-btn is-active" data-view-mode="mapa"><i class="bi bi-geo-alt-fill"></i><span>Mapa</span></button>
                        <button type="button" class="cc-view-btn" data-view-mode="lista"><i class="bi bi-list-ul"></i><span>Lista</span></button>
                    </div>
                    <div class="cc-overlay-panels cc-overlay-panels--floating">
                    </div>
                    <div id="mapa-canvas" class="mt-2"></div>
                    <div id="mapa-lista-locais" class="cc-lista-locais" hidden></div>
                    <div class="cc-overlay-panels cc-overlay-panels--floating">
                        <aside id="mapa-detalhes" class="cc-overlay-panel cc-overlay-panel--detalhes" data-panel="detalhes" hidden>
                            <button type="button" class="cc-panel-toggle" aria-expanded="false" aria-controls="cc-panel-detalhes-body">
                                <span>Local selecionado</span>
                                <span class="cc-panel-toggle-icon" aria-hidden="true"><i class="bi bi-chevron-down"></i></span>
                            </button>

                            <div class="cc-panel-body" id="cc-panel-detalhes-body">
                                <p class="cc-filtro-texto">Toque em um pino para ver detalhes e atividades.</p>
                            </div>
                        </aside>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
