<?php

namespace Learni\Courses;

final class Outline
{
    /**
     * @return array<int, array{type:string, refId:int, label:string, sortOrder:int, isPreview:bool}>
     */
    public static function get_items(int $course_post_id): array
    {
        if ($course_post_id <= 0) {
            return [];
        }

        global $wpdb;
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT item_type, item_ref_id, label, sort_order, is_preview
                 FROM {$wpdb->prefix}learni_course_items
                 WHERE course_post_id = %d
                 ORDER BY sort_order ASC, id ASC",
                $course_post_id
            ),
            ARRAY_A
        );

        $items = [];
        foreach ((array) $rows as $row) {
            $type = isset($row['item_type']) ? (string) $row['item_type'] : '';
            if ($type !== 'lesson' && $type !== 'header') {
                continue;
            }

            $items[] = [
                'type' => $type,
                'refId' => isset($row['item_ref_id']) ? (int) $row['item_ref_id'] : 0,
                'label' => isset($row['label']) ? (string) $row['label'] : '',
                'sortOrder' => isset($row['sort_order']) ? (int) $row['sort_order'] : 0,
                'isPreview' => !empty($row['is_preview']),
            ];
        }

        return $items;
    }

    /**
     * @return int[]
     */
    public static function lesson_ids(int $course_post_id): array
    {
        $items = self::get_items($course_post_id);
        $ids = [];
        foreach ($items as $item) {
            if ($item['type'] !== 'lesson') {
                continue;
            }
            if ($item['refId'] > 0) {
                $ids[] = (int) $item['refId'];
            }
        }
        return array_values(array_unique($ids));
    }
}

