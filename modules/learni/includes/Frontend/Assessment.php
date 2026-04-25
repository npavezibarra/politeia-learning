<?php
/**
 * Frontend Assessment logic for Learni.
 */

if (!defined('ABSPATH')) {
    exit;
}

final class PL_Learni_Frontend_Assessment
{
    private const RESTART_CYCLE_META_PREFIX = 'learni_binomial_cycle_after_attempt_';

    private static function restart_cycle_meta_key(int $course_id, int $quiz_id): string
    {
        return self::RESTART_CYCLE_META_PREFIX . $course_id . '_' . $quiz_id;
    }

    private static function restart_cycle_cutoff_attempt_id(int $user_id, int $course_id, int $quiz_id): int
    {
        if ($user_id <= 0 || $course_id <= 0 || $quiz_id <= 0) {
            return 0;
        }
        return (int) get_user_meta($user_id, self::restart_cycle_meta_key($course_id, $quiz_id), true);
    }

    private static function parse_mysql_timestamp(string $submitted_at): int
    {
        $submitted_at = trim($submitted_at);
        if ($submitted_at === '') {
            return 0;
        }
        $dt = date_create_immutable_from_format('Y-m-d H:i:s', $submitted_at, wp_timezone());
        if ($dt instanceof \DateTimeImmutable) {
            return (int) $dt->getTimestamp();
        }
        return (int) strtotime($submitted_at);
    }

