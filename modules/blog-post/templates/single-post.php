<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

global $post;
if (!($post instanceof WP_Post)) {
    exit;
}

if (!function_exists('pl_bp_blog_post_primary_category_name')) {
    function pl_bp_blog_post_primary_category_name(int $post_id): string
    {
        $categories = get_the_category($post_id);
        if (!is_array($categories) || count($categories) === 0) {
            return '';
        }

        $first = $categories[0];
        if (!($first instanceof WP_Term)) {
            return '';
        }

        return (string) $first->name;
    }
}

if (!function_exists('pl_bp_blog_post_lead_text')) {
    function pl_bp_blog_post_lead_text(WP_Post $post, int $words = 38): string
    {
        $excerpt = (string) $post->post_excerpt;
        if ($excerpt !== '') {
            return wp_trim_words($excerpt, $words, '...');
        }

        $content = (string) $post->post_content;
        $content = wp_strip_all_tags((string) strip_shortcodes($content));
        return wp_trim_words($content, $words, '...');
    }
}

if (!function_exists('pl_bp_blog_post_author_name')) {
    function pl_bp_blog_post_author_name(int $user_id): string
    {
        $first = (string) get_user_meta($user_id, 'first_name', true);
        $last = (string) get_user_meta($user_id, 'last_name', true);
        $full = trim(trim($first) . ' ' . trim($last));
        if ($full !== '') {
            return $full;
        }

        $display = (string) get_the_author_meta('display_name', $user_id);
        return $display !== '' ? $display : 'Politeia';
    }
}

if (!function_exists('pl_bp_blog_post_apply_leadin_caps')) {
    function pl_bp_blog_post_apply_leadin_caps(string $html, int $words = 3): string
    {
        $words = max(1, (int) $words);
        if ($html === '') {
            return $html;
        }

        if (strpos($html, 'pl-bp-leadin-caps') !== false) {
            return $html;
        }

        if (!class_exists('DOMDocument')) {
            return $html;
        }

        $doc = new DOMDocument();
        libxml_use_internal_errors(true);
        $wrapped = '<div id="pl-bp-root">' . $html . '</div>';
        $loaded = $doc->loadHTML('<?xml encoding="utf-8" ?>' . $wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors(false);

        if (!$loaded) {
            return $html;
        }

        $root = $doc->getElementById('pl-bp-root');
        if (!$root) {
            return $html;
        }

        $paragraphs = $root->getElementsByTagName('p');
        foreach ($paragraphs as $p) {
            if (!($p instanceof DOMElement)) {
                continue;
            }

            // Avoid applying inside captions/figures.
            $ancestor = $p->parentNode;
            while ($ancestor instanceof DOMNode) {
                if ($ancestor instanceof DOMElement && strtolower($ancestor->tagName) === 'figcaption') {
                    continue 2;
                }
                $ancestor = $ancestor->parentNode;
            }

            if (trim((string) $p->textContent) === '') {
                continue;
            }

            $node = null;
            foreach ($p->childNodes as $child) {
                if ($child instanceof DOMText && trim((string) $child->nodeValue) !== '') {
                    $node = $child;
                    break;
                }
            }
            if (!($node instanceof DOMText)) {
                continue;
            }

            $text = (string) $node->nodeValue;

            // Split on whitespace tokens; keep original spacing between the first N words.
            $pattern = '/^(\s*)(\S+)(\s+)(\S+)(\s+)(\S+)(.*)$/u';
            $pattern2 = '/^(\s*)(\S+)(\s+)(\S+)(.*)$/u';
            $pattern1 = '/^(\s*)(\S+)(.*)$/u';

            $leading = '';
            $lead_text = '';
            $rest = '';

            if ($words >= 3 && preg_match($pattern, $text, $m)) {
                $leading = $m[1];
                $lead_text = $m[2] . $m[3] . $m[4] . $m[5] . $m[6];
                $rest = $m[7];
            } elseif ($words >= 2 && preg_match($pattern2, $text, $m)) {
                $leading = $m[1];
                $lead_text = $m[2] . $m[3] . $m[4];
                $rest = $m[5];
            } elseif (preg_match($pattern1, $text, $m)) {
                $leading = $m[1];
                $lead_text = $m[2];
                $rest = $m[3];
            } else {
                continue;
            }

            $frag = $doc->createDocumentFragment();
            if ($leading !== '') {
                $frag->appendChild($doc->createTextNode($leading));
            }

            $span = $doc->createElement('span');
            $span->setAttribute('class', 'pl-bp-leadin-caps');
            $span->appendChild($doc->createTextNode($lead_text));
            $frag->appendChild($span);

            if ($rest !== '') {
                $frag->appendChild($doc->createTextNode($rest));
            }

            $node->parentNode->replaceChild($frag, $node);
            break;
        }

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $doc->saveHTML($child);
        }

        return $out !== '' ? $out : $html;
    }
}

