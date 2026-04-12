<?php
/**
 * Profile Template Options template for Politeia Learning.
 */
if (!defined('ABSPATH'))
    exit;
?>

<?php
$save_label = __('Guardar Cambios', 'politeia-learning');
if ($save_label === '') {
    $save_label = 'Guardar Cambios';
}
?>

<div class="wrap pcg-dashboard">
    <div class="pcg-dashboard-header">
        <h1>
            <?php _e('Politeia Learning - Profile Template', 'politeia-learning'); ?>
        </h1>
        <p class="description">
            <?php _e('Selecciona el template que se utilizará para los perfiles de los miembros.', 'politeia-learning'); ?>
        </p>
    </div>

    <div class="pcg-status-grid">
        <div class="pcg-card pcg-status-card">
            <h2><span class="dashicons dashicons-admin-users"></span>
                <?php _e('Configuración de Perfil', 'politeia-learning'); ?>
            </h2>

            <form method="post" action="">
                <?php wp_nonce_field('pcg_save_profile_template'); ?>
                <input type="hidden" name="pcg_profile_template_submitted" value="1">

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="pcg_profile_template">
                                <?php _e('Template de Perfil', 'politeia-learning'); ?>
                            </label>
                        </th>
                        <td>
                            <select name="pcg_profile_template" id="pcg_profile_template">
                                <option value="politeia-profile" <?php selected($current_template, 'politeia-profile'); ?>>
                                    <?php _e('Politeia Profile (Max width)', 'politeia-learning'); ?>
                                </option>
                                <option value="politeia-profile-fullwidth" <?php selected($current_template, 'politeia-profile-fullwidth'); ?>>
                                    <?php _e('Politeia Profile (Full width)', 'politeia-learning'); ?>
                                </option>
                            </select>
                            <p class="description">
                                <?php _e('Selecciona el layout del perfil público (/profile/{username}).', 'politeia-learning'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="pcg_operation_template">
                                <?php _e('Operation Template', 'politeia-learning'); ?>
                            </label>
                        </th>
                        <td>
                            <select name="pcg_operation_template" id="pcg_operation_template">
                                <option value="/center" <?php selected($current_operation_template, '/center'); ?>>
                                    /center
                                </option>
                                <option value="/center-2" <?php selected($current_operation_template, '/center-2'); ?>>
                                    /center-2
                                </option>
                            </select>
                            <p class="description">
                                <?php _e('Selecciona el template que se utilizará para las operaciones.', 'politeia-learning'); ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <input type="submit" name="submit" id="submit" class="button button-primary"
                        value="<?php echo esc_attr($save_label); ?>">
                </p>
            </form>
        </div>
    </div>
</div>
