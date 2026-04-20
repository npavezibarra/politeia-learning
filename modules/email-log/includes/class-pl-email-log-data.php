<?php
/**
 * Data catalogs and constants for Email Log.
 */

if (!defined('ABSPATH')) {
    exit;
}

final class PL_Email_Log_Data
{
    /**
     * Unified catalog for the Test Emails UI.
     *
     * @return array<string,array{id:string,label:string,origin:string,default_template:string,recipient?:string}>
     */
    public static function get_test_emails_catalog(): array
    {
        $items = [];

        foreach (self::get_wp_test_emails_catalog() as $key => $item) {
            $items[$key] = $item;
        }

        foreach (self::get_woo_test_emails_catalog() as $key => $item) {
            if (!isset($items[$key])) {
                $items[$key] = $item;
            }
        }

        foreach (self::get_politeia_woo_custom_test_emails_catalog() as $key => $item) {
            if (!isset($items[$key])) {
                $items[$key] = $item;
            }
        }

        foreach (self::get_learni_test_emails_catalog() as $key => $item) {
            if (!isset($items[$key])) {
                $items[$key] = $item;
            }
        }

        return $items;
    }

    /**
     * @return array<string,array{id:string,label:string,origin:string,default_template:string,recipient?:string}>
     */
    public static function get_wp_test_emails_catalog(): array
    {
        return [
            'new_user_admin' => [
                'id' => 'new_user_admin',
                'label' => __('Nuevo usuario (admin)', 'politeia-learning'),
                'origin' => 'WP',
                'recipient' => 'admin',
                'default_template' => '',
            ],
            'new_user_user' => [
                'id' => 'new_user_user',
                'label' => __('Nuevo usuario (usuario)', 'politeia-learning'),
                'origin' => 'WP',
                'recipient' => 'customer',
                'default_template' => 'templates/emails/auth-confirmation.php',
            ],
            'password_reset' => [
                'id' => 'password_reset',
                'label' => __('Reset de contraseña', 'politeia-learning'),
                'origin' => 'WP',
                'recipient' => 'customer',
                'default_template' => 'templates/emails/password-reset.php',
            ],
            'password_change_user' => [
                'id' => 'password_change_user',
                'label' => __('Contraseña cambiada (usuario)', 'politeia-learning'),
                'origin' => 'WP',
                'recipient' => 'customer',
                'default_template' => 'templates/emails/password_change_user.php',
            ],
            'password_change_admin' => [
                'id' => 'password_change_admin',
                'label' => __('Contraseña cambiada (admin)', 'politeia-learning'),
                'origin' => 'WP',
                'recipient' => 'admin',
                'default_template' => 'templates/emails/password_change_admin.php',
            ],
            'email_change_user' => [
                'id' => 'email_change_user',
                'label' => __('Email de usuario cambiado', 'politeia-learning'),
                'origin' => 'WP',
                'recipient' => 'customer',
                'default_template' => 'templates/emails/email_change_user.php',
            ],
            'admin_email_changed_notification' => [
                'id' => 'admin_email_changed_notification',
                'label' => __('Email admin cambiado (notificación)', 'politeia-learning'),
                'origin' => 'WP',
                'recipient' => 'admin',
                'default_template' => 'templates/emails/admin_email_changed_notification.php',
            ],
            'admin_email_change_confirm' => [
                'id' => 'admin_email_change_confirm',
                'label' => __('Confirmación cambio email admin', 'politeia-learning'),
                'origin' => 'WP',
                'recipient' => 'admin',
                'default_template' => 'templates/emails/admin_email_change_confirm.php',
            ],
            'comment_notification_postauthor' => [
                'id' => 'comment_notification_postauthor',
                'label' => __('Nuevo comentario (autor del post)', 'politeia-learning'),
                'origin' => 'WP',
                'recipient' => 'other',
                'default_template' => 'templates/emails/comment_notification_postauthor.php',
            ],
            'comment_moderation' => [
                'id' => 'comment_moderation',
                'label' => __('Moderación de comentario', 'politeia-learning'),
                'origin' => 'WP',
                'recipient' => 'admin',
                'default_template' => 'templates/emails/comment_moderation.php',
            ],
        ];
    }

