<?php
/**
 * Admin: User Profile Enrollment Management
 */

namespace Learni\Admin;

use Learni\Database\Enrollments;
use Learni\PostTypes\Course;

final class UserProfile
{
    public static function init(): void
    {
        if (!is_admin()) {
            return;
        }

        add_action('show_user_profile', [__CLASS__, 'render_enrollment_section'], 20);
        add_action('edit_user_profile', [__CLASS__, 'render_enrollment_section'], 20);

        add_action('personal_options_update', [__CLASS__, 'handle_profile_updates'], 10);
        add_action('edit_user_profile_update', [__CLASS__, 'handle_profile_updates'], 10);
    }

    public static function render_enrollment_section(\WP_User $user): void
    {
        if (!current_user_can('edit_user', $user->ID)) {
            return;
        }

        $enrollments = Enrollments::get_for_user($user->ID);
        $courses = get_posts([
            'post_type' => Course::POST_TYPE,
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'orderby' => 'title',
            'order' => 'ASC',
        ]);

        wp_nonce_field('learni_user_enrollments', '_learni_nonce', false);
        ?>
        <hr />
        <h2><?php esc_html_e('Learni LMS - Inscripciones', 'politeia-learning'); ?></h2>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><?php esc_html_e('Cursos Inscritos', 'politeia-learning'); ?></th>
                <td>
                    <?php if (empty($enrollments)) : ?>
                        <p class="description"><?php esc_html_e('El usuario no está inscrito en ningún curso.', 'politeia-learning'); ?></p>
                    <?php else: ?>
                        <table class="widefat striped" style="max-width: 600px;">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('Curso', 'politeia-learning'); ?></th>
                                    <th><?php esc_html_e('Fecha', 'politeia-learning'); ?></th>
                                    <th><?php esc_html_e('Estado', 'politeia-learning'); ?></th>
                                    <th><?php esc_html_e('Acción', 'politeia-learning'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($enrollments as $e) : ?>
                                    <tr>
                                        <td><strong><?php echo esc_html($e['title']); ?></strong></td>
                                        <td><?php echo esc_html($e['startedAt']); ?></td>
                                        <td><?php echo esc_html(strtoupper($e['status'])); ?></td>
                                        <td>
                                            <label style="color: #d63638; cursor: pointer;">
                                                <input type="checkbox" name="learni_unenroll[]" value="<?php echo esc_attr((string) $e['courseId']); ?>" />
                                                <?php esc_html_e('Desinscribir', 'politeia-learning'); ?>
                                            </label>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <p class="description"><?php esc_html_e('Marca los cursos que desees eliminar y presiona "Guardar" al final de la página.', 'politeia-learning'); ?></p>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Inscribir en Nuevo Curso', 'politeia-learning'); ?></th>
                <td>
                    <select name="learni_enroll_course_id" id="learni_enroll_course_id">
                        <option value=""><?php esc_html_e('-- Selecciona un curso --', 'politeia-learning'); ?></option>
                        <?php foreach ($courses as $c) : ?>
                            <option value="<?php echo esc_attr((string) $c->ID); ?>"><?php echo esc_html($c->post_title); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description"><?php esc_html_e('Selecciona un curso para inscribir al usuario manualmente.', 'politeia-learning'); ?></p>
                </td>
            </tr>
        </table>
        <?php
    }

    public static function handle_profile_updates(int $user_id): void
    {
        if (!current_user_can('edit_user', $user_id)) {
            return;
        }

        if (!isset($_POST['_learni_nonce']) || !wp_verify_nonce($_POST['_learni_nonce'], 'learni_user_enrollments')) {
            return;
        }

        // Handle Un-enrollments
        if (!empty($_POST['learni_unenroll']) && is_array($_POST['learni_unenroll'])) {
            foreach ($_POST['learni_unenroll'] as $course_id) {
                Enrollments::delete($user_id, (int) $course_id);
            }
        }

        // Handle New Enrollment
        $new_course_id = isset($_POST['learni_enroll_course_id']) ? (int) $_POST['learni_enroll_course_id'] : 0;
        if ($new_course_id > 0) {
            Enrollments::upsert($user_id, $new_course_id, [
                'status' => Enrollments::STATUS_ACTIVE,
                'source' => Enrollments::SOURCE_MANUAL,
            ]);
        }
    }
}
