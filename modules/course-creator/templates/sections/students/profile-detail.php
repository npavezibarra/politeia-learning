<?php
/**
 * Students Section - Profile Detail (Individual View)
 */
if (!defined('ABSPATH')) exit;
?>

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
                            <!-- Loaded via AJAX -->
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
                            <!-- Loaded via AJAX -->
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
                            <!-- Loaded via AJAX -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
