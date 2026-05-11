<?php
/**
 * Admin UI for Email SMTP settings.
 */

if (!defined('ABSPATH')) {
    exit;
}

final class PL_Email_SMTP_Admin
{
    private const PAGE_SLUG = 'pl-email-smtp';
    private const NONCE_ACTION = 'pl_email_smtp_save';
    private const TEST_NONCE_ACTION = 'pl_email_smtp_test';
    private const OAUTH_STATE_TRANSIENT_PREFIX = 'pl_email_smtp_oauth_state_';

    public static function init(): void
    {
        add_action('admin_menu', [__CLASS__, 'register_page'], 25);
        add_action('admin_post_pl_email_smtp_save', [__CLASS__, 'handle_save']);
        add_action('admin_post_pl_email_smtp_test', [__CLASS__, 'handle_test']);
        add_action('admin_post_pl_email_smtp_oauth_start', [__CLASS__, 'handle_oauth_start']);
        add_action('admin_post_pl_email_smtp_oauth_callback', [__CLASS__, 'handle_oauth_callback']);
        add_action('admin_post_pl_email_smtp_oauth_disconnect', [__CLASS__, 'handle_oauth_disconnect']);
    }

    public static function register_page(): void
    {
        add_submenu_page(
            'politeia-learning',
            __('SMTP Email', 'politeia-learning'),
            __('SMTP Email', 'politeia-learning'),
            'manage_options',
            self::PAGE_SLUG,
            [__CLASS__, 'render_page']
        );
    }

