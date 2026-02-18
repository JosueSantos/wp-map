let eventos = [];
let contatos = [];

const TIPOS_CONTATO = [
  "telefone","whatsapp","instagram","facebook","youtube","site","email"
];

function mapaAdicionarEvento() {

    const evt = {
        titulo: prompt("Título do evento"),
        tipo: prompt("Tipo: missa, confissao, grupo..."),
        dia: prompt("Dia da semana 0-6"),
        horario: prompt("Horário HH:MM"),
        descricao: "",
        observacao: "",
        tags: []
    };

    eventos.push(evt);

    document.getElementById("eventos").innerHTML =
        JSON.stringify(eventos, null, 2);
}

function mapaAdicionarContato() {

    const tipo = prompt("Tipo: telefone, whatsapp, instagram, facebook, youtube, site, email");

    if (!TIPOS_CONTATO.includes(tipo)) {
        alert("Tipo inválido");
        return;
    }

    const valor = prompt("Valor:");

    if (!tipo || !valor) return;

    contatos.push({ tipo, valor });

    document.getElementById("contatos-lista").textContent =
        JSON.stringify(contatos, null, 2);
}

async function mapaEnviar() {

    const data = {
        nome: document.getElementById("nome").value,
        tipo: document.getElementById("tipo").value,
        latitude: document.getElementById("latitude").value,
        longitude: document.getElementById("longitude").value,
        endereco: document.getElementById("endereco").value,
        contatos: contatos,
        eventos: eventos
    };

    const res = await fetch(MAPA_API.url, {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
            "X-WP-Nonce": MAPA_API.nonce
        },
        body: JSON.stringify(data)
    });

    if (!res.ok) {
        alert("Erro ao salvar. Verifique os dados.");
        return;
    }

    const json = await res.json();

    document.getElementById("mapa-debug").textContent =
        JSON.stringify(json, null, 2);
}
