<?php
if (!defined('ABSPATH')) exit;

// Layout variant (set by wrapper template when needed).
$pl_profile_layout = isset($pl_profile_layout) ? (string) $pl_profile_layout : 'maxwidth';
$pl_profile_is_fullwidth = ($pl_profile_layout === 'fullwidth');
$pl_container_max_width = (string) get_option('pcg_container_max_width', '1200px');
$pl_profile_content_container_class = $pl_profile_is_fullwidth ? 'w-full' : 'max-w-6xl mx-auto';

// Public profile route: /profile/{username}
$user_id = (int) get_query_var('pl_profile_user_id', 0);

// User ID is provided by the public route or defaults to logged-in user.

// Pure WordPress fallback: logged-in user's own profile.
if (!$user_id) {
    if (is_user_logged_in()) {
        $user_id = get_current_user_id();
    } else {
        auth_redirect();
        exit;
    }
}

$userdata = get_userdata($user_id);
$userdata = $userdata instanceof WP_User ? $userdata : null;
if (!$userdata) {
    status_header(404);
    nocache_headers();
    require get_404_template();
    exit;
}
$display_name = function_exists('pl_get_user_full_name_or_display_name') 
    ? pl_get_user_full_name_or_display_name($user_id, $userdata->display_name) 
    : $userdata->display_name;

$avatar_url = function_exists('pl_get_user_profile_avatar_custom_url')
    ? pl_get_user_profile_avatar_custom_url($user_id, 128)
    : '';
if ($avatar_url === '') {
    $avatar_url = get_avatar_url($user_id, ['size' => 128]);
}

// --- Portfolio Settings ---
$portfolio_manager = PL_Member_Profile_Portfolio_Manager::get_instance();
$portfolio_settings = $portfolio_manager->get_settings($user_id);
// --- Social Settings (Native User Meta) ---
$twitter = get_user_meta($user_id, 'twitter_url', true);
$linkedin = get_user_meta($user_id, 'linkedin_url', true);
$github = get_user_meta($user_id, 'github_url', true);
$instagram = get_user_meta($user_id, 'instagram_url', true);

// Rank
$rank = get_user_meta($user_id, 'pl_profile_rank', true) ?: 'Premium Member';

// Header context helpers.
$logged_in_user_id = (int) get_current_user_id();
$is_own_profile = is_user_logged_in() && $logged_in_user_id > 0 && $logged_in_user_id === (int) $user_id;

$pl_access_level = 'public';
$pl_allowed_tabs = ['main'];
$pl_follow_status = '';
$pl_friend_status = '';
$pl_subscribe_active = false;
if (class_exists('PL_Relationships')) {
    $pl_access_level = PL_Relationships::get_access_level($logged_in_user_id, (int) $user_id);
    if ($pl_access_level === 'owner') {
        $pl_allowed_tabs = ['main', 'courses', 'writings', 'specializations', 'thoughts', 'plans', 'book', 'connections'];
    } else {
        $policy_kind = in_array($pl_access_level, [PL_Relationships::TYPE_FOLLOW, PL_Relationships::TYPE_FRIEND, PL_Relationships::TYPE_SUBSCRIBE], true)
            ? $pl_access_level
            : 'public';
        $policy = PL_Relationships::get_owner_policy((int) $user_id, $policy_kind);
        $pl_allowed_tabs = isset($policy['profile_tabs']) && is_array($policy['profile_tabs']) ? $policy['profile_tabs'] : ['main'];
        $pl_allowed_tabs = array_values(array_unique(array_map(static function ($tab) {
            $tab = sanitize_key((string) $tab);
            return $tab === 'requests' ? 'connections' : $tab;
        }, $pl_allowed_tabs)));
        if ($pl_allowed_tabs === []) {
            $pl_allowed_tabs = ['main'];
        }
    }

    if (!$is_own_profile && $logged_in_user_id > 0) {
        $follow = PL_Relationships::get_relationship($logged_in_user_id, (int) $user_id, PL_Relationships::TYPE_FOLLOW);
        $friend = PL_Relationships::get_relationship($logged_in_user_id, (int) $user_id, PL_Relationships::TYPE_FRIEND);
        $pl_follow_status = is_array($follow) ? (string) ($follow['status'] ?? '') : '';
        $pl_friend_status = is_array($friend) ? (string) ($friend['status'] ?? '') : '';
        $pl_subscribe_active = PL_Relationships::is_effective($logged_in_user_id, (int) $user_id, PL_Relationships::TYPE_SUBSCRIBE);
    }
}

$user_domain = '';
$friends_url = '';
$notifications_url = '';

$friends_count = 0;
$unread_notifications = 0;

$pl_pending_follow_requests = [];
$pl_pending_course_partner_invites = [];
$pl_recent_course_partner_accept = null;
$pl_relationship_respond_nonce = '';
$pl_relationship_block_nonce = '';
$pl_course_partner_invite_nonce = '';
if ($is_own_profile && class_exists('PL_Relationships')) {
    $pl_relationship_respond_nonce = (string) wp_create_nonce('pl_relationship_respond');
    $pl_relationship_block_nonce = (string) wp_create_nonce('pl_relationship_block');
    $pending = PL_Relationships::get_pending_requests_for_owner((int) $logged_in_user_id);
    foreach ($pending as $req) {
        if (($req['rel_type'] ?? '') !== PL_Relationships::TYPE_FOLLOW) {
            continue;
        }
        $from_id = (int) ($req['from_user_id'] ?? 0);
        if ($from_id <= 0) {
            continue;
        }
        $u = get_userdata($from_id);
        $name = ($u instanceof WP_User) ? ((string) ($u->display_name ?: $u->user_login)) : ('User #' . $from_id);
        $avatar = function_exists('pl_get_user_profile_avatar_custom_url')
            ? pl_get_user_profile_avatar_custom_url($from_id, 64)
            : '';
        if ($avatar === '') {
            $avatar = (string) get_avatar_url($from_id, ['size' => 64]);
        }
        $pl_pending_follow_requests[] = [
            'id' => (int) ($req['id'] ?? 0),
            'from_user_id' => $from_id,
            'from_name' => $name,
            'from_avatar_url' => $avatar,
            'created_at' => (string) ($req['created_at'] ?? ''),
        ];
    }
}

