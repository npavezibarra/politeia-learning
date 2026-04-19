<?php
/**
 * Unified email shell (top).
 *
 * Expected variables:
 * - $pl_email_document_title (string) <title> content.
 * - $pl_email_header_title   (string) Header title (left).
 * - $logo_url                (string) Logo URL.
 * - $lang                    (string) 2-letter language (optional).
 */

if (!defined('ABSPATH')) {
    exit;
}

$pl_email_document_title = isset($pl_email_document_title) ? (string) $pl_email_document_title : '';
$lang = isset($lang) ? (string) $lang : 'en';
?>
<!doctype html>
<html lang="<?php echo esc_attr($lang !== '' ? $lang : 'en'); ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo esc_html($pl_email_document_title); ?></title>
    <style>
        body {
            margin: 0;
            padding: 0;
            width: 100% !important;
            -webkit-text-size-adjust: 100%;
            -ms-text-size-adjust: 100%;
        }
        img {
            line-height: 100%;
            text-decoration: none;
            border: 0;
            outline: none;
        }
        table { border-collapse: collapse !important; }
        .poppins {
            font-family: 'Poppins', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif !important;
        }
        .btn:hover { background-color: #333333 !important; }
        a { text-decoration: none !important; }
    </style>
</head>
<body style="margin:0;padding:0;background-color:#ffffff;font-family:Arial,sans-serif;">
    <center style="width:100%;background-color:#ffffff;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#ffffff" style="background-color:#ffffff;">
            <tr>
                <td align="center" style="padding:20px 15px;">
                    <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:600px;background-color:#ffffff;border-radius:0px;overflow:hidden;">
                        <?php include PL_PATH . 'templates/emails/partials/unified-header.php'; ?>

