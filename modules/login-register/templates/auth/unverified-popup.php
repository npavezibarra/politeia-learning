<?php
/**
 * Unverified popup template.
 */
if (!defined('ABSPATH')) {
    exit;
}
?>
<div id="pl-auth-unverified" class="pl-auth-unverified<?php echo $data['should_open'] ? ' is-open' : ''; ?>" role="dialog" aria-modal="true" aria-label="<?php echo esc_attr($data['title']); ?>">
    <div class="pl-auth-unverified__card">
        <div class="pl-auth-unverified__inner">
            <button type="button" class="pl-auth-unverified__close" aria-label="<?php echo esc_attr__('Close', 'politeia-learning'); ?>" data-pl-auth-unverified-close>×</button>
            <h3 class="pl-auth-unverified__title"><?php echo esc_html($data['title']); ?></h3>
            <p class="pl-auth-unverified__text"><?php echo esc_html($data['body']); ?></p>

            <?php if (!empty($data['message'])) : ?>
                <p class="pl-auth-unverified__notice pl-auth-unverified__notice--<?php echo esc_attr($data['message_type'] ?: 'info'); ?>">
                    <?php echo esc_html($data['message']); ?>
                </p>
            <?php endif; ?>
            
            <form method="post" action="<?php echo esc_url($data['action_url']); ?>" class="pl-auth-unverified__actions">
                <input type="hidden" name="action" value="pl_auth_resend_confirmation">
                <input type="hidden" name="pl_auth_resend_nonce" value="<?php echo esc_attr($data['nonce']); ?>">
                <input type="hidden" name="redirect_to" value="<?php echo esc_attr($data['redirect_to']); ?>">
                <button type="submit" class="pl-auth-unverified__btn"><?php echo esc_html($data['cta']); ?></button>
            </form>
        </div>
    </div>
</div>