    /**
     * @return array<string,array{id:string,label:string,origin:string,default_template:string,recipient?:string}>
     */
    public static function get_woo_test_emails_catalog(): array
    {
        if (!function_exists('WC')) {
            return [];
        }

        $wc = WC();
        if (!$wc || !method_exists($wc, 'mailer')) {
            return [];
        }

        $mailer = $wc->mailer();
        if (!$mailer || !method_exists($mailer, 'get_emails')) {
            return [];
        }

        $emails = $mailer->get_emails();
        if (!is_array($emails)) {
            return [];
        }

        $items = [];
        foreach ($emails as $email) {
            if (!is_object($email)) {
                continue;
            }

            $id = isset($email->id) ? sanitize_key((string) $email->id) : '';
            if ($id === '') {
                continue;
            }

            $title = isset($email->title) ? (string) $email->title : '';
            if ($title === '') {
                $title = $id;
            }

            $template_html = isset($email->template_html) ? (string) $email->template_html : '';

            $items[$id] = [
                'id' => $id,
                'label' => $title,
                'origin' => 'Woo',
                'recipient' => self::guess_wc_email_recipient_kind($id),
                'default_template' => $template_html,
            ];
        }

        return $items;
    }

    public static function guess_wc_email_recipient_kind(string $id): string
    {
        $id = sanitize_key($id);
        if (strpos($id, 'customer_') === 0) {
            return 'customer';
        }

        if (in_array($id, ['new_order', 'cancelled_order', 'failed_order', 'low_stock', 'no_stock', 'backorder'], true)) {
            return 'admin';
        }

        if (in_array($id, ['customer_processing_order', 'customer_completed_order', 'customer_on_hold_order', 'customer_partially_refunded_order', 'customer_payment_retry'], true)) {
            return 'customer';
        }

        return 'other';
    }

