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
        if ($origin === 'Woo Custom') {
            return $this->get_woo_custom_test_email_preview($key, $catalog[$key]);
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
        $username = $user && $user->user_login ? (string) $user->user_login : 'usuario';
        $user_email = $user && $user->user_email ? (string) $user->user_email : (string) get_option('admin_email');

        // Check if a dedicated template exists
        if (class_exists('PL_Email')) {
            $html = PL_Email::render($key, $this->get_template_render_vars($key));
            
            if ($html !== '') {
                return [
                    'subject' => sprintf('[%s] %s', $site_name, $key),
                    'message_html' => wp_kses($html, $this->get_email_template_allowed_html()),
                    'message_text' => wp_strip_all_tags($html),
                ];
            }
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
        ];

        return isset($samples[$key]) ? $samples[$key] : [
            'subject' => sprintf('[%s] Notificación WP', $site_name),
            'message_text' => "Este es un correo de prueba para el evento {$key}.",
        ];
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

    private function get_woo_custom_test_email_preview(string $key, array $item): array
    {
        $template_rel = isset($item['default_template']) ? (string) $item['default_template'] : '';
        $path = defined('PL_PATH') ? PL_PATH . $template_rel : '';

        if ($template_rel === '' || !file_exists($path)) {
            return [
                'subject' => sprintf('[Woo Custom] %s', $key),
                'message_text' => sprintf('Archivo de template no econtrado: %s', $template_rel),
            ];
        }

        $order = $this->get_sample_wc_order();
        $site_name = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);
        
        $subject = isset($item['label']) ? (string) $item['label'] : $key;
        if ($order) {
            $subject = str_replace('#%s', '#' . $order->get_order_number(), $subject);
        }

        $vars = [
            'order' => $order,
            'logo_url' => $this->get_site_logo_url(),
            'view' => ($order && class_exists('PL_Woo_Emails')) ? PL_Woo_Emails::identify_order_type($order) : 'course',
            'access_links' => ($order && class_exists('PL_Woo_Emails')) ? PL_Woo_Emails::get_access_links($order) : [],
            'bank_details' => $order ? $this->get_bank_details($order) : [],
            'product' => ($order) ? current($order->get_items('line_item'))->get_product() : null,
            'refund' => null,
            'payment_url' => $order ? $order->get_checkout_payment_url() : home_url(),
        ];

        // Email Log attribution.
        if (class_exists('PL_Email_Log_Manager')) {
            PL_Email_Log_Manager::set_last_template_file($path);
        }

        extract($vars, EXTR_SKIP);
        ob_start();
        include $path;
        $html = (string) ob_get_clean();

        $allowed_html = $this->get_email_template_allowed_html();
        $safe_html = wp_kses($html, $allowed_html);

        return [
            'subject' => $subject,
            'message_html' => $safe_html,
            'message_text' => wp_strip_all_tags($safe_html),
        ];
    }

    private function get_sample_wc_order()
    {
        if (!function_exists('wc_get_orders')) {
            return null;
        }

        $orders = wc_get_orders(['limit' => 1, 'orderby' => 'date', 'order' => 'DESC']);
        return !empty($orders) ? reset($orders) : null;
    }

    private function get_site_logo_url(): string
    {
        // 1. Check for custom email logo specifically set in the settings
        $custom_logo_id = get_option('pl_email_custom_logo_id');
        if ($custom_logo_id) {
            $url = wp_get_attachment_image_url($custom_logo_id, 'full');
            if ($url) {
                return $url;
            }
        }

        // 2. Fallback to theme custom logo
        $logo_id = get_theme_mod('custom_logo');
        $url = $logo_id ? wp_get_attachment_image_url($logo_id, 'full') : '';
        
        // Rasterize SVG logos for email compatibility if conversion is possible
        if (!empty($url) && class_exists('PL_Core_Email_Assets')) {
            $url = PL_Core_Email_Assets::get_rasterized_logo_url($url);
        }
        
        return $url;
    }

    private function get_bank_details(WC_Order $order): array
    {
        $method_id = (string) $order->get_payment_method();
        if ($method_id !== 'bacs') return [];

        if (function_exists('WC') && WC() && isset(WC()->payment_gateways)) {
            $gateways = WC()->payment_gateways->get_available_payment_gateways();
            if (is_array($gateways) && isset($gateways['bacs'])) {
                $bacs = $gateways['bacs'];
                $accounts = is_object($bacs) && isset($bacs->account_details) ? $bacs->account_details : null;
                if (is_array($accounts) && !empty($accounts) && is_array($accounts[0])) {
                    return (array) $accounts[0];
                }
            }
        }
        return [];
    }

    private function get_learni_test_email_preview(string $key, array $item): array
    {
        $site_name = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);
        $site_url = home_url();

        $user = wp_get_current_user();
        $username = $user && $user->user_login ? (string) $user->user_login : 'usuario';
        $label = isset($item['label']) ? (string) $item['label'] : $key;

        // Proactively check for matching template files
        if (class_exists('PL_Email')) {
            $render_vars = $this->get_template_render_vars($key);
            
            // Special logic for quiz variations can still apply to vars before render
            if ($key === 'learni_final_quiz_completed') {
                $render_vars['percentage_first'] = random_int(0, 100);
                $render_vars['percentage_final'] = random_int(0, 100);
            } elseif ($key === 'learni_first_quiz_completed') {
                $render_vars['percentage'] = random_int(0, 100);
            }

            $html = PL_Email::render($key, $render_vars);
            
            if ($html !== '') {
                $safe_html = wp_kses($html, $this->get_email_template_allowed_html());
                return [
                    'subject' => sprintf('[%s] %s', $site_name, $label),
                    'message_html' => $safe_html,
                    'message_text' => wp_strip_all_tags($safe_html),
                ];
            }
        }

        // Fallback for special keys with non-exact template names or complex logic
        if ($key === 'learni_final_quiz_completed') {
            $percentage_first = random_int(0, 100);
            $percentage_final = random_int(0, 100);
            $delta = $percentage_final - $percentage_first;
            $delta_abs = abs($delta);
            $subject = ($delta > 0) ? '¡Excelente progreso! Has superado tu marca inicial 🚀' : (($delta === 0) ? 'Has mantenido tu nivel de conocimientos 📊' : 'Evaluación final completada: analicemos tus resultados 🔍');
            
            return [
                'subject' => $subject,
                'message_text' => "First Quiz: {$percentage_first}%\nFinal Quiz: {$percentage_final}%\nVariación: " . ($delta > 0 ? "+{$delta_abs}%" : ($delta < 0 ? "-{$delta_abs}%" : "0%")),
            ];
        }

        return [
            'subject' => sprintf('[%s] %s', $site_name, $label),
            'message_text' => "Evento Learni: {$key}\n\nHola {$username},\n\nEste es un correo de prueba para \"{$label}\".\n\n{$site_url}",
        ];
    }

    private function get_template_render_vars(string $key): array
    {
        $user = wp_get_current_user();
        $site_name = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);
        
        return [
            'user_name' => $user->display_name ?: 'Nicolas',
            'user_login' => $user->user_login ?: 'nicolas',
            'user_email' => $user->user_email ?: get_option('admin_email'),
            'site_name' => $site_name,
            'site_url' => home_url(),
            'invitee_name' => 'Partner Juan',
            'inviter_name' => $user->display_name ?: 'Politeia',
            'course_name' => 'Curso de Marketing Digital',
            'accept_url' => add_query_arg(['pl_invite' => 'accept'], home_url('/')),
            'reset_url' => add_query_arg(['key' => 'dummy', 'login' => 'dummy'], home_url('/restablecer-contrasena/')),
            'verification_url' => add_query_arg(['pl_auth_action' => 'confirm', 'token' => 'dummy'], home_url('/')),
            'token' => 'ABCD-1234',
        ];
    }

    private function get_email_template_allowed_html(): array
    {
        $allowed = wp_kses_allowed_html('post');
        $allowed['style'] = [];
        $allowed['html'] = ['lang' => true, 'xmlns' => true];
        $allowed['head'] = [];
        $allowed['body'] = ['style' => true, 'class' => true, 'bgcolor' => true];
        $allowed['meta'] = ['content' => true, 'name' => true, 'charset' => true, 'http-equiv' => true];
        $allowed['title'] = [];
        $allowed['link'] = ['rel' => true, 'href' => true, 'type' => true];
        $allowed['center'] = [];
        $allowed['table'] = ['width' => true, 'border' => true, 'cellpadding' => true, 'cellspacing' => true, 'style' => true, 'align' => true, 'bgcolor' => true];
        $allowed['tr'] = ['style' => true, 'align' => true, 'valign' => true];
        $allowed['td'] = ['width' => true, 'style' => true, 'align' => true, 'valign' => true, 'colspan' => true, 'rowspan' => true];
        $allowed['img'] = ['src' => true, 'alt' => true, 'width' => true, 'height' => true, 'border' => true, 'style' => true];
        
        return $allowed;
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

        // If it starts with <html it likely is a full document, just return it
        if (strpos(trim($message_html), '<html') === 0 || strpos(trim($message_html), '<!DOCTYPE') === 0) {
            return $message_html;
        }

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
        
        // Enqueue WordPress media scripts if on the Test Emails tab
        if (self::TAB_TEST_EMAILS === $active_tab) {
            wp_enqueue_media();
        }
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
