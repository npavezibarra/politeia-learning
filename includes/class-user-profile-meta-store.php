<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Unified user profile meta store for Politeia Learning.
 *
 * Table name is "{$wpdb->prefix}politeia_user_profile_meta" (commonly "wp_politeia_user_profile_meta").
 */
class PL_User_Profile_Meta_Store
{
    private const TABLE_SLUG = 'politeia_user_profile_meta';
    private const CACHE_GROUP = 'pl_user_profile_meta';

    private static function table_name(): string
    {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_SLUG;
    }

    private static function normalize_context(string $context): string
    {
        $context = sanitize_key($context);
        return $context !== '' ? $context : 'default';
    }

    private static function normalize_key(string $meta_key): string
    {
        return sanitize_key($meta_key);
    }

    private static function cache_key(int $user_id, string $context): string
    {
        return $user_id . ':' . $context;
    }

    private static function invalidate_cache(int $user_id, string $context): void
    {
        wp_cache_delete(self::cache_key($user_id, $context), self::CACHE_GROUP);
    }

    /**
     * Get a single meta value (decoded to PHP types).
     */
    public static function get(int $user_id, string $context, string $meta_key, mixed $default = null): mixed
    {
        $context = self::normalize_context($context);
        $meta_key = self::normalize_key($meta_key);
        if ($user_id <= 0 || $meta_key === '') {
            return $default;
        }

        $all = self::get_context($user_id, $context);
        return array_key_exists($meta_key, $all) ? $all[$meta_key] : $default;
    }

