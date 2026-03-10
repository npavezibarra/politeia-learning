<div class="wrap">
    <h1>
        <?php esc_html_e('Módulos', 'politeia-learning'); ?>
    </h1>

    <p>
        <?php esc_html_e('Activa o desactiva las secciones del Centro de Creadores (/center) para Usuarios y Administradores.', 'politeia-learning'); ?>
    </p>

    <form method="post" action="">
        <?php wp_nonce_field('pcg_save_modules_options'); ?>
        <input type="hidden" name="pcg_modules_options_submitted" value="1">

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th scope="col">
                        <?php esc_html_e('Functionality (Module)', 'politeia-learning'); ?>
                    </th>
                    <th scope="col">
                        <?php esc_html_e('For Users', 'politeia-learning'); ?>
                    </th>
                    <th scope="col">
                        <?php esc_html_e('For Admin', 'politeia-learning'); ?>
                    </th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($modules_config as $key => $module): ?>
                    <tr>
                        <td><strong>
                                <?php echo esc_html($module['label']); ?>
                            </strong></td>
                        <td>
                            <label class="pcg-toggle-switch">
                                <input type="checkbox" name="pcg_modules[<?php echo esc_attr($key); ?>][users]" value="1"
                                    <?php checked($current_settings[$key]['users'] ?? true, true); ?>>
                                <span class="pcg-toggle-slider"></span>
                            </label>
                        </td>
                        <td>
                            <label class="pcg-toggle-switch">
                                <input type="checkbox" name="pcg_modules[<?php echo esc_attr($key); ?>][admin]" value="1"
                                    <?php checked($current_settings[$key]['admin'] ?? true, true); ?>>
                                <span class="pcg-toggle-slider"></span>
                            </label>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <style>
            .pcg-toggle-switch {
                position: relative;
                display: inline-block;
                width: 44px;
                height: 24px;
            }

            .pcg-toggle-switch input {
                opacity: 0;
                width: 0;
                height: 0;
            }

            .pcg-toggle-slider {
                position: absolute;
                cursor: pointer;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background-color: #ccc;
                transition: .4s;
                border-radius: 24px;
            }

            .pcg-toggle-slider:before {
                position: absolute;
                content: "";
                height: 18px;
                width: 18px;
                left: 3px;
                bottom: 3px;
                background-color: white;
                transition: .4s;
                border-radius: 50%;
            }

            input:checked+.pcg-toggle-slider {
                background-color: #4cd964;
            }

            input:focus+.pcg-toggle-slider {
                box-shadow: 0 0 1px #4cd964;
            }

            input:checked+.pcg-toggle-slider:before {
                transform: translateX(20px);
            }
        </style>

        <p class="submit">
            <button type="submit" class="button button-primary">
                <?php esc_html_e('Save Changes', 'politeia-learning'); ?>
            </button>
        </p>
    </form>
</div>