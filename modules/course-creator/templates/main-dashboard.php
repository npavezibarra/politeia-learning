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
    wp_die(__('Usuario no encontrado.', 'politeia-course-group'));
}

$current_section = isset($_GET['section']) ? sanitize_text_field($_GET['section']) : 'create-course';

get_header();
?>

<div class="pcg-creator-dashboard-wrapper">
    <div class="pcg-creator-container">

        <aside class="pcg-creator-sidebar">
            <div class="pcg-user-info">
                <?php echo get_avatar($user->ID, 64); ?>
                <h2>
                    <?php echo esc_html($user->display_name); ?>
                </h2>
                <span class="user-role">
                    <?php _e('Creador de Cursos', 'politeia-course-group'); ?>
                </span>
            </div>

            <nav class="pcg-creator-nav">
                <ul>
                    <li class="<?php echo $current_section === 'create-course' ? 'active' : ''; ?>">
                        <a href="?section=create-course">
                            <span class="dashicons dashicons-plus-alt"></span>
                            <?php _e('Crear Curso', 'politeia-course-group'); ?>
                        </a>
                    </li>
                    <li class="<?php echo $current_section === 'create-group' ? 'active' : ''; ?>">
                        <a href="?section=create-group">
                            <span class="dashicons dashicons-category"></span>
                            <?php _e('Crear Grupo de Cursos', 'politeia-course-group'); ?>
                        </a>
                    </li>
                    <li class="<?php echo $current_section === 'sales' ? 'active' : ''; ?>">
                        <a href="?section=sales">
                            <span class="dashicons dashicons-chart-area"></span>
                            <?php _e('Sales Dashboard', 'politeia-course-group'); ?>
                        </a>
                    </li>
                    <li class="<?php echo $current_section === 'stats' ? 'active' : ''; ?>">
                        <a href="?section=stats">
                            <span class="dashicons dashicons-groups"></span>
                            <?php _e('Student Stats', 'politeia-course-group'); ?>
                        </a>
                    </li>
                </ul>
            </nav>
        </aside>

        <main class="pcg-creator-content">
            <header class="pcg-content-header">
                <h1>
                    <?php
                    switch ($current_section) {
                        case 'create-course':
                            echo __('Nuevo Curso LearnDash', 'politeia-course-group');
                            break;
                        case 'create-group':
                            echo __('Nuevo Programa de Cursos', 'politeia-course-group');
                            break;
                        case 'sales':
                            echo __('Resumen de Ventas', 'politeia-course-group');
                            break;
                        case 'stats':
                            echo __('Estadísticas de Estudiantes', 'politeia-course-group');
                            break;
                        default:
                            echo __('Dashboard', 'politeia-course-group');
                            break;
                    }
                    ?>
                </h1>
            </header>

            <div class="pcg-section-container">
                <?php
                $template_file = PCG_CC_PATH . 'templates/sections/' . $current_section . '.php';
                if (file_exists($template_file)) {
                    include $template_file;
                } else {
                    echo '<p>' . __('Sección en construcción...', 'politeia-course-group') . '</p>';
                }
                ?>
            </div>
        </main>

    </div>
</div>

<?php
get_footer();
