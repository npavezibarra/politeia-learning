<?php
/**
 * Configure SMTP for wp_mail() via PHPMailer.
 *
 * Intended for simple Gmail SMTP (App Password) usage.
 */

if (!defined('ABSPATH')) {
    exit;
}

final class PL_Email_SMTP
{
    public const OPTION_KEY = 'pl_email_smtp_settings_v1';
    private const ENC_PREFIX = 'enc:';

    public static function init(): void
    {
        add_action('phpmailer_init', [__CLASS__, 'configure_phpmailer'], 10, 1);
        add_action('wp_mail_failed', [__CLASS__, 'maybe_log_wp_mail_failed'], 10, 1);
    }

    /**
     * @return array<string,mixed>
     */
    public static function get_settings(): array
    {
        $defaults = [
            'enabled' => false,
            'host' => 'smtp.gmail.com',
            'port' => 587,
            'encryption' => 'tls', // tls|ssl|none
            'username' => '',
            'password' => '',
            'from_email' => '',
            'from_name' => '',
            'force_from' => true,
        ];

        $value = get_option(self::OPTION_KEY, []);
        $settings = is_array($value) ? array_merge($defaults, $value) : $defaults;

        $settings['enabled'] = (bool) ($settings['enabled'] ?? false);
        $settings['host'] = sanitize_text_field((string) ($settings['host'] ?? 'smtp.gmail.com'));
        $settings['port'] = (int) ($settings['port'] ?? 587);
        $settings['encryption'] = sanitize_key((string) ($settings['encryption'] ?? 'tls'));
        $settings['username'] = sanitize_email((string) ($settings['username'] ?? ''));
        $settings['password'] = is_string($settings['password'] ?? '') ? (string) $settings['password'] : '';
        $settings['from_email'] = sanitize_email((string) ($settings['from_email'] ?? ''));
        $settings['from_name'] = sanitize_text_field((string) ($settings['from_name'] ?? ''));
        $settings['force_from'] = (bool) ($settings['force_from'] ?? true);

        return $settings;
    }

    /**
     * @param array<string,mixed> $settings
     */
    public static function save_settings(array $settings): void
    {
        $previous = self::get_settings();
        $sanitized = [
            'enabled' => !empty($settings['enabled']),
            'host' => sanitize_text_field((string) ($settings['host'] ?? 'smtp.gmail.com')),
            'port' => (int) ($settings['port'] ?? 587),
            'encryption' => sanitize_key((string) ($settings['encryption'] ?? 'tls')),
            'username' => sanitize_email((string) ($settings['username'] ?? '')),
            'password' => (string) ($settings['password'] ?? ''),
            'from_email' => sanitize_email((string) ($settings['from_email'] ?? '')),
            'from_name' => sanitize_text_field((string) ($settings['from_name'] ?? '')),
            'force_from' => !empty($settings['force_from']),
        ];

        // Only re-encrypt if we received a new plain secret.
        if ($sanitized['password'] === '') {
            $sanitized['password'] = (string) ($previous['password'] ?? '');
        } elseif (strpos($sanitized['password'], self::ENC_PREFIX) !== 0) {
            $sanitized['password'] = self::encrypt_secret((string) $sanitized['password']);
        }

        update_option(self::OPTION_KEY, $sanitized, false);
    }

    public static function configure_phpmailer($phpmailer): void
    {
        if (!is_object($phpmailer)) {
            return;
        }

        $settings = self::get_settings();
        if (empty($settings['enabled'])) {
            return;
        }

        $username = (string) ($settings['username'] ?? '');
        $password = self::decrypt_secret((string) ($settings['password'] ?? ''));

        if ($username === '' || $password === '') {
            return;
        }

        $host = (string) ($settings['host'] ?? 'smtp.gmail.com');
        $port = (int) ($settings['port'] ?? 587);
        $encryption = (string) ($settings['encryption'] ?? 'tls');

        $phpmailer->isSMTP();
        $phpmailer->Host = $host;
        $phpmailer->Port = $port;
        $phpmailer->SMTPAuth = true;
        $phpmailer->Username = $username;
        $phpmailer->Password = $password;
        $phpmailer->CharSet = 'UTF-8';

        if ($encryption === 'ssl') {
            $phpmailer->SMTPSecure = 'ssl';
        } elseif ($encryption === 'tls') {
            $phpmailer->SMTPSecure = 'tls';
        } else {
            $phpmailer->SMTPSecure = '';
        }

        $from_email = (string) ($settings['from_email'] ?? '');
        if ($from_email === '') {
            $from_email = $username;
        }
        $from_name = (string) ($settings['from_name'] ?? '');
        if ($from_name === '') {
            $from_name = wp_specialchars_decode((string) get_bloginfo('name'), ENT_QUOTES);
        }

        if (!empty($settings['force_from'])) {
            try {
                $phpmailer->setFrom($from_email, $from_name, false);
            } catch (Throwable $e) {
                unset($e);
            }
        }
    }

    /**
     * Best-effort logging for mail failures.
     */
    public static function maybe_log_wp_mail_failed($wp_error): void
    {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }
        if (!($wp_error instanceof WP_Error)) {
            return;
        }

        $data = $wp_error->get_error_data();
        $message = $wp_error->get_error_message();
        error_log('[PL SMTP] wp_mail_failed: ' . $message . ' data=' . wp_json_encode($data));
    }

    private static function encryption_key(): string
    {
        $key = defined('AUTH_KEY') ? (string) AUTH_KEY : '';
        if ($key === '' && defined('SECURE_AUTH_KEY')) {
            $key = (string) SECURE_AUTH_KEY;
        }
        if ($key === '') {
            $key = wp_salt('auth');
        }
        return hash('sha256', $key, true);
    }

    private static function encrypt_secret(string $plain): string
    {
        $plain = trim($plain);
        if ($plain === '') {
            return '';
        }

        if (!function_exists('openssl_encrypt')) {
            return $plain;
        }

        $iv = random_bytes(16);
        $cipher = openssl_encrypt($plain, 'AES-256-CBC', self::encryption_key(), OPENSSL_RAW_DATA, $iv);
        if ($cipher === false) {
            return $plain;
        }

        return self::ENC_PREFIX . base64_encode($iv . $cipher);
    }

    private static function decrypt_secret(string $stored): string
    {
        $stored = (string) $stored;
        if ($stored === '') {
            return '';
        }

        if (strpos($stored, self::ENC_PREFIX) !== 0) {
            return $stored;
        }

        $payload = base64_decode(substr($stored, strlen(self::ENC_PREFIX)), true);
        if ($payload === false || strlen($payload) < 17) {
            return '';
        }

        if (!function_exists('openssl_decrypt')) {
            return '';
        }

        $iv = substr($payload, 0, 16);
        $cipher = substr($payload, 16);
        $plain = openssl_decrypt($cipher, 'AES-256-CBC', self::encryption_key(), OPENSSL_RAW_DATA, $iv);
        return is_string($plain) ? $plain : '';
    }
}
