<?php
/**
 * Manager for Email Log.
 */

if (!defined('ABSPATH')) {
    exit;
}

final class PL_Email_Log_Manager
{
    private const DB_VERSION_OPTION = 'politeia_email_log_db_version_v1';
    private const LEGACY_DB_VERSION_OPTION = 'pl_email_log_db_version_v1';
    private const DB_VERSION = '1.0.0';

    private static $instance = null;
    private $capture_data = null;
    private static $last_template_file = '';

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function init()
    {
        return self::get_instance();
    }

    private function __construct()
    {
        // Ensure table exists (admin-first, but also safe for frontend emails).
        add_action('admin_init', [$this, 'maybe_create_table'], 5);
        add_action('init', [$this, 'maybe_create_table'], 5);
        $this->maybe_create_table();

        add_filter('wp_mail', [$this, 'capture_wp_mail_args'], 9999);
        add_action('wp_mail_failed', [$this, 'log_failed_email'], 10, 1);

        global $wp_version;
        if (isset($wp_version) && version_compare($wp_version, '5.9', '>=')) {
            add_action('wp_mail_succeeded', [$this, 'log_successful_email'], 10, 1);
        } else {
            add_action('phpmailer_init', [$this, 'fallback_capture_phpmailer'], 10, 1);
        }

        require_once __DIR__ . '/class-pl-email-log-admin.php';
        PL_Email_Log_Admin::get_instance();
    }

    /**
     * Ensure table exists.
     */
    public function maybe_create_table()
    {
        $db = PL_Email_Log_DB::get_instance();
        $stored_version = get_option(self::DB_VERSION_OPTION, '');
        if ($stored_version === '') {
            $stored_version = get_option(self::LEGACY_DB_VERSION_OPTION, '');
        }
        $needs_install = ($stored_version !== self::DB_VERSION);
        $missing_table = !$db->table_exists();

        if ($needs_install || $missing_table) {
            PL_Email_Log_DB::get_instance()->create_table();
            update_option(self::DB_VERSION_OPTION, self::DB_VERSION);
        }
    }

    /**
     * Store the last template file processed (for custom plugin emails).
     */
    public static function set_last_template_file($file)
    {
        self::$last_template_file = (string) $file;
    }

    /**
     * Capture arguments from wp_mail filter.
     *
     * @param array<string,mixed> $args
     * @return array<string,mixed>
     */
    public function capture_wp_mail_args($args)
    {
        $this->capture_data = $args;

        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        $caller_file = '';

        foreach ($trace as $step) {
            if (!isset($step['file'])) {
                continue;
            }

            $file = wp_normalize_path($step['file']);

            if (strpos($file, '/wp-includes/') !== false || strpos($file, '/wp-admin/') !== false) {
                continue;
            }

            if (
                strpos($file, 'class-pl-email-log-manager.php') !== false
                || strpos($file, 'class-pl-email-log-db.php') !== false
                || strpos($file, 'class-pl-email-log-admin.php') !== false
            ) {
                continue;
            }

            $caller_file = $file;
            break;
        }

        $this->capture_data['caller_file'] = self::$last_template_file ?: ($caller_file ?: 'unknown');
        self::$last_template_file = '';

        return $args;
    }

    /**
     * Log successful email.
     */
    public function log_successful_email($mail_data)
    {
        $this->save_log('success');
    }

    /**
     * Log failed email.
     */
    public function log_failed_email($wp_error)
    {
        $this->save_log('failed');
    }

    /**
     * Fallback for older WP versions.
     */
    public function fallback_capture_phpmailer($phpmailer)
    {
        $this->save_log('sent');
    }

