<?php
/**
 * Custom Shop Template for Politeia
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

if (function_exists('wc_enqueue_scripts')) {
    wc_enqueue_scripts();
}

$paged = (int) max(1, (int) get_query_var('paged'), (int) get_query_var('page'));
$per_page = 12;

$products = [];
$total_pages = 1;

if (function_exists('wc_get_products')) {
    $args = [
        'status' => 'publish',
        'limit' => $per_page,
        'page' => $paged,
        'paginate' => true,
        'orderby' => 'date',
        'order' => 'DESC',
    ];

    $results = wc_get_products($args);

    if (is_object($results) && isset($results->products)) {
        $products = $results->products;
        $total = (int) $results->total;
        $total_pages = (int) max(1, (int) ceil($total / $per_page));
    }
}

pl_template_open();
?>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600&display=swap" rel="stylesheet">
<style>
    #pl-shop-body { font-family: 'Inter', sans-serif; background-color: #fff; color: #000; }
    .product-card:hover .add-to-cart { opacity: 1; transform: translateY(0); }
    .add-to-cart { transition: all 0.3s ease; }
    #politeia-shop-pagination .page-numbers { list-style: none; display: flex; gap: 10px; }
    #politeia-shop-pagination .page-numbers a, #politeia-shop-pagination .page-numbers span {
        display: inline-flex; min-width: 34px; height: 34px; border: 1px solid #e5e7eb; justify-content: center; align-items: center; font-size: 12px; font-weight: 600; text-transform: uppercase;
    }
    #politeia-shop-pagination .page-numbers .current { background: #000; color: #fff; border-color: #000; }
</style>

<div id="pl-shop-body" class="antialiased py-12 px-4">
    <main id="politeia-shop-main" class="mx-auto" style="max-width: var(--wp--style--global--wide-size);">
        <div class="flex items-end justify-between mb-12">
            <h1 class="text-3xl font-bold">Tienda</h1>
            <div class="flex items-center gap-6">
                <?php if ($total_pages > 1) : ?>
                    <div id="politeia-shop-pagination">
                        <?php echo paginate_links(['current' => $paged, 'total' => $total_pages, 'type' => 'list']); ?>
                    </div>
                <?php endif; ?>
                <a href="<?php echo esc_url(home_url('/cursos/')); ?>" class="bg-black text-white px-8 py-2 rounded-md font-bold uppercase tracking-widest text-[10px]">Ver Cursos</a>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-x-8 gap-y-16">
            <?php foreach ($products as $product) : ?>
                <?php
                $pid = $product->get_id();
                $name = $product->get_name();
                $link = $product->get_permalink();
                $image_url = wp_get_attachment_image_url($product->get_image_id(), 'large');
                $price_html = $product->get_price_html();
                ?>
                <div class="product-card group relative">
                    <a href="<?php echo esc_url($link); ?>" class="block">
                        <div class="relative aspect-square mb-4 overflow-hidden bg-gray-100 border border-transparent group-hover:border-black transition-all">
                            <?php if ($image_url) : ?>
                                <img src="<?php echo esc_url($image_url); ?>" class="w-full h-full object-cover">
                            <?php endif; ?>
                            <div class="add-to-cart absolute bottom-0 left-0 w-full bg-black text-white py-4 text-center text-[10px] font-bold uppercase tracking-widest opacity-0 transform translate-y-4">Ver producto</div>
                        </div>
                        <div class="flex justify-between items-start">
                            <h3 class="uppercase text-xs font-semibold tracking-wider"><?php echo esc_html($name); ?></h3>
                            <span class="text-sm font-bold"><?php echo wp_kses_post($price_html); ?></span>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </main>
</div>
<?php
pl_template_close();
