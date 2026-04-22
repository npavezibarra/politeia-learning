<?php
/**
 * Register Fields part.
 */
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="pl-auth-grid pl-auth-register-only pl-auth-hidden" data-pl-auth-register-fields>
    <div class="pl-auth-field">
        <label for="pl-auth-first-name"><?php echo esc_html($labels['first_name']); ?></label>
        <input id="pl-auth-first-name" name="first_name" type="text" autocomplete="given-name" placeholder="Ana">
    </div>
    <div class="pl-auth-field">
        <label for="pl-auth-last-name"><?php echo esc_html($labels['last_name']); ?></label>
        <input id="pl-auth-last-name" name="last_name" type="text" autocomplete="family-name" placeholder="García">
    </div>
</div>
