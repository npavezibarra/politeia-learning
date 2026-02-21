<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Adds a "Programa" selector in WooCommerce product edit screen.
 *
 * When set, the product will:
 * - store `_pcg_related_program` = program ID
 * - sync LearnDash WooCommerce meta `_related_group` to all groups associated to the program
 *
 * This allows program purchases to grant access to all linked LearnDash Groups.
 */
class PL_Woo_Product_Program_Selector
{
    const META_KEY = '_pcg_related_program';

    public static function init(): void
    {
        if (!class_exists('WooCommerce')) {
            return;
        }

        add_action('woocommerce_product_options_general_product_data', [__CLASS__, 'render_field']);
        add_action('woocommerce_admin_process_product_object', [__CLASS__, 'save_field']);
    }

    public static function render_field(): void
    {
        global $post;
        if (!$post || $post->post_type !== 'product') {
            return;
        }

        $current_program_id = (int) get_post_meta($post->ID, self::META_KEY, true);

        $options = [
            '' => __('— Sin programa —', 'politeia-learning'),
        ];

        $programs = get_posts([
            'post_type' => 'course_program',
            'post_status' => ['publish', 'draft', 'pending', 'private'],
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
            'fields' => 'ids',
        ]);

        foreach ((array) $programs as $program_id) {
            $title = get_the_title($program_id);
            if (!is_string($title) || $title === '') {
                continue;
            }
            $options[(string) (int) $program_id] = $title;
        }

        echo '<div class="options_group">';

        woocommerce_wp_select([
            'id' => self::META_KEY,
            'label' => __('Programa', 'politeia-learning'),
            'description' => __('Al asociar un programa, este producto dará acceso a todos los grupos (especializaciones) del programa.', 'politeia-learning'),
            'desc_tip' => true,
            'class' => 'select short',
            'value' => $current_program_id ? (string) $current_program_id : '',
            'options' => $options,
        ]);

        echo '</div>';
    }

    public static function save_field(WC_Product $product): void
    {
        if (!current_user_can('edit_post', $product->get_id())) {
            return;
        }

        $raw = $_POST[self::META_KEY] ?? '';
        $program_id = absint(is_array($raw) ? '' : wp_unslash($raw));

        if ($program_id > 0 && get_post_type($program_id) === 'course_program') {
            $product->update_meta_data(self::META_KEY, $program_id);

            $group_ids = self::get_program_group_ids($program_id);
            update_post_meta($product->get_id(), '_related_group', $group_ids);

            // Back-link for convenience (used by creator dashboard).
            update_post_meta($program_id, '_pcg_woo_product_id', $product->get_id());
            update_post_meta($program_id, '_pcg_program_custom_button_url', get_permalink($product->get_id()));
        } else {
            $product->delete_meta_data(self::META_KEY);
        }
    }

    private static function get_program_group_ids(int $program_id): array
    {
        $raw_groups = get_post_meta($program_id, 'politeia_program_groups', true);

        if (is_string($raw_groups) && $raw_groups !== '') {
            $decoded = json_decode($raw_groups, true);
            if (is_array($decoded)) {
                $raw_groups = $decoded;
            }
        }

        if (!is_array($raw_groups)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map('absint', $raw_groups))));
    }
}

