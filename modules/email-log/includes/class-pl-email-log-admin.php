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

    private function get_email_template_allowed_html(): array
    {
        $allowed = wp_kses_allowed_html('post');
        $allowed['style'] = [];
        $allowed['html'] = ['lang' => true];
        $allowed['head'] = [];
        $allowed['body'] = ['style' => true, 'class' => true];
        $allowed['meta'] = ['content' => true, 'name' => true, 'charset' => true, 'http-equiv' => true];
        $allowed['title'] = [];
        $allowed['link'] = ['rel' => true, 'href' => true, 'type' => true];
        $allowed['center'] = [];
        
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
