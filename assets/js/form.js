let eventos = [];
let contatos = [];
let mapaCadastro;
let marcadorCadastro;

document.addEventListener('DOMContentLoaded', function () {
    mapaCarregarTiposComunidade();
    mapaIniciarSeletorDeCoordenadas();
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
        vazio.className = 'p-2 text-sm text-gray-600';
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
    div.className = "bg-gray-50 p-6 rounded-2xl space-y-4 shadow-sm";

    div.innerHTML = `
        <input type="text" placeholder="Título"
            class="evento-titulo w-full rounded-xl border-gray-300">

        <div class="grid grid-cols-2 gap-4">
            <input type="number" min="0" max="6" placeholder="Dia da semana (0=Dom)"
                class="evento-dia rounded-xl border-gray-300">

            <input type="text" placeholder="Horário"
                class="evento-horario rounded-xl border-gray-300">
        </div>

        <textarea placeholder="Descrição"
            class="evento-descricao w-full rounded-xl border-gray-300"></textarea>

        <textarea placeholder="Observação"
            class="evento-observacao w-full rounded-xl border-gray-300"></textarea>

        <div class="grid grid-cols-2 gap-4">
            <select class="tipo-evento rounded-xl border-gray-300">
                <option>Carregando tipos...</option>
            </select>

            <select class="tags-evento rounded-xl border-gray-300" multiple>
            </select>
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
    div.className = "grid grid-cols-2 gap-3 bg-gray-50 p-4 rounded-xl";

    const options = TIPOS_CONTATO.map(tipo =>
        `<option value="${tipo}">${tipo}</option>`
    ).join('');

    div.innerHTML = `
        <select class="contato-tipo rounded-lg border-gray-300 focus:ring-blue-500">
            <option value="">Selecione</option>
            ${options}
        </select>

        <input type="text" placeholder="Valor"
            class="contato-valor rounded-lg border-gray-300 focus:ring-blue-500">
    `;

    container.appendChild(div);
}

function mapaEnviar() {

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

    const dados = {
        nome: document.getElementById('nome').value,
        tipo: document.getElementById('tipo').value,
        latitude: document.getElementById('latitude').value,
        longitude: document.getElementById('longitude').value,
        endereco: document.getElementById('endereco').value,
        parent_paroquia: document.getElementById('parent_paroquia').value,
        contatos,
        eventos
    };

    fetch(MAPA_API.url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': MAPA_API.nonce
        },
        body: JSON.stringify(dados)
    })
    .then(r => r.json())
    .then(resp => {
        document.getElementById('mapa-debug').innerText = JSON.stringify(resp, null, 2);
    });
}
