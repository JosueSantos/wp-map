let eventos = [];
let contatos = [];
let mapaCadastro;
let marcadorCadastro;
let modoEdicao = false;
let comunidadeEditandoId = null;
let eventosRemovidos = [];
let mapaDefinirCoordenadas = null;
let tagsEventoCache = [];
const FREQUENCIA_MISSA_DOMINICAL = 'missa_dominical';

document.addEventListener('DOMContentLoaded', async function () {
    mapaConfigurarBloqueioDeNaoLogado();
    mapaExibirSaudacaoUsuario();

    mapaCarregarTiposComunidade();
    mapaIniciarSeletorDeCoordenadas();
    mapaIniciarEtapasDoFormulario();
    mapaIniciarValidadorImagem();
    mapaInitAtividadesUI();

    await mapaPreencherFormularioEdicao();
});


function mapaObterParametroUrl(nome) {
    return new URLSearchParams(window.location.search).get(nome);
}

function mapaConfigurarBloqueioDeNaoLogado() {
    const modal = document.getElementById('mapa-auth-modal');
    const loginLink = document.getElementById('mapa-login-link');
    const registerLink = document.getElementById('mapa-register-link');

    if (loginLink && MAPA_API?.login_url) loginLink.href = MAPA_API.login_url;
    if (registerLink && MAPA_API?.register_url) registerLink.href = MAPA_API.register_url;

    if (!modal || MAPA_API?.is_logged_in) return;

    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function mapaExibirSaudacaoUsuario() {
    if (!MAPA_API?.is_logged_in) return;

    const greeting = document.getElementById('mapa-user-greeting');
    if (!greeting) return;

    const nome = (MAPA_API.current_user_name || '').trim() || 'usuário';
    greeting.textContent = `Olá ${nome}`;
    greeting.classList.remove('hidden');
}

async function mapaPreencherFormularioEdicao() {
    const editarId = parseInt(mapaObterParametroUrl('editar_comunidade'), 10);
    if (!Number.isInteger(editarId) || editarId <= 0) return;

    modoEdicao = true;
    comunidadeEditandoId = editarId;
    mapaDefinirEstadoCarregamentoEdicao(true);

    mapaMostrarFeedback('Carregando dados do local para edição...', 'info');

    try {
        const response = await fetch(`/wp-json/mapa/v1/comunidade/${editarId}`, {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                'X-WP-Nonce': MAPA_API.nonce
            }
        });

        const dados = await response.json();

        if (!response.ok) {
            throw new Error(dados?.message || 'Não foi possível carregar o local para edição.');
        }

        mapaAplicarDadosDaComunidade(dados);
        mapaMostrarFeedback('Você está editando este local. Ajuste os campos e salve.', 'info');

        const titulo = document.querySelector('#secao-etapa-1 h3');
        if (titulo) titulo.textContent = '1. Dados principais (edição)';

        const heading = document.querySelector('h2');
        if (heading) heading.textContent = 'Editar Local';
    } catch (error) {
        mapaMostrarFeedback(error.message || 'Falha ao carregar dados de edição.', 'erro');
    } finally {
        mapaDefinirEstadoCarregamentoEdicao(false);
    }
}

function mapaDefinirEstadoCarregamentoEdicao(ativo) {
    const overlay = document.getElementById('mapa-loading-edicao');
    const conteudo = document.getElementById('mapa-form-content');

    if (overlay) {
        overlay.classList.toggle('hidden', !ativo);
    }

    if (conteudo) {
        conteudo.classList.toggle('pointer-events-none', !!ativo);
        conteudo.classList.toggle('opacity-60', !!ativo);
        conteudo.setAttribute('aria-busy', ativo ? 'true' : 'false');
    }
}

function mapaAplicarDadosDaComunidade(dados) {
    document.getElementById('nome').value = dados.nome || '';
    document.getElementById('endereco').value = dados.endereco || '';
    document.getElementById('latitude').value = dados.latitude || '';
    document.getElementById('longitude').value = dados.longitude || '';

    if (Number.isFinite(parseFloat(dados.latitude)) && Number.isFinite(parseFloat(dados.longitude)) && typeof mapaDefinirCoordenadas === 'function') {
        mapaDefinirCoordenadas(parseFloat(dados.latitude), parseFloat(dados.longitude), false);
    }

    const selectTipo = document.getElementById('tipo');
    if (selectTipo && dados.tipo_id) {
        const setTipo = () => {
            selectTipo.value = String(dados.tipo_id);
            selectTipo.dispatchEvent(new Event('change'));
        };

        if (selectTipo.options.length <= 1) {
            setTimeout(setTipo, 300);
        } else {
            setTipo();
        }
    }

    if (dados.parent_paroquia_id) {
        document.getElementById('parent_paroquia').value = String(dados.parent_paroquia_id);
        document.getElementById('busca-paroquia').value = dados.parent_paroquia_nome || '';
    }

    const contatosContainer = document.getElementById('contatos-container');
    contatosContainer.innerHTML = '';
    (dados.contatos || []).forEach((contato) => {
        mapaAdicionarContato(contato.tipo || '', contato.valor || '', false);
    });

    wizardState.atividades = [];
    (dados.eventos || []).forEach((evento) => {
        mapaAdicionarEvento(evento, false);
    });
    renderAtividades();

    mapaAtualizarPreviewImagemExistente(dados.imagem_url || '');
}

async function mapaCarregarTiposComunidade() {

    const select = document.getElementById('tipo');

    try {

        const response = await fetch('/wp-json/wp/v2/tipo_comunidade?per_page=100');
        const termos = await response.json();

        const prioridadeTipo = {
            capela: 0,
            igreja_matriz: 1,
            paroquia: 2,
        };

        const termosOrdenados = [...termos].sort((a, b) => {
            const prioridadeA = prioridadeTipo[a.slug] ?? 99;
            const prioridadeB = prioridadeTipo[b.slug] ?? 99;

            if (prioridadeA !== prioridadeB) {
                return prioridadeA - prioridadeB;
            }

            return String(a.name || '').localeCompare(String(b.name || ''), 'pt-BR', { sensitivity: 'base' });
        });

        select.innerHTML = '<option value="">Selecione</option>';

        termosOrdenados.forEach(termo => {

            const option = document.createElement('option');
            option.value = termo.id; // IMPORTANTE: usar ID
            option.textContent = termo.name;

            select.appendChild(option);

        });

    } catch (error) {

        select.innerHTML = '<option value="">Erro ao carregar</option>';
        console.error('Erro ao carregar tipo_comunidade:', error);

    }
}

document.getElementById('tipo').addEventListener('change', function () {

    const textoSelecionado = this.options[this.selectedIndex].text.toLowerCase();

    const campo = document.getElementById('campo-paroquia');

    if (textoSelecionado.includes('capela')) {
        campo.classList.remove('hidden');
    } else {
        campo.classList.add('hidden');
        document.getElementById('parent_paroquia').value = '';
    }

});

