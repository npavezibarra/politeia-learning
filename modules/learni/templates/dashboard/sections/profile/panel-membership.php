<?php
/**
 * Profile Creator - Membership & Billing Panel
 */
if (!defined('ABSPATH')) exit;

$current_user_id = get_current_user_id();
$current_user = $current_user_id ? get_userdata($current_user_id) : null;
$current_user_slug = ($current_user instanceof WP_User) ? (string) $current_user->user_nicename : '';

// Display helpers (saved/failed redirect from handler).
$notice = '';
$error = '';
if (isset($_GET['pl_membership_notice']) && sanitize_key((string) wp_unslash($_GET['pl_membership_notice'])) === 'saved') {
    $notice = __('Membresía guardada.', 'politeia-learning');
}
if (isset($_GET['pl_membership_error'])) {
    $error = sanitize_text_field((string) wp_unslash($_GET['pl_membership_error']));
}

// Read the current monthly tier amount from PPS (source of truth).
$membership_amount_minor = 0;
if ($current_user_id > 0 && class_exists('Politeia_PPS_Subscription_Engine') && method_exists('Politeia_PPS_Subscription_Engine', 'get_creator_tier_by_slug')) {
    $tier = Politeia_PPS_Subscription_Engine::get_creator_tier_by_slug($current_user_id, 'monthly');
    if (is_array($tier) && isset($tier['amount_minor'])) {
        $membership_amount_minor = (int) $tier['amount_minor'];
    }
}

// Fallback (legacy): user meta used when PPS module is missing.
if ($membership_amount_minor <= 0) {
    $membership_amount_minor = (int) get_user_meta($current_user_id, 'politeia_membership_monthly_amount', true);
}
if ($membership_amount_minor <= 0) {
    $membership_amount_minor = 5000;
}
?>

<div data-profile-panel="membership" class="pcg-profile-view" style="display:none;">
    <div class="pcg-profile-container">
        <aside class="pcg-identity-aside">
            <div class="pcg-identity-header">
                <div class="pcg-identity-text">
                    <h1 style="font-size: 1.5rem; text-transform: uppercase;"><?php _e('MEMBRESÍA', 'politeia-learning'); ?></h1>
                    <p class="pcg-email">
                        <?php _e('Facturación y niveles de apoyo.', 'politeia-learning'); ?>
                    </p>
                    <div class="pcg-badge">
                        <i data-lucide="credit-card"></i>
                        <?php echo esc_html__('Mensual', 'politeia-learning'); ?>
                    </div>
                </div>
            </div>

            <div class="pcg-membership-summary pt-8">
                <p style="font-size: 0.8rem; color: var(--pcg-profile-text-muted); line-height: 1.6;">
                    <?php _e('Define el valor de tu suscripción mensual para que tus seguidores puedan apoyarte y acceder a contenido exclusivo.', 'politeia-learning'); ?>
                </p>
            </div>
        </aside>

        <main class="pcg-connectivity-main">
            <h2 class="section-title" style="border-top: none; padding-top: 0;"><?php _e('CONFIGURACIÓN DE PAGOS', 'politeia-learning'); ?></h2>

            <?php if ($notice !== '') : ?>
                <div class="politeia-pps__notice" role="status" style="margin: 12px 0;">
                    <?php echo esc_html($notice); ?>
                </div>
            <?php endif; ?>
            <?php if ($error !== '') : ?>
                <div class="politeia-pps__error" role="alert" style="margin: 12px 0;">
                    <?php echo esc_html($error); ?>
                </div>
            <?php endif; ?>
            
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="m-0">
                <?php wp_nonce_field('pl_cc_membership_tier', 'pl_cc_membership_tier_nonce'); ?>
                <input type="hidden" name="action" value="pl_cc_save_membership_tier" />
                <input type="hidden" name="user_slug" value="<?php echo esc_attr($current_user_slug); ?>" />

                <div class="pcg-form-group">
                    <label><?php _e('VALOR DE SUSCRIPCIÓN MENSUAL (CLP)', 'politeia-learning'); ?></label>
                    <div class="pcg-input-wrapper">
                        <i data-lucide="dollar-sign"></i>
                        <input 
                            type="text" 
                            name="monthly_amount" 
                            value="<?php echo esc_attr((string) $membership_amount_minor); ?>" 
                            class="input-field input-field--membership-price" 
                            inputmode="numeric" 
                            placeholder="5000"
                        />
                    </div>
                    <p style="margin-top:10px; color:#a3a3a3; font-size:11px; text-transform: uppercase; letter-spacing: 0.05em;">
                        <?php _e('Este monto será cobrado mensualmente a tus suscriptores.', 'politeia-learning'); ?>
                    </p>
                </div>

                <div class="pt-8 mt-8" style="border-top: 1px solid #f0f0f0;">
                    <button type="submit" class="gold-cta gold-cta--compact">
                        <i data-lucide="save"></i>
                        <?php _e('Guardar Membresía', 'politeia-learning'); ?>
                    </button>
                </div>
                <p class="pcg-footer-note">Actualización <span>segura</span> vía Stripe/MercadoPago</p>
            </form>
        </main>
    </div>
</div>
