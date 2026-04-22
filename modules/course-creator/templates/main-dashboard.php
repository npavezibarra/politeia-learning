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

// Module Visibility Logic
$is_admin = current_user_can('manage_options');
$def_modules = [
    'create-course' => true,
    'mis-escritos' => true,
    'especializacion' => true,
    'create-group' => true,
    'sales' => true,
    'students' => true,
    'profile' => true
];
$saved_settings = get_option('pcg_modules_visibility', []);
	$active_modules = [];
	foreach ($def_modules as $key => $default) {
	    if (isset($saved_settings[$key])) {
	        $active_modules[$key] = $is_admin ? !empty($saved_settings[$key]['admin']) : !empty($saved_settings[$key]['users']);
	    } else {
	        $active_modules[$key] = $default;
	    }
	}

	$pcg_specialization_label = __('ESPECIALIZACIÓN', 'politeia-learning');
	if ($pcg_specialization_label === '') {
	    $pcg_specialization_label = 'ESPECIALIZACIÓN';
	}

// Security: Check if current section is actually enabled for this user.
if (empty($active_modules[$current_section])) {
    $first_active = null;
    foreach ($active_modules as $key => $is_active) {
        if ($is_active) {
            $first_active = $key;
            break;
        }
    }
    if ($first_active) {
        $current_section = $first_active;
    } else {
        wp_die(__('No tienes módulos habilitados en este momento.', 'politeia-learning'));
    }
}

pl_template_open();
?>

<div class="pcg-creator-dashboard-wrapper pcg-template-center">
    <div class="pcg-creator-container">

        <aside id="pcg-creator-sidebar" class="pcg-creator-sidebar">
            <a href="?section=profile" class="pcg-user-info-link">
                <div class="pcg-user-info">
                    <?php echo get_avatar($user->ID, 64); ?>
                    <h2>
                        <?php echo esc_html($user->display_name); ?>
                    </h2>
                    <span class="user-role">
                        <?php _e('OPERACIONES', 'politeia-learning'); ?>
                    </span>
                </div>
            </a>

            <nav class="pcg-creator-nav">
                <ul>
                    <?php if ($active_modules['create-course']): ?>
                        <li class="<?php echo $current_section === 'create-course' ? 'active' : ''; ?>">
                            <a href="?section=create-course">
                                <span class="dashicons dashicons-plus-alt"></span>
                                <?php _e('MIS CURSOS', 'politeia-learning'); ?>
                            </a>
                        </li>
                    <?php endif; ?>
                    <?php if ($active_modules['mis-escritos']): ?>
                        <li class="<?php echo $current_section === 'mis-escritos' ? 'active' : ''; ?>">
                            <a href="?section=mis-escritos">
                                <span class="dashicons dashicons-edit"></span>
                                <?php _e('MIS ESCRITOS', 'politeia-learning'); ?>
                            </a>
                        </li>
                    <?php endif; ?>
	                    <?php if ($active_modules['especializacion']): ?>
	                        <li class="<?php echo $current_section === 'especializacion' ? 'active' : ''; ?>">
	                            <a href="?section=especializacion">
	                                <span class="dashicons dashicons-welcome-learn-more"></span>
	                                <?php echo esc_html($pcg_specialization_label); ?>
	                            </a>
	                        </li>
	                    <?php endif; ?>
                    <?php if ($active_modules['create-group']): ?>
                        <li class="<?php echo $current_section === 'create-group' ? 'active' : ''; ?>">
                            <a href="?section=create-group">
                                <span class="dashicons dashicons-category"></span>
                                <?php _e('PROGRAMAS', 'politeia-learning'); ?>
                            </a>
                        </li>
                    <?php endif; ?>
                    <?php if ($active_modules['sales']): ?>
                        <li class="<?php echo $current_section === 'sales' ? 'active' : ''; ?>">
                            <a href="?section=sales">
                                <span class="dashicons dashicons-chart-area"></span>
                                <?php _e('VENTAS', 'politeia-learning'); ?>
                            </a>
                        </li>
                    <?php endif; ?>
                    <?php if ($active_modules['students']): ?>
                        <li class="<?php echo $current_section === 'students' ? 'active' : ''; ?>">
                            <a href="?section=students">
                                <span class="dashicons dashicons-groups"></span>
                                <?php _e('ESTUDIANTES', 'politeia-learning'); ?>
                            </a>
                        </li>
                    <?php endif; ?>
                    <?php if ($active_modules['profile']): ?>
                        <li class="<?php echo $current_section === 'profile' ? 'active' : ''; ?>">
                            <a href="?section=profile">
                                <span class="dashicons dashicons-admin-users"></span>
                                <?php _e('PERFIL', 'politeia-learning'); ?>
                            </a>
                        </li>
                    <?php endif; ?>
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
pl_template_close();