document.getElementById('busca-paroquia').addEventListener('input', async function () {

    const termo = this.value;
    const resultadoBox = document.getElementById('resultado-paroquias');

    if (termo.length < 2) {
        resultadoBox.innerHTML = '';
        resultadoBox.classList.add('hidden');
        document.getElementById('parent_paroquia').value = '';
        return;
    }

    const response = await fetch(
        `/wp-json/mapa/v1/paroquias?search=${termo}&per_page=20`
    );

    const comunidades = await response.json();

    resultadoBox.innerHTML = '';
    resultadoBox.classList.remove('hidden');

    if (!comunidades.length) {
        const vazio = document.createElement('div');
        vazio.className = 'p-3 text-base text-gray-700';
        vazio.textContent = 'Nenhuma paróquia encontrada. Cadastre uma nova paróquia.';
        resultadoBox.appendChild(vazio);
        return;
    }

    comunidades.forEach(c => {

        const item = document.createElement('div');
        item.className = "p-2 hover:bg-gray-100 cursor-pointer";
        item.textContent = c.nome;

        item.onclick = () => {
            document.getElementById('busca-paroquia').value = c.nome;
            document.getElementById('parent_paroquia').value = c.id;
            resultadoBox.classList.add('hidden');
        };

        resultadoBox.appendChild(item);
    });

});

function mapaIniciarSeletorDeCoordenadas() {

    const mapaEl = document.getElementById('mapa-cadastro');

    if (!mapaEl || typeof L === 'undefined') return;

    const latInput = document.getElementById('latitude');
    const lngInput = document.getElementById('longitude');
    const enderecoInput = document.getElementById('endereco');
    const botaoBusca = document.getElementById('buscar-endereco-mapa');
    const botaoLocalizacaoAtual = document.getElementById('mapa-usar-localizacao-atual');
    const mensagemAjuste = document.getElementById('mapa-ajuste-msg');
    const mensagemErro = document.getElementById('mapa-endereco-erro');

    const latInicial = parseFloat(latInput.value);
    const lngInicial = parseFloat(lngInput.value);
    const centroInicial = Number.isFinite(latInicial) && Number.isFinite(lngInicial)
        ? [latInicial, lngInicial]
        : [-3.7319, -38.5267];

    mapaCadastro = L.map('mapa-cadastro').setView(centroInicial, 12);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(mapaCadastro);

    if (Number.isFinite(latInicial) && Number.isFinite(lngInicial)) {
        mapaAtualizarMarcadorCadastro(latInicial, lngInicial, true);
    }

    mapaCadastro.on('click', function (event) {
        mapaAtualizarMarcadorCadastro(event.latlng.lat, event.latlng.lng, true);
        mensagemErro.classList.add('hidden');
    });

    botaoBusca.addEventListener('click', function () {
        mapaBuscarEnderecoNoOpenStreetMap(enderecoInput.value, mensagemErro);
    });

    if (botaoLocalizacaoAtual) {
        botaoLocalizacaoAtual.addEventListener('click', function () {
            mapaUsarLocalizacaoAtual(mensagemErro);
        });
    }

    enderecoInput.addEventListener('keydown', function (event) {
        if (event.key === 'Enter') {
            event.preventDefault();
            mapaBuscarEnderecoNoOpenStreetMap(enderecoInput.value, mensagemErro);
        }
    });

    function mapaAtualizarMarcadorCadastro(lat, lng, mostrarMensagem = false) {

        latInput.value = Number(lat).toFixed(6);
        lngInput.value = Number(lng).toFixed(6);

        if (!marcadorCadastro) {
            marcadorCadastro = L.marker([lat, lng], { draggable: true }).addTo(mapaCadastro);
            marcadorCadastro.on('dragend', function (event) {
                const ponto = event.target.getLatLng();
                mapaAtualizarMarcadorCadastro(ponto.lat, ponto.lng, true);
            });
        } else {
            marcadorCadastro.setLatLng([lat, lng]);
        }

        mapaCadastro.setView([lat, lng], mapaCadastro.getZoom() < 15 ? 15 : mapaCadastro.getZoom());

        if (mostrarMensagem) {
            mensagemAjuste.classList.remove('hidden');
        }
    }

    mapaDefinirCoordenadas = mapaAtualizarMarcadorCadastro;

    async function mapaBuscarEnderecoNoOpenStreetMap(endereco, erroEl) {

        const enderecoBusca = endereco.trim();

        if (!enderecoBusca) {
            erroEl.textContent = 'Digite um endereço para buscar no mapa.';
            erroEl.classList.remove('hidden');
            return;
        }

        erroEl.classList.add('hidden');

        try {

            const response = await fetch(`https://nominatim.openstreetmap.org/search?format=json&limit=1&q=${encodeURIComponent(enderecoBusca)}`, {
                headers: {
                    'Accept': 'application/json'
                }
            });

            if (!response.ok) throw new Error('Falha ao consultar o endereço.');

            const resultados = await response.json();

            if (!resultados.length) {
                erroEl.textContent = 'Endereço não encontrado. Ajuste o texto ou marque no mapa manualmente.';
                erroEl.classList.remove('hidden');
                return;
            }

            const local = resultados[0];
            mapaAtualizarMarcadorCadastro(parseFloat(local.lat), parseFloat(local.lon), true);

        } catch (error) {
            erroEl.textContent = 'Não foi possível buscar o endereço agora. Tente novamente ou marque no mapa.';
            erroEl.classList.remove('hidden');
        }
    }

    function mapaUsarLocalizacaoAtual(erroEl) {
        if (!navigator.geolocation) {
            erroEl.textContent = 'Seu navegador não suporta geolocalização. Marque o ponto manualmente no mapa.';
            erroEl.classList.remove('hidden');
            return;
        }

        erroEl.classList.add('hidden');

        navigator.geolocation.getCurrentPosition(
            (posicao) => {
                const latitude = posicao?.coords?.latitude;
                const longitude = posicao?.coords?.longitude;

                if (!Number.isFinite(latitude) || !Number.isFinite(longitude)) {
                    erroEl.textContent = 'Não foi possível obter coordenadas válidas da sua localização atual.';
                    erroEl.classList.remove('hidden');
                    return;
                }

                mapaAtualizarMarcadorCadastro(latitude, longitude, true);
            },
            () => {
                erroEl.textContent = 'Permita o acesso à localização para usar sua posição atual.';
                erroEl.classList.remove('hidden');
            },
            {
                enableHighAccuracy: true,
                timeout: 10000,
                maximumAge: 0,
            }
        );
    }
}


function mapaIniciarEtapasDoFormulario() {

    const barra = document.getElementById('progresso-cadastro');
    const botoesEtapa = document.querySelectorAll('[data-step-nav]');
    const secoes = document.querySelectorAll('[data-step]');

    if (!barra || !botoesEtapa.length || !secoes.length) return;

    botoesEtapa.forEach(botao => {
        botao.addEventListener('click', function () {
            const step = parseInt(this.dataset.stepNav, 10);
            mapaAtualizarEtapaVisual(step);
            const secao = document.querySelector(`#secao-etapa-${step}`);
            if (secao) {
                secao.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    });

    const observador = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const step = parseInt(entry.target.dataset.step, 10);
                mapaAtualizarEtapaVisual(step);
            }
        });
    }, { threshold: 0.5 });

    secoes.forEach(secao => observador.observe(secao));

    function mapaAtualizarEtapaVisual(stepAtual) {
        const totalEtapas = botoesEtapa.length || 1;
        const etapaNormalizada = Math.max(1, Math.min(totalEtapas, stepAtual));
        const progresso = (etapaNormalizada / totalEtapas) * 100;
        barra.style.width = `${progresso}%`;

        botoesEtapa.forEach(botao => {
            const stepBtn = parseInt(botao.dataset.stepNav, 10);
            if (stepBtn <= stepAtual) {
                botao.className = 'step-nav w-full text-left px-3 py-2 rounded-lg border border-indigo-200 bg-indigo-50 text-indigo-700 font-medium';
            } else {
                botao.className = 'step-nav w-full text-left px-3 py-2 rounded-lg border border-gray-200 bg-white text-gray-500';
            }
        });
    }
}


