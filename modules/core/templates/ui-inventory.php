<?php
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap pcg-dashboard">
    <div class="pcg-dashboard-header">
        <h1><?php echo esc_html__('UI Inventory', 'politeia-learning'); ?></h1>
        <p style="margin:0;">
            <?php echo esc_html__('Crea una página privada que renderiza botones y formularios reales del plugin para revisar estilos y uniformar UI.', 'politeia-learning'); ?>
        </p>
    </div>

    <?php if (!empty($error)) : ?>
        <div class="notice notice-error"><p><?php echo esc_html((string) $error); ?></p></div>
    <?php endif; ?>

    <?php if (!empty($created_id)) : ?>
        <div class="notice notice-success"><p><?php echo esc_html__('Page created.', 'politeia-learning'); ?></p></div>
    <?php endif; ?>

    <div class="pcg-card">
        <h2><?php echo esc_html__('Inventory Page', 'politeia-learning'); ?></h2>

        <?php if (!empty($page_id) && !empty($page_link)) : ?>
            <p style="margin:0 0 12px 0;">
                <?php echo esc_html__('Página actual:', 'politeia-learning'); ?>
                <a href="<?php echo esc_url($page_link); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($page_link); ?></a>
            </p>
            <p style="margin:0;">
                <a class="button button-primary" href="<?php echo esc_url($page_link); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html__('Open UI Inventory', 'politeia-learning'); ?></a>
            </p>
            <form method="post" action="" style="margin-top:12px;">
                <?php wp_nonce_field(PL_Core_UI_Inventory::NONCE_ACTION_RESCAN); ?>
                <input type="hidden" name="pl_ui_inventory_rescan" value="1" />
                <button type="submit" class="button"><?php echo esc_html__('Re-scan UI (refresh cache)', 'politeia-learning'); ?></button>
            </form>
        <?php else : ?>
            <p style="margin:0 0 12px 0;">
                <?php echo esc_html__('Aún no existe una página para el inventario UI.', 'politeia-learning'); ?>
            </p>
            <form method="post" action="">
                <?php wp_nonce_field(PL_Core_UI_Inventory::NONCE_ACTION_CREATE_PAGE); ?>
                <input type="hidden" name="pl_ui_inventory_create" value="1" />
                <button type="submit" class="button button-primary"><?php echo esc_html__('Create Private Page', 'politeia-learning'); ?></button>
            </form>
            <p style="margin-top:12px;color:#64748b;">
                <?php echo esc_html__('La página se crea como "private" e incluye el shortcode [pl_ui_inventory].', 'politeia-learning'); ?>
            </p>
        <?php endif; ?>
    </div>
</div>
