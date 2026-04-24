<?php
/**
 * Custom Checkout Template for Politeia
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('WC')) {
    wp_die(esc_html__('WooCommerce es necesario para ver esta página.', 'politeia-learning'));
}

$cart = WC()->cart;
if (!$cart) {
    wp_die(esc_html__('El carrito no está disponible.', 'politeia-learning'));
}

$checkout = WC()->checkout();
if (!$checkout) {
    wp_die(esc_html__('La página de pago no está disponible.', 'politeia-learning'));
}

$checkout_url = wc_get_checkout_url();

// Remove item from cart while staying on checkout.
if (
    isset($_GET['pl_remove_item'], $_GET['pl_remove_nonce'])
    && is_string($_GET['pl_remove_item'])
    && is_string($_GET['pl_remove_nonce'])
) {
    $remove_key = (string) wp_unslash($_GET['pl_remove_item']);
    $remove_nonce = (string) wp_unslash($_GET['pl_remove_nonce']);

    if (wp_verify_nonce($remove_nonce, 'pl_remove_item') && $remove_key !== '') {
        $cart->remove_cart_item($remove_key);
        $cart->calculate_totals();
    }

    wp_safe_redirect(remove_query_arg(array('pl_remove_item', 'pl_remove_nonce'), $checkout_url));
    exit;
}

$items = $cart->get_cart();
$has_physical = false;
foreach ($items as $cart_item) {
    $product = $cart_item['data'] ?? null;
    if ($product && is_object($product) && method_exists($product, 'needs_shipping') && $product->needs_shipping()) {
        $has_physical = true;
        break;
    }
}
$remove_nonce = wp_create_nonce('pl_remove_item');
$shop_url = function_exists('wc_get_page_permalink') ? (string) wc_get_page_permalink('shop') : (string) home_url('/shop/');

pl_template_open();
?>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&icon_names=private_connectivity&display=block" />
	<style>
	    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=Poppins:wght@400;600;700&display=swap');
			    html, body { height: 100%; }
			    #pl-checkout-body {font-family:'Inter',sans-serif;background-color:#f9f9f9 !important; padding-top: 20px !important; min-height: 100vh;}
	    #politeia-checkout-container{max-width: var(--wp--style--global--wide-size);padding-bottom: 40px;}
	    #politeia-checkout-title{font-size:28px;letter-spacing:1px;margin-bottom:0px}
    #politeia-checkout-place-order{padding:10px;border-radius:6px}
    div#politeia-checkout-order-col {
        border: none !important;
        border-radius: 6px !important;
        background: white !important;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05) !important;
    }
    @media (min-width: 992px){
        .checkout-container{display:flex;gap:60px;align-items:flex-start}
        .billing-col{flex:1 1 50%}
        .order-col{flex:1 1 50%;position:sticky;top:40px}
    }
	    .form-input, .woocommerce-input-wrapper input, .woocommerce-input-wrapper select, .woocommerce-input-wrapper textarea {
	        width: 100%;
	        padding: 8px 12px;
	        border: 1px solid #e5e7eb;
	        border-radius: 3px;
	        outline: none;
	        font-size: 22px;
	        background-color: #fff !important;
	    }
	    .label-text{font-size:.75rem;text-transform:uppercase;letter-spacing:.05em;font-weight:700;color:#000}
	    #politeia-checkout-total-row .woocommerce-Price-amount{font-size:22px; font-weight: 800;}
	    span.woocommerce-Price-amount.amount { font-size: 22px; }
	    body.woocommerce-checkout .wp-block-woocommerce-mini-cart,
	    body.woocommerce-checkout .wc-block-mini-cart,
	    body.woocommerce-checkout .widget_shopping_cart,
	    body.woocommerce-checkout .widget_shopping_cart_content,
	    body.woocommerce-checkout .wp-block-woocommerce-customer-account__cart,
	    body.woocommerce-checkout .wp-block-woocommerce-customer-account__cart-link {
	        display: none !important;
	    }
	    /* Hide site footer on checkout to keep focus and avoid layout gaps */
	    body.woocommerce-checkout footer,
	    body.woocommerce-checkout #colophon,
	    body.woocommerce-checkout .wp-block-template-part[aria-label="Footer"],
	    body.woocommerce-checkout .wp-block-template-part.wp-block-template-part--footer,
	    body.woocommerce-checkout .wp-site-blocks > footer {
	        display: none !important;
	    }
	    .wp-block-woocommerce-filled-mini-cart-contents-block,
	    .wp-block-woocommerce-filled-mini-cart-contents-block * {
	        font-family: 'Poppins', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif !important;
	    }
	    .pl-checkout-item {
	        display: flex;
	        align-items: center;
	        gap: 12px;
	    }
	    .pl-checkout-item__thumb {
	        width: 44px;
	        height: 44px;
	        border-radius: 8px;
	        object-fit: cover;
	        flex: 0 0 auto;
	        border: 1px solid rgba(17, 24, 39, 0.08);
	        background: #fff;
	    }
	    .pl-checkout-item__meta {
	        display: grid;
	        gap: 2px;
	        line-height: 1.2;
	    }
	    .pl-checkout-item__type {
	        font-size: 10px;
	        font-weight: 800;
	        letter-spacing: 0.14em;
	        text-transform: uppercase;
	        color: rgba(17, 24, 39, 0.45);
	    }
	    .pl-checkout-item__name {
	        font-size: 12px;
	        font-weight: 700;
	        letter-spacing: 0.08em;
	        text-transform: uppercase;
	        color: rgba(17, 24, 39, 0.9);
	    }
	    button.w-full.bg-black.hover\:bg-gray-800.text-white.font-bold.py-5.px-4.uppercase.tracking-widest.text-sm.rounded-md {
	        padding: 14px;
	    }
	</style>

