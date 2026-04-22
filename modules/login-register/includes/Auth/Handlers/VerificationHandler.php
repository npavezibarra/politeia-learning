<?php

namespace Learni\Auth\Handlers;

use Learni\Auth\Utilities\AuthUtils;
use PL_Email;
use WP_Error;
use WP_User;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles email verification and confirmation tokens.
 */
class VerificationHandler
{
    public const VERIFIED_META = 'pl_auth_email_verified';
    public const TOKEN_HASH_META = 'pl_auth_verification_token_hash';
    public const TOKEN_EXPIRES_META = 'pl_auth_verification_token_expires';

    /**
     * Confirms a user's email with a token.
     */
    public static function confirm_user_email(string $email, string $token)
    {
        if ($email === '' || !is_email($email) || $token === '') {
            return new WP_Error('invalid_token', __('The confirmation link is incomplete.', 'politeia-learning'));
        }

        $user = get_user_by('email', $email);
        if (!($user instanceof WP_User)) {
            return new WP_Error('invalid_token', __('We could not find that account.', 'politeia-learning'));
        }

        $stored_hash = (string) get_user_meta($user->ID, self::TOKEN_HASH_META, true);
        $stored_expires = (int) get_user_meta($user->ID, self::TOKEN_EXPIRES_META, true);
        
        if ($stored_hash === '' || $stored_expires < time()) {
            return new WP_Error('token_expired', __('This confirmation token has expired. Please register again or request a new link.', 'politeia-learning'));
        }

        $provided_hash = hash_hmac('sha256', $token, wp_salt('auth'));
        if (!hash_equals($stored_hash, $provided_hash)) {
            return new WP_Error('invalid_token', __('The confirmation token is invalid.', 'politeia-learning'));
        }

        update_user_meta($user->ID, self::VERIFIED_META, 1);
        delete_user_meta($user->ID, self::TOKEN_HASH_META);
        delete_user_meta($user->ID, self::TOKEN_EXPIRES_META);

        return true;
    }

    /**
     * Sends a verification email to the user.
     */
    public static function send_confirmation(int $user_id, string $email, string $display_name, string $redirect_to, string $token): void
    {
        $verification_url = add_query_arg([
            'pl_auth_action' => 'confirm',
            'email' => $email,
            'token' => $token,
            'redirect_to' => $redirect_to,
        ], home_url('/'));

        $switched_locale = switch_to_user_locale($user_id);
        
        if (class_exists('PL_Email')) {
            PL_Email::send_auth_confirmation($email, $display_name, $verification_url, $token);
        }
        
        if ($switched_locale) {
            restore_previous_locale();
        }
    }

    /**
     * Issues a new confirmation token for a user.
     */
    public static function issue_token(int $user_id): string
    {
        $token = bin2hex(random_bytes(32));
        $hash = hash_hmac('sha256', $token, wp_salt('auth'));
        
        update_user_meta($user_id, self::VERIFIED_META, 0);
        update_user_meta($user_id, self::TOKEN_HASH_META, $hash);
        update_user_meta($user_id, self::TOKEN_EXPIRES_META, time() + DAY_IN_SECONDS * 2);

        return $token;
    }

    /**
     * Checks if a user requires verification.
     */
    public static function requires_verification(int $user_id): bool
    {
        return get_user_meta($user_id, self::VERIFIED_META, true) !== '' || 
               get_user_meta($user_id, self::TOKEN_HASH_META, true) !== '';
    }

    /**
     * Checks if a user is already verified.
     */
    public static function is_verified(int $user_id): bool
    {
        return (string) get_user_meta($user_id, self::VERIFIED_META, true) === '1';
    }
}