const EVENTO_GRUPOS = {
    missa: {
        containerId: 'eventos-missas',
        label: 'Missa',
        slugsPreferidos: ['missa', 'missas']
    },
    confissao: {
        containerId: 'eventos-confissoes',
        label: 'Confissão',
        slugsPreferidos: ['confissao', 'confissões', 'confissaoes']
    },
    adoracao_santissimo: {
        containerId: 'eventos-adoracao-santissimo',
        label: 'Adoração ao Santíssimo',
        slugsPreferidos: ['adoracao-ao-santissimo', 'adoracao_santissimo', 'adoracao', 'santissimo']
    },
    acao_caritativa: {
        containerId: 'eventos-acao-caritativa',
        label: 'Ação caritativa',
        slugsPreferidos: ['acao-caritativa', 'acao_caritativa', 'caridade', 'acao-social']
    }
};

let tiposEventoCache = [];
let tipoEventoPorGrupo = {};

function mapaNormalizarTexto(texto = '') {
    return String(texto)
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .toLowerCase()
        .trim();
}

function mapaResolverGrupoPorTipoEvento(tipoEventoId) {
    const id = parseInt(tipoEventoId, 10);
    if (!Number.isInteger(id) || id <= 0) return '';

    const tipo = tiposEventoCache.find((item) => parseInt(item.id, 10) === id);
    if (!tipo) return '';

    const slug = mapaNormalizarTexto(tipo.slug || '');
    const nome = mapaNormalizarTexto(tipo.name || '');

    if (slug.includes('missa') || nome.includes('missa')) return 'missa';
    if (slug.includes('conf') || nome.includes('conf')) return 'confissao';
    if (slug.includes('ador') || slug.includes('sant') || nome.includes('adora') || nome.includes('sant')) return 'adoracao_santissimo';
    if (slug.includes('carit') || slug.includes('social') || nome.includes('carit') || nome.includes('social')) return 'acao_caritativa';

    return '';
}

async function mapaCarregarTiposEvento(select, grupo = '') {
    if (!Array.isArray(tiposEventoCache) || !tiposEventoCache.length) {
        const response = await fetch('/wp-json/wp/v2/tipo_evento?per_page=100');
        tiposEventoCache = await response.json();
    }

    if (!Object.keys(tipoEventoPorGrupo).length) {
        Object.entries(EVENTO_GRUPOS).forEach(([chave, config]) => {
            const encontrado = tiposEventoCache.find((termo) => {
                const slug = mapaNormalizarTexto(termo.slug || '');
                return config.slugsPreferidos.some((s) => slug === mapaNormalizarTexto(s));
            });

            if (encontrado) {
                tipoEventoPorGrupo[chave] = parseInt(encontrado.id, 10);
            }
        });
    }

    if (grupo && Number.isInteger(tipoEventoPorGrupo[grupo])) {
        const tipo = tiposEventoCache.find((termo) => parseInt(termo.id, 10) === tipoEventoPorGrupo[grupo]);
        select.innerHTML = '';

        if (tipo) {
            const option = document.createElement('option');
            option.value = tipo.id;
            option.textContent = tipo.name;
            select.appendChild(option);
            select.value = String(tipo.id);
        }

        select.disabled = true;
        return;
    }

    select.disabled = false;
    select.innerHTML = '<option value="">Selecione</option>';

    tiposEventoCache.forEach((termo) => {
        const option = document.createElement('option');
        option.value = termo.id;
        option.textContent = termo.name;
        select.appendChild(option);
    });
}

async function mapaCarregarTagsEvento(select) {
    if (!Array.isArray(tagsEventoCache) || !tagsEventoCache.length) {
        const response = await fetch('/wp-json/wp/v2/tags_evento?per_page=100&_fields=id,name,meta');
        tagsEventoCache = await response.json();
    }

    const tipoEventoId = parseInt(select.dataset.tipoEventoId || '', 10);
    const selecionadas = Array.from(select.selectedOptions).map((option) => parseInt(option.value, 10));

    select.innerHTML = '';

    tagsEventoCache.forEach((termo) => {
        const exclusivos = Array.isArray(termo?.meta?.exclusive_tipo_evento_ids)
            ? termo.meta.exclusive_tipo_evento_ids.map((id) => parseInt(id, 10)).filter(Number.isInteger)
            : [];

        const semExclusividade = exclusivos.length === 0;
        const permitidoPeloTipo = Number.isInteger(tipoEventoId) && tipoEventoId > 0 && exclusivos.includes(tipoEventoId);

        if (!semExclusividade && !permitidoPeloTipo) {
            return;
        }

        const option = document.createElement('option');
        option.value = termo.id;
        option.textContent = termo.name;
        option.selected = selecionadas.includes(parseInt(termo.id, 10));

        select.appendChild(option);
    });
}

function mapaGerarDescricaoFrequencia(frequencia, dias = [], diaMes = '', numeroSemana = '', mes = '') {
    const diaMap = ['Domingo', 'Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado'];
    const mesMap = {
        1: 'Janeiro', 2: 'Fevereiro', 3: 'Março', 4: 'Abril', 5: 'Maio', 6: 'Junho',
        7: 'Julho', 8: 'Agosto', 9: 'Setembro', 10: 'Outubro', 11: 'Novembro', 12: 'Dezembro'
    };

    if (frequencia === FREQUENCIA_MISSA_DOMINICAL) return 'Missa Dominical';
    if (frequencia === 'mensal') return diaMes ? `Mensal • dia ${diaMes}` : 'Mensal';
    if (frequencia === 'numero_semana') {
        const diaNumeroSemana = Array.isArray(dias) && dias.length ? diaMap[parseInt(dias[0], 10)] : '';
        return numeroSemana && diaNumeroSemana ? `${numeroSemana}ª semana • ${diaNumeroSemana}` : 'Por número da semana';
    }
    if (frequencia === 'anual') {
        const nomeMes = mesMap[parseInt(mes, 10)] || '';
        return (diaMes && nomeMes) ? `Anual • ${diaMes} de ${nomeMes}` : 'Anual';
    }

    if (Array.isArray(dias) && dias.length) {
        const nomes = dias
            .map((dia) => diaMap[parseInt(dia, 10)])
            .filter(Boolean);

        if (nomes.length) {
            return `Semanal • ${nomes.join(', ')}`;
        }
    }

    return 'Semanal';
}

const DIAS_SEMANA_LABEL = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];
const STORAGE_ATIVIDADES_KEY = 'mapa_form_atividades_v2';
const wizardState = {
    atividades: [],
    currentStep: 1,
    editingIndex: null,
    selectedFrequencyIndex: null,
    modalOpen: false,
    draft: null
};

function mapaAtividadesPadrao(grupo = 'missa') {
    return {
        titulo: '',
        descricao: '',
        observacao: '',
        grupo,
        tipo_evento: tipoEventoPorGrupo[grupo] || null,
        tags_evento: [],
        frequencias: []
    };
}