    public static function render_page(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $settings = class_exists('PL_Email_SMTP') ? PL_Email_SMTP::get_settings() : [];
        $notices = [
            'saved' => __('Settings saved.', 'politeia-learning'),
            'test_ok' => __('Test email sent (accepted by mailer).', 'politeia-learning'),
            'test_fail' => __('Test email failed. Check your SMTP credentials and server logs.', 'politeia-learning'),
            'oauth_connected' => __('Gmail connected successfully.', 'politeia-learning'),
            'oauth_disconnected' => __('Gmail disconnected.', 'politeia-learning'),
            'oauth_missing_refresh' => __('OAuth completed, but no refresh token was returned. Try again with "prompt=consent" and ensure you are not reusing an old grant.', 'politeia-learning'),
            'oauth_not_connected' => __('Connect Gmail first, then send a test email.', 'politeia-learning'),
            'oauth_missing_client' => __('Please save OAuth Client ID and Secret first.', 'politeia-learning'),
        ];

        $notice_key = isset($_GET['pl_notice']) ? sanitize_key((string) $_GET['pl_notice']) : '';
        if ($notice_key && isset($notices[$notice_key])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($notices[$notice_key]) . '</p></div>';
        }

        $error_key = isset($_GET['pl_error']) ? sanitize_key((string) $_GET['pl_error']) : '';
        if ($error_key === 'test_fail') {
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($notices['test_fail']) . '</p></div>';
        }
        if ($error_key === 'oauth_not_connected') {
            echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html($notices['oauth_not_connected']) . '</p></div>';
        }
        if ($error_key === 'oauth_missing_client') {
            echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html($notices['oauth_missing_client']) . '</p></div>';
        }

        $action_url = admin_url('admin-post.php');
        $page_url = menu_page_url(self::PAGE_SLUG, false);
        $oauth_redirect_uri = admin_url('admin-post.php?action=pl_email_smtp_oauth_callback');
        $has_refresh = !empty($settings['oauth_refresh_token']);
        $mode = !empty($settings['mode']) ? (string) $settings['mode'] : 'gmail_oauth';

        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Gmail Email', 'politeia-learning'); ?></h1>
            <p style="max-width: 860px;">
                <?php echo esc_html__('Configura el envío de correos (wp_mail) usando Gmail. Recomendado: OAuth (Gmail API) para mejor compatibilidad en hosting sin SMTP.', 'politeia-learning'); ?>
            </p>

            <h2 class="title"><?php echo esc_html__('Settings', 'politeia-learning'); ?></h2>
            <form method="post" action="<?php echo esc_url($action_url); ?>">
                <input type="hidden" name="action" value="pl_email_smtp_save">
                <?php wp_nonce_field(self::NONCE_ACTION, 'pl_email_smtp_nonce'); ?>

                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row"><?php echo esc_html__('Enable', 'politeia-learning'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="enabled" value="1" <?php checked(!empty($settings['enabled'])); ?>>
                                    <?php echo esc_html__('Use Gmail for outgoing emails', 'politeia-learning'); ?>
                                </label>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><?php echo esc_html__('Mode', 'politeia-learning'); ?></th>
                            <td>
                                <fieldset>
                                    <label style="display:block; margin-bottom: 6px;">
                                        <input type="radio" name="mode" value="gmail_oauth" <?php checked($mode === 'gmail_oauth'); ?>>
                                        <?php echo esc_html__('OAuth (Gmail API) - Recommended', 'politeia-learning'); ?>
                                    </label>
                                    <label style="display:block;">
                                        <input type="radio" name="mode" value="smtp" <?php checked($mode === 'smtp'); ?>>
                                        <?php echo esc_html__('SMTP (App Password)', 'politeia-learning'); ?>
                                    </label>
                                </fieldset>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><?php echo esc_html__('OAuth Redirect URI', 'politeia-learning'); ?></th>
                            <td>
                                <code><?php echo esc_html($oauth_redirect_uri); ?></code>
                                <p class="description"><?php echo esc_html__('Agrega este URI en Google Cloud Console → Credentials → OAuth client → Authorized redirect URIs.', 'politeia-learning'); ?></p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><?php echo esc_html__('OAuth Client ID', 'politeia-learning'); ?></th>
                            <td>
                                <input type="text" class="regular-text" name="oauth_client_id" value="<?php echo esc_attr((string) ($settings['oauth_client_id'] ?? '')); ?>" placeholder="xxxxxx.apps.googleusercontent.com" autocomplete="off">
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><?php echo esc_html__('OAuth Client Secret', 'politeia-learning'); ?></th>
                            <td>
                                <input type="password" class="regular-text" name="oauth_client_secret" value="" placeholder="••••••••" autocomplete="new-password">
                                <p class="description"><?php echo esc_html__('No mostramos el secret guardado. Si lo dejas vacío, se mantiene el actual.', 'politeia-learning'); ?></p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><?php echo esc_html__('Gmail OAuth Connection', 'politeia-learning'); ?></th>
                            <td>
                                <?php if ($has_refresh): ?>
                                    <p style="margin:0 0 8px;"><strong><?php echo esc_html__('Status:', 'politeia-learning'); ?></strong> <?php echo esc_html__('Connected', 'politeia-learning'); ?></p>
                                <?php else: ?>
                                    <p style="margin:0 0 8px;"><strong><?php echo esc_html__('Status:', 'politeia-learning'); ?></strong> <?php echo esc_html__('Not connected', 'politeia-learning'); ?></p>
                                <?php endif; ?>

                                <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                                    <a class="button button-secondary" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=pl_email_smtp_oauth_start'), 'pl_email_smtp_oauth_start')); ?>">
                                        <?php echo esc_html__('Connect Gmail', 'politeia-learning'); ?>
                                    </a>
                                    <?php if ($has_refresh): ?>
                                        <a class="button" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=pl_email_smtp_oauth_disconnect'), 'pl_email_smtp_oauth_disconnect')); ?>">
                                            <?php echo esc_html__('Disconnect', 'politeia-learning'); ?>
                                        </a>
                                    <?php endif; ?>
                                </div>
                                <p class="description"><?php echo esc_html__('Conecta una cuenta Gmail para permitir el envío vía Gmail API.', 'politeia-learning'); ?></p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><?php echo esc_html__('SMTP Gmail Address', 'politeia-learning'); ?></th>
                            <td>
                                <input type="email" class="regular-text" name="username" value="<?php echo esc_attr((string) ($settings['username'] ?? '')); ?>" placeholder="you@gmail.com" autocomplete="off">
                                <p class="description"><?php echo esc_html__('Solo para modo SMTP. Para Gmail, usa la dirección completa.', 'politeia-learning'); ?></p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><?php echo esc_html__('SMTP App Password', 'politeia-learning'); ?></th>
                            <td>
                                <input type="password" class="regular-text" name="password" value="" placeholder="xxxx xxxx xxxx xxxx" autocomplete="new-password">
                                <p class="description"><?php echo esc_html__('Solo para modo SMTP. Si lo dejas vacío, se mantiene el actual.', 'politeia-learning'); ?></p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><?php echo esc_html__('From Name', 'politeia-learning'); ?></th>
                            <td>
                                <input type="text" class="regular-text" name="from_name" value="<?php echo esc_attr((string) ($settings['from_name'] ?? '')); ?>" placeholder="<?php echo esc_attr(wp_specialchars_decode((string) get_bloginfo('name'), ENT_QUOTES)); ?>">
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><?php echo esc_html__('From Email (optional)', 'politeia-learning'); ?></th>
                            <td>
                                <input type="email" class="regular-text" name="from_email" value="<?php echo esc_attr((string) ($settings['from_email'] ?? '')); ?>" placeholder="<?php echo esc_attr((string) ($settings['username'] ?? 'you@gmail.com')); ?>" autocomplete="off">
                                <p class="description"><?php echo esc_html__('Si está vacío, usamos el Gmail Address.', 'politeia-learning'); ?></p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><?php echo esc_html__('Force From', 'politeia-learning'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="force_from" value="1" <?php checked(!empty($settings['force_from'])); ?>>
                                    <?php echo esc_html__('Override From for all emails', 'politeia-learning'); ?>
                                </label>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <?php submit_button(__('Save Settings', 'politeia-learning')); ?>
            </form>

            <hr>

            <h2 class="title"><?php echo esc_html__('Test Email', 'politeia-learning'); ?></h2>
            <form method="post" action="<?php echo esc_url($action_url); ?>" style="max-width: 860px;">
                <input type="hidden" name="action" value="pl_email_smtp_test">
                <?php wp_nonce_field(self::TEST_NONCE_ACTION, 'pl_email_smtp_test_nonce'); ?>

                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row"><?php echo esc_html__('Send to', 'politeia-learning'); ?></th>
                            <td>
                                <input type="email" class="regular-text" name="to" value="<?php echo esc_attr(sanitize_email((string) get_option('admin_email'))); ?>">
                            </td>
                        </tr>
                    </tbody>
                </table>

                <?php submit_button(__('Send Test Email', 'politeia-learning'), 'secondary'); ?>
                <p class="description">
                    <?php
                    echo esc_html__('Si el test falla, revisa también el Email Log y los logs del servidor para errores SMTP.', 'politeia-learning');
                    ?>
                </p>
            </form>
        </div>
        <?php

        unset($page_url);
    }

    public static function handle_save(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Forbidden');
        }

        if (!isset($_POST['pl_email_smtp_nonce']) || !wp_verify_nonce((string) wp_unslash($_POST['pl_email_smtp_nonce']), self::NONCE_ACTION)) {
            wp_die('Invalid nonce');
        }

        $settings = class_exists('PL_Email_SMTP') ? PL_Email_SMTP::get_settings() : [];

        $new = [
            'enabled' => isset($_POST['enabled']) && (string) wp_unslash($_POST['enabled']) === '1',
            'mode' => isset($_POST['mode']) ? (string) wp_unslash($_POST['mode']) : (string) ($settings['mode'] ?? 'gmail_oauth'),
            'username' => isset($_POST['username']) ? (string) wp_unslash($_POST['username']) : '',
            'from_email' => isset($_POST['from_email']) ? (string) wp_unslash($_POST['from_email']) : '',
            'from_name' => isset($_POST['from_name']) ? (string) wp_unslash($_POST['from_name']) : '',
            'force_from' => isset($_POST['force_from']) && (string) wp_unslash($_POST['force_from']) === '1',
            'oauth_client_id' => isset($_POST['oauth_client_id']) ? (string) wp_unslash($_POST['oauth_client_id']) : '',
        ];

        $password = isset($_POST['password']) ? trim((string) wp_unslash($_POST['password'])) : '';
        if ($password === '') {
            // Keep existing encrypted secret
            $new['password'] = (string) ($settings['password'] ?? '');
        } else {
            $new['password'] = $password;
        }

        $oauth_secret = isset($_POST['oauth_client_secret']) ? trim((string) wp_unslash($_POST['oauth_client_secret'])) : '';
        if ($oauth_secret === '') {
            $new['oauth_client_secret'] = (string) ($settings['oauth_client_secret'] ?? '');
        } else {
            $new['oauth_client_secret'] = $oauth_secret;
        }

        // Keep refresh token unless explicitly overwritten by OAuth callback.
        $new['oauth_refresh_token'] = (string) ($settings['oauth_refresh_token'] ?? '');

        if (class_exists('PL_Email_SMTP')) {
            PL_Email_SMTP::save_settings($new);
        }

        $url = add_query_arg(['page' => self::PAGE_SLUG, 'pl_notice' => 'saved'], admin_url('admin.php'));
        wp_safe_redirect($url);
        exit;
    }

