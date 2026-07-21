<?php
if (!defined('ABSPATH')) exit;

$term = get_queried_object();
$comunidade_ids = $term instanceof WP_Term ? cc_get_comunidade_ids_by_taxonomy('tipo_comunidade', $term->term_id) : [];

get_header();
?>
<main class="bg-slate-50 min-h-screen py-10">
    <section class="max-w-6xl mx-auto px-4 space-y-8">
        <header class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 lg:p-8 space-y-3">
            <p class="text-sm text-slate-500">Tipo de comunidade</p>
            <h1 class="text-3xl font-bold text-slate-900">Locais do tipo <?php echo esc_html($term->name ?? 'selecionado'); ?></h1>
            <?php if (!empty($term->description)): ?>
                <p class="text-slate-600"><?php echo esc_html($term->description); ?></p>
            <?php endif; ?>
        </header>

        <?php if (empty($comunidade_ids)): ?>
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
                <p class="text-slate-600">Nenhum local publicado possui este tipo de comunidade.</p>
            </div>
        <?php else: ?>
            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach ($comunidade_ids as $comunidade_id): ?>
                    <?php echo cc_render_comunidade_card($comunidade_id); ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</main>
<?php get_footer(); ?>
