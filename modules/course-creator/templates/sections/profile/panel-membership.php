<?php
/**
 * Profile Creator - Membership & Billing Panel
 */
if (!defined('ABSPATH')) exit;

$current_user_id = get_current_user_id();
// Mock data or real meta for subscription
$current_tier = get_user_meta($current_user_id, 'pl_membership_tier', true) ?: 'Gratis';
$membership_price = get_user_meta($current_user_id, 'pl_membership_price', true) ?: '5000';
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
                        <?php echo esc_html($current_tier); ?>
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
            
            <div class="pcg-form-group">
                <label><?php _e('VALOR DE SUSCRIPCIÓN MENSUAL (CLP)', 'politeia-learning'); ?></label>
                <div class="pcg-input-wrapper">
                    <i data-lucide="dollar-sign"></i>
                    <input 
                        type="text" 
                        name="membership_price" 
                        value="<?php echo esc_attr($membership_price); ?>" 
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
                <button type="button" class="gold-cta gold-cta--compact">
                    <i data-lucide="save"></i>
                    <?php _e('Guardar Membresía', 'politeia-learning'); ?>
                </button>
            </div>
            <p class="pcg-footer-note">Actualización <span>segura</span> vía Stripe/MercadoPago</p>
        </main>
    </div>
</div>
