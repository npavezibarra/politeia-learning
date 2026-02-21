<div class="pcg-form-nav pcg-sales-nav">
    <div class="pcg-sales-nav-inner">
        <div class="pcg-nav-left">
            <span class="pcg-current-course-label"><?php _e('VENTAS', 'politeia-learning'); ?></span>
        </div>
        <div class="pcg-nav-right">
            <div class="pcg-segmented-control" id="pcg-sales-tabs">
                <div class="pcg-segment active" data-sales-tab="general">
                    <?php _e('GENERAL', 'politeia-learning'); ?>
                </div>
                <div class="pcg-segment" data-sales-tab="list">
                    <?php _e('LIST', 'politeia-learning'); ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="pcg-creator-section">
    <div data-sales-panel="general">
        <div class="pcg-sales-dashboard" data-pcg-sales-dashboard>
            <div class="pcg-sales-dashboard-header">
                <div>
                    <h2 class="pcg-sales-title"><?php _e('Panel de ventas', 'politeia-learning'); ?></h2>
                    <p class="pcg-sales-subtitle"><?php _e('Resumen de desempeño por periodo', 'politeia-learning'); ?>
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

            <div class="pcg-sales-metrics">
                <div id="politeia-user-sales-all" class="pcg-metric-card pcg-metric-gold">
                    <div class="pcg-metric-top">
                        <span class="pcg-metric-icon dashicons dashicons-chart-line"></span>
                        <span class="pcg-metric-tag"><?php _e('General', 'politeia-learning'); ?></span>
                    </div>
                    <div class="pcg-metric-label" data-label="total"><?php _e('Ventas totales', 'politeia-learning'); ?>
                    </div>
                    <div class="pcg-metric-value" data-metric="total">$0</div>
                    <div class="pcg-metric-foot"><?php _e('Desempeño total', 'politeia-learning'); ?></div>
                </div>

                <div id="politeia-user-sales-courses" class="pcg-metric-card pcg-metric-silver">
                    <div class="pcg-metric-top">
                        <span class="pcg-metric-icon dashicons dashicons-welcome-learn-more"></span>
                        <span class="pcg-metric-tag"><?php _e('Cursos', 'politeia-learning'); ?></span>
                    </div>
                    <div class="pcg-metric-label" data-label="courses">
                        <?php _e('Ventas de cursos', 'politeia-learning'); ?></div>
                    <div class="pcg-metric-value" data-metric="courses">$0</div>
                    <div class="pcg-metric-foot" data-metric-pct="courses"
                        data-suffix="<?php _e('del total', 'politeia-learning'); ?>">0%</div>
                </div>

                <div id="politeia-user-sales-books" class="pcg-metric-card pcg-metric-silver">
                    <div class="pcg-metric-top">
                        <span class="pcg-metric-icon dashicons dashicons-book"></span>
                        <span class="pcg-metric-tag"><?php _e('Libros', 'politeia-learning'); ?></span>
                    </div>
                    <div class="pcg-metric-label" data-label="books">
                        <?php _e('Ventas de libros', 'politeia-learning'); ?></div>
                    <div class="pcg-metric-value" data-metric="books">$0</div>
                    <div class="pcg-metric-foot" data-metric-pct="books"
                        data-suffix="<?php _e('del total', 'politeia-learning'); ?>">0%</div>
                </div>

                <div id="politeia-user-sales-patronage" class="pcg-metric-card pcg-metric-copper">
                    <div class="pcg-metric-top">
                        <span class="pcg-metric-icon dashicons dashicons-heart"></span>
                        <span class="pcg-metric-tag"><?php _e('Apoyo', 'politeia-learning'); ?></span>
                    </div>
                    <div class="pcg-metric-label" data-label="patronage"><?php _e('Patrocinio', 'politeia-learning'); ?>
                    </div>
                    <div class="pcg-metric-value" data-metric="patronage">$0</div>
                    <div class="pcg-metric-foot" data-metric-pct="patronage"
                        data-suffix="<?php _e('del total', 'politeia-learning'); ?>">0%</div>
                </div>
            </div>

            <div class="pcg-sales-chart-card">
                <div class="pcg-sales-chart-head">
                    <div>
                        <h3><?php _e('Distribución de ingresos', 'politeia-learning'); ?></h3>
                        <p><?php _e('Desglose por día', 'politeia-learning'); ?></p>
                    </div>
                    <div class="pcg-sales-chart-legend" data-pcg-sales-legend aria-label="<?php esc_attr_e('Leyenda', 'politeia-learning'); ?>"></div>
                </div>
                <div class="pcg-sales-chart-wrap">
                    <canvas data-pcg-sales-chart></canvas>
                </div>
            </div>
        </div>
    </div>

    <div data-sales-panel="list" style="display:none;">
        <div class="pcg-sales-dashboard">
            <div class="pcg-sales-dashboard-header">
                <div>
                    <h2 class="pcg-sales-title"><?php _e('Panel de ventas', 'politeia-learning'); ?></h2>
                    <p class="pcg-sales-subtitle"><?php _e('Resumen de desempeño por periodo', 'politeia-learning'); ?>
                    </p>
                </div>
            </div>
        </div>

        <div class="pcg-sales-dashboard" data-pcg-sales-list>
            <div class="pcg-sales-list-topbar">
                <div class="pcg-sales-list-tabs" role="tablist" aria-label="<?php _e('Tablas', 'politeia-learning'); ?>">
                    <button type="button" class="pcg-sales-list-tab" id="pcg-sales-list-operational-tab"
                        data-pcg-sales-list-tab="operational" role="tab" aria-selected="true"
                        aria-controls="pcg-sales-list-operational">
                        <?php _e('Operacional', 'politeia-learning'); ?>
                    </button>
                    <button type="button" class="pcg-sales-list-tab" id="pcg-sales-list-summary-tab"
                        data-pcg-sales-list-tab="summary" role="tab" aria-selected="false"
                        aria-controls="pcg-sales-list-summary">
                        <?php _e('Resumen', 'politeia-learning'); ?>
                    </button>
                </div>

                <div class="pcg-sales-list-pagination" aria-label="<?php esc_attr_e('Paginación', 'politeia-learning'); ?>">
                    <button type="button" class="pcg-sales-list-page-btn" data-pcg-sales-page-prev
                        aria-label="<?php esc_attr_e('Página anterior', 'politeia-learning'); ?>">‹</button>
                    <span class="pcg-sales-list-page-label" data-pcg-sales-page-label aria-live="polite"></span>
                    <button type="button" class="pcg-sales-list-page-btn" data-pcg-sales-page-next
                        aria-label="<?php esc_attr_e('Página siguiente', 'politeia-learning'); ?>">›</button>
                </div>
            </div>

            <section class="pcg-sales-list-panel" id="pcg-sales-list-operational" role="tabpanel"
                data-pcg-sales-list-panel="operational" aria-labelledby="pcg-sales-list-operational-tab">
                <div class="pcg-sales-list-panel-head">
                    <div class="pcg-sales-list-panel-title">
                        <h3><?php _e('Ventas operacionales', 'politeia-learning'); ?></h3>
                        <p class="pcg-sales-list-hint">
                            <?php _e('Una fila por venta. Busca por estudiante, email o producto.', 'politeia-learning'); ?>
                        </p>
                    </div>

                    <div class="pcg-sales-list-controls">
                        <div class="pcg-sales-list-search">
                            <input type="search" autocomplete="off" data-pcg-sales-op-search
                                placeholder="<?php esc_attr_e('Buscar nombre, email o producto…', 'politeia-learning'); ?>">
                            <button type="button" class="pcg-sales-list-clear" data-pcg-sales-op-clear
                                title="<?php esc_attr_e('Limpiar', 'politeia-learning'); ?>">×</button>
                        </div>
                        <div class="pcg-sales-list-pill" aria-live="polite">
                            <?php _e('Mostrando', 'politeia-learning'); ?>
                            <strong data-pcg-sales-op-count>0</strong>
                            <?php _e('de', 'politeia-learning'); ?>
                            <strong data-pcg-sales-op-total>0</strong>
                        </div>
                    </div>
                </div>

                <div class="pcg-sales-list-table-wrap">
                    <table class="pcg-sales-list-table" aria-label="<?php esc_attr_e('Ventas operacionales', 'politeia-learning'); ?>">
                        <thead>
                            <tr>
                                <th style="width:320px;"><?php _e('Estudiante', 'politeia-learning'); ?></th>
                                <th><?php _e('Producto', 'politeia-learning'); ?></th>
                                <th style="width:120px;"><?php _e('Orden', 'politeia-learning'); ?></th>
                                <th style="width:130px;"><?php _e('Estado', 'politeia-learning'); ?></th>
                                <th style="width:140px; text-align:right;"><?php _e('Pagado', 'politeia-learning'); ?></th>
                                <th style="width:150px;"><?php _e('Fecha', 'politeia-learning'); ?></th>
                            </tr>
                        </thead>
                        <tbody data-pcg-sales-op-body></tbody>
                    </table>
                    <div class="pcg-sales-list-empty" data-pcg-sales-op-empty hidden>
                        <?php _e('No hay ventas que coincidan.', 'politeia-learning'); ?>
                    </div>
                </div>
            </section>

            <section class="pcg-sales-list-panel" id="pcg-sales-list-summary" role="tabpanel"
                data-pcg-sales-list-panel="summary" aria-labelledby="pcg-sales-list-summary-tab" hidden>
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
    </div>
</div>

<script>
    (function () {
        const tabs = document.getElementById('pcg-sales-tabs');
        if (!tabs) return;

        const nav = tabs.closest('.pcg-sales-nav');
        const panelsRoot = (nav && nav.nextElementSibling && nav.nextElementSibling.classList.contains('pcg-creator-section'))
            ? nav.nextElementSibling
            : document;

        const setActive = (tab) => {
            tabs.querySelectorAll('.pcg-segment').forEach(el => el.classList.remove('active'));
            const seg = tabs.querySelector(`.pcg-segment[data-sales-tab="${tab}"]`);
            if (seg) seg.classList.add('active');

            panelsRoot.querySelectorAll('[data-sales-panel]').forEach(p => p.style.display = 'none');
            const panel = panelsRoot.querySelector(`[data-sales-panel="${tab}"]`);
            if (panel) panel.style.display = '';

            window.dispatchEvent(new CustomEvent('pcg:sales-tab-changed', { detail: { tab } }));
        };

        document.addEventListener('click', (e) => {
            const seg = e.target.closest('#pcg-sales-tabs .pcg-segment[data-sales-tab]');
            if (!seg) return;
            setActive(seg.getAttribute('data-sales-tab'));
        }, true);
    })();
</script>
