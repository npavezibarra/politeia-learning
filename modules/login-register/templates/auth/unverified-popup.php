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
        <span class="material-symbols-outlined pl-auth-unverified__tab-icon" aria-hidden="true">mark_email_read</span>
    </button>

    <div class="pl-auth-unverified__overlay" role="dialog" aria-modal="true" aria-label="<?php echo esc_attr($data['title']); ?>">
        <div class="pl-auth-unverified__card">
            <div class="pl-auth-unverified__inner">
                <button type="button" class="pl-auth-unverified__close" aria-label="<?php echo esc_attr__('Close', 'politeia-learning'); ?>" data-pl-auth-unverified-close>×</button>
                <?php if (empty($data['show_token_form'])) : ?>
                    <h3 class="pl-auth-unverified__title"><?php echo esc_html($data['title']); ?></h3>
                    <p class="pl-auth-unverified__text"><?php echo esc_html($data['body']); ?></p>
                <?php endif; ?>

                <?php if (!empty($data['message'])) : ?>
                    <div class="pl-auth-unverified__notice">
                        <p class="pl-auth-unverified__notice-text pl-auth-unverified__notice-text--<?php echo esc_attr($data['message_type'] ?: 'info'); ?>">
                            <?php echo esc_html($data['message']); ?>
                        </p>
                    </div>
                <?php endif; ?>

                <?php if (empty($data['show_token_form'])) : ?>
                    <form method="post" action="<?php echo esc_url($data['action_url']); ?>" class="pl-auth-unverified__actions">
                        <input type="hidden" name="action" value="pl_auth_resend_confirmation">
                        <input type="hidden" name="pl_auth_resend_nonce" value="<?php echo esc_attr($data['nonce']); ?>">
                        <input type="hidden" name="redirect_to" value="<?php echo esc_attr($data['redirect_to']); ?>">
                        <button type="submit" class="pl-auth-unverified__btn"><?php echo esc_html($data['cta']); ?></button>
                    </form>
                <?php endif; ?>

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