$featured_img = get_the_post_thumbnail_url($post, 'large');
$author_name = pl_bp_blog_post_author_name((int) $post->post_author);
$date_display = (string) get_the_date('j \d\e F, Y', $post);
$lead_text = pl_bp_blog_post_lead_text($post, 40);

$post_id = (int) $post->ID;
$related_category_ids = wp_get_post_terms($post_id, 'category', ['fields' => 'ids']);
$related_tag_ids = wp_get_post_terms($post_id, 'post_tag', ['fields' => 'ids']);
$related_category_ids = is_array($related_category_ids) ? array_values(array_filter(array_map('intval', $related_category_ids))) : [];
$related_tag_ids = is_array($related_tag_ids) ? array_values(array_filter(array_map('intval', $related_tag_ids))) : [];

$related_tax_query = [];
if ($related_category_ids !== [] || $related_tag_ids !== []) {
    $related_tax_query = ['relation' => 'OR'];
    if ($related_category_ids !== []) {
        $related_tax_query[] = [
            'taxonomy' => 'category',
            'field' => 'term_id',
            'terms' => $related_category_ids,
        ];
    }
    if ($related_tag_ids !== []) {
        $related_tax_query[] = [
            'taxonomy' => 'post_tag',
            'field' => 'term_id',
            'terms' => $related_tag_ids,
        ];
    }
}

$other_posts = get_posts(
    array_filter(
        [
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => 4,
            'post__not_in' => [$post_id],
            'ignore_sticky_posts' => true,
            'no_found_rows' => true,
            'orderby' => 'rand',
            'tax_query' => $related_tax_query !== [] ? $related_tax_query : null,
        ],
        static fn($value): bool => $value !== null
    )
);

if (count($other_posts) < 4) {
    $exclude_ids = [$post_id];
    foreach ($other_posts as $p) {
        if ($p instanceof WP_Post) {
            $exclude_ids[] = (int) $p->ID;
        }
    }

    $fallback_posts = get_posts(
        [
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => 4 - count($other_posts),
            'post__not_in' => $exclude_ids,
            'ignore_sticky_posts' => true,
            'no_found_rows' => true,
            'orderby' => 'rand',
        ]
    );

    if (is_array($fallback_posts) && $fallback_posts !== []) {
        $other_posts = array_merge($other_posts, $fallback_posts);
    }
}

pl_template_open();
?>