function mapaFrequenciaPadrao() {
    return {
        id: null,
        frequencia: 'semanal',
        dias: [],
        dia_mes: '',
        numero_semana: '',
        mes: '',
        horarios: []
    };
}

function mapaInitAtividadesUI() {
    const root = document.getElementById('atividades-root');
    if (!root) return;

    document.getElementById('atividade-add-btn')?.addEventListener('click', () => mapaAbrirWizardAtividade());
    document.getElementById('wizard-close-btn')?.addEventListener('click', mapaFecharWizardAtividade);
    document.getElementById('wizard-voltar-btn')?.addEventListener('click', () => mapaTrocarStepWizard(-1));
    document.getElementById('wizard-avancar-btn')?.addEventListener('click', () => mapaTrocarStepWizard(1));
    document.getElementById('wizard-salvar-btn')?.addEventListener('click', mapaSalvarWizardAtividade);

    document.addEventListener('keydown', (event) => {
        if (!wizardState.modalOpen) return;
        if (event.key === 'Escape') {
            if (document.activeElement?.classList?.contains('inline-editor')) {
                return;
            }
            mapaFecharWizardAtividade();
        }
    });

    mapaPrepararTipoEventoPorGrupo();
    mapaRestaurarAtividadesLocalStorage();
    renderAtividades();
}

async function mapaPrepararTipoEventoPorGrupo() {
    if (Object.keys(tipoEventoPorGrupo).length) return;

    try {
        await mapaCarregarTiposEvento(document.createElement('select'));
    } catch (error) {
        console.warn('Não foi possível carregar tipo_evento para o wizard de atividades.', error);
    }
}

function mapaRestaurarAtividadesLocalStorage() {
    try {
        const bruto = localStorage.getItem(STORAGE_ATIVIDADES_KEY);
        if (!bruto) return;
        const atividades = JSON.parse(bruto);
        if (!Array.isArray(atividades)) return;
        wizardState.atividades = atividades;
    } catch (error) {
        console.warn('Não foi possível restaurar atividades do cache local.', error);
    }
}

function mapaPersistirAtividadesLocalStorage() {
    try {
        localStorage.setItem(STORAGE_ATIVIDADES_KEY, JSON.stringify(wizardState.atividades));
    } catch (error) {
        console.warn('Não foi possível persistir atividades no cache local.', error);
    }
}

function mapaAbrirWizardAtividade(indice = null) {
    const modal = document.getElementById('atividade-wizard-modal');
    if (!modal) return;

    wizardState.modalOpen = true;
    wizardState.currentStep = 1;
    wizardState.editingIndex = Number.isInteger(indice) ? indice : null;
    wizardState.selectedFrequencyIndex = 0;

    const atividadeBase = Number.isInteger(indice)
        ? JSON.parse(JSON.stringify(wizardState.atividades[indice]))
        : mapaAtividadesPadrao('missa');

    if (!Array.isArray(atividadeBase.frequencias)) atividadeBase.frequencias = [];
    wizardState.draft = atividadeBase;

    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    mapaRenderWizard();
}

function mapaFecharWizardAtividade() {
    const modal = document.getElementById('atividade-wizard-modal');
    if (!modal) return;

    wizardState.modalOpen = false;
    wizardState.draft = null;
    modal.classList.add('hidden');
    document.body.style.overflow = '';
}

function mapaTrocarStepWizard(delta) {
    const proximo = wizardState.currentStep + delta;
    if (proximo < 1 || proximo > 3) return;

    if (delta > 0 && !mapaValidarStepAtual()) return;

    wizardState.currentStep = proximo;
    mapaRenderWizard();
}

function mapaValidarStepAtual() {
    if (!wizardState.draft) return false;

    const feedback = document.getElementById('wizard-feedback');
    const erro = (msg) => {
        if (!feedback) return;
        feedback.className = 'rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700';
        feedback.textContent = msg;
    };

    if (wizardState.currentStep === 1) {
        if (!String(wizardState.draft.titulo || '').trim()) {
            erro('Informe o nome da atividade para continuar.');
            return false;
        }
    }

    if (wizardState.currentStep === 2 && !wizardState.draft.frequencias.length) {
        erro('Adicione ao menos uma frequência para continuar.');
        return false;
    }

    if (wizardState.currentStep === 3) {
        const freq = wizardState.draft.frequencias[wizardState.selectedFrequencyIndex ?? 0];
        if (!freq || !Array.isArray(freq.horarios) || !freq.horarios.length) {
            erro('Adicione ao menos um horário para salvar a atividade.');
            return false;
        }
    }

    if (feedback) feedback.className = 'hidden';
    return true;
}

function mapaSalvarWizardAtividade() {
    if (!mapaValidarStepAtual()) return;

    const btnSalvar = document.getElementById('wizard-salvar-btn');
    if (btnSalvar) {
        btnSalvar.disabled = true;
        btnSalvar.textContent = 'Salvando...';
    }

    const draft = JSON.parse(JSON.stringify(wizardState.draft));

    if (Number.isInteger(wizardState.editingIndex)) {
        wizardState.atividades[wizardState.editingIndex] = draft;
    } else {
        wizardState.atividades.push(draft);
    }

    mapaPersistirAtividadesLocalStorage();
    renderAtividades();
    mapaMostrarFeedback('Atividade salva com sucesso.', 'sucesso');
    mapaFecharWizardAtividade();

    if (btnSalvar) {
        btnSalvar.disabled = false;
        btnSalvar.textContent = 'Salvar atividade';
    }
}

function mapaResumoAtividade(atividade) {
    return (atividade.frequencias || []).map((freq) => {
        const descricao = mapaGerarDescricaoFrequencia(freq.frequencia, freq.dias, freq.dia_mes, freq.numero_semana, freq.mes);
        return `${descricao} (${(freq.horarios || []).length} horário(s))`;
    });
}

function renderAtividades() {
    const lista = document.getElementById('atividades-lista');
    const vazio = document.getElementById('atividades-vazio');
    if (!lista || !vazio) return;

    lista.innerHTML = '';

    wizardState.atividades.forEach((atividade, idx) => {
        const card = document.createElement('article');
        card.className = 'rounded-xl border border-gray-200 bg-white p-4';

        const resumo = mapaResumoAtividade(atividade).map((item) => `<li class="text-sm text-gray-600">↳ ${item}</li>`).join('');

        card.innerHTML = `
            <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-2">
                <div>
                    <p class="font-semibold text-gray-800">${atividade.titulo || 'Atividade sem nome'}</p>
                    ${atividade.descricao ? `<p class="text-sm text-gray-600 mt-1">${atividade.descricao}</p>` : ''}
                </div>
                <div class="flex gap-2">
                    <button type="button" class="atividade-editar px-3 py-1.5 rounded-lg border border-indigo-200 bg-indigo-50 text-indigo-700 text-sm font-medium" aria-label="Editar atividade">✏️ Editar</button>
                    <button type="button" class="atividade-remover px-3 py-1.5 rounded-lg border border-red-200 bg-red-50 text-red-700 text-sm font-medium" aria-label="Remover atividade">🗑️ Remover</button>
                </div>
            </div>
            <ul class="mt-3 space-y-1">${resumo || '<li class="text-sm text-gray-500">Sem frequências cadastradas.</li>'}</ul>
            <pre class="mt-3 rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-700 whitespace-pre-wrap">${mapaResumoAutomaticoTexto(atividade)}</pre>
        `;

        card.querySelector('.atividade-editar')?.addEventListener('click', () => mapaAbrirWizardAtividade(idx));
        card.querySelector('.atividade-remover')?.addEventListener('click', () => {
            if (!window.confirm('Deseja remover esta atividade?')) return;
            const idsRemovidos = (atividade.frequencias || []).map((f) => f.id).filter((id) => Number.isInteger(id) && id > 0);
            eventosRemovidos.push(...idsRemovidos);
            wizardState.atividades.splice(idx, 1);
            mapaPersistirAtividadesLocalStorage();
            renderAtividades();
        });

        lista.appendChild(card);
    });

    vazio.classList.toggle('hidden', wizardState.atividades.length > 0);
}

