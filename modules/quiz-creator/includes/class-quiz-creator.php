<?php
/**
 * Quiz Creator Class
 * Handles the creation of LearnDash quizzes from structured data
 */

if (!defined('ABSPATH')) {
    exit;
}

class PQC_Quiz_Creator
{

    /**
     * Create a LearnDash quiz from structured data
     * 
     * @param array $quiz_data Structured quiz data
     * @return array|WP_Error Result with quiz IDs or error
     */
    public static function create_quiz($quiz_data)
    {
        global $wpdb;

        // Validate data
        $validation = self::validate_quiz_data($quiz_data);
        if (is_wp_error($validation)) {
            return $validation;
        }

        // Create WordPress post
        $quiz_post_id = wp_insert_post([
            'post_type' => 'sfwd-quiz',
            'post_title' => sanitize_text_field($quiz_data['title']),
            'post_status' => 'publish',
            'post_content' => '',
            'post_author' => get_current_user_id(),
        ]);

        if (is_wp_error($quiz_post_id)) {
            return $quiz_post_id;
        }

        // Prepare quiz settings with defaults
        $settings = wp_parse_args($quiz_data['settings'] ?? [], [
            'time_limit' => 0,
            'random_questions' => 0,
            'random_answers' => 0,
            'run_once' => 0,
            'run_once_type' => 0,
            'show_points' => 0,
            'force_solve' => 0,
            'description' => '',
        ]);

        // Create pro quiz master entry
        $wpdb->insert(
            $wpdb->prefix . 'learndash_pro_quiz_master',
            [
                'name' => sanitize_text_field($quiz_data['title']),
                'text' => wp_kses_post($settings['description']),
                'result_text' => serialize([
                    'text' => [''],
                    'prozent' => [0],
                    'activ' => [1]
                ]),
                'result_grade_enabled' => 1,
                'title_hidden' => 1,
                'btn_restart_quiz_hidden' => 0,
                'btn_view_question_hidden' => 0,
                'question_random' => intval($settings['random_questions']),
                'answer_random' => intval($settings['random_answers']),
                'time_limit' => intval($settings['time_limit']),
                'statistics_on' => 1,
                'statistics_ip_lock' => 0,
                'show_points' => intval($settings['show_points']),
                'quiz_run_once' => intval($settings['run_once']),
                'quiz_run_once_type' => intval($settings['run_once_type']),
                'quiz_run_once_cookie' => 0,
                'quiz_run_once_time' => 0,
                'numbered_answer' => 0,
                'hide_answer_message_box' => 0,
                'disabled_answer_mark' => 0,
                'show_max_question' => 0,
                'show_max_question_value' => 0,
                'show_max_question_percent' => 0,
                'toplist_activated' => 0,
                'toplist_data' => serialize([
                    'toplistDataAddPermissions' => 1,
                    'toplistDataSort' => 1,
                    'toplistDataAddMultiple' => false,
                    'toplistDataCaptcha' => false,
                    'toplistDataAddAutomatic' => false,
                    'toplistDataShowLimit' => 0,
                    'toplistDataShowIn' => 0,
                    'toplistDataDateFormat' => 'Y/m/d g:i A'
                ]),
                'show_average_result' => 0,
                'prerequisite' => 0,
                'quiz_modus' => 0,
                'show_review_question' => 0,
                'quiz_summary_hide' => 1,
                'skip_question_disabled' => 1,
                'email_notification' => 0,
                'user_email_notification' => 0,
                'show_category_score' => 0,
                'hide_result_correct_question' => 0,
                'hide_result_quiz_time' => 0,
                'hide_result_points' => 0,
                'autostart' => 0,
                'forcing_question_solve' => intval($settings['force_solve']),
                'hide_question_position_overview' => 1,
                'hide_question_numbering' => 1,
                'form_activated' => 0,
                'form_show_position' => 0,
                'start_only_registered_user' => 0,
                'questions_per_page' => 0,
                'sort_categories' => 0,
                'show_category' => 0,
            ]
        );

        $quiz_pro_id = $wpdb->insert_id;

        if (!$quiz_pro_id) {
            wp_delete_post($quiz_post_id, true);
            return new WP_Error('quiz_creation_failed', __('Failed to create quiz in database.', 'politeia-quiz-creator'));
        }

        // Link them with postmeta
        update_post_meta($quiz_post_id, 'quiz_pro_id', $quiz_pro_id);
        update_post_meta($quiz_post_id, 'quiz_pro_id_' . $quiz_pro_id, $quiz_pro_id);
        update_post_meta($quiz_post_id, 'quiz_pro_primary_' . $quiz_pro_id, 1);
        update_post_meta($quiz_post_id, 'ld_quiz_questions', []);
        update_post_meta($quiz_post_id, '_timeLimitCookie', 0);
        update_post_meta($quiz_post_id, '_viewProfileStatistics', 1);
        update_post_meta($quiz_post_id, '_ld_certificate', '');
        update_post_meta($quiz_post_id, '_ld_certificate_threshold', '');
        update_post_meta($quiz_post_id, '_ld_course_steps_count', 0);



        // Create _sfwd-quiz settings
        $sfwd_settings = self::create_sfwd_quiz_settings($quiz_data, $quiz_pro_id);
        update_post_meta($quiz_post_id, '_sfwd-quiz', $sfwd_settings);

        // Link quiz to course if course_id is provided
        $course_id = intval($settings['course_id'] ?? 0);
        if ($course_id > 0) {
            update_post_meta($course_id, '_first_quiz_id', $quiz_post_id);
            update_post_meta($course_id, '_final_quiz_id', $quiz_post_id);
            // Also update the quiz-course association in LD meta if needed (already handled in create_sfwd_quiz_settings)
        }

        // Add questions
        $question_results = [];
        $builder_questions = [];
        if (!empty($quiz_data['questions']) && is_array($quiz_data['questions'])) {
            foreach ($quiz_data['questions'] as $index => $question_data) {
                $result = self::add_question_to_quiz(
                    ['quiz_post_id' => $quiz_post_id, 'quiz_pro_id' => $quiz_pro_id],
                    $question_data,
                    $index + 1
                );

                if (is_wp_error($result)) {
                    $question_results[] = [
                        'success' => false,
                        'error' => $result->get_error_message()
                    ];
                } else {
                    $q_id = $result['question_post_id'];
                    $q_pro_id = $result['question_pro_id'];
                    $question_results[] = [
                        'success' => true,
                        'question_id' => $q_id
                    ];
                    $builder_questions[$q_id] = $q_pro_id;
                }
            }
        }

        // Update course steps for the Builder to show the correct questions
        if (!empty($builder_questions)) {
            update_post_meta($quiz_post_id, 'ld_course_steps', [
                'steps' => [
                    'h' => [
                        'sfwd-lessons' => [],
                        'sfwd-quiz' => [],
                    ]
                ],
                'course_id' => $quiz_post_id,
                'version' => '4.23.0',
                'empty' => false,
                'course_builder_enabled' => true,
                'course_shared_steps_enabled' => true,
                'steps_count' => count($builder_questions),
            ]);
            update_post_meta($quiz_post_id, '_ld_course_steps_count', count($builder_questions));
        }

        // Set dirty flag to force LD Builder to re-sync
        update_post_meta($quiz_post_id, 'ld_quiz_questions_dirty', $quiz_post_id);
        update_post_meta($quiz_post_id, 'quiz_pro_primary_' . $quiz_pro_id, 1);

        return [
            'success' => true,
            'quiz_post_id' => $quiz_post_id,
            'quiz_pro_id' => $quiz_pro_id,
            'quiz_url' => get_permalink($quiz_post_id),
            'edit_url' => get_edit_post_link($quiz_post_id, 'raw'),
            'questions' => $question_results,
        ];
    }

