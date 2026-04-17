<?php
/**
 * Custom Single Product Template for Politeia
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

global $product;
if (!is_a($product, 'WC_Product')) {
    $product = wc_get_product(get_the_ID());
}

if (!$product) {
    return;
}

$product_id = $product->get_id();
$title = $product->get_name();
$price_html = $product->get_price_html();
$description = $product->get_short_description();
if (empty($description)) {
    $description = $product->get_description();
}
$description = wp_strip_all_tags($description);

$image_url = wp_get_attachment_image_url($product->get_image_id(), 'full');
if (!$image_url) {
    $image_url = function_exists('wc_placeholder_img_src') ? wc_placeholder_img_src('full') : '';
}

$terms = get_the_terms($product_id, 'product_cat');
$primary_cat = ($terms && !is_wp_error($terms)) ? $terms[0]->name : '';

$author_id = (int) get_post_field('post_author', $product_id);
if ($author_id <= 1) { // Fallback to course author if linked
    $linked_course_id = (int) get_post_meta($product_id, '_related_course_id', true) ?: (int) get_post_meta($product_id, '_related_course', true);
    if ($linked_course_id) {
        $author_id = (int) get_post_field('post_author', $linked_course_id);
    }
}
$author_name = $author_id ? get_the_author_meta('display_name', $author_id) : '';

pl_template_open();
?>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap');
    #pl-product-body { font-family: 'Inter', sans-serif; background-color: #fff; color: #1a1a1a; }
    .product-image-container { aspect-ratio: 1/1; background-color: #f9f9f9; overflow: hidden; border-radius: 6px; }
    .quantity-input::-webkit-outer-spin-button, .quantity-input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
    .btn-primary { transition: all 0.3s ease; border-radius: 6px; }
    .btn-primary:hover { background-color: #333; }
</style>

<div id="pl-product-body" class="antialiased py-12 px-4">
    <main id="politeia-product-page" class="mx-auto" style="max-width: var(--wp--style--global--wide-size);">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-12 lg:gap-16">
            <!-- Image -->
            <div>
                <div class="product-image-container relative">
                    <img id="mainImage" src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr($title); ?>" class="w-full h-full object-cover">
                </div>
            </div>

            <!-- Info -->
            <div class="flex flex-col text-left">
                <nav class="flex text-[10px] text-gray-400 mb-4 uppercase tracking-widest font-medium">
                    <a href="<?php echo esc_url(home_url('/')); ?>" class="hover:text-black no-underline transition-colors">Inicio</a>
                    <?php if ($primary_cat) : ?>
                        <span class="mx-2">/</span>
                        <span class="text-gray-900"><?php echo esc_html($primary_cat); ?></span>
                    <?php endif; ?>
                </nav>

                <h1 class="text-2xl md:text-3xl font-medium mb-2 leading-tight"><?php echo esc_html($title); ?></h1>
                
                <?php if ($author_name) : ?>
                    <div class="flex items-center gap-2 mb-6 text-sm text-gray-500">
                        <span>Por</span>
                        <span class="font-medium text-black"><?php echo esc_html($author_name); ?></span>
                    </div>
                <?php endif; ?>

                <div class="text-xl font-light text-gray-900 mb-6"><?php echo $price_html; ?></div>

                <p class="text-gray-500 leading-snug mb-8 text-[20px]"><?php echo esc_html($description); ?></p>

                <div class="flex items-center gap-3 mb-8">
                    <div class="flex border border-gray-200 rounded-md px-1 items-center bg-gray-50 h-12">
                        <button onclick="stepQty(-1)" class="p-2 text-gray-400 hover:text-black transition-colors bg-transparent border-0 cursor-pointer"><i class="fa-solid fa-minus text-[10px]"></i></button>
                        <input type="number" id="quantity" value="1" min="1" class="quantity-input bg-transparent w-10 text-center border-none focus:ring-0 text-xs font-medium">
                        <button onclick="stepQty(1)" class="p-2 text-gray-400 hover:text-black transition-colors bg-transparent border-0 cursor-pointer"><i class="fa-solid fa-plus text-[10px]"></i></button>
                    </div>
                    
                    <form action="<?php echo esc_url($product->get_permalink()); ?>" method="post" enctype='multipart/form-data' class="flex-grow flex gap-3 m-0">
                        <input type="hidden" name="add-to-cart" value="<?php echo esc_attr((string) $product_id); ?>" />
                        <input type="hidden" id="real-quantity" name="quantity" value="1" />
                        <button type="submit" class="btn-primary flex-grow bg-black text-white py-3 px-6 text-[10px] font-bold tracking-widest uppercase border-0 cursor-pointer h-12">
                            AÑADIR AL CARRITO
                        </button>
                    </form>
                </div>

                <div class="py-6 border-t border-gray-100 flex items-center gap-3 text-xs text-gray-600 font-medium">
                    <i class="fa-solid fa-truck-fast text-black"></i>
                    <span>Despachos a todo Chile</span>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
    function stepQty(val) {
        const input = document.getElementById('quantity');
        const realInput = document.getElementById('real-quantity');
        let current = parseInt(input.value);
        if (current + val >= 1) {
            input.value = current + val;
            if (realInput) realInput.value = input.value;
        }
    }
</script>
<?php
pl_template_close();