function mapaResumoAutomaticoTexto(atividade) {
    const linhas = [`${atividade.titulo || 'Atividade'}:`];
    (atividade.frequencias || []).forEach((freq) => {
        const desc = mapaGerarDescricaoFrequencia(freq.frequencia, freq.dias, freq.dia_mes, freq.numero_semana, freq.mes);
        const horarios = (freq.horarios || []).join(', ') || 'sem horário';
        linhas.push(`${desc} → ${horarios}`);
    });
    return linhas.join('\n');
}

function mapaRenderWizard() {
    if (!wizardState.modalOpen || !wizardState.draft) return;

    const titulo = document.getElementById('wizard-titulo');
    if (titulo) titulo.textContent = Number.isInteger(wizardState.editingIndex) ? 'Editar atividade' : 'Nova atividade';

    const stepper = document.getElementById('wizard-stepper');
    if (stepper) {
        const labels = ['Dados da atividade', 'Frequências', 'Horários'];
        stepper.innerHTML = labels.map((label, idx) => {
            const step = idx + 1;
            const ativo = step === wizardState.currentStep;
            const concluido = step < wizardState.currentStep;
            const cls = ativo ? 'border-indigo-300 bg-indigo-50 text-indigo-700' : (concluido ? 'border-emerald-300 bg-emerald-50 text-emerald-700' : 'border-gray-200 bg-white text-gray-500');
            return `<li class="rounded-lg border px-2 py-2 font-medium ${cls}">${step}. ${label}</li>`;
        }).join('');
    }

    document.querySelectorAll('.wizard-step').forEach((section) => {
        const step = parseInt(section.dataset.wizardStep, 10);
        section.classList.toggle('hidden', step !== wizardState.currentStep);
    });

    renderStepDadosAtividade();
    renderFrequencias();
    renderHorarios();

    document.getElementById('wizard-voltar-btn')?.classList.toggle('invisible', wizardState.currentStep === 1);
    document.getElementById('wizard-avancar-btn')?.classList.toggle('hidden', wizardState.currentStep === 3);
    document.getElementById('wizard-salvar-btn')?.classList.toggle('hidden', wizardState.currentStep !== 3);
}

function renderStepDadosAtividade() {
    const step = document.querySelector('[data-wizard-step="1"]');
    if (!step || !wizardState.draft) return;

    step.innerHTML = `
        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">Nome *</label>
            <input type="text" id="wizard-atividade-nome" class="w-full rounded-xl border-2 border-gray-200 bg-white px-3 py-2" value="${wizardState.draft.titulo || ''}">
        </div>
        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">Descrição</label>
            <textarea id="wizard-atividade-descricao" class="w-full rounded-xl border-2 border-gray-200 bg-white px-3 py-2 min-h-[90px]">${wizardState.draft.descricao || ''}</textarea>
        </div>
        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">Grupo</label>
            <select id="wizard-atividade-grupo" class="w-full rounded-xl border-2 border-gray-200 bg-white px-3 py-2">
                ${Object.entries(EVENTO_GRUPOS).map(([k, v]) => `<option value="${k}" ${wizardState.draft.grupo === k ? 'selected' : ''}>${v.label}</option>`).join('')}
            </select>
        </div>
        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">Características</label>
            <select id="wizard-atividade-tags" class="w-full rounded-xl border-2 border-gray-200 bg-white px-3 py-2" multiple></select>
        </div>
        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">Observação</label>
            <textarea id="wizard-atividade-observacao" class="w-full rounded-xl border-2 border-gray-200 bg-white px-3 py-2 min-h-[80px]">${wizardState.draft.observacao || ''}</textarea>
        </div>
    `;

    const nome = step.querySelector('#wizard-atividade-nome');
    const descricao = step.querySelector('#wizard-atividade-descricao');
    const grupo = step.querySelector('#wizard-atividade-grupo');
    const observacao = step.querySelector('#wizard-atividade-observacao');
    const tags = step.querySelector('#wizard-atividade-tags');

    nome?.addEventListener('input', (e) => { wizardState.draft.titulo = e.target.value; });
    descricao?.addEventListener('input', (e) => { wizardState.draft.descricao = e.target.value; });
    observacao?.addEventListener('input', (e) => { wizardState.draft.observacao = e.target.value; });
    grupo?.addEventListener('change', (e) => {
        wizardState.draft.grupo = e.target.value;
        wizardState.draft.tipo_evento = tipoEventoPorGrupo[e.target.value] || wizardState.draft.tipo_evento;
        mapaRenderWizard();
    });

    if (tags) {
        tags.dataset.tipoEventoId = String(wizardState.draft.tipo_evento || '');
        mapaCarregarTagsEvento(tags).then(() => {
            Array.from(tags.options).forEach((option) => {
                option.selected = (wizardState.draft.tags_evento || []).includes(parseInt(option.value, 10));
            });
        });
        tags.addEventListener('change', () => {
            wizardState.draft.tags_evento = Array.from(tags.selectedOptions).map((opt) => parseInt(opt.value, 10)).filter(Number.isInteger);
        });
        tags.addEventListener('mousedown', (event) => {
            const option = event.target;
            if (!option || option.tagName !== 'OPTION') return;
            event.preventDefault();
            option.selected = !option.selected;
        });
    }
}

