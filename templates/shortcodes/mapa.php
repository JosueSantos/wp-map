<section id="mapa-igrejas" data-dominio="<?php echo esc_attr($dominio); ?>" data-url-cadastro="<?php echo esc_attr($url_cadastro); ?>" data-user-logado="<?php echo $is_user_logged_in ? '1' : '0'; ?>" class="cc-mapa-fullwidth">
    <div class="cc-mapa-layout">
        <div class="cc-mapa-main">
            <div class="cc-mapa-topbar">
                <label class="cc-busca-wrap" for="filtro-busca">
                    <span>Pesquisar</span>
                    <div class="cc-busca-acoes">
                        <input id="filtro-busca" list="mapa-comunidades-list" type="search" placeholder="Digite o nome da comunidade" autocomplete="off" />
                        <button id="mapa-buscar-comunidade" type="button"><i class="bi bi-search"></i></button>
                    </div>
                    <datalist id="mapa-comunidades-list"></datalist>
                </label>
            </div>

            <div class="cc-mapa-content" aria-live="polite">
                <div class="cc-mapa-sidebar">
                    <div class="cc-overlay-panels">
                        <aside class="cc-overlay-panel cc-overlay-panel--filtros" data-panel="filtros">
                            <button type="button" class="cc-panel-toggle" aria-expanded="true" aria-controls="cc-panel-filtros-body">
                                <span>Filtros do mapa</span>
                                <span class="cc-panel-toggle-icon" aria-hidden="true"><i class="bi bi-chevron-down"></i></span>
                            </button>

                            <div class="cc-panel-body" id="cc-panel-filtros-body">
                                <p class="cc-filtro-texto">Selecione os filtros para refinar comunidades e eventos.</p>

                                <form id="mapa-filtros" class="cc-filtros-form">
                                    <label>
                                        <span>Período</span>
                                        <select id="filtro-periodo" name="periodo"></select>
                                    </label>

                                    <label id="filtro-data-wrap">
                                        <span>Dia selecionado</span>
                                        <input type="date" id="filtro-data" name="data">
                                    </label>

                                    <label>
                                        <span>Tipo de evento</span>
                                        <select id="filtro-tipo-evento" name="tipo_evento"></select>
                                    </label>

                                    <label>
                                        <span>Tipo de comunidade</span>
                                        <select id="filtro-tipo-comunidade" name="tipo_comunidade"></select>
                                    </label>

                                    <label>
                                        <span>Tag</span>
                                        <select id="filtro-tag" name="tag"></select>
                                    </label>
                                </form>

                                <div class="cc-filtros-acoes">
                                    <button id="mapa-aplicar-filtros" type="button">Aplicar filtros</button>
                                    <button id="mapa-limpar-filtros" type="button">Limpar</button>
                                </div>
                            </div>
                        </aside>

                        <aside id="mapa-detalhes" class="cc-overlay-panel cc-overlay-panel--detalhes" data-panel="detalhes">
                            <button type="button" class="cc-panel-toggle" aria-expanded="false" aria-controls="cc-panel-detalhes-body">
                                <span>Comunidade selecionada</span>
                                <span class="cc-panel-toggle-icon" aria-hidden="true"><i class="bi bi-chevron-down"></i></span>
                            </button>

                            <div class="cc-panel-body" id="cc-panel-detalhes-body">
                                <p class="cc-filtro-texto">Toque em um pino para ver detalhes e eventos.</p>
                            </div>
                        </aside>
                    </div>
                </div>

                <div class="cc-mapa-stage">
                    <div id="mapa-canvas"></div>
                </div>
            </div>
        </div>
    </div>
</section>
