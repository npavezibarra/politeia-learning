<?php
/**
 * Students Section - General Metrics Dashboard
 */
if (!defined('ABSPATH')) exit;
?>

<div data-students-panel="general">
    <div class="pcg-sales-dashboard" data-pcg-students-dashboard>
        <div class="pcg-sales-dashboard-header">
            <div>
                <h2 class="pcg-sales-title"><?php _e('Panel de Estudiantes', 'politeia-learning'); ?></h2>
                <p class="pcg-sales-subtitle">
                    <?php _e('Resumen características de estudiantes y hábitos de estudio', 'politeia-learning'); ?>
                </p>
            </div>

            <div class="pcg-sales-controls">
                <div class="pcg-sales-timeframes" role="tablist"
                    aria-label="<?php _e('Periodo', 'politeia-learning'); ?>">
                    <button type="button" class="pcg-sales-tf-btn"
                        data-timeframe="day"><?php _e('Día', 'politeia-learning'); ?></button>
                    <button type="button" class="pcg-sales-tf-btn"
                        data-timeframe="week"><?php _e('Semana', 'politeia-learning'); ?></button>
                    <button type="button" class="pcg-sales-tf-btn active"
                        data-timeframe="month"><?php _e('Mes', 'politeia-learning'); ?></button>
                    <button type="button" class="pcg-sales-tf-btn"
                        data-timeframe="year"><?php _e('Año', 'politeia-learning'); ?></button>
                    <button type="button" class="pcg-sales-tf-btn"
                        data-timeframe="custom"><?php _e('Personalizado', 'politeia-learning'); ?></button>
                </div>

                <div class="pcg-sales-custom-range" data-custom-range style="display:none;">
                    <div class="pcg-sales-date">
                        <label><?php _e('Fecha inicio', 'politeia-learning'); ?></label>
                        <input type="date" data-start-date>
                    </div>
                    <div class="pcg-sales-date">
                        <label><?php _e('Fecha fin', 'politeia-learning'); ?></label>
                        <input type="date" data-end-date>
                    </div>
                </div>
            </div>
        </div>

        <div id="pcg-students-metrics" class="pcg-sales-metrics pcg-students-metrics">
            <div id="pcg-students-metric-total-students" class="pcg-metric-card pcg-metric-gold">
                <div class="pcg-metric-top">
                    <span class="pcg-metric-icon dashicons dashicons-groups"></span>
                    <span class="pcg-metric-tag"><?php _e('General', 'politeia-learning'); ?></span>
                </div>
                <div class="pcg-metric-main">
                    <div class="pcg-metric-label"><?php _e('Número de Estudiantes', 'politeia-learning'); ?></div>
                    <div class="pcg-metric-value" data-students-metric="students_total">0</div>
                </div>
                <div class="pcg-metric-foot"><?php _e('Total', 'politeia-learning'); ?></div>
            </div>

            <div id="pcg-students-metric-avg-days" class="pcg-metric-card pcg-metric-silver">
                <div class="pcg-metric-top">
                    <span class="pcg-metric-icon dashicons dashicons-clock"></span>
                    <span class="pcg-metric-tag"><?php _e('Tiempo', 'politeia-learning'); ?></span>
                </div>
                <div class="pcg-metric-main">
                    <div class="pcg-metric-label">
                        <?php _e('Tiempo Promedio finalización de Curso (Days)', 'politeia-learning'); ?>
                    </div>
                    <div class="pcg-metric-value" data-students-metric="avg_course_completion_days">0</div>
                </div>
                <div class="pcg-metric-foot"><?php _e('Promedio', 'politeia-learning'); ?></div>
            </div>

            <div id="pcg-students-metric-avg-completed" class="pcg-metric-card pcg-metric-silver">
                <div class="pcg-metric-top">
                    <span class="pcg-metric-icon dashicons dashicons-yes-alt"></span>
                    <span class="pcg-metric-tag"><?php _e('Finalización', 'politeia-learning'); ?></span>
                </div>
                <div class="pcg-metric-main">
                    <div class="pcg-metric-label">
                        <?php _e('Promedio Cursos Finalizados por Estudiante', 'politeia-learning'); ?>
                    </div>
                    <div class="pcg-metric-value" data-students-metric="avg_courses_completed_per_student">0</div>
                </div>
                <div class="pcg-metric-foot"><?php _e('Promedio', 'politeia-learning'); ?></div>
            </div>

            <div id="pcg-students-metric-delta" class="pcg-metric-card pcg-metric-copper">
                <div class="pcg-metric-top">
                    <span class="pcg-metric-icon dashicons dashicons-chart-line"></span>
                    <span class="pcg-metric-tag"><?php _e('Evaluación', 'politeia-learning'); ?></span>
                </div>
                <div class="pcg-metric-main">
                    <div class="pcg-metric-label">
                        <?php _e('Variación Evaluación Inicial/Final', 'politeia-learning'); ?>
                    </div>
                    <div class="pcg-metric-value">
                        <span class="pcg-students-delta" data-students-delta="0"
                            data-students-metric="assessment_delta_pct">0%</span>
                    </div>
                </div>
                <div class="pcg-metric-foot"><?php _e('Cambio', 'politeia-learning'); ?></div>
            </div>
        </div>

        <div class="pcg-sales-chart-card">
            <div class="pcg-sales-chart-head">
                <div>
                    <div class="pcg-students-chart-head-desktop">
                        <h3><?php _e('Elementos de estudio', 'politeia-learning'); ?></h3>
                        <p><?php _e('Desglose por día', 'politeia-learning'); ?></p>
                    </div>

                    <div class="pcg-students-chart-head-mobile">
                        <div class="pcg-students-chart-head-mobile-row">
                            <h3 class="pcg-students-chart-title">
                                <?php _e('Elementos de estudio', 'politeia-learning'); ?>
                            </h3>
                            <div class="pcg-sales-chart-legend" data-pcg-students-legend
                                aria-label="<?php esc_attr_e('Leyenda', 'politeia-learning'); ?>"></div>
                        </div>
                    </div>
                </div>
                <div class="pcg-sales-chart-legend" data-pcg-students-legend
                    aria-label="<?php esc_attr_e('Leyenda', 'politeia-learning'); ?>"></div>
            </div>
            <div class="pcg-sales-chart-wrap">
                <canvas data-pcg-sales-chart></canvas>
            </div>
        </div>

        <div class="pcg-students-range-overlay pcg-students-range-overlay--hidden"
            data-pcg-students-range-overlay aria-hidden="true">
            <div class="pcg-students-range-overlay__backdrop" data-pcg-students-range-backdrop></div>
            <div class="pcg-students-range-modal" data-pcg-students-range-modal role="dialog" aria-modal="true"
                aria-label="<?php esc_attr_e('Seleccionar rango', 'politeia-learning'); ?>">
                <div class="pcg-students-range-modal__head">
                    <h3 class="pcg-students-range-modal__title">
                        <?php _e('Seleccionar rango', 'politeia-learning'); ?>
                    </h3>
                    <button type="button" class="pcg-students-range-modal__close" data-pcg-students-range-close
                        aria-label="<?php esc_attr_e('Cerrar', 'politeia-learning'); ?>">
                        <span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
                    </button>
                </div>

                <div class="pcg-students-range-modal__options">
                    <button type="button" class="pcg-students-range-modal__option"
                        data-pcg-students-range="week"><?php _e('Últimos 7 días', 'politeia-learning'); ?></button>
                    <button type="button" class="pcg-students-range-modal__option"
                        data-pcg-students-range="month"><?php _e('Últimos 30 días', 'politeia-learning'); ?></button>
                    <button type="button" class="pcg-students-range-modal__option"
                        data-pcg-students-range="this_month"><?php _e('Este mes', 'politeia-learning'); ?></button>
                </div>

                <button type="button" class="pcg-students-range-modal__apply"
                    data-pcg-students-range-apply><?php _e('Aplicar filtro', 'politeia-learning'); ?></button>
            </div>
        </div>
    </div>
</div>
