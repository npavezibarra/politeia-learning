<?php
/**
 * AJAX handlers for Email Log.
 */

if (!defined('ABSPATH')) {
    exit;
}

final class PL_Email_Log_Ajax
{
    private $admin;

    public function __construct(PL_Email_Log_Admin $admin)
    {
        $this->admin = $admin;
    }

    public function register_hooks(): void
    {
        add_action('wp_ajax_pl_get_email_content', [$this, 'ajax_get_email_content']);
        add_action('wp_ajax_pl_get_test_email_preview', [$this, 'ajax_get_test_email_preview']);
        add_action('wp_ajax_pl_send_test_email', [$this, 'ajax_send_test_email']);
        add_action('wp_ajax_pl_get_test_email_template', [$this, 'ajax_get_test_email_template']);
        add_action('wp_ajax_pl_save_test_email_template', [$this, 'ajax_save_test_email_template']);
        add_action('wp_ajax_pl_set_test_email_template_mode', [$this, 'ajax_set_test_email_template_mode']);
    }

    public function ajax_get_email_content(): void
    {
        check_ajax_referer('pl_email_log_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die();
        }

        $id = isset($_GET['id']) ? absint($_GET['id']) : 0;
        if (!$id) {
            wp_die();
        }

        $db = PL_Email_Log_DB::get_instance();
        $log = $db->get_log($id);

        if (!$log || empty($log->content)) {
            echo '<p>' . esc_html__('Contenido no disponible.', 'politeia-learning') . '</p>';
            wp_die();
        }

        // Si el contenido ya es HTML (empieza por <), lo mostramos tal cual
        if (strpos(trim($log->content), '<') === 0) {
            echo $log->content;
        } else {
            echo nl2br(esc_html($log->content));
        }
        wp_die();
    }

    public function ajax_get_test_email_preview(): void
    {
        check_ajax_referer(PL_Email_Log_Admin::TEST_EMAIL_NONCE_ACTION, 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die();
        }

        $key = isset($_GET['key']) ? sanitize_key($_GET['key']) : '';
        if ($key === '') {
            wp_die();
        }

        $preview = $this->admin->get_test_email_preview($key);
        if (empty($preview)) {
            echo $this->admin->render_test_email_preview_error_html(__('Email no encontrado o preview no disponible.', 'politeia-learning'));
            wp_die();
        }

        $settings = $this->admin->get_test_email_template_settings($key);
        $html = '';

        if ($settings['enabled'] && !empty($settings['template'])) {
            $html = $this->admin->build_custom_email_html($key, $preview, $settings['template']);
        } else {
            $html = $this->admin->render_test_email_preview_html($preview);
        }

        echo $html;
        wp_die();
    }

    public function ajax_send_test_email(): void
    {
        check_ajax_referer(PL_Email_Log_Admin::TEST_EMAIL_NONCE_ACTION, 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'No permission']);
        }

        $key = isset($_POST['key']) ? sanitize_key($_POST['key']) : '';
        if ($key === '') {
            wp_send_json_error(['message' => 'No key']);
        }

        $preview = $this->admin->get_test_email_preview($key);
        if (empty($preview)) {
            wp_send_json_error(['message' => 'No preview']);
        }

        $settings = $this->admin->get_test_email_template_settings($key);

        $subject = isset($preview['subject']) ? (string) $preview['subject'] : '';
        $body = '';

        if ($settings['enabled'] && !empty($settings['template'])) {
            $body = $this->admin->build_custom_email_html($key, $preview, $settings['template']);
        } else {
            $body = isset($preview['message_html']) && !empty($preview['message_html'])
                ? (string) $preview['message_html']
                : (isset($preview['message_text']) ? nl2br((string) $preview['message_text']) : '');
        }

        $user = wp_get_current_user();
        $to = $user && $user->user_email ? (string) $user->user_email : (string) get_option('admin_email');

        $headers = ['Content-Type: text/html; charset=UTF-8'];

        $sent = wp_mail($to, $subject, $body, $headers);

        if ($sent) {
            wp_send_json_success(['to' => $to]);
        } else {
            wp_send_json_error(['message' => 'Failed to send']);
        }
    }

    public function ajax_get_test_email_template(): void
    {
        check_ajax_referer(PL_Email_Log_Admin::TEST_EMAIL_NONCE_ACTION, 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error();
        }

        $key = isset($_GET['key']) ? sanitize_key($_GET['key']) : '';
        if ($key === '') {
            wp_send_json_error();
        }

        $settings = $this->admin->get_test_email_template_settings($key);
        wp_send_json_success($settings);
    }

    public function ajax_save_test_email_template(): void
    {
        check_ajax_referer(PL_Email_Log_Admin::TEST_EMAIL_NONCE_ACTION, 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error();
        }

        $key = isset($_POST['key']) ? sanitize_key($_POST['key']) : '';
        if ($key === '') {
            wp_send_json_error();
        }

        $template = isset($_POST['template']) ? (string) $_POST['template'] : '';
        $enabled = !empty($_POST['enabled']);

        $templates = get_option(PL_Email_Log_Admin::TEST_EMAIL_TEMPLATES_OPTION, []);
        if (!is_array($templates)) {
            $templates = [];
        }

        $templates[$key] = [
            'template' => $template,
            'enabled' => $enabled,
        ];

        update_option(PL_Email_Log_Admin::TEST_EMAIL_TEMPLATES_OPTION, $templates);

        wp_send_json_success();
    }

    public function ajax_set_test_email_template_mode(): void
    {
        check_ajax_referer(PL_Email_Log_Admin::TEST_EMAIL_NONCE_ACTION, 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error();
        }

        $key = isset($_POST['key']) ? sanitize_key($_POST['key']) : '';
        $mode = isset($_POST['mode']) ? sanitize_key($_POST['mode']) : '';

        if ($key === '' || ($mode !== 'custom' && $mode !== 'traditional')) {
            wp_send_json_error();
        }

        $templates = get_option(PL_Email_Log_Admin::TEST_EMAIL_TEMPLATES_OPTION, []);
        if (!is_array($templates)) {
            $templates = [];
        }

        if (!isset($templates[$key])) {
            $templates[$key] = ['template' => '', 'enabled' => false];
        }

        $templates[$key]['enabled'] = ($mode === 'custom');

        update_option(PL_Email_Log_Admin::TEST_EMAIL_TEMPLATES_OPTION, $templates);

        wp_send_json_success();
    }
}
