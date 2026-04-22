<?php
/**
 * Trait for Taxonomy and Role management in Course Creator.
 */

if (!defined('ABSPATH'))
    exit;

trait PL_CC_Taxonomy_Roles_Trait
{
    /**
     * Read collaborators for an object, preferring the unified partnerships table when available.
     */
    private function get_course_roles_for_object(string $object_type, int $object_id): array
    {
        if ($object_type === '' || $object_id <= 0) {
            return [];
        }

        global $wpdb;
        if (!$wpdb) {
            return [];
        }

        $roles_table = $wpdb->prefix . 'politeia_course_roles';

        if (class_exists('PL_Partnerships_Repository') && method_exists('PL_Partnerships_Repository', 'get_object_partners')) {
            try {
                $partners = PL_Partnerships_Repository::get_object_partners($object_type, $object_id);
                if (!empty($partners)) {
                    $partner_user_ids = [];
                    foreach ($partners as $row) {
                        if (!is_array($row)) {
                            continue;
                        }
                        $uid = (int) ($row['partner_user_id'] ?? 0);
                        if ($uid > 0) {
                            $partner_user_ids[] = $uid;
                        }
                    }
                    $partner_user_ids = array_values(array_unique(array_filter($partner_user_ids)));

                    $legacy_by_user_id = [];
                    if (!empty($partner_user_ids)) {
                        $placeholders = implode(',', array_fill(0, count($partner_user_ids), '%d'));
                        $sql = $wpdb->prepare(
                            "SELECT *
                            FROM {$roles_table}
                            WHERE object_type = %s
                              AND object_id = %d
                              AND user_id IN ({$placeholders})",
                            array_merge([$object_type, $object_id], $partner_user_ids)
                        );
                        $legacy_rows = $wpdb->get_results($sql);
                        foreach ((array) $legacy_rows as $lr) {
                            $legacy_by_user_id[(int) ($lr->user_id ?? 0)] = $lr;
                        }
                    }

                    $out = [];
                    foreach ($partners as $row) {
                        if (!is_array($row)) {
                            continue;
                        }

                        $uid = (int) ($row['partner_user_id'] ?? 0);
                        if ($uid <= 0) {
                            continue;
                        }

                        if (!empty($legacy_by_user_id[$uid])) {
                            $out[] = $legacy_by_user_id[$uid];
                            continue;
                        }

                        $out[] = (object) [
                            'user_id' => $uid,
                            'role_slug' => $this->denormalize_partnership_role_slug((string) ($row['role'] ?? '')),
                            'profit_percentage' => 0,
                            'role_description' => '',
                        ];
                    }

                    if (!empty($out)) {
                        return $out;
                    }
                }
            } catch (\Throwable $e) {
                // Best-effort: fall back to legacy reads.
            }
        }

        $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$roles_table} WHERE object_type = %s AND object_id = %d", $object_type, $object_id));
        return is_array($rows) ? $rows : [];
    }

    private function maybe_dual_write_partnership(string $object_type, int $object_id, int $user_id, string $role_slug): void
    {
        if ($object_type === '' || $object_id <= 0 || $user_id <= 0) {
            return;
        }

        if (!class_exists('PL_Partnerships_Repository') || !method_exists('PL_Partnerships_Repository', 'add_partner')) {
            return;
        }

        $normalized_role = $this->normalize_partnership_role($role_slug);
        try {
            PL_Partnerships_Repository::add_partner($object_type, $object_id, $user_id, $normalized_role);
        } catch (\Throwable $e) {
            // Best-effort dual-write.
        }
    }

    private function assign_learning_terms(int $object_id, $category_ids, $tag_ids): void
    {
        if ($object_id <= 0 || !class_exists('PL_Taxonomy')) {
            return;
        }

        $categories = $this->normalize_term_id_list($category_ids);
        $tags = $this->normalize_term_id_list($tag_ids);

        if (taxonomy_exists(PL_Taxonomy::CATEGORY_TAXONOMY)) {
            wp_set_object_terms($object_id, $categories, PL_Taxonomy::CATEGORY_TAXONOMY, false);
        }

        if (taxonomy_exists(PL_Taxonomy::TAG_TAXONOMY)) {
            wp_set_object_terms($object_id, $tags, PL_Taxonomy::TAG_TAXONOMY, false);
        }
    }

    private function get_learning_terms_for_object(int $object_id): array
    {
        $out = [
            'category_ids' => [],
            'tag_ids' => [],
            'tags' => [],
        ];

        if ($object_id <= 0 || !class_exists('PL_Taxonomy')) {
            return $out;
        }

        if (taxonomy_exists(PL_Taxonomy::CATEGORY_TAXONOMY)) {
            $cats = wp_get_object_terms($object_id, PL_Taxonomy::CATEGORY_TAXONOMY, ['fields' => 'ids']);
            if (!is_wp_error($cats) && is_array($cats)) {
                $out['category_ids'] = array_values(array_unique(array_map('absint', $cats)));
            }
        }

        if (taxonomy_exists(PL_Taxonomy::TAG_TAXONOMY)) {
            $tags = wp_get_object_terms($object_id, PL_Taxonomy::TAG_TAXONOMY, ['fields' => 'all']);
            if (!is_wp_error($tags) && is_array($tags)) {
                $out['tag_ids'] = array_values(array_unique(array_map(static function ($t) {
                    return absint($t->term_id);
                }, $tags)));
                $out['tags'] = array_values(array_map(static function ($t) {
                    return [
                        'id' => (int) $t->term_id,
                        'name' => (string) $t->name,
                        'slug' => (string) $t->slug,
                    ];
                }, $tags));
            }
        }

        return $out;
    }

    public function handle_get_learning_meta_terms(): void
    {
        check_ajax_referer('pcg_creator_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => __('No autorizado.', 'politeia-learning')], 401);
        }

        if (!class_exists('PL_Taxonomy')) {
            wp_send_json_error(['message' => __('Taxonomías no disponibles.', 'politeia-learning')], 400);
        }

        $categories = [];
        if (taxonomy_exists(PL_Taxonomy::CATEGORY_TAXONOMY)) {
            $terms = get_terms([
                'taxonomy' => PL_Taxonomy::CATEGORY_TAXONOMY,
                'hide_empty' => false,
                'orderby' => 'name',
                'order' => 'ASC',
            ]);
            if (!is_wp_error($terms) && is_array($terms)) {
                foreach ($terms as $t) {
                    $categories[] = [
                        'id' => (int) $t->term_id,
                        'name' => (string) $t->name,
                        'slug' => (string) $t->slug,
                        'parent' => (int) $t->parent,
                    ];
                }
            }
        }

        $tags = [];
        if (taxonomy_exists(PL_Taxonomy::TAG_TAXONOMY)) {
            $terms = get_terms([
                'taxonomy' => PL_Taxonomy::TAG_TAXONOMY,
                'hide_empty' => false,
                'orderby' => 'name',
                'order' => 'ASC',
                'number' => 500,
            ]);
            if (!is_wp_error($terms) && is_array($terms)) {
                foreach ($terms as $t) {
                    $tags[] = [
                        'id' => (int) $t->term_id,
                        'name' => (string) $t->name,
                        'slug' => (string) $t->slug,
                    ];
                }
            }
        }

        wp_send_json_success([
            'categories' => $categories,
            'tags' => $tags,
        ]);
    }

    public function handle_create_learning_tag(): void
    {
        check_ajax_referer('pcg_creator_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => __('No autorizado.', 'politeia-learning')], 401);
        }

        if (!class_exists('PL_Taxonomy') || !taxonomy_exists(PL_Taxonomy::TAG_TAXONOMY)) {
            wp_send_json_error(['message' => __('Taxonomías no disponibles.', 'politeia-learning')], 400);
        }

        $name = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
        $name = trim($name);
        if ($name === '') {
            wp_send_json_error(['message' => __('Nombre inválido.', 'politeia-learning')], 400);
        }

        $existing = term_exists($name, PL_Taxonomy::TAG_TAXONOMY);
        if (is_array($existing) && !empty($existing['term_id'])) {
            $term = get_term((int) $existing['term_id'], PL_Taxonomy::TAG_TAXONOMY);
            if ($term && !is_wp_error($term)) {
                wp_send_json_success([
                    'id' => (int) $term->term_id,
                    'name' => (string) $term->name,
                    'slug' => (string) $term->slug,
                ]);
            }
        }

        $inserted = wp_insert_term($name, PL_Taxonomy::TAG_TAXONOMY);
        if (is_wp_error($inserted) || empty($inserted['term_id'])) {
            wp_send_json_error(['message' => __('No se pudo crear la etiqueta.', 'politeia-learning')], 500);
        }

        $term = get_term((int) $inserted['term_id'], PL_Taxonomy::TAG_TAXONOMY);
        if (!$term || is_wp_error($term)) {
            wp_send_json_error(['message' => __('No se pudo crear la etiqueta.', 'politeia-learning')], 500);
        }

        wp_send_json_success([
            'id' => (int) $term->term_id,
            'name' => (string) $term->name,
            'slug' => (string) $term->slug,
        ]);
    }
}