// Course partner invitations (separate from friend/follow requests).
if ($is_own_profile && $logged_in_user_id > 0) {
    $pl_course_partner_invite_nonce = (string) wp_create_nonce('pl_course_partner_invite_respond');
    $current_user = get_userdata((int) $logged_in_user_id);
    $current_email = ($current_user instanceof WP_User) ? strtolower(trim((string) ($current_user->user_email ?? ''))) : '';

    if ($current_email !== '') {
        global $wpdb;
        if ($wpdb) {
            $now_ts = current_time('timestamp', true);

            $add_invite = static function (array $invite) use (&$pl_pending_course_partner_invites) {
                $key = (string) ($invite['course_id'] ?? 0) . '|' . (string) ($invite['invitee_email'] ?? '');
                $pl_pending_course_partner_invites[$key] = $invite;
            };

            $table = $wpdb->prefix . 'politeia_user_object_partnerships';
            $table_exists = ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table);
            if ($table_exists) {
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
                $rows = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT id, object_id, owner_user_id, invitee_email, invited_at, created_at, expires_at
                         FROM {$table}
                         WHERE object_type = %s
                           AND role = %s
                           AND status = %s
                           AND LOWER(invitee_email) = %s
                         ORDER BY id DESC",
                        'course',
                        'partner',
                        'pending',
                        $current_email
                    ),
                    ARRAY_A
                );

                foreach ((array) $rows as $row) {
                    $invite_id = (int) ($row['id'] ?? 0);
                    $course_id = (int) ($row['object_id'] ?? 0);
                    if ($invite_id <= 0 || $course_id <= 0) {
                        continue;
                    }
                    $expires_ts = strtotime((string) ($row['expires_at'] ?? '') . ' UTC');
                    if ($expires_ts && $expires_ts < $now_ts) {
                        continue;
                    }

                    $from_id = (int) ($row['owner_user_id'] ?? 0);
                    $from_user = $from_id > 0 ? get_userdata($from_id) : null;
                    $from_name = ($from_user instanceof WP_User) ? (string) ($from_user->display_name ?: $from_user->user_login) : '';
                    if ($from_name === '') {
                        $from_name = $from_id > 0 ? ('User #' . $from_id) : __('Someone', 'politeia-learning');
                    }
                    $from_avatar = $from_id > 0 && function_exists('pl_get_user_profile_avatar_custom_url')
                        ? pl_get_user_profile_avatar_custom_url($from_id, 64)
                        : '';
                    if ($from_avatar === '' && $from_id > 0) {
                        $from_avatar = (string) get_avatar_url($from_id, ['size' => 64]);
                    }

                    $course_title = (string) get_the_title($course_id);
                    if ($course_title === '') {
                        $course_title = sprintf(__('Course #%d', 'politeia-learning'), $course_id);
                    }

                    $add_invite([
                        'id' => $invite_id,
                        'source' => 'partnerships',
                        'course_id' => $course_id,
                        'course_title' => $course_title,
                        'from_user_id' => $from_id,
                        'from_name' => $from_name,
                        'from_avatar_url' => $from_avatar,
                        'created_at' => (string) ($row['invited_at'] ?? $row['created_at'] ?? ''),
                        'invitee_email' => $current_email,
                    ]);
                }
            }

            $legacy = $wpdb->prefix . 'politeia_plan_participant_invites';
            $legacy_exists = ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $legacy)) === $legacy);
            if ($legacy_exists) {
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
                $rows = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT id, object_id, inviter_user_id, invitee_email, created_at, expires_at
                         FROM {$legacy}
                         WHERE object_type = %s
                           AND role = %s
                           AND status = %s
                           AND LOWER(invitee_email) = %s
                         ORDER BY id DESC",
                        'course',
                        'partner',
                        'pending',
                        $current_email
                    ),
                    ARRAY_A
                );

                foreach ((array) $rows as $row) {
                    $invite_id = (int) ($row['id'] ?? 0);
                    $course_id = (int) ($row['object_id'] ?? 0);
                    if ($invite_id <= 0 || $course_id <= 0) {
                        continue;
                    }
                    $expires_ts = strtotime((string) ($row['expires_at'] ?? '') . ' UTC');
                    if ($expires_ts && $expires_ts < $now_ts) {
                        continue;
                    }

                    $from_id = (int) ($row['inviter_user_id'] ?? 0);
                    $from_user = $from_id > 0 ? get_userdata($from_id) : null;
                    $from_name = ($from_user instanceof WP_User) ? (string) ($from_user->display_name ?: $from_user->user_login) : '';
                    if ($from_name === '') {
                        $from_name = $from_id > 0 ? ('User #' . $from_id) : __('Someone', 'politeia-learning');
                    }
                    $from_avatar = $from_id > 0 && function_exists('pl_get_user_profile_avatar_custom_url')
                        ? pl_get_user_profile_avatar_custom_url($from_id, 64)
                        : '';
                    if ($from_avatar === '' && $from_id > 0) {
                        $from_avatar = (string) get_avatar_url($from_id, ['size' => 64]);
                    }

                    $course_title = (string) get_the_title($course_id);
                    if ($course_title === '') {
                        $course_title = sprintf(__('Course #%d', 'politeia-learning'), $course_id);
                    }

                    $add_invite([
                        'id' => $invite_id,
                        'source' => 'legacy',
                        'course_id' => $course_id,
                        'course_title' => $course_title,
                        'from_user_id' => $from_id,
                        'from_name' => $from_name,
                        'from_avatar_url' => $from_avatar,
                        'created_at' => (string) ($row['created_at'] ?? ''),
                        'invitee_email' => $current_email,
                    ]);
                }
            }
        }
    }
}

