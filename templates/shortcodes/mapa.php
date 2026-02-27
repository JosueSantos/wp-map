<section id="mapa-igrejas" data-dominio="<?php echo esc_attr($dominio); ?>" data-url-cadastro="<?php echo esc_attr($url_cadastro); ?>" data-user-logado="<?php echo $is_user_logged_in ? '1' : '0'; ?>" class="cc-mapa-fullwidth">
    <div class="cc-mapa-layout">
        <aside id="mapa-sidebar" class="cc-sidebar">
            <div class="cc-sidebar-header">
                <h2>Filtros</h2>
                <button id="mapa-fechar-filtros" type="button" class="cc-mobile-only">✕</button>
            </div>

            <div class="cc-sidebar-body">
                <p class="cc-filtro-texto">Selecione os filtros para refinar comunidades e eventos.</p>

                <form id="mapa-filtros" class="cc-filtros-form">
                    <label>
                        <span>Dia</span>
                        <select id="filtro-dia" name="dia"></select>
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

                <aside id="mapa-detalhes" class="cc-detalhes-card">
                    <h3>Comunidade selecionada</h3>
                    <p>Toque em um pino para ver detalhes e eventos.</p>
                </aside>
            </div>
        </aside>

        <div class="cc-mapa-main">
            <div class="cc-mapa-topbar">
                <button id="mapa-toggle-filtros" type="button" class="cc-mobile-only">Filtros ☰</button>
                <label class="cc-busca-wrap" for="filtro-busca">
                    <span>Pesquisar comunidade</span>
                    <div class="cc-busca-acoes">
                        <input id="filtro-busca" list="mapa-comunidades-list" type="search" placeholder="Digite o nome da comunidade" autocomplete="off" />
                        <button id="mapa-buscar-comunidade" type="button">Pesquisar</button>
                    </div>
                    <datalist id="mapa-comunidades-list"></datalist>
                </label>
            </div>

            <div id="mapa-canvas"></div>
        </div>
    </div>
</section>
