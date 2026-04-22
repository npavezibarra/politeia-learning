<?php
/**
 * Frontend Certificates logic for Learni.
 */

if (!defined('ABSPATH')) {
    exit;
}

final class PL_Learni_Frontend_Certificates
{
    public static function template_exists(int $course_id): bool
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

    public static function view_url(int $course_id): string
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

    private static function paragraph_with_replacements(string $paragraph, int $course_id, int $user_id, string $issued_label): string
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

        if (!self::template_exists($course_id)) {
            wp_safe_redirect((string) get_permalink($course_id));
            exit;
        }

        $data = self::get_data($course_id, $user_id);
        $title = $data['title'] ?? __('Certificado de Finalización', 'politeia-learning');
        $sheet = self::render_sheet_html($data);

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

    public static function get_data(int $course_id, int $user_id): array
    {
        $summary = class_exists('\\Learni\\Database\\Progress') ? \Learni\Database\Progress::course_summary($user_id, $course_id) : ['percent' => 0];
        $percent = (int) ($summary['percent'] ?? 0);
        $eligible = $percent >= 100;

        $title = (string) get_post_meta($course_id, \Learni\PostTypes\Course::META_CERTIFICATE_TITLE, true);
        $title = $title !== '' ? $title : __('Certificado de Finalización', 'politeia-learning');

        $paragraph = (string) get_post_meta($course_id, \Learni\PostTypes\Course::META_CERTIFICATE_CONGRATS, true);
        $issued_label = wp_date(get_option('date_format'));
        $paragraph = self::paragraph_with_replacements($paragraph, $course_id, $user_id, $issued_label);

        $logo_id = (int) get_post_meta($course_id, \Learni\PostTypes\Course::META_CERTIFICATE_LOGO_ATTACHMENT_ID, true);
        $logo_url = $logo_id > 0 ? (string) wp_get_attachment_image_url($logo_id, 'medium') : '';
        $logo_align = (string) get_post_meta($course_id, \Learni\PostTypes\Course::META_CERTIFICATE_LOGO_ALIGN, true);
        $logo_align = in_array($logo_align, ['left', 'center', 'right'], true) ? $logo_align : 'left';
        $logo_justify = $logo_align === 'right' ? 'flex-end' : ($logo_align === 'center' ? 'center' : 'flex-start');

        $sig_id = (int) get_post_meta($course_id, \Learni\PostTypes\Course::META_CERTIFICATE_SIGNATURE_ATTACHMENT_ID, true);
        $sig_url = $sig_id > 0 ? (string) wp_get_attachment_image_url($sig_id, 'medium') : '';
        $sig_label = (string) get_post_meta($course_id, \Learni\PostTypes\Course::META_CERTIFICATE_SIGNATURE_LABEL, true);
        $sig_label = $sig_label !== '' ? $sig_label : __('Firma', 'politeia-learning');

        $binomial = PL_Learni_Frontend_Assessment::binomial_course_state($course_id, $user_id, $percent);
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

    public static function render_sheet_html(array $data): string
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

    public static function render_modal_html(int $course_id, int $user_id): string
    {
        $html = '<div id="learni-cert-modal" class="learni-cert-modal" aria-hidden="true">';
        $html .= '<div class="learni-cert-modal__backdrop" data-learni-cert-close="1"></div>';
        $html .= '<div class="learni-cert-modal__panel" role="dialog" aria-modal="true" aria-label="' . esc_attr__('Certificado', 'politeia-learning') . '">';
        $html .= '<div class="learni-cert-modal__head">';
        $html .= '<div class="learni-cert-modal__title">' . esc_html__('Certificado', 'politeia-learning') . '</div>';
        $html .= '<div class="learni-cert-modal__actions">';
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
}
