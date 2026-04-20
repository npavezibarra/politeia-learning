<?php
/**
 * Admin UI for Email Log.
 */

if (!defined('ABSPATH')) {
    exit;
}

final class PL_Email_Log_Admin
{
    private static $instance = null;
    private const TAB_EMAIL_LOG = 'email_log';
    private const TAB_TEST_EMAILS = 'test_emails';
    private const TEST_EMAIL_NONCE_ACTION = 'pl_test_emails_nonce';
    private const TEST_EMAIL_TEMPLATES_OPTION = 'pl_test_email_templates';

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        add_action('admin_menu', [$this, 'register_admin_pages'], 110);
        add_action('wp_ajax_pl_get_email_content', [$this, 'ajax_get_email_content']);
        add_action('wp_ajax_pl_get_test_email_preview', [$this, 'ajax_get_test_email_preview']);
        add_action('wp_ajax_pl_send_test_email', [$this, 'ajax_send_test_email']);
        add_action('wp_ajax_pl_get_test_email_template', [$this, 'ajax_get_test_email_template']);
        add_action('wp_ajax_pl_save_test_email_template', [$this, 'ajax_save_test_email_template']);
        add_action('wp_ajax_pl_set_test_email_template_mode', [$this, 'ajax_set_test_email_template_mode']);
    }

    public function register_admin_pages()
    {
        $parent_slug = 'politeia-learning';

        add_submenu_page(
            $parent_slug,
            __('Registro de Emails', 'politeia-learning'),
            __('Email Log', 'politeia-learning'),
            'manage_options',
            'pl-email-log',
            [$this, 'render_admin_page']
        );
    }

    /**
     * AJAX handler to get email content by ID.
     */
    public function ajax_get_email_content()
    {
        check_ajax_referer('pl_email_log_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $id = isset($_GET['id']) ? absint($_GET['id']) : 0;
        if (!$id) {
            wp_send_json_error('Invalid ID');
        }

        $log = PL_Email_Log_DB::get_instance()->get_log($id);
        if (!$log) {
            wp_send_json_error('Log not found');
        }

        echo (string) $log->content;
        exit;
    }

    public function ajax_get_test_email_preview()
    {
        check_ajax_referer(self::TEST_EMAIL_NONCE_ACTION, 'nonce');

        if (!current_user_can('manage_options')) {
            status_header(403);
            echo $this->render_test_email_preview_error_html(__('No autorizado.', 'politeia-learning')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            exit;
        }

        $key = isset($_GET['key']) ? sanitize_key($_GET['key']) : '';
        if (!$key) {
            status_header(400);
            echo $this->render_test_email_preview_error_html(__('Key inválida.', 'politeia-learning')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            exit;
        }

        $preview = $this->get_test_email_preview($key);
        if (empty($preview)) {
            status_header(404);
            echo $this->render_test_email_preview_error_html(__('Preview no encontrado.', 'politeia-learning')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            exit;
        }

        $template = $this->get_test_email_template_settings($key);
        $use_custom = !empty($template['enabled']) && !empty($template['template']);

        if ($use_custom) {
            echo $this->build_custom_email_html($key, $preview, (string) $template['template']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            exit;
        }

        $traditional_html = $this->get_traditional_email_html_for_key($key, $preview);
        if ($traditional_html !== '') {
            echo $traditional_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            exit;
        }

        echo $this->render_test_email_preview_html($preview); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        exit;
    }

    public function ajax_send_test_email()
    {
        check_ajax_referer(self::TEST_EMAIL_NONCE_ACTION, 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $key = isset($_POST['key']) ? sanitize_key($_POST['key']) : '';
        if (!$key) {
            wp_send_json_error('Invalid key');
        }

        $preview = $this->get_test_email_preview($key);
        if (empty($preview)) {
            wp_send_json_error('Email not found');
        }

        $user = wp_get_current_user();
        $to = is_email($user->user_email) ? $user->user_email : get_option('admin_email');
        if (!is_email($to)) {
            wp_send_json_error('Invalid recipient');
        }

        $subject = sprintf('[TEST] %s', (string) $preview['subject']);
        $template = $this->get_test_email_template_settings($key);
        $use_custom = !empty($template['enabled']) && !empty($template['template']);

        if (!$use_custom && $key === 'new_user_user' && class_exists('PL_Email')) {
            $display_name = $user && !empty($user->display_name) ? (string) $user->display_name : '';
            $token = bin2hex(random_bytes(32));
            $verification_url = add_query_arg([
                'pl_auth_action' => 'confirm',
                'email' => $to,
                'token' => $token,
                'redirect_to' => home_url('/'),
            ], home_url('/'));

            $switched_locale = switch_to_user_locale(get_current_user_id());
            $sent = (bool) PL_Email::send_auth_confirmation($to, $display_name, $verification_url, $token);
            if ($switched_locale) {
                restore_previous_locale();
            }
            if (!$sent) {
                wp_send_json_error('Send failed');
            }

            wp_send_json_success(['to' => $to]);
        }

        if (!$use_custom && $key === 'password_reset' && class_exists('PL_Email')) {
            $switched_locale = switch_to_user_locale(get_current_user_id());

            $dummy_key = bin2hex(random_bytes(16));
            $reset_url = add_query_arg([
                'key' => $dummy_key,
                'login' => $user->user_login ?: 'user',
            ], home_url('/restablecer-contrasena/'));

            $html = (string) PL_Email::render('password-reset', [
                'user_login' => $user->user_login ?: 'user',
                'reset_url' => $reset_url,
            ]);

            if ($switched_locale) {
                restore_previous_locale();
            }

            $sent = wp_mail($to, $subject, $html, ['Content-Type: text/html; charset=UTF-8']);
            if (!$sent) {
                wp_send_json_error('Send failed');
            }

            wp_send_json_success(['to' => $to]);
        }

        if (!$use_custom) {
            $traditional_html = $this->get_traditional_email_html_for_key($key, $preview);
            if ($traditional_html !== '') {
                $sent = wp_mail($to, $subject, $traditional_html, ['Content-Type: text/html; charset=UTF-8']);
                if (!$sent) {
                    wp_send_json_error('Send failed');
                }
                wp_send_json_success(['to' => $to]);
            }
        }

        if ($use_custom) {
            $message = $this->build_custom_email_html($key, $preview, (string) $template['template']);
            $headers = ['Content-Type: text/html; charset=UTF-8'];
        } else {
            $message_html = isset($preview['message_html']) ? (string) $preview['message_html'] : '';
            $message_text = isset($preview['message_text']) ? (string) $preview['message_text'] : '';

            if ($message_html !== '') {
                $message = $message_html;
                $headers = ['Content-Type: text/html; charset=UTF-8'];
            } else {
                $message = $message_text;
                $headers = [];
            }
        }

        $sent = wp_mail($to, $subject, $message, $headers);
        if (!$sent) {
            wp_send_json_error('Send failed');
        }

	        wp_send_json_success(['to' => $to]);
	    }

	    private function render_politeia_woo_email_preview(string $slug, string $path): string
	    {
	        if (!function_exists('wc_get_orders')) {
	            return '<div style="padding:18px;font-family:system-ui;">WooCommerce no está disponible para previsualizar este correo.</div>';
	        }

	        $orders = wc_get_orders([
	            'limit' => 1,
	            'orderby' => 'date',
	            'order' => 'DESC',
	            'return' => 'objects',
	        ]);
	        $order = is_array($orders) && !empty($orders) ? $orders[0] : null;

	        // Best-effort fallbacks if there are no orders yet.
	        if (!$order instanceof WC_Order) {
	            return '<div style="padding:18px;font-family:system-ui;">No hay órdenes en WooCommerce para previsualizar este correo.</div>';
	        }

	        $logo_url = '';
	        $access_links = [];
	        $view = 'physical';

	        if (class_exists('PL_Woo_Emails')) {
	            $view = PL_Woo_Emails::identify_order_type($order);
	            $access_links = PL_Woo_Emails::get_access_links($order);
	        }

	        $bank_details = [];
	        if ($slug === 'wc-bank-transfer-on-hold') {
	            $method_id = (string) $order->get_payment_method();
	            if ($method_id === 'bacs' && function_exists('WC') && WC() && isset(WC()->payment_gateways) && method_exists(WC()->payment_gateways, 'get_available_payment_gateways')) {
	                $gateways = WC()->payment_gateways->get_available_payment_gateways();
	                if (is_array($gateways) && isset($gateways['bacs'])) {
	                    $bacs = $gateways['bacs'];
	                    $accounts = is_object($bacs) && isset($bacs->account_details) ? $bacs->account_details : null;
	                    if (is_array($accounts) && !empty($accounts) && is_array($accounts[0])) {
	                        $bank_details = $accounts[0];
	                    }
	                }
	            }
	        }

	        if (in_array($slug, ['wc-admin-low-stock', 'wc-admin-no-stock', 'wc-admin-backorder'], true)) {
	            $product = null;
	            if (function_exists('wc_get_products')) {
	                $products = wc_get_products(['limit' => 1, 'orderby' => 'date', 'order' => 'DESC']);
	                if (is_array($products) && !empty($products) && $products[0] instanceof WC_Product) {
	                    $product = $products[0];
	                }
	            }
	            if (!$product instanceof WC_Product) {
	                return '<div style="padding:18px;font-family:system-ui;">No hay productos para previsualizar este correo.</div>';
	            }

	            if (!empty($access_links)) {
	                unset($access_links);
	            }
	            $order_for_backorder = $slug === 'wc-admin-backorder' ? $order : null;

	            ob_start();
	            // Variables expected by the templates.
	            $logo_url = $logo_url;
	            $product = $product;
	            $order = $order_for_backorder;
	            include $path;
	            return (string) ob_get_clean();
	        }

	        $payment_url = '';
	        if ($slug === 'wc-customer-payment-retry' && method_exists($order, 'get_checkout_payment_url')) {
	            $payment_url = (string) $order->get_checkout_payment_url();
	        }

	        $refund = null;
	        if ($slug === 'wc-customer-partially-refunded-order') {
	            $refunds = $order->get_refunds();
	            if (is_array($refunds) && !empty($refunds) && $refunds[0] instanceof WC_Order_Refund) {
	                $refund = $refunds[0];
	            }
	        }

	        ob_start();
	        // Variables expected by the templates.
	        $logo_url = $logo_url;
	        $access_links = $access_links;
	        $view = $view;
	        $bank_details = $bank_details;
	        $payment_url = $payment_url;
	        $refund = $refund;
	        include $path;
	        return (string) ob_get_clean();
	    }

	    private function get_traditional_email_html_for_key(string $key, array $preview = []): string
	    {
	        $catalog = $this->get_test_emails_catalog();
	        if (!isset($catalog[$key]) || !is_array($catalog[$key])) {
            return '';
        }

        $default_template = isset($catalog[$key]['default_template']) ? trim((string) $catalog[$key]['default_template']) : '';
        if ($default_template === '') {
            return '';
        }

	        $template_kind = '';
	        if (preg_match('#^templates/emails/([a-z0-9\\-_]+)\\.php$#i', $default_template, $m)) {
	            $template_kind = 'pl_core';
	        } elseif (preg_match('#^modules/woo/templates/emails/([a-z0-9\\-_]+)\\.php$#i', $default_template, $m)) {
	            $template_kind = 'pl_woo';
	        } else {
	            // Other templates (Woo core templates, theme overrides) are not supported here.
	            return '';
	        }

        $slug = sanitize_key((string) $m[1]);
        if ($slug === '' || !defined('PL_PATH')) {
            return '';
        }

	        $path = $template_kind === 'pl_woo'
	            ? PL_PATH . 'modules/woo/templates/emails/' . $slug . '.php'
	            : PL_PATH . 'templates/emails/' . $slug . '.php';
	        if (!file_exists($path)) {
	            return '';
	        }

	        if ($template_kind === 'pl_woo') {
	            return $this->render_politeia_woo_email_preview($slug, $path);
	        }

	        if (!class_exists('PL_Email')) {
	            ob_start();
	            include $path;
	            return (string) ob_get_clean();
	        }

        // Provide best-effort variables for templates that expect them.
        if ($slug === 'password-reset') {
            $user = wp_get_current_user();
            $username = $user && $user->user_login ? (string) $user->user_login : 'usuario';
            $dummy_key = bin2hex(random_bytes(16));
            $reset_url = add_query_arg([
                'key' => $dummy_key,
                'login' => $username,
            ], home_url('/restablecer-contrasena/'));

            return (string) PL_Email::render('password-reset', [
                'user_login' => $username,
                'reset_url' => $reset_url,
            ]);
        }

        if ($slug === 'auth-confirmation') {
            $user = wp_get_current_user();
            $username = $user && $user->user_login ? (string) $user->user_login : 'usuario';
            $user_email = $user && $user->user_email ? (string) $user->user_email : (string) get_option('admin_email');
            $display_name = $user && !empty($user->display_name) ? (string) $user->display_name : $username;
            $token = bin2hex(random_bytes(32));
            $verification_url = add_query_arg([
                'pl_auth_action' => 'confirm',
                'email' => $user_email,
                'token' => $token,
                'redirect_to' => home_url('/'),
            ], home_url('/'));

            return (string) PL_Email::render('auth-confirmation', [
                'user_name' => $display_name,
                'verification_url' => $verification_url,
                'token' => $token,
            ]);
        }

        if ($slug === 'course-partner-invite') {
            $site_name = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);
            $user = wp_get_current_user();
            $username = $user && $user->user_login ? (string) $user->user_login : 'usuario';
            $inviter_display = $user && !empty($user->display_name) ? (string) $user->display_name : ($site_name !== '' ? $site_name : 'Politeia');

            return (string) PL_Email::render('course-partner-invite', [
                'invitee_name' => $username,
                'inviter_name' => $inviter_display,
                'course_name' => __('Curso de prueba', 'politeia-learning'),
                'accept_url' => add_query_arg(['pl_invite' => 'accept'], home_url('/')),
            ]);
        }

        if (in_array($slug, ['learni_partner_invitation_sent', 'learni_partner_invitation_received'], true)) {
            $site_name = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);
            $user = wp_get_current_user();
            $inviter_display = $user && !empty($user->display_name) ? (string) $user->display_name : ($site_name !== '' ? $site_name : 'Politeia');

            return (string) PL_Email::render($slug, [
                'invitee_name' => __('Partner', 'politeia-learning'),
                'inviter_name' => $inviter_display,
                'course_name' => __('Curso de prueba', 'politeia-learning'),
                'accept_url' => add_query_arg(['pl_invite' => 'accept'], home_url('/')),
            ]);
        }

        if ($slug === 'learni_first_quiz_completed') {
            $percentage = isset($preview['percentage']) ? (int) $preview['percentage'] : random_int(0, 100);
            $percentage = max(0, min(100, $percentage));

            return (string) PL_Email::render('learni_first_quiz_completed', [
                'percentage' => $percentage,
            ]);
        }

        if ($slug === 'learni_final_quiz_completed') {
            $percentage_first = isset($preview['percentage_first']) ? (int) $preview['percentage_first'] : random_int(0, 100);
            $percentage_final = isset($preview['percentage_final']) ? (int) $preview['percentage_final'] : random_int(0, 100);
            $percentage_first = max(0, min(100, $percentage_first));
            $percentage_final = max(0, min(100, $percentage_final));

            return (string) PL_Email::render('learni_final_quiz_completed', [
                'percentage_first' => $percentage_first,
                'percentage_final' => $percentage_final,
            ]);
        }

        if ($slug === 'learni_cross_eval_completed') {
            // Dummy data used only for the Test Emails previewer (to visualize colors/variation/time blocks).
            $percentage_first = isset($preview['percentage_first']) ? (int) $preview['percentage_first'] : 11;
            $percentage_final = isset($preview['percentage_final']) ? (int) $preview['percentage_final'] : 20;
            $percentage_first = max(0, min(100, $percentage_first));
            $percentage_final = max(0, min(100, $percentage_final));

            return (string) PL_Email::render('learni_cross_eval_completed', [
                'course_name' => __('Curso de prueba', 'politeia-learning'),
                'tester_name' => __('Partner', 'politeia-learning'),
                'tested_name' => __('Estudiante', 'politeia-learning'),
                'recipient_role' => 'tested',
                'percentage_first' => $percentage_first,
                'percentage_final' => $percentage_final,
                'first_date_label' => date_i18n('d M Y', strtotime('-66 days')),
                'final_date_label' => date_i18n('d M Y'),
                'duration_days' => 66,
                'cta_url' => add_query_arg('learni_open_cert', '1', home_url('/')),
                'cta_label' => __('VER CERTIFICADO', 'politeia-learning'),
            ]);
        }

        return (string) PL_Email::render($slug, []);
    }

    public function ajax_get_test_email_template()
    {
        check_ajax_referer(self::TEST_EMAIL_NONCE_ACTION, 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $key = isset($_GET['key']) ? sanitize_key($_GET['key']) : '';
        if (!$key) {
            wp_send_json_error('Invalid key');
        }

        $template = $this->get_test_email_template_settings($key);

        wp_send_json_success([
            'enabled' => !empty($template['enabled']),
            'template' => (string) $template['template'],
        ]);
    }

    public function ajax_save_test_email_template()
    {
        check_ajax_referer(self::TEST_EMAIL_NONCE_ACTION, 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $key = isset($_POST['key']) ? sanitize_key($_POST['key']) : '';
        if (!$key) {
            wp_send_json_error('Invalid key');
        }

        $template = isset($_POST['template']) ? (string) wp_unslash($_POST['template']) : '';
        $enabled = isset($_POST['enabled']) ? (bool) absint($_POST['enabled']) : false;

        $templates = $this->get_test_email_templates_option();
        $templates[$key] = [
            'enabled' => $enabled,
            'template' => $template,
        ];

        update_option(self::TEST_EMAIL_TEMPLATES_OPTION, $templates, false);

        wp_send_json_success(['saved' => true]);
    }

    public function ajax_set_test_email_template_mode()
    {
        check_ajax_referer(self::TEST_EMAIL_NONCE_ACTION, 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $key = isset($_POST['key']) ? sanitize_key($_POST['key']) : '';
        if (!$key) {
            wp_send_json_error('Invalid key');
        }

        $mode = isset($_POST['mode']) ? sanitize_key($_POST['mode']) : 'traditional';
        $enabled = ('custom' === $mode);

        $templates = $this->get_test_email_templates_option();
        $existing = isset($templates[$key]) && is_array($templates[$key]) ? $templates[$key] : [];
        $templates[$key] = [
            'enabled' => $enabled,
            'template' => isset($existing['template']) ? (string) $existing['template'] : '',
        ];

        update_option(self::TEST_EMAIL_TEMPLATES_OPTION, $templates, false);

        wp_send_json_success(['enabled' => $enabled]);
    }

    private function get_active_tab(): string
    {
        $tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : self::TAB_EMAIL_LOG;

        $allowed_tabs = [
            self::TAB_EMAIL_LOG,
            self::TAB_TEST_EMAILS,
        ];

        if (!in_array($tab, $allowed_tabs, true)) {
            return self::TAB_EMAIL_LOG;
        }

        return $tab;
    }

    private function render_tabs(string $active_tab): void
    {
        $base_url = menu_page_url('pl-email-log', false);
        $tabs = [
            self::TAB_EMAIL_LOG => __('Registro de Emails', 'politeia-learning'),
            self::TAB_TEST_EMAILS => __('Test Emails', 'politeia-learning'),
        ];

        echo '<h2 class="nav-tab-wrapper">';
        foreach ($tabs as $tab_key => $tab_label) {
            $classes = ['nav-tab'];
            if ($tab_key === $active_tab) {
                $classes[] = 'nav-tab-active';
            }

            printf(
                '<a href="%s" class="%s">%s</a>',
                esc_url(add_query_arg('tab', $tab_key, $base_url)),
                esc_attr(implode(' ', $classes)),
                esc_html($tab_label)
            );
        }
        echo '</h2>';
    }

	    /**
	     * Unified catalog for the Test Emails UI.
	     *
	     * @return array<string,array{id:string,label:string,origin:string,default_template:string,recipient?:string}>
	     */
	    private function get_test_emails_catalog(): array
	    {
	        $items = [];

        foreach ($this->get_wp_test_emails_catalog() as $key => $item) {
            $items[$key] = $item;
        }

	        foreach ($this->get_woo_test_emails_catalog() as $key => $item) {
	            if (!isset($items[$key])) {
	                $items[$key] = $item;
	            }
	        }

	        foreach ($this->get_politeia_woo_custom_test_emails_catalog() as $key => $item) {
	            if (!isset($items[$key])) {
	                $items[$key] = $item;
	            }
	        }

	        foreach ($this->get_learni_test_emails_catalog() as $key => $item) {
	            if (!isset($items[$key])) {
	                $items[$key] = $item;
	            }
	        }

        return $items;
    }

	    /**
	     * @return array<string,array{id:string,label:string,origin:string,default_template:string,recipient?:string}>
	     */
	    private function get_wp_test_emails_catalog(): array
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
	    private function get_woo_test_emails_catalog(): array
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
	                'recipient' => $this->guess_wc_email_recipient_kind($id),
	                'default_template' => $template_html,
	            ];
	        }

	        return $items;
	    }

	    private function guess_wc_email_recipient_kind(string $id): string
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
	    private function get_politeia_woo_custom_test_emails_catalog(): array
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
	    private function get_learni_test_emails_catalog(): array
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

    private function get_default_template_label(array $item): string
    {
        $path = isset($item['default_template']) ? trim((string) $item['default_template']) : '';
        return $path !== '' ? $path : __('No existe', 'politeia-learning');
    }

    private function get_test_emails_copy_instructions(): string
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

    private function get_test_email_templates_option(): array
    {
        $value = get_option(self::TEST_EMAIL_TEMPLATES_OPTION, []);
        return is_array($value) ? $value : [];
    }

    private function get_test_email_template_settings(string $key): array
    {
        $templates = $this->get_test_email_templates_option();
        $template = isset($templates[$key]) && is_array($templates[$key]) ? $templates[$key] : [];

        $legacy_html = isset($template['html']) ? (string) $template['html'] : '';
        $legacy_css = isset($template['css']) ? (string) $template['css'] : '';
        $legacy_merged = '';
        if ($legacy_html !== '' || $legacy_css !== '') {
            $legacy_merged = ($legacy_css !== '' ? '<style>' . $legacy_css . '</style>' : '') . $legacy_html;
        }

        return [
            'enabled' => !empty($template['enabled']),
            'template' => isset($template['template']) ? (string) $template['template'] : $legacy_merged,
        ];
    }

    private function get_test_email_preview(string $key): array
    {
        $catalog = $this->get_test_emails_catalog();
        if (!isset($catalog[$key]) || !is_array($catalog[$key])) {
            return [];
        }

        $origin = isset($catalog[$key]['origin']) ? (string) $catalog[$key]['origin'] : '';

        if ($origin === 'WP') {
            return $this->get_wp_test_email_preview($key);
        }

        if ($origin === 'Woo') {
            return $this->get_woo_test_email_preview($key, $catalog[$key]);
        }

        if ($origin === 'Learni') {
            return $this->get_learni_test_email_preview($key, $catalog[$key]);
        }

        return $this->get_not_implemented_test_email_preview($key);
    }

    private function get_not_implemented_test_email_preview(string $key): array
    {
        return [
            'subject' => sprintf('[%s] %s', wp_specialchars_decode(get_option('blogname'), ENT_QUOTES), $key),
            'message_text' => sprintf('Preview no implementado para "%s".', $key),
        ];
    }

    private function get_wp_test_email_preview(string $key): array
    {
        $site_name = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);
        $site_url = home_url();

        $user = wp_get_current_user();
        $username = $user && $user->user_login ? (string) $user->user_login : 'usuario';
        $user_email = $user && $user->user_email ? (string) $user->user_email : (string) get_option('admin_email');

        if ($key === 'new_user_user' && class_exists('PL_Email')) {
            $switched_locale = switch_to_user_locale(get_current_user_id());
            $token = bin2hex(random_bytes(32));
            $verification_url = add_query_arg([
                'pl_auth_action' => 'confirm',
                'email' => $user_email,
                'token' => $token,
                'redirect_to' => home_url('/'),
            ], home_url('/'));

            $display_name = $user && !empty($user->display_name) ? (string) $user->display_name : $username;

            $html = (string) PL_Email::render('auth-confirmation', [
                'user_name' => $display_name,
                'verification_url' => $verification_url,
                'token' => $token,
            ]);

            if ($switched_locale) {
                restore_previous_locale();
            }

            if ('' === trim($html)) {
                $html = sprintf(
                    '<p>%s</p><p><a href="%s">%s</a></p><p>%s: <code>%s</code></p>',
                    esc_html(sprintf(__('Hi %s, please confirm your account.', 'politeia-learning'), $display_name !== '' ? $display_name : __('there', 'politeia-learning'))),
                    esc_url($verification_url),
                    esc_html__('Confirm account', 'politeia-learning'),
                    esc_html__('Token', 'politeia-learning'),
                    esc_html($token)
                );
            }

            $allowed_html = $this->get_email_template_allowed_html();

            return [
                'subject' => __('Confirm your Politeia account', 'politeia-learning'),
                'message_html' => wp_kses($html, $allowed_html),
                'message_text' => wp_strip_all_tags($html),
            ];
        }

        if ($key === 'password_reset' && class_exists('PL_Email')) {
            $switched_locale = switch_to_user_locale(get_current_user_id());

            $dummy_key = bin2hex(random_bytes(16));
            $reset_url = add_query_arg([
                'key' => $dummy_key,
                'login' => $username,
            ], home_url('/restablecer-contrasena/'));

            $html = (string) PL_Email::render('password-reset', [
                'user_login' => $username,
                'reset_url' => $reset_url,
            ]);

            if ($switched_locale) {
                restore_previous_locale();
            }

            $allowed_html = $this->get_email_template_allowed_html();

            return [
                'subject' => __('Reset Password', 'politeia-learning'),
                'message_html' => wp_kses($html, $allowed_html),
                'message_text' => wp_strip_all_tags($html),
            ];
        }

        $samples = [
            'new_user_admin' => [
                'subject' => sprintf('[%s] Nuevo registro de usuario', $site_name),
                'message_text' => "Se ha registrado un nuevo usuario en {$site_name}.\n\nUsuario: {$username}\nEmail: {$user_email}\n\n{$site_url}",
            ],
            'new_user_user' => [
                'subject' => sprintf('[%s] Bienvenido/a', $site_name),
                'message_text' => "Hola {$username},\n\nTu cuenta en {$site_name} fue creada.\n\nPuedes ingresar aquí:\n{$site_url}\n",
            ],
            'password_reset' => [
                'subject' => sprintf('[%s] Reset de contraseña', $site_name),
                'message_text' => "Hola {$username},\n\nRecibimos una solicitud para restablecer tu contraseña.\n\nSi fuiste tú, usa este enlace:\n{$site_url}/wp-login.php?action=rp\n\nSi no fuiste tú, ignora este correo.",
            ],
            'password_change_user' => [
                'subject' => sprintf('[%s] Contraseña cambiada', $site_name),
                'message_text' => "Hola {$username},\n\nTu contraseña fue cambiada.\n\nSi no fuiste tú, contacta al administrador del sitio.\n\n{$site_url}",
            ],
            'password_change_admin' => [
                'subject' => sprintf('[%s] Contraseña cambiada', $site_name),
                'message_text' => "Aviso:\n\nEl usuario {$username} cambió su contraseña.\n\n{$site_url}",
            ],
            'email_change_user' => [
                'subject' => sprintf('[%s] Email cambiado', $site_name),
                'message_text' => "Hola {$username},\n\nEl email de tu cuenta fue cambiado.\n\nSi no fuiste tú, contacta al administrador del sitio.\n\n{$site_url}",
            ],
            'admin_email_changed_notification' => [
                'subject' => sprintf('[%s] Admin Email Changed', $site_name),
                'message_text' => "Este aviso confirma que el email de administración del sitio fue cambiado.\n\nSitio: {$site_name}\nURL: {$site_url}",
            ],
            'admin_email_change_confirm' => [
                'subject' => sprintf('[%s] New Admin Email Address', $site_name),
                'message_text' => "Se solicitó cambiar el email de administración del sitio.\n\nPara confirmar, visita:\n{$site_url}/wp-admin/options.php\n",
            ],
            'comment_notification_postauthor' => [
                'subject' => sprintf('[%s] Nuevo comentario', $site_name),
                'message_text' => "Se ha publicado un nuevo comentario en una entrada.\n\n{$site_url}",
            ],
            'comment_moderation' => [
                'subject' => sprintf('[%s] Comentario pendiente de moderación', $site_name),
                'message_text' => "Hay un comentario pendiente de moderación.\n\n{$site_url}/wp-admin/edit-comments.php",
            ],
        ];

        return isset($samples[$key]) ? $samples[$key] : [];
    }

    private function get_wc_email_by_id(string $id)
    {
        $id = sanitize_key($id);
        if ($id === '' || !function_exists('WC')) {
            return null;
        }

        $wc = WC();
        if (!$wc || !method_exists($wc, 'mailer')) {
            return null;
        }

        $mailer = $wc->mailer();
        if (!$mailer || !method_exists($mailer, 'get_emails')) {
            return null;
        }

        $emails = $mailer->get_emails();
        if (!is_array($emails)) {
            return null;
        }

        foreach ($emails as $email) {
            if (!is_object($email) || !isset($email->id)) {
                continue;
            }

            if (sanitize_key((string) $email->id) === $id) {
                return $email;
            }
        }

        return null;
    }

    private function get_woo_test_email_preview(string $key, array $item): array
    {
        $email = $this->get_wc_email_by_id($key);

        $subject = isset($item['label']) ? (string) $item['label'] : $key;
        $message_html = '';
        $message_text = '';

        if ($email && is_object($email)) {
            try {
                if (method_exists($email, 'get_subject')) {
                    $maybe_subject = (string) $email->get_subject();
                    if ($maybe_subject !== '') {
                        $subject = $maybe_subject;
                    }
                }
            } catch (Throwable $e) {
                unset($e);
            }

            try {
                if (method_exists($email, 'get_content_html')) {
                    $message_html = (string) $email->get_content_html();
                }
            } catch (Throwable $e) {
                unset($e);
                $message_html = '';
            }

            if ($message_html === '') {
                try {
                    if (method_exists($email, 'get_content_plain')) {
                        $message_text = (string) $email->get_content_plain();
                    }
                } catch (Throwable $e) {
                    unset($e);
                    $message_text = '';
                }
            }
        }

        $template_path = isset($item['default_template']) ? trim((string) $item['default_template']) : '';

        if ($message_html === '' && $message_text === '') {
            $message_text = "WooCommerce email: {$key}\n"
                . "Título: " . (isset($item['label']) ? (string) $item['label'] : $key) . "\n"
                . "Template: " . ($template_path !== '' ? $template_path : __('No existe', 'politeia-learning')) . "\n\n"
                . "Nota: este es un preview de referencia (no se cargó el contenido real del email).";
        }

        $allowed_html = $this->get_email_template_allowed_html();

        $subject = $subject !== '' ? $subject : $key;
        $subject = wp_strip_all_tags($subject);

        if ($message_html !== '') {
            $message_html = wp_kses($message_html, $allowed_html);
            if ($message_text === '') {
                $message_text = wp_strip_all_tags($message_html);
            }
        }

        return [
            'subject' => $subject,
            'message_html' => $message_html,
            'message_text' => $message_text,
        ];
    }

    private function get_learni_test_email_preview(string $key, array $item): array
    {
        $site_name = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);
        $site_url = home_url();

        $user = wp_get_current_user();
        $username = $user && $user->user_login ? (string) $user->user_login : 'usuario';

        if (in_array($key, ['learni_partner_invitation_sent', 'learni_partner_invitation_received'], true) && class_exists('PL_Email')) {
            $accept_url = add_query_arg(['pl_invite' => 'accept'], home_url('/'));
            $course_name = __('Curso de prueba', 'politeia-learning');
            $inviter_display = $user && !empty($user->display_name) ? (string) $user->display_name : $site_name;
            $invitee_display = __('Partner', 'politeia-learning');

            $template_slug = $key === 'learni_partner_invitation_sent'
                ? 'learni_partner_invitation_sent'
                : 'learni_partner_invitation_received';

            $html = (string) PL_Email::render($template_slug, [
                'invitee_name' => $invitee_display,
                'inviter_name' => $inviter_display,
                'course_name' => $course_name,
                'accept_url' => $accept_url,
            ]);

            if ('' === trim($html)) {
                $html = sprintf(
                    '<p>%s</p><p><a href="%s">%s</a></p>',
                    esc_html__('Te invitaron como partner de un curso.', 'politeia-learning'),
                    esc_url($accept_url),
                    esc_html__('Aceptar invitación', 'politeia-learning')
                );
            }

            $allowed_html = $this->get_email_template_allowed_html();
            $safe_html = wp_kses($html, $allowed_html);

            return [
                'subject' => sprintf('[%s] %s', $site_name, isset($item['label']) ? (string) $item['label'] : $key),
                'message_html' => $safe_html,
                'message_text' => wp_strip_all_tags($safe_html),
            ];
        }

        if ($key === 'learni_final_quiz_completed') {
            $percentage_first = random_int(0, 100);
            $percentage_final = random_int(0, 100);

            $delta = $percentage_final - $percentage_first;
            $delta_abs = abs($delta);

            if ($delta > 0) {
                $subject = '¡Excelente progreso! Has superado tu marca inicial 🚀';
                $variation_label = "+{$delta_abs}%";
            } elseif ($delta === 0) {
                $subject = 'Has mantenido tu nivel de conocimientos 📊';
                $variation_label = '0%';
            } else {
                $subject = 'Evaluación final completada: analicemos tus resultados 🔍';
                $variation_label = "-{$delta_abs}%";
            }

            return [
                'subject' => $subject,
                'percentage_first' => $percentage_first,
                'percentage_final' => $percentage_final,
                'message_text' => "First Quiz: {$percentage_first}%\nFinal Quiz: {$percentage_final}%\nVariación: {$variation_label}",
            ];
        }

        if ($key === 'learni_first_quiz_completed') {
            $percentage = random_int(0, 100);

            if ($percentage >= 90) {
                $subject = '¡Excelente desempeño en tu quiz! 🚀';
            } elseif ($percentage >= 70) {
                $subject = '¡Buen trabajo! Vas por muy buen camino 📈';
            } else {
                $subject = '¡Quiz completado! Sigue fortaleciendo tus conocimientos 📚';
            }

            return [
                'subject' => $subject,
                'percentage' => $percentage,
                'message_text' => "First Quiz: {$percentage}%",
            ];
        }

        $label = isset($item['label']) ? (string) $item['label'] : $key;

        return [
            'subject' => sprintf('[%s] %s', $site_name, $label),
            'message_text' => "Evento Learni: {$key}\n\n"
                . "Hola {$username},\n\n"
                . "Este es un correo de prueba (registro/preview) para \"{$label}\".\n\n"
                . $site_url,
        ];
    }

    private function build_custom_email_html(string $key, array $preview, string $template): string
    {
        $context = $this->get_test_email_template_context($key, $preview);

        $raw_html = $this->replace_template_vars($template, $context);

        $css_chunks = [];
        if (preg_match_all('/<style[^>]*>(.*?)<\/style\s*>/is', $raw_html, $matches)) {
            foreach ($matches[1] as $css_chunk) {
                $css_chunks[] = (string) $css_chunk;
            }
        }

        $body_attrs = '';
        if (preg_match('/<body([^>]*)>/i', $raw_html, $body_attr_matches)) {
            $body_attrs = (string) $body_attr_matches[1];
        }

        $html_without_style = (string) preg_replace('/<style[^>]*>.*?<\/style\s*>/is', '', $raw_html);

        $body_html = $html_without_style;
        if (preg_match('/<body[^>]*>(.*)<\/body\s*>/is', $html_without_style, $body_matches)) {
            $body_html = (string) $body_matches[1];
        }

        $allowed_html = $this->get_email_template_allowed_html();
        $safe_body = wp_kses($body_html, $allowed_html);

        $safe_css = trim(implode("\n", $css_chunks));
        $safe_css = (string) preg_replace('/<\/style\s*>/i', '', $safe_css);
        $safe_css = trim($safe_css);

        $title = isset($preview['subject']) ? esc_html((string) $preview['subject']) : 'Email';
        $style_tag = $safe_css !== '' ? '<style>' . $safe_css . '</style>' : '';

        $safe_body_attrs = '';
        if ($body_attrs !== '') {
            $safe_body_attrs = (string) wp_kses(
                '<body ' . $body_attrs . '></body>',
                ['body' => ['style' => true, 'bgcolor' => true]]
            );
            $safe_body_attrs = (string) preg_replace('/^<body\s*|><\/body>$/', '', $safe_body_attrs);
            $safe_body_attrs = trim($safe_body_attrs);
            $safe_body_attrs = $safe_body_attrs !== '' ? ' ' . $safe_body_attrs : '';
        }

        return '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">' . $style_tag . '<title>' . $title . '</title></head><body' . $safe_body_attrs . '>' . $safe_body . '</body></html>';
    }

    private function get_test_email_template_context(string $key, array $preview): array
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

    private function replace_template_vars(string $content, array $context): string
    {
        if ($content === '' || empty($context)) {
            return $content;
        }

        return strtr($content, $context);
    }

    private function get_email_template_allowed_html(): array
    {
        $allowed = wp_kses_allowed_html('post');
        $allowed['style'] = [];
        $allowed['center'] = [
            'class' => true,
            'dir' => true,
            'id' => true,
            'lang' => true,
            'style' => true,
            'title' => true,
            'width' => true,
        ];
        $allowed['table'] = [
            'align' => true,
            'bgcolor' => true,
            'border' => true,
            'cellpadding' => true,
            'cellspacing' => true,
            'class' => true,
            'dir' => true,
            'id' => true,
            'lang' => true,
            'style' => true,
            'summary' => true,
            'width' => true,
        ];
        $allowed['td'] = [
            'abbr' => true,
            'align' => true,
            'axis' => true,
            'bgcolor' => true,
            'class' => true,
            'colspan' => true,
            'dir' => true,
            'headers' => true,
            'height' => true,
            'id' => true,
            'lang' => true,
            'nowrap' => true,
            'rowspan' => true,
            'scope' => true,
            'style' => true,
            'valign' => true,
            'width' => true,
        ];
        $allowed['th'] = $allowed['td'];
        $allowed['tr'] = [
            'align' => true,
            'bgcolor' => true,
            'class' => true,
            'dir' => true,
            'id' => true,
            'lang' => true,
            'style' => true,
            'valign' => true,
        ];
        $allowed['img'] = [
            'alt' => true,
            'class' => true,
            'height' => true,
            'id' => true,
            'src' => true,
            'style' => true,
            'title' => true,
            'width' => true,
        ];

        return $allowed;
    }

    private function render_test_email_preview_html(array $preview): string
    {
        $subject = isset($preview['subject']) ? (string) $preview['subject'] : '';
        $message_text = isset($preview['message_text']) ? (string) $preview['message_text'] : '';
        $message_html = isset($preview['message_html']) ? (string) $preview['message_html'] : '';

        $subject_html = esc_html($subject);
        $body_html = $message_html !== '' ? $message_html : nl2br(esc_html($message_text));

        return '
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Email Preview</title>
</head>
<body style="margin:0;padding:20px;background:#f1f5f9;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Oxygen-Sans,Ubuntu,Cantarell,Helvetica Neue,sans-serif;color:#0f172a;">
  <div style="max-width:760px;margin:0 auto;background:#ffffff;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;box-shadow:0 10px 25px rgba(15,23,42,0.08);">
    <div style="padding:16px 20px;background:#f8fafc;border-bottom:1px solid #e2e8f0;">
      <div style="font-size:12px;color:#64748b;margin-bottom:6px;">Asunto</div>
      <div style="font-size:15px;font-weight:700;">' . $subject_html . '</div>
    </div>
    <div style="padding:18px 20px;font-size:13px;line-height:1.6;">
      ' . $body_html . '
    </div>
  </div>
</body>
</html>';
    }

    private function render_test_email_preview_error_html(string $message): string
    {
        $message_html = esc_html($message);

        return '
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Email Preview</title>
</head>
<body style="margin:0;padding:20px;background:#f1f5f9;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Oxygen-Sans,Ubuntu,Cantarell,Helvetica Neue,sans-serif;color:#0f172a;">
  <div style="max-width:760px;margin:0 auto;background:#ffffff;border:1px solid #fecaca;border-radius:12px;overflow:hidden;box-shadow:0 10px 25px rgba(15,23,42,0.08);">
    <div style="padding:16px 20px;background:#fef2f2;border-bottom:1px solid #fecaca;">
      <div style="font-size:15px;font-weight:700;color:#991b1b;">' . $message_html . '</div>
    </div>
  </div>
</body>
</html>';
    }

	    private function render_test_emails_tab(): void
	    {
	        $items = $this->get_test_emails_catalog();
	        $origins = [];
	        foreach ($items as $item) {
	            $origin = isset($item['origin']) ? trim((string) $item['origin']) : '';
	            if ($origin === '') {
	                $origin = __('Otros', 'politeia-learning');
	            }
	            $origins[$origin] = true;
	        }
	        $origin_labels = array_keys($origins);
	        sort($origin_labels, SORT_NATURAL | SORT_FLAG_CASE);
	        $nonce = wp_create_nonce(self::TEST_EMAIL_NONCE_ACTION);
	        $templates = $this->get_test_email_templates_option();
	        $instructions = $this->get_test_emails_copy_instructions();
	        ?>
	        <p style="margin-top: 12px;">
	            <?php echo esc_html__('Lista unificada de correos automáticos (WP core / WooCommerce / Learni). Todos se muestran, incluso si no tienen template.', 'politeia-learning'); ?>
	        </p>

	        <style>
	            @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;700;900&family=JetBrains+Mono:wght@700&display=swap');

	            .pl-email-cards {
	                display: grid;
	                grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
	                gap: 14px;
	            }
	            .pl-email-card {
	                width: 100%;
	                max-width: 340px;
	                border: 1px solid #000;
	                border-radius: 2px;
	                overflow: hidden;
	                background: #fff;
	                box-shadow: 4px 4px 0px 0px rgba(0,0,0,0.05);
	                font-family: 'Inter', ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, "Apple Color Emoji", "Segoe UI Emoji";
	            }
	            .pl-email-card * { box-sizing: border-box; }
	            .pl-email-card-header {
	                padding: 12px;
	                display: flex;
	                justify-content: space-between;
	                align-items: flex-start;
	                cursor: pointer;
	                transition: background-color .15s ease;
	            }
	            .pl-email-card-header:hover { background: #f9fafb; }
	            .pl-email-card-header-left { min-width: 0; padding-right: 8px; }
	            .pl-email-card-toggle {
	                margin-top: 2px;
	                padding: 4px;
	                background: transparent;
	                border: 0;
	                cursor: pointer;
	                color: #000;
	                border-radius: 2px;
	                transition: all .15s ease;
	                flex: 0 0 auto;
	            }
	            .pl-email-card-toggle:hover { background: #000; color: #fff; }
	            .pl-email-card-toggle svg { width: 16px; height: 16px; stroke-width: 3px; transition: transform .15s ease; }
	            .pl-email-card-toggle:hover svg { stroke: #fff; }
	            .pl-email-card-toggle svg.pl-rotated { transform: rotate(180deg); }
	            .pl-email-card-collapsible {
	                border-top: 1px solid rgba(0,0,0,0.06);
	                max-height: 0;
	                overflow: hidden;
	                opacity: 0;
	                transition: max-height .2s ease, opacity .2s ease;
	            }
	            .pl-email-card.is-open .pl-email-card-collapsible { max-height: 1000px; opacity: 1; }
	            .pl-email-card-badge {
	                display: inline-block;
	                font-size: 9px;
	                font-weight: 800;
	                text-transform: uppercase;
	                letter-spacing: -0.02em;
	                color: #000;
	                border: 1px solid #000;
	                padding: 2px 6px;
	                border-radius: 2px;
	                font-family: 'JetBrains Mono', ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, Liberation Mono, Courier New, monospace;
	            }
	            .pl-email-card-title {
	                margin: 10px 0 12px;
	                font-size: 13px;
	                font-weight: 900;
	                text-transform: uppercase;
	                letter-spacing: -0.02em;
	                line-height: 1.15;
	                color: #000;
	            }
	            .pl-email-card-actions { padding: 12px; padding-top: 10px; display: flex; gap: 8px; }
	            .pl-email-card-btn {
	                flex: 1 1 0;
	                display: inline-flex;
	                align-items: center;
	                justify-content: center;
	                gap: 6px;
	                padding: 10px 8px;
	                border: 1px solid #000;
	                background: #fff;
	                color: #000;
	                font-size: 10px;
	                font-weight: 900;
	                text-transform: uppercase;
	                cursor: pointer;
	                transition: all .15s ease;
	            }
	            .pl-email-card-btn:hover { background:#000; color:#fff; }
	            .pl-email-card-btn svg { width: 12px; height: 12px; stroke-width: 3px; }
	            .pl-email-card-btn:hover svg { stroke: #fff; }
	            .pl-email-card-meta { padding: 14px 12px; display: grid; gap: 14px; }
	            .pl-email-card-meta-grid { display:grid; grid-template-columns: 1fr 1fr; gap: 14px; }
	            .pl-email-card-k { font-size: 9px; font-weight: 800; text-transform: uppercase; letter-spacing: .18em; color: rgba(0,0,0,0.4); margin-bottom: 6px; }
	            .pl-email-card-v {
	                display: inline-flex;
	                align-items: center;
	                gap: 6px;
	                font-size: 11px;
	                font-weight: 800;
	                color: #000;
	            }
	            .pl-email-card-v svg { width: 12px; height: 12px; stroke-width: 3px; }
	            .pl-email-card-select {
	                width: 100%;
	                appearance: none;
	                background-color: #fff;
	                border: 1px solid #000;
	                border-radius: 2px;
	                padding: 10px 34px 10px 10px;
	                font-size: 11px;
	                font-weight: 800;
	                color: #000;
	                cursor: pointer;
	                transition: all .15s ease;
	                background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='3' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E");
	                background-repeat: no-repeat;
	                background-position: right 10px center;
	                background-size: 16px;
	            }
	            .pl-email-card-select:focus {
	                outline: none;
	                background-color: #000;
	                color: #fff;
	                background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='white' stroke-width='3' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E");
	            }
	            .pl-email-card-footer {
	                padding: 12px;
	                background:#000;
	                display:flex;
	                align-items:center;
	                justify-content: space-between;
	                gap: 10px;
	            }
	            .pl-email-card-copy {
	                display:inline-flex;
	                align-items:center;
	                gap: 6px;
	                background: transparent;
	                border: 0;
	                cursor: pointer;
	                padding: 0;
	                color: rgba(255,255,255,0.75);
	                font-size: 10px;
	                font-weight: 800;
	                text-transform: uppercase;
	            }
	            .pl-email-card-copy[disabled] { opacity: 0.45; cursor: not-allowed; }
	            .pl-email-card-copy:hover { color: #fff; }
	            .pl-email-card-copy svg { width: 12px; height: 12px; stroke: rgba(255,255,255,0.75); }
	            .pl-email-card-copy:hover svg { stroke: #fff; }
	            .pl-email-card-copy.pl-copied { color: #34d399; }
	            .pl-email-card-copy.pl-copied svg { stroke: #34d399; }
	            .pl-email-card-edit {
	                display:inline-flex;
	                align-items:center;
	                gap: 6px;
	                padding: 7px 10px;
	                border-radius: 2px;
	                border: 0;
	                cursor: pointer;
	                background: #fff;
	                color: #000;
	                font-size: 10px;
	                font-weight: 900;
	                text-transform: uppercase;
	                letter-spacing: -0.02em;
	                box-shadow: 2px 2px 0px 0px rgba(255,255,255,0.2);
	            }
	            .pl-email-card-edit:hover { background: #e5e7eb; }
	            .pl-email-card-edit svg { width: 12px; height: 12px; stroke-width: 2.6px; }
	        </style>

	        <div style="margin-top: 10px; margin-bottom: 12px; display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
	            <button type="button" class="button" id="pl-copy-email-instructions"><?php echo esc_html__('COPY INSTRUCTIONS', 'politeia-learning'); ?></button>
	            <div id="pl-test-emails-origin-filter" style="display:flex;gap:8px;align-items:center;">
	                <span style="font-size:12px;color:#334155;font-weight:600;"><?php echo esc_html__('Tipos de Emails', 'politeia-learning'); ?>:</span>
		                <button type="button" class="button" id="pl-test-emails-origin-filter-toggle" aria-expanded="false" aria-controls="pl-test-emails-origin-filter-panel">
		                    <?php echo esc_html__('Todos', 'politeia-learning'); ?>
		                </button>
	                <div id="pl-test-emails-origin-filter-panel" style="display:none; position:relative;">
	                    <div style="position:absolute;z-index:999; margin-top:6px; background:#fff; border:1px solid #e2e8f0; border-radius:10px; box-shadow:0 10px 25px rgba(15,23,42,0.12); padding:10px 12px; min-width: 220px;">
	                        <div style="display:flex;flex-direction:column;gap:8px;">
	                            <label style="display:flex;gap:8px;align-items:center;font-size:12px;color:#0f172a;">
	                                <input type="checkbox" class="pl-test-emails-origin-all" checked>
	                                <span><?php echo esc_html__('Todos', 'politeia-learning'); ?></span>
	                            </label>
	                            <div style="height:1px;background:#e2e8f0;"></div>
	                            <?php foreach ($origin_labels as $origin_label): ?>
	                                <?php $origin_key = sanitize_key(str_replace(' ', '_', (string) $origin_label)); ?>
	                                <label style="display:flex;gap:8px;align-items:center;font-size:12px;color:#0f172a;">
	                                    <input type="checkbox" class="pl-test-emails-origin-opt" value="<?php echo esc_attr($origin_key); ?>" checked>
	                                    <span><?php echo esc_html((string) $origin_label); ?></span>
	                                </label>
	                            <?php endforeach; ?>
	                        </div>
	                    </div>
		                </div>
		            </div>
		            <div id="pl-test-emails-recipient-filter" style="display:flex;gap:8px;align-items:center;">
		                <span style="font-size:12px;color:#334155;font-weight:600;"><?php echo esc_html__('Quién recibe', 'politeia-learning'); ?>:</span>
		                <button type="button" class="button" id="pl-test-emails-recipient-filter-toggle" aria-expanded="false" aria-controls="pl-test-emails-recipient-filter-panel">
		                    <?php echo esc_html__('Todos', 'politeia-learning'); ?>
		                </button>
		                <div id="pl-test-emails-recipient-filter-panel" style="display:none; position:relative;">
		                    <div style="position:absolute;z-index:999; margin-top:6px; background:#fff; border:1px solid #e2e8f0; border-radius:10px; box-shadow:0 10px 25px rgba(15,23,42,0.12); padding:10px 12px; min-width: 220px;">
		                        <div style="display:flex;flex-direction:column;gap:8px;">
		                            <label style="display:flex;gap:8px;align-items:center;font-size:12px;color:#0f172a;">
		                                <input type="checkbox" class="pl-test-emails-recipient-all" checked>
		                                <span><?php echo esc_html__('Todos', 'politeia-learning'); ?></span>
		                            </label>
		                            <div style="height:1px;background:#e2e8f0;"></div>
		                            <label style="display:flex;gap:8px;align-items:center;font-size:12px;color:#0f172a;">
		                                <input type="checkbox" class="pl-test-emails-recipient-opt" value="admin" checked>
		                                <span><?php echo esc_html__('Admin', 'politeia-learning'); ?></span>
		                            </label>
		                            <label style="display:flex;gap:8px;align-items:center;font-size:12px;color:#0f172a;">
		                                <input type="checkbox" class="pl-test-emails-recipient-opt" value="customer" checked>
		                                <span><?php echo esc_html__('Cliente', 'politeia-learning'); ?></span>
		                            </label>
		                            <label style="display:flex;gap:8px;align-items:center;font-size:12px;color:#0f172a;">
		                                <input type="checkbox" class="pl-test-emails-recipient-opt" value="other" checked>
		                                <span><?php echo esc_html__('Otro', 'politeia-learning'); ?></span>
		                            </label>
		                        </div>
		                    </div>
		                </div>
		            </div>
		            <span id="pl-copy-email-instructions-status" style="font-size:12px;color:#64748b;"></span>
		            <textarea id="pl-copy-email-instructions-text" style="position:absolute;left:-9999px;top:-9999px;"><?php echo esc_textarea($instructions); ?></textarea>
		        </div>

	        <div class="pl-test-emails-layout" style="display:flex; gap:16px; align-items:flex-start; margin-top: 12px;">
	            <div class="pl-test-emails-list" style="flex: 1 1 720px; max-width: 720px;">
	                <div class="pl-email-cards">
	                    <?php foreach ($items as $key => $item): ?>
	                        <?php $enabled = !empty($templates[$key]['enabled']); ?>
	                        <?php $custom_template = isset($templates[$key]['template']) ? (string) $templates[$key]['template'] : ''; ?>
	                        <?php $default_template_label = $this->get_default_template_label($item); ?>
	                        <?php $has_custom = $enabled && trim($custom_template) !== ''; ?>
	                        <?php $origin = isset($item['origin']) ? (string) $item['origin'] : ''; ?>
	                        <?php $origin_label = $origin !== '' ? $origin : __('Otros', 'politeia-learning'); ?>
	                        <?php $origin_key = sanitize_key(str_replace(' ', '_', $origin_label)); ?>
	                        <?php $recipient = isset($item['recipient']) ? sanitize_key((string) $item['recipient']) : 'other'; ?>
	                        <?php if (!in_array($recipient, ['admin', 'customer', 'other'], true)) { $recipient = 'other'; } ?>
	                        <?php $recipient_label = $recipient === 'admin' ? __('Admin', 'politeia-learning') : ($recipient === 'customer' ? __('Cliente', 'politeia-learning') : __('Otro', 'politeia-learning')); ?>
	                        <?php $name = isset($item['label']) ? (string) $item['label'] : (string) $key; ?>
	                        <?php $path_to_copy = $has_custom ? __('Custom', 'politeia-learning') : $default_template_label; ?>

	                        <div class="pl-email-card pl-test-email-card" data-key="<?php echo esc_attr((string) $key); ?>" data-email-origin="<?php echo esc_attr($origin_key); ?>" data-email-recipient="<?php echo esc_attr($recipient); ?>">
	                            <div class="pl-email-card-header" data-toggle="card">
	                                <div class="pl-email-card-header-left">
	                                    <div><span class="pl-email-card-badge"><?php echo esc_html((string) $key); ?></span></div>
	                                    <div class="pl-email-card-title"><?php echo esc_html($name); ?></div>
	                                </div>
	                                <button type="button" class="pl-email-card-toggle" aria-expanded="false" aria-label="<?php echo esc_attr__('Expandir', 'politeia-learning'); ?>">
	                                    <svg class="pl-email-card-toggle-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
	                                        <path d="m6 9 6 6 6-6"></path>
	                                    </svg>
	                                </button>
	                            </div>

	                            <div class="pl-email-card-collapsible">
	                                <div class="pl-email-card-actions">
	                                    <button type="button" class="pl-email-card-btn pl-test-email-view" data-key="<?php echo esc_attr((string) $key); ?>">
	                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
	                                        <?php echo esc_html__('Ver', 'politeia-learning'); ?>
	                                    </button>
	                                    <button type="button" class="pl-email-card-btn pl-test-email-send" data-key="<?php echo esc_attr((string) $key); ?>">
	                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><path d="M4.5 3h15"/><path d="M6 3v16a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V3"/><path d="M6 14h12"/></svg>
	                                        <?php echo esc_html__('Test', 'politeia-learning'); ?>
	                                    </button>
	                                </div>

	                                <div class="pl-email-card-meta">
	                                    <div class="pl-email-card-meta-grid">
	                                        <div>
	                                            <div class="pl-email-card-k"><?php echo esc_html__('Origen', 'politeia-learning'); ?></div>
	                                            <div class="pl-email-card-v">
	                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41 12 22l-8.59-8.59a2 2 0 0 1 0-2.82l8.59-8.59 8.59 8.59a2 2 0 0 1 0 2.82Z"/><path d="M7 7h.01"/></svg>
	                                                <?php echo esc_html($origin_label); ?>
	                                            </div>
	                                        </div>
	                                        <div>
	                                            <div class="pl-email-card-k"><?php echo esc_html__('Recibe', 'politeia-learning'); ?></div>
	                                            <div class="pl-email-card-v">
	                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
	                                                <?php echo esc_html($recipient_label); ?>
	                                            </div>
	                                        </div>
	                                    </div>

	                                    <div>
	                                        <label class="pl-email-card-k" for="pl-email-mode-<?php echo esc_attr((string) $key); ?>"><?php echo esc_html__('Template File', 'politeia-learning'); ?></label>
	                                        <select id="pl-email-mode-<?php echo esc_attr((string) $key); ?>" class="pl-email-card-select pl-test-email-mode" data-key="<?php echo esc_attr((string) $key); ?>">
	                                            <option value="traditional" <?php selected(false, $enabled); ?>><?php echo esc_html((string) $default_template_label); ?></option>
	                                            <option value="custom" <?php selected(true, $enabled); ?>><?php echo esc_html__('Custom', 'politeia-learning'); ?></option>
	                                        </select>
	                                    </div>
	                                </div>

	                                <div class="pl-email-card-footer">
	                                    <button type="button" class="pl-email-card-copy pl-test-email-copy-path" data-copy="<?php echo esc_attr((string) $default_template_label); ?>" <?php echo $default_template_label === __('No existe', 'politeia-learning') ? 'disabled' : ''; ?>>
	                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
	                                        <span class="pl-email-card-copy-text"><?php echo esc_html__('Ruta', 'politeia-learning'); ?></span>
	                                    </button>
	                                    <button type="button" class="pl-email-card-edit pl-test-email-template" data-key="<?php echo esc_attr((string) $key); ?>">
	                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09a1.65 1.65 0 0 0-1-1.51 1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09a1.65 1.65 0 0 0 1.51-1 1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1Z"/></svg>
	                                        <?php echo esc_html__('Editar', 'politeia-learning'); ?>
	                                    </button>
	                                </div>
	                            </div>
	                        </div>
	                    <?php endforeach; ?>
	                </div>
	                <p class="description" style="margin-top: 10px;">
	                    <?php echo esc_html__('“TEST” envía un correo de prueba al email del usuario actual.', 'politeia-learning'); ?>
	                </p>
	            </div>

            <div class="pl-test-emails-preview" style="flex: 1 1 auto; min-width: 420px; position: sticky; top: 32px; align-self: flex-start;">
                <div style="background:#fff;border:1px solid #e2e8f0;border-radius:10px;overflow:hidden;">
                    <div style="padding:10px 12px;background:#f8fafc;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;">
                        <strong><?php echo esc_html__('Preview', 'politeia-learning'); ?></strong>
                        <span id="pl-test-emails-status" style="font-size:12px;color:#64748b;"></span>
                    </div>
                    <div style="height: calc(100vh - 220px); min-height: 520px; background: #f1f5f9;">
                        <iframe id="pl-test-email-preview-frame" style="width:100%;height:100%;border:none;background:#f1f5f9;" src="about:blank"></iframe>
                    </div>
                </div>
            </div>
        </div>

        <div id="pl-test-email-template-overlay" style="display:none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 99999; align-items: center; justify-content: center; backdrop-filter: blur(5px);">
            <div style="background:#fff; width: 92%; max-width: 980px; height: 86%; border-radius: 12px; display: flex; flex-direction: column; overflow: hidden; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5);">
                <div style="padding: 15px 18px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
                    <h3 style="margin:0; font-size: 1.1rem; color: #1e293b;"><?php echo esc_html__('Template de Email', 'politeia-learning'); ?></h3>
                    <button type="button" class="pl-close-template" style="background:none;border:none;font-size:28px;cursor:pointer;color:#94a3b8;">&times;</button>
                </div>
                <div style="flex-grow:1; background:#fff; padding: 16px 18px; overflow: auto;">
                    <input type="hidden" id="pl-template-key" value="">

                    <p class="description" style="margin-top:0;">
                        <?php echo esc_html__('Variables disponibles: {{site_name}}, {{site_url}}, {{username}}, {{user_email}}, {{subject}}, {{message}} (HTML), {{message_text}}.', 'politeia-learning'); ?>
                    </p>

                    <div style="display:flex; gap:14px; align-items:flex-start; margin-top: 12px;">
                        <div style="flex: 1 1 auto;">
                            <label for="pl-template" style="display:block;font-weight:600;margin-bottom:6px;">
                                <?php echo esc_html__('HTML + CSS', 'politeia-learning'); ?>
                            </label>
                            <textarea id="pl-template" rows="18" style="width:100%;font-family:ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, Liberation Mono, Courier New, monospace;" placeholder="<style>/* CSS aquí */</style>\n<div>...HTML...</div>"></textarea>
                        </div>
                    </div>

                    <div style="display:flex; justify-content:flex-end; gap:10px; margin-top: 12px;">
                        <label style="display:flex; align-items:center; gap:8px; margin-right:auto;">
                            <input type="checkbox" id="pl-template-enabled" value="1">
                            <?php echo esc_html__('Activar Custom Template', 'politeia-learning'); ?>
                        </label>
                        <button type="button" class="button pl-template-cancel"><?php echo esc_html__('Cancelar', 'politeia-learning'); ?></button>
                        <button type="button" class="button button-primary pl-template-save"><?php echo esc_html__('Guardar', 'politeia-learning'); ?></button>
                    </div>
                </div>
            </div>
        </div>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const frame = document.getElementById('pl-test-email-preview-frame');
                const status = document.getElementById('pl-test-emails-status');
                const nonce = <?php echo wp_json_encode($nonce); ?>;
                const copyBtn = document.getElementById('pl-copy-email-instructions');
                const copyStatus = document.getElementById('pl-copy-email-instructions-status');
                const copyText = document.getElementById('pl-copy-email-instructions-text');
                const overlay = document.getElementById('pl-test-email-template-overlay');
                const templateKeyInput = document.getElementById('pl-template-key');
                const templateInput = document.getElementById('pl-template');
	                const enabledInput = document.getElementById('pl-template-enabled');
	                const closeTemplateBtn = document.querySelector('.pl-close-template');
	                const cancelTemplateBtn = document.querySelector('.pl-template-cancel');
		                const saveTemplateBtn = document.querySelector('.pl-template-save');
		                const originFilterToggle = document.getElementById('pl-test-emails-origin-filter-toggle');
		                const originFilterPanelWrap = document.getElementById('pl-test-emails-origin-filter-panel');
		                const originAll = document.querySelector('#pl-test-emails-origin-filter-panel .pl-test-emails-origin-all');
		                const originOpts = Array.from(document.querySelectorAll('#pl-test-emails-origin-filter-panel .pl-test-emails-origin-opt'));
		                const recipientFilterToggle = document.getElementById('pl-test-emails-recipient-filter-toggle');
		                const recipientFilterPanelWrap = document.getElementById('pl-test-emails-recipient-filter-panel');
		                const recipientAll = document.querySelector('#pl-test-emails-recipient-filter-panel .pl-test-emails-recipient-all');
		                const recipientOpts = Array.from(document.querySelectorAll('#pl-test-emails-recipient-filter-panel .pl-test-emails-recipient-opt'));
		                const emailRows = Array.from(document.querySelectorAll('.pl-test-email-card'));
		                const originStorageKey = 'pl_test_emails_origin_filter_v1';
		                const recipientStorageKey = 'pl_test_emails_recipient_filter_v1';

	                function setOriginPanelOpen(open) {
	                    if (!originFilterToggle || !originFilterPanelWrap) return;
	                    originFilterToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
	                    originFilterPanelWrap.style.display = open ? 'block' : 'none';
	                }

		                function getSelectedOrigins() {
		                    return originOpts.filter(opt => opt.checked).map(opt => opt.value);
		                }

		                function setRecipientPanelOpen(open) {
		                    if (!recipientFilterToggle || !recipientFilterPanelWrap) return;
		                    recipientFilterToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
		                    recipientFilterPanelWrap.style.display = open ? 'block' : 'none';
		                }

		                function getSelectedRecipients() {
		                    return recipientOpts.filter(opt => opt.checked).map(opt => opt.value);
		                }

		                function updateRecipientToggleLabel() {
		                    if (!recipientFilterToggle) return;
		                    const selected = getSelectedRecipients();
		                    if (selected.length === 0 || selected.length === recipientOpts.length) {
		                        recipientFilterToggle.textContent = 'Todos';
		                        return;
		                    }
		                    recipientFilterToggle.textContent = selected.length + ' seleccionado(s)';
		                }

	                function updateOriginToggleLabel() {
	                    if (!originFilterToggle) return;
	                    const selected = getSelectedOrigins();
	                    if (selected.length === 0 || selected.length === originOpts.length) {
	                        originFilterToggle.textContent = 'Todos';
	                        return;
	                    }
	                    originFilterToggle.textContent = selected.length + ' seleccionado(s)';
	                }

		                function applyFilters() {
		                    const selectedOrigins = getSelectedOrigins();
		                    const selectedRecipients = getSelectedRecipients();
		                    const allOriginsSelected = selectedOrigins.length === 0 || selectedOrigins.length === originOpts.length;
		                    const allRecipientsSelected = selectedRecipients.length === 0 || selectedRecipients.length === recipientOpts.length;

		                    emailRows.forEach(card => {
		                        const rowOrigin = card.getAttribute('data-email-origin') || '';
		                        const rowRecipient = card.getAttribute('data-email-recipient') || 'other';
		                        const showByOrigin = allOriginsSelected || selectedOrigins.includes(rowOrigin);
		                        const showByRecipient = allRecipientsSelected || selectedRecipients.includes(rowRecipient);
		                        card.style.display = (showByOrigin && showByRecipient) ? '' : 'none';
		                    });
		                }

		                function persistOriginFilter() {
		                    try {
		                        const selected = getSelectedOrigins();
		                        window.localStorage.setItem(originStorageKey, JSON.stringify(selected));
		                    } catch (e) {}
		                }

		                function persistRecipientFilter() {
		                    try {
		                        const selected = getSelectedRecipients();
		                        window.localStorage.setItem(recipientStorageKey, JSON.stringify(selected));
		                    } catch (e) {}
		                }

		                function restoreOriginFilter() {
		                    let selected = null;
		                    try {
		                        const raw = window.localStorage.getItem(originStorageKey);
	                        if (raw) selected = JSON.parse(raw);
	                    } catch (e) {}

		                    if (!Array.isArray(selected)) {
		                        if (originAll) originAll.checked = true;
		                        originOpts.forEach(opt => opt.checked = true);
		                        updateOriginToggleLabel();
		                        applyFilters();
		                        return;
		                    }

	                    const selectedSet = new Set(selected.map(String));
	                    originOpts.forEach(opt => opt.checked = selectedSet.has(opt.value));
		                    const nowSelected = getSelectedOrigins();
		                    if (originAll) originAll.checked = nowSelected.length === originOpts.length;
		                    updateOriginToggleLabel();
		                    applyFilters();
		                }

		                function restoreRecipientFilter() {
		                    let selected = null;
		                    try {
		                        const raw = window.localStorage.getItem(recipientStorageKey);
		                        if (raw) selected = JSON.parse(raw);
		                    } catch (e) {}

		                    if (!Array.isArray(selected)) {
		                        if (recipientAll) recipientAll.checked = true;
		                        recipientOpts.forEach(opt => opt.checked = true);
		                        updateRecipientToggleLabel();
		                        applyFilters();
		                        return;
		                    }

		                    const selectedSet = new Set(selected.map(String));
		                    recipientOpts.forEach(opt => opt.checked = selectedSet.has(opt.value));
		                    const nowSelected = getSelectedRecipients();
		                    if (recipientAll) recipientAll.checked = nowSelected.length === recipientOpts.length;
		                    updateRecipientToggleLabel();
		                    applyFilters();
		                }

		                if (originFilterToggle && originFilterPanelWrap && originAll && originOpts.length) {
	                    originFilterToggle.addEventListener('click', function () {
	                        const open = originFilterToggle.getAttribute('aria-expanded') === 'true';
	                        setOriginPanelOpen(!open);
	                    });

		                    document.addEventListener('click', function (e) {
		                        if (originFilterToggle.contains(e.target) || originFilterPanelWrap.contains(e.target)) return;
		                        setOriginPanelOpen(false);
		                    });

		                    originAll.addEventListener('change', function () {
		                        const checked = originAll.checked;
		                        originOpts.forEach(opt => opt.checked = checked);
		                        updateOriginToggleLabel();
		                        applyFilters();
		                        persistOriginFilter();
		                    });

		                    originOpts.forEach(opt => {
		                        opt.addEventListener('change', function () {
		                            const selected = getSelectedOrigins();
		                            originAll.checked = selected.length === originOpts.length;
		                            updateOriginToggleLabel();
		                            applyFilters();
		                            persistOriginFilter();
		                        });
		                    });

		                    restoreOriginFilter();
		                }

		                if (recipientFilterToggle && recipientFilterPanelWrap && recipientAll && recipientOpts.length) {
		                    recipientFilterToggle.addEventListener('click', function () {
		                        const open = recipientFilterToggle.getAttribute('aria-expanded') === 'true';
		                        setRecipientPanelOpen(!open);
		                    });

		                    document.addEventListener('click', function (e) {
		                        if (recipientFilterToggle.contains(e.target) || recipientFilterPanelWrap.contains(e.target)) return;
		                        setRecipientPanelOpen(false);
		                    });

		                    recipientAll.addEventListener('change', function () {
		                        const checked = recipientAll.checked;
		                        recipientOpts.forEach(opt => opt.checked = checked);
		                        updateRecipientToggleLabel();
		                        applyFilters();
		                        persistRecipientFilter();
		                    });

		                    recipientOpts.forEach(opt => {
		                        opt.addEventListener('change', function () {
		                            const selected = getSelectedRecipients();
		                            recipientAll.checked = selected.length === recipientOpts.length;
		                            updateRecipientToggleLabel();
		                            applyFilters();
		                            persistRecipientFilter();
		                        });
		                    });

		                    restoreRecipientFilter();
		                }

	                function setStatus(text, isError) {
	                    status.textContent = text || '';
	                    status.style.color = isError ? '#b91c1c' : '#64748b';
	                }

                function setCopyStatus(text, isError) {
                    copyStatus.textContent = text || '';
                    copyStatus.style.color = isError ? '#b91c1c' : '#64748b';
                }

                function updateTemplateLabel(key, mode) {
                    const el = document.querySelector('.pl-test-email-template-label[data-key=\"' + key + '\"]');
                    if (!el) return;

                    const defaultLabel = el.getAttribute('data-default-label') || 'No existe';
                    const label = mode === 'custom' ? 'Custom' : defaultLabel;
                    el.textContent = label;
                    el.style.color = label === 'No existe' ? '#b91c1c' : '#64748b';
                }

                copyBtn.addEventListener('click', async function() {
                    setCopyStatus('', false);
                    const text = copyText.value || '';

                    try {
                        if (navigator.clipboard && navigator.clipboard.writeText) {
                            await navigator.clipboard.writeText(text);
                        } else {
                            copyText.focus();
                            copyText.select();
                            document.execCommand('copy');
                        }
                        setCopyStatus('Copiado.', false);
                    } catch (e) {
                        setCopyStatus('No se pudo copiar.', true);
                    }
                });

                document.addEventListener('click', async function(e) {
                    const header = e.target && e.target.closest ? e.target.closest('.pl-email-card-header[data-toggle=\"card\"]') : null;
                    const toggle = e.target && e.target.closest ? e.target.closest('.pl-email-card-toggle') : null;
                    const card = (header || toggle) && e.target && e.target.closest ? e.target.closest('.pl-email-card') : null;
                    if (card && (header || toggle)) {
                        e.preventDefault();
                        const icon = card.querySelector('.pl-email-card-toggle-icon');
                        const toggleBtn = card.querySelector('.pl-email-card-toggle');
                        card.classList.toggle('is-open');
                        if (icon) icon.classList.toggle('pl-rotated');
                        if (toggleBtn) toggleBtn.setAttribute('aria-expanded', card.classList.contains('is-open') ? 'true' : 'false');
                        return;
                    }

                    const btn = e.target && e.target.closest ? e.target.closest('.pl-test-email-copy-path') : null;
                    if (!btn) return;
                    e.preventDefault();
                    if (btn.disabled) return;

                    const text = btn.getAttribute('data-copy') || '';
                    if (!text || text === 'No existe') return;

                    try {
                        if (navigator.clipboard && navigator.clipboard.writeText) {
                            await navigator.clipboard.writeText(text);
                        } else {
                            const temp = document.createElement('textarea');
                            temp.value = text;
                            temp.style.position = 'fixed';
                            temp.style.top = '-9999px';
                            temp.style.left = '-9999px';
                            document.body.appendChild(temp);
                            temp.focus();
                            temp.select();
                            document.execCommand('copy');
                            temp.remove();
                        }

                        const label = btn.querySelector('.pl-email-card-copy-text');
                        const original = label ? label.textContent : null;
                        if (label) label.textContent = 'Copiado';
                        btn.classList.add('pl-copied');
                        window.setTimeout(() => {
                            if (label && original) label.textContent = original;
                            btn.classList.remove('pl-copied');
                        }, 2000);
                    } catch (err) {
                        // no-op
                    }
                });

                function openTemplateModal() {
                    overlay.style.display = 'flex';
                }

                function closeTemplateModal() {
                    overlay.style.display = 'none';
                    templateKeyInput.value = '';
                }

                document.querySelectorAll('.pl-test-email-view').forEach(btn => {
                    btn.addEventListener('click', function() {
                        const key = this.getAttribute('data-key');
                        frame.src = 'about:blank';
                        setStatus('Cargando preview…', false);

                        fetch('<?php echo esc_url(admin_url('admin-ajax.php')); ?>?action=pl_get_test_email_preview&key=' + encodeURIComponent(key) + '&nonce=' + encodeURIComponent(nonce))
                            .then(r => r.text())
                            .then(html => {
                                const doc = frame.contentWindow.document;
                                doc.open();
                                doc.write(html);
                                doc.close();
                                setStatus('', false);
                            })
                            .catch(() => setStatus('No se pudo cargar el preview.', true));
                    });
                });

                document.querySelectorAll('.pl-test-email-send').forEach(btn => {
                    btn.addEventListener('click', function() {
                        const key = this.getAttribute('data-key');
                        setStatus('Enviando test…', false);

                        const body = new URLSearchParams();
                        body.set('action', 'pl_send_test_email');
                        body.set('nonce', nonce);
                        body.set('key', key);

                        fetch('<?php echo esc_url(admin_url('admin-ajax.php')); ?>', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                            body: body.toString()
                        })
                            .then(r => r.json())
                            .then(data => {
                                if (data && data.success) {
                                    setStatus('Enviado a ' + (data.data && data.data.to ? data.data.to : ''), false);
                                } else {
                                    setStatus('No se pudo enviar el test.', true);
                                }
                            })
                            .catch(() => setStatus('No se pudo enviar el test.', true));
                    });
                });

                document.querySelectorAll('.pl-test-email-mode').forEach(select => {
                    select.addEventListener('change', function() {
                        const key = this.getAttribute('data-key');
                        const mode = this.value;
                        updateTemplateLabel(key, mode);

                        const body = new URLSearchParams();
                        body.set('action', 'pl_set_test_email_template_mode');
                        body.set('nonce', nonce);
                        body.set('key', key);
                        body.set('mode', mode);

                        fetch('<?php echo esc_url(admin_url('admin-ajax.php')); ?>', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                            body: body.toString()
                        }).catch(() => {});
                    });
                });

                document.querySelectorAll('.pl-test-email-template').forEach(btn => {
                    btn.addEventListener('click', function() {
                        const key = this.getAttribute('data-key');
                        templateKeyInput.value = key;
                        templateInput.value = '';
                        enabledInput.checked = false;
                        openTemplateModal();

                        fetch('<?php echo esc_url(admin_url('admin-ajax.php')); ?>?action=pl_get_test_email_template&key=' + encodeURIComponent(key) + '&nonce=' + encodeURIComponent(nonce))
                            .then(r => r.json())
                            .then(data => {
                                if (data && data.success && data.data) {
                                    templateInput.value = data.data.template || '';
                                    enabledInput.checked = !!data.data.enabled;
                                }
                            })
                            .catch(() => {});
                    });
                });

                saveTemplateBtn.addEventListener('click', function() {
                    const key = templateKeyInput.value;
                    if (!key) return;

                    const body = new URLSearchParams();
                    body.set('action', 'pl_save_test_email_template');
                    body.set('nonce', nonce);
                    body.set('key', key);
                    body.set('template', templateInput.value || '');
                    body.set('enabled', enabledInput.checked ? '1' : '0');

                    setStatus('Guardando template…', false);
                    fetch('<?php echo esc_url(admin_url('admin-ajax.php')); ?>', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                        body: body.toString()
                    })
                        .then(r => r.json())
                        .then(data => {
                            if (data && data.success) {
                                setStatus('Template guardado.', false);
                                const modeSelect = document.querySelector('.pl-test-email-mode[data-key=\"' + key + '\"]');
                                if (modeSelect) {
                                    modeSelect.value = enabledInput.checked ? 'custom' : 'traditional';
                                }
                                updateTemplateLabel(key, enabledInput.checked ? 'custom' : 'traditional');
                                closeTemplateModal();
                            } else {
                                setStatus('No se pudo guardar el template.', true);
                            }
                        })
                        .catch(() => setStatus('No se pudo guardar el template.', true));
                });

                cancelTemplateBtn.addEventListener('click', closeTemplateModal);
                closeTemplateBtn.addEventListener('click', closeTemplateModal);
                overlay.addEventListener('click', function(e) {
                    if (e.target === overlay) closeTemplateModal();
                });
            });
        </script>
        <?php
    }

    public function render_admin_page()
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('No tienes permisos para ver esta página.', 'politeia-learning'));
        }

        $active_tab = $this->get_active_tab();

        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php echo esc_html__('Email Log', 'politeia-learning'); ?></h1>
            <hr class="wp-header-end">
            <?php $this->render_tabs($active_tab); ?>

            <?php if (self::TAB_TEST_EMAILS === $active_tab): ?>
                <?php $this->render_test_emails_tab(); ?>
            <?php else: ?>
                <?php
                $paged = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
                $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
                $per_page = 20;
                $offset = ($paged - 1) * $per_page;

                $db = PL_Email_Log_DB::get_instance();
                $logs = $db->get_logs($per_page, $offset, $search);
                $total_count = $db->get_total_count($search);
                $total_pages = (int) ceil($total_count / $per_page);

                $nonce = wp_create_nonce('pl_email_log_nonce');
                ?>

                <div class="pl-email-log-header" style="margin-top: 20px; display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 20px;">
                    <p><?php echo esc_html__('Aquí se registran todos los correos enviados desde el sitio.', 'politeia-learning'); ?></p>
                    <form method="get">
                        <input type="hidden" name="page" value="pl-email-log">
                        <input type="hidden" name="tab" value="<?php echo esc_attr(self::TAB_EMAIL_LOG); ?>">
                        <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="<?php echo esc_attr__('Buscar destinatario o asunto...', 'politeia-learning'); ?>">
                        <button type="submit" class="button"><?php echo esc_html__('Buscar', 'politeia-learning'); ?></button>
                    </form>
                </div>

                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width: 15%;"><?php echo esc_html__('A quién', 'politeia-learning'); ?></th>
                            <th style="width: 25%;"><?php echo esc_html__('Asunto', 'politeia-learning'); ?></th>
                            <th style="width: 10%;"><?php echo esc_html__('Tipo', 'politeia-learning'); ?></th>
                            <th style="width: 35%;"><?php echo esc_html__('Archivo', 'politeia-learning'); ?></th>
                            <th style="width: 10%;"><?php echo esc_html__('Fecha y Hora', 'politeia-learning'); ?></th>
                            <th style="width: 5%;"><?php echo esc_html__('Acción', 'politeia-learning'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($logs)): ?>
                            <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td><strong><?php echo esc_html($log->recipient); ?></strong></td>
                                    <td><?php echo esc_html($log->subject); ?></td>
                                    <td>
                                        <span class="pl-badge type-<?php echo esc_attr(strtolower($log->email_type)); ?>">
                                            <?php echo esc_html($log->email_type); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <code style="font-size: 10px; background: #f1f5f9; padding: 2px 4px; border-radius: 3px; color: #64748b;">
                                            <?php echo esc_html($log->file_path ?: __('Desconocido', 'politeia-learning')); ?>
                                        </code>
                                    </td>
                                    <td><?php echo esc_html(date_i18n('d/m/Y H:i', strtotime((string) $log->sent_at))); ?></td>
                                    <td>
                                        <button type="button" class="button pl-view-email" data-id="<?php echo esc_attr($log->id); ?>">
                                            <?php echo esc_html__('Ver', 'politeia-learning'); ?>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6"><?php echo esc_html__('No se encontraron correos registrados.', 'politeia-learning'); ?></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>

                <?php if ($total_pages > 1): ?>
                    <div class="tablenav bottom">
                        <div class="tablenav-pages">
                            <span class="displaying-num"><?php echo esc_html(sprintf(__('%s elementos', 'politeia-learning'), (string) $total_count)); ?></span>
                            <span class="pagination-links">
                                <?php if ($paged > 1): ?>
                                    <a class="prev-page button" href="<?php echo esc_url(add_query_arg(['paged' => $paged - 1, 'tab' => self::TAB_EMAIL_LOG])); ?>">&lsaquo;</a>
                                <?php endif; ?>
                                <span class="paging-input">
                                    <span class="current-page"><?php echo esc_html((string) $paged); ?></span>
                                    <?php echo esc_html__('de', 'politeia-learning'); ?>
                                    <span class="total-pages"><?php echo esc_html((string) $total_pages); ?></span>
                                </span>
                                <?php if ($paged < $total_pages): ?>
                                    <a class="next-page button" href="<?php echo esc_url(add_query_arg(['paged' => $paged + 1, 'tab' => self::TAB_EMAIL_LOG])); ?>">&rsaquo;</a>
                                <?php endif; ?>
                            </span>
                        </div>
                    </div>
                <?php endif; ?>

                <div id="pl-email-overlay" class="pl-overlay" style="display:none;">
                    <div class="pl-modal">
                        <div class="pl-modal-header">
                            <h3><?php echo esc_html__('Visualizar Correo', 'politeia-learning'); ?></h3>
                            <button type="button" class="pl-close-modal" aria-label="<?php echo esc_attr__('Cerrar', 'politeia-learning'); ?>">&times;</button>
                        </div>
                        <div class="pl-modal-body">
                            <iframe id="pl-email-frame" style="width: 100%; height: 100%; border: none;"></iframe>
                        </div>
                    </div>
                </div>

            <style>
                .pl-badge {
                    padding: 4px 8px;
                    border-radius: 4px;
                    font-size: 11px;
                    font-weight: 600;
                    text-transform: uppercase;
                    background: #f0f0f1;
                    color: #50575e;
                }
                .type-woocommerce { background: #EBDCF2; color: #763F98; }
                .type-contacto { background: #DFF1E4; color: #1E6C3B; }
                .type-viajes { background: #DFF1FB; color: #155E8D; }
                .type-registro { background: #FEF3C7; color: #92400E; }
                .type-cuenta { background: #FCE7F3; color: #9D174D; }

                .pl-overlay {
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(0,0,0,0.8);
                    z-index: 99999;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    backdrop-filter: blur(5px);
                }
                .pl-modal {
                    background: #fff;
                    width: 90%;
                    max-width: 900px;
                    height: 85%;
                    border-radius: 12px;
                    display: flex;
                    flex-direction: column;
                    overflow: hidden;
                    box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5);
                }
                .pl-modal-header {
                    padding: 15px 25px;
                    background: #f8fafc;
                    border-bottom: 1px solid #e2e8f0;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                }
                .pl-modal-header h3 { margin: 0; font-size: 1.1rem; color: #1e293b; }
                .pl-close-modal {
                    background: none;
                    border: none;
                    font-size: 28px;
                    cursor: pointer;
                    color: #94a3b8;
                    transition: color 0.2s;
                }
                .pl-close-modal:hover { color: #ef4444; }
                .pl-modal-body { flex-grow: 1; background: #f1f5f9; }
            </style>

            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const overlay = document.getElementById('pl-email-overlay');
                    const frame = document.getElementById('pl-email-frame');
                    const closeBtn = document.querySelector('.pl-close-modal');

                    document.querySelectorAll('.pl-view-email').forEach(btn => {
                        btn.onclick = function() {
                            const id = this.getAttribute('data-id');
                            frame.src = 'about:blank';
                            overlay.style.display = 'flex';

	                            fetch('<?php echo admin_url('admin-ajax.php'); ?>?action=pl_get_email_content&id=' + id + '&nonce=<?php echo $nonce; ?>')
                                .then(response => response.text())
                                .then(html => {
                                    const doc = frame.contentWindow.document;
                                    doc.open();
                                    doc.write(html);
                                    doc.close();
                                });
                        };
                    });

                    closeBtn.onclick = function() {
                        overlay.style.display = 'none';
                        frame.src = 'about:blank';
                    };

                    overlay.onclick = function(e) {
                        if (e.target === overlay) {
                            closeBtn.onclick();
                        }
                    };
                });
            </script>
            <?php endif; ?>
        </div>
        <?php
    }
}
