<?php
/**
 * Course Creator - Article/Escrito Picker Modal
 */
if (!defined('ABSPATH')) exit;
?>

<!-- START: ESCRITOS PICKER OVERLAY (LECCIONES) -->
<div id="pcg-escrito-picker-overlay" class="pcg-escrito-picker-overlay pcg-escrito-picker-overlay--hidden" aria-hidden="true">
    <button type="button" class="pcg-escrito-picker-overlay__backdrop" data-pcg-escrito-picker-close aria-label="<?php esc_attr_e('Cerrar', 'politeia-learning'); ?>"></button>
    <div class="pcg-escrito-picker-modal" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e('Seleccionar escrito', 'politeia-learning'); ?>">
        <div class="pcg-escrito-picker-modal__head">
            <h3 class="pcg-escrito-picker-modal__title"><?php _e('SELECCIONAR TEXTO', 'politeia-learning'); ?></h3>
            <button type="button" class="pcg-escrito-picker-modal__close" data-pcg-escrito-picker-close aria-label="<?php esc_attr_e('Cerrar', 'politeia-learning'); ?>">
                <span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
            </button>
        </div>
        <div class="pcg-escrito-picker-modal__body">
            <div class="pcg-escrito-picker-modal__search">
                <input type="text" class="pcg-modern-input" id="pcg-escrito-picker-search" placeholder="<?php esc_attr_e('Buscar por título...', 'politeia-learning'); ?>" autocomplete="off">
            </div>
            <div class="pcg-escrito-picker-modal__list" data-pcg-escrito-picker-list aria-live="polite"></div>
        </div>
        <div class="pcg-escrito-picker-modal__footer">
            <button type="button" class="pcg-escrito-picker-btn pcg-escrito-picker-btn--ghost" data-pcg-escrito-picker-close>
                <?php _e('CANCELAR', 'politeia-learning'); ?>
            </button>
            <button type="button" class="pcg-escrito-picker-btn pcg-escrito-picker-btn--primary" data-pcg-escrito-picker-accept disabled>
                <?php _e('ACEPTAR', 'politeia-learning'); ?>
            </button>
        </div>
    </div>
</div>
