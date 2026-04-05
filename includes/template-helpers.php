<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Template helpers for Politeia Learning.
 *
 * Goal: ensure plugin templates render the same header/footer as the active theme.
 * - Classic themes: use get_header()/get_footer()
 * - Block themes (Site Editor): pre-render template parts via do_blocks() BEFORE wp_head()
 */

function pl_is_block_theme(): bool
{
    return function_exists('wp_is_block_theme') && wp_is_block_theme();
}

/**
 * Print the document header and theme header.
 * Stores the footer HTML for pl_template_close().
 */
function pl_template_open(): void
{
    global $pl_theme_footer_html;

    if (!pl_is_block_theme()) {
        get_header();
        return;
    }

    $pl_theme_header_html = '';
    $pl_theme_footer_html = '';

    // Pre-render block theme template parts BEFORE wp_head so their assets are enqueued in the correct place.
    if (function_exists('do_blocks')) {
        $pl_theme_header_html = (string) do_blocks('<!-- wp:template-part {"slug":"header","area":"header"} /-->');
        $pl_theme_footer_html = (string) do_blocks('<!-- wp:template-part {"slug":"footer","area":"footer"} /-->');
    }

    ?><!doctype html>
    <html <?php language_attributes(); ?>>
    <head>
        <meta charset="<?php bloginfo('charset'); ?>">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?php echo esc_html((string) wp_get_document_title()); ?></title>
        <?php wp_head(); ?>
    </head>
    <body <?php body_class(); ?>>
        <?php
        if (function_exists('wp_body_open')) {
            wp_body_open();
        }

        if ($pl_theme_header_html !== '') {
            echo $pl_theme_header_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }
        ?>
    <?php
}

/**
 * Print the theme footer and close the document.
 */
function pl_template_close(): void
{
    global $pl_theme_footer_html;

    if (!pl_is_block_theme()) {
        get_footer();
        return;
    }

    if (!empty($pl_theme_footer_html)) {
        echo $pl_theme_footer_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    wp_footer();
    ?>
    </body>
    </html>
    <?php
}

function pl_get_user_profile_avatar_attachment_id(int $user_id): int
{
    if ($user_id <= 0) {
        return 0;
    }

    return absint(get_user_meta($user_id, '_pl_profile_avatar_attachment_id', true));
}

function pl_get_user_profile_avatar_custom_url(int $user_id, int $size = 96): string
{
    if ($user_id <= 0) {
        return '';
    }

    $attachment_id = pl_get_user_profile_avatar_attachment_id($user_id);
    if ($attachment_id > 0) {
        $size = max(1, $size);
        $url = wp_get_attachment_image_url($attachment_id, [$size, $size]);
        if (is_string($url) && $url !== '') {
            return $url;
        }

        $url = wp_get_attachment_url($attachment_id);
        if (is_string($url) && $url !== '') {
            return $url;
        }
    }

    $stored_url = trim((string) get_user_meta($user_id, '_pl_profile_avatar_url', true));
    return $stored_url !== '' ? $stored_url : '';
}

function pl_resolve_user_id_from_avatar_source($id_or_email): int
{
    if (is_numeric($id_or_email)) {
        return absint($id_or_email);
    }

    if ($id_or_email instanceof WP_User) {
        return absint($id_or_email->ID);
    }

    if ($id_or_email instanceof WP_Comment) {
        $user_id = absint($id_or_email->user_id);
        if ($user_id > 0) {
            return $user_id;
        }

        $user = get_user_by('email', (string) $id_or_email->comment_author_email);
        return $user ? absint($user->ID) : 0;
    }

    if (is_string($id_or_email) && is_email($id_or_email)) {
        $user = get_user_by('email', $id_or_email);
        return $user ? absint($user->ID) : 0;
    }

    return 0;
}

function pl_filter_get_avatar_url(string $url, $id_or_email, array $args): string
{
    $user_id = pl_resolve_user_id_from_avatar_source($id_or_email);
    if ($user_id <= 0) {
        return $url;
    }

    $size = isset($args['size']) ? absint($args['size']) : 96;
    $custom_url = pl_get_user_profile_avatar_custom_url($user_id, $size);
    return $custom_url !== '' ? $custom_url : $url;
}

add_filter('get_avatar_url', 'pl_filter_get_avatar_url', 10, 3);
