<?php
/**
 * Politeia custom WooCommerce admin backorder notification.
 *
 * @var WC_Product $product
 * @var WC_Order|null $order
 * @var string $logo_url
 */

if (!defined('ABSPATH')) {
    exit;
}

$product_name = $product ? $product->get_name() : '';
$product_id = $product ? $product->get_id() : 0;
$sku = $product ? $product->get_sku() : '';
$edit_url = $product_id ? get_edit_post_link($product_id, '') : '';

$order_number = ($order instanceof WC_Order) ? $order->get_order_number() : '';
$admin_order_url = ($order instanceof WC_Order) ? admin_url('post.php?post=' . $order->get_id() . '&action=edit') : '';
$site_name = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html($site_name); ?> - <?php echo esc_html__('Backorder', 'politeia-learning'); ?></title>
    <style>
        body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #efefef; margin: 0; padding: 24px 16px; color: #111827; }
        .container { max-width: 500px; margin: 0 auto; background-color: #ffffff; border-radius: 6px; overflow: hidden; border: 1px solid #f3f4f6; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04); }
        .header { padding: 28px 32px 16px; text-align: center; }
        .pill { display:inline-block; font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.12em; background:#ede9fe; color:#5b21b6; padding: 6px 10px; border-radius: 999px; }
        .subheader { font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.2em; color: #9ca3af; margin-top: 14px; }
        .title { font-size: 20px; font-weight: 600; letter-spacing: -0.025em; margin: 6px 0 0; }
        .content { padding: 8px 32px 28px; }
        .receipt { width: 100%; border-collapse: collapse; margin-top: 16px; border: 1px solid #e5e7eb; border-radius: 12px; overflow: hidden; }
        .receipt td { padding: 12px 16px; border-bottom: 1px solid #f3f4f6; font-size: 13px; }
        .receipt tr:last-child td { border-bottom: none; }
        .label { color:#6b7280; font-weight: 600; }
        .value { text-align:right; font-weight: 800; }
        .btn { display:block; width:100%; background:#000; color:#fff !important; text-decoration:none; text-align:center; padding: 12px 0; border-radius: 6px; font-size: 12px; font-weight: 600; letter-spacing: 0.05em; margin-top: 16px; }
        .muted { color:#6b7280; font-size: 13px; margin: 10px 0 0; line-height: 1.6; }
        .logo { width: 140px; height: auto; display: inline-block; }
        .logo-text { display: inline-block; border-bottom: 2px solid #000; padding-bottom: 2px; margin-bottom: 8px; text-transform: uppercase; font-weight: 900; font-size: 18px; letter-spacing: -0.04em; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <div style="margin-bottom: 18px;">
            <?php if (!empty($logo_url)) : ?>
                <img src="<?php echo esc_url((string) $logo_url); ?>" alt="<?php echo esc_attr($site_name); ?>" class="logo">
            <?php else : ?>
                <div class="logo-text"><?php echo esc_html($site_name !== '' ? $site_name : 'Politeia'); ?></div>
            <?php endif; ?>
        </div>
        <span class="pill"><?php echo esc_html__('Backorder', 'politeia-learning'); ?></span>
        <p class="subheader"><?php echo esc_html__('Notificación inventario', 'politeia-learning'); ?></p>
        <h1 class="title"><?php echo esc_html($product_name); ?></h1>
    </div>
    <div class="content">
        <p class="muted">
            <?php
            echo esc_html(
                $order_number
                    ? sprintf(__('Se solicitó un producto en backorder para el pedido #%s.', 'politeia-learning'), $order_number)
                    : __('Se solicitó un producto en backorder.', 'politeia-learning')
            );
            ?>
        </p>
        <table class="receipt">
            <tr><td class="label"><?php echo esc_html__('SKU', 'politeia-learning'); ?></td><td class="value"><?php echo esc_html($sku ?: '—'); ?></td></tr>
            <?php if ($order_number) : ?>
                <tr><td class="label"><?php echo esc_html__('Pedido', 'politeia-learning'); ?></td><td class="value">#<?php echo esc_html($order_number); ?></td></tr>
            <?php endif; ?>
        </table>

        <?php if ($admin_order_url) : ?>
            <a class="btn" href="<?php echo esc_url($admin_order_url); ?>"><?php echo esc_html__('Ver pedido', 'politeia-learning'); ?></a>
        <?php elseif ($edit_url) : ?>
            <a class="btn" href="<?php echo esc_url($edit_url); ?>"><?php echo esc_html__('Editar producto', 'politeia-learning'); ?></a>
        <?php endif; ?>
    </div>
</div>
</body>
</html>

