<?php
/**
 * Trait for utility and permission helpers in Course Creator.
 */

if (!defined('ABSPATH'))
    exit;

trait PL_CC_Utils_Trait
{
    private function normalize_partnership_role(string $role_slug): string
    {
        $key = trim($role_slug);
        if ($key === '') {
            return 'collaborator';
        }

        if (function_exists('remove_accents')) {
            $key = remove_accents($key);
        }

        $key = strtolower($key);

        $map = [
            'autor principal' => 'author',
            'editor' => 'editor',
            'teacher' => 'teacher',
            'profesor' => 'teacher',
        ];

        return $map[$key] ?? 'collaborator';
    }

    private function denormalize_partnership_role_slug(string $role_value): string
    {
        $key = trim($role_value);
        if ($key === '') {
            return __('Colaborador', 'politeia-learning');
        }

        $raw_lower = $key;
        if (function_exists('remove_accents')) {
            $raw_lower = remove_accents($raw_lower);
        }
        $raw_lower = strtolower($raw_lower);

        $legacy_map = [
            'autor principal' => __('Autor principal', 'politeia-learning'),
            'editor' => __('Editor', 'politeia-learning'),
            'profesor' => __('Profesor', 'politeia-learning'),
            'teacher' => __('Teacher', 'politeia-learning'),
        ];
        if (isset($legacy_map[$raw_lower])) {
            return $legacy_map[$raw_lower];
        }

        $map = [
            'author' => __('Autor principal', 'politeia-learning'),
            'editor' => __('Editor', 'politeia-learning'),
            'teacher' => __('Teacher', 'politeia-learning'),
            'collaborator' => __('Colaborador', 'politeia-learning'),
        ];

        return $map[strtolower($key)] ?? __('Colaborador', 'politeia-learning');
    }

    private function user_can_manage_group(int $group_id, int $user_id): bool
    {
        if ($group_id <= 0 || $user_id <= 0) {
            return false;
        }

        if (current_user_can('manage_options')) {
            return true;
        }

        $author_id = (int) get_post_field('post_author', $group_id);
        return $author_id === $user_id;
    }

    private function user_can_manage_programa(int $programa_id, int $user_id): bool
    {
        if ($programa_id <= 0 || $user_id <= 0) {
            return false;
        }

        if (current_user_can('manage_options')) {
            return true;
        }

        $author_id = (int) get_post_field('post_author', $programa_id);
        return $author_id === $user_id;
    }

    private function normalize_term_id_list($raw): array
    {
        if (!is_array($raw)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map('absint', $raw))));
    }
}
