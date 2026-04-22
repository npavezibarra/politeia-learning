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
            
            <form method="post" action="<?php echo esc_url($data['action_url']); ?>" class="pl-auth-unverified__actions">
                <input type="hidden" name="action" value="pl_auth_resend_confirmation">
                <input type="hidden" name="pl_auth_resend_nonce" value="<?php echo esc_attr($data['nonce']); ?>">
                <input type="hidden" name="redirect_to" value="<?php echo esc_attr($data['redirect_to']); ?>">
                <button type="submit" class="pl-auth-unverified__btn"><?php echo esc_html($data['cta']); ?></button>
            </form>
        </div>
    </div>
</div>
<style>
    #pl-auth-unverified {
        position: fixed;
        inset: 0;
        z-index: 10001;
        display: none;
        align-items: center;
        justify-content: center;
        padding: 24px;
        background: rgba(15, 23, 42, 0.68);
        backdrop-filter: blur(10px);
        box-sizing: border-box;
        font-family: Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
    }
    #pl-auth-unverified.is-open { display: flex; }
    #pl-auth-unverified * { box-sizing: border-box; }
    .pl-auth-unverified__card {
        width: min(100%, 520px);
        background: #fff;
        border: 1px solid rgba(15, 23, 42, 0.08);
        border-radius: 24px;
        box-shadow: 0 30px 80px rgba(15, 23, 42, 0.28);
        overflow: hidden;
    }
    .pl-auth-unverified__inner { padding: 28px 28px 26px; position: relative; }
    .pl-auth-unverified__close {
        position: absolute;
        right: 14px;
        top: 12px;
        width: 42px;
        height: 42px;
        border-radius: 999px;
        border: 0;
        background: rgba(15, 23, 42, 0.06);
        cursor: pointer;
        font-size: 18px;
        line-height: 1;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
    .pl-auth-unverified__title { margin: 0 0 10px; font-size: 20px; line-height: 1.15; color: #0f172a; }
    .pl-auth-unverified__text { margin: 0 0 18px; font-size: 15px; line-height: 1.5; color: #475569; }
    .pl-auth-unverified__actions { display: flex; justify-content: center; }
    .pl-auth-unverified__btn {
        appearance: none;
        border: 1px solid rgba(17, 24, 39, 0.12);
        background: #111827;
        color: #fff;
        border-radius: 12px;
        height: 44px;
        padding: 0 16px;
        font-weight: 700;
        letter-spacing: 0.12em;
        text-transform: uppercase;
        cursor: pointer;
    }
</style>
<script>
(function(){
    var overlay = document.getElementById('pl-auth-unverified');
    if (!overlay) return;
    try {
        if (overlay.classList.contains('is-open') && window.history && window.history.replaceState) {
            var url = new URL(window.location.href);
            url.searchParams.delete('pl_auth_unverified_after_quiz');
            url.searchParams.delete('pl_auth_unverified');
            url.searchParams.delete('pl_auth_notice');
            url.searchParams.delete('pl_auth_error');
            window.history.replaceState({}, document.title, url.toString());
        }
    } catch (e) {}
    var btn = overlay.querySelector('[data-pl-auth-unverified-close]');
    if (btn) btn.addEventListener('click', function(){ overlay.classList.remove('is-open'); });
})();
</script>
