<?php
$titulo = $titulo ?? '';
$resumo = $resumo ?? '';
$endereco = $endereco ?? '';
$imagem = $imagem ?? '';
$url = $url ?? '';
?>
<article class="comunidade bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden hover:shadow-lg transition">
    <?php if ($imagem): ?>
        <a href="<?php echo esc_url($url); ?>" aria-label="<?php echo esc_attr($titulo); ?>">
            <img class="w-full h-48 object-cover" src="<?php echo esc_url($imagem); ?>" alt="<?php echo esc_attr($titulo); ?>" loading="lazy" decoding="async">
        </a>
    <?php endif; ?>
    <div class="p-5 space-y-3">
        <h3 class="text-xl font-semibold text-slate-900">
            <?php if ($url): ?>
                <a class="hover:text-sky-700 transition" href="<?php echo esc_url($url); ?>"><?php echo esc_html($titulo); ?></a>
            <?php else: ?>
                <?php echo esc_html($titulo); ?>
            <?php endif; ?>
        </h3>
        <?php if ($endereco): ?>
            <p class="text-sm text-slate-700"><i class="bi bi-geo-alt"></i> <?php echo esc_html($endereco); ?></p>
        <?php endif; ?>
        <?php if ($resumo): ?>
            <p class="text-slate-600"><?php echo esc_html(wp_trim_words(wp_strip_all_tags($resumo), 22)); ?></p>
        <?php endif; ?>
        <?php if ($url): ?>
            <a class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-sky-700 text-white hover:text-white hover:bg-sky-800 transition" href="<?php echo esc_url($url); ?>">
                Ver local
            </a>
        <?php endif; ?>
    </div>
</article>
