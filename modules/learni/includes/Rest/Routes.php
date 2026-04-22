<?php

namespace Learni\Rest;

use Learni\Access\Access;
use Learni\Database\Progress;
use Learni\Database\Enrollments;
use Learni\PostTypes\Course;
use WP_Error;
use WP_REST_Request;

final class Routes
{
    public const REST_NAMESPACE = 'learni/v1';
    private const FINAL_RETRY_COOLDOWN_DAYS = 7;



    /**
     * Return the active (owner, partner) user ids for a course.
     *
     * Owner can be missing in the partnership row for legacy data; in that case we
     * infer it from the enrollments table (first non-partner_invite active enrollment).
     *
     * @return array{owner:int,partner:int}
     */
    public static function course_partner_users(int $course_id): array
    {
        $out = ['owner' => 0, 'partner' => 0];
        if ($course_id <= 0) {
            return $out;
        }

        if (!class_exists('PL_Partnerships_Repository') || !method_exists('PL_Partnerships_Repository', 'get_single_partner')) {
            return $out;
        }

        $partner = null;
        try {
            $partner = \PL_Partnerships_Repository::get_single_partner('course', $course_id, 'partner');
        } catch (\Throwable $e) {
            $partner = null;
        }

        if (!is_array($partner) || empty($partner['partner_user_id'])) {
            return $out;
        }

        $out['partner'] = (int) ($partner['partner_user_id'] ?? 0);
        $out['owner'] = (int) ($partner['owner_user_id'] ?? 0);

        if ($out['owner'] <= 0 && $out['partner'] > 0) {
            global $wpdb;
            if ($wpdb) {
                $enroll_table = $wpdb->prefix . 'learni_enrollments';
                $rows = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT user_id, source, payment_provider
                         FROM {$enroll_table}
                         WHERE course_post_id = %d AND status = %s
                         ORDER BY created_at ASC
                         LIMIT 10",
                        $course_id,
                        \Learni\Database\Enrollments::STATUS_ACTIVE
                    ),
                    ARRAY_A
                );
                foreach ((array) $rows as $r) {
                    if (!is_array($r)) {
                        continue;
                    }
                    $candidate_id = (int) ($r['user_id'] ?? 0);
                    if ($candidate_id <= 0) {
                        continue;
                    }
                    $src = (string) ($r['source'] ?? '');
                    $prov = (string) ($r['payment_provider'] ?? '');
                    $is_owner_row = ($src === \Learni\Database\Enrollments::SOURCE_WOOCOMMERCE)
                        || ($src === \Learni\Database\Enrollments::SOURCE_DIRECT)
                        || ($src === \Learni\Database\Enrollments::SOURCE_MANUAL && $prov !== 'partner_invite');
                    if ($is_owner_row) {
                        $out['owner'] = $candidate_id;
                        break;
                    }
                }
            }
        }

        return $out;
    }

    public static function register(): void
    {
        register_rest_route(
            self::REST_NAMESPACE,
            '/courses/(?P<id>\\d+)/binomial',
            [
                'methods' => 'GET',
                'permission_callback' => static function () {
                    return is_user_logged_in();
                },
                'callback' => [Binomial::class, 'get_course_status'],
            ]
        );

        register_rest_route(
            self::REST_NAMESPACE,
            '/courses/(?P<id>\\d+)/binomial/start',
            [
                'methods' => 'POST',
                'permission_callback' => static function () {
                    return is_user_logged_in();
                },
                'callback' => [Binomial::class, 'post_start'],
                'args' => [
                    'phase' => [
                        'type' => 'string',
                        'required' => true,
                        'sanitize_callback' => static function ($value) {
                            $value = is_string($value) ? sanitize_key($value) : '';
                            return in_array($value, ['initial', 'final'], true) ? $value : 'initial';
                        },
                    ],
                ],
            ]
        );

        // Cross evaluation ("Test Partner") handshake.
        register_rest_route(
            self::REST_NAMESPACE,
            '/courses/(?P<id>\\d+)/cross-eval/create',
            [
                'methods' => 'POST',
                'permission_callback' => static function () {
                    return is_user_logged_in();
                },
                'callback' => [CrossEval::class, 'post_create'],
            ]
        );

        register_rest_route(
            self::REST_NAMESPACE,
            '/cross-eval/pending',
            [
                'methods' => 'GET',
                'permission_callback' => static function () {
                    return is_user_logged_in();
                },
                'callback' => [CrossEval::class, 'get_pending'],
            ]
        );

        register_rest_route(
            self::REST_NAMESPACE,
            '/cross-eval/sessions/(?P<id>\\d+)',
            [
                'methods' => 'GET',
                'permission_callback' => static function () {
                    return is_user_logged_in();
                },
                'callback' => [CrossEval::class, 'get_session'],
            ]
        );

        register_rest_route(
            self::REST_NAMESPACE,
            '/cross-eval/sessions/(?P<id>\\d+)/respond',
            [
                'methods' => 'POST',
                'permission_callback' => static function () {
                    return is_user_logged_in();
                },
                'callback' => [CrossEval::class, 'post_respond'],
                'args' => [
                    'decision' => [
                        'type' => 'string',
                        'required' => true,
                        'sanitize_callback' => static function ($value) {
                            $value = is_string($value) ? sanitize_key($value) : '';
                            return in_array($value, ['accept', 'decline'], true) ? $value : 'decline';
                        },
                    ],
                ],
            ]
        );

        register_rest_route(
            self::REST_NAMESPACE,
            '/cross-eval/sessions/(?P<id>\\d+)/cancel',
            [
                'methods' => 'POST',
                'permission_callback' => static function () {
                    return is_user_logged_in();
                },
                'callback' => [CrossEval::class, 'post_cancel'],
            ]
        );

        register_rest_route(
            self::REST_NAMESPACE,
            '/courses/(?P<id>\\d+)/cross-eval/binomial/start',
            [
                'methods' => 'POST',
                'permission_callback' => static function () {
                    return is_user_logged_in();
                },
                'callback' => [CrossEval::class, 'post_binomial_start'],
                'args' => [
                    'sessionId' => [
                        'type' => 'integer',
                        'required' => true,
                        'sanitize_callback' => 'absint',
                    ],
                ],
            ]
        );

        register_rest_route(
            self::REST_NAMESPACE,
            '/attempts/(?P<id>\\d+)/submit',
            [
                'methods' => 'POST',
                'permission_callback' => static function () {
                    return is_user_logged_in();
                },
                'callback' => [Attempts::class, 'post_submit'],
                'args' => [
                    'answers' => [
                        'type' => 'object',
                        'required' => true,
                    ],
                ],
            ]
        );

        register_rest_route(
            self::REST_NAMESPACE,
            '/attempts/(?P<id>\\d+)/cross-eval/submit',
            [
                'methods' => 'POST',
                'permission_callback' => static function () {
                    return is_user_logged_in();
                },
                'callback' => [CrossEval::class, 'post_submit'],
                'args' => [
                    'sessionId' => [
                        'type' => 'integer',
                        'required' => true,
                        'sanitize_callback' => 'absint',
                    ],
                    'answers' => [
                        'type' => 'object',
                        'required' => true,
                    ],
                ],
            ]
        );

        register_rest_route(
            self::REST_NAMESPACE,
            '/courses/(?P<id>\\d+)/restart',
            [
                'methods' => 'POST',
                'permission_callback' => static function () {
                    return is_user_logged_in();
                },
                'callback' => [Binomial::class, 'post_restart'],
            ]
        );

        register_rest_route(
            self::REST_NAMESPACE,
            '/courses/(?P<id>\\d+)/certificate',
            [
                'methods' => 'GET',
                'permission_callback' => static function () {
                    return is_user_logged_in();
                },
                'callback' => [Certificates::class, 'get_course_certificate'],
            ]
        );
    }




    /**
     * Returns partner info for the course (for UI gating).
     *
     * @return array<string,mixed>
     */
    public static function course_partner_info(int $course_id): array
    {
        $out = [
            'hasPartner' => false,
            'partnerUserId' => 0,
            'ownerUserId' => 0,
            'isPartner' => false,
            'isOwner' => false,
            'otherUserId' => 0,
            'otherLessonsPercent' => 0,
        ];

        if ($course_id <= 0) {
            return $out;
        }

        $ids = self::course_partner_users($course_id);
        $owner_user_id = (int) ($ids['owner'] ?? 0);
        $partner_user_id = (int) ($ids['partner'] ?? 0);
        if ($partner_user_id <= 0) {
            return $out;
        }

        $current_user_id = (int) get_current_user_id();
        $out['hasPartner'] = true;
        $out['partnerUserId'] = $partner_user_id;
        $out['ownerUserId'] = $owner_user_id;
        $out['isPartner'] = ($partner_user_id === $current_user_id);
        $out['isOwner'] = ($owner_user_id > 0 && $owner_user_id === $current_user_id);

        $other_user_id = 0;
        if ($partner_user_id > 0) {
            $other_user_id = ($partner_user_id === $current_user_id) ? $owner_user_id : $partner_user_id;
        }
        $out['otherUserId'] = $other_user_id > 0 ? $other_user_id : 0;
        if ($other_user_id > 0) {
            $sum = Progress::course_summary($other_user_id, $course_id);
            $out['otherLessonsPercent'] = isset($sum['percent']) ? (int) $sum['percent'] : 0;
        }

        return $out;
    }


    public static function course_certificate_eligible(int $course_id, int $user_id): bool
    {
        if ($course_id <= 0 || $user_id <= 0) {
            return false;
        }
        if (!Access::user_can_access_course($user_id, $course_id)) {
            return false;
        }
        if (!Certificates::template_exists($course_id)) {
            return false;
        }

        $quiz = Binomial::quiz_for_course($course_id);
        $quiz_id = (int) ($quiz['id'] ?? 0);
        if ($quiz_id <= 0) {
            return false;
        }

        if (!Binomial::user_has_eligible_final($course_id, $quiz_id, $user_id)) {
            return false;
        }

        $ids = self::course_partner_users($course_id);
        $partner_user_id = (int) ($ids['partner'] ?? 0);
        if ($partner_user_id <= 0) {
            return true;
        }

        $owner_user_id = (int) ($ids['owner'] ?? 0);
        $other_user_id = ($partner_user_id === $user_id) ? $owner_user_id : $partner_user_id;
        if ($other_user_id <= 0) {
            return false;
        }

        return Binomial::user_has_eligible_final($course_id, $quiz_id, $other_user_id);
    }




    public static function quiz_passing_score(int $quiz_id): int
    {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT passing_score
                 FROM {$wpdb->prefix}learni_quizzes
                 WHERE id = %d
                 LIMIT 1",
                $quiz_id
            ),
            ARRAY_A
        );
        return isset($row['passing_score']) ? (int) $row['passing_score'] : 0;
    }

    /**
     * @param array<int,int> $question_ids
     * @return array<int,array<int,int>> question_id => [answer_id,...]
     */
    public static function correct_answer_ids_by_question(array $question_ids): array
    {
        global $wpdb;
        if (empty($question_ids)) {
            return [];
        }
        $in = implode(',', array_fill(0, count($question_ids), '%d'));
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, question_id
                 FROM {$wpdb->prefix}learni_quiz_answers
                 WHERE question_id IN ($in) AND is_correct = 1",
                $question_ids
            ),
            ARRAY_A
        );

        $out = [];
        foreach ($rows as $r) {
            $qid = (int) ($r['question_id'] ?? 0);
            $aid = (int) ($r['id'] ?? 0);
            if ($qid <= 0 || $aid <= 0) {
                continue;
            }
            if (!isset($out[$qid])) {
                $out[$qid] = [];
            }
            $out[$qid][] = $aid;
        }
        return $out;
    }

    /**
     * @return array{id:int,title:string,passingScore:int,settings:array<string,mixed>}
     */

    /**
     * @param array<int,int> $question_ids
     * @param array<string,mixed> $answer_order question_id => [answer_id,...]
     * @return array<int,array<string,mixed>>
     */



}