    /**
     * Add a question to a quiz
     */
    private static function add_question_to_quiz($quiz_ids, $question_data, $sort_order)
    {
        global $wpdb;

        $quiz_post_id = $quiz_ids['quiz_post_id'];
        $quiz_pro_id = $quiz_ids['quiz_pro_id'];

        // Create question post
        $question_post_id = wp_insert_post([
            'post_type' => 'sfwd-question',
            'post_title' => sanitize_text_field($question_data['title']),
            'post_status' => 'publish',
            'post_content' => '',
            'post_author' => get_current_user_id(),
            'post_parent' => $quiz_post_id, // Link question to quiz
        ]);

        if (is_wp_error($question_post_id)) {
            return $question_post_id;
        }

        // Prepare answers
        $answers = [];
        if (!empty($question_data['answers']) && is_array($question_data['answers'])) {
            foreach ($question_data['answers'] as $answer) {
                $answer_obj = new WpProQuiz_Model_AnswerTypes();
                $answer_obj->setAnswer(sanitize_text_field($answer['text']));
                $answer_obj->setCorrect(!empty($answer['correct']));
                $answer_obj->setPoints(intval($answer['points'] ?? 0));
                $answers[] = $answer_obj;
            }
        }

        // Create pro question entry
        $wpdb->insert(
            $wpdb->prefix . 'learndash_pro_quiz_question',
            [
                'quiz_id' => $quiz_pro_id,
                'online' => 1,
                'previous_id' => 0,
                'sort' => $sort_order,
                'title' => sanitize_text_field($question_data['title']),
                'points' => intval($question_data['points'] ?? 5),
                'question' => '<p>' . wp_kses_post($question_data['question_text']) . '</p>',
                'correct_msg' => '',
                'incorrect_msg' => '',
                'correct_same_text' => 0,
                'tip_enabled' => 0,
                'tip_msg' => '',
                'answer_type' => sanitize_text_field($question_data['answer_type'] ?? 'single'),
                'show_points_in_box' => 0,
                'answer_points_activated' => 0,
                'answer_data' => serialize($answers),
                'category_id' => 0,
                'answer_points_diff_modus_activated' => 0,
                'disable_correct' => 0,
                'matrix_sort_answer_criteria_width' => 20,
            ]
        );

        $question_pro_id = $wpdb->insert_id;

        if (!$question_pro_id) {
            wp_delete_post($question_post_id, true);
            return new WP_Error('question_creation_failed', __('Failed to create question in database.', 'politeia-quiz-creator'));
        }

        // Link question to quiz in postmeta
        update_post_meta($question_post_id, 'quiz_id', $quiz_post_id);
        update_post_meta($question_post_id, 'course_id', $quiz_post_id); // Add this
        update_post_meta($question_post_id, 'question_pro_id', $question_pro_id);
        update_post_meta($question_post_id, 'question_type', sanitize_text_field($question_data['answer_type'] ?? 'single'));
        update_post_meta($question_post_id, 'question_points', intval($question_data['points'] ?? 5));
        update_post_meta($question_post_id, 'question_pro_category', 0);

        // Add sfwd-question settings (important: include index 0 for LD compatibility)
        update_post_meta($question_post_id, '_sfwd-question', [
            0 => '',
            'sfwd-question_quiz' => $quiz_post_id
        ]);

        // Update quiz's question list
        $quiz_questions = get_post_meta($quiz_post_id, 'ld_quiz_questions', true);
        if (!is_array($quiz_questions)) {
            $quiz_questions = [];
        }
        $quiz_questions[$question_post_id] = $question_pro_id;
        update_post_meta($quiz_post_id, 'ld_quiz_questions', $quiz_questions);

        return [
            'question_post_id' => $question_post_id,
            'question_pro_id' => $question_pro_id
        ];
    }

