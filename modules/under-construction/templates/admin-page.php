<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap">
    <h1><?php echo esc_html__('Under Construction', 'politeia-learning'); ?></h1>

    <?php if (!empty($saved)) : ?>
        <div class="updated"><p><?php echo esc_html__('Settings saved.', 'politeia-learning'); ?></p></div>
    <?php endif; ?>

    <form method="post">
        <?php wp_nonce_field('pl_under_construction_save_settings'); ?>
        <input type="hidden" name="pl_under_construction_submitted" value="1">

        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><?php echo esc_html__('Estado', 'politeia-learning'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="pl_under_construction_enabled" value="1" <?php checked(!empty($enabled)); ?>>
                        <?php echo esc_html__('Activar modo under construction', 'politeia-learning'); ?>
                    </label>
                    <p class="description">
                        <?php echo esc_html__('Cuando está activo, solo administradores y editores pueden navegar el sitio y hacer login.', 'politeia-learning'); ?>
                    </p>
                </td>
            </tr>
        </table>

        <?php submit_button(__('Guardar', 'politeia-learning')); ?>
    </form>
</div>