    /**
     * Detect email type and save to DB.
     */
    private function save_log($status = 'sent')
    {
        if (!$this->capture_data) {
            return;
        }

        // Best-effort: ensure table exists before inserting.
        $this->maybe_create_table();

        $args = $this->capture_data;
        $this->capture_data = null;

        $to = is_array($args['to']) ? implode(', ', $args['to']) : (string) ($args['to'] ?? '');
        $subject = (string) ($args['subject'] ?? '');
        $message = (string) ($args['message'] ?? '');
        $headers = is_array($args['headers'] ?? null) ? implode("\n", (array) $args['headers']) : (string) ($args['headers'] ?? '');

        $type_info = $this->identify_email_info($subject, $headers, $message);
        $caller_file = (string) ($args['caller_file'] ?? 'unknown');

        $display_file = basename($caller_file);
        $normalized = wp_normalize_path($caller_file);
        $needle = '/wp-content/plugins/politeia-learning/';
        $pos = strpos($normalized, $needle);
        if (false !== $pos) {
            $display_file = '.../' . substr($normalized, $pos + strlen($needle));
        }

        PL_Email_Log_DB::get_instance()->insert_log([
            'recipient'  => $to,
            'subject'    => $subject,
            'content'    => $message,
            'headers'    => $headers,
            'email_type' => $type_info['type'],
            'template'   => $type_info['template'],
            'file_path'  => $display_file,
            'sent_at'    => current_time('mysql'),
        ]);
    }

    /**
     * Logic to identify email type and template.
     *
     * @return array{type:string,template:string}
     */
    private function identify_email_info($subject, $headers, $message)
    {
        $subject_low = strtolower((string) $subject);
        $headers_low = strtolower((string) $headers);

        $type = 'General';
        $template = 'Genérico';

        if (strpos($headers_low, 'x-wc-email') !== false) {
            $type = 'WooCommerce';

            if (preg_match('/x-wc-email:\s*([a-z0-9_]+)/i', (string) $headers, $matches)) {
                $template_id = $matches[1];
                $template = $this->map_wc_template($template_id);
            }
        } elseif (
            strpos($headers_low, 'woocommerce') !== false
            || strpos($subject_low, 'pedido') !== false
            || strpos($subject_low, 'orden') !== false
        ) {
            $type = 'WooCommerce';
            $template = 'Pedido (General)';
        }

        if ($type === 'General') {
            if (strpos($subject_low, 'interés: viaje') !== false || strpos($subject_low, 'viaje') !== false) {
                $type = 'Viajes';
                if (preg_match('/viaje\s+(.+)\s+—/i', (string) $subject, $m)) {
                    $template = "Interés: " . trim($m[1]);
                } else {
                    $template = 'Interés: Viaje';
                }
            } elseif (strpos($subject_low, 'contacto') !== false) {
                $type = 'Contacto';
                $template = 'Consulta General';
            }
        }

        if ($type === 'General') {
            if (strpos($subject_low, 'bienvenida') !== false || strpos($subject_low, 'registro') !== false) {
                $type = 'Registro';
                $template = 'Bienvenida (Confirmación)';
            } elseif (strpos($subject_low, 'restablecer') !== false || (strpos($subject_low, 'contraseña') !== false && strpos($subject_low, 'reset') !== false)) {
                $type = 'Cuenta';
                $template = 'Restablecer Contraseña';
            }
        }

        if ($type === 'General' && (strpos($subject_low, 'notificación') !== false || strpos($subject_low, 'lecciones') !== false)) {
            $type = 'Lecciones';
            $template = 'Notificación Alumnos';
        }

        return ['type' => $type, 'template' => $template];
    }

    /**
     * Map WooCommerce template IDs to friendly names.
     */
    private function map_wc_template($id)
    {
        $map = [
            'new_order'                         => 'Nueva Orden (Admin)',
            'customer_processing_order'         => 'Pedido Recibido (Cliente)',
            'customer_completed_order'          => 'Pedido Completado',
            'customer_on_hold_order'            => 'Pedido en Espera',
            'cancelled_order'                   => 'Pedido Cancelado',
            'failed_order'                      => 'Pedido Fallido',
            'customer_invoice'                  => 'Factura Cliente',
            'customer_note'                     => 'Nota del Pedido',
            'customer_reset_password'           => 'Restablecer Contraseña',
            'customer_new_account'              => 'Bienvenida Cliente',
            'customer_refunded_order'           => 'Pedido Reembolsado',
            'customer_partially_refunded_order' => 'Pedido Rerembolsado Parcial',
        ];

        return $map[$id] ?? ucwords(str_replace('_', ' ', (string) $id));
    }
}
