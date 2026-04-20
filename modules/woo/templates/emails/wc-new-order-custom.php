<?php
/**
 * Politeia custom WooCommerce "Order confirmed" email (replaces customer_processing/completed).
 *
 * @var WC_Order $order
 * @var string   $logo_url
 * @var string   $view          'course'|'physical'
 * @var array    $access_links  array<array{url:string,label:string,context:string}>
 * @var int      $test_user_id  Optional (email tester support)
 */

if (!defined('ABSPATH')) {
    exit;
}

$order_number = $order->get_order_number();
$billing_first_name = (string) $order->get_billing_first_name();

if (isset($test_user_id) && (int) $test_user_id > 0) {
    $u = get_userdata((int) $test_user_id);
    if ($u) {
        $billing_first_name = $u->first_name ?: $u->display_name;
    }
}

$total = $order->get_formatted_order_total();
$date = wc_format_datetime($order->get_date_created());
$site_name = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);

$has_links = isset($access_links) && is_array($access_links) && !empty($access_links);
$is_course_view = isset($view) && $view === 'course';

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html($site_name); ?> - <?php echo esc_html(sprintf(__('Pedido confirmado #%s', 'politeia-learning'), $order_number)); ?></title>
    <style>
        body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #efefef; margin: 0; padding: 24px 16px; color: #111827; }
        .container { max-width: 500px; margin: 0 auto; background-color: #ffffff; border-radius: 6px; overflow: hidden; border: 1px solid #f3f4f6; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04); }
        .header { padding: 32px 32px 16px 32px; text-align: center; }
        .subheader { font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.2em; color: #9ca3af; margin-bottom: 4px; }
        .order-title { font-size: 24px; font-weight: 300; letter-spacing: -0.025em; margin: 0; line-height: 1.2; }
        .order-title strong { font-weight: 500; }
        .content { padding: 16px 32px; }
        .greeting { font-size: 14px; font-weight: 600; margin-bottom: 12px; }
        .message { font-size: 14px; line-height: 1.6; color: #4b5563; margin-bottom: 18px; }
        .btn-container { text-align: center; margin: 22px 0 28px; }
        .btn { background-color: #000000; color: #ffffff !important; padding: 12px 32px; border-radius: 6px; text-decoration: none; font-weight: 600; font-size: 12px; letter-spacing: 0.1em; text-transform: uppercase; display: inline-block; }
        .btn + .btn { margin-left: 10px; }
        .item-row { padding: 4px 0; display: flex; justify-content: space-between; font-size: 13px; }
        .receipt-card { max-width: 500px; margin: 24px auto; background: #ffffff; border: 1px solid #e5e7eb; border-radius: 12px; overflow: hidden; }
        .receipt-table { width: 100%; border-collapse: collapse; }
        .receipt-table td { padding: 16px 24px; border-bottom: 1px solid #f3f4f6; }
        .receipt-table tr:last-child td { border-bottom: none; }
        .receipt-label { color: #6b7280; font-size: 13px; font-weight: 500; width: 40%; text-align: left; }
        .receipt-value { color: #111827; font-size: 13px; font-weight: 600; text-align: right; }
        .receipt-total-row { background-color: #f9fafb; }
        .receipt-total-value { color: #111827; font-size: 18px; font-weight: 800; }
        .footer { padding: 24px 32px; text-align: center; font-size: 8px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.15em; color: #d1d5db; }
        .logo { width: 170px; height: auto; display: inline-block; }
        .logo-text { display: inline-block; border-bottom: 2px solid #000; padding-bottom: 2px; margin-bottom: 8px; text-transform: uppercase; font-weight: 900; font-size: 18px; letter-spacing: -0.04em; }
    </style>
</head>
<body style="margin:0;padding:0;background-color:#efefef;">
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
                        <!-- Logo Divider -->
                        <div style="width: 100%; height: 4px; background-color: #000000; margin: 8px auto 0;"></div>
                    </div>
                    <div class="subheader"><?php echo esc_html__('Pedido confirmado', 'politeia-learning'); ?></div>
                    <h1 class="order-title"><?php echo esc_html__('Tu pedido', 'politeia-learning'); ?> <strong>#<?php echo esc_html($order_number); ?></strong> <?php echo esc_html__('está listo', 'politeia-learning'); ?></h1>
                </div>

                <div class="content">
                    <div class="greeting"><?php echo esc_html(sprintf(__('Hola %s,', 'politeia-learning'), $billing_first_name !== '' ? $billing_first_name : __('!', 'politeia-learning'))); ?></div>
                    <div class="message">
                        <?php if ($is_course_view) : ?>
                            <?php echo esc_html__('¡Excelentes noticias! Tu acceso a tus cursos ya está activo.', 'politeia-learning'); ?>
                        <?php else : ?>
                            <?php echo esc_html__('¡Gracias por tu compra! Tu pedido fue confirmado.', 'politeia-learning'); ?>
                        <?php endif; ?>
                    </div>

                    <?php if ($has_links) : ?>
                        <div class="btn-container">
                            <?php foreach ($access_links as $link) : ?>
                                <?php
                                $url = isset($link['url']) ? (string) $link['url'] : '';
                                $label = isset($link['label']) ? (string) $link['label'] : '';
                                if ($url === '' || $label === '') {
                                    continue;
                                }
                                ?>
                                <a href="<?php echo esc_url($url); ?>" class="btn"><?php echo esc_html($label); ?></a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <div style="margin-bottom: 24px;">
                        <h4 style="font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.15em; color: #9ca3af; margin-bottom: 12px;"><?php echo esc_html__('Resumen de compra', 'politeia-learning'); ?></h4>
                        <?php foreach ($order->get_items() as $item) : ?>
                            <div class="item-row">
                                <span style="max-width: 70%;"><?php echo esc_html($item->get_name()); ?> x<?php echo esc_html((string) $item->get_quantity()); ?></span>
                                <span style="font-weight: 600;"><?php echo wp_kses_post($order->get_formatted_line_subtotal($item)); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div style="padding: 0 32px 32px 32px;">
                    <header style="margin-bottom: 48px; text-align: center;">
                        <h1 style="font-size: 24px; font-weight: 300; color: #000000; letter-spacing: -0.025em; margin: 0; line-height: 1.2;"><?php echo esc_html__('Detalles del Pedido', 'politeia-learning'); ?></h1>
                        <div style="margin: 16px auto 0; border-bottom: 4px solid #000000; width: 64px;"></div>
                    </header>

                    <table style="width: 100%; text-align: left; border-collapse: collapse;">
                        <tbody style="border-top: 1px solid #e5e7eb;">
                            <tr>
                                <th style="padding: 20px 0; font-size: 10px; font-weight: 700; color: #000000; text-transform: uppercase; letter-spacing: 0.2em; border-bottom: 1px solid #e5e7eb; width: 50%;"><?php echo esc_html__('ID Pedido', 'politeia-learning'); ?></th>
                                <td style="padding: 20px 0; font-size: 14px; font-weight: 600; color: #000000; text-align: right; border-bottom: 1px solid #e5e7eb;">#<?php echo esc_html($order_number); ?></td>
                            </tr>
                            <tr>
                                <th style="padding: 20px 0; font-size: 10px; font-weight: 700; color: #000000; text-transform: uppercase; letter-spacing: 0.2em; border-bottom: 1px solid #e5e7eb;"><?php echo esc_html__('Fecha', 'politeia-learning'); ?></th>
                                <td style="padding: 20px 0; font-size: 14px; color: #000000; text-align: right; border-bottom: 1px solid #e5e7eb;"><?php echo esc_html($date); ?></td>
                            </tr>
                            <tr>
                                <th style="padding: 20px 0; font-size: 10px; font-weight: 700; color: #000000; text-transform: uppercase; letter-spacing: 0.2em; border-bottom: 1px solid #e5e7eb;"><?php echo esc_html__('Método', 'politeia-learning'); ?></th>
                                <td style="padding: 20px 0; font-size: 14px; color: #000000; text-align: right; border-bottom: 1px solid #e5e7eb;"><?php echo esc_html($order->get_payment_method_title()); ?></td>
                            </tr>
                            <tr>
                                <th style="padding: 32px 0; font-size: 14px; font-weight: 900; color: #000000; text-transform: uppercase; letter-spacing: 0.2em; border-top: 4px solid #000000;"><?php echo esc_html__('Total Pagado', 'politeia-learning'); ?></th>
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

