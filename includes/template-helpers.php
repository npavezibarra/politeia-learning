<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Template helpers for Politeia Learning.
 *
 * Goal: ensure plugin templates render the same header/footer as the active theme.
 * - Classic themes: use get_header()/get_footer()
 * - Block themes (Site Editor): pre-render template parts via do_blocks() BEFORE wp_head()
 */

function pl_is_block_theme(): bool
{
    return function_exists('wp_is_block_theme') && wp_is_block_theme();
}

function pl_should_suppress_theme_footer(): bool
{
    if (class_exists('\\Learni\\Navigation\\NavOrchestrator') && \Learni\Navigation\NavOrchestrator::get_instance()->is_politeia_page()) {
        return true;
    }

    if (get_query_var('prs_my_single_plan') || get_query_var('prs_my_plans_ver_2')) {
        return true;
    }

    return false;
}

/**
 * Print the document header and theme header.
 * Stores the footer HTML for pl_template_close().
 */
function pl_template_open(): void
{
    global $pl_theme_footer_html;

    if (!pl_is_block_theme()) {
        get_header();
        return;
    }

    $pl_theme_header_html = '';
    $pl_theme_footer_html = '';

    // Pre-render block theme template parts BEFORE wp_head so their assets are enqueued in the correct place.
    if (function_exists('do_blocks')) {
        $pl_theme_header_html = (string) do_blocks('<!-- wp:template-part {"slug":"header","area":"header"} /-->');
        $should_suppress = pl_should_suppress_theme_footer();

        if (!$should_suppress) {
            $pl_theme_footer_html = (string) do_blocks('<!-- wp:template-part {"slug":"footer","area":"footer"} /-->');
        }
    }

    ?><!doctype html>
    <html <?php language_attributes(); ?>>
    <head>
        <meta charset="<?php bloginfo('charset'); ?>">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?php echo esc_html((string) wp_get_document_title()); ?></title>
        <?php wp_head(); ?>
    </head>
    <body <?php body_class(); ?>>
        <?php
        if (function_exists('wp_body_open')) {
            wp_body_open();
        }

        if ($pl_theme_header_html !== '') {
            echo $pl_theme_header_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }
        ?>
    <?php
}

/**
 * Print the theme footer and close the document.
 */
