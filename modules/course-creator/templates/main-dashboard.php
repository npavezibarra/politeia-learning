<?php
/**
 * Master Dashboard Template for Course Creator
 */

if (!defined('ABSPATH'))
    exit;

// Get current user details
$user_slug = get_query_var('pcg_creator_user');
$user = get_user_by('slug', $user_slug);

if (!$user) {
    wp_die(__('Usuario no encontrado.', 'politeia-learning'));
}

$current_section = isset($_GET['section']) ? sanitize_text_field($_GET['section']) : 'create-course';
if ($current_section === 'stats') {
    $current_section = 'students';
}

get_header();
?>

<div class="pcg-creator-dashboard-wrapper">
    <div class="pcg-mobile-nav" data-pcg-mobile-nav data-pcg-section="<?php echo esc_attr($current_section); ?>">
        <div class="pcg-mobile-topbar">
            <div class="pcg-mobile-subbar" role="navigation" aria-label="<?php esc_attr_e('Creator dashboard navigation', 'politeia-learning'); ?>">
                <div class="bb-left-panel-icon-wrap">
                    <a href="#" class="push-left bb-left-panel-mobile pcg-mobile-icon-btn" id="pcg-mobile-mainmenu-btn"
                        aria-label="<?php esc_attr_e('Open Menu', 'politeia-learning'); ?>"
                        aria-controls="pcg-mobile-main-drawer" aria-expanded="false" role="button">
                        <span class="material-symbols-outlined" aria-hidden="true">more_horiz</span>
                        <span class="screen-reader-text"><?php esc_html_e('Menu', 'politeia-learning'); ?></span>
                    </a>
                </div>

                <div class="pcg-mobile-page-title" id="pcg-mobile-page-title"></div>

                <button type="button" class="pcg-mobile-icon-btn pcg-mobile-icon-btn--section" id="pcg-mobile-sectionmenu-btn"
                    aria-controls="pcg-mobile-section-drawer" aria-expanded="false" style="display:none;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false">
                        <circle cx="12" cy="12" r="1.6" fill="currentColor"></circle>
                        <circle cx="12" cy="5" r="1.6" fill="currentColor"></circle>
                        <circle cx="12" cy="19" r="1.6" fill="currentColor"></circle>
                    </svg>
                    <span class="screen-reader-text"><?php esc_html_e('Section menu', 'politeia-learning'); ?></span>
                </button>
            </div>
        </div>

        <div class="pcg-mobile-drawer" id="pcg-mobile-main-drawer" aria-hidden="true">
            <div class="pcg-mobile-drawer-inner">
                <a class="pcg-mobile-drawer-item <?php echo $current_section === 'create-course' ? 'active' : ''; ?>" href="?section=create-course">
                    <?php _e('MIS CURSOS', 'politeia-learning'); ?>
                </a>
                <a class="pcg-mobile-drawer-item <?php echo $current_section === 'mis-escritos' ? 'active' : ''; ?>" href="?section=mis-escritos">
                    <?php _e('MIS ESCRITOS', 'politeia-learning'); ?>
                </a>
                <a class="pcg-mobile-drawer-item <?php echo $current_section === 'especializacion' ? 'active' : ''; ?>" href="?section=especializacion">
                    <?php _e('ESPECIALIZACIÓN', 'politeia-learning'); ?>
                </a>
                <a class="pcg-mobile-drawer-item <?php echo $current_section === 'create-group' ? 'active' : ''; ?>" href="?section=create-group">
                    <?php _e('PROGRAMAS', 'politeia-learning'); ?>
                </a>
                <a class="pcg-mobile-drawer-item <?php echo $current_section === 'sales' ? 'active' : ''; ?>" href="?section=sales">
                    <?php _e('VENTAS', 'politeia-learning'); ?>
                </a>
                <a class="pcg-mobile-drawer-item <?php echo $current_section === 'students' ? 'active' : ''; ?>" href="?section=students">
                    <?php _e('ESTUDIANTES', 'politeia-learning'); ?>
                </a>
            </div>
        </div>

        <div class="pcg-mobile-drawer" id="pcg-mobile-section-drawer" aria-hidden="true">
            <div class="pcg-mobile-drawer-inner" id="pcg-mobile-section-items"></div>
        </div>

        <div class="pcg-mobile-overlay" id="pcg-mobile-overlay" aria-hidden="true"></div>
    </div>

    <div class="pcg-creator-container">

        <aside id="pcg-creator-sidebar" class="pcg-creator-sidebar">
            <div class="pcg-user-info">
                <?php echo get_avatar($user->ID, 64); ?>
                <h2>
                    <?php echo esc_html($user->display_name); ?>
                </h2>
                <span class="user-role">
                    <?php _e('Creador de Cursos', 'politeia-learning'); ?>
                </span>
            </div>

            <nav class="pcg-creator-nav">
                <ul>
                    <li class="<?php echo $current_section === 'create-course' ? 'active' : ''; ?>">
                        <a href="?section=create-course">
                            <span class="dashicons dashicons-plus-alt"></span>
                            <?php _e('MIS CURSOS', 'politeia-learning'); ?>
                        </a>
                    </li>
                    <li class="<?php echo $current_section === 'mis-escritos' ? 'active' : ''; ?>">
                        <a href="?section=mis-escritos">
                            <span class="dashicons dashicons-edit"></span>
                            <?php _e('MIS ESCRITOS', 'politeia-learning'); ?>
                        </a>
                    </li>
                    <li class="<?php echo $current_section === 'especializacion' ? 'active' : ''; ?>">
                        <a href="?section=especializacion">
                            <span class="dashicons dashicons-welcome-learn-more"></span>
                            <?php _e('ESPECIALIZACIÓN', 'politeia-learning'); ?>
                        </a>
                    </li>
                    <li class="<?php echo $current_section === 'create-group' ? 'active' : ''; ?>">
                        <a href="?section=create-group">
                            <span class="dashicons dashicons-category"></span>
                            <?php _e('PROGRAMAS', 'politeia-learning'); ?>
                        </a>
                    </li>
                    <li class="<?php echo $current_section === 'sales' ? 'active' : ''; ?>">
                        <a href="?section=sales">
                            <span class="dashicons dashicons-chart-area"></span>
                            <?php _e('VENTAS', 'politeia-learning'); ?>
                        </a>
                    </li>
                    <li class="<?php echo $current_section === 'students' ? 'active' : ''; ?>">
                        <a href="?section=students">
                            <span class="dashicons dashicons-groups"></span>
                            <?php _e('ESTUDIANTES', 'politeia-learning'); ?>
                        </a>
                    </li>
                </ul>
            </nav>
        </aside>

        <main id="pcg-creator-content" class="pcg-creator-content">

            <div class="pcg-section-container">
                <?php
                $template_file = PL_CC_PATH . 'templates/sections/' . $current_section . '.php';
                if (file_exists($template_file)) {
                    include $template_file;
                } else {
                    echo '<p>' . __('Sección en construcción...', 'politeia-learning') . '</p>';
                }
                ?>
            </div>
        </main>

    </div>

    <div class="pcg-mobile-footer" id="pcg-mobile-footer" style="display:none;">
        <button type="button" class="pcg-mobile-action-btn" id="pcg-mobile-action-btn"></button>
    </div>
</div>

<?php
get_footer();
