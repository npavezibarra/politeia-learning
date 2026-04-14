<?php
/**
 * Handles saving courses created via the Course Creator dashboard.
 */

if (!defined('ABSPATH'))
    exit;

class PL_CC_Course_Save_Handler
{
    private const REQUIRED_PRODUCT_CATEGORY_NAME = 'Cursos';
    private const REQUIRED_PRODUCT_CATEGORY_SLUG = 'cursos';
    private const AJAX_META_TERMS = 'pcg_get_learning_meta_terms';
    private const AJAX_CREATE_TAG = 'pcg_create_learning_tag';

    private function normalize_partnership_role(string $role_slug): string
    {
        $key = trim($role_slug);
        if ($key === '') {
            return 'collaborator';
        }

        if (function_exists('remove_accents')) {
            $key = remove_accents($key);
        }

        $key = strtolower($key);

        $map = [
            'autor principal' => 'author',
            'editor' => 'editor',
            'teacher' => 'teacher',
            'profesor' => 'teacher',
        ];

        return $map[$key] ?? 'collaborator';
    }

    private function denormalize_partnership_role_slug(string $role_value): string
    {
        $key = trim($role_value);
        if ($key === '') {
            return __('Colaborador', 'politeia-learning');
        }

        $raw_lower = $key;
        if (function_exists('remove_accents')) {
            $raw_lower = remove_accents($raw_lower);
        }
        $raw_lower = strtolower($raw_lower);

        // If the row already stores a human-facing role_slug (legacy/migrated rows), preserve it.
        $legacy_map = [
            'autor principal' => __('Autor principal', 'politeia-learning'),
            'editor' => __('Editor', 'politeia-learning'),
            'profesor' => __('Profesor', 'politeia-learning'),
            'teacher' => __('Teacher', 'politeia-learning'),
        ];
        if (isset($legacy_map[$raw_lower])) {
            return $legacy_map[$raw_lower];
        }

        $map = [
            'author' => __('Autor principal', 'politeia-learning'),
            'editor' => __('Editor', 'politeia-learning'),
            'teacher' => __('Teacher', 'politeia-learning'),
            'collaborator' => __('Colaborador', 'politeia-learning'),
        ];

        return $map[strtolower($key)] ?? __('Colaborador', 'politeia-learning');
    }

    /**
     * Read collaborators for an object, preferring the unified partnerships table when available.
     *
     * Returns the legacy structure (objects with user_id, role_slug, profit_percentage, role_description).
     *
     * @return array<int,object>
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
                        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
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

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
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
            // Best-effort dual-write: never break the legacy flow.
        }
    }

    private function user_can_manage_group(int $group_id, int $user_id): bool
    {
        if ($group_id <= 0 || $user_id <= 0) {
            return false;
        }

        if (current_user_can('manage_options')) {
            return true;
        }

        $author_id = (int) get_post_field('post_author', $group_id);
        if ($author_id === $user_id) {
            return true;
        }

        if (function_exists('learndash_get_administrators_group_ids')) {
            $leader_group_ids = learndash_get_administrators_group_ids($user_id);
            $leader_group_ids = array_map('absint', (array) $leader_group_ids);
            if (in_array($group_id, $leader_group_ids, true)) {
                return true;
            }
        }

        return false;
    }

    private function user_can_manage_programa(int $programa_id, int $user_id): bool
    {
        if ($programa_id <= 0 || $user_id <= 0) {
            return false;
        }

        if (current_user_can('manage_options')) {
            return true;
        }

        $author_id = (int) get_post_field('post_author', $programa_id);
        return $author_id === $user_id;
    }

    public function __construct()
    {
        add_action('wp_ajax_pcg_save_course', [$this, 'handle_save_course']);
        add_action('wp_ajax_pcg_get_my_courses', [$this, 'handle_get_my_courses']);
        add_action('wp_ajax_pcg_get_published_courses', [$this, 'handle_get_published_courses']);
        add_action('wp_ajax_pcg_get_my_specializations', [$this, 'handle_get_my_specializations']);
        add_action('wp_ajax_pcg_get_published_specializations', [$this, 'handle_get_published_specializations']);
        add_action('wp_ajax_pcg_save_specialization', [$this, 'handle_save_specialization']);
        add_action('wp_ajax_pcg_get_specialization_for_edit', [$this, 'handle_get_specialization_for_edit']);
        add_action('wp_ajax_pcg_delete_specialization', [$this, 'handle_delete_specialization']);
        add_action('wp_ajax_pcg_get_my_programas', [$this, 'handle_get_my_programas']);
        add_action('wp_ajax_pcg_save_programa', [$this, 'handle_save_programa']);
        add_action('wp_ajax_pcg_get_programa_for_edit', [$this, 'handle_get_programa_for_edit']);
        add_action('wp_ajax_pcg_delete_programa', [$this, 'handle_delete_programa']);
        add_action('wp_ajax_pcg_delete_course', [$this, 'handle_delete_course']);
        add_action('wp_ajax_pcg_get_course_for_edit', [$this, 'handle_get_course_for_edit']);
        add_action('wp_ajax_pcg_upload_cropped_image', [$this, 'handle_upload_cropped_image']);

        add_action('wp_ajax_pcg_save_escrito', [$this, 'handle_save_escrito']);
        add_action('wp_ajax_pcg_get_my_escritos', [$this, 'handle_get_my_escritos']);
        add_action('wp_ajax_pcg_get_escrito_for_edit', [$this, 'handle_get_escrito_for_edit']);
        add_action('wp_ajax_pcg_delete_escrito', [$this, 'handle_delete_escrito']);
        add_action('wp_ajax_pcg_save_profile_avatar', [$this, 'handle_save_profile_avatar']);

        add_action('wp_ajax_' . self::AJAX_META_TERMS, [$this, 'handle_get_learning_meta_terms']);
        add_action('wp_ajax_' . self::AJAX_CREATE_TAG, [$this, 'handle_create_learning_tag']);

        add_action('pcg_inclusion_snapshot_approved', [$this, 'handle_inclusion_snapshot_approved'], 10, 3);
    }

    private function normalize_term_id_list($raw): array
    {
        if (!is_array($raw)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map('absint', $raw))));
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

    /**
     * Handles uploading and saving a cropped image from the cropper.
     */
    public function handle_upload_cropped_image()
    {
        check_ajax_referer('pcg_creator_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => __('No autorizado.', 'politeia-learning')]);
        }

        $image_data = $_POST['image_data'] ?? '';
        $type = $_POST['type'] ?? 'thumbnail'; // thumbnail or cover

        if (empty($image_data)) {
            wp_send_json_error(['message' => 'No image data received.']);
        }

        $ext = '.png';
        if (preg_match('/^data:image\/(\w+);base64,/', $image_data, $typeMatch)) {
            $ext = $typeMatch[1] === 'jpeg' ? '.jpg' : '.' . $typeMatch[1];
            $image_data = substr($image_data, strpos($image_data, ',') + 1);
        }

        $decoded_image = base64_decode($image_data);

        if (!$decoded_image) {
            wp_send_json_error(['message' => 'Invalid image data.']);
        }

        $upload_dir = wp_upload_dir();
        $user = wp_get_current_user();
        $username = sanitize_title($user->user_login);
        $entity_id = intval($_POST['entity_id'] ?? 0);

        $filename = "{$username}-{$entity_id}-" . time() . '-' . sanitize_title($type) . $ext;
        $file_path = $upload_dir['path'] . '/' . $filename;

        // Save to file
        if (false === file_put_contents($file_path, $decoded_image)) {
            wp_send_json_error(['message' => 'No se pudo guardar el archivo en el servidor.']);
        }

        $filetype = wp_check_filetype($filename, null);
        $attachment = [
            'post_mime_type' => $filetype['type'],
            'post_title' => sanitize_file_name($filename),
            'post_content' => '',
            'post_status' => 'inherit',
            'post_author' => get_current_user_id()
        ];

        $attach_id = wp_insert_attachment($attachment, $file_path);

        if (is_wp_error($attach_id)) {
            wp_send_json_error(['message' => 'Error al crear el attachment: ' . $attach_id->get_error_message()]);
        }

        require_once(ABSPATH . 'wp-admin/includes/image.php');
        $attach_data = wp_generate_attachment_metadata($attach_id, $file_path);
        wp_update_attachment_metadata($attach_id, $attach_data);

        wp_send_json_success([
            'id' => $attach_id,
            'url' => wp_get_attachment_url($attach_id)
        ]);
    }