function pl_template_close(): void
{
    global $pl_theme_footer_html;

    if (!pl_is_block_theme()) {
        $should_suppress = pl_should_suppress_theme_footer();

        if (!$should_suppress) {
            get_footer();
        } else {
            wp_footer(); // Always call wp_footer() for script enqueuing
            echo '</body></html>';
            return;
        }
        return;
    }

    if (!empty($pl_theme_footer_html)) {
        echo $pl_theme_footer_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    wp_footer();
    ?>
    </body>
    </html>
    <?php
}

function pl_get_user_profile_avatar_attachment_id(int $user_id): int
{
    if ($user_id <= 0) {
        return 0;
    }

    return absint(get_user_meta($user_id, '_pl_profile_avatar_attachment_id', true));
}

function pl_get_user_profile_avatar_custom_url(int $user_id, int $size = 96): string
{
    if ($user_id <= 0) {
        return '';
    }

    $attachment_id = pl_get_user_profile_avatar_attachment_id($user_id);
    if ($attachment_id > 0) {
        $size = max(1, $size);
        $url = wp_get_attachment_image_url($attachment_id, [$size, $size]);
        if (is_string($url) && $url !== '') {
            return $url;
        }

        $url = wp_get_attachment_url($attachment_id);
        if (is_string($url) && $url !== '') {
            return $url;
        }
    }

    $stored_url = trim((string) get_user_meta($user_id, '_pl_profile_avatar_url', true));
    return $stored_url !== '' ? $stored_url : '';
}

function pl_resolve_user_id_from_avatar_source($id_or_email): int
{
    if (is_numeric($id_or_email)) {
        return absint($id_or_email);
    }

    if ($id_or_email instanceof WP_User) {
        return absint($id_or_email->ID);
    }

    if ($id_or_email instanceof WP_Comment) {
        $user_id = absint($id_or_email->user_id);
        if ($user_id > 0) {
            return $user_id;
        }

        $user = get_user_by('email', (string) $id_or_email->comment_author_email);
        return $user ? absint($user->ID) : 0;
    }

    if (is_string($id_or_email) && is_email($id_or_email)) {
        $user = get_user_by('email', $id_or_email);
        return $user ? absint($user->ID) : 0;
    }

    return 0;
}

function pl_filter_get_avatar_url(string $url, $id_or_email, array $args): string
{
    $user_id = pl_resolve_user_id_from_avatar_source($id_or_email);
    if ($user_id <= 0) {
        return $url;
    }

    $size = isset($args['size']) ? absint($args['size']) : 96;
    $custom_url = pl_get_user_profile_avatar_custom_url($user_id, $size);
    return $custom_url !== '' ? $custom_url : $url;
}

add_filter('get_avatar_url', 'pl_filter_get_avatar_url', 10, 3);

function pl_get_politeia_logo_url(): string
{
    return defined('PL_URL') ? (string) PL_URL . 'assets/images/politeia-logo.png' : '';
}

/**
 * Return the most recent pending partner invite for a course.
 *
 * @return array{label:string,email:string,user_id:int}|null
 */
function pl_get_pending_course_partner_invite(int $course_id): ?array
{
    global $wpdb;

    if ($course_id <= 0 || !$wpdb) {
        return null;
    }

    // Prefer unified partnerships table (Politeia Learning-owned) for pending course partner invites.
    $table = $wpdb->prefix . 'politeia_user_object_partnerships';
    $table_exists = ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table);
    if ($table_exists) {
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT invitee_email
                 FROM {$table}
                 WHERE object_type = %s
                   AND object_id = %d
                   AND role = %s
                   AND status = %s
                 ORDER BY id DESC
                 LIMIT 1",
                'course',
                $course_id,
                'partner',
                'pending'
            ),
            ARRAY_A
        );

        if (is_array($row) && !empty($row['invitee_email'])) {
            $email = sanitize_email((string) ($row['invitee_email'] ?? ''));
            if ($email !== '') {
                $u = get_user_by('email', $email);
                $invitee_user_id = ($u instanceof WP_User) ? (int) $u->ID : 0;
                $label = ($u instanceof WP_User && !empty($u->display_name)) ? (string) $u->display_name : $email;

                return [
                    'label' => $label,
                    'email' => $email,
                    'user_id' => $invitee_user_id,
                ];
            }
        }
    }

    // Legacy fallback: Bookshelf Reading Planner invites table.
    $table = $wpdb->prefix . 'politeia_plan_participant_invites';
    $table_exists = ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table);
    if (!$table_exists) {
        return null;
    }

    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
    $row = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT invitee_email, invitee_user_id
             FROM {$table}
             WHERE object_type = %s
               AND object_id = %d
               AND role = %s
               AND status = %s
             ORDER BY id DESC
             LIMIT 1",
            'course',
            $course_id,
            'partner',
            'pending'
        ),
        ARRAY_A
    );

    if (!is_array($row) || empty($row['invitee_email'])) {
        return null;
    }

    $email = sanitize_email((string) ($row['invitee_email'] ?? ''));
    if ($email === '') {
        return null;
    }

    $invitee_user_id = isset($row['invitee_user_id']) ? absint($row['invitee_user_id']) : 0;
    $label = $email;
    if ($invitee_user_id > 0) {
        $u = get_userdata($invitee_user_id);
        if ($u instanceof WP_User && !empty($u->display_name)) {
            $label = (string) $u->display_name;
        }
    }

    return [
        'label' => $label,
        'email' => $email,
        'user_id' => $invitee_user_id,
    ];
}

