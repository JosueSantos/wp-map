<?php
if (!defined('ABSPATH')) exit;

$evento_id = get_queried_object_id();
$evento = get_post($evento_id);

if (!$evento || $evento->post_type !== 'evento') {
    get_header();
    echo '<main class="bg-slate-50 min-h-screen py-10"><div class="max-w-5xl mx-auto px-4"><p>Evento não encontrado.</p></div></main>';
    get_footer();
    return;
}

$comunidade_id = (int) get_post_meta($evento_id, 'comunidade_id', true);
$descricao = get_post_meta($evento_id, 'descricao', true);
$observacao = get_post_meta($evento_id, 'observacao', true);
$frequencia = get_post_meta($evento_id, 'frequencia', true) ?: 'semanal';
$dia_semana = get_post_meta($evento_id, 'dia_semana', true);
$horario = get_post_meta($evento_id, 'horario', true);
$horario_inicio = get_post_meta($evento_id, 'horario_inicio', true);
$horario_fim = get_post_meta($evento_id, 'horario_fim', true);
$dia_mes = get_post_meta($evento_id, 'dia_mes', true);
$numero_semana = get_post_meta($evento_id, 'numero_semana', true);
$mes = get_post_meta($evento_id, 'mes', true);
$tipos = wp_get_post_terms($evento_id, 'tipo_evento', ['fields' => 'names']);
$tags = wp_get_post_terms($evento_id, 'tags_evento', ['fields' => 'names']);
$horario_exibicao = trim((string) $horario_inicio);

if ($horario_fim) {
    $horario_exibicao .= $horario_exibicao ? ' - ' . $horario_fim : $horario_fim;
}

if (!$horario_exibicao) {
    $horario_exibicao = $horario ?: 'Horário não informado';
}

$dia_map = [
    '0' => 'Domingo',
    '1' => 'Segunda',
    '2' => 'Terça',
    '3' => 'Quarta',
    '4' => 'Quinta',
    '5' => 'Sexta',
    '6' => 'Sábado',
    'domingo' => 'Domingo',
    'segunda' => 'Segunda',
    'terca' => 'Terça',
    'terça' => 'Terça',
    'quarta' => 'Quarta',
    'quinta' => 'Quinta',
    'sexta' => 'Sexta',
    'sabado' => 'Sábado',
    'sábado' => 'Sábado',
];

$mes_map = [
    '1' => 'janeiro',
    '2' => 'fevereiro',
    '3' => 'março',
    '4' => 'abril',
    '5' => 'maio',
    '6' => 'junho',
    '7' => 'julho',
    '8' => 'agosto',
    '9' => 'setembro',
    '10' => 'outubro',
    '11' => 'novembro',
    '12' => 'dezembro',
];

function cc_evento_nome_dia_single_evento($dia, $dia_map) {
    $chave = strtolower(trim((string) $dia));
    return $dia_map[$chave] ?? $dia_map[(string) $dia] ?? '';
}

function cc_evento_horario_frase_single_evento($horario) {
    $horario = trim((string) $horario);
    return $horario !== '' && $horario !== 'Horário não informado' ? ' às ' . $horario : '';
}

function cc_evento_formatar_quando_single_evento($frequencia, $dia_semana, $dia_mes, $numero_semana, $mes, $horario_exibicao, $dia_map, $mes_map) {
    $frequencia = (string) $frequencia;
    $dia_nome = cc_evento_nome_dia_single_evento($dia_semana, $dia_map);
    $horario_frase = cc_evento_horario_frase_single_evento($horario_exibicao);

    if ($frequencia === 'missa_dominical') {
        return 'Domingo' . ($horario_frase ?: '');
    }

    if ($frequencia === 'semanal') {
        return ($dia_nome ?: 'Dia da semana não informado') . ($horario_frase ?: '');
    }

    if ($frequencia === 'mensal') {
        return $dia_mes ? 'Todo dia ' . $dia_mes . ' do mês' . ($horario_frase ?: '') : 'Mensal' . ($horario_frase ?: '');
    }

    if ($frequencia === 'numero_semana') {
        $semana = $numero_semana ? $numero_semana . 'ª semana' : 'Semana não informada';
        $dia = $dia_nome ? ' (' . $dia_nome . ')' : '';
        return $semana . $dia . ' do mês' . ($horario_frase ?: '');
    }

    if ($frequencia === 'anual') {
        $mes_nome = $mes_map[(string) $mes] ?? '';
        if ($dia_mes && $mes_nome) {
            return 'Todo dia ' . $dia_mes . ' de ' . $mes_nome . ($horario_frase ?: '');
        }
        return 'Anual' . ($horario_frase ?: '');
    }

    return ucfirst(str_replace('_', ' ', $frequencia)) . ($horario_frase ?: '');
}

$quando_exibicao = cc_evento_formatar_quando_single_evento($frequencia, $dia_semana, $dia_mes, $numero_semana, $mes, $horario_exibicao, $dia_map, $mes_map);

get_header();
?>
<main class="bg-slate-50 min-h-screen py-10">
    <article class="max-w-5xl mx-auto px-4 space-y-8">
        <header class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 lg:p-8 space-y-4">
            <p class="text-sm text-slate-500">Atividade</p>
            <h1 class="text-3xl font-bold text-slate-900"><?php echo esc_html(get_the_title($evento_id)); ?></h1>
            <?php if (!empty($tipos)): ?>
                <p class="text-sm text-sky-700 font-medium"><?php echo esc_html(implode(' • ', $tipos)); ?></p>
            <?php endif; ?>
        </header>

        <section class="grid lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 space-y-6">
                <section class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 space-y-4">
                    <h2 class="text-2xl font-semibold text-slate-900">Detalhes da atividade</h2>
                    <dl class="grid sm:grid-cols-2 gap-4 text-slate-700">
                        <div class="border border-slate-200 bg-slate-50 rounded-xl p-4">
                            <dt class="text-sm font-medium text-slate-500">Quando ocorre</dt>
                            <dd class="text-lg font-semibold text-slate-900"><?php echo esc_html($quando_exibicao); ?></dd>
                        </div>
                        <div class="border border-slate-200 bg-slate-50 rounded-xl p-4">
                            <dt class="text-sm font-medium text-slate-500">Horário</dt>
                            <dd class="text-lg font-semibold text-slate-900"><?php echo esc_html($horario_exibicao); ?></dd>
                        </div>
                    </dl>
                    <?php if ($descricao): ?>
                        <p class="text-slate-700"><?php echo esc_html($descricao); ?></p>
                    <?php endif; ?>
                    <?php if ($observacao): ?>
                        <p class="text-sm text-slate-500"><?php echo esc_html($observacao); ?></p>
                    <?php endif; ?>
                    <?php if (!empty($tags)): ?>
                        <p class="text-xs font-medium text-slate-500">Características: <?php echo esc_html(implode(' • ', $tags)); ?></p>
                    <?php endif; ?>
                </section>
            </div>

            <aside class="space-y-6">
                <section class="space-y-3">
                    <h2 class="text-xl font-semibold text-slate-900">Local da atividade</h2>
                    <?php echo $comunidade_id ? cc_render_comunidade_card($comunidade_id) : '<p class="text-slate-600">Local não informado.</p>'; ?>
                </section>
            </aside>
        </section>
    </article>
</main>
<?php get_footer(); ?>
