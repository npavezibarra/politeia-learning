<?php
/**
 * Frontend UI inventory (buttons + forms) for Politeia Learning.
 *
 * - Provides `[pl_ui_inventory]` shortcode (admin-only).
 * - Stores/creates a private WP page that embeds the shortcode.
 */
if (!defined('ABSPATH')) {
    exit;
}

class PL_Core_UI_Inventory
{
    public const OPTION_PAGE_ID = 'pl_ui_inventory_page_id';
    public const NONCE_ACTION_CREATE_PAGE = 'pl_ui_inventory_create_page';
    public const NONCE_ACTION_RESCAN = 'pl_ui_inventory_rescan';
    public const SHORTCODE = 'pl_ui_inventory';
    private const TRANSIENT_SCAN = 'pl_ui_inventory_scan_v1';

    public function __construct()
    {
        add_shortcode(self::SHORTCODE, [$this, 'render_shortcode']);
    }

    public static function get_page_id(): int
    {
        return absint(get_option(self::OPTION_PAGE_ID, 0));
    }

    /**
     * Create the UI inventory page once and store its post ID.
     */
    public static function create_page_if_missing(): int
    {
        $existing = self::get_page_id();
        if ($existing > 0 && get_post_status($existing)) {
            return $existing;
        }

        $post_id = wp_insert_post([
            'post_type' => 'page',
            'post_status' => 'private',
            'post_title' => 'Politeia UI Inventory',
            'post_name' => 'pl-ui-inventory',
            'post_content' => '[' . self::SHORTCODE . ']',
            'comment_status' => 'closed',
            'ping_status' => 'closed',
        ], true);

        if (is_wp_error($post_id)) {
            return 0;
        }

        update_option(self::OPTION_PAGE_ID, absint($post_id));
        return absint($post_id);
    }

    public function render_shortcode($atts = []): string
    {
        if (!current_user_can('manage_options')) {
            return '';
        }

        $atts = shortcode_atts([
            'course_id' => 0,
            'show_library' => 1,
        ], (array) $atts, self::SHORTCODE);

        $course_id = absint($atts['course_id'] ?? 0);
        if ($course_id <= 0) {
            $course_id = self::find_sample_course_id();
        }

        $show_library = (int) ($atts['show_library'] ?? 1) === 1;

        $sections = [];

        $key_forms = self::render_key_forms();
        if ($key_forms !== '') {
            $sections[] = self::wrap_section('Key Forms', $key_forms);
        }

        // Payments/subscriptions (PPS) shortcodes.
        if (shortcode_exists('politeia_subscriptions_marketplace')) {
            $sections[] = self::wrap_section('Subscriptions Marketplace', do_shortcode('[politeia_subscriptions_marketplace]'));
        }
        if (shortcode_exists('politeia_subscriptions_creator_dashboard')) {
            $sections[] = self::wrap_section('Subscriptions Creator Dashboard', do_shortcode('[politeia_subscriptions_creator_dashboard]'));
        }
        if (shortcode_exists('politeia_subscriptions_subscriber_dashboard')) {
            $sections[] = self::wrap_section('Subscriptions Subscriber Dashboard', do_shortcode('[politeia_subscriptions_subscriber_dashboard]'));
        }

        // Login/register module (auth links shortcode).
        if (shortcode_exists('pl_auth_links')) {
            $sections[] = self::wrap_section('Auth Links', do_shortcode('[pl_auth_links]'));
        }

        // Learni creator dashboard + quiz editor/creator.
        if (shortcode_exists('pcg_course_creator_dashboard')) {
            $sections[] = self::wrap_section('Learni Creator Dashboard', do_shortcode('[pcg_course_creator_dashboard]'));
        }

        $learni_course_id = self::find_sample_learni_course_id();
        if (shortcode_exists('politeia_quiz_creator')) {
            $sc = $learni_course_id > 0 ? '[politeia_quiz_creator course_id="' . (int) $learni_course_id . '"]' : '[politeia_quiz_creator]';
            $sections[] = self::wrap_section('Quiz Creator', do_shortcode($sc));
        }
        if (shortcode_exists('politeia_quiz_editor')) {
            $sc = $learni_course_id > 0 ? '[politeia_quiz_editor course_id="' . (int) $learni_course_id . '"]' : '[politeia_quiz_editor]';
            $sections[] = self::wrap_section('Quiz Editor', do_shortcode($sc));
        }

        // Bookshelf (ChatGPT module) UI bits.
        if (shortcode_exists('politeia_chatgpt_input')) {
            $sections[] = self::wrap_section('ChatGPT Input', do_shortcode('[politeia_chatgpt_input]'));
        }
        if (shortcode_exists('politeia_confirm_table')) {
            $sections[] = self::wrap_section('Confirm Table', do_shortcode('[politeia_confirm_table]'));
        }

        // Bookshelf: Add book + library tables/forms.
        if (shortcode_exists('politeia_add_book')) {
            $sections[] = self::wrap_section('Add Book', do_shortcode('[politeia_add_book]'));
        }
        if ($show_library && shortcode_exists('politeia_my_books')) {
            $sections[] = self::wrap_section('My Books (Library)', do_shortcode('[politeia_my_books render="full"]'));
        }

        // Reading Planner: create plan / start plan UI.
        if (shortcode_exists('politeia_reading_plan')) {
            $sections[] = self::wrap_section('Reading Plan', do_shortcode('[politeia_reading_plan]'));
        }

        // Partner add (inline, per-course).
        if ($course_id > 0 && shortcode_exists('politeia_add_partner')) {
            $sections[] = self::wrap_section(
                'Add Partner (Inline)',
                do_shortcode('[politeia_add_partner course_id="' . (int) $course_id . '" label="Add Partner (Inventory)"]')
            );
        } else {
            $sections[] = self::wrap_section(
                'Add Partner (Inline)',
                '<p style="margin:0;">No sample course found to render partner UI.</p>'
            );
        }

        // Course Partner modal (normally only on course pages). Force-load for this page.
        if (class_exists('PL_Course_Partner_Modal')) {
            $sections[] = self::wrap_section(
                'Add Partner (Modal)',
                self::render_course_partner_modal_inventory($course_id)
            );
        }

        $scan = self::discover_ui_elements();
        if (!empty($scan['button_examples']) || !empty($scan['button_class_tokens'])) {
            $sections[] = self::wrap_section(
                'Auto-Discovered Button Styles',
                self::render_button_gallery($scan)
            );
        }
        if (!empty($scan['forms'])) {
            $sections[] = self::wrap_section(
                'Auto-Discovered Forms',
                self::render_forms_list($scan['forms'])
            );
        }

        $sections[] = self::wrap_section(
            'Proposal: Unified UI Standard',
            self::render_unification_proposal()
        );

        $html = '';
        $html .= '<div class="pl-ui-inventory" style="max-width:1200px;margin:0 auto;padding:24px;">';
        $html .= '<h1 style="margin:0 0 12px 0;">UI Inventory</h1>';
        $html .= '<p style="margin:0 0 24px 0;color:#475569;">Politeia Learning: botones y formularios renderizados desde los shortcodes y UI reales del plugin.</p>';
        $html .= implode("\n", $sections);
        $html .= '</div>';

        return $html;
    }

