<?php
/**
 * WP-CLI commands for Politeia Learning partnerships.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('WP_CLI') || !WP_CLI) {
    return;
}

class PL_CLI_Partnerships
{
    /**
     * Normalize legacy role_slug / partnership role values into a stable key.
     */
    private static function normalize_role(string $role): string
    {
        $key = trim($role);
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
            'author' => 'author',
            'collaborator' => 'collaborator',
        ];

        return $map[$key] ?? 'collaborator';
    }

    public static function register(): void
    {
        \WP_CLI::add_command('politeia partnerships:verify', [__CLASS__, 'verify']);
    }

    /**
     * Verify parity between legacy wp_politeia_course_roles and unified partnerships.
     *
     * ## OPTIONS
     *
     * [--sample=<n>]
     * : Number of missing/orphan sample rows to print. Default: 20.
     *
     * ## EXAMPLES
     *
     *     wp politeia partnerships:verify
     *     wp politeia partnerships:verify --sample=50
     */
    public static function verify(array $args, array $assoc_args): void
    {
        global $wpdb;
        if (!$wpdb) {
            \WP_CLI::error('No $wpdb available.');
        }

        $sample = isset($assoc_args['sample']) ? (int) $assoc_args['sample'] : 20;
        if ($sample < 0) {
            $sample = 0;
        }

        $roles_table = $wpdb->prefix . 'politeia_course_roles';
        $partnerships_table = $wpdb->prefix . 'politeia_user_object_partnerships';
        $types = ['course', 'group', 'program'];

        $roles_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$roles_table}");
        $partnerships_count = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*)
                 FROM {$partnerships_table}
                 WHERE object_type IN (%s, %s, %s)",
                $types[0],
                $types[1],
                $types[2]
            )
        );

        \WP_CLI::line(sprintf('✓ course_roles rows: %d', $roles_count));
        \WP_CLI::line(sprintf('✓ partnerships rows: %d', $partnerships_count));

        $roles_rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT object_type, object_id, user_id, role_slug
                 FROM {$roles_table}
                 WHERE object_type IN (%s, %s, %s)",
                $types[0],
                $types[1],
                $types[2]
            ),
            ARRAY_A
        );

        $partnership_rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT object_type, object_id, partner_user_id, role, status
                 FROM {$partnerships_table}
                 WHERE object_type IN (%s, %s, %s)",
                $types[0],
                $types[1],
                $types[2]
            ),
            ARRAY_A
        );

        $legacy_keys = [];
        foreach ((array) $roles_rows as $r) {
            if (!is_array($r)) {
                continue;
            }
            $object_type = (string) ($r['object_type'] ?? '');
            $object_id = (int) ($r['object_id'] ?? 0);
            $user_id = (int) ($r['user_id'] ?? 0);
            if ($object_type === '' || $object_id <= 0 || $user_id <= 0) {
                continue;
            }
            $role_norm = self::normalize_role((string) ($r['role_slug'] ?? ''));
            $key = $object_type . '|' . $object_id . '|' . $user_id . '|' . $role_norm;
            $legacy_keys[$key] = [
                'object_type' => $object_type,
                'object_id' => $object_id,
                'user_id' => $user_id,
                'role_slug' => (string) ($r['role_slug'] ?? ''),
                'role_norm' => $role_norm,
            ];
        }

        $partnership_keys = [];
        foreach ((array) $partnership_rows as $r) {
            if (!is_array($r)) {
                continue;
            }
            $object_type = (string) ($r['object_type'] ?? '');
            $object_id = (int) ($r['object_id'] ?? 0);
            $user_id = (int) ($r['partner_user_id'] ?? 0);
            if ($object_type === '' || $object_id <= 0 || $user_id <= 0) {
                continue;
            }
            $role_norm = self::normalize_role((string) ($r['role'] ?? ''));
            $key = $object_type . '|' . $object_id . '|' . $user_id . '|' . $role_norm;
            $partnership_keys[$key] = [
                'object_type' => $object_type,
                'object_id' => $object_id,
                'user_id' => $user_id,
                'role' => (string) ($r['role'] ?? ''),
                'role_norm' => $role_norm,
                'status' => (string) ($r['status'] ?? ''),
            ];
        }

        $missing = array_diff_key($legacy_keys, $partnership_keys);
        $orphans = array_diff_key($partnership_keys, $legacy_keys);

        \WP_CLI::line(sprintf('✓ missing partnerships: %d', count($missing)));
        if ($sample > 0 && !empty($missing)) {
            \WP_CLI::line('  Sample missing (legacy → partnerships):');
            $i = 0;
            foreach ($missing as $row) {
                \WP_CLI::line(sprintf(
                    '  - %s %d user=%d role_slug="%s" (norm=%s)',
                    $row['object_type'],
                    $row['object_id'],
                    $row['user_id'],
                    $row['role_slug'],
                    $row['role_norm']
                ));
                $i++;
                if ($i >= $sample) {
                    break;
                }
            }
        }

        \WP_CLI::line(sprintf('✓ orphan partnerships: %d', count($orphans)));
        if ($sample > 0 && !empty($orphans)) {
            \WP_CLI::line('  Sample orphans (partnerships → legacy):');
            $i = 0;
            foreach ($orphans as $row) {
                \WP_CLI::line(sprintf(
                    '  - %s %d user=%d role="%s" status=%s (norm=%s)',
                    $row['object_type'],
                    $row['object_id'],
                    $row['user_id'],
                    $row['role'],
                    $row['status'] !== '' ? $row['status'] : 'NULL',
                    $row['role_norm']
                ));
                $i++;
                if ($i >= $sample) {
                    break;
                }
            }
        }

        if (empty($missing) && empty($orphans)) {
            \WP_CLI::success('Partnerships are in sync with legacy course roles.');
        } else {
            \WP_CLI::warning('Partnerships are NOT fully in sync with legacy course roles.');
        }
    }
}

PL_CLI_Partnerships::register();

