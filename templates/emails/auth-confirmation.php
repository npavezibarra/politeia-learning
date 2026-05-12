<?php
/**
 * Email: welcome + auth confirmation (new user)
 *
 * Available variables:
 * - $user_name        (string)
 * - $verification_url (string)
 * - $token            (string)
 */

if (!defined('ABSPATH')) {
    exit;
}

$user_name = isset($user_name) ? (string) $user_name : '';
$verification_url = isset($verification_url) ? (string) $verification_url : '';
$token = isset($token) ? (string) $token : '';

$display_name = $user_name !== '' ? $user_name : __('there', 'politeia-learning');
$site_name = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);
$year = (string) wp_date('Y');

$logo_url = function_exists('get_site_icon_url') ? (string) get_site_icon_url(256) : '';
$pl_email_header_title = __('NEW USER', 'politeia-learning');
$pl_email_document_title = (string) __('Confirm your email', 'politeia-learning');
?>
<?php include PL_PATH . 'templates/emails/partials/unified-shell-top.php'; ?>

                        <tr>
                            <td align="center" class="poppins" style="padding:50px 40px 18px;font-size:16px;line-height:1.7;color:#000000;">
                                <strong class="poppins" style="color:#000000; font-size: 20px; font-weight: 800;">
                                    <?php echo esc_html__('Welcome to Politeia', 'politeia-learning'); ?>
                                </strong>
                                <br><br>
                                <?php
                                echo esc_html(
                                    sprintf(
                                        /* translators: 1: user name */
                                        __('Hi %1$s, thanks for creating your account.', 'politeia-learning'),
                                        $display_name
                                    )
                                );
                                ?>
                                <br>
                                <?php echo esc_html__('To activate your account, please confirm your email:', 'politeia-learning'); ?>
                            </td>
                        </tr>

                        <tr>
                            <td align="center" style="padding:10px 40px 18px;">
                                <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                    <tr>
                                        <td align="center" bgcolor="#000000" style="border-radius:6px;">
                                            <a class="poppins btn" href="<?php echo esc_url($verification_url); ?>"
                                                style="background-color:#000000;color:#ffffff !important;text-decoration:none;display:inline-block;padding:12px 26px;border-radius:6px;font-weight:800;font-size:12px;text-transform:uppercase;letter-spacing:2px;border:1px solid #000000;">
                                                <?php echo esc_html__('Confirm email', 'politeia-learning'); ?>
                                            </a>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>

                        <tr>
                            <td align="center" class="poppins" style="padding:0 40px 0;font-size:13px;line-height:1.6;color:#475569;">
                                <?php echo esc_html__('Until you confirm, you will see an “Unverified account” notice while browsing.', 'politeia-learning'); ?>
                            </td>
                        </tr>

                        <tr>
                            <td align="center" class="poppins" style="padding:20px 40px 60px;font-size:13px;line-height:1.6;color:#666666;">
                                <?php echo esc_html__('If your email provider blocks the button, copy this token and paste it into the verification notice on the site:', 'politeia-learning'); ?>
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top:12px;">
                                    <tr>
                                        <td align="center" bgcolor="#f9fafb" style="padding:12px; border:1px dashed #d1d5db; border-radius:4px; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace; font-size:12px; color:#374151; word-break: break-all;">
                                            <?php echo esc_html($token); ?>
                                        </td>
                                    </tr>
                                </table>
                                <div class="poppins" style="margin-top:14px; font-size:12px; color:#94a3b8;">
                                    <?php echo esc_html__('If you did not create this account, you can ignore this email.', 'politeia-learning'); ?>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td align="center" class="poppins" style="padding:0 40px 40px; font-size: 12px; color: #9ca3af;">
                                <?php
                                echo esc_html(
                                    sprintf(
                                        /* translators: 1: year, 2: site name */
                                        __('© %1$s %2$s. All rights reserved.', 'politeia-learning'),
                                        $year,
                                        $site_name
                                    )
                                );
                                ?>
                            </td>
                        </tr>
<?php include PL_PATH . 'templates/emails/partials/unified-shell-bottom.php'; ?>
