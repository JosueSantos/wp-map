let eventos = [];
let contatos = [];
let mapaCadastro;
let marcadorCadastro;
let modoEdicao = false;
let comunidadeEditandoId = null;
let eventosRemovidos = [];
let mapaDefinirCoordenadas = null;
let tagsEventoCache = [];

document.addEventListener('DOMContentLoaded', async function () {
    mapaConfigurarBloqueioDeNaoLogado();
    mapaExibirSaudacaoUsuario();

    mapaCarregarTiposComunidade();
    mapaIniciarSeletorDeCoordenadas();
    mapaIniciarEtapasDoFormulario();
    mapaIniciarValidadorImagem();

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

    document.querySelectorAll('.eventos-lista').forEach((lista) => {
        lista.innerHTML = '';
    });
    (dados.eventos || []).forEach((evento) => {
        mapaAdicionarEvento(evento, false);
    });

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

function mapaCriarOcorrencia(ocorrencia = null) {
    const item = document.createElement('div');
    item.className = 'evento-ocorrencia rounded-xl border border-gray-200 bg-white p-4 space-y-3';
    if (ocorrencia?.id) {
        item.dataset.eventoId = String(ocorrencia.id);
    }

    item.innerHTML = `
        <div>
            <label class="block text-base font-semibold text-gray-700 mb-1">Frequência</label>
            <select class="evento-frequencia rounded-xl border-2 border-gray-200 bg-white px-3 py-2 focus:ring-2 focus:ring-indigo-500 w-full">
                <option value="semanal">Semanal</option>
                <option value="mensal">Mensal</option>
                <option value="numero_semana">Por número da semana</option>
                <option value="anual">Anual</option>
            </select>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="evento-campo-dia-semana">
                <label class="block text-base font-semibold text-gray-700 mb-2">Dia(s) da semana</label>
                <div class="evento-dias-semana grid grid-cols-2 sm:grid-cols-3 gap-2 rounded-xl border-2 border-gray-200 bg-white p-3">
                    <label class="inline-flex items-center gap-2 text-sm text-gray-700"><input type="checkbox" class="evento-dia-check" value="0"> Domingo</label>
                    <label class="inline-flex items-center gap-2 text-sm text-gray-700"><input type="checkbox" class="evento-dia-check" value="1"> Segunda-feira</label>
                    <label class="inline-flex items-center gap-2 text-sm text-gray-700"><input type="checkbox" class="evento-dia-check" value="2"> Terça-feira</label>
                    <label class="inline-flex items-center gap-2 text-sm text-gray-700"><input type="checkbox" class="evento-dia-check" value="3"> Quarta-feira</label>
                    <label class="inline-flex items-center gap-2 text-sm text-gray-700"><input type="checkbox" class="evento-dia-check" value="4"> Quinta-feira</label>
                    <label class="inline-flex items-center gap-2 text-sm text-gray-700"><input type="checkbox" class="evento-dia-check" value="5"> Sexta-feira</label>
                    <label class="inline-flex items-center gap-2 text-sm text-gray-700"><input type="checkbox" class="evento-dia-check" value="6"> Sábado</label>
                </div>
            </div>

            <div class="evento-campo-dia-mes hidden">
                <label class="block text-base font-semibold text-gray-700 mb-1">Dia do mês</label>
                <input type="number" min="1" max="31" class="evento-dia-mes rounded-xl border-2 border-gray-200 bg-white px-3 py-2 focus:ring-2 focus:ring-indigo-500 w-full" placeholder="1 a 31">
            </div>

            <div class="evento-campo-numero-semana hidden">
                <label class="block text-base font-semibold text-gray-700 mb-1">Número da semana</label>
                <select class="evento-numero-semana rounded-xl border-2 border-gray-200 bg-white px-3 py-2 focus:ring-2 focus:ring-indigo-500 w-full">
                    <option value="">Selecione</option>
                    <option value="1">Semana 1</option>
                    <option value="2">Semana 2</option>
                    <option value="3">Semana 3</option>
                    <option value="4">Semana 4</option>
                    <option value="5">Semana 5</option>
                </select>
            </div>

            <div class="evento-campo-mes hidden">
                <label class="block text-base font-semibold text-gray-700 mb-1">Mês</label>
                <select class="evento-mes rounded-xl border-2 border-gray-200 bg-white px-3 py-2 focus:ring-2 focus:ring-indigo-500 w-full">
                    <option value="">Selecione o mês</option>
                    <option value="1">Janeiro</option>
                    <option value="2">Fevereiro</option>
                    <option value="3">Março</option>
                    <option value="4">Abril</option>
                    <option value="5">Maio</option>
                    <option value="6">Junho</option>
                    <option value="7">Julho</option>
                    <option value="8">Agosto</option>
                    <option value="9">Setembro</option>
                    <option value="10">Outubro</option>
                    <option value="11">Novembro</option>
                    <option value="12">Dezembro</option>
                </select>
            </div>

            <div>
                <label class="block text-base font-semibold text-gray-700 mb-1">Horário</label>
                <input type="time" class="evento-horario rounded-xl border-2 border-gray-200 bg-white px-3 py-2 focus:ring-2 focus:ring-indigo-500 w-full">
            </div>
        </div>

        <button type="button" class="evento-ocorrencia-remover px-4 py-2 rounded-lg bg-red-50 text-red-700 border border-red-200 font-medium">Remover frequência</button>
    `;

    const frequenciaSelect = item.querySelector('.evento-frequencia');
    const campoDiaSemana = item.querySelector('.evento-campo-dia-semana');
    const campoDiaMes = item.querySelector('.evento-campo-dia-mes');
    const campoNumeroSemana = item.querySelector('.evento-campo-numero-semana');
    const campoMes = item.querySelector('.evento-campo-mes');

    function atualizarCamposFrequencia() {
        const frequencia = frequenciaSelect.value;

        campoDiaSemana.classList.add('hidden');
        campoDiaMes.classList.add('hidden');
        campoNumeroSemana.classList.add('hidden');
        campoMes.classList.add('hidden');

        if (frequencia === 'semanal') {
            campoDiaSemana.classList.remove('hidden');
        } else if (frequencia === 'mensal') {
            campoDiaMes.classList.remove('hidden');
        } else if (frequencia === 'numero_semana') {
            campoNumeroSemana.classList.remove('hidden');
            campoDiaSemana.classList.remove('hidden');
        } else if (frequencia === 'anual') {
            campoDiaMes.classList.remove('hidden');
            campoMes.classList.remove('hidden');
        }
    }

    frequenciaSelect.addEventListener('change', atualizarCamposFrequencia);

    if (ocorrencia) {
        frequenciaSelect.value = ocorrencia.frequencia || 'semanal';
        const diasEvento = Array.isArray(ocorrencia.dias) ? ocorrencia.dias : (ocorrencia.dia !== undefined && ocorrencia.dia !== null && ocorrencia.dia !== '' ? [ocorrencia.dia] : []);
        item.querySelectorAll('.evento-dia-check').forEach((checkbox) => {
            checkbox.checked = diasEvento.map(String).includes(checkbox.value);
        });
        item.querySelector('.evento-dia-mes').value = ocorrencia.dia_mes ?? '';
        item.querySelector('.evento-numero-semana').value = ocorrencia.numero_semana ?? '';
        item.querySelector('.evento-mes').value = ocorrencia.mes ?? '';
        item.querySelector('.evento-horario').value = ocorrencia.horario || '';
    }

    atualizarCamposFrequencia();

    item.querySelector('.evento-ocorrencia-remover').addEventListener('click', function () {
        const eventoId = parseInt(item.dataset.eventoId, 10);
        if (Number.isInteger(eventoId) && eventoId > 0) {
            eventosRemovidos.push(eventoId);
        }
        item.remove();
    });

    return item;
}

function mapaAdicionarEventoPorGrupo(grupo, evento = null, adicionarNoTopo = false) {
    const grupoConfig = EVENTO_GRUPOS[grupo];
    if (!grupoConfig) return;

    const container = document.getElementById(grupoConfig.containerId);
    if (!container) return;

    if (!evento) {
        container.querySelectorAll(':scope > div').forEach((eventoExistente) => {
            const conteudo = eventoExistente.querySelector('.evento-conteudo');
            const iconeToggle = eventoExistente.querySelector('.evento-toggle-icon i');
            if (conteudo) conteudo.classList.add('hidden');
            if (iconeToggle) {
                iconeToggle.classList.toggle('bi-chevron-down', true);
                iconeToggle.classList.toggle('bi-chevron-up', false);
            }
        });
    }

    const div = document.createElement('div');
    div.className = 'bg-gray-50 rounded-2xl shadow-sm border border-gray-200 overflow-hidden';
    div.dataset.eventoGrupo = grupo;

    div.innerHTML = `
        <button type="button" class="evento-toggle w-full px-4 py-3 text-left bg-white hover:bg-gray-100 transition flex items-center justify-between gap-3">
            <span class="evento-resumo font-semibold text-gray-800 truncate">Nova atividade</span>
            <span class="evento-toggle-icon text-gray-500 text-sm"><i class="bi bi-chevron-down"></i></span>
        </button>

        <div class="evento-conteudo p-4 space-y-3 border-t border-gray-200">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Nome da atividade</label>
                    <input type="text" placeholder="Ex.: ${grupoConfig.label} da comunidade" class="evento-titulo w-full rounded-xl border-2 border-gray-200 bg-white px-3 py-2">
                </div>

                <div class="hidden">
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Tipo de atividade (pré-definido)</label>
                    <select class="tipo-evento rounded-xl border-2 border-gray-200 bg-white px-3 py-2 w-full focus:ring-2 focus:ring-indigo-500"></select>
                </div>

                <div class="campo-caracteristicas md:col-span-2">
                    <div class="flex items-center justify-between gap-2 mb-1">
                        <label class="block text-sm font-semibold text-gray-700">Características</label>
                        <button type="button" class="evento-limpar-caracteristicas text-xs text-indigo-700 hover:underline">Limpar seleção</button>
                    </div>
                    <p class="text-xs text-gray-500 mb-1">Dica: para desmarcar uma característica, clique nela novamente.</p>
                    <select class="tags-evento rounded-xl border-2 border-gray-200 bg-white px-3 py-2 w-full" multiple style="height: auto;"></select>
                </div>

                <div class="md:col-span-2">
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Observação</label>
                    <textarea placeholder="Observação" class="evento-observacao w-full rounded-xl border-2 border-gray-200 bg-white px-3 py-2 min-h-[78px]"></textarea>
                </div>
            </div>

            <div>
                <p class="text-base font-semibold text-gray-700">Frequências e horários</p>
                <div class="evento-ocorrencias space-y-3 mt-3"></div>
                <button type="button" class="evento-ocorrencia-adicionar mt-3 px-4 py-2 rounded-lg bg-indigo-50 text-indigo-700 border border-indigo-200 font-medium hover:bg-indigo-100 transition">+ Adicionar frequência</button>
            </div>

            <div class="pt-2 border-t border-gray-200">
                <button type="button" class="evento-remover px-4 py-2 rounded-lg bg-red-50 text-red-700 border border-red-200 font-medium">Remover atividade</button>
            </div>
        </div>
    `;

    if (adicionarNoTopo) {
        container.prepend(div);
    } else {
        container.appendChild(div);
    }

    const selectTipo = div.querySelector('.tipo-evento');
    const selectTags = div.querySelector('.tags-evento');
    const btnLimparCaracteristicas = div.querySelector('.evento-limpar-caracteristicas');
    const campoTitulo = div.querySelector('.evento-titulo');
    const eventoResumo = div.querySelector('.evento-resumo');
    const botaoToggle = div.querySelector('.evento-toggle');
    const icone = div.querySelector('.evento-toggle-icon i');
    const conteudoEvento = div.querySelector('.evento-conteudo');
    const ocorrenciasContainer = div.querySelector('.evento-ocorrencias');

    const definirEstadoSanfona = (expandido) => {
        conteudoEvento.classList.toggle('hidden', !expandido);
        icone.classList.toggle('bi-chevron-down', !expandido);
        icone.classList.toggle('bi-chevron-up', expandido);
    };

    const atualizarResumoEvento = () => {
        const titulo = campoTitulo.value.trim();
        eventoResumo.textContent = titulo || 'Nova atividade';
    };

    botaoToggle.addEventListener('click', () => definirEstadoSanfona(conteudoEvento.classList.contains('hidden')));
    campoTitulo.addEventListener('input', atualizarResumoEvento);

    if (btnLimparCaracteristicas) {
        btnLimparCaracteristicas.addEventListener('click', () => {
            Array.from(selectTags.options).forEach((option) => {
                option.selected = false;
            });
        });
    }

    selectTags.addEventListener('mousedown', (event) => {
        const option = event.target;
        if (!option || option.tagName !== 'OPTION') return;
        event.preventDefault();
        option.selected = !option.selected;
    });

    if (grupo === 'confissao') {
        const campoCaracteristicas = div.querySelector('.campo-caracteristicas');
        if (campoCaracteristicas) campoCaracteristicas.classList.add('hidden');
    }

    mapaCarregarTiposEvento(selectTipo, grupo).then(() => {
        if (evento?.tipo_evento_id) {
            selectTipo.value = String(evento.tipo_evento_id);
        }

        selectTags.dataset.tipoEventoId = selectTipo.value || '';
        if (grupo === 'confissao') {
            selectTags.innerHTML = '';
            return;
        }
        mapaCarregarTagsEvento(selectTags).then(() => {
            if (Array.isArray(evento?.tags_evento_ids)) {
                Array.from(selectTags.options).forEach((option) => {
                    option.selected = evento.tags_evento_ids.includes(parseInt(option.value, 10));
                });
            }
        });
    });

    div.querySelector('.evento-ocorrencia-adicionar').addEventListener('click', () => {
        ocorrenciasContainer.appendChild(mapaCriarOcorrencia());
    });

    const ocorrencias = Array.isArray(evento?.ocorrencias) && evento.ocorrencias.length ? evento.ocorrencias : (evento ? [evento] : [null]);
    ocorrencias.forEach((ocorrencia) => {
        ocorrenciasContainer.appendChild(mapaCriarOcorrencia(ocorrencia));
    });

    if (evento) {
        campoTitulo.value = evento.titulo || '';
        div.querySelector('.evento-observacao').value = evento.observacao || '';
    }

    atualizarResumoEvento();
    definirEstadoSanfona(!!(!evento));

    div.querySelector('.evento-remover').addEventListener('click', function () {
        const titulo = div.querySelector('.evento-titulo').value || 'sem título';
        const confirmou = window.confirm(`você tem certeza que deseja apagar o evento ${titulo}?`);
        if (!confirmou) return;

        div.querySelectorAll('.evento-ocorrencia').forEach((item) => {
            const eventoId = parseInt(item.dataset.eventoId, 10);
            if (Number.isInteger(eventoId) && eventoId > 0) {
                eventosRemovidos.push(eventoId);
            }
        });

        div.remove();
    });
}

function mapaAdicionarEvento(evento = null, adicionarNoTopo = false) {
    const grupo = evento ? mapaResolverGrupoPorTipoEvento(evento.tipo_evento_id) : 'missa';
    mapaAdicionarEventoPorGrupo(grupo || 'missa', evento, adicionarNoTopo);
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

function mapaAdicionarContato(tipoInicial = '', valorInicial = '', adicionarNoTopo = true) {
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
        telefone: '(85) 99999-9999',
        whatsapp: '(85) 99999-9999',
        instagram: '@usuario ou https://instagram.com/usuario',
        facebook: '@usuario ou https://facebook.com/pagina',
        youtube: '@canal ou https://youtube.com/@canal',
        site: 'https://seusite.com.br',
        email: 'contato@exemplo.com',
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
            ? 'O local foi atualizada com sucesso.'
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

    document.querySelectorAll('.eventos-lista > div').forEach((div) => {
        const tagsSelecionadas = Array.from(div.querySelector('.tags-evento').selectedOptions)
            .map((option) => parseInt(option.value, 10))
            .filter(Number.isInteger);

        const tipoEvento = parseInt(div.querySelector('.tipo-evento').value, 10);
        const titulo = div.querySelector('.evento-titulo').value;
        const observacao = div.querySelector('.evento-observacao').value;

        div.querySelectorAll('.evento-ocorrencia').forEach((ocorrencia) => {
            const eventoId = parseInt(ocorrencia.dataset.eventoId, 10);

            eventos.push({
                id: Number.isInteger(eventoId) ? eventoId : null,
                titulo,
                frequencia: ocorrencia.querySelector('.evento-frequencia').value,
                dias: Array.from(ocorrencia.querySelectorAll('.evento-dia-check:checked')).map((checkbox) => checkbox.value),
                dia_mes: ocorrencia.querySelector('.evento-dia-mes').value,
                numero_semana: ocorrencia.querySelector('.evento-numero-semana').value,
                mes: ocorrencia.querySelector('.evento-mes').value,
                horario: ocorrencia.querySelector('.evento-horario').value,
                observacao,
                tipo_evento: Number.isInteger(tipoEvento) ? tipoEvento : null,
                tags_evento: tagsSelecionadas
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
