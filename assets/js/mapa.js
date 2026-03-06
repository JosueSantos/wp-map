document.addEventListener("DOMContentLoaded", async function () {
    const containerEl = document.getElementById("mapa-igrejas");
    if (!containerEl) return;

    const mapaEl = document.getElementById("mapa-canvas");
    const filtrosForm = document.getElementById("mapa-filtros");
    const detalhesEl = document.getElementById("mapa-detalhes");
    const limparBtn = document.getElementById("mapa-limpar-filtros");
    const aplicarBtn = document.getElementById("mapa-aplicar-filtros");
    const panelEls = Array.from(containerEl.querySelectorAll(".cc-overlay-panel"));
    const buscaEl = document.getElementById("filtro-busca");
    const buscaListEl = document.getElementById("mapa-comunidades-list");
    const buscaBtn = document.getElementById("mapa-buscar-comunidade");

    const fallbackCenter = [-3.7319, -38.5267]; // Fortaleza
    const fallbackZoom = 13;
    const userZoom = 13;

    let dominio = containerEl.dataset.dominio || "";
    if (dominio.endsWith("/")) dominio = dominio.slice(0, -1);

    const API_BASE = dominio ? `${dominio}/wp-json/mapa/v1` : "/wp-json/mapa/v1";
    const API_URL = `${API_BASE}/comunidades`;
    const API_FILTROS_URL = `${API_BASE}/filtros`;

    const map = L.map(mapaEl, {
        minZoom: 3,
        maxZoom: 19,
        zoomControl: true,
    }).setView(fallbackCenter, fallbackZoom);

    L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
        attribution: "&copy; OpenStreetMap"
    }).addTo(map);

    const resizeObserver = new ResizeObserver(() => {
        map.invalidateSize();
    });

    resizeObserver.observe(mapaEl);

    const state = {
        userLocation: null,
        userMarker: null,
        markers: [],
        comunidades: [],
        autocompleteBase: [],
        termoBusca: "",
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

    const mesMap = {
        "1": "Janeiro", "2": "Fevereiro", "3": "Março", "4": "Abril", "5": "Maio", "6": "Junho",
        "7": "Julho", "8": "Agosto", "9": "Setembro", "10": "Outubro", "11": "Novembro", "12": "Dezembro",
    };

    function descricaoRecorrencia(evento) {
        const frequencia = String(evento?.frequencia || 'semanal');
        const dias = Array.isArray(evento?.dias)
            ? evento.dias.map((dia) => String(dia)).filter((dia) => Object.prototype.hasOwnProperty.call(diaMap, dia))
            : [];
        const diaSemana = diaMap[String(evento?.dia)] || 'dia não informado';
        const diaMes = evento?.dia_mes ? String(evento.dia_mes) : '';
        const mes = mesMap[String(evento?.mes)] || '';
        const numeroSemana = evento?.numero_semana ? String(evento.numero_semana) : '';

        if (frequencia === 'mensal') return diaMes ? `Todo dia ${diaMes}` : 'Mensal';
        if (frequencia === 'numero_semana') return (numeroSemana && diaSemana) ? `${numeroSemana}ª ${diaSemana} do mês` : 'Por número da semana';
        if (frequencia === 'anual') return (diaMes && mes) ? `Todo dia ${diaMes} de ${mes}` : 'Anual';
        if (dias.length) return `Toda ${dias.map((dia) => diaMap[dia]).join(', ')}`;
        return `Todo ${diaSemana}`;
    }
    function isMobile() {
        return window.matchMedia("(max-width: 1023px)").matches;
    }

    function scrollDetalhesIntoView() {
        if (!detalhesEl) return;
        detalhesEl.scrollIntoView({ behavior: "smooth", block: "start" });
    }

    function escapeHtml(value) {
        return String(value ?? "")
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/\"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    function parseJsonString(raw) {
        if (typeof raw !== "string") return null;
        const trimmed = raw.trim();
        if (!trimmed) return null;
        if (!(trimmed.startsWith("{") || trimmed.startsWith("["))) return null;

        try {
            return JSON.parse(trimmed);
        } catch (e) {
            return null;
        }
    }

    function sanitizeKey(key) {
        return String(key || "")
            .normalize("NFD")
            .replace(/[̀-ͯ]/g, "")
            .toLowerCase()
            .trim();
    }

    function isEmail(value) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(value || "").trim());
    }

    function isUrl(value) {
        return /^https?:\/\//i.test(String(value || "").trim());
    }

    function ensureUrl(value) {
        const raw = String(value || "").trim();
        if (!raw) return "";
        if (isUrl(raw)) return raw;

        const lower = raw.toLowerCase();
        const invalidTokens = ["facebook", "instagram", "youtube", "whatsapp", "twitter", "x", "linkedin", "tiktok", "email", "telefone", "site"];
        if (invalidTokens.includes(lower)) return "";

        const seemsDomain = lower.startsWith("www.") || lower.includes(".") || lower.includes("/");
        if (!seemsDomain) return "";

        return `https://${raw.replace(/^\/+/, "")}`;
    }

    function extractContatos(contatos) {
        let data = contatos;
        const parsed = parseJsonString(contatos);
        if (parsed !== null) data = parsed;

        const result = {
            telefones: [],
            emails: [],
            redes: [],
            outros: [],
        };

        const seen = new Set();

        function pushUnique(bucket, value) {
            const text = String(value || "").trim();
            if (!text) return;
            const key = `${bucket}:${text.toLowerCase()}`;
            if (seen.has(key)) return;
            seen.add(key);
            result[bucket].push(text);
        }

        function pushRede(type, label, value) {
            const href = ensureUrl(value);
            if (!href) return;
            const key = `rede:${type.toLowerCase()}:${href.toLowerCase()}`;
            if (seen.has(key)) return;
            seen.add(key);
            result.redes.push({ type, label, href });
        }

        function classify(key, value) {
            const k = sanitizeKey(key);
            const v = String(value || "").trim();
            if (!v) return;

            const lower = v.toLowerCase();
            const placeholders = ["facebook", "instagram", "youtube", "whatsapp", "twitter", "x", "linkedin", "tiktok", "email", "telefone", "site"];
            if (placeholders.includes(lower)) return;

            if (k.includes("email") || isEmail(v)) {
                pushUnique("emails", v);
                return;
            }

            if (k.includes("whatsapp") || k === "zap") {
                pushRede("whatsapp", "WhatsApp", v);
                return;
            }

            if (k.includes("telefone") || k.includes("fone") || k.includes("celular") || k === "tel") {
                pushUnique("telefones", v);
                return;
            }

            if (k.includes("instagram")) return pushRede("instagram", "Instagram", v);
            if (k.includes("facebook")) return pushRede("facebook", "Facebook", v);
            if (k.includes("youtube")) return pushRede("youtube", "YouTube", v);
            if (k.includes("tiktok")) return pushRede("tiktok", "TikTok", v);
            if (k.includes("linkedin")) return pushRede("linkedin", "LinkedIn", v);
            if (k.includes("twitter") || k === "x") return pushRede("x", "X", v);
            if (k.includes("site") || k.includes("website")) return pushRede("site", "Site", v);

            const maybeUrl = ensureUrl(v);
            if (maybeUrl && (isUrl(v) || /instagram|facebook|youtube|tiktok|linkedin|twitter|x\.com|wa\.me|whatsapp/i.test(v))) {
                pushRede("rede", "Rede", maybeUrl);
                return;
            }

            pushUnique("outros", `${key}: ${v}`);
        }

        function walk(node) {
            if (!node) return;

            if (typeof node === "string") {
                const text = node.trim();
                if (!text) return;
                if (isEmail(text)) return pushUnique("emails", text);
                if (isUrl(text)) return pushRede("rede", "Rede", text);
                return pushUnique("outros", text);
            }

            if (Array.isArray(node)) {
                node.forEach(walk);
                return;
            }

            if (typeof node === "object") {
                const tipo = node.tipo || node.type || node.chave || node.key;
                const valor = node.valor || node.value || node.url || node.link || node.contato;
                if (tipo && valor && (typeof valor === "string" || typeof valor === "number")) {
                    classify(tipo, String(valor));
                    return;
                }

                Object.entries(node).forEach(([key, value]) => {
                    if (value && typeof value === "object") {
                        walk(value);
                        return;
                    }
                    classify(key, value);
                });
            }
        }

        walk(data);
        return result;
    }

    function renderContatos(contatos) {
        const data = extractContatos(contatos);
        const blocks = [];

        if (data.telefones.length) {
            const itens = data.telefones
                .map((tel) => `<li class="flex items-center gap-2"><i class="bi bi-telephone text-slate-500"></i>${escapeHtml(tel)}</li>`)
                .join("");

            blocks.push(`
                <div class="space-y-1">
                    <p class="text-xs font-semibold text-slate-700 uppercase">Telefone</p>
                    <ul class="text-sm text-slate-700 space-y-1">${itens}</ul>
                </div>
            `);
        }

        if (data.emails.length) {
            const itens = data.emails
                .map((email) => `
                    <li class="flex items-start gap-2 break-all">
                        <i class="bi bi-envelope text-slate-500"></i>
                        <a href="mailto:${encodeURIComponent(email)}" class="hover:underline">
                            ${escapeHtml(email)}
                        </a>
                    </li>
                `).join("");

            blocks.push(`
                <div class="space-y-1">
                    <p class="text-xs font-semibold text-slate-700 uppercase">E-mail</p>
                    <ul class="text-sm text-slate-700 space-y-1">${itens}</ul>
                </div>
            `);
        }

        if (data.redes.length) {

            const iconMap = {
                facebook: "bi-facebook",
                instagram: "bi-instagram",
                whatsapp: "bi-whatsapp",
                youtube: "bi-youtube",
                tiktok: "bi-tiktok",
                linkedin: "bi-linkedin",
                x: "bi-twitter-x",
                site: "bi-globe",
                rede: "bi-link-45deg",
            };

            const itens = data.redes.map((rede) => {

                const type = String(rede.type || "rede").toLowerCase();
                const icon = iconMap[type] || iconMap.rede;

                return `
                    <a href="${escapeHtml(rede.href)}"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="flex items-center gap-2 px-3 py-2 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 text-sm">
                        <i class="bi ${icon} text-slate-600"></i>
                        <span>${escapeHtml(rede.label)}</span>
                    </a>
                `;
            }).join("");

            blocks.push(`
                <div class="space-y-2">
                    <p class="text-xs font-semibold text-slate-700 uppercase">Redes sociais</p>
                    <div class="grid grid-cols-2 gap-2">
                        ${itens}
                    </div>
                </div>
            `);
        }

        if (data.outros.length) {
            const itens = data.outros
                .map((item) => `<li>${escapeHtml(item)}</li>`)
                .join("");

            blocks.push(`
                <div class="space-y-1">
                    <p class="text-xs font-semibold text-slate-700 uppercase">Outros contatos</p>
                    <ul class="text-sm text-slate-700 space-y-1">${itens}</ul>
                </div>
            `);
        }

        return blocks.join("");
    }


    function setPanelOpen(panel, open) {
        if (!panel) return;

        const shouldOpen = !!open;
        const toggle = panel.querySelector(".cc-panel-toggle");
        const body = panel.querySelector(".cc-panel-body");

        panel.classList.toggle("is-open", shouldOpen);
        if (toggle) toggle.setAttribute("aria-expanded", shouldOpen ? "true" : "false");
        if (body) body.hidden = !shouldOpen;
    }

    function setupAccordionPanels() {
        panelEls.forEach((panel) => {
            const toggle = panel.querySelector(".cc-panel-toggle");
            if (!toggle) return;

            const startsOpen = panel.classList.contains("is-open");
            setPanelOpen(panel, startsOpen);

            toggle.addEventListener("click", () => {
                const isOpen = panel.classList.contains("is-open");
                setPanelOpen(panel, !isOpen);
                map.invalidateSize();
            });
        });
    }


    function bindDetalhesToggle() {
        const detalhesPanel = detalhesEl?.closest(".cc-overlay-panel");
        const toggle = detalhesEl?.querySelector(".cc-panel-toggle");
        if (!detalhesPanel || !toggle) return;

        toggle.addEventListener("click", () => {
            const open = detalhesPanel.classList.contains("is-open");
            setPanelOpen(detalhesPanel, !open);
            map.invalidateSize();
        });
    }

    function renderDetalhes(comunidade) {
        if (!detalhesEl) return;

        if (!comunidade) {
            detalhesEl.innerHTML = `
                <button type="button" class="cc-panel-toggle" aria-expanded="false" aria-controls="cc-panel-detalhes-body">
                    <span>Comunidade selecionada</span>
                    <span class="cc-panel-toggle-icon" aria-hidden="true"><i class="bi bi-chevron-down"></i></span>
                </button>
                <div class="cc-panel-body" id="cc-panel-detalhes-body">
                    <p class="cc-filtro-texto">Toque em um pino para ver detalhes e eventos.</p>
                </div>
            `;
            const detalhesPanel = detalhesEl.closest(".cc-overlay-panel");
            setPanelOpen(detalhesPanel, false);
            bindDetalhesToggle();
            return;
        }

        const eventosHtml = (comunidade.eventos || []).length
            ? comunidade.eventos.map((evento) => {
                const recorrencia = descricaoRecorrencia(evento);
                return `
                    <li class="bg-slate-50 border border-slate-200 rounded-lg p-3">
                        <p class="text-sm font-medium text-slate-900">${escapeHtml(evento.titulo || "Evento")}</p>
                        <p class="text-xs text-slate-600">${escapeHtml(recorrencia)} • ${escapeHtml(evento.horario || "Horário não informado")}</p>
                        ${evento.observacao ? `<br><small>${escapeHtml(evento.observacao)}</small>` : ""}
                    </li>
                `;
            }).join("")
            : "<li class='bg-slate-50 border border-slate-200 rounded-lg p-3'><p class='text-xs text-slate-600'>Sem eventos para os filtros selecionados.</p></li>";

        const contatosFormatados = renderContatos(comunidade.contatos);

        detalhesEl.innerHTML = `
            <button type="button" class="cc-panel-toggle" aria-expanded="true" aria-controls="cc-panel-detalhes-body">
                <span>Comunidade selecionada</span>
                <span class="cc-panel-toggle-icon" aria-hidden="true"><i class="bi bi-chevron-down"></i></span>
            </button>
            <div class="cc-panel-body" id="cc-panel-detalhes-body">
                <article class="space-y-4 mt-2">
                    ${comunidade.foto ? `<div class="w-full max-w-sm mx-auto"><div class="aspect-square overflow-hidden rounded-xl bg-slate-100"><img src="${escapeHtml(comunidade.foto)}" alt="${escapeHtml(comunidade.nome || "Comunidade")}" class="w-full h-full object-contain shadow-sm"></div></div>` : ""}
                    <h4 class="text-lg font-semibold text-slate-900 text-center">${escapeHtml(comunidade.nome || "Comunidade")}</h4>
                    ${comunidade.endereco ? `<p class="text-sm text-slate-600 leading-snug text-center max-w-xs mx-auto">${escapeHtml(comunidade.endereco)}</p>` : ""}
                    ${contatosFormatados ? `<div class="space-y-3">${contatosFormatados}</div>` : ""}
                    ${comunidade.distancia_km ? `<p><small>Distância: ${Number(comunidade.distancia_km).toFixed(1)} km</small></p>` : ""}
                    <div class="space-y-2">
                        <h5 class="text-sm font-semibold text-slate-800 border-b pb-1">Eventos</h5>
                        <ul class="grid gap-2">${eventosHtml}</ul>
                    </div>
                </article>
            </div>
        `;

        const detalhesPanel = detalhesEl.closest(".cc-overlay-panel");
        setPanelOpen(detalhesPanel, true);
        bindDetalhesToggle();
    }

    function buildPopup(comunidade) {
        return `
            <div class="w-[250px] text-center">
                <h3 class="text-sm font-semibold text-slate-900 mb-1">${escapeHtml(comunidade.nome)}</h3>
                ${comunidade.endereco ? `<p class="text-xs text-slate-600 leading-snug">${escapeHtml(comunidade.endereco)}</p>` : ""}
            </div>
        `;
    }

    function aplicarBusca() {
        state.termoBusca = buscaEl?.value || "";
        carregarComunidades();
    }

    function clearMarkers() {
        state.markers.forEach((marker) => marker.remove());
        state.markers = [];
    }

    function addMarker(comunidade) {
        const lat = Number(comunidade.latitude);
        const lng = Number(comunidade.longitude);
        if (!Number.isFinite(lat) || !Number.isFinite(lng)) return;

        const marker = L.marker([lat, lng]).addTo(map);
        marker.bindTooltip(`${escapeHtml(comunidade.nome)}`, {
            direction: "top",
            offset: [0, -10],
            opacity: 0.95,
        });
        marker.bindPopup(buildPopup(comunidade), { maxHeight: 260 });

        marker.on("click", function () {
            renderDetalhes(comunidade);
            if (isMobile()) scrollDetalhesIntoView();
        });

        state.markers.push(marker);
    }

    function buildUrlWithFilters() {
        const params = new URLSearchParams();

        if (filtrosForm) {
            const formData = new FormData(filtrosForm);
            for (const [key, value] of formData.entries()) {
                if (!value) continue;
                params.append(key, value);
            }
        }

        // filtros de proximidade/raio removidos
        params.delete("proximidade");
        params.delete("raio");
        params.delete("lat");
        params.delete("lng");

        const queryString = params.toString();
        return queryString ? `${API_URL}?${queryString}` : API_URL;
    }

    async function fetchComunidades() {
        const res = await fetch(buildUrlWithFilters());
        const comunidades = await res.json();
        return Array.isArray(comunidades) ? comunidades : [];
    }

    function updateAutocomplete(lista) {
        if (!buscaListEl) return;

        const nomes = Array.from(new Set((lista || [])
            .map((item) => (item?.nome || "").trim())
            .filter(Boolean)));

        buscaListEl.innerHTML = nomes
            .map((nome) => `<option value="${escapeHtml(nome)}"></option>`)
            .join("");
    }

    function filtrarPorBusca(comunidades) {
        const termo = state.termoBusca.trim().toLowerCase();
        if (!termo) return comunidades;

        return comunidades.filter((item) => {
            const nome = String(item.nome || "").toLowerCase();
            const endereco = String(item.endereco || "").toLowerCase();
            return nome.includes(termo) || endereco.includes(termo);
        });
    }

    function ajustarVisaoMapa() {
        if (state.userLocation?.lat && state.userLocation?.lng) {
            map.setView([state.userLocation.lat, state.userLocation.lng], userZoom);
            return;
        }

        const pontos = state.comunidades
            .map((c) => [Number(c.latitude), Number(c.longitude)])
            .filter(([lat, lng]) => Number.isFinite(lat) && Number.isFinite(lng));

        if (pontos.length > 0) {
            const bounds = L.latLngBounds(pontos);
            map.fitBounds(bounds, { padding: [30, 30], maxZoom: 15 });
            return;
        }

        map.setView(fallbackCenter, fallbackZoom);
    }

    async function carregarComunidades() {
        clearMarkers();
        renderDetalhes(null);

        try {
            const lista = await fetchComunidades();

            state.autocompleteBase = lista;
            updateAutocomplete(state.autocompleteBase);

            state.comunidades = filtrarPorBusca(lista);
            state.comunidades.forEach(addMarker);

            ajustarVisaoMapa();
            map.invalidateSize();
        } catch (err) {
            console.error("Erro ao carregar mapa:", err);
        }
    }

    function selectToOption(selectId, options, defaultLabel) {
        const select = document.getElementById(selectId);
        if (!select) return;

        const opts = [`<option value="">${escapeHtml(defaultLabel)}</option>`];
        (options || []).forEach((opt) => {
            opts.push(`<option value="${escapeHtml(opt.slug)}">${escapeHtml(opt.nome)}</option>`);
        });
        select.innerHTML = opts.join("");
    }

    async function carregarFiltros() {
        try {
            const res = await fetch(API_FILTROS_URL);
            const filtros = await res.json();

            selectToOption("filtro-periodo", filtros.periodos || [], "Qualquer período");
            selectToOption("filtro-tipo-evento", filtros.tipos_evento || [], "Todos os tipos");
            selectToOption("filtro-tipo-comunidade", filtros.tipos_comunidade || [], "Todos os tipos");
            selectToOption("filtro-tag", filtros.tags || [], "Todas as tags");
        } catch (err) {
            console.error("Erro ao carregar filtros:", err);
        }
    }



    function atualizarCampoDataFiltro() {
        const periodoEl = document.getElementById('filtro-periodo');
        const dataEl = document.getElementById('filtro-data');
        const dataWrapEl = document.getElementById('filtro-data-wrap');
        if (!periodoEl || !dataEl || !dataWrapEl) return;

        const habilitado = periodoEl.value === 'data';
        dataEl.disabled = !habilitado;
        dataWrapEl.style.display = habilitado ? '' : 'none';
        if (!habilitado) dataEl.value = '';
    }
    async function requestUserLocationIfNeeded() {
        if (!navigator.geolocation) return;

        try {
            const pos = await new Promise((resolve, reject) => {
                navigator.geolocation.getCurrentPosition(resolve, reject, {
                    enableHighAccuracy: true,
                    timeout: 9000,
                });
            });

            state.userLocation = {
                lat: Number(pos.coords.latitude),
                lng: Number(pos.coords.longitude),
            };

            state.userMarker = L.circleMarker([state.userLocation.lat, state.userLocation.lng], {
                radius: 7,
                color: "#1d4ed8",
                fillColor: "#3b82f6",
                fillOpacity: 0.9,
            }).addTo(map).bindTooltip("Sua localização", { direction: "top" });
        } catch (err) {
            // fallback padrão já aplicado
        }
    }

    buscaEl?.addEventListener("input", () => {
        state.termoBusca = buscaEl.value || "";

        const termo = state.termoBusca.trim().toLowerCase();
        const subset = termo
            ? state.autocompleteBase.filter((c) => String(c.nome || "").toLowerCase().includes(termo)).slice(0, 40)
            : state.autocompleteBase.slice(0, 80);
        updateAutocomplete(subset);
    });

    buscaEl?.addEventListener("change", () => {
        aplicarBusca();
    });

    buscaEl?.addEventListener("keydown", (event) => {
        if (event.key !== "Enter") return;
        event.preventDefault();
        aplicarBusca();
    });

    buscaBtn?.addEventListener("click", aplicarBusca);

    filtrosForm?.addEventListener("change", () => {
        atualizarCampoDataFiltro();
        if (!isMobile()) carregarComunidades();
    });

    aplicarBtn?.addEventListener("click", () => {
        carregarComunidades();
    });

    limparBtn?.addEventListener("click", () => {
        filtrosForm?.reset();
        if (buscaEl) buscaEl.value = "";
        state.termoBusca = "";
        updateAutocomplete(state.autocompleteBase);
        carregarComunidades();
    });

    window.addEventListener("resize", () => {
        map.invalidateSize();
    });

    setupAccordionPanels();

    await carregarFiltros();
    atualizarCampoDataFiltro();
    await requestUserLocationIfNeeded();
    await carregarComunidades();

});
