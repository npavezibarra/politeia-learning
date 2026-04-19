<?php
if (!defined('ABSPATH')) {
    exit;
}

$locale = determine_locale();
$lang = strtolower(substr((string) $locale, 0, 2));
$logo_url = 'http://nupoliteia.local/wp-content/uploads/2026/04/Captura-de-pantalla-2026-04-05-a-las-12.29.54-p.m.png';
$pl_email_header_title = __('MODERACIÓN COMENTARIO', 'politeia-learning');
$pl_email_document_title = (string) __('Comment moderation', 'politeia-learning');

include PL_PATH . 'templates/emails/partials/unified-shell-top.php';
include PL_PATH . 'templates/emails/partials/unified-shell-bottom.php';

