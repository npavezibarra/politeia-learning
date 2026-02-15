<?php
/**
 * Handles saving courses created via the Course Creator dashboard.
 */

if (!defined('ABSPATH'))
    exit;

class PL_CC_Course_Save_Handler
{

    public function __construct()
    {
        add_action('wp_ajax_pcg_save_course', [$this, 'handle_save_course']);
        add_action('wp_ajax_pcg_get_my_courses', [$this, 'handle_get_my_courses']);
        add_action('wp_ajax_pcg_delete_course', [$this, 'handle_delete_course']);
        add_action('wp_ajax_pcg_get_course_for_edit', [$this, 'handle_get_course_for_edit']);
        add_action('wp_ajax_pcg_upload_cropped_image', [$this, 'handle_upload_cropped_image']);
    }

    /**
     * Handles uploading and saving a cropped image from the cropper.
     */
    public function handle_upload_cropped_image()
    {
        check_ajax_referer('pcg_creator_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'No autorizado.']);
        }

        $image_data = $_POST['image_data'] ?? '';
        $type = $_POST['type'] ?? 'thumbnail'; // thumbnail or cover

        if (empty($image_data)) {
            wp_send_json_error(['message' => 'No image data received.']);
        }

        // Remove the data:image/png;base64, part
        if (strpos($image_data, 'base64,') !== false) {
            $image_data = substr($image_data, strpos($image_data, 'base64,') + 7);
        }

        $decoded_image = base64_decode($image_data);

        if (!$decoded_image) {
            wp_send_json_error(['message' => 'Invalid image data.']);
        }

        $upload_dir = wp_upload_dir();
        $filename = 'course-' . $type . '-' . get_current_user_id() . '-' . time() . '.png';
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
        $title = sanitize_text_field($data['title']);
        $description = wp_kses_post($data['description']);
        $excerpt = wp_kses_post($data['excerpt'] ?? '');
        $price = sanitize_text_field($data['price']);
        $thumbnail_id = intval($data['thumbnail_id'] ?? 0);
        $progression = sanitize_text_field($data['progression'] ?? '');
        $content_list = $data['content'] ?? [];

        // 1. Create or Update Course
        $post_data = [
            'post_title' => $title,
            'post_content' => $description,
            'post_excerpt' => $excerpt,
            'post_status' => 'publish',
            'post_type' => 'sfwd-courses',
            'post_author' => get_current_user_id()
        ];

        if ($course_id > 0) {
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

        // 2b. Set Cover Photo (BuddyBoss MultiPostThumbnails)
        $cover_photo_id = intval($data['cover_photo_id'] ?? 0);
        $cover_meta_key = 'sfwd-courses_course-cover-image_thumbnail_id';
        if ($cover_photo_id > 0) {
            update_post_meta($course_id, $cover_meta_key, $cover_photo_id);
        } else {
            delete_post_meta($course_id, $cover_meta_key);
        }

        // 3. Save Course Settings (Price etc)
        $formatted_price = '0';
        $price_type = 'free';

        if (!empty($price)) {
            $price_type = 'closed';
            // Clean input and format: 10000 -> $10,000
            $numeric_price = intval(preg_replace('/[^0-9]/', '', $price));
            $formatted_price = '$' . number_format($numeric_price, 0, '.', ',');
        }

        $course_settings = [
            'sfwd-courses_course_price_type' => $price_type,
            'sfwd-courses_course_price' => $formatted_price,
            'sfwd-courses_custom_button_url' => '',
            'sfwd-courses_course_materials' => '',
            'sfwd-courses_course_disable_lesson_progression' => $progression,
        ];
        update_post_meta($course_id, '_sfwd-courses', $course_settings);

        // 3. Handle Lessons and Sections
        $this->process_course_content($course_id, $content_list);

        // 4. Sync with WooCommerce
        $product_url = $this->sync_course_to_woo_product($course_id, $data, $price_type);

        // 5. Update Course with Product URL if applicable
        if ($price_type === 'closed' && !empty($product_url)) {
            $course_settings['sfwd-courses_custom_button_url'] = $product_url;
            update_post_meta($course_id, '_sfwd-courses', $course_settings);
        }

        wp_send_json_success([
            'course_id' => $course_id,
            'product_url' => $product_url,
            'permalink' => get_permalink($course_id),
            'message' => 'Curso guardado y sincronizado con la tienda exitosamente.'
        ]);
    }

    /**
     * Creates or updates a WooCommerce product linked to the course.
     */
    private function sync_course_to_woo_product($course_id, $data, $price_type)
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

        $post_data = [
            'post_title' => $title,
            'post_content' => $description,
            'post_excerpt' => $excerpt,
            'post_status' => 'publish',
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

        // WooCommerce Meta
        update_post_meta($product_id, '_regular_price', $price);
        update_post_meta($product_id, '_price', $price);
        update_post_meta($product_id, '_thumbnail_id', $thumbnail_id);

        // Link to LearnDash Course (Addon meta key discovered: _related_course)
        // Store as array to match expected serialized format a:1:{i:0;i:XX;}
        update_post_meta($product_id, '_related_course', [$course_id]);

        return get_permalink($product_id);
    }

    private function process_course_content($course_id, $content_list)
    {
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

    public function handle_get_my_courses()
    {
        check_ajax_referer('pcg_creator_nonce', 'nonce');

        $args = [
            'post_type' => 'sfwd-courses',
            'post_status' => 'publish',
            'author' => get_current_user_id(),
            'posts_per_page' => -1,
            'orderby' => 'date',
            'order' => 'DESC'
        ];

        $courses = get_posts($args);
        $data = [];

        foreach ($courses as $post) {
            $thumbnail_url = get_the_post_thumbnail_url($post->ID, 'medium');
            $price_settings = get_post_meta($post->ID, '_sfwd-courses', true);
            $price = $price_settings['sfwd-courses_course_price'] ?? '0';

            // Clean display if price is 0 or empty
            $numeric_price = intval(preg_replace('/[^0-9]/', '', $price));
            if ($numeric_price === 0) {
                $price = __('Gratis', 'politeia-learning');
            }

            // Count lessons using LD relationship
            $lessons = get_posts([
                'post_type' => 'sfwd-lessons',
                'meta_key' => 'course_id',
                'meta_value' => $post->ID,
                'posts_per_page' => -1,
                'fields' => 'ids'
            ]);

            $data[] = [
                'id' => $post->ID,
                'title' => $post->post_title,
                'thumbnail_url' => $thumbnail_url ? $thumbnail_url : PL_CC_URL . 'assets/img/default-course.jpg',
                'price' => $price,
                'lesson_count' => count($lessons)
            ];
        }

        wp_send_json_success($data);
    }

    public function handle_delete_course()
    {
        check_ajax_referer('pcg_creator_nonce', 'nonce');
        $course_id = intval($_POST['course_id'] ?? 0);

        if ($course_id <= 0 || get_post_field('post_author', $course_id) != get_current_user_id()) {
            wp_send_json_error(['message' => 'No autorizado.']);
        }

        // 1. Delete associated lessons
        $lessons = get_posts([
            'post_type' => 'sfwd-lessons',
            'meta_key' => 'course_id',
            'meta_value' => $course_id,
            'posts_per_page' => -1
        ]);

        foreach ($lessons as $lesson) {
            wp_delete_post($lesson->ID, true);
        }

        // 2. Delete the course
        $product_id = get_post_meta($course_id, '_pcg_woo_product_id', true);
        if ($product_id) {
            wp_delete_post($product_id, true);
        }

        wp_delete_post($course_id, true);

        wp_send_json_success(['message' => 'Curso y producto asociado eliminados.']);
    }

    public function handle_get_course_for_edit()
    {
        check_ajax_referer('pcg_creator_nonce', 'nonce');
        $course_id = intval($_POST['course_id'] ?? 0);

        if ($course_id <= 0 || get_post_field('post_author', $course_id) != get_current_user_id()) {
            wp_send_json_error(['message' => 'No autorizado.']);
        }

        $post = get_post($course_id);
        $price_settings = get_post_meta($course_id, '_sfwd-courses', true);
        $thumbnail_id = get_post_thumbnail_id($course_id);
        $thumbnail_url = wp_get_attachment_url($thumbnail_id);

        $cover_meta_key = 'sfwd-courses_course-cover-image_thumbnail_id';
        $cover_photo_id = get_post_meta($course_id, $cover_meta_key, true);
        $cover_photo_url = wp_get_attachment_url($cover_photo_id);

        // Get content structure (lessons/sections) from ld_course_steps
        $steps = get_post_meta($course_id, 'ld_course_steps', true);
        $content = [];

        if (!empty($steps['steps']['h'])) {
            $h = $steps['steps']['h'];

            // This is a simplified reconstruction of the flat list from the LD hierarchy
            // In a real LD steps array, it's more complex, but for our app, we'll try to reconstruct the order
            foreach ($h as $key => $val) {
                if (is_array($val) && isset($val['type']) && $val['type'] === 'section') {
                    $content[] = [
                        'type' => 'section',
                        'title' => $val['title']
                    ];
                } else if ($key === 'sfwd-lessons') {
                    foreach ($val as $lesson_id => $sub) {
                        $l_settings = get_post_meta($lesson_id, '_sfwd-lessons', true);
                        $v_url = $l_settings['sfwd-lessons_lesson_video_url'] ?? '';
                        $a_date = '';
                        if (isset($l_settings['sfwd-lessons_visible_after_specific_date']) && $l_settings['sfwd-lessons_visible_after_specific_date'] > 0) {
                            $a_date = date('Y-m-d', $l_settings['sfwd-lessons_visible_after_specific_date']);
                        }

                        $content[] = [
                            'type' => 'lesson',
                            'id' => $lesson_id,
                            'title' => get_the_title($lesson_id),
                            'video_url' => $v_url,
                            'available_date' => $a_date
                        ];
                    }
                }
            }
        }

        wp_send_json_success([
            'id' => $post->ID,
            'title' => $post->post_title,
            'description' => $post->post_content,
            'excerpt' => $post->post_excerpt,
            'price' => preg_replace('/[^0-9]/', '', $price_settings['sfwd-courses_course_price'] ?? '0'),
            'thumbnail_id' => $thumbnail_id,
            'thumbnail_url' => $thumbnail_url,
            'cover_photo_id' => $cover_photo_id,
            'cover_photo_url' => $cover_photo_url,
            'permalink' => get_permalink($course_id),
            'progression' => $price_settings['sfwd-courses_course_disable_lesson_progression'] ?? '',
            'content' => $content
        ]);
    }
}
