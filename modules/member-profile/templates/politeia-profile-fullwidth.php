<?php
/**
 * Full-width variant for the public profile template.
 *
 * This file intentionally delegates to `politeia-profile.php` to avoid duplicating
 * the full template. The included template will adapt layout based on the
 * `$pl_profile_layout` variable.
 */

if (!defined('ABSPATH')) {
    exit;
}

$pl_profile_layout = 'fullwidth';
require __DIR__ . '/politeia-profile.php';