function renderFrequencias() {
    const step = document.querySelector('[data-wizard-step="2"]');
    if (!step || !wizardState.draft) return;

    step.innerHTML = `
        <div class="flex items-center justify-between gap-2">
            <p class="text-sm text-gray-600">Edite frequência inline usando Enter (salvar) ou ESC (cancelar).</p>
            <button type="button" id="wizard-add-frequencia" class="px-3 py-2 rounded-lg bg-indigo-50 border border-indigo-200 text-indigo-700 font-medium">+ Adicionar frequência</button>
        </div>
        <div id="wizard-frequencias-lista" class="space-y-2"></div>
    `;

    step.querySelector('#wizard-add-frequencia')?.addEventListener('click', () => {
        wizardState.draft.frequencias.push(mapaFrequenciaPadrao());
        wizardState.selectedFrequencyIndex = wizardState.draft.frequencias.length - 1;
        mapaRenderWizard();
    });

    const lista = step.querySelector('#wizard-frequencias-lista');
    wizardState.draft.frequencias.forEach((freq, idx) => {
        const row = document.createElement('div');
        row.className = 'rounded-lg border border-gray-200 bg-white px-3 py-2';
        row.innerHTML = `
            <div class="flex items-center justify-between gap-2">
                <button type="button" class="freq-select text-left flex-1">
                    <span class="font-medium text-gray-800">${mapaGerarDescricaoFrequencia(freq.frequencia, freq.dias, freq.dia_mes, freq.numero_semana, freq.mes)}</span>
                </button>
                <div class="flex gap-1">
                    <button type="button" class="freq-inline-editar px-2 py-1 text-sm rounded border border-gray-200" aria-label="Editar frequência">✏️</button>
                    <button type="button" class="freq-remover px-2 py-1 text-sm rounded border border-red-200 text-red-700" aria-label="Remover frequência">🗑️</button>
                </div>
            </div>
            <div class="freq-inline-wrap hidden mt-2 grid grid-cols-1 sm:grid-cols-2 gap-2">
                <select class="inline-editor freq-frequencia rounded-lg border border-gray-300 px-2 py-1.5">
                    ${wizardState.draft.grupo === 'missa' ? `<option value="${FREQUENCIA_MISSA_DOMINICAL}" ${freq.frequencia === FREQUENCIA_MISSA_DOMINICAL ? 'selected' : ''}>Missa Dominical</option>` : ''}
                    <option value="semanal" ${freq.frequencia === 'semanal' ? 'selected' : ''}>Semanal</option>
                    <option value="mensal" ${freq.frequencia === 'mensal' ? 'selected' : ''}>Mensal</option>
                    <option value="numero_semana" ${freq.frequencia === 'numero_semana' ? 'selected' : ''}>Por número da semana</option>
                    <option value="anual" ${freq.frequencia === 'anual' ? 'selected' : ''}>Anual</option>
                </select>
                <select class="inline-editor freq-dia rounded-lg border border-gray-300 px-2 py-1.5">
                    <option value="">Dia da semana</option>
                    ${DIAS_SEMANA_LABEL.map((d, dIdx) => `<option value="${dIdx}" ${freq.dias?.includes(String(dIdx)) || freq.dias?.includes(dIdx) ? 'selected' : ''}>${d}</option>`).join('')}
                </select>
            </div>
        `;

        row.querySelector('.freq-select')?.addEventListener('click', () => {
            wizardState.selectedFrequencyIndex = idx;
            mapaRenderWizard();
        });

        row.querySelector('.freq-remover')?.addEventListener('click', () => {
            if (!window.confirm('Remover esta frequência?')) return;
            if (Number.isInteger(freq.id) && freq.id > 0) eventosRemovidos.push(freq.id);
            wizardState.draft.frequencias.splice(idx, 1);
            wizardState.selectedFrequencyIndex = Math.max(0, Math.min(wizardState.selectedFrequencyIndex || 0, wizardState.draft.frequencias.length - 1));
            mapaRenderWizard();
        });

        const btnEditar = row.querySelector('.freq-inline-editar');
        const wrap = row.querySelector('.freq-inline-wrap');
        const freqInput = row.querySelector('.freq-frequencia');
        const diaInput = row.querySelector('.freq-dia');

        const salvarInline = () => {
            freq.frequencia = freqInput.value;
            freq.dias = diaInput.value === '' ? [] : [diaInput.value];
            wrap.classList.add('hidden');
            mapaRenderWizard();
        };

        const cancelarInline = () => {
            wrap.classList.add('hidden');
            mapaRenderWizard();
        };

        btnEditar?.addEventListener('click', () => {
            wrap.classList.remove('hidden');
            freqInput?.focus();
        });

        [freqInput, diaInput].forEach((inp) => inp?.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();
                salvarInline();
            }
            if (event.key === 'Escape') {
                event.preventDefault();
                cancelarInline();
            }
        }));

        [freqInput, diaInput].forEach((inp) => inp?.addEventListener('change', salvarInline));

        if (idx === wizardState.selectedFrequencyIndex) {
            row.classList.add('ring-2', 'ring-indigo-200');
        }

        lista.appendChild(row);
    });
}

function renderHorarios() {
    const step = document.querySelector('[data-wizard-step="3"]');
    if (!step || !wizardState.draft) return;

    const freq = wizardState.draft.frequencias[wizardState.selectedFrequencyIndex ?? 0];

    if (!freq) {
        step.innerHTML = '<p class="text-sm text-gray-600">Selecione uma frequência na etapa anterior.</p>';
        return;
    }

    step.innerHTML = `
        <div class="flex items-center justify-between gap-2">
            <p class="text-sm text-gray-600">Frequência ativa: <strong>${mapaGerarDescricaoFrequencia(freq.frequencia, freq.dias, freq.dia_mes, freq.numero_semana, freq.mes)}</strong></p>
            <button type="button" id="wizard-add-horario" class="px-3 py-2 rounded-lg bg-indigo-50 border border-indigo-200 text-indigo-700 font-medium">+ Adicionar horário</button>
        </div>
        <div id="wizard-horarios-lista" class="space-y-2"></div>
    `;

    step.querySelector('#wizard-add-horario')?.addEventListener('click', () => {
        freq.horarios = Array.isArray(freq.horarios) ? freq.horarios : [];
        freq.horarios.push('');
        mapaRenderWizard();
        const ultimo = step.querySelector('#wizard-horarios-lista input:last-of-type');
        ultimo?.focus();
    });

    const lista = step.querySelector('#wizard-horarios-lista');
    (freq.horarios || []).forEach((horario, idx) => {
        const row = document.createElement('div');
        row.className = 'rounded-lg border border-gray-200 bg-white px-3 py-2 flex items-center gap-2';
        row.innerHTML = `
            <input type="time" class="horario-input flex-1 rounded-lg border border-gray-300 px-2 py-1.5" value="${horario || ''}" aria-label="Horário">
            <button type="button" class="horario-save px-2 py-1 text-sm rounded border border-gray-300">Salvar</button>
            <button type="button" class="horario-remove px-2 py-1 text-sm rounded border border-red-200 text-red-700" aria-label="Remover horário">🗑️</button>
        `;

        const input = row.querySelector('.horario-input');
        const salvar = () => {
            const valor = input.value;
            if (!valor) {
                mapaMostrarFeedback('Informe um horário válido.', 'erro');
                return;
            }
            const duplicado = (freq.horarios || []).some((h, hIdx) => h === valor && hIdx !== idx);
            if (duplicado) {
                mapaMostrarFeedback('Não é permitido cadastrar horários duplicados.', 'erro');
                return;
            }
            freq.horarios[idx] = valor;
            mapaRenderWizard();
        };

        row.querySelector('.horario-save')?.addEventListener('click', salvar);
        row.querySelector('.horario-remove')?.addEventListener('click', () => {
            freq.horarios.splice(idx, 1);
            mapaRenderWizard();
        });
        input?.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();
                salvar();
            }
            if (event.key === 'Escape') {
                event.preventDefault();
                mapaRenderWizard();
            }
        });

        lista.appendChild(row);
    });
}

