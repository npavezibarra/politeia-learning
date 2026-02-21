<?php
class PL_Templates
{
    public function __construct()
    {
        add_filter('template_include', [$this, 'load_custom_templates']);
        add_filter( 'learndash_template', [ $this, 'override_learndash_templates' ], 10, 5 );
    }

    public function load_custom_templates($template)
    {
        if (is_singular('course_program')) {
            $custom = PL_CP_PATH . 'templates/single-course_program.php';
            if (file_exists($custom))
                return $custom;
        }
        if (is_post_type_archive('course_program')) {
            $archive = PL_CP_PATH . 'templates/archive-course_program.php';
            if (file_exists($archive))
                return $archive;
        }
        return $template;
    }

    /**
     * Override LearnDash templates used inside Group pages.
     *
     * @param string     $filepath         Template file path resolved by LearnDash.
     * @param string     $name             Template name (usually without .php).
     * @param array|null $args             Template args.
     * @param bool|null  $echo             Whether LearnDash echoes output.
     * @param bool       $return_file_path Whether LearnDash wants file path only.
     *
     * @return string
     */
    public function override_learndash_templates( $filepath, $name, $args = null, $echo = null, $return_file_path = false ) {
        if ( empty( $name ) || ! defined( 'PL_CP_PATH' ) ) {
            return $filepath;
        }

        // Only care about LearnDash Groups pages for now.
        if ( ! is_singular( 'groups' ) ) {
            return $filepath;
        }

        // LearnDash Modern Groups resolve to modern/group/index.php (via modern/group.php -> index.php).
        if ( is_string( $filepath ) && preg_match( '#/modern/group/index\\.php$#', $filepath ) ) {
            $override = trailingslashit( PL_CP_PATH ) . 'templates/learndash/ld30/modern/group/index.php';
            if ( file_exists( $override ) ) {
                return $override;
            }
        }

        // Override modern group content wrapper to adjust layout for group description + courses.
        if ( is_string( $filepath ) && preg_match( '#/modern/group/content\\.php$#', $filepath ) ) {
            $override = trailingslashit( PL_CP_PATH ) . 'templates/learndash/ld30/modern/group/content.php';
            if ( file_exists( $override ) ) {
                return $override;
            }
        }

        // LearnDash LD30 Groups resolve to group.php when modern groups are disabled.
        if ( is_string( $filepath ) && preg_match( '#/group\\.php$#', $filepath ) ) {
            $override = trailingslashit( PL_CP_PATH ) . 'templates/learndash/ld30/group.php';
            if ( file_exists( $override ) ) {
                return $override;
            }
        }

        return $filepath;
    }
}
