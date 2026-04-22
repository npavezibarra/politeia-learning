<?php

namespace Learni\Navigation;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Specialized renderer for Gutenberg Navigation Blocks.
 */
class GutenbergRenderer
{
    /**
     * Filters the content of a core/navigation block.
     */
    public static function filter_block(string $content, array $block): string
    {
        if (is_admin()) return $content;

        // Skip if not a navigation block
        if (!isset($block['blockName']) || $block['blockName'] !== 'core/navigation') {
            return $content;
        }

        // Avoid injecting in footer menus (usually identified by certain attributes)
        if (strpos($content, '"overlayMenu":"never"') !== false) {
            return $content;
        }

        // Prevent double injection
        if (strpos($content, 'pl-auth-menu-link') !== false || strpos($content, 'pl-user-menu-link') !== false) {
            return $content;
        }

        $items_html = self::build_block_items_html();
        if ($items_html === '') return $content;

        // Match the opening <ul ... wp-block-navigation__container ...>
        $pattern = '~(<ul[^>]*class="[^"]*wp-block-navigation__container[^"]*"[^>]*>).*~s';
        
        if (preg_match($pattern, $content, $matches)) {
            $opening_ul = $matches[1];
            // Replace everything after the opening tag with our items and a closing tag
            $content = preg_replace($pattern, $opening_ul . $items_html . '</ul>', $content);
        }

        return $content;
    }

    /**
     * Builds items with classes compatible with block-based themes.
     */
    private static function build_block_items_html(): string
    {
        $items = NavEngine::get_menu_items();
        $html = '';

        foreach ($items as $item) {
            $html .= self::render_block_item($item);
        }

        return $html;
    }

    private static function render_block_item(array $item): string
    {
        $label = $item['label'] ?? '';
        $url = $item['url'] ?? '';
        $type = $item['type'] ?? 'link';

        $base_classes = 'wp-block-navigation-item wp-block-navigation-link menu-item';
        
        if ($type === 'user') {
            // User menu in Gutenberg requires special classes for consistency
            return DesktopRenderer::render_user_item(array_merge($item, [
                'classes' => array_merge([$base_classes], $item['classes'] ?? [])
            ]));
        }

        if ($type === 'auth') {
            return sprintf(
                '<li class="%s pl-auth-menu-item"><button type="button" class="wp-block-navigation-item__content pl-auth-menu-link" data-pl-auth-open="1" data-pl-auth-view="login">%s</button></li>',
                esc_attr($base_classes),
                esc_html($label)
            );
        }

        return sprintf(
            '<li class="%s"><a class="wp-block-navigation-item__content" href="%s"><span class="wp-block-navigation-item__label">%s</span></a></li>',
            esc_attr($base_classes),
            esc_url($url),
            esc_html($label)
        );
    }
}
