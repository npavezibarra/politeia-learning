<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles global financial settings for Politeia Learning.
 * Adds a "Ajustes Financieros" subpage under the Politeia Learning menu.
 */
class PL_Woo_Financial_Settings
{
    const OPTION_IVA = 'pl_financial_iva';
    const OPTION_GATEWAY_FEE = 'pl_financial_gateway_fee';

    public static function init(): void
    {
        add_action('admin_menu', [__CLASS__, 'register_submenu'], 20);
    }

    /**
     * Registers the "Ajustes Financieros" submenu.
     */
    public static function register_submenu(): void
    {
        add_submenu_page(
            'politeia-learning',
            __('Ajustes Financieros', 'politeia-learning'),
            __('Ajustes Financieros', 'politeia-learning'),
            'manage_options',
            'pl-financial-settings',
            [__CLASS__, 'render_page']
        );
    }

    /**
     * Renders the settings page.
     */
    public static function render_page(): void
    {
        if (isset($_POST['pl_financial_settings_submitted']) && check_admin_referer('pl_save_financial_settings')) {
            $iva = floatval($_POST['pl_iva'] ?? 19);
            $gateway_fee = floatval($_POST['pl_gateway_fee'] ?? 3);

            update_option(self::OPTION_IVA, $iva);
            update_option(self::OPTION_GATEWAY_FEE, $gateway_fee);

            echo '<div class="updated"><p>' . __('Ajustes guardados correctamente.', 'politeia-learning') . '</p></div>';
        }

        $iva = get_option(self::OPTION_IVA, 19);
        $gateway_fee = get_option(self::OPTION_GATEWAY_FEE, 3);

        ?>
        <div class="wrap">
            <h1>
                <?php _e('Ajustes Financieros', 'politeia-learning'); ?>
            </h1>
            <p>
                <?php _e('Configura los valores globales para el cálculo de ganancias en el dashboard.', 'politeia-learning'); ?>
            </p>

            <form method="post" action="">
                <?php wp_nonce_field('pl_save_financial_settings'); ?>
                <input type="hidden" name="pl_financial_settings_submitted" value="1" />

                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="pl_iva">
                                <?php _e('IVA Chile (%)', 'politeia-learning'); ?>
                            </label></th>
                        <td>
                            <input name="pl_iva" type="number" id="pl_iva" value="<?php echo esc_attr($iva); ?>"
                                class="regular-text" step="0.01" min="0" />
                            <p class="description">
                                <?php _e('Porcentaje de IVA que se descontará del valor bruto (ej: 19).', 'politeia-learning'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="pl_gateway_fee">
                                <?php _e('Comisión Pasarela (ej. Flow) (%)', 'politeia-learning'); ?>
                            </label></th>
                        <td>
                            <input name="pl_gateway_fee" type="number" id="pl_gateway_fee"
                                value="<?php echo esc_attr($gateway_fee); ?>" class="regular-text" step="0.01" min="0" />
                            <p class="description">
                                <?php _e('Porcentaje de comisión de la pasarela de pago aplicado sobre el total bruto (ej: 3).', 'politeia-learning'); ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <?php submit_button(__('Guardar Ajustes', 'politeia-learning')); ?>
            </form>
        </div>
        <?php
    }
}
