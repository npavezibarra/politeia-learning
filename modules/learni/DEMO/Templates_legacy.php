<?php
/**
 * Frontend template wiring for internal Learni module.
 *
 * Loads Learni templates for learni_course + learni_lesson without enabling
 * the full Learni frontend routing layer yet.
 */

if (!defined('ABSPATH')) {
    exit;
}

final class PL_Learni_Frontend_Templates
{
    private static bool $did_register_content_filter = false;

    private static function strip_empty_course_padding_group(string $html): string
    {
        if ($html === '') {
            return $html;
        }

        // Remove the empty padding-only block group that sometimes ends up in course content.
        // Example:
        // <div class="wp-block-group has-global-padding is-layout-constrained wp-block-group-is-layout-constrained" style="padding-top:var(--wp--preset--spacing--60);padding-bottom:var(--wp--preset--spacing--60)">
        // </div>
        $pattern = '#<div\s+class="wp-block-group[^"]*has-global-padding[^"]*is-layout-constrained[^"]*wp-block-group-is-layout-constrained[^"]*"\s+style="[^"]*padding-top:var\(--wp--preset--spacing--60\)[^"]*padding-bottom:var\(--wp--preset--spacing--60\)[^"]*"\s*>\s*</div>#i';
        $cleaned = preg_replace($pattern, '', $html);

        return is_string($cleaned) ? $cleaned : $html;
    }

    public static function init(): void
    {
        add_filter('template_include', [__CLASS__, 'template_include'], 50);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets'], 20);
        add_filter('render_block', [__CLASS__, 'filter_theme_blocks'], 10, 2);
        add_filter('body_class', [__CLASS__, 'filter_body_class'], 20);
        add_action('admin_post_pl_learni_enroll_course', [__CLASS__, 'handle_enroll_course']);
        add_action('admin_post_nopriv_pl_learni_enroll_course', [__CLASS__, 'handle_enroll_course_nopriv']);
        add_action('admin_post_pl_learni_mark_lesson_complete', [__CLASS__, 'handle_mark_lesson_complete']);
        add_action('admin_post_nopriv_pl_learni_mark_lesson_complete', [__CLASS__, 'handle_mark_lesson_complete_nopriv']);
        add_action('admin_post_pl_learni_checkout_course', [__CLASS__, 'handle_checkout_course']);
        add_action('admin_post_nopriv_pl_learni_checkout_course', [__CLASS__, 'handle_checkout_course_nopriv']);
        add_action('admin_post_pl_learni_view_certificate', [__CLASS__, 'handle_view_certificate']);
        add_action('admin_post_nopriv_pl_learni_view_certificate', [__CLASS__, 'handle_view_certificate_nopriv']);

        // For block themes, we want the theme's header/footer to stay identical to the rest
        // of the site, so we render the Learni screens inside the normal singular template.
        if (!self::$did_register_content_filter) {
            add_filter('the_content', [__CLASS__, 'render_block_theme_content'], 99);
            self::$did_register_content_filter = true;
        }
    }

    private static function certificate_template_exists(int $course_id): bool
    {
        if ($course_id <= 0) {
            return false;
        }

        if (!class_exists('\\Learni\\PostTypes\\Course')) {
            return false;
        }

        $title = (string) get_post_meta($course_id, \Learni\PostTypes\Course::META_CERTIFICATE_TITLE, true);
        $paragraph = (string) get_post_meta($course_id, \Learni\PostTypes\Course::META_CERTIFICATE_CONGRATS, true);
        $logo_id = (int) get_post_meta($course_id, \Learni\PostTypes\Course::META_CERTIFICATE_LOGO_ATTACHMENT_ID, true);
        $sig_id = (int) get_post_meta($course_id, \Learni\PostTypes\Course::META_CERTIFICATE_SIGNATURE_ATTACHMENT_ID, true);
        $show_first = (bool) get_post_meta($course_id, \Learni\PostTypes\Course::META_CERTIFICATE_CLAIM_FIRST, true);
        $show_final = (bool) get_post_meta($course_id, \Learni\PostTypes\Course::META_CERTIFICATE_CLAIM_FINAL, true);
        $show_variation = (bool) get_post_meta($course_id, \Learni\PostTypes\Course::META_CERTIFICATE_CLAIM_VARIATION, true);

        return ($title !== '') || ($paragraph !== '') || ($logo_id > 0) || ($sig_id > 0) || $show_first || $show_final || $show_variation;
    }

    private static function certificate_view_url(int $course_id): string
    {
        if ($course_id <= 0) {
            return '';
        }
        return (string) add_query_arg(
            [
                'action' => 'pl_learni_view_certificate',
                'course_id' => (string) $course_id,
            ],
            admin_url('admin-post.php')
        );
    }

    private static function checkout_course_url(int $course_id): string
    {
        if ($course_id <= 0) {
            return '';
        }
        return (string) add_query_arg(
            [
                'action' => 'pl_learni_checkout_course',
                'course_id' => (string) $course_id,
            ],
            admin_url('admin-post.php')
        );
    }

