<?php
/**
 * Email-safe chart helpers (rendered as images).
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('pl_quickchart_doughnut_url')) {
    function pl_quickchart_doughnut_url(int $value, string $label = '', string $primary_color = '#8A6B1E'): string
    {
        unset($primary_color);

        $value = max(0, min(100, (int) $value));
        $rest = 100 - $value;

        $config = [
            'type' => 'doughnut',
            'data' => [
                'datasets' => [
                    [
                        'data' => [$value, $rest],
                        // Use a single solid color (the middle gradient stop) for better email rendering.
                        'backgroundColor' => ['#EDBC07', '#eeeeee'],
                        'borderWidth' => 0,
                    ],
                ],
            ],
            'options' => [
                'cutoutPercentage' => 75,
                'legend' => ['display' => false],
                'plugins' => [
                    'datalabels' => ['display' => false],
                    'doughnutlabel' => [
                        'labels' => [
                            [
                                'text' => $value . '%',
                                'font' => [
                                    'size' => 40,
                                    'family' => 'sans-serif',
                                    'weight' => 'bold',
                                ],
                                'color' => '#000000',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        return 'https://quickchart.io/chart?c=' . urlencode((string) wp_json_encode($config));
    }
}