// Build a "recently accepted" card so the UI can expand and show progress after clicking Accept.
if ($is_own_profile && $logged_in_user_id > 0) {
    $recent_status = isset($_GET['pl_cp_invite']) ? sanitize_key((string) wp_unslash($_GET['pl_cp_invite'])) : '';
    $recent_id = isset($_GET['pl_cp_invite_id']) ? absint((string) wp_unslash($_GET['pl_cp_invite_id'])) : 0;
    $recent_source = isset($_GET['pl_cp_invite_source']) ? sanitize_key((string) wp_unslash($_GET['pl_cp_invite_source'])) : '';

    if ($recent_status === 'accepted' && $recent_id > 0) {
        $current_user = get_userdata((int) $logged_in_user_id);
        $current_email = ($current_user instanceof WP_User) ? strtolower(trim((string) ($current_user->user_email ?? ''))) : '';

        global $wpdb;
        if ($wpdb && $current_email !== '') {
            $source = ($recent_source === 'legacy') ? 'legacy' : 'partnerships';
            $table = $wpdb->prefix . ($source === 'legacy' ? 'politeia_plan_participant_invites' : 'politeia_user_object_partnerships');

            $row = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT id, object_id, object_type, role, status, invitee_email, accepted_at, created_at, invited_at, owner_user_id, inviter_user_id
                     FROM {$table}
                     WHERE id = %d
                     LIMIT 1",
                    $recent_id
                ),
                ARRAY_A
            );

            if (is_array($row)) {
                $object_type = sanitize_key((string) ($row['object_type'] ?? ''));
                $role = sanitize_key((string) ($row['role'] ?? ''));
                $status = sanitize_key((string) ($row['status'] ?? ''));
                $invite_email = strtolower(trim((string) ($row['invitee_email'] ?? '')));
                $course_id = (int) ($row['object_id'] ?? 0);

                if ($object_type === 'course' && $role === 'partner' && $status === 'accepted' && $invite_email === $current_email && $course_id > 0) {
                    $from_user_id = (int) (($source === 'legacy') ? ($row['inviter_user_id'] ?? 0) : ($row['owner_user_id'] ?? 0));
                    $from_user = $from_user_id > 0 ? get_userdata($from_user_id) : null;
                    $from_name = ($from_user instanceof WP_User) ? (string) ($from_user->display_name ?: $from_user->user_login) : '';
                    if ($from_name === '') {
                        $from_name = $from_user_id > 0 ? ('User #' . $from_user_id) : __('Someone', 'politeia-learning');
                    }
                    $from_avatar = $from_user_id > 0 && function_exists('pl_get_user_profile_avatar_custom_url')
                        ? pl_get_user_profile_avatar_custom_url($from_user_id, 64)
                        : '';
                    if ($from_avatar === '' && $from_user_id > 0) {
                        $from_avatar = (string) get_avatar_url($from_user_id, ['size' => 64]);
                    }

                    $course_title = (string) get_the_title($course_id);
                    if ($course_title === '') {
                        $course_title = sprintf(__('Course #%d', 'politeia-learning'), $course_id);
                    }

                    $course_url = (string) get_permalink($course_id);

                    $me_name = $display_name;
                    $me_avatar = $avatar_url;

                    $me_percent = 0;
                    $from_percent = 0;
                    if (class_exists('\\Learni\\Database\\Progress')) {
                        try {
                            $sum_me = \Learni\Database\Progress::course_summary((int) $logged_in_user_id, $course_id);
                            $sum_from = $from_user_id > 0 ? \Learni\Database\Progress::course_summary((int) $from_user_id, $course_id) : null;
                            $me_percent = is_array($sum_me) ? (int) ($sum_me['percent'] ?? 0) : 0;
                            $from_percent = is_array($sum_from) ? (int) ($sum_from['percent'] ?? 0) : 0;
                        } catch (\Throwable $e) {
                            $me_percent = 0;
                            $from_percent = 0;
                        }
                    }

                    $pl_recent_course_partner_accept = [
                        'invite_id' => $recent_id,
                        'course_id' => $course_id,
                        'course_title' => $course_title,
                        'course_url' => $course_url,
                        'accepted_at' => (string) ($row['accepted_at'] ?? ''),
                        'me' => [
                            'user_id' => (int) $logged_in_user_id,
                            'name' => $me_name,
                            'avatar_url' => $me_avatar,
                            'percent' => $me_percent,
                        ],
                        'other' => [
                            'user_id' => $from_user_id,
                            'name' => $from_name,
                            'avatar_url' => $from_avatar,
                            'percent' => $from_percent,
                        ],
                    ];
                }
            }
        }
    }
}

