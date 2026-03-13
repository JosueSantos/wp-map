<?php
if (!defined('ABSPATH')) exit;

global $post;

if (!$post || $post->post_type !== 'comunidade') {
    get_header();
    echo '<main class="max-w-4xl mx-auto px-4 py-10"><p>Local não encontrado.</p></main>';
    get_footer();
    return;
}

$comunidade_id = $post->ID;
$nome = get_the_title($comunidade_id);
$conteudo = apply_filters('the_content', $post->post_content);
$endereco = get_post_meta($comunidade_id, 'endereco', true);
$latitude = get_post_meta($comunidade_id, 'latitude', true);
$longitude = get_post_meta($comunidade_id, 'longitude', true);
$contatos = get_post_meta($comunidade_id, 'contatos', true);
$contatos = is_array($contatos) ? $contatos : [];
$imagem = get_the_post_thumbnail_url($comunidade_id, 'large');
$tipos = wp_get_post_terms($comunidade_id, 'tipo_comunidade', ['fields' => 'names']);
$eventos = function_exists('cc_obter_eventos_comunidade_ordenados') ? cc_obter_eventos_comunidade_ordenados($comunidade_id) : [];
$paroquia_id = (int) get_post_meta($comunidade_id, 'parent_paroquia', true);
$paroquia_link = $paroquia_id ? get_permalink($paroquia_id) : '';
$paroquia_nome = $paroquia_id ? get_the_title($paroquia_id) : '';
$comunidades_atreladas = [];

if (has_term('paroquia', 'tipo_comunidade', $comunidade_id)) {
    $comunidades_atreladas = get_posts([
        'post_type' => 'comunidade',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'post__not_in' => [$comunidade_id],
        'meta_query' => [[
            'key' => 'parent_paroquia',
            'value' => $comunidade_id,
            'compare' => '=',
            'type' => 'NUMERIC',
        ]],
        'orderby' => 'title',
        'order' => 'ASC',
    ]);
}

function cc_formatar_recorrencia_single($evento) {
    $dia_map = ['0' => 'Domingo', '1' => 'Segunda', '2' => 'Terça', '3' => 'Quarta', '4' => 'Quinta', '5' => 'Sexta', '6' => 'Sábado'];
    $mes_map = ['1' => 'Janeiro', '2' => 'Fevereiro', '3' => 'Março', '4' => 'Abril', '5' => 'Maio', '6' => 'Junho', '7' => 'Julho', '8' => 'Agosto', '9' => 'Setembro', '10' => 'Outubro', '11' => 'Novembro', '12' => 'Dezembro'];

    $frequencia = (string) ($evento['frequencia'] ?? 'semanal');
    $dia_semana = $dia_map[(string) ($evento['dia'] ?? '')] ?? 'dia não informado';
    $dia_mes = (string) ($evento['dia_mes'] ?? '');
    $mes = $mes_map[(string) ($evento['mes'] ?? '')] ?? '';
    $numero_semana = (string) ($evento['numero_semana'] ?? '');
    $dias = is_array($evento['dias'] ?? null) ? $evento['dias'] : [];

    if ($frequencia === 'mensal') return $dia_mes ? "Todo dia {$dia_mes}" : 'Mensal';
    if ($frequencia === 'numero_semana') return ($numero_semana && $dia_semana) ? "{$numero_semana}ª {$dia_semana} do mês" : 'Por número da semana';
    if ($frequencia === 'anual') return ($dia_mes && $mes) ? "Todo dia {$dia_mes} de {$mes}" : 'Anual';

    if (!empty($dias)) {
        $nomes = [];
        foreach ($dias as $dia) {
            $key = (string) $dia;
            if (isset($dia_map[$key])) $nomes[] = $dia_map[$key];
        }
        if (!empty($nomes)) return 'Toda ' . implode(', ', $nomes);
    }

    return "Todo {$dia_semana}";
}

$share_url = urlencode(get_permalink($comunidade_id));
$share_text = urlencode('Confira este local: ' . $nome);


function cc_contato_label($tipo) {
    $map = [
        'telefone' => 'Telefone',
        'whatsapp' => 'Whatsapp',
        'instagram' => 'Instagram',
        'facebook' => 'Facebook',
        'youtube' => 'Youtube',
        'site' => 'Site',
        'email' => 'Email',
    ];

    $key = sanitize_key((string) $tipo);
    return $map[$key] ?? ucfirst((string) $tipo ?: 'Contato');
}

