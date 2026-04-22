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
        add_action('admin_post_pl_learni_enroll_course', ['PL_Learni_Frontend_Actions', 'handle_enroll_course']);
        add_action('admin_post_nopriv_pl_learni_enroll_course', ['PL_Learni_Frontend_Actions', 'handle_enroll_course_nopriv']);
        add_action('admin_post_pl_learni_mark_lesson_complete', ['PL_Learni_Frontend_Actions', 'handle_mark_lesson_complete']);
        add_action('admin_post_nopriv_pl_learni_mark_lesson_complete', ['PL_Learni_Frontend_Actions', 'handle_mark_lesson_complete_nopriv']);
        add_action('admin_post_pl_learni_checkout_course', ['PL_Learni_Frontend_Actions', 'handle_checkout_course']);
        add_action('admin_post_nopriv_pl_learni_checkout_course', ['PL_Learni_Frontend_Actions', 'handle_checkout_course_nopriv']);
        add_action('admin_post_pl_learni_view_certificate', ['PL_Learni_Frontend_Certificates', 'handle_view_certificate']);
        add_action('admin_post_nopriv_pl_learni_view_certificate', ['PL_Learni_Frontend_Certificates', 'handle_view_certificate_nopriv']);

        if (!self::$did_register_content_filter) {
            add_filter('the_content', [__CLASS__, 'render_block_theme_content'], 99);
            self::$did_register_content_filter = true;
        }
    }

    public static function checkout_course_url(int $course_id): string
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

    private static function is_block_theme(): bool
    {
        return function_exists('wp_is_block_theme') && wp_is_block_theme();
    }

    public static function filter_theme_blocks(string $block_content, array $block): string
    {
        if (!self::is_block_theme()) {
            return $block_content;
        }

        if (!is_singular('learni_course') && !is_singular('learni_lesson')) {
            return $block_content;
        }

        $name = (string) ($block['blockName'] ?? '');
        if ($name === 'core/post-featured-image') {
            return '';
        }

        if (is_singular('learni_lesson') && $name === 'core/template-part') {
            $attrs = (array) ($block['attrs'] ?? []);
            $slug = (string) ($attrs['slug'] ?? '');
            if ($slug === 'footer') {
                return '';
            }
        }

        if ($name === 'core/group') {
            $maybe_stripped = self::strip_empty_course_padding_group($block_content);
            if ($maybe_stripped !== $block_content) {
                return $maybe_stripped;
            }

            if (preg_match('/<p>\\s*Escrito\\s+por\\s*<\\/p>/i', $block_content) && preg_match('/<p>\\s*en\\s*<\\/p>/i', $block_content)) {
                return '';
            }
        }

        return $block_content;
    }

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

        wp_enqueue_style(
            'pl-learni-material-symbols',
            'https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=swap',
            [],
            null
        );

        wp_enqueue_style(
            'pl-learni-learner',
            PL_LEARNI_URL . 'assets/learner.css',
            ['pl-learni-material-symbols'],
            defined('LEARNI_VERSION') ? (string) LEARNI_VERSION : '0.0.0'
        );

        if (is_singular('learni_lesson') && defined('PL_CC_URL') && defined('PL_CC_PATH')) {
            $lesson_id = (int) get_queried_object_id();
            $src_meta_key = class_exists('\\Learni\\PostTypes\\Lesson') ? \Learni\PostTypes\Lesson::META_SOURCE_POST_ID : 'learni_source_post_id';
            $src_post_id = $lesson_id > 0 ? (int) get_post_meta($lesson_id, $src_meta_key, true) : 0;
            if ($src_post_id > 0) {
                $frontend_css_path = PL_CC_PATH . 'assets/css/escrito-frontend.css';
                $frontend_css_ver = file_exists($frontend_css_path) ? (string) filemtime($frontend_css_path) : '1.0.0';
                wp_enqueue_style('pcg-escrito-frontend-css', PL_CC_URL . 'assets/css/escrito-frontend.css', [], $frontend_css_ver);
            }
        }

        wp_enqueue_script('pl-learni-quiz-utils', PL_LEARNI_URL . 'assets/quiz-parts/quiz-utils.js', [], defined('LEARNI_VERSION') ? (string) LEARNI_VERSION : '0.0.0', true);
        wp_enqueue_script('pl-learni-quiz-ui', PL_LEARNI_URL . 'assets/quiz-parts/quiz-ui-modals.js', ['pl-learni-quiz-utils'], defined('LEARNI_VERSION') ? (string) LEARNI_VERSION : '0.0.0', true);
        wp_enqueue_script('pl-learni-quiz-binomial', PL_LEARNI_URL . 'assets/quiz-parts/quiz-binomial-logic.js', ['pl-learni-quiz-utils', 'pl-learni-quiz-ui'], defined('LEARNI_VERSION') ? (string) LEARNI_VERSION : '0.0.0', true);
        wp_enqueue_script('pl-learni-quiz-certs', PL_LEARNI_URL . 'assets/quiz-parts/quiz-certificates.js', ['pl-learni-quiz-utils', 'pl-learni-quiz-ui'], defined('LEARNI_VERSION') ? (string) LEARNI_VERSION : '0.0.0', true);
        wp_enqueue_script('pl-learni-quiz-cross', PL_LEARNI_URL . 'assets/quiz-parts/quiz-cross-eval.js', ['pl-learni-quiz-utils', 'pl-learni-quiz-ui', 'pl-learni-quiz-binomial'], defined('LEARNI_VERSION') ? (string) LEARNI_VERSION : '0.0.0', true);
        wp_enqueue_script('pl-learni-quiz-sidebars', PL_LEARNI_URL . 'assets/quiz-parts/quiz-sidebars.js', ['pl-learni-quiz-utils', 'pl-learni-quiz-ui', 'pl-learni-quiz-binomial', 'pl-learni-quiz-certs', 'pl-learni-quiz-cross'], defined('LEARNI_VERSION') ? (string) LEARNI_VERSION : '0.0.0', true);
        wp_enqueue_script('pl-learni-quiz-auth', PL_LEARNI_URL . 'assets/quiz-parts/quiz-auth.js', ['pl-learni-quiz-utils', 'pl-learni-quiz-ui'], defined('LEARNI_VERSION') ? (string) LEARNI_VERSION : '0.0.0', true);
        wp_enqueue_script('pl-learni-quiz', PL_LEARNI_URL . 'assets/learner-quiz.js', ['pl-learni-quiz-utils', 'pl-learni-quiz-ui', 'pl-learni-quiz-binomial', 'pl-learni-quiz-certs', 'pl-learni-quiz-cross', 'pl-learni-quiz-sidebars', 'pl-learni-quiz-auth'], defined('LEARNI_VERSION') ? (string) LEARNI_VERSION : '0.0.0', true);

        wp_add_inline_script('pl-learni-quiz', 'window.Learni = Object.assign({}, window.Learni || {}, ' . wp_json_encode([
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
        ]) . ');', 'before');

        if (is_singular('learni_lesson')) {
            wp_enqueue_script('pl-learni-lesson-outline', PL_LEARNI_URL . 'assets/learner-lesson-outline.js', [], defined('LEARNI_VERSION') ? (string) LEARNI_VERSION : '0.0.0', true);
        }

        if (self::is_block_theme() && is_singular('learni_course')) {
            wp_enqueue_script('pl-learni-tabs', PL_LEARNI_URL . 'assets/learner-tabs.js', [], defined('LEARNI_VERSION') ? (string) LEARNI_VERSION : '0.0.0', true);
        }
    }

    public static function render_block_theme_content(string $content): string
    {
        if (is_admin() || !self::is_block_theme()) {
            return $content;
        }

        remove_filter('the_content', [__CLASS__, __FUNCTION__], 99);
        $out = $content;
        try {
            if (is_singular('learni_course')) {
                $out = PL_Learni_Frontend_ViewCourse::render();
            } elseif (is_singular('learni_lesson')) {
                $out = PL_Learni_Frontend_ViewLesson::render();
            }
        } catch (\Throwable $e) {
            $out = $content;
        }
        add_filter('the_content', [__CLASS__, __FUNCTION__], 99);

        return $out;
    }

    public static function course_linear_order_enabled(int $course_id): bool
    {
        if ($course_id <= 0 || !class_exists('\\Learni\\PostTypes\\Course')) {
            return true;
        }
        $meta_key = \Learni\PostTypes\Course::META_LINEAR_ORDER;
        $exists = metadata_exists('post', $course_id, $meta_key);
        $raw = get_post_meta($course_id, $meta_key, true);

        if (!$exists) {
            return true;
        }
        if ($raw === '' || $raw === false || $raw === 0 || $raw === '0') {
            return false;
        }
        return is_bool($raw) ? $raw : (bool) (int) $raw;
    }

    public static function lesson_index_map(array $lesson_ids): array
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

    public static function max_unlocked_lesson_index(array $lesson_ids, array $completed_map, bool $linear_order): int
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

    public static function template_include(string $template): string
    {
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
}
