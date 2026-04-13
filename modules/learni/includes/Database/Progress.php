<?php

namespace Learni\Database;

use Learni\Courses\Outline;

final class Progress
{
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETE = 'complete';

    public static function completed_lesson_ids(int $user_id, int $course_post_id): array
    {
        if ($user_id <= 0 || $course_post_id <= 0) {
            return [];
        }

        global $wpdb;
        $table = $wpdb->prefix . 'learni_progress';

        $ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT lesson_post_id
                 FROM {$table}
                 WHERE user_id = %d AND course_post_id = %d AND status = %s",
                $user_id,
                $course_post_id,
                self::STATUS_COMPLETE
            )
        );

        return array_values(array_unique(array_map('intval', (array) $ids)));
    }

    /**
     * @return array{total:int, completed:int, percent:int}
     */
    public static function course_summary(int $user_id, int $course_post_id): array
    {
        $lesson_ids = Outline::lesson_ids($course_post_id);
        $total = count($lesson_ids);

        if ($total === 0 || $user_id <= 0) {
            return [
                'total' => $total,
                'completed' => 0,
                'percent' => 0,
            ];
        }

        $completed_ids = self::completed_lesson_ids($user_id, $course_post_id);
        $completed = count(array_intersect($lesson_ids, $completed_ids));
        $percent = (int) round(($completed / $total) * 100);

        return [
            'total' => $total,
            'completed' => $completed,
            'percent' => $percent,
        ];
    }
}