function cc_contato_link($tipo, $valor) {
    $tipo = sanitize_key((string) $tipo);
    $valor = trim((string) $valor);

    if ($valor === '') return '';

    if ($tipo === 'email') {
        return 'mailto:' . sanitize_email($valor);
    }

    if ($tipo === 'telefone' || $tipo === 'whatsapp') {
        $digits = preg_replace('/[^0-9]/', '', $valor);
        if ($digits === '') return '';
        return $tipo === 'whatsapp' ? 'https://wa.me/' . $digits : 'tel:' . $digits;
    }

    if (preg_match('#^https?://#i', $valor)) {
        return $valor;
    }

    if (in_array($tipo, ['instagram', 'facebook', 'youtube', 'site'], true)) {
        return 'https://' . ltrim($valor, '/');
    }

    return '';
}

get_header();
?>
<main class="bg-slate-50 min-h-screen py-10">
    <article class="max-w-5xl mx-auto px-4 space-y-8">
        <header class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 lg:p-8 space-y-4">
            <p class="text-sm text-slate-500">Local</p>
            <h1 class="text-3xl font-bold text-slate-900"><?php echo esc_html($nome); ?></h1>

            <?php if (!empty($tipos)): ?>
                <p class="text-sm text-sky-700 font-medium"><?php echo esc_html(implode(' • ', $tipos)); ?></p>
            <?php endif; ?>

            <?php if ($endereco): ?>
                <p class="text-slate-700"><i class="bi bi-geo-alt"></i> <a class="text-sky-700 hover:underline" href="https://www.google.com/maps/search/?api=1&query=<?php echo rawurlencode($endereco); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($endereco); ?></a></p>
            <?php endif; ?>

            <div class="flex flex-wrap gap-3 pt-2">
                <a class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-green-600 text-white hover:bg-green-700" href="https://wa.me/?text=<?php echo $share_text . '%20' . $share_url; ?>" target="_blank" rel="noopener noreferrer"><i class="bi bi-whatsapp"></i> Compartilhar no WhatsApp</a>
                <a class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-blue-600 text-white hover:bg-blue-700" href="https://www.facebook.com/sharer/sharer.php?u=<?php echo $share_url; ?>" target="_blank" rel="noopener noreferrer"><i class="bi bi-facebook"></i> Compartilhar no Facebook</a>
                <a class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-slate-300 bg-white text-slate-700 hover:bg-slate-100" href="mailto:?subject=<?php echo rawurlencode($nome); ?>&body=<?php echo $share_text . '%20' . $share_url; ?>"><i class="bi bi-envelope"></i> Compartilhar por e-mail</a>
            </div>
        </header>

        <section class="grid lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 space-y-6">
                <?php if ($imagem): ?>
                    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-4">
                        <img class="w-full rounded-xl object-cover max-h-[440px]" src="<?php echo esc_url($imagem); ?>" alt="<?php echo esc_attr($nome); ?>">
                    </div>
                <?php endif; ?>

                <?php if (!empty(trim(wp_strip_all_tags((string) $conteudo)))): ?>
                    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 prose max-w-none">
                        <h2>Sobre o local</h2>
                        <?php echo $conteudo; ?>
                    </div>
                <?php endif; ?>

                <?php if ($paroquia_id && $paroquia_link): ?>
                    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
                        <p class="text-slate-700"><strong>Pertence a Paróquia:</strong> <a href="<?php echo esc_url($paroquia_link); ?>" class="text-sky-700 hover:underline"><?php echo esc_html($paroquia_nome ?: 'Paróquia'); ?></a></p>
                    </div>
                <?php endif; ?>

                <?php if (!empty($comunidades_atreladas)): ?>
                    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 space-y-3">
                        <h2 class="text-2xl font-semibold text-slate-900">Comunidades vinculadas</h2>
                        <ul class="list-disc pl-5 space-y-1">
                            <?php foreach ($comunidades_atreladas as $comunidade_filha): ?>
                                <li><a href="<?php echo esc_url(get_permalink($comunidade_filha->ID)); ?>" class="text-sky-700 hover:underline"><?php echo esc_html($comunidade_filha->post_title); ?></a></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 space-y-4">
                    <h2 class="text-2xl font-semibold text-slate-900">Atividades do local</h2>

                    <?php if (empty($eventos)): ?>
                        <p class="text-slate-600">Nenhuma atividade cadastrada.</p>
                    <?php else: ?>
                        <ul class="space-y-3">
                            <?php foreach ($eventos as $evento): ?>
                                <li class="border border-slate-200 bg-slate-50 rounded-xl p-4 space-y-2">
                                    <h3 class="text-lg font-semibold text-slate-900"><?php echo esc_html($evento['titulo'] ?: 'Evento'); ?></h3>
                                    <p class="text-sm text-slate-600"><?php echo esc_html(cc_formatar_recorrencia_single($evento)); ?> • <?php echo esc_html($evento['horario'] ?: 'Horário não informado'); ?></p>
                                    <?php if (!empty($evento['tipos_evento'])): ?>
                                        <p class="text-xs font-medium text-sky-700"><?php echo esc_html(implode(' • ', $evento['tipos_evento'])); ?></p>
                                    <?php endif; ?>
                                    <?php if (!empty($evento['descricao'])): ?>
                                        <p class="text-slate-700"><?php echo esc_html($evento['descricao']); ?></p>
                                    <?php endif; ?>
                                    <?php if (!empty($evento['observacao'])): ?>
                                        <p class="text-sm text-slate-500"><?php echo esc_html($evento['observacao']); ?></p>
                                    <?php endif; ?>
                                    <?php if (!empty($evento['tags_evento'])): ?>
                                        <p class="text-xs text-slate-500">Tags: <?php echo esc_html(implode(', ', $evento['tags_evento'])); ?></p>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>

            <aside class="space-y-6">
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 space-y-3">
                    <h2 class="text-xl font-semibold text-slate-900">Informações rápidas</h2>
                    <?php if ($endereco): ?>
                        <p class="text-sm text-slate-700"><strong>Endereço:</strong> <a class="text-sky-700 hover:underline" href="https://www.google.com/maps/search/?api=1&query=<?php echo rawurlencode($endereco); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($endereco); ?></a></p>
                    <?php endif; ?>
                </div>

                <?php if (!empty($contatos)): ?>
                    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 space-y-3">
                        <h2 class="text-xl font-semibold text-slate-900">Contatos</h2>
                        <ul class="space-y-2">
                            <?php foreach ($contatos as $contato): ?>
                                <?php $contato_tipo = $contato['tipo'] ?? 'contato'; ?>
                                <?php $contato_valor = $contato['valor'] ?? ''; ?>
                                <?php $contato_link = cc_contato_link($contato_tipo, $contato_valor); ?>
                                <li class="text-sm text-slate-700"><strong><?php echo esc_html(cc_contato_label($contato_tipo)); ?>:</strong> <?php if ($contato_link): ?><a class="text-sky-700 hover:underline break-all" href="<?php echo esc_url($contato_link); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($contato_valor); ?></a><?php else: ?><?php echo esc_html($contato_valor); ?><?php endif; ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            </aside>
        </section>
        <?php if ($latitude !== '' && $longitude !== ''): ?>
            <section class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 space-y-3">
                <h2 class="text-2xl font-semibold text-slate-900">Mapa</h2>
                <div id="single-comunidade-map" style="height: 360px; border-radius: 0.75rem;"></div>
            </section>
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    if (!window.L) return;
                    const lat = Number('<?php echo esc_js($latitude); ?>');
                    const lng = Number('<?php echo esc_js($longitude); ?>');
                    if (!Number.isFinite(lat) || !Number.isFinite(lng)) return;

                    const map = L.map('single-comunidade-map', { scrollWheelZoom: false }).setView([lat, lng], 15);
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        maxZoom: 19,
                        attribution: '&copy; OpenStreetMap contributors'
                    }).addTo(map);
                    const markers = L.markerClusterGroup();
                    markers.addLayer(L.marker([lat, lng]).bindPopup('<?php echo esc_js($nome); ?>'));
                    map.addLayer(markers);
                    markers.eachLayer(function (marker) { marker.openPopup(); });
                });
            </script>
        <?php endif; ?>
    </article>
</main>
<?php get_footer(); ?>