    /**
     * Get all meta for a context as [meta_key => value].
     *
     * Values are returned as native PHP values (array for json, int/float/bool for typed values).
     */
    public static function get_context(int $user_id, string $context): array
    {
        $context = self::normalize_context($context);
        if ($user_id <= 0) {
            return [];
        }

        $ck = self::cache_key($user_id, $context);
        $cached = wp_cache_get($ck, self::CACHE_GROUP);
        if (is_array($cached)) {
            return $cached;
        }

        global $wpdb;
        $table = self::table_name();

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT meta_key, value_type, value_string, value_json, value_int, value_float, value_bool, value_date, value_datetime
                 FROM {$table}
                 WHERE user_id = %d AND context = %s",
                $user_id,
                $context
            ),
            ARRAY_A
        );

        $out = [];
        if (is_array($rows)) {
            foreach ($rows as $r) {
                $k = (string) ($r['meta_key'] ?? '');
                if ($k === '') {
                    continue;
                }
                $out[$k] = self::decode_row_value($r);
            }
        }

        wp_cache_set($ck, $out, self::CACHE_GROUP);
        return $out;
    }

    /**
     * Upsert a single meta value.
     *
     * @param array{type?:string} $opts
     */
    public static function set(int $user_id, string $context, string $meta_key, mixed $value, array $opts = []): bool
    {
        $context = self::normalize_context($context);
        $meta_key = self::normalize_key($meta_key);
        if ($user_id <= 0 || $meta_key === '') {
            return false;
        }

        $type = isset($opts['type']) ? (string) $opts['type'] : '';
        [$value_type, $columns, $hash] = self::encode_value($value, $type);

        global $wpdb;
        $table = self::table_name();

        $data = array_merge(
            [
                'user_id' => $user_id,
                'context' => $context,
                'meta_key' => $meta_key,
                'value_type' => $value_type,
                'value_hash' => $hash,
            ],
            $columns
        );

        // Ensure all value columns exist (dbDelta can be picky about schema diffs; we keep inserts explicit).
        $value_cols = [
            'value_string' => null,
            'value_json' => null,
            'value_int' => null,
            'value_float' => null,
            'value_bool' => null,
            'value_date' => null,
            'value_datetime' => null,
        ];
        $data = array_merge($value_cols, $data);

        $cols = array_keys($data);
        $insert_cols = implode(',', $cols);

        // Build a query that preserves NULLs (wpdb->prepare would otherwise coerce null to '').
        $insert_placeholders = [];
        $args = [];
        foreach ($cols as $c) {
            $v = $data[$c];
            if ($v === null) {
                $insert_placeholders[] = 'NULL';
                continue;
            }

            $ph = match ($c) {
                'user_id', 'value_int', 'value_bool' => '%d',
                'value_float' => '%f',
                default => '%s',
            };
            $insert_placeholders[] = $ph;
            $args[] = $v;
        }
        $insert_placeholder = implode(',', $insert_placeholders);

        $update_parts = [];
        foreach ($cols as $c) {
            if (in_array($c, ['user_id', 'context', 'meta_key', 'created_at', 'updated_at'], true)) {
                continue;
            }
            $update_parts[] = "{$c} = VALUES({$c})";
        }
        $update_sql = implode(', ', $update_parts);

        $query = "INSERT INTO {$table} ({$insert_cols}) VALUES ({$insert_placeholder})
                  ON DUPLICATE KEY UPDATE {$update_sql}";

        $prepared = $wpdb->prepare($query, $args);
        $res = $prepared ? $wpdb->query($prepared) : false;
        self::invalidate_cache($user_id, $context);

        return $res !== false;
    }

    public static function delete(int $user_id, string $context, string $meta_key): bool
    {
        $context = self::normalize_context($context);
        $meta_key = self::normalize_key($meta_key);
        if ($user_id <= 0 || $meta_key === '') {
            return false;
        }

        global $wpdb;
        $table = self::table_name();
        $res = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table} WHERE user_id = %d AND context = %s AND meta_key = %s",
            $user_id,
            $context,
            $meta_key
        ));

        self::invalidate_cache($user_id, $context);
        return $res !== false;
    }

    private static function decode_row_value(array $r): mixed
    {
        $t = (string) ($r['value_type'] ?? 'string');
        return match ($t) {
            'int' => isset($r['value_int']) ? (int) $r['value_int'] : 0,
            'float' => isset($r['value_float']) ? (float) $r['value_float'] : 0.0,
            'bool' => isset($r['value_bool']) ? ((int) $r['value_bool'] === 1) : false,
            'date' => (string) ($r['value_date'] ?? ''),
            'datetime' => (string) ($r['value_datetime'] ?? ''),
            'json' => self::safe_json_decode((string) ($r['value_json'] ?? '')),
            default => (string) ($r['value_string'] ?? ''),
        };
    }

    private static function safe_json_decode(string $raw): mixed
    {
        if ($raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @return array{0:string,1:array<string,mixed>,2:string|null} [value_type, value_columns, value_hash]
     */
    private static function encode_value(mixed $value, string $force_type = ''): array
    {
        $force_type = sanitize_key($force_type);

        $value_type = $force_type;
        if ($value_type === '') {
            if (is_bool($value)) {
                $value_type = 'bool';
            } elseif (is_int($value)) {
                $value_type = 'int';
            } elseif (is_float($value)) {
                $value_type = 'float';
            } elseif (is_array($value) || is_object($value)) {
                $value_type = 'json';
            } else {
                $value_type = 'string';
            }
        }

        $cols = [];
        $canonical = '';

        switch ($value_type) {
            case 'bool': {
                $b = (bool) $value;
                $cols['value_bool'] = $b ? 1 : 0;
                $canonical = $b ? '1' : '0';
                break;
            }
            case 'int': {
                $i = (int) $value;
                $cols['value_int'] = $i;
                $canonical = (string) $i;
                break;
            }
            case 'float': {
                $f = (float) $value;
                $cols['value_float'] = $f;
                $canonical = rtrim(rtrim(number_format($f, 6, '.', ''), '0'), '.');
                if ($canonical === '') {
                    $canonical = '0';
                }
                break;
            }
            case 'date': {
                $d = self::normalize_date($value);
                $cols['value_date'] = $d;
                $canonical = $d;
                break;
            }
            case 'datetime': {
                $dt = self::normalize_datetime($value);
                $cols['value_datetime'] = $dt;
                $canonical = $dt;
                break;
            }
            case 'json': {
                $sanitized = self::sanitize_json_like($value);
                $json = wp_json_encode($sanitized);
                $cols['value_json'] = $json;
                $canonical = $json ?: '[]';
                break;
            }
            default: {
                $s = is_scalar($value) ? (string) $value : '';
                $s = sanitize_text_field($s);
                $cols['value_string'] = $s;
                $canonical = $s;
                $value_type = 'string';
                break;
            }
        }

        $hash = $canonical !== '' ? hash('sha256', $value_type . ':' . $canonical) : null;

        return [$value_type, $cols, $hash];
    }

    private static function normalize_date(mixed $value): string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }
        $s = sanitize_text_field((string) $value);
        $tz = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC');
        $dt = DateTimeImmutable::createFromFormat('Y-m-d', $s, $tz);
        if ($dt instanceof DateTimeImmutable) {
            return $dt->format('Y-m-d');
        }
        $ts = strtotime($s);
        if ($ts !== false) {
            return (new DateTimeImmutable('@' . $ts))->setTimezone($tz)->format('Y-m-d');
        }
        return '';
    }

    private static function normalize_datetime(mixed $value): string
    {
        $tz = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC');
        if ($value instanceof DateTimeInterface) {
            return (new DateTimeImmutable($value->format('c')))->setTimezone($tz)->format('Y-m-d H:i:s');
        }
        $s = sanitize_text_field((string) $value);
        if ($s === '') {
            return '';
        }
        $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $s, $tz);
        if ($dt instanceof DateTimeImmutable) {
            return $dt->format('Y-m-d H:i:s');
        }
        $ts = strtotime($s);
        if ($ts !== false) {
            return (new DateTimeImmutable('@' . $ts))->setTimezone($tz)->format('Y-m-d H:i:s');
        }
        return '';
    }

    private static function sanitize_json_like(mixed $value): mixed
    {
        if (is_array($value)) {
            $out = [];
            foreach ($value as $k => $v) {
                $safe_key = is_int($k) ? $k : sanitize_key((string) $k);
                $out[$safe_key] = self::sanitize_json_like($v);
            }
            return $out;
        }
        if (is_object($value)) {
            return self::sanitize_json_like((array) $value);
        }
        if (is_bool($value) || is_int($value) || is_float($value) || $value === null) {
            return $value;
        }
        return sanitize_text_field((string) $value);
    }
}