    public static function binomial_course_state(int $course_id, int $user_id, int $lesson_percent): array
    {
        if ($course_id <= 0 || $user_id <= 0 || !class_exists('\\Learni\\Database\\Progress')) {
            return [
                'quizId' => 0,
                'needsInitial' => false,
                'needsFinal' => false,
                'canTakeFinal' => false,
                'initial' => null,
                'final' => null,
            ];
        }

        global $wpdb;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, lesson_post_id, settings_json
                 FROM {$wpdb->prefix}learni_quizzes
                 WHERE course_post_id = %d
                 ORDER BY id DESC",
                $course_id
            ),
            ARRAY_A
        );

        $quiz_id = 0;
        $fallback_id = 0;
        $restart_cooldown_days = 0;
        foreach ($rows as $row) {
            $settings = [];
            if (!empty($row['settings_json'])) {
                $decoded = json_decode((string) $row['settings_json'], true);
                if (is_array($decoded)) {
                    $settings = $decoded;
                }
            }
            if (isset($settings['role']) && (string) $settings['role'] === 'binomial') {
                $quiz_id = (int) ($row['id'] ?? 0);
                $restart_cooldown_days = max(0, (int) ($settings['restartCooldownDays'] ?? 0));
                break;
            }
            if ($fallback_id <= 0 && empty($row['lesson_post_id'])) {
                $fallback_id = (int) ($row['id'] ?? 0);
            }
        }
        if ($quiz_id <= 0 && $fallback_id > 0) {
            $quiz_id = $fallback_id;
        }

        if ($quiz_id <= 0) {
            return [
                'quizId' => 0,
                'needsInitial' => false,
                'needsFinal' => false,
                'canTakeFinal' => false,
                'initial' => null,
                'final' => null,
            ];
        }

        $restart_cooldown_days = isset($restart_cooldown_days) ? (int) $restart_cooldown_days : 0;
        $cutoff_attempt_id = self::restart_cycle_cutoff_attempt_id($user_id, $course_id, $quiz_id);

        $attempts_table = $wpdb->prefix . 'learni_quiz_attempts';
        if ($cutoff_attempt_id > 0) {
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT id, score, submitted_at, answers_json
                     FROM {$attempts_table}
                     WHERE quiz_id = %d AND user_id = %d AND status = %s AND id > %d
                     ORDER BY submitted_at ASC, id ASC
                     LIMIT 200",
                    $quiz_id,
                    $user_id,
                    'submitted',
                    $cutoff_attempt_id
                ),
                ARRAY_A
            );
        } else {
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT id, score, submitted_at, answers_json
                     FROM {$attempts_table}
                     WHERE quiz_id = %d AND user_id = %d AND status = %s
                     ORDER BY submitted_at ASC, id ASC
                     LIMIT 200",
                    $quiz_id,
                    $user_id,
                    'submitted'
                ),
                ARRAY_A
            );
        }

        $series = [];
        $idx = 0;
        foreach ((array) $rows as $r) {
            if (!is_array($r)) {
                continue;
            }
            $idx++;
            $payload = [];
            if (!empty($r['answers_json'])) {
                $decoded = json_decode((string) $r['answers_json'], true);
                if (is_array($decoded)) {
                    $payload = $decoded;
                }
            }
            $phase = '';
            if (isset($payload['phase']) && is_string($payload['phase'])) {
                $p = sanitize_key($payload['phase']);
                if (in_array($p, ['initial', 'final'], true)) {
                    $phase = $p;
                }
            }
            if ($phase === '') {
                $phase = ($idx % 2 === 1) ? 'initial' : 'final';
            }
            $a = self::attempt_public_payload($r);
            $a['phase'] = $phase;
            $series[] = $a;
        }

        $submitted_count = count($series);

        $initial = null;
        $final = null;
        $finals_after_initial = [];
        foreach ($series as $a) {
            $p = isset($a['phase']) ? (string) $a['phase'] : '';
            if ($p === 'initial') {
                $initial = $a;
                $finals_after_initial = [];
                continue;
            }
            if ($p === 'final' && is_array($initial)) {
                $finals_after_initial[] = $a;
            }
        }
        if (!empty($finals_after_initial)) {
            $final = $finals_after_initial[count($finals_after_initial) - 1];
        }

        $eligible_final = false;
        $final_failed = false;
        $cooldown_until = '';
        $cooldown_days_remaining = 0;
        $restart_cooldown_until = '';
        $restart_cooldown_days_remaining = 0;

        $baseline = is_array($initial) ? (int) ($initial['percent'] ?? 0) : null;
        if ($baseline !== null) {
            foreach ($finals_after_initial as $f) {
                $fp = (int) ($f['percent'] ?? 0);
                if ($fp >= $baseline) {
                    $eligible_final = true;
                    break;
                }
            }
        }

        if (!$eligible_final && is_array($final) && $baseline !== null) {
            $fp = (int) ($final['percent'] ?? 0);
            if ($fp < $baseline) {
                $final_failed = true;
                $submitted_at = (string) ($final['submittedAt'] ?? '');
                $ts = self::parse_mysql_timestamp($submitted_at);
                if ($ts > 0) {
                    $cool_ts = $ts + (7 * DAY_IN_SECONDS);
                    $cooldown_until = wp_date('Y-m-d H:i:s', $cool_ts, wp_timezone());
                    $now = (int) current_time('timestamp');
                    $diff = $cool_ts - $now;
                    if ($diff > 0) {
                        $days_since = intdiv(max(0, $now - $ts), DAY_IN_SECONDS);
                        $cooldown_days_remaining = (int) max(0, 7 - $days_since);
                    }
                }
            }
        }

        $can_restart_now = $eligible_final;
        if ($eligible_final && is_array($final) && $restart_cooldown_days > 0) {
            $ts = self::parse_mysql_timestamp((string) ($final['submittedAt'] ?? ''));
            if ($ts > 0) {
                $cool_ts = $ts + ($restart_cooldown_days * DAY_IN_SECONDS);
                $restart_cooldown_until = wp_date('Y-m-d H:i:s', $cool_ts, wp_timezone());
                $now = (int) current_time('timestamp');
                $diff = $cool_ts - $now;
                if ($diff > 0) {
                    $days_since = intdiv(max(0, $now - $ts), DAY_IN_SECONDS);
                    $restart_cooldown_days_remaining = (int) max(0, $restart_cooldown_days - $days_since);
                    $can_restart_now = false;
                }
            }
        }

        $needs_initial = !is_array($initial);
        $needs_final = is_array($initial) && !$eligible_final;
        $has_access = class_exists('\\Learni\\Access\\Access') && \Learni\Access\Access::user_can_access_course($user_id, $course_id);
        $can_take_final = $needs_final && $lesson_percent >= 100 && $has_access && $cooldown_days_remaining <= 0;

        return [
            'quizId' => $quiz_id,
            'submittedCount' => $submitted_count,
            'needsInitial' => $needs_initial,
            'needsFinal' => $needs_final,
            'canTakeFinal' => $can_take_final,
            'canRestart' => $eligible_final && $has_access && $restart_cooldown_days_remaining <= 0 && $can_restart_now,
            'initial' => $initial,
            'final' => $final,
            'eligibleFinal' => $eligible_final,
            'finalFailed' => $final_failed,
            'cooldownUntil' => $cooldown_until,
            'cooldownDaysRemaining' => $cooldown_days_remaining,
            'restartCooldownUntil' => $restart_cooldown_until,
            'restartCooldownDaysRemaining' => $restart_cooldown_days_remaining,
        ];
    }

    public static function binomial_quiz_id_for_course(int $course_id): int
    {
        if ($course_id <= 0) {
            return 0;
        }

        global $wpdb;
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, settings_json
                 FROM {$wpdb->prefix}learni_quizzes
                 WHERE course_post_id = %d AND lesson_post_id = 0
                 ORDER BY id DESC",
                $course_id
            ),
            ARRAY_A
        );

        $quiz_id = 0;
        foreach ($rows as $row) {
            $settings = [];
            if (!empty($row['settings_json'])) {
                $decoded = json_decode((string) $row['settings_json'], true);
                if (is_array($decoded)) {
                    $settings = $decoded;
                }
            }
            if (isset($settings['role']) && (string) $settings['role'] === 'binomial') {
                $quiz_id = (int) ($row['id'] ?? 0);
                break;
            }
        }

        if ($quiz_id <= 0 && !empty($rows)) {
            $quiz_id = (int) ($rows[0]['id'] ?? 0);
        }

        return $quiz_id > 0 ? $quiz_id : 0;
    }

    public static function attempt_public_payload(array $row): array
    {
        $payload = [];
        if (!empty($row['answers_json'])) {
            $decoded = json_decode((string) $row['answers_json'], true);
            if (is_array($decoded)) {
                $payload = $decoded;
            }
        }

        return [
            'id' => (int) ($row['id'] ?? 0),
            'percent' => (int) ($row['score'] ?? 0),
            'submittedAt' => (string) ($row['submitted_at'] ?? ''),
            'phase' => isset($payload['phase']) ? sanitize_key($payload['phase']) : '',
        ];
    }
}
