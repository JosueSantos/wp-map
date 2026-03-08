<?php
if (!defined('ABSPATH')) exit;

global $post;

if (!$post || $post->post_type !== 'comunidade') {
    get_header();
    echo '<main class="max-w-4xl mx-auto px-4 py-10"><p>Comunidade não encontrada.</p></main>';
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
$share_text = urlencode('Confira esta comunidade: ' . $nome);

get_header();
?>
<main class="bg-slate-50 min-h-screen py-10">
    <article class="max-w-5xl mx-auto px-4 space-y-8">
        <header class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 lg:p-8 space-y-4">
            <p class="text-sm text-slate-500">Comunidade</p>
            <h1 class="text-3xl font-bold text-slate-900"><?php echo esc_html($nome); ?></h1>

            <?php if (!empty($tipos)): ?>
                <p class="text-sm text-sky-700 font-medium"><?php echo esc_html(implode(' • ', $tipos)); ?></p>
            <?php endif; ?>

            <?php if ($endereco): ?>
                <p class="text-slate-700"><i class="bi bi-geo-alt"></i> <?php echo esc_html($endereco); ?></p>
            <?php endif; ?>

            <div class="flex flex-wrap gap-3 pt-2">
                <a class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-sky-600 text-white hover:bg-sky-700" href="https://wa.me/?text=<?php echo $share_text . '%20' . $share_url; ?>" target="_blank" rel="noopener noreferrer"><i class="bi bi-whatsapp"></i> Compartilhar no WhatsApp</a>
                <a class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-slate-800 text-white hover:bg-slate-900" href="https://www.facebook.com/sharer/sharer.php?u=<?php echo $share_url; ?>" target="_blank" rel="noopener noreferrer"><i class="bi bi-facebook"></i> Compartilhar no Facebook</a>
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

                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 prose max-w-none">
                    <h2>Sobre a comunidade</h2>
                    <?php echo $conteudo ?: '<p>Sem descrição cadastrada.</p>'; ?>
                </div>

                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 space-y-4">
                    <h2 class="text-2xl font-semibold text-slate-900">Eventos da comunidade</h2>

                    <?php if (empty($eventos)): ?>
                        <p class="text-slate-600">Nenhum evento cadastrado.</p>
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
                    <?php if ($latitude !== '' && $longitude !== ''): ?>
                        <p class="text-sm text-slate-700 break-all"><strong>Coordenadas:</strong> <?php echo esc_html($latitude . ', ' . $longitude); ?></p>
                    <?php endif; ?>
                    <?php if ($endereco): ?>
                        <p class="text-sm text-slate-700"><strong>Endereço:</strong> <?php echo esc_html($endereco); ?></p>
                    <?php endif; ?>
                </div>

                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 space-y-3">
                    <h2 class="text-xl font-semibold text-slate-900">Contatos</h2>
                    <?php if (empty($contatos)): ?>
                        <p class="text-sm text-slate-600">Sem contatos cadastrados.</p>
                    <?php else: ?>
                        <ul class="space-y-2">
                            <?php foreach ($contatos as $contato): ?>
                                <li class="text-sm text-slate-700"><strong><?php echo esc_html($contato['tipo'] ?? 'Contato'); ?>:</strong> <?php echo esc_html($contato['valor'] ?? ''); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </aside>
        </section>
    </article>
</main>
<?php get_footer(); ?>
