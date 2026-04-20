<?php
/**
 * Politeia custom WooCommerce admin "new order" notification.
 *
 * @var WC_Order $order
 * @var string   $logo_url
 */

if (!defined('ABSPATH')) {
    exit;
}

$order_number = $order->get_order_number();
$billing_name = trim((string) $order->get_billing_first_name() . ' ' . (string) $order->get_billing_last_name());
$total = $order->get_formatted_order_total();
$date = wc_format_datetime($order->get_date_created());
$site_name = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html($site_name); ?> - <?php echo esc_html(sprintf(__('Nueva venta #%s', 'politeia-learning'), $order_number)); ?></title>
    <style>
        body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #efefef; margin: 0; padding: 24px 16px; color: #111827; }
        .container { max-width: 500px; margin: 0 auto; background-color: #ffffff; border-radius: 6px; overflow: hidden; border: 1px solid #f3f4f6; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04); }
        .header { padding: 32px 32px 16px 32px; text-align: center; }
        .subheader { font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.2em; color: #9ca3af; margin-bottom: 4px; }
        .order-title { font-size: 24px; font-weight: 300; letter-spacing: -0.025em; margin: 0; line-height: 1.2; }
        .order-title strong { font-weight: 500; }
        .content { padding: 16px 32px; }
        .notice-box { background-color: #f8fafc; border: 1px solid #e2e8f0; padding: 20px; border-radius: 6px; margin-bottom: 24px; }
        .item-row { padding: 4px 0; display: flex; justify-content: space-between; font-size: 13px; }
        .receipt-card { max-width: 500px; margin: 24px auto; background: #ffffff; border: 1px solid #e5e7eb; border-radius: 12px; overflow: hidden; }
        .receipt-table { width: 100%; border-collapse: collapse; }
        .receipt-table td { padding: 16px 24px; border-bottom: 1px solid #f3f4f6; }
        .receipt-label { color: #6b7280; font-size: 13px; font-weight: 500; text-align: left; }
        .receipt-value { color: #111827; font-size: 13px; font-weight: 600; text-align: right; }
        .receipt-total-row { background-color: #f9fafb; }
        .receipt-total-value { color: #000000; font-size: 18px; font-weight: 800; }
        .footer { padding: 24px 32px; text-align: center; font-size: 8px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.15em; color: #d1d5db; }
        .logo { width: 170px; height: auto; display: inline-block; }
        .logo-text { display: inline-block; border-bottom: 2px solid #000; padding-bottom: 2px; margin-bottom: 8px; text-transform: uppercase; font-weight: 900; font-size: 18px; letter-spacing: -0.04em; }
    </style>
</head>
<body>
<table width="100%" border="0" cellpadding="0" cellspacing="0" bgcolor="#efefef">
    <tr>
        <td align="center" style="padding:24px 16px;">
            <div class="container">
                <div class="header">
                    <div style="margin-bottom: 24px;">
                        <?php if (!empty($logo_url)) : ?>
                            <img src="<?php echo esc_url((string) $logo_url); ?>" alt="<?php echo esc_attr($site_name); ?>" class="logo" width="170">
                        <?php else : ?>
                            <div class="logo-text"><?php echo esc_html($site_name !== '' ? $site_name : 'Politeia'); ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="subheader"><?php echo esc_html__('Notificación administrador', 'politeia-learning'); ?></div>
                    <h1 class="order-title"><?php echo esc_html__('Nueva venta', 'politeia-learning'); ?> <strong>#<?php echo esc_html($order_number); ?></strong></h1>
                </div>

                <div class="content">
                    <div class="notice-box">
                        <p style="margin: 0; font-size: 13px; color: #334155; line-height: 1.5;">
                            <?php
                            echo wp_kses_post(
                                sprintf(
                                    __('Se ha realizado un nuevo pedido de <strong>%s</strong>.', 'politeia-learning'),
                                    esc_html($billing_name !== '' ? $billing_name : __('Cliente', 'politeia-learning'))
                                )
                            );
                            ?>
                        </p>
                    </div>

                    <h4 style="font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.15em; color: #9ca3af; margin-bottom: 12px;"><?php echo esc_html__('Productos', 'politeia-learning'); ?></h4>
                    <?php foreach ($order->get_items() as $item) : ?>
                        <div class="item-row">
                            <span style="max-width: 70%;"><?php echo esc_html($item->get_name()); ?> x<?php echo esc_html((string) $item->get_quantity()); ?></span>
                            <span style="font-weight: 600;"><?php echo wp_kses_post($order->get_formatted_line_subtotal($item)); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div style="padding: 0 32px 32px 32px;">
                    <header style="margin-bottom: 48px; text-align: center;">
                        <h1 style="font-size: 30px; font-weight: 900; color: #000000; text-transform: uppercase; letter-spacing: -0.05em; margin: 0;"><?php echo esc_html__('Detalles de la Venta', 'politeia-learning'); ?></h1>
                        <div style="margin: 16px auto 0; border-bottom: 4px solid #000000; width: 64px;"></div>
                    </header>

                    <table style="width: 100%; text-align: left; border-collapse: collapse;">
                        <tbody style="border-top: 1px solid #e5e7eb;">
                            <tr>
                                <th style="padding: 20px 0; font-size: 10px; font-weight: 700; color: #000000; text-transform: uppercase; letter-spacing: 0.2em; border-bottom: 1px solid #e5e7eb; width: 50%;"><?php echo esc_html__('ID Venta', 'politeia-learning'); ?></th>
                                <td style="padding: 20px 0; font-size: 14px; font-weight: 600; color: #000000; text-align: right; border-bottom: 1px solid #e5e7eb;">#<?php echo esc_html($order_number); ?></td>
                            </tr>
                            <tr>
                                <th style="padding: 20px 0; font-size: 10px; font-weight: 700; color: #000000; text-transform: uppercase; letter-spacing: 0.2em; border-bottom: 1px solid #e5e7eb;"><?php echo esc_html__('Fecha', 'politeia-learning'); ?></th>
                                <td style="padding: 20px 0; font-size: 14px; color: #000000; text-align: right; border-bottom: 1px solid #e5e7eb;"><?php echo esc_html($date); ?></td>
                            </tr>
                            <tr>
                                <th style="padding: 20px 0; font-size: 10px; font-weight: 700; color: #000000; text-transform: uppercase; letter-spacing: 0.2em; border-bottom: 1px solid #e5e7eb;"><?php echo esc_html__('Pago', 'politeia-learning'); ?></th>
                                <td style="padding: 20px 0; font-size: 14px; color: #000000; text-align: right; border-bottom: 1px solid #e5e7eb;"><?php echo esc_html($order->get_payment_method_title()); ?></td>
                            </tr>
                            <tr>
                                <th style="padding: 32px 0; font-size: 14px; font-weight: 900; color: #000000; text-transform: uppercase; letter-spacing: 0.2em; border-top: 4px solid #000000;"><?php echo esc_html__('Total Recibido', 'politeia-learning'); ?></th>
                                <td style="padding: 32px 0; font-size: 24px; font-weight: 900; color: #000000; text-align: right; border-top: 4px solid #000000;"><?php echo wp_kses_post($total); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="footer">
                    &copy; <?php echo esc_html((string) wp_date('Y')); ?> <?php echo esc_html($site_name !== '' ? $site_name : 'Politeia'); ?>
                </div>
            </div>
        </td>
    </tr>
</table>
</body>
</html>

