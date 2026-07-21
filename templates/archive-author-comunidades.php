<?php
if (!defined('ABSPATH')) exit;

$author = get_queried_object();
$author_id = $author && isset($author->ID) ? (int) $author->ID : (int) get_query_var('author');
$paged = max(1, (int) get_query_var('paged'));
$q = new WP_Query([
    'post_type' => 'comunidade',
    'post_status' => 'publish',
    'author' => $author_id,
    'posts_per_page' => 12,
    'paged' => $paged,
]);

get_header();
?>
<main class="bg-slate-50 min-h-screen py-10">
    <section class="max-w-6xl mx-auto px-4 space-y-8">
        <header class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 lg:p-8 space-y-3">
            <p class="text-sm text-slate-500">Autor</p>
            <h1 class="text-3xl font-bold text-slate-900">Locais cadastrados por <?php echo esc_html(get_the_author_meta('display_name', $author_id)); ?></h1>
        </header>

        <?php if (!$q->have_posts()): ?>
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
                <p class="text-slate-600">Nenhum local cadastrado por este autor.</p>
            </div>
        <?php else: ?>
            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php while ($q->have_posts()): $q->the_post(); ?>
                    <?php echo cc_render_comunidade_card(get_the_ID()); ?>
                <?php endwhile; ?>
            </div>
            <div class="text-center">
                <?php echo paginate_links(['total' => $q->max_num_pages, 'current' => $paged]); ?>
            </div>
        <?php endif; ?>
        <?php wp_reset_postdata(); ?>
    </section>
</main>
<?php get_footer(); ?>
