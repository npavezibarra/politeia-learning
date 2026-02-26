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

    <div data-students-panel="profile" style="display:none;">
        <div class="pcg-sales-dashboard">
            <div class="pcg-sales-dashboard-header">
                <div>
                    <h2 class="pcg-sales-title"><?php _e('Perfil Estudiantes', 'politeia-learning'); ?></h2>
                </div>
            </div>
        </div>

        <!-- Default: Students list (same table previously shown in Ventas > List > Resumen) -->
        <div class="pcg-students-profile-index" data-pcg-sales-list data-pcg-sales-list-mode="summary"
            data-pcg-students-profile-links>
            <div class="pcg-sales-list-topbar">
                <div class="pcg-sales-list-tabs" aria-hidden="true"></div>
                <div class="pcg-sales-list-pagination" aria-label="<?php esc_attr_e('Paginación', 'politeia-learning'); ?>">
                    <button type="button" class="pcg-sales-list-page-btn" data-pcg-sales-page-prev
                        aria-label="<?php esc_attr_e('Página anterior', 'politeia-learning'); ?>">‹</button>
                    <span class="pcg-sales-list-page-label" data-pcg-sales-page-label aria-live="polite"></span>
                    <button type="button" class="pcg-sales-list-page-btn" data-pcg-sales-page-next
                        aria-label="<?php esc_attr_e('Página siguiente', 'politeia-learning'); ?>">›</button>
                </div>
            </div>

            <section class="pcg-sales-list-panel" role="region" aria-label="<?php esc_attr_e('Resumen por estudiante', 'politeia-learning'); ?>">
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
                    <table class="pcg-sales-list-table" aria-label="<?php esc_attr_e('Resumen por estudiante', 'politeia-learning'); ?>">
                        <thead>
                            <tr>
                                <th style="width:320px;"><?php _e('Estudiante', 'politeia-learning'); ?></th>
                                <th style="width:110px;"><?php _e('Cursos', 'politeia-learning'); ?></th>
                                <th style="width:110px;"><?php _e('Libros', 'politeia-learning'); ?></th>
                                <th style="width:140px;"><?php _e('Patrocinio', 'politeia-learning'); ?></th>
                                <th style="width:180px; text-align:right;"><?php _e('Total', 'politeia-learning'); ?></th>
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

        <!-- Detail view: a specific student profile -->
        <div class="pcg-students-profile-detail" hidden>
        <div class="pcg-students-profile">
            <button type="button" class="pcg-students-profile__back" data-pcg-student-profile-back>
                <span aria-hidden="true">‹</span> <?php _e('Volver', 'politeia-learning'); ?>
            </button>
            <div class="pcg-students-profile__search">
                <div class="pcg-students-profile__search-inner">
                    <span class="pcg-students-profile__search-icon" aria-hidden="true">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="11" cy="11" r="8"></circle>
                            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                        </svg>
                    </span>
                    <input type="text" class="pcg-students-profile__search-input"
                        placeholder="<?php esc_attr_e('Buscar cursos, libros o registros...', 'politeia-learning'); ?>"
                        autocomplete="off" />
                </div>
            </div>

            <div class="pcg-students-profile__head">
                <div class="pcg-students-profile__user">
                    <div class="pcg-students-profile__avatar" aria-hidden="true">
                        <svg width="44" height="44" viewBox="0 0 24 24" fill="currentColor">
                            <path
                                d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z" />
                        </svg>
                    </div>
                    <div class="pcg-students-profile__user-meta">
                        <div class="pcg-students-profile__name" data-pcg-student-profile-name>Alex Thompson</div>
                        <div class="pcg-students-profile__email" data-pcg-student-profile-email>alex.thompson@example.com</div>
                    </div>
                </div>

                <div class="pcg-students-profile__tabs" role="tablist"
                    aria-label="<?php esc_attr_e('Secciones del perfil', 'politeia-learning'); ?>">
                    <button type="button" class="pcg-students-profile__tab is-active" data-profile-tab="courses"
                        role="tab">
                        <?php _e('Cursos', 'politeia-learning'); ?>
                    </button>
                    <button type="button" class="pcg-students-profile__tab" data-profile-tab="books" role="tab">
                        <?php _e('Libros', 'politeia-learning'); ?>
                    </button>
                    <button type="button" class="pcg-students-profile__tab" data-profile-tab="patronage" role="tab">
                        <?php _e('Patrocinio', 'politeia-learning'); ?>
                    </button>
                    <button type="button" class="pcg-students-profile__tab" data-profile-tab="value" role="tab">
                        <?php _e('VALOR', 'politeia-learning'); ?>
                    </button>
                </div>
            </div>

            <div class="pcg-students-profile__card">
                <!-- Courses -->
                <div class="pcg-students-profile__panel" data-profile-panel="courses">
                    <div class="pcg-students-profile__table-wrap">
                        <table class="pcg-students-profile__table" aria-label="<?php esc_attr_e('Cursos', 'politeia-learning'); ?>">
                            <thead>
                                <tr>
                                    <th><?php _e('Detalle del curso', 'politeia-learning'); ?></th>
                                    <th><?php _e('Progreso', 'politeia-learning'); ?></th>
                                    <th><?php _e('Quiz inicial', 'politeia-learning'); ?></th>
                                    <th><?php _e('Quiz final', 'politeia-learning'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>
                                        <div class="pcg-students-profile__cell-course">
                                            <div class="pcg-students-profile__emoji">⚛️</div>
                                            <span class="pcg-students-profile__course-title">Advanced React Patterns</span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="pcg-students-profile__progress">
                                            <div class="pcg-students-profile__progress-bar">
                                                <div class="pcg-students-profile__progress-fill" style="width: 100%"></div>
                                            </div>
                                            <div class="pcg-students-profile__progress-label">
                                                <?php _e('Completado', 'politeia-learning'); ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="pcg-students-profile__pct">85%</td>
                                    <td class="pcg-students-profile__pct pcg-students-profile__pct--strong">92%</td>
                                </tr>
                                <tr>
                                    <td>
                                        <div class="pcg-students-profile__cell-course">
                                            <div class="pcg-students-profile__emoji">🎨</div>
                                            <span class="pcg-students-profile__course-title">UI/UX Design Fundamentals</span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="pcg-students-profile__progress">
                                            <div class="pcg-students-profile__progress-bar">
                                                <div class="pcg-students-profile__progress-fill" style="width: 95%"></div>
                                            </div>
                                            <div class="pcg-students-profile__progress-label">
                                                <?php _e('95% completo', 'politeia-learning'); ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="pcg-students-profile__pct">90%</td>
                                    <td class="pcg-students-profile__pct pcg-students-profile__pct--strong">95%</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Books -->
                <div class="pcg-students-profile__panel" data-profile-panel="books" hidden>
                    <div class="pcg-students-profile__table-wrap">
                        <table class="pcg-students-profile__table" aria-label="<?php esc_attr_e('Libros', 'politeia-learning'); ?>">
                            <thead>
                                <tr>
                                    <th><?php _e('Título del libro', 'politeia-learning'); ?></th>
                                    <th><?php _e('Quiz de estudio', 'politeia-learning'); ?></th>
                                    <th><?php _e('Puntaje de dominio', 'politeia-learning'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>
                                        <div class="pcg-students-profile__cell-course">
                                            <div class="pcg-students-profile__emoji">📚</div>
                                            <span class="pcg-students-profile__course-title">The Pragmatic Programmer</span>
                                        </div>
                                    </td>
                                    <td class="pcg-students-profile__pct">94%</td>
                                    <td class="pcg-students-profile__pct pcg-students-profile__pct--strong">100%</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Patronage -->
                <div class="pcg-students-profile__panel" data-profile-panel="patronage" hidden>
                    <div class="pcg-students-profile__table-wrap">
                        <table class="pcg-students-profile__table" aria-label="<?php esc_attr_e('Patrocinio', 'politeia-learning'); ?>">
                            <thead>
                                <tr>
                                    <th><?php _e('Fecha y hora de pago', 'politeia-learning'); ?></th>
                                    <th class="pcg-students-profile__th-right">
                                        <?php _e('Monto aportado', 'politeia-learning'); ?>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>
                                        <div class="pcg-students-profile__cell-patronage">
                                            <div class="pcg-students-profile__patronage-date">May 15, 2024</div>
                                            <div class="pcg-students-profile__patronage-sub">14:30 PM • #PX-9921</div>
                                        </div>
                                    </td>
                                    <td class="pcg-students-profile__pct pcg-students-profile__th-right pcg-students-profile__pct--strong">
                                        $25.00
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Value -->
                <div class="pcg-students-profile__panel" data-profile-panel="value" hidden>
                    <div class="pcg-students-profile__value-grid">
                        <div class="pcg-students-profile__value-card">
                            <div class="pcg-students-profile__value-label"><?php _e('Gasto en cursos', 'politeia-learning'); ?></div>
                            <div class="pcg-students-profile__value-amt">$450.00</div>
                        </div>
                        <div class="pcg-students-profile__value-card">
                            <div class="pcg-students-profile__value-label"><?php _e('Gasto en libros', 'politeia-learning'); ?></div>
                            <div class="pcg-students-profile__value-amt">$120.50</div>
                        </div>
                        <div class="pcg-students-profile__value-card">
                            <div class="pcg-students-profile__value-label"><?php _e('Patrocinio', 'politeia-learning'); ?></div>
                            <div class="pcg-students-profile__value-amt">$75.00</div>
                        </div>
                        <div class="pcg-students-profile__value-card pcg-students-profile__value-card--total">
                            <div class="pcg-students-profile__value-label pcg-students-profile__value-label--total">
                                <?php _e('Total', 'politeia-learning'); ?>
                            </div>
                            <div class="pcg-students-profile__value-amt">$645.50</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        </div>
    </div>
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
        const root = document.querySelector('[data-students-panel="profile"] .pcg-students-profile');
        if (!root) return;

        const tabs = Array.from(root.querySelectorAll('[data-profile-tab]'));
        const panels = Array.from(root.querySelectorAll('[data-profile-panel]'));

        const setActive = (tab) => {
            tabs.forEach((btn) => {
                const active = btn.getAttribute('data-profile-tab') === tab;
                btn.classList.toggle('is-active', active);
            });

            panels.forEach((panel) => {
                const active = panel.getAttribute('data-profile-panel') === tab;
                if (active) {
                    panel.removeAttribute('hidden');
                } else {
                    panel.setAttribute('hidden', 'hidden');
                }
            });
        };

        root.addEventListener('click', (e) => {
            const btn = e.target && e.target.closest ? e.target.closest('[data-profile-tab]') : null;
            if (!btn) return;
            const tab = btn.getAttribute('data-profile-tab');
            if (!tab) return;
            if (e && typeof e.preventDefault === 'function') e.preventDefault();
            setActive(tab);
        });

        setActive('courses');
    })();
