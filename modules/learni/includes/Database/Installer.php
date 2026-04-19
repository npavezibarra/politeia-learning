<?php

namespace Learni\Database;

use Learni\PostTypes\Course;
use Learni\PostTypes\Lesson;

/**
 * Learni schema installer (ported for internal module use).
 */
final class Installer
{
    private const OPTION_DB_VERSION = 'learni_db_version';

    public static function activate(): void
    {
        self::install_or_upgrade_schema();
        self::ensure_caps();
    }

    public static function maybe_upgrade(): void
    {
        $current = (int) get_option(self::OPTION_DB_VERSION, 0);
        if ($current >= (int) LEARNI_DB_VERSION) {
            return;
        }

        self::install_or_upgrade_schema();
        self::ensure_caps();
    }

    private static function install_or_upgrade_schema(): void
    {
        global $wpdb;
        if (!$wpdb) {
            return;
        }

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();
        $prefix = $wpdb->prefix;

        $tables = [];

        $tables[] = "CREATE TABLE {$prefix}learni_course_items (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            course_post_id BIGINT UNSIGNED NOT NULL,
            item_type VARCHAR(32) NOT NULL,
            item_ref_id BIGINT UNSIGNED NOT NULL,
            label VARCHAR(255) NULL,
            sort_order INT UNSIGNED NOT NULL DEFAULT 0,
            is_preview TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
            PRIMARY KEY  (id),
            KEY course_post_id (course_post_id),
            KEY item_lookup (item_type, item_ref_id)
        ) {$charset_collate};";

        $tables[] = "CREATE TABLE {$prefix}learni_enrollments (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            course_post_id BIGINT UNSIGNED NOT NULL,
            status VARCHAR(20) NOT NULL,
            source VARCHAR(20) NOT NULL,
            woocommerce_order_id BIGINT UNSIGNED NULL,
            payment_provider VARCHAR(50) NULL,
            payment_reference VARCHAR(100) NULL,
            payment_amount DECIMAL(10,2) NULL,
            payment_currency VARCHAR(10) NULL,
            started_at DATETIME NULL,
            expires_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
            PRIMARY KEY  (id),
            UNIQUE KEY user_course (user_id, course_post_id),
            KEY course_post_id (course_post_id),
            KEY woocommerce_order_id (woocommerce_order_id),
            KEY payment_reference (payment_reference)
        ) {$charset_collate};";

        $tables[] = "CREATE TABLE {$prefix}learni_progress (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            course_post_id BIGINT UNSIGNED NOT NULL,
            lesson_post_id BIGINT UNSIGNED NOT NULL,
            status VARCHAR(20) NOT NULL,
            completed_at DATETIME NULL,
            updated_at DATETIME NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY user_lesson (user_id, lesson_post_id),
            KEY course_post_id (course_post_id),
            KEY user_course_status_completed (user_id, course_post_id, status, completed_at)
        ) {$charset_collate};";

        $tables[] = "CREATE TABLE {$prefix}learni_quizzes (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            course_post_id BIGINT UNSIGNED NOT NULL,
            lesson_post_id BIGINT UNSIGNED NULL,
            title VARCHAR(255) NOT NULL,
            passing_score INT UNSIGNED NOT NULL DEFAULT 0,
            time_limit_seconds INT UNSIGNED NOT NULL DEFAULT 0,
            settings_json LONGTEXT NULL,
            created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
            PRIMARY KEY  (id),
            KEY course_post_id (course_post_id),
            KEY lesson_post_id (lesson_post_id)
        ) {$charset_collate};";

        $tables[] = "CREATE TABLE {$prefix}learni_quiz_questions (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            quiz_id BIGINT UNSIGNED NOT NULL,
            type VARCHAR(32) NOT NULL,
            prompt LONGTEXT NOT NULL,
            explanation LONGTEXT NULL,
            points INT UNSIGNED NOT NULL DEFAULT 1,
            sort_order INT UNSIGNED NOT NULL DEFAULT 0,
            meta_json LONGTEXT NULL,
            PRIMARY KEY  (id),
            KEY quiz_id (quiz_id)
        ) {$charset_collate};";

        $tables[] = "CREATE TABLE {$prefix}learni_quiz_answers (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            question_id BIGINT UNSIGNED NOT NULL,
            answer_text LONGTEXT NOT NULL,
            is_correct TINYINT(1) NOT NULL DEFAULT 0,
            sort_order INT UNSIGNED NOT NULL DEFAULT 0,
            meta_json LONGTEXT NULL,
            PRIMARY KEY  (id),
            KEY question_id (question_id)
        ) {$charset_collate};";

        $tables[] = "CREATE TABLE {$prefix}learni_quiz_attempts (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            quiz_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            status VARCHAR(20) NOT NULL,
            score INT UNSIGNED NULL,
            passed TINYINT(1) NULL,
            started_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
            submitted_at DATETIME NULL,
            answers_json LONGTEXT NULL,
            PRIMARY KEY  (id),
            KEY quiz_id (quiz_id),
            KEY user_id (user_id)
        ) {$charset_collate};";

        $tables[] = "CREATE TABLE {$prefix}learni_cross_eval_sessions (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            course_id BIGINT UNSIGNED NOT NULL,
            quiz_id BIGINT UNSIGNED NOT NULL,
            initiator_user_id BIGINT UNSIGNED NOT NULL,
            target_user_id BIGINT UNSIGNED NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            expires_at DATETIME NULL,
            responded_at DATETIME NULL,
            started_at DATETIME NULL,
            completed_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
            updated_at DATETIME NULL,
            PRIMARY KEY  (id),
            KEY course_users (course_id, initiator_user_id, target_user_id),
            KEY target_status (target_user_id, status),
            KEY initiator_status (initiator_user_id, status),
            KEY expires_at (expires_at)
        ) {$charset_collate};";

        foreach ($tables as $sql) {
            dbDelta($sql);
        }

        update_option(self::OPTION_DB_VERSION, (int) LEARNI_DB_VERSION, true);
    }

    private static function ensure_caps(): void
    {
        $admin = get_role('administrator');
        if (!$admin) {
            return;
        }

        // Minimal capability surface for internal use.
        $admin->add_cap('manage_learni');

        foreach ([[Course::POST_TYPE, 'learni_courses'], [Lesson::POST_TYPE, 'learni_lessons']] as $pair) {
            [$singular, $plural] = $pair;
            foreach (self::post_type_caps($singular, $plural) as $cap) {
                $admin->add_cap($cap);
            }
        }
    }

    /**
     * @return string[]
     */
    private static function post_type_caps(string $singular, string $plural): array
    {
        return [
            "edit_{$singular}",
            "read_{$singular}",
            "delete_{$singular}",
            "edit_{$plural}",
            "edit_others_{$plural}",
            "delete_{$plural}",
            "publish_{$plural}",
            "read_private_{$plural}",
        ];
    }
}
