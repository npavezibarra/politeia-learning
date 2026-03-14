<?php
/**
 * Profile Template Options template for Politeia Learning.
 */
if (!defined('ABSPATH'))
    exit;
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
                                <option value="default" <?php selected($current_template, 'default'); ?>>
                                    <?php _e('Default BuddyBoss', 'politeia-learning'); ?>
                                </option>
                                <option value="politeia-profile" <?php selected($current_template, 'politeia-profile'); ?>>
                                    <?php _e('Politeia Profile', 'politeia-learning'); ?>
                                </option>
                            </select>
                            <p class="description">
                                <?php _e('Elige entre el diseño estándar de BuddyBoss o el nuevo diseño de Politeia.', 'politeia-learning'); ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <input type="submit" name="submit" id="submit" class="button button-primary"
                        value="<?php _e('Guardar Cambios', 'politeia-learning'); ?>">
                </p>
            </form>
        </div>
    </div>
</div>
