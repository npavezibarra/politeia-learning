<?php
/**
 * Politeia custom WooCommerce bank transfer (BACS) "on-hold" email.
 *
 * @var WC_Order $order
 * @var string   $logo_url
 * @var array    $access_links array<array{url:string,label:string,context:string}>
 * @var array    $bank_details Optional: bacs gateway account details
 * @var int      $test_user_id Optional (email tester support)
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

$subtotal = $order->get_subtotal_to_display();
$total = $order->get_formatted_order_total();
$date = wc_format_datetime($order->get_date_created());
$site_name = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);

$bank_details = isset($bank_details) && is_array($bank_details) ? $bank_details : [];
$bank_name = isset($bank_details['bank_name']) ? (string) $bank_details['bank_name'] : '';
$account_name = isset($bank_details['account_name']) ? (string) $bank_details['account_name'] : '';
$account_number = isset($bank_details['account_number']) ? (string) $bank_details['account_number'] : '';

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html($site_name); ?> - <?php echo esc_html(sprintf(__('Pedido en espera #%s', 'politeia-learning'), $order_number)); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #ffffff; margin: 0; padding: 24px 16px; color: #000000; }
        .table-container { max-width: 500px; margin: 40px auto; background: #ffffff; padding: 16px; }
        .header { margin-bottom: 48px; text-align: center; }
        .title { font-size: 30px; font-weight: 900; color: #000000; text-transform: uppercase; letter-spacing: -0.05em; margin: 0; }
        .underline { margin: 16px auto 0; border-bottom: 4px solid #000000; width: 64px; }
        .receipt-table { width: 100%; text-align: left; border-collapse: collapse; }
        .receipt-table th { padding: 20px 0; font-size: 10px; font-weight: 700; color: #000000; text-transform: uppercase; letter-spacing: 0.15em; border-bottom: 1px solid #e5e7eb; }
        .receipt-table td { padding: 20px 0; font-size: 14px; font-weight: 500; color: #000000; text-align: right; border-bottom: 1px solid #e5e7eb; }
        .total-row th { padding: 32px 0; font-size: 14px; font-weight: 900; border-top: 4px solid #000000; border-bottom: none; }
        .total-row td { padding: 32px 0; font-size: 24px; font-weight: 900; border-top: 4px solid #000000; border-bottom: none; }
        .bank-box { background-color: #f9fafb; padding: 24px; border-radius: 8px; margin-bottom: 32px; border: 1px solid #e5e7eb; }
        .bank-title { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.15em; color: #6b7280; margin: 0 0 12px 0; }
        .bank-item { font-size: 13px; color: #000000; margin-bottom: 4px; line-height: 1.5; }
        .bank-item strong { font-weight: 700; color: #6b7280; }
    </style>
</head>
<body style="margin:0;padding:24px 16px;background-color:#ffffff;font-family:'Inter', sans-serif;">
    <div class="table-container">
        <!-- Header -->
        <div class="header" style="margin-bottom: 48px; text-align: center;">
            <h1 class="title" style="font-size: 30px; font-weight: 900; color: #000000; text-transform: uppercase; letter-spacing: -0.05em; margin: 0;"><?php echo esc_html__('Detalles del Pedido', 'politeia-learning'); ?></h1>
            <div class="underline" style="margin: 16px auto 0; border-bottom: 4px solid #000000; width: 64px;"></div>
        </div>

        <!-- Greeting & Info -->
        <div style="margin-bottom: 32px; text-align: center;">
            <p style="font-size: 16px; font-weight: 600; margin: 0 0 8px 0;"><?php echo esc_html(sprintf(__('Hola %s,', 'politeia-learning'), $billing_first_name)); ?></p>
            <p style="font-size: 14px; color: #6b7280; line-height: 1.5; margin: 0;">
                <?php echo esc_html__('Tu pedido está en espera de la transferencia bancaria. Una vez confirmada, activaremos tu acceso.', 'politeia-learning'); ?>
            </p>
        </div>

        <!-- Bank Details -->
        <?php if ($account_name !== '' || $account_number !== '' || $bank_name !== '') : ?>
        <div class="bank-box" style="background-color: #f9fafb; padding: 24px; border-radius: 8px; margin-bottom: 32px; border: 1px solid #e5e7eb;">
            <p class="bank-title" style="font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.15em; color: #6b7280; margin: 0 0 12px 0;"><?php echo esc_html__('Datos para la transferencia', 'politeia-learning'); ?></p>
            <?php if ($account_name !== '') : ?><div class="bank-item" style="font-size: 13px; color: #000000; margin-bottom: 4px; line-height: 1.5;"><strong style="font-weight: 700; color: #6b7280;"><?php echo esc_html__('Titular', 'politeia-learning'); ?>:</strong> <?php echo esc_html($account_name); ?></div><?php endif; ?>
            <?php if ($bank_name !== '') : ?><div class="bank-item" style="font-size: 13px; color: #000000; margin-bottom: 4px; line-height: 1.5;"><strong style="font-weight: 700; color: #6b7280;"><?php echo esc_html__('Banco', 'politeia-learning'); ?>:</strong> <?php echo esc_html($bank_name); ?></div><?php endif; ?>
            <?php if ($account_number !== '') : ?><div class="bank-item" style="font-size: 13px; color: #000000; margin-bottom: 4px; line-height: 1.5;"><strong style="font-weight: 700; color: #6b7280;"><?php echo esc_html__('Cuenta', 'politeia-learning'); ?>:</strong> <?php echo esc_html($account_number); ?></div><?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Receipt Table -->
        <table class="receipt-table" role="presentation" style="width: 100%; text-align: left; border-collapse: collapse;">
            <tbody>
                <tr>
                    <th style="width: 50%; padding: 20px 0; font-size: 10px; font-weight: 700; color: #000000; text-transform: uppercase; letter-spacing: 0.15em; border-bottom: 1px solid #e5e7eb;"><?php echo esc_html__('ID Pedido', 'politeia-learning'); ?></th>
                    <td style="padding: 20px 0; font-size: 14px; font-weight: 500; color: #000000; text-align: right; border-bottom: 1px solid #e5e7eb;">#<?php echo esc_html($order_number); ?></td>
                </tr>
                <tr>
                    <th style="padding: 20px 0; font-size: 10px; font-weight: 700; color: #000000; text-transform: uppercase; letter-spacing: 0.15em; border-bottom: 1px solid #e5e7eb;"><?php echo esc_html__('Fecha', 'politeia-learning'); ?></th>
                    <td style="padding: 20px 0; font-size: 14px; font-weight: 500; color: #000000; text-align: right; border-bottom: 1px solid #e5e7eb;"><?php echo esc_html($date); ?></td>
                </tr>
                <tr>
                    <th style="padding: 20px 0; font-size: 10px; font-weight: 700; color: #000000; text-transform: uppercase; letter-spacing: 0.15em; border-bottom: 1px solid #e5e7eb;"><?php echo esc_html__('Método de pago', 'politeia-learning'); ?></th>
                    <td style="padding: 20px 0; font-size: 14px; font-weight: 500; color: #000000; text-align: right; border-bottom: 1px solid #e5e7eb;"><?php echo esc_html($order->get_payment_method_title()); ?></td>
                </tr>
                <tr>
                    <th style="padding: 20px 0; font-size: 10px; font-weight: 700; color: #000000; text-transform: uppercase; letter-spacing: 0.15em; border-bottom: 1px solid #e5e7eb;"><?php echo esc_html__('Subtotal', 'politeia-learning'); ?></th>
                    <td style="padding: 20px 0; font-size: 14px; font-weight: 500; color: #000000; text-align: right; border-bottom: 1px solid #e5e7eb;"><?php echo wp_kses_post($subtotal); ?></td>
                </tr>
                <tr class="total-row">
                    <th style="padding: 32px 0; font-size: 14px; font-weight: 900; border-top: 4px solid #000000; text-transform: uppercase; letter-spacing: 0.15em;"><?php echo esc_html__('Total a transferir', 'politeia-learning'); ?></th>
                    <td style="padding: 32px 0; font-size: 24px; font-weight: 900; border-top: 4px solid #000000; text-align: right;"><?php echo wp_kses_post($total); ?></td>
                </tr>
            </tbody>
        </table>

        <!-- Summary of items -->
        <div style="margin-top: 48px; border-top: 1px solid #e5e7eb; padding-top: 24px;">
            <p style="font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.15em; color: #6b7280; margin-bottom: 16px; text-align: center;">
                <?php echo esc_html__('Resumen de productos', 'politeia-learning'); ?>
            </p>
            <?php foreach ($order->get_items() as $item) : ?>
                <div style="display: flex; justify-content: space-between; font-size: 13px; margin-bottom: 8px;">
                    <span style="color: #4b5563;"><?php echo esc_html($item->get_name()); ?> <span style="color: #9ca3af; margin-left: 4px;">x<?php echo esc_html((string) $item->get_quantity()); ?></span></span>
                    <span style="font-weight: 600; color: #000000;"><?php echo wp_kses_post($order->get_formatted_line_subtotal($item)); ?></span>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Footer -->
        <div style="margin-top: 64px; text-align: center; font-size: 11px; color: #9ca3af; font-weight: 500;">
            &copy; <?php echo esc_html((string) wp_date('Y')); ?> <?php echo esc_html($site_name); ?>. <?php echo esc_html__('Gracias por tu preferencia.', 'politeia-learning'); ?>
        </div>
    </div>
</body>
</html>
