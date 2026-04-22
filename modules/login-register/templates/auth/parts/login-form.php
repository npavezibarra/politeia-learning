<?php
/**
 * Login Form part.
 */
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="pl-auth-row pl-auth-login-only" data-pl-auth-login-row>
    <label class="pl-auth-remember">
        <input type="checkbox" name="remember" value="1">
        <?php echo esc_html($labels['remember_me']); ?>
    </label>
    <a href="#" class="pl-auth-forgot" data-pl-auth-forgot-link style="font-size: 0.75rem; color:#000000; text-decoration: underline; font-weight: 600;">
        <?php echo esc_html($labels['forgot_link']); ?>
    </a>
</div>
