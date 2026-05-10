<?php
namespace Politeia\ReadingPlanner;

if (!defined('ABSPATH')) {
    exit;
}

class Ajax
{
    private static function normalize_search_value(string $value): string
    {
        $value = trim($value);
        if (function_exists('prs_normalize_title')) {
            return (string) prs_normalize_title($value);
        }

        $value = remove_accents($value);
        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/i', ' ', $value);
        $value = preg_replace('/\s+/', ' ', $value);

        return trim((string) $value);
    }

    private static function score_user_book_suggestion(string $query, string $title, string $author): int
    {
        $query = self::normalize_search_value($query);
        $title = self::normalize_search_value($title);
        $author = self::normalize_search_value($author);

        if ('' === $query || '' === $title) {
            return 0;
        }

        if ($query === $title) {
            return 1000;
        }

        $score = 0;
        if (str_starts_with($title, $query)) {
            $score += 700;
        } elseif (false !== strpos($title, $query)) {
            $score += 450;
        }

        $query_parts = array_filter(explode(' ', $query));
        $title_parts = array_filter(explode(' ', $title));
        foreach ($query_parts as $part) {
            foreach ($title_parts as $title_part) {
                if ('' !== $part && str_starts_with($title_part, $part)) {
                    $score += 60;
                    break;
                }
            }
        }

        if ('' !== $author) {
            $author_parts = array_filter(explode(' ', $author));
            foreach ($query_parts as $part) {
                foreach ($author_parts as $author_part) {
                    if ('' !== $part && str_starts_with($author_part, $part)) {
                        $score += 10;
                        break;
                    }
                }
            }
        }

        $score += max(0, 50 - abs(strlen($title) - strlen($query)));

        return $score;
    }

    /**
     * Initialize AJAX hooks.
     */
    public static function init(): void
    {
        add_action('wp_ajax_desist_reading_plan', array(__CLASS__, 'handle_desist_plan'));
        add_action('wp_ajax_prs_user_book_search', array(__CLASS__, 'handle_user_book_search'));
    }

