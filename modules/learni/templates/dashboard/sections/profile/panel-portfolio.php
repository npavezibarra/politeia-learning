<?php
/**
 * Profile Creator - Portfolio Redesign [Premium B&W]
 */
if (!defined('ABSPATH')) exit;

$portfolio_sections = [
    'courses' => ['label' => __('Cursos', 'politeia-learning'), 'icon' => 'book-open'],
    'specializations' => ['label' => __('Especializaciones', 'politeia-learning'), 'icon' => 'award'],
    'programs' => ['label' => __('Programas', 'politeia-learning'), 'icon' => 'layers'],
    'writings' => ['label' => __('Mis Escritos / Artículos', 'politeia-learning'), 'icon' => 'file-text']
];

$portfolio_manager = null;
if (class_exists('PL_Member_Profile_Portfolio_Manager')) {
    $portfolio_manager = PL_Member_Profile_Portfolio_Manager::get_instance();
}
?>

<div data-profile-panel="portfolio" class="pcg-profile-view" style="display:none;">
    <div class="pcg-profile-container pcg-profile-container--identity pcg-profile-container--portfolio">
        <div class="pcg-profile-form">
            <!-- Top bar: title + save aligned -->
            <header class="pcg-portfolio-topbar">
                <div class="pcg-portfolio-header">
                    <h1 class="pcg-portfolio-title"><?php _e('PORTAFOLIO', 'politeia-learning'); ?></h1>
                    <p class="pcg-portfolio-subtitle">
                        <?php _e('Gestiona la visibilidad de tus contenidos en tu perfil público de Learni.', 'politeia-learning'); ?>
                    </p>
                </div>

                <button type="button" id="pcg-save-portfolio" class="pcg-profile-save-btn">
                    <i data-lucide="save" size="14"></i>
                    <?php _e('Guardar', 'politeia-learning'); ?>
                </button>
            </header>

            <div class="pcg-portfolio-sections-container">
                <?php foreach ($portfolio_sections as $type => $data):
                    $settings = $portfolio_manager ? $portfolio_manager->get_settings(get_current_user_id(), $type) : null;
                    $selected_ids = $settings ? $settings->selected_ids : [];
                ?>
                    <section class="pcg-portfolio-section" data-section="<?php echo esc_attr($type); ?>">
                        <div class="pcg-portfolio-section-head">
                            <i data-lucide="<?php echo esc_attr($data['icon']); ?>" aria-hidden="true"></i>
                            <h2><?php echo esc_html($data['label']); ?></h2>
                        </div>

                        <!-- Hidden data for logic -->
                        <div class="pcg-hidden-selected-data" style="display:none;">
                            <?php foreach ($selected_ids as $sid): ?>
                                <span class="pcg-tag-pill" data-id="<?php echo esc_attr($sid); ?>"></span>
                            <?php endforeach; ?>
                        </div>

                        <div class="pcg-table-header">
                            <button type="button" class="pcg-item-content pcg-bulk-toggle-wrap">
                                <div class="pcg-custom-checkbox pcg-select-all-toggle" aria-hidden="true"></div>
                                <span class="pcg-header-label"><?php _e('DISPONIBLES (TODOS)', 'politeia-learning'); ?></span>
                            </button>
                            <div class="text-right">
                                <span class="pcg-header-label">•••</span>
                            </div>
                        </div>

                        <div class="pcg-item-list pcg-item-grid-container" data-page="1">
                            <div class="pcg-item-grid">
                                <!-- Rows loaded via AJAX -->
                            </div>

                            <footer class="pcg-portfolio-footer">
                                <div class="pcg-footer-count">
                                    <span class="pcg-selected-count-value">0</span>/<?php _e('0 SELECCIONADOS', 'politeia-learning'); ?>
                                </div>

                                <div class="pcg-pagination">
                                    <button type="button" class="pcg-page-btn pcg-prev-page" disabled>
                                        <i data-lucide="chevron-left"></i>
                                    </button>

                                    <div class="pcg-page-indicator">
                                        <span class="pcg-page-current">1</span>
                                        <span>/</span>
                                        <span class="pcg-page-total">1</span>
                                    </div>

                                    <button type="button" class="pcg-page-btn pcg-next-page" disabled>
                                        <i data-lucide="chevron-right"></i>
                                    </button>
                                </div>
                            </footer>
                        </div>
                    </section>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- Notification Toast -->
<div id="pcg-notification"></div>
