document.addEventListener("DOMContentLoaded", async function () {
    const containerEl = document.getElementById("mapa-igrejas");
    if (!containerEl) return;

    const mapaEl = document.getElementById("mapa-canvas");
    const filtrosForm = document.getElementById("mapa-filtros");
    const detalhesEl = document.getElementById("mapa-detalhes");
    const limparBtn = document.getElementById("mapa-limpar-filtros");
    const aplicarBtn = document.getElementById("mapa-aplicar-filtros");
    const toggleFiltrosBtn = document.getElementById("mapa-toggle-filtros");
    const fecharFiltrosBtn = document.getElementById("mapa-fechar-filtros");
    const sidebarEl = document.getElementById("mapa-sidebar");
    const buscaEl = document.getElementById("filtro-busca");
    const buscaListEl = document.getElementById("mapa-comunidades-list");

    const fallbackCenter = [-3.7319, -38.5267]; // Fortaleza
    const fallbackZoom = 13;
    const userZoom = 8;

    let dominio = containerEl.dataset.dominio || "";
    if (dominio.endsWith("/")) dominio = dominio.slice(0, -1);

    const API_BASE = dominio ? `${dominio}/wp-json/mapa/v1` : "/wp-json/mapa/v1";
    const API_URL = `${API_BASE}/comunidades`;
    const API_FILTROS_URL = `${API_BASE}/filtros`;
    const cadastroBaseUrl = containerEl.dataset.urlCadastro || "/cadastro-comunidade/";
    const userLoggedIn = containerEl.dataset.userLogado === "1";

    const map = L.map(mapaEl, {
        minZoom: 3,
        maxZoom: 19,
        zoomControl: true,
    }).setView(fallbackCenter, fallbackZoom);

    L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
        attribution: "&copy; OpenStreetMap"
    }).addTo(map);

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

    function isMobile() {
        return window.matchMedia("(max-width: 1023px)").matches;
    }

    function scrollMapIntoView() {
        if (!containerEl) return;

        const top = window.pageYOffset + containerEl.getBoundingClientRect().top - 20;
        window.scrollTo({ top: Math.max(0, top), behavior: "smooth" });
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
            const itens = data.telefones.map((tel) => `<li>📞 ${escapeHtml(tel)}</li>`).join("");
            blocks.push(`<div><strong>Telefone</strong><ul style="margin:.25rem 0 0;padding-left:1rem;">${itens}</ul></div>`);
        }

        if (data.emails.length) {
            const itens = data.emails.map((email) => `<li>✉️ <a href="mailto:${encodeURIComponent(email)}">${escapeHtml(email)}</a></li>`).join("");
            blocks.push(`<div><strong>E-mail</strong><ul style="margin:.25rem 0 0;padding-left:1rem;">${itens}</ul></div>`);
        }

        if (data.redes.length) {
            const iconMap = {
                facebook: { cls: "bi bi-facebook", fallback: "f" },
                instagram: { cls: "bi bi-instagram", fallback: "◎" },
                whatsapp: { cls: "bi bi-whatsapp", fallback: "✆" },
                youtube: { cls: "bi bi-youtube", fallback: "▶" },
                tiktok: { cls: "bi bi-tiktok", fallback: "♪" },
                linkedin: { cls: "bi bi-linkedin", fallback: "in" },
                x: { cls: "bi bi-twitter-x", fallback: "𝕏" },
                site: { cls: "bi bi-globe", fallback: "◉" },
                rede: { cls: "bi bi-link-45deg", fallback: "↗" },
            };

            const itens = data.redes.map((rede) => {
                const type = String(rede.type || "rede").toLowerCase();
                const cfg = iconMap[type] || iconMap.rede;
                const cls = `cc-social-pill cc-social-${type}`;
                return `<a href="${escapeHtml(rede.href)}" target="_blank" rel="noopener noreferrer" class="${cls}"><i class="${cfg.cls}" aria-hidden="true">${cfg.fallback}</i><span>${escapeHtml(rede.label)}</span></a>`;
            }).join("");

            blocks.push(`<div><strong>Redes sociais</strong><div class="cc-social-grid" style="margin-top:.35rem;">${itens}</div></div>`);
        }

        if (data.outros.length) {
            const itens = data.outros.map((item) => `<li>${escapeHtml(item)}</li>`).join("");
            blocks.push(`<div><strong>Outros contatos</strong><ul style="margin:.25rem 0 0;padding-left:1rem;">${itens}</ul></div>`);
        }

        return blocks.join("");
    }


    function buildEditarLink(comunidade) {
        const base = String(cadastroBaseUrl || '/cadastro-comunidade/').trim();
        const separador = base.includes('?') ? '&' : '?';
        return `${base}${separador}editar_comunidade=${encodeURIComponent(comunidade.id)}`;
    }

    function setSidebarOpen(open) {
        if (!sidebarEl || !isMobile()) return;
        sidebarEl.classList.toggle("is-open", !!open);
        window.setTimeout(() => map.invalidateSize(), 260);
    }

    function renderDetalhes(comunidade) {
        if (!detalhesEl) return;

        if (!comunidade) {
            detalhesEl.innerHTML = `
                <h3>Comunidade selecionada</h3>
                <p>Toque em um pino para ver detalhes e eventos.</p>
            `;
            return;
        }

        const eventosHtml = (comunidade.eventos || []).length
            ? comunidade.eventos.map((evento) => {
                const dia = diaMap[String(evento.dia)] || evento.dia || "Dia não informado";
                return `
                    <li>
                        <strong>${escapeHtml(evento.titulo || "Evento")}</strong><br>
                        ${escapeHtml(dia)} • ${escapeHtml(evento.horario || "Horário não informado")}
                        ${evento.observacao ? `<br><small>${escapeHtml(evento.observacao)}</small>` : ""}
                    </li>
                `;
            }).join("")
            : "<li>Sem eventos para os filtros selecionados.</li>";

        const contatosFormatados = renderContatos(comunidade.contatos);

        detalhesEl.innerHTML = `
            <h3>Comunidade selecionada</h3>
            <article>
                <h4>${escapeHtml(comunidade.nome || "Comunidade")}</h4>
                ${comunidade.foto ? `<img src="${escapeHtml(comunidade.foto)}" alt="${escapeHtml(comunidade.nome || "Comunidade")}" style="width:100%;max-height:170px;object-fit:cover;border-radius:8px;margin:.45rem 0;" />` : ""}
                ${comunidade.endereco ? `<p>${escapeHtml(comunidade.endereco)}</p>` : ""}
                ${contatosFormatados ? `<div style="display:grid;gap:.55rem;">${contatosFormatados}</div>` : ""}
                ${comunidade.distancia_km ? `<p><small>Distância: ${Number(comunidade.distancia_km).toFixed(1)} km</small></p>` : ""}
                <strong>Eventos</strong>
                <ul style="margin-top:.45rem;padding-left:1rem;display:grid;gap:.4rem;">${eventosHtml}</ul>
                ${userLoggedIn ? `<p style="margin-top:.8rem;"><a href="${escapeHtml(buildEditarLink(comunidade))}" style="color:#4338ca;text-decoration:underline;font-weight:600;">existe algo errado ou desatualizado? clique aqui para atualizar</a></p>` : ""}
            </article>
        `;
    }

    function buildPopup(comunidade) {
        const eventos = comunidade.eventos || [];
        const eventosHtml = eventos.length
            ? eventos.map((evento) => {
                const dia = diaMap[String(evento.dia)] || evento.dia || "Dia";
                return `<div style="margin-top:6px;padding-top:6px;border-top:1px solid #e2e8f0;"><strong>${escapeHtml(evento.titulo || "Evento")}</strong><br><small>${escapeHtml(dia)} • ${escapeHtml(evento.horario || "")}</small></div>`;
            }).join("")
            : '<div style="margin-top:6px;color:#64748b;">Sem eventos nos filtros atuais.</div>';

        return `
            <div style="min-width:250px;">
                <h3 style="margin:0 0 4px;font-size:14px;color:#0f172a;">${escapeHtml(comunidade.nome)}</h3>
                ${comunidade.endereco ? `<p style="margin:0 0 6px;font-size:12px;color:#475569;">${escapeHtml(comunidade.endereco)}</p>` : ""}
                ${eventosHtml}
                ${userLoggedIn ? `<div style="margin-top:8px;padding-top:6px;border-top:1px solid #e2e8f0;"><a href="${escapeHtml(buildEditarLink(comunidade))}" style="font-size:12px;color:#4338ca;text-decoration:underline;font-weight:600;">existe algo errado ou desatualizado? clique aqui para atualizar</a></div>` : ""}
            </div>
        `;
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
        marker.bindTooltip(`${escapeHtml(comunidade.nome)} (${(comunidade.eventos || []).length} eventos)`, {
            direction: "top",
            offset: [0, -10],
            opacity: 0.95,
        });
        marker.bindPopup(buildPopup(comunidade), { maxHeight: 260 });

        marker.on("click", function () {
            renderDetalhes(comunidade);
            if (isMobile()) setSidebarOpen(false);
            scrollMapIntoView();
            setTimeout(scrollDetalhesIntoView, 80);
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

            selectToOption("filtro-dia", filtros.dias || [], "Qualquer dia");
            selectToOption("filtro-tipo-evento", filtros.tipos_evento || [], "Todos os tipos");
            selectToOption("filtro-tipo-comunidade", filtros.tipos_comunidade || [], "Todos os tipos");
            selectToOption("filtro-tag", filtros.tags || [], "Todas as tags");
        } catch (err) {
            console.error("Erro ao carregar filtros:", err);
        }
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
        carregarComunidades();
    });

    buscaEl?.addEventListener("keydown", (event) => {
        if (event.key !== "Enter") return;
        event.preventDefault();
        carregarComunidades();
    });

    filtrosForm?.addEventListener("change", () => {
        if (!isMobile()) carregarComunidades();
    });

    aplicarBtn?.addEventListener("click", () => {
        carregarComunidades();
        if (isMobile()) setSidebarOpen(false);
    });

    limparBtn?.addEventListener("click", () => {
        filtrosForm?.reset();
        if (buscaEl) buscaEl.value = "";
        state.termoBusca = "";
        updateAutocomplete(state.autocompleteBase);
        carregarComunidades();
    });

    toggleFiltrosBtn?.addEventListener("click", () => setSidebarOpen(true));
    fecharFiltrosBtn?.addEventListener("click", () => setSidebarOpen(false));

    window.addEventListener("resize", () => {
        if (!isMobile()) setSidebarOpen(false);
        map.invalidateSize();
    });

    await carregarFiltros();
    await requestUserLocationIfNeeded();
    await carregarComunidades();

    if (isMobile()) {
        setSidebarOpen(false);
    }
});
