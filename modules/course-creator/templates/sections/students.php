<div class="pcg-form-nav pcg-sales-nav">
    <div class="pcg-sales-nav-inner">
        <div class="pcg-nav-left">
            <span class="pcg-current-course-label"><?php _e('ESTUDIANTES', 'politeia-learning'); ?></span>
        </div>
        <div class="pcg-nav-right">
            <div class="pcg-segmented-control" id="pcg-students-tabs">
                <div class="pcg-segment active" data-students-tab="general">
                    <?php _e('GENERAL', 'politeia-learning'); ?>
                </div>
                <div class="pcg-segment" data-students-tab="ranking">
                    <?php _e('RANKING', 'politeia-learning'); ?>
                </div>
                <div class="pcg-segment" data-students-tab="profile">
                    <?php _e('PROFILE', 'politeia-learning'); ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="pcg-creator-section">
    <div data-students-panel="general">
        <div class="pcg-sales-dashboard" data-pcg-students-dashboard>
            <div class="pcg-sales-dashboard-header">
                <div>
                    <h2 class="pcg-sales-title"><?php _e('Panel de Estudiantes', 'politeia-learning'); ?></h2>
                    <p class="pcg-sales-subtitle"><?php _e('Resumen características de estudiantes y hábitos de estudio', 'politeia-learning'); ?>
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
                    <div class="pcg-metric-label"><?php _e('Número de Estudiantes', 'politeia-learning'); ?>
                    </div>
                    <div class="pcg-metric-value" data-students-metric="students_total">0</div>
                    <div class="pcg-metric-foot"><?php _e('Total', 'politeia-learning'); ?></div>
                </div>

                <div id="pcg-students-metric-avg-courses" class="pcg-metric-card pcg-metric-silver">
                    <div class="pcg-metric-top">
                        <span class="pcg-metric-icon dashicons dashicons-welcome-learn-more"></span>
                        <span class="pcg-metric-tag"><?php _e('Cursos', 'politeia-learning'); ?></span>
                    </div>
                    <div class="pcg-metric-label"><?php _e('Promedio Cursos por Estudiante', 'politeia-learning'); ?></div>
                    <div class="pcg-metric-value" data-students-metric="avg_courses_per_student">0</div>
                    <div class="pcg-metric-foot"><?php _e('Promedio', 'politeia-learning'); ?></div>
                </div>

                <div id="pcg-students-metric-avg-days" class="pcg-metric-card pcg-metric-silver">
                    <div class="pcg-metric-top">
                        <span class="pcg-metric-icon dashicons dashicons-clock"></span>
                        <span class="pcg-metric-tag"><?php _e('Tiempo', 'politeia-learning'); ?></span>
                    </div>
                    <div class="pcg-metric-label"><?php _e('Tiempo Promedio finalización de Curso (Days)', 'politeia-learning'); ?></div>
                    <div class="pcg-metric-value" data-students-metric="avg_course_completion_days">0</div>
                    <div class="pcg-metric-foot"><?php _e('Promedio', 'politeia-learning'); ?></div>
                </div>

                <div id="pcg-students-metric-avg-completed" class="pcg-metric-card pcg-metric-silver">
                    <div class="pcg-metric-top">
                        <span class="pcg-metric-icon dashicons dashicons-yes-alt"></span>
                        <span class="pcg-metric-tag"><?php _e('Finalización', 'politeia-learning'); ?></span>
                    </div>
                    <div class="pcg-metric-label"><?php _e('Promedio Cursos Finalizados por Estudiante', 'politeia-learning'); ?></div>
                    <div class="pcg-metric-value" data-students-metric="avg_courses_completed_per_student">0</div>
                    <div class="pcg-metric-foot"><?php _e('Promedio', 'politeia-learning'); ?></div>
                </div>

                <div id="pcg-students-metric-delta" class="pcg-metric-card pcg-metric-copper">
                    <div class="pcg-metric-top">
                        <span class="pcg-metric-icon dashicons dashicons-chart-line"></span>
                        <span class="pcg-metric-tag"><?php _e('Evaluación', 'politeia-learning'); ?></span>
                    </div>
                    <div class="pcg-metric-label"><?php _e('Variación Evaluación Inicial/Final', 'politeia-learning'); ?></div>
                    <div class="pcg-metric-value">
                        <span class="pcg-students-delta" data-students-delta="0" data-students-metric="assessment_delta_pct">0%</span>
                    </div>
                    <div class="pcg-metric-foot"><?php _e('Cambio', 'politeia-learning'); ?></div>
                </div>
            </div>

            <div class="pcg-sales-chart-card">
                <div class="pcg-sales-chart-head">
                    <div>
                        <h3><?php _e('Distribución de hábitos de estudio', 'politeia-learning'); ?></h3>
                        <p><?php _e('Desglose por día', 'politeia-learning'); ?></p>
                    </div>
                </div>
                <div class="pcg-sales-chart-wrap">
                    <canvas data-pcg-sales-chart></canvas>
                </div>
            </div>
        </div>
    </div>

    <div data-students-panel="ranking" style="display:none;">
        <div class="pcg-ranking-grid" data-pcg-students-rankings>
            <div class="pcg-ranking-card">
                <h3 class="pcg-ranking-title"><?php _e('Top 10 - Cursos comprados', 'politeia-learning'); ?></h3>
                <table class="pcg-ranking-table" aria-label="<?php esc_attr_e('Top 10 - Cursos comprados', 'politeia-learning'); ?>">
                    <thead>
                        <tr>
                            <th><?php _e('Nombre', 'politeia-learning'); ?></th>
                            <th class="pcg-ranking-num"><?php _e('# Cursos', 'politeia-learning'); ?></th>
                        </tr>
                    </thead>
                    <tbody data-ranking-table="purchases">
                        <tr>
                            <td colspan="2"><?php _e('Cargando...', 'politeia-learning'); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="pcg-ranking-card">
                <h3 class="pcg-ranking-title"><?php _e('Top 10 - Mayor aumento en quiz', 'politeia-learning'); ?></h3>
                <table class="pcg-ranking-table" aria-label="<?php esc_attr_e('Top 10 - Mayor aumento en quiz', 'politeia-learning'); ?>">
                    <thead>
                        <tr>
                            <th><?php _e('Nombre', 'politeia-learning'); ?></th>
                            <th><?php _e('Curso', 'politeia-learning'); ?></th>
                            <th class="pcg-ranking-num"><?php _e('Aumento', 'politeia-learning'); ?></th>
                        </tr>
                    </thead>
                    <tbody data-ranking-table="quiz_improvement">
                        <tr>
                            <td colspan="3"><?php _e('Cargando...', 'politeia-learning'); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="pcg-ranking-card">
                <h3 class="pcg-ranking-title"><?php _e('Top 10 - Menos días para completar', 'politeia-learning'); ?></h3>
                <table class="pcg-ranking-table" aria-label="<?php esc_attr_e('Top 10 - Menos días para completar', 'politeia-learning'); ?>">
                    <thead>
                        <tr>
                            <th><?php _e('Nombre', 'politeia-learning'); ?></th>
                            <th><?php _e('Curso', 'politeia-learning'); ?></th>
                            <th class="pcg-ranking-num"><?php _e('Días', 'politeia-learning'); ?></th>
                        </tr>
                    </thead>
                    <tbody data-ranking-table="fastest_completion">
                        <tr>
                            <td colspan="3"><?php _e('Cargando...', 'politeia-learning'); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="pcg-ranking-card">
                <h3 class="pcg-ranking-title"><?php _e('Top 10 - Más días para completar', 'politeia-learning'); ?></h3>
                <table class="pcg-ranking-table" aria-label="<?php esc_attr_e('Top 10 - Más días para completar', 'politeia-learning'); ?>">
                    <thead>
                        <tr>
                            <th><?php _e('Nombre', 'politeia-learning'); ?></th>
                            <th><?php _e('Curso', 'politeia-learning'); ?></th>
                            <th class="pcg-ranking-num"><?php _e('Días', 'politeia-learning'); ?></th>
                        </tr>
                    </thead>
                    <tbody data-ranking-table="slowest_completion">
                        <tr>
                            <td colspan="3"><?php _e('Cargando...', 'politeia-learning'); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div data-students-panel="profile" style="display:none;"></div>
    </div>

