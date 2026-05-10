<?php
/**
 * Handles Portfolio visibility settings and item selection.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PL_Member_Profile_Portfolio_Manager
{
    private const SETTINGS_TABLE = 'politeia_portfolio_settings';

    private static $instance = null;

    public static function get_instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct()
    {
        add_action('wp_ajax_pl_save_portfolio_settings', [$this, 'ajax_save_settings']);
        add_action('wp_ajax_pl_search_portfolio_items', [$this, 'ajax_search_items']);
        add_action('wp_ajax_pl_get_portfolio_items', [$this, 'ajax_get_items']);
        add_action('wp_ajax_pl_get_bulk_portfolio_items', [$this, 'ajax_get_bulk_items']);
    }

    /**
     * Get portfolio settings for a specific user and section.
     */
    public function get_settings($user_id, $section_id = null)
    {
        global $wpdb;
        $table = $wpdb->prefix . self::SETTINGS_TABLE;

        if ($section_id) {
            $row = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$table} WHERE user_id = %d AND section_id = %s",
                $user_id,
                $section_id
            ));
            
            if ($row) {
                $row->selected_ids = json_decode($row->selected_ids, true) ?: [];
                return $row;
            }

            return (object) [
                'user_id' => $user_id,
                'section_id' => $section_id,
                'is_private' => 0,
                'visibility_mode' => 'all',
                'selected_ids' => []
            ];
        }

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE user_id = %d",
            $user_id
        ));

        $settings = [];
        foreach ($results as $row) {
            $row->selected_ids = json_decode($row->selected_ids, true) ?: [];
            $settings[$row->section_id] = $row;
        }

        return $settings;
    }

    /**
     * AJAX: Save portfolio settings.
     */
    public function ajax_save_settings()
    {
        check_ajax_referer('pl_portfolio_nonce', 'nonce');

        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error(['message' => __('No autorizado', 'politeia-learning')]);
        }

        $section_id = sanitize_text_field($_POST['section_id'] ?? '');
        $is_private = !empty($_POST['is_private']) ? 1 : 0;
        $visibility_mode = sanitize_text_field($_POST['visibility_mode'] ?? 'all');
        $selected_ids = isset($_POST['selected_ids']) ? array_map('intval', (array)$_POST['selected_ids']) : [];

        if (empty($section_id)) {
            wp_send_json_error(['message' => __('ID de sección faltante', 'politeia-learning')]);
        }

        global $wpdb;
        $table = $wpdb->prefix . self::SETTINGS_TABLE;

        $result = $wpdb->replace(
            $table,
            [
                'user_id' => $user_id,
                'section_id' => $section_id,
                'is_private' => $is_private,
                'visibility_mode' => $visibility_mode,
                'selected_ids' => json_encode($selected_ids),
                'updated_at' => current_time('mysql')
            ],
            ['%d', '%s', '%d', '%s', '%s', '%s']
        );

        if ($result !== false) {
            wp_send_json_success(['message' => __('Configuración guardada correctamente', 'politeia-learning')]);
        } else {
            wp_send_json_error(['message' => __('Error al guardar la configuración', 'politeia-learning')]);
        }
    }

    /**
     * AJAX: Search for items (Courses, Posts, etc).
     */
    public function ajax_search_items()
    {
        check_ajax_referer('pl_portfolio_nonce', 'nonce');

        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error();
        }

        $term = sanitize_text_field($_POST['term'] ?? '');
        $type = sanitize_text_field($_POST['type'] ?? 'courses');

        $post_type = 'learni_course';
        if ($type === 'writings') {
            $post_type = 'post';
        } elseif ($type === 'specializations') {
            $post_type = 'learni_special';
        } elseif ($type === 'programs') {
            $post_type = 'learni_program';
        }

        $args = [
            'post_type' => $post_type,
            'post_status' => 'publish',
            'author' => $user_id,
            's' => $term,
            'posts_per_page' => 10
        ];

        $query = new WP_Query($args);
        $results = [];

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $results[] = [
                    'id' => get_the_ID(),
                    'title' => get_the_title()
                ];
            }
        }
        wp_reset_postdata();

        wp_send_json_success($results);
    }

    /**
     * AJAX: Get paginated items for listing.
     */
    public function ajax_get_items()
    {
        check_ajax_referer('pl_portfolio_nonce', 'nonce');

        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error();
        }

        $type = sanitize_text_field($_POST['type'] ?? 'courses');
        $paged = max(1, intval($_POST['paged'] ?? 1));
        $posts_per_page = 10;

        $post_type = 'learni_course';
        if ($type === 'writings') {
            $post_type = 'post';
        } elseif ($type === 'specializations') {
            $post_type = 'learni_special';
        } elseif ($type === 'programs') {
            $post_type = 'learni_program';
        }

        $args = [
            'post_type' => $post_type,
            'post_status' => 'publish',
            'author' => $user_id,
            'posts_per_page' => $posts_per_page,
            'paged' => $paged,
            'orderby' => 'date',
            'order' => 'DESC',
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
            'fields' => 'ids',
            'suppress_filters' => true,
            'no_found_rows' => false
        ];

        $query = new WP_Query($args);
        $items = [];

        if ($query->have_posts()) {
            foreach ($query->posts as $post_id) {
                $items[] = [
                    'id' => $post_id,
                    'title' => get_the_title($post_id)
                ];
            }
        }

        wp_send_json_success([
            'items' => $items,
            'total_pages' => $query->max_num_pages,
            'current_page' => $paged
        ]);
        wp_reset_postdata();
    }

    /**
     * AJAX: Get multiple sections in one request.
     */
    public function ajax_get_bulk_items()
    {
        $user_id = get_current_user_id();
        $sections = isset($_POST['sections']) ? (array)$_POST['sections'] : [];
        $results = [];

        foreach ($sections as $type) {
            $post_type = 'learni_course';
            if ($type === 'writings') $post_type = 'post';
            elseif ($type === 'specializations') $post_type = 'learni_special';
            elseif ($type === 'programs') $post_type = 'learni_program';

            $args = [
                'post_type' => $post_type,
                'post_status' => 'publish',
                'posts_per_page' => 10,
                'orderby' => 'date',
                'order' => 'DESC'
            ];
            
            if ($user_id) {
                $args['author'] = $user_id;
            }

            $posts = get_posts($args);
            $items = [];
            foreach ($posts as $post) {
                $items[] = [
                    'id' => $post->ID,
                    'title' => $post->post_title
                ];
            }

            $results[$type] = [
                'items' => $items,
                'total_pages' => 1,
                'current_page' => 1
            ];
        }

        wp_send_json_success($results);
    }
}