    /**
     * Politeia custom WooCommerce emails (sent via wp_mail, not WC_Email).
     *
     * @return array<string,array{id:string,label:string,origin:string,default_template:string,recipient?:string}>
     */
    public static function get_politeia_woo_custom_test_emails_catalog(): array
    {
        return [
            'pl_wc_new_order_custom' => [
                'id' => 'pl_wc_new_order_custom',
                'label' => __('Woo: Pedido confirmado (custom)', 'politeia-learning'),
                'origin' => 'Woo Custom',
                'recipient' => 'customer',
                'default_template' => 'modules/woo/templates/emails/wc-new-order-custom.php',
            ],
            'pl_wc_bank_transfer_on_hold' => [
                'id' => 'pl_wc_bank_transfer_on_hold',
                'label' => __('Woo: Transferencia en espera (custom)', 'politeia-learning'),
                'origin' => 'Woo Custom',
                'recipient' => 'customer',
                'default_template' => 'modules/woo/templates/emails/wc-bank-transfer-on-hold.php',
            ],
            'pl_wc_admin_new_order' => [
                'id' => 'pl_wc_admin_new_order',
                'label' => __('Woo: Nueva venta (admin, custom)', 'politeia-learning'),
                'origin' => 'Woo Custom',
                'recipient' => 'admin',
                'default_template' => 'modules/woo/templates/emails/wc-admin-new-order.php',
            ],
            'pl_wc_admin_cancelled' => [
                'id' => 'pl_wc_admin_cancelled',
                'label' => __('Woo: Pedido cancelado (admin, custom)', 'politeia-learning'),
                'origin' => 'Woo Custom',
                'recipient' => 'admin',
                'default_template' => 'modules/woo/templates/emails/wc-admin-cancelled-order.php',
            ],
            'pl_wc_admin_failed' => [
                'id' => 'pl_wc_admin_failed',
                'label' => __('Woo: Pedido fallido (admin, custom)', 'politeia-learning'),
                'origin' => 'Woo Custom',
                'recipient' => 'admin',
                'default_template' => 'modules/woo/templates/emails/wc-admin-failed-order.php',
            ],
            'pl_wc_customer_partially_refunded' => [
                'id' => 'pl_wc_customer_partially_refunded',
                'label' => __('Woo: Reembolso parcial (custom)', 'politeia-learning'),
                'origin' => 'Woo Custom',
                'recipient' => 'customer',
                'default_template' => 'modules/woo/templates/emails/wc-customer-partially-refunded-order.php',
            ],
            'pl_wc_customer_payment_retry' => [
                'id' => 'pl_wc_customer_payment_retry',
                'label' => __('Woo: Reintento de pago (custom)', 'politeia-learning'),
                'origin' => 'Woo Custom',
                'recipient' => 'customer',
                'default_template' => 'modules/woo/templates/emails/wc-customer-payment-retry.php',
            ],
            'pl_wc_admin_low_stock' => [
                'id' => 'pl_wc_admin_low_stock',
                'label' => __('Woo: Stock bajo (admin, custom)', 'politeia-learning'),
                'origin' => 'Woo Custom',
                'recipient' => 'admin',
                'default_template' => 'modules/woo/templates/emails/wc-admin-low-stock.php',
            ],
            'pl_wc_admin_no_stock' => [
                'id' => 'pl_wc_admin_no_stock',
                'label' => __('Woo: Sin stock (admin, custom)', 'politeia-learning'),
                'origin' => 'Woo Custom',
                'recipient' => 'admin',
                'default_template' => 'modules/woo/templates/emails/wc-admin-no-stock.php',
            ],
            'pl_wc_admin_backorder' => [
                'id' => 'pl_wc_admin_backorder',
                'label' => __('Woo: Backorder (admin, custom)', 'politeia-learning'),
                'origin' => 'Woo Custom',
                'recipient' => 'admin',
                'default_template' => 'modules/woo/templates/emails/wc-admin-backorder.php',
            ],
        ];
    }

    /**
     * @return array<string,array{id:string,label:string,origin:string,default_template:string,recipient?:string}>
     */
    public static function get_learni_test_emails_catalog(): array
    {
        $learni_emails = [
            'learni_course_enroll_free' => [
                'label' => __('Enroll curso gratuito', 'politeia-learning'),
                'template' => 'templates/emails/learni_course_enroll_free.php',
            ],
            'learni_course_purchase' => [
                'label' => __('Compra de curso', 'politeia-learning'),
                'template' => 'templates/emails/learni_course_purchase.php',
            ],
            'learni_first_quiz_completed' => [
                'label' => __('First Quiz completado', 'politeia-learning'),
                'template' => 'templates/emails/learni_first_quiz_completed.php',
            ],
            'learni_final_quiz_completed' => [
                'label' => __('Final Quiz completado (progreso)', 'politeia-learning'),
                'template' => 'templates/emails/learni_final_quiz_completed.php',
            ],
            'learni_cross_eval_completed' => [
                'label' => __('Test Partner completado (cross evaluation)', 'politeia-learning'),
                'template' => 'templates/emails/learni_cross_eval_completed.php',
            ],
            'learni_partner_invitation_sent' => [
                'label' => __('Invitación partner enviada', 'politeia-learning'),
                'template' => 'templates/emails/learni_partner_invitation_sent.php',
            ],
            'learni_partner_invitation_received' => [
                'label' => __('Invitación partner recibida', 'politeia-learning'),
                'template' => 'templates/emails/learni_partner_invitation_received.php',
            ],
        ];

        $items = [];
        foreach ($learni_emails as $id => $data) {
            $items[$id] = [
                'id' => $id,
                'label' => isset($data['label']) ? (string) $data['label'] : $id,
                'origin' => 'Learni',
                'recipient' => 'customer',
                'default_template' => isset($data['template']) && is_string($data['template']) ? $data['template'] : '',
            ];
        }

        return $items;
    }

