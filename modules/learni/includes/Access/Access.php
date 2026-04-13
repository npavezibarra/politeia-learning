<?php

namespace Learni\Access;

use Learni\Database\Enrollments;

final class Access
{
    /**
     * Minimal access rule for internal rollout:
     * - free courses: public
     * - paid courses: must have an active enrollment
     */
    public static function user_can_access_course(int $user_id, int $course_post_id): bool
    {
        if ($course_post_id <= 0) {
            return false;
        }

        $price = (float) get_post_meta($course_post_id, 'learni_price', true);
        if ($price <= 0) {
            return true;
        }

        return $user_id > 0 && Enrollments::user_has_active($user_id, $course_post_id);
    }
}

