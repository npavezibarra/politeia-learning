<?php
/**
 * Politeia custom WooCommerce customer "partially refunded" email.
 *
 * @var WC_Order $order
 * @var WC_Order|null $refund
 * @var string $logo_url
 */

if (!defined('ABSPATH')) {
    exit;
}

$order_number = $order->get_order_number();
$billing_first_name = (string) $order->get_billing_first_name();
$total = $order->get_formatted_order_total();
$date = wc_format_datetime($order->get_date_created());
$site_name = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);

$refund_total = '';
if (isset($refund) && $refund instanceof WC_Order && method_exists($refund, 'get_amount')) {
    $refund_total = wc_price((float) $refund->get_amount());
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html($site_name); ?> - <?php echo esc_html(sprintf(__('Reembolso parcial #%s', 'politeia-learning'), $order_number)); ?></title>
    <style>
        body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #efefef; margin: 0; padding: 24px 16px; color: #111827; }
        .container { max-width: 500px; margin: 0 auto; background-color: #ffffff; border-radius: 6px; overflow: hidden; border: 1px solid #f3f4f6; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04); }
        .header { padding: 32px 32px 16px 32px; text-align: center; }
        .subheader { font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.2em; color: #9ca3af; margin-bottom: 4px; }
        .title { font-size: 22px; font-weight: 500; letter-spacing: -0.025em; margin: 0; }
        .content { padding: 16px 32px 28px; }
        .box { background:#f8fafc; border: 1px solid #e2e8f0; padding: 16px; border-radius: 8px; margin-top: 12px; }
        .k { font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.12em; color:#9ca3af; margin: 0 0 6px; }
        .v { font-size: 14px; font-weight: 800; margin: 0; }
        .muted { color:#6b7280; font-size: 13px; margin: 10px 0 0; line-height: 1.6; }
        .logo { width: 140px; height: auto; display: inline-block; }
        .logo-text { display: inline-block; border-bottom: 2px solid #000; padding-bottom: 2px; margin-bottom: 8px; text-transform: uppercase; font-weight: 900; font-size: 18px; letter-spacing: -0.04em; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <div style="margin-bottom: 24px;">
            <?php if (!empty($logo_url)) : ?>
                <img src="<?php echo esc_url((string) $logo_url); ?>" alt="<?php echo esc_attr($site_name); ?>" class="logo">
            <?php else : ?>
                <div class="logo-text"><?php echo esc_html($site_name !== '' ? $site_name : 'Politeia'); ?></div>
            <?php endif; ?>
        </div>
        <div class="subheader"><?php echo esc_html__('Reembolso parcial', 'politeia-learning'); ?></div>
        <h1 class="title"><?php echo esc_html(sprintf(__('Pedido #%s', 'politeia-learning'), $order_number)); ?></h1>
    </div>
    <div class="content">
        <p class="muted"><?php echo esc_html(sprintf(__('Hola %s, se ha realizado un reembolso parcial de tu pedido.', 'politeia-learning'), $billing_first_name !== '' ? $billing_first_name : '')); ?></p>

        <?php if ($refund_total !== '') : ?>
            <div class="box">
                <p class="k"><?php echo esc_html__('Monto reembolsado', 'politeia-learning'); ?></p>
                <p class="v"><?php echo wp_kses_post($refund_total); ?></p>
            </div>
        <?php endif; ?>

        <div class="box">
            <p class="k"><?php echo esc_html__('Total del pedido', 'politeia-learning'); ?></p>
            <p class="v"><?php echo wp_kses_post($total); ?></p>
        </div>

        <div class="box">
            <p class="k"><?php echo esc_html__('Fecha', 'politeia-learning'); ?></p>
            <p class="v"><?php echo esc_html($date); ?></p>
        </div>
    </div>
</div>
</body>
</html>