    public function handle_save_course()
    {
        check_ajax_referer('pcg_creator_nonce', 'nonce');

        $data = $_POST['course_data'] ?? [];
        if (empty($data)) {
            wp_send_json_error(['message' => 'No se han recibido datos del curso.']);
        }

        $course_id = intval($data['id'] ?? 0);
        $status = sanitize_text_field((string) ($data['status'] ?? 'publish'));
        if (!in_array($status, ['draft', 'publish'], true)) {
            $status = 'publish';
        }
        $title = sanitize_text_field($data['title']);
        $description = wp_kses_post($data['description']);
        $excerpt = wp_kses_post($data['excerpt'] ?? '');
        $price = sanitize_text_field($data['price']);
        $thumbnail_id = intval($data['thumbnail_id'] ?? 0);
        $progression = sanitize_text_field($data['progression'] ?? '');
        $certificate_attachment_id = intval($data['certificate_attachment_id'] ?? 0);
        $certificate_title = sanitize_text_field((string) ($data['certificate_title'] ?? ''));
        $certificate_congrats = wp_kses_post((string) ($data['certificate_congrats'] ?? ''));
        $certificate_logo_attachment_id = intval($data['certificate_logo_attachment_id'] ?? 0);
        $certificate_signature_attachment_id = intval($data['certificate_signature_attachment_id'] ?? 0);
        $certificate_signature_label = sanitize_text_field((string) ($data['certificate_signature_label'] ?? ''));
        $content_list = $data['content'] ?? [];
        $teacher_ids = $data['teachers'] ?? [];
        $category_ids = $data['category_ids'] ?? [];
        $tag_ids = $data['tag_ids'] ?? [];

        // 1. Create or Update Course (Learni internal CPT)
        $post_data = [
            'post_title' => $title,
            'post_content' => $description,
            'post_excerpt' => $excerpt,
            'post_status' => $status,
            'post_type' => 'learni_course',
            'post_author' => get_current_user_id()
        ];

        if ($course_id > 0) {
            $existing = get_post($course_id);
            if (!$existing || $existing->post_type !== 'learni_course' || (int) $existing->post_author !== (int) get_current_user_id()) {
                wp_send_json_error(['message' => __('No autorizado.', 'politeia-learning')], 403);
            }
            $post_data['ID'] = $course_id;
            $course_id = wp_update_post($post_data);
        } else {
            $course_id = wp_insert_post($post_data);
        }

        if (is_wp_error($course_id)) {
            wp_send_json_error(['message' => 'Error al guardar el curso: ' . $course_id->get_error_message()]);
        }

        // 2. Set Featured Image
        if ($thumbnail_id > 0) {
            set_post_thumbnail($course_id, $thumbnail_id);
        } else {
            delete_post_thumbnail($course_id);
        }

        // 2b. Set Cover Photo (Politeia meta on Learni courses)
        $cover_photo_id = intval($data['cover_photo_id'] ?? 0);
        $cover_meta_key = class_exists('\\Learni\\PostTypes\\Course') ? \Learni\PostTypes\Course::META_COVER_PHOTO_ID : 'pl_cover_photo_id';
        if ($cover_photo_id > 0) {
            update_post_meta($course_id, $cover_meta_key, $cover_photo_id);
        } else {
            delete_post_meta($course_id, $cover_meta_key);
        }

        // 2d. Certificate template (attachment id).
        $cert_meta_key = class_exists('\\Learni\\PostTypes\\Course') ? \Learni\PostTypes\Course::META_CERTIFICATE_ATTACHMENT_ID : 'learni_certificate_attachment_id';
        if ($certificate_attachment_id > 0) {
            update_post_meta($course_id, $cert_meta_key, $certificate_attachment_id);
        } else {
            delete_post_meta($course_id, $cert_meta_key);
        }

        // 2e. Certificate configuration (title, paragraph, claims, logo, signature).
        $meta_keys = class_exists('\\Learni\\PostTypes\\Course')
            ? [
                'title' => \Learni\PostTypes\Course::META_CERTIFICATE_TITLE,
                'congrats' => \Learni\PostTypes\Course::META_CERTIFICATE_CONGRATS,
                'claim_first' => \Learni\PostTypes\Course::META_CERTIFICATE_CLAIM_FIRST,
                'claim_final' => \Learni\PostTypes\Course::META_CERTIFICATE_CLAIM_FINAL,
                'claim_variation' => \Learni\PostTypes\Course::META_CERTIFICATE_CLAIM_VARIATION,
                'logo_id' => \Learni\PostTypes\Course::META_CERTIFICATE_LOGO_ATTACHMENT_ID,
                'logo_align' => \Learni\PostTypes\Course::META_CERTIFICATE_LOGO_ALIGN,
                'signature_id' => \Learni\PostTypes\Course::META_CERTIFICATE_SIGNATURE_ATTACHMENT_ID,
                'signature_label' => \Learni\PostTypes\Course::META_CERTIFICATE_SIGNATURE_LABEL,
            ]
            : [
                'title' => 'pl_certificate_title',
                'congrats' => 'pl_certificate_congrats',
                'claim_first' => 'pl_certificate_claim_first',
                'claim_final' => 'pl_certificate_claim_final',
                'claim_variation' => 'pl_certificate_claim_variation',
                'logo_id' => 'pl_certificate_logo_attachment_id',
                'logo_align' => 'pl_certificate_logo_align',
                'signature_id' => 'pl_certificate_signature_attachment_id',
                'signature_label' => 'pl_certificate_signature_label',
            ];

        if ($certificate_title !== '') {
            update_post_meta($course_id, $meta_keys['title'], $certificate_title);
        } else {
            delete_post_meta($course_id, $meta_keys['title']);
        }

        if ($certificate_congrats !== '') {
            update_post_meta($course_id, $meta_keys['congrats'], $certificate_congrats);
        } else {
            delete_post_meta($course_id, $meta_keys['congrats']);
        }

        // Claims are always shown on the certificate (first/final/variation).
        update_post_meta($course_id, $meta_keys['claim_first'], 1);
        update_post_meta($course_id, $meta_keys['claim_final'], 1);
        update_post_meta($course_id, $meta_keys['claim_variation'], 1);

        if ($certificate_logo_attachment_id > 0) {
            update_post_meta($course_id, $meta_keys['logo_id'], $certificate_logo_attachment_id);
        } else {
            delete_post_meta($course_id, $meta_keys['logo_id']);
        }
        // Logo alignment is always left (no UI toggle).
        update_post_meta($course_id, $meta_keys['logo_align'], 'left');

        if ($certificate_signature_attachment_id > 0) {
            update_post_meta($course_id, $meta_keys['signature_id'], $certificate_signature_attachment_id);
        } else {
            delete_post_meta($course_id, $meta_keys['signature_id']);
        }

        if ($certificate_signature_label !== '') {
            update_post_meta($course_id, $meta_keys['signature_label'], $certificate_signature_label);
        } else {
            delete_post_meta($course_id, $meta_keys['signature_label']);
        }

        // 2c. Set Additional Teachers (Legacy Meta + New Table)
        global $wpdb;
        $roles_table = $wpdb->prefix . 'politeia_course_roles';
        $wpdb->delete($roles_table, ['object_type' => 'course', 'object_id' => $course_id], ['%s', '%d']);

        if (!empty($teacher_ids) && is_array($teacher_ids)) {
            foreach ($teacher_ids as $teacher) {
                $teacher_user_id = intval($teacher['user_id'] ?? 0);
                $teacher_role_slug = sanitize_text_field((string) ($teacher['role_slug'] ?? ''));
                $wpdb->insert($roles_table, [
                    'object_type' => 'course',
                    'object_id' => $course_id,
                    'user_id' => $teacher_user_id,
                    'role_slug' => $teacher_role_slug,
                    'profit_percentage' => floatval($teacher['profit_percentage']),
                    'role_description' => wp_kses_post($teacher['role_description']),
                ], ['%s', '%d', '%d', '%s', '%f', '%s']);

                $this->maybe_dual_write_partnership('course', (int) $course_id, (int) $teacher_user_id, (string) $teacher_role_slug);
            }
        }

        // 3. Save Learni course settings (price + order/progression)
        $price_type = 'free';
        $numeric_price = 0;
        if (!empty($price)) {
            $price_type = 'closed';
            $numeric_price = intval(preg_replace('/[^0-9]/', '', (string) $price));
        }
        update_post_meta($course_id, 'learni_price', (float) $numeric_price);

        // UI: "FLUJO LIBRE" checked => progression disabled => Learni linear order false.
        $linear = ($progression === 'on') ? false : true;
        update_post_meta($course_id, 'learni_linear_order', $linear ? 1 : 0);

        // 3. Handle Lessons and Sections
        $this->process_course_content($course_id, $content_list);

        // 4. Sync with WooCommerce
        $product_url = $this->sync_course_to_woo_product($course_id, $data, $price_type, $status);

        // 5. Save learning categories/tags.
        $this->assign_learning_terms((int) $course_id, $category_ids, $tag_ids);

        wp_send_json_success([
            'course_id' => $course_id,
            'product_url' => $product_url,
            'permalink' => get_permalink($course_id),
            'status' => get_post_status($course_id),
            'message' => __('Curso guardado y sincronizado con la tienda exitosamente.', 'politeia-learning')
        ]);
    }

    /**
     * Creates or updates a WooCommerce product linked to the course.
     */
    private function sync_course_to_woo_product($course_id, $data, $price_type, string $status = 'publish')
    {
        if (!class_exists('WooCommerce')) {
            return '';
        }

        $product_id = get_post_meta($course_id, '_pcg_woo_product_id', true);
        $title = sanitize_text_field($data['title']);
        $description = wp_kses_post($data['description']);
        $excerpt = wp_kses_post($data['excerpt'] ?? '');
        $price = intval(preg_replace('/[^0-9]/', '', $data['price'] ?? '0'));
        $thumbnail_id = intval($data['thumbnail_id'] ?? 0);

        // If course becomes free, we might want to unpublish or trash the product
        if ($price_type === 'free') {
            if ($product_id) {
                wp_trash_post($product_id);
                delete_post_meta($course_id, '_pcg_woo_product_id');
            }
            return '';
        }

        if (!in_array($status, ['draft', 'publish'], true)) {
            $status = 'publish';
        }

        $post_data = [
            'post_title' => $title,
            'post_content' => $description,
            'post_excerpt' => $excerpt,
            'post_status' => $status,
            'post_type' => 'product',
        ];

        if ($product_id && get_post($product_id)) {
            $post_data['ID'] = $product_id;
            wp_update_post($post_data);
        } else {
            $product_id = wp_insert_post($post_data);
            if (!is_wp_error($product_id)) {
                update_post_meta($course_id, '_pcg_woo_product_id', $product_id);
            }
        }

        if (is_wp_error($product_id)) {
            return '';
        }

        // Set Product Type to 'course' (slug handles the selection in the UI)
        wp_set_object_terms($product_id, 'course', 'product_type');

        // Always ensure the "Cursos" category is set for course products created from the frontend.
        $this->ensure_required_product_category($product_id);

        // WooCommerce Meta
        update_post_meta($product_id, '_regular_price', $price);
        update_post_meta($product_id, '_price', $price);
        update_post_meta($product_id, '_thumbnail_id', $thumbnail_id);

        // Product owner: the course main author.
        $author_id = (int) get_post_field('post_author', $course_id);
        if ($author_id > 0) {
            update_post_meta($product_id, 'product_owner', $author_id);
        }

        // Link product -> course (compat for legacy Politeia modules + Learni native meta).
        // Store as array to match expected serialized format a:1:{i:0;i:XX;} used by older code.
        update_post_meta($product_id, '_related_course', [$course_id]);
        update_post_meta($product_id, '_learni_course_id', $course_id);
        update_post_meta($course_id, 'learni_wc_product_id', $product_id);

        return get_permalink($product_id);
    }

    /**
     * Creates or updates a WooCommerce product linked to a LearnDash Group (Especialización).
     *
     * Uses LearnDash WooCommerce addon meta key: _related_group
     */
    private function sync_group_to_woo_product(int $group_id, array $data, string $price_type, string $post_status = 'publish'): string
    {
        if (!class_exists('WooCommerce')) {
            return '';
        }

        $product_id = (int) get_post_meta($group_id, '_pcg_woo_product_id', true);
        $title = sanitize_text_field($data['title'] ?? get_the_title($group_id));
        $description = wp_kses_post($data['description'] ?? '');
        $price = (int) preg_replace('/[^0-9]/', '', (string) ($data['price'] ?? '0'));
        $thumbnail_id = (int) ($data['thumbnail_id'] ?? 0);

        if ($price_type === 'free') {
            if ($product_id) {
                wp_trash_post($product_id);
                delete_post_meta($group_id, '_pcg_woo_product_id');
            }
            return '';
        }

        $post_data = [
            'post_title' => $title,
            'post_content' => $description,
            'post_excerpt' => '',
            'post_status' => $post_status,
            'post_type' => 'product',
        ];

        if ($product_id && get_post($product_id)) {
            $post_data['ID'] = $product_id;
            wp_update_post($post_data);
        } else {
            $product_id = (int) wp_insert_post($post_data);
            if ($product_id > 0 && !is_wp_error($product_id)) {
                update_post_meta($group_id, '_pcg_woo_product_id', $product_id);
            }
        }

        if (!$product_id || is_wp_error($product_id)) {
            return '';
        }

        wp_set_object_terms($product_id, 'course', 'product_type');
        $this->ensure_required_product_category($product_id);

        update_post_meta($product_id, '_regular_price', $price);
        update_post_meta($product_id, '_price', $price);
        if ($thumbnail_id > 0) {
            update_post_meta($product_id, '_thumbnail_id', $thumbnail_id);
        }

        $author_id = (int) get_post_field('post_author', $group_id);
        if ($author_id > 0) {
            update_post_meta($product_id, 'product_owner', $author_id);
        }

        // Link to LearnDash Group (LearnDash WooCommerce).
        update_post_meta($product_id, '_related_group', [$group_id]);

        return get_permalink($product_id);
    }

    /**
     * Creates or updates a WooCommerce product linked to a Programa (course_program),
     * granting access to all associated LearnDash Groups by syncing _related_group.
     */
    private function sync_program_to_woo_product(int $programa_id, array $data, string $price_type, array $group_ids, string $post_status = 'publish'): string
    {
        if (!class_exists('WooCommerce')) {
            return '';
        }

        $product_id = (int) get_post_meta($programa_id, '_pcg_woo_product_id', true);
        $title = sanitize_text_field($data['title'] ?? get_the_title($programa_id));
        $description = wp_kses_post($data['description'] ?? '');
        $price = (int) preg_replace('/[^0-9]/', '', (string) ($data['price'] ?? '0'));
        $thumbnail_id = (int) ($data['thumbnail_id'] ?? 0);

        if ($price_type !== 'closed') {
            if ($product_id) {
                wp_trash_post($product_id);
                delete_post_meta($programa_id, '_pcg_woo_product_id');
            }
            return '';
        }

        $group_ids = array_values(array_unique(array_filter(array_map('absint', (array) $group_ids))));

        $post_data = [
            'post_title' => $title,
            'post_content' => $description,
            'post_excerpt' => '',
            'post_status' => $post_status,
            'post_type' => 'product',
        ];

        if ($product_id && get_post($product_id)) {
            $post_data['ID'] = $product_id;
            wp_update_post($post_data);
        } else {
            $product_id = (int) wp_insert_post($post_data);
            if ($product_id > 0 && !is_wp_error($product_id)) {
                update_post_meta($programa_id, '_pcg_woo_product_id', $product_id);
            }
        }

        if (!$product_id || is_wp_error($product_id)) {
            return '';
        }

        wp_set_object_terms($product_id, 'course', 'product_type');
        $this->ensure_required_product_category($product_id);

        update_post_meta($product_id, '_regular_price', $price);
        update_post_meta($product_id, '_price', $price);
        if ($thumbnail_id > 0) {
            update_post_meta($product_id, '_thumbnail_id', $thumbnail_id);
        }

        $author_id = (int) get_post_field('post_author', $programa_id);
        if ($author_id > 0) {
            update_post_meta($product_id, 'product_owner', $author_id);
        }

        // Store program linkage and sync groups for LearnDash WooCommerce enrollment.
        update_post_meta($product_id, '_pcg_related_program', $programa_id);
        update_post_meta($product_id, '_related_group', $group_ids);

        return get_permalink($product_id);
    }

    private function ensure_required_product_category($product_id): void
    {
        if (!taxonomy_exists('product_cat')) {
            return;
        }

        $term = get_term_by('slug', self::REQUIRED_PRODUCT_CATEGORY_SLUG, 'product_cat');
        if (!$term || is_wp_error($term)) {
            $term = get_term_by('name', self::REQUIRED_PRODUCT_CATEGORY_NAME, 'product_cat');
        }

        if (!$term || is_wp_error($term)) {
            $inserted = wp_insert_term(self::REQUIRED_PRODUCT_CATEGORY_NAME, 'product_cat', [
                'slug' => self::REQUIRED_PRODUCT_CATEGORY_SLUG,
            ]);

            if (is_wp_error($inserted)) {
                error_log('[politeia-learning] Failed to ensure required product category "Cursos": ' . $inserted->get_error_message());
                return;
            }

            $term_id = (int) ($inserted['term_id'] ?? 0);
        } else {
            $term_id = (int) $term->term_id;
        }

        if ($term_id > 0) {
            // Append (do not overwrite) any existing categories.
            wp_set_object_terms($product_id, [$term_id], 'product_cat', true);
        }
    }