    public static function handle_test(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Forbidden');
        }

        if (!isset($_POST['pl_email_smtp_test_nonce']) || !wp_verify_nonce((string) wp_unslash($_POST['pl_email_smtp_test_nonce']), self::TEST_NONCE_ACTION)) {
            wp_die('Invalid nonce');
        }

        $to = isset($_POST['to']) ? sanitize_email((string) wp_unslash($_POST['to'])) : '';
        if ($to === '' || !is_email($to)) {
            $url = add_query_arg(['page' => self::PAGE_SLUG, 'pl_error' => 'test_fail'], admin_url('admin.php'));
            wp_safe_redirect($url);
            exit;
        }

        $settings = class_exists('PL_Email_SMTP') ? PL_Email_SMTP::get_settings() : [];
        if (!empty($settings['enabled']) && (($settings['mode'] ?? '') === 'gmail_oauth') && empty($settings['oauth_refresh_token'])) {
            $url = add_query_arg(['page' => self::PAGE_SLUG, 'pl_error' => 'oauth_not_connected'], admin_url('admin.php'));
            wp_safe_redirect($url);
            exit;
        }

        $sent = (bool) wp_mail(
            $to,
            __('Politeia test email', 'politeia-learning'),
            __('This is a test email to verify Gmail mailer configuration.', 'politeia-learning'),
            ['Content-Type: text/plain; charset=UTF-8']
        );

