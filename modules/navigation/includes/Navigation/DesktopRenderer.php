<?php

namespace Learni\Navigation;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles rendering for Desktop navigation elements.
 */
class DesktopRenderer
{
    /**
     * Builds the standard <li> items for a menu.
     */
    public static function build_items_html(): string
    {
        $items = NavEngine::get_menu_items();
        if (empty($items)) {
            return '';
        }

        $html = '';
        foreach ($items as $item) {
            $html .= self::render_item($item);
        }

        return $html;
    }

    /**
     * Renders a single menu item.
     */
    private static function render_item(array $item): string
    {
        $type = $item['type'] ?? 'link';
        
        switch ($type) {
            case 'user':
                return self::render_user_item($item);
            case 'auth':
                return self::render_auth_item($item);
            default:
                return self::render_standard_item($item);
        }
    }

    private static function render_standard_item(array $item): string
    {
        $classes = $item['classes'] ?? [];
        $class_attr = !empty($classes) ? ' class="' . esc_attr(implode(' ', $classes)) . '"' : '';
        
        return sprintf(
            '<li%s><a class="pl-menu-link" href="%s">%s</a></li>',
            $class_attr,
            esc_url($item['url']),
            esc_html($item['label'])
        );
    }

    public static function render_user_item(array $item): string
    {
        $user_id = get_current_user_id();
        $avatar_url = get_avatar_url($user_id, ['size' => 64]);
        
        $classes = $item['classes'] ?? [];
        $class_attr = !empty($classes) ? ' class="' . esc_attr(implode(' ', $classes)) . '"' : '';

        ob_start();
        ?>
        <li<?php echo $class_attr; ?>>
            <button type="button" class="pl-menu-link pl-user-menu-link pl-user-menu__toggle" aria-haspopup="true" aria-expanded="false">
                <img class="pl-user-menu__avatar" src="<?php echo esc_url($avatar_url); ?>" alt="" aria-hidden="true" />
                <span class="pl-user-menu__label"><?php echo esc_html($item['label']); ?></span>
                <svg class="pl-user-menu__toggle-caret" viewBox="0 0 20 20" aria-hidden="true"><path fill="currentColor" d="M5.5 7.5 10 12l4.5-4.5 1.1 1.1L10 14.2 4.4 8.6z"/></svg>
            </button>
            <?php echo self::render_user_dropdown(); ?>
        </li>
        <?php
        return ob_get_clean();
    }

    private static function render_auth_item(array $item): string
    {
        return sprintf(
            '<li class="pl-auth-menu-item"><button type="button" class="pl-auth-menu-link" data-pl-auth-open="1" data-pl-auth-view="login">%s</button></li>',
            esc_html($item['label'])
        );
    }

    private static function render_user_dropdown(): string
    {
        $items = NavEngine::get_user_dropdown_items();
        if (empty($items)) return '';

        $html = '<ul class="pl-user-menu__dropdown">';
        foreach ($items as $item) {
            $html .= sprintf(
                '<li class="pl-user-menu__dropdown-item"><a href="%s">%s</a></li>',
                esc_url($item['url']),
                esc_html($item['label'])
            );
        }
        $html .= '</ul>';
        return $html;
    }
}