    public function handle_inclusion_snapshot_approved(string $container_type, int $container_id, int $snapshot_id): void
    {
        // Safety: only apply for supported container types.
        if (!in_array($container_type, ['group', 'program'], true) || $container_id <= 0 || $snapshot_id <= 0) {
            return;
        }

        $this->apply_inclusion_snapshot($container_type, $container_id, $snapshot_id);
    }

    private function apply_inclusion_snapshot(string $container_type, int $container_id, int $snapshot_id): void
    {
        $payload = PL_CC_Inclusion_Approvals::get_snapshot_payload($snapshot_id);
        if (!$payload) {
            return;
        }

        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $participants = is_array($payload['participants'] ?? null) ? $payload['participants'] : [];

        // Persist roles to shared roles table (active state).
        global $wpdb;
        $roles_table = $wpdb->prefix . 'politeia_course_roles';

        if ($container_type === 'group') {
            // Publish group.
            wp_update_post([
                'ID' => $container_id,
                'post_status' => 'publish',
            ]);

            if (function_exists('learndash_update_setting') && function_exists('learndash_set_group_enrolled_courses')) {
                $price_type = sanitize_text_field((string) ($data['price_type'] ?? 'free'));
                $price = (int) ($data['price'] ?? 0);
                $course_ids = array_values(array_unique(array_filter(array_map('absint', (array) ($data['course_ids'] ?? [])))));

                learndash_update_setting($container_id, 'group_price_type', $price_type);
                learndash_update_setting($container_id, 'group_price', $price);
                learndash_set_group_enrolled_courses($container_id, $course_ids);

                // Ensure Woo product published (if closed) and button URL points to it.
                $product_url = '';
                if ($price_type === 'closed') {
                    $product_url = $this->sync_group_to_woo_product($container_id, [
                        'title' => (string) ($data['title'] ?? get_the_title($container_id)),
                        'description' => (string) ($data['description'] ?? ''),
                        'price' => (string) $price,
                    ], $price_type, 'publish');
                }
                learndash_update_setting($container_id, 'custom_button_url', $product_url ? $product_url : '');

                $product_id = (int) get_post_meta($container_id, '_pcg_woo_product_id', true);
                if ($product_id) {
                    wp_update_post(['ID' => $product_id, 'post_status' => 'publish']);
                }
            }

            $wpdb->delete($roles_table, ['object_type' => 'group', 'object_id' => $container_id], ['%s', '%d']);
            foreach ($participants as $p) {
                $user_id = (int) ($p['user_id'] ?? 0);
                $role_slug = sanitize_text_field((string) ($p['role_slug'] ?? ''));
                $wpdb->insert($roles_table, [
                    'object_type' => 'group',
                    'object_id' => $container_id,
                    'user_id' => $user_id,
                    'role_slug' => $role_slug,
                    'profit_percentage' => (float) ($p['profit_percentage'] ?? 0),
                    'role_description' => wp_kses_post((string) ($p['role_description'] ?? '')),
                ], ['%s', '%d', '%d', '%s', '%f', '%s']);

                $this->maybe_dual_write_partnership('group', (int) $container_id, (int) $user_id, (string) $role_slug);
            }

            return;
        }

        if ($container_type === 'program') {
            // Publish program.
            wp_update_post([
                'ID' => $container_id,
                'post_status' => 'publish',
            ]);

            $price_type = sanitize_text_field((string) ($data['price_type'] ?? 'open'));
            $price = (int) ($data['price'] ?? 0);
            $group_ids = array_values(array_unique(array_filter(array_map('absint', (array) ($data['group_ids'] ?? [])))));

            $price_display = $price > 0 ? ('$' . number_format($price, 0, '.', ',')) : __('Gratis', 'politeia-learning');
            update_post_meta($container_id, 'politeia_program_price', $price_display);
            update_post_meta($container_id, 'politeia_program_groups', wp_json_encode($group_ids));
            update_post_meta($container_id, '_pcg_program_access_mode', $price_type === 'closed' ? 'closed' : 'open');
            update_post_meta($container_id, '_pcg_program_price_type', $price_type);
            update_post_meta($container_id, '_pcg_program_price', $price);

            $product_url = '';
            if ($price_type === 'closed') {
                $product_url = $this->sync_program_to_woo_product($container_id, [
                    'title' => (string) ($data['title'] ?? get_the_title($container_id)),
                    'description' => (string) ($data['description'] ?? ''),
                    'price' => (string) $price,
                ], $price_type, $group_ids, 'publish');
            }
            update_post_meta($container_id, '_pcg_program_custom_button_url', $product_url ? $product_url : '');

            $product_id = (int) get_post_meta($container_id, '_pcg_woo_product_id', true);
            if ($product_id) {
                wp_update_post(['ID' => $product_id, 'post_status' => 'publish']);
            }

            $wpdb->delete($roles_table, ['object_type' => 'program', 'object_id' => $container_id], ['%s', '%d']);
            foreach ($participants as $p) {
                $user_id = (int) ($p['user_id'] ?? 0);
                $role_slug = sanitize_text_field((string) ($p['role_slug'] ?? ''));
                $wpdb->insert($roles_table, [
                    'object_type' => 'program',
                    'object_id' => $container_id,
                    'user_id' => $user_id,
                    'role_slug' => $role_slug,
                    'profit_percentage' => (float) ($p['profit_percentage'] ?? 0),
                    'role_description' => wp_kses_post((string) ($p['role_description'] ?? '')),
                ], ['%s', '%d', '%d', '%s', '%f', '%s']);

                $this->maybe_dual_write_partnership('program', (int) $container_id, (int) $user_id, (string) $role_slug);
            }
        }
    }

    private function process_course_content($course_id, $content_list)
    {
        $post_type = $course_id > 0 ? (string) get_post_type($course_id) : '';
        if ($post_type === 'learni_course') {
            $this->process_learni_course_content((int) $course_id, (array) $content_list);
            return;
        }

        // Cleanup existing lessons to avoid duplicates on every save
        $existing_lessons = get_posts([
            'post_type' => 'sfwd-lessons',
            'posts_per_page' => -1,
            'meta_query' => [
                [
                    'key' => 'course_id',
                    'value' => $course_id,
                ]
            ]
        ]);

        foreach ($existing_lessons as $old_lesson) {
            wp_delete_post($old_lesson->ID, true);
        }

        if (!class_exists('LearnDash_Settings_Section')) {
            return; // LD not active or not loaded
        }

        $steps_h = []; // Hierarchy
        $steps_count = 0;

        foreach ($content_list as $index => $item) {
            $type = $item['type'];
            $title = sanitize_text_field($item['title']);

            if ($type === 'lesson') {
                $video_url = sanitize_text_field($item['video_url'] ?? '');
                $available_date = sanitize_text_field($item['available_date'] ?? '');

                // Create/Update Lesson
                $lesson_id = wp_insert_post([
                    'post_title' => $title,
                    'post_status' => 'publish',
                    'post_type' => 'sfwd-lessons',
                    'menu_order' => $index
                ]);

                if (!is_wp_error($lesson_id)) {
                    update_post_meta($lesson_id, 'course_id', $course_id);

                    // LearnDash Lesson Settings
                    $lesson_settings = [
                        'sfwd-lessons_course' => $course_id,
                        'sfwd-lessons_lesson_video_url' => $video_url,
                        'sfwd-lessons_lesson_video_enabled' => !empty($video_url) ? 'on' : '',
                    ];

                    if (!empty($available_date)) {
                        $timestamp = strtotime($available_date);
                        if ($timestamp) {
                            $lesson_settings['sfwd-lessons_visible_after_specific_date'] = $timestamp;
                        }
                    }

                    update_post_meta($lesson_id, '_sfwd-lessons', $lesson_settings);

                    $steps_h['sfwd-lessons'] = $steps_h['sfwd-lessons'] ?? [];
                    $steps_h['sfwd-lessons'][$lesson_id] = [
                        'sfwd-topic' => [],
                        'sfwd-quiz' => []
                    ];
                    $steps_count++;
                }
            } else if ($type === 'section') {
                // LearnDash sections are virtual in the steps array
                $section_key = 'section-' . $index;
                $steps_h[$section_key] = [
                    'title' => $title,
                    'type' => 'section'
                ];
            }
        }

        // Update Course Builder Steps (Modern LD)
        $course_steps = [
            'steps' => [
                'h' => $steps_h
            ],
            'course_id' => $course_id,
            'version' => '4.23.0',
            'empty' => empty($steps_h),
            'course_builder_enabled' => true,
            'course_shared_steps_enabled' => true,
            'steps_count' => $steps_count
        ];

        update_post_meta($course_id, 'ld_course_steps', $course_steps);
    }

