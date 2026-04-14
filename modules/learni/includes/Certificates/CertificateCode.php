<?php

namespace Learni\Certificates;

/**
 * Handles the generation and validation of signed certificate verification codes.
 * Mirrors the logic found in the Learni plugin.
 */
final class CertificateCode
{
    private const VERSION = 1;

    /**
     * Encodes a payload into a signed base64url string.
     *
     * @param array<string,mixed> $payload
     * @return string
     */
    public static function encode(array $payload): string
    {
        $payload['v'] = self::VERSION;

        $json = wp_json_encode($payload);
        if (!is_string($json) || $json === '') {
            return '';
        }

        $body = self::base64url_encode($json);
        if ($body === '') {
            return '';
        }

        // Use wp_salt('auth') for a stable site-specific secret.
        $mac = hash_hmac('sha256', $body, (string) wp_salt('auth'), true);
        $sig = self::base64url_encode($mac);

        return $body . '.' . $sig;
    }

    /**
     * Decodes and verifies a signed code.
     *
     * @param string $code
     * @return array<string,mixed>|null
     */
    public static function decode(string $code): ?array
    {
        $code = trim($code);
        if (strlen($code) > 4096) {
            return null;
        }
        if ($code === '' || strpos($code, '.') === false) {
            return null;
        }

        $parts = explode('.', $code, 2);
        if (count($parts) !== 2) {
            return null;
        }

        $body = trim((string) $parts[0]);
        $sig  = trim((string) $parts[1]);
        if ($body === '' || $sig === '') {
            return null;
        }

        $expected = self::base64url_encode(hash_hmac('sha256', $body, (string) wp_salt('auth'), true));
        if ($expected === '' || !hash_equals($expected, $sig)) {
            return null;
        }

        $json = self::base64url_decode($body);
        if ($json === '') {
            return null;
        }

        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return null;
        }

        $v = isset($decoded['v']) ? (int) $decoded['v'] : 0;
        if ($v !== self::VERSION) {
            return null;
        }

        return $decoded;
    }

    private static function base64url_encode(string $raw): string
    {
        $enc = base64_encode($raw);
        if (!is_string($enc) || $enc === '') {
            return '';
        }
        return rtrim(strtr($enc, '+/', '-_'), '=');
    }

    private static function base64url_decode(string $enc): string
    {
        $enc = trim($enc);
        if ($enc === '') {
            return '';
        }

        $padded = strtr($enc, '-_', '+/');
        $pad = strlen($padded) % 4;
        if ($pad !== 0) {
            $padded .= str_repeat('=', 4 - $pad);
        }

        $raw = base64_decode($padded, true);
        return is_string($raw) ? $raw : '';
    }
}
