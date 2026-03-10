<div class="pcg-students-section">
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
                            <div class="pcg-metric-label"><?php _e('Número de Estudiantes', 'politeia-learning'); ?>
                            </div>
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
                            <div class="pcg-metric-value" data-students-metric="avg_courses_completed_per_student">0
                            </div>
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

        <div data-students-panel="ranking" style="display:none;">
            <div class="pcg-ranking-grid" data-pcg-students-rankings>
                <div class="pcg-ranking-card">
                    <h3 class="pcg-ranking-title"><?php _e('Top 10 - Cursos comprados', 'politeia-learning'); ?></h3>
                    <table class="pcg-ranking-table"
                        aria-label="<?php esc_attr_e('Top 10 - Cursos comprados', 'politeia-learning'); ?>">
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
                    <h3 class="pcg-ranking-title"><?php _e('Top 10 - Mayor aumento en quiz', 'politeia-learning'); ?>
                    </h3>
                    <table class="pcg-ranking-table"
                        aria-label="<?php esc_attr_e('Top 10 - Mayor aumento en quiz', 'politeia-learning'); ?>">
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
                    <h3 class="pcg-ranking-title">
                        <?php _e('Top 10 - Menos días para completar', 'politeia-learning'); ?>
                    </h3>
                    <table class="pcg-ranking-table"
                        aria-label="<?php esc_attr_e('Top 10 - Menos días para completar', 'politeia-learning'); ?>">
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
                    <h3 class="pcg-ranking-title"><?php _e('Top 10 - Más días para completar', 'politeia-learning'); ?>
                    </h3>
                    <table class="pcg-ranking-table"
                        aria-label="<?php esc_attr_e('Top 10 - Más días para completar', 'politeia-learning'); ?>">
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
            <!-- Default: Students list (same table previously shown in Ventas > List > Resumen) -->
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

            <!-- Detail view: a specific student profile -->
            <div class="pcg-students-profile-detail" hidden>
                <div class="pcg-students-profile">
                    <div class="pcg-students-profile__nav">
                        <div class="pcg-students-profile__nav-left">
                            <button type="button" class="pcg-students-profile__back" data-pcg-student-profile-back>
                                <span aria-hidden="true">‹</span> <?php _e('Volver', 'politeia-learning'); ?>
                            </button>
                            <div class="pcg-students-profile__search">
                                <div class="pcg-students-profile__search-inner">
                                    <span class="pcg-students-profile__search-icon" aria-hidden="true">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none"
                                            stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                            stroke-linejoin="round">
                                            <circle cx="11" cy="11" r="8"></circle>
                                            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                                        </svg>
                                    </span>
                                    <input type="text" class="pcg-students-profile__search-input"
                                        placeholder="<?php esc_attr_e('Buscar cursos, libros o registros...', 'politeia-learning'); ?>"
                                        autocomplete="off" />
                                </div>
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
                            <button type="button" class="pcg-students-profile__tab" data-profile-tab="patronage"
                                role="tab">
                                <?php _e('Patrocinio', 'politeia-learning'); ?>
                            </button>
                        </div>
                    </div>

                    <div class="pcg-students-profile__head">
                        <div class="pcg-students-profile__user">
                            <div class="pcg-students-profile__avatar" data-pcg-student-profile-avatar
                                aria-hidden="true">
                                <svg width="44" height="44" viewBox="0 0 24 24" fill="currentColor">
                                    <path
                                        d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z" />
                                </svg>
                            </div>
                            <div class="pcg-students-profile__user-meta">
                                <div class="pcg-students-profile__name" data-pcg-student-profile-name>Alex Thompson
                                </div>
                                <div class="pcg-students-profile__email" data-pcg-student-profile-email>
                                    alex.thompson@example.com</div>
                            </div>
                        </div>

                        <div class="pcg-students-profile__metrics">
                            <div class="pcg-students-profile__metric">
                                <span
                                    class="pcg-students-profile__metric-label"><?php _e('GASTO EN CURSOS', 'politeia-learning'); ?></span>
                                <span class="pcg-students-profile__metric-val" data-pcg-student-val-courses>$0.00</span>
                            </div>
                            <div class="pcg-students-profile__metric">
                                <span
                                    class="pcg-students-profile__metric-label"><?php _e('GASTO EN LIBROS', 'politeia-learning'); ?></span>
                                <span class="pcg-students-profile__metric-val" data-pcg-student-val-books>$0.00</span>
                            </div>
                            <div class="pcg-students-profile__metric">
                                <span
                                    class="pcg-students-profile__metric-label"><?php _e('PATROCINIO', 'politeia-learning'); ?></span>
                                <span class="pcg-students-profile__metric-val"
                                    data-pcg-student-val-patronage>$0.00</span>
                            </div>
                            <div class="pcg-students-profile__metric pcg-students-profile__metric--total">
                                <span
                                    class="pcg-students-profile__metric-label"><?php _e('TOTAL', 'politeia-learning'); ?></span>
                                <span class="pcg-students-profile__metric-val" data-pcg-student-val-total>$0.00</span>
                            </div>
                        </div>
                    </div>

                    <div class="pcg-students-profile__card">
                        <!-- Courses -->
                        <div class="pcg-students-profile__panel" data-profile-panel="courses">
                            <div class="pcg-students-profile__table-wrap">
                                <table class="pcg-students-profile__table"
                                    aria-label="<?php esc_attr_e('Cursos', 'politeia-learning'); ?>">
                                    <thead>
                                        <tr>
                                            <th><?php _e('Detalle del curso', 'politeia-learning'); ?></th>
                                            <th><?php _e('Progreso', 'politeia-learning'); ?></th>
                                            <th><?php _e('Quiz inicial', 'politeia-learning'); ?></th>
                                            <th><?php _e('Quiz final', 'politeia-learning'); ?></th>
                                            <th><?php _e('Días tr.', 'politeia-learning'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>
                                                <div class="pcg-students-profile__cell-course">
                                                    <div class="pcg-students-profile__emoji">⚛️</div>
                                                    <span class="pcg-students-profile__course-title">Advanced React
                                                        Patterns</span>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="pcg-students-profile__progress">
                                                    <div class="pcg-students-profile__progress-bar">
                                                        <div class="pcg-students-profile__progress-fill"
                                                            style="width: 100%"></div>
                                                    </div>
                                                    <div class="pcg-students-profile__progress-label">
                                                        <?php _e('Completado', 'politeia-learning'); ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="pcg-students-profile__pct">85%</td>
                                            <td class="pcg-students-profile__pct pcg-students-profile__pct--strong">92%
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>
                                                <div class="pcg-students-profile__cell-course">
                                                    <div class="pcg-students-profile__emoji">🎨</div>
                                                    <span class="pcg-students-profile__course-title">UI/UX Design
                                                        Fundamentals</span>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="pcg-students-profile__progress">
                                                    <div class="pcg-students-profile__progress-bar">
                                                        <div class="pcg-students-profile__progress-fill"
                                                            style="width: 95%"></div>
                                                    </div>
                                                    <div class="pcg-students-profile__progress-label">
                                                        <?php _e('95% completo', 'politeia-learning'); ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="pcg-students-profile__pct">90%</td>
                                            <td class="pcg-students-profile__pct pcg-students-profile__pct--strong">95%
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Books -->
                        <div class="pcg-students-profile__panel" data-profile-panel="books" hidden>
                            <div class="pcg-students-profile__table-wrap">
                                <table class="pcg-students-profile__table"
                                    aria-label="<?php esc_attr_e('Libros', 'politeia-learning'); ?>">
                                    <thead>
                                        <tr>
                                            <th><?php _e('Título del libro', 'politeia-learning'); ?></th>
                                            <th><?php _e('Sesiones', 'politeia-learning'); ?></th>
                                            <th><?php _e('Páginas leídas', 'politeia-learning'); ?></th>
                                            <th><?php _e('Quiz de estudio', 'politeia-learning'); ?></th>
                                            <th><?php _e('Puntaje de dominio', 'politeia-learning'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>
                                                <div class="pcg-students-profile__cell-course">
                                                    <div class="pcg-students-profile__emoji">📚</div>
                                                    <span class="pcg-students-profile__course-title">The Pragmatic
                                                        Programmer</span>
                                                </div>
                                            </td>
                                            <td class="pcg-students-profile__pct">94%</td>
                                            <td class="pcg-students-profile__pct pcg-students-profile__pct--strong">100%
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Patronage -->
                        <div class="pcg-students-profile__panel" data-profile-panel="patronage" hidden>
                            <div class="pcg-students-profile__table-wrap">
                                <table class="pcg-students-profile__table"
                                    aria-label="<?php esc_attr_e('Patrocinio', 'politeia-learning'); ?>">
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
                                                    <div class="pcg-students-profile__patronage-sub">14:30 PM • #PX-9921
                                                    </div>
                                                </div>
                                            </td>
                                            <td
                                                class="pcg-students-profile__pct pcg-students-profile__th-right pcg-students-profile__pct--strong">
                                                $25.00
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
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
            const avatarEl = panel.querySelector('[data-pcg-student-profile-avatar]');

            const coursesBody = panel.querySelector('[data-profile-panel="courses"] tbody');
            const booksBody = panel.querySelector('[data-profile-panel="books"] tbody');

            if (!index || !detail) return;

            const showIndex = () => {
                index.hidden = false;
                detail.hidden = true;
            };

            const renderCourseRow = (c) => {
                const progressText = c.progress >= 100 ? '<?php _e('Completado', 'politeia-learning'); ?>' : c.progress + '% <?php _e('completo', 'politeia-learning'); ?>';

                return `
                    <tr>
                        <td>
                            <div class="pcg-students-profile__cell-course">
                                <span class="pcg-students-profile__course-title">${c.title}</span>
                            </div>
                        </td>
                        <td>
                            <div class="pcg-students-profile__progress">
                                <div class="pcg-students-profile__progress-bar">
                                    <div class="pcg-students-profile__progress-fill" style="width: ${c.progress}%"></div>
                                </div>
                                <div class="pcg-students-profile__progress-label">
                                    ${progressText}
                                </div>
                            </div>
                        </td>
                        <td class="pcg-students-profile__pct">
                            <div class="pcg-students-profile__score-wrap">
                                <div class="pcg-students-profile__score-val">${c.first_quiz}</div>
                                <div class="pcg-students-profile__score-date">${c.first_quiz_date || ''}</div>
                            </div>
                        </td>
                        <td class="pcg-students-profile__pct pcg-students-profile__pct--strong">
                            <div class="pcg-students-profile__score-wrap">
                                <div class="pcg-students-profile__score-val">${c.final_quiz}</div>
                                <div class="pcg-students-profile__score-date">${c.final_quiz_date || ''}</div>
                            </div>
                        </td>
                        <td class="pcg-students-profile__pct">${c.days_delta}</td>
                    </tr>
                `;
            };

            const renderBookRow = (b) => {
                return `
                    <tr>
                        <td>
                            <div class="pcg-students-profile__cell-course">
                                <span class="pcg-students-profile__course-title">${b.title}</span>
                            </div>
                        </td>
                        <td class="pcg-students-profile__pct">${b.sessions || 0}</td>
                        <td class="pcg-students-profile__pct">${b.pages_read || 0}</td>
                        <td class="pcg-students-profile__pct">${b.first_quiz}</td>
                        <td class="pcg-students-profile__pct pcg-students-profile__pct--strong">${b.final_quiz}</td>
                    </tr>
                `;
            };

            const showDetail = (id, name, email, avatar, metrics = {}) => {
                if (nameEl) nameEl.textContent = name || '—';
                if (emailEl) emailEl.textContent = email || '';

                // Update metrics
                const mCourses = panel.querySelector('[data-pcg-student-val-courses]');
                const mBooks = panel.querySelector('[data-pcg-student-val-books]');
                const mPatronage = panel.querySelector('[data-pcg-student-val-patronage]');
                const mTotal = panel.querySelector('[data-pcg-student-val-total]');

                if (mCourses) mCourses.textContent = metrics.courses || '$0.00';
                if (mBooks) mBooks.textContent = metrics.books || '$0.00';
                if (mPatronage) mPatronage.textContent = metrics.patronage || '$0.00';
                if (mTotal) mTotal.textContent = metrics.total || '$0.00';

                if (avatarEl) {
                    if (avatar) {
                        avatarEl.innerHTML = `<img src="${avatar}" alt="${name}" style="width:100%; height:100%; border-radius:50%; object-fit:cover;">`;
                    } else {
                        avatarEl.innerHTML = `<svg width="44" height="44" viewBox="0 0 24 24" fill="currentColor"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z" /></svg>`;
                    }
                }

                if (coursesBody) {
                    coursesBody.innerHTML = '<tr><td colspan="5" style="text-align:center; padding:40px; color:#666;"><?php _e('Cargando cursos...', 'politeia-learning'); ?></td></tr>';
                }
                if (booksBody) {
                    booksBody.innerHTML = '<tr><td colspan="5" style="text-align:center; padding:40px; color:#666;"><?php _e('Cargando libros...', 'politeia-learning'); ?></td></tr>';
                }

                index.hidden = true;
                detail.hidden = false;

                if (id && typeof pcgStudentsData !== 'undefined') {
                    const params = new URLSearchParams({
                        action: pcgStudentsData.studentDetailAction,
                        nonce: pcgStudentsData.studentDetailNonce,
                        student_user_id: id
                    });

                    fetch(`${pcgStudentsData.ajaxUrl}?${params.toString()}`)
                        .then(r => r.json())
                        .then(res => {
                            if (res.success && res.data) {
                                // Courses
                                if (coursesBody) {
                                    if (Array.isArray(res.data.courses) && res.data.courses.length > 0) {
                                        coursesBody.innerHTML = res.data.courses.map(renderCourseRow).join('');
                                    } else {
                                        coursesBody.innerHTML = '<tr><td colspan="5" style="text-align:center; padding:40px; color:#666;"><?php _e('No hay cursos registrados.', 'politeia-learning'); ?></td></tr>';
                                    }
                                }
                                // Books
                                if (booksBody) {
                                    if (Array.isArray(res.data.books) && res.data.books.length > 0) {
                                        booksBody.innerHTML = res.data.books.map(renderBookRow).join('');
                                    } else {
                                        booksBody.innerHTML = '<tr><td colspan="5" style="text-align:center; padding:40px; color:#666;"><?php _e('No hay libros registrados.', 'politeia-learning'); ?></td></tr>';
                                    }
                                }
                            } else {
                                const err = '<tr><td colspan="5" style="text-align:center; padding:40px; color:#cc0000;"><?php _e('Error al cargar datos.', 'politeia-learning'); ?></td></tr>';
                                if (coursesBody) coursesBody.innerHTML = err;
                                if (booksBody) booksBody.innerHTML = err;
                            }
                        })
                        .catch(() => {
                            const err = '<tr><td colspan="5" style="text-align:center; padding:40px; color:#cc0000;"><?php _e('Error de red.', 'politeia-learning'); ?></td></tr>';
                            if (coursesBody) coursesBody.innerHTML = err;
                            if (booksBody) booksBody.innerHTML = err;
                        });
                }
            };

            panel.addEventListener('click', (e) => {
                const btn = e.target && e.target.closest ? e.target.closest('[data-pcg-student-open]') : null;
                if (!btn) return;
                e.preventDefault();
                const metrics = {
                    courses: btn.getAttribute('data-student-val-courses'),
                    books: btn.getAttribute('data-student-val-books'),
                    patronage: btn.getAttribute('data-student-val-patronage'),
                    total: btn.getAttribute('data-student-val-total'),
                };
                showDetail(
                    btn.getAttribute('data-student-id') || '',
                    btn.getAttribute('data-student-name') || '—',
                    btn.getAttribute('data-student-email') || '',
                    btn.getAttribute('data-student-avatar') || '',
                    metrics
                );
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

</div>