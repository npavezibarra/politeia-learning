<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Adds a "Product Owner" metabox to WooCommerce products.
 *
 * Stores the selected user id in post meta key: product_owner
 */
class PL_Woo_Product_Owner_Metabox
{
    const META_KEY = 'product_owner';
    const NONCE_ACTION = 'pl_product_owner_save';
    const NONCE_NAME = 'pl_product_owner_nonce';

    public static function init(): void
    {
        if (!is_admin()) {
            return;
        }

        add_action('add_meta_boxes', [__CLASS__, 'register_metabox']);
        add_action('save_post_product', [__CLASS__, 'save_metabox'], 10, 2);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
        add_action('wp_ajax_pl_woo_user_search', [__CLASS__, 'ajax_user_search']);
    }

    public static function register_metabox(): void
    {
        // Only add if WooCommerce exists.
        if (!class_exists('WooCommerce')) {
            return;
        }

        add_meta_box(
            'pl_product_owner',
            __('Propietario del producto', 'politeia-learning'),
            [__CLASS__, 'render_metabox'],
            'product',
            'side',
            'default'
        );
    }

    public static function enqueue_assets(string $hook): void
    {
        if (!class_exists('WooCommerce')) {
            return;
        }

        // Only on product edit/new screens.
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || $screen->post_type !== 'product') {
            return;
        }

        wp_enqueue_script('jquery-ui-autocomplete');

        $handle = 'pl-woo-product-owner';
        wp_enqueue_script(
            $handle,
            PL_URL . 'modules/woo/assets/js/admin-product-owner.js',
            ['jquery', 'jquery-ui-autocomplete'],
            '1.0.0',
            true
        );

        wp_localize_script($handle, 'PLWooProductOwner', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('pl_woo_user_search'),
            'noResults' => __('Sin resultados', 'politeia-learning'),
        ]);
    }

    public static function render_metabox(\WP_Post $post): void
    {
        $owner_id = (int) get_post_meta($post->ID, self::META_KEY, true);
        $owner_label = '';

        if ($owner_id) {
            $u = get_userdata($owner_id);
            if ($u) {
                $full = trim(($u->first_name ?? '') . ' ' . ($u->last_name ?? ''));
                $name = $full !== '' ? $full : ($u->display_name ?: $u->user_login);
                $email = $u->user_email ?: '';
                $owner_label = $email ? sprintf('%s <%s>', $name, $email) : $name;
            }
        }

        wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME);

        echo '<p style="margin-top:0;">' . esc_html__('Busca y selecciona un usuario (solo uno).', 'politeia-learning') . '</p>';

        echo '<input type="text" id="pl_product_owner_search" class="widefat" placeholder="' . esc_attr__('Escribe un nombre o email...', 'politeia-learning') . '" value="' . esc_attr($owner_label) . '" autocomplete="off" />';
        echo '<input type="hidden" id="pl_product_owner_id" name="pl_product_owner_id" value="' . esc_attr($owner_id) . '" />';

        echo '<p style="margin:8px 0 0 0;">';
        echo '<button type="button" class="button" id="pl_product_owner_clear">' . esc_html__('Quitar', 'politeia-learning') . '</button>';
        echo '</p>';
    }

    public static function save_metabox(int $post_id, \WP_Post $post): void
    {
        if (!isset($_POST[self::NONCE_NAME]) || !wp_verify_nonce((string) $_POST[self::NONCE_NAME], self::NONCE_ACTION)) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $owner_id = isset($_POST['pl_product_owner_id']) ? (int) $_POST['pl_product_owner_id'] : 0;

        if ($owner_id <= 0) {
            delete_post_meta($post_id, self::META_KEY);
            return;
        }

        // Ensure user exists.
        if (!get_userdata($owner_id)) {
            delete_post_meta($post_id, self::META_KEY);
            return;
        }

        update_post_meta($post_id, self::META_KEY, $owner_id);
    }

    public static function ajax_user_search(): void
    {
        if (!current_user_can('edit_products')) {
            wp_send_json_error(['message' => 'forbidden'], 403);
        }

        check_ajax_referer('pl_woo_user_search', 'nonce');

        $term = isset($_GET['term']) ? sanitize_text_field((string) $_GET['term']) : '';
        if ($term === '') {
            wp_send_json_success([]);
        }

        $query = new WP_User_Query([
            'number' => 20,
            'orderby' => 'display_name',
            'order' => 'ASC',
            'search' => '*' . $term . '*',
            'search_columns' => ['user_login', 'user_email', 'display_name'],
            'fields' => ['ID', 'user_login', 'user_email', 'display_name', 'first_name', 'last_name'],
        ]);

        $results = [];
        foreach ($query->get_results() as $u) {
            $results[(int) $u->ID] = $u;
        }

        // Expand matching a bit for first/last name without doing heavy LIKE queries.
        if (count($results) < 20) {
            $q2 = new WP_User_Query([
                'number' => 20,
                'fields' => ['ID', 'user_login', 'user_email', 'display_name', 'first_name', 'last_name'],
                'meta_query' => [
                    'relation' => 'OR',
                    [
                        'key' => 'first_name',
                        'value' => $term,
                        'compare' => 'LIKE',
                    ],
                    [
                        'key' => 'last_name',
                        'value' => $term,
                        'compare' => 'LIKE',
                    ],
                ],
            ]);

            foreach ($q2->get_results() as $u) {
                $results[(int) $u->ID] = $u;
            }
        }

        $payload = [];
        foreach (array_values($results) as $u) {
            $full = trim(($u->first_name ?? '') . ' ' . ($u->last_name ?? ''));
            $name = $full !== '' ? $full : ($u->display_name ?: $u->user_login);
            $label = $u->user_email ? sprintf('%s <%s>', $name, $u->user_email) : $name;

            $payload[] = [
                'id' => (int) $u->ID,
                'label' => $label,
                'value' => $label,
            ];
        }

        wp_send_json_success(array_slice($payload, 0, 20));
    }
}
