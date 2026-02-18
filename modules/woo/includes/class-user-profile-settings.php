<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles custom user profile settings for Politeia Learning.
 * Adds a commission rate field to the user profile in the WordPress admin.
 */
class PL_Woo_User_Profile_Settings
{
    const META_KEY = '_pl_commission_rate';

    public static function init(): void
    {
        // Display fields on user profile and edit user pages
        add_action('show_user_profile', [__CLASS__, 'render_commission_field']);
        add_action('edit_user_profile', [__CLASS__, 'render_commission_field']);

        // Save fields
        add_action('personal_options_update', [__CLASS__, 'save_commission_field']);
        add_action('edit_user_profile_update', [__CLASS__, 'save_commission_field']);
    }

    /**
     * Renders the commission rate field in the user profile.
     */
    public static function render_commission_field(WP_User $user): void
    {
        $rate = get_user_meta($user->ID, self::META_KEY, true);
        if ($rate === '') {
            $rate = 25; // Default value
        }
        ?>
        <h3>
            <?php _e('Configuración de Politeia Learning', 'politeia-learning'); ?>
        </h3>
        <table class="form-table">
            <tr>
                <th><label for="pl_commission_rate">
                        <?php _e('Comisión de la Plataforma (%)', 'politeia-learning'); ?>
                    </label></th>
                <td>
                    <input type="number" name="pl_commission_rate" id="pl_commission_rate"
                        value="<?php echo esc_attr($rate); ?>" class="regular-text" step="0.01" min="0" max="100" />
                    <p class="description">
                        <?php _e('Porcentaje que la plataforma (Politeia) retiene del valor neto de cada venta.', 'politeia-learning'); ?>
                    </p>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Saves the commission rate field.
     */
    public static function save_commission_field(int $user_id): void
    {
        if (!current_user_can('edit_user', $user_id)) {
            return;
        }

        if (isset($_POST['pl_commission_rate'])) {
            $rate = floatval($_POST['pl_commission_rate']);
            // Clamp between 0 and 100
            $rate = max(0, min(100, $rate));
            update_user_meta($user_id, self::META_KEY, $rate);
        }
    }
}
