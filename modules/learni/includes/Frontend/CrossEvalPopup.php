<?php
/**
 * Global "accept cross evaluation now" popup for Learni.
 *
 * This is intentionally loaded across the site (when logged in) so the partner
 * can receive the prompt from anywhere.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PL_Learni_Cross_Eval_Popup
{
    private static bool $inited = false;

    private static function is_relevant_page(): bool
    {
        return is_singular('learni_course') || is_singular('learni_lesson');
    }

    public static function init(): void
    {
        if (self::$inited) {
            return;
        }
        self::$inited = true;

        if (!is_user_logged_in()) {
            return;
        }

        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets'], 30);
    }

    public static function enqueue_assets(): void
    {
        if (!is_user_logged_in()) {
            return;
        }

        // Load the Learni quiz overlay globally so the tested partner can see results from anywhere.
        // This is safe because scripts/CSS are scoped to `.learni-*` classes and no-op on non-course pages.
        wp_enqueue_style(
            'pl-learni-material-symbols',
            'https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=block',
            [],
            null
        );

        $learner_css_path = PL_LEARNI_PATH . 'assets/learner.css';
        wp_enqueue_style(
            'pl-learni-learner',
            PL_LEARNI_URL . 'assets/learner.css',
            ['pl-learni-material-symbols'],
            file_exists($learner_css_path) ? (string) filemtime($learner_css_path) : (defined('LEARNI_VERSION') ? (string) LEARNI_VERSION : '0.0.0')
        );

        $learner_quiz_js_path = PL_LEARNI_PATH . 'assets/learner-quiz.js';
        wp_enqueue_script(
            'pl-learni-quiz',
            PL_LEARNI_URL . 'assets/learner-quiz.js',
            [],
            file_exists($learner_quiz_js_path) ? (string) filemtime($learner_quiz_js_path) : (defined('LEARNI_VERSION') ? (string) LEARNI_VERSION : '0.0.0'),
            true
        );

        wp_add_inline_script(
            'pl-learni-quiz',
            '(function(){' .
                'var __plLearniBase=' . wp_json_encode([
                    'restUrl' => esc_url_raw(rest_url()),
                    'restNonce' => wp_create_nonce('wp_rest'),
                    'authBaseUrl' => esc_url_raw(home_url('/')),
                    'isLoggedIn' => true,
                    'loginUrl' => function_exists('wc_get_page_permalink') ? (string) wc_get_page_permalink('myaccount') : wp_login_url(),
                ]) . ';' .
                'var __plLearniI18n=' . wp_json_encode([
                    'close' => __('Cerrar', 'politeia-learning'),
                    'loading' => __('Cargando…', 'politeia-learning'),
                    'quizInitialKicker' => __('Evaluación inicial', 'politeia-learning'),
                    'quizFinalKicker' => __('Evaluación final', 'politeia-learning'),
                    'quizCrossKicker' => __('Test Partner', 'politeia-learning'),
                    'quizCrossDoneTitle' => __('Listo', 'politeia-learning'),
                    'quizCrossDoneClose' => __('Cerrar', 'politeia-learning'),
                    'quizCrossResultsBody' => __('Terminaste la Evaluación Final. Estos fueron los resultados:', 'politeia-learning'),
                    'quizCrossResultsClose' => __('Cerrar', 'politeia-learning'),
                ]) . ';' .
                'var prev=(window.Learni||{});' .
                'var prevI18n=(prev&&prev.i18n)?prev.i18n:{};' .
                'window.Learni=Object.assign({}, prev, __plLearniBase, {i18n:Object.assign({}, prevI18n, __plLearniI18n)});' .
            '})();',
            'before'
        );

        $css_path = PL_LEARNI_PATH . 'assets/learner-cross-eval-popup.css';
        $js_path = PL_LEARNI_PATH . 'assets/learner-cross-eval-popup.js';

        wp_enqueue_style(
            'pl-learni-cross-eval-popup',
            PL_LEARNI_URL . 'assets/learner-cross-eval-popup.css',
            [],
            file_exists($css_path) ? (string) filemtime($css_path) : (defined('LEARNI_VERSION') ? (string) LEARNI_VERSION : '0.0.0')
        );

        wp_enqueue_script(
            'pl-learni-cross-eval-popup',
            PL_LEARNI_URL . 'assets/learner-cross-eval-popup.js',
            [],
            file_exists($js_path) ? (string) filemtime($js_path) : (defined('LEARNI_VERSION') ? (string) LEARNI_VERSION : '0.0.0'),
            true
        );

        wp_add_inline_script(
            'pl-learni-cross-eval-popup',
            'window.LearniCrossEval = Object.assign({}, window.LearniCrossEval || {}, ' . wp_json_encode([
                'restUrl' => esc_url_raw(rest_url()),
                'restNonce' => wp_create_nonce('wp_rest'),
                'polling' => [
                    'fastMs' => 3500,
                    'watchMs' => 10000,
                    'idleMs' => 60000,
                    'maxMs' => 300000,
                    'relevantPage' => self::is_relevant_page(),
                    'pauseWhenHidden' => true,
                ],
                'i18n' => [
                    'title' => __('Test Partner', 'politeia-learning'),
                    'online' => __('{name} está online', 'politeia-learning'),
                    'question' => __('¿Aceptas tomar la Evaluación Final de {course} ahora?', 'politeia-learning'),
                    'resultsTitle' => __('Listo', 'politeia-learning'),
                    'accept' => __('Aceptar', 'politeia-learning'),
                    'decline' => __('Rechazar', 'politeia-learning'),
                    'busy' => __('Cargando…', 'politeia-learning'),
                ],
            ]) . ');',
            'before'
        );
    }
}
