<?php
/**
 * Unverified popup template.
 */
if (!defined('ABSPATH')) {
    exit;
}
?>
<div id="pl-auth-unverified" class="pl-auth-unverified<?php echo $data['should_open'] ? ' is-open' : ''; ?>">
    <button type="button" class="pl-auth-unverified__tab" aria-label="<?php echo esc_attr($data['title']); ?>" data-pl-auth-unverified-open>
        <svg class="pl-auth-unverified__tab-icon" width="22" height="22" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <path fill="currentColor" d="M12 1a9 9 0 0 0-9 9c0 1.9.6 3.7 1.7 5.2.3.4.9.5 1.3.2.4-.3.5-.9.2-1.3A7.1 7.1 0 0 1 4.8 10 7.2 7.2 0 0 1 12 2.8 7.2 7.2 0 0 1 19.2 10c0 1.5-.5 2.9-1.3 4.1-.3.4-.2 1 .2 1.3.4.3 1 .2 1.3-.2A8.9 8.9 0 0 0 21 10a9 9 0 0 0-9-9Zm0 4a5 5 0 0 0-5 5c0 .7.1 1.3.4 1.9.2.5.8.7 1.3.5.5-.2.7-.8.5-1.3-.1-.3-.2-.7-.2-1.1a3.2 3.2 0 0 1 3.2-3.2 3.2 3.2 0 0 1 3.2 3.2c0 .9-.4 1.8-1 2.4l-2.4 2.4a3.7 3.7 0 0 0-1.1 2.6v.8c0 .5.4.9.9.9s.9-.4.9-.9v-.8c0-.5.2-1 .6-1.4l2.4-2.4A5 5 0 0 0 17 10a5 5 0 0 0-5-5Zm0 14a1.2 1.2 0 1 0 0 2.4 1.2 1.2 0 0 0 0-2.4Z"/>
        </svg>
    </button>

    <div class="pl-auth-unverified__overlay" role="dialog" aria-modal="true" aria-label="<?php echo esc_attr($data['title']); ?>">
        <div class="pl-auth-unverified__card">
            <div class="pl-auth-unverified__inner">
                <button type="button" class="pl-auth-unverified__close" aria-label="<?php echo esc_attr__('Close', 'politeia-learning'); ?>" data-pl-auth-unverified-close>×</button>
                <h3 class="pl-auth-unverified__title"><?php echo esc_html($data['title']); ?></h3>
                <p class="pl-auth-unverified__text"><?php echo esc_html($data['body']); ?></p>

                <?php if (!empty($data['message'])) : ?>
                    <div class="pl-auth-unverified__notice">
                        <p class="pl-auth-unverified__notice-text pl-auth-unverified__notice-text--<?php echo esc_attr($data['message_type'] ?: 'info'); ?>">
                            <?php echo esc_html($data['message']); ?>
                        </p>
                    </div>
                <?php endif; ?>
                
                <form method="post" action="<?php echo esc_url($data['action_url']); ?>" class="pl-auth-unverified__actions">
                    <input type="hidden" name="action" value="pl_auth_resend_confirmation">
                    <input type="hidden" name="pl_auth_resend_nonce" value="<?php echo esc_attr($data['nonce']); ?>">
                    <input type="hidden" name="redirect_to" value="<?php echo esc_attr($data['redirect_to']); ?>">
                    <button type="submit" class="pl-auth-unverified__btn"><?php echo esc_html($data['cta']); ?></button>
                </form>

                <?php if (!empty($data['show_token_form'])) : ?>
                    <form method="post" action="<?php echo esc_url($data['action_url']); ?>" class="pl-auth-unverified__token">
                        <input type="hidden" name="action" value="pl_auth_confirm_token">
                        <input type="hidden" name="pl_auth_confirm_nonce" value="<?php echo esc_attr($data['confirm_nonce']); ?>">
                        <input type="hidden" name="redirect_to" value="<?php echo esc_attr($data['redirect_to']); ?>">
                        <label class="pl-auth-unverified__token-label" for="pl-auth-unverified-token">
                            <?php echo esc_html__('If your email provider blocks the button, paste the token here:', 'politeia-learning'); ?>
                        </label>
                        <div class="pl-auth-unverified__token-row">
                            <input id="pl-auth-unverified-token" name="pl_auth_token" type="text" class="pl-auth-unverified__token-input" inputmode="text" autocomplete="one-time-code" placeholder="<?php echo esc_attr__('Paste token', 'politeia-learning'); ?>">
                            <button type="submit" class="pl-auth-unverified__token-btn">
                                <?php echo esc_html__('Confirm', 'politeia-learning'); ?>
                            </button>
                        </div>
                        <?php if (!empty($data['user_email'])) : ?>
                            <p class="pl-auth-unverified__token-help">
                                <?php
                                echo esc_html(
                                    sprintf(
                                        /* translators: 1: email */
                                        __('This will verify: %1$s', 'politeia-learning'),
                                        (string) $data['user_email']
                                    )
                                );
                                ?>
                            </p>
                        <?php endif; ?>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
