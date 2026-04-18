<?php
/**
 * Plugin Name: Politeia Learning
 * Description: Custom functionalities for Politeia website related to courses, grouping, selling, and creating courses.
 * Author: Nico / Politeia
 * Version: 1.3.0
 * Text Domain: politeia-learning
 * Domain Path: /languages
 * Codex Enabled: true
 */

if (!defined('ABSPATH'))
    exit;


// Core Constants
define('PL_PATH', plugin_dir_path(__FILE__));
define('PL_URL', plugin_dir_url(__FILE__));
define('PL_DB_VERSION', '1.6.1');

// Load Global Includes
require_once PL_PATH . 'includes/class-installer.php';
require_once PL_PATH . 'includes/class-upgrader.php';
require_once PL_PATH . 'includes/class-taxonomy.php';
require_once PL_PATH . 'includes/class-user-profile-meta-store.php';
require_once PL_PATH . 'includes/class-relationships.php';
require_once PL_PATH . 'includes/class-relationship-policies-ui.php';
require_once PL_PATH . 'includes/class-partnerships-repository.php';
require_once PL_PATH . 'includes/class-email.php';
require_once PL_PATH . 'includes/class-rest-partnerships.php';
require_once PL_PATH . 'includes/class-partner-add-shortcode.php';
require_once PL_PATH . 'includes/class-course-partner-modal.php';
require_once PL_PATH . 'includes/template-helpers.php';

if (class_exists('PL_Rest_Partnerships')) {
    PL_Rest_Partnerships::init();
}

if (class_exists('PL_Partner_Add_Shortcode')) {
    PL_Partner_Add_Shortcode::init();
}

if (class_exists('PL_Course_Partner_Modal')) {
    PL_Course_Partner_Modal::init();
}

if (class_exists('PL_Relationships')) {
    PL_Relationships::init();
}

if (class_exists('PL_Relationship_Policies_UI')) {
    PL_Relationship_Policies_UI::init();
}

// WP-CLI commands (loaded only under WP-CLI).
if (defined('WP_CLI') && WP_CLI) {
    require_once PL_PATH . 'includes/class-cli-partnerships.php';
}

// Automatic Database Upgrades
add_action('plugins_loaded', ['PL_Upgrader', 'maybe_upgrade']);

// Step 1 & 6: Introduce a proper activation hook with background seeding
register_activation_hook(__FILE__, function () {
    // Run database installation
    PL_Installer::install();

    // Install/upgrade Learni internal module schema.
    $learni_bootstrap = PL_PATH . 'modules/learni/init.php';
    if (file_exists($learni_bootstrap)) {
        require_once $learni_bootstrap;
    }
    if (class_exists('PL_Learni_Module')) {
        PL_Learni_Module::activate();
    }

    // Schedule background taxonomy seeding
    if (!get_option('pl_learning_taxonomy_seed_v1')) {
        wp_schedule_single_event(time(), 'pl_seed_default_categories');
    }

    // Ensure our custom rewrites exist before flushing (pure WP routes).
    if (class_exists('PL_Member_Profile_Public_Route')) {
        PL_Member_Profile_Public_Route::register_rewrites_for_flush();
    }
    flush_rewrite_rules(false);
});

register_deactivation_hook(__FILE__, function () {
    flush_rewrite_rules(false);
});

// Unified taxonomy registration.
add_action('plugins_loaded', function () {
    if (class_exists('PL_Taxonomy')) {
        PL_Taxonomy::init();
    }
}, 1);

// Load translations. WordPress will prefer WP_LANG_DIR/plugins first.
add_action('plugins_loaded', function () {
    load_plugin_textdomain('politeia-learning', false, dirname(plugin_basename(__FILE__)) . '/languages');
}, 5);

/**
 * Prefer "FirstName LastName" on LearnDash course pages when templates ask for display_name.
 * Keeps global display_name unchanged, only affects rendering.
 */
function pl_get_user_full_name_or_display_name(int $user_id, string $fallback = ''): string
{
    $user = get_userdata($user_id);
    if (!$user) {
        return $fallback;
    }

    $full_name = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));
    if ($full_name !== '') {
        return $full_name;
    }

    return $user->display_name ?: $fallback;
}

add_filter('get_the_author_display_name', function ($display_name, $user_id) {
    if (!is_singular('sfwd-courses') && !is_singular('learni_course')) {
        return $display_name;
    }

    return pl_get_user_full_name_or_display_name((int) $user_id, (string) $display_name);
}, 10, 2);

