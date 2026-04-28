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
        \WP_CLI::add_command('politeia partnerships:backfill-reading-plans', [__CLASS__, 'backfill_reading_plans']);
        \WP_CLI::add_command('politeia partnerships:backfill-reading-plan-invites', [__CLASS__, 'backfill_reading_plan_invites']);
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

    /**
     * Backfill reading_plan observers from legacy plan_participants into unified partnerships.
     *
     * ## OPTIONS
     *
     * [--batch-size=<n>]
     * : Batch size for scanning plan participants. Default: 500.
     *
     * [--max-batches=<n>]
     * : Max number of batches to process (0 = all). Default: 0.
     *
     * [--dry-run]
     * : Don't write anything; only count rows. Default: false.
     *
     * ## EXAMPLES
     *
     *     wp politeia partnerships:backfill-reading-plans
     *     wp politeia partnerships:backfill-reading-plans --batch-size=200 --max-batches=5
     *     wp politeia partnerships:backfill-reading-plans --dry-run
     */
    public static function backfill_reading_plans(array $args, array $assoc_args): void
    {
        $batch_size = isset($assoc_args['batch-size']) ? (int) $assoc_args['batch-size'] : 500;
        $max_batches = isset($assoc_args['max-batches']) ? (int) $assoc_args['max-batches'] : 0;
        $dry_run = isset($assoc_args['dry-run']);

        if (!class_exists('PL_Partnership_Backfill') || !method_exists('PL_Partnership_Backfill', 'backfill_reading_plan_observers')) {
            \WP_CLI::error('Backfill class not available.');
        }

        \WP_CLI::line(sprintf('Running backfill (batch_size=%d, max_batches=%d, dry_run=%s)...', $batch_size, $max_batches, $dry_run ? 'true' : 'false'));
        $stats = PL_Partnership_Backfill::backfill_reading_plan_observers($batch_size, $max_batches, $dry_run);

        \WP_CLI::line(sprintf('✓ batches: %d', (int) ($stats['batches'] ?? 0)));
        \WP_CLI::line(sprintf('✓ rows scanned: %d', (int) ($stats['rows'] ?? 0)));
        \WP_CLI::line(sprintf('✓ activated: %d', (int) ($stats['activated'] ?? 0)));
        \WP_CLI::line(sprintf('✓ skipped: %d', (int) ($stats['skipped'] ?? 0)));
        \WP_CLI::line(sprintf('✓ errors: %d', (int) ($stats['errors'] ?? 0)));

        if ($dry_run) {
            \WP_CLI::success('Dry run complete.');
        } elseif ((int) ($stats['errors'] ?? 0) > 0) {
            \WP_CLI::warning('Backfill completed with errors.');
        } else {
            \WP_CLI::success('Backfill complete.');
        }
    }

    /**
     * Backfill pending reading_plan invites from legacy invites into unified partnerships.
     *
     * ## OPTIONS
     *
     * [--batch-size=<n>]
     * : Batch size for scanning legacy invites. Default: 500.
     *
     * [--max-batches=<n>]
     * : Max number of batches to process (0 = all). Default: 0.
     *
     * [--dry-run]
     * : Don't write anything; only count rows. Default: false.
     *
     * ## EXAMPLES
     *
     *     wp politeia partnerships:backfill-reading-plan-invites
     *     wp politeia partnerships:backfill-reading-plan-invites --batch-size=200 --max-batches=5
     *     wp politeia partnerships:backfill-reading-plan-invites --dry-run
     */
    public static function backfill_reading_plan_invites(array $args, array $assoc_args): void
    {
        $batch_size = isset($assoc_args['batch-size']) ? (int) $assoc_args['batch-size'] : 500;
        $max_batches = isset($assoc_args['max-batches']) ? (int) $assoc_args['max-batches'] : 0;
        $dry_run = isset($assoc_args['dry-run']);

        if (!class_exists('PL_Partnership_Backfill') || !method_exists('PL_Partnership_Backfill', 'backfill_reading_plan_pending_invites')) {
            \WP_CLI::error('Backfill class not available.');
        }

        \WP_CLI::line(sprintf('Running invites backfill (batch_size=%d, max_batches=%d, dry_run=%s)...', $batch_size, $max_batches, $dry_run ? 'true' : 'false'));
        $stats = PL_Partnership_Backfill::backfill_reading_plan_pending_invites($batch_size, $max_batches, $dry_run);

        \WP_CLI::line(sprintf('✓ batches: %d', (int) ($stats['batches'] ?? 0)));
        \WP_CLI::line(sprintf('✓ rows scanned: %d', (int) ($stats['rows'] ?? 0)));
        \WP_CLI::line(sprintf('✓ upserted: %d', (int) ($stats['upserted'] ?? 0)));
        \WP_CLI::line(sprintf('✓ skipped: %d', (int) ($stats['skipped'] ?? 0)));
        \WP_CLI::line(sprintf('✓ errors: %d', (int) ($stats['errors'] ?? 0)));

        if ($dry_run) {
            \WP_CLI::success('Dry run complete.');
        } elseif ((int) ($stats['errors'] ?? 0) > 0) {
            \WP_CLI::warning('Backfill completed with errors.');
        } else {
            \WP_CLI::success('Backfill complete.');
        }
    }
}

PL_CLI_Partnerships::register();