    private static function wrap_section(string $title, string $body_html): string
    {
        $out = '';
        $out .= '<section class="pl-ui-inventory__section" style="margin:0 0 28px 0;padding:16px;border:1px solid #e2e8f0;border-radius:12px;background:#fff;">';
        $out .= '<h2 style="margin:0 0 12px 0;font-size:16px;line-height:1.2;">' . esc_html($title) . '</h2>';
        $out .= '<div class="pl-ui-inventory__body">' . $body_html . '</div>';
        $out .= '</section>';
        return $out;
    }

    private static function find_sample_course_id(): int
    {
        $types = ['sfwd-courses', 'learni_course'];
        foreach ($types as $type) {
            $posts = get_posts([
                'post_type' => $type,
                'post_status' => 'publish',
                'numberposts' => 1,
                'orderby' => 'date',
                'order' => 'DESC',
                'fields' => 'ids',
            ]);
            if (!empty($posts)) {
                return absint($posts[0]);
            }
        }
        return 0;
    }

    private static function find_sample_learni_course_id(): int
    {
        $posts = get_posts([
            'post_type' => 'learni_course',
            'post_status' => 'publish',
            'numberposts' => 1,
            'orderby' => 'date',
            'order' => 'DESC',
            'fields' => 'ids',
        ]);
        if (!empty($posts)) {
            return absint($posts[0]);
        }
        return 0;
    }

    private static function render_course_partner_modal_inventory(int $course_id): string
    {
        $course_id = max(0, (int) $course_id);

        $enabled_filter = static function ($should_load) {
            return true;
        };
        $course_filter = static function ($detected_id) use ($course_id) {
            return $course_id > 0 ? $course_id : (int) $detected_id;
        };

        add_filter('pl_course_partner_modal_should_load', $enabled_filter, 10, 1);
        add_filter('pl_course_partner_modal_course_id', $course_filter, 10, 1);

        // Enqueue assets + render modal markup (captured).
        PL_Course_Partner_Modal::enqueue_assets();
        ob_start();
        PL_Course_Partner_Modal::render_modal();
        $modal_html = (string) ob_get_clean();

        remove_filter('pl_course_partner_modal_should_load', $enabled_filter, 10);
        remove_filter('pl_course_partner_modal_course_id', $course_filter, 10);

        $button = '<button type="button" class="pl-ui-inventory-open-modal button button-primary" data-pl-ui-inv-open-partner-modal="1">Open Partner Modal</button>';
        $script = '<script>(function(){var b=document.querySelector("[data-pl-ui-inv-open-partner-modal]");if(!b)return;b.addEventListener("click",function(){var o=document.getElementById("plPartnerOverlay");if(!o)return;o.hidden=false;o.classList.add("is-open");});})();</script>';

        return $button . $modal_html . $script;
    }

