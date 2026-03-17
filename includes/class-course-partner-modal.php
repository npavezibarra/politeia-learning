<?php
/**
 * Course page "Add Partner" modal + JS wiring.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PL_Course_Partner_Modal
{
    public static function init(): void
    {
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets'], 30);
        // Print modal markup before footer scripts run (wp_print_footer_scripts is priority 20).
        add_action('wp_footer', [__CLASS__, 'render_modal'], 5);
    }

    public static function enqueue_assets(): void
    {
        if (!is_singular('sfwd-courses')) {
            return;
        }

        $course_id = (int) get_queried_object_id();
        if ($course_id <= 0) {
            return;
        }

        wp_enqueue_style(
            'pl-course-partner-modal',
            PL_URL . 'assets/css/course-partner-modal.css',
            [],
            (string) @filemtime(PL_PATH . 'assets/css/course-partner-modal.css')
        );

        wp_enqueue_script(
            'pl-course-partner-modal',
            PL_URL . 'assets/js/course-partner-modal.js',
            [],
            (string) @filemtime(PL_PATH . 'assets/js/course-partner-modal.js'),
            true
        );

        wp_localize_script('pl-course-partner-modal', 'PLCoursePartner', [
            'courseId' => $course_id,
            'friendsSearchUrl' => rest_url('politeia/v1/friends/search'),
            'addPartnerUrl' => rest_url('politeia/v1/partnerships/add'),
            'invitePartnerUrl' => rest_url('politeia/v1/partnerships/invite'),
            'nonce' => wp_create_nonce('wp_rest'),
            'i18n' => self::i18n_strings(),
        ]);
    }

    private static function i18n_strings(): array
    {
        $locale = function_exists('determine_locale') ? (string) determine_locale() : (string) get_locale();
        $is_es = str_starts_with(strtolower($locale), 'es');

        $submit_members = __('Confirm partner', 'politeia-learning');
        $submit_invite = __('Send invitation', 'politeia-learning');

        // Fallbacks if translations are not configured.
        if ($is_es) {
            if ($submit_members === 'Confirm partner') {
                $submit_members = 'CONFIRMAR';
            }
            if ($submit_invite === 'Send invitation') {
                $submit_invite = 'ENVIAR INVITACIÓN';
            }
        } else {
            // Force requested uppercase label if not translated.
            if ($submit_invite === 'Send invitation') {
                $submit_invite = 'SEND INVITATION';
            }
        }

        return [
            'submitMembers' => $submit_members,
            'submitInvite' => $submit_invite,
        ];
    }

    public static function render_modal(): void
    {
        if (!is_singular('sfwd-courses')) {
            return;
        }

        if (!is_user_logged_in()) {
            return;
        }

        // Render once.
        static $rendered = false;
        if ($rendered) {
            return;
        }
        $rendered = true;
        ?>
        <div id="plPartnerOverlay" class="pl-partner-overlay" hidden>
            <div class="pl-partner-modal" role="dialog" aria-modal="true" aria-labelledby="plPartnerTitle">
                <div class="pl-partner-modal__header">
                    <h3 id="plPartnerTitle" class="pl-partner-modal__title"><?php echo esc_html__('Add Partner', 'politeia-learning'); ?></h3>
                    <button type="button" class="pl-partner-modal__close" aria-label="<?php echo esc_attr__('Close', 'politeia-learning'); ?>">
                        ×
                    </button>
                </div>

                <div id="plPartnerFormContent">
                    <div class="pl-partner-toggle" data-state="members">
                        <button type="button" class="pl-partner-toggle__option is-active" data-option="members">
                            <?php echo esc_html__('Members', 'politeia-learning'); ?>
                        </button>
                        <button type="button" class="pl-partner-toggle__option" data-option="non-members">
                            <?php echo esc_html__('Non-member', 'politeia-learning'); ?>
                        </button>
                    </div>

                    <form id="plPartnerForm" class="pl-partner-form">
                        <div class="pl-partner-field" data-mode="members">
                            <label class="pl-partner-label" for="plPartnerMemberInput"><?php echo esc_html__('Search friend', 'politeia-learning'); ?></label>
                            <input id="plPartnerMemberInput" class="pl-partner-input" type="text" autocomplete="off"
                                placeholder="<?php echo esc_attr__('Start typing…', 'politeia-learning'); ?>" />
                            <div id="plPartnerMemberResults" class="pl-partner-results" aria-live="polite"></div>
                            <div id="plPartnerMemberError" class="pl-partner-error" hidden>
                                <?php echo esc_html__('Select a friend from the list.', 'politeia-learning'); ?>
                            </div>
                        </div>

                        <div class="pl-partner-field" data-mode="non-members" hidden>
                            <div>
                                <label class="pl-partner-label" for="plPartnerFirstName"><?php echo esc_html__('First name', 'politeia-learning'); ?></label>
                                <input id="plPartnerFirstName" class="pl-partner-input" type="text" autocomplete="off"
                                    placeholder="<?php echo esc_attr__('First name', 'politeia-learning'); ?>" />
                            </div>

                            <div>
                                <label class="pl-partner-label" for="plPartnerLastName"><?php echo esc_html__('Last name', 'politeia-learning'); ?></label>
                                <input id="plPartnerLastName" class="pl-partner-input" type="text" autocomplete="off"
                                    placeholder="<?php echo esc_attr__('Last name', 'politeia-learning'); ?>" />
                            </div>

                            <div>
                                <label class="pl-partner-label" for="plPartnerEmail"><?php echo esc_html__('Email', 'politeia-learning'); ?></label>
                                <input id="plPartnerEmail" class="pl-partner-input" type="email" autocomplete="off"
                                    placeholder="<?php echo esc_attr__('name@email.com', 'politeia-learning'); ?>" />
                            </div>
                        </div>

                        <button id="plPartnerSubmit" type="submit" class="pl-partner-submit" disabled>
                            <?php echo esc_html__('Confirm', 'politeia-learning'); ?>
                        </button>

                        <div id="plPartnerError" class="pl-partner-error" hidden></div>
                    </form>
                </div>

                <div id="plPartnerSuccessContent" class="pl-partner-success" hidden>
                    <div class="pl-partner-success__title"><?php echo esc_html__('Done!', 'politeia-learning'); ?></div>
                    <div id="plPartnerSuccessMessage" class="pl-partner-success__msg"></div>
                </div>
            </div>
        </div>
        <?php
    }
}
