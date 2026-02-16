<div class="pcg-form-nav pcg-sales-nav">
    <div class="pcg-nav-left">
        <span class="pcg-current-course-label"><?php _e('VENTAS', 'politeia-learning'); ?></span>
    </div>
    <div class="pcg-nav-right">
        <div class="pcg-segmented-control" id="pcg-sales-tabs">
            <div class="pcg-segment active" data-sales-tab="general">
                <?php _e('GENERAL', 'politeia-learning'); ?>
            </div>
            <div class="pcg-segment" data-sales-tab="courses">
                <?php _e('CURSOS', 'politeia-learning'); ?>
            </div>
            <div class="pcg-segment" data-sales-tab="books">
                <?php _e('LIBROS', 'politeia-learning'); ?>
            </div>
        </div>
    </div>
</div>

<div class="pcg-creator-section">
    <div data-sales-panel="general">
        <div class="pcg-sales-dashboard">
            <div class="pcg-sales-dashboard-header">
                <div>
                    <h2 class="pcg-sales-title"><?php _e('Panel de ventas', 'politeia-learning'); ?></h2>
                    <p class="pcg-sales-subtitle"><?php _e('Resumen de desempeño por periodo', 'politeia-learning'); ?></p>
                </div>

                <div class="pcg-sales-controls">
                    <div class="pcg-sales-timeframes" role="tablist" aria-label="<?php _e('Periodo', 'politeia-learning'); ?>">
                        <button type="button" class="pcg-sales-tf-btn" data-timeframe="day"><?php _e('Día', 'politeia-learning'); ?></button>
                        <button type="button" class="pcg-sales-tf-btn" data-timeframe="week"><?php _e('Semana', 'politeia-learning'); ?></button>
                        <button type="button" class="pcg-sales-tf-btn active" data-timeframe="month"><?php _e('Mes', 'politeia-learning'); ?></button>
                        <button type="button" class="pcg-sales-tf-btn" data-timeframe="year"><?php _e('Año', 'politeia-learning'); ?></button>
                        <button type="button" class="pcg-sales-tf-btn" data-timeframe="custom"><?php _e('Personalizado', 'politeia-learning'); ?></button>
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
                    <div class="pcg-metric-label"><?php _e('Ventas totales', 'politeia-learning'); ?></div>
                    <div class="pcg-metric-value" data-metric="total">$0</div>
                    <div class="pcg-metric-foot"><?php _e('Desempeño total', 'politeia-learning'); ?></div>
                </div>

                <div id="politeia-user-sales-courses" class="pcg-metric-card pcg-metric-silver">
                    <div class="pcg-metric-top">
                        <span class="pcg-metric-icon dashicons dashicons-welcome-learn-more"></span>
                        <span class="pcg-metric-tag"><?php _e('Cursos', 'politeia-learning'); ?></span>
                    </div>
                    <div class="pcg-metric-label"><?php _e('Ventas de cursos', 'politeia-learning'); ?></div>
                    <div class="pcg-metric-value" data-metric="courses">$0</div>
                    <div class="pcg-metric-foot" data-metric-pct="courses" data-suffix="<?php _e('del total', 'politeia-learning'); ?>">0%</div>
                </div>

                <div id="politeia-user-sales-books" class="pcg-metric-card pcg-metric-silver">
                    <div class="pcg-metric-top">
                        <span class="pcg-metric-icon dashicons dashicons-book"></span>
                        <span class="pcg-metric-tag"><?php _e('Libros', 'politeia-learning'); ?></span>
                    </div>
                    <div class="pcg-metric-label"><?php _e('Ventas de libros', 'politeia-learning'); ?></div>
                    <div class="pcg-metric-value" data-metric="books">$0</div>
                    <div class="pcg-metric-foot" data-metric-pct="books" data-suffix="<?php _e('del total', 'politeia-learning'); ?>">0%</div>
                </div>

                <div id="politeia-user-sales-patronage" class="pcg-metric-card pcg-metric-copper">
                    <div class="pcg-metric-top">
                        <span class="pcg-metric-icon dashicons dashicons-heart"></span>
                        <span class="pcg-metric-tag"><?php _e('Apoyo', 'politeia-learning'); ?></span>
                    </div>
                    <div class="pcg-metric-label"><?php _e('Patrocinio', 'politeia-learning'); ?></div>
                    <div class="pcg-metric-value" data-metric="patronage">$0</div>
                    <div class="pcg-metric-foot" data-metric-pct="patronage" data-suffix="<?php _e('del total', 'politeia-learning'); ?>">0%</div>
                </div>
            </div>

            <div class="pcg-sales-chart-card">
                <div class="pcg-sales-chart-head">
                    <div>
                        <h3><?php _e('Distribución de ingresos', 'politeia-learning'); ?></h3>
                        <p><?php _e('Desglose por día', 'politeia-learning'); ?></p>
                    </div>
                </div>
                <div class="pcg-sales-chart-wrap">
                    <canvas data-pcg-sales-chart></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="pcg-stats-overview" data-sales-panel="courses" style="display:none;">
        <div class="stat-card">
            <span class="stat-label"><?php _e('Ventas de cursos', 'politeia-learning'); ?></span>
            <span class="stat-value">$0.00</span>
        </div>
        <div class="stat-card">
            <span class="stat-label"><?php _e('Cursos vendidos', 'politeia-learning'); ?></span>
            <span class="stat-value">0</span>
        </div>
    </div>

    <div class="pcg-stats-overview" data-sales-panel="books" style="display:none;">
        <div class="stat-card">
            <span class="stat-label"><?php _e('Ventas de libros', 'politeia-learning'); ?></span>
            <span class="stat-value">$0.00</span>
        </div>
        <div class="stat-card">
            <span class="stat-label"><?php _e('Libros vendidos', 'politeia-learning'); ?></span>
            <span class="stat-value">0</span>
        </div>
    </div>
</div>

<script>
    (function () {
        const tabs = document.getElementById('pcg-sales-tabs');
        if (!tabs) return;

        const setActive = (tab) => {
            tabs.querySelectorAll('.pcg-segment').forEach(el => el.classList.remove('active'));
            tabs.querySelector(`.pcg-segment[data-sales-tab="${tab}"]`)?.classList.add('active');

            document.querySelectorAll('[data-sales-panel]').forEach(p => p.style.display = 'none');
            document.querySelector(`[data-sales-panel="${tab}"]`)?.style.display = '';

            window.dispatchEvent(new CustomEvent('pcg:sales-tab-changed', { detail: { tab } }));
        };

        tabs.addEventListener('click', (e) => {
            const seg = e.target.closest('.pcg-segment');
            if (!seg) return;
            setActive(seg.getAttribute('data-sales-tab'));
        });
    })();
</script>
