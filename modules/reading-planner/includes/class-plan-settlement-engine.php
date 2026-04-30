<?php
namespace Politeia\ReadingPlanner;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Engine to settle past sessions into final states.
 * 
 * Implements "Freeze the Past" logic (v1.0 Compliance):
 * - Past sessions (date < today user_tz) must be immutable.
 * - 'planned' sessions in the past are resolved to 'missed', 'partial', or 'accomplished'.
 * - Compares actual reading vs "Ideal Baseline" (expected pages).
 */
class PlanSettlementEngine
{
    /**
     * Settle past sessions for a given plan.
     *
     * @param int $plan_id The Plan ID to settle.
     * @param int $user_id The User ID strictly for permission/context check.
     * @return void
     */
    public static function settle(int $plan_id, int $user_id): void
    {
        global $wpdb;
        $plans_tbl = $wpdb->prefix . 'politeia_plans';
        $goals_tbl = $wpdb->prefix . 'politeia_plan_goals';

        $sessions_tbl = $wpdb->prefix . 'politeia_planned_sessions';
        $reading_tbl = $wpdb->prefix . 'politeia_reading_sessions';

        // 1. Fetch Plan Context & Goal Targets
        $finish_book_tbl = $wpdb->prefix . 'politeia_plan_finish_book';
        $books_tbl = $wpdb->prefix . 'politeia_books';
        $user_books_tbl = $wpdb->prefix . 'politeia_user_books';

        $owner_user_id = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT user_id FROM {$plans_tbl} WHERE id = %d LIMIT 1",
                $plan_id
            )
        );

        if ($owner_user_id <= 0 || $owner_user_id !== $user_id) {
            return;
        }

        $plan_info = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT p.user_id, pfb.user_book_id, ub.book_id as canonical_book_id, pfb.start_page, b.pages as book_total_pages
				 FROM {$plans_tbl} p
				 INNER JOIN {$finish_book_tbl} pfb ON pfb.plan_id = p.id
                 INNER JOIN {$user_books_tbl} ub ON ub.id = pfb.user_book_id
                 LEFT JOIN {$books_tbl} b ON b.id = ub.book_id
				 WHERE p.id = %d
				 LIMIT 1",
                $plan_id
            )
        );

        $user_book_id = 0;
        $starting_page = 1;
        $total_target = 0;

        if ($plan_info && (int) $plan_info->user_id === $user_id) {
            $user_book_id = (int) $plan_info->user_book_id;
            $starting_page = (int) $plan_info->start_page;
            if ($starting_page < 1) {
                $starting_page = 1;
            }
            $total_target = (int) $plan_info->book_total_pages;
        } else {
            $legacy_goal = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT goal_kind, book_id, target_value, starting_page
                     FROM {$goals_tbl}
                     WHERE plan_id = %d
                     ORDER BY (book_id IS NULL), id ASC
                     LIMIT 1",
                    $plan_id
                )
            );

            if (!$legacy_goal) {
                return;
            }

            $goal_book_id = !empty($legacy_goal->book_id) ? (int) $legacy_goal->book_id : 0;
            $starting_page = !empty($legacy_goal->starting_page) ? (int) $legacy_goal->starting_page : 1;
            if ($starting_page < 1) {
                $starting_page = 1;
            }
            $total_target = !empty($legacy_goal->target_value) ? (int) $legacy_goal->target_value : 0;

            if ($goal_book_id > 0) {
                if (function_exists('prs_ensure_user_book')) {
                    $user_book_id = (int) prs_ensure_user_book($user_id, $goal_book_id);
                }

                $book_pages = (int) $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT pages FROM {$books_tbl} WHERE id = %d LIMIT 1",
                        $goal_book_id
                    )
                );

                if ($book_pages > 0) {
                    $total_target = $book_pages;
                }
            }
        }

        if ($total_target <= 0) {
            $total_target = 0;
        }

        // 2. Determine User Timezone & Cutoff
        // Try user meta first, fallback to WP timezone
        $user_tz_str = get_user_meta($user_id, 'timezone', true);
        if (!$user_tz_str) {
            $timezone = wp_timezone();
        } else {
            try {
                $timezone = new \DateTimeZone($user_tz_str);
            } catch (\Exception $e) {
                $timezone = wp_timezone();
            }
        }

        $now_user = new \DateTimeImmutable('now', $timezone);
        $today_str = $now_user->format('Y-m-d'); // Session dates are Y-m-d

        // 3. Fetch Stale Sessions (Planned < Today)
        // planned_start_datetime is stored as DATETIME; we want "date < today" without calling DATE() (index-friendly).
        $cutoff_dt = $today_str . ' 00:00:00';
        $stale_sessions = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, planned_start_datetime
				 FROM {$sessions_tbl}
				 WHERE plan_id = %d
				   AND status = 'planned'
				   AND planned_start_datetime < %s
                 ORDER BY planned_start_datetime ASC",
                $plan_id,
                $cutoff_dt
            )
        );

        if (empty($stale_sessions)) {
            return;
        }

        // 4. Calculate "Ideal Baseline" (Expected Pages)
        // We need ALL session dates to distribute the Goal Target evenly as if perfect.
        // Result: A map of 'YYYY-MM-DD' => expected_pages_count
        $all_session_dates = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT DATE_FORMAT(planned_start_datetime, '%%Y-%%m-%%d') 
				 FROM {$sessions_tbl} 
				 WHERE plan_id = %d 
				 ORDER BY planned_start_datetime ASC",
                $plan_id
            )
        );

        $expected_map = array();
        if (class_exists('\\Politeia\\ReadingPlanner\\PlanSessionDeriver') && !empty($all_session_dates)) {
            // Simulate from beginning of time (1970) to catch all dates
            $ideal_projections = \Politeia\ReadingPlanner\PlanSessionDeriver::derive_sessions(
                $total_target,
                $starting_page,
                0, // 0 pages read (Perfect Plan assumes start from scratch)
                $all_session_dates,
                '1970-01-01' // Process all dates
            );

            foreach ($ideal_projections as $p) {
                $expected_map[$p['date']] = (int) $p['pages'];
            }
        }

        // Preload reading logs once for the stale date range to avoid per-session queries.
        $pages_read_map = array();
        if ($user_book_id > 0 && !empty($stale_sessions)) {
            $date_keys = array();
            foreach ($stale_sessions as $session) {
                $date_keys[] = substr((string) $session->planned_start_datetime, 0, 10);
            }
            $date_keys = array_values(array_unique($date_keys));
            sort($date_keys);

            $first_date = $date_keys[0] ?? '';
            $last_date = $date_keys[count($date_keys) - 1] ?? '';
            if ($first_date !== '' && $last_date !== '') {
                $day_start = date_create($first_date . ' 00:00:00', $timezone);
                $day_end = date_create($last_date . ' 23:59:59', $timezone);
                if ($day_start && $day_end) {
                    $gmt_start = $day_start->setTimezone(new \DateTimeZone('GMT'))->format('Y-m-d H:i:s');
                    $gmt_end = $day_end->setTimezone(new \DateTimeZone('GMT'))->format('Y-m-d H:i:s');

                    $logs = $wpdb->get_results(
                        $wpdb->prepare(
                            "SELECT start_time, start_page, end_page
                             FROM {$reading_tbl}
                             WHERE user_id = %d
                               AND user_book_id = %d
                               AND deleted_at IS NULL
                               AND start_time BETWEEN %s AND %s",
                            $user_id,
                            $user_book_id,
                            $gmt_start,
                            $gmt_end
                        )
                    );

                    foreach ($logs as $log) {
                        $start_time = isset($log->start_time) ? (string) $log->start_time : '';
                        $dt_gmt = $start_time !== '' ? \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $start_time, new \DateTimeZone('GMT')) : null;
                        if (!$dt_gmt) {
                            continue;
                        }

                        $date_key = $dt_gmt->setTimezone($timezone)->format('Y-m-d');
                        $start_page = isset($log->start_page) ? (int) $log->start_page : 0;
                        $end_page = isset($log->end_page) ? (int) $log->end_page : 0;
                        if ($end_page < $start_page) {
                            continue;
                        }

                        $pages_read_map[$date_key] = (int) ($pages_read_map[$date_key] ?? 0) + ($end_page - $start_page + 1);
                    }
                }
            }
        }

        // 5. Process Each Stale Session
        foreach ($stale_sessions as $session) {
            $date_key = substr($session->planned_start_datetime, 0, 10);

            // Default: missed (if no book or generic)
            $new_status = 'missed';

            if ($user_book_id > 0) {
                $pages_read_today = (int) ($pages_read_map[$date_key] ?? 0);

                // Compare vs Expected
                $expected = isset($expected_map[$date_key]) ? $expected_map[$date_key] : 0;

                if ($pages_read_today <= 0) {
                    $new_status = 'missed';
                } elseif ($pages_read_today < $expected) {
                    $new_status = 'partial';
                } else {
                    $new_status = 'accomplished';
                }
            }

            // 6. Update Status
            $wpdb->update(
                $sessions_tbl,
                array('status' => $new_status),
                array('id' => $session->id),
                array('%s'),
                array('%d')
            );
        }
    }
}
