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
<main class="cc-author-page">
    <section class="cc-author-container">
        <header class="cc-author-header">
            <p class="cc-author-eyebrow">Autor</p>
            <h1 class="cc-author-title">Locais cadastrados por <?php echo esc_html(get_the_author_meta('display_name', $author_id)); ?></h1>
        </header>

        <?php if (!$q->have_posts()): ?>
            <div class="cc-author-empty">
                <p>Nenhum local cadastrado por este autor.</p>
            </div>
        <?php else: ?>
            <div class="cc-author-grid">
                <?php while ($q->have_posts()): $q->the_post(); ?>
                    <?php echo cc_render_comunidade_card(get_the_ID()); ?>
                <?php endwhile; ?>
            </div>
            <div class="cc-author-pagination">
                <?php echo paginate_links(['total' => $q->max_num_pages, 'current' => $paged, 'mid_size' => 3, 'end_size' => 0, 'prev_next' => true]); ?>
            </div>
        <?php endif; ?>
        <?php wp_reset_postdata(); ?>
    </section>
</main>
<?php get_footer(); ?>
