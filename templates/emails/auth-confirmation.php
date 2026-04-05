<?php
/**
 * Email: auth confirmation
 *
 * Available variables:
 * - $user_name        (string)
 * - $verification_url (string)
 * - $token            (string)
 */

$user_name = isset($user_name) ? (string) $user_name : '';
$verification_url = isset($verification_url) ? (string) $verification_url : '';
$token = isset($token) ? (string) $token : '';

$display_name = $user_name !== '' ? $user_name : __('there', 'politeia-learning');
?>
<div style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; line-height: 1.6; color: #111827;">
    <h2 style="margin: 0 0 12px; font-size: 20px;">
        <?php echo esc_html__('Confirm your account', 'politeia-learning'); ?>
    </h2>

    <p style="margin: 0 0 16px;">
        <?php
        echo esc_html(
            sprintf(
                /* translators: 1: user name */
                __('Hi %1$s, thanks for registering at Politeia Learning.', 'politeia-learning'),
                $display_name
            )
        );
        ?>
    </p>

    <p style="margin: 0 0 16px;">
        <?php echo esc_html__('Please confirm your email address by clicking the button below. Your account will stay inactive until you verify it.', 'politeia-learning'); ?>
    </p>

    <p style="margin: 0 0 24px;">
        <a href="<?php echo esc_url($verification_url); ?>"
            style="display: inline-block; padding: 12px 18px; background: #111827; color: #ffffff; text-decoration: none; border-radius: 10px; font-weight: 600;">
            <?php echo esc_html__('Confirm email address', 'politeia-learning'); ?>
        </a>
    </p>

    <p style="margin: 0 0 10px; color: #6b7280; font-size: 13px;">
        <?php echo esc_html__('If the button does not work, paste this token into the confirmation form:', 'politeia-learning'); ?>
    </p>

    <p style="margin: 0; word-break: break-all;">
        <code style="display: inline-block; padding: 10px 12px; background: #f3f4f6; border-radius: 8px;"><?php echo esc_html($token); ?></code>
    </p>
</div>
