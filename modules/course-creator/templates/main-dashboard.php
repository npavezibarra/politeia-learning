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

get_header();
?>

<div class="pcg-creator-dashboard-wrapper">
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
                    <li class="<?php echo $current_section === 'stats' ? 'active' : ''; ?>">
                        <a href="?section=stats">
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
</div>

<?php
get_footer();
