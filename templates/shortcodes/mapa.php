<section id="mapa-igrejas" data-dominio="<?php echo esc_attr($dominio); ?>" data-url-cadastro="<?php echo esc_attr($url_cadastro); ?>" data-user-logado="<?php echo $is_user_logged_in ? '1' : '0'; ?>" class="cc-mapa-fullwidth">
    <div class="cc-mapa-layout">
        <div class="cc-mapa-main">
            <div class="cc-mapa-topbar">
                <label class="cc-busca-wrap" for="filtro-busca">
                    <span>Pesquisar</span>
                    <div class="cc-busca-acoes">
                        <input id="filtro-busca" list="mapa-comunidades-list" type="search" placeholder="Digite o nome do Local" autocomplete="off" />
                        <button id="mapa-buscar-comunidade" type="button"><i class="bi bi-search"></i></button>
                    </div>
                    <datalist id="mapa-comunidades-list"></datalist>
                </label>
            </div>

            <div class="cc-mapa-content" aria-live="polite">
                <div class="cc-mapa-sidebar">
                    <div class="cc-overlay-panels">
                        <aside id="mapa-detalhes" class="cc-overlay-panel cc-overlay-panel--detalhes" data-panel="detalhes">
                            <button type="button" class="cc-panel-toggle" aria-expanded="false" aria-controls="cc-panel-detalhes-body">
                                <span>Local selecionado</span>
                                <span class="cc-panel-toggle-icon" aria-hidden="true"><i class="bi bi-chevron-down"></i></span>
                            </button>

                            <div class="cc-panel-body" id="cc-panel-detalhes-body">
                                <p class="cc-filtro-texto">Toque em um pino para ver detalhes e atividades.</p>
                            </div>
                        </aside>

                        <aside class="cc-overlay-panel cc-overlay-panel--filtros" data-panel="filtros">
                            <button type="button" class="cc-panel-toggle" aria-expanded="true" aria-controls="cc-panel-filtros-body">
                                <span>Filtros do mapa</span>
                                <span class="cc-panel-toggle-icon" aria-hidden="true"><i class="bi bi-chevron-down"></i></span>
                            </button>

                            <div class="cc-panel-body" id="cc-panel-filtros-body">
                                <p class="cc-filtro-texto font-bold">Selecione os filtros para refinar os locais e as atividades.</p>

                                <form id="mapa-filtros" class="cc-filtros-form">
                                    <label>
                                        <span>Filtro rápido de missas e confissões</span>
                                        <select id="filtro-evento-periodo"></select>
                                    </label>

                                    <label id="filtro-data-wrap">
                                        <span>Data específica</span>
                                        <input type="date" id="filtro-data" name="data">
                                    </label>

                                    <input type="hidden" id="filtro-periodo" name="periodo" value="">
                                    <input type="hidden" id="filtro-tipo-evento" name="tipo_evento" value="">

                                    <label>
                                        <span>Categoria da atividade</span>
                                        <select id="filtro-tag" name="tag"></select>
                                    </label>

                                    <label>
                                        <span>Tipo de Local</span>
                                        <select id="filtro-tipo-comunidade" name="tipo_comunidade"></select>
                                    </label>
                                </form>

                                <div class="cc-filtros-acoes">
                                    <button id="mapa-aplicar-filtros" type="button">Aplicar filtros</button>
                                    <button id="mapa-limpar-filtros" type="button">Limpar</button>
                                </div>
                            </div>
                        </aside>
                    </div>
                </div>

                <div class="cc-mapa-stage">
                    <div id="mapa-canvas" class="mt-2"></div>
                </div>
            </div>
        </div>
    </div>
</section>
