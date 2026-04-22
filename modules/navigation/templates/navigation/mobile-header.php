<?php
/**
 * Mobile Header Template
 */
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="pl-smartphone-header" data-pl-smartphone-header>
    <div class="pl-smartphone-header__inner pl-smartphone-header__inner--top">
        <div class="pl-smartphone-header__logo">
            <?php if ($logo_html !== ''): ?>
                <?php echo $logo_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <?php else: ?>
                <a href="<?php echo esc_url($home_url); ?>" aria-label="<?php esc_attr_e('Ir al inicio', 'politeia-learning'); ?>">
                    <span class="pl-smartphone-header__logo-text"><?php echo esc_html(get_bloginfo('name')); ?></span>
                </a>
            <?php endif; ?>
        </div>

        <button id="pl-hamburger-main" type="button" class="pl-smartphone-header__hamburger" aria-label="<?php esc_attr_e('Abrir menú', 'politeia-learning'); ?>">
            <span class="pl-smartphone-header__hamburger-lines" aria-hidden="true"></span>
        </button>
    </div>

    <?php if ($is_center): ?>
    <div id="pl-smartphone-header-sub" class="pl-smartphone-header__inner pl-smartphone-header__inner--sub" role="navigation"
        aria-label="<?php esc_attr_e('Navegación de sección', 'politeia-learning'); ?>">
        <div class="pl-smartphone-header__subbar">
            <button type="button" class="pl-subbar-back-btn" data-pl-subbar-back aria-label="<?php esc_attr_e('Volver', 'politeia-learning'); ?>">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
            </button>
            <div class="pl-subbar-content">
                <?php if (!empty($bc_action)): ?>
                    <span class="pl-subbar-action"><?php echo esc_html($bc_action); ?></span>
                <?php endif; ?>
                <span class="pl-subbar-parent" title="<?php echo esc_attr($bc_parent); ?>"><?php echo esc_html($bc_parent); ?></span>
                <?php if (!empty($bc_sub)): ?>
                    <span class="pl-subbar-sub"><?php echo esc_html($bc_sub); ?></span>
                <?php endif; ?>
            </div>
            <div class="pl-subbar-actions-right">
                <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&icon_names=arrow_drop_down_circle" />
                <style>
                .pl-subbar-menu-btn .material-symbols-outlined {
                    font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
                    text-transform: none !important;
                    letter-spacing: normal !important;
                    font-size: 24px;
                    color: #000;
                }
                </style>
                <button id="pl-hamburger-sub" type="button" class="pl-subbar-menu-btn" aria-label="<?php esc_attr_e('Abrir submenú', 'politeia-learning'); ?>">
                    <span class="material-symbols-outlined">arrow_drop_down_circle</span>
                </button>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php if ($is_center): ?>
<div class="pl-smartphone-menu-overlay" data-pl-smartphone-menu-overlay hidden></div>
<aside class="pl-smartphone-menu-panel" data-pl-smartphone-menu-panel aria-hidden="true" hidden>
    <header class="pl-smartphone-menu-panel__header">
        <div class="pl-smartphone-menu-panel__title"><?php echo esc_html($section_label); ?></div>
        <button type="button" class="pl-smartphone-menu-panel__close" data-pl-smartphone-menu-close
            aria-label="<?php esc_attr_e('Cerrar', 'politeia-learning'); ?>">
            <span aria-hidden="true">×</span>
        </button>
    </header>

    <div class="pl-smartphone-menu-panel__body">
        <div class="pl-smartphone-menu-panel__items" data-pl-smartphone-menu-items></div>
    </div>
</aside>
<?php endif; ?>

<!-- MAIN MENU OVERLAY -->
<div class="pl-smartphone-menu-overlay pl-main-menu-overlay" data-pl-smartphone-main-overlay hidden></div>
<aside class="pl-smartphone-menu-panel pl-main-menu-panel" data-pl-smartphone-main-panel aria-hidden="true" hidden>
    <div class="pl-smartphone-menu-panel__body">
        <ul class="pl-smartphone-main-menu-list">
            <?php echo Learni\Navigation\DesktopRenderer::build_items_html(); ?>
        </ul>
    </div>
</aside>

<div class="pl-smartphone-actionbar" data-pl-smartphone-actionbar hidden>
    <button type="button" class="pl-smartphone-actionbar__btn" data-pl-smartphone-action-btn></button>
</div>
