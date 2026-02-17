document.addEventListener("DOMContentLoaded", async function () {

    const mapaEl = document.getElementById("mapa-igrejas");
    if (!mapaEl) return;

    let dominio = mapaEl.dataset.dominio || "";

    if (dominio.endsWith("/")) {
        dominio = dominio.slice(0, -1);
    }

    const API_URL = dominio
        ? dominio + "/wp-json/mapa/v1/comunidades"
        : "/wp-json/mapa/v1/comunidades";

    // Criar mapa
    const map = L.map("mapa-igrejas").setView([-5.0892, -42.8016], 13);

    L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
        attribution: "&copy; OpenStreetMap"
    }).addTo(map);

    try {

        const res = await fetch(API_URL);
        const comunidades = await res.json();

        comunidades.forEach(c => {

            const marker = L.marker([c.latitude, c.longitude]).addTo(map);

            let eventosHtml = "";

            c.eventos.forEach(e => {
                eventosHtml += `
                    <div style="margin-bottom:6px;">
                        <strong>${e.titulo}</strong><br>
                        ${e.dia} às ${e.horario}
                    </div>
                `;
            });

            const popup = `
                <div style="min-width:200px">
                    <h3 style="margin:0">${c.nome}</h3>
                    <p>${c.endereco ?? ""}</p>
                    ${eventosHtml}
                </div>
            `;

            marker.bindPopup(popup);
        });

    } catch (err) {
        console.error("Erro ao carregar mapa:", err);
    }

});
