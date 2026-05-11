<?php
/**
 * Email SMTP module bootstrap.
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/includes/class-pl-email-smtp.php';
require_once __DIR__ . '/includes/class-pl-email-smtp-admin.php';

if (class_exists('PL_Email_SMTP')) {
    PL_Email_SMTP::init();
}

if (is_admin() && class_exists('PL_Email_SMTP_Admin')) {
    PL_Email_SMTP_Admin::init();
}

