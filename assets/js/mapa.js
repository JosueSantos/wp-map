document.addEventListener("DOMContentLoaded", async function () {

    /* =====================================================
       ELEMENTO BASE
    ===================================================== */
    const containerEl = document.getElementById("mapa-igrejas");
    if (!containerEl) return;

    /* =====================================================
       CONFIG
    ===================================================== */
    const config = {
        fallbackCenter: [-3.7319, -38.5267],
        fallbackZoom: 13,
        userZoom: 13,
        mobileBreakpoint: 1023,
        apiBase: (containerEl.dataset.dominio || "").replace(/\/$/, "") + "/wp-json/mapa/v1"
    };

    const API_URL = `${config.apiBase}/comunidades`;
    const API_FILTROS_URL = `${config.apiBase}/filtros`;

    /* =====================================================
       ELEMENTOS
    ===================================================== */
    const mapaEl = document.getElementById("mapa-canvas");
    const filtrosForm = document.getElementById("mapa-filtros");
    const detalhesEl = document.getElementById("mapa-detalhes");
    const buscaEl = document.getElementById("filtro-busca");
    const buscaBtn = document.getElementById("mapa-buscar-comunidade");
    const buscaListEl = document.getElementById("mapa-comunidades-list");
    const sidebarEl = document.getElementById("mapa-sidebar");
    const aplicarBtn = document.getElementById("mapa-aplicar-filtros");
    const limparBtn = document.getElementById("mapa-limpar-filtros");

    /* =====================================================
       MAPA + CLUSTER
    ===================================================== */
    const map = L.map(mapaEl, { minZoom: 3, maxZoom: 19 })
        .setView(config.fallbackCenter, config.fallbackZoom);

    L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
        attribution: "&copy; OpenStreetMap"
    }).addTo(map);

    const clusterGroup = L.markerClusterGroup();
    map.addLayer(clusterGroup);

    /* =====================================================
       STATE
    ===================================================== */
    const state = {
        comunidades: [],
        autocompleteBase: [],
        markersById: new Map(),
        termoBusca: "",
        userLocation: null,
        userMarker: null,
        abortController: null
    };

    /* =====================================================
       UTILS
    ===================================================== */
    const isMobile = () =>
        window.matchMedia(`(max-width:${config.mobileBreakpoint}px)`).matches;

    const setLoading = (v) =>
        containerEl.classList.toggle("is-loading", !!v);

    const escapeHtml = (value) => {
        const div = document.createElement("div");
        div.textContent = value ?? "";
        return div.innerHTML;
    };

    const debounce = (fn, delay = 300) => {
        let t;
        return (...args) => {
            clearTimeout(t);
            t = setTimeout(() => fn(...args), delay);
        };
    };

    /* =====================================================
       GEOLOCALIZAÇÃO
    ===================================================== */
    async function requestUserLocationIfNeeded() {
        if (!navigator.geolocation) return;

        try {
            const pos = await new Promise((resolve, reject) =>
                navigator.geolocation.getCurrentPosition(resolve, reject, {
                    enableHighAccuracy: true,
                    timeout: 8000
                })
            );

            state.userLocation = {
                lat: Number(pos.coords.latitude),
                lng: Number(pos.coords.longitude)
            };

            state.userMarker = L.circleMarker(
                [state.userLocation.lat, state.userLocation.lng],
                {
                    radius: 7,
                    color: "#1d4ed8",
                    fillColor: "#3b82f6",
                    fillOpacity: 0.9
                }
            )
                .addTo(map)
                .bindTooltip("Sua localização", { direction: "top" });

        } catch (err) {
            // fallback automático
        }
    }

    /* =====================================================
       FILTROS
    ===================================================== */
    function buildUrlWithFilters() {
        const params = new URLSearchParams();

        if (filtrosForm) {
            const formData = new FormData(filtrosForm);
            for (const [key, value] of formData.entries()) {
                if (!value) continue;
                params.append(key, value);
            }
        }

        const query = params.toString();
        return query ? `${API_URL}?${query}` : API_URL;
    }

    async function carregarFiltros() {
        try {
            const res = await fetch(API_FILTROS_URL);
            if (!res.ok) throw new Error();
            const filtros = await res.json();

            populateSelect("filtro-dia", filtros.dias, "Qualquer dia");
            populateSelect("filtro-tipo-evento", filtros.tipos_evento, "Todos os tipos");
            populateSelect("filtro-tipo-comunidade", filtros.tipos_comunidade, "Todos os tipos");
            populateSelect("filtro-tag", filtros.tags, "Todas as tags");
        } catch (err) {
            console.error("Erro ao carregar filtros");
        }
    }

    function populateSelect(id, options, label) {
        const select = document.getElementById(id);
        if (!select) return;

        select.innerHTML = "";

        const defaultOption = document.createElement("option");
        defaultOption.value = "";
        defaultOption.textContent = label;
        select.appendChild(defaultOption);

        (options || []).forEach(opt => {
            const o = document.createElement("option");
            o.value = opt.slug;
            o.textContent = opt.nome;
            select.appendChild(o);
        });
    }

    /* =====================================================
       FETCH COM ABORT
    ===================================================== */
    async function fetchComunidades() {

        if (state.abortController)
            state.abortController.abort();

        state.abortController = new AbortController();

        const res = await fetch(buildUrlWithFilters(), {
            signal: state.abortController.signal
        });

        if (!res.ok)
            throw new Error(`HTTP ${res.status}`);

        return await res.json();
    }

    /* =====================================================
       AUTOCOMPLETE
    ===================================================== */
    function updateAutocomplete(lista) {
        if (!buscaListEl) return;

        buscaListEl.innerHTML = "";

        const nomes = [...new Set(
            (lista || [])
                .map(c => (c?.nome || "").trim())
                .filter(Boolean)
        )];

        nomes.slice(0, 80).forEach(nome => {
            const opt = document.createElement("option");
            opt.value = nome;
            buscaListEl.appendChild(opt);
        });
    }

    function filtrarPorBusca(lista) {
        const termo = state.termoBusca.trim().toLowerCase();
        if (!termo) return lista;

        return lista.filter(c => {
            const nome = String(c.nome || "").toLowerCase();
            const endereco = String(c.endereco || "").toLowerCase();
            return nome.includes(termo) || endereco.includes(termo);
        });
    }

    /* =====================================================
       MARKERS INTELIGENTES + CLUSTER
    ===================================================== */
    function updateMarkers(lista) {

        const newIds = new Set(lista.map(c => c.id));

        for (const [id, marker] of state.markersById.entries()) {
            if (!newIds.has(id)) {
                clusterGroup.removeLayer(marker);
                state.markersById.delete(id);
            }
        }

        lista.forEach(c => {

            if (state.markersById.has(c.id)) return;

            const lat = Number(c.latitude);
            const lng = Number(c.longitude);
            if (!Number.isFinite(lat) || !Number.isFinite(lng)) return;

            const marker = L.marker([lat, lng]);

            marker.bindTooltip(escapeHtml(c.nome), {
                direction: "top",
                offset: [0, -10]
            });

            marker.bindPopup(buildPopup(c), { maxHeight: 260 });

            marker.on("click", () => {
                renderDetalhes(c);
                if (isMobile()) sidebarEl?.classList.remove("is-open");
            });

            clusterGroup.addLayer(marker);
            state.markersById.set(c.id, marker);
        });
    }

    /* =====================================================
       POPUP
    ===================================================== */
    function buildPopup(c) {
        return `
            <div style="min-width:220px;">
                <strong>${escapeHtml(c.nome)}</strong>
                ${c.endereco ? `<div style="font-size:12px;color:#555">${escapeHtml(c.endereco)}</div>` : ""}
            </div>
        `;
    }

    /* =====================================================
       DETALHES
    ===================================================== */
    function renderDetalhes(c) {

        detalhesEl.innerHTML = "";

        if (!c) {
            const p = document.createElement("p");
            p.textContent = "Selecione uma comunidade.";
            detalhesEl.appendChild(p);
            return;
        }

        const article = document.createElement("article");

        const h4 = document.createElement("h4");
        h4.textContent = c.nome;
        article.appendChild(h4);

        if (c.endereco) {
            const p = document.createElement("p");
            p.textContent = c.endereco;
            article.appendChild(p);
        }

        const eventos = c.eventos || [];

        const ul = document.createElement("ul");
        eventos.forEach(ev => {
            const li = document.createElement("li");
            li.textContent = `${ev.titulo || "Evento"} - ${ev.horario || ""}`;
            ul.appendChild(li);
        });

        article.appendChild(ul);
        detalhesEl.appendChild(article);
    }

    /* =====================================================
       AJUSTAR VISÃO MAPA
    ===================================================== */
    function ajustarVisaoMapa(lista) {

        if (state.userLocation) {
            map.setView(
                [state.userLocation.lat, state.userLocation.lng],
                config.userZoom
            );
            return;
        }

        const pontos = lista
            .map(c => [Number(c.latitude), Number(c.longitude)])
            .filter(([lat, lng]) =>
                Number.isFinite(lat) && Number.isFinite(lng)
            );

        if (!pontos.length) {
            map.setView(config.fallbackCenter, config.fallbackZoom);
            return;
        }

        const bounds = L.latLngBounds(pontos);
        map.fitBounds(bounds, { padding: [30, 30], maxZoom: 15 });
    }

    /* =====================================================
       CARREGAR COMUNIDADES
    ===================================================== */
    async function carregarComunidades() {

        setLoading(true);
        renderDetalhes(null);

        try {
            const lista = await fetchComunidades();

            state.autocompleteBase = lista;
            updateAutocomplete(lista);

            state.comunidades = filtrarPorBusca(lista);

            if (!state.comunidades.length) {
                clusterGroup.clearLayers();
                state.markersById.clear();
                detalhesEl.textContent = "Nenhuma comunidade encontrada.";
                return;
            }

            updateMarkers(state.comunidades);
            ajustarVisaoMapa(state.comunidades);

        } catch (err) {
            if (err.name !== "AbortError") {
                detalhesEl.textContent = "Erro ao carregar comunidades.";
                console.error(err);
            }
        } finally {
            setLoading(false);
        }
    }

    /* =====================================================
       EVENTOS
    ===================================================== */
    const debouncedBusca = debounce(() => {
        state.termoBusca = buscaEl?.value || "";
        carregarComunidades();
    }, 300);

    buscaEl?.addEventListener("input", debouncedBusca);
    buscaBtn?.addEventListener("click", debouncedBusca);

    aplicarBtn?.addEventListener("click", () => {
        carregarComunidades();
        if (isMobile()) sidebarEl?.classList.remove("is-open");
    });

    limparBtn?.addEventListener("click", () => {
        filtrosForm?.reset();
        if (buscaEl) buscaEl.value = "";
        state.termoBusca = "";
        carregarComunidades();
    });

    window.addEventListener("resize", () => {
        map.invalidateSize();
    });

    /* =====================================================
       INIT
    ===================================================== */
    await carregarFiltros();
    await requestUserLocationIfNeeded();
    await carregarComunidades();

});