    public static function get_test_emails_copy_instructions(): string
    {
        return "You are designing an HTML email template. Follow these constraints strictly:\n"
            . "- Use TABLE-based layout (avoid relying on div flex/grid).\n"
            . "- Prefer INLINE styles for critical visuals (especially buttons and backgrounds).\n"
            . "- If you include <style>, keep it simple; some clients override link colors.\n"
            . "- For buttons (<a> links), FORCE white text with inline style: color:#ffffff !important; text-decoration:none; display:inline-block.\n"
            . "- Do not place <div> directly inside <table>/<tr>. Use <td> cells.\n"
            . "- Background colors: do not rely only on body background. Also set bgcolor on the outer table/cell.\n"
            . "- Avoid complex CSS (box-shadow, position, advanced selectors). Many clients ignore them.\n"
            . "- Use these variables if needed: {{site_name}}, {{site_url}}, {{username}}, {{user_email}}, {{subject}}, {{message}} (HTML), {{message_text}}.\n\n"
            . "Skeleton to adapt:\n"
            . "<!doctype html>\n"
            . "<html>\n"
            . "<head>\n"
            . "  <meta charset=\"utf-8\">\n"
            . "  <meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">\n"
            . "  <title>{{subject}}</title>\n"
            . "</head>\n"
            . "<body style=\"margin:0;padding:0;background:#f4f7f9;\">\n"
            . "  <center style=\"width:100%;background:#f4f7f9;\">\n"
            . "    <table role=\"presentation\" width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\" bgcolor=\"#f4f7f9\" style=\"background:#f4f7f9;\">\n"
            . "      <tr>\n"
            . "        <td align=\"center\" style=\"padding:40px 12px;\">\n"
            . "          <table role=\"presentation\" width=\"600\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\" style=\"width:100%;max-width:600px;background:#fff;border-radius:12px;overflow:hidden;\">\n"
            . "            <tr>\n"
            . "              <td style=\"padding:32px 32px 12px;font:700 24px -apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:#111827;\">Politeia</td>\n"
            . "            </tr>\n"
            . "            <tr>\n"
            . "              <td style=\"padding:0 32px 28px;font:400 16px/1.6 -apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:#374151;\">\n"
            . "                {{message}}\n"
            . "                <div style=\"height:16px;line-height:16px;\">&nbsp;</div>\n"
            . "                <a href=\"{{site_url}}\" style=\"background:#111827;color:#ffffff !important;text-decoration:none;display:inline-block;padding:12px 20px;border-radius:8px;font-weight:700;\">Call to action</a>\n"
            . "              </td>\n"
            . "            </tr>\n"
            . "          </table>\n"
            . "        </td>\n"
            . "      </tr>\n"
            . "    </table>\n"
            . "  </center>\n"
            . "</body>\n"
            . "</html>\n";
    }

    public static function get_test_email_template_context(string $key, array $preview): array
    {
        $site_name = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);
        $site_url = home_url();

        $user = wp_get_current_user();
        $username = $user && $user->user_login ? (string) $user->user_login : 'usuario';
        $user_email = $user && $user->user_email ? (string) $user->user_email : (string) get_option('admin_email');

        return [
            '{{key}}' => $key,
            '{{site_name}}' => $site_name,
            '{{site_url}}' => $site_url,
            '{{username}}' => $username,
            '{{user_email}}' => $user_email,
            '{{subject}}' => isset($preview['subject']) ? (string) $preview['subject'] : '',
            '{{message}}' => isset($preview['message_text']) ? nl2br(esc_html((string) $preview['message_text'])) : '',
            '{{message_text}}' => isset($preview['message_text']) ? (string) $preview['message_text'] : '',
        ];
    }
}
