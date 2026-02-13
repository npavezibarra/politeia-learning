<?php
/**
 * File Parser Class
 * Parses different file formats (JSON, CSV, XML, TXT) into questions array
 * Settings are now handled separately in the dashboard form
 */

if (!defined('ABSPATH')) {
    exit;
}

class PQC_File_Parser
{

    /**
     * Parse uploaded file - returns questions array only
     * 
     * @param string $file_path Path to uploaded file
     * @param string $file_type File extension
     * @return array|WP_Error Array of questions or error
     */
    public static function parse_file($file_path, $file_type)
    {
        switch (strtolower($file_type)) {
            case 'json':
                return self::parse_json($file_path);
            case 'csv':
                return self::parse_csv($file_path);
            case 'xml':
                return self::parse_xml($file_path);
            case 'txt':
                return self::parse_txt($file_path);
            default:
                return new WP_Error('invalid_file_type', __('Unsupported file type.', 'politeia-quiz-creator'));
        }
    }

    /**
     * Parse JSON file - returns questions array
     */
    private static function parse_json($file_path)
    {
        error_log('PQC: parse_json() called for file: ' . $file_path);

        $content = file_get_contents($file_path);
        if ($content === false) {
            error_log('PQC: Could not read file');
            return new WP_Error('file_read_error', __('Could not read file.', 'politeia-quiz-creator'));
        }

        error_log('PQC: File content length: ' . strlen($content));

        $data = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('PQC: JSON parse error: ' . json_last_error_msg());
            return new WP_Error('json_parse_error', __('Invalid JSON format: ', 'politeia-quiz-creator') . json_last_error_msg());
        }

        error_log('PQC: JSON decoded successfully. Type: ' . gettype($data));
        error_log('PQC: Data structure: ' . print_r(array_keys($data), true));

        // If data is already an array of questions, return it
        if (isset($data[0]) && isset($data[0]['question_text'])) {
            error_log('PQC: Found array of questions. Count: ' . count($data));
            return self::normalize_questions($data);
        }

        // If data has 'questions' key (old format), extract it
        if (isset($data['questions'])) {
            error_log('PQC: Found questions key. Count: ' . count($data['questions']));
            return self::normalize_questions($data['questions']);
        }