<script>
    (function () {
        const tabs = document.getElementById('pcg-students-tabs');
        if (!tabs) return;

        const container = tabs.closest('.pcg-section-container') || document;

        const setActive = (tab) => {
            tabs.querySelectorAll('.pcg-segment').forEach(el => el.classList.remove('active'));
            const segment = tabs.querySelector('.pcg-segment[data-students-tab="' + tab + '"]');
            if (segment) segment.classList.add('active');

            container.querySelectorAll('[data-students-panel]').forEach(p => p.style.display = 'none');
            const panel = container.querySelector('[data-students-panel="' + tab + '"]');
            if (panel) panel.style.display = '';

            window.dispatchEvent(new CustomEvent('pcg:sales-tab-changed', { detail: { tab } }));
        };

        tabs.addEventListener('click', (e) => {
            const seg = e.target && e.target.closest ? e.target.closest('.pcg-segment') : null;
            if (!seg) return;
            const tab = seg.getAttribute('data-students-tab');
            if (!tab) return;
            if (e && typeof e.stopPropagation === 'function') e.stopPropagation();
            setActive(tab);
        }, true);

        setActive('general');
    })();
</script>

<script>
    (function () {
        const el = document.querySelector('[data-students-delta]');
        if (!el) return;

        const raw = String(el.getAttribute('data-students-delta') || '').replace('%', '').trim();
        const value = Number(raw);
        if (!isFinite(value)) return;

        const pct = `${Math.abs(value).toFixed(0)}%`;
        el.textContent = pct;

        if (value > 0) {
            el.classList.add('pcg-students-delta--positive');
            const icon = document.createElement('span');
            icon.className = 'dashicons dashicons-arrow-up-alt';
            el.prepend(icon);
        } else if (value < 0) {
            el.classList.add('pcg-students-delta--negative');
        } else {
            el.classList.add('pcg-students-delta--neutral');
        }
    })();
</script>
