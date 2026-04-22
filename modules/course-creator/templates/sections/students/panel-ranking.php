<?php
/**
 * Students Section - Ranking & Top Performances
 */
if (!defined('ABSPATH')) exit;
?>

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
