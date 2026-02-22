let eventos = [];
let contatos = [];

const TIPOS_CONTATO = [
  "telefone","whatsapp","instagram","facebook","youtube","site","email"
];

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
    `;

    container.appendChild(div);
}

function mapaAdicionarContato() {
    const container = document.getElementById('contatos-container');

    const div = document.createElement('div');
    div.className = "grid grid-cols-2 gap-3 bg-gray-50 p-4 rounded-xl";

    div.innerHTML = `
        <input type="text" placeholder="Tipo (email, instagram...)"
            class="contato-tipo rounded-lg border-gray-300 focus:ring-blue-500">

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
        eventos.push({
            titulo: div.querySelector('.evento-titulo').value,
            dia: div.querySelector('.evento-dia').value,
            horario: div.querySelector('.evento-horario').value,
            descricao: div.querySelector('.evento-descricao').value
        });
    });

    const dados = {
        nome: document.getElementById('nome').value,
        tipo: document.getElementById('tipo').value,
        latitude: document.getElementById('latitude').value,
        longitude: document.getElementById('longitude').value,
        endereco: document.getElementById('endereco').value,
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