$pl_connections_data = [
    'summary' => [
        'pending_received' => 0,
        'pending_sent' => 0,
        'active_projects' => 0,
        'memberships' => 0,
        'history' => 0,
    ],
    'pending_received' => [],
    'pending_sent' => [],
    'active_projects' => [],
    'memberships' => [],
    'history' => [],
    'recent_accepted_partner_invite' => $pl_recent_course_partner_accept,
];

if ($is_own_profile && $logged_in_user_id > 0) {
    global $wpdb;
    if ($wpdb) {
        $current_user = get_userdata((int) $logged_in_user_id);
        $current_email = ($current_user instanceof WP_User) ? strtolower(trim((string) ($current_user->user_email ?? ''))) : '';
        $current_login = ($current_user instanceof WP_User) ? (string) $current_user->user_login : '';

        $user_payload = static function (int $user_id, string $fallback_name = 'User') use ($avatar_url, $display_name): array {
            $user_id = (int) $user_id;
            if ($user_id <= 0) {
                return [
                    'user_id' => 0,
                    'name' => $fallback_name,
                    'avatar_url' => '',
                ];
            }

            $u = get_userdata($user_id);
            $name = ($u instanceof WP_User) ? (string) ($u->display_name ?: $u->user_login) : '';
            if ($name === '') {
                $name = $fallback_name !== '' ? $fallback_name : sprintf('User #%d', $user_id);
            }

            $avatar = function_exists('pl_get_user_profile_avatar_custom_url')
                ? pl_get_user_profile_avatar_custom_url($user_id, 64)
                : '';
            if ($avatar === '') {
                $avatar = (string) get_avatar_url($user_id, ['size' => 64]);
            }

            return [
                'user_id' => $user_id,
                'name' => $name,
                'avatar_url' => $avatar,
            ];
        };

        $course_title_for = static function (int $course_id): string {
            $course_id = (int) $course_id;
            if ($course_id <= 0) {
                return '';
            }

            $title = (string) get_the_title($course_id);
            if ($title === '') {
                $title = sprintf('Course #%d', $course_id);
            }

            return $title;
        };

        $plan_title_for = static function (int $plan_id) use ($wpdb): string {
            $plan_id = (int) $plan_id;
            if ($plan_id <= 0 || !$wpdb) {
                return '';
            }

            $plans_table = $wpdb->prefix . 'politeia_plans';
            $title = (string) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT name FROM {$plans_table} WHERE id = %d LIMIT 1",
                    $plan_id
                )
            );

            if ($title === '') {
                $title = sprintf('Plan #%d', $plan_id);
            }

            return $title;
        };

        $subscription_creator_profile_url = static function (int $user_id): string {
            $user_id = (int) $user_id;
            $u = $user_id > 0 ? get_userdata($user_id) : null;
            if ($u instanceof WP_User && (string) $u->user_nicename !== '') {
                return (string) home_url('/profile/' . rawurlencode((string) $u->user_nicename) . '/');
            }

            return '';
        };

        $normalize_state = static function (string $status, ?string $expires_at = null): string {
            $status = sanitize_key($status);
            if ($expires_at !== null && $expires_at !== '') {
                $ts = strtotime($expires_at . ' UTC');
                if ($ts !== false && $ts < current_time('timestamp', true)) {
                    return 'expired';
                }
            }

            if ($status === 'accepted' || $status === 'active') {
                return 'active';
            }
            if ($status === 'pending') {
                return 'pending';
            }
            if (in_array($status, ['rejected', 'declined'], true)) {
                return 'rejected';
            }
            if (in_array($status, ['revoked', 'cancelled', 'canceled', 'paused', 'suspended'], true)) {
                return 'revoked';
            }
            if ($status === 'expired') {
                return 'expired';
            }

            return $status !== '' ? $status : 'unknown';
        };

        $state_label_for = static function (string $state): string {
            $state = sanitize_key($state);
            switch ($state) {
                case 'pending':
                    return __('Pendiente', 'politeia-learning');
                case 'active':
                    return __('Activa', 'politeia-learning');
                case 'rejected':
                    return __('Rechazada', 'politeia-learning');
                case 'revoked':
                    return __('Revocada', 'politeia-learning');
                case 'expired':
                    return __('Expirada', 'politeia-learning');
                default:
                    return $state !== '' ? ucfirst($state) : __('Desconocida', 'politeia-learning');
            }
        };

        $pending_received_map = [];
        $pending_sent_map = [];
        $active_projects_map = [];
        $memberships_map = [];
        $history_map = [];

        $add_unique = static function (array &$bucket, string $key, array $item): void {
            $bucket[$key] = $item;
        };

        $relationships_table = $wpdb->prefix . 'politeia_user_relationships';
        $relationships_exists = ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $relationships_table)) === $relationships_table);
        if ($relationships_exists) {
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT id, from_user_id, to_user_id, rel_type, status, expires_at, created_at, updated_at
                     FROM {$relationships_table}
                     WHERE from_user_id = %d OR to_user_id = %d
                     ORDER BY id DESC",
                    $logged_in_user_id,
                    $logged_in_user_id
                ),
                ARRAY_A
            );

            foreach ((array) $rows as $row) {
                $id = (int) ($row['id'] ?? 0);
                $from_id = (int) ($row['from_user_id'] ?? 0);
                $to_id = (int) ($row['to_user_id'] ?? 0);
                $rel_type = sanitize_key((string) ($row['rel_type'] ?? ''));
                $status = sanitize_key((string) ($row['status'] ?? ''));
                $expires_at = (string) ($row['expires_at'] ?? '');
                $state = $normalize_state($status, $expires_at);
                $other_user_id = $from_id === (int) $logged_in_user_id ? $to_id : $from_id;
                $other_user = $user_payload($other_user_id, __('Someone', 'politeia-learning'));
                $direction = $from_id === (int) $logged_in_user_id ? 'sent' : 'received';

                $base_item = [
                    'id' => $id,
                    'kind' => 'relationship',
                    'group' => in_array($rel_type, [PL_Relationships::TYPE_FOLLOW, PL_Relationships::TYPE_FRIEND], true) ? 'community' : 'other',
                    'rel_type' => $rel_type,
                    'status' => $state,
                    'state_label' => $state_label_for($state),
                    'direction' => $direction,
                    'user' => $other_user,
                    'title' => $rel_type === PL_Relationships::TYPE_FRIEND ? __('Friendship', 'politeia-learning') : ucfirst($rel_type),
                    'created_at' => (string) ($row['created_at'] ?? ''),
                    'expires_at' => $expires_at,
                ];

                if ($state === 'pending' && in_array($rel_type, [PL_Relationships::TYPE_FOLLOW, PL_Relationships::TYPE_FRIEND], true)) {
                    if ($direction === 'received') {
                        $add_unique($pending_received_map, 'rel:' . $id, $base_item);
                    } else {
                        $add_unique($pending_sent_map, 'rel:' . $id, $base_item);
                    }
                    continue;
                }

                if ($state === 'active' && in_array($rel_type, [PL_Relationships::TYPE_FOLLOW, PL_Relationships::TYPE_FRIEND], true)) {
                    $add_unique($active_projects_map, 'rel:' . $id, $base_item + [
                        'group' => 'community',
                    ]);
                    continue;
                }

                if ($rel_type === PL_Relationships::TYPE_SUBSCRIBE) {
                    $item = $base_item + [
                        'group' => 'membership',
                    ];
                    if ($state === 'active') {
                        $add_unique($memberships_map, 'sub:' . $id, $item);
                    } else {
                        $add_unique($history_map, 'sub:' . $id, $item);
                    }
                    continue;
                }

                if ($state !== 'active') {
                    $add_unique($history_map, 'rel:' . $id, $base_item);
                }
            }
        }

        $partnerships_table = $wpdb->prefix . 'politeia_user_object_partnerships';
        $partnerships_exists = ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $partnerships_table)) === $partnerships_table);
        if ($partnerships_exists) {
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT id, object_type, object_id, owner_user_id, partner_user_id, invitee_email, role, status, invitation_token_hash, invited_at, expires_at, accepted_at, declined_at, revoked_at, created_at, updated_at
                     FROM {$partnerships_table}
                     WHERE owner_user_id = %d
                        OR partner_user_id = %d
                        OR LOWER(invitee_email) = %s
                     ORDER BY id DESC",
                    $logged_in_user_id,
                    $logged_in_user_id,
                    $current_email
                ),
                ARRAY_A
            );

            foreach ((array) $rows as $row) {
                $id = (int) ($row['id'] ?? 0);
                $object_type = sanitize_key((string) ($row['object_type'] ?? ''));
                $object_id = (int) ($row['object_id'] ?? 0);
                $role = sanitize_key((string) ($row['role'] ?? 'observer'));
                $status = sanitize_key((string) ($row['status'] ?? ''));
                $expires_at = (string) ($row['expires_at'] ?? '');
                $state = $normalize_state($status, $expires_at);
                $owner_id = (int) ($row['owner_user_id'] ?? 0);
                $partner_id = (int) ($row['partner_user_id'] ?? 0);
                $invitee_email = strtolower(trim((string) ($row['invitee_email'] ?? '')));
                $direction = ($partner_id === (int) $logged_in_user_id) ? 'received' : 'sent';
                $subject_user_id = $partner_id > 0 ? $partner_id : $owner_id;
                $subject_user = $user_payload($subject_user_id, __('Someone', 'politeia-learning'));

                if ($object_type === 'course') {
                    $object_title = $course_title_for($object_id);
                    $object_url = $object_id > 0 ? (string) get_permalink($object_id) : '';
                } elseif ($object_type === 'reading_plan') {
                    $object_title = $plan_title_for($object_id);
                    $object_url = $object_id > 0 ? (string) home_url('/my-plan/' . max(0, $object_id) . '/') : '';
                } else {
                    $object_title = $object_type !== '' ? ucfirst($object_type) : __('Connection', 'politeia-learning');
                    $object_url = '';
                }

                $base_item = [
                    'id' => $id,
                    'kind' => 'partnership',
                    'group' => 'project',
                    'source' => 'partnerships',
                    'object_type' => $object_type,
                    'object_id' => $object_id,
                    'object' => [
                        'type' => $object_type,
                        'id' => $object_id,
                        'title' => $object_title,
                        'url' => $object_url,
                    ],
                    'role' => $role,
                    'status' => $state,
                    'state_label' => $state_label_for($state),
                    'direction' => $direction,
                    'user' => $subject_user,
                    'created_at' => (string) ($row['created_at'] ?? ''),
                    'expires_at' => $expires_at,
                    'owner_user_id' => $owner_id,
                    'partner_user_id' => $partner_id,
                    'invitee_email' => $invitee_email,
                ];

                if ($state === 'pending') {
                    if ($direction === 'received') {
                        $add_unique($pending_received_map, 'part:' . $id, $base_item);
                    } else {
                        $add_unique($pending_sent_map, 'part:' . $id, $base_item);
                    }
                    continue;
                }

                if ($state === 'active') {
                    $add_unique($active_projects_map, 'part:' . $id, $base_item);
                    continue;
                }

                $add_unique($history_map, 'part:' . $id, $base_item);
            }
        }

        $legacy_invites_table = $wpdb->prefix . 'politeia_plan_participant_invites';
        $legacy_invites_exists = ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $legacy_invites_table)) === $legacy_invites_table);
        if ($legacy_invites_exists) {
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT id, plan_id, object_type, object_id, inviter_user_id, invitee_email, invitee_user_id, role, notify_on, status, token_hash, expires_at, accepted_at, declined_at, revoked_at, created_at, updated_at
                     FROM {$legacy_invites_table}
                     WHERE inviter_user_id = %d
                        OR LOWER(invitee_email) = %s
                     ORDER BY id DESC",
                    $logged_in_user_id,
                    $current_email
                ),
                ARRAY_A
            );

            foreach ((array) $rows as $row) {
                $id = (int) ($row['id'] ?? 0);
                $plan_id = (int) ($row['plan_id'] ?? ($row['object_id'] ?? 0));
                $object_type = sanitize_key((string) ($row['object_type'] ?? 'reading_plan'));
                $role = sanitize_key((string) ($row['role'] ?? 'observer'));
                $status = sanitize_key((string) ($row['status'] ?? ''));
                $expires_at = (string) ($row['expires_at'] ?? '');
                $state = $normalize_state($status, $expires_at);
                $invitee_user_id = (int) ($row['invitee_user_id'] ?? 0);
                $inviter_user_id = (int) ($row['inviter_user_id'] ?? 0);
                $direction = $inviter_user_id === (int) $logged_in_user_id ? 'sent' : 'received';
                $subject_user = $user_payload($direction === 'sent' ? $invitee_user_id : $inviter_user_id, __('Someone', 'politeia-learning'));
                $plan_title = $plan_title_for($plan_id);
                $plan_url = $plan_id > 0 ? (string) home_url('/my-plan/' . max(0, $plan_id) . '/') : '';

                $base_item = [
                    'id' => $id,
                    'kind' => 'reading_plan_invite',
                    'group' => 'project',
                    'source' => 'legacy',
                    'object_type' => $object_type,
                    'object_id' => $plan_id,
                    'object' => [
                        'type' => $object_type,
                        'id' => $plan_id,
                        'title' => $plan_title,
                        'url' => $plan_url,
                    ],
                    'role' => $role,
                    'status' => $state,
                    'state_label' => $state_label_for($state),
                    'direction' => $direction,
                    'user' => $subject_user,
                    'created_at' => (string) ($row['created_at'] ?? ''),
                    'expires_at' => $expires_at,
                    'notify_on' => (string) ($row['notify_on'] ?? 'none'),
                ];

                if ($state === 'pending') {
                    if ($direction === 'received') {
                        $add_unique($pending_received_map, 'planinv:' . $id, $base_item);
                    } else {
                        $add_unique($pending_sent_map, 'planinv:' . $id, $base_item);
                    }
                    continue;
                }

                if ($state === 'active') {
                    $add_unique($active_projects_map, 'planinv:' . $id, $base_item);
                    continue;
                }

                $add_unique($history_map, 'planinv:' . $id, $base_item);
            }
        }

        $plan_participants_table = $wpdb->prefix . 'politeia_plan_participants';
        $plans_table = $wpdb->prefix . 'politeia_plans';
        $plan_participants_exists = ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $plan_participants_table)) === $plan_participants_table);
        $plans_exists = ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $plans_table)) === $plans_table);
        if ($plan_participants_exists && $plans_exists) {
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT pp.plan_id, pp.user_id, pp.role, pp.notify_on, pp.added_by_user_id, pp.added_at, pp.revoked_at, p.user_id AS owner_user_id, p.name AS plan_name, p.status AS plan_status
                     FROM {$plan_participants_table} pp
                     INNER JOIN {$plans_table} p ON p.id = pp.plan_id
                     WHERE pp.user_id = %d OR p.user_id = %d
                     ORDER BY pp.plan_id DESC, pp.user_id DESC",
                    $logged_in_user_id,
                    $logged_in_user_id
                ),
                ARRAY_A
            );

            foreach ((array) $rows as $row) {
                $plan_id = (int) ($row['plan_id'] ?? 0);
                $participant_id = (int) ($row['user_id'] ?? 0);
                $owner_user_id = (int) ($row['owner_user_id'] ?? 0);
                $revoked_at = (string) ($row['revoked_at'] ?? '');
                $state = $normalize_state($revoked_at !== '' ? 'revoked' : 'active', $revoked_at);
                $direction = $participant_id === (int) $logged_in_user_id ? 'received' : 'sent';
                $other_user = $user_payload($direction === 'received' ? $owner_user_id : $participant_id, __('Someone', 'politeia-learning'));
                $plan_title = (string) ($row['plan_name'] ?? '');
                if ($plan_title === '') {
                    $plan_title = $plan_title_for($plan_id);
                }

                $base_item = [
                    'id' => $plan_id . ':' . $participant_id,
                    'kind' => 'plan_participant',
                    'group' => 'project',
                    'object_type' => 'reading_plan',
                    'object_id' => $plan_id,
                    'object' => [
                        'type' => 'reading_plan',
                        'id' => $plan_id,
                        'title' => $plan_title,
                        'url' => $plan_id > 0 ? (string) home_url('/my-plan/' . max(0, $plan_id) . '/') : '',
                    ],
                    'role' => sanitize_key((string) ($row['role'] ?? 'observer')),
                    'status' => $state,
                    'state_label' => $state_label_for($state),
                    'direction' => $direction,
                    'user' => $other_user,
                    'created_at' => (string) ($row['added_at'] ?? ''),
                    'expires_at' => $revoked_at,
                    'notify_on' => (string) ($row['notify_on'] ?? 'none'),
                ];

                if ($state === 'active') {
                    $add_unique($active_projects_map, 'planpart:' . $plan_id . ':' . $participant_id, $base_item);
                } else {
                    $add_unique($history_map, 'planpart:' . $plan_id . ':' . $participant_id, $base_item);
                }
            }
        }

        $subscriptions_table = $wpdb->prefix . 'politeia_subscriptions';
        $tiers_table = $wpdb->prefix . 'politeia_subscription_meta';
        $subscriptions_exists = ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $subscriptions_table)) === $subscriptions_table);
        $tiers_exists = ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $tiers_table)) === $tiers_table);
        if ($subscriptions_exists) {
            $select_tier = $tiers_exists
                ? ", t.tier_name, t.amount_minor, t.currency, t.interval_unit, t.interval_count"
                : "";
            $join_tier = $tiers_exists ? "LEFT JOIN {$tiers_table} t ON t.id = s.tier_id" : "";
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT s.id, s.creator_user_id, s.subscriber_user_id, s.tier_id, s.gateway, s.mp_preapproval_id, s.flow_subscription_id, s.status, s.current_period_end, s.cancel_at_period_end, s.cancelled_at, s.gateway_cancelled_at, s.cancellation_reason, s.created_at, s.updated_at {$select_tier}
                     FROM {$subscriptions_table} s
                     {$join_tier}
                     WHERE s.creator_user_id = %d OR s.subscriber_user_id = %d
                     ORDER BY s.created_at DESC",
                    $logged_in_user_id,
                    $logged_in_user_id
                ),
                ARRAY_A
            );

            foreach ((array) $rows as $row) {
                $id = (int) ($row['id'] ?? 0);
                $creator_user_id = (int) ($row['creator_user_id'] ?? 0);
                $subscriber_user_id = (int) ($row['subscriber_user_id'] ?? 0);
                $state = $normalize_state((string) ($row['status'] ?? 'pending'), (string) ($row['current_period_end'] ?? ''));
                $direction = $subscriber_user_id === (int) $logged_in_user_id ? 'outgoing' : 'incoming';
                $other_user = $user_payload($direction === 'outgoing' ? $creator_user_id : $subscriber_user_id, __('Someone', 'politeia-learning'));
                $tier_name = (string) ($row['tier_name'] ?? '');
                if ($tier_name === '') {
                    $tier_name = sprintf('Tier #%d', (int) ($row['tier_id'] ?? 0));
                }

                $base_item = [
                    'id' => $id,
                    'kind' => 'subscription',
                    'group' => 'membership',
                    'status' => $state,
                    'state_label' => $state_label_for($state),
                    'direction' => $direction,
                    'user' => $other_user,
                    'title' => $tier_name,
                    'subtitle' => $direction === 'outgoing' ? __('Tu suscripción', 'politeia-learning') : __('Te suscriben', 'politeia-learning'),
                    'created_at' => (string) ($row['created_at'] ?? ''),
                    'expires_at' => (string) ($row['current_period_end'] ?? ''),
                    'gateway' => (string) ($row['gateway'] ?? 'mercadopago'),
                    'mp_preapproval_id' => (string) ($row['mp_preapproval_id'] ?? ''),
                    'flow_subscription_id' => (string) ($row['flow_subscription_id'] ?? ''),
                    'cancel_at_period_end' => !empty($row['cancel_at_period_end']),
                    'cancelled_at' => (string) ($row['cancelled_at'] ?? ''),
                    'gateway_cancelled_at' => (string) ($row['gateway_cancelled_at'] ?? ''),
                    'cancellation_reason' => (string) ($row['cancellation_reason'] ?? ''),
                ];

                if ($state === 'active') {
                    $add_unique($memberships_map, 'sub:' . $id, $base_item);
                } else {
                    $add_unique($history_map, 'sub:' . $id, $base_item);
                }
            }
        }

        $enrollments_table = $wpdb->prefix . 'learni_enrollments';
        $enrollments_exists = ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $enrollments_table)) === $enrollments_table);
        if ($enrollments_exists) {
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT e.id, e.course_post_id, e.status, e.source, e.payment_provider, e.payment_reference, e.payment_amount, e.payment_currency, e.started_at, e.expires_at, p.post_title
                     FROM {$enrollments_table} e
                     LEFT JOIN {$wpdb->posts} p ON p.ID = e.course_post_id
                     WHERE e.user_id = %d
                     ORDER BY e.created_at DESC",
                    $logged_in_user_id
                ),
                ARRAY_A
            );

            foreach ((array) $rows as $row) {
                $id = (int) ($row['id'] ?? 0);
                $course_id = (int) ($row['course_post_id'] ?? 0);
                $state = $normalize_state((string) ($row['status'] ?? 'active'), (string) ($row['expires_at'] ?? ''));
                $course_title = (string) ($row['post_title'] ?? '');
                if ($course_title === '') {
                    $course_title = $course_title_for($course_id);
                }
                $base_item = [
                    'id' => $id,
                    'kind' => 'course_enrollment',
                    'group' => 'project',
                    'status' => $state,
                    'state_label' => $state_label_for($state),
                    'direction' => 'owned',
                    'title' => $course_title,
                    'subtitle' => (string) ($row['source'] ?? ''),
                    'created_at' => (string) ($row['started_at'] ?? ''),
                    'expires_at' => (string) ($row['expires_at'] ?? ''),
                    'course_id' => $course_id,
                    'payment_provider' => (string) ($row['payment_provider'] ?? ''),
                    'payment_reference' => (string) ($row['payment_reference'] ?? ''),
                ];

                if ($state === 'active') {
                    $add_unique($active_projects_map, 'enr:' . $id, $base_item);
                } else {
                    $add_unique($history_map, 'enr:' . $id, $base_item);
                }
            }
        }

        $pl_connections_data['pending_received'] = array_values($pending_received_map);
        $pl_connections_data['pending_sent'] = array_values($pending_sent_map);
        $pl_connections_data['active_projects'] = array_values($active_projects_map);
        $pl_connections_data['memberships'] = array_values($memberships_map);
        $pl_connections_data['history'] = array_values($history_map);
        $pl_connections_data['summary'] = [
            'pending_received' => count($pl_connections_data['pending_received']),
            'pending_sent' => count($pl_connections_data['pending_sent']),
            'active_projects' => count($pl_connections_data['active_projects']),
            'memberships' => count($pl_connections_data['memberships']),
            'history' => count($pl_connections_data['history']),
        ];
    }
}

