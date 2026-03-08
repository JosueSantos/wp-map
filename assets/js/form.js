let eventos = [];
let contatos = [];
let mapaCadastro;
let marcadorCadastro;
let modoEdicao = false;
let comunidadeEditandoId = null;
let eventosRemovidos = [];
let mapaDefinirCoordenadas = null;

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

    mapaMostrarFeedback('Carregando dados da comunidade para edição...', 'info');

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
            throw new Error(dados?.message || 'Não foi possível carregar a comunidade para edição.');
        }

        mapaAplicarDadosDaComunidade(dados);
        mapaMostrarFeedback('Você está editando esta comunidade. Ajuste os campos e salve.', 'info');

        const titulo = document.querySelector('#secao-etapa-1 h3');
        if (titulo) titulo.textContent = '1. Dados principais (edição)';

        const heading = document.querySelector('h2');
        if (heading) heading.textContent = 'Editar Comunidade';
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
        mapaAdicionarContato(contato.tipo || '', contato.valor || '');
    });

    const eventosContainer = document.getElementById('eventos');
    eventosContainer.innerHTML = '';
    (dados.eventos || []).forEach((evento) => {
        mapaAdicionarEvento(evento);
    });

    mapaAtualizarPreviewImagemExistente(dados.imagem_url || '');
}

