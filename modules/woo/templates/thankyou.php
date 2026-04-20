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
$method_id = $order->get_payment_method();

// Get Bank Details if pending and method is BACS
$bank_details = [];
if ($is_pending && $method_id === 'bacs' && function_exists('WC') && WC() && isset(WC()->payment_gateways) && method_exists(WC()->payment_gateways, 'get_available_payment_gateways')) {
    $gateways = WC()->payment_gateways->get_available_payment_gateways();
    if (is_array($gateways) && isset($gateways['bacs'])) {
        $bacs = $gateways['bacs'];
        $accounts = is_object($bacs) && isset($bacs->account_details) ? $bacs->account_details : null;
        if (is_array($accounts) && !empty($accounts) && is_array($accounts[0])) {
            $bank_details = $accounts[0];
        }
    }
}

$courses_data = [];
foreach ($order->get_items() as $item) {
    if (!$item instanceof WC_Order_Item_Product) continue;
    $product = $item->get_product();
    if (!$product) continue;

    $course_url = '#';
    $is_course = false;
    $course_id = 0;

    // Important: for variable products, `$product->get_id()` is the variation ID.
    // We want the parent product ID for meta/category lookups.
    $parent_product_id = (int) $item->get_product_id();
    $variation_product_id = (int) $item->get_variation_id();
    $product_ids_for_meta = array_values(array_unique(array_filter([$variation_product_id, $parent_product_id])));

    foreach ($product_ids_for_meta as $pid_for_meta) {
        $cid = (int) get_post_meta($pid_for_meta, '_learni_course_id', true);
        if ($cid <= 0) {
            $cid = (int) get_post_meta($pid_for_meta, '_related_course_id', true);
        }
        if ($cid > 0 && get_post_type($cid) === 'learni_course') {
            $course_id = $cid;
            break;
        }

        $fallback = get_post_meta($pid_for_meta, '_related_course', true);
        if (is_array($fallback)) {
            foreach ($fallback as $maybe_id) {
                $maybe_id = absint($maybe_id);
                if ($maybe_id > 0 && get_post_type($maybe_id) === 'learni_course') {
                    $course_id = $maybe_id;
                    break 2;
                }
            }
        }
    }

    if ($course_id > 0) {
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

                <?php if ($is_pending && $method_id === 'bacs' && !empty($bank_details)) : ?>
                    <div class="mb-10">
                        <h2 class="text-xs font-bold uppercase tracking-widest mb-6 opacity-40">Datos para la transferencia</h2>
                        <div class="p-4 border border-zinc-100 rounded-lg bg-zinc-50 text-sm text-zinc-700 leading-relaxed">
                            <?php if (!empty($bank_details['account_name'])) : ?>
                                <div><strong>Nombre:</strong> <?php echo esc_html((string) $bank_details['account_name']); ?></div>
                            <?php endif; ?>
                            <?php if (!empty($bank_details['bank_name'])) : ?>
                                <div><strong>Banco:</strong> <?php echo esc_html((string) $bank_details['bank_name']); ?></div>
                            <?php endif; ?>
                            <?php if (!empty($bank_details['account_number'])) : ?>
                                <div><strong>Cuenta:</strong> <?php echo esc_html((string) $bank_details['account_number']); ?></div>
                            <?php endif; ?>
                            <?php if (!empty($bank_details['sort_code'])) : ?>
                                <div><strong>Sort code:</strong> <?php echo esc_html((string) $bank_details['sort_code']); ?></div>
                            <?php endif; ?>
                            <?php if (!empty($bank_details['iban'])) : ?>
                                <div><strong>IBAN:</strong> <?php echo esc_html((string) $bank_details['iban']); ?></div>
                            <?php endif; ?>
                            <?php if (!empty($bank_details['bic'])) : ?>
                                <div><strong>BIC:</strong> <?php echo esc_html((string) $bank_details['bic']); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
	
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
	                            <?php if ($is_success && $c['url'] !== '#' && !empty($c['is_course'])) : ?>
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
