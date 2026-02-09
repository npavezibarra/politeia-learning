<?php
if (!defined('ABSPATH')) {
    exit;
}

class PCG_Metaboxes
{

    const PRICE_META_KEY = 'politeia_program_price';
    const GROUPS_META_KEY = 'politeia_program_groups';
    const AJAX_ACTION = 'pcg_search_groups';
    const NONCE_FIELD = 'pcg_program_details_nonce';

    public function __construct()
    {
        add_action('add_meta_boxes', [$this, 'register_metabox']);
        add_action('save_post', [$this, 'save_metabox']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('wp_ajax_' . self::AJAX_ACTION, [$this, 'ajax_search_groups']);
    }

    public function register_metabox()
    {
        add_meta_box(
            'pcg-program-details',
            __('Detalles del Programa Politeia', 'politeia-course-group'),
            [$this, 'render_metabox'],
            'course_program',
            'normal',
            'default'
        );
    }

    public function render_metabox($post)
    {
        wp_nonce_field('pcg_program_details_action', self::NONCE_FIELD);

        $price = get_post_meta($post->ID, self::PRICE_META_KEY, true);
        $groups = $this->get_saved_groups($post->ID);

        $group_ids = array_map('absint', array_keys($groups));
        ?>
        <div class="pcg-program-field components-base-control">
            <label for="pcg-program-price"><strong><?php esc_html_e('Precio', 'politeia-course-group'); ?></strong></label>
            <input type="number" id="pcg-program-price" name="<?php echo esc_attr(self::PRICE_META_KEY); ?>"
                value="<?php echo esc_attr($price); ?>" class="widefat" step="0.01" min="0" />
        </div>

        <div class="pcg-program-field components-base-control">
            <label
                for="pcg-program-groups-input"><strong><?php esc_html_e('Grupos', 'politeia-course-group'); ?></strong></label>
            <div class="pcg-groups-field">
                <div class="pcg-groups-tags tagchecklist" aria-live="polite">
                    <?php foreach ($groups as $group_id => $group_title): ?>
                        <span class="pcg-group-tag" data-group-id="<?php echo esc_attr($group_id); ?>"
                            data-group-title="<?php echo esc_attr($group_title); ?>">
                            <span class="pcg-group-tag__label"><?php echo esc_html($group_title); ?></span>
                            <button type="button" class="pcg-group-tag__remove"
                                aria-label="<?php esc_attr_e('Eliminar grupo', 'politeia-course-group'); ?>">&times;</button>
                        </span>
                    <?php endforeach; ?>
                </div>
                <input type="text" id="pcg-program-groups-input" class="pcg-groups-input"
                    placeholder="<?php esc_attr_e('Busca y selecciona grupos...', 'politeia-course-group'); ?>"
                    autocomplete="off" />
                <input type="hidden" class="pcg-groups-hidden" name="<?php echo esc_attr(self::GROUPS_META_KEY); ?>"
                    value='<?php echo esc_attr(wp_json_encode($group_ids)); ?>' />
                <div class="pcg-groups-suggestions"></div>
            </div>
        </div>
        <?php
    }

    public function save_metabox($post_id)
    {
        if (!isset($_POST[self::NONCE_FIELD]) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[self::NONCE_FIELD])), 'pcg_program_details_action')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (isset($_POST['post_type']) && 'course_program' === $_POST['post_type']) {
            if (!current_user_can('edit_post', $post_id)) {
                return;
            }
        } else {
            return;
        }

        if (isset($_POST[self::PRICE_META_KEY])) {
            $price_raw = wp_unslash($_POST[self::PRICE_META_KEY]);
            if ('' === $price_raw) {
                delete_post_meta($post_id, self::PRICE_META_KEY);
            } else {
                $price = sanitize_text_field($price_raw);
                update_post_meta($post_id, self::PRICE_META_KEY, $price);
            }
        } else {
            delete_post_meta($post_id, self::PRICE_META_KEY);
        }

        if (isset($_POST[self::GROUPS_META_KEY])) {
            $raw_groups = wp_unslash($_POST[self::GROUPS_META_KEY]);
            $decoded = json_decode($raw_groups, true);

            if (is_array($decoded)) {
                $sanitized = array_values(array_unique(array_map('absint', $decoded)));
                if (!empty($sanitized)) {
                    update_post_meta($post_id, self::GROUPS_META_KEY, wp_json_encode($sanitized));
                } else {
                    delete_post_meta($post_id, self::GROUPS_META_KEY);
                }
            } else {
                delete_post_meta($post_id, self::GROUPS_META_KEY);
            }
        } else {
            delete_post_meta($post_id, self::GROUPS_META_KEY);
        }
    }

    public function enqueue_assets($hook)
    {
        if ('post.php' !== $hook && 'post-new.php' !== $hook) {
            return;
        }

        $screen = get_current_screen();
        if (!$screen || 'course_program' !== $screen->post_type) {
            return;
        }

        wp_enqueue_style('pcg-metaboxes', PCG_CP_URL . 'assets/css/pcg-metaboxes.css', [], '1.0.0');
        wp_enqueue_script('pcg-groups-field', PCG_CP_URL . 'assets/js/pcg-groups-field.js', ['jquery'], '1.0.0', true);
        wp_localize_script('pcg-groups-field', 'pcgGroupsField', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('pcg_groups_search'),
            'action' => self::AJAX_ACTION,
            'labels' => [
                'remove' => __('Eliminar grupo', 'politeia-course-group'),
            ],
        ]);
    }

    public function ajax_search_groups()
    {
        check_ajax_referer('pcg_groups_search', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('No tienes permisos suficientes.', 'politeia-course-group'), 403);
        }

        $query = isset($_POST['q']) ? sanitize_text_field(wp_unslash($_POST['q'])) : '';

        $groups_query = new WP_Query([
            'post_type' => 'groups',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            's' => $query,
            'orderby' => 'title',
            'order' => 'ASC',
            'fields' => 'ids',
        ]);

        $results = [];

        if (!empty($groups_query->posts)) {
            foreach ($groups_query->posts as $group_id) {
                $results[] = [
                    'id' => $group_id,
                    'title' => get_the_title($group_id),
                ];
            }
        }

        wp_send_json_success($results);
    }

    private function get_saved_groups($post_id)
    {
        $raw = get_post_meta($post_id, self::GROUPS_META_KEY, true);

        if (empty($raw)) {
            return [];
        }

        $decoded = json_decode($raw, true);

        if (empty($decoded) || !is_array($decoded)) {
            return [];
        }

        $decoded = array_map('absint', $decoded);
        $decoded = array_filter($decoded);

        if (empty($decoded)) {
            return [];
        }

        $groups = get_posts([
            'post_type' => 'groups',
            'post__in' => $decoded,
            'posts_per_page' => -1,
            'orderby' => 'post__in',
        ]);

        $formatted = [];

        foreach ($groups as $group) {
            $formatted[$group->ID] = $group->post_title;
        }

        return $formatted;
    }
}
