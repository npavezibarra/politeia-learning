<?php
/**
 * Politeia custom WooCommerce customer "payment retry" email.
 *
 * @var WC_Order $order
 * @var string   $logo_url
 * @var string   $payment_url
 */

if (!defined('ABSPATH')) {
    exit;
}

$order_number = $order->get_order_number();
$billing_first_name = (string) $order->get_billing_first_name();
$total = $order->get_formatted_order_total();
$site_name = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html($site_name); ?> - <?php echo esc_html(sprintf(__('Pago pendiente #%s', 'politeia-learning'), $order_number)); ?></title>
    <style>
        body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #efefef; margin: 0; padding: 24px 16px; color: #111827; }
        .container { max-width: 500px; margin: 0 auto; background-color: #ffffff; border-radius: 6px; overflow: hidden; border: 1px solid #f3f4f6; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04); }
        .header { padding: 28px 32px 16px; text-align: center; }
        .pill { display:inline-block; font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.12em; background:#e0f2fe; color:#075985; padding: 6px 10px; border-radius: 999px; }
        .subheader { font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.2em; color: #9ca3af; margin: 10px 0 6px; }
        .title { font-size: 22px; font-weight: 500; letter-spacing: -0.025em; margin: 0; }
        .content { padding: 8px 32px 28px; }
        .box { background:#f8fafc; border: 1px solid #e2e8f0; padding: 16px; border-radius: 8px; }
        .k { font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.12em; color:#9ca3af; margin: 0 0 6px; }
        .v { font-size: 14px; font-weight: 800; margin: 0; }
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
        <span class="pill"><?php echo esc_html__('Pago pendiente', 'politeia-learning'); ?></span>
        <p class="subheader"><?php echo esc_html__('Reintenta tu pago', 'politeia-learning'); ?></p>
        <h1 class="title"><?php echo esc_html(sprintf(__('Pedido #%s', 'politeia-learning'), $order_number)); ?></h1>
    </div>
    <div class="content">
        <p class="muted"><?php echo esc_html(sprintf(__('Hola %s, tu pago no se completó. Puedes reintentar usando el botón de abajo.', 'politeia-learning'), $billing_first_name !== '' ? $billing_first_name : '')); ?></p>
        <div style="margin-top: 24px;">
            <header style="margin-bottom: 32px; text-align: center;">
                <h2 style="font-size: 20px; font-weight: 900; color: #000000; text-transform: uppercase; letter-spacing: -0.05em; margin: 0;"><?php echo esc_html__('Detalles del Pago', 'politeia-learning'); ?></h2>
                <div style="margin: 12px auto 0; border-bottom: 4px solid #000000; width: 48px;"></div>
            </header>

            <table style="width: 100%; text-align: left; border-collapse: collapse;">
                <tbody style="border-top: 1px solid #e5e7eb;">
                    <tr>
                        <th style="padding: 16px 0; font-size: 9px; font-weight: 700; color: #000000; text-transform: uppercase; letter-spacing: 0.2em; border-bottom: 1px solid #e5e7eb; width: 50%;"><?php echo esc_html__('ID Pedido', 'politeia-learning'); ?></th>
                        <td style="padding: 16px 0; font-size: 13px; font-weight: 600; color: #000000; text-align: right; border-bottom: 1px solid #e5e7eb;">#<?php echo esc_html($order_number); ?></td>
                    </tr>
                    <tr>
                        <th style="padding: 24px 0; font-size: 13px; font-weight: 900; color: #000000; text-transform: uppercase; letter-spacing: 0.2em; border-top: 4px solid #000000;"><?php echo esc_html__('Total a Pagar', 'politeia-learning'); ?></th>
                        <td style="padding: 24px 0; font-size: 20px; font-weight: 900; color: #000000; text-align: right; border-top: 4px solid #000000;"><?php echo wp_kses_post($total); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php if (!empty($payment_url)) : ?>
            <a class="btn" href="<?php echo esc_url((string) $payment_url); ?>"><?php echo esc_html__('Reintentar pago', 'politeia-learning'); ?></a>
        <?php endif; ?>
        <p class="muted" style="margin-top: 14px;"><?php echo esc_html__('Si necesitas ayuda, contáctanos desde el sitio.', 'politeia-learning'); ?></p>
    </div>
</div>
</body>
</html>

