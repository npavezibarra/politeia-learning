<?php
if (!defined('ABSPATH')) {
    exit;
}

class PL_Metaboxes
{

    const PRICE_META_KEY = 'politeia_program_price';
    const GROUPS_META_KEY = 'politeia_program_groups';
    const ACCESS_MODE_META_KEY = '_pcg_program_access_mode';
    const WOO_PRODUCT_META_KEY = '_pcg_woo_product_id';
    const PROGRAM_PRICE_TYPE_META_KEY = '_pcg_program_price_type';
    const PROGRAM_PRICE_NUMERIC_META_KEY = '_pcg_program_price';
    const PROGRAM_PRODUCT_URL_META_KEY = '_pcg_program_custom_button_url';
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
            __('Detalles del Programa Politeia', 'politeia-learning'),
            [$this, 'render_metabox'],
            'course_program',
            'normal',
            'default'
        );
    }

    public function render_metabox($post)
    {
        wp_nonce_field('pcg_program_details_action', self::NONCE_FIELD);

        $stored_price = (string) get_post_meta($post->ID, self::PRICE_META_KEY, true);
        $price_value = (int) preg_replace('/[^0-9]/', '', $stored_price);
        $groups = $this->get_saved_groups($post->ID);
        $access_mode = (string) get_post_meta($post->ID, self::ACCESS_MODE_META_KEY, true);
        if ($access_mode !== 'closed' && $access_mode !== 'open') {
            $access_mode = $price_value > 0 ? 'closed' : 'open';
        }

        $product_id = (int) get_post_meta($post->ID, self::WOO_PRODUCT_META_KEY, true);
        $product_url = $product_id ? get_edit_post_link($product_id, 'raw') : '';
        $product_front_url = $product_id ? get_permalink($product_id) : '';

        $group_ids = array_map('absint', array_keys($groups));
        ?>
        <div class="pcg-program-field components-base-control">
            <strong><?php esc_html_e('Acceso', 'politeia-learning'); ?></strong>
            <p class="description" style="margin-top:6px;">
                <?php esc_html_e('Define cómo los usuarios obtienen acceso al programa.', 'politeia-learning'); ?>
            </p>
            <fieldset style="margin-top:8px;">
                <label style="display:block;margin-bottom:6px;">
                    <input type="radio" name="<?php echo esc_attr(self::ACCESS_MODE_META_KEY); ?>" value="open" <?php checked('open', $access_mode); ?> />
                    <?php esc_html_e('Abierto', 'politeia-learning'); ?>
                </label>
                <label style="display:block;">
                    <input type="radio" name="<?php echo esc_attr(self::ACCESS_MODE_META_KEY); ?>" value="closed" <?php checked('closed', $access_mode); ?> />
                    <?php esc_html_e('Cerrado (requiere compra)', 'politeia-learning'); ?>
                </label>
            </fieldset>
        </div>

        <div class="pcg-program-field components-base-control">
            <label for="pcg-program-price"><strong><?php esc_html_e('Precio', 'politeia-learning'); ?></strong></label>
            <input type="number" id="pcg-program-price" name="<?php echo esc_attr(self::PRICE_META_KEY); ?>"
                value="<?php echo esc_attr($price_value); ?>" class="widefat" step="1" min="0" />
            <p class="description" style="margin-top:6px;">
                <?php esc_html_e('Si el acceso es “Cerrado”, se creará/actualizará un producto de WooCommerce asociado.', 'politeia-learning'); ?>
            </p>
            <?php if ($product_id && $product_url) : ?>
                <p class="description" style="margin-top:6px;">
                    <?php
                    printf(
                        /* translators: 1: product edit URL, 2: product front URL */
                        __('Producto asociado: <a href="%1$s">Editar</a> · <a href="%2$s" target="_blank" rel="noopener">Ver</a>', 'politeia-learning'),
                        esc_url($product_url),
                        esc_url($product_front_url)
                    );
                    ?>
                </p>
            <?php endif; ?>
        </div>

        <div class="pcg-program-field components-base-control">
            <label
                for="pcg-program-groups-input"><strong><?php esc_html_e('Grupos', 'politeia-learning'); ?></strong></label>
            <div class="pcg-groups-field">
                <div class="pcg-groups-tags tagchecklist" aria-live="polite">
                    <?php foreach ($groups as $group_id => $group_title): ?>
                        <span class="pcg-group-tag" data-group-id="<?php echo esc_attr($group_id); ?>"
                            data-group-title="<?php echo esc_attr($group_title); ?>">
                            <span class="pcg-group-tag__label"><?php echo esc_html($group_title); ?></span>
                            <button type="button" class="pcg-group-tag__remove"
                                aria-label="<?php esc_attr_e('Eliminar grupo', 'politeia-learning'); ?>">&times;</button>
                        </span>
                    <?php endforeach; ?>
                </div>
                <input type="text" id="pcg-program-groups-input" class="pcg-groups-input"
                    placeholder="<?php esc_attr_e('Busca y selecciona grupos...', 'politeia-learning'); ?>"
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

        $access_mode = 'open';
        if (isset($_POST[self::ACCESS_MODE_META_KEY])) {
            $access_mode = sanitize_text_field(wp_unslash($_POST[self::ACCESS_MODE_META_KEY]));
        }
        if ($access_mode !== 'closed') {
            $access_mode = 'open';
        }
        update_post_meta($post_id, self::ACCESS_MODE_META_KEY, $access_mode);

        $numeric_price = 0;
        if (isset($_POST[self::PRICE_META_KEY])) {
            $price_raw = wp_unslash($_POST[self::PRICE_META_KEY]);
            $numeric_price = (int) preg_replace('/[^0-9]/', '', (string) $price_raw);
        }

        $price_display = $numeric_price > 0 ? ('$' . number_format($numeric_price, 0, '.', ',')) : __('Gratis', 'politeia-learning');
        update_post_meta($post_id, self::PRICE_META_KEY, $price_display);
        update_post_meta($post_id, self::PROGRAM_PRICE_NUMERIC_META_KEY, $numeric_price);
        update_post_meta($post_id, self::PROGRAM_PRICE_TYPE_META_KEY, ($access_mode === 'closed' && $numeric_price > 0) ? 'closed' : 'open');

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

        // Sync WooCommerce product linkage for closed programs.
        $this->sync_program_product($post_id, $access_mode, $numeric_price);
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

        wp_enqueue_style('pcg-metaboxes', PL_CP_URL . 'assets/css/pcg-metaboxes.css', [], '1.0.0');
        wp_enqueue_script('pcg-groups-field', PL_CP_URL . 'assets/js/pcg-groups-field.js', ['jquery'], '1.0.0', true);
        wp_localize_script('pcg-groups-field', 'pcgGroupsField', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('pcg_groups_search'),
            'action' => self::AJAX_ACTION,
            'labels' => [
                'remove' => __('Eliminar grupo', 'politeia-learning'),
            ],
        ]);
    }

    public function ajax_search_groups()
    {
        check_ajax_referer('pcg_groups_search', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('No tienes permisos suficientes.', 'politeia-learning'), 403);
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

    private function sync_program_product(int $program_id, string $access_mode, int $numeric_price): void
    {
        if (!class_exists('WooCommerce')) {
            return;
        }

        $product_id = (int) get_post_meta($program_id, self::WOO_PRODUCT_META_KEY, true);
        $should_have_product = ($access_mode === 'closed' && $numeric_price > 0);

        if (!$should_have_product) {
            if ($product_id) {
                wp_trash_post($product_id);
                delete_post_meta($program_id, self::WOO_PRODUCT_META_KEY);
            }
            delete_post_meta($program_id, self::PROGRAM_PRODUCT_URL_META_KEY);
            return;
        }

        $group_ids = $this->get_program_group_ids($program_id);

        $post_data = [
            'post_title' => get_the_title($program_id),
            'post_content' => get_post_field('post_content', $program_id),
            'post_excerpt' => '',
            'post_status' => 'publish',
            'post_type' => 'product',
        ];

        if ($product_id && get_post($product_id)) {
            $post_data['ID'] = $product_id;
            wp_update_post($post_data);
        } else {
            $product_id = (int) wp_insert_post($post_data);
            if ($product_id > 0 && !is_wp_error($product_id)) {
                update_post_meta($program_id, self::WOO_PRODUCT_META_KEY, $product_id);
            }
        }

        if (!$product_id || is_wp_error($product_id)) {
            return;
        }

        // Product type used by LearnDash WooCommerce.
        wp_set_object_terms($product_id, 'course', 'product_type');

        // Make sure the "Cursos" category exists and assign it.
        $this->ensure_required_product_category($product_id);

        update_post_meta($product_id, '_regular_price', $numeric_price);
        update_post_meta($product_id, '_price', $numeric_price);

        $thumbnail_id = (int) get_post_thumbnail_id($program_id);
        if ($thumbnail_id > 0) {
            update_post_meta($product_id, '_thumbnail_id', $thumbnail_id);
        }

        $author_id = (int) get_post_field('post_author', $program_id);
        if ($author_id > 0) {
            update_post_meta($product_id, 'product_owner', $author_id);
        }

        update_post_meta($product_id, '_pcg_related_program', $program_id);
        update_post_meta($product_id, '_related_group', $group_ids);

        $front_url = get_permalink($product_id);
        update_post_meta($program_id, self::PROGRAM_PRODUCT_URL_META_KEY, $front_url ? $front_url : '');
    }

    private function get_program_group_ids(int $program_id): array
    {
        $raw = get_post_meta($program_id, self::GROUPS_META_KEY, true);

        if (empty($raw)) {
            return [];
        }

        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $raw = $decoded;
            } else {
                $maybe = maybe_unserialize($raw);
                if (is_array($maybe)) {
                    $raw = $maybe;
                }
            }
        }

        if (!is_array($raw)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map('absint', $raw))));
    }

    private function ensure_required_product_category(int $product_id): void
    {
        if (!taxonomy_exists('product_cat')) {
            return;
        }

        $required_slug = 'cursos';
        $required_name = 'Cursos';

        $term = get_term_by('slug', $required_slug, 'product_cat');
        if (!$term || is_wp_error($term)) {
            $term = get_term_by('name', $required_name, 'product_cat');
        }

        if (!$term || is_wp_error($term)) {
            $inserted = wp_insert_term($required_name, 'product_cat', ['slug' => $required_slug]);
            if (is_wp_error($inserted) || empty($inserted['term_id'])) {
                return;
            }
            $term_id = (int) $inserted['term_id'];
        } else {
            $term_id = (int) $term->term_id;
        }

        if ($term_id > 0) {
            wp_set_object_terms($product_id, [$term_id], 'product_cat', true);
        }
    }
}
