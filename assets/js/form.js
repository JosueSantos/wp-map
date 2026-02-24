let eventos = [];
let contatos = [];

document.addEventListener('DOMContentLoaded', function () {
    mapaCarregarTiposComunidade();
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
