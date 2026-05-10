<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Template helpers for Politeia Bookshelf (My Books).
 *
 * Goal: render the SAME header/footer as the active theme.
 * - If Politeia Learning helpers exist, reuse them for consistency.
 * - Otherwise, apply the same block-theme approach (do_blocks template-parts before wp_head).
 */

function prs_template_open(): void
{
    if (function_exists('pl_template_open')) {
        pl_template_open();
        return;
    }

    $is_block_theme = function_exists('wp_is_block_theme') && wp_is_block_theme();
    if (!$is_block_theme) {
        get_header();
        return;
    }

    global $prs_theme_footer_html;
    $prs_theme_footer_html = '';

    $theme_header_html = '';
    if (function_exists('do_blocks')) {
        $theme_header_html = (string) do_blocks('<!-- wp:template-part {"slug":"header","area":"header"} /-->');
        if (!get_query_var('prs_my_plans_ver_2')) {
            $prs_theme_footer_html = (string) do_blocks('<!-- wp:template-part {"slug":"footer","area":"footer"} /-->');
        }
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

        if ($theme_header_html !== '') {
            echo $theme_header_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }
        ?>
    <?php
}

function prs_template_close(): void
{
    if (function_exists('pl_template_close')) {
        pl_template_close();
        return;
    }

    $is_block_theme = function_exists('wp_is_block_theme') && wp_is_block_theme();
    if (!$is_block_theme) {
        if (!get_query_var('prs_my_plans_ver_2')) {
            get_footer();
        } else {
            wp_footer();
            echo '</body></html>';
        }
        return;
    }

    global $prs_theme_footer_html;
    if (!empty($prs_theme_footer_html)) {
        echo $prs_theme_footer_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    wp_footer();
    ?>
    </body>
    </html>
    <?php
}
