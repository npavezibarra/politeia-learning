<?php
/**
 * Style Options template for Politeia Learning.
 */
if (!defined('ABSPATH'))
    exit;
?>

<div class="wrap pcg-dashboard">
    <div class="pcg-dashboard-header">
        <h1>
            <?php _e('Politeia Learning - Style Options', 'politeia-course-group'); ?>
        </h1>
        <p class="description">
            <?php _e('Personaliza la apariencia del panel de creador y otros elementos de la interfaz.', 'politeia-course-group'); ?>
        </p>
    </div>

    <div class="pcg-status-grid">
        <div class="pcg-card pcg-status-card">
            <h2><span class="dashicons dashicons-admin-appearance"></span>
                <?php _e('Dimensiones del Panel de Creador', 'politeia-course-group'); ?>
            </h2>

            <form method="post" action="">
                <?php wp_nonce_field('pcg_save_style_options'); ?>
                <input type="hidden" name="pcg_style_options_submitted" value="1">

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="pcg_creator_max_width">
                                <?php _e('Ancho Máximo del Panel', 'politeia-course-group'); ?>
                            </label>
                        </th>
                        <td>
                            <input name="pcg_creator_max_width" type="text" id="pcg_creator_max_width"
                                value="<?php echo esc_attr($creator_max_width); ?>" class="regular-text">
                            <p class="description">
                                <?php _e('Define el max-width para .pcg-creator-container. (Ej: 1400px, 100%, etc.)', 'politeia-course-group'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="pcg_container_max_width">
                                <?php _e('Ancho Máximo del Contenedor (.container)', 'politeia-course-group'); ?>
                            </label>
                        </th>
                        <td>
                            <input name="pcg_container_max_width" type="text" id="pcg_container_max_width"
                                value="<?php echo esc_attr($container_max_width); ?>" class="regular-text">
                            <p class="description">
                                <?php _e('Define el max-width para .container dentro de la página central del miembro.', 'politeia-course-group'); ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <input type="submit" name="submit" id="submit" class="button button-primary"
                        value="<?php _e('Guardar Cambios', 'politeia-course-group'); ?>">
                </p>
            </form>
        </div>
    </div>
</div>