    private function process_learni_course_content(int $course_id, array $content_list): void
    {
        if ($course_id <= 0) {
            return;
        }

        global $wpdb;
        if (!$wpdb) {
            return;
        }

        // Delete existing outline + linked lessons (Center editor rewrites the outline wholesale).
        $items_table = $wpdb->prefix . 'learni_course_items';
        $existing_lesson_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT item_ref_id FROM {$items_table} WHERE course_post_id = %d AND item_type = %s",
                $course_id,
                'lesson'
            )
        );
        foreach (array_values(array_unique(array_map('absint', (array) $existing_lesson_ids))) as $lesson_id) {
            if ($lesson_id > 0 && get_post_type($lesson_id) === 'learni_lesson') {
                wp_delete_post($lesson_id, true);
            }
        }
        $wpdb->delete($items_table, ['course_post_id' => $course_id], ['%d']);

        $sort = 0;
        foreach ($content_list as $item) {
            $type = isset($item['type']) ? (string) $item['type'] : '';
            $title = isset($item['title']) ? sanitize_text_field((string) $item['title']) : '';
            if ($type === '' || $title === '') {
                $sort++;
                continue;
            }

            if ($type === 'section') {
                $wpdb->insert(
                    $items_table,
                    [
                        'course_post_id' => $course_id,
                        'item_type' => 'header',
                        'item_ref_id' => 0,
                        'label' => $title,
                        'sort_order' => $sort,
                        'is_preview' => 0,
                        'created_at' => current_time('mysql'),
                    ],
                    ['%d', '%s', '%d', '%s', '%d', '%d', '%s']
                );
                $sort++;
                continue;
            }

            if ($type !== 'lesson') {
                $sort++;
                continue;
            }

            $video_url = isset($item['video_url']) ? esc_url_raw((string) $item['video_url']) : '';
            $available_date = isset($item['available_date']) ? sanitize_text_field((string) $item['available_date']) : '';

            $lesson_id = wp_insert_post(
                [
                    'post_title' => $title,
                    'post_status' => 'publish',
                    'post_type' => 'learni_lesson',
                    'post_author' => get_current_user_id(),
                    'menu_order' => $sort,
                ],
                true
            );

            if (is_wp_error($lesson_id) || (int) $lesson_id <= 0) {
                $sort++;
                continue;
            }

            update_post_meta((int) $lesson_id, 'learni_video_url', $video_url);
            update_post_meta((int) $lesson_id, 'learni_available_at', $available_date);

            $wpdb->insert(
                $items_table,
                [
                    'course_post_id' => $course_id,
                    'item_type' => 'lesson',
                    'item_ref_id' => (int) $lesson_id,
                    'label' => '',
                    'sort_order' => $sort,
                    'is_preview' => 0,
                    'created_at' => current_time('mysql'),
                ],
                ['%d', '%s', '%d', '%s', '%d', '%d', '%s']
            );

            $sort++;
        }
    }

    public function handle_get_my_courses()
    {
        check_ajax_referer('pcg_creator_nonce', 'nonce');

        $args = [
            'post_type' => 'learni_course',
            'post_status' => ['publish', 'draft'],
            'author' => get_current_user_id(),
            'posts_per_page' => -1,
            'orderby' => 'date',
            'order' => 'DESC'
        ];

        $courses = get_posts($args);
        $data = [];

        foreach ($courses as $post) {
            $thumbnail_url = get_the_post_thumbnail_url($post->ID, 'medium');
            $numeric_price = (int) (float) get_post_meta($post->ID, 'learni_price', true);
            $price = $numeric_price > 0 ? ('$' . number_format($numeric_price, 0, '.', ',')) : __('Gratis', 'politeia-learning');

            // Count lessons using Learni outline table.
            global $wpdb;
            $lesson_count = 0;
            if ($wpdb) {
                $lesson_count = (int) $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT COUNT(*) FROM {$wpdb->prefix}learni_course_items WHERE course_post_id = %d AND item_type = %s",
                        (int) $post->ID,
                        'lesson'
                    )
                );
            }

            $data[] = [
                'id' => $post->ID,
                'title' => $post->post_title,
                'thumbnail_url' => $thumbnail_url ? $thumbnail_url : '',
                'price' => $price,
                'lesson_count' => $lesson_count,
                'status' => $post->post_status,
            ];
        }

        wp_send_json_success($data);
    }

    public function handle_get_published_courses()
    {
        check_ajax_referer('pcg_creator_nonce', 'nonce');

        $args = [
            'post_type' => 'sfwd-courses',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'date',
            'order' => 'DESC',
        ];

        $courses = get_posts($args);
        $data = [];

        foreach ($courses as $post) {
            $author_id = (int) $post->post_author;
            $author = get_userdata($author_id);
            $author_email = $author ? (string) $author->user_email : '';
            $data[] = [
                'id' => $post->ID,
                'title' => $post->post_title,
                'author_id' => $author_id,
                'author_name' => get_the_author_meta('display_name', $author_id),
                'author_avatar' => get_avatar_url($author_id, ['size' => 64]),
                'author_email' => $author_email,
            ];
        }

        wp_send_json_success($data);
    }

    public function handle_get_my_specializations()
    {
        check_ajax_referer('pcg_creator_nonce', 'nonce');

        $user_id = get_current_user_id();

        // Include pending approvals where the current user is a required participant.
        global $wpdb;
        $pending_group_ids = [];
        $approvals_table = $wpdb->prefix . PL_CC_Inclusion_Approvals::APPROVALS_TABLE;
        $snapshots_table = $wpdb->prefix . PL_CC_Inclusion_Approvals::SNAPSHOTS_TABLE;
        $pending_group_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT DISTINCT s.container_id
                 FROM {$approvals_table} a
                 INNER JOIN {$snapshots_table} s ON s.id = a.snapshot_id
                 WHERE a.approver_user_id = %d
                   AND a.status = %s
                   AND s.status = %s
                   AND s.container_type = %s",
                $user_id,
                'pending',
                PL_CC_Inclusion_Approvals::STATUS_PENDING,
                'group'
            )
        );
        $pending_group_ids = array_values(array_unique(array_filter(array_map('absint', (array) $pending_group_ids))));

        // Include groups where the current user is an approved participant (active roles table).
        $participant_group_ids = [];
        $roles_table = $wpdb->prefix . 'politeia_course_roles';
        $participant_group_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT DISTINCT object_id
                 FROM {$roles_table}
                 WHERE object_type = %s AND user_id = %d",
                'group',
                $user_id
            )
        );
        $participant_group_ids = array_values(array_unique(array_filter(array_map('absint', (array) $participant_group_ids))));

        $author_group_ids = get_posts([
            'post_type' => 'groups',
            'post_status' => ['publish', 'draft'],
            'author' => $user_id,
            'posts_per_page' => -1,
            'fields' => 'ids',
        ]);

        $leader_group_ids = [];
        if (function_exists('learndash_get_administrators_group_ids')) {
            $leader_group_ids = learndash_get_administrators_group_ids($user_id);
        }

        $group_ids = array_values(array_unique(array_filter(array_map('absint', array_merge((array) $author_group_ids, (array) $leader_group_ids, (array) $pending_group_ids, (array) $participant_group_ids)))));

        if (empty($group_ids)) {
            wp_send_json_success([]);
        }

        // Important: avoid WP_Query permission filtering for drafts authored by others.
        $groups = [];
        foreach ($group_ids as $gid) {
            $p = get_post($gid);
            if (!$p || $p->post_type !== 'groups') {
                continue;
            }
            if (!in_array($p->post_status, ['publish', 'draft'], true)) {
                continue;
            }
            $groups[] = $p;
        }
        usort($groups, static function ($a, $b) {
            return strcmp((string) $b->post_date, (string) $a->post_date);
        });

        $data = [];

        foreach ($groups as $group) {
            $thumbnail_url = get_the_post_thumbnail_url($group->ID, 'medium');

            $course_count = 0;
            $course_titles = [];
            if (function_exists('learndash_group_enrolled_courses')) {
                $course_ids = learndash_group_enrolled_courses($group->ID);
                if (is_array($course_ids)) {
                    $course_count = count($course_ids);
                    if (!empty($course_ids)) {
                        $course_ids = array_values(array_filter(array_map('absint', $course_ids)));
                        foreach ($course_ids as $course_id) {
                            $title = get_the_title($course_id);
                            if (is_string($title) && $title !== '') {
                                $course_titles[] = $title;
                            }
                        }
                    }
                }
            }

            $author_id = (int) get_post_field('post_author', $group->ID);
            $can_delete = current_user_can('manage_options') || ($author_id === $user_id);
            $can_edit = $this->user_can_manage_group($group->ID, $user_id);
            $pending_snapshot_id = PL_CC_Inclusion_Approvals::get_pending_snapshot_id($group->ID);
            $pending_snapshot_status = '';
            if ($pending_snapshot_id > 0) {
                $snapshots_table = $wpdb->prefix . PL_CC_Inclusion_Approvals::SNAPSHOTS_TABLE;
                $pending_snapshot_status = (string) $wpdb->get_var($wpdb->prepare("SELECT status FROM {$snapshots_table} WHERE id = %d", $pending_snapshot_id));
            }

            $data[] = [
                'id' => $group->ID,
                'title' => $group->post_title,
                'thumbnail_url' => $thumbnail_url ? $thumbnail_url : '',
                'course_count' => $course_count,
                'permalink' => get_permalink($group->ID),
                'can_delete' => $can_delete,
                'can_edit' => $can_edit,
                'course_titles' => $course_titles,
                'post_status' => $group->post_status,
                'pending_snapshot_status' => $pending_snapshot_status,
                'is_pending_approval' => ($pending_snapshot_status === PL_CC_Inclusion_Approvals::STATUS_PENDING) || in_array((int) $group->ID, $pending_group_ids, true),
            ];
        }

        wp_send_json_success($data);
    }

    public function handle_get_published_specializations()
    {
        check_ajax_referer('pcg_creator_nonce', 'nonce');

        $args = [
            'post_type' => 'groups',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'date',
            'order' => 'DESC',
        ];

        $groups = get_posts($args);
        $data = [];

        foreach ($groups as $group) {
            $author_id = (int) get_post_field('post_author', $group->ID);
            $author = get_userdata($author_id);
            $author_email = $author ? (string) $author->user_email : '';
            $data[] = [
                'id' => $group->ID,
                'title' => $group->post_title,
                'author_id' => $author_id,
                'author_name' => get_the_author_meta('display_name', $author_id),
                'author_avatar' => get_avatar_url($author_id, ['size' => 64]),
                'author_email' => $author_email,
            ];
        }

        wp_send_json_success($data);
    }

    public function handle_save_specialization()
    {
        check_ajax_referer('pcg_creator_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => __('No autorizado.', 'politeia-learning')], 401);
        }

        if (!function_exists('learndash_update_setting') || !function_exists('learndash_set_group_enrolled_courses')) {
            wp_send_json_error(['message' => __('LearnDash no está activo.', 'politeia-learning')], 400);
        }

        $data = $_POST['group_data'] ?? [];
        if (empty($data) || !is_array($data)) {
            wp_send_json_error(['message' => __('No se han recibido datos.', 'politeia-learning')], 400);
        }

        $group_id = absint($data['id'] ?? 0);
        $title = sanitize_text_field($data['title'] ?? '');
        $description = wp_kses_post($data['description'] ?? '');
        $price_raw = sanitize_text_field($data['price'] ?? '');
        $course_ids = $data['course_ids'] ?? [];
        $teacher_ids = $data['teachers'] ?? [];
        $category_ids = $data['category_ids'] ?? [];
        $tag_ids = $data['tag_ids'] ?? [];

        if ($title === '') {
            wp_send_json_error(['message' => __('Ingresa un nombre para la especialización.', 'politeia-learning')], 400);
        }

        $numeric_price = (int) preg_replace('/[^0-9]/', '', $price_raw);
        // Use LearnDash "closed" access and route purchases through WooCommerce.
        $price_type = $numeric_price > 0 ? 'closed' : 'free';

        $post_data = [
            'post_title' => $title,
            'post_content' => $description,
            'post_status' => 'draft',
            'post_type' => 'groups',
            'post_author' => get_current_user_id(),
        ];

        if ($group_id > 0) {
            if (!$this->user_can_manage_group($group_id, get_current_user_id())) {
                wp_send_json_error(['message' => __('No autorizado.', 'politeia-learning')], 403);
            }

            $post_data['ID'] = $group_id;
            // Keep original author when updating.
            unset($post_data['post_author']);
            $group_id = wp_update_post($post_data, true);
        } else {
            $group_id = wp_insert_post($post_data, true);
        }

        if (is_wp_error($group_id)) {
            wp_send_json_error(['message' => $group_id->get_error_message()], 500);
        }

        // LearnDash access settings for group price.
        learndash_update_setting($group_id, 'group_price_type', $price_type);
        learndash_update_setting($group_id, 'group_price', $numeric_price);

        // Assign courses to group.
        if (!is_array($course_ids)) {
            $course_ids = [];
        }
        $course_ids = array_values(array_unique(array_filter(array_map('absint', $course_ids))));
        learndash_set_group_enrolled_courses($group_id, $course_ids);

        // Save learning categories/tags.
        $this->assign_learning_terms((int) $group_id, $category_ids, $tag_ids);

        // Build and submit snapshot for participant approvals.
        $participants = [];
        if (!empty($teacher_ids) && is_array($teacher_ids)) {
            foreach ($teacher_ids as $teacher) {
                $participants[] = [
                    'user_id' => intval($teacher['user_id'] ?? 0),
                    'role_slug' => sanitize_text_field((string) ($teacher['role_slug'] ?? '')),
                    'role_description' => wp_kses_post((string) ($teacher['role_description'] ?? '')),
                    'profit_percentage' => floatval($teacher['profit_percentage'] ?? 0),
                ];
            }
        }

        $creator_id = get_current_user_id();
        if (empty($participants)) {
            $participants[] = [
                'user_id' => $creator_id,
                'role_slug' => __('Autor principal', 'politeia-learning'),
                'role_description' => '',
                'profit_percentage' => 100,
            ];
        }

        $split_locked = !empty($data['split_locked']);

        // Ensure that all included course authors are participants.
        $required_user_ids = [];
        foreach ($course_ids as $cid) {
            $cid = absint($cid);
            if (!$cid || get_post_type($cid) !== 'sfwd-courses') {
                continue;
            }
            $required_user_ids[] = (int) get_post_field('post_author', $cid);
        }
        $required_user_ids[] = (int) get_current_user_id();
        $required_user_ids = array_values(array_unique(array_filter($required_user_ids)));

        $by_user = [];
        $pct_sum = 0.0;
        foreach ($participants as $p) {
            $uid = (int) ($p['user_id'] ?? 0);
            if ($uid <= 0) {
                continue;
            }
            $by_user[$uid] = $p;
            $pct_sum += (float) ($p['profit_percentage'] ?? 0);
        }

        foreach ($required_user_ids as $uid) {
            if (empty($by_user[$uid])) {
                $participants[] = [
                    'user_id' => $uid,
                    'role_slug' => $uid === $creator_id ? __('Autor principal', 'politeia-learning') : __('Profesor', 'politeia-learning'),
                    'role_description' => '',
                    'profit_percentage' => 0,
                ];
            }
        }

        // If not locked, distribute equally by default.
        if (!$split_locked) {
            $n = count($participants);
            $base = (int) floor(10000 / max(1, $n));
            $remainder = 10000 - ($base * $n);
            foreach ($participants as $i => $p) {
                $hund = $base + ($i === 0 ? $remainder : 0);
                $participants[$i]['profit_percentage'] = $hund / 100;
            }
        } else {
            // Locked split: ensure any required participants have at least 1% by taking from creator.
            $min_pct = 1.0;

            // Index participants by user.
            $indexed = [];
            foreach ($participants as $idx => $p) {
                $uid = (int) ($p['user_id'] ?? 0);
                if ($uid > 0) {
                    $indexed[$uid] = $idx;
                }
            }

            $creator_idx = $indexed[$creator_id] ?? null;
            if ($creator_idx === null) {
                wp_send_json_error(['message' => __('Falta el creador en la lista de participantes.', 'politeia-learning')], 400);
            }

            foreach ($required_user_ids as $uid) {
                if ($uid === $creator_id) {
                    continue;
                }

                $idx = $indexed[$uid] ?? null;
                if ($idx === null) {
                    continue;
                }

                $pct = (float) ($participants[$idx]['profit_percentage'] ?? 0);
                if ($pct > 0) {
                    continue;
                }

                $creator_pct = (float) ($participants[$creator_idx]['profit_percentage'] ?? 0);
                if ($creator_pct < $min_pct) {
                    $name = get_the_author_meta('display_name', $uid);
                    wp_send_json_error(['message' => sprintf(__('No hay porcentaje disponible para asignar a %s. Ajusta los porcentajes.', 'politeia-learning'), $name ? $name : (string) $uid)], 400);
                }

                $participants[$idx]['profit_percentage'] = $min_pct;
                $participants[$creator_idx]['profit_percentage'] = $creator_pct - $min_pct;
            }

            // Enforce "main author is remainder": creator gets 100 - sum(others).
            $sum_others = 0.0;
            foreach ($participants as $idx => $p) {
                if ($idx === $creator_idx) {
                    continue;
                }
                $sum_others += (float) ($p['profit_percentage'] ?? 0);
            }
            $remainder_pct = round(100 - $sum_others, 2);
            if ($remainder_pct < 0) {
                wp_send_json_error(['message' => __('La suma de porcentajes de los colaboradores supera el 100%. Ajusta los porcentajes.', 'politeia-learning')], 400);
            }
            $participants[$creator_idx]['profit_percentage'] = $remainder_pct;
        }

        // Validate final distribution.
        $pct_sum = 0.0;
        $by_user = [];
        foreach ($participants as $p) {
            $uid = (int) ($p['user_id'] ?? 0);
            if ($uid <= 0) {
                continue;
            }
            $by_user[$uid] = $p;
            $pct_sum += (float) ($p['profit_percentage'] ?? 0);
        }

        foreach ($required_user_ids as $uid) {
            if (empty($by_user[$uid]) || (float) ($by_user[$uid]['profit_percentage'] ?? 0) <= 0) {
                $name = get_the_author_meta('display_name', $uid);
                wp_send_json_error(['message' => sprintf(__('Debes incluir a %s como participante y asignarle un porcentaje mayor a 0.', 'politeia-learning'), $name ? $name : (string) $uid)], 400);
            }
        }

        if (abs($pct_sum - 100.0) > 0.01) {
            wp_send_json_error(['message' => __('La suma de porcentajes de participación debe ser 100%.', 'politeia-learning')], 400);
        }

        $payload = [
            'included' => array_map(static function ($cid) {
                return ['type' => 'course', 'id' => (int) $cid];
            }, $course_ids),
            'participants' => $participants,
            'split_locked' => $split_locked,
            'data' => [
                'title' => $title,
                'description' => $description,
                'price_type' => $price_type,
                'price' => $numeric_price,
                'course_ids' => $course_ids,
            ],
        ];

        $snapshot = PL_CC_Inclusion_Approvals::create_snapshot('group', $group_id, $creator_id, $payload);
        if (empty($snapshot['snapshot_id'])) {
            wp_send_json_error([
                'message' => sprintf(
                    /* translators: 1: DB error */
                    __('No se pudo crear la solicitud de aprobación. %s', 'politeia-learning'),
                    (string) ($snapshot['db_error'] ?? '')
                )
            ], 500);
        }

        // Keep product in draft until all participants approve.
        $product_url = '';
        if ($price_type === 'closed') {
            $product_url = $this->sync_group_to_woo_product($group_id, $data, $price_type, $snapshot['status'] === PL_CC_Inclusion_Approvals::STATUS_APPROVED ? 'publish' : 'draft');
        }
        learndash_update_setting($group_id, 'custom_button_url', ($snapshot['status'] === PL_CC_Inclusion_Approvals::STATUS_APPROVED && $product_url) ? $product_url : '');

        if ($snapshot['status'] === PL_CC_Inclusion_Approvals::STATUS_APPROVED) {
            $this->apply_inclusion_snapshot('group', $group_id, (int) $snapshot['snapshot_id']);
        }

        wp_send_json_success([
            'group_id' => $group_id,
            'permalink' => get_permalink($group_id),
            'product_url' => $product_url,
            'snapshot_status' => $snapshot['status'],
            'snapshot_id' => (int) $snapshot['snapshot_id'],
        ]);
    }

    public function handle_get_specialization_for_edit()
    {
        check_ajax_referer('pcg_creator_nonce', 'nonce');

        $group_id = absint($_POST['group_id'] ?? 0);
        if ($group_id <= 0) {
            wp_send_json_error(['message' => __('ID inválido.', 'politeia-learning')], 400);
        }

        if (!$this->user_can_manage_group($group_id, get_current_user_id())) {
            wp_send_json_error(['message' => __('No autorizado.', 'politeia-learning')], 403);
        }

        $post = get_post($group_id);
        if (!$post || $post->post_type !== 'groups') {
            wp_send_json_error(['message' => __('No encontrado.', 'politeia-learning')], 404);
        }

        $price = '';
        if (function_exists('learndash_get_setting')) {
            $price = (string) learndash_get_setting($group_id, 'group_price');
        }

        $course_ids = [];
        if (function_exists('learndash_group_enrolled_courses')) {
            $course_ids = learndash_group_enrolled_courses($group_id);
            if (!is_array($course_ids)) {
                $course_ids = [];
            }
        }
        $course_ids = array_values(array_unique(array_filter(array_map('absint', (array) $course_ids))));

        $included_authors = [];
        foreach ($course_ids as $cid) {
            if (!$cid || get_post_type($cid) !== 'sfwd-courses') {
                continue;
            }
            $author_id = (int) get_post_field('post_author', $cid);
            if ($author_id <= 0) {
                continue;
            }
            if (!isset($included_authors[$author_id])) {
                $u = get_userdata($author_id);
                $full_name = trim((string) ($u->first_name ?? '') . ' ' . (string) ($u->last_name ?? ''));
                if ($full_name === '' && $u) {
                    $full_name = (string) $u->display_name;
                }
                $included_authors[$author_id] = [
                    'id' => $author_id,
                    'name' => $full_name,
                    'email' => $u ? (string) $u->user_email : '',
                    'avatar' => get_avatar_url($author_id, ['size' => 64]),
                ];
            }
        }

        $teachers_data = [];
        $pending_snapshot_id = PL_CC_Inclusion_Approvals::get_pending_snapshot_id($group_id);
        $payload = $pending_snapshot_id ? PL_CC_Inclusion_Approvals::get_snapshot_payload($pending_snapshot_id) : null;
        $approvals_by_user = [];

        if ($pending_snapshot_id > 0) {
            global $wpdb;
            $approvals_table = $wpdb->prefix . PL_CC_Inclusion_Approvals::APPROVALS_TABLE;
            $rows = $wpdb->get_results($wpdb->prepare("SELECT approver_user_id, status FROM {$approvals_table} WHERE snapshot_id = %d", $pending_snapshot_id));
            foreach ((array) $rows as $row) {
                $approvals_by_user[(int) $row->approver_user_id] = (string) $row->status;
            }
        }

        if ($payload && ((int) ($payload['created_by'] ?? 0) === get_current_user_id() || current_user_can('manage_options'))) {
            foreach ((array) ($payload['participants'] ?? []) as $p) {
                $uid = (int) ($p['user_id'] ?? 0);
                if ($uid <= 0) {
                    continue;
                }
                $user = get_userdata($uid);
                if (!$user) {
                    continue;
                }
                $full_name = trim($user->first_name . ' ' . $user->last_name);
                if (empty($full_name)) {
                    $full_name = $user->display_name;
                }
                $teachers_data[] = [
                    'id' => $user->ID,
                    'name' => $full_name . ' (' . $user->user_email . ')',
                    'avatar' => get_avatar_url($user->ID, ['size' => 64]),
                    'role_slug' => (string) ($p['role_slug'] ?? ''),
                    'profit_percentage' => (float) ($p['profit_percentage'] ?? 0),
                    'role_description' => (string) ($p['role_description'] ?? ''),
                    'approval_status' => $uid === get_current_user_id() ? 'approved' : ($approvals_by_user[$uid] ?? 'approved'),
                ];
            }
        } else {
            $roles = $this->get_course_roles_for_object('group', (int) $group_id);

            foreach ((array) $roles as $role) {
                $user = get_userdata($role->user_id);
                if ($user) {
                    $full_name = trim($user->first_name . ' ' . $user->last_name);
                    if (empty($full_name)) {
                        $full_name = $user->display_name;
                    }

                    $teachers_data[] = [
                        'id' => $user->ID,
                        'name' => $full_name . ' (' . $user->user_email . ')',
                        'avatar' => get_avatar_url($user->ID, ['size' => 64]),
                        'role_slug' => $role->role_slug,
                        'profit_percentage' => $role->profit_percentage,
                        'role_description' => $role->role_description
                    ];
                }
            }
        }

        $product_url = '';
        if (function_exists('learndash_get_setting')) {
            $product_url = (string) learndash_get_setting($group_id, 'custom_button_url');
        }

        $meta_terms = $this->get_learning_terms_for_object((int) $group_id);

        wp_send_json_success([
            'id' => $post->ID,
            'title' => $post->post_title,
            'description' => $post->post_content,
            'price' => $price,
            'course_ids' => $course_ids,
            'category_ids' => $meta_terms['category_ids'],
            'tag_ids' => $meta_terms['tag_ids'],
            'tags' => $meta_terms['tags'],
            'included_authors' => array_values($included_authors),
            'teachers' => $teachers_data,
            'author_id' => $post->post_author,
            'author_name' => get_the_author_meta('display_name', $post->post_author),
            'author_avatar' => get_avatar_url($post->post_author, ['size' => 64]),
            'product_url' => $product_url,
            'pending_snapshot_id' => $pending_snapshot_id,
            'approval_statuses' => $approvals_by_user,
        ]);
    }

    public function handle_delete_specialization()
    {
        check_ajax_referer('pcg_creator_nonce', 'nonce');

        $group_id = absint($_POST['group_id'] ?? 0);
        if ($group_id <= 0) {
            wp_send_json_error(['message' => __('ID inválido.', 'politeia-learning')], 400);
        }

        $author_id = (int) get_post_field('post_author', $group_id);
        if (!current_user_can('manage_options') && $author_id !== get_current_user_id()) {
            wp_send_json_error(['message' => __('No autorizado.', 'politeia-learning')], 403);
        }

        if (function_exists('learndash_set_group_enrolled_courses')) {
            learndash_set_group_enrolled_courses($group_id, []);
        }

        // Delete linked WooCommerce product if any.
        $product_id = (int) get_post_meta($group_id, '_pcg_woo_product_id', true);
        if ($product_id) {
            wp_delete_post($product_id, true);
            delete_post_meta($group_id, '_pcg_woo_product_id');
        }

        // Delete roles entries for this group.
        global $wpdb;
        $roles_table = $wpdb->prefix . 'politeia_course_roles';
        $wpdb->delete($roles_table, ['object_type' => 'group', 'object_id' => $group_id], ['%s', '%d']);

        // Delete inclusion snapshots/approvals for this group.
        $snapshots_table = $wpdb->prefix . PL_CC_Inclusion_Approvals::SNAPSHOTS_TABLE;
        $approvals_table = $wpdb->prefix . PL_CC_Inclusion_Approvals::APPROVALS_TABLE;
        $snapshot_ids = $wpdb->get_col($wpdb->prepare("SELECT id FROM {$snapshots_table} WHERE container_type = %s AND container_id = %d", 'group', $group_id));
        if (!empty($snapshot_ids)) {
            $in = implode(',', array_map('absint', (array) $snapshot_ids));
            $wpdb->query("DELETE FROM {$approvals_table} WHERE snapshot_id IN ({$in})");
        }
        $wpdb->delete($snapshots_table, ['container_type' => 'group', 'container_id' => $group_id], ['%s', '%d']);

        wp_delete_post($group_id, true);
        wp_send_json_success(['message' => __('Especialización eliminada.', 'politeia-learning')]);
    }

    public function handle_get_my_programas()
    {
        check_ajax_referer('pcg_creator_nonce', 'nonce');

        $user_id = get_current_user_id();
        global $wpdb;

        // Own programs.
        $own_program_ids = get_posts([
            'post_type' => 'course_program',
            'post_status' => ['publish', 'draft'],
            'author' => $user_id,
            'posts_per_page' => -1,
            'fields' => 'ids',
        ]);

        // Pending approvals where the current user is a required participant.
        $pending_program_ids = [];
        $approvals_table = $wpdb->prefix . PL_CC_Inclusion_Approvals::APPROVALS_TABLE;
        $snapshots_table = $wpdb->prefix . PL_CC_Inclusion_Approvals::SNAPSHOTS_TABLE;
        $pending_program_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT DISTINCT s.container_id
                 FROM {$approvals_table} a
                 INNER JOIN {$snapshots_table} s ON s.id = a.snapshot_id
                 WHERE a.approver_user_id = %d
                   AND a.status = %s
                   AND s.status = %s
                   AND s.container_type = %s",
                $user_id,
                'pending',
                PL_CC_Inclusion_Approvals::STATUS_PENDING,
                'program'
            )
        );
        $pending_program_ids = array_values(array_unique(array_filter(array_map('absint', (array) $pending_program_ids))));

        // Include programs where the current user is an approved participant (active roles table).
        $participant_program_ids = [];
        $roles_table = $wpdb->prefix . 'politeia_course_roles';
        $participant_program_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT DISTINCT object_id
                 FROM {$roles_table}
                 WHERE object_type = %s AND user_id = %d",
                'program',
                $user_id
            )
        );
        $participant_program_ids = array_values(array_unique(array_filter(array_map('absint', (array) $participant_program_ids))));

        $program_ids = array_values(array_unique(array_filter(array_map('absint', array_merge((array) $own_program_ids, (array) $pending_program_ids, (array) $participant_program_ids)))));
        if (empty($program_ids)) {
            wp_send_json_success([]);
        }

        // Important: avoid WP_Query permission filtering for drafts authored by others.
        $programs = [];
        foreach ($program_ids as $pid) {
            $p = get_post($pid);
            if (!$p || $p->post_type !== 'course_program') {
                continue;
            }
            if (!in_array($p->post_status, ['publish', 'draft'], true)) {
                continue;
            }
            $programs[] = $p;
        }
        usort($programs, static function ($a, $b) {
            return strcmp((string) $b->post_date, (string) $a->post_date);
        });
        $data = [];

        foreach ($programs as $post) {
            $thumbnail_url = get_the_post_thumbnail_url($post->ID, 'medium');

            $raw_groups = get_post_meta($post->ID, 'politeia_program_groups', true);
            $decoded = [];
            if (is_string($raw_groups) && $raw_groups !== '') {
                $decoded = json_decode($raw_groups, true);
            } elseif (is_array($raw_groups)) {
                $decoded = $raw_groups;
            }

            $group_ids = [];
            if (is_array($decoded)) {
                $group_ids = array_values(array_unique(array_filter(array_map('absint', $decoded))));
            }

            $price = get_post_meta($post->ID, 'politeia_program_price', true);
            if (is_string($price)) {
                $price = trim($price);
            }

            $pending_snapshot_id = PL_CC_Inclusion_Approvals::get_pending_snapshot_id($post->ID);
            $pending_snapshot_status = '';
            if ($pending_snapshot_id > 0) {
                $snapshots_table = $wpdb->prefix . PL_CC_Inclusion_Approvals::SNAPSHOTS_TABLE;
                $pending_snapshot_status = (string) $wpdb->get_var($wpdb->prepare("SELECT status FROM {$snapshots_table} WHERE id = %d", $pending_snapshot_id));
            }
            $is_owner = ((int) $post->post_author === $user_id) || current_user_can('manage_options');

            $data[] = [
                'id' => $post->ID,
                'title' => $post->post_title,
                'thumbnail_url' => $thumbnail_url ? $thumbnail_url : '',
                'group_count' => count($group_ids),
                'price' => $price,
                'permalink' => get_permalink($post->ID),
                'can_delete' => $is_owner,
                'can_edit' => $is_owner,
                'post_status' => $post->post_status,
                'pending_snapshot_status' => $pending_snapshot_status,
                'is_pending_approval' => ($pending_snapshot_status === PL_CC_Inclusion_Approvals::STATUS_PENDING) || in_array((int) $post->ID, $pending_program_ids, true),
            ];
        }

        wp_send_json_success($data);
    }

    public function handle_save_programa()
    {
        check_ajax_referer('pcg_creator_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => __('No autorizado.', 'politeia-learning')], 401);
        }

        $data = $_POST['programa_data'] ?? [];
        if (empty($data) || !is_array($data)) {
            wp_send_json_error(['message' => __('No se han recibido datos.', 'politeia-learning')], 400);
        }

        $programa_id = absint($data['id'] ?? 0);
        $title = sanitize_text_field($data['title'] ?? '');
        $description = wp_kses_post($data['description'] ?? '');
        $price_raw = sanitize_text_field($data['price'] ?? '');
        $group_ids = $data['group_ids'] ?? [];
        $teacher_ids = $data['teachers'] ?? [];
        $category_ids = $data['category_ids'] ?? [];
        $tag_ids = $data['tag_ids'] ?? [];

        if ($title === '') {
            wp_send_json_error(['message' => __('Ingresa un nombre para el programa.', 'politeia-learning')], 400);
        }

        if (!is_array($group_ids)) {
            $group_ids = [];
        }
        $group_ids = array_values(array_unique(array_filter(array_map('absint', $group_ids))));

        $numeric_price = (int) preg_replace('/[^0-9]/', '', $price_raw);
        $price = $numeric_price > 0 ? ('$' . number_format($numeric_price, 0, '.', ',')) : __('Gratis', 'politeia-learning');
        $price_type = $numeric_price > 0 ? 'closed' : 'open';

        $post_data = [
            'post_title' => $title,
            'post_content' => $description,
            'post_status' => 'draft',
            'post_type' => 'course_program',
            'post_author' => get_current_user_id(),
        ];

        if ($programa_id > 0) {
            if (!$this->user_can_manage_programa($programa_id, get_current_user_id())) {
                wp_send_json_error(['message' => __('No autorizado.', 'politeia-learning')], 403);
            }

            $post_data['ID'] = $programa_id;
            unset($post_data['post_author']);
            $programa_id = wp_update_post($post_data, true);
        } else {
            $programa_id = wp_insert_post($post_data, true);
        }

        if (is_wp_error($programa_id)) {
            wp_send_json_error(['message' => $programa_id->get_error_message()], 500);
        }

        update_post_meta($programa_id, 'politeia_program_price', $price);
        update_post_meta($programa_id, 'politeia_program_groups', wp_json_encode($group_ids));
        update_post_meta($programa_id, '_pcg_program_access_mode', $price_type === 'closed' ? 'closed' : 'open');
        update_post_meta($programa_id, '_pcg_program_price_type', $price_type);
        update_post_meta($programa_id, '_pcg_program_price', $numeric_price);

        // Save learning categories/tags.
        $this->assign_learning_terms((int) $programa_id, $category_ids, $tag_ids);

        $participants = [];
        if (!empty($teacher_ids) && is_array($teacher_ids)) {
            foreach ($teacher_ids as $teacher) {
                $participants[] = [
                    'user_id' => intval($teacher['user_id'] ?? 0),
                    'role_slug' => sanitize_text_field((string) ($teacher['role_slug'] ?? '')),
                    'role_description' => wp_kses_post((string) ($teacher['role_description'] ?? '')),
                    'profit_percentage' => floatval($teacher['profit_percentage'] ?? 0),
                ];
            }
        }

        $creator_id = get_current_user_id();
        if (empty($participants)) {
            $participants[] = [
                'user_id' => $creator_id,
                'role_slug' => __('Autor principal', 'politeia-learning'),
                'role_description' => '',
                'profit_percentage' => 100,
            ];
        }

        $split_locked = !empty($data['split_locked']);

        // Ensure that all included group authors are participants.
        $required_user_ids = [];
        foreach ($group_ids as $gid) {
            $gid = absint($gid);
            if (!$gid || get_post_type($gid) !== 'groups') {
                continue;
            }
            $required_user_ids[] = (int) get_post_field('post_author', $gid);
        }
        $required_user_ids[] = (int) get_current_user_id();
        $required_user_ids = array_values(array_unique(array_filter($required_user_ids)));

        $by_user = [];
        $pct_sum = 0.0;
        foreach ($participants as $p) {
            $uid = (int) ($p['user_id'] ?? 0);
            if ($uid <= 0) {
                continue;
            }
            $by_user[$uid] = $p;
            $pct_sum += (float) ($p['profit_percentage'] ?? 0);
        }

        foreach ($required_user_ids as $uid) {
            if (empty($by_user[$uid])) {
                $participants[] = [
                    'user_id' => $uid,
                    'role_slug' => $uid === $creator_id ? __('Autor principal', 'politeia-learning') : __('Profesor', 'politeia-learning'),
                    'role_description' => '',
                    'profit_percentage' => 0,
                ];
            }
        }

        if (!$split_locked) {
            $n = count($participants);
            $base = (int) floor(10000 / max(1, $n));
            $remainder = 10000 - ($base * $n);
            foreach ($participants as $i => $p) {
                $hund = $base + ($i === 0 ? $remainder : 0);
                $participants[$i]['profit_percentage'] = $hund / 100;
            }
        } else {
            // Locked split: ensure any required participants have at least 1% by taking from creator.
            $min_pct = 1.0;

            $indexed = [];
            foreach ($participants as $idx => $p) {
                $uid = (int) ($p['user_id'] ?? 0);
                if ($uid > 0) {
                    $indexed[$uid] = $idx;
                }
            }

            $creator_idx = $indexed[$creator_id] ?? null;
            if ($creator_idx === null) {
                wp_send_json_error(['message' => __('Falta el creador en la lista de participantes.', 'politeia-learning')], 400);
            }

            foreach ($required_user_ids as $uid) {
                if ($uid === $creator_id) {
                    continue;
                }

                $idx = $indexed[$uid] ?? null;
                if ($idx === null) {
                    continue;
                }

                $pct = (float) ($participants[$idx]['profit_percentage'] ?? 0);
                if ($pct > 0) {
                    continue;
                }

                $creator_pct = (float) ($participants[$creator_idx]['profit_percentage'] ?? 0);
                if ($creator_pct < $min_pct) {
                    $name = get_the_author_meta('display_name', $uid);
                    wp_send_json_error(['message' => sprintf(__('No hay porcentaje disponible para asignar a %s. Ajusta los porcentajes.', 'politeia-learning'), $name ? $name : (string) $uid)], 400);
                }

                $participants[$idx]['profit_percentage'] = $min_pct;
                $participants[$creator_idx]['profit_percentage'] = $creator_pct - $min_pct;
            }

            // Enforce "main author is remainder": creator gets 100 - sum(others).
            $sum_others = 0.0;
            foreach ($participants as $idx => $p) {
                if ($idx === $creator_idx) {
                    continue;
                }
                $sum_others += (float) ($p['profit_percentage'] ?? 0);
            }
            $remainder_pct = round(100 - $sum_others, 2);
            if ($remainder_pct < 0) {
                wp_send_json_error(['message' => __('La suma de porcentajes de los colaboradores supera el 100%. Ajusta los porcentajes.', 'politeia-learning')], 400);
            }
            $participants[$creator_idx]['profit_percentage'] = $remainder_pct;
        }

        $pct_sum = 0.0;
        $by_user = [];
        foreach ($participants as $p) {
            $uid = (int) ($p['user_id'] ?? 0);
            if ($uid <= 0) {
                continue;
            }
            $by_user[$uid] = $p;
            $pct_sum += (float) ($p['profit_percentage'] ?? 0);
        }

        foreach ($required_user_ids as $uid) {
            if (empty($by_user[$uid]) || (float) ($by_user[$uid]['profit_percentage'] ?? 0) <= 0) {
                $name = get_the_author_meta('display_name', $uid);
                wp_send_json_error(['message' => sprintf(__('Debes incluir a %s como participante y asignarle un porcentaje mayor a 0.', 'politeia-learning'), $name ? $name : (string) $uid)], 400);
            }
        }

        if (abs($pct_sum - 100.0) > 0.01) {
            wp_send_json_error(['message' => __('La suma de porcentajes de participación debe ser 100%.', 'politeia-learning')], 400);
        }

        $payload = [
            'included' => array_map(static function ($gid) {
                return ['type' => 'group', 'id' => (int) $gid];
            }, $group_ids),
            'participants' => $participants,
            'split_locked' => $split_locked,
            'data' => [
                'title' => $title,
                'description' => $description,
                'price_type' => $price_type,
                'price' => $numeric_price,
                'group_ids' => $group_ids,
            ],
        ];

        $snapshot = PL_CC_Inclusion_Approvals::create_snapshot('program', $programa_id, $creator_id, $payload);
        if (empty($snapshot['snapshot_id'])) {
            wp_send_json_error([
                'message' => sprintf(
                    /* translators: 1: DB error */
                    __('No se pudo crear la solicitud de aprobación. %s', 'politeia-learning'),
                    (string) ($snapshot['db_error'] ?? '')
                )
            ], 500);
        }

        // Keep product in draft until all participants approve.
        $product_url = '';
        if ($price_type === 'closed') {
            $product_url = $this->sync_program_to_woo_product($programa_id, $data, $price_type, $group_ids, $snapshot['status'] === PL_CC_Inclusion_Approvals::STATUS_APPROVED ? 'publish' : 'draft');
        }
        update_post_meta($programa_id, '_pcg_program_custom_button_url', ($snapshot['status'] === PL_CC_Inclusion_Approvals::STATUS_APPROVED && $product_url) ? $product_url : '');

        if ($snapshot['status'] === PL_CC_Inclusion_Approvals::STATUS_APPROVED) {
            $this->apply_inclusion_snapshot('program', $programa_id, (int) $snapshot['snapshot_id']);
        }

        wp_send_json_success([
            'programa_id' => $programa_id,
            'permalink' => get_permalink($programa_id),
            'product_url' => $product_url,
            'snapshot_status' => $snapshot['status'],
            'snapshot_id' => (int) $snapshot['snapshot_id'],
        ]);
    }

    public function handle_get_programa_for_edit()
    {
        check_ajax_referer('pcg_creator_nonce', 'nonce');

        $programa_id = absint($_POST['programa_id'] ?? 0);
        if ($programa_id <= 0) {
            wp_send_json_error(['message' => __('ID inválido.', 'politeia-learning')], 400);
        }

        if (!$this->user_can_manage_programa($programa_id, get_current_user_id())) {
            wp_send_json_error(['message' => __('No autorizado.', 'politeia-learning')], 403);
        }

        $post = get_post($programa_id);
        if (!$post || $post->post_type !== 'course_program') {
            wp_send_json_error(['message' => __('No encontrado.', 'politeia-learning')], 404);
        }

        $price = (string) get_post_meta($programa_id, 'politeia_program_price', true);
        $price_numeric = preg_replace('/[^0-9]/', '', $price);

        $raw_groups = get_post_meta($programa_id, 'politeia_program_groups', true);
        $decoded = [];
        if (is_string($raw_groups) && $raw_groups !== '') {
            $decoded = json_decode($raw_groups, true);
        } elseif (is_array($raw_groups)) {
            $decoded = $raw_groups;
        }
        $group_ids = [];
        if (is_array($decoded)) {
            $group_ids = array_values(array_unique(array_filter(array_map('absint', $decoded))));
        }

        $included_authors = [];
        foreach ($group_ids as $gid) {
            if (!$gid || get_post_type($gid) !== 'groups') {
                continue;
            }
            $author_id = (int) get_post_field('post_author', $gid);
            if ($author_id <= 0) {
                continue;
            }
            if (!isset($included_authors[$author_id])) {
                $u = get_userdata($author_id);
                $full_name = trim((string) ($u->first_name ?? '') . ' ' . (string) ($u->last_name ?? ''));
                if ($full_name === '' && $u) {
                    $full_name = (string) $u->display_name;
                }
                $included_authors[$author_id] = [
                    'id' => $author_id,
                    'name' => $full_name,
                    'email' => $u ? (string) $u->user_email : '',
                    'avatar' => get_avatar_url($author_id, ['size' => 64]),
                ];
            }
        }

        $teachers_data = [];
        $pending_snapshot_id = PL_CC_Inclusion_Approvals::get_pending_snapshot_id($programa_id);
        $payload = $pending_snapshot_id ? PL_CC_Inclusion_Approvals::get_snapshot_payload($pending_snapshot_id) : null;
        $approvals_by_user = [];

        if ($pending_snapshot_id > 0) {
            global $wpdb;
            $approvals_table = $wpdb->prefix . PL_CC_Inclusion_Approvals::APPROVALS_TABLE;
            $rows = $wpdb->get_results($wpdb->prepare("SELECT approver_user_id, status FROM {$approvals_table} WHERE snapshot_id = %d", $pending_snapshot_id));
            foreach ((array) $rows as $row) {
                $approvals_by_user[(int) $row->approver_user_id] = (string) $row->status;
            }
        }

        if ($payload && ((int) ($payload['created_by'] ?? 0) === get_current_user_id() || current_user_can('manage_options'))) {
            foreach ((array) ($payload['participants'] ?? []) as $p) {
                $uid = (int) ($p['user_id'] ?? 0);
                if ($uid <= 0) {
                    continue;
                }
                $user = get_userdata($uid);
                if (!$user) {
                    continue;
                }
                $full_name = trim($user->first_name . ' ' . $user->last_name);
                if (empty($full_name)) {
                    $full_name = $user->display_name;
                }
                $teachers_data[] = [
                    'id' => $user->ID,
                    'name' => $full_name . ' (' . $user->user_email . ')',
                    'avatar' => get_avatar_url($user->ID, ['size' => 64]),
                    'role_slug' => (string) ($p['role_slug'] ?? ''),
                    'profit_percentage' => (float) ($p['profit_percentage'] ?? 0),
                    'role_description' => (string) ($p['role_description'] ?? ''),
                    'approval_status' => $uid === get_current_user_id() ? 'approved' : ($approvals_by_user[$uid] ?? 'approved'),
                ];
            }
        } else {
            $roles = $this->get_course_roles_for_object('program', (int) $programa_id);

            foreach ((array) $roles as $role) {
                $user = get_userdata($role->user_id);
                if ($user) {
                    $full_name = trim($user->first_name . ' ' . $user->last_name);
                    if (empty($full_name)) {
                        $full_name = $user->display_name;
                    }

                    $teachers_data[] = [
                        'id' => $user->ID,
                        'name' => $full_name . ' (' . $user->user_email . ')',
                        'avatar' => get_avatar_url($user->ID, ['size' => 64]),
                        'role_slug' => $role->role_slug,
                        'profit_percentage' => $role->profit_percentage,
                        'role_description' => $role->role_description
                    ];
                }
            }
        }

        $product_url = (string) get_post_meta($programa_id, '_pcg_program_custom_button_url', true);

        $meta_terms = $this->get_learning_terms_for_object((int) $programa_id);

        wp_send_json_success([
            'id' => $post->ID,
            'title' => $post->post_title,
            'description' => $post->post_content,
            'price' => $price_numeric,
            'group_ids' => $group_ids,
            'category_ids' => $meta_terms['category_ids'],
            'tag_ids' => $meta_terms['tag_ids'],
            'tags' => $meta_terms['tags'],
            'included_authors' => array_values($included_authors),
            'teachers' => $teachers_data,
            'author_id' => $post->post_author,
            'author_name' => get_the_author_meta('display_name', $post->post_author),
            'author_avatar' => get_avatar_url($post->post_author, ['size' => 64]),
            'product_url' => $product_url,
            'pending_snapshot_id' => $pending_snapshot_id,
            'approval_statuses' => $approvals_by_user,
        ]);
    }

    public function handle_delete_programa()
    {
        check_ajax_referer('pcg_creator_nonce', 'nonce');

        $programa_id = absint($_POST['programa_id'] ?? 0);
        if ($programa_id <= 0) {
            wp_send_json_error(['message' => __('ID inválido.', 'politeia-learning')], 400);
        }

        $author_id = (int) get_post_field('post_author', $programa_id);
        if (!current_user_can('manage_options') && $author_id !== get_current_user_id()) {
            wp_send_json_error(['message' => __('No autorizado.', 'politeia-learning')], 403);
        }

        // Delete linked WooCommerce product if any.
        $product_id = (int) get_post_meta($programa_id, '_pcg_woo_product_id', true);
        if ($product_id) {
            wp_delete_post($product_id, true);
            delete_post_meta($programa_id, '_pcg_woo_product_id');
        }

        // Delete roles entries for this program.
        global $wpdb;
        $roles_table = $wpdb->prefix . 'politeia_course_roles';
        $wpdb->delete($roles_table, ['object_type' => 'program', 'object_id' => $programa_id], ['%s', '%d']);

        // Delete inclusion snapshots/approvals for this program.
        $snapshots_table = $wpdb->prefix . PL_CC_Inclusion_Approvals::SNAPSHOTS_TABLE;
        $approvals_table = $wpdb->prefix . PL_CC_Inclusion_Approvals::APPROVALS_TABLE;
        $snapshot_ids = $wpdb->get_col($wpdb->prepare("SELECT id FROM {$snapshots_table} WHERE container_type = %s AND container_id = %d", 'program', $programa_id));
        if (!empty($snapshot_ids)) {
            $in = implode(',', array_map('absint', (array) $snapshot_ids));
            $wpdb->query("DELETE FROM {$approvals_table} WHERE snapshot_id IN ({$in})");
        }
        $wpdb->delete($snapshots_table, ['container_type' => 'program', 'container_id' => $programa_id], ['%s', '%d']);

        wp_delete_post($programa_id, true);
        wp_send_json_success(['message' => __('Programa eliminado.', 'politeia-learning')]);
    }

    public function handle_delete_course()
    {
        check_ajax_referer('pcg_creator_nonce', 'nonce');
        $course_id = intval($_POST['course_id'] ?? 0);

        if (
            $course_id <= 0
            || get_post_type($course_id) !== 'learni_course'
            || get_post_field('post_author', $course_id) != get_current_user_id()
        ) {
            wp_send_json_error(['message' => __('No autorizado.', 'politeia-learning')]);
        }

        // 1. Delete associated lessons + outline entries.
        global $wpdb;
        if ($wpdb) {
            $items_table = $wpdb->prefix . 'learni_course_items';
            $lesson_ids = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT item_ref_id FROM {$items_table} WHERE course_post_id = %d AND item_type = %s",
                    $course_id,
                    'lesson'
                )
            );
            foreach (array_values(array_unique(array_map('absint', (array) $lesson_ids))) as $lesson_id) {
                if ($lesson_id > 0 && get_post_type($lesson_id) === 'learni_lesson') {
                    wp_delete_post($lesson_id, true);
                }
            }
            $wpdb->delete($items_table, ['course_post_id' => $course_id], ['%d']);
        }

        // 2. Delete the course
        $product_id = get_post_meta($course_id, '_pcg_woo_product_id', true);
        if ($product_id) {
            wp_delete_post($product_id, true);
        }

        // Delete roles entries for this course.
        $roles_table = $wpdb->prefix . 'politeia_course_roles';
        $wpdb->delete($roles_table, ['object_type' => 'course', 'object_id' => $course_id], ['%s', '%d']);

        wp_delete_post($course_id, true);

        wp_send_json_success(['message' => __('Curso y producto asociado eliminados.', 'politeia-learning')]);
    }

    public function handle_get_course_for_edit()
    {
        check_ajax_referer('pcg_creator_nonce', 'nonce');
        $course_id = intval($_POST['course_id'] ?? 0);

        if (
            $course_id <= 0
            || get_post_type($course_id) !== 'learni_course'
            || get_post_field('post_author', $course_id) != get_current_user_id()
        ) {
            wp_send_json_error(['message' => __('No autorizado.', 'politeia-learning')]);
        }

        $post = get_post($course_id);
        $numeric_price = (int) (float) get_post_meta($course_id, 'learni_price', true);
        $thumbnail_id = get_post_thumbnail_id($course_id);
        $thumbnail_url = wp_get_attachment_url($thumbnail_id);

        $cover_meta_key = class_exists('\\Learni\\PostTypes\\Course') ? \Learni\PostTypes\Course::META_COVER_PHOTO_ID : 'pl_cover_photo_id';
        $cover_photo_id = get_post_meta($course_id, $cover_meta_key, true);
        $cover_photo_url = wp_get_attachment_url($cover_photo_id);

        $cert_meta_key = class_exists('\\Learni\\PostTypes\\Course') ? \Learni\PostTypes\Course::META_CERTIFICATE_ATTACHMENT_ID : 'learni_certificate_attachment_id';
        $certificate_attachment_id = (int) get_post_meta($course_id, $cert_meta_key, true);
        $certificate_url = $certificate_attachment_id > 0 ? wp_get_attachment_url($certificate_attachment_id) : '';

        $cert_title_key = class_exists('\\Learni\\PostTypes\\Course') ? \Learni\PostTypes\Course::META_CERTIFICATE_TITLE : 'pl_certificate_title';
        $cert_congrats_key = class_exists('\\Learni\\PostTypes\\Course') ? \Learni\PostTypes\Course::META_CERTIFICATE_CONGRATS : 'pl_certificate_congrats';
        $cert_claim_first_key = class_exists('\\Learni\\PostTypes\\Course') ? \Learni\PostTypes\Course::META_CERTIFICATE_CLAIM_FIRST : 'pl_certificate_claim_first';
        $cert_claim_final_key = class_exists('\\Learni\\PostTypes\\Course') ? \Learni\PostTypes\Course::META_CERTIFICATE_CLAIM_FINAL : 'pl_certificate_claim_final';
        $cert_claim_variation_key = class_exists('\\Learni\\PostTypes\\Course') ? \Learni\PostTypes\Course::META_CERTIFICATE_CLAIM_VARIATION : 'pl_certificate_claim_variation';
        $cert_logo_id_key = class_exists('\\Learni\\PostTypes\\Course') ? \Learni\PostTypes\Course::META_CERTIFICATE_LOGO_ATTACHMENT_ID : 'pl_certificate_logo_attachment_id';
        $cert_logo_align_key = class_exists('\\Learni\\PostTypes\\Course') ? \Learni\PostTypes\Course::META_CERTIFICATE_LOGO_ALIGN : 'pl_certificate_logo_align';
        $cert_signature_id_key = class_exists('\\Learni\\PostTypes\\Course') ? \Learni\PostTypes\Course::META_CERTIFICATE_SIGNATURE_ATTACHMENT_ID : 'pl_certificate_signature_attachment_id';
        $cert_signature_label_key = class_exists('\\Learni\\PostTypes\\Course') ? \Learni\PostTypes\Course::META_CERTIFICATE_SIGNATURE_LABEL : 'pl_certificate_signature_label';

        $certificate_title = (string) get_post_meta($course_id, $cert_title_key, true);
        $certificate_congrats = (string) get_post_meta($course_id, $cert_congrats_key, true);
        // Claims are always shown on certificates.
        $certificate_claim_first = true;
        $certificate_claim_final = true;
        $certificate_claim_variation = true;

        $certificate_logo_attachment_id = (int) get_post_meta($course_id, $cert_logo_id_key, true);
        $certificate_logo_url = $certificate_logo_attachment_id > 0 ? wp_get_attachment_url($certificate_logo_attachment_id) : '';
        $certificate_logo_align = 'left';

        $certificate_signature_attachment_id = (int) get_post_meta($course_id, $cert_signature_id_key, true);
        $certificate_signature_url = $certificate_signature_attachment_id > 0 ? wp_get_attachment_url($certificate_signature_attachment_id) : '';
        $certificate_signature_label = (string) get_post_meta($course_id, $cert_signature_label_key, true);

        $roles = $this->get_course_roles_for_object('course', (int) $course_id);
        $teachers_data = [];

        foreach ($roles as $role) {
            $user = get_userdata($role->user_id);
            if ($user) {
                $full_name = trim($user->first_name . ' ' . $user->last_name);
                if (empty($full_name)) {
                    $full_name = $user->display_name;
                }

                $teachers_data[] = [
                    'id' => $user->ID,
                    'name' => $full_name . ' (' . $user->user_email . ')',
                    'avatar' => get_avatar_url($user->ID, ['size' => 64]),
                    'role_slug' => $role->role_slug,
                    'profit_percentage' => $role->profit_percentage,
                    'role_description' => $role->role_description
                ];
            }
        }

        $content = [];
        global $wpdb;
        if ($wpdb) {
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT item_type, item_ref_id, label
                     FROM {$wpdb->prefix}learni_course_items
                     WHERE course_post_id = %d
                     ORDER BY sort_order ASC, id ASC",
                    $course_id
                ),
                ARRAY_A
            );

            foreach ((array) $rows as $row) {
                $type = isset($row['item_type']) ? (string) $row['item_type'] : '';
                if ($type === 'header') {
                    $content[] = [
                        'type' => 'section',
                        'title' => (string) ($row['label'] ?? ''),
                    ];
                    continue;
                }

                if ($type !== 'lesson') {
                    continue;
                }

                $lesson_id = isset($row['item_ref_id']) ? (int) $row['item_ref_id'] : 0;
                if ($lesson_id <= 0 || get_post_type($lesson_id) !== 'learni_lesson') {
                    continue;
                }

                $content[] = [
                    'type' => 'lesson',
                    'id' => $lesson_id,
                    'title' => get_the_title($lesson_id),
                    'video_url' => (string) get_post_meta($lesson_id, 'learni_video_url', true),
                    'available_date' => (string) get_post_meta($lesson_id, 'learni_available_at', true),
                ];
            }
        }

        $meta_terms = $this->get_learning_terms_for_object((int) $course_id);

        wp_send_json_success([
            'id' => $post->ID,
            'title' => $post->post_title,
            'description' => $post->post_content,
            'excerpt' => $post->post_excerpt,
            'status' => $post->post_status,
            'price' => (string) $numeric_price,
            'category_ids' => $meta_terms['category_ids'],
            'tag_ids' => $meta_terms['tag_ids'],
            'tags' => $meta_terms['tags'],
            'thumbnail_id' => $thumbnail_id,
            'thumbnail_url' => $thumbnail_url,
            'cover_photo_id' => $cover_photo_id,
            'cover_photo_url' => $cover_photo_url,
            'certificate_attachment_id' => $certificate_attachment_id,
            'certificate_url' => $certificate_url,
            'certificate_title' => $certificate_title,
            'certificate_congrats' => $certificate_congrats,
            'certificate_claim_first' => $certificate_claim_first,
            'certificate_claim_final' => $certificate_claim_final,
            'certificate_claim_variation' => $certificate_claim_variation,
            'certificate_logo_attachment_id' => $certificate_logo_attachment_id,
            'certificate_logo_url' => $certificate_logo_url,
            'certificate_logo_align' => $certificate_logo_align,
            'certificate_signature_attachment_id' => $certificate_signature_attachment_id,
            'certificate_signature_url' => $certificate_signature_url,
            'certificate_signature_label' => $certificate_signature_label,
            'permalink' => get_permalink($course_id),
            'progression' => get_post_meta($course_id, 'learni_linear_order', true) ? '' : 'on',
            'content' => $content,
            'teachers' => $teachers_data,
            'author_id' => $post->post_author,
            'author_name' => get_the_author_meta('display_name', $post->post_author),
            'author_avatar' => get_avatar_url($post->post_author, ['size' => 64])
        ]);
    }

    /**
     * Handles saving an "Escrito" (post).
     */
    public function handle_save_escrito()
    {
        check_ajax_referer('pcg_creator_nonce', 'nonce');

        $data = $_POST['escrito_data'] ?? [];
        if (empty($data)) {
            wp_send_json_error(['message' => 'No se han recibido datos.']);
        }

        $post_id = intval($data['id'] ?? 0);
        $title = sanitize_text_field($data['title']);
        $content = wp_kses_post($data['content']);
        $excerpt = wp_kses_post($data['excerpt'] ?? '');
        $thumbnail_id = intval($data['thumbnail_id'] ?? 0);
        $status = sanitize_text_field($data['status'] ?? 'publish');

        if (!in_array($status, ['draft', 'publish'])) {
            $status = 'publish';
        }

        $post_data = [
            'post_title' => $title,
            'post_content' => $content,
            'post_excerpt' => $excerpt,
            'post_status' => $status,
            'post_type' => 'post',
            'post_author' => get_current_user_id()
        ];

        if ($post_id > 0) {
            $post_data['ID'] = $post_id;
            $post_id = wp_update_post($post_data);
        } else {
            $post_id = wp_insert_post($post_data);
        }

        if (is_wp_error($post_id)) {
            wp_send_json_error(['message' => 'Error al guardar: ' . $post_id->get_error_message()]);
        }

        if ($thumbnail_id > 0) {
            set_post_thumbnail($post_id, $thumbnail_id);
        } else {
            delete_post_thumbnail($post_id);
        }

        wp_send_json_success([
            'escrito_id' => $post_id,
            'permalink' => get_permalink($post_id),
            'message' => 'Escrito publicado exitosamente.'
        ]);
    }

    /**
     * Get user's Escritos.
     */
    public function handle_get_my_escritos()
    {
        check_ajax_referer('pcg_creator_nonce', 'nonce');

        $args = [
            'post_type' => 'post',
            'post_status' => ['publish', 'draft'],
            'author' => get_current_user_id(),
            'posts_per_page' => -1,
            'orderby' => 'date',
            'order' => 'DESC'
        ];

        $posts = get_posts($args);
        $data = [];

        foreach ($posts as $post) {
            // Use a larger size for dashboard cards to avoid pixelation on retina/high-DPI screens.
            // Theme/CSS can still scale it down as needed.
            $thumbnail_url = get_the_post_thumbnail_url($post->ID, 'large');
            $data[] = [
                'id' => $post->ID,
                'title' => $post->post_title,
                'thumbnail_url' => $thumbnail_url ? $thumbnail_url : '',
                'date' => get_the_date('', $post->ID),
                'status' => $post->post_status,
                'permalink' => get_permalink($post->ID)
            ];
        }

        wp_send_json_success($data);
    }

    /**
     * Get a single Escrito for editing.
     */
    public function handle_get_escrito_for_edit()
    {
        check_ajax_referer('pcg_creator_nonce', 'nonce');

        $post_id = intval($_POST['escrito_id'] ?? 0);
        $post = get_post($post_id);

        if (!$post || $post->post_author != get_current_user_id()) {
            wp_send_json_error(['message' => 'Escrito no encontrado o no tienes permiso.']);
        }

        $thumbnail_id = get_post_thumbnail_id($post->ID);
        $thumbnail_url = get_the_post_thumbnail_url($post->ID, 'large');

        wp_send_json_success([
            'id' => $post->ID,
            'title' => $post->post_title,
            'content' => $post->post_content,
            'excerpt' => $post->post_excerpt,
            'status' => $post->post_status,
            'thumbnail_id' => $thumbnail_id,
            'thumbnail_url' => $thumbnail_url,
            'permalink' => get_permalink($post->ID)
        ]);
    }

    /**
     * Delete an Escrito.
     */
    public function handle_delete_escrito()
    {
        check_ajax_referer('pcg_creator_nonce', 'nonce');

        $post_id = intval($_POST['escrito_id'] ?? 0);
        $post = get_post($post_id);

        if (!$post || $post->post_author != get_current_user_id()) {
            wp_send_json_error(['message' => 'No tienes permiso para eliminar esto.']);
        }

        wp_trash_post($post_id);
        wp_send_json_success();
    }
    public function handle_save_profile_avatar()
    {
        check_ajax_referer('pcg_creator_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => __('No autorizado.', 'politeia-learning')]);
        }

        $image_data = isset($_POST['image_data']) ? (string) wp_unslash($_POST['image_data']) : '';
        if (empty($image_data)) {
            wp_send_json_error(['message' => 'No image data received.']);
        }

        // Decode base64
        if (preg_match('/^data:image\/(\w+);base64,/', $image_data, $typeMatch)) {
            $image_data = substr($image_data, strpos($image_data, ',') + 1);
        }
        $decoded_image = base64_decode($image_data);

        if (!$decoded_image) {
            wp_send_json_error(['message' => 'Invalid image data.']);
        }

        $user_id = get_current_user_id();
        $upload_dir = wp_upload_dir();
        if (!empty($upload_dir['error'])) {
            wp_send_json_error(['message' => $upload_dir['error']]);
        }

        $subdir = trailingslashit($upload_dir['basedir']) . 'politeia/profile-avatars/' . $user_id;
        if (!wp_mkdir_p($subdir)) {
            wp_send_json_error(['message' => 'Could not create avatar directory.']);
        }

        $extension = 'png';
        if (preg_match('/^data:image\/(\w+);base64,/', $image_data, $type_match)) {
            $extension = strtolower((string) $type_match[1]);
            if ($extension === 'jpeg') {
                $extension = 'jpg';
            }
            if (!in_array($extension, ['jpg', 'png', 'gif', 'webp'], true)) {
                $extension = 'png';
            }
        }

        $filename = sprintf('avatar-%d-%s.%s', $user_id, wp_generate_password(12, false, false), $extension);
        $file_path = trailingslashit($subdir) . $filename;

        if (file_put_contents($file_path, $decoded_image) === false) {
            wp_send_json_error(['message' => 'Could not save avatar file.']);
        }

        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        $filetype = wp_check_filetype($file_path, null);
        $attachment_id = wp_insert_attachment(
            [
                'post_mime_type' => $filetype['type'] ?: 'image/png',
                'post_title' => sprintf('Profile avatar %d', $user_id),
                'post_content' => '',
                'post_status' => 'inherit',
                'post_author' => $user_id,
            ],
            $file_path
        );

        if (is_wp_error($attachment_id) || !$attachment_id) {
            wp_send_json_error(['message' => 'Could not create avatar attachment.']);
        }

        $metadata = wp_generate_attachment_metadata((int) $attachment_id, $file_path);
        if (!is_wp_error($metadata) && !empty($metadata)) {
            wp_update_attachment_metadata((int) $attachment_id, $metadata);
        }

        $previous_attachment_id = absint(get_user_meta($user_id, '_pl_profile_avatar_attachment_id', true));
        if ($previous_attachment_id > 0 && $previous_attachment_id !== (int) $attachment_id) {
            wp_delete_attachment($previous_attachment_id, true);
        }

        update_user_meta($user_id, '_pl_profile_avatar_attachment_id', (int) $attachment_id);
        update_user_meta($user_id, '_pl_profile_avatar_url', wp_get_attachment_url((int) $attachment_id));

        wp_send_json_success([
            'url' => wp_get_attachment_image_url((int) $attachment_id, 'full') ?: wp_get_attachment_url((int) $attachment_id),
            'message' => __('Foto de perfil actualizada exitosamente.', 'politeia-learning')
        ]);
    }
}