/**
 * Return the most recent pending partner invite for a course that targets the given user.
 *
 * This is used to render an "Accept / Reject" CTA directly on the course page aside.
 *
 * @return array{invite_id:int,source:string,invitee_email:string,owner_user_id:int,owner_label:string}|null
 */
function pl_get_pending_course_partner_invite_for_user(int $course_id, int $user_id): ?array
{
    global $wpdb;

    if ($course_id <= 0 || $user_id <= 0 || !$wpdb) {
        return null;
    }

    $u = get_userdata($user_id);
    if (!($u instanceof WP_User)) {
        return null;
    }
    $email = sanitize_email((string) ($u->user_email ?? ''));
    if ($email === '') {
        return null;
    }
    $email_norm = strtolower(trim($email));

    // Prefer unified partnerships table (Politeia Learning-owned) for pending course partner invites.
    $table = $wpdb->prefix . 'politeia_user_object_partnerships';
    $table_exists = ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table);
    if ($table_exists) {
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, owner_user_id, invitee_email
                 FROM {$table}
                 WHERE object_type = %s
                   AND object_id = %d
                   AND role = %s
                   AND status = %s
                 ORDER BY id DESC
                 LIMIT 1",
                'course',
                $course_id,
                'partner',
                'pending'
            ),
            ARRAY_A
        );

        if (is_array($row) && !empty($row['invitee_email'])) {
            $invitee = strtolower(trim(sanitize_email((string) ($row['invitee_email'] ?? ''))));
            if ($invitee !== '' && $invitee === $email_norm) {
                $owner_user_id = !empty($row['owner_user_id']) ? absint($row['owner_user_id']) : 0;
                $owner_label = '';
                if ($owner_user_id > 0) {
                    $owner = get_userdata($owner_user_id);
                    if ($owner instanceof WP_User) {
                        $owner_label = (string) ($owner->display_name ?? '');
                    }
                }
                $owner_label = $owner_label !== '' ? $owner_label : ($owner_user_id > 0 ? (string) $owner_user_id : __('Someone', 'politeia-learning'));

                return [
                    'invite_id' => absint($row['id'] ?? 0),
                    'source' => 'partnerships',
                    'invitee_email' => $invitee,
                    'owner_user_id' => $owner_user_id,
                    'owner_label' => $owner_label,
                ];
            }
        }
    }

    // Legacy fallback: Bookshelf Reading Planner invites table.
    $table = $wpdb->prefix . 'politeia_plan_participant_invites';
    $table_exists = ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table);
    if (!$table_exists) {
        return null;
    }

    $row = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT id, owner_user_id, invitee_email
             FROM {$table}
             WHERE object_type = %s
               AND object_id = %d
               AND role = %s
               AND status = %s
             ORDER BY id DESC
             LIMIT 1",
            'course',
            $course_id,
            'partner',
            'pending'
        ),
        ARRAY_A
    );

    if (!is_array($row) || empty($row['invitee_email'])) {
        return null;
    }

    $invitee = strtolower(trim(sanitize_email((string) ($row['invitee_email'] ?? ''))));
    if ($invitee === '' || $invitee !== $email_norm) {
        return null;
    }

    $owner_user_id = !empty($row['owner_user_id']) ? absint($row['owner_user_id']) : 0;
    $owner_label = '';
    if ($owner_user_id > 0) {
        $owner = get_userdata($owner_user_id);
        if ($owner instanceof WP_User) {
            $owner_label = (string) ($owner->display_name ?? '');
        }
    }
    $owner_label = $owner_label !== '' ? $owner_label : ($owner_user_id > 0 ? (string) $owner_user_id : __('Someone', 'politeia-learning'));

    return [
        'invite_id' => absint($row['id'] ?? 0),
        'source' => 'legacy',
        'invitee_email' => $invitee,
        'owner_user_id' => $owner_user_id,
        'owner_label' => $owner_label,
    ];
}
