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

// Subscription "content access" (MVP): which Profile tabs are visible for subscribers.
$membership_tabs = ['main', 'courses', 'writings', 'specializations', 'thoughts', 'plans', 'book'];
$membership_tabs_selected = $membership_tabs;
$membership_tabs_labels = [
    'main' => __('Inicio', 'politeia-learning'),
    'courses' => __('Mis Cursos', 'politeia-learning'),
    'writings' => __('Escritos', 'politeia-learning'),
    'specializations' => __('Especializaciones', 'politeia-learning'),
    'thoughts' => __('Feed de Pensamientos', 'politeia-learning'),
    'plans' => __('Planes', 'politeia-learning'),
    'book' => __('Libros', 'politeia-learning'),
];
if ($current_user_id > 0 && class_exists('PL_Relationships') && method_exists('PL_Relationships', 'get_owner_policy')) {
    $policy = PL_Relationships::get_owner_policy((int) $current_user_id, PL_Relationships::TYPE_SUBSCRIBE);
    $tabs = isset($policy['profile_tabs']) && is_array($policy['profile_tabs']) ? $policy['profile_tabs'] : [];
    $tabs = array_values(array_unique(array_filter(array_map('sanitize_key', $tabs))));
    if ($tabs !== []) {
        $membership_tabs_selected = $tabs;
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

                <div class="pcg-form-group" style="margin-top: 28px;">
                    <label><?php _e('CONTENIDO INCLUIDO EN LA MEMBRESÍA', 'politeia-learning'); ?></label>
                    <p style="margin-top:10px; color:#a3a3a3; font-size:11px; text-transform: uppercase; letter-spacing: 0.05em;">
                        <?php _e('Define qué secciones del perfil (/profile/{username}) se desbloquean para tus suscriptores.', 'politeia-learning'); ?>
                    </p>

                    <div style="margin-top: 14px; display: grid; grid-template-columns: 1fr; gap: 10px;">
                        <?php foreach ($membership_tabs as $tab_id): ?>
                            <?php
                            $tab_id = sanitize_key((string) $tab_id);
                            $label = isset($membership_tabs_labels[$tab_id]) ? (string) $membership_tabs_labels[$tab_id] : $tab_id;
                            ?>
                            <label style="display:flex; align-items:center; gap:10px; padding:10px 12px; border: 1px solid #f0f0f0; border-radius: 10px; background: #fff;">
                                <input type="checkbox" name="pl_policy_subscribe_tabs[]" value="<?php echo esc_attr($tab_id); ?>" <?php checked(in_array($tab_id, $membership_tabs_selected, true)); ?> />
                                <span style="font-size: 13px; font-weight: 600; letter-spacing: 0.02em;"><?php echo esc_html($label); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
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
