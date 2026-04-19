<?php
/**
 * Email: Learni partner invitation (received by invitee).
 *
 * Available variables:
 * - $invitee_name (string)
 * - $inviter_name (string)
 * - $course_name  (string)
 * - $accept_url   (string)
 */

if (!defined('ABSPATH')) {
    exit;
}

$invitee_name = isset($invitee_name) ? (string) $invitee_name : '';
$inviter_name = isset($inviter_name) ? (string) $inviter_name : 'Politeia';
$course_name = isset($course_name) ? (string) $course_name : '';
$accept_url = isset($accept_url) ? (string) $accept_url : '';

$locale = determine_locale();
$lang = strtolower(substr((string) $locale, 0, 2));
$logo_url = 'http://nupoliteia.local/wp-content/uploads/2026/04/Captura-de-pantalla-2026-04-05-a-las-12.29.54-p.m.png';
$pl_email_header_title = __('INVITACIÓN PARTNER', 'politeia-learning');
$pl_email_document_title = (string) __('Invitación al Curso - Politeia', 'politeia-learning');
?>
<?php include PL_PATH . 'templates/emails/partials/unified-shell-top.php'; ?>
<?php include PL_PATH . 'templates/emails/partials/course-partner-invite-body.php'; ?>
<?php include PL_PATH . 'templates/emails/partials/unified-shell-bottom.php'; ?>

