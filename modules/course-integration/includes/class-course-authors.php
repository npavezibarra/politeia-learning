<?php
/**
 * Handles multiple authors/teachers for LearnDash courses.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PL_CI_Course_Authors
{

    const META_KEY = '_pcg_course_teachers';
    const AJAX_ACTION = 'pcg_search_teachers';
    const NONCE_NAME = 'pcg_course_teachers_nonce';

    public function __construct()
    {
        add_action('add_meta_boxes', [$this, 'register_metabox']);
        add_action('save_post_sfwd-courses', [$this, 'save_metabox']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('wp_ajax_' . self::AJAX_ACTION, [$this, 'ajax_search_teachers']);
    }

    /**
     * Register the metabox on LearnDash Courses.
     */
    public function register_metabox()
    {
        add_meta_box(
            'pcg-course-teachers-metabox',
            __('Profesores del Curso (Politeia)', 'politeia-learning'),
            [$this, 'render_metabox'],
            'sfwd-courses',
            'side',
            'default'
        );
    }

    /**
     * Render the metabox content.
     */
    public function render_metabox($post)
    {
        wp_nonce_field('pcg_save_course_teachers', self::NONCE_NAME);

        $teacher_ids = get_post_meta($post->ID, self::META_KEY, false);
        if (!is_array($teacher_ids)) {
            $teacher_ids = [];
        }

        // Get current post author to exclude them from the list
        $post_author_id = get_post_field('post_author', $post->ID);

        $teachers = [];
        if (!empty($teacher_ids)) {
            foreach ($teacher_ids as $id) {
                // Skip if this is the main author (in case they were added before this change)
                if ((int) $id === (int) $post_author_id)
                    continue;

                $user = get_userdata($id);
                if ($user) {
                    $teachers[] = [
                        'id' => $user->ID,
                        'name' => $user->display_name . ' (' . $user->user_login . ')',
                    ];
                }
            }
        }

        ?>
        <div class="pcg-teachers-field" id="pcg-course-teachers-wrapper">
            <div class="pcg-teachers-list tagchecklist" aria-live="polite">
                <?php foreach ($teachers as $teacher): ?>
                    <span class="pcg-teacher-tag" data-user-id="<?php echo esc_attr($teacher['id']); ?>">
                        <span class="pcg-tag-label">
                            <?php echo esc_html($teacher['name']); ?>
                        </span>
                        <button type="button" class="pcg-tag-remove"
                            aria-label="<?php esc_attr_e('Eliminar profesor', 'politeia-learning'); ?>">&times;</button>
                    </span>
                <?php endforeach; ?>
            </div>

            <div class="pcg-search-wrapper">
                <input type="text" id="pcg-teacher-search" class="widefat"
                    placeholder="<?php esc_attr_e('Buscar profesor...', 'politeia-learning'); ?>" autocomplete="off" />
                <div class="pcg-search-results" style="display:none;"></div>
            </div>

            <input type="hidden" name="pcg_course_teachers" id="pcg-teachers-hidden"
                value="<?php echo esc_attr(wp_json_encode($teacher_ids)); ?>" />
        </div>
        <style>
            .pcg-teacher-tag {
                display: inline-flex;
                align-items: center;
                background: #f0f0f1;
                border: 1px solid #c3c4c7;
                border-radius: 3px;
                padding: 2px 8px;
                margin: 2px;
                font-size: 12px;
            }

            .pcg-tag-remove {
                background: none;
                border: none;
                color: #d63638;
                cursor: pointer;
                font-size: 18px;
                line-height: 1;
                margin-left: 5px;
                padding: 0;
            }

            .pcg-tag-remove:hover {
                color: #8a1f11;
            }

            .pcg-search-wrapper {
                position: relative;
                margin-top: 10px;
            }

            .pcg-search-results {
                position: absolute;
                top: 100%;
                left: 0;
                right: 0;
                background: #fff;
                border: 1px solid #c3c4c7;
                z-index: 100;
                max-height: 200px;
                overflow-y: auto;
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            }

            .pcg-search-results div {
                padding: 8px 12px;
                cursor: pointer;
                border-bottom: 1px solid #f0f0f1;
            }

            .pcg-search-results div:hover {
                background: #f0f0f1;
            }

            .pcg-search-results div:last-child {
                border-bottom: none;
            }
        </style>
        <?php
    }

    /**
     * Save the metabox data.
     */
    public function save_metabox($post_id)
    {
        if (!isset($_POST[self::NONCE_NAME]) || !wp_verify_nonce($_POST[self::NONCE_NAME], 'pcg_save_course_teachers')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        if (isset($_POST['pcg_course_teachers'])) {
            $data = json_decode(wp_unslash($_POST['pcg_course_teachers']), true);

            // Delete existing relations
            delete_post_meta($post_id, self::META_KEY);

            if (is_array($data)) {
                $sanitized = array_unique(array_filter(array_map('absint', $data)));
                $post_author_id = get_post_field('post_author', $post_id);

                foreach ($sanitized as $teacher_id) {
                    // Prevent adding main author as additional teacher
                    if ((int) $teacher_id === (int) $post_author_id)
                        continue;

                    add_post_meta($post_id, self::META_KEY, $teacher_id);
                }
            }
        }
    }

    /**
     * Enqueue JS and CSS.
     */
    public function enqueue_assets($hook)
    {
        if (!in_array($hook, ['post.php', 'post-new.php'])) {
            return;
        }

        $screen = get_current_screen();
        if (!$screen || 'sfwd-courses' !== $screen->post_type) {
            return;
        }

        wp_enqueue_script('pcg-course-authors', PL_CI_URL . 'assets/js/pcg-course-authors.js', ['jquery'], '1.0.0', true);

        $screen = get_current_screen();
        $post_id = isset($_GET['post']) ? absint($_GET['post']) : 0;
        $post_author_id = $post_id ? get_post_field('post_author', $post_id) : 0;

        wp_localize_script('pcg-course-authors', 'pcgCourseAuthors', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('pcg_search_teachers_nonce'),
            'action' => self::AJAX_ACTION,
            'mainAuthorId' => (int) $post_author_id,
        ]);
    }

    /**
     * AJAX handler for searching teachers.
     */
    public function ajax_search_teachers()
    {
        check_ajax_referer('pcg_search_teachers_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Unauthorized', 403);
        }

        $query = isset($_POST['q']) ? sanitize_text_field($_POST['q']) : '';
        $exclude_id = isset($_POST['exclude_author']) ? absint($_POST['exclude_author']) : 0;

        $user_query_args = [
            'search' => '*' . $query . '*',
            'search_columns' => ['user_login', 'display_name', 'user_email', 'user_nicename'],
            'number' => 20,
            'orderby' => 'display_name',
            'order' => 'ASC',
        ];

        if ($exclude_id) {
            $user_query_args['exclude'] = [$exclude_id];
        }

        $user_query = new WP_User_Query($user_query_args);

        $results = [];
        $users = $user_query->get_results();

        if (!empty($users)) {
            foreach ($users as $user) {
                $results[] = [
                    'id' => $user->ID,
                    'name' => $user->display_name . ' (' . $user->user_login . ')',
                ];
            }
        }

        wp_send_json_success($results);
    }
}
