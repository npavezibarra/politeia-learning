<?php
/**
 * Profile Creator Navigation
 * Aligned with Ventas/Estudiantes style.
 */
if (!defined('ABSPATH')) exit;

$pcg_active_profile_tab = isset($_GET['profile_tab']) ? sanitize_text_field($_GET['profile_tab']) : 'profile';
?>

<div class="pcg-form-nav pcg-profile-nav">
    <div class="pcg-profile-nav-inner">
        <div class="pcg-nav-left">
            <span class="pcg-current-course-label"><?php _e('PERFIL', 'politeia-learning'); ?></span>
        </div>
        <div class="pcg-nav-right">
            <div id="pcg-profile-tabs" class="pcg-segmented-control">
                <div class="pcg-segment <?php echo $pcg_active_profile_tab === 'profile' ? 'active' : ''; ?>" data-profile-tab="profile">
                    <?php _e('IDENTIDAD', 'politeia-learning'); ?>
                </div>
                <div class="pcg-segment <?php echo $pcg_active_profile_tab === 'portfolio' ? 'active' : ''; ?>" data-profile-tab="portfolio">
                    <?php _e('PORTAFOLIO', 'politeia-learning'); ?>
                </div>
                <div class="pcg-segment <?php echo $pcg_active_profile_tab === 'membership' ? 'active' : ''; ?>" data-profile-tab="membership">
                    <?php _e('MEMBRESÍA', 'politeia-learning'); ?>
                </div>
            </div>
        </div>
    </div>
</div>