        // Otherwise assume the whole data is a single question
        error_log('PQC: Treating as single question');
        return self::normalize_questions([$data]);
    }

    /**
     * Parse CSV file - returns questions array
     * Format: title, question_text, answer_type, points, answer1, correct1, answer2, correct2, ...
     */
    private static function parse_csv($file_path)
    {
        $handle = fopen($file_path, 'r');
        if ($handle === false) {
            return new WP_Error('file_read_error', __('Could not read file.', 'politeia-quiz-creator'));
        }

        $questions = [];
        $row_num = 0;

        while (($row = fgetcsv($handle)) !== false) {
            $row_num++;

            // Skip empty rows
            if (empty(array_filter($row))) {
                continue;
            }

            // Parse question row
            $question = [
                'title' => $row[0] ?? "Question {$row_num}",
                'question_text' => $row[1] ?? '',
                'answer_type' => $row[2] ?? 'single',
                'points' => intval($row[3] ?? 5),
                'answers' => []
            ];

            // Parse answers (starting from column 4)
            // Format: answer_text, is_correct, answer_text, is_correct, ...
            for ($i = 4; $i < count($row); $i += 2) {
                if (!empty($row[$i])) {
                    $question['answers'][] = [
                        'text' => $row[$i],
                        'correct' => !empty($row[$i + 1]) && strtolower($row[$i + 1]) === 'true',
                        'points' => 0
                    ];
                }
            }

            if (!empty($question['answers'])) {
                $questions[] = $question;
            }
        }

        fclose($handle);

        return self::normalize_questions($questions);
    }

    /**
     * Parse XML file - returns questions array
     */
    private static function parse_xml($file_path)
    {
        $content = file_get_contents($file_path);
        if ($content === false) {
            return new WP_Error('file_read_error', __('Could not read file.', 'politeia-quiz-creator'));
        }

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($content);

        if ($xml === false) {
            $errors = libxml_get_errors();
            libxml_clear_errors();
            return new WP_Error('xml_parse_error', __('Invalid XML format.', 'politeia-quiz-creator'));
        }

        $questions = [];

        // Parse questions
        if (isset($xml->question)) {
            foreach ($xml->question as $q) {
                $question = [
                    'title' => (string) ($q->title ?? ''),
                    'question_text' => (string) ($q->text ?? ''),
                    'answer_type' => (string) ($q->answer_type ?? 'single'),
                    'points' => intval($q->points ?? 5),
                    'answers' => []
                ];

                if (isset($q->answers->answer)) {
                    foreach ($q->answers->answer as $a) {
                        $question['answers'][] = [
                            'text' => (string) $a->text,
                            'correct' => strtolower((string) ($a->correct ?? 'false')) === 'true',
                            'points' => intval($a->points ?? 0)
                        ];
                    }
                }

                $questions[] = $question;
            }
        }

        return self::normalize_questions($questions);
    }

    /**
     * Parse TXT file - returns questions array
     * Simple format:
     * Q: Question text
     * A: Answer (correct)
     * A: Answer
     * Q: Next question
     * ...
     */
    private static function parse_txt($file_path)
    {
        $content = file_get_contents($file_path);
        if ($content === false) {
            return new WP_Error('file_read_error', __('Could not read file.', 'politeia-quiz-creator'));
        }

        $lines = explode("\n", $content);
        $questions = [];
        $current_question = null;

        foreach ($lines as $line) {
            $line = trim($line);

            // Skip empty lines
            if (empty($line)) {
                continue;
            }

            // Question
            if (preg_match('/^Q:\s*(.+)$/i', $line, $matches)) {
                // Save previous question if exists
                if ($current_question !== null && !empty($current_question['answers'])) {
                    $questions[] = $current_question;
                }

                // Start new question
                $current_question = [
                    'title' => trim($matches[1]),
                    'question_text' => trim($matches[1]),
                    'answer_type' => 'single',
                    'points' => 5,
                    'answers' => []
                ];
                continue;
            }

            // Answer
            if (preg_match('/^A:\s*(.+?)(\s*\(correct\))?$/i', $line, $matches)) {
                if ($current_question !== null) {
                    $current_question['answers'][] = [
                        'text' => trim($matches[1]),
                        'correct' => !empty($matches[2]),
                        'points' => 0
                    ];
                }
                continue;
            }
        }

        // Save last question
        if ($current_question !== null && !empty($current_question['answers'])) {
            $questions[] = $current_question;
        }

        return self::normalize_questions($questions);
    }

    /**
     * Normalize and validate questions array
     */
    private static function normalize_questions($questions)
    {
        if (!is_array($questions)) {
            return [];
        }

        // Normalize each question
        foreach ($questions as &$question) {
            if (!isset($question['title'])) {
                $question['title'] = $question['question_text'] ?? 'Untitled Question';
            }

            if (!isset($question['question_text'])) {
                $question['question_text'] = $question['title'];
            }

            if (!isset($question['answer_type'])) {
                $question['answer_type'] = 'single';
            }

            if (!isset($question['points'])) {
                $question['points'] = 5;
            }

            if (!isset($question['answers'])) {
                $question['answers'] = [];
            }
        }

        return $questions;
    }

    /**
     * Get sample data for each format (questions only)
     */
    public static function get_sample_data($format)
    {
        switch (strtolower($format)) {
            case 'json':
                return self::get_json_sample();
            case 'csv':
                return self::get_csv_sample();
            case 'xml':
                return self::get_xml_sample();
            case 'txt':
                return self::get_txt_sample();
            default:
                return '';
        }
    }

    private static function get_json_sample()
    {
        return json_encode([
            [
                'title' => 'Who is the father of political science?',
                'question_text' => 'Who is considered the founder of political science?',
                'answer_type' => 'single',
                'points' => 5,
                'answers' => [
                    ['text' => 'Aristotle', 'correct' => true, 'points' => 0],
                    ['text' => 'Plato', 'correct' => false, 'points' => 0],
                    ['text' => 'Socrates', 'correct' => false, 'points' => 0]
                ]
            ],
            [
                'title' => 'What is democracy?',
                'question_text' => 'Democracy is best defined as:',
                'answer_type' => 'single',
                'points' => 5,
                'answers' => [
                    ['text' => 'Rule by the people', 'correct' => true, 'points' => 0],
                    ['text' => 'Rule by the wealthy', 'correct' => false, 'points' => 0],
                    ['text' => 'Rule by one person', 'correct' => false, 'points' => 0]
                ]
            ]
        ], JSON_PRETTY_PRINT);
    }

    private static function get_csv_sample()
    {
        return "Question 1,Who is considered the founder of political science?,single,5,Aristotle,true,Plato,false,Socrates,false\nQuestion 2,Democracy is best defined as:,single,5,Rule by the people,true,Rule by the wealthy,false,Rule by one person,false\n";
    }

    private static function get_xml_sample()
    {
        return '<?xml version="1.0" encoding="UTF-8"?>
<questions>
    <question>
        <title>Who is the father of political science?</title>
        <text>Who is considered the founder of political science?</text>
        <answer_type>single</answer_type>
        <points>5</points>
        <answers>
            <answer>
                <text>Aristotle</text>
                <correct>true</correct>
            </answer>
            <answer>
                <text>Plato</text>
                <correct>false</correct>
            </answer>
        </answers>
    </question>
    <question>
        <title>What is democracy?</title>
        <text>Democracy is best defined as:</text>
        <answer_type>single</answer_type>
        <points>5</points>
        <answers>
            <answer>
                <text>Rule by the people</text>
                <correct>true</correct>
            </answer>
            <answer>
                <text>Rule by the wealthy</text>
                <correct>false</correct>
            </answer>
        </answers>
    </question>
</questions>';
    }

    private static function get_txt_sample()
    {
        return "Q: Who is the father of political science?
A: Aristotle (correct)
A: Plato
A: Socrates

Q: What is democracy?
A: Rule by the people (correct)
A: Rule by one person
A: Rule by the wealthy";
    }
}
