<?php
/**
 * Profile Creator - Identity Panel (2-Column Minimalist Design)
 * Optimized for Learni with Lucide Icons.
 */
if (!defined('ABSPATH')) exit;

$current_user = wp_get_current_user();
$full_name = trim($current_user->first_name . ' ' . $current_user->last_name);
if (empty($full_name)) $full_name = $current_user->display_name;

$bio = get_user_meta($current_user->ID, 'description', true);
$job_title = get_user_meta($current_user->ID, 'job_title', true);
$linkedin = get_user_meta($current_user->ID, 'linkedin', true);
$instagram = get_user_meta($current_user->ID, 'instagram', true);
$facebook = get_user_meta($current_user->ID, 'facebook', true);
$personal_site = get_user_meta($current_user->ID, 'personal_site', true);
$twitter = get_user_meta($current_user->ID, 'twitter', true);
?>

<div data-profile-panel="profile" class="pcg-profile-view">
    <div class="pcg-profile-container pcg-profile-container--identity">
        <form id="pcg-profile-form" class="pcg-profile-form">
            <header class="pcg-identity-topbar">
                <div class="pcg-identity-header">
                    <div class="pcg-avatar-container">
                        <div class="pcg-avatar-circle">
                            <img src="<?php echo get_avatar_url($current_user->ID, ['size' => 128]); ?>"
                                alt="<?php echo esc_attr($full_name); ?>" class="profile-photo">
                        </div>
                        <div class="pcg-camera-btn profile-photo-container" role="button"
                            aria-label="<?php esc_attr_e('Cambiar foto de perfil', 'politeia-learning'); ?>">
                            <i data-lucide="camera" size="10"></i>
                        </div>
                    </div>

                    <div class="pcg-identity-text">
                        <h1><?php echo esc_html($full_name); ?></h1>
                        <p class="pcg-email"><?php echo esc_html($current_user->user_email); ?></p>
                    </div>
                </div>

                <button type="submit" class="pcg-profile-save-btn" id="saveBtn">
                    <span class="pcg-profile-loader" id="loader" aria-hidden="true"></span>
                    <i data-lucide="save" id="saveIcon" size="14"></i>
                    <span id="btnText"><?php _e('Guardar', 'politeia-learning'); ?></span>
                </button>
            </header>

            <div class="pcg-profile-identity-grid">
                <!-- Columna Izquierda: Identidad -->
                <aside class="pcg-identity-aside">
                    <section class="pcg-personal-data">
                        <h2 class="section-title"><?php _e('Datos Personales', 'politeia-learning'); ?></h2>
                        <div class="pcg-form-group">
                            <label><?php _e('Biografía Corta', 'politeia-learning'); ?></label>
                            <textarea name="description"
                                placeholder="<?php esc_attr_e('ESCRIBE TU BIOGRAFÍA...', 'politeia-learning'); ?>"><?php echo esc_textarea($bio); ?></textarea>
                        </div>
                        <div class="pcg-form-group">
                            <label><?php _e('Título Profesional', 'politeia-learning'); ?></label>
                            <input type="text" name="job_title" class="input-field" value="<?php echo esc_attr($job_title); ?>"
                                placeholder="<?php esc_attr_e('EJ: SENIOR PRODUCT DESIGNER', 'politeia-learning'); ?>">
                        </div>
                    </section>
                </aside>

                <!-- Columna Derecha: Conectividad -->
                <main class="pcg-connectivity-main">
                    <h2 class="section-title section-title--no-border"><?php _e('Conectividad', 'politeia-learning'); ?></h2>

                    <div class="pcg-form-group">
                        <label><?php _e('LinkedIn Profile URL', 'politeia-learning'); ?></label>
                        <div class="pcg-input-wrapper">
                            <i data-lucide="linkedin"></i>
                            <input type="text" name="linkedin" value="<?php echo esc_attr($linkedin); ?>" class="input-field"
                                placeholder="<?php esc_attr_e('LINKEDIN.COM/IN/USERNAME', 'politeia-learning'); ?>">
                        </div>
                    </div>

                    <div class="pcg-form-group">
                        <label><?php _e('Instagram Username', 'politeia-learning'); ?></label>
                        <div class="pcg-input-wrapper">
                            <i data-lucide="instagram"></i>
                            <input type="text" name="instagram" value="<?php echo esc_attr($instagram); ?>" class="input-field"
                                placeholder="<?php esc_attr_e('@USERNAME', 'politeia-learning'); ?>">
                        </div>
                    </div>

                    <div class="pcg-form-group">
                        <label><?php _e('Facebook Profile', 'politeia-learning'); ?></label>
                        <div class="pcg-input-wrapper">
                            <i data-lucide="facebook"></i>
                            <input type="text" name="facebook" value="<?php echo esc_attr($facebook); ?>" class="input-field"
                                placeholder="<?php esc_attr_e('FACEBOOK.COM/USERNAME', 'politeia-learning'); ?>">
                        </div>
                    </div>

                    <div class="pcg-form-group">
                        <label><?php _e('Personal Portfolio URL', 'politeia-learning'); ?></label>
                        <div class="pcg-input-wrapper">
                            <i data-lucide="globe"></i>
                            <input type="text" name="personal_site" value="<?php echo esc_attr($personal_site); ?>" class="input-field"
                                placeholder="<?php esc_attr_e('WWW.YOURPAGE.COM', 'politeia-learning'); ?>">
                        </div>
                    </div>

                    <div class="pcg-form-group">
                        <label><?php _e('Twitter / X Username', 'politeia-learning'); ?></label>
                        <div class="pcg-input-wrapper">
                            <i data-lucide="x"></i>
                            <input type="text" name="twitter" value="<?php echo esc_attr($twitter); ?>" class="input-field"
                                placeholder="<?php esc_attr_e('@USERNAME', 'politeia-learning'); ?>">
                        </div>
                    </div>
                </main>
            </div>
        </form>
    </div>
</div>
