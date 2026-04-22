<?php
if (!defined('ABSPATH')) {
    exit;
}

$home_url = home_url('/');
$logo_html = function_exists('get_custom_logo') ? (string) get_custom_logo() : '';

$section = isset($_GET['section']) ? sanitize_text_field((string) $_GET['section']) : '';
$section_label = '';
switch ($section) {
    case 'create-course':
        $section_label = __('MIS CURSOS', 'politeia-learning');
        break;
    case 'mis-escritos':
        $section_label = __('MIS ESCRITOS', 'politeia-learning');
        break;
    case 'especializacion':
        $section_label = __('ESPECIALIZACIÓN', 'politeia-learning');
        break;
    case 'create-group':
        $section_label = __('PROGRAMAS', 'politeia-learning');
        break;
    case 'sales':
        $section_label = __('VENTAS', 'politeia-learning');
        break;
    case 'students':
        $section_label = __('ESTUDIANTES', 'politeia-learning');
        break;
    case 'profile':
        $section_label = __('PERFIL', 'politeia-learning');
        break;
    default:
        $section_label = '';
        break;
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

        <button type="button" class="pl-smartphone-header__hamburger" aria-label="<?php esc_attr_e('Abrir menú', 'politeia-learning'); ?>">
            <span class="pl-smartphone-header__hamburger-lines" aria-hidden="true"></span>
        </button>
    </div>

    <div class="pl-smartphone-header__inner pl-smartphone-header__inner--sub" role="navigation"
        aria-label="<?php esc_attr_e('Navegación de sección', 'politeia-learning'); ?>">
        <div class="pl-smartphone-header__subbar">
            <div class="pl-smartphone-header__subbar-spacer" aria-hidden="true"></div>
            <div class="pl-smartphone-header__subbar-title">
                <?php echo esc_html($section_label); ?>
            </div>
            <button type="button" class="pl-smartphone-header__subbar-hamburger" aria-label="<?php esc_attr_e('Abrir submenú', 'politeia-learning'); ?>">
                <span class="pl-smartphone-header__subbar-hamburger-lines" aria-hidden="true"></span>
            </button>
        </div>
    </div>
</div>

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

<div class="pl-smartphone-actionbar" data-pl-smartphone-actionbar hidden>
    <button type="button" class="pl-smartphone-actionbar__btn" data-pl-smartphone-action-btn></button>
</div>