function mapaAdicionarEventoPorGrupo(grupo, evento = null) {
    if (!wizardState.atividades) wizardState.atividades = [];
    const base = mapaAtividadesPadrao(grupo || 'missa');

    if (evento) {
        const freq = {
            id: evento.id || null,
            frequencia: evento.frequencia || 'semanal',
            dias: Array.isArray(evento.dias) ? evento.dias : (evento.dia !== undefined && evento.dia !== null && evento.dia !== '' ? [String(evento.dia)] : []),
            dia_mes: evento.dia_mes || '',
            numero_semana: evento.numero_semana || '',
            mes: evento.mes || '',
            horarios: evento.horario ? [evento.horario] : []
        };

        let atividadeExistente = wizardState.atividades.find((a) => a.titulo === (evento.titulo_base || evento.titulo || '') && a.grupo === (grupo || 'missa'));
        if (!atividadeExistente) {
            atividadeExistente = {
                ...base,
                titulo: evento.titulo_base || evento.titulo || '',
                observacao: evento.observacao || '',
                tipo_evento: evento.tipo_evento_id || tipoEventoPorGrupo[grupo] || null,
                tags_evento: Array.isArray(evento.tags_evento_ids) ? evento.tags_evento_ids : [],
                frequencias: []
            };
            wizardState.atividades.push(atividadeExistente);
        }
        atividadeExistente.frequencias.push(freq);
        mapaPersistirAtividadesLocalStorage();
        renderAtividades();
        return;
    }

    mapaAbrirWizardAtividade(null);
}

function mapaAdicionarEvento(evento = null) {
    const grupo = evento ? mapaResolverGrupoPorTipoEvento(evento.tipo_evento_id) : 'missa';
    mapaAdicionarEventoPorGrupo(grupo || 'missa', evento);
}

function mapaMascaraTelefone(value) {
    const digits = String(value || '').replace(/\D+/g, '').slice(0, 13);
    if (!digits) return '';

    if (digits.length <= 2) return `(${digits}`;
    if (digits.length <= 6) return `(${digits.slice(0, 2)}) ${digits.slice(2)}`;
    if (digits.length <= 10) return `(${digits.slice(0, 2)}) ${digits.slice(2, 6)}-${digits.slice(6)}`;
    return `(${digits.slice(0, 2)}) ${digits.slice(2, 7)}-${digits.slice(7)}`;
}

function mapaValidarValorContato(tipo, valor) {
    const raw = String(valor || '').trim();
    if (!tipo || !raw) return false;

    if (tipo === 'email') {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(raw);
    }

    if (tipo === 'telefone' || tipo === 'whatsapp') {
        const digits = raw.replace(/\D+/g, '');
        return digits.length >= 10;
    }

    if (['instagram', 'facebook', 'youtube'].includes(tipo)) {
        return /^@[a-z0-9._]+$/i.test(raw) || /^https?:\/\//i.test(raw);
    }

    if (tipo === 'site') {
        return /^https?:\/\//i.test(raw) || /^[a-z0-9.-]+\.[a-z]{2,}(\/.*)?$/i.test(raw);
    }

    return true;
}

const TIPOS_CONTATO = [
  "telefone", "whatsapp", "instagram", "facebook", "youtube", "site", "email"
];

const LABELS_TIPOS_CONTATO = {
    telefone: "Telefone",
    whatsapp: "Whatsapp",
    instagram: "Instagram",
    facebook: "Facebook",
    youtube: "Youtube",
    site: "Site",
    email: "Email",
};

function mapaAdicionarContato(tipoInicial = '', valorInicial = '', adicionarNoTopo = false) {
    const container = document.getElementById('contatos-container');

    const div = document.createElement('div');
    div.className = "grid grid-cols-1 sm:grid-cols-[1fr_1fr_auto] gap-3 bg-gray-50 p-4 rounded-xl border border-gray-200 shadow-sm items-start";

    const options = TIPOS_CONTATO.map(tipo =>
        `<option value="${tipo}">${LABELS_TIPOS_CONTATO[tipo] || tipo}</option>`
    ).join('');

    div.innerHTML = `
        <select class="contato-tipo rounded-lg border-2 border-gray-200 bg-white px-3 py-2 focus:ring-2 focus:ring-indigo-500 text-base">
            <option value="">Selecione</option>
            ${options}
        </select>

        <input type="text" placeholder="Valor"
            class="contato-valor rounded-lg border-2 border-gray-200 bg-white px-3 py-2 focus:ring-2 focus:ring-indigo-500 text-base">

        <button type="button" class="contato-remover px-4 py-2 rounded-lg bg-red-50 text-red-700 border border-red-200 font-medium">Remover</button>
    `;

    if (adicionarNoTopo) {
        container.prepend(div);
    } else {
        container.appendChild(div);
    }

    const tipoEl = div.querySelector('.contato-tipo');
    const valorEl = div.querySelector('.contato-valor');

    const placeholders = {
        telefone: 'Formato: (DDD) 99999-9999 | mínimo 10 números',
        whatsapp: 'Formato: (DDD) 99999-9999 | mínimo 10 números',
        instagram: 'Use @usuario ou URL completa do perfil',
        facebook: 'Use @pagina ou URL completa da página',
        youtube: 'Use @canal ou URL completa do canal',
        site: 'Informe URL (https://...) ou domínio válido',
        email: 'Informe um e-mail válido (ex.: contato@dominio.com)',
    };

    const atualizarInputContato = () => {
        const tipo = tipoEl.value;
        valorEl.placeholder = placeholders[tipo] || 'Valor';
        if (tipo === 'telefone' || tipo === 'whatsapp') {
            valorEl.value = mapaMascaraTelefone(valorEl.value);
        }
    };

    tipoEl.addEventListener('change', atualizarInputContato);
    valorEl.addEventListener('input', () => {
        if (tipoEl.value === 'telefone' || tipoEl.value === 'whatsapp') {
            valorEl.value = mapaMascaraTelefone(valorEl.value);
        }
    });

    tipoEl.value = tipoInicial;
    valorEl.value = valorInicial;
    atualizarInputContato();

    div.querySelector('.contato-remover').addEventListener('click', () => div.remove());
}


function mapaAtualizarPreviewImagemExistente(url) {
    const previewWrap = document.getElementById('imagem-comunidade-preview-wrap');
    const preview = document.getElementById('imagem-comunidade-preview');

    if (!previewWrap || !preview) return;

    if (!url) {
        preview.src = '';
        previewWrap.classList.add('hidden');
        return;
    }

    preview.src = url;
    previewWrap.classList.remove('hidden');
}

function mapaIniciarValidadorImagem() {

    const inputImagem = document.getElementById('imagem-comunidade');
    const mensagem = document.getElementById('imagem-comunidade-msg');

    if (!inputImagem || !mensagem) return;

    const tiposAceitos = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    const tamanhoMaximo = 5 * 1024 * 1024;

    inputImagem.addEventListener('change', function () {
        mensagem.classList.add('hidden');
        mensagem.classList.remove('text-red-700', 'text-emerald-700', 'font-medium');

        if (!this.files || !this.files.length) return;

        const arquivo = this.files[0];

        if (!tiposAceitos.includes(arquivo.type)) {
            this.value = '';
            mensagem.textContent = 'Arquivo inválido. Envie uma imagem JPG, PNG, WEBP ou GIF.';
            mensagem.classList.remove('hidden');
            mensagem.classList.add('text-red-700', 'font-medium');
            return;
        }

        if (arquivo.size > tamanhoMaximo) {
            this.value = '';
            mensagem.textContent = 'Imagem muito grande. Envie um arquivo com até 5MB.';
            mensagem.classList.remove('hidden');
            mensagem.classList.add('text-red-700', 'font-medium');
            return;
        }

        mensagem.textContent = 'Imagem válida selecionada. Ela substituirá a imagem atual.';
        mensagem.classList.remove('hidden');
        mensagem.classList.add('text-emerald-700', 'font-medium');

        mapaAtualizarPreviewImagemExistente(URL.createObjectURL(arquivo));
    });
}

