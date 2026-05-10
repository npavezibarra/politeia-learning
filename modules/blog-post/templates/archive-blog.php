<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

if (function_exists('pl_template_open')) {
    pl_template_open();
} else {
    get_header();
}

$categories = [
    'CHILE',
    'INTERNACIONAL',
    'CIENCIA POLÍTICA',
    'SOCIOLOGÍA',
    'HISTORIA',
    'LITERATURA',
    'ANTROPOLOGÍA',
    'FILOSOFÍA',
];

$active = isset($_GET['categoria']) ? sanitize_title((string) wp_unslash($_GET['categoria'])) : '';
if ($active === 'todo') {
    $active = '';
}

$query_args = [
    'post_type' => 'post',
    'post_status' => 'publish',
    'ignore_sticky_posts' => true,
    'posts_per_page' => 12,
];

if ($active !== '') {
    $query_args['tax_query'] = [
        [
            'taxonomy' => 'category',
            'field' => 'slug',
            'terms' => [$active],
        ],
    ];
}

$posts = new WP_Query($query_args);

function pl_bp_blog_archive_category_slug(string $label): string
{
    $label = trim($label);
    if ($label === '') {
        return '';
    }
    return sanitize_title($label);
}

function pl_bp_blog_archive_post_lead_text(WP_Post $post, int $words = 26): string
{
    $excerpt = (string) $post->post_excerpt;
    if ($excerpt !== '') {
        return wp_trim_words($excerpt, $words, '...');
    }

    $content = (string) $post->post_content;
    $content = wp_strip_all_tags((string) strip_shortcodes($content));
    return wp_trim_words($content, $words, '...');
}

function pl_bp_blog_archive_author_name(int $user_id): string
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

$today = date_i18n('j F Y');
?>

