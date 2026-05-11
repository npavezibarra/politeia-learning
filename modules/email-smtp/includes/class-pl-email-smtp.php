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
    private const GOOGLE_TOKEN_ENDPOINT = 'https://oauth2.googleapis.com/token';
    private const GMAIL_SEND_ENDPOINT = 'https://gmail.googleapis.com/gmail/v1/users/me/messages/send';

    public static function init(): void
    {
        add_action('phpmailer_init', [__CLASS__, 'configure_phpmailer'], 10, 1);
        add_action('wp_mail_failed', [__CLASS__, 'maybe_log_wp_mail_failed'], 10, 1);
        add_filter('pre_wp_mail', [__CLASS__, 'maybe_send_via_gmail_api'], 10, 2);
    }

    /**
     * @return array<string,mixed>
     */
    public static function get_settings(): array
    {
        $defaults = [
            'enabled' => false,
            'mode' => 'gmail_oauth', // smtp|gmail_oauth
            'host' => 'smtp.gmail.com',
            'port' => 587,
            'encryption' => 'tls', // tls|ssl|none
            'username' => '',
            'password' => '',
            'oauth_client_id' => '',
            'oauth_client_secret' => '',
            'oauth_refresh_token' => '',
            'from_email' => '',
            'from_name' => '',
            'force_from' => true,
        ];

        $value = get_option(self::OPTION_KEY, []);
        $settings = is_array($value) ? array_merge($defaults, $value) : $defaults;

        $settings['enabled'] = (bool) ($settings['enabled'] ?? false);
        $settings['mode'] = sanitize_key((string) ($settings['mode'] ?? 'smtp'));
        $settings['host'] = sanitize_text_field((string) ($settings['host'] ?? 'smtp.gmail.com'));
        $settings['port'] = (int) ($settings['port'] ?? 587);
        $settings['encryption'] = sanitize_key((string) ($settings['encryption'] ?? 'tls'));
        $settings['username'] = sanitize_email((string) ($settings['username'] ?? ''));
        $settings['password'] = is_string($settings['password'] ?? '') ? (string) $settings['password'] : '';
        $settings['oauth_client_id'] = sanitize_text_field((string) ($settings['oauth_client_id'] ?? ''));
        $settings['oauth_client_secret'] = is_string($settings['oauth_client_secret'] ?? '') ? (string) $settings['oauth_client_secret'] : '';
        $settings['oauth_refresh_token'] = is_string($settings['oauth_refresh_token'] ?? '') ? (string) $settings['oauth_refresh_token'] : '';
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
            'mode' => sanitize_key((string) ($settings['mode'] ?? ($previous['mode'] ?? 'smtp'))),
            'host' => sanitize_text_field((string) ($settings['host'] ?? 'smtp.gmail.com')),
            'port' => (int) ($settings['port'] ?? 587),
            'encryption' => sanitize_key((string) ($settings['encryption'] ?? 'tls')),
            'username' => sanitize_email((string) ($settings['username'] ?? '')),
            'password' => (string) ($settings['password'] ?? ''),
            'oauth_client_id' => sanitize_text_field((string) ($settings['oauth_client_id'] ?? ($previous['oauth_client_id'] ?? ''))),
            'oauth_client_secret' => (string) ($settings['oauth_client_secret'] ?? ''),
            'oauth_refresh_token' => (string) ($settings['oauth_refresh_token'] ?? ''),
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

        if ($sanitized['oauth_client_secret'] === '') {
            $sanitized['oauth_client_secret'] = (string) ($previous['oauth_client_secret'] ?? '');
        } elseif (strpos($sanitized['oauth_client_secret'], self::ENC_PREFIX) !== 0) {
            $sanitized['oauth_client_secret'] = self::encrypt_secret((string) $sanitized['oauth_client_secret']);
        }

        if ($sanitized['oauth_refresh_token'] === '') {
            $sanitized['oauth_refresh_token'] = (string) ($previous['oauth_refresh_token'] ?? '');
        } elseif (strpos($sanitized['oauth_refresh_token'], self::ENC_PREFIX) !== 0) {
            $sanitized['oauth_refresh_token'] = self::encrypt_secret((string) $sanitized['oauth_refresh_token']);
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
        if (($settings['mode'] ?? 'smtp') !== 'smtp') {
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
     * Attempt to send via Gmail API (OAuth) before WP builds PHPMailer.
     *
     * @param null|bool $return Short-circuit value.
     * @param array<string,mixed> $atts wp_mail atts.
     * @return null|bool
     */
    public static function maybe_send_via_gmail_api($return, $atts)
    {
        if ($return !== null) {
            return $return;
        }

        $settings = self::get_settings();
        if (empty($settings['enabled']) || ($settings['mode'] ?? '') !== 'gmail_oauth') {
            return null;
        }

        $client_id = (string) ($settings['oauth_client_id'] ?? '');
        $client_secret = self::decrypt_secret((string) ($settings['oauth_client_secret'] ?? ''));
        $refresh_token = self::decrypt_secret((string) ($settings['oauth_refresh_token'] ?? ''));

        if ($client_id === '' || $client_secret === '' || $refresh_token === '') {
            return null;
        }

        $access_token = self::fetch_access_token($client_id, $client_secret, $refresh_token);
        if ($access_token === '') {
            return false;
        }

        $mime = self::build_mime_message($atts, $settings);
        if ($mime === '') {
            return false;
        }

        $body = wp_json_encode([
            'raw' => self::base64url_encode($mime),
        ]);

        $resp = wp_remote_post(self::GMAIL_SEND_ENDPOINT, [
            'timeout' => 20,
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json; charset=UTF-8',
            ],
            'body' => $body,
        ]);

        if (is_wp_error($resp)) {
            error_log('[PL SMTP] Gmail API send error: ' . $resp->get_error_message());
            return false;
        }

        $code = (int) wp_remote_retrieve_response_code($resp);
        if ($code >= 200 && $code < 300) {
            return true;
        }

        error_log('[PL SMTP] Gmail API send failed HTTP ' . $code . ' body=' . wp_remote_retrieve_body($resp));
        return false;
    }

    private static function fetch_access_token(string $client_id, string $client_secret, string $refresh_token): string
    {
        $resp = wp_remote_post(self::GOOGLE_TOKEN_ENDPOINT, [
            'timeout' => 20,
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded; charset=UTF-8',
            ],
            'body' => http_build_query([
                'client_id' => $client_id,
                'client_secret' => $client_secret,
                'refresh_token' => $refresh_token,
                'grant_type' => 'refresh_token',
            ], '', '&'),
        ]);

        if (is_wp_error($resp)) {
            error_log('[PL SMTP] OAuth token error: ' . $resp->get_error_message());
            return '';
        }

        $code = (int) wp_remote_retrieve_response_code($resp);
        $json = json_decode((string) wp_remote_retrieve_body($resp), true);
        if ($code < 200 || $code >= 300 || !is_array($json)) {
            error_log('[PL SMTP] OAuth token failed HTTP ' . $code . ' body=' . wp_remote_retrieve_body($resp));
            return '';
        }

        $token = isset($json['access_token']) ? (string) $json['access_token'] : '';
        return $token;
    }

    /**
     * @param array<string,mixed> $atts
     * @param array<string,mixed> $settings
     */
    private static function build_mime_message(array $atts, array $settings): string
    {
        $to = $atts['to'] ?? '';
        if (is_array($to)) {
            $to = implode(', ', $to);
        }
        $to = trim((string) $to);

        $subject = isset($atts['subject']) ? (string) $atts['subject'] : '';
        $message = isset($atts['message']) ? (string) $atts['message'] : '';

        $headers = $atts['headers'] ?? [];
        if (is_string($headers)) {
            $headers = preg_split("/\\r\\n|\\r|\\n/", $headers) ?: [];
        }
        if (!is_array($headers)) {
            $headers = [];
        }

        $from_email = (string) ($settings['from_email'] ?? '');
        if ($from_email === '') {
            $from_email = sanitize_email((string) ($settings['username'] ?? ''));
        }
        if ($from_email === '') {
            $from_email = sanitize_email((string) get_option('admin_email'));
        }
        $from_name = (string) ($settings['from_name'] ?? '');
        if ($from_name === '') {
            $from_name = wp_specialchars_decode((string) get_bloginfo('name'), ENT_QUOTES);
        }

        $content_type = 'text/plain; charset=UTF-8';
        foreach ($headers as $h) {
            if (stripos((string) $h, 'content-type:') === 0) {
                $content_type = trim(substr((string) $h, strlen('content-type:')));
                break;
            }
        }

        if ($to === '') {
            return '';
        }

        $lines = [];
        $lines[] = 'To: ' . $to;
        $lines[] = 'Subject: ' . self::encode_header($subject);
        $lines[] = 'From: ' . self::encode_header($from_name) . ' <' . $from_email . '>';
        $lines[] = 'MIME-Version: 1.0';
        $lines[] = 'Content-Type: ' . $content_type;
        $lines[] = '';
        $lines[] = $message;

        return implode("\r\n", $lines);
    }

    private static function encode_header(string $value): string
    {
        $value = (string) $value;
        if ($value === '') {
            return '';
        }
        return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }

    private static function base64url_encode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
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

    public static function decrypt_secret(string $stored): string
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
