<?php
/**
 * Email: password reset
 *
 * Available variables:
 * - $user_login (string)
 * - $reset_url  (string)
 */

if (!defined('ABSPATH')) {
    exit;
}

$user_login = isset($user_login) ? (string) $user_login : '';
$reset_url = isset($reset_url) ? (string) $reset_url : '';

$locale = determine_locale();
$lang = strtolower(substr((string) $locale, 0, 2));
$logo_url = 'http://nupoliteia.local/wp-content/uploads/2026/04/Captura-de-pantalla-2026-04-05-a-las-12.29.54-p.m.png';
$pl_email_header_title = __('RESTABLECER CLAVE', 'politeia-learning');
$pl_email_document_title = (string) __('Reset Password', 'politeia-learning');
?>
<?php include PL_PATH . 'templates/emails/partials/unified-shell-top.php'; ?>

                        <tr>
                            <td align="center" class="poppins" style="padding:50px 40px 20px;font-size:16px;line-height:1.6;color:#000000;">
                                <?php
                                echo esc_html(
                                    sprintf(
                                        /* translators: 1: username */
                                        __('Hello %1$s,', 'politeia-learning'),
                                        $user_login !== '' ? $user_login : __('there', 'politeia-learning')
                                    )
                                );
                                ?>
                                <br><br>
                                <?php echo esc_html__('We received a request to reset your password. If this was you, use the button below to continue:', 'politeia-learning'); ?>
                            </td>
                        </tr>

                        <tr>
                            <td align="center" style="padding:10px 40px 30px;">
                                <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                    <tr>
                                        <td align="center" bgcolor="#000000" style="border-radius:6px;">
                                            <a href="<?php echo esc_url($reset_url); ?>" class="poppins btn"
                                                style="background-color:#000000;color:#ffffff !important;text-decoration:none;display:inline-block;padding:12px 24px;border-radius:6px;font-weight:700;font-size:12px;text-transform:uppercase;letter-spacing:2px;border:1px solid #000000;">
                                                <?php echo esc_html__('Reset password', 'politeia-learning'); ?>
                                            </a>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>

                        <tr>
                            <td align="center" class="poppins" style="padding:0 40px 60px;font-size:14px;line-height:1.6;color:#666666;">
                                <?php echo esc_html__('If you did not request this, you can safely ignore this email.', 'politeia-learning'); ?>
                            </td>
                        </tr>
<?php include PL_PATH . 'templates/emails/partials/unified-shell-bottom.php'; ?>
