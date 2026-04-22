<?php
/**
 * Students Section - Profile Index (List)
 */
if (!defined('ABSPATH')) exit;
?>

<div class="pcg-students-profile-index" data-pcg-sales-list data-pcg-sales-list-mode="summary"
    data-pcg-students-profile-links>
    <div class="pcg-sales-list-topbar"
        style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h2 class="pcg-sales-title" style="margin: 0;">
            <?php _e('Perfil Estudiantes', 'politeia-learning'); ?>
        </h2>
        <div class="pcg-sales-list-pagination"
            aria-label="<?php esc_attr_e('Paginación', 'politeia-learning'); ?>">
            <button type="button" class="pcg-sales-list-page-btn" data-pcg-sales-page-prev
                aria-label="<?php esc_attr_e('Página anterior', 'politeia-learning'); ?>">‹</button>
            <span class="pcg-sales-list-page-label" data-pcg-sales-page-label aria-live="polite"></span>
            <button type="button" class="pcg-sales-list-page-btn" data-pcg-sales-page-next
                aria-label="<?php esc_attr_e('Página siguiente', 'politeia-learning'); ?>">›</button>
        </div>
    </div>

    <section class="pcg-sales-list-panel" role="region"
        aria-label="<?php esc_attr_e('Resumen por estudiante', 'politeia-learning'); ?>">
        <div class="pcg-sales-list-panel-head">
            <div class="pcg-sales-list-panel-title">
                <h3><?php _e('Resumen por estudiante', 'politeia-learning'); ?></h3>
                <p class="pcg-sales-list-hint">
                    <?php _e('Una fila por estudiante. Totales solo consideran ventas pagadas.', 'politeia-learning'); ?>
                </p>
            </div>

            <div class="pcg-sales-list-controls">
                <div class="pcg-sales-list-search">
                    <input type="search" autocomplete="off" data-pcg-sales-sum-search
                        placeholder="<?php esc_attr_e('Buscar nombre, email o producto…', 'politeia-learning'); ?>">
                    <button type="button" class="pcg-sales-list-clear" data-pcg-sales-sum-clear
                        title="<?php esc_attr_e('Limpiar', 'politeia-learning'); ?>">×</button>
                </div>
                <div class="pcg-sales-list-pill" aria-live="polite">
                    <?php _e('Mostrando', 'politeia-learning'); ?>
                    <strong data-pcg-sales-sum-count>0</strong>
                    <?php _e('de', 'politeia-learning'); ?>
                    <strong data-pcg-sales-sum-total>0</strong>
                </div>
            </div>
        </div>

        <div class="pcg-sales-list-table-wrap">
            <table class="pcg-sales-list-table"
                aria-label="<?php esc_attr_e('Resumen por estudiante', 'politeia-learning'); ?>">
                <thead>
                    <tr>
                        <th style="width:320px;"><?php _e('Estudiante', 'politeia-learning'); ?></th>
                        <th style="width:110px;"><?php _e('Cursos', 'politeia-learning'); ?></th>
                        <th style="width:110px;"><?php _e('Libros', 'politeia-learning'); ?></th>
                        <th style="width:140px;"><?php _e('Patrocinio', 'politeia-learning'); ?></th>
                        <th style="width:180px; text-align:right;">
                            <?php _e('Total', 'politeia-learning'); ?>
                        </th>
                    </tr>
                </thead>
                <tbody data-pcg-sales-sum-body></tbody>
            </table>
            <div class="pcg-sales-list-empty" data-pcg-sales-sum-empty hidden>
                <?php _e('No hay estudiantes que coincidan.', 'politeia-learning'); ?>
            </div>
        </div>
    </section>
</div>