<div id="pcg-blog-post-root" class="pl-bp-blog-post-root">
    <main class="pl-bp-blog-post-main">
        <?php if (have_posts()) : while (have_posts()) : the_post(); ?>
            <?php
	            $post_object = get_post();
	            if (!($post_object instanceof WP_Post)) {
	                continue;
	            }

	            $featured_img = get_the_post_thumbnail_url($post_object, 'large');
	            $has_featured_img = is_string($featured_img) && $featured_img !== '';
	            $author_name = pl_bp_blog_post_author_name((int) $post_object->post_author);
	            $date_display = (string) get_the_date('j \d\e F, Y', $post_object);
	            $lead_text = pl_bp_blog_post_lead_text($post_object, 40);
	            $primary_category = pl_bp_blog_post_primary_category_name((int) $post_object->ID);
	            $title = (string) get_the_title($post_object);
	            $title_len = function_exists('mb_strlen') ? mb_strlen(trim($title), 'UTF-8') : strlen(trim($title));
	            $title_size_class = $title_len <= 24 ? 'pl-bp-title-short' : 'pl-bp-title-long';
	            $hero_classes = 'pl-bp-blog-post-hero' . ($has_featured_img ? '' : ' pl-bp-hero--no-image');
	            ?>

	            <article id="post-<?php the_ID(); ?>" <?php post_class('pl-bp-blog-post-article'); ?>>
	                <div class="<?php echo esc_attr($hero_classes); ?>">
	                    <?php if ($has_featured_img) : ?>
	                        <div class="pl-bp-blog-post-hero-media">
	                            <img
	                                src="<?php echo esc_url($featured_img); ?>"
	                                alt="<?php echo esc_attr($title); ?>"
	                                class="pl-bp-blog-post-cover"
	                                decoding="async"
	                                loading="eager"
	                            />
	                        </div>
	                    <?php endif; ?>

	                    <div class="pl-bp-blog-post-hero-copy">
	                        <h1 class="pl-bp-blog-post-title <?php echo esc_attr($title_size_class); ?>"><?php echo esc_html($title); ?></h1>
	                        <p class="pl-bp-blog-post-meta">
	                            <?php echo esc_html__('por', 'politeia-learning'); ?>
	                            <span><?php echo esc_html($author_name !== '' ? $author_name : 'Politeia'); ?></span>
	                            <?php echo esc_html(' | ' . $date_display); ?>
                            <?php if ($primary_category !== '') : ?>
                                <span class="pl-bp-blog-post-meta-separator">|</span>
                                <span><?php echo esc_html($primary_category); ?></span>
                            <?php endif; ?>
                        </p>
                        <?php if ($lead_text !== '') : ?>
                            <div class="pl-bp-blog-post-lead">
                                <?php echo esc_html($lead_text); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

	                <div class="pl-bp-blog-post-content-wrap">
	                    <div class="pl-bp-blog-post-content">
	                        <?php
	                        $content_html = (string) apply_filters('the_content', (string) $post_object->post_content);
	                        $content_html = pl_bp_blog_post_apply_leadin_caps($content_html, 3);
	                        echo $content_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	                        ?>
	                    </div>
	                </div>

                <?php if (is_array($other_posts) && $other_posts !== []) : ?>
                    <section class="pl-bp-blog-post-related">
                        <h2 class="pl-bp-blog-post-related-title"><?php echo esc_html__('Artículos relacionados', 'politeia-learning'); ?></h2>
                        <div class="pl-bp-blog-post-related-grid">
                            <?php foreach ($other_posts as $other_post) : ?>
                                <?php
                                if (!($other_post instanceof WP_Post)) {
                                    continue;
                                }
	                                $other_img = get_the_post_thumbnail_url($other_post, 'medium_large');
	                                $other_cat = pl_bp_blog_post_primary_category_name((int) $other_post->ID);
	                                $other_author = pl_bp_blog_post_author_name((int) $other_post->post_author);
	                                $other_date = (string) get_the_date('j M Y', $other_post);
	                                ?>
                                <a href="<?php echo esc_url((string) get_permalink($other_post)); ?>" class="pl-bp-blog-post-card">
                                    <div class="pl-bp-blog-post-card-media">
                                        <?php if (is_string($other_img) && $other_img !== '') : ?>
                                            <img
                                                src="<?php echo esc_url($other_img); ?>"
                                                alt="<?php echo esc_attr((string) get_the_title($other_post)); ?>"
                                                class="pl-bp-blog-post-card-img"
                                                decoding="async"
                                                loading="lazy"
                                            />
                                        <?php endif; ?>
                                        <?php if ($other_cat !== '') : ?>
                                            <span class="pl-bp-blog-post-card-cat"><?php echo esc_html($other_cat); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <h3 class="pl-bp-blog-post-card-title"><?php echo esc_html((string) get_the_title($other_post)); ?></h3>
                                    <div class="pl-bp-blog-post-card-meta">
                                        <span><?php echo esc_html($other_author !== '' ? $other_author : 'Politeia'); ?></span>
                                        <span class="pl-bp-blog-post-card-meta-sep">•</span>
                                        <span><?php echo esc_html($other_date); ?></span>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endif; ?>

                <?php if (comments_open() || get_comments_number()) : ?>
                    <section class="pl-bp-blog-post-comments" id="comments">
                        <?php comments_template(); ?>
                    </section>
                <?php endif; ?>
            </article>

        <?php endwhile; endif; ?>
    </main>
</div>

<?php
pl_template_close();