function mapaValidarRegraCapela() {

    const selectTipo = document.getElementById('tipo');
    const parentParoquia = document.getElementById('parent_paroquia').value;

    const textoSelecionado = selectTipo.options[selectTipo.selectedIndex]?.text?.toLowerCase() || '';

    if (textoSelecionado.includes('capela') && !parentParoquia) {
        document.getElementById('mapa-debug').innerText = 'Para cadastrar uma Capela, selecione uma Paróquia Responsável. Se não existir, cadastre primeiro a paróquia.';
        document.getElementById('campo-paroquia').classList.remove('hidden');
        document.getElementById('busca-paroquia').focus();
        return false;
    }

    return true;
}


function mapaMostrarFeedback(mensagem, tipo = 'info') {

    const debug = document.getElementById('mapa-debug');
    if (!debug) return;

    debug.className = 'text-base rounded-xl px-4 py-3';

    if (tipo === 'sucesso') {
        debug.classList.add('bg-emerald-50', 'text-emerald-800', 'border', 'border-emerald-200');
    } else if (tipo === 'erro') {
        debug.classList.add('bg-red-50', 'text-red-800', 'border', 'border-red-200', 'font-medium');
    } else {
        debug.classList.add('bg-gray-50', 'text-gray-700', 'border', 'border-gray-200');
    }

    debug.innerText = mensagem;
}


function mapaDefinirEstadoBotaoEnvio(emEnvio) {
    const botao = document.getElementById('mapa-submit-btn');
    if (!botao) return;

    if (!botao.dataset.labelOriginal) {
        botao.dataset.labelOriginal = botao.textContent.trim();
    }

    botao.disabled = !!emEnvio;
    botao.classList.toggle('opacity-70', !!emEnvio);
    botao.classList.toggle('cursor-not-allowed', !!emEnvio);
    botao.textContent = emEnvio ? 'Salvando...' : botao.dataset.labelOriginal;
}

function mapaExibirModalSucesso(resp) {
    const modal = document.getElementById('mapa-sucesso-modal');
    const texto = document.getElementById('mapa-sucesso-texto');
    const botaoNovo = document.getElementById('mapa-sucesso-novo');
    const botaoMapa = document.getElementById('mapa-sucesso-mapa');

    if (!modal || !botaoNovo || !botaoMapa) return;

    if (texto) {
        texto.textContent = modoEdicao
            ? 'O local foi atualizado com sucesso.'
            : `Local cadastrado com sucesso! Código: ${resp?.comunidade_id || '-'}.`;
    }

    botaoNovo.onclick = function () {
        const baseFormUrl = MAPA_API?.form_url || window.location.pathname;
        window.location.href = String(baseFormUrl).split('?')[0];
    };

    botaoMapa.onclick = function () {
        window.location.href = MAPA_API?.map_url || '/';
    };

    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function mapaEnviar() {

    if (!MAPA_API?.is_logged_in) {
        mapaMostrarFeedback('Faça login para enviar o formulário.', 'erro');
        return;
    }

    if (!mapaValidarRegraCapela()) return;

    const contatos = [];
    let contatoInvalido = null;

    document.querySelectorAll('#contatos-container > div').forEach(div => {
        const tipo = div.querySelector('.contato-tipo').value;
        const valor = div.querySelector('.contato-valor').value;

        if (!tipo && !String(valor || '').trim()) return;

        if (!mapaValidarValorContato(tipo, valor)) {
            contatoInvalido = tipo || 'contato';
            return;
        }

        if (tipo && valor) {
            contatos.push({ tipo, valor });
        }
    });

    if (contatoInvalido) {
        mapaMostrarFeedback(`Valor inválido para ${contatoInvalido}. Confira o formato informado.`, 'erro');
        return;
    }

    const eventos = [];
    (wizardState.atividades || []).forEach((atividade) => {
        const titulo = atividade.titulo || '';
        const observacao = atividade.observacao || '';
        const tipoEvento = parseInt(atividade.tipo_evento, 10);
        const tagsSelecionadas = Array.isArray(atividade.tags_evento) ? atividade.tags_evento : [];

        (atividade.frequencias || []).forEach((ocorrencia) => {
            const eventoId = parseInt(ocorrencia.id, 10);
            const horarios = Array.isArray(ocorrencia.horarios) ? ocorrencia.horarios.filter(Boolean) : [];

            horarios.forEach((horario) => {
                eventos.push({
                    id: Number.isInteger(eventoId) ? eventoId : null,
                    titulo,
                    titulo_base: titulo,
                    frequencia: ocorrencia.frequencia || 'semanal',
                    dias: Array.isArray(ocorrencia.dias) ? ocorrencia.dias : [],
                    dia_mes: ocorrencia.dia_mes || '',
                    numero_semana: ocorrencia.numero_semana || '',
                    mes: ocorrencia.mes || '',
                    horario: horario || '',
                    observacao,
                    tipo_evento: Number.isInteger(tipoEvento) ? tipoEvento : null,
                    tags_evento: tagsSelecionadas
                });
            });
        });
    });

    const formData = new FormData();

    formData.append('nome', document.getElementById('nome').value);
    formData.append('tipo', document.getElementById('tipo').value);
    formData.append('latitude', document.getElementById('latitude').value);
    formData.append('longitude', document.getElementById('longitude').value);
    formData.append('endereco', document.getElementById('endereco').value);
    formData.append('parent_paroquia', document.getElementById('parent_paroquia').value);
    formData.append('contatos', JSON.stringify(contatos));
    formData.append('eventos', JSON.stringify(eventos));
    formData.append('eventos_removidos', JSON.stringify(eventosRemovidos));

    if (modoEdicao && comunidadeEditandoId) {
        formData.append('comunidade_id', String(comunidadeEditandoId));
    }

    const imagemInput = document.getElementById('imagem-comunidade');
    if (imagemInput?.files?.length) {
        formData.append('imagem_comunidade', imagemInput.files[0]);
    }

    mapaMostrarFeedback('Enviando cadastro... aguarde.', 'info');
    mapaDefinirEstadoBotaoEnvio(true);

    fetch(MAPA_API.url, {
        method: 'POST',
        headers: {
            'X-WP-Nonce': MAPA_API.nonce
        },
        body: formData
    })
    .then(async (r) => {
        const resp = await r.json();
        if (!r.ok) {
            throw new Error(resp?.message || 'Não foi possível salvar o cadastro.');
        }
        return resp;
    })
    .then(resp => {
        mapaMostrarFeedback(modoEdicao ? 'Local atualizado com sucesso!' : `Cadastro realizado com sucesso! ID da comunidade: ${resp.comunidade_id}.`, 'sucesso');
        mapaExibirModalSucesso(resp);
    })
    .catch(error => {
        mapaMostrarFeedback(error.message || 'Erro ao enviar cadastro. Tente novamente.', 'erro');
    })
    .finally(() => {
        mapaDefinirEstadoBotaoEnvio(false);
    });
}