</script>

<script>
    (function () {
        const panel = document.querySelector('[data-students-panel="profile"]');
        if (!panel) return;

        const index = panel.querySelector('.pcg-students-profile-index');
        const detail = panel.querySelector('.pcg-students-profile-detail');
        const backBtn = panel.querySelector('[data-pcg-student-profile-back]');
        const nameEl = panel.querySelector('[data-pcg-student-profile-name]');
        const emailEl = panel.querySelector('[data-pcg-student-profile-email]');

        if (!index || !detail) return;

        const showIndex = () => {
            index.hidden = false;
            detail.hidden = true;
        };

        const showDetail = (name, email) => {
            if (nameEl && name) nameEl.textContent = name;
            if (emailEl && email) emailEl.textContent = email;
            index.hidden = true;
            detail.hidden = false;
        };

        panel.addEventListener('click', (e) => {
            const btn = e.target && e.target.closest ? e.target.closest('[data-pcg-student-open]') : null;
            if (!btn) return;
            e.preventDefault();
            showDetail(btn.getAttribute('data-student-name') || '—', btn.getAttribute('data-student-email') || '');
        });

        if (backBtn) {
            backBtn.addEventListener('click', (e) => {
                e.preventDefault();
                showIndex();
            });
        }

        // Default state
        showIndex();
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
