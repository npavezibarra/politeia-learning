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
        $default_should_load = (is_singular('sfwd-courses') || is_singular('learni_course'));
        $should_load = (bool) apply_filters('pl_course_partner_modal_should_load', $default_should_load);
        if (!$should_load) {
            return;
        }

        $course_id = (int) get_queried_object_id();
        $course_id = (int) apply_filters('pl_course_partner_modal_course_id', $course_id);
        if ($course_id <= 0) {
            return;
        }

        // Fonts/icons for the modal UI.
        wp_enqueue_style(
            'pl-course-partner-poppins',
            'https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap',
            [],
            null
        );

        wp_enqueue_style(
            'pl-course-partner-material-symbols',
            // Use the full font (no `icon_names` subsetting) to avoid glyph fallback showing text like "CLOSE".
            'https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=block',
            [],
            null
        );

        wp_enqueue_style(
            'pl-course-partner-modal',
            PL_URL . 'assets/css/course-partner-modal.css',
            ['pl-course-partner-poppins', 'pl-course-partner-material-symbols'],
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
            'revokePartnerUrl' => rest_url('politeia/v1/partnerships/revoke'),
            'nonce' => wp_create_nonce('wp_rest'),
            'i18n' => self::i18n_strings(),
        ]);
    }

    private static function i18n_strings(): array
    {
        $locale = function_exists('determine_locale') ? (string) determine_locale() : (string) get_locale();
        $is_es = str_starts_with(strtolower($locale), 'es');

        $submit_members = __('Send invitation', 'politeia-learning');
        $submit_invite = __('Send invitation', 'politeia-learning');

        // Fallbacks if translations are not configured.
        if ($is_es) {
            if ($submit_members === 'Send invitation') {
                $submit_members = 'ENVIAR INVITACIÓN';
            }
            if ($submit_invite === 'Send invitation') {
                $submit_invite = 'ENVIAR INVITACIÓN';
            }
        } else {
            // Force requested uppercase label if not translated.
            if ($submit_invite === 'Send invitation') {
                $submit_invite = 'SEND INVITATION';
            }
            if ($submit_members === 'Send invitation') {
                $submit_members = 'SEND INVITATION';
            }
        }

        return [
            'submitMembers' => $submit_members,
            'submitInvite' => $submit_invite,
            'confirmRemove' => $is_es ? '¿Eliminar partner? Esto revocará su acceso al curso.' : 'Remove partner? This will revoke their access to the course.',
            'removeFailed' => $is_es ? 'No se pudo eliminar el partner.' : 'Could not remove partner.',
        ];
    }

    public static function render_modal(): void
    {
        $default_should_load = (is_singular('sfwd-courses') || is_singular('learni_course'));
        $should_load = (bool) apply_filters('pl_course_partner_modal_should_load', $default_should_load);
        if (!$should_load) {
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
                        <span class="material-symbols-outlined" aria-hidden="true">close</span>
                    </button>
                </div>

                <div id="plPartnerFormContent">
                    <div class="pl-partner-intro" aria-hidden="false">
                        <div class="pl-partner-intro__icon" aria-hidden="true">
                            <span class="material-symbols-outlined" aria-hidden="true">groups</span>
                        </div>
                        <p class="pl-partner-intro__copy">
                            <?php echo esc_html__('Puedes añadir a un compañero de estudio que tendrá acceso al mismo contenido y podrá hacer la evaluación de manera cruzada.', 'politeia-learning'); ?>
                            <span class="pl-partner-intro__copy-strong"><?php echo esc_html__('Súma a quien tú quieras para hacer de tu estudio una aventura acompañada.', 'politeia-learning'); ?></span>
                        </p>
                    </div>

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
                            <label class="pl-partner-label" for="plPartnerMemberInput"><?php echo esc_html__('Search name', 'politeia-learning'); ?></label>
                            <div class="pl-partner-input-row">
                                <input id="plPartnerMemberInput" class="pl-partner-input" type="text" autocomplete="off"
                                    placeholder="<?php echo esc_attr__('Start typing…', 'politeia-learning'); ?>" />
                                <span class="pl-partner-input-icon material-symbols-outlined" aria-hidden="true">search</span>
                            </div>
                            <div id="plPartnerMemberResults" class="pl-partner-results" aria-live="polite"></div>
                            <div id="plPartnerMemberError" class="pl-partner-error" hidden>
                                <?php echo esc_html__('Select a member from the list.', 'politeia-learning'); ?>
                            </div>
                        </div>

                        <div class="pl-partner-field" data-mode="non-members" hidden>
                            <div>
                                <label class="pl-partner-label" for="plPartnerFirstName"><?php echo esc_html__('First name', 'politeia-learning'); ?></label>
                                <div class="pl-partner-input-row">
                                    <input id="plPartnerFirstName" class="pl-partner-input" type="text" autocomplete="off"
                                        placeholder="<?php echo esc_attr__('First name', 'politeia-learning'); ?>" />
                                </div>
                            </div>

                            <div>
                                <label class="pl-partner-label" for="plPartnerLastName"><?php echo esc_html__('Last name', 'politeia-learning'); ?></label>
                                <div class="pl-partner-input-row">
                                    <input id="plPartnerLastName" class="pl-partner-input" type="text" autocomplete="off"
                                        placeholder="<?php echo esc_attr__('Last name', 'politeia-learning'); ?>" />
                                </div>
                            </div>

                            <div>
                                <label class="pl-partner-label" for="plPartnerEmail"><?php echo esc_html__('Email', 'politeia-learning'); ?></label>
                                <div class="pl-partner-input-row">
                                    <input id="plPartnerEmail" class="pl-partner-input" type="email" autocomplete="off"
                                        placeholder="<?php echo esc_attr__('name@email.com', 'politeia-learning'); ?>" />
                                </div>
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
