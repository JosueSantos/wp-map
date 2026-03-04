<div id="mapa-auth-modal" class="hidden fixed inset-0 w-screen h-screen z-[9999] bg-black/70 px-4 py-8 alignfull">
    <div class="mx-auto max-w-xl h-full flex items-center justify-center">
        <div class="w-full bg-white rounded-2xl shadow-2xl p-5 sm:p-8 space-y-3 sm:space-y-4 text-center">
            <h3 class="text-2xl font-bold text-gray-800">Faça login para continuar</h3>
            <p class="text-gray-600">Para criar ou editar comunidades e eventos, faça login com sua conta WordPress. Se ainda não tiver acesso, faça seu cadastro e depois retorne para concluir o formulário.</p>
            <div class="flex flex-col sm:flex-row gap-3 justify-center pt-1 sm:pt-2">
                <a id="mapa-login-link" href="#" class="px-5 py-2.5 rounded-xl bg-indigo-600 text-white font-semibold">Entrar</a>
                <a id="mapa-register-link" href="#" class="px-5 py-2.5 rounded-xl border border-indigo-200 text-indigo-700 font-semibold bg-indigo-50">Criar cadastro</a>
            </div>
        </div>
    </div>
</div>

<div class="max-w-5xl mx-auto bg-white shadow-xl rounded-2xl p-4 sm:p-8 space-y-6">
    <p id="mapa-user-greeting" class="text-lg text-gray-700 font-medium hidden"></p>
    <div class="sticky top-8 z-20 bg-white/95 backdrop-blur-sm py-3 border-b border-gray-100 rounded-xl px-2 shadow-sm">
        <div class="space-y-2">
            <h2 class="text-2xl font-bold text-gray-800">Cadastrar Comunidade</h2>
            <p class="text-gray-600 text-base">Fluxo guiado por etapas para facilitar o preenchimento.</p>
        </div>

        <div class="h-2 bg-gray-200 rounded-full overflow-hidden">
            <div id="progresso-cadastro" class="h-full bg-indigo-600 rounded-full transition-all duration-300" style="width: 25%;"></div>
        </div>

        <div id="etapas-cadastro" class="mt-3 hidden md:grid md:grid-cols-4 gap-2 text-sm sm:text-base">
            <button type="button" data-step-nav="1" class="step-nav w-full text-left px-3 py-2 rounded-lg border border-indigo-200 bg-indigo-50 text-indigo-700 font-medium">1. Dados principais</button>
            <button type="button" data-step-nav="2" class="step-nav w-full text-left px-3 py-2 rounded-lg border border-gray-200 bg-white text-gray-500">2. Localização</button>
            <button type="button" data-step-nav="3" class="step-nav w-full text-left px-3 py-2 rounded-lg border border-gray-200 bg-white text-gray-500">3. Contatos</button>
            <button type="button" data-step-nav="4" class="step-nav w-full text-left px-3 py-2 rounded-lg border border-gray-200 bg-white text-gray-500">4. Eventos</button>
        </div>
    </div>

    <section id="secao-etapa-1" data-step="1" class="rounded-2xl border border-gray-200 p-4 sm:p-6 space-y-4">
        <h3 class="text-lg font-semibold text-gray-800">1. Dados principais</h3>

        <div class="grid md:grid-cols-2 gap-4 sm:gap-6">
            <div>
                <label class="block text-sm font-medium text-gray-700">Nome</label>
                <input type="text" id="nome"
                    class="mt-1 w-full rounded-xl border-2 border-gray-200 bg-gray-50 px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 shadow-sm text-base"
                    placeholder="Ex.: Comunidade São José">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Tipo</label>
                <select id="tipo"
                    class="mt-1 w-full rounded-xl border-2 border-gray-200 bg-gray-50 px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 shadow-sm text-base">
                    <option value="">Carregando...</option>
                </select>
            </div>

            <div id="campo-paroquia" class="hidden md:col-span-2 rounded-xl border border-amber-200 bg-amber-50 p-4">
                <label class="block text-sm font-medium text-amber-900">Paróquia Responsável (obrigatório para Capela)</label>
                <input type="text" id="busca-paroquia"
                    placeholder="Digite para buscar paróquia..."
                    class="mt-1 w-full rounded-xl border-2 border-amber-300 bg-white px-3 py-2 focus:ring-2 focus:ring-amber-500 text-base">
                <input type="hidden" id="parent_paroquia">
                <div id="resultado-paroquias" class="bg-white border rounded-xl mt-2 hidden"></div>
                <p class="mt-2 text-sm sm:text-base text-amber-900 leading-relaxed">
                    Se a paróquia não existir, cadastre primeiro a paróquia antes de seguir com o cadastro da capela.
                </p>
            </div>
        </div>
    </section>

    <section id="secao-etapa-2" data-step="2" class="rounded-2xl border border-gray-200 p-4 sm:p-6 space-y-4">
        <h3 class="text-lg font-semibold text-gray-800">2. Localização</h3>

        <div class="grid md:grid-cols-2 gap-4 sm:gap-6">
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700">Endereço</label>
                <div class="mt-1 flex flex-col sm:flex-row gap-2">
                    <input type="text" id="endereco"
                        class="w-full rounded-xl border-2 border-gray-200 bg-gray-50 px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 shadow-sm text-base"
                        placeholder="Digite o endereço e busque no mapa">
                    <button type="button" id="buscar-endereco-mapa"
                        class="px-5 py-2.5 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200 rounded-xl text-base font-medium whitespace-nowrap transition">
                        Buscar no mapa
                    </button>
                </div>
                <p id="mapa-ajuste-msg" class="mt-3 text-base text-gray-700 hidden">Esta certo a marcação no mapa? Clique para ajustar</p>
                <p id="mapa-endereco-erro" class="mt-3 text-base font-medium text-red-700 hidden"></p>
                <div id="mapa-cadastro" class="mt-3 rounded-xl border" style="height: 320px; z-index: 1;"></div>
            </div>

            <div class="hidden">
                <label class="block text-sm font-medium text-gray-700">Latitude</label>
                <input type="number" step="any" id="latitude"
                    class="mt-1 w-full rounded-xl border-2 border-gray-200 bg-gray-50 px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 shadow-sm text-base">
            </div>

            <div class="hidden">
                <label class="block text-sm font-medium text-gray-700">Longitude</label>
                <input type="number" step="any" id="longitude"
                    class="mt-1 w-full rounded-xl border-2 border-gray-200 bg-gray-50 px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 shadow-sm text-base">
            </div>
        </div>
    </section>

    <section id="secao-etapa-3" data-step="3" class="rounded-2xl border border-gray-200 p-4 sm:p-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">3. Contatos</h3>

        <div id="contatos-container" class="space-y-3"></div>

        <button type="button"
            onclick="mapaAdicionarContato()"
            class="mt-3 w-full sm:w-auto px-5 py-2.5 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200 rounded-xl text-base font-medium transition">
            + Adicionar contato
        </button>
    </section>

    <section id="secao-etapa-4" data-step="4" class="rounded-2xl border border-gray-200 p-4 sm:p-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">4. Eventos [Missa, Confissão, Ação social]</h3>

        <div id="eventos" class="space-y-3"></div>

        <button type="button"
            onclick="mapaAdicionarEvento()"
            class="w-full sm:w-auto mt-3 px-5 py-2.5 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200 rounded-xl text-base font-medium transition">
            + Adicionar evento
        </button>
    </section>

    <section id="secao-imagem" class="rounded-2xl border border-gray-200 p-4 sm:p-6 space-y-3">
        <h3 class="text-lg font-semibold text-gray-800">Imagem da comunidade (opcional)</h3>
        <p class="text-base text-gray-600">Aceita JPG, PNG, WEBP ou GIF. Máximo sugerido: 5MB.</p>
        <input type="file" id="imagem-comunidade" accept="image/jpeg,image/png,image/webp,image/gif"
            class="block w-full rounded-xl border-2 border-gray-200 bg-gray-50 p-2 text-base text-gray-700 file:mr-4 file:py-2.5 file:px-4 file:rounded-lg file:border-0 file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
        <p id="imagem-comunidade-msg" class="text-base hidden"></p>
        <div id="imagem-comunidade-preview-wrap" class="hidden">
            <p class="text-sm text-gray-600">Imagem atual:</p>
            <img id="imagem-comunidade-preview" src="" alt="Imagem atual da comunidade" class="mt-2 w-40 h-40 object-cover rounded-lg border border-gray-200">
        </div>
    </section>

    <div class="pt-1">
        <div class="pt-4 mt-2 border-t border-gray-200">
            <button onclick="mapaEnviar()"
                class="w-full bg-indigo-600 hover:bg-indigo-700 text-white py-3 rounded-xl font-semibold transition shadow-sm">
                Salvar Comunidade
            </button>
        </div>
    </div>

    <div id="mapa-debug" class="text-base text-gray-600"></div>

</div>
