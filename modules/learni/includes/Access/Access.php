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
            if ($user_id <= 0 || !class_exists('\\Learni\\Database\\Enrollments')) {
                return false;
            }

            if (\Learni\Database\Enrollments::user_has_active($user_id, $course_post_id)) {
                return true;
            }

            // Course partner access: if the user is an active partner for this course, grant access and
            // ensure an enrollment row exists so the rest of the Learni UX works consistently.
            if (class_exists('PL_Partnerships_Repository') && method_exists('PL_Partnerships_Repository', 'get_object_partners_by_role')) {
                try {
                    $rows = \PL_Partnerships_Repository::get_object_partners_by_role('course', $course_post_id, 'partner');
                    if (is_array($rows)) {
                        foreach ($rows as $row) {
                            if (is_array($row) && (int) ($row['partner_user_id'] ?? 0) === $user_id && ($row['status'] ?? '') === 'active') {
                                \Learni\Database\Enrollments::upsert($user_id, $course_post_id, [
                                    'status' => \Learni\Database\Enrollments::STATUS_ACTIVE,
                                    'source' => \Learni\Database\Enrollments::SOURCE_MANUAL,
                                    'payment_provider' => 'partner_invite',
                                    'payment_reference' => 'course_partner',
                                ]);
                                return true;
                            }
                        }
                    }
                } catch (\Throwable $e) {
                    // ignore and fall through
                }
            }

            return false;
        }

        // Free courses are public.
        return true;
    }
}