// BuddyBoss/BuddyPress display name (if used by the theme on course pages).
add_filter('bp_core_get_user_displayname', function ($display_name, $user_id) {
    if (!is_singular('sfwd-courses') && !is_singular('learni_course')) {
        return $display_name;
    }

    return pl_get_user_full_name_or_display_name((int) $user_id, (string) $display_name);
}, 10, 2);

// Global Styles and Typography
add_action('wp_enqueue_scripts', function () {
    // Enqueue Poppins for all pages to support branded elements like the mini-cart badge
    wp_enqueue_style(
        'pl-global-styles',
        'https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap',
        [],
        null
    );

    // Mini-cart badge styling (requested by user)
    $custom_css = "
        .wc-block-mini-cart__badge {
            align-items: center;
            font-family: 'Poppins', sans-serif;
            border-radius: 1em;
            box-sizing: border-box;
            display: flex;
            font-size: 9px;
            font-weight: 600;
            height: 18px;
            background: linear-gradient(135deg, #783F27, #B87333, #E5AA70);
            justify-content: center;
            left: 100%;
            margin-left: -44%;
            min-width: 18px;
            padding: 0 .25em;
            position: absolute;
            transform: translateY(-50%);
            white-space: nowrap;
            z-index: 1;
        }
    ";

    // Legacy Course Typography enforcements (kept for backward compatibility)
    if (is_singular('sfwd-courses') || is_singular('sfwd-lessons') || is_singular('learni_course') || is_singular('learni_lesson')) {
        $custom_css .= '
            body.single-sfwd-courses,body.single-sfwd-lessons{font-family:"Poppins",sans-serif!important;}
            body.single-learni_course,body.single-learni_lesson{font-family:"Poppins",sans-serif!important;}
            body.single-sfwd-courses button,
            body.single-sfwd-courses input,
            body.single-sfwd-courses select,
            body.single-sfwd-courses textarea,
            body.single-sfwd-courses h1,
            body.single-sfwd-courses h2,
            body.single-sfwd-courses h3,
            body.single-sfwd-courses h4,
            body.single-sfwd-courses h5,
            body.single-sfwd-courses h6,
            body.single-sfwd-lessons button,
            body.single-sfwd-lessons input,
            body.single-sfwd-lessons select,
            body.single-sfwd-lessons textarea,
            body.single-sfwd-lessons h1,
            body.single-sfwd-lessons h2,
            body.single-sfwd-lessons h3,
            body.single-sfwd-lessons h4,
            body.single-sfwd-lessons h5,
            body.single-sfwd-lessons h6{
                font-family:"Poppins",sans-serif!important;
            }
        ';
    }

    wp_add_inline_style('pl-global-styles', $custom_css);
}, 20);

/**
 * Load Global Dependencies
 */
// Composer Autoloader
if (file_exists(PL_PATH . 'vendor/autoload.php')) {
    require_once PL_PATH . 'vendor/autoload.php';
}

// Codex Init
if (file_exists(PL_PATH . 'codex/init.php')) {
    require_once PL_PATH . 'codex/init.php';
}

/**
 * Module Loader Class
 * Manages the different standalone modules of the plugin.
 */
class PL_Module_Loader
{
    /**
     * List of available modules and their status.
     * In the future, this could be managed via an admin UI or settings.
     */
    private static $modules = [
        'learni' => true,
        'core' => true,
        'menu-management' => true,
        'blog-post' => true,
        'login-register' => true,
        'course-programs' => true,
        'course-integration' => true,
        'course-creator' => true,
        'quiz-creator' => true,
        'quiz-control' => true,
        'woo' => true,
        'member-profile' => true,
        'payments-subscriptions' => true,
        'email-log' => true,
    ];

    /**
     * Initialize active modules.
     */
    public static function init()
    {
        $t_start = microtime(true);
        foreach (self::$modules as $module_slug => $enabled) {
            if ($enabled) {
                $init_file = PL_PATH . 'modules/' . $module_slug . '/init.php';
                if (file_exists($init_file)) {
                    require_once $init_file;
                }
            }
        }
        error_log('[Politeia Audit] Modules init time: ' . (microtime(true) - $t_start));
    }

    /**
     * Check if a module is enabled.
     */
    public static function is_module_enabled($module_slug)
    {
        return isset(self::$modules[$module_slug]) && self::$modules[$module_slug];
    }
}

// Start the modules
PL_Module_Loader::init();