    /**
     * Handle request to desist a reading plan.
     */
    public static function handle_desist_plan(): void
    {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'desist_plan_nonce')) {
            wp_send_json_error('Invalid nonce');
            return;
        }

        // Get plan ID
        $plan_id = isset($_POST['plan_id']) ? intval($_POST['plan_id']) : 0;
        if (!$plan_id) {
            wp_send_json_error('Invalid plan ID');
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'politeia_plans';

        // Get the plan to verify ownership
        $plan = $wpdb->get_row($wpdb->prepare(
            "SELECT user_id, status FROM {$table_name} WHERE id = %d",
            $plan_id
        ));

        if (!$plan) {
            wp_send_json_error('Plan not found');
            return;
        }

        // Verify user owns the plan
        $current_user_id = get_current_user_id();
        if ($plan->user_id != $current_user_id) {
            wp_send_json_error('You do not have permission to modify this plan');
            return;
        }

        // Update plan status to 'desisted'
        $updated = $wpdb->update(
            $table_name,
            array('status' => 'desisted'),
            array('id' => $plan_id),
            array('%s'),
            array('%d')
        );

        if ($updated === false) {
            wp_send_json_error('Failed to update plan status');
            return;
        }

        wp_send_json_success('Plan desisted successfully');
    }

    /**
     * Handle user book search for autocomplete.
     */
    public static function handle_user_book_search(): void
    {
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Login required.'), 403);
            return;
        }

        // Verify nonce (using checking specific search nonce)
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'prs_user_book_search')) {
            wp_send_json_error(array('message' => 'Invalid nonce.'), 403);
            return;
        }

        $query = isset($_POST['query']) ? sanitize_text_field(wp_unslash($_POST['query'])) : '';
        if ('' === $query || strlen($query) < 2) {
            wp_send_json(array('items' => array()));
            return;
        }

        $user_id = get_current_user_id();
        $results = array();

        if (function_exists('prs_get_user_books_for_library')) {
            $results = prs_get_user_books_for_library(
                $user_id,
                array(
                    'search' => $query,
                    'per_page' => 20,
                    'offset' => 0,
                    'order' => 'title_asc',
                )
            );
        }

        if (empty($results)) {
            global $wpdb;
            $ub_table = $wpdb->prefix . 'politeia_user_books';
            $b_table = $wpdb->prefix . 'politeia_books';
            $like = '%' . $wpdb->esc_like($query) . '%';
            $results = $wpdb->get_results($wpdb->prepare(
                "SELECT ub.id as user_book_id, ub.book_id, ub.pages as pages, ub.cover_reference as cover_reference,
                        b.title, b.year, b.pages as book_total_pages, b.cover_attachment_id, '' AS authors
                 FROM {$ub_table} ub
                 INNER JOIN {$b_table} b ON b.id = ub.book_id
                 WHERE ub.user_id = %d
                   AND ub.deleted_at IS NULL
                   AND (b.title LIKE %s OR b.normalized_title LIKE %s)
                 ORDER BY b.title ASC, ub.id ASC
                 LIMIT 20",
                $user_id,
                $like,
                $like
            ));
        }

        $items = array();
        if ($results) {
            foreach ($results as $row) {
                $bid = (int) ($row->book_id ?? 0);
                $title = isset($row->title) ? (string) $row->title : '';
                $authors = isset($row->authors) ? (string) $row->authors : '';
                if ('' === $authors && $bid > 0) {
                    $authors = self::get_authors_for_book($bid);
                }

                $pages = 0;
                if (isset($row->pages) && (int) $row->pages > 0) {
                    $pages = (int) $row->pages;
                } elseif (isset($row->user_pages) && (int) $row->user_pages > 0) {
                    $pages = (int) $row->user_pages;
                } elseif (isset($row->book_total_pages) && (int) $row->book_total_pages > 0) {
                    $pages = (int) $row->book_total_pages;
                } elseif (isset($row->canonical_pages) && (int) $row->canonical_pages > 0) {
                    $pages = (int) $row->canonical_pages;
                }

                // Solve cover
                $cover_url = '';
                $user_cover = isset($row->cover_reference) ? (string) $row->cover_reference : (isset($row->user_cover) ? (string) $row->user_cover : '');
                $cover_attachment_id = isset($row->cover_attachment_id) ? (int) $row->cover_attachment_id : 0;
                if ('' !== $user_cover) {
                    if (is_numeric($user_cover)) {
                        $cover_url = wp_get_attachment_image_url((int) $user_cover, 'medium');
                    } else {
                        $cover_url = esc_url_raw($user_cover);
                    }
                } elseif ($cover_attachment_id) {
                    $cover_url = wp_get_attachment_image_url($cover_attachment_id, 'medium');
                }

                $items[] = array(
                    'score' => self::score_user_book_suggestion($query, (string) $title, (string) $authors),
                    'user_book_id' => (int) ($row->user_book_id ?? 0),
                    'book_id' => $bid,
                    'title' => $title,
                    'author' => $authors,
                    'pages' => $pages,
                    'cover' => $cover_url,
                    'source' => 'user_library'
                );
            }

            usort(
                $items,
                static function (array $left, array $right): int {
                    $leftScore = (int) ($left['score'] ?? 0);
                    $rightScore = (int) ($right['score'] ?? 0);
                    if ($leftScore === $rightScore) {
                        return strcasecmp((string) ($left['title'] ?? ''), (string) ($right['title'] ?? ''));
                    }
                    return $rightScore <=> $leftScore;
                }
            );

            $items = array_slice($items, 0, 2);

            foreach ($items as &$item) {
                unset($item['score']);
            }
            unset($item);
        }

        wp_send_json(array('items' => $items));
    }

    private static function get_authors_for_book($book_id): string
    {
        global $wpdb;
        $ba_table = $wpdb->prefix . 'politeia_book_authors';
        $a_table = $wpdb->prefix . 'politeia_authors';

        $names = $wpdb->get_col($wpdb->prepare(
            "SELECT a.display_name 
             FROM {$a_table} a 
             INNER JOIN {$ba_table} ba ON ba.author_id = a.id 
             WHERE ba.book_id = %d 
             ORDER BY a.display_name ASC",
            $book_id
        ));

        return $names ? implode(', ', $names) : '';
    }
}

Ajax::init();