    private static function render_key_forms(): string
    {
        $out = '';
        $out .= '<div style="display:grid;gap:18px;">';

        // Add Book: extract the form element for easier visual review.
        if (shortcode_exists('politeia_add_book')) {
            $full = do_shortcode('[politeia_add_book]');
            $modal = self::extract_element_by_id($full, 'prs-add-book-modal');
            $out .= '<div>';
            $out .= '<div style="font-weight:800;margin:0 0 8px 0;">Agregar Libro</div>';
            $out .= '<div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin:0 0 10px 0;">';
            $out .= '<button type="button" class="button button-primary" data-pl-open-add-book="1">Open Agregar Libro</button>';
            $out .= '</div>';
            $out .= $modal !== '' ? $modal : $full;
            $out .= '</div>';
        }

        // Library filters: modal + form.
        if (shortcode_exists('politeia_my_books')) {
            $content = do_shortcode('[politeia_my_books render="content"]');
            $dashboard = self::extract_element_by_id($content, 'prs-filter-dashboard');
            $overlay = self::extract_element_by_id($content, 'prs-filter-overlay');
            $out .= '<div>';
            $out .= '<div style="font-weight:800;margin:0 0 8px 0;">Filtro Libros</div>';
            $out .= '<div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin:0 0 10px 0;">';
            $out .= '<button type="button" class="button button-secondary" data-pl-open-filter="1">Open Filtro</button>';
            $out .= '</div>';
            $out .= ($overlay !== '' ? $overlay : '') . ($dashboard !== '' ? $dashboard : $content);
            $out .= '</div>';
        }

        // Add session (manual): fixture markup from template so we can review visuals without needing a book page.
        $out .= '<div>';
        $out .= '<div style="font-weight:800;margin:0 0 8px 0;">Agregar Sesion (Manual)</div>';
        $out .= self::render_add_session_form_fixture();
        $out .= '</div>';

        $out .= '<script>(function(){'
            . 'var ab=document.querySelector("[data-pl-open-add-book]");'
            . 'if(ab){ab.addEventListener("click",function(){var m=document.getElementById("prs-add-book-modal"); if(!m) return; m.style.display="flex"; m.removeAttribute("hidden"); m.setAttribute("aria-hidden","false");});}'
            . 'var fl=document.querySelector("[data-pl-open-filter]");'
            . 'if(fl){fl.addEventListener("click",function(){var d=document.getElementById("prs-filter-dashboard"); var o=document.getElementById("prs-filter-overlay"); if(o){o.hidden=false;o.style.display="block";} if(d){d.hidden=false; d.style.display="block"; d.setAttribute("aria-hidden","false");}});}'
            . '})();</script>';

        $out .= '</div>';

        return $out;
    }

