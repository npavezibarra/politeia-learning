<?php

namespace Learni\QuizEditor;

use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Parses different file formats (JSON, CSV, XML, TXT) into questions array.
 */
final class FileParser
{
    /**
     * Parse uploaded file.
     */
    public static function parse(string $file_path, string $file_type)
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
                return new WP_Error('invalid_file_type', __('Unsupported file type.', 'politeia-learning'));
        }
    }

    private static function parse_json(string $file_path)
    {
        $content = file_get_contents($file_path);
        if ($content === false) {
            return new WP_Error('file_read_error', __('Could not read file.', 'politeia-learning'));
        }

        $data = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('json_parse_error', __('Invalid JSON format: ', 'politeia-learning') . json_last_error_msg());
        }

        if (isset($data[0]) && isset($data[0]['question_text'])) {
            return self::normalize_questions($data);
        }

        if (isset($data['questions'])) {
            return self::normalize_questions($data['questions']);
        }

        return self::normalize_questions([$data]);
    }

    private static function parse_csv(string $file_path)
    {
        $handle = fopen($file_path, 'r');
        if ($handle === false) {
            return new WP_Error('file_read_error', __('Could not read file.', 'politeia-learning'));
        }

        $questions = [];
        $row_num = 0;

        while (($row = fgetcsv($handle)) !== false) {
            $row_num++;
            if (empty(array_filter($row))) continue;

            $question = [
                'title' => $row[0] ?? "Question {$row_num}",
                'question_text' => $row[1] ?? '',
                'answer_type' => $row[2] ?? 'single',
                'points' => intval($row[3] ?? 5),
                'answers' => []
            ];

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

    private static function parse_xml(string $file_path)
    {
        $content = file_get_contents($file_path);
        if ($content === false) {
            return new WP_Error('file_read_error', __('Could not read file.', 'politeia-learning'));
        }

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($content);

        if ($xml === false) {
            libxml_clear_errors();
            return new WP_Error('xml_parse_error', __('Invalid XML format.', 'politeia-learning'));
        }

        $questions = [];
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

    private static function parse_txt(string $file_path)
    {
        $content = file_get_contents($file_path);
        if ($content === false) {
            return new WP_Error('file_read_error', __('Could not read file.', 'politeia-learning'));
        }

        $lines = explode("\n", $content);
        $questions = [];
        $current_question = null;

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            if (preg_match('/^Q:\s*(.+)$/i', $line, $matches)) {
                if ($current_question !== null && !empty($current_question['answers'])) {
                    $questions[] = $current_question;
                }
                $current_question = [
                    'title' => trim($matches[1]),
                    'question_text' => trim($matches[1]),
                    'answer_type' => 'single',
                    'points' => 5,
                    'answers' => []
                ];
                continue;
            }

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

        if ($current_question !== null && !empty($current_question['answers'])) {
            $questions[] = $current_question;
        }

        return self::normalize_questions($questions);
    }

    private static function normalize_questions(array $questions): array
    {
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

    public static function get_sample_data(string $format): string
    {
        switch (strtolower($format)) {
            case 'json':
                return json_encode([
                    [
                        'title' => 'Sample Question',
                        'question_text' => 'Who is considered the founder of political science?',
                        'answer_type' => 'single',
                        'points' => 5,
                        'answers' => [
                            ['text' => 'Aristotle', 'correct' => true],
                            ['text' => 'Plato', 'correct' => false]
                        ]
                    ]
                ], JSON_PRETTY_PRINT);
            case 'csv':
                return "Question 1,Who is considered the founder of political science?,single,5,Aristotle,true,Plato,false\n";
            case 'xml':
                return '<?xml version="1.0" encoding="UTF-8"?><questions><question><title>Sample</title><text>Who?</text><answer_type>single</answer_type><points>5</points><answers><answer><text>Aristotle</text><correct>true</correct></answer></answers></question></questions>';
            case 'txt':
                return "Q: Who is the father of political science?\nA: Aristotle (correct)\nA: Plato";
            default:
                return '';
        }
    }
}