    private static function certificate_paragraph_with_replacements(string $paragraph, int $course_id, int $user_id, string $issued_label): string
    {
        $student_name = '';
        $first_name = '';
        if ($user_id > 0) {
            $u = get_userdata($user_id);
            if ($u) {
                $first_name = (string) ($u->first_name ?? '');
                $student_name = (string) ($u->display_name ?? '');
            }
        }
        if ($student_name === '') {
            $student_name = __('Student', 'politeia-learning');
        }
        if ($first_name === '') {
            $first_name = $student_name;
        }

        $course_title = $course_id > 0 ? (string) get_the_title($course_id) : '';
        $date_start = '';
        if ($course_id > 0 && $user_id > 0) {
            global $wpdb;
            if ($wpdb) {
                $started_at = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT started_at FROM {$wpdb->prefix}learni_enrollments WHERE user_id = %d AND course_post_id = %d LIMIT 1",
                        $user_id,
                        $course_id
                    )
                );
                if (is_string($started_at) && $started_at !== '') {
                    $date_start = wp_date(get_option('date_format'), strtotime($started_at));
                }
            }
        }

        $replacements = [
            '[display_full_name]' => $student_name,
            '[first_name]' => $first_name,
            '[course_name]' => $course_title,
            '[date_start]' => $date_start,
            '[date_end]' => $issued_label,
            '{{display_full_name}}' => $student_name,
            '{{first_name}}' => $first_name,
            '{{course_name}}' => $course_title,
            '{{date_start}}' => $date_start,
            '{{date_end}}' => $issued_label,
        ];

        foreach ($replacements as $key => $val) {
            $paragraph = str_replace($key, (string) $val, $paragraph);
        }

        return $paragraph;
    }

    public static function handle_view_certificate_nopriv(): void
    {
        $course_id = isset($_GET['course_id']) ? (int) $_GET['course_id'] : 0;
        $redirect = $course_id > 0 ? (string) get_permalink($course_id) : home_url('/');
        wp_safe_redirect(wp_login_url($redirect));
        exit;
    }

    public static function handle_checkout_course_nopriv(): void
    {
        $course_id = isset($_GET['course_id']) ? (int) $_GET['course_id'] : 0;
        $redirect = $course_id > 0 ? self::checkout_course_url($course_id) : home_url('/');
        wp_safe_redirect(wp_login_url($redirect));
        exit;
    }

    public static function handle_checkout_course(): void
    {
        if (!is_user_logged_in()) {
            self::handle_checkout_course_nopriv();
        }

        $course_id = isset($_GET['course_id']) ? (int) $_GET['course_id'] : 0;
        if ($course_id <= 0 || get_post_type($course_id) !== \Learni\PostTypes\Course::POST_TYPE) {
            wp_safe_redirect(home_url('/'));
            exit;
        }

        $product_id = (int) get_post_meta($course_id, 'learni_wc_product_id', true);
        if ($product_id <= 0 || !class_exists('WooCommerce') || !function_exists('wc_get_checkout_url')) {
            wp_safe_redirect((string) get_permalink($course_id));
            exit;
        }

        if (function_exists('wc_load_cart')) {
            wc_load_cart();
        }

        if (function_exists('WC') && WC() && isset(WC()->cart) && WC()->cart) {
            $cart_id = WC()->cart->generate_cart_id($product_id);
            $existing_key = $cart_id ? WC()->cart->find_product_in_cart($cart_id) : '';
            if (!$existing_key) {
                WC()->cart->add_to_cart($product_id, 1);
            }
        }

        wp_safe_redirect(wc_get_checkout_url());
        exit;
    }

    public static function handle_view_certificate(): void
    {
        $user_id = (int) get_current_user_id();
        $course_id = isset($_GET['course_id']) ? (int) $_GET['course_id'] : 0;
        if ($user_id <= 0) {
            self::handle_view_certificate_nopriv();
        }

        if ($course_id <= 0 || get_post_type($course_id) !== \Learni\PostTypes\Course::POST_TYPE) {
            wp_safe_redirect(home_url('/'));
            exit;
        }

        if (class_exists('\\Learni\\Access\\Access') && !\Learni\Access\Access::user_can_access_course($user_id, $course_id)) {
            wp_safe_redirect((string) get_permalink($course_id));
            exit;
        }

        if (!self::certificate_template_exists($course_id)) {
            wp_safe_redirect((string) get_permalink($course_id));
            exit;
        }

        $data = self::get_certificate_data($course_id, $user_id);
        $title = $data['title'] ?? __('Certificado de Finalización', 'politeia-learning');
        $sheet = self::render_certificate_sheet_html($data);

        $html = '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
        $html .= '<title>' . esc_html($title) . '</title>';
        $html .= '<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>';
        $html .= '<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">';
        $html .= '<style>
            body{margin:0;background:#f3f4f6;font-family:Poppins,system-ui,-apple-system,Segoe UI,Roboto,sans-serif;color:#111827}
            .wrap{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px}
            .sheet{width:min(920px,100%);background:#fff;border-radius:18px;box-shadow:0 30px 90px rgba(0,0,0,.18);padding:50px 46px}
            .top{display:flex;align-items:center;justify-content:' . esc_attr($data['logo_justify']) . '}
            .logo{max-height:54px;max-width:220px;object-fit:contain}
            .title{margin:26px 0 10px;font-size:32px;font-weight:800;letter-spacing:.04em;text-align:center}
            .kicker{margin:0;font-size:12px;font-weight:800;letter-spacing:.2em;text-transform:uppercase;color:rgba(17,24,39,.55);text-align:center}
            .name{margin:10px 0 8px;font-size:34px;font-weight:800;text-align:center}
            .course{margin:10px 0 0;font-size:18px;font-weight:700;text-align:center;color:rgba(17,24,39,.85)}
            .paragraph{margin:18px auto 0;max-width:720px;font-size:14px;line-height:1.6;text-align:center;color:rgba(17,24,39,.72)}
            .notice{margin:18px auto 0;max-width:720px;font-size:13px;text-align:center;color:rgba(17,24,39,.6)}
            .claims{margin:22px auto 0;max-width:720px;display:grid;gap:6px;justify-items:center}
            .claim{font-size:13px;font-weight:700;color:rgba(17,24,39,.75)}
            .bottom{margin-top:36px;display:flex;align-items:flex-end;justify-content:space-between;gap:18px;flex-wrap:wrap}
            .meta{display:grid;gap:6px}
            .meta-row{font-size:12px;color:rgba(17,24,39,.7)}
            .sig{display:grid;justify-items:end;gap:8px;min-width:220px}
            .sigimg{max-height:56px;max-width:220px;object-fit:contain}
            .sigline{width:220px;height:1px;background:rgba(17,24,39,.22)}
            .siglabel{font-size:12px;font-weight:700;color:rgba(17,24,39,.7)}
            .actions{margin-top:26px;display:flex;justify-content:center;gap:10px;flex-wrap:wrap}
            .btn{appearance:none;border:1px solid rgba(17,24,39,.14);background:#111827;color:#fff;border-radius:10px;height:42px;padding:0 16px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;justify-content:center}
            .btn.secondary{background:#fff;color:rgba(17,24,39,.9)}
            @media print{body{background:#fff}.wrap{padding:0}.sheet{box-shadow:none;border-radius:0;width:100%;padding:40px}}
        </style>';
        $html .= '</head><body><div class="wrap">' . $sheet . '</div>';
        $html .= '<script>
            document.addEventListener("click", function(e) {
                if (e.target && e.target.closest("[data-learni-cert-download]")) {
                    window.print();
                }
            });
        </script></body></html>';

        nocache_headers();
        header('Content-Type: text/html; charset=' . get_bloginfo('charset'));
        echo $html;
        exit;
    }

    private static function get_certificate_data(int $course_id, int $user_id): array
    {
        $summary = class_exists('\\Learni\\Database\\Progress') ? \Learni\Database\Progress::course_summary($user_id, $course_id) : ['percent' => 0];
        $percent = (int) ($summary['percent'] ?? 0);
        $eligible = $percent >= 100;

        $title = (string) get_post_meta($course_id, \Learni\PostTypes\Course::META_CERTIFICATE_TITLE, true);
        $title = $title !== '' ? $title : __('Certificado de Finalización', 'politeia-learning');

        $paragraph = (string) get_post_meta($course_id, \Learni\PostTypes\Course::META_CERTIFICATE_CONGRATS, true);
        $issued_label = wp_date(get_option('date_format'));
        $paragraph = self::certificate_paragraph_with_replacements($paragraph, $course_id, $user_id, $issued_label);

        $logo_id = (int) get_post_meta($course_id, \Learni\PostTypes\Course::META_CERTIFICATE_LOGO_ATTACHMENT_ID, true);
        $logo_url = $logo_id > 0 ? (string) wp_get_attachment_image_url($logo_id, 'medium') : '';
        $logo_align = (string) get_post_meta($course_id, \Learni\PostTypes\Course::META_CERTIFICATE_LOGO_ALIGN, true);
        $logo_align = in_array($logo_align, ['left', 'center', 'right'], true) ? $logo_align : 'left';
        $logo_justify = $logo_align === 'right' ? 'flex-end' : ($logo_align === 'center' ? 'center' : 'flex-start');

        $sig_id = (int) get_post_meta($course_id, \Learni\PostTypes\Course::META_CERTIFICATE_SIGNATURE_ATTACHMENT_ID, true);
        $sig_url = $sig_id > 0 ? (string) wp_get_attachment_image_url($sig_id, 'medium') : '';
        $sig_label = (string) get_post_meta($course_id, \Learni\PostTypes\Course::META_CERTIFICATE_SIGNATURE_LABEL, true);
        $sig_label = $sig_label !== '' ? $sig_label : __('Firma', 'politeia-learning');

        $binomial = self::binomial_course_state($course_id, $user_id, $percent);
        $first_pct = is_array($binomial['initial'] ?? null) ? (int) ($binomial['initial']['percent'] ?? 0) : null;
        $final_pct = is_array($binomial['final'] ?? null) ? (int) ($binomial['final']['percent'] ?? 0) : null;
        $show_first = (bool) get_post_meta($course_id, \Learni\PostTypes\Course::META_CERTIFICATE_CLAIM_FIRST, true);
        $show_final = (bool) get_post_meta($course_id, \Learni\PostTypes\Course::META_CERTIFICATE_CLAIM_FINAL, true);
        $show_variation = (bool) get_post_meta($course_id, \Learni\PostTypes\Course::META_CERTIFICATE_CLAIM_VARIATION, true);

        $claims = [];
        if ($show_first && is_int($first_pct)) {
            $claims[] = sprintf(__('Evaluación inicial: %d%%', 'politeia-learning'), $first_pct);
        }
        if ($show_final && is_int($final_pct)) {
            $claims[] = sprintf(__('Evaluación final: %d%%', 'politeia-learning'), $final_pct);
        }
        if ($show_variation && is_int($first_pct) && is_int($final_pct)) {
            $variation = (int) $final_pct - (int) $first_pct;
            $sign = $variation > 0 ? '+' : '';
            $claims[] = sprintf(__('Variación: %s%d%%', 'politeia-learning'), $sign, $variation);
        }


        $issued_ts = time();
        $code = '';
        if ($eligible) {
            $payload = [
                'uid' => $user_id,
                'cid' => $course_id,
                'i'   => $issued_ts,
                'ip'  => $first_pct,
                'fp'  => $final_pct,
            ];
            if (class_exists('\\Learni\\Certificates\\CertificateCode')) {
                $code = \Learni\Certificates\CertificateCode::encode($payload);
            }
        }

        return [
            'course_id' => $course_id,
            'title' => $title,
            'paragraph' => $paragraph,
            'logo_url' => $logo_url,
            'logo_justify' => $logo_justify,
            'sig_url' => $sig_url,
            'sig_label' => $sig_label,
            'claims' => $claims,
            'issued_label' => $issued_label,
            'eligible' => $eligible,
            'course_title' => (string) get_the_title($course_id),
            'student_name' => (string) wp_get_current_user()->display_name,
            'code' => $code,
        ];
    }

    private static function render_certificate_sheet_html(array $data): string
    {
        $logo_url = $data['logo_url'] ?? '';
        $logo_justify = $data['logo_justify'] ?? 'flex-start';
        $title = $data['title'] ?? '';
        $student_name = $data['student_name'] ?? '';
        $course_title = $data['course_title'] ?? '';
        $paragraph = $data['paragraph'] ?? '';
        $eligible = !empty($data['eligible']);
        $claims = $data['claims'] ?? [];
        $issued_label = $data['issued_label'] ?? '';
        $sig_url = $data['sig_url'] ?? '';
        $sig_label = $data['sig_label'] ?? '';
        $course_id = $data['course_id'] ?? 0;
        $code = $data['code'] ?? '';

        $html = '<div class="sheet" data-learni-cert-sheet="1">';
        if ($logo_url !== '') {
            $html .= '<div class="top" style="justify-content:' . esc_attr($logo_justify) . '"><img class="logo" src="' . esc_url($logo_url) . '" alt=""></div>';
        }
        $html .= '<div class="title">' . esc_html($title) . '</div>';
        $html .= '<p class="kicker">' . esc_html__('Certifica que', 'politeia-learning') . '</p>';
        $html .= '<div class="name">' . esc_html($student_name) . '</div>';
        $html .= '<p class="kicker">' . esc_html__('ha completado satisfactoriamente', 'politeia-learning') . '</p>';
        $html .= '<div class="course">' . esc_html($course_title) . '</div>';
        if ($paragraph !== '') {
            $html .= '<div class="paragraph">' . wp_kses_post($paragraph) . '</div>';
        }
        if (!$eligible) {
            $html .= '<div class="notice">' . esc_html__('Completa el curso para desbloquear tu certificado.', 'politeia-learning') . '</div>';
        }
        if (!empty($claims)) {
            $html .= '<div class="claims">';
            foreach ($claims as $c) {
                $html .= '<div class="claim">' . esc_html($c) . '</div>';
            }
            $html .= '</div>';
        }
        $html .= '<div class="bottom">';
        $html .= '<div class="meta">';
        $html .= '<div class="meta-row"><strong>' . esc_html__('Emitido', 'politeia-learning') . '</strong>: ' . esc_html($issued_label) . '</div>';
        if ($code !== '') {
            $html .= '<div class="meta-row verification-code"><span class="meta-code-label">' . esc_html__('Número de Verificación:', 'politeia-learning') . '</span> <span class="meta-code-value">' . esc_html($code) . '</span></div>';
        }
        $html .= '</div>';
        $html .= '<div class="sig">';
        if ($sig_url !== '') {
            $html .= '<img class="sigimg" src="' . esc_url($sig_url) . '" alt="">';
        }
        $html .= '<div class="sigline"></div>';
        $html .= '<div class="siglabel">' . esc_html($sig_label) . '</div>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '<div class="actions">';
        $html .= '<button class="btn" type="button" data-learni-cert-download="1">' . esc_html__('Descargar PDF', 'politeia-learning') . '</button>';
        $html .= '<a class="btn secondary" href="' . esc_url((string) get_permalink($course_id)) . '">' . esc_html__('Volver al curso', 'politeia-learning') . '</a>';
        $html .= '</div>';
        $html .= '</div>';

        return $html;
    }

    public static function render_certificate_modal_html(int $course_id, int $user_id): string
    {
        // The modal shell is rendered server-side so translations apply and we can reuse the
        // same markup across templates. The actual certificate sheet is fetched/rendered
        // client-side via the REST endpoint (see `assets/learner-quiz.js`) so it always
        // matches the current certificate renderer/styling.
        $html = '<div id="learni-cert-modal" class="learni-cert-modal" aria-hidden="true">';
        $html .= '<div class="learni-cert-modal__backdrop" data-learni-cert-close="1"></div>';
        $html .= '<div class="learni-cert-modal__panel" role="dialog" aria-modal="true" aria-label="' . esc_attr__('Certificado', 'politeia-learning') . '">';
        $html .= '<div class="learni-cert-modal__head">';
        $html .= '<div class="learni-cert-modal__title">' . esc_html__('Certificado', 'politeia-learning') . '</div>';
        $html .= '<div class="learni-cert-modal__actions">';
        // Hidden by default; JS toggles based on eligibility from the REST response.
        $html .= '<button type="button" class="learni-btn secondary" data-learni-cert-download="1" style="display:none" disabled>' . esc_html__('Descargar PDF', 'politeia-learning') . '</button>';
        $html .= '<button type="button" class="learni-btn secondary" data-learni-cert-close="1">' . esc_html__('Cerrar', 'politeia-learning') . '</button>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '<div class="learni-cert-modal__notice" id="learni-cert-modal-notice" style="display:none"></div>';
        $html .= '<div class="learni-cert-modal__body" id="learni-cert-modal-body"></div>';
        $html .= '</div>';
        $html .= '</div>';

        return $html;
    }

    private static function is_block_theme(): bool
    {
        return function_exists('wp_is_block_theme') && wp_is_block_theme();
    }

    /**
     * Remove theme blocks that don't make sense on Learni screens.
     *
     * Example: a "Written by {author} on {date}" row can become "Escrito por  en" if author/date blocks
     * are hidden or empty. Remove the whole group in that case.
     *
     * @param string $block_content
     * @param array  $block
     */
    public static function filter_theme_blocks(string $block_content, array $block): string
    {
        if (!self::is_block_theme()) {
            return $block_content;
        }

        if (!is_singular('learni_course') && !is_singular('learni_lesson')) {
            return $block_content;
        }

        $name = (string) ($block['blockName'] ?? '');
        // Remove theme-rendered featured image on Learni screens since we render it inside the hero card.
        if ($name === 'core/post-featured-image') {
            return '';
        }

        // Remove the site footer on single lesson screens (Learni layout is full-height).
        if (is_singular('learni_lesson') && $name === 'core/template-part') {
            $attrs = (array) ($block['attrs'] ?? []);
            $slug = (string) ($attrs['slug'] ?? '');
            if ($slug === 'footer') {
                return '';
            }
        }

	        if ($name === 'core/group') {
	            // Remove an empty padding-only group that some block themes insert around the content area.
	            // This is purely spacing markup and becomes a blank gap on Learni screens.
	            $maybe_stripped = self::strip_empty_course_padding_group($block_content);
	            if ($maybe_stripped !== $block_content) {
	                return $maybe_stripped;
	            }

	            // Spanish pattern from the active theme: "Escrito por {author} en {date}".
	            if (preg_match('/<p>\\s*Escrito\\s+por\\s*<\\/p>/i', $block_content) && preg_match('/<p>\\s*en\\s*<\\/p>/i', $block_content)) {
	                return '';
	            }
	        }

        return $block_content;
    }

    /**
     * Add body classes for Learni screens so we can scope theme overrides safely.
     *
     * @param array $classes
     * @return array
     */
    public static function filter_body_class(array $classes): array
    {
        if (is_singular('learni_lesson')) {
            $classes[] = 'pl-learni-lesson';
        } elseif (is_singular('learni_course')) {
            $classes[] = 'pl-learni-course';
        }
        return $classes;
    }

    public static function enqueue_assets(): void
    {
        if (!is_singular('learni_course') && !is_singular('learni_lesson')) {
            return;
        }

        // Keep this self-contained (no dependency on separate Learni plugin).
        wp_enqueue_style(
            'pl-learni-material-symbols',
            // Use the full font (no `icon_names` subsetting) to avoid glyph fallback showing text like "PERSON_ADD".
            'https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=block',
            [],
            null
        );

        wp_enqueue_style(
            'pl-learni-learner',
            PL_LEARNI_URL . 'assets/learner.css',
            ['pl-learni-material-symbols'],
            defined('LEARNI_VERSION') ? (string) LEARNI_VERSION : '0.0.0'
        );

        // When a lesson reuses an Escrito (WP post) as its body, reuse the same frontend CSS
        // so typography/images match the blog rendering.
        if (is_singular('learni_lesson') && defined('PL_CC_URL') && defined('PL_CC_PATH')) {
            $lesson_id = (int) get_queried_object_id();
            $src_meta_key = class_exists('\\Learni\\PostTypes\\Lesson')
                ? \Learni\PostTypes\Lesson::META_SOURCE_POST_ID
                : 'learni_source_post_id';
            $src_post_id = $lesson_id > 0 ? (int) get_post_meta($lesson_id, $src_meta_key, true) : 0;
            if ($src_post_id > 0) {
                $frontend_css_path = PL_CC_PATH . 'assets/css/escrito-frontend.css';
                $frontend_css_ver = file_exists($frontend_css_path) ? (string) filemtime($frontend_css_path) : '1.0.0';
                wp_enqueue_style('pcg-escrito-frontend-css', PL_CC_URL . 'assets/css/escrito-frontend.css', [], $frontend_css_ver);
            }
        }

	        if (is_singular('learni_course') || is_singular('learni_lesson')) {
	            wp_enqueue_script(
	                'pl-learni-quiz',
	                PL_LEARNI_URL . 'assets/learner-quiz.js',
	                [],
	                defined('LEARNI_VERSION') ? (string) LEARNI_VERSION : '0.0.0',
	                true
	            );
	            wp_add_inline_script(
	                'pl-learni-quiz',
	                'window.Learni = Object.assign({}, window.Learni || {}, ' . wp_json_encode([
	                    'restUrl' => esc_url_raw(rest_url()),
	                    'restNonce' => wp_create_nonce('wp_rest'),
	                    'authBaseUrl' => esc_url_raw(home_url('/')),
	                    'isLoggedIn' => is_user_logged_in(),
	                    'loginUrl' => function_exists('wc_get_page_permalink') ? (string) wc_get_page_permalink('myaccount') : wp_login_url(),
	                    'i18n' => [
	                        'certTitle' => __('Certificado', 'politeia-learning'),
	                        'downloadPdf' => __('Descargar PDF', 'politeia-learning'),
	                        'close' => __('Cerrar', 'politeia-learning'),
	                        'loading' => __('Cargando…', 'politeia-learning'),
	                        'ineligible' => __('Completa el curso para desbloquear tu certificado.', 'politeia-learning'),
	                        'quizInitialKicker' => __('Evaluación inicial', 'politeia-learning'),
	                        'quizFinalKicker' => __('Evaluación final', 'politeia-learning'),
	                        'quizCrossKicker' => __('Test Partner', 'politeia-learning'),
	                        'quizCrossWaitTitle' => __('Estamos a la espera de que tu partner acepte…', 'politeia-learning'),
	                        'quizCrossWaitBody' => __('Tu partner recibirá una notificación para aceptar tomar la evaluación en este momento.', 'politeia-learning'),
	                        'quizCrossCancel' => __('Cancelar', 'politeia-learning'),
	                        'quizCrossPrepFinal' => __("Estás por iniciar el Test Partner de “{course}”. Responderás {count} preguntas.\n\nTu partner responderá verbalmente y tú marcarás las respuestas en pantalla.", 'politeia-learning'),
	                        'quizCrossDoneTitle' => __('Listo', 'politeia-learning'),
	                        'quizCrossDoneBody' => __('El resultado quedó guardado en la cuenta de tu partner.', 'politeia-learning'),
	                        'quizCrossResultsBody' => __('Terminaste la Evaluación Final. Estos fueron los resultados:', 'politeia-learning'),
	                        'quizCrossDoneClose' => __('Cerrar', 'politeia-learning'),
	                        'quizCrossResultsClose' => __('Cerrar', 'politeia-learning'),
	                        'quizBegin' => __('Comenzar', 'politeia-learning'),
	                        'quizCancel' => __('Cancelar', 'politeia-learning'),
	                        'quizPrepInitial' => __("Vas a comenzar la evaluación de “{course}”. Responderás {count} preguntas.\n\nResponde con sinceridad: así podremos medir tu progreso.", 'politeia-learning'),
	                        'quizPrepFinal' => __("Estás por iniciar la evaluación final de “{course}”. En la evaluación inicial obtuviste {score}%.\n\nResponde basándote en lo que aprendiste en el curso. Al final verás una comparación entre Inicial y Final para medir tu progreso.\n\nResponderás {count} preguntas.", 'politeia-learning'),
	                        'quizQuestionOf' => __('Pregunta {current} de {total}', 'politeia-learning'),
	                        'quizNext' => __('Siguiente', 'politeia-learning'),
	                        'quizSubmit' => __('Enviar', 'politeia-learning'),
	                        'quizBack' => __('Atrás', 'politeia-learning'),
	                        'quizChooseAnswer' => __('Por favor elige una respuesta.', 'politeia-learning'),
	                        'quizAnswerAll' => __('Por favor responde todas las preguntas.', 'politeia-learning'),
	                    ],
	                ]) . ');',
	                'before'
	            );
	        }

        if (is_singular('learni_lesson')) {
            wp_enqueue_script(
                'pl-learni-lesson-outline',
                PL_LEARNI_URL . 'assets/learner-lesson-outline.js',
                [],
                defined('LEARNI_VERSION') ? (string) LEARNI_VERSION : '0.0.0',
                true
            );
        }

        if (self::is_block_theme() && is_singular('learni_course')) {
            wp_enqueue_script(
                'pl-learni-tabs',
                PL_LEARNI_URL . 'assets/learner-tabs.js',
                [],
                defined('LEARNI_VERSION') ? (string) LEARNI_VERSION : '0.0.0',
                true
            );
        }
    }

    public static function render_block_theme_content(string $content): string
    {
        if (is_admin()) {
            return $content;
        }

        if (!self::is_block_theme()) {
            return $content;
        }

        // Avoid recursion when rendering post content.
        remove_filter('the_content', [__CLASS__, __FUNCTION__], 99);

        $out = $content;
        try {
            if (is_singular('learni_course')) {
                $out = self::render_course_block_theme();
            } elseif (is_singular('learni_lesson')) {
                $out = self::render_lesson_block_theme();
            }
        } catch (\Throwable $e) {
            $out = $content;
        }

        add_filter('the_content', [__CLASS__, __FUNCTION__], 99);

        return $out;
    }

    private static function render_course_block_theme(): string
    {
        $course_id = (int) get_the_ID();
        if ($course_id <= 0) {
            return '<div class="learni-learner"><p>' . esc_html__('Course not found.', 'politeia-learning') . '</p></div>';
        }

        $user_id = (int) get_current_user_id();
        $is_logged_in = $user_id > 0;
        $has_access = $is_logged_in && class_exists('\\Learni\\Access\\Access') && \Learni\Access\Access::user_can_access_course($user_id, $course_id);

        $title = (string) get_the_title($course_id);
        $excerpt = (string) get_post_field('post_excerpt', $course_id);
        if ($excerpt === '') {
            $excerpt = wp_trim_words(wp_strip_all_tags((string) get_post_field('post_content', $course_id)), 40, '…');
        }

        $items = class_exists('\\Learni\\Courses\\Outline') ? \Learni\Courses\Outline::get_items($course_id) : [];
        $lesson_ids = class_exists('\\Learni\\Courses\\Outline') ? \Learni\Courses\Outline::lesson_ids($course_id) : [];
        $summary = ($is_logged_in && class_exists('\\Learni\\Database\\Progress')) ? \Learni\Database\Progress::course_summary($user_id, $course_id) : ['total' => count($lesson_ids), 'completed' => 0, 'percent' => 0];
        $completed = ($has_access && class_exists('\\Learni\\Database\\Progress')) ? array_flip(\Learni\Database\Progress::completed_lesson_ids($user_id, $course_id)) : [];
        $linear_order = self::course_linear_order_enabled($course_id);
        $lesson_index = self::lesson_index_map($lesson_ids);
        $max_unlocked = self::max_unlocked_lesson_index($lesson_ids, $completed, $linear_order);

        $percent = (int) ($summary['percent'] ?? 0);
        $total = (int) ($summary['total'] ?? 0);
        $done = (int) ($summary['completed'] ?? 0);
        $progress_text = sprintf(__('COMPLETADO %1$d DE %2$d LECCIONES', 'politeia-learning'), $done, $total);
        $price = (float) get_post_meta($course_id, 'learni_price', true);
        $product_id = (int) get_post_meta($course_id, 'learni_wc_product_id', true);
        $price_label = $price > 0 ? '$' . number_format((float) $price, 0, '.', ',') : __('FREE', 'politeia-learning');
        $is_free = $price <= 0 && $product_id <= 0;
	        $thumb_id = (int) get_post_thumbnail_id($course_id);
	        $thumb_url = $thumb_id > 0 ? (string) wp_get_attachment_image_url($thumb_id, 'large') : '';
        $has_course_partner = false;
        $partner_user_id = 0;
        $owner_user_id = 0;
        if (class_exists('PL_Partnerships_Repository') && method_exists('PL_Partnerships_Repository', 'get_single_partner')) {
            try {
                $p = PL_Partnerships_Repository::get_single_partner('course', $course_id, 'partner');
                $has_course_partner = is_array($p) && !empty($p['partner_user_id']);
                if (is_array($p)) {
                    $partner_user_id = !empty($p['partner_user_id']) ? (int) $p['partner_user_id'] : 0;
                    $owner_user_id = !empty($p['owner_user_id']) ? (int) $p['owner_user_id'] : 0;
                }
            } catch (\Throwable $e) {
                $has_course_partner = false;
            }
        }
        // Fallback: attempt to infer the purchaser/owner from active enrollments.
        if ($owner_user_id <= 0 && $partner_user_id > 0 && $has_access && class_exists('\\Learni\\Database\\Enrollments')) {
            global $wpdb;
            if ($wpdb) {
                $enroll_table = $wpdb->prefix . 'learni_enrollments';
                $rows = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT user_id, source, payment_provider
                         FROM {$enroll_table}
                         WHERE course_post_id = %d AND status = %s
                         ORDER BY created_at ASC
                         LIMIT 10",
                        $course_id,
                        \Learni\Database\Enrollments::STATUS_ACTIVE
                    ),
                    ARRAY_A
                );
                foreach ((array) $rows as $r) {
                    if (!is_array($r)) {
                        continue;
                    }
                    $candidate_id = (int) ($r['user_id'] ?? 0);
                    if ($candidate_id <= 0) {
                        continue;
                    }
                    $src = (string) ($r['source'] ?? '');
                    $prov = (string) ($r['payment_provider'] ?? '');
                    $is_owner_row = ($src === \Learni\Database\Enrollments::SOURCE_WOOCOMMERCE)
                        || ($src === \Learni\Database\Enrollments::SOURCE_DIRECT)
                        || ($src === \Learni\Database\Enrollments::SOURCE_MANUAL && $prov !== 'partner_invite');
                    if ($is_owner_row) {
                        $owner_user_id = $candidate_id;
                        break;
                    }
                }
            }
        }

	        // Only show the certificate CTA once the user has completed the final evaluation.
	        // For partner courses, both users must have completed their final evaluation (mutual testing).
	        $certificate_available = $has_access && ($percent >= 100) && self::certificate_template_exists($course_id);
	        if ($certificate_available) {
	            $self_binomial = self::binomial_course_state($course_id, $user_id, $percent);
	            $certificate_available = $certificate_available && !empty($self_binomial['eligibleFinal']);

	            if ($certificate_available && $has_course_partner && $partner_user_id > 0) {
	                $other_user_id = ($partner_user_id === $user_id) ? $owner_user_id : $partner_user_id;
	                if ($other_user_id > 0) {
	                    $other_summary = class_exists('\\Learni\\Database\\Progress') ? \Learni\Database\Progress::course_summary($other_user_id, $course_id) : ['percent' => 0];
	                    $other_percent = (int) ($other_summary['percent'] ?? 0);
	                    $other_binomial = self::binomial_course_state($course_id, $other_user_id, $other_percent);
	                    $certificate_available = $certificate_available && ($other_percent >= 100) && !empty($other_binomial['eligibleFinal']);
	                } else {
	                    $certificate_available = false;
	                }
	            }
	        }

        $author_id = (int) get_post_field('post_author', $course_id);
        $author = $author_id > 0 ? get_userdata($author_id) : null;
        $author_first = ($author instanceof \WP_User) ? trim((string) ($author->first_name ?? '')) : '';
        $author_last = ($author instanceof \WP_User) ? trim((string) ($author->last_name ?? '')) : '';
        $author_full_name = trim($author_first . ' ' . $author_last);
        if ($author_full_name === '' && $author instanceof \WP_User) {
            $author_full_name = trim((string) ($author->display_name ?? ''));
        }
        if ($author_full_name === '') {
            $author_full_name = (string) $author_id;
        }
        $author_slug = ($author instanceof \WP_User) ? (string) ($author->user_nicename ?? '') : '';
        if ($author_slug === '' && $author instanceof \WP_User) {
            $author_slug = (string) ($author->user_login ?? '');
        }
        $author_profile_url = $author_slug !== '' ? home_url('/profile/' . rawurlencode($author_slug) . '/') : '';
        if ($author_profile_url === '' && $author_id > 0 && function_exists('get_author_posts_url')) {
            $author_profile_url = (string) get_author_posts_url($author_id);
        }
        $author_avatar = ($author_id > 0 && function_exists('pl_get_user_profile_avatar_custom_url'))
            ? (string) pl_get_user_profile_avatar_custom_url($author_id, 72)
            : '';
        if ($author_avatar === '' && $author_id > 0 && function_exists('get_avatar_url')) {
            $author_avatar = (string) get_avatar_url($author_id, ['size' => 72]);
        }

	        // Match Learni structure: the main wrapper is both `#learni-course` and `.learni-learner`
	        // (needed for full-bleed hero math + consistent alignment with the site header).
	        $html = '<div id="learni-course" class="learni-learner alignwide" data-course-id="' . esc_attr((string) $course_id) . '">';

	        $cover_meta_key = class_exists('\\Learni\\PostTypes\\Course')
	            ? \Learni\PostTypes\Course::META_COVER_PHOTO_ID
	            : 'pl_cover_photo_id';
	        $cover_photo_id = (int) get_post_meta($course_id, $cover_meta_key, true);
	        $cover_photo_url = $cover_photo_id > 0 ? (string) wp_get_attachment_image_url($cover_photo_id, 'full') : '';
	        $hero_class = 'learni-course-hero' . ($cover_photo_url !== '' ? ' has-cover' : '');
	        $hero_style = $cover_photo_url !== '' ? ' style="--learni-hero-image:url(' . esc_url($cover_photo_url) . ');"' : '';

	        // Hero (banner + aside card).
	        $html .= '<section class="' . esc_attr($hero_class) . '"' . $hero_style . '><div class="learni-course-hero-content"><div class="learni-course-hero-inner">';
        $html .= '<div class="learni-course-hero-left">';
        $html .= '<h1 id="learni-course-title">' . esc_html($title) . '</h1>';
        if ($excerpt !== '') {
            $html .= '<p class="learni-course-description">' . esc_html($excerpt) . '</p>';
        }
        $html .= '<div class="learni-course-author">';
        if ($author_avatar !== '') {
            if ($author_profile_url !== '') {
                $html .= '<a class="learni-course-author-avatar-link" href="' . esc_url($author_profile_url) . '">';
                $html .= '<img class="learni-course-author-avatar" src="' . esc_url($author_avatar) . '" alt="">';
                $html .= '</a>';
            } else {
                $html .= '<div class="learni-course-author-avatar-link" aria-hidden="true">';
                $html .= '<img class="learni-course-author-avatar" src="' . esc_url($author_avatar) . '" alt="">';
                $html .= '</div>';
            }
        }
        $html .= '<div class="learni-course-author-meta">';
        $html .= '<div class="learni-course-author-label">' . esc_html__('Profesor', 'politeia-learning') . '</div>';
        if ($author_profile_url !== '') {
            $html .= '<a class="learni-course-author-name learni-course-author-name-link" href="' . esc_url($author_profile_url) . '">';
            $html .= esc_html($author_full_name);
            $html .= '</a>';
        } else {
            $html .= '<div class="learni-course-author-name">' . esc_html($author_full_name) . '</div>';
        }
        $html .= '</div>';
        $html .= '</div>';

        // Hide the main hero progress when a course partner is accepted; the competitive comparison lives in the aside.
        if (!$has_course_partner) {
            $html .= '<div class="learni-progress">';
            $html .= '<div class="learni-progress-head">';
            $html .= '<span class="learni-progress-text">' . esc_html($progress_text) . '</span>';
            $html .= '<span class="learni-progress-percent">' . esc_html((string) $percent) . '%</span>';
            $html .= '</div>';
            $html .= '<div class="learni-progress-bar" role="progressbar" aria-valuenow="' . esc_attr((string) $percent) . '" aria-valuemin="0" aria-valuemax="100">';
            $html .= '<div class="learni-progress-bar-fill" style="width:' . esc_attr((string) $percent) . '%">';
            $html .= '<div class="learni-progress-shimmer" aria-hidden="true"></div>';
            $html .= '</div>';
            $html .= '</div>';
            $html .= '</div>';
        }
        $html .= '</div>'; // left

        $html .= '<aside class="learni-course-hero-card" aria-label="' . esc_attr__('Course details', 'politeia-learning') . '">';
	        if ($thumb_id > 0) {
	            $thumb_img = wp_get_attachment_image(
	                $thumb_id,
	                // Use full + srcset so retina screens can pick a higher-res candidate.
	                'full',
	                false,
	                [
	                    'class' => 'learni-course-card-thumbnail',
	                    // The card is at most 420px wide on desktop.
	                    'sizes' => '(min-width: 971px) 420px, 100vw',
	                    'loading' => 'lazy',
	                    'decoding' => 'async',
	                ]
	            );
	            if (is_string($thumb_img) && $thumb_img !== '') {
	                $html .= '<div class="learni-course-card-thumbnail-wrap">' . $thumb_img . '</div>';
	            }
	        } elseif ($thumb_url !== '') {
	            // Fallback (should be rare): keep old behavior if attachment lookup fails.
	            $html .= '<div class="learni-course-card-thumbnail-wrap"><img class="learni-course-card-thumbnail" src="' . esc_url($thumb_url) . '" alt=""></div>';
	        }
        $html .= '<div class="learni-course-hero-card-body">';
        $html .= '<div class="learni-course-price-row">';
        $html .= '<div class="learni-course-price">' . esc_html($price_label) . '</div>';
        if ($certificate_available) {
            $html .= '<button id="learni-course-cert-trigger" class="learni-course-cert-trigger" type="button" aria-label="' . esc_attr__('View certificate', 'politeia-learning') . '" data-learni-cert-open="1" data-course-id="' . esc_attr((string) $course_id) . '">';
            $html .= '<span class="material-symbols-outlined learni-ms-icon learni-course-cert-icon" aria-hidden="true">history_edu</span>';
            $html .= '<span class="learni-course-cert-text">' . esc_html__('CERTIFICADO', 'politeia-learning') . '</span>';
            $html .= '</button>';
        }
        $html .= '</div>';
        $html .= '<div class="learni-course-card-actions">';

        // Pending partner invite CTA for the current user (shown on the course page aside).
        $pending_for_me = null;
        if ($is_logged_in && function_exists('pl_get_pending_course_partner_invite_for_user')) {
            $pending_for_me = pl_get_pending_course_partner_invite_for_user($course_id, $user_id);
        }

        // Binomial quiz aside controls (ported subset).
        $binomial = $is_logged_in ? self::binomial_course_state($course_id, $user_id, $percent) : [];
        $binomial_quiz_id = (int) ($binomial['quizId'] ?? 0);
        if ($binomial_quiz_id <= 0) {
            $binomial_quiz_id = self::binomial_quiz_id_for_course($course_id);
        }
        if ($binomial_quiz_id > 0) {
            if (is_array($binomial['initial'] ?? null)) {
                $ip = (int) ($binomial['initial']['percent'] ?? 0);
                $html .= '<div class="learni-eval" data-learni-eval="initial">';
                $html .= '<div class="learni-eval-head"><span class="learni-eval-title">' . esc_html__('EVALUACIÓN INICIAL', 'politeia-learning') . '</span><span class="learni-eval-percent">' . esc_html((string) $ip) . '%</span></div>';
                $html .= '<div class="learni-eval-track"><div class="learni-eval-bar" style="width:' . esc_attr((string) $ip) . '%"></div></div>';
                $html .= '</div>';
            }
            if (is_array($binomial['final'] ?? null)) {
                $fp = (int) ($binomial['final']['percent'] ?? 0);
                $html .= '<div class="learni-eval" data-learni-eval="final">';
                $html .= '<div class="learni-eval-head"><span class="learni-eval-title">' . esc_html__('EVALUACIÓN FINAL', 'politeia-learning') . '</span><span class="learni-eval-percent">' . esc_html((string) $fp) . '%</span></div>';
                $html .= '<div class="learni-eval-track"><div class="learni-eval-bar" style="width:' . esc_attr((string) $fp) . '%"></div></div>';
                $html .= '</div>';
            }

            $submitted_count = null;
            if (isset($binomial['submittedCount'])) {
                $submitted_count = (int) $binomial['submittedCount'];
            } elseif ($is_logged_in && $binomial_quiz_id > 0) {
                // Fallback: if binomial_course_state() didn't return submittedCount for any reason,
                // query attempts directly so partners still see the correct CTA.
                global $wpdb;
                if ($wpdb) {
                    $attempts_table = $wpdb->prefix . 'learni_quiz_attempts';
                    $submitted_count = (int) $wpdb->get_var(
                        $wpdb->prepare(
                            "SELECT COUNT(*)
                             FROM {$attempts_table}
                             WHERE quiz_id = %d AND user_id = %d AND status = %s",
                            $binomial_quiz_id,
                            $user_id,
                            'submitted'
                        )
                    );
                }
            }

            $show_first = false;
            if (!$is_logged_in) {
                $show_first = true;
            } elseif ($has_access) {
                if ($submitted_count !== null && $submitted_count === 0) {
                    $show_first = true;
                } elseif (!empty($binomial['needsInitial'])) {
                    $show_first = true;
                }
            }

            if ($show_first) {
                $html .= '<button id="learni-course-first-quiz" class="learni-btn learni-btn-quiz" type="button" data-course-id="' . esc_attr((string) $course_id) . '" data-phase="initial">' . esc_html__('TAKE FIRST QUIZ', 'politeia-learning') . '</button>';
            }

            // Final evaluation CTA:
            // - Courses without partner: the learner takes their own final quiz ("Take Final Quiz").
            // - Courses with partner: the final is always initiated by the *other* user via "Test Partner".
            $is_enrolled = class_exists('\\Learni\\Database\\Enrollments') ? \Learni\Database\Enrollments::user_has_active($user_id, $course_id) : false;
	            if ($has_course_partner && $partner_user_id > 0) {
	                $is_in_pair = ($user_id === $partner_user_id) || ($owner_user_id > 0 && $user_id === $owner_user_id);
	                $other_user_id = ($partner_user_id === $user_id) ? $owner_user_id : $partner_user_id;
	                $show_test_partner = false;
	                $test_partner_disabled = false;
	                $test_partner_tooltip = '';
	                if ($is_logged_in && $has_access && $is_in_pair && $other_user_id > 0) {
	                    $other_summary = class_exists('\\Learni\\Database\\Progress') ? \Learni\Database\Progress::course_summary($other_user_id, $course_id) : ['percent' => 0];
	                    $other_percent = (int) ($other_summary['percent'] ?? 0);
	                    $other_binomial = self::binomial_course_state($course_id, $other_user_id, $other_percent);
	                    if (!empty($other_binomial['needsFinal']) && $other_percent >= 100 && empty($other_binomial['eligibleFinal'])) {
	                        $show_test_partner = true;
	                        if (empty($other_binomial['canTakeFinal']) || !empty($other_binomial['cooldownDaysRemaining'])) {
	                            $test_partner_disabled = true;
	                            $days = (int) ($other_binomial['cooldownDaysRemaining'] ?? 0);
	                            if ($days > 0) {
	                                $test_partner_tooltip = sprintf(
	                                    _n(
	                                        'En %d día podrás volver a tomar la Evaluación Final.',
	                                        'En %d días podrás volver a tomar la Evaluación Final.',
	                                        $days,
	                                        'politeia-learning'
	                                    ),
	                                    $days
	                                );
	                            }
	                        }
	                    }
	                }
	
	                if ($show_test_partner) {
	                    $disabled = ($is_enrolled && !$test_partner_disabled) ? '' : ' disabled';
	                    $label = __('TEST PARTNER', 'politeia-learning');
	                    $days = (int) ($other_binomial['cooldownDaysRemaining'] ?? 0);
	                    if ($disabled !== '' && $days > 0) {
	                        $label = sprintf(__('%s — %d días +', 'politeia-learning'), $label, $days);
	                    }
	                    $btn = '<button id="learni-course-test-partner" class="learni-btn learni-btn-quiz" type="button" data-base-label="' . esc_attr__('TEST PARTNER', 'politeia-learning') . '" data-course-id="' . esc_attr((string) $course_id) . '"' . $disabled . '>' . esc_html($label) . '</button>';
	                    if ($disabled !== '' && $test_partner_tooltip !== '') {
	                        $html .= '<span class="learni-tooltip-wrap" title="' . esc_attr($test_partner_tooltip) . '">' . $btn . '</span>';
	                    } else {
	                        $html .= $btn;
	                    }
	                }
	            } else {
	                if ($is_logged_in && !empty($binomial['needsFinal']) && $percent >= 100 && empty($binomial['eligibleFinal'])) {
	                    $disabled = (!empty($binomial['canTakeFinal']) && $is_enrolled) ? '' : ' disabled';
	                    $tooltip = '';
	                    $days = (int) ($binomial['cooldownDaysRemaining'] ?? 0);
	                    if ($disabled !== '' && $days > 0) {
	                        $tooltip = sprintf(
	                            _n(
	                                'En %d día podrás volver a tomar la Evaluación Final.',
	                                'En %d días podrás volver a tomar la Evaluación Final.',
	                                $days,
	                                'politeia-learning'
	                            ),
	                            $days
	                        );
	                    }
	                    $label = __('TAKE FINAL QUIZ', 'politeia-learning');
	                    if ($disabled !== '' && $days > 0) {
	                        $label = sprintf(__('%s — %d días +', 'politeia-learning'), $label, $days);
	                    }
	                    $btn = '<button id="learni-course-final-quiz" class="learni-btn learni-btn-quiz" type="button" data-base-label="' . esc_attr__('TAKE FINAL QUIZ', 'politeia-learning') . '" data-course-id="' . esc_attr((string) $course_id) . '" data-phase="final"' . $disabled . '>' . esc_html($label) . '</button>';
	                    if ($tooltip !== '') {
	                        $html .= '<span class="learni-tooltip-wrap" title="' . esc_attr($tooltip) . '">' . $btn . '</span>';
	                    } else {
	                        $html .= $btn;
	                    }
	                }
	            }
	        }

        $is_enrolled = $has_access && class_exists('\\Learni\\Database\\Enrollments') ? \Learni\Database\Enrollments::user_has_active($user_id, $course_id) : false;
        $course_permalink = (string) get_permalink($course_id);
        $first_lesson_url = '';
        if (!empty($lesson_ids)) {
            $first_slug = (string) get_post_field('post_name', (int) $lesson_ids[0]);
            $first_lesson_url = $first_slug !== '' ? home_url('/?learni_lesson=' . rawurlencode($first_slug)) : '';
        }

        $continue_lesson_url = $first_lesson_url;
        if (!empty($lesson_ids)) {
            if ($linear_order) {
                $continue_id = ($max_unlocked >= 0 && isset($lesson_ids[$max_unlocked])) ? (int) $lesson_ids[$max_unlocked] : (int) $lesson_ids[0];
                $continue_slug = (string) get_post_field('post_name', $continue_id);
                $continue_lesson_url = $continue_slug !== '' ? home_url('/?learni_lesson=' . rawurlencode($continue_slug)) : $first_lesson_url;
            } else {
                $continue_id = 0;
                foreach ($lesson_ids as $lid) {
                    $lid = (int) $lid;
                    if ($lid > 0 && !isset($completed[$lid])) {
                        $continue_id = $lid;
                        break;
                    }
                }
                if ($continue_id <= 0) {
                    $continue_id = (int) $lesson_ids[0];
                }
                $continue_slug = (string) get_post_field('post_name', $continue_id);
                $continue_lesson_url = $continue_slug !== '' ? home_url('/?learni_lesson=' . rawurlencode($continue_slug)) : $first_lesson_url;
            }
        }

        if ($has_access && !empty($binomial['canRestart'])) {
            $html .= '<button id="learni-course-restart" class="learni-btn learni-course-primary-btn" type="button" data-course-id="' . esc_attr((string) $course_id) . '">' . esc_html__('REINICIAR CURSO', 'politeia-learning') . '</button>';
        } elseif (!$is_enrolled && !$is_free) {
            $checkout_url = $product_id > 0 ? self::checkout_course_url($course_id) : '#';
            $product_url = ($user_id <= 0 && $checkout_url !== '#') ? wp_login_url($checkout_url) : $checkout_url;
            if ($product_url === '' || $product_url === '#') {
                $product_url = $course_permalink;
            }
            $html .= '<a class="learni-btn learni-course-primary-btn" href="' . esc_url($product_url) . '">' . esc_html__('COMPRAR CURSO', 'politeia-learning') . '</a>';
        } elseif ($is_free && !$is_enrolled) {
            $redirect_to = $first_lesson_url !== '' ? $first_lesson_url : $course_permalink;
            $html .= '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
            $html .= '<input type="hidden" name="action" value="pl_learni_enroll_course">';
            $html .= '<input type="hidden" name="course_id" value="' . esc_attr((string) $course_id) . '">';
            $html .= '<input type="hidden" name="redirect_to" value="' . esc_attr($redirect_to) . '">';
            $html .= wp_nonce_field('pl_learni_enroll_course_' . $course_id, '_wpnonce', true, false);
            $html .= '<button type="submit" class="learni-btn learni-course-primary-btn">' . esc_html__('START COURSE', 'politeia-learning') . '</button>';
            $html .= '</form>';
        } elseif ($has_access && $continue_lesson_url !== '') {
            $html .= '<a class="learni-btn learni-course-primary-btn" href="' . esc_url($continue_lesson_url) . '">' . esc_html__($is_enrolled ? 'CONTINUE' : 'START COURSE', 'politeia-learning') . '</a>';
        } else {
            $html .= '<button type="button" class="learni-btn learni-course-primary-btn" disabled>' . esc_html__('START COURSE', 'politeia-learning') . '</button>';
        }

        // Pending partner invite (place it right under the main CTA).
        if (is_array($pending_for_me) && !empty($pending_for_me['invite_id']) && !empty($pending_for_me['source'])) {
            $invite_id = (int) ($pending_for_me['invite_id'] ?? 0);
            $source = (string) ($pending_for_me['source'] ?? '');
            $owner_label = (string) ($pending_for_me['owner_label'] ?? '');
            $owner_user_id = (int) ($pending_for_me['owner_user_id'] ?? 0);
            $redirect_to = (string) get_permalink($course_id);

            $owner_avatar = '';
            if ($owner_user_id > 0 && function_exists('pl_get_user_profile_avatar_custom_url')) {
                $owner_avatar = (string) pl_get_user_profile_avatar_custom_url($owner_user_id, 48);
            }
            if ($owner_avatar === '' && $owner_user_id > 0 && function_exists('get_avatar_url')) {
                $owner_avatar = (string) get_avatar_url($owner_user_id, ['size' => 48]);
            }

            $html .= '<div class="learni-course-partner-invite" aria-label="' . esc_attr__('Course partner invite', 'politeia-learning') . '">';
            $html .= '<div class="learni-course-partner-invite__title">' . esc_html__('Partner invite', 'politeia-learning') . '</div>';
            $html .= '<div class="learni-course-partner-invite__row">';
            if ($owner_avatar !== '') {
                $html .= '<img class="learni-course-partner-invite__avatar" src="' . esc_url($owner_avatar) . '" alt="">';
            }
            $html .= '<div class="learni-course-partner-invite__text">' . esc_html(sprintf(__('%s invited you to be a course partner.', 'politeia-learning'), $owner_label !== '' ? $owner_label : __('Someone', 'politeia-learning'))) . '</div>';
            $html .= '</div>';

            $html .= '<div class="learni-course-partner-invite__actions">';
            $html .= '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin:0;">';
            $html .= '<input type="hidden" name="action" value="pl_course_partner_invite_respond">';
            $html .= '<input type="hidden" name="invite_id" value="' . esc_attr((string) $invite_id) . '">';
            $html .= '<input type="hidden" name="source" value="' . esc_attr($source) . '">';
            $html .= '<input type="hidden" name="decision" value="accept">';
            $html .= '<input type="hidden" name="redirect_to" value="' . esc_attr($redirect_to) . '">';
            $html .= wp_nonce_field('pl_course_partner_invite_respond', '_wpnonce', true, false);
            $html .= '<button type="submit" class="learni-btn learni-btn-quiz">' . esc_html__('ACCEPT', 'politeia-learning') . '</button>';
            $html .= '</form>';

            $html .= '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin:0;">';
            $html .= '<input type="hidden" name="action" value="pl_course_partner_invite_respond">';
            $html .= '<input type="hidden" name="invite_id" value="' . esc_attr((string) $invite_id) . '">';
            $html .= '<input type="hidden" name="source" value="' . esc_attr($source) . '">';
            $html .= '<input type="hidden" name="decision" value="reject">';
            $html .= '<input type="hidden" name="redirect_to" value="' . esc_attr($redirect_to) . '">';
            $html .= wp_nonce_field('pl_course_partner_invite_respond', '_wpnonce', true, false);
            $html .= '<button type="submit" class="learni-btn secondary learni-btn-quiz">' . esc_html__('REJECT', 'politeia-learning') . '</button>';
            $html .= '</form>';
            $html .= '</div>';
            $html .= '</div>';
        }

        // Course partner UI (after primary action).
        // - Show info to enrolled users (including the invited partner).
        // - Only the purchaser/owner (or admin) can add/replace/remove partners.
        $show_partner_section = $is_logged_in && $has_access;
        $can_manage_partner = $is_logged_in && (current_user_can('manage_options') || (class_exists('\\Learni\\Database\\Enrollments') && method_exists('\\Learni\\Database\\Enrollments', 'user_is_owner') && \Learni\Database\Enrollments::user_is_owner($user_id, $course_id)));

        if ($show_partner_section) {
            $partner = null;
            if (class_exists('PL_Partnerships_Repository') && method_exists('PL_Partnerships_Repository', 'get_single_partner')) {
                try {
                    $partner = PL_Partnerships_Repository::get_single_partner('course', $course_id, 'partner');
                } catch (\Throwable $e) {
                    $partner = null;
                }
            }

            $partner_user_id = (is_array($partner) && !empty($partner['partner_user_id'])) ? (int) $partner['partner_user_id'] : 0;
            $partner_user = $partner_user_id > 0 ? get_userdata($partner_user_id) : null;
            $partner_name = ($partner_user instanceof \WP_User) ? (string) $partner_user->display_name : '';
            $is_current_partner = $partner_user_id > 0 && $partner_user_id === $user_id;
            if ($is_current_partner) {
                $can_manage_partner = false;
            }
            $owner_user_id = (is_array($partner) && !empty($partner['owner_user_id'])) ? (int) $partner['owner_user_id'] : 0;

            // Fallback: attempt to infer the purchaser/owner from active enrollments.
            if ($owner_user_id <= 0 && $partner_user_id > 0) {
                global $wpdb;
                if ($wpdb) {
                    $enroll_table = $wpdb->prefix . 'learni_enrollments';
                    $rows = $wpdb->get_results(
                        $wpdb->prepare(
                            "SELECT user_id, source, payment_provider
                             FROM {$enroll_table}
                             WHERE course_post_id = %d AND status = %s
                             ORDER BY created_at ASC
                             LIMIT 10",
                            $course_id,
                            \Learni\Database\Enrollments::STATUS_ACTIVE
                        ),
                        ARRAY_A
                    );
                    foreach ((array) $rows as $r) {
                        if (!is_array($r)) {
                            continue;
                        }
                        $candidate_id = (int) ($r['user_id'] ?? 0);
                        if ($candidate_id <= 0) {
                            continue;
                        }
                        $src = (string) ($r['source'] ?? '');
                        $prov = (string) ($r['payment_provider'] ?? '');
                        $is_owner_row = ($src === \Learni\Database\Enrollments::SOURCE_WOOCOMMERCE)
                            || ($src === \Learni\Database\Enrollments::SOURCE_DIRECT)
                            || ($src === \Learni\Database\Enrollments::SOURCE_MANUAL && $prov !== 'partner_invite');
                        if ($is_owner_row) {
                            $owner_user_id = $candidate_id;
                            break;
                        }
                    }
                }
            }

            $other_user_id = 0;
            if ($partner_user_id > 0) {
                $other_user_id = ($partner_user_id === $user_id) ? $owner_user_id : $partner_user_id;
            }

            $html .= '<div class="learni-course-partner">';
            $html .= '<div class="learni-course-partner-title-row">';
            $html .= '<div class="learni-course-partner-title">' . esc_html__('Partner', 'politeia-learning') . '</div>';
            if ($can_manage_partner && $partner_user_id > 0) {
                $html .= '<button type="button" class="pl-partner-remove learni-course-partner-remove" data-object-type="course" data-object-id="' . esc_attr((string) $course_id) . '" data-user-id="' . esc_attr((string) $partner_user_id) . '" aria-label="' . esc_attr__('Eliminar partner', 'politeia-learning') . '" title="' . esc_attr__('Eliminar partner', 'politeia-learning') . '">×</button>';
            }
            $html .= '</div>';

            $pending_invite = function_exists('pl_get_pending_course_partner_invite') ? pl_get_pending_course_partner_invite((int) $course_id) : null;
            if (is_array($pending_invite) && !empty($pending_invite['label'])) {
                $html .= '<div class="learni-course-partner-pending">' . esc_html(sprintf(__('Esperando a %s', 'politeia-learning'), (string) $pending_invite['label'])) . '</div>';
            }

            // Partner section: show lesson progress (black bar). Once a user completes the Final quiz,
            // replace the bar with the final score label.
            if (class_exists('\\Learni\\Database\\Progress') && $other_user_id > 0 && $partner_user_id > 0) {
                $progress_users = array_values(array_unique(array_filter(array_map('absint', [$other_user_id, $user_id]))));

                if (!empty($progress_users)) {
                    $html .= '<div class="learni-course-partner-progress" aria-label="' . esc_attr__('Latest evaluation scores', 'politeia-learning') . '">';

                    foreach ($progress_users as $pid) {
                        $u = get_userdata($pid);
                        $display = ($u instanceof \WP_User) ? (string) $u->display_name : '';
                        if ($display === '') {
                            $display = (string) $pid;
                        }
                        $avatar = '';
                        if (function_exists('pl_get_user_profile_avatar_custom_url')) {
                            $avatar = (string) pl_get_user_profile_avatar_custom_url((int) $pid, 48);
                        }
                        if ($avatar === '' && function_exists('get_avatar_url')) {
                            $avatar = (string) get_avatar_url((int) $pid, ['size' => 48]);
                        }
                        $p_summary = \Learni\Database\Progress::course_summary($pid, $course_id);
                        $lesson_percent = (int) ($p_summary['percent'] ?? 0);
                        if ($lesson_percent < 0) {
                            $lesson_percent = 0;
                        } elseif ($lesson_percent > 100) {
                            $lesson_percent = 100;
                        }

                        $p_binomial = self::binomial_course_state($course_id, (int) $pid, $lesson_percent);
                        $has_final = is_array($p_binomial['final'] ?? null);
                        $final_percent = $has_final ? (int) ($p_binomial['final']['percent'] ?? 0) : 0;
                        if ($final_percent < 0) {
                            $final_percent = 0;
                        } elseif ($final_percent > 100) {
                            $final_percent = 100;
                        }

                        $label = $has_final
                            ? '🏆 ' . sprintf(__('PUNTAJE FINAL: %d%%', 'politeia-learning'), $final_percent)
                            : sprintf(__('%d%%', 'politeia-learning'), $lesson_percent);

                        $html .= '<div class="learni-course-partner-progress-item" data-user-id="' . esc_attr((string) $pid) . '" data-lessons-percent="' . esc_attr((string) $lesson_percent) . '" data-has-final="' . esc_attr($has_final ? '1' : '0') . '" data-final-percent="' . esc_attr((string) $final_percent) . '">';
                        $html .= '<div class="learni-course-partner-progress-head">';
                        $html .= '<div class="learni-course-partner-progress-user">';
                        if ($avatar !== '') {
                            $html .= '<img class="learni-course-partner-progress-avatar" src="' . esc_url($avatar) . '" alt="">';
                        }
                        $html .= '<span class="learni-course-partner-progress-name">' . esc_html($display) . '</span>';
                        $html .= '</div>';
                        $html .= '<span class="learni-course-partner-progress-percent">' . esc_html($label) . '</span>';
                        $html .= '</div>';
                        if (!$has_final) {
                            $html .= '<div class="learni-course-partner-progress-bar" role="progressbar" aria-valuenow="' . esc_attr((string) $lesson_percent) . '" aria-valuemin="0" aria-valuemax="100">';
                            $html .= '<span class="learni-course-partner-progress-fill" style="width:' . esc_attr((string) $lesson_percent) . '%"></span>';
                            $html .= '</div>';
                        }
                        $html .= '</div>';
                    }

                    $html .= '</div>';
                }
            }

            if ($can_manage_partner) {
                $btn_label = ($partner_user_id > 0) ? __('Replace Partner', 'politeia-learning') : __('Add Partner', 'politeia-learning');
                $html .= '<button id="pl-add-partner-btn-' . esc_attr((string) $course_id) . '" type="button" class="learni-btn learni-course-partner-btn pl-add-partner addPartnerBtn">';
                $html .= '<span class="material-symbols-outlined learni-ms-icon" aria-hidden="true">person_add</span>';
                $html .= '<span class="learni-course-partner-btn-text">' . esc_html($btn_label) . '</span>';
                $html .= '</button>';
            }
            $html .= '</div>';
        }

        $html .= '</div>';
        $html .= '<div class="learni-course-includes">';
        $html .= '<div class="learni-course-includes-title">' . esc_html__('CURSO INCLUYE', 'politeia-learning') . '</div>';
        $html .= '<div class="learni-course-includes-row">';
        $html .= '<span class="learni-course-includes-icon"><span class="material-symbols-outlined learni-ms-icon" aria-hidden="true">auto_stories</span></span>';
        $html .= '<span class="learni-course-includes-text">' . esc_html(sprintf(_n('%d Lección', '%d Lecciones', $total, 'politeia-learning'), $total)) . '</span>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</aside>';

        $html .= '</div></div></section>';

        // Render course content using the normal WP content pipeline (minus our own wrapper filter).
        $raw = (string) get_post_field('post_content', $course_id);
        $processed = apply_filters('the_content', $raw);
        $processed = self::strip_empty_course_padding_group($processed);
        $html .= '<div class="learni-course-body">';
        $html .= '<div class="learni-tabs" role="tablist" aria-label="' . esc_attr__('Course tabs', 'politeia-learning') . '">';
        $html .= '<button type="button" class="learni-tab is-active" role="tab" aria-selected="true" data-learni-tab="content">' . esc_html__('CONTENT', 'politeia-learning') . '</button>';
        $html .= '<button type="button" class="learni-tab" role="tab" aria-selected="false" data-learni-tab="lessons">' . esc_html__('LESSONS', 'politeia-learning') . '</button>';
        $html .= '</div>';

        $html .= '<div class="learni-tabpanel is-active" role="tabpanel" data-learni-panel="content">';
        $html .= '<section class="learni-course-content">' . $processed . '</section>';
        $html .= '</div>';

        $html .= '<div class="learni-tabpanel" role="tabpanel" data-learni-panel="lessons">';
        $html .= '<section class="learni-outline">';

        if (empty($items)) {
            $html .= '<p>' . esc_html__('No lessons yet.', 'politeia-learning') . '</p>';
        } else {
            $html .= '<ul class="learni-outline-list">';
            foreach ($items as $item) {
                $type = (string) ($item['type'] ?? '');
                if ($type === 'header') {
                    $html .= '<li class="learni-outline-header">' . esc_html((string) ($item['label'] ?? '')) . '</li>';
                    continue;
                }
                if ($type !== 'lesson') {
                    continue;
                }

                $lesson_id = (int) ($item['refId'] ?? 0);
                if ($lesson_id <= 0) {
                    continue;
                }

                $lesson_slug = (string) get_post_field('post_name', $lesson_id);
                $url = $lesson_slug !== '' ? home_url('/?learni_lesson=' . rawurlencode($lesson_slug)) : '';
                $is_done = isset($completed[$lesson_id]);
                $label = (string) get_the_title($lesson_id);
                $pos = isset($lesson_index[$lesson_id]) ? (int) $lesson_index[$lesson_id] : -1;
                $is_locked = $linear_order && $pos >= 0 && $max_unlocked >= 0 && $pos > $max_unlocked;

                $is_purchased = class_exists('\\Learni\\Access\\Access') && \Learni\Access\Access::user_can_access_course($user_id, $course_id);
                $html .= '<li class="learni-outline-lesson' . ($is_done ? ' is-complete' : '') . ($is_locked || !$is_purchased ? ' is-locked' : '') . '"' . ($is_locked ? ' title="' . esc_attr__('Completa las lecciones anteriores para desbloquear.', 'politeia-learning') . '"' : ($is_purchased ? '' : ' title="' . esc_attr__('Compra el curso para acceder.', 'politeia-learning') . '"')) . '>';
                if ($url !== '' && !$is_locked && $is_purchased) {
                    $html .= '<a href="' . esc_url($url) . '">';
                } else {
                    $html .= '<span>';
                }
                $html .= '<span class="learni-check" aria-hidden="true">' . ($is_done ? '✓' : '•') . '</span>';
                $html .= '<span class="learni-label">' . esc_html($label) . '</span>';
                $html .= ($url !== '' && !$is_locked && $is_purchased) ? '</a>' : '</span>';
                $html .= '</li>';
            }
            $html .= '</ul>';
        }

        $html .= '</section>';
        $html .= '</div>'; // lessons panel

        $html .= '</div>'; // course body
        $html .= '</div>'; // #learni-course

        if ($certificate_available) {
            $html .= self::render_certificate_modal_html($course_id, $user_id);
        }

        return $html;
    }

    private static function render_lesson_block_theme(): string
    {
        $lesson_id = (int) get_the_ID();
        if ($lesson_id <= 0) {
            return '<div class="learni-learner"><p>' . esc_html__('Lesson not found.', 'politeia-learning') . '</p></div>';
        }

        $user_id = (int) get_current_user_id();
        $course_id = self::get_course_id_for_lesson($lesson_id);

        // Enforce access control on frontend main query.
        if (!is_admin() && is_main_query() && $course_id > 0) {
            if (!class_exists('\\Learni\\Access\\Access') || !\Learni\Access\Access::user_can_access_course($user_id, $course_id)) {
                wp_safe_redirect((string) get_permalink($course_id));
                exit;
            }
        }

        $course_id = 0;
        global $wpdb;
        if ($wpdb) {
            $course_id = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT course_post_id
                     FROM {$wpdb->prefix}learni_course_items
                     WHERE item_type = %s AND item_ref_id = %d
                     ORDER BY id DESC
                     LIMIT 1",
                    'lesson',
                    $lesson_id
                )
            );
        }

        $items = ($course_id > 0 && class_exists('\\Learni\\Courses\\Outline')) ? \Learni\Courses\Outline::get_items($course_id) : [];
        $lesson_ids = ($course_id > 0 && class_exists('\\Learni\\Courses\\Outline')) ? \Learni\Courses\Outline::lesson_ids($course_id) : [];
        $index = array_search($lesson_id, $lesson_ids, true);
        $index = is_int($index) ? $index : -1;

        $prev_id = $index > 0 ? (int) $lesson_ids[$index - 1] : 0;
        $next_id = ($index >= 0 && $index < count($lesson_ids) - 1) ? (int) $lesson_ids[$index + 1] : 0;

        $prev_url = '';
        $next_url = '';
        if ($prev_id > 0) {
            $prev_slug = (string) get_post_field('post_name', $prev_id);
            $prev_url = $prev_slug !== '' ? home_url('/?learni_lesson=' . rawurlencode($prev_slug)) : '';
        }
        if ($next_id > 0) {
            $next_slug = (string) get_post_field('post_name', $next_id);
            $next_url = $next_slug !== '' ? home_url('/?learni_lesson=' . rawurlencode($next_slug)) : '';
        }

        $summary = ($user_id > 0 && $course_id > 0 && class_exists('\\Learni\\Database\\Progress'))
            ? \Learni\Database\Progress::course_summary($user_id, $course_id)
            : ['total' => count($lesson_ids), 'completed' => 0, 'percent' => 0];

        $completed = ($user_id > 0 && $course_id > 0 && class_exists('\\Learni\\Database\\Progress'))
            ? array_flip(\Learni\Database\Progress::completed_lesson_ids($user_id, $course_id))
            : [];

        $linear_order = $course_id > 0 ? self::course_linear_order_enabled($course_id) : true;
        $max_unlocked = self::max_unlocked_lesson_index($lesson_ids, $completed, $linear_order);
        $lesson_index = self::lesson_index_map($lesson_ids);
        $is_locked = $linear_order && $index >= 0 && $max_unlocked >= 0 && $index > $max_unlocked;

        $is_complete = isset($completed[$lesson_id]);
        $total = (int) ($summary['total'] ?? 0);
        $step = $index >= 0 ? $index + 1 : 0;
        $percent = (int) ($summary['percent'] ?? 0);

        $raw = (string) get_post_field('post_content', $lesson_id);
        ob_start();
        $processed_filtered = apply_filters('the_content', $raw);
        $processed_echoed = ob_get_clean();
        $processed = $processed_echoed . (is_string($processed_filtered) ? $processed_filtered : '');

        $src_meta_key = class_exists('\\Learni\\PostTypes\\Lesson')
            ? \Learni\PostTypes\Lesson::META_SOURCE_POST_ID
            : 'learni_source_post_id';
        $src_post_id = $lesson_id > 0 ? (int) get_post_meta($lesson_id, $src_meta_key, true) : 0;
        if ($src_post_id > 0) {
            $escrito_post = get_post($src_post_id);
            $valid_type = ($escrito_post instanceof \WP_Post) && $escrito_post->post_type === 'post';
            $valid_status = $valid_type && in_array((string) $escrito_post->post_status, ['publish', 'draft'], true);
            $can_view_draft = $valid_type && current_user_can('edit_post', (int) $src_post_id);
            if (!$valid_type || !$valid_status || ((string) $escrito_post->post_status !== 'publish' && !$can_view_draft)) {
                $processed = '<p>' . esc_html__('El texto vinculado no está disponible.', 'politeia-learning') . '</p>';
            } else {
                $orig_post = $GLOBALS['post'] ?? null;
                $GLOBALS['post'] = $escrito_post;
                setup_postdata($GLOBALS['post']);
                ob_start();
                $escrito_filtered = apply_filters('the_content', (string) ($escrito_post->post_content ?? ''));
                $escrito_echoed = ob_get_clean();
                wp_reset_postdata();
                if ($orig_post instanceof \WP_Post) {
                    $GLOBALS['post'] = $orig_post;
                }
                $processed = '<div class="pcg-escrito-content-editor">' . $escrito_echoed . (is_string($escrito_filtered) ? $escrito_filtered : '') . '</div>';
            }
        }

        $video_meta_key = class_exists('\\Learni\\PostTypes\\Lesson')
            ? \Learni\PostTypes\Lesson::META_VIDEO_URL
            : 'learni_video_url';
        $video_url = (string) get_post_meta($lesson_id, $video_meta_key, true);
        $video_html = '';
        $video_provider = '';
        $youtube_id = '';
        if ($video_url !== '') {
            $youtube_id = self::parse_youtube_id($video_url);
            if ($youtube_id !== '') {
                $video_provider = 'youtube';
                $embed_url = self::youtube_embed_url($youtube_id);
                $video_html = $embed_url !== '' ? '<iframe id="learni-youtube-player" src="' . esc_url($embed_url) . '" title="" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen loading="lazy" referrerpolicy="strict-origin-when-cross-origin"></iframe>' : '';
            } else {
                $video_html = (string) wp_oembed_get($video_url);
            }
        }

        $course_url = $course_id > 0 ? (string) get_permalink($course_id) : '';
        $course_title = $course_id > 0 ? (string) get_the_title($course_id) : '';

        if ($is_locked) {
            $unlocked_id = ($max_unlocked >= 0 && isset($lesson_ids[$max_unlocked])) ? (int) $lesson_ids[$max_unlocked] : 0;
            $unlocked_slug = $unlocked_id > 0 ? (string) get_post_field('post_name', $unlocked_id) : '';
            $unlocked_url = $unlocked_slug !== '' ? home_url('/?learni_lesson=' . rawurlencode($unlocked_slug)) : '';
            $prev_url = $unlocked_url !== '' ? $unlocked_url : $prev_url;
            $processed = '<div class="learni-lesson-locked"><div class="learni-lesson-locked-title">' . esc_html__('Lección bloqueada', 'politeia-learning') . '</div><div class="learni-lesson-locked-text">' . esc_html__('Debes finalizar las lecciones anteriores para continuar.', 'politeia-learning') . '</div>' . ($unlocked_url !== '' ? '<a class="learni-lesson-locked-link" href="' . esc_url($unlocked_url) . '">' . esc_html__('Ir a la siguiente lección disponible', 'politeia-learning') . '</a>' : '') . '</div>';
            $video_html = '';
        }

        $html = '<div class="learni-learner learni-lesson-layout alignwide" data-learni-course-id="' . esc_attr((string) $course_id) . '">';
        $html .= '<div class="learni-lesson-shell">';

        $html .= '<aside class="learni-lesson-aside" aria-label="' . esc_attr__('Course navigation', 'politeia-learning') . '">';
        if ($course_url !== '') {
            $html .= '<a class="learni-lesson-back" href="' . esc_url($course_url) . '"><span class="learni-lesson-back-label">' . esc_html__('VOLVER A CURSO', 'politeia-learning') . '</span></a>';
        }
        if ($course_title !== '') {
            $html .= '<h2 id="learni-lesson-course-title" class="learni-lesson-course-title">' . esc_html($course_title) . '</h2>';
        }
        if ($course_id > 0) {
            $html .= '<div class="learni-lesson-course-progress" aria-label="' . esc_attr__('Course progress', 'politeia-learning') . '">';
            $html .= '<div class="learni-lesson-course-progress-label">' . esc_html(sprintf(__('%d%% COMPLETO', 'politeia-learning'), $percent)) . '</div>';
            $html .= '<div class="learni-lesson-course-progress-bar" role="progressbar" aria-valuenow="' . esc_attr((string) $percent) . '" aria-valuemin="0" aria-valuemax="100">';
            $html .= '<span class="learni-lesson-course-progress-fill" style="width:' . esc_attr((string) $percent) . '%"></span>';
            $html .= '</div>';
            $html .= '</div>';
        }

        $html .= '<nav id="learni-lesson-outline" class="learni-lesson-outline" aria-label="' . esc_attr__('Lessons', 'politeia-learning') . '">';
        if (empty($items)) {
            $html .= '<div class="learni-lesson-outline-empty">' . esc_html__('No lessons yet.', 'politeia-learning') . '</div>';
        } else {
            $html .= '<ul class="learni-lesson-outline-list">';
            foreach ($items as $item) {
                $type = (string) ($item['type'] ?? '');
                if ($type === 'header') {
                    $label = (string) ($item['label'] ?? '');
                    if ($label !== '') {
                        $html .= '<li class="learni-lesson-outline-header">' . esc_html($label) . '</li>';
                    }
                    continue;
                }
                if ($type !== 'lesson') {
                    continue;
                }
                $item_lesson_id = (int) ($item['refId'] ?? 0);
                if ($item_lesson_id <= 0) {
                    continue;
                }

                $label = (string) get_the_title($item_lesson_id);
                $slug = (string) get_post_field('post_name', $item_lesson_id);
                $url = $slug !== '' ? home_url('/?learni_lesson=' . rawurlencode($slug)) : '';
                $item_is_complete = isset($completed[$item_lesson_id]);
                $pos = isset($lesson_index[$item_lesson_id]) ? (int) $lesson_index[$item_lesson_id] : -1;
                $item_is_locked = $linear_order && $pos >= 0 && $max_unlocked >= 0 && $pos > $max_unlocked;
                $classes = 'learni-lesson-outline-item';
                if ($item_lesson_id === $lesson_id) {
                    $classes .= ' is-active';
                }
                if ($item_is_complete) {
                    $classes .= ' is-complete';
                }
                if ($item_is_locked) {
                    $classes .= ' is-locked';
                }

                $html .= '<li class="' . esc_attr($classes) . '">';
                if ($url !== '' && !$item_is_locked) {
                    $html .= '<a href="' . esc_url($url) . '">';
                } else {
                    $html .= '<span>';
                }
                $html .= '<span class="learni-lesson-outline-label">' . esc_html($label) . '</span>';
                $html .= '<span class="learni-lesson-outline-status" aria-hidden="true">' . ($item_is_complete ? '✓' : '') . '</span>';
                $html .= ($url !== '') ? '</a>' : '</span>';
                $html .= '</li>';
            }
            $html .= '</ul>';
        }
        $html .= '</nav>';
        $html .= '</aside>';

        $html .= '<section class="learni-lesson-main" aria-label="' . esc_attr__('Lesson content', 'politeia-learning') . '">';
        $html .= '<div class="learni-lesson-top">';
        $html .= '<div class="learni-lesson-step">' . esc_html(sprintf(__('LECCIÓN %1$d DE %2$d', 'politeia-learning'), (int) $step, (int) $total)) . '</div>';
        $html .= '<div class="learni-lesson-top-actions">';

        $btn_label = __('FINALIZADO', 'politeia-learning');
        $btn_disabled = ($user_id <= 0) || $is_complete || ($course_id <= 0) || $is_locked;
        $requires_video_gate = (!$btn_disabled && $video_provider === 'youtube' && $video_html !== '');
        $btn_disabled = $btn_disabled || $requires_video_gate;
        $complete_redirect = ($next_url !== '' && !$is_locked) ? $next_url : (wp_get_raw_referer() ?: '');
        $html .= '<form class="learni-lesson-complete-form" method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        $html .= '<input type="hidden" name="action" value="pl_learni_mark_lesson_complete">';
        $html .= '<input type="hidden" name="lesson_id" value="' . esc_attr((string) $lesson_id) . '">';
        $html .= '<input type="hidden" name="redirect_to" value="' . esc_attr((string) $complete_redirect) . '">';
        $html .= wp_nonce_field('pl_learni_complete_lesson_' . $lesson_id, '_wpnonce', true, false);
        if ($requires_video_gate) {
            $html .= '<span class="learni-lesson-complete-wrap" data-learni-tooltip="' . esc_attr__('Finaliza el video para declarar la lección como finalizada.', 'politeia-learning') . '">';
        }
        $html .= '<button type="submit" class="learni-lesson-complete-btn' . ($is_complete ? ' is-complete' : '') . '"' . ($requires_video_gate ? ' data-learni-requires-video="1"' : '') . ($btn_disabled ? ' disabled' : '') . '>';
        $html .= '<span class="learni-lesson-complete-icon" aria-hidden="true"></span>';
        $html .= '<span class="learni-lesson-complete-text">' . esc_html($btn_label) . '</span>';
        $html .= '</button>';
        if ($requires_video_gate) {
            $html .= '<span class="learni-tooltip" role="tooltip">' . esc_html__('Finaliza el video para declarar la lección como finalizada.', 'politeia-learning') . '</span>';
            $html .= '</span>';
        }
        $html .= '</form>';

        $next_pos = ($next_id > 0 && isset($lesson_index[$next_id])) ? (int) $lesson_index[$next_id] : -1;
        $next_unlocked = (!$linear_order) || ($next_pos >= 0 && $max_unlocked >= 0 && $next_pos <= $max_unlocked);
        if ($next_url !== '' && !$is_locked && (!$linear_order || $is_complete) && $next_unlocked) {
            $html .= '<a class="learni-lesson-next-btn" href="' . esc_url($next_url) . '" aria-label="' . esc_attr__('Next lesson', 'politeia-learning') . '">→</a>';
        }
        $html .= '</div>'; // actions
        $html .= '</div>'; // top

        $html .= '<h1 class="learni-lesson-title">' . esc_html(get_the_title($lesson_id)) . '</h1>';
        $html .= '<div class="learni-lesson-body">';
        if ($video_html !== '') {
            $html .= '<div id="learni-lesson-video" class="learni-lesson-video"' . ($video_provider !== '' ? ' data-learni-video-provider="' . esc_attr($video_provider) . '"' : '') . ($video_provider === 'youtube' && $youtube_id !== '' ? ' data-learni-youtube-id="' . esc_attr($youtube_id) . '"' : '') . '>' . $video_html . '</div>';
        }
        $html .= $processed;
        $html .= '</div>';
        $html .= '</section>';

        $html .= '<button type="button" class="learni-outline-fab" aria-label="' . esc_attr__('Open lessons', 'politeia-learning') . '" aria-controls="learni-lesson-outline-overlay" aria-expanded="false">';
        $html .= self::outline_fab_icon_svg();
        $html .= '</button>';

        $html .= '<div id="learni-lesson-outline-overlay" class="learni-outline-overlay" aria-hidden="true">';
        $html .= '<button type="button" class="learni-outline-overlay-backdrop" aria-label="' . esc_attr__('Close lessons', 'politeia-learning') . '"></button>';
        $html .= '<div class="learni-outline-overlay-panel" role="dialog" tabindex="-1" aria-modal="true" aria-label="' . esc_attr__('Lessons', 'politeia-learning') . '">';
        $html .= '<div class="learni-outline-overlay-handle" aria-hidden="true"></div>';
        $html .= '<nav class="learni-lesson-outline learni-lesson-outline-overlay" aria-label="' . esc_attr__('Lessons', 'politeia-learning') . '">';
        if (empty($items)) {
            $html .= '<div class="learni-lesson-outline-empty">' . esc_html__('No lessons yet.', 'politeia-learning') . '</div>';
        } else {
            $html .= '<ul class="learni-lesson-outline-list">';
            foreach ($items as $item) {
                $type = (string) ($item['type'] ?? '');
                if ($type === 'header') {
                    $label = (string) ($item['label'] ?? '');
                    if ($label !== '') {
                        $html .= '<li class="learni-lesson-outline-header">' . esc_html($label) . '</li>';
                    }
                    continue;
                }
                if ($type !== 'lesson') {
                    continue;
                }
                $item_lesson_id = (int) ($item['refId'] ?? 0);
                if ($item_lesson_id <= 0) {
                    continue;
                }

                $label = (string) get_the_title($item_lesson_id);
                $slug = (string) get_post_field('post_name', $item_lesson_id);
                $url = $slug !== '' ? home_url('/?learni_lesson=' . rawurlencode($slug)) : '';
                $item_is_complete = isset($completed[$item_lesson_id]);
                $pos = isset($lesson_index[$item_lesson_id]) ? (int) $lesson_index[$item_lesson_id] : -1;
                $item_is_locked = $linear_order && $pos >= 0 && $max_unlocked >= 0 && $pos > $max_unlocked;
                $classes = 'learni-lesson-outline-item';
                if ($item_lesson_id === $lesson_id) {
                    $classes .= ' is-active';
                }
                if ($item_is_complete) {
                    $classes .= ' is-complete';
                }
                if ($item_is_locked) {
                    $classes .= ' is-locked';
                }

                $html .= '<li class="' . esc_attr($classes) . '">';
                if ($url !== '' && !$item_is_locked) {
                    $html .= '<a href="' . esc_url($url) . '">';
                } else {
                    $html .= '<span>';
                }
                $html .= '<span class="learni-lesson-outline-label">' . esc_html($label) . '</span>';
                $html .= '<span class="learni-lesson-outline-status" aria-hidden="true">' . ($item_is_complete ? '✓' : '') . '</span>';
                $html .= ($url !== '') ? '</a>' : '</span>';
                $html .= '</li>';
            }
            $html .= '</ul>';
        }
        $html .= '</nav>';
        $html .= '</div>';
        $html .= '</div>';

        $html .= '</div></div>';

        return $html;
    }

    private static function outline_fab_icon_svg(): string
    {
        return '<svg class="learni-outline-fab-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M19 2H8c-1.1 0-2 .9-2 2v15c0 .55.45 1 1 1h12v-2H8V4h11v16h2V4c0-1.1-.9-2-2-2zM3 6c-.55 0-1 .45-1 1v14c0 .55.45 1 1 1h14v-2H4V7c0-.55-.45-1-1-1z"></path></svg>';
    }

    public static function handle_mark_lesson_complete_nopriv(): void
    {
        $redirect = isset($_POST['redirect_to']) ? (string) wp_unslash($_POST['redirect_to']) : '';
        if ($redirect === '') {
            $redirect = home_url('/');
        }
        wp_safe_redirect(wp_login_url($redirect));
        exit;
    }

    public static function handle_enroll_course_nopriv(): void
    {
        $redirect = isset($_POST['redirect_to']) ? (string) wp_unslash($_POST['redirect_to']) : '';
        if ($redirect === '') {
            $redirect = home_url('/');
        }
        wp_safe_redirect(wp_login_url($redirect));
        exit;
    }

    public static function handle_enroll_course(): void
    {
        $user_id = (int) get_current_user_id();
        $course_id = isset($_POST['course_id']) ? (int) $_POST['course_id'] : 0;
        $redirect = isset($_POST['redirect_to']) ? (string) wp_unslash($_POST['redirect_to']) : '';

        if ($redirect === '') {
            $redirect = wp_get_referer() ?: home_url('/');
        }

        if ($user_id <= 0) {
            wp_safe_redirect(wp_login_url($redirect));
            exit;
        }

        if ($course_id <= 0) {
            wp_safe_redirect($redirect);
            exit;
        }

        check_admin_referer('pl_learni_enroll_course_' . $course_id);

        $price = (float) get_post_meta($course_id, 'learni_price', true);
        if ($price > 0) {
            // Paid enrollments are handled via WooCommerce for now.
            wp_safe_redirect($redirect);
            exit;
        }

        if (class_exists('\\Learni\\Database\\Enrollments')) {
            \Learni\Database\Enrollments::upsert(
                $user_id,
                $course_id,
                [
                    'status' => \Learni\Database\Enrollments::STATUS_ACTIVE,
                    'source' => \Learni\Database\Enrollments::SOURCE_DIRECT,
                ]
            );
        }

        wp_safe_redirect($redirect);
        exit;
    }

    public static function handle_mark_lesson_complete(): void
    {
        $user_id = (int) get_current_user_id();
        $lesson_id = isset($_POST['lesson_id']) ? (int) $_POST['lesson_id'] : 0;
        $redirect = isset($_POST['redirect_to']) ? (string) wp_unslash($_POST['redirect_to']) : '';

        if ($redirect === '') {
            $redirect = wp_get_referer() ?: home_url('/');
        }

        if ($user_id <= 0) {
            wp_safe_redirect(wp_login_url($redirect));
            exit;
        }

        if ($lesson_id <= 0) {
            wp_safe_redirect($redirect);
            exit;
        }

        check_admin_referer('pl_learni_complete_lesson_' . $lesson_id);

        $course_id = 0;
        global $wpdb;
        if ($wpdb) {
            $course_id = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT course_post_id
                     FROM {$wpdb->prefix}learni_course_items
                     WHERE item_type = %s AND item_ref_id = %d
                     ORDER BY id DESC
                     LIMIT 1",
                    'lesson',
                    $lesson_id
                )
            );
        }

        if ($course_id <= 0) {
            wp_safe_redirect($redirect);
            exit;
        }

        if (class_exists('\\Learni\\Access\\Access') && !\Learni\Access\Access::user_can_access_course($user_id, $course_id)) {
            wp_safe_redirect($redirect);
            exit;
        }

        $linear_order = self::course_linear_order_enabled($course_id);
        if ($linear_order && class_exists('\\Learni\\Courses\\Outline') && class_exists('\\Learni\\Database\\Progress')) {
            $lesson_ids = \Learni\Courses\Outline::lesson_ids($course_id);
            $lesson_index = self::lesson_index_map($lesson_ids);
            $pos = isset($lesson_index[$lesson_id]) ? (int) $lesson_index[$lesson_id] : -1;
            $completed = array_flip(\Learni\Database\Progress::completed_lesson_ids($user_id, $course_id));
            $max_unlocked = self::max_unlocked_lesson_index($lesson_ids, $completed, true);
            if ($pos >= 0 && $max_unlocked >= 0 && $pos > $max_unlocked) {
                wp_safe_redirect($redirect);
                exit;
            }
        }

        $now = current_time('mysql');
        $table = $wpdb->prefix . 'learni_progress';

        $wpdb->query(
            $wpdb->prepare(
                "INSERT INTO {$table} (user_id, course_post_id, lesson_post_id, status, completed_at, updated_at)
                 VALUES (%d, %d, %d, %s, %s, %s)
                 ON DUPLICATE KEY UPDATE course_post_id = VALUES(course_post_id), status = VALUES(status), completed_at = VALUES(completed_at), updated_at = VALUES(updated_at)",
                $user_id,
                $course_id,
                $lesson_id,
                \Learni\Database\Progress::STATUS_COMPLETE,
                $now,
                $now
            )
        );

        wp_safe_redirect($redirect);
        exit;
    }

    private static function course_linear_order_enabled(int $course_id): bool
    {
        if ($course_id <= 0 || !class_exists('\\Learni\\PostTypes\\Course')) {
            return true;
        }
        $meta_key = \Learni\PostTypes\Course::META_LINEAR_ORDER;
        $exists = metadata_exists('post', $course_id, $meta_key);
        $raw = get_post_meta($course_id, $meta_key, true);

        // Default behavior: if the meta was never set, enforce linear order.
        // Note: for registered boolean meta, WordPress may store `false` as an empty string.
        if (!$exists) {
            return true;
        }
        if ($raw === '' || $raw === false || $raw === 0 || $raw === '0') {
            return false;
        }
        if (is_bool($raw)) {
            return $raw;
        }
        return (bool) (int) $raw;
    }

    /**
     * @param array<int,int> $lesson_ids
     * @return array<int,int> map lesson_id => index
     */
    private static function lesson_index_map(array $lesson_ids): array
    {
        $map = [];
        foreach ($lesson_ids as $i => $id) {
            $id = (int) $id;
            if ($id > 0) {
                $map[$id] = (int) $i;
            }
        }
        return $map;
    }

    /**
     * Computes the highest lesson index the user may open when linear order is enabled.
     *
     * @param array<int,int> $lesson_ids
     * @param array<int,true> $completed_map
     */
    private static function max_unlocked_lesson_index(array $lesson_ids, array $completed_map, bool $linear_order): int
    {
        $n = count($lesson_ids);
        if ($n <= 0) {
            return -1;
        }
        if (!$linear_order) {
            return $n - 1;
        }
        $i = 0;
        while ($i < $n) {
            $lesson_id = (int) $lesson_ids[$i];
            if (!isset($completed_map[$lesson_id])) {
                break;
            }
            $i++;
        }
        return min($i, $n - 1);
    }

    private static function parse_youtube_id(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }

        $parts = wp_parse_url($url);
        if (!is_array($parts)) {
            return '';
        }

        $host = isset($parts['host']) ? strtolower((string) $parts['host']) : '';
        $path = isset($parts['path']) ? (string) $parts['path'] : '';
        $query = [];
        if (isset($parts['query'])) {
            wp_parse_str((string) $parts['query'], $query);
        }

        if (str_contains($host, 'youtube.com')) {
            $id = isset($query['v']) ? (string) $query['v'] : '';
            $id = preg_replace('/[^a-zA-Z0-9_-]/', '', $id);
            return $id ?: '';
        }

        if ($host === 'youtu.be') {
            $id = trim($path, '/');
            $id = preg_replace('/[^a-zA-Z0-9_-]/', '', $id);
            return $id ?: '';
        }

        return '';
    }

    private static function youtube_embed_url(string $video_id): string
    {
        $video_id = preg_replace('/[^a-zA-Z0-9_-]/', '', $video_id);
        if ($video_id === '') {
            return '';
        }

        $origin_parts = wp_parse_url(home_url('/'));
        $origin = '';
        if (is_array($origin_parts)) {
            $scheme = isset($origin_parts['scheme']) ? (string) $origin_parts['scheme'] : 'https';
            $host = isset($origin_parts['host']) ? (string) $origin_parts['host'] : '';
            $port = isset($origin_parts['port']) ? (int) $origin_parts['port'] : 0;
            if ($host !== '') {
                $origin = $scheme . '://' . $host . ($port ? ':' . $port : '');
            }
        }

        $args = [
            'enablejsapi' => '1',
            'rel' => '0',
            'modestbranding' => '1',
        ];
        if ($origin !== '') {
            $args['origin'] = $origin;
        }

        return (string) add_query_arg($args, 'https://www.youtube.com/embed/' . $video_id);
    }

    public static function template_include(string $template): string
    {
        // For block themes, keep the theme's header/footer/template structure.
        if (self::is_block_theme()) {
            return $template;
        }

        if (is_singular('learni_course')) {
            $t = PL_LEARNI_PATH . 'templates/single-learni_course.php';
            if (file_exists($t)) {
                return $t;
            }
        }

        if (is_singular('learni_lesson')) {
            $t = PL_LEARNI_PATH . 'templates/single-learni_lesson.php';
            if (file_exists($t)) {
                return $t;
            }
        }

        return $template;
    }

    private static function binomial_course_state(int $course_id, int $user_id, int $lesson_percent): array
    {
        if ($course_id <= 0 || $user_id <= 0 || !class_exists('\\Learni\\Database\\Progress')) {
            return [
                'quizId' => 0,
                'needsInitial' => false,
                'needsFinal' => false,
                'canTakeFinal' => false,
                'initial' => null,
                'final' => null,
            ];
        }

        global $wpdb;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, lesson_post_id, settings_json
                 FROM {$wpdb->prefix}learni_quizzes
                 WHERE course_post_id = %d
                 ORDER BY id DESC",
                $course_id
            ),
            ARRAY_A
        );

        $quiz_id = 0;
        $fallback_id = 0;
        foreach ($rows as $row) {
            $settings = [];
            if (!empty($row['settings_json'])) {
                $decoded = json_decode((string) $row['settings_json'], true);
                if (is_array($decoded)) {
                    $settings = $decoded;
                }
            }
            if (isset($settings['role']) && (string) $settings['role'] === 'binomial') {
                $quiz_id = (int) ($row['id'] ?? 0);
                break;
            }
            if ($fallback_id <= 0 && empty($row['lesson_post_id'])) {
                $fallback_id = (int) ($row['id'] ?? 0);
            }
        }
        if ($quiz_id <= 0 && $fallback_id > 0) {
            $quiz_id = $fallback_id;
        }

        if ($quiz_id <= 0) {
            return [
                'quizId' => 0,
                'needsInitial' => false,
                'needsFinal' => false,
                'canTakeFinal' => false,
                'initial' => null,
                'final' => null,
            ];
        }

        $attempts_table = $wpdb->prefix . 'learni_quiz_attempts';
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, score, submitted_at, answers_json
                 FROM {$attempts_table}
                 WHERE quiz_id = %d AND user_id = %d AND status = %s
                 ORDER BY submitted_at ASC, id ASC
                 LIMIT 200",
                $quiz_id,
                $user_id,
                'submitted'
            ),
            ARRAY_A
        );

        $series = [];
        $idx = 0;
        foreach ((array) $rows as $r) {
            if (!is_array($r)) {
                continue;
            }
            $idx++;
            $payload = [];
            if (!empty($r['answers_json'])) {
                $decoded = json_decode((string) $r['answers_json'], true);
                if (is_array($decoded)) {
                    $payload = $decoded;
                }
            }
            $phase = '';
            if (isset($payload['phase']) && is_string($payload['phase'])) {
                $p = sanitize_key($payload['phase']);
                if (in_array($p, ['initial', 'final'], true)) {
                    $phase = $p;
                }
            }
            if ($phase === '') {
                $phase = ($idx % 2 === 1) ? 'initial' : 'final';
            }
            $a = self::attempt_public_payload($r);
            $a['phase'] = $phase;
            $series[] = $a;
        }

        $submitted_count = count($series);

        // Current cycle baseline: most recent initial; only finals after that count.
        $initial = null;
        $final = null;
        $finals_after_initial = [];
        foreach ($series as $a) {
            $p = isset($a['phase']) ? (string) $a['phase'] : '';
            if ($p === 'initial') {
                $initial = $a;
                $finals_after_initial = [];
                continue;
            }
            if ($p === 'final' && is_array($initial)) {
                $finals_after_initial[] = $a;
            }
        }
        if (!empty($finals_after_initial)) {
            $final = $finals_after_initial[count($finals_after_initial) - 1];
        }

        $eligible_final = false;
        $final_failed = false;
        $cooldown_until = '';
        $cooldown_days_remaining = 0;

        $baseline = is_array($initial) ? (int) ($initial['percent'] ?? 0) : null;
        if ($baseline !== null) {
            foreach ($finals_after_initial as $f) {
                $fp = (int) ($f['percent'] ?? 0);
                if ($fp >= $baseline) {
                    $eligible_final = true;
                    break;
                }
            }
        }

	        if (!$eligible_final && is_array($final) && $baseline !== null) {
	            $fp = (int) ($final['percent'] ?? 0);
	            if ($fp < $baseline) {
	                $final_failed = true;
	                $submitted_at = (string) ($final['submittedAt'] ?? '');
	                $ts = 0;
	                if ($submitted_at !== '') {
	                    $dt = date_create_immutable_from_format('Y-m-d H:i:s', $submitted_at, wp_timezone());
	                    if ($dt instanceof \DateTimeImmutable) {
	                        $ts = $dt->getTimestamp();
	                    } else {
	                        $ts = (int) strtotime($submitted_at);
	                    }
	                }
	                if ($ts > 0) {
	                    $cool_ts = $ts + (7 * DAY_IN_SECONDS);
	                    $cooldown_until = wp_date('Y-m-d H:i:s', $cool_ts, wp_timezone());
	                    $now = (int) current_time('timestamp');
	                    $diff = $cool_ts - $now;
	                    if ($diff > 0) {
	                        // Cooldown based on *full days completed* since the last failed final attempt.
	                        // Example: within the first 24h -> "7 días"; after 24h -> "6 días".
	                        $days_since = intdiv(max(0, $now - $ts), DAY_IN_SECONDS);
	                        $cooldown_days_remaining = (int) max(0, 7 - $days_since);
	                    }
	                }
	            }
	        }

        $needs_initial = !is_array($initial);
        $needs_final = is_array($initial) && !$eligible_final;
        $has_access = class_exists('\\Learni\\Access\\Access') && \Learni\Access\Access::user_can_access_course($user_id, $course_id);
        $can_take_final = $needs_final && $lesson_percent >= 100 && $has_access && $cooldown_days_remaining <= 0;

        return [
            'quizId' => $quiz_id,
            'submittedCount' => $submitted_count,
            'needsInitial' => $needs_initial,
            'needsFinal' => $needs_final,
            'canTakeFinal' => $can_take_final,
            'canRestart' => $eligible_final && $lesson_percent >= 100 && $has_access,
            'initial' => $initial,
            'final' => $final,
            'eligibleFinal' => $eligible_final,
            'finalFailed' => $final_failed,
            'cooldownUntil' => $cooldown_until,
            'cooldownDaysRemaining' => $cooldown_days_remaining,
        ];
    }

    private static function binomial_quiz_id_for_course(int $course_id): int
    {
        if ($course_id <= 0) {
            return 0;
        }

        global $wpdb;
        if (!$wpdb) {
            return 0;
        }

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, lesson_post_id, settings_json
                 FROM {$wpdb->prefix}learni_quizzes
                 WHERE course_post_id = %d
                 ORDER BY id DESC",
                $course_id
            ),
            ARRAY_A
        );
        if (empty($rows)) {
            return 0;
        }

        $quiz_id = 0;
        $fallback_id = 0;
        foreach ($rows as $row) {
            $settings = [];
            if (!empty($row['settings_json'])) {
                $decoded = json_decode((string) $row['settings_json'], true);
                if (is_array($decoded)) {
                    $settings = $decoded;
                }
            }
            if (isset($settings['role']) && (string) $settings['role'] === 'binomial') {
                $quiz_id = (int) ($row['id'] ?? 0);
                break;
            }
            if ($fallback_id <= 0 && empty($row['lesson_post_id'])) {
                $fallback_id = (int) ($row['id'] ?? 0);
            }
        }
        if ($quiz_id <= 0 && $fallback_id > 0) {
            $quiz_id = $fallback_id;
        }

        return $quiz_id > 0 ? $quiz_id : 0;
    }

    private static function attempt_public_payload(array $row): array
    {
        $payload = [];
        if (!empty($row['answers_json'])) {
            $decoded = json_decode((string) $row['answers_json'], true);
            if (is_array($decoded)) {
                $payload = $decoded;
            }
        }
        $total = isset($payload['total']) ? (int) $payload['total'] : 0;
        $score = isset($row['score']) ? (int) $row['score'] : 0;
        $percent = isset($payload['percent']) ? (int) $payload['percent'] : ($total > 0 ? (int) round(($score / $total) * 100) : 0);
        return [
            'attemptId' => (int) ($row['id'] ?? 0),
            'score' => $score,
            'total' => $total,
            'percent' => $percent,
            'submittedAt' => (string) ($row['submitted_at'] ?? ''),
        ];
    }

    private static function get_course_id_for_lesson(int $lesson_id): int
    {
        global $wpdb;
        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT course_post_id FROM {$wpdb->prefix}learni_course_items WHERE item_type = %s AND item_ref_id = %d LIMIT 1",
                'lesson',
                $lesson_id
            )
        );
    }
}