$is_notifications_view = false;
$is_friends_view = false;

$pl_subscribe_error_code = isset($_GET['pl_subscribe_error']) ? sanitize_key((string) wp_unslash($_GET['pl_subscribe_error'])) : '';
$pl_subscribe_error_message = '';
if ($pl_subscribe_error_code !== '') {
    $pl_subscribe_error_message = __('No se pudo iniciar la suscripción. Revisa el registro (debug.log) para más detalles.', 'politeia-learning');
    if ($pl_subscribe_error_code === 'tier_not_found') {
        $pl_subscribe_error_message = __('Este creador aún no tiene membresía mensual configurada.', 'politeia-learning');
    } elseif ($pl_subscribe_error_code === 'mp_policy_blocked') {
        $pl_subscribe_error_message = __('Mercado Pago (sandbox) bloqueó la creación del plan de suscripción (PolicyAgent 403). En MLC esto puede pasar por políticas del sandbox. Solución: probar en LIVE o cambiar a Direct.', 'politeia-learning');
    } elseif ($pl_subscribe_error_code === 'mp_back_url_required') {
        $pl_subscribe_error_message = __('Mercado Pago exige "back_url" para crear el plan de suscripción. Configura "Success URL" en Pagos o reintenta (el sistema usa Home por defecto).', 'politeia-learning');
    } elseif ($pl_subscribe_error_code === 'mp_card_token_required') {
        $pl_subscribe_error_message = __('Mercado Pago exige "card_token_id" para crear la suscripción en este flujo. Cambia Subscription Flow a Direct (tokenización de tarjeta) o usa credenciales/flujo que habiliten checkout hosted.', 'politeia-learning');
    } elseif ($pl_subscribe_error_code === 'mp_payer_collector_mismatch') {
        $pl_subscribe_error_message = __('Mercado Pago exige que payer y collector sean ambos usuarios reales o ambos de prueba. Para Hosted, inicia sesión en el checkout con un comprador de prueba (o usa Direct).', 'politeia-learning');
    } elseif ($pl_subscribe_error_code === 'mp_payer_email_required') {
        $pl_subscribe_error_message = __('Mercado Pago exige "payer_email" para crear la suscripción. Configura "Payer Email Override" (si estás usando compradores de prueba) o asegúrate que el usuario tenga email válido.', 'politeia-learning');
    } elseif ($pl_subscribe_error_code === 'mp_missing_sandbox_init_point') {
        $pl_subscribe_error_message = __('Mercado Pago no entregó URL de sandbox para el checkout. En MLC el sandbox puede fallar; prueba en LIVE o usa Direct.', 'politeia-learning');
    } elseif ($pl_subscribe_error_code === 'collector_mismatch') {
        $pl_subscribe_error_message = __('Las credenciales de Mercado Pago no corresponden al seller esperado. Revisa los tokens/configuración.', 'politeia-learning');
    } elseif ($pl_subscribe_error_code === 'mp_api_error') {
        $pl_subscribe_error_message = __('Mercado Pago rechazó la solicitud o el sandbox no respondió. Revisa debug.log.', 'politeia-learning');
    }
}

$server_view = $is_notifications_view ? 'notifications' : ($is_friends_view ? 'friends' : '');
$initial_tab = $server_view !== '' ? $server_view : 'main';
$initial_label = $server_view === 'notifications' ? 'Notifications' : ($server_view === 'friends' ? 'Friends' : 'Main');
$initial_connections_view = 'pending';

// Allow forcing an initial tab via ?tab=requests or ?tab=connections.
$requested_tab = isset($_GET['tab']) ? sanitize_key((string) wp_unslash($_GET['tab'])) : '';
if ($requested_tab === 'requests') {
    $requested_tab = 'connections';
}

$requested_connections_view = isset($_GET['connections_view']) ? sanitize_key((string) wp_unslash($_GET['connections_view'])) : '';
if (in_array($requested_connections_view, ['pending', 'projects', 'community', 'memberships', 'history'], true)) {
    $initial_connections_view = $requested_connections_view;
}
if ($requested_tab !== '' && in_array($requested_tab, $pl_allowed_tabs, true)) {
    $initial_tab = $requested_tab;
    $initial_label = ucfirst($requested_tab);
}
