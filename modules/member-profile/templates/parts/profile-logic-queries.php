<?php
if (!defined('ABSPATH')) exit;

// --- Filter Queries based on Portfolio ---
$pl_allow_courses = in_array('courses', $pl_allowed_tabs, true);
$pl_allow_writings = in_array('writings', $pl_allowed_tabs, true);
$pl_allow_specs = in_array('specializations', $pl_allowed_tabs, true);

$show_courses = $pl_allow_courses && !(isset($portfolio_settings['courses']) && $portfolio_settings['courses']->is_private == 1);
$show_writings = $pl_allow_writings && !(isset($portfolio_settings['writings']) && $portfolio_settings['writings']->is_private == 1);
$show_specs = $pl_allow_specs && !(isset($portfolio_settings['specializations']) && $portfolio_settings['specializations']->is_private == 1);

if ($show_courses && isset($portfolio_settings['courses'])) {
    if ($portfolio_settings['courses']->visibility_mode === 'selected') {
        $courses_ids = !empty($portfolio_settings['courses']->selected_ids) ? $portfolio_settings['courses']->selected_ids : [-1]; // -1 ensures no results if empty
    }
}
if ($show_writings && isset($portfolio_settings['writings'])) {
    if ($portfolio_settings['writings']->visibility_mode === 'selected') {
        $writings_ids = !empty($portfolio_settings['writings']->selected_ids) ? $portfolio_settings['writings']->selected_ids : [-1];
    }
}
if ($show_specs && isset($portfolio_settings['specializations'])) {
    if ($portfolio_settings['specializations']->visibility_mode === 'selected') {
        $specs_ids = !empty($portfolio_settings['specializations']->selected_ids) ? $portfolio_settings['specializations']->selected_ids : [-1];
    }
}

// Courses Query
$user_courses = [];
if ($show_courses) {
    $courses_args = [
        'post_type' => 'learni_course',
        'post_status' => 'publish',
        'author' => $user_id,
        'posts_per_page' => -1
    ];
    if (!empty($courses_ids)) {
        $courses_args['post__in'] = $courses_ids;
        $courses_args['orderby'] = 'post__in';
    }
    $courses_query = new WP_Query($courses_args);
    if ($courses_query->have_posts()) {
        while ($courses_query->have_posts()) {
            $courses_query->the_post();
            $user_courses[] = [
                'id' => get_the_ID(),
                'title' => get_the_title(),
                'price' => get_post_meta(get_the_ID(), 'learni_price', true) ?: 'Free',
                'img' => get_the_post_thumbnail_url(get_the_ID(), 'large') ?: 'https://images.unsplash.com/photo-1633356122544-f134324a6cee?auto=format&fit=crop&w=400&q=80',
                'link' => get_permalink()
            ];
        }
        wp_reset_postdata();
    }
}

// Writings Query
$user_writings = [];
if ($show_writings) {
    $writings_args = [
        'post_type' => 'post',
        'post_status' => 'publish',
        'author' => $user_id,
        'posts_per_page' => -1
    ];
    if (!empty($writings_ids)) {
        $writings_args['post__in'] = $writings_ids;
        $writings_args['orderby'] = 'post__in';
    }
    $writings_query = new WP_Query($writings_args);
    if ($writings_query->have_posts()) {
        while ($writings_query->have_posts()) {
            $writings_query->the_post();
            $categories = get_the_category();
            $category_name = !empty($categories) ? $categories[0]->name : 'Writing';
            $user_writings[] = [
                'id' => get_the_ID(),
                'category' => $category_name,
                'title' => get_the_title(),
                'img' => get_the_post_thumbnail_url(get_the_ID(), 'large') ?: 'https://images.unsplash.com/photo-1518770660439-4636190af475?auto=format&fit=crop&w=400&q=80',
                'link' => get_permalink()
            ];
        }
        wp_reset_postdata();
    }
}

// Specializations Query (Groups)
$user_specs = [];
if ($show_specs) {
    $specs_args = [
        'post_type' => 'learni_special',
        'post_status' => 'publish',
        'author' => $user_id,
        'posts_per_page' => -1
    ];
    if (!empty($specs_ids)) {
        $specs_args['post__in'] = $specs_ids;
        $specs_args['orderby'] = 'post__in';
    }
    $specs_query = new WP_Query($specs_args);
    if ($specs_query->have_posts()) {
        while ($specs_query->have_posts()) {
            $specs_query->the_post();
            $user_specs[] = [
                'id' => get_the_ID(),
                'title' => get_the_title(),
                'img' => get_the_post_thumbnail_url(get_the_ID(), 'large') ?: 'https://images.unsplash.com/photo-1579621970795-87f967b16ce8?auto=format&fit=crop&w=400&q=80',
                'link' => get_permalink()
            ];
        }
        wp_reset_postdata();
    }
}

// Book Notes (Thoughts Feed)
global $wpdb;
$table_notes = $wpdb->prefix . 'politeia_read_ses_notes';
$table_ub = $wpdb->prefix . 'politeia_user_books';
$table_books = $wpdb->prefix . 'politeia_books';
$table_book_authors = $wpdb->prefix . 'politeia_book_authors';
$table_authors = $wpdb->prefix . 'politeia_authors';

$user_notes = $wpdb->get_results( $wpdb->prepare(
    "SELECT 
        n.id as note_id,
        n.note, 
        n.created_at, 
        b.title AS book_title, 
        (SELECT GROUP_CONCAT(a.display_name SEPARATOR ', ') 
         FROM {$table_book_authors} ba 
         JOIN {$table_authors} a ON ba.author_id = a.id 
         WHERE ba.book_id = b.id
        ) AS book_author,
        ub.cover_url AS user_cover,
        b.cover_url AS book_cover,
        b.year AS book_year
    FROM {$table_notes} n
    JOIN {$table_ub} ub ON n.user_book_id = ub.id
    JOIN {$table_books} b ON ub.book_id = b.id
    WHERE n.user_id = %d AND n.visibility = 'public' AND n.note != ''
    ORDER BY n.created_at DESC",
    $user_id
) );

$book_thoughts = [];
foreach ( $user_notes as $note ) {
    $final_cover = !empty($note->user_cover) ? $note->user_cover : $note->book_cover;
    $cover = !empty($final_cover) ? $final_cover : 'https://via.placeholder.com/60x90?text=No+Cover';
    
    $book_thoughts[] = [
        'id' => $note->note_id,
        'user' => $display_name,
        'handle' => '@' . $userdata->user_nicename,
        'avatar' => $avatar_url,
        'time' => human_time_diff( strtotime( $note->created_at ), current_time( 'timestamp' ) ) . ' ago',
        'content' => $note->note,
        'book' => $note->book_title,
        'author' => $note->book_author ?: 'Unknown Author',
        'cover' => $cover,
        'date' => date_i18n( 'F j, Y', strtotime( $note->created_at ) ),
        'book_year' => $note->book_year ?: 'N/A'
    ];
}
