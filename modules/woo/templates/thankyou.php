<?php
/**
 * Custom Thankyou Template for Politeia
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

$order_id = get_query_var('order-received');
$order    = wc_get_order($order_id);

if (!$order) {
    return;
}

$status = $order->get_status();
$is_success = in_array($status, ['processing', 'completed'], true);
$is_pending = in_array($status, ['on-hold', 'pending'], true);
$order_number = $order->get_order_number();
$order_date = wc_format_datetime($order->get_date_created(), 'd/m/Y');
$order_total = $order->get_formatted_order_total();
$method_title = $order->get_payment_method_title();

$courses_data = [];
foreach ($order->get_items() as $item) {
    if (!$item instanceof WC_Order_Item_Product) continue;
    $product = $item->get_product();
    if (!$product) continue;

    $course_url = '#';
    $is_course = false;
    // Check if it's a course (LearnDash or internal logic)
    $course_id = (int) get_post_meta($item->get_product_id(), '_related_course_id', true);
    if ($course_id) {
        $is_course = true;
        $course_url = get_permalink($course_id);
    }

    $courses_data[] = [
        'title' => $item->get_name(),
        'url' => $course_url,
        'price' => wc_price((float)$item->get_total()),
        'is_course' => $is_course,
        'image' => wp_get_attachment_image_url($product->get_image_id(), 'thumbnail') ?: '',
    ];
}

pl_template_open();
?>
<script src="https://cdn.tailwindcss.com"></script>
<style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap');
    #pl-thankyou-body { font-family: 'Inter', sans-serif; background-color: #f9f9f9; }
    .order-card { box-shadow: 0 24px 48px rgba(0, 0, 0, 0.04); background: white; border: 1px solid #000; }
    .btn-black { background-color: #000; color: #fff; text-decoration: none; border-radius: 6px; }
    .btn-black:hover { background-color: #262626; color: #fff; }
</style>

<div id="pl-thankyou-body" class="antialiased py-12 px-4">
    <div class="flex flex-col items-center justify-start py-12 md:py-24" style="background-image: linear-gradient(rgba(0,0,0,0.45), rgba(0,0,0,0.45)), url('<?php echo esc_url(plugins_url('politeia-learning/modules/woo/assets/thankyou-bg.png')); ?>'); background-size: cover; background-position: center; border-radius: 12px; margin: 0 20px;">
        <div class="max-w-2xl w-full order-card p-10 fade-in">
            <div class="text-center border-b border-zinc-100 pb-8 mb-8">
                <h1 class="text-2xl font-bold uppercase tracking-widest mb-2"><?php echo $is_success ? 'Pedido Confirmado' : 'Pedido Recibido'; ?></h1>
                <p class="text-zinc-500 text-sm max-w-sm mx-auto">
                    <?php echo $is_success ? '¡Muchas gracias por tu compra! Tu acceso ya está activo.' : 'Hemos recibido tu orden. Pendiente de confirmación de pago.'; ?>
                </p>
            </div>

            <div class="mb-10">
                <h2 class="text-xs font-bold uppercase tracking-widest mb-6 opacity-40">Resumen de Compra</h2>
                <div class="space-y-4">
                    <?php foreach ($courses_data as $c) : ?>
                        <div class="flex items-center justify-between p-4 border border-zinc-100 rounded-lg">
                            <div class="flex items-center gap-4">
                                <div class="w-12 h-12 bg-zinc-50 rounded overflow-hidden">
                                    <?php if ($c['image']) : ?>
                                        <img src="<?php echo esc_url($c['image']); ?>" class="w-full h-full object-cover">
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <p class="text-sm font-bold uppercase"><?php echo esc_html($c['title']); ?></p>
                                    <p class="text-[10px] text-zinc-400"><?php echo wp_kses_post($c['price']); ?></p>
                                </div>
                            </div>
                            <?php if ($is_success && $c['url'] !== '#') : ?>
                                <a href="<?php echo esc_url($c['url']); ?>" class="btn-black px-4 py-2 text-[10px] font-bold uppercase tracking-widest">Ir al curso</a>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="border-t border-zinc-100 pt-8 grid grid-cols-2 md:grid-cols-4 gap-6 text-center">
                <div>
                    <p class="text-[10px] font-bold text-zinc-400 uppercase mb-1">ID</p>
                    <p class="text-sm font-bold">#<?php echo esc_html($order_number); ?></p>
                </div>
                <div>
                    <p class="text-[10px] font-bold text-zinc-400 uppercase mb-1">Fecha</p>
                    <p class="text-sm font-bold"><?php echo esc_html($order_date); ?></p>
                </div>
                <div>
                    <p class="text-[10px] font-bold text-zinc-400 uppercase mb-1">Total</p>
                    <p class="text-sm font-bold"><?php echo $order_total; ?></p>
                </div>
                <div>
                    <p class="text-[10px] font-bold text-zinc-400 uppercase mb-1">Método</p>
                    <p class="text-sm font-bold"><?php echo esc_html($method_title); ?></p>
                </div>
            </div>
        </div>

        <div class="mt-8 flex gap-8">
            <a href="<?php echo esc_url(home_url('/')); ?>" class="text-[10px] font-bold uppercase tracking-widest text-white hover:underline">Inicio</a>
            <button onclick="window.print()" class="text-[10px] font-bold uppercase tracking-widest text-white border-0 bg-transparent cursor-pointer">Imprimir</button>
        </div>
    </div>
</div>
<?php
pl_template_close();
