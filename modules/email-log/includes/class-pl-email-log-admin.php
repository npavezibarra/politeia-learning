<?php
/**
 * Admin logic for Email Log Module.
 * Now acts as a clean orchestrator delegating to specialized components.
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/class-pl-email-log-data.php';
require_once __DIR__ . '/class-pl-email-log-ajax.php';

final class PL_Email_Log_Admin
{
    private static $instance = null;

    public static function get_instance(): self
    {
        if (null === self::$instance) {
            self::$instance = new self();
            self::$instance->init();
        }
        return self::$instance;
    }

    public const TEST_EMAIL_NONCE_ACTION = 'pl_test_email_nonce';
    public const TEST_EMAIL_TEMPLATES_OPTION = 'pl_test_email_templates';

    public const TAB_EMAIL_LOG = 'email-log';
    public const TAB_TEST_EMAILS = 'test-emails';

    private $ajax_handler;

    public function __construct()
    {
        $this->ajax_handler = new PL_Email_Log_Ajax($this);
    }

    public function init(): void
    {
        add_action('admin_menu', [$this, 'register_admin_pages'], 20);
        $this->ajax_handler->register_hooks();
    }

    public function register_admin_pages(): void
    {
        add_submenu_page(
            'politeia-learning',
            __('Email Log', 'politeia-learning'),
            __('Email Log', 'politeia-learning'),
            'manage_options',
            'pl-email-log',
            [$this, 'render_admin_page']
        );
    }

    private function get_active_tab(): string
    {
        $tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : self::TAB_EMAIL_LOG;
        return in_array($tab, [self::TAB_EMAIL_LOG, self::TAB_TEST_EMAILS], true) ? $tab : self::TAB_EMAIL_LOG;
    }

    private function render_tabs(string $active_tab): void
    {
        $tabs = [
            self::TAB_EMAIL_LOG => __('Registro de Emails', 'politeia-learning'),
            self::TAB_TEST_EMAILS => __('Test Emails', 'politeia-learning'),
        ];

        echo '<h2 class="nav-tab-wrapper" style="margin-bottom: 0;">';
        foreach ($tabs as $id => $label) {
            $class = ($id === $active_tab) ? 'nav-tab nav-tab-active' : 'nav-tab';
            $url = add_query_arg(['tab' => $id, 'paged' => false, 's' => false]);
            echo '<a href="' . esc_url($url) . '" class="' . esc_attr($class) . '">' . esc_html($label) . '</a>';
        }
        echo '</h2>';
    }

    /**
     * Core logic delegated to PL_Email_Log_Data
     */
    public function get_test_emails_catalog(): array
    {
        return PL_Email_Log_Data::get_test_emails_catalog();
    }

    public function get_test_email_template_settings(string $key): array
    {
        $value = get_option(self::TEST_EMAIL_TEMPLATES_OPTION, []);
        $templates = is_array($value) ? $value : [];
        $template = isset($templates[$key]) && is_array($templates[$key]) ? $templates[$key] : [];

        return [
            'enabled' => !empty($template['enabled']),
            'template' => isset($template['template']) ? (string) $template['template'] : '',
        ];
    }

    public function get_test_email_preview(string $key): array
    {
        $catalog = $this->get_test_emails_catalog();
        if (!isset($catalog[$key])) {
            return [];
        }

        $origin = isset($catalog[$key]['origin']) ? $catalog[$key]['origin'] : '';

        // Internal mapping for previews
        if ($origin === 'WP') {
            return $this->get_wp_test_email_preview($key);
        }
        if ($origin === 'Woo') {
            return $this->get_woo_test_email_preview($key, $catalog[$key]);
        }
        if ($origin === 'Learni') {
            return $this->get_learni_test_email_preview($key, $catalog[$key]);
        }

        return [
            'subject' => sprintf('[%s] %s', wp_specialchars_decode(get_option('blogname'), ENT_QUOTES), $key),
            'message_text' => sprintf('Preview no implementado para "%s".', $key),
        ];
    }

    /**
     * Helper logic for previews (kept here as they are site-specific logic, not raw data)
     */
    private function get_wp_test_email_preview(string $key): array
    {
        $site_name = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);
        $site_url = home_url();
        $user = wp_get_current_user();
        $username = $user->user_login ?: 'usuario';
        $user_email = $user->user_email ?: get_option('admin_email');

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
        ];

        return isset($samples[$key]) ? $samples[$key] : [
            'subject' => sprintf('[%s] Notificación WP', $site_name),
            'message_text' => "Este es un correo de prueba para el evento {$key}.",
        ];
    }

    private function get_woo_test_email_preview(string $key, array $item): array
    {
        return [
            'subject' => isset($item['label']) ? $item['label'] : $key,
            'message_text' => "Contenido de referencia para el correo de WooCommerce: {$key}",
        ];
    }

    private function get_learni_test_email_preview(string $key, array $item): array
    {
        $site_name = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);
        return [
            'subject' => sprintf('[%s] %s', $site_name, isset($item['label']) ? $item['label'] : $key),
            'message_text' => "Este es un correo de prueba del sistema Learni para el evento: {$key}.",
        ];
    }

    /**
     * Replacement logic for custom templates
     */
    public function build_custom_email_html(string $key, array $preview, string $template): string
    {
        $context = PL_Email_Log_Data::get_test_email_template_context($key, $preview);
        return strtr($template, $context);
    }

    public function render_test_email_preview_html(array $preview): string
    {
        $subject = isset($preview['subject']) ? $preview['subject'] : '';
        $message_text = isset($preview['message_text']) ? $preview['message_text'] : '';
        $message_html = isset($preview['message_html']) ? $preview['message_html'] : nl2br(esc_html($message_text));

        return '<html><body style="font-family:sans-serif; padding:20px;">' .
               '<h3>' . esc_html($subject) . '</h3>' . 
               '<div>' . $message_html . '</div>' .
               '</body></html>';
    }

    public function render_test_email_preview_error_html(string $message): string
    {
        return '<html><body style="color:red; font-family:sans-serif; padding:20px;">' . esc_html($message) . '</body></html>';
    }

    public function render_admin_page(): void
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
                <?php 
                $items = $this->get_test_emails_catalog();
                $origin_labels = array_unique(array_column($items, 'origin'));
                sort($origin_labels);
                $nonce = wp_create_nonce(self::TEST_EMAIL_NONCE_ACTION);
                $templates = get_option(self::TEST_EMAIL_TEMPLATES_OPTION, []);
                $instructions = PL_Email_Log_Data::get_test_emails_copy_instructions();
                
                include dirname(__DIR__) . '/templates/admin-test-emails.php';
                ?>
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

                include dirname(__DIR__) . '/templates/admin-log-table.php';
                ?>
            <?php endif; ?>
        </div>
        <?php
    }
}
