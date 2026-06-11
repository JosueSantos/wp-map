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
    const filtroEventoPeriodoEl = document.getElementById("filtro-evento-periodo");
    const filtroTagMissaEl = document.getElementById("filtro-tag-missa");
    const filtroTagAcaoEl = document.getElementById("filtro-tag-acao-caritativa");
    const filtroTagHiddenEl = document.getElementById("filtro-tag");
    const urlCadastro = containerEl.dataset.urlCadastro || "";
    const quickFilterBtns = Array.from(containerEl.querySelectorAll("[data-quick-filter]"));
    const toggleFiltrosBtn = containerEl.querySelector("[data-toggle-filtros]");
    const viewModeBtns = Array.from(containerEl.querySelectorAll("[data-view-mode]"));
    const listaLocaisEl = document.getElementById("mapa-lista-locais");
    const filtrosFixosEl = containerEl.querySelector("[data-filtros-fixo]");

    const fallbackCenter = [-3.72528, -38.52439]; // Catedral Metropolitana de Fortaleza
    const fallbackZoom = 15;
    const userZoom = 16;

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

    const markerLayerGroup = L.layerGroup().addTo(map);

    const resizeObserver = new ResizeObserver(() => {
        map.invalidateSize();
    });

    resizeObserver.observe(mapaEl);

    const state = {
        userLocation: null,
        userMarker: null,
        markers: [],
        comunidades: [],
        requestId: 0,
        autocompleteBase: [],
        termoBusca: "",
        viewMode: "mapa",
        quickFilterAtivo: "",
        paginaAtual: 1,
        itensPorPagina: 5,
        comunidadeSelecionadaId: null,
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

        if (frequencia === 'missa_dominical') return 'Missa Dominical';
        if (frequencia === 'mensal') return diaMes ? `Todo dia ${diaMes}` : 'Mensal';
        if (frequencia === 'numero_semana') return (numeroSemana && diaSemana) ? `${numeroSemana}ª ${diaSemana} do mês` : 'Por número da semana';
        if (frequencia === 'anual') return (diaMes && mes) ? `Todo dia ${diaMes} de ${mes}` : 'Anual';
        if (dias.length) return `Toda ${dias.map((dia) => diaMap[dia]).join(', ')}`;
        return `Todo ${diaSemana}`;
    }


    function tituloPadraoAtividade(evento) {
        const textos = [evento?.tipo, evento?.titulo_base, evento?.titulo, evento?.descricao]
            .map((valor) => String(valor || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase());

        if (textos.some((texto) => texto.includes('missa'))) return 'Missa';
        if (textos.some((texto) => texto.includes('conf'))) return 'Confissão';
        if (textos.some((texto) => texto.includes('ador') || texto.includes('santissimo'))) return 'Adoração ao Santíssimo';
        if (textos.some((texto) => texto.includes('carit') || texto.includes('acao'))) return 'Ação Caritativa';

        return String(evento?.titulo_base || evento?.titulo || 'Atividade');
    }

    function labelsRecorrenciaAtividade(evento) {
        const frequencia = String(evento?.frequencia || 'semanal');
        const dias = Array.isArray(evento?.dias)
            ? evento.dias.map((dia) => String(dia)).filter((dia) => Object.prototype.hasOwnProperty.call(diaMap, dia))
            : [];

        if (frequencia === 'missa_dominical') return ['Domingo'];

        if (frequencia === 'semanal') {
            const diasSemana = dias.length
                ? dias
                : (Object.prototype.hasOwnProperty.call(diaMap, String(evento?.dia)) ? [String(evento.dia)] : []);

            return diasSemana.length ? diasSemana.map((dia) => diaMap[dia]) : ['Dia não informado'];
        }

        return [descricaoRecorrencia(evento)];
    }


    function formatarIntervaloEvento(evento) {
        const inicio = String(evento?.horario_inicio || '').trim();
        const fim = String(evento?.horario_fim || '').trim();

        if (inicio) {
            return fim ? `${inicio} - ${fim}` : inicio;
        }

        return String(evento?.horario || '').trim() || 'Horário não informado';
    }

    function agruparAtividadesParaExibicao(eventos) {
        const grupos = new Map();

        eventos.forEach((evento) => {
            const frequencia = String(evento?.frequencia || 'semanal');
            const titulo = (frequencia === 'semanal' || frequencia === 'missa_dominical')
                ? tituloPadraoAtividade(evento)
                : String(evento?.titulo_base || evento?.titulo || tituloPadraoAtividade(evento));
            const chave = `${titulo.toLowerCase()}|${frequencia}`;

            if (!grupos.has(chave)) {
                grupos.set(chave, {
                    titulo,
                    linhas: new Map(),
                    observacoes: [],
                });
            }

            const grupo = grupos.get(chave);
            const horario = formatarIntervaloEvento(evento);
            labelsRecorrenciaAtividade(evento).forEach((label) => {
                const recorrencia = String(label || '').trim() || 'Dia não informado';
                if (!grupo.linhas.has(recorrencia)) grupo.linhas.set(recorrencia, []);
                const horarios = grupo.linhas.get(recorrencia);
                if (!horarios.includes(horario)) horarios.push(horario);
            });

            const observacao = String(evento?.observacao || '').trim();
            if (observacao && !grupo.observacoes.includes(observacao)) grupo.observacoes.push(observacao);
        });

        return Array.from(grupos.values()).map((grupo) => ({
            ...grupo,
            linhas: Array.from(grupo.linhas.entries()).map(([recorrencia, horarios]) => ({
                recorrencia,
                horarios: horarios.sort((a, b) => a.localeCompare(b, 'pt-BR', { numeric: true })),
            })),
        }));
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

    function revelarFiltrosFixos() {
        if (!filtrosFixosEl) return;
        filtrosFixosEl.classList.remove("cc-filtros-mapa-fixo--oculto");
    }

    function ocultarFiltrosFixos() {
        if (!filtrosFixosEl) return;
        filtrosFixosEl.classList.add("cc-filtros-mapa-fixo--oculto");
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

    function buildSocialLink(type, value) {
        const raw = String(value || '').trim();
        if (!raw) return '';

        if (/^https?:\/\//i.test(raw)) return raw;

        if (type === 'instagram' && /^@[a-z0-9._]+$/i.test(raw)) return `https://instagram.com/${raw.slice(1)}`;
        if (type === 'facebook' && /^@[a-z0-9._-]+$/i.test(raw)) return `https://facebook.com/${raw.slice(1)}`;
        if (type === 'youtube' && /^@[a-z0-9._-]+$/i.test(raw)) return `https://youtube.com/${raw}`;

        if (type === 'whatsapp') {
            const digits = raw.replace(/\D+/g, '');
            return digits ? `https://wa.me/${digits}` : '';
        }

        return ensureUrl(raw);
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
            const original = String(value || '').trim();
            const href = buildSocialLink(String(type || '').toLowerCase(), original);
            if (!href) return;
            const key = `rede:${type.toLowerCase()}:${href.toLowerCase()}`;
            if (seen.has(key)) return;
            seen.add(key);
            result.redes.push({ type, label, href, display: original || href });
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
                .map((tel) => `<li class="flex items-center gap-2"><i class="bi bi-telephone text-slate-500"></i><a href="tel:${escapeHtml(String(tel).replace(/[^0-9+]/g, ""))}" target="_blank" rel="noopener noreferrer" class="text-sky-700 hover:text-sky-900 hover:underline transition">${escapeHtml(tel)}</a></li>`)
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
                        <a href="mailto:${encodeURIComponent(email)}" target="_blank" rel="noopener noreferrer" class="text-sky-700 hover:text-sky-900 hover:underline transition">
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
                    class="flex items-center gap-2 px-3 py-2 rounded-lg border border-slate-200 bg-white text-sky-700 hover:shadow-md text-sm transition">
                        <i class="bi ${icon} text-slate-600"></i>
                        <span>${escapeHtml(rede.display || rede.label)}</span>
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
            containerEl.classList.remove("cc-mobile-comunidade-selecionada");
            state.comunidadeSelecionadaId = null;
            const detalhesPanel = detalhesEl.closest(".cc-overlay-panel");
            if (detalhesPanel) detalhesPanel.hidden = true;
            detalhesEl.innerHTML = `
                <button type="button" class="cc-panel-toggle" aria-expanded="false" aria-controls="cc-panel-detalhes-body">
                    <span>Local selecionado</span>
                    <span class="cc-panel-toggle-icon" aria-hidden="true"><i class="bi bi-chevron-down"></i></span>
                </button>
                <div class="cc-panel-body" id="cc-panel-detalhes-body">
                    <p class="cc-filtro-texto">Toque em um pino para ver detalhes e atividades.</p>
                </div>
            `;
            const detalhesPanelAtual = detalhesEl.closest(".cc-overlay-panel");
            setPanelOpen(detalhesPanelAtual, false);
            bindDetalhesToggle();
            return;
        }

        if (isMobile()) {
            containerEl.classList.add("cc-mobile-comunidade-selecionada");
        } else {
            containerEl.classList.remove("cc-mobile-comunidade-selecionada");
        }

        state.comunidadeSelecionadaId = Number(comunidade.id) || null;
        const detalhesPanel = detalhesEl.closest(".cc-overlay-panel");
        if (detalhesPanel) detalhesPanel.hidden = false;

        const eventosOrdenados = Array.isArray(comunidade.todas_atividades)
            ? comunidade.todas_atividades
            : (Array.isArray(comunidade.eventos) ? comunidade.eventos : []);

        const eventosHtml = eventosOrdenados.length
            ? agruparAtividadesParaExibicao(eventosOrdenados).map((grupo) => `
                    <li class="bg-slate-50 border border-slate-200 rounded-lg p-3">
                        <p class="text-sm font-medium text-slate-900">${escapeHtml(grupo.titulo || "Atividade")}</p>
                        <div class="mt-1 space-y-1">
                            ${grupo.linhas.map((linha) => `<p class="text-xs text-slate-600"><strong>${escapeHtml(linha.recorrencia)}:</strong> ${escapeHtml(linha.horarios.join(" - "))}</p>`).join("")}
                        </div>
                        ${grupo.observacoes.map((observacao) => `<p class="mt-1 text-xs text-slate-500">${escapeHtml(observacao)}</p>`).join("")}
                    </li>
                `).join("")
            : "<li class='bg-slate-50 border border-slate-200 rounded-lg p-3'><p class='text-xs text-slate-600'>Sem atividades para os filtros selecionados.</p></li>";

        const contatosFormatados = renderContatos(comunidade.contatos);

        const linkEdicao = urlCadastro && Number(comunidade.id) > 0
            ? `${urlCadastro}${urlCadastro.includes("?") ? "&" : "?"}editar_comunidade=${Number(comunidade.id)}`
            : "";
        const linkSingle = comunidade.permalink || "";
        const shareUrl = encodeURIComponent(linkSingle || window.location.href);
        const shareText = encodeURIComponent(`Confira este local: ${comunidade.nome || "Comunidade"}`);

        detalhesEl.innerHTML = `
            <button type="button" class="cc-panel-toggle" aria-expanded="true" aria-controls="cc-panel-detalhes-body">
                <span>Local selecionado</span>
                <span class="cc-panel-toggle-icon" aria-hidden="true"><i class="bi bi-chevron-down"></i></span>
            </button>
            <div class="cc-panel-body" id="cc-panel-detalhes-body">
                <article class="space-y-4 mt-2">
                    ${comunidade.foto ? `<div class="w-full max-w-sm mx-auto"><div class="aspect-square overflow-hidden rounded-xl bg-slate-100"><img src="${escapeHtml(comunidade.foto)}" alt="${escapeHtml(comunidade.nome || "Comunidade")}" class="w-full h-full object-contain shadow-sm"></div></div>` : ""}
                    <h4 class="text-lg font-semibold text-slate-900 text-center">${escapeHtml(comunidade.nome || "Comunidade")}</h4>
                    ${comunidade.endereco ? `<p class="text-sm text-slate-600 leading-snug text-center max-w-xs mx-auto">${escapeHtml(comunidade.endereco)}</p>` : ""}
                    ${contatosFormatados ? `<div class="space-y-3">${contatosFormatados}</div>` : ""}
                    ${comunidade.distancia_km ? `<p><small>Distância: ${Number(comunidade.distancia_km).toFixed(1)} km</small></p>` : ""}
                    <div class="cc-detalhes-acoes">
                        ${linkSingle ? `<a class="cc-btn-detalhes cc-btn-detalhes--primary" href="${escapeHtml(linkSingle)}"><i class="bi bi-info-circle"></i> Ver mais informações</a>` : ""}
                        <a class="cc-btn-detalhes cc-btn-detalhes--whatsapp" href="https://wa.me/?text=${shareText}%20${shareUrl}" target="_blank" rel="noopener noreferrer"><i class="bi bi-whatsapp"></i> Compartilhar no WhatsApp</a>
                        <a class="cc-btn-detalhes cc-btn-detalhes--facebook" href="https://www.facebook.com/sharer/sharer.php?u=${shareUrl}" target="_blank" rel="noopener noreferrer"><i class="bi bi-facebook"></i> Compartilhar no Facebook</a>
                    </div>
                    <div class="space-y-2">
                        <h5 class="text-sm font-semibold text-slate-800 border-b pb-1">Atividades</h5>
                        <ul class="grid gap-2">${eventosHtml}</ul>
                    </div>
                    ${linkEdicao ? `<p class="text-xs text-slate-600 pt-2 border-t border-slate-200"><a href="${escapeHtml(linkEdicao)}" class="text-sky-700 hover:underline font-medium">Existe alguma informação incorreta ou desatualizada?</a></p>` : ""}
                </article>
            </div>
        `;

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
        limparFiltrosExceto({ manterBusca: true });
        state.termoBusca = buscaEl?.value || "";
        carregarComunidades();
    }

    function clearMarkers() {
        markerLayerGroup.clearLayers();
        state.markers = [];
    }

    function addMarker(comunidade) {
        const lat = Number(comunidade.latitude);
        const lng = Number(comunidade.longitude);
        if (!Number.isFinite(lat) || !Number.isFinite(lng)) return;

        const marker = L.marker([lat, lng]);
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

        markerLayerGroup.addLayer(marker);
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
        params.delete("proximidade");

        const queryString = params.toString();
        return queryString ? `${API_URL}?${queryString}` : API_URL;
    }

    async function fetchComunidades() {
        const res = await fetch(buildUrlWithFilters(), { headers: { Accept: "application/json" } });
        const raw = await res.text();
        let comunidades = [];
        try {
            comunidades = JSON.parse(raw);
        } catch (e) {
            throw new Error(`Resposta inválida da API: ${raw.slice(0, 140)}`);
        }
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

        map.setView(fallbackCenter, fallbackZoom);
    }

    function normalizarCoordenada(valor) {
        if (typeof valor === "number") return Number.isFinite(valor) ? valor : NaN;
        return Number(String(valor ?? "").replace(",", ".").trim());
    }

    function distanciaKm(lat1, lng1, lat2, lng2) {
        const toRad = (graus) => (graus * Math.PI) / 180;
        const raioTerraKm = 6371;
        const dLat = toRad(lat2 - lat1);
        const dLng = toRad(lng2 - lng1);
        const a = Math.sin(dLat / 2) ** 2
            + Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) * Math.sin(dLng / 2) ** 2;
        return 2 * raioTerraKm * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    }

    function ordenarComunidades(lista) {
        const copia = Array.isArray(lista) ? [...lista] : [];

        if (state.userLocation?.lat && state.userLocation?.lng) {
            return copia.sort((a, b) => {
                const alat = normalizarCoordenada(a?.latitude);
                const alng = normalizarCoordenada(a?.longitude);
                const blat = normalizarCoordenada(b?.latitude);
                const blng = normalizarCoordenada(b?.longitude);
                const da = (Number.isFinite(alat) && Number.isFinite(alng))
                    ? distanciaKm(state.userLocation.lat, state.userLocation.lng, alat, alng)
                    : Number.MAX_SAFE_INTEGER;
                const db = (Number.isFinite(blat) && Number.isFinite(blng))
                    ? distanciaKm(state.userLocation.lat, state.userLocation.lng, blat, blng)
                    : Number.MAX_SAFE_INTEGER;
                const va = Number.isFinite(da) ? da : Number.MAX_SAFE_INTEGER;
                const vb = Number.isFinite(db) ? db : Number.MAX_SAFE_INTEGER;
                return va - vb;
            });
        }

        return copia.sort((a, b) => {
            const ea = Array.isArray(a?.eventos) ? a.eventos : [];
            const eb = Array.isArray(b?.eventos) ? b.eventos : [];
            const ha = String(ea[0]?.horario_inicio || ea[0]?.horario || "99:99");
            const hb = String(eb[0]?.horario_inicio || eb[0]?.horario || "99:99");
            return ha.localeCompare(hb);
        });
    }


    function atualizarEstadoSelecaoLista() {
        if (!listaLocaisEl) return;
        const selected = Number(state.comunidadeSelecionadaId) || null;
        listaLocaisEl.querySelectorAll(".cc-local-item").forEach((el) => {
            const id = Number(el.dataset.id) || null;
            el.classList.toggle("is-selected", !!selected && id === selected);
        });
    }

    function renderListaLocais() {
        if (!listaLocaisEl) return;
        if (!state.comunidades.length) {
            listaLocaisEl.innerHTML = '<p class="cc-filtro-texto">Nenhum local encontrado para os filtros selecionados.</p>';
            return;
        }

        const totalPaginas = Math.max(1, Math.ceil(state.comunidades.length / state.itensPorPagina));
        state.paginaAtual = Math.min(Math.max(1, state.paginaAtual), totalPaginas);
        const inicio = (state.paginaAtual - 1) * state.itensPorPagina;
        const paginaItens = state.comunidades.slice(inicio, inicio + state.itensPorPagina);

        const itensHtml = paginaItens.map((c) => `
            <article class="cc-local-item" data-id="${Number(c.id) || ''}" role="button" tabindex="0">
                <h4>${escapeHtml(c.nome || 'Local')}</h4>
                ${c.endereco ? `<p>${escapeHtml(c.endereco)}</p>` : ''}
            </article>
        `).join('');

        listaLocaisEl.innerHTML = `${itensHtml}<div class="cc-lista-pagination"><button type="button" data-page="prev" ${state.paginaAtual <= 1 ? 'disabled' : ''}>Anterior</button><span>Página ${state.paginaAtual} de ${totalPaginas}</span><button type="button" data-page="next" ${state.paginaAtual >= totalPaginas ? 'disabled' : ''}>Próxima</button></div>`;

        listaLocaisEl.querySelectorAll('.cc-local-item').forEach((itemEl) => {
            const selecionar = () => {
                const id = Number(itemEl.dataset.id);
                const comunidade = state.comunidades.find((c) => Number(c.id) === id);
                if (!comunidade) return;
                renderDetalhes(comunidade);
                atualizarEstadoSelecaoLista();
            };
            itemEl.addEventListener('click', selecionar);
            itemEl.addEventListener('keydown', (ev) => {
                if (ev.key === 'Enter' || ev.key === ' ') { ev.preventDefault(); selecionar(); }
            });
        });

        const prevBtn = listaLocaisEl.querySelector('[data-page=\"prev\"]');
        const nextBtn = listaLocaisEl.querySelector('[data-page=\"next\"]');
        if (prevBtn) prevBtn.addEventListener('click', () => { state.paginaAtual -= 1; renderListaLocais(); });
        if (nextBtn) nextBtn.addEventListener('click', () => { state.paginaAtual += 1; renderListaLocais(); });

        atualizarEstadoSelecaoLista();
    }

    function atualizarModoVisualizacao() {
        const mostrarMapa = state.viewMode === 'mapa';
        if (mapaEl) mapaEl.hidden = !mostrarMapa;
        if (listaLocaisEl) listaLocaisEl.hidden = mostrarMapa;
        if (listaLocaisEl) listaLocaisEl.style.display = mostrarMapa ? 'none' : 'grid';
        viewModeBtns.forEach((btn) => btn.classList.toggle('is-active', btn.dataset.viewMode === state.viewMode));
        if (mostrarMapa) map.invalidateSize();
    }


    function limparFiltrosExceto(opcoes = {}) {
        const {
            manterBusca = false,
            manterTipoComunidade = false,
            manterEventoPeriodo = false,
        } = opcoes;

        const tipoComunidadeEl = document.getElementById('filtro-tipo-comunidade');
        const valorBusca = manterBusca ? String(buscaEl?.value || '') : '';
        const valorTipoComunidade = manterTipoComunidade ? String(tipoComunidadeEl?.value || '') : '';
        const valorEventoPeriodo = manterEventoPeriodo ? String(filtroEventoPeriodoEl?.value || '|') : '|';

        filtrosForm?.reset();

        if (filtroEventoPeriodoEl) filtroEventoPeriodoEl.value = valorEventoPeriodo;
        if (tipoComunidadeEl) tipoComunidadeEl.value = valorTipoComunidade;
        if (filtroTagMissaEl) filtroTagMissaEl.value = '';
        if (filtroTagAcaoEl) filtroTagAcaoEl.value = '';
        if (buscaEl) buscaEl.value = valorBusca;

        sincronizarFiltrosEventoPeriodo();
        sincronizarFiltroTag();
        atualizarCampoDataFiltro();

        state.quickFilterAtivo = '';
        quickFilterBtns.forEach((btn) => btn.classList.remove('is-active'));

        state.termoBusca = valorBusca;
        updateAutocomplete(state.autocompleteBase);
    }

    function aplicarFiltroRapido(tipo) {
        if (!filtroEventoPeriodoEl) return;
        limparFiltrosExceto({ manterEventoPeriodo: true });
        state.quickFilterAtivo = tipo;
        if (tipo === 'missa_hoje') filtroEventoPeriodoEl.value = 'hoje|missa';
        if (tipo === 'confissao_hoje') filtroEventoPeriodoEl.value = 'hoje|confissao';
        if (tipo === 'adoracao_semana') {
            const option = Array.from(filtroEventoPeriodoEl.options || []).find((opt) => String(opt.value || '').includes('semana|') && String(opt.value || '').toLowerCase().includes('ador'));
            if (option) filtroEventoPeriodoEl.value = option.value;
        }
        quickFilterBtns.forEach((btn) => btn.classList.toggle('is-active', btn.dataset.quickFilter === tipo));
        sincronizarFiltrosEventoPeriodo();
        atualizarCampoDataFiltro();
        carregarComunidades();
    }

    async function carregarComunidades() {
        const requestId = ++state.requestId;
        clearMarkers();
        renderDetalhes(null);

        try {
            const lista = await fetchComunidades();
            if (requestId !== state.requestId) return;

            const unicos = [];
            const vistos = new Set();
            (lista || []).forEach((item) => {
                const id = Number(item?.id);
                if (Number.isFinite(id) && id > 0) {
                    if (vistos.has(id)) return;
                    vistos.add(id);
                }
                unicos.push(item);
            });

            state.autocompleteBase = unicos;
            updateAutocomplete(state.autocompleteBase);

            const filtrados = filtrarPorBusca(unicos);
            state.comunidades = ordenarComunidades(filtrados);
            state.paginaAtual = 1;
            state.comunidades.forEach(addMarker);
            renderListaLocais();

            ajustarVisaoMapa();
            map.invalidateSize();
        } catch (err) {
            if (requestId !== state.requestId) return;
            console.error("Erro ao carregar mapa:", err);
        }
    }


    function normalizarSlugTipo(valor) {
        const slug = sanitizeKey(valor);
        if (slug.includes('confiss')) return 'confissao';
        if (slug.includes('acao') || slug.includes('carit')) return 'acao_caritativa';
        if (slug.includes('ador') || slug.includes('sant')) return 'adoracao_santissimo';
        if (slug.includes('missa')) return 'missa';
        return slug.replace(/[^a-z0-9_\-]/g, '');
    }

    function sincronizarFiltroTag() {
        if (!filtroTagHiddenEl) return;

        let tagMissa = String(filtroTagMissaEl?.value || '').trim();
        let tagAcao = String(filtroTagAcaoEl?.value || '').trim();

        if (tagMissa && tagAcao) {
            if (document.activeElement === filtroTagMissaEl) {
                tagAcao = '';
                if (filtroTagAcaoEl) filtroTagAcaoEl.value = '';
            } else {
                tagMissa = '';
                if (filtroTagMissaEl) filtroTagMissaEl.value = '';
            }
        }

        filtroTagHiddenEl.value = tagMissa || tagAcao || '';
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

    function preencherFiltroEventoPeriodo(tiposEvento) {
        if (!filtroEventoPeriodoEl) return;

        const mapTipos = {};
        (tiposEvento || []).forEach((tipo) => {
            mapTipos[String(tipo.slug || '').toLowerCase()] = tipo;
        });

        const opcoes = [{ value: '|', label: 'Todas as atividades' }];

        const missa = mapTipos['missa'];
        if (missa) {
            opcoes.push({ value: `hoje|${missa.slug}`, label: 'Missa hoje' });
            opcoes.push({ value: `semana|${missa.slug}`, label: 'Missa esta semana' });
            opcoes.push({ value: `data|${missa.slug}`, label: 'Missa data específica' });
        }

        const confissao = mapTipos['confissao'] || mapTipos['confissão'];
        if (confissao) {
            opcoes.push({ value: `hoje|${confissao.slug}`, label: 'Confissão hoje' });
            opcoes.push({ value: `semana|${confissao.slug}`, label: 'Confissão esta semana' });
            opcoes.push({ value: `data|${confissao.slug}`, label: 'Confissão data específica' });
        }

        const adoracao = Object.values(mapTipos).find((tipo) => {
            const slug = String(tipo?.slug || '').toLowerCase();
            return slug.includes('ador') || slug.includes('sant');
        });
        if (adoracao) {
            opcoes.push({ value: `semana|${adoracao.slug}`, label: 'Adoração ao Santíssimo esta semana' });
        }

        filtroEventoPeriodoEl.innerHTML = opcoes
            .map((opcao) => `<option value="${escapeHtml(opcao.value)}">${escapeHtml(opcao.label)}</option>`)
            .join('');
    }

    function sincronizarFiltrosEventoPeriodo() {
        const periodoEl = document.getElementById('filtro-periodo');
        const tipoEventoEl = document.getElementById('filtro-tipo-evento');
        if (!filtroEventoPeriodoEl || !periodoEl || !tipoEventoEl) return;

        const [periodo, tipoEvento] = String(filtroEventoPeriodoEl.value || '|').split('|');
        periodoEl.value = periodo || '';
        tipoEventoEl.value = tipoEvento || '';
    }

    async function carregarFiltros() {
        try {
            const res = await fetch(API_FILTROS_URL);
            const filtros = await res.json();

            preencherFiltroEventoPeriodo(filtros.tipos_evento || []);
            selectToOption("filtro-tipo-comunidade", filtros.tipos_comunidade || [], "Todos os locais");

            const tagsPorTipo = {};
            (filtros.tags || []).forEach((tag) => {
                const tipo = normalizarSlugTipo(tag?.tipo_evento_slug || tag?.tipo_evento || '');
                if (!tagsPorTipo[tipo]) tagsPorTipo[tipo] = [];
                tagsPorTipo[tipo].push(tag);
            });

            selectToOption("filtro-tag-missa", tagsPorTipo.missa || [], "Todas as características de missa");
            selectToOption("filtro-tag-acao-caritativa", [
                { slug: "com_alguma_obra_caritativa", nome: "Com alguma Obra Caritativa" },
                ...(tagsPorTipo.acao_caritativa || []),
            ], "Todos os tipos de ações caritativas");
            sincronizarFiltroTag();
            sincronizarFiltrosEventoPeriodo();
        } catch (err) {
            console.error("Erro ao carregar filtros:", err);
        }
    }



    function atualizarCampoDataFiltro() {
        const dataEl = document.getElementById('filtro-data');
        const dataWrapEl = document.getElementById('filtro-data-wrap');
        if (!dataEl || !dataWrapEl) return;

        const valorFiltroCombinado = String(filtroEventoPeriodoEl?.value || '|');
        const habilitado = valorFiltroCombinado.startsWith('data|');
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

    filtroTagMissaEl?.addEventListener("change", () => {
        if (filtroTagMissaEl.value && filtroTagAcaoEl) filtroTagAcaoEl.value = "";
        sincronizarFiltroTag();
    });

    filtroTagAcaoEl?.addEventListener("change", () => {
        if (filtroTagAcaoEl.value && filtroTagMissaEl) filtroTagMissaEl.value = "";
        sincronizarFiltroTag();
    });

    const filtroTipoComunidadeEl = document.getElementById('filtro-tipo-comunidade');
    filtroTipoComunidadeEl?.addEventListener('change', () => {
        if (!filtroTipoComunidadeEl.value) return;
        limparFiltrosExceto({ manterTipoComunidade: true });
        if (!isMobile()) carregarComunidades();
    });

    filtrosForm?.addEventListener("change", () => {
        state.quickFilterAtivo = "";
        quickFilterBtns.forEach((btn) => btn.classList.remove("is-active"));
        sincronizarFiltrosEventoPeriodo();
        sincronizarFiltroTag();
        atualizarCampoDataFiltro();
        if (!isMobile()) carregarComunidades();
    });

    aplicarBtn?.addEventListener("click", () => {
        carregarComunidades();
    });

    limparBtn?.addEventListener("click", () => {
        limparFiltrosExceto();
        carregarComunidades();
    });


    quickFilterBtns.forEach((btn) => {
        btn.addEventListener('click', () => {
            aplicarFiltroRapido(btn.dataset.quickFilter || '');
            const filtrosPanel = containerEl.querySelector('[data-panel="filtros"]');
            if (filtrosPanel) setPanelOpen(filtrosPanel, false);
            ocultarFiltrosFixos();
        });
    });

    toggleFiltrosBtn?.addEventListener('click', () => {
        revelarFiltrosFixos();
        const filtrosPanel = containerEl.querySelector('[data-panel="filtros"]');
        if (!filtrosPanel) return;
        const aberto = filtrosPanel.classList.contains('is-open');
        setPanelOpen(filtrosPanel, !aberto);
    });

    viewModeBtns.forEach((btn) => {
        btn.addEventListener('click', () => {
            state.viewMode = btn.dataset.viewMode === 'lista' ? 'lista' : 'mapa';
            atualizarModoVisualizacao();
        });
    });

    window.addEventListener("resize", () => {
        map.invalidateSize();
    });

    setupAccordionPanels();
    atualizarModoVisualizacao();

    await carregarFiltros();
    atualizarCampoDataFiltro();
    await requestUserLocationIfNeeded();
    await carregarComunidades();

});
