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
        $pl_allowed_tabs = ['main', 'courses', 'writings', 'specializations', 'thoughts', 'plans', 'book', 'requests'];
    } else {
        $policy_kind = in_array($pl_access_level, [PL_Relationships::TYPE_FOLLOW, PL_Relationships::TYPE_FRIEND, PL_Relationships::TYPE_SUBSCRIBE], true)
            ? $pl_access_level
            : 'public';
        $policy = PL_Relationships::get_owner_policy((int) $user_id, $policy_kind);
        $pl_allowed_tabs = isset($policy['profile_tabs']) && is_array($policy['profile_tabs']) ? $policy['profile_tabs'] : ['main'];
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

// Allow forcing an initial tab via ?tab=requests (used after responding to a request).
$requested_tab = isset($_GET['tab']) ? sanitize_key((string) wp_unslash($_GET['tab'])) : '';
if ($requested_tab !== '' && in_array($requested_tab, $pl_allowed_tabs, true)) {
    $initial_tab = $requested_tab;
    $initial_label = ucfirst($requested_tab);
}
