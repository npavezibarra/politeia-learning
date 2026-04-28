<?php

namespace Learni\Navigation;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles data retrieval for navigation menus.
 */
class NavEngine
{
    /**
     * Returns the current navigation breadcrumb (e.g. ['Mis Cursos', 'Lecciones'])
     */
    public static function get_breadcrumb(): array
    {
        $breadcrumb = [
            'action' => '',
            'parent' => '',
            'sub'    => ''
        ];
        
        $section = isset($_GET['section']) && $_GET['section'] !== '' ? sanitize_text_field($_GET['section']) : 'create-course';
        
        // Base labels
        $labels = [
            'create-course'   => __('MIS CURSOS', 'politeia-learning'),
            'mis-escritos'    => __('MIS ESCRITOS', 'politeia-learning'),
            'especializacion' => __('ESPECIALIZACIÓN', 'politeia-learning'),
            'create-group'    => __('PROGRAMAS', 'politeia-learning'),
            'sales'           => __('VENTAS', 'politeia-learning'),
            'students'        => __('ESTUDIANTES', 'politeia-learning'),
            'profile'         => __('PERFIL', 'politeia-learning'),
        ];

        // Default parent is the section name
        $breadcrumb['parent'] = $labels[$section] ?? __('CENTER', 'politeia-learning');

        // Course Editor override
        if ($section === 'create-course' && isset($_GET['course_id'])) {
            $course_id = (int)$_GET['course_id'];
            $title = get_the_title($course_id);
            $breadcrumb['action'] = 'Edit:';
            $breadcrumb['parent'] = !empty($title) ? $title : __('CURSO', 'politeia-learning');

            if (isset($_GET['lesson_id'])) {
                $breadcrumb['sub'] = '> ' . __('LECCIONES', 'politeia-learning');
            } elseif (isset($_GET['quiz_id'])) {
                $breadcrumb['sub'] = '> ' . __('EVALUACIÓN', 'politeia-learning');
            }
        }

        return apply_filters('pl_navigation_breadcrumb', $breadcrumb);
    }
    /**
     * Returns the array of menu items to be displayed.
     * 
     * @return array<int, array<string, mixed>>
     */
    public static function get_menu_items(): array
    {
        $items = [];

        // 0. Inicio
        $items[] = [
            'label' => __('Inicio', 'politeia-learning'),
            'url' => home_url('/'),
            'classes' => ['menu-item', 'pl-menu-item-home'],
        ];

        // 1. Home / Courses
        $items[] = [
            'label' => __('Cursos', 'politeia-learning'),
            'url' => (get_post_type_archive_link('learni_course') ?: home_url('/courses/')),
            'classes' => ['menu-item', 'pl-menu-item-courses'],
        ];

        // 2. My Books
        $items[] = [
            'label' => __('My Books', 'politeia-learning'),
            'url' => home_url('/my-books/'),
            'classes' => ['menu-item', 'pl-menu-item-my-books'],
        ];

        // 3. User Specific items
        if (is_user_logged_in()) {
            $center_url = self::get_center_url();
            if ($center_url !== '') {
                $items[] = [
                    'label' => __('Center', 'politeia-learning'),
                    'url' => $center_url,
                    'classes' => ['menu-item', 'pl-center-menu-item'],
                ];
            }

            $items[] = [
                'type' => 'user',
                'label' => self::get_user_display_name(),
                'url' => $center_url !== '' ? $center_url : home_url('/'),
                'classes' => ['menu-item', 'pl-user-menu-item'],
            ];
        } else {
            $items[] = [
                'type' => 'auth',
                'label' => __('INGRESAR', 'politeia-learning'),
                'url' => '#',
                'classes' => ['menu-item', 'pl-auth-menu-item'],
            ];
        }

        /**
         * Filter to allow other modules to inject items.
         */
        return apply_filters('pl_navigation_menu_items', $items);
    }

    /**
     * Gets the Center URL for the current user.
     */
    public static function get_center_url(): string
    {
        $user = wp_get_current_user();
        if (!$user || 0 === $user->ID) {
            return '';
        }

        $template = get_option('pcg_operation_template', '/center');
        $slug = ltrim((string) $template, '/');
        if ($slug === '') {
            $slug = 'center';
        }

        return home_url(sprintf('/members/%s/%s', rawurlencode($user->user_login), rawurlencode($slug)));
    }

    /**
     * Gets the display name for the user menu.
     */
    public static function get_user_display_name(): string
    {
        $user = wp_get_current_user();
        if (!$user || 0 === $user->ID) {
            return '';
        }

        $first_name = trim((string) get_user_meta($user->ID, 'first_name', true));
        if ($first_name !== '') {
            return $first_name;
        }

        return $user->display_name ?: __('Account', 'politeia-learning');
    }

    /**
     * Items for the user dropdown menu.
     */
    public static function get_user_dropdown_items(): array
    {
        $user = wp_get_current_user();
        if (!$user || 0 === $user->ID) {
            return [];
        }

        $items = [];
        $items[] = [
            'label' => __('Mi Perfil', 'politeia-learning'),
            'url' => home_url('/profile/' . $user->user_nicename),
        ];

        $items[] = [
            'label' => __('Mis Lecturas', 'politeia-learning'),
            'url' => home_url('/members/' . $user->user_login . '/my-reading-stats'),
        ];

        if (defined('PL_READING_PLANNER_MODULE_ENABLED') && PL_READING_PLANNER_MODULE_ENABLED) {
            $items[] = [
                'label' => __('Planificador de Lecturas', 'politeia-learning'),
                'url' => home_url('/members/' . $user->user_login . '/my-plans-ver-2'),
            ];
        }

        $items[] = [
            'label' => __('Cerrar sesión', 'politeia-learning'),
            'url' => wp_logout_url(home_url('/')),
            'classes' => ['pl-logout-link'],
        ];

        return apply_filters('pl_navigation_user_dropdown_items', $items);
    }
}
