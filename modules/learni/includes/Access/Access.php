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

        // Admins and editors always have access.
        if ($user_id > 0 && user_can($user_id, 'manage_options')) {
            return true;
        }

        $price = (float) get_post_meta($course_post_id, 'learni_price', true);
        $product_id = (int) get_post_meta($course_post_id, 'learni_wc_product_id', true);

        // If it's a paid course (has price or a linked WC product).
        if ($price > 0 || $product_id > 0) {
            return $user_id > 0 && class_exists('\\Learni\\Database\\Enrollments') && \Learni\Database\Enrollments::user_has_active($user_id, $course_post_id);
        }

        // Free courses are public.
        return true;
    }
}