    private static function extract_element_by_id(string $html, string $id): string
    {
        $html = trim($html);
        if ($html === '' || $id === '') {
            return '';
        }

        if (!class_exists('DOMDocument')) {
            return '';
        }

        $prev = libxml_use_internal_errors(true);
        $doc = new DOMDocument();
        $wrapped = '<!doctype html><html><head><meta charset="utf-8"></head><body>' . $html . '</body></html>';
        $loaded = $doc->loadHTML($wrapped, LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        if (!$loaded) {
            return '';
        }

        $node = $doc->getElementById($id);
        if (!$node) {
            return '';
        }

        $out = $doc->saveHTML($node);
        return is_string($out) ? $out : '';
    }

    private static function render_add_session_form_fixture(): string
    {
        // Ensure the bookshelf reading styles are present (this is just a visual inventory).
        if (function_exists('wp_enqueue_style')) {
            wp_enqueue_style('politeia-reading');
            wp_enqueue_script('politeia-my-book');
        }

        $out = '';
        $out .= '<div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">';
        $out .= '<button type="button" class="prs-sr-btn prs-sr-btn--save" data-pl-open-ms="1"><span class="prs-sr-btn-icon" aria-hidden="true">+</span><span class="prs-sr-btn-label">Open Add Session</span></button>';
        $out .= '</div>';

        // Markup based on `templates/my-book-single-ver-2/reading-sessions.php` around `prs-manual-session-form`.
        $out .= '<div id="prs-manual-session-modal" class="prs-session-modal" role="dialog" aria-modal="true" aria-hidden="true" aria-label="Add session" style="display:none;">';
        $out .= '<div class="prs-session-modal__content">';
        $out .= '<button type="button" id="prs-manual-session-close" class="prs-session-modal__close" aria-label="Close add session">×</button>';
        $out .= '<div class="prs-ms" data-book-id="0">';
        $out .= '<div class="prs-ms__header">Add Session</div>';
        $out .= '<form id="prs-manual-session-form" class="prs-sr-form prs-ms__form" autocomplete="off">';
        $out .= '<div class="prs-ms__row">';
        $out .= '<div class="prs-sr-field">';
        $out .= '<input type="hidden" id="prs-ms-start-iso" name="start_datetime" value="" />';
        $out .= '<div class="prs-ms-dtpfield">';
        $out .= '<input type="text" id="prs-ms-start-dt" class="prs-sr-input" value="" readonly="readonly" />';
        $out .= '<button type="button" class="prs-ms-dtpbtn" data-ms-dtp-open="start" aria-label="Open date picker"></button>';
        $out .= '</div>';
        $out .= '<label for="prs-ms-start-dt" class="prs-sr-label">Date & time*</label>';
        $out .= '</div>';
        $out .= '<div class="prs-sr-field">';
        $out .= '<input type="number" min="1" id="prs-ms-start-page" name="start_page" class="prs-sr-input" inputmode="numeric" required />';
        $out .= '<label for="prs-ms-start-page" class="prs-sr-label">Start page*</label>';
        $out .= '</div>';
        $out .= '</div>';
        $out .= '<div class="prs-ms__row">';
        $out .= '<div class="prs-sr-field">';
        $out .= '<input type="hidden" id="prs-ms-end-iso" name="end_datetime" value="" />';
        $out .= '<div class="prs-ms-dtpfield">';
        $out .= '<input type="text" id="prs-ms-end-dt" class="prs-sr-input" value="" readonly="readonly" />';
        $out .= '<button type="button" class="prs-ms-dtpbtn" data-ms-dtp-open="end" aria-label="Open date picker"></button>';
        $out .= '</div>';
        $out .= '<label for="prs-ms-end-dt" class="prs-sr-label">Date & time*</label>';
        $out .= '</div>';
        $out .= '<div class="prs-sr-field">';
        $out .= '<input type="number" min="1" id="prs-ms-end-page" name="end_page" class="prs-sr-input" inputmode="numeric" required />';
        $out .= '<label for="prs-ms-end-page" class="prs-sr-label">Final page*</label>';
        $out .= '</div>';
        $out .= '</div>';
        $out .= '<div class="prs-sr-field">';
        $out .= '<input type="text" id="prs-ms-chapter" name="chapter_name" class="prs-sr-input" placeholder="Chapter" maxlength="255" />';
        $out .= '<label for="prs-ms-chapter" class="prs-sr-label">Chapter</label>';
        $out .= '</div>';
        $out .= '<div class="prs-sr-actions">';
        $out .= '<button type="submit" class="prs-sr-btn prs-sr-btn--save"><span class="prs-sr-btn-icon" aria-hidden="true">&#10003;</span><span class="prs-sr-btn-label">Save Session</span></button>';
        $out .= '</div>';
        $out .= '<p id="prs-ms-status" class="prs-ms__status" role="status" aria-live="polite"></p>';
        $out .= '</form>';
        $out .= '</div>';
        $out .= '</div>';
        $out .= '</div>';

        $out .= '<script>(function(){var open=document.querySelector("[data-pl-open-ms]");var modal=document.getElementById("prs-manual-session-modal");var close=document.getElementById("prs-manual-session-close");if(open&&modal){open.addEventListener("click",function(){modal.style.display=\"block\";modal.setAttribute(\"aria-hidden\",\"false\");});}if(close&&modal){close.addEventListener(\"click\",function(){modal.style.display=\"none\";modal.setAttribute(\"aria-hidden\",\"true\");});}})();</script>';

        return $out;
    }

    private static function discover_ui_elements(): array
    {
        $cached = get_transient(self::TRANSIENT_SCAN);
        if (is_array($cached)) {
            return $cached;
        }

        $root = rtrim((string) PL_PATH, '/');
        if ($root === '') {
            return [];
        }

        $button_examples = [];
        $button_class_tokens = [];
        $forms = [];

        $skip_dirs = [
            $root . '/vendor',
            $root . '/languages',
            $root . '/.git',
            $root . '/node_modules',
        ];

        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($it as $file) {
            if (!($file instanceof SplFileInfo)) {
                continue;
            }

            $path = (string) $file->getPathname();
            $is_skipped = false;
            foreach ($skip_dirs as $skip) {
                if ($skip !== '' && str_starts_with($path, $skip)) {
                    $is_skipped = true;
                    break;
                }
            }
            if ($is_skipped) {
                continue;
            }

            $ext = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
            if (!in_array($ext, ['php', 'js'], true)) {
                continue;
            }

            $contents = @file_get_contents($path);
            if (!is_string($contents) || $contents === '') {
                continue;
            }

            // class="..." and class='...'
            if (preg_match_all('/class\\s*=\\s*(["\\\'])([^"\\\']{1,500})\\1/i', $contents, $m, PREG_SET_ORDER)) {
                foreach ($m as $match) {
                    $class_attr = isset($match[2]) ? (string) $match[2] : '';
                    $class_attr = trim(preg_replace('/\\s+/', ' ', $class_attr));
                    if ($class_attr === '') {
                        continue;
                    }

                    $tokens = preg_split('/\\s+/', $class_attr) ?: [];
                    foreach ($tokens as $t) {
                        $t = trim((string) $t);
                        if ($t === '' || strlen($t) > 80) {
                            continue;
                        }
                        $button_class_tokens[$t] = true;
                    }

                    if (preg_match('/\\b(btn|button|cta|tab|pill)\\b/i', $class_attr)) {
                        $button_examples[$class_attr] = true;
                    }
                }
            }

            // JS: el.className = '...'
            if (preg_match_all('/\\bclassName\\s*=\\s*(["\\\'])([^"\\\']{1,500})\\1/', $contents, $jm, PREG_SET_ORDER)) {
                foreach ($jm as $match) {
                    $class_attr = isset($match[2]) ? (string) $match[2] : '';
                    $class_attr = trim(preg_replace('/\\s+/', ' ', $class_attr));
                    if ($class_attr === '') {
                        continue;
                    }
                    $tokens = preg_split('/\\s+/', $class_attr) ?: [];
                    foreach ($tokens as $t) {
                        $t = trim((string) $t);
                        if ($t === '' || strlen($t) > 80) {
                            continue;
                        }
                        $button_class_tokens[$t] = true;
                    }
                    if (preg_match('/\\b(btn|button|cta|tab|pill)\\b/i', $class_attr)) {
                        $button_examples[$class_attr] = true;
                    }
                }
            }

            // JS: el.classList.add('a', 'b') or el.classList.add("a")
            if (preg_match_all('/\\bclassList\\.add\\(([^\\)]{1,300})\\)/', $contents, $am, PREG_SET_ORDER)) {
                foreach ($am as $match) {
                    $args = isset($match[1]) ? (string) $match[1] : '';
                    if ($args === '') {
                        continue;
                    }
                    if (preg_match_all('/(["\\\'])([^"\\\']{1,80})\\1/', $args, $qm, PREG_SET_ORDER)) {
                        $tokens = [];
                        foreach ($qm as $q) {
                            $tok = isset($q[2]) ? trim((string) $q[2]) : '';
                            if ($tok !== '') {
                                $tokens[] = $tok;
                            }
                        }
                        foreach ($tokens as $t) {
                            $button_class_tokens[$t] = true;
                        }
                        $combo = implode(' ', $tokens);
                        if ($combo !== '' && preg_match('/\\b(btn|button|cta|tab|pill)\\b/i', $combo)) {
                            $button_examples[$combo] = true;
                        }
                    }
                }
            }

            // <form ...>
            if (preg_match_all('/<form\\b[^>]{0,1200}>/i', $contents, $fm, PREG_OFFSET_CAPTURE)) {
                foreach ($fm[0] as $hit) {
                    $snippet = (string) ($hit[0] ?? '');
                    $offset = (int) ($hit[1] ?? 0);
                    $line = substr_count(substr($contents, 0, max(0, $offset)), "\n") + 1;

                    $forms[] = [
                        'file' => $path,
                        'line' => $line,
                        'snippet' => trim(preg_replace('/\\s+/', ' ', $snippet)),
                    ];
                }
            }
        }

        $button_examples = array_keys($button_examples);
        sort($button_examples);
        $button_class_tokens = array_keys($button_class_tokens);
        sort($button_class_tokens);

        // Keep page manageable.
        $button_examples = array_slice($button_examples, 0, 140);
        $button_class_tokens = array_slice($button_class_tokens, 0, 180);
        $forms = array_slice($forms, 0, 200);

        $data = [
            'button_examples' => $button_examples,
            'button_class_tokens' => $button_class_tokens,
            'forms' => $forms,
        ];

        set_transient(self::TRANSIENT_SCAN, $data, HOUR_IN_SECONDS);
        return $data;
    }

    private static function render_button_gallery(array $scan): string
    {
        $examples = isset($scan['button_examples']) && is_array($scan['button_examples']) ? $scan['button_examples'] : [];
        $tokens = isset($scan['button_class_tokens']) && is_array($scan['button_class_tokens']) ? $scan['button_class_tokens'] : [];

        $out = '';
        $out .= '<div style="display:grid;gap:16px;">';
        $out .= '<div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;">';
        $out .= '<button type="button" class="button">WP button</button>';
        $out .= '<button type="button" class="button button-secondary">WP secondary</button>';
        $out .= '<button type="button" class="button button-primary">WP primary</button>';
        $out .= '</div>';

        if (!empty($examples)) {
            $out .= '<div>';
            $out .= '<div style="font-weight:700;margin:6px 0 10px 0;">Examples (class attribute combos)</div>';
            $out .= '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:10px;">';
            foreach ($examples as $class_attr) {
                $label = $class_attr;
                if (strlen($label) > 120) {
                    $label = substr($label, 0, 117) . '...';
                }
                $safe_class = esc_attr($class_attr);
                $out .= '<div style="border:1px solid #e2e8f0;border-radius:10px;padding:12px;background:#f8fafc;">';
                $out .= '<div style="font-family:ui-monospace,Menlo,Monaco,monospace;font-size:12px;color:#334155;margin:0 0 10px 0;word-break:break-word;">' . esc_html($label) . '</div>';
                $out .= '<button type="button" class="' . $safe_class . '">Button</button> ';
                $out .= '<a href="#" class="' . $safe_class . '" onclick="return false;" role="button">Link</a>';
                $out .= '</div>';
            }
            $out .= '</div>';
            $out .= '</div>';
        }

        if (!empty($tokens)) {
            $out .= '<div>';
            $out .= '<div style="font-weight:700;margin:6px 0 10px 0;">Tokens</div>';
            $out .= '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:10px;">';
            foreach ($tokens as $t) {
                if (!preg_match('/(btn|button|cta|tab|pill)/i', $t)) {
                    continue;
                }
                $safe = esc_attr($t);
                $out .= '<div style="border:1px solid #e2e8f0;border-radius:10px;padding:12px;background:#ffffff;">';
                $out .= '<div style="font-family:ui-monospace,Menlo,Monaco,monospace;font-size:12px;color:#334155;margin:0 0 10px 0;word-break:break-word;">' . esc_html($t) . '</div>';
                $out .= '<button type="button" class="' . $safe . '">Button</button> ';
                $out .= '<a href="#" class="' . $safe . '" onclick="return false;" role="button">Link</a>';
                $out .= '</div>';
            }
            $out .= '</div>';
            $out .= '</div>';
        }

        $out .= '</div>';
        return $out;
    }

    private static function render_forms_list(array $forms): string
    {
        $out = '';
        $out .= '<div style="overflow:auto;">';
        $out .= '<table class="widefat striped">';
        $out .= '<thead><tr><th>File</th><th>Line</th><th>Form</th></tr></thead><tbody>';
        foreach ($forms as $f) {
            $file = isset($f['file']) ? (string) $f['file'] : '';
            $line = isset($f['line']) ? (int) $f['line'] : 0;
            $snippet = isset($f['snippet']) ? (string) $f['snippet'] : '';
            $file_short = str_replace(rtrim((string) PL_PATH, '/') . '/', '', $file);
            $out .= '<tr>';
            $out .= '<td><code>' . esc_html($file_short) . '</code></td>';
            $out .= '<td>' . esc_html((string) $line) . '</td>';
            $out .= '<td><code>' . esc_html($snippet) . '</code></td>';
            $out .= '</tr>';
        }
        $out .= '</tbody></table></div>';
        return $out;
    }

    private static function render_unification_proposal(): string
    {
        $out = '';

        // This UI kit is self-contained (inventory-only) and intentionally does not ship global styles yet.
        $out .= '<style>
        .pl-ui-kit{--pl-font:ui-sans-serif,system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,"Apple Color Emoji","Segoe UI Emoji";
          --pl-radius:10px;--pl-radius-sm:8px;--pl-border:#e2e8f0;--pl-bg:#ffffff;--pl-fg:#0f172a;--pl-muted:#475569;
          --pl-primary:#0f172a;--pl-primary-contrast:#ffffff;--pl-secondary:#ffffff;--pl-secondary-fg:#0f172a;
          --pl-ghost:#f1f5f9;--pl-danger:#b91c1c;--pl-focus:#2563eb;
          font-family:var(--pl-font);color:var(--pl-fg)}
        .pl-ui-kit *{box-sizing:border-box}
        .pl-ui-kit__grid{display:grid;grid-template-columns:320px 1fr;gap:14px;align-items:start}
        @media (max-width: 860px){.pl-ui-kit__grid{grid-template-columns:1fr}}
        .pl-ui-kit__panel{border:1px solid var(--pl-border);border-radius:12px;background:var(--pl-bg);padding:14px}
        .pl-ui-kit__title{margin:0 0 6px 0;font-size:14px;font-weight:800}
        .pl-ui-kit__muted{margin:0;color:var(--pl-muted);font-size:13px}
        .pl-ui-kit__row{display:grid;grid-template-columns:1fr 120px;gap:10px;align-items:center;margin-top:10px}
        .pl-ui-kit__row label{font-size:12px;color:var(--pl-muted)}
        .pl-ui-kit__row input[type=color],.pl-ui-kit__row input[type=number]{width:120px;height:34px;border:1px solid var(--pl-border);border-radius:8px;padding:0 8px;background:#fff}
        .pl-ui-kit__seg{display:flex;gap:8px;flex-wrap:wrap;margin-top:10px}
        .pl-ui-kit__seg button{border:1px solid var(--pl-border);background:#fff;border-radius:999px;padding:6px 10px;font-size:12px;cursor:pointer}
        .pl-ui-kit__seg button[aria-pressed=true]{background:var(--pl-ghost);border-color:#cbd5e1}
        .pl-ui-kit__preview{border:1px solid var(--pl-border);border-radius:12px;background:linear-gradient(180deg,#fff,#f8fafc);padding:16px;min-height:240px}
        .pl-ui-kit__preview h4{margin:0 0 10px 0;font-size:13px;color:var(--pl-muted);font-weight:700}
        .pl-ui-kit__stack{display:flex;flex-wrap:wrap;gap:10px;align-items:center}
        .pl-ui-kit__stack + .pl-ui-kit__stack{margin-top:12px}

        .pl-btn{appearance:none;border:1px solid transparent;border-radius:var(--pl-radius-sm);font-weight:800;letter-spacing:.6px;
          text-transform:uppercase;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;gap:8px;
          transition:transform .06s ease,background .15s ease,border-color .15s ease,opacity .15s ease,box-shadow .15s ease;
          font-family:var(--pl-font);text-decoration:none;user-select:none}
        .pl-btn:active{transform:translateY(1px)}
        .pl-btn:focus-visible{outline:0;box-shadow:0 0 0 3px color-mix(in srgb, var(--pl-focus) 30%, transparent)}
        .pl-btn[disabled],.pl-btn[aria-disabled=true]{opacity:.45;cursor:not-allowed;transform:none}

        .pl-btn--sm{height:32px;padding:0 12px;font-size:12px}
        .pl-btn--md{height:40px;padding:0 16px;font-size:13px}
        .pl-btn--lg{height:50px;padding:0 18px;font-size:14px}

        .pl-btn--primary{background:var(--pl-primary);color:var(--pl-primary-contrast)}
        .pl-btn--primary:hover{background:color-mix(in srgb, var(--pl-primary) 88%, #000)}

        .pl-btn--secondary{background:var(--pl-secondary);color:var(--pl-secondary-fg);border-color:var(--pl-border)}
        .pl-btn--secondary:hover{background:var(--pl-ghost)}

        .pl-btn--ghost{background:var(--pl-ghost);color:var(--pl-secondary-fg);border-color:transparent}
        .pl-btn--ghost:hover{background:color-mix(in srgb, var(--pl-ghost) 85%, #cbd5e1)}

        .pl-btn--destructive{background:var(--pl-danger);color:#fff}
        .pl-btn--destructive:hover{background:color-mix(in srgb, var(--pl-danger) 88%, #000)}

        .pl-btn--link{background:transparent;color:var(--pl-focus);border-color:transparent;text-transform:none;letter-spacing:0;font-weight:700;padding:0}
        .pl-btn--link:hover{text-decoration:underline}

        .pl-field{display:grid;gap:6px}
        .pl-label{font-size:12px;font-weight:700;color:var(--pl-muted)}
        .pl-input,.pl-select,.pl-textarea{width:100%;border:1px solid var(--pl-border);border-radius:10px;background:#fff;padding:10px 12px;font-size:14px}
        .pl-input,.pl-select{height:40px}
        .pl-textarea{min-height:96px;resize:vertical}
        .pl-help{font-size:12px;color:var(--pl-muted)}
        .pl-error{font-size:12px;color:var(--pl-danger)}
        .pl-row{display:grid;grid-template-columns:1fr 1fr;gap:10px}
        @media (max-width: 640px){.pl-row{grid-template-columns:1fr}}
        </style>';

        $out .= '<div class="pl-ui-kit">';
        $out .= '<p style="margin:0 0 12px 0;color:var(--pl-muted);">En vez de leer una propuesta textual, acá puedes mirar y ajustar el estándar. Esto es solo un preview dentro del inventario.</p>';

        $out .= '<div class="pl-ui-kit__grid">';

        // Controls panel
        $out .= '<div class="pl-ui-kit__panel">';
        $out .= '<div class="pl-ui-kit__title">Tokens</div>';
        $out .= '<p class="pl-ui-kit__muted">Ajusta colores/radius para ver el sistema.</p>';
        $out .= '<div class="pl-ui-kit__row"><label for="plTokPrimary">Primary</label><input id="plTokPrimary" type="color" value="#0f172a" /></div>';
        $out .= '<div class="pl-ui-kit__row"><label for="plTokFocus">Focus</label><input id="plTokFocus" type="color" value="#2563eb" /></div>';
        $out .= '<div class="pl-ui-kit__row"><label for="plTokDanger">Danger</label><input id="plTokDanger" type="color" value="#b91c1c" /></div>';
        $out .= '<div class="pl-ui-kit__row"><label for="plTokRadius">Radius</label><input id="plTokRadius" type="number" min="6" max="16" step="1" value="10" /></div>';

        $out .= '<div class="pl-ui-kit__title" style="margin-top:14px;">Pick Size</div>';
        $out .= '<div class="pl-ui-kit__seg" role="group" aria-label="Pick size">';
        $out .= '<button type="button" data-pl-size="sm" aria-pressed="false">Small</button>';
        $out .= '<button type="button" data-pl-size="md" aria-pressed="true">Medium</button>';
        $out .= '<button type="button" data-pl-size="lg" aria-pressed="false">Large</button>';
        $out .= '</div>';

        $out .= '<div class="pl-ui-kit__title" style="margin-top:14px;">Pick Variant</div>';
        $out .= '<div class="pl-ui-kit__seg" role="group" aria-label="Pick variant">';
        $out .= '<button type="button" data-pl-variant="primary" aria-pressed="true">Primary</button>';
        $out .= '<button type="button" data-pl-variant="secondary" aria-pressed="false">Secondary</button>';
        $out .= '<button type="button" data-pl-variant="ghost" aria-pressed="false">Ghost</button>';
        $out .= '<button type="button" data-pl-variant="destructive" aria-pressed="false">Danger</button>';
        $out .= '<button type="button" data-pl-variant="link" aria-pressed="false">Link</button>';
        $out .= '</div>';

        $out .= '<p class="pl-ui-kit__muted" style="margin-top:14px;">Regla sugerida: <strong>lg</strong> solo para 1 CTA principal por pantalla; <strong>md</strong> default; <strong>sm</strong> para tablas/paginación.</p>';
        $out .= '</div>';

        // Preview panel
        $out .= '<div class="pl-ui-kit__preview">';
        $out .= '<h4>Preview</h4>';
        $out .= '<div class="pl-ui-kit__stack">';
        $out .= '<button type="button" class="pl-btn pl-btn--md pl-btn--primary" data-pl-preview-btn="1">Primary Action</button>';
        $out .= '<button type="button" class="pl-btn pl-btn--md pl-btn--secondary" data-pl-preview-secondary="1">Secondary</button>';
        $out .= '<button type="button" class="pl-btn pl-btn--md pl-btn--ghost" data-pl-preview-ghost="1">Ghost</button>';
        $out .= '<button type="button" class="pl-btn pl-btn--md pl-btn--destructive" data-pl-preview-danger="1">Delete</button>';
        $out .= '<a href="#" onclick="return false;" class="pl-btn pl-btn--link" data-pl-preview-link="1">Link action</a>';
        $out .= '</div>';

        $out .= '<div class="pl-ui-kit__stack">';
        $out .= '<button type="button" class="pl-btn pl-btn--md pl-btn--primary" disabled>Disabled</button>';
        $out .= '<button type="button" class="pl-btn pl-btn--md pl-btn--secondary" aria-disabled="true">Aria-disabled</button>';
        $out .= '</div>';

        $out .= '<h4 style="margin-top:16px;">Form Preview</h4>';
        $out .= '<div class="pl-row">';
        $out .= '<div class="pl-field"><div class="pl-label">Nombre</div><input class="pl-input" type="text" value="Ej: Nico" /><div class="pl-help">Helper text</div></div>';
        $out .= '<div class="pl-field"><div class="pl-label">Email</div><input class="pl-input" type="email" value="nico@politeia.cl" /><div class="pl-error">Error example</div></div>';
        $out .= '</div>';
        $out .= '<div class="pl-field" style="margin-top:10px;"><div class="pl-label">Tipo</div><select class="pl-select"><option>Opcion A</option><option>Opcion B</option></select></div>';
        $out .= '<div class="pl-field" style="margin-top:10px;"><div class="pl-label">Nota</div><textarea class="pl-textarea">Texto...</textarea></div>';
        $out .= '<div style="margin-top:12px;display:flex;gap:10px;flex-wrap:wrap;">';
        $out .= '<button type="button" class="pl-btn pl-btn--md pl-btn--primary">Guardar</button>';
        $out .= '<button type="button" class="pl-btn pl-btn--md pl-btn--secondary">Cancelar</button>';
        $out .= '</div>';

        $out .= '</div>';

        $out .= '</div>'; // grid

        // Tiny JS wiring.
        $out .= '<script>(function(){var root=document.currentScript&&document.currentScript.previousElementSibling; if(!root||!root.classList||!root.classList.contains("pl-ui-kit")){root=document.querySelector(".pl-ui-kit");} if(!root) return;'
            . 'function setVar(name,val){root.style.setProperty(name,val);} '
            . 'var primary=root.querySelector("#plTokPrimary"); var focus=root.querySelector("#plTokFocus"); var danger=root.querySelector("#plTokDanger"); var radius=root.querySelector("#plTokRadius");'
            . 'if(primary) primary.addEventListener("input",function(){setVar("--pl-primary",primary.value);});'
            . 'if(focus) focus.addEventListener("input",function(){setVar("--pl-focus",focus.value);});'
            . 'if(danger) danger.addEventListener("input",function(){setVar("--pl-danger",danger.value);});'
            . 'if(radius) radius.addEventListener("input",function(){var v=Math.max(6,Math.min(16,parseInt(radius.value||"10",10)||10)); setVar("--pl-radius",v+"px"); setVar("--pl-radius-sm",Math.max(6,v-2)+"px");});'
            . 'var size="md",variant="primary";'
            . 'function updatePressed(sel,attr,val){root.querySelectorAll(sel).forEach(function(b){b.setAttribute("aria-pressed", b.getAttribute(attr)===val ? "true":"false");});}'
            . 'function apply(){var btn=root.querySelector("[data-pl-preview-btn]"); if(!btn) return; btn.className="pl-btn pl-btn--"+size+" pl-btn--"+variant; }'
            . 'root.querySelectorAll("[data-pl-size]").forEach(function(b){b.addEventListener("click",function(){size=b.getAttribute("data-pl-size")||"md"; updatePressed("[data-pl-size]","data-pl-size",size); apply();});});'
            . 'root.querySelectorAll("[data-pl-variant]").forEach(function(b){b.addEventListener("click",function(){variant=b.getAttribute("data-pl-variant")||"primary"; updatePressed("[data-pl-variant]","data-pl-variant",variant); apply();});});'
            . 'apply();})();</script>';

        $out .= '</div>';
        return $out;
    }
}
