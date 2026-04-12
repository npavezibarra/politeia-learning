<?php
/**
 * Shortcode UI for adding a single course partner (friends-only search).
 */

if (!defined('ABSPATH')) {
    exit;
}

class PL_Partner_Add_Shortcode
{
    private const COURSE_POST_TYPE = 'sfwd-courses';
    private const COURSE_TEACHERS_META_KEY = '_pcg_course_teachers';

    public static function init(): void
    {
        add_shortcode('politeia_add_partner', [__CLASS__, 'render']);
    }

    public static function render($atts): string
    {
        if (!is_user_logged_in()) {
            return '';
        }

        $atts = shortcode_atts([
            'course_id' => 0,
            'label' => __('Add Partner', 'politeia-learning'),
            'placeholder' => __('Search a friend…', 'politeia-learning'),
        ], (array) $atts, 'politeia_add_partner');

        $course_id = (int) ($atts['course_id'] ?? 0);
        if ($course_id <= 0 && is_singular(self::COURSE_POST_TYPE)) {
            $course_id = (int) get_queried_object_id();
        }

        if ($course_id <= 0) {
            return '';
        }

        $current_user = (int) get_current_user_id();
        if (!self::user_can_manage_course($current_user, $course_id)) {
            return '';
        }

        if (class_exists('PL_Partnerships_Repository') && method_exists('PL_Partnerships_Repository', 'get_object_partners_by_role')) {
            $partners = PL_Partnerships_Repository::get_object_partners_by_role('course', $course_id, 'partner');
            if (!empty($partners)) {
                return '';
            }
        } elseif (class_exists('PL_Partnerships_Repository') && method_exists('PL_Partnerships_Repository', 'get_object_partners')) {
            $partners = PL_Partnerships_Repository::get_object_partners('course', $course_id);
            foreach ((array) $partners as $row) {
                if (!is_array($row)) {
                    continue;
                }
                if (($row['role'] ?? '') === 'partner') {
                    return '';
                }
            }
        }

        wp_enqueue_style(
            'pl-partner-add',
            PL_URL . 'assets/css/partner-add.css',
            [],
            '1.0.0'
        );

        wp_enqueue_script(
            'pl-partner-add',
            PL_URL . 'assets/js/partner-add.js',
            [],
            '1.0.0',
            true
        );

        wp_localize_script('pl-partner-add', 'PLPartnerAdd', [
            'courseId' => $course_id,
            'friendsSearchUrl' => rest_url('politeia/v1/friends/search'),
            'addPartnerUrl' => rest_url('politeia/v1/partnerships/add'),
            'invitePartnerUrl' => rest_url('politeia/v1/partnerships/invite'),
            'nonce' => wp_create_nonce('wp_rest'),
            'i18n' => [
                'added' => __('Partner added', 'politeia-learning'),
                'invited' => __('Invitation sent', 'politeia-learning'),
                'notFound' => __('No friends found', 'politeia-learning'),
            ],
        ]);

        $label = (string) ($atts['label'] ?? '');
        $placeholder = (string) ($atts['placeholder'] ?? '');

        ob_start();
        ?>
        <div class="pl-partner-add">
            <form id="partnerForm" class="pl-partner-add__form">
                <label class="pl-partner-add__label" for="memberInput"><?php echo esc_html($label); ?></label>

                <div class="pl-partner-add__search">
                    <input id="memberInput" class="pl-partner-add__input" type="text" autocomplete="off"
                        placeholder="<?php echo esc_attr($placeholder); ?>" />
                    <div id="memberResults" class="pl-partner-add__results" aria-live="polite"></div>
                </div>

                <button id="partnerSubmit" class="pl-partner-add__submit" type="submit" disabled>
                    <?php echo esc_html($label); ?>
                </button>

                <div id="partnerSuccess" class="pl-partner-add__success" style="display:none;"></div>
            </form>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    private static function user_can_manage_course(int $user_id, int $course_id): bool
    {
        if ($user_id <= 0 || $course_id <= 0) {
            return false;
        }

        if (current_user_can('manage_options')) {
            return true;
        }

        $post = get_post($course_id);
        if (!$post || ($post->post_type ?? '') !== self::COURSE_POST_TYPE) {
            return false;
        }

        $author_id = (int) ($post->post_author ?? 0);
        if ($author_id === $user_id) {
            return true;
        }

        $teacher_ids = get_post_meta($course_id, self::COURSE_TEACHERS_META_KEY, false);
        $teacher_ids = array_map('absint', (array) $teacher_ids);
        if (in_array($user_id, $teacher_ids, true)) {
            return true;
        }

        return false;
    }
}
