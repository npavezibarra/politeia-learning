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

    public static function init(): void
    {
        add_action('admin_menu', [__CLASS__, 'register_page'], 25);
        add_action('admin_post_pl_email_smtp_save', [__CLASS__, 'handle_save']);
        add_action('admin_post_pl_email_smtp_test', [__CLASS__, 'handle_test']);
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
        ];

        $notice_key = isset($_GET['pl_notice']) ? sanitize_key((string) $_GET['pl_notice']) : '';
        if ($notice_key && isset($notices[$notice_key])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($notices[$notice_key]) . '</p></div>';
        }

        $error_key = isset($_GET['pl_error']) ? sanitize_key((string) $_GET['pl_error']) : '';
        if ($error_key === 'test_fail') {
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($notices['test_fail']) . '</p></div>';
        }

        $action_url = admin_url('admin-post.php');
        $page_url = menu_page_url(self::PAGE_SLUG, false);

        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('SMTP Email (Gmail)', 'politeia-learning'); ?></h1>
            <p style="max-width: 860px;">
                <?php echo esc_html__('Configura SMTP para que los correos de WordPress (wp_mail) salgan vía Gmail. Recomendado: usar "App Password" (requiere 2FA).', 'politeia-learning'); ?>
            </p>

            <h2 class="title"><?php echo esc_html__('Settings', 'politeia-learning'); ?></h2>
            <form method="post" action="<?php echo esc_url($action_url); ?>">
                <input type="hidden" name="action" value="pl_email_smtp_save">
                <?php wp_nonce_field(self::NONCE_ACTION, 'pl_email_smtp_nonce'); ?>

                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row"><?php echo esc_html__('Enable SMTP', 'politeia-learning'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="enabled" value="1" <?php checked(!empty($settings['enabled'])); ?>>
                                    <?php echo esc_html__('Use SMTP for outgoing emails', 'politeia-learning'); ?>
                                </label>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><?php echo esc_html__('Gmail Address', 'politeia-learning'); ?></th>
                            <td>
                                <input type="email" class="regular-text" name="username" value="<?php echo esc_attr((string) ($settings['username'] ?? '')); ?>" placeholder="you@gmail.com" autocomplete="off">
                                <p class="description"><?php echo esc_html__('Este será el usuario SMTP. Para Gmail, usa la dirección completa.', 'politeia-learning'); ?></p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><?php echo esc_html__('Gmail App Password', 'politeia-learning'); ?></th>
                            <td>
                                <input type="password" class="regular-text" name="password" value="" placeholder="xxxx xxxx xxxx xxxx" autocomplete="new-password">
                                <p class="description"><?php echo esc_html__('No mostramos el password guardado. Si lo dejas vacío, se mantiene el actual.', 'politeia-learning'); ?></p>
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
            'username' => isset($_POST['username']) ? (string) wp_unslash($_POST['username']) : '',
            'from_email' => isset($_POST['from_email']) ? (string) wp_unslash($_POST['from_email']) : '',
            'from_name' => isset($_POST['from_name']) ? (string) wp_unslash($_POST['from_name']) : '',
            'force_from' => isset($_POST['force_from']) && (string) wp_unslash($_POST['force_from']) === '1',
        ];

        $password = isset($_POST['password']) ? trim((string) wp_unslash($_POST['password'])) : '';
        if ($password === '') {
            // Keep existing encrypted secret
            $new['password'] = (string) ($settings['password'] ?? '');
        } else {
            $new['password'] = $password;
        }

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

        $sent = (bool) wp_mail(
            $to,
            __('Politeia SMTP test email', 'politeia-learning'),
            __('This is a test email to verify SMTP configuration.', 'politeia-learning'),
            ['Content-Type: text/plain; charset=UTF-8']
        );

        $url = add_query_arg(
            ['page' => self::PAGE_SLUG, $sent ? 'pl_notice' : 'pl_error' => $sent ? 'test_ok' : 'test_fail'],
            admin_url('admin.php')
        );
        wp_safe_redirect($url);
        exit;
    }
}

