document.addEventListener("DOMContentLoaded", async function () {

    const containerEl = document.getElementById("mapa-igrejas");
    if (!containerEl) return;

    const mapaEl = document.getElementById("mapa-canvas");
    const filtrosForm = document.getElementById("mapa-filtros");
    const detalhesEl = document.getElementById("mapa-detalhes");
    const limparBtn = document.getElementById("mapa-limpar-filtros");

    const fallbackCenter = [-3.7319, -38.5267]; // Fortaleza, Ceará, Brasil
    const fallbackZoom = 11;
    const userZoom = 8;

    let dominio = containerEl.dataset.dominio || "";

    if (dominio.endsWith("/")) {
        dominio = dominio.slice(0, -1);
    }

    const API_BASE = dominio ? `${dominio}/wp-json/mapa/v1` : "/wp-json/mapa/v1";
    const API_URL = `${API_BASE}/comunidades`;
    const API_FILTROS_URL = `${API_BASE}/filtros`;

    const map = L.map(mapaEl, {
        minZoom: 3,
        maxZoom: 19,
    }).setView(fallbackCenter, fallbackZoom);

    L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
        attribution: "&copy; OpenStreetMap"
    }).addTo(map);

    const state = {
        userLocation: null,
        markers: [],
        comunidades: [],
    };

    const diaMap = {
        "0": "Domingo",
        "1": "Segunda",
        "2": "Terça",
        "3": "Quarta",
        "4": "Quinta",
        "5": "Sexta",
        "6": "Sábado",
    };

    function isMobile() {
        return window.matchMedia("(max-width: 1024px)").matches;
    }

    function renderDetalhes(comunidade) {
        if (!detalhesEl) return;

        if (!comunidade) {
            detalhesEl.innerHTML = `
                <h3 class="mb-2 text-base font-semibold text-slate-800">Detalhes da comunidade</h3>
                <p>Selecione um pino no mapa para visualizar informações da comunidade e dos eventos.</p>
            `;
            return;
        }

        const eventosHtml = (comunidade.eventos || []).length
            ? comunidade.eventos.map((evento) => {
                const dia = diaMap[String(evento.dia)] || evento.dia || "Dia não informado";
                return `
                    <li class="rounded-lg border border-slate-200 bg-white p-3">
                        <p class="font-semibold text-slate-800">${evento.titulo || "Evento"}</p>
                        <p class="text-slate-600">${dia} • ${evento.horario || "Horário não informado"}</p>
                        ${evento.tipo ? `<p class="text-xs uppercase tracking-wide text-sky-700">${evento.tipo}</p>` : ""}
                        ${evento.observacao ? `<p class="mt-1 text-xs text-slate-500">${evento.observacao}</p>` : ""}
                    </li>
                `;
            }).join("")
            : '<li class="rounded-lg border border-slate-200 bg-white p-3 text-slate-500">Nenhum evento para os filtros selecionados.</li>';

        detalhesEl.innerHTML = `
            <article class="space-y-3">
                <h3 class="text-lg font-semibold text-slate-900">${comunidade.nome || "Comunidade"}</h3>
                ${comunidade.foto ? `<img src="${comunidade.foto}" alt="${comunidade.nome}" class="h-40 w-full rounded-lg object-cover" />` : ""}
                ${comunidade.endereco ? `<p class="text-slate-700">${comunidade.endereco}</p>` : ""}
                ${comunidade.contatos ? `<p class="text-slate-600">${comunidade.contatos}</p>` : ""}
                ${comunidade.distancia_km ? `<p class="rounded-md bg-sky-50 px-2 py-1 text-xs font-medium text-sky-700">Distância aproximada: ${Number(comunidade.distancia_km).toFixed(1)} km</p>` : ""}
                <div>
                    <h4 class="mb-2 text-sm font-semibold uppercase tracking-wide text-slate-700">Eventos</h4>
                    <ul class="space-y-2">${eventosHtml}</ul>
                </div>
            </article>
        `;
    }

    function buildPopup(comunidade) {
        const firstEvent = comunidade.eventos?.[0];
        const eventoResumo = firstEvent
            ? `<div style="margin-top:6px;font-size:12px;color:#334155;"><strong>${firstEvent.titulo}</strong><br>${diaMap[String(firstEvent.dia)] || firstEvent.dia} • ${firstEvent.horario || ""}</div>`
            : '<div style="margin-top:6px;font-size:12px;color:#64748b;">Sem eventos nos filtros atuais.</div>';

        return `
            <div style="min-width:220px;">
                <h3 style="margin:0;font-size:14px;color:#0f172a;">${comunidade.nome}</h3>
                ${comunidade.endereco ? `<p style="margin:4px 0 0;font-size:12px;color:#475569;">${comunidade.endereco}</p>` : ""}
                ${eventoResumo}
                <p style="margin:8px 0 0;font-size:11px;color:#0369a1;">Clique para ver detalhes no painel lateral.</p>
            </div>
        `;
    }

    function addMarker(comunidade) {
        const marker = L.marker([Number(comunidade.latitude), Number(comunidade.longitude)]).addTo(map);

        marker.bindTooltip(comunidade.nome, {
            direction: "top",
            offset: [0, -10],
            opacity: 0.9,
        });

        marker.bindPopup(buildPopup(comunidade));

        marker.on("click", function () {
            renderDetalhes(comunidade);
        });

        state.markers.push(marker);
    }

    function clearMarkers() {
        state.markers.forEach((marker) => marker.remove());
        state.markers = [];
    }

    function buildUrlWithFilters() {
        const params = new URLSearchParams();

        const formData = new FormData(filtrosForm);
        for (const [key, value] of formData.entries()) {
            if (!value) continue;
            params.append(key, value);
        }

        if (!document.getElementById("filtro-proximidade")?.checked) {
            params.delete("proximidade");
        }

        if (state.userLocation?.lat && state.userLocation?.lng) {
            params.set("lat", state.userLocation.lat);
            params.set("lng", state.userLocation.lng);
        }

        const queryString = params.toString();
        return queryString ? `${API_URL}?${queryString}` : API_URL;
    }

    async function carregarComunidades() {
        clearMarkers();
        renderDetalhes(null);

        try {
            const res = await fetch(buildUrlWithFilters());
            const comunidades = await res.json();

            state.comunidades = Array.isArray(comunidades) ? comunidades : [];

            state.comunidades.forEach(addMarker);

            if (state.comunidades.length > 0) {
                const bounds = L.latLngBounds(state.comunidades.map((c) => [Number(c.latitude), Number(c.longitude)]));
                map.fitBounds(bounds.pad(0.2));
            } else if (state.userLocation) {
                map.setView([state.userLocation.lat, state.userLocation.lng], userZoom);
            } else {
                map.setView(fallbackCenter, fallbackZoom);
            }
        } catch (err) {
            console.error("Erro ao carregar mapa:", err);
        }
    }

    function fillSelect(selectId, options, defaultLabel) {
        const select = document.getElementById(selectId);
        if (!select) return;

        const opts = [`<option value="">${defaultLabel}</option>`];
        options.forEach((opt) => {
            opts.push(`<option value="${opt.slug}">${opt.nome}</option>`);
        });

        select.innerHTML = opts.join("");
    }

    async function carregarFiltros() {
        try {
            const res = await fetch(API_FILTROS_URL);
            const filtros = await res.json();

            fillSelect("filtro-dia", filtros.dias || [], "Qualquer dia");
            fillSelect("filtro-tipo-evento", filtros.tipos_evento || [], "Todos os tipos");
            fillSelect("filtro-tipo-comunidade", filtros.tipos_comunidade || [], "Todos os tipos");
            fillSelect("filtro-tag", filtros.tags || [], "Todas as tags");
        } catch (err) {
            console.error("Erro ao carregar filtros:", err);
        }
    }

    async function requestUserLocationIfNeeded() {
        if (!isMobile() || !navigator.geolocation) {
            map.setView(fallbackCenter, fallbackZoom);
            return;
        }

        try {
            const pos = await new Promise((resolve, reject) => {
                navigator.geolocation.getCurrentPosition(resolve, reject, {
                    enableHighAccuracy: true,
                    timeout: 8000,
                });
            });

            state.userLocation = {
                lat: Number(pos.coords.latitude),
                lng: Number(pos.coords.longitude),
            };

            map.setView([state.userLocation.lat, state.userLocation.lng], userZoom);

            L.circleMarker([state.userLocation.lat, state.userLocation.lng], {
                radius: 7,
                color: "#0284c7",
                fillColor: "#38bdf8",
                fillOpacity: 0.9,
            }).addTo(map).bindTooltip("Sua localização", { direction: "top" });
        } catch (err) {
            map.setView(fallbackCenter, fallbackZoom);
        }
    }

    filtrosForm?.addEventListener("change", () => {
        carregarComunidades();
    });

    limparBtn?.addEventListener("click", () => {
        filtrosForm?.reset();
        carregarComunidades();
    });

    await carregarFiltros();
    await requestUserLocationIfNeeded();
    await carregarComunidades();
});