async function mapaCarregarTiposComunidade() {

    const select = document.getElementById('tipo');

    try {

        const response = await fetch('/wp-json/wp/v2/tipo_comunidade?per_page=100');
        const termos = await response.json();

        select.innerHTML = '<option value="">Selecione</option>';

        termos.forEach(termo => {

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
        const progresso = Math.max(1, Math.min(4, stepAtual)) * 25;
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


async function mapaCarregarTiposEvento(select) {

    const response = await fetch('/wp-json/wp/v2/tipo_evento?per_page=100');
    const termos = await response.json();

    select.innerHTML = '<option value="">Selecione</option>';

    termos.forEach(termo => {

        const option = document.createElement('option');
        option.value = termo.id;
        option.textContent = termo.name;

        select.appendChild(option);

    });
}

async function mapaCarregarTagsEvento(select) {

    const response = await fetch('/wp-json/wp/v2/tags_evento?per_page=100');
    const termos = await response.json();

    select.innerHTML = '';

    termos.forEach(termo => {

        const option = document.createElement('option');
        option.value = termo.id;
        option.textContent = termo.name;

        select.appendChild(option);

    });
}

function mapaAdicionarEvento(evento = null) {
    const container = document.getElementById('eventos');

    if (!evento) {
        container.querySelectorAll(':scope > div').forEach((eventoExistente) => {
            const conteudo = eventoExistente.querySelector('.evento-conteudo');
            const iconeToggle = eventoExistente.querySelector('.evento-toggle-icon');
            const icone = iconeToggle?.querySelector('i');

            if (conteudo) conteudo.classList.add('hidden');
            if (icone) icone.classList.toggle('bi-chevron-down', true);
        });
    }

    const div = document.createElement('div');
    div.className = "bg-gray-50 rounded-2xl shadow-sm border border-gray-200 overflow-hidden";

    div.innerHTML = `
        <button type="button" class="evento-toggle w-full px-4 py-3 text-left bg-white hover:bg-gray-100 transition flex items-center justify-between gap-3">
            <span class="evento-resumo font-semibold text-gray-800 truncate">Novo evento</span>
            <span class="evento-toggle-icon text-gray-500 text-sm"><i class="bi bi-chevron-down"></i></span>
        </button>

        <div class="evento-conteudo p-6 space-y-4 border-t border-gray-200">
            <div>
                <label class="block text-base font-semibold text-gray-700 mb-1">Nome do evento</label>
                <input type="text" placeholder="Ex.: Missa da comunidade" class="evento-titulo w-full rounded-xl border-2 border-gray-200 bg-white px-3 py-2">
            </div>
        
            <div>
                <label class="block text-base font-semibold text-gray-700 mb-1">Tipo de evento</label>
                <select class="tipo-evento rounded-xl border-2 border-gray-200 bg-white px-3 py-2 w-full focus:ring-2 focus:ring-indigo-500">
                    <option>Carregando tipos...</option>
                </select>
            </div>

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
                    <input type="time"
                        class="evento-horario rounded-xl border-2 border-gray-200 bg-white px-3 py-2 focus:ring-2 focus:ring-indigo-500 w-full">
                </div>
            </div>

            <div>
                <label class="block text-base font-semibold text-gray-700 mb-1">Características</label>
                <select class="tags-evento rounded-xl border-2 border-gray-200 bg-white px-3 py-2 w-full" multiple style="height: auto;">
                </select>
            </div>

            <div>
                <label class="block text-base font-semibold text-gray-700 mb-1">Descrição</label>
                <textarea placeholder="Descrição"
                    class="evento-descricao w-full rounded-xl border-2 border-gray-200 bg-white px-3 py-2 min-h-[96px]"></textarea>
            </div>

            <div>
                <label class="block text-base font-semibold text-gray-700 mb-1">Observação</label>
                <textarea placeholder="Observação"
                    class="evento-observacao w-full rounded-xl border-2 border-gray-200 bg-white px-3 py-2 min-h-[96px]"></textarea>
            </div>

            <div class="pt-2 border-t border-gray-200">
                <button type="button" class="evento-remover px-4 py-2 rounded-lg bg-red-50 text-red-700 border border-red-200 font-medium">Remover evento</button>
            </div>
        </div>
    `;

    container.appendChild(div);

    const novoEvento = container.lastElementChild;

    const selectTipo = novoEvento.querySelector('.tipo-evento');
    const selectTags = novoEvento.querySelector('.tags-evento');
    const campoTitulo = novoEvento.querySelector('.evento-titulo');
    const eventoResumo = novoEvento.querySelector('.evento-resumo');
    const botaoToggle = novoEvento.querySelector('.evento-toggle');
    const iconeToggle = novoEvento.querySelector('.evento-toggle-icon');
    const icone = iconeToggle.querySelector('i');
    const conteudoEvento = novoEvento.querySelector('.evento-conteudo');

    function atualizarResumoEvento() {
        const titulo = campoTitulo.value.trim();
        eventoResumo.textContent = titulo || 'Novo evento';
    }


    function definirEstadoSanfona(expandido) {
        conteudoEvento.classList.toggle('hidden', !expandido);

        icone.classList.toggle('bi-chevron-down', !expandido);
        icone.classList.toggle('bi-chevron-up', expandido);
    }

    botaoToggle.addEventListener('click', function () {
        definirEstadoSanfona(conteudoEvento.classList.contains('hidden'));
    });

    campoTitulo.addEventListener('input', atualizarResumoEvento);

    mapaCarregarTiposEvento(selectTipo).then(() => {
        if (evento?.tipo_evento_id) {
            selectTipo.value = String(evento.tipo_evento_id);
        }
    });

    mapaCarregarTagsEvento(selectTags).then(() => {
        if (Array.isArray(evento?.tags_evento_ids)) {
            Array.from(selectTags.options).forEach((option) => {
                option.selected = evento.tags_evento_ids.includes(parseInt(option.value, 10));
            });
        }
    });

    if (evento) {
        novoEvento.dataset.eventoId = evento.id ? String(evento.id) : '';
        campoTitulo.value = evento.titulo || '';
        novoEvento.querySelector('.evento-frequencia').value = evento.frequencia || 'semanal';
        const diasEvento = Array.isArray(evento.dias) ? evento.dias : (evento.dia !== undefined && evento.dia !== null && evento.dia !== '' ? [evento.dia] : []);
        novoEvento.querySelectorAll('.evento-dia-check').forEach((checkbox) => {
            checkbox.checked = diasEvento.map(String).includes(checkbox.value);
        });
        novoEvento.querySelector('.evento-dia-mes').value = evento.dia_mes ?? '';
        novoEvento.querySelector('.evento-numero-semana').value = evento.numero_semana ?? '';
        novoEvento.querySelector('.evento-mes').value = evento.mes ?? '';
        novoEvento.querySelector('.evento-horario').value = evento.horario || '';
        novoEvento.querySelector('.evento-descricao').value = evento.descricao || '';
        novoEvento.querySelector('.evento-observacao').value = evento.observacao || '';
    }

    atualizarResumoEvento();
    definirEstadoSanfona(false);


    const frequenciaSelect = novoEvento.querySelector('.evento-frequencia');
    const campoDiaSemana = novoEvento.querySelector('.evento-campo-dia-semana');
    const campoDiaMes = novoEvento.querySelector('.evento-campo-dia-mes');
    const campoNumeroSemana = novoEvento.querySelector('.evento-campo-numero-semana');
    const campoMes = novoEvento.querySelector('.evento-campo-mes');

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
    atualizarCamposFrequencia();

    if (!evento) {
        definirEstadoSanfona(true);
        novoEvento.scrollIntoView({ behavior: 'smooth', block: 'start' });
        campoTitulo.focus();
    }

    novoEvento.querySelector('.evento-remover').addEventListener('click', function () {
        const titulo = novoEvento.querySelector('.evento-titulo').value || 'sem título';
        const confirmou = window.confirm(`você tem certeza que deseja apagar o evento ${titulo}?`);
        if (!confirmou) return;

        const eventoId = parseInt(novoEvento.dataset.eventoId, 10);
        if (Number.isInteger(eventoId) && eventoId > 0) {
            eventosRemovidos.push(eventoId);
        }

        novoEvento.remove();
    });
}

const TIPOS_CONTATO = [
  "telefone","whatsapp","instagram","facebook","youtube","site","email"
];

function mapaAdicionarContato(tipoInicial = '', valorInicial = '') {
    const container = document.getElementById('contatos-container');

    const div = document.createElement('div');
    div.className = "grid grid-cols-1 sm:grid-cols-2 gap-3 bg-gray-50 p-4 rounded-xl border border-gray-200 shadow-sm";

    const options = TIPOS_CONTATO.map(tipo =>
        `<option value="${tipo}">${tipo}</option>`
    ).join('');

    div.innerHTML = `
        <select class="contato-tipo rounded-lg border-2 border-gray-200 bg-white px-3 py-2 focus:ring-2 focus:ring-indigo-500 text-base">
            <option value="">Selecione</option>
            ${options}
        </select>

        <input type="text" placeholder="Valor"
            class="contato-valor rounded-lg border-2 border-gray-200 bg-white px-3 py-2 focus:ring-2 focus:ring-indigo-500 text-base">
    `;

    container.appendChild(div);

    div.querySelector('.contato-tipo').value = tipoInicial;
    div.querySelector('.contato-valor').value = valorInicial;
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
            ? 'A comunidade foi atualizada com sucesso.'
            : `Comunidade cadastrada com sucesso! Código: ${resp?.comunidade_id || '-'}.`;
    }

    botaoNovo.onclick = function () {
        window.location.href = window.location.pathname;
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
    document.querySelectorAll('#contatos-container > div').forEach(div => {
        const tipo = div.querySelector('.contato-tipo').value;
        const valor = div.querySelector('.contato-valor').value;

        if (tipo && valor) {
            contatos.push({ tipo, valor });
        }
    });

    const eventos = [];

    document.querySelectorAll('#eventos > div').forEach(div => {

        const tagsSelecionadas = Array.from(
            div.querySelector('.tags-evento').selectedOptions
        ).map(option => parseInt(option.value));

        const eventoId = parseInt(div.dataset.eventoId, 10);

        eventos.push({
            id: Number.isInteger(eventoId) ? eventoId : null,
            titulo: div.querySelector('.evento-titulo').value,
            frequencia: div.querySelector('.evento-frequencia').value,
            dias: Array.from(div.querySelectorAll('.evento-dia-check:checked')).map((checkbox) => checkbox.value),
            dia_mes: div.querySelector('.evento-dia-mes').value,
            numero_semana: div.querySelector('.evento-numero-semana').value,
            mes: div.querySelector('.evento-mes').value,
            horario: div.querySelector('.evento-horario').value,
            descricao: div.querySelector('.evento-descricao').value,
            observacao: div.querySelector('.evento-observacao').value,
            tipo_evento: parseInt(div.querySelector('.tipo-evento').value),
            tags_evento: tagsSelecionadas
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
        mapaMostrarFeedback(modoEdicao ? 'Comunidade atualizada com sucesso!' : `Cadastro realizado com sucesso! ID da comunidade: ${resp.comunidade_id}.`, 'sucesso');
        mapaExibirModalSucesso(resp);
    })
    .catch(error => {
        mapaMostrarFeedback(error.message || 'Erro ao enviar cadastro. Tente novamente.', 'erro');
    })
    .finally(() => {
        mapaDefinirEstadoBotaoEnvio(false);
    });
}
