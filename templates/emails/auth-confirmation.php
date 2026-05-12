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
$lang = (string) substr((string) determine_locale(), 0, 2);
$lang = $lang !== '' ? $lang : 'en';

$subject_title = (string) __('Confirm your email', 'politeia-learning');
?>
<!doctype html>
<html lang="<?php echo esc_attr($lang); ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo esc_html($subject_title); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@600&family=Newsreader:ital,wght@0,400;1,400&display=swap" rel="stylesheet">
    <style>
        body { margin:0; padding:0; width:100% !important; -webkit-text-size-adjust:100%; -ms-text-size-adjust:100%; background:#ffffff; }
        table { border-collapse:collapse !important; }
        img { border:0; outline:none; text-decoration:none; line-height:100%; }
        a { text-decoration:none !important; }
        .pl-h { font-family:'Poppins','Trebuchet MS',Helvetica,Arial,sans-serif !important; letter-spacing:-0.2px; }
        .pl-h-lg { font-family:'Poppins','Trebuchet MS',Helvetica,Arial,sans-serif !important; letter-spacing:-0.5px; }
        .pl-b { font-family:'Newsreader',Georgia,'Times New Roman',serif !important; line-height:1.6; }
    </style>
</head>
<body>
    <center style="width:100%; background-color:#ffffff;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#ffffff" style="background-color:#ffffff;">
            <tr>
                <td align="center" style="padding:24px 16px;">
                    <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:100%; max-width:600px;">

                        <tr>
                            <td align="left" style="padding:0 0 18px;">
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                    <tr>
                                        <td align="left" valign="middle" class="pl-h" style="font-weight:600; font-size:12px; color:#000000; letter-spacing:0.18em; text-transform:uppercase;">
                                            <?php echo esc_html__('NEW USER', 'politeia-learning'); ?>
                                        </td>
                                        <td align="right" valign="middle">
                                            <?php if ($logo_url !== '') : ?>
                                                <img src="<?php echo esc_url($logo_url); ?>" width="28" height="28" alt="<?php echo esc_attr($site_name); ?>" style="display:block; width:28px; height:28px; border-radius:6px;">
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>

                        <tr>
                            <td align="center" bgcolor="#ffffff" style="border:1px solid #e5e7eb; border-radius:6px;">
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                    <tr>
                                        <td align="center" style="padding:44px 40px 14px;">
                                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                                <tr>
                                                    <td align="center" class="pl-h-lg" style="font-weight:600; font-size:34px; line-height:1.15; color:#000000;">
                                                        <?php echo esc_html__('Welcome to Politeia', 'politeia-learning'); ?>
                                                    </td>
                                                </tr>
                                            </table>
                                        </td>
                                    </tr>

                                    <tr>
                                        <td align="center" class="pl-b" style="padding:0 52px 18px; font-size:18px; color:#111827;">
                                            <?php
                                            echo esc_html(
                                                sprintf(
                                                    /* translators: 1: user name */
                                                    __('Hi %1$s, thanks for creating your account.', 'politeia-learning'),
                                                    $display_name
                                                )
                                            );
                                            ?>
                                        </td>
                                    </tr>

                                    <tr>
                                        <td align="center" class="pl-b" style="padding:0 52px 26px; font-size:18px; color:#111827;">
                                            <?php echo esc_html__('To activate your account, please confirm your email:', 'politeia-learning'); ?>
                                        </td>
                                    </tr>

                                    <tr>
                                        <td align="center" style="padding:0 40px 18px;">
                                            <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                                <tr>
                                                    <td align="center" bgcolor="#000000" style="border-radius:9px;">
                                                        <a href="<?php echo esc_url($verification_url); ?>"
                                                            class="pl-h"
                                                            style="display:inline-block; background-color:#000000; border:1px solid #000000; border-radius:9px; color:#ffffff !important; padding:14px 28px; font-weight:600; font-size:12px; text-transform:uppercase; letter-spacing:0.12em;">
                                                            <?php echo esc_html__('Confirm email', 'politeia-learning'); ?>
                                                        </a>
                                                    </td>
                                                </tr>
                                            </table>
                                        </td>
                                    </tr>

                                    <tr>
                                        <td align="center" class="pl-b" style="padding:0 52px 6px; font-size:15px; color:#334155;">
                                            <?php echo esc_html__('Until you confirm, you will see an “Unverified account” notice while browsing.', 'politeia-learning'); ?>
                                        </td>
                                    </tr>

                                    <tr>
                                        <td align="center" class="pl-b" style="padding:0 52px 24px; font-size:14px; color:#64748b;">
                                            <?php echo esc_html__('If your email provider blocks the button, copy this token and paste it into the verification notice on the site:', 'politeia-learning'); ?>
                                        </td>
                                    </tr>

                                    <tr>
                                        <td align="center" style="padding:0 40px 16px;">
                                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                                <tr>
                                                    <td align="center" bgcolor="#f9fafb" style="padding:14px 14px; border:1px dashed #d1d5db; border-radius:6px; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace; font-size:12px; color:#111827; word-break: break-all;">
                                                        <?php echo esc_html($token); ?>
                                                    </td>
                                                </tr>
                                            </table>
                                        </td>
                                    </tr>

                                    <tr>
                                        <td align="center" class="pl-b" style="padding:0 52px 8px; font-size:13px; color:#94a3b8;">
                                            <?php echo esc_html__('If you did not create this account, you can ignore this email.', 'politeia-learning'); ?>
                                        </td>
                                    </tr>

                                    <tr>
                                        <td align="center" class="pl-b" style="padding:0 52px 40px; font-size:13px; color:#94a3b8;">
                                            <a href="<?php echo esc_url($verification_url); ?>" style="color:#000000 !important; text-decoration:underline !important;">
                                                <?php echo esc_html($verification_url); ?>
                                            </a>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>

                        <tr>
                            <td align="center" class="pl-b" style="padding:22px 10px 0; font-size:12px; color:#9ca3af;">
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

                    </table>
                </td>
            </tr>
        </table>
    </center>
</body>
</html>