        $url = add_query_arg(
            ['page' => self::PAGE_SLUG, $sent ? 'pl_notice' : 'pl_error' => $sent ? 'test_ok' : 'test_fail'],
            admin_url('admin.php')
        );
        wp_safe_redirect($url);
        exit;
    }

    public static function handle_oauth_start(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Forbidden');
        }
        check_admin_referer('pl_email_smtp_oauth_start');

        $settings = class_exists('PL_Email_SMTP') ? PL_Email_SMTP::get_settings() : [];
        $client_id = isset($settings['oauth_client_id']) ? (string) $settings['oauth_client_id'] : '';
        $client_secret = isset($settings['oauth_client_secret']) ? (string) $settings['oauth_client_secret'] : '';

        if ($client_id === '' || $client_secret === '') {
            $url = add_query_arg(['page' => self::PAGE_SLUG, 'pl_error' => 'oauth_missing_client'], admin_url('admin.php'));
            wp_safe_redirect($url);
            exit;
        }

        $redirect_uri = admin_url('admin-post.php?action=pl_email_smtp_oauth_callback');
        $state = wp_generate_password(32, false, false);
        set_transient(self::OAUTH_STATE_TRANSIENT_PREFIX . get_current_user_id(), $state, 10 * MINUTE_IN_SECONDS);

        $auth_url = add_query_arg([
            'client_id' => $client_id,
            'redirect_uri' => $redirect_uri,
            'response_type' => 'code',
            'access_type' => 'offline',
            'prompt' => 'consent',
            'include_granted_scopes' => 'true',
            'scope' => implode(' ', [
                'https://www.googleapis.com/auth/gmail.send',
                'openid',
                'email',
            ]),
            'state' => $state,
        ], 'https://accounts.google.com/o/oauth2/v2/auth');

        wp_safe_redirect($auth_url);
        exit;
    }

    public static function handle_oauth_callback(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Forbidden');
        }

        $code = isset($_GET['code']) ? trim((string) wp_unslash($_GET['code'])) : '';
        $state = isset($_GET['state']) ? trim((string) wp_unslash($_GET['state'])) : '';
        $expected = (string) get_transient(self::OAUTH_STATE_TRANSIENT_PREFIX . get_current_user_id());
        delete_transient(self::OAUTH_STATE_TRANSIENT_PREFIX . get_current_user_id());

        if ($code === '' || $state === '' || $expected === '' || !hash_equals($expected, $state)) {
            wp_die('Invalid OAuth state');
        }

        $settings = class_exists('PL_Email_SMTP') ? PL_Email_SMTP::get_settings() : [];
        $client_id = (string) ($settings['oauth_client_id'] ?? '');
        $client_secret_plain = class_exists('PL_Email_SMTP')
            ? PL_Email_SMTP::decrypt_secret((string) ($settings['oauth_client_secret'] ?? ''))
            : (string) ($settings['oauth_client_secret'] ?? '');

        if ($client_id === '' || $client_secret_plain === '') {
            wp_die('Missing OAuth client credentials');
        }

        $redirect_uri = admin_url('admin-post.php?action=pl_email_smtp_oauth_callback');

        // Exchange code for tokens.
        $resp = wp_remote_post('https://oauth2.googleapis.com/token', [
            'timeout' => 20,
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded; charset=UTF-8',
            ],
            'body' => http_build_query([
                'code' => $code,
                'client_id' => $client_id,
                'client_secret' => $client_secret_plain,
                'redirect_uri' => $redirect_uri,
                'grant_type' => 'authorization_code',
            ], '', '&'),
        ]);

        if (is_wp_error($resp)) {
            wp_die('OAuth token request failed: ' . esc_html($resp->get_error_message()));
        }

        $json = json_decode((string) wp_remote_retrieve_body($resp), true);
        $refresh = is_array($json) && isset($json['refresh_token']) ? (string) $json['refresh_token'] : '';

        if ($refresh !== '' && class_exists('PL_Email_SMTP')) {
            $settings['oauth_refresh_token'] = $refresh;
            $settings['mode'] = 'gmail_oauth';
            PL_Email_SMTP::save_settings($settings);
            $url = add_query_arg(['page' => self::PAGE_SLUG, 'pl_notice' => 'oauth_connected'], admin_url('admin.php'));
            wp_safe_redirect($url);
            exit;
        }

        $url = add_query_arg(['page' => self::PAGE_SLUG, 'pl_notice' => 'oauth_missing_refresh'], admin_url('admin.php'));
        wp_safe_redirect($url);
        exit;
    }

    public static function handle_oauth_disconnect(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Forbidden');
        }
        check_admin_referer('pl_email_smtp_oauth_disconnect');

        if (class_exists('PL_Email_SMTP')) {
            $settings = PL_Email_SMTP::get_settings();
            $settings['oauth_refresh_token'] = '';
            PL_Email_SMTP::save_settings($settings);
        }

        $url = add_query_arg(['page' => self::PAGE_SLUG, 'pl_notice' => 'oauth_disconnected'], admin_url('admin.php'));
        wp_safe_redirect($url);
        exit;
    }
}
