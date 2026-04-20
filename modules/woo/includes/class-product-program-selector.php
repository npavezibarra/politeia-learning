<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Adds a "Programa" selector in WooCommerce product edit screen.
 *
 * When set, the product will:
 * - store `_learni_related_program` = program ID
 * - sync `_learni_related_specializations` to all specializations associated to the program
 *
 * This allows program purchases to grant access to all linked Learni Specializations.
 */
class PL_Woo_Product_Program_Selector
{
    const META_KEY = '_learni_related_program';

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
            'post_type' => 'learni_program',
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
            'description' => __('Al asociar un programa, este producto dará acceso a todas las especializaciones del programa.', 'politeia-learning'),
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

        if ($program_id > 0 && get_post_type($program_id) === 'learni_program') {
            $product->update_meta_data(self::META_KEY, $program_id);

            $specialization_ids = self::get_program_specialization_ids($program_id);
            update_post_meta($product->get_id(), '_learni_related_specializations', $specialization_ids);

            // Back-link for convenience (used by creator dashboard).
            update_post_meta($program_id, '_pcg_woo_product_id', $product->get_id());
            update_post_meta($program_id, 'learni_program_custom_button_url', get_permalink($product->get_id()));
        } else {
            $product->delete_meta_data(self::META_KEY);
        }
    }

    private static function get_program_specialization_ids(int $program_id): array
    {
        $raw_specializations = get_post_meta($program_id, 'learni_specializations');

        if (!is_array($raw_specializations)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map('absint', $raw_specializations))));
    }
}