<main class="pl-bp-blog-archive" id="pl-bp-blog-archive">
    <header class="pl-bp-ba-header">
        <div class="pl-bp-ba-container">
            <nav class="pl-bp-ba-nav" aria-label="Categorías del blog">
                <a class="pl-bp-ba-navLink <?php echo $active === '' ? 'is-active' : ''; ?>" href="<?php echo esc_url(home_url('/blog/')); ?>">Todo</a>
                <?php foreach ($categories as $label): ?>
                    <?php $slug = pl_bp_blog_archive_category_slug($label); ?>
                    <a
                        class="pl-bp-ba-navLink <?php echo $active === $slug ? 'is-active' : ''; ?>"
                        href="<?php echo esc_url(add_query_arg('categoria', $slug, home_url('/blog/'))); ?>"
                    ><?php echo esc_html($label); ?></a>
                <?php endforeach; ?>
            </nav>
        </div>
    </header>

    <div class="pl-bp-ba-container">
        <section class="pl-bp-ba-hero">
            <?php
            $hero_post = null;
            if ($posts->have_posts()) {
                $posts->the_post();
                $hero_post = get_post();
            }

            $sidebar_posts = [];
            if ($posts->have_posts()) {
                for ($i = 0; $i < 2 && $posts->have_posts(); $i++) {
                    $posts->the_post();
                    $sidebar_posts[] = get_post();
                }
            }
            $sidebar_feature_post = null;
            $sidebar_feature = get_posts([
                'post_type' => 'post',
                'post_status' => 'publish',
                'posts_per_page' => 1,
                'offset' => 3,
                'ignore_sticky_posts' => true,
                'tax_query' => isset($query_args['tax_query']) ? $query_args['tax_query'] : [],
            ]);
            if (is_array($sidebar_feature) && isset($sidebar_feature[0]) && $sidebar_feature[0] instanceof WP_Post) {
                $sidebar_feature_post = $sidebar_feature[0];
            }
            wp_reset_postdata();
            ?>

            <?php if ($hero_post instanceof WP_Post): ?>
                <article class="pl-bp-ba-featured">
                    <a class="pl-bp-ba-featuredLink" href="<?php echo esc_url(get_permalink($hero_post)); ?>">
                        <div class="pl-bp-ba-featuredMedia">
                            <?php if (has_post_thumbnail($hero_post)): ?>
                                <?php echo get_the_post_thumbnail($hero_post, 'large', ['class' => 'pl-bp-ba-img', 'loading' => 'eager']); ?>
                            <?php else: ?>
                                <div class="pl-bp-ba-img pl-bp-ba-imgFallback" aria-hidden="true"></div>
                            <?php endif; ?>
                        </div>
                        <?php
                        $cats = get_the_category($hero_post->ID);
                        $cat_name = (is_array($cats) && isset($cats[0]) && $cats[0] instanceof WP_Term) ? (string) $cats[0]->name : '';
                        ?>
                        <?php if ($cat_name !== ''): ?>
                            <div class="pl-bp-ba-kicker"><?php echo esc_html($cat_name); ?></div>
                        <?php endif; ?>
                        <h1 class="pl-bp-ba-h1"><?php echo esc_html(get_the_title($hero_post)); ?></h1>
                        <p class="pl-bp-ba-excerpt"><?php echo esc_html(pl_bp_blog_archive_post_lead_text($hero_post, 42)); ?></p>
                        <div class="pl-bp-ba-byline">
                            Por <?php echo esc_html(pl_bp_blog_archive_author_name((int) $hero_post->post_author)); ?>
                            <span class="pl-bp-ba-bylineSep">|</span>
                            <?php echo esc_html(get_the_date('j F, Y', $hero_post)); ?>
                        </div>
                    </a>
                </article>
            <?php endif; ?>

            <aside class="pl-bp-ba-sidebar">
                <div class="pl-bp-ba-sectionTitle">Opinión</div>
                <?php foreach ($sidebar_posts as $sp): ?>
                    <?php if (!($sp instanceof WP_Post)) continue; ?>
                    <article class="pl-bp-ba-opItem">
                        <a class="pl-bp-ba-opLink" href="<?php echo esc_url(get_permalink($sp)); ?>">
                            <div class="pl-bp-ba-opAuthor"><?php echo esc_html(pl_bp_blog_archive_author_name((int) $sp->post_author)); ?></div>
                            <h3 class="pl-bp-ba-opTitle"><?php echo esc_html(get_the_title($sp)); ?></h3>
                            <p class="pl-bp-ba-opLead"><?php echo esc_html(pl_bp_blog_archive_post_lead_text($sp, 22)); ?></p>
                        </a>
                    </article>
                <?php endforeach; ?>

                <?php if ($sidebar_feature_post instanceof WP_Post): ?>
                    <div class="pl-bp-ba-opinionMore">
                        <div class="pl-bp-ba-opItem">
                            <a class="pl-bp-ba-opLink" href="<?php echo esc_url(get_permalink($sidebar_feature_post)); ?>">
                                <div class="pl-bp-ba-opAuthor"><?php echo esc_html(pl_bp_blog_archive_author_name((int) $sidebar_feature_post->post_author)); ?></div>
                                <h3 class="pl-bp-ba-opTitle"><?php echo esc_html(get_the_title($sidebar_feature_post)); ?></h3>
                                <p class="pl-bp-ba-opLead"><?php echo esc_html(pl_bp_blog_archive_post_lead_text($sidebar_feature_post, 22)); ?></p>
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </aside>
        </section>

        <div class="pl-bp-ba-sectionTitle">Lo Último</div>

        <section class="pl-bp-ba-grid" aria-label="Últimas noticias">
            <?php
            $grid_query = new WP_Query($query_args);
            if ($grid_query->have_posts()):
                while ($grid_query->have_posts()):
                    $grid_query->the_post();
                    $p = get_post();
                    if (!($p instanceof WP_Post)) {
                        continue;
                    }
                    $cats = get_the_category($p->ID);
                    $cat_name = (is_array($cats) && isset($cats[0]) && $cats[0] instanceof WP_Term) ? (string) $cats[0]->name : '';
            ?>
                <article class="pl-bp-ba-card">
                    <a class="pl-bp-ba-cardLink" href="<?php the_permalink(); ?>">
                        <div class="pl-bp-ba-cardMedia">
                            <?php if (has_post_thumbnail($p)): ?>
                                <?php echo get_the_post_thumbnail($p, 'medium_large', ['class' => 'pl-bp-ba-cardImg', 'loading' => 'lazy']); ?>
                            <?php else: ?>
                                <div class="pl-bp-ba-cardImg pl-bp-ba-imgFallback" aria-hidden="true"></div>
                            <?php endif; ?>
                        </div>
                        <?php if ($cat_name !== ''): ?>
                            <div class="pl-bp-ba-kicker"><?php echo esc_html($cat_name); ?></div>
                        <?php endif; ?>
                        <h3 class="pl-bp-ba-cardTitle"><?php the_title(); ?></h3>
                        <p class="pl-bp-ba-cardLead"><?php echo esc_html(pl_bp_blog_archive_post_lead_text($p, 22)); ?></p>
                    </a>
                </article>
            <?php
                endwhile;
            endif;
            wp_reset_postdata();
            ?>
        </section>
    </div>
</main>

<?php
if (function_exists('pl_template_close')) {
    // Suppress the theme footer for the blog archive page.
    wp_footer();
    echo '</body></html>';
} else {
    // Classic themes: avoid get_footer() (it would print the footer). Close the document after wp_footer().
    wp_footer();
    echo '</body></html>';
}