<div id="pl-checkout-body" class="antialiased py-12 px-4">
    <div id="politeia-checkout-container" class="mx-auto">
        <header id="politeia-checkout-header" class="border-b flex justify-between items-end mb-8 pb-4">
            <h1 id="politeia-checkout-title" class="text-3xl font-bold text-gray-900 mb-2"><?php echo esc_html__('Finalizar compra', 'politeia-learning'); ?></h1>
            <div id="politeia-checkout-secure" class="text-[10px] uppercase tracking-widest text-gray-500">
                <span class="material-symbols-outlined align-middle mr-1 text-[22px] leading-none">private_connectivity</span>
                <?php echo esc_html__('Transacción segura', 'politeia-learning'); ?>
            </div>
        </header>

        <?php if ($cart->is_empty()) : ?>
            <div class="border border-black p-8 bg-white"><?php echo esc_html__('Tu carrito está vacío.', 'politeia-learning'); ?></div>
        <?php else : ?>
            <?php
            remove_action('woocommerce_before_checkout_form', 'woocommerce_checkout_login_form', 10);
            remove_action('woocommerce_before_checkout_form', 'woocommerce_checkout_coupon_form', 10);
            do_action('woocommerce_before_checkout_form', $checkout);
            ?>

            <form id="politeia-checkout-form" name="checkout" method="post" class="checkout woocommerce-checkout checkout-container" action="<?php echo esc_url(wc_get_checkout_url()); ?>" enctype="multipart/form-data">
                <div class="billing-col">
                    <div id="politeia-checkout-identity" class="mb-12">
                        <h2 class="text-lg font-bold mb-8 uppercase tracking-widest border-b border-gray-100 pb-4">1. <?php echo esc_html__('Identificación', 'politeia-learning'); ?></h2>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                            <div>
                                <label class="label-text" for="billing_first_name"><?php echo esc_html__('Nombre *', 'politeia-learning'); ?></label>
                                <input id="billing_first_name" name="billing_first_name" type="text" class="form-input" required value="<?php echo esc_attr($checkout->get_value('billing_first_name')); ?>">
                            </div>
                            <div>
                                <label class="label-text" for="billing_last_name"><?php echo esc_html__('Apellidos *', 'politeia-learning'); ?></label>
                                <input id="billing_last_name" name="billing_last_name" type="text" class="form-input" required value="<?php echo esc_attr($checkout->get_value('billing_last_name')); ?>">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                            <div>
                                <label class="label-text" for="billing_email"><?php echo esc_html__('Email *', 'politeia-learning'); ?></label>
                                <input id="billing_email" name="billing_email" type="email" class="form-input" required value="<?php echo esc_attr($checkout->get_value('billing_email')); ?>">
                            </div>
                            <div>
                                <label class="label-text" for="billing_phone"><?php echo esc_html__('Teléfono *', 'politeia-learning'); ?></label>
                                <input id="billing_phone" name="billing_phone" type="tel" class="form-input" required value="<?php echo esc_attr($checkout->get_value('billing_phone')); ?>">
                            </div>
                        </div>

                        <?php if ($has_physical) : ?>
                            <div class="mb-6">
                                <label class="label-text" for="billing_address_1"><?php echo esc_html__('Calle y Número *', 'politeia-learning'); ?></label>
                                <input id="billing_address_1" name="billing_address_1" type="text" class="form-input" required value="<?php echo esc_attr($checkout->get_value('billing_address_1')); ?>">
                            </div>
                            <div class="mb-6">
                                <label class="label-text" for="billing_city"><?php echo esc_html__('Ciudad / Comuna *', 'politeia-learning'); ?></label>
                                <input id="billing_city" name="billing_city" type="text" class="form-input" required value="<?php echo esc_attr($checkout->get_value('billing_city')); ?>">
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php do_action('woocommerce_checkout_after_customer_details'); ?>
                </div>

                <div id="politeia-checkout-order-col" class="order-col p-8">
                    <h2 class="text-lg font-bold uppercase tracking-widest mb-8"><?php echo esc_html__('Resumen de Pedido', 'politeia-learning'); ?></h2>
                    <table class="w-full mb-8">
                        <tbody>
	                            <?php foreach ($items as $cart_item_key => $cart_item) : ?>
	                                <tr class="border-b border-gray-100">
	                                    <td class="py-4 text-sm font-medium uppercase">
	                                        <?php
	                                        $product = $cart_item['data'] ?? null;
	                                        $thumb_html = '';
	                                        if ($product && is_object($product) && method_exists($product, 'get_image_id')) {
	                                            $image_id = (int) $product->get_image_id();
	                                            if ($image_id > 0) {
	                                                $thumb_html = wp_get_attachment_image($image_id, [44, 44], false, ['class' => 'pl-checkout-item__thumb', 'loading' => 'lazy']);
	                                            } elseif (function_exists('wc_placeholder_img')) {
	                                                $thumb_html = wc_placeholder_img([44, 44]);
	                                                $thumb_html = str_replace('class="', 'class="pl-checkout-item__thumb ', (string) $thumb_html);
	                                            }
	                                        }
	                                        ?>
	                                        <?php
	                                        $type_label = '';
	                                        if ($product && is_object($product) && method_exists($product, 'get_id')) {
	                                            $pid = (int) $product->get_id();
	                                            if ($pid > 0 && function_exists('wp_get_post_terms')) {
	                                                $slugs = wp_get_post_terms($pid, 'product_cat', ['fields' => 'slugs']);
	                                                $names = wp_get_post_terms($pid, 'product_cat', ['fields' => 'names']);
	                                                $slugs = is_array($slugs) ? array_map('strtolower', array_map('sanitize_title', $slugs)) : [];
	                                                $names = is_array($names) ? array_map('strtolower', array_map('sanitize_text_field', $names)) : [];
	                                                $all = array_unique(array_filter(array_merge($slugs, $names)));
	                                                if (in_array('libro', $all, true) || in_array('libros', $all, true)) {
	                                                    $type_label = 'LIBRO';
	                                                } elseif (in_array('curso', $all, true) || in_array('cursos', $all, true)) {
	                                                    $type_label = 'CURSO';
	                                                }
	                                            }
	                                        }
	                                        $product_name = $product && is_object($product) && method_exists($product, 'get_name') ? (string) $product->get_name() : '';
	                                        $qty = (int) ($cart_item['quantity'] ?? 1);
	                                        ?>
	                                        <div class="pl-checkout-item">
	                                            <?php echo $thumb_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	                                            <div class="pl-checkout-item__meta">
	                                                <?php if ($type_label !== '') : ?>
	                                                    <div class="pl-checkout-item__type"><?php echo esc_html($type_label); ?></div>
	                                                <?php endif; ?>
	                                                <div class="pl-checkout-item__name"><?php echo esc_html($product_name . ' × ' . $qty); ?></div>
	                                            </div>
	                                        </div>
	                                    </td>
	                                    <td class="py-4 text-right font-bold"><?php echo wp_kses_post($cart->get_product_subtotal($cart_item['data'], $cart_item['quantity'])); ?></td>
	                                </tr>
	                            <?php endforeach; ?>
                            <tr class="border-b border-gray-100">
                                <th class="py-4 text-left uppercase text-xs font-bold text-gray-400"><?php echo esc_html__('Subtotal', 'politeia-learning'); ?></th>
                                <td class="py-4 text-right font-bold"><?php echo wp_kses_post($cart->get_cart_subtotal()); ?></td>
                            </tr>
                            <tr id="politeia-checkout-total-row">
                                <th class="py-6 text-left text-xl font-black"><?php echo esc_html__('TOTAL', 'politeia-learning'); ?></th>
                                <td class="py-6 text-right text-2xl font-black"><?php echo wp_kses_post($cart->get_total()); ?></td>
                            </tr>
                        </tbody>
                    </table>

                    <div class="payment-methods">
                        <?php
                        $available_gateways = WC()->payment_gateways()->get_available_payment_gateways();
                        if ($available_gateways) : ?>
                            <div class="space-y-4 mb-8">
                                <?php foreach ($available_gateways as $gateway) : ?>
                                    <div class="flex items-center gap-3">
                                        <input type="radio" id="payment_method_<?php echo esc_attr($gateway->id); ?>" name="payment_method" value="<?php echo esc_attr($gateway->id); ?>" <?php checked($gateway->chosen, true); ?>>
                                        <label for="payment_method_<?php echo esc_attr($gateway->id); ?>" class="text-sm font-bold uppercase cursor-pointer"><?php echo esc_html($gateway->get_title()); ?></label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <button type="submit" class="w-full bg-black hover:bg-gray-800 text-white font-bold py-5 px-4 uppercase tracking-widest text-sm rounded-md" name="woocommerce_checkout_place_order">
                            <?php echo esc_html__('Confirmar Compra', 'politeia-learning'); ?>
                        </button>
                        <?php wp_nonce_field('woocommerce-process_checkout', 'woocommerce-process-checkout-nonce'); ?>
                    </div>
                </div>
            </form>
            <?php do_action('woocommerce_after_checkout_form', $checkout); ?>
        <?php endif; ?>
    </div>
</div>
<?php
pl_template_close();
