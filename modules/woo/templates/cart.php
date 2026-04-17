<?php
/**
 * Custom Cart Template for Politeia
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

$cart_url = wc_get_cart_url();
$checkout_url = wc_get_checkout_url();

// Remove item from cart while staying on cart.
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

    wp_safe_redirect(remove_query_arg(array('pl_remove_item', 'pl_remove_nonce'), $cart_url));
    exit;
}

// Quantity updates + coupon apply.
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['pl_cart_action'], $_POST['pl_cart_nonce'])
    && is_string($_POST['pl_cart_action'])
    && is_string($_POST['pl_cart_nonce'])
) {
    $action = (string) wp_unslash($_POST['pl_cart_action']);
    $nonce = (string) wp_unslash($_POST['pl_cart_nonce']);

    if (wp_verify_nonce($nonce, 'pl_cart_action')) {
        if ($action === 'set_qty') {
            $key = isset($_POST['pl_cart_item_key']) ? (string) wp_unslash($_POST['pl_cart_item_key']) : '';
            $qty = isset($_POST['pl_cart_qty']) ? absint((string) wp_unslash($_POST['pl_cart_qty'])) : 0;
            $qty = max(1, (int) $qty);

            if ($key !== '' && $cart->get_cart_item($key)) {
                $cart->set_quantity($key, $qty, true);
                $cart->calculate_totals();
            }
        }

        if ($action === 'apply_coupon') {
            $code = isset($_POST['coupon_code']) ? wc_format_coupon_code((string) wp_unslash($_POST['coupon_code'])) : '';
            if ($code !== '') {
                $cart->apply_coupon($code);
                $cart->calculate_totals();
            }
        }
    }

    wp_safe_redirect($cart_url);
    exit;
}

$items = $cart->get_cart();
$remove_nonce = wp_create_nonce('pl_remove_item');
$cart_nonce = wp_create_nonce('pl_cart_action');
$shop_url = function_exists('wc_get_page_permalink') ? (string) wc_get_page_permalink('shop') : (string) home_url('/shop/');
if ($shop_url === '') {
    $shop_url = (string) home_url('/shop/');
}

// Universal Header
pl_template_open();
?>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<style>
    #pl-cart-body {
        font-family: 'Inter', sans-serif;
        background-color: #f9f9f9 !important;
        color: #1a1a1a;
    }
    .cart-item:last-child {
        border-bottom: none;
    }
    input::-webkit-outer-spin-button,
    input::-webkit-inner-spin-button {
        -webkit-appearance: none;
        margin: 0;
    }
    .transition-all {
        transition: all 0.3s ease;
    }
    .tracking-tightest {
        letter-spacing: -0.05em;
    }
    div#politeia-cart-order-summary {
        border: none !important;
        border-radius: 6px !important;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05) !important;
        background: white !important;
    }
</style>

<div id="pl-cart-body" class="antialiased py-12 px-4">
    <main
        id="politeia-cart"
        class="mx-auto"
        style="max-width: var(--wp--style--global--wide-size); width: 100%;"
    >
        <h1 id="politeia-cart-title" class="text-3xl font-bold text-gray-900 mb-8"><?php echo esc_html__('Bolsa de compras', 'politeia-learning'); ?></h1>

        <?php if ($cart->is_empty()) : ?>
            <div class="bg-white p-8 rounded-sm shadow-sm border border-gray-100">
                <p class="text-sm text-gray-600"><?php echo esc_html__('Tu carrito está vacío.', 'politeia-learning'); ?></p>
                <a id="politeia-cart-empty-return-shop" href="<?php echo esc_url($shop_url); ?>" class="inline-block mt-6 bg-black text-white py-3 px-6 text-[11px] uppercase tracking-[0.3em] font-bold hover:bg-gray-800 transition-all">
                    <?php echo esc_html__('Volver a la tienda', 'politeia-learning'); ?>
                </a>
            </div>
        <?php else : ?>
            <div class="flex flex-col lg:flex-row gap-16">

                <!-- Cart Items Section -->
                <div class="lg:w-2/3">
                    <div class="border-t border-gray-200">
                        <?php foreach ($items as $cart_item_key => $cart_item) : ?>
                            <?php
                            $product = $cart_item['data'] ?? null;
                            if (!$product || !is_object($product)) {
                                continue;
                            }

                            $qty = (int) ($cart_item['quantity'] ?? 1);
                            $qty = max(1, $qty);
                            $line = $cart->get_product_subtotal($product, $qty);
                            $item_data = wc_get_formatted_cart_item_data($cart_item);
                            $row_id = 'politeia-cart-item-' . preg_replace('/[^a-zA-Z0-9_\\-]/', '', (string) $cart_item_key);

                            $remove_url = add_query_arg(
                                array(
                                    'pl_remove_item' => (string) $cart_item_key,
                                    'pl_remove_nonce' => $remove_nonce,
                                ),
                                $cart_url
                            );
                            ?>
                            <div id="<?php echo esc_attr($row_id); ?>" class="cart-item py-8 border-b border-gray-100 flex gap-6">
                                <div class="w-24 h-24 md:w-32 md:h-32 bg-gray-100 flex-shrink-0 rounded-sm overflow-hidden">
                                    <?php
                                    echo wp_kses_post(
                                        $product->get_image(
                                            'woocommerce_thumbnail',
                                            array(
                                                'class' => 'w-full h-full object-cover transition-all duration-500',
                                            )
                                        )
                                    );
                                    ?>
                                </div>
                                <div class="flex-grow flex flex-col justify-between">
                                    <div>
                                        <div class="flex justify-between items-start gap-6">
                                            <h3 class="text-lg font-semibold tracking-tight"><?php echo esc_html($product->get_name()); ?></h3>
                                            <p class="text-lg font-medium"><?php echo wp_kses_post($line); ?></p>
                                        </div>
                                        <?php if ($item_data !== '') : ?>
                                            <div class="text-sm text-gray-500 mt-1"><?php echo wp_kses_post($item_data); ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex justify-between items-center mt-4">
                                        <form id="<?php echo esc_attr($row_id . '-qty-form'); ?>" method="post" action="<?php echo esc_url($cart_url); ?>" class="pl-cart-qty-form flex items-center border border-gray-200 rounded-sm">
                                            <input type="hidden" name="pl_cart_action" value="set_qty" />
                                            <input type="hidden" name="pl_cart_nonce" value="<?php echo esc_attr($cart_nonce); ?>" />
                                            <input type="hidden" name="pl_cart_item_key" value="<?php echo esc_attr((string) $cart_item_key); ?>" />
                                            <button id="<?php echo esc_attr($row_id . '-qty-minus'); ?>" type="button" data-delta="-1" class="px-3 py-1 hover:bg-gray-50 text-gray-400">-</button>
                                            <input
                                                id="<?php echo esc_attr($row_id . '-qty-input'); ?>"
                                                type="number"
                                                name="pl_cart_qty"
                                                value="<?php echo esc_attr((string) $qty); ?>"
                                                min="1"
                                                class="w-10 text-center text-sm font-medium focus:outline-none bg-transparent"
                                            />
                                            <button id="<?php echo esc_attr($row_id . '-qty-plus'); ?>" type="button" data-delta="1" class="px-3 py-1 hover:bg-gray-50 text-gray-400">+</button>
                                        </form>
                                        <a id="<?php echo esc_attr($row_id . '-remove'); ?>" href="<?php echo esc_url($remove_url); ?>" class="text-[10px] uppercase tracking-widest font-bold text-gray-400 hover:text-black underline underline-offset-4 transition-colors">
                                            <?php echo esc_html__('Eliminar', 'politeia-learning'); ?>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Promo Code -->
                        <div class="mt-10">
                            <button id="politeia-cart-promo-toggle" type="button" class="text-xs uppercase tracking-widest font-bold flex items-center group">
                                <span><?php echo esc_html__('¿Tienes un código promocional?', 'politeia-learning'); ?></span>
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 ml-2 group-hover:translate-x-1 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3" /></svg>
                            </button>
                            <form id="politeia-cart-promo-input" method="post" action="<?php echo esc_url($cart_url); ?>" class="hidden mt-4 flex max-w-sm">
                                <input type="hidden" name="pl_cart_action" value="apply_coupon" />
                                <input type="hidden" name="pl_cart_nonce" value="<?php echo esc_attr($cart_nonce); ?>" />
                                <input id="politeia-cart-coupon-code" type="text" name="coupon_code" placeholder="<?php echo esc_attr__('Ingresa el código', 'politeia-learning'); ?>" class="flex-grow border-b border-gray-300 bg-transparent py-2 focus:outline-none focus:border-black transition-colors text-sm">
                                <button id="politeia-cart-coupon-apply" type="submit" class="ml-4 text-[10px] uppercase tracking-[0.2em] font-bold"><?php echo esc_html__('Aplicar', 'politeia-learning'); ?></button>
                            </form>
                        </div>
                </div>

                <!-- Summary Section -->
                <div class="lg:w-1/3">
                    <div id="politeia-cart-order-summary" class="bg-white p-8">
                        <h2 id="politeia-cart-order-summary-title" class="text-lg font-bold uppercase tracking-widest mb-8"><?php echo esc_html__('Resumen del pedido', 'politeia-learning'); ?></h2>

                        <div class="space-y-4 text-sm">
                            <div class="flex justify-between">
                                <span id="politeia-cart-subtotal-label" class="text-gray-400 uppercase tracking-widest text-[11px] font-bold"><?php echo esc_html__('Subtotal', 'politeia-learning'); ?></span>
                                <span id="politeia-cart-subtotal-value" class="font-medium"><?php echo wp_kses_post($cart->get_cart_subtotal()); ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span id="politeia-cart-shipping-label" class="text-gray-400 uppercase tracking-widest text-[11px] font-bold"><?php echo esc_html__('Envío', 'politeia-learning'); ?></span>
                                <span id="politeia-cart-shipping-value" class="text-gray-500"><?php echo esc_html__('Por definir', 'politeia-learning'); ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span id="politeia-cart-tax-label" class="text-gray-400 uppercase tracking-widest text-[11px] font-bold"><?php echo esc_html__('Impuestos', 'politeia-learning'); ?></span>
                                <span id="politeia-cart-tax-value" class="font-medium"><?php echo wp_kses_post(wc_price((float) $cart->get_total_tax())); ?></span>
                            </div>

                            <div class="pt-6 border-t border-gray-100 flex justify-between items-baseline">
                                <span id="politeia-cart-total-label" class="text-sm font-bold uppercase tracking-widest"><?php echo esc_html__('Total', 'politeia-learning'); ?></span>
                                <span id="politeia-cart-total-value" class="text-3xl font-[800] tracking-tightest"><?php echo wp_kses_post($cart->get_total()); ?></span>
                            </div>
                        </div>

                        <a id="politeia-cart-checkout-now" href="<?php echo esc_url($checkout_url); ?>" class="block w-full text-center bg-black text-white mt-8 uppercase tracking-[0.3em] hover:bg-gray-800 transition-all transform active:scale-[0.98]" style="font-weight:700; font-size:12px; padding:14px; border-radius:6px;">
                            <?php echo esc_html__('Pagar ahora', 'politeia-learning'); ?>
                        </a>
                    </div>

                    <a id="politeia-cart-return-shop" href="<?php echo esc_url($shop_url); ?>" class="block text-center mt-8 text-[10px] uppercase tracking-[0.2em] font-bold text-gray-400 hover:text-black transition-colors">
                        &larr; <?php echo esc_html__('Volver a la tienda', 'politeia-learning'); ?>
                    </a>
                </div>
            </div>


        <?php endif; ?>
    </main>
</div>

<script>
    (function () {
        const promoToggle = document.getElementById('politeia-cart-promo-toggle');
        const promoInput = document.getElementById('politeia-cart-promo-input');

        if (promoToggle && promoInput) {
            promoToggle.addEventListener('click', () => {
                if (promoInput.classList.contains('hidden')) {
                    promoInput.classList.remove('hidden');
                    const label = promoToggle.querySelector('span');
                    if (label) label.textContent = 'Ingresa el código:';
                } else {
                    promoInput.classList.add('hidden');
                    const label = promoToggle.querySelector('span');
                    if (label) label.textContent = '¿Tienes un código promocional?';
                }
            });
        }

        document.querySelectorAll('.pl-cart-qty-form').forEach(form => {
            const input = form.querySelector('input[type="number"]');
            if (!input) return;

            function clampQty(value) {
                const parsed = parseInt(String(value || ''), 10);
                return Number.isFinite(parsed) ? Math.max(1, parsed) : 1;
            }

            form.querySelectorAll('button[data-delta]').forEach(btn => {
                btn.addEventListener('click', () => {
                    const delta = parseInt(btn.getAttribute('data-delta') || '0', 10) || 0;
                    input.value = clampQty(clampQty(input.value) + delta);
                    form.submit();
                });
            });

            input.addEventListener('change', () => {
                input.value = clampQty(input.value);
                form.submit();
            });
        });
    })();
</script>
<?php
pl_template_close();
