let eventos = [];
let contatos = [];
let mapaCadastro;
let marcadorCadastro;

document.addEventListener('DOMContentLoaded', function () {
    mapaCarregarTiposComunidade();
    mapaIniciarSeletorDeCoordenadas();
    mapaIniciarEtapasDoFormulario();
    mapaIniciarValidadorImagem();
});

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
        : [-5.0892, -42.8016];

    mapaCadastro = L.map('mapa-cadastro').setView(centroInicial, 13);

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

function mapaAdicionarEvento() {
    const container = document.getElementById('eventos');

    const div = document.createElement('div');
    div.className = "bg-gray-50 p-6 rounded-2xl space-y-4 shadow-sm border border-gray-200";

    div.innerHTML = `
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Tipo de evento</label>
            <select class="tipo-evento rounded-xl border-2 border-gray-200 bg-white px-3 py-2 w-full focus:ring-2 focus:ring-indigo-500">
                <option>Carregando tipos...</option>
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Nome do evento</label>
            <input type="text" placeholder="Ex.: Missa da comunidade"
                class="evento-titulo w-full rounded-xl border-2 border-gray-200 bg-white px-3 py-2">
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Dia da semana</label>
                <select class="evento-dia rounded-xl border-2 border-gray-200 bg-white px-3 py-2 focus:ring-2 focus:ring-indigo-500 w-full">
                    <option value="">Selecione o dia</option>
                    <option value="0">Domingo</option>
                    <option value="1">Segunda-feira</option>
                    <option value="2">Terça-feira</option>
                    <option value="3">Quarta-feira</option>
                    <option value="4">Quinta-feira</option>
                    <option value="5">Sexta-feira</option>
                    <option value="6">Sábado</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Horário</label>
                <input type="time"
                    class="evento-horario rounded-xl border-2 border-gray-200 bg-white px-3 py-2 focus:ring-2 focus:ring-indigo-500 w-full">
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Características</label>
            <select class="tags-evento rounded-xl border-2 border-gray-200 bg-white px-3 py-2 w-full" multiple>
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Descrição</label>
            <textarea placeholder="Descrição"
                class="evento-descricao w-full rounded-xl border-2 border-gray-200 bg-white px-3 py-2"></textarea>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Observação</label>
            <textarea placeholder="Observação"
                class="evento-observacao w-full rounded-xl border-2 border-gray-200 bg-white px-3 py-2"></textarea>
        </div>
    `;

    container.appendChild(div);

    const novoEvento = container.lastElementChild;

    const selectTipo = novoEvento.querySelector('.tipo-evento');
    const selectTags = novoEvento.querySelector('.tags-evento');

    mapaCarregarTiposEvento(selectTipo);
    mapaCarregarTagsEvento(selectTags);
}

const TIPOS_CONTATO = [
  "telefone","whatsapp","instagram","facebook","youtube","site","email"
];

function mapaAdicionarContato() {
    const container = document.getElementById('contatos-container');

    const div = document.createElement('div');
    div.className = "grid grid-cols-1 sm:grid-cols-2 gap-3 bg-gray-50 p-4 rounded-xl border border-gray-200";

    const options = TIPOS_CONTATO.map(tipo =>
        `<option value="${tipo}">${tipo}</option>`
    ).join('');

    div.innerHTML = `
        <select class="contato-tipo rounded-lg border-2 border-gray-200 bg-white px-3 py-2 focus:ring-2 focus:ring-indigo-500">
            <option value="">Selecione</option>
            ${options}
        </select>

        <input type="text" placeholder="Valor"
            class="contato-valor rounded-lg border-2 border-gray-200 bg-white px-3 py-2 focus:ring-2 focus:ring-indigo-500">
    `;

    container.appendChild(div);
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

        mensagem.textContent = 'Imagem válida selecionada.';
        mensagem.classList.remove('hidden');
        mensagem.classList.add('text-emerald-700', 'font-medium');
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

function mapaEnviar() {

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

        eventos.push({
            titulo: div.querySelector('.evento-titulo').value,
            dia: div.querySelector('.evento-dia').value,
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

    const imagemInput = document.getElementById('imagem-comunidade');
    if (imagemInput?.files?.length) {
        formData.append('imagem_comunidade', imagemInput.files[0]);
    }

    mapaMostrarFeedback('Enviando cadastro... aguarde.', 'info');

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
        mapaMostrarFeedback(`Cadastro realizado com sucesso! ID da comunidade: ${resp.comunidade_id}.`, 'sucesso');
    })
    .catch(error => {
        mapaMostrarFeedback(error.message || 'Erro ao enviar cadastro. Tente novamente.', 'erro');
    });
}