    /**
     * Create _sfwd-quiz settings array
     */
    private static function create_sfwd_quiz_settings($quiz_data, $quiz_pro_id)
    {
        $settings = $quiz_data['settings'] ?? [];

        return [
            0 => '',
            'sfwd-quiz_quiz_pro' => $quiz_pro_id,
            'sfwd-quiz_course_short_description' => '',
            'sfwd-quiz_lesson_schedule' => '',
            'sfwd-quiz_visible_after' => '',
            'sfwd-quiz_visible_after_specific_date' => '',
            'sfwd-quiz_course' => $settings['course_id'] ?? '',
            'sfwd-quiz_lesson' => $settings['lesson_id'] ?? '',
            'sfwd-quiz_certificate' => '',
            'sfwd-quiz_threshold' => '',
            'sfwd-quiz_passingpercentage' => intval($settings['passing_percentage'] ?? 80),
            'sfwd-quiz_quiz_materials' => '',
            'sfwd-quiz_repeats' => '',
            'sfwd-quiz_quiz_materials_enabled' => 'off',
        ];
    }

    /**
     * Validate quiz data structure
     */
    private static function validate_quiz_data($data)
    {
        if (empty($data['title'])) {
            return new WP_Error('missing_title', __('Quiz title is required.', 'politeia-quiz-creator'));
        }

        if (empty($data['questions']) || !is_array($data['questions'])) {
            return new WP_Error('missing_questions', __('Quiz must have at least one question.', 'politeia-quiz-creator'));
        }

        foreach ($data['questions'] as $index => $question) {
            if (empty($question['title'])) {
                return new WP_Error('missing_question_title', sprintf(__('Question #%d is missing a title.', 'politeia-quiz-creator'), $index + 1));
            }

            if (empty($question['answers']) || !is_array($question['answers'])) {
                return new WP_Error('missing_answers', sprintf(__('Question #%d must have at least one answer.', 'politeia-quiz-creator'), $index + 1));
            }

            // Check if at least one answer is marked as correct
            $has_correct = false;
            foreach ($question['answers'] as $answer) {
                if (!empty($answer['correct'])) {
                    $has_correct = true;
                    break;
                }
            }

            if (!$has_correct) {
                return new WP_Error('no_correct_answer', sprintf(__('Question #%d must have at least one correct answer.', 'politeia-quiz-creator'), $index + 1));
            }
        }

        return true;
    }

