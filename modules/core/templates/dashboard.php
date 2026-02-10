<?php
/**
 * Dashboard template for Politeia Learning.
 */
if (!defined('ABSPATH'))
    exit;
?>

<div class="wrap pcg-dashboard">
    <div class="pcg-dashboard-header">
        <h1>
            <?php _e('Politeia Learning - Dashboard', 'politeia-course-group'); ?>
        </h1>
        <p class="description">
            <?php _e('Bienvenido al centro de control de Politeia Learning. Aquí puedes verificar el estado de los módulos y las integraciones.', 'politeia-course-group'); ?>
        </p>
    </div>

    <div class="pcg-status-grid">
        <div class="pcg-card pcg-status-card">
            <h2><span class="dashicons dashicons-plugins-checked"></span>
                <?php _e('Estado de Dependencias', 'politeia-course-group'); ?>
            </h2>
            <p>
                <?php _e('Para que todos los módulos de Politeia funcionen correctamente, asegúrate de que los siguientes plugins estén activos:', 'politeia-course-group'); ?>
            </p>

            <ul class="pcg-plugin-list">
                <?php foreach ($plugins_status as $plugin): ?>
                    <li class="<?php echo $plugin['active'] ? 'is-active' : 'is-inactive'; ?>">
                        <div class="plugin-info">
                            <strong>
                                <?php echo esc_html($plugin['name']); ?>
                            </strong>
                            <code><?php echo esc_html($plugin['path']); ?></code>
                        </div>
                        <div class="plugin-status">
                            <?php if ($plugin['active']): ?>
                                <span class="status-badge active"><span class="dashicons dashicons-yes"></span>
                                    <?php _e('Activo', 'politeia-course-group'); ?>
                                </span>
                            <?php else: ?>
                                <span class="status-badge inactive"><span class="dashicons dashicons-no"></span>
                                    <?php _e('Inactivo', 'politeia-course-group'); ?>
                                </span>
                                <a href="<?php echo esc_url(admin_url('plugins.php')); ?>" class="button button-small">
                                    <?php _e('Gestionar', 'politeia-course-group'); ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <div class="pcg-card pcg-info-card">
            <h2><span class="dashicons dashicons-info"></span>
                <?php _e('Módulos de Politeia', 'politeia-course-group'); ?>
            </h2>
            <div class="pcg-module-item">
                <h3>Course Programs</h3>
                <p>
                    <?php _e('Gestión de programas filosóficos y grupos de cursos.', 'politeia-course-group'); ?>
                </p>
                <span class="status-badge active">
                    <?php _e('Módulo Cargado', 'politeia-course-group'); ?>
                </span>
            </div>
            <hr>
            <div class="pcg-module-item">
                <h3>Course Integration</h3>
                <p>
                    <?php _e('Integración avanzada WooCommerce + LearnDash + Politeia.', 'politeia-course-group'); ?>
                </p>
                <span class="status-badge active">
                    <?php _e('Módulo Cargado', 'politeia-course-group'); ?>
                </span>
            </div>
        </div>
    </div>
</div>