    /**
     * Get quiz data for editor
     */
    public static function get_quiz_data($quiz_id)
    {
        global $wpdb;

        $quiz_post = get_post($quiz_id);
        if (!$quiz_post)
            return null;

        $quiz_questions_meta = get_post_meta($quiz_id, 'ld_quiz_questions', true);
        if (!is_array($quiz_questions_meta)) {
            // Fallback to post_parent if meta is missing
            $question_posts = get_posts([
                'post_type' => 'sfwd-question',
                'post_parent' => $quiz_id,
                'posts_per_page' => -1,
                'orderby' => 'ID',
                'order' => 'ASC'
            ]);
            $quiz_questions_meta = [];
            foreach ($question_posts as $q_post) {
                $pro_id = get_post_meta($q_post->ID, 'question_pro_id', true);
                $quiz_questions_meta[$q_post->ID] = $pro_id;
            }
        }

        $questions = [];
        foreach ($quiz_questions_meta as $q_post_id => $q_pro_id) {
            $q_post = get_post($q_post_id);
            if (!$q_post)
                continue;

            $pro_data = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}learndash_pro_quiz_question WHERE id = %d",
                $q_pro_id
            ), ARRAY_A);

            if ($pro_data) {
                // Ensure the ProQuiz classes are loaded if possible
                if (!class_exists('WpProQuiz_Model_AnswerTypes') && defined('WPPROQUIZ_PATH')) {
                    require_once WPPROQUIZ_PATH . '/lib/model/WpProQuiz_Model_AnswerTypes.php';
                }

                $answer_data = unserialize($pro_data['answer_data']);
                $answers = [];
                if (is_array($answer_data)) {
                    foreach ($answer_data as $ans) {
                        if (is_object($ans)) {
                            $answers[] = [
                                'text' => method_exists($ans, 'getAnswer') ? $ans->getAnswer() : (isset($ans->_answer) ? $ans->_answer : ''),
                                'correct' => method_exists($ans, 'isCorrect') ? $ans->isCorrect() : (isset($ans->_correct) ? $ans->_correct : false),
                                'points' => method_exists($ans, 'getPoints') ? $ans->getPoints() : (isset($ans->_points) ? $ans->_points : 0)
                            ];
                        } else if (is_array($ans)) {
                            $answers[] = $ans;
                        }
                    }
                }

                $questions[] = [
                    'id' => $q_post_id,
                    'pro_id' => $q_pro_id,
                    'title' => $q_post->post_title,
                    'question_text' => wp_kses_post($pro_data['question']),
                    'answer_type' => $pro_data['answer_type'],
                    'points' => $pro_data['points'],
                    'answers' => $answers
                ];
            }
        }

        return [
            'id' => $quiz_id,
            'title' => $quiz_post->post_title,
            'questions' => $questions
        ];
    }

    /**
     * Get the quiz ID linked to a course
     */
    public static function get_quiz_id_by_course($course_id)
    {
        if (!$course_id)
            return 0;

        // Check LearnDash standard meta first
        $quiz_id = get_post_meta($course_id, '_final_quiz_id', true);
        if ($quiz_id && get_post_type($quiz_id) === 'sfwd-quiz') {
            return intval($quiz_id);
        }

        // Alternative check: quizzes belonging to this course
        $quizzes = get_posts([
            'post_type' => 'sfwd-quiz',
            'meta_query' => [
                [
                    'key' => 'course_id',
                    'value' => $course_id
                ]
            ],
            'posts_per_page' => 1,
            'fields' => 'ids'
        ]);

        if (!empty($quizzes)) {
            return intval($quizzes[0]);
        }

        // Try LD core meta
        $sfwd_quizzes = get_posts([
            'post_type' => 'sfwd-quiz',
            'posts_per_page' => -1,
            'fields' => 'ids'
        ]);

        foreach ($sfwd_quizzes as $sq_id) {
            $meta = get_post_meta($sq_id, '_sfwd-quiz', true);
            if (isset($meta['sfwd-quiz_course']) && intval($meta['sfwd-quiz_course']) === intval($course_id)) {
                return intval($sq_id);
            }
        }

        return 0;
    }

    /**
     * Delete a quiz and its questions
     */
    public static function delete_quiz($quiz_id)
    {
        if (get_post_type($quiz_id) !== 'sfwd-quiz')
            return false;

        // Get and delete questions
        $questions = get_post_meta($quiz_id, 'ld_quiz_questions', true);
        if (is_array($questions)) {
            foreach ($questions as $q_post_id => $q_pro_id) {
                // Delete from DB ProQuiz table directly is handled by LD usually, 
                // but we should delete the post at least
                wp_delete_post($q_post_id, true);
            }
        }

        // Delete from ProQuiz Master table
        global $wpdb;
        $pro_id = get_post_meta($quiz_id, 'quiz_pro_id', true);
        if ($pro_id) {
            $wpdb->delete($wpdb->prefix . 'learndash_pro_quiz_master', ['id' => $pro_id]);
            $wpdb->delete($wpdb->prefix . 'learndash_pro_quiz_question', ['quiz_id' => $pro_id]);
        }

        // Clear associations in courses
        $courses = get_posts([
            'post_type' => 'sfwd-courses',
            'meta_query' => [
                'relation' => 'OR',
                [
                    'key' => '_first_quiz_id',
                    'value' => $quiz_id
                ],
                [
                    'key' => '_final_quiz_id',
                    'value' => $quiz_id
                ]
            ],
            'posts_per_page' => -1,
            'fields' => 'ids'
        ]);

        foreach ($courses as $c_id) {
            delete_post_meta($c_id, '_first_quiz_id');
            delete_post_meta($c_id, '_final_quiz_id');
        }

        // Finally delete the quiz post
        wp_delete_post($quiz_id, true);

        return true;
    }